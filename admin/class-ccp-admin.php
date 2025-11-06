<?php
/**
 * Admin interface class for Change Checkpoints
 *
 * @package Change_Checkpoints
 */

// Prevent direct access
if (!defined('ABSPATH')) {
  exit;
}

/**
 * CCP_Admin class
 */
class CCP_Admin
{

  /**
   * Page hook suffixes
   */
  private $page_hooks = array();

  /**
   * Constructor
   */
  public function __construct()
  {
    add_action('admin_menu', array($this, 'add_admin_menu'));
    add_action('admin_init', array($this, 'handle_admin_actions'));
    add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

    // AJAX handlers
    add_action('wp_ajax_ccp_clear_all', array($this, 'ajax_clear_all'));
  }

  /**
   * Add admin menu
   */
  public function add_admin_menu()
  {
    // Main menu page
    $this->page_hooks['overview'] = add_menu_page(
      __('Change Tracker', 'change-checkpoints'),
      __('Change Tracker', 'change-checkpoints'),
      'manage_options',
      'change-checkpoints',
      array($this, 'display_overview_page'),
      'dashicons-backup',
      30
    );

    // Overview submenu (same as main page)
    $this->page_hooks['overview_sub'] = add_submenu_page(
      'change-checkpoints',
      __('Changes', 'change-checkpoints'),
      __('Changes', 'change-checkpoints'),
      'manage_options',
      'change-checkpoints',
      array($this, 'display_overview_page')
    );
  }

  /**
   * Handle admin actions
   */
  public function handle_admin_actions()
  {
    if (!current_user_can('manage_options')) {
      return;
    }

    // Check for nonce
    if (!isset($_REQUEST['_wpnonce'])) {
      return;
    }

    $action = isset($_REQUEST['action']) ? sanitize_text_field($_REQUEST['action']) : '';

    switch ($action) {
      case 'clear_all':
        if (wp_verify_nonce($_REQUEST['_wpnonce'], 'ccp_clear_all')) {
          $this->handle_clear_all();
        }
        break;
    }
  }

  /**
   * Enqueue admin assets
   */
  public function enqueue_admin_assets($hook_suffix)
  {
    // Only load on our plugin pages
    if (!in_array($hook_suffix, $this->page_hooks)) {
      return;
    }

    // Enqueue CSS
    wp_enqueue_style(
      'ccp-admin',
      CCP_PLUGIN_URL . 'assets/css/admin.css',
      array(),
      CCP_VERSION
    );

    // Enqueue JavaScript
    wp_enqueue_script(
      'ccp-admin',
      CCP_PLUGIN_URL . 'assets/js/admin.js',
      array(),
      CCP_VERSION,
      true
    );

    // Localize script
    wp_localize_script('ccp-admin', 'ccpAdmin', array(
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('ccp_admin_nonce'),
      'strings' => array(
        'confirmClearAll' => __('This will permanently delete all recorded changes. This action cannot be undone. Continue?', 'change-checkpoints'),
        'clearing' => __('Clearing...', 'change-checkpoints'),
        'error' => __('An error occurred. Please try again.', 'change-checkpoints'),
        'success' => __('Operation completed successfully.', 'change-checkpoints')
      )
    ));
  }

  /**
   * Display overview page
   */
  public function display_overview_page()
  {
    // Handle pagination
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 50;
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

    // Get events
    $args = array(
      'page' => $current_page,
      'per_page' => $per_page,
      'search' => $search
    );

    $events = $this->get_formatted_events($args);
    $total_events = ccp()->database->get_events_count($args);
    $total_pages = ceil($total_events / $per_page);

    // Include template
    include CCP_PLUGIN_DIR . 'admin/views/overview.php';
  }

  /**
   * Get formatted events for display
   */
  private function get_formatted_events($args)
  {
    $events = ccp()->database->get_events($args);
    $formatted_events = array();

    foreach ($events as $event) {
      $formatted_event = $this->format_event($event);
      if ($formatted_event) {
        $formatted_events[] = $formatted_event;
      }
    }

    return $formatted_events;
  }

  /**
   * Format a single event for display
   */
  private function format_event($event)
  {
    $formatted = new stdClass();
    $formatted->id = $event->id;
    $formatted->timestamp = $event->timestamp;
    $formatted->action = $event->action;
    $formatted->object_name = $event->object_name;
    $formatted->author_id = $event->author_id;
    
    // Initialize all type flags
    $formatted->is_media = false;
    $formatted->is_theme = false;
    $formatted->is_plugin = false;
    $formatted->is_user = false;
    $formatted->is_menu = false;
    $formatted->is_menu_item = false;
    $formatted->is_setting = false;

    // Set object type display name
    if ($event->object_kind === 'post') {
      $post_type_object = get_post_type_object($event->object_subtype);
      $formatted->object_type = $post_type_object ? $post_type_object->labels->singular_name : ucfirst($event->object_subtype);
      $formatted->is_user = false;
      $formatted->is_menu = false;
      $formatted->is_menu_item = false;
    } elseif ($event->object_kind === 'term') {
      $taxonomy_object = get_taxonomy($event->object_subtype);
      $formatted->object_type = $taxonomy_object ? $taxonomy_object->labels->singular_name : ucfirst($event->object_subtype);
    } elseif ($event->object_kind === 'media' || in_array($event->object_subtype, array('image', 'video', 'audio', 'application'))) {
      // Handle media types (both new format with object_kind='media' and legacy format without object_kind)
      switch ($event->object_subtype) {
        case 'image':
          $formatted->object_type = __('Image', 'change-checkpoints');
          $formatted->is_media = true;
          break;
        case 'video':
          $formatted->object_type = __('Video', 'change-checkpoints');
          $formatted->is_media = true;
          break;
        case 'audio':
          $formatted->object_type = __('Audio', 'change-checkpoints');
          $formatted->is_media = true;
          break;
        case 'application':
          $formatted->object_type = __('File', 'change-checkpoints');
          $formatted->is_media = true;
          break;
        default:
          $formatted->object_type = __('Media', 'change-checkpoints');
          $formatted->is_menu = false;
          $formatted->is_menu_item = false;
      }
    } elseif ($event->object_kind === 'theme') {
      $formatted->object_type = __('Theme', 'change-checkpoints');
      $formatted->is_theme = true;
    } elseif ($event->object_kind === 'plugin') {
      $formatted->object_type = __('Plugin', 'change-checkpoints');
      $formatted->is_plugin = true;
    } elseif ($event->object_kind === 'user') {
      $formatted->object_type = __('User', 'change-checkpoints');
      $formatted->is_user = true;
    } elseif ($event->object_kind === 'menu') {
      // Handle navigation menu types
      switch ($event->object_subtype) {
        case 'nav_menu':
          $formatted->object_type = __('Menu', 'change-checkpoints');
          $formatted->is_menu = true;
          break;
        case 'nav_menu_item':
          $formatted->object_type = __('Menu Item', 'change-checkpoints');
          $formatted->is_menu_item = true;
          break;
        default:
          $formatted->object_type = __('Menu', 'change-checkpoints');
          $formatted->is_menu = true;
      }
      $formatted->is_media = false;
      $formatted->is_theme = false;
      $formatted->is_plugin = false;
      $formatted->is_user = false;
    } elseif ($event->object_kind === 'setting') {
      // Handle WordPress Settings
      $page_names = array(
        'general' => __('General', 'change-checkpoints'),
        'writing' => __('Writing', 'change-checkpoints'),
        'reading' => __('Reading', 'change-checkpoints'),
        'discussion' => __('Discussion', 'change-checkpoints'),
        'media' => __('Media', 'change-checkpoints'),
        'permalink' => __('Permalinks', 'change-checkpoints')
      );
      
      $page_display = isset($page_names[$event->object_subtype]) ? $page_names[$event->object_subtype] : ucfirst($event->object_subtype);
      $formatted->object_type = sprintf(__('Settings (%s)', 'change-checkpoints'), $page_display);
      $formatted->is_setting = true;
    } else {
      $formatted->object_type = ucfirst($event->object_subtype);
    }

    // Set action display text
    switch ($event->action) {
      case 'create':
        $formatted->action_text = __('create', 'change-checkpoints');
        break;
      case 'update':
        $formatted->action_text = __('update', 'change-checkpoints');
        break;
      case 'delete':
        $formatted->action_text = __('delete', 'change-checkpoints');
        break;
      case 'activate':
        $formatted->action_text = __('activate', 'change-checkpoints');
        break;
      default:
        $formatted->action_text = $event->action;
    }

    // Parse details if available
    $formatted->details = array();
    if (!empty($event->details_json)) {
      $details = json_decode($event->details_json, true);
      if (is_array($details)) {
        $formatted->details = $details;
      }
    }

    // Get author name
    if ($event->author_id) {
      $author = get_userdata($event->author_id);
      $formatted->author_name = $author ? $author->display_name : __('Unknown', 'change-checkpoints');
    } else {
      $formatted->author_name = __('System', 'change-checkpoints');
    }

    return $formatted;
  }

  /**
   * Handle clear all events
   */
  private function handle_clear_all()
  {
    $result = ccp()->database->delete_all_events();

    if ($result) {
      $this->add_admin_notice(__('All changes have been cleared.', 'change-checkpoints'), 'success');
    } else {
      $this->add_admin_notice(__('Failed to clear changes.', 'change-checkpoints'), 'error');
    }

    wp_redirect(admin_url('admin.php?page=change-checkpoints'));
    exit;
  }

  /**
   * AJAX: Clear all events
   */
  public function ajax_clear_all()
  {
    check_ajax_referer('ccp_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_die(-1);
    }

    $result = ccp()->database->delete_all_events();

    if ($result) {
      wp_send_json_success(array(
        'message' => __('All changes have been cleared.', 'change-checkpoints')
      ));
    } else {
      wp_send_json_error(array(
        'message' => __('Failed to clear changes.', 'change-checkpoints')
      ));
    }
  }

  /**
   * Add admin notice
   */
  private function add_admin_notice($message, $type = 'info')
  {
    add_action('admin_notices', function () use ($message, $type) {
      printf(
        '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
        esc_attr($type),
        esc_html($message)
      );
    });
  }
}