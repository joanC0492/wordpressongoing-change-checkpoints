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
   * Media file types to track
   */
  private $tracked_media_types = array(
    'image',
    'video',
    'audio',
    'application'
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

    // Media tracking hooks
    add_action('add_attachment', array($this, 'track_media_upload'), 10, 1);
    add_action('edit_attachment', array($this, 'track_media_edit'), 10, 1);
    add_action('delete_attachment', array($this, 'track_media_delete'), 10, 1);
    add_action('updated_postmeta', array($this, 'track_media_meta_update'), 10, 4);

    // Theme tracking hooks
    add_action('switch_theme', array($this, 'track_theme_switch'), 10, 3);
    add_action('delete_theme', array($this, 'track_theme_delete'), 10, 1);

    // Plugin tracking hooks
    add_action('activated_plugin', array($this, 'track_plugin_activate'), 10, 2);
    add_action('deactivated_plugin', array($this, 'track_plugin_deactivate'), 10, 2);
    add_action('deleted_plugin', array($this, 'track_plugin_delete'), 10, 2);

    // User tracking hooks
    add_action('user_register', array($this, 'track_user_create'), 10, 2);
    add_action('profile_update', array($this, 'track_user_update'), 10, 3);
    add_action('delete_user', array($this, 'track_user_delete'), 10, 3);

    // Navigation menu tracking
    add_action('wp_create_nav_menu', array($this, 'track_nav_menu_create'), 10, 2);
    add_action('wp_update_nav_menu', array($this, 'track_nav_menu_update'), 10, 1);
    // Note: Using delete_term instead of wp_delete_nav_menu for better data access
    // add_action('wp_delete_nav_menu', array($this, 'track_nav_menu_delete'), 10, 1);
    add_action('wp_update_nav_menu_item', array($this, 'track_nav_menu_item_update'), 10, 3);
    
    // Track menu item deletions - special handling since they're nav_menu_item posts
    add_action('before_delete_post', array($this, 'track_nav_menu_item_delete'), 5, 2);
    
    // Track menu deletion via delete_term hook - provides better access to menu data
    add_action('delete_term', array($this, 'track_nav_menu_term_delete'), 10, 4);
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
   * Track media upload
   */
  public function track_media_upload($attachment_id)
  {
    // Check if we should track this request
    if (!$this->should_track_request()) {
      return;
    }

    $attachment = get_post($attachment_id);
    if (!$attachment || $attachment->post_type !== 'attachment') {
      return;
    }

    // Get media type and file info
    $file_path = get_attached_file($attachment_id);
    $file_url = wp_get_attachment_url($attachment_id);
    $file_type = wp_check_filetype($file_path);
    $media_type = $this->get_media_type_from_mime($file_type['type']);

    // Skip if not a tracked media type
    if (!$media_type) {
      return;
    }

    $details = array(
      'file_url' => $file_url,
      'file_name' => basename($file_path),
      'file_type' => $file_type['type'],
      'file_size' => filesize($file_path),
      'media_type' => $media_type
    );

    // Track the event
    $this->track_event(
      'media',
      $media_type,
      $attachment_id,
      $attachment->post_title ?: basename($file_path),
      'create',
      $details
    );
  }

  /**
   * Track media edit
   */
  public function track_media_edit($attachment_id)
  {
    // Check if we should track this request
    if (!$this->should_track_request()) {
      return;
    }

    $attachment = get_post($attachment_id);
    if (!$attachment || $attachment->post_type !== 'attachment') {
      return;
    }

    // Get media type
    $file_path = get_attached_file($attachment_id);
    $file_url = wp_get_attachment_url($attachment_id);
    $file_type = wp_check_filetype($file_path);
    $media_type = $this->get_media_type_from_mime($file_type['type']);

    // Skip if not a tracked media type
    if (!$media_type) {
      return;
    }

    $details = array(
      'file_url' => $file_url,
      'file_name' => basename($file_path),
      'file_type' => $file_type['type'],
      'media_type' => $media_type
    );

    // Track the event
    $this->track_event(
      'media',
      $media_type,
      $attachment_id,
      $attachment->post_title ?: basename($file_path),
      'update',
      $details
    );
  }

  /**
   * Track media deletion
   */
  public function track_media_delete($attachment_id)
  {
    // Check if we should track this request
    if (!$this->should_track_request()) {
      return;
    }

    $attachment = get_post($attachment_id);
    if (!$attachment || $attachment->post_type !== 'attachment') {
      return;
    }

    // Get media type before deletion
    $file_path = get_attached_file($attachment_id);
    $file_url = wp_get_attachment_url($attachment_id);
    $file_type = wp_check_filetype($file_path);
    $media_type = $this->get_media_type_from_mime($file_type['type']);

    // Skip if not a tracked media type
    if (!$media_type) {
      return;
    }

    $details = array(
      'file_url' => $file_url,
      'file_name' => basename($file_path),
      'file_type' => $file_type['type'],
      'media_type' => $media_type
    );

    // Track the event
    $this->track_event(
      'media',
      $media_type,
      $attachment_id,
      $attachment->post_title ?: basename($file_path),
      'delete',
      $details
    );
  }

  /**
   * Track media meta updates (alt text, caption, description)
   */
  public function track_media_meta_update($meta_id, $post_id, $meta_key, $meta_value)
  {
    // Only track specific media meta keys
    $tracked_meta_keys = array(
      '_wp_attachment_image_alt',
      '_wp_attachment_metadata'
    );

    if (!in_array($meta_key, $tracked_meta_keys)) {
      return;
    }

    $attachment = get_post($post_id);
    if (!$attachment || $attachment->post_type !== 'attachment') {
      return;
    }

    // Check if we should track this request
    if (!$this->should_track_request()) {
      return;
    }

    // Get media type
    $file_path = get_attached_file($post_id);
    $file_url = wp_get_attachment_url($post_id);
    $file_type = wp_check_filetype($file_path);
    $media_type = $this->get_media_type_from_mime($file_type['type']);

    // Skip if not a tracked media type
    if (!$media_type) {
      return;
    }

    $details = array(
      'file_url' => $file_url,
      'meta_key' => $meta_key,
      'file_name' => basename($file_path),
      'media_type' => $media_type
    );

    switch ($meta_key) {
      case '_wp_attachment_image_alt':
        $details['alt_text_changed'] = true;
        break;
      case '_wp_attachment_metadata':
        $details['metadata_changed'] = true;
        break;
    }

    // Track the event
    $this->track_event(
      'media',
      $media_type,
      $post_id,
      $attachment->post_title ?: basename($file_path),
      'update',
      $details
    );
  }

  /**
   * Get media type from MIME type
   */
  private function get_media_type_from_mime($mime_type)
  {
    if (!$mime_type) {
      return null;
    }

    $type_parts = explode('/', $mime_type);
    $main_type = $type_parts[0];

    if (in_array($main_type, $this->tracked_media_types)) {
      return $main_type;
    }

    return null;
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
   * Track theme switch/activation
   */
  public function track_theme_switch($new_name, $new_theme, $old_theme)
  {
    // Verificar si debemos rastrear esta solicitud
    if (!$this->should_track_request()) {
      return;
    }

    // Get the new theme name
    $theme_name = $new_theme ? $new_theme->get('Name') : $new_name;

    // Track the event with minimal details
    $this->track_event(
      'theme',
      'theme',
      0, // No specific ID for themes
      $theme_name,
      'activate',
      array() // No details to avoid confusion
    );
  }

  /**
   * Track theme deletion
   */
  public function track_theme_delete($stylesheet)
  {
    // Check if we should track this request
    if (!$this->should_track_request()) {
      return;
    }

    // Get theme info before deletion
    $theme = wp_get_theme($stylesheet);
    $theme_name = $theme->exists() ? $theme->get('Name') : $stylesheet;

    // Track the event with minimal details
    $this->track_event(
      'theme',
      'theme',
      0, // No specific ID for themes
      $theme_name,
      'delete',
      array() // No details to avoid confusion
    );
  }

  /**
   * Track plugin activation
   */
  public function track_plugin_activate($plugin, $network_wide)
  {
    // Debug: Log plugin activation
    error_log('CCP Debug - Plugin activated: ' . $plugin);

    // Check if we should track this request
    if (!$this->should_track_request()) {
      return;
    }

    // Get plugin data using WordPress function
    if (!function_exists('get_plugin_data')) {
      require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    
    $plugin_path = ABSPATH . 'wp-content/plugins/' . $plugin;
    $plugin_data = get_plugin_data($plugin_path);
    $plugin_name = !empty($plugin_data['Name']) ? $plugin_data['Name'] : basename($plugin, '.php');

    // Track the event
    $this->track_event(
      'plugin',
      'plugin',
      0, // No specific ID for plugins
      $plugin_name,
      'activate',
      array(
        'plugin_file' => $plugin,
        'network_wide' => $network_wide,
        'version' => !empty($plugin_data['Version']) ? $plugin_data['Version'] : ''
      )
    );
  }

  /**
   * Track plugin deactivation
   */
  public function track_plugin_deactivate($plugin, $network_deactivating)
  {
    // Check if we should track this request
    if (!$this->should_track_request()) {
      return;
    }

    // Get plugin data using WordPress function
    if (!function_exists('get_plugin_data')) {
      require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    
    $plugin_path = ABSPATH . 'wp-content/plugins/' . $plugin;
    $plugin_data = get_plugin_data($plugin_path);
    $plugin_name = !empty($plugin_data['Name']) ? $plugin_data['Name'] : basename($plugin, '.php');

    // Track the event
    $this->track_event(
      'plugin',
      'plugin',
      0, // No specific ID for plugins
      $plugin_name,
      'deactivate',
      array(
        'plugin_file' => $plugin,
        'network_deactivating' => $network_deactivating,
        'version' => !empty($plugin_data['Version']) ? $plugin_data['Version'] : ''
      )
    );
  }

  /**
   * Track plugin deletion
   */
  public function track_plugin_delete($plugin_file, $deleted)
  {
    // Check if we should track this request
    if (!$this->should_track_request()) {
      return;
    }

    // Only track successful deletions
    if (!$deleted) {
      return;
    }

    // Try to get plugin data - may not be available if already deleted
    $plugin_data = array();
    $plugin_path = ABSPATH . 'wp-content/plugins/' . $plugin_file;
    
    if (file_exists($plugin_path)) {
      if (!function_exists('get_plugin_data')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
      }
      $plugin_data = get_plugin_data($plugin_path);
    }
    
    $plugin_name = !empty($plugin_data['Name']) ? $plugin_data['Name'] : basename($plugin_file, '.php');

    // Track the event
    $this->track_event(
      'plugin',
      'plugin',
      0, // No specific ID for plugins
      $plugin_name,
      'delete',
      array(
        'plugin_file' => $plugin_file,
        'deleted' => $deleted,
        'version' => !empty($plugin_data['Version']) ? $plugin_data['Version'] : ''
      )
    );
  }

  /**
   * Track user creation
   */
  public function track_user_create($user_id, $userdata)
  {
    // Debug: Log user creation
    error_log('CCP Debug - User created: ID ' . $user_id);

    // Check if we should track this request
    if (!$this->should_track_request()) {
      return;
    }

    // Get user data
    $user = get_userdata($user_id);
    if (!$user) {
      return;
    }

    $username = $user->user_login;
    $user_roles = $user->roles;
    $primary_role = !empty($user_roles) ? $user_roles[0] : 'subscriber';

    // Get role display name
    global $wp_roles;
    $role_display_name = isset($wp_roles->roles[$primary_role]['name']) ? 
      $wp_roles->roles[$primary_role]['name'] : ucfirst($primary_role);

    // Track the event
    $this->track_event(
      'user',
      'user',
      $user_id,
      $username,
      'create',
      array(
        'user_email' => $user->user_email,
        'role' => $primary_role,
        'role_display_name' => $role_display_name,
        'display_name' => $user->display_name ?: $username
      )
    );
  }

  /**
   * Track user profile updates
   */
  public function track_user_update($user_id, $old_user_data, $userdata)
  {
    // Check if we should track this request
    if (!$this->should_track_request()) {
      return;
    }

    // Get current user data
    $user = get_userdata($user_id);
    if (!$user) {
      return;
    }

    $username = $user->user_login;
    $user_roles = $user->roles;
    $primary_role = !empty($user_roles) ? $user_roles[0] : 'subscriber';

    // Get role display name
    global $wp_roles;
    $role_display_name = isset($wp_roles->roles[$primary_role]['name']) ? 
      $wp_roles->roles[$primary_role]['name'] : ucfirst($primary_role);

    // Track specific changes
    $changes = array();
    
    // Check for role changes
    $old_roles = $old_user_data->roles;
    $old_primary_role = !empty($old_roles) ? $old_roles[0] : 'subscriber';
    if ($primary_role !== $old_primary_role) {
      $changes['role_changed'] = array(
        'from' => $old_primary_role,
        'to' => $primary_role
      );
    }

    // Check for email changes
    if ($user->user_email !== $old_user_data->user_email) {
      $changes['email_changed'] = true;
    }

    // Check for display name changes
    if ($user->display_name !== $old_user_data->display_name) {
      $changes['display_name_changed'] = true;
    }

    // Track the event
    $this->track_event(
      'user',
      'user',
      $user_id,
      $username,
      'update',
      array_merge(array(
        'user_email' => $user->user_email,
        'role' => $primary_role,
        'role_display_name' => $role_display_name,
        'display_name' => $user->display_name ?: $username
      ), $changes)
    );
  }

  /**
   * Track user deletion
   */
  public function track_user_delete($user_id, $reassign, $user)
  {
    // Check if we should track this request
    if (!$this->should_track_request()) {
      return;
    }

    // User object is provided by the hook
    if (!$user || !isset($user->user_login)) {
      return;
    }

    $username = $user->user_login;
    $user_roles = $user->roles;
    $primary_role = !empty($user_roles) ? $user_roles[0] : 'subscriber';

    // Get role display name
    global $wp_roles;
    $role_display_name = isset($wp_roles->roles[$primary_role]['name']) ? 
      $wp_roles->roles[$primary_role]['name'] : ucfirst($primary_role);

    // Track the event
    $this->track_event(
      'user',
      'user',
      $user_id,
      $username,
      'delete',
      array(
        'user_email' => $user->user_email,
        'role' => $primary_role,
        'role_display_name' => $role_display_name,
        'display_name' => $user->display_name ?: $username,
        'reassign_to' => $reassign
      )
    );
  }

  /**
   * Track navigation menu creation
   */
  public function track_nav_menu_create($menu_id, $menu_data)
  {
    // Check if we should track this request
    if (!$this->should_track_request()) {
      return;
    }

    $menu_name = isset($menu_data['menu-name']) ? $menu_data['menu-name'] : 'Menu';

    // Track the event
    $this->track_event(
      'menu',
      'nav_menu',
      $menu_id,
      $menu_name,
      'create',
      array(
        'menu_id' => $menu_id
      )
    );
  }

  /**
   * Track navigation menu updates
   */
  public function track_nav_menu_update($menu_id, $menu_data = array())
  {
    // Check if we should track this request
    if (!$this->should_track_request()) {
      return;
    }

    $menu_term = wp_get_nav_menu_object($menu_id);
    $menu_name = $menu_term ? $menu_term->name : 'Menu';

    // Get menu item count for context
    $menu_items = wp_get_nav_menu_items($menu_id);
    $item_count = is_array($menu_items) ? count($menu_items) : 0;

    // Track the event
    $this->track_event(
      'menu',
      'nav_menu',
      $menu_id,
      $menu_name,
      'update',
      array(
        'menu_id' => $menu_id,
        'item_count' => $item_count
      )
    );
  }

  /**
   * Track navigation menu deletion
   */
  public function track_nav_menu_delete($menu_term)
  {
    // Check if we should track this request
    if (!$this->should_track_request()) {
      return;
    }

    $menu_name = 'Menu';
    $menu_id = 0;

    // Handle different types of input from WordPress
    if (is_object($menu_term)) {
      // Standard WP_Term object
      if (isset($menu_term->name)) {
        $menu_name = $menu_term->name;
      }
      if (isset($menu_term->term_id)) {
        $menu_id = $menu_term->term_id;
      }
    } elseif (is_numeric($menu_term)) {
      // Sometimes WordPress passes just the menu ID
      $menu_obj = wp_get_nav_menu_object($menu_term);
      if ($menu_obj) {
        $menu_name = $menu_obj->name;
        $menu_id = $menu_obj->term_id;
      }
    } elseif (is_string($menu_term)) {
      // Sometimes it might pass the menu name/slug
      $menu_obj = wp_get_nav_menu_object($menu_term);
      if ($menu_obj) {
        $menu_name = $menu_obj->name;
        $menu_id = $menu_obj->term_id;
      } else {
        // Use the string as the name if we can't find the object
        $menu_name = $menu_term;
      }
    }

    // Track the event
    $this->track_event(
      'menu',
      'nav_menu',
      $menu_id,
      $menu_name,
      'delete',
      array(
        'menu_id' => $menu_id,
        'menu_name' => $menu_name,
        'method' => 'wp_delete_nav_menu'
      )
    );
  }

  /**
   * Track navigation menu item updates
   */
  public function track_nav_menu_item_update($menu_id, $menu_item_db_id, $args)
  {
    // Check if we should track this request
    if (!$this->should_track_request()) {
      return;
    }

    // Skip tracking individual item updates during bulk menu save
    // We'll catch the overall menu update instead
    if (isset($_POST['action']) && $_POST['action'] === 'update') {
      return;
    }

    $menu_term = wp_get_nav_menu_object($menu_id);
    $menu_name = $menu_term ? $menu_term->name : 'Menu';

    // Get menu item details - try multiple sources for the title
    $menu_item = get_post($menu_item_db_id);
    $item_title = '';

    // Try to get title from different sources in order of preference
    if (isset($args['menu-item-title']) && !empty($args['menu-item-title'])) {
      // Custom links and manually set titles
      $item_title = $args['menu-item-title'];
    } elseif ($menu_item && !empty($menu_item->post_title)) {
      // Existing menu item post title
      $item_title = $menu_item->post_title;
    } elseif (isset($args['menu-item-object-id']) && $args['menu-item-object-id']) {
      // Try to get title from the source object (page, post, category, etc.)
      $object_id = $args['menu-item-object-id'];
      $object_type = isset($args['menu-item-type']) ? $args['menu-item-type'] : '';
      
      if ($object_type === 'post_type') {
        // Get title from post/page
        $source_post = get_post($object_id);
        if ($source_post) {
          $item_title = $source_post->post_title;
        }
      } elseif ($object_type === 'taxonomy') {
        // Get title from category/tag/taxonomy term
        $source_term = get_term($object_id);
        if ($source_term && !is_wp_error($source_term)) {
          $item_title = $source_term->name;
        }
      }
    }

    // Fallback to generic name if we still don't have a title
    if (empty($item_title)) {
      $item_title = 'Menu Item';
    }

    // Determine the type of change
    $item_details = array(
      'menu_id' => $menu_id,
      'item_id' => $menu_item_db_id,
      'item_title' => $item_title,
      'item_type' => isset($args['menu-item-type']) ? $args['menu-item-type'] : 'unknown',
      'object_type' => isset($args['menu-item-object']) ? $args['menu-item-object'] : ''
    );

    // Check if this is a new item (no existing post) or update
    $action = $menu_item ? 'update' : 'create';

    // Track the event with the menu name and item info
    $this->track_event(
      'menu',
      'nav_menu_item',
      $menu_item_db_id,
      $menu_name . ' → ' . $item_title,
      $action,
      $item_details
    );
  }

  /**
   * Track navigation menu term deletion (alternative method)
   */
  public function track_nav_menu_term_delete($term_id, $tt_id, $taxonomy, $deleted_term)
  {
    // Only track nav_menu taxonomy deletions
    if ($taxonomy !== 'nav_menu') {
      return;
    }

    // Check if we should track this request
    if (!$this->should_track_request()) {
      return;
    }

    // Get menu name from the deleted term
    $menu_name = 'Menu';
    if (is_object($deleted_term) && isset($deleted_term->name)) {
      $menu_name = $deleted_term->name;
    }

    // Track the event
    $this->track_event(
      'menu',
      'nav_menu',
      $term_id,
      $menu_name,
      'delete',
      array(
        'menu_id' => $term_id,
        'menu_name' => $menu_name,
        'method' => 'delete_term'
      )
    );
  }

  /**
   * Track navigation menu item deletion
   */
  public function track_nav_menu_item_delete($post_id, $post)
  {
    // Only track nav_menu_item posts (which we normally exclude)
    if ($post->post_type !== 'nav_menu_item') {
      return;
    }

    // Check if we should track this request
    if (!$this->should_track_request()) {
      return;
    }

    // Get menu information before the item is deleted
    $menu_terms = wp_get_post_terms($post_id, 'nav_menu');
    $menu_id = 0;
    $menu_name = 'Menu';
    
    if (!is_wp_error($menu_terms) && !empty($menu_terms)) {
      $menu_id = $menu_terms[0]->term_id;
      $menu_name = $menu_terms[0]->name;
    }

    // Get the menu item title
    $item_title = $post->post_title ?: 'Menu Item';

    // Get additional details about the deleted item
    $menu_item_meta = get_post_meta($post_id);
    $item_type = isset($menu_item_meta['_menu_item_type'][0]) ? $menu_item_meta['_menu_item_type'][0] : 'unknown';
    $object_type = isset($menu_item_meta['_menu_item_object'][0]) ? $menu_item_meta['_menu_item_object'][0] : '';

    $item_details = array(
      'menu_id' => $menu_id,
      'item_id' => $post_id,
      'item_title' => $item_title,
      'item_type' => $item_type,
      'object_type' => $object_type
    );

    // Track the deletion event
    $this->track_event(
      'menu',
      'nav_menu_item',
      $post_id,
      $menu_name . ' → ' . $item_title,
      'delete',
      $item_details
    );
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