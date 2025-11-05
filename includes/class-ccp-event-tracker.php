<?php
/**
 * Event tracking class for Change Checkpoints
 *
 * @package Change_Checkpoints
 */

// Prevent direct access
if (!defined('ABSPATH')) {
  exit;
}

/**
 * CCP_Event_Tracker class
 */
class CCP_Event_Tracker
{

  /**
   * Database instance
   */
  private $database;

  /**
   * Post types to exclude from tracking
   */
  private $excluded_post_types = array(
    'revision',
    'nav_menu_item',
    'customize_changeset',
    'oembed_cache',
    'user_request',
    'wp_block',
    'wp_template',
    'wp_template_part',
    'wp_global_styles',
    'wp_navigation'
  );

  /**
   * Constructor
   */
  public function __construct()
  {
    // Initialize hooks immediately since we always track now
    $this->init_hooks();
  }

  /**
   * Get database instance
   */
  private function get_database()
  {
    if (!$this->database) {
      $this->database = ccp()->database;
    }
    return $this->database;
  }

  /**
   * Initialize tracking hooks
   */
  public function init_hooks()
  {
    // Post tracking hooks
    add_action('save_post', array($this, 'track_post_save'), 10, 3);
    add_action('transition_post_status', array($this, 'track_post_status_change'), 10, 3);
    add_action('before_delete_post', array($this, 'track_post_delete'), 10, 2);
    
    // Meta tracking hooks for specific fields
    add_action('updated_postmeta', array($this, 'track_post_meta_update'), 10, 4);

    // Term tracking hooks
    add_action('created_term', array($this, 'track_term_create'), 10, 3);
    add_action('edited_term', array($this, 'track_term_edit'), 10, 3);
    add_action('delete_term', array($this, 'track_term_delete'), 10, 4);
  }

  /**
   * Track post save events
   */
  public function track_post_save($post_id, $post, $update)
  {
    // Skip autosaves and revisions
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
      return;
    }

    // Skip excluded post types
    if (in_array($post->post_type, $this->excluded_post_types)) {
      return;
    }

    // Skip if this is not a public post type
    $post_type_object = get_post_type_object($post->post_type);
    if (!$post_type_object || !$post_type_object->public) {
      return;
    }

    // Check if we should track this request
    if (!$this->should_track_request()) {
      return;
    }

    // Determine action
    $action = $update ? 'update' : 'create';

    // Get previous post data for comparison if this is an update
    $details = array();
    if ($update) {
      $details = $this->get_post_change_details($post_id, $post);
    }

    // Track the event
    $this->track_event(
      'post',
      $post->post_type,
      $post_id,
      $post->post_title,
      $action,
      $details
    );
  }

  /**
   * Track post status changes
   */
  public function track_post_status_change($new_status, $old_status, $post)
  {
    // Skip if status didn't actually change
    if ($new_status === $old_status) {
      return;
    }

    // Skip autosaves and revisions
    if (wp_is_post_autosave($post->ID) || wp_is_post_revision($post->ID)) {
      return;
    }

    // Skip excluded post types
    if (in_array($post->post_type, $this->excluded_post_types)) {
      return;
    }

    // Check if we should track this request
    if (!$this->should_track_request()) {
      return;
    }

    $details = array(
      'status_from' => $old_status,
      'status_to' => $new_status
    );

    // Track the event
    $this->track_event(
      'post',
      $post->post_type,
      $post->ID,
      $post->post_title,
      'update',
      $details
    );
  }

  /**
   * Track post deletion
   */
  public function track_post_delete($post_id, $post)
  {
    // Skip excluded post types
    if (in_array($post->post_type, $this->excluded_post_types)) {
      return;
    }

    // Check if we should track this request
    if (!$this->should_track_request()) {
      return;
    }

    // Track the event
    $this->track_event(
      'post',
      $post->post_type,
      $post_id,
      $post->post_title,
      'delete'
    );
  }

  /**
   * Track specific post meta updates
   */
  public function track_post_meta_update($meta_id, $post_id, $meta_key, $meta_value)
  {
    // Only track specific meta keys
    $tracked_meta_keys = array(
      '_thumbnail_id',
      '_wp_page_template',
      'post_parent'
    );

    if (!in_array($meta_key, $tracked_meta_keys)) {
      return;
    }

    $post = get_post($post_id);
    if (!$post || in_array($post->post_type, $this->excluded_post_types)) {
      return;
    }

    // Check if we should track this request
    if (!$this->should_track_request()) {
      return;
    }

    $details = array();
    switch ($meta_key) {
      case '_thumbnail_id':
        $details['thumbnail_changed'] = true;
        break;
      case '_wp_page_template':
        $details['template_changed'] = true;
        break;
      case 'post_parent':
        $details['parent_changed'] = true;
        break;
    }

    // Track the event
    $this->track_event(
      'post',
      $post->post_type,
      $post_id,
      $post->post_title,
      'update',
      $details
    );
  }

  /**
   * Track term creation
   */
  public function track_term_create($term_id, $tt_id, $taxonomy)
  {
    // Skip if not a public taxonomy
    $taxonomy_object = get_taxonomy($taxonomy);
    if (!$taxonomy_object || !$taxonomy_object->public) {
      return;
    }

    // Check if we should track this request
    if (!$this->should_track_request()) {
      return;
    }

    $term = get_term($term_id, $taxonomy);
    if (is_wp_error($term)) {
      return;
    }

    // Track the event
    $this->track_event(
      'term',
      $taxonomy,
      $term_id,
      $term->name,
      'create'
    );
  }

  /**
   * Track term editing
   */
  public function track_term_edit($term_id, $tt_id, $taxonomy)
  {
    // Skip if not a public taxonomy
    $taxonomy_object = get_taxonomy($taxonomy);
    if (!$taxonomy_object || !$taxonomy_object->public) {
      return;
    }

    // Check if we should track this request
    if (!$this->should_track_request()) {
      return;
    }

    $term = get_term($term_id, $taxonomy);
    if (is_wp_error($term)) {
      return;
    }

    // Get change details
    $details = $this->get_term_change_details($term_id, $taxonomy);

    // Track the event
    $this->track_event(
      'term',
      $taxonomy,
      $term_id,
      $term->name,
      'update',
      $details
    );
  }

  /**
   * Track term deletion
   */
  public function track_term_delete($term_id, $tt_id, $taxonomy, $deleted_term)
  {
    // Skip if not a public taxonomy
    $taxonomy_object = get_taxonomy($taxonomy);
    if (!$taxonomy_object || !$taxonomy_object->public) {
      return;
    }

    // Check if we should track this request
    if (!$this->should_track_request()) {
      return;
    }

    // Track the event
    $this->track_event(
      'term',
      $taxonomy,
      $term_id,
      $deleted_term->name,
      'delete'
    );
  }

  /**
   * Track an event
   */
  private function track_event($object_kind, $object_subtype, $object_id, $object_name, $action, $details = null)
  {
    return $this->get_database()->add_event(
      $object_kind,
      $object_subtype,
      $object_id,
      $object_name,
      $action,
      $details
    );
  }

  /**
   * Get post change details
   */
  private function get_post_change_details($post_id, $post)
  {
    $details = array();

    // We can't easily detect all changes without storing previous state
    // For now, we'll just track that an update occurred
    // In a more advanced implementation, we could store post data temporarily

    return $details;
  }

  /**
   * Get term change details
   */
  private function get_term_change_details($term_id, $taxonomy)
  {
    $details = array();

    // Similar to posts, we can't easily detect specific changes
    // without storing previous state

    return $details;
  }

  /**
   * Check if we should track this request
   */
  private function should_track_request()
  {
    // Skip AJAX requests from frontend
    if (wp_doing_ajax() && !is_admin()) {
      return false;
    }

    // Skip cron jobs
    if (wp_doing_cron()) {
      return false;
    }

    // Skip REST API requests (for now)
    if (defined('REST_REQUEST') && REST_REQUEST) {
      return false;
    }

    // Skip if this is an import operation
    if (defined('WP_IMPORTING') && WP_IMPORTING) {
      return false;
    }

    return true;
  }
}