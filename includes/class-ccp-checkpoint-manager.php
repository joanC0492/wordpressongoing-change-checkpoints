<?php
/**
 * Checkpoint management class for Change Checkpoints
 *
 * @package Change_Checkpoints
 */

// Prevent direct access
if (!defined('ABSPATH')) {
  exit;
}

/**
 * CCP_Checkpoint_Manager class
 */
class CCP_Checkpoint_Manager
{

  /**
   * Database instance
   */
  private $database;

  /**
   * Constructor
   */
  public function __construct()
  {
    // Database will be set after instantiation
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
   * Check if there's an active checkpoint
   */
  public function has_active_checkpoint()
  {
    $active_checkpoint = $this->get_active_checkpoint();
    return !empty($active_checkpoint);
  }

  /**
   * Get the active checkpoint
   */
  public function get_active_checkpoint()
  {
    return $this->get_database()->get_active_checkpoint();
  }

  /**
   * Create a new checkpoint
   */
  public function create_checkpoint($title = null, $note = null)
  {
    // Close any existing active checkpoint first
    $active_checkpoint = $this->get_active_checkpoint();
    if ($active_checkpoint) {
      $this->close_checkpoint($active_checkpoint->id);
    }

    // Create the new checkpoint
    $checkpoint_id = $this->get_database()->create_checkpoint($title, $note);

    if ($checkpoint_id) {
      do_action('ccp_checkpoint_created', $checkpoint_id);
      return $checkpoint_id;
    }

    return false;
  }

  /**
   * Close a checkpoint
   */
  public function close_checkpoint($checkpoint_id)
  {
    $result = $this->get_database()->close_checkpoint($checkpoint_id);

    if ($result) {
      do_action('ccp_checkpoint_closed', $checkpoint_id);
    }

    return $result;
  }

  /**
   * Delete a checkpoint
   */
  public function delete_checkpoint($checkpoint_id)
  {
    // Check permissions
    if (!current_user_can('manage_options')) {
      return false;
    }

    $result = $this->get_database()->delete_checkpoint($checkpoint_id);

    if ($result) {
      do_action('ccp_checkpoint_deleted', $checkpoint_id);
    }

    return $result;
  }

  /**
   * Delete all checkpoints
   */
  public function delete_all_checkpoints()
  {
    // Check permissions
    if (!current_user_can('manage_options')) {
      return false;
    }

    $result = $this->get_database()->delete_all_checkpoints();

    if ($result) {
      do_action('ccp_all_checkpoints_deleted');
    }

    return $result;
  }

  /**
   * Get a specific checkpoint
   */
  public function get_checkpoint($checkpoint_id)
  {
    return $this->get_database()->get_checkpoint($checkpoint_id);
  }

  /**
   * Get checkpoints with pagination and search
   */
  public function get_checkpoints($args = array())
  {
    $checkpoints = $this->get_database()->get_checkpoints($args);

    // Add events count to each checkpoint
    foreach ($checkpoints as &$checkpoint) {
      $checkpoint->events_count = $this->get_database()->get_checkpoint_events_count($checkpoint->id);
    }

    return $checkpoints;
  }

  /**
   * Get checkpoints count
   */
  public function get_checkpoints_count($args = array())
  {
    return $this->get_database()->get_checkpoints_count($args);
  }

  /**
   * Get events for a checkpoint
   */
  public function get_checkpoint_events($checkpoint_id)
  {
    return $this->get_database()->get_checkpoint_events($checkpoint_id);
  }

  /**
   * Get formatted events for display
   */
  public function get_formatted_checkpoint_events($checkpoint_id)
  {
    $events = $this->get_checkpoint_events($checkpoint_id);

    if (empty($events)) {
      return array();
    }

    $formatted_events = array();
    $grouped_events = array();

    // Group events by object type
    foreach ($events as $event) {
      $group_key = $this->get_event_group_key($event);
      if (!isset($grouped_events[$group_key])) {
        $grouped_events[$group_key] = array();
      }
      $grouped_events[$group_key][] = $event;
    }

    // Format each group
    $group_order = array('page', 'post');

    // Add custom post types to the order
    $post_types = get_post_types(array('public' => true, '_builtin' => false), 'objects');
    foreach ($post_types as $post_type) {
      $group_order[] = $post_type->name;
    }

    // Add taxonomies
    $group_order[] = 'category';
    $group_order[] = 'post_tag';

    $taxonomies = get_taxonomies(array('public' => true, '_builtin' => false), 'objects');
    foreach ($taxonomies as $taxonomy) {
      $group_order[] = $taxonomy->name;
    }

    foreach ($group_order as $group_key) {
      if (isset($grouped_events[$group_key])) {
        $formatted_events[$group_key] = array(
          'label' => $this->get_group_label($group_key),
          'events' => array_map(array($this, 'format_event'), $grouped_events[$group_key])
        );
      }
    }

    return $formatted_events;
  }

  /**
   * Get event group key
   */
  private function get_event_group_key($event)
  {
    if ($event->object_kind === 'post') {
      return $event->object_subtype;
    } elseif ($event->object_kind === 'term') {
      return $event->object_subtype;
    }

    return 'other';
  }

  /**
   * Get group label for display
   */
  private function get_group_label($group_key)
  {
    switch ($group_key) {
      case 'page':
        return __('Pages', 'change-checkpoints');
      case 'post':
        return __('Posts', 'change-checkpoints');
      case 'category':
        return __('Categories', 'change-checkpoints');
      case 'post_tag':
        return __('Tags', 'change-checkpoints');
      default:
        // Try to get post type or taxonomy label
        $post_type = get_post_type_object($group_key);
        if ($post_type) {
          return $post_type->labels->name;
        }

        $taxonomy = get_taxonomy($group_key);
        if ($taxonomy) {
          return $taxonomy->labels->name;
        }

        return ucfirst(str_replace('_', ' ', $group_key));
    }
  }

  /**
   * Format a single event for display
   */
  private function format_event($event)
  {
    $details = json_decode($event->details_json, true);
    $author = get_userdata($event->author_id);
    $author_name = $author ? $author->display_name : __('Unknown', 'change-checkpoints');

    // Format timestamp to local time
    $local_time = wp_date('H:i', strtotime($event->timestamp));

    // Get action label
    $action_label = $this->get_action_label($event->action);

    // Get object type label
    $object_type_label = $this->get_object_type_label($event->object_kind, $event->object_subtype);

    // Format details
    $details_text = $this->format_event_details($details);

    return array(
      'time' => $local_time,
      'object_type' => $object_type_label,
      'action' => $action_label,
      'object_name' => $event->object_name,
      'author' => $author_name,
      'details' => $details_text,
      'raw' => $event
    );
  }

  /**
   * Get action label
   */
  private function get_action_label($action)
  {
    switch ($action) {
      case 'create':
        return __('create', 'change-checkpoints');
      case 'update':
        return __('update', 'change-checkpoints');
      case 'delete':
        return __('delete', 'change-checkpoints');
      default:
        return $action;
    }
  }

  /**
   * Get object type label
   */
  private function get_object_type_label($object_kind, $object_subtype)
  {
    if ($object_kind === 'post') {
      switch ($object_subtype) {
        case 'page':
          return __('Page', 'change-checkpoints');
        case 'post':
          return __('Post', 'change-checkpoints');
        default:
          $post_type = get_post_type_object($object_subtype);
          return $post_type ? $post_type->labels->singular_name : ucfirst($object_subtype);
      }
    } elseif ($object_kind === 'term') {
      switch ($object_subtype) {
        case 'category':
          return __('Category', 'change-checkpoints');
        case 'post_tag':
          return __('Tag', 'change-checkpoints');
        default:
          $taxonomy = get_taxonomy($object_subtype);
          return $taxonomy ? $taxonomy->labels->singular_name : ucfirst($object_subtype);
      }
    }

    return ucfirst($object_kind);
  }

  /**
   * Format event details
   */
  private function format_event_details($details)
  {
    if (empty($details) || !is_array($details)) {
      return '';
    }

    $formatted_details = array();

    foreach ($details as $key => $value) {
      switch ($key) {
        case 'status_from':
        case 'status_to':
          if (isset($details['status_from']) && isset($details['status_to'])) {
            $formatted_details[] = sprintf(
              __('status: %s → %s', 'change-checkpoints'),
              $details['status_from'],
              $details['status_to']
            );
            unset($details['status_to']); // Prevent duplicate
          }
          break;
        case 'title_changed':
          if ($value) {
            $formatted_details[] = __('title updated', 'change-checkpoints');
          }
          break;
        case 'template_changed':
          if ($value) {
            $formatted_details[] = __('template changed', 'change-checkpoints');
          }
          break;
        case 'parent_changed':
          if ($value) {
            $formatted_details[] = __('parent changed', 'change-checkpoints');
          }
          break;
        case 'thumbnail_changed':
          if ($value) {
            $formatted_details[] = __('featured image changed', 'change-checkpoints');
          }
          break;
        case 'name_changed':
          if ($value) {
            $formatted_details[] = __('name changed', 'change-checkpoints');
          }
          break;
        case 'slug_changed':
          if ($value) {
            $formatted_details[] = __('slug changed', 'change-checkpoints');
          }
          break;
      }
    }

    return implode('; ', $formatted_details);
  }
}