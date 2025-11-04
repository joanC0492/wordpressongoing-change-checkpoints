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
    add_action('wp_ajax_ccp_create_checkpoint', array($this, 'ajax_create_checkpoint'));
    add_action('wp_ajax_ccp_close_checkpoint', array($this, 'ajax_close_checkpoint'));
    add_action('wp_ajax_ccp_delete_checkpoint', array($this, 'ajax_delete_checkpoint'));
    add_action('wp_ajax_ccp_bulk_delete', array($this, 'ajax_bulk_delete'));
  }

  /**
   * Add admin menu
   */
  public function add_admin_menu()
  {
    // Main menu page
    $this->page_hooks['overview'] = add_menu_page(
      __('Change Checkpoints', 'change-checkpoints'),
      __('Change Checkpoints', 'change-checkpoints'),
      'manage_options',
      'change-checkpoints',
      array($this, 'display_overview_page'),
      'dashicons-backup',
      30
    );

    // Overview submenu (same as main page)
    $this->page_hooks['overview_sub'] = add_submenu_page(
      'change-checkpoints',
      __('Overview', 'change-checkpoints'),
      __('Overview', 'change-checkpoints'),
      'manage_options',
      'change-checkpoints',
      array($this, 'display_overview_page')
    );

    // View checkpoint page (hidden from menu)
    $this->page_hooks['view'] = add_submenu_page(
      null, // Hidden from menu
      __('View Checkpoint', 'change-checkpoints'),
      __('View Checkpoint', 'change-checkpoints'),
      'manage_options',
      'change-checkpoints-view',
      array($this, 'display_view_checkpoint_page')
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
      case 'create_checkpoint':
        if (wp_verify_nonce($_REQUEST['_wpnonce'], 'ccp_create_checkpoint')) {
          $this->handle_create_checkpoint();
        }
        break;

      case 'close_checkpoint':
        if (wp_verify_nonce($_REQUEST['_wpnonce'], 'ccp_close_checkpoint')) {
          $this->handle_close_checkpoint();
        }
        break;

      case 'delete_checkpoint':
        if (wp_verify_nonce($_REQUEST['_wpnonce'], 'ccp_delete_checkpoint')) {
          $this->handle_delete_checkpoint();
        }
        break;

      case 'bulk_delete':
        if (wp_verify_nonce($_REQUEST['_wpnonce'], 'ccp_bulk_action')) {
          $this->handle_bulk_delete();
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
        'confirmDelete' => __('This will permanently remove the checkpoint and its recorded changes. Continue?', 'change-checkpoints'),
        'confirmBulkDelete' => __('This will delete selected checkpoints and their changes. This action cannot be undone.', 'change-checkpoints'),
        'confirmDeleteAll' => __('This will delete all checkpoints and their changes. This action cannot be undone.', 'change-checkpoints'),
        'creating' => __('Creating...', 'change-checkpoints'),
        'closing' => __('Closing...', 'change-checkpoints'),
        'deleting' => __('Deleting...', 'change-checkpoints'),
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
    $checkpoint_manager = ccp()->checkpoint_manager;
    $active_checkpoint = $checkpoint_manager->get_active_checkpoint();

    // Handle pagination
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 20;
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

    // Get checkpoints
    $args = array(
      'page' => $current_page,
      'per_page' => $per_page,
      'search' => $search
    );

    $checkpoints = $checkpoint_manager->get_checkpoints($args);
    $total_checkpoints = $checkpoint_manager->get_checkpoints_count($args);
    $total_pages = ceil($total_checkpoints / $per_page);

    // Include template
    include CCP_PLUGIN_DIR . 'admin/views/overview.php';
  }

  /**
   * Display view checkpoint page
   */
  public function display_view_checkpoint_page()
  {
    $checkpoint_id = isset($_GET['checkpoint_id']) ? intval($_GET['checkpoint_id']) : 0;

    if (!$checkpoint_id) {
      wp_die(__('Invalid checkpoint ID.', 'change-checkpoints'));
    }

    $checkpoint_manager = ccp()->checkpoint_manager;
    $checkpoint = $checkpoint_manager->get_checkpoint($checkpoint_id);

    if (!$checkpoint) {
      wp_die(__('Checkpoint not found.', 'change-checkpoints'));
    }

    $events = $checkpoint_manager->get_formatted_checkpoint_events($checkpoint_id);
    $active_checkpoint = $checkpoint_manager->get_active_checkpoint();
    $is_active = $active_checkpoint && $active_checkpoint->id == $checkpoint_id;

    // Include template
    include CCP_PLUGIN_DIR . 'admin/views/view-checkpoint.php';
  }

  /**
   * Handle create checkpoint
   */
  private function handle_create_checkpoint()
  {
    $title = isset($_POST['checkpoint_title']) ? sanitize_text_field($_POST['checkpoint_title']) : '';
    $note = isset($_POST['checkpoint_note']) ? wp_kses_post($_POST['checkpoint_note']) : '';

    $checkpoint_id = ccp()->checkpoint_manager->create_checkpoint($title, $note);

    if ($checkpoint_id) {
      $this->add_admin_notice(__('Checkpoint created and set as active.', 'change-checkpoints'), 'success');
    } else {
      $this->add_admin_notice(__('Failed to create checkpoint.', 'change-checkpoints'), 'error');
    }

    wp_redirect(admin_url('admin.php?page=change-checkpoints'));
    exit;
  }

  /**
   * Handle close checkpoint
   */
  private function handle_close_checkpoint()
  {
    $checkpoint_id = isset($_POST['checkpoint_id']) ? intval($_POST['checkpoint_id']) : 0;

    if (!$checkpoint_id) {
      $this->add_admin_notice(__('Invalid checkpoint ID.', 'change-checkpoints'), 'error');
      wp_redirect(admin_url('admin.php?page=change-checkpoints'));
      exit;
    }

    $result = ccp()->checkpoint_manager->close_checkpoint($checkpoint_id);

    if ($result) {
      $this->add_admin_notice(__('Checkpoint closed. Changes will no longer be recorded until a new checkpoint is created.', 'change-checkpoints'), 'success');
    } else {
      $this->add_admin_notice(__('Failed to close checkpoint.', 'change-checkpoints'), 'error');
    }

    $redirect_url = isset($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : admin_url('admin.php?page=change-checkpoints');
    wp_redirect($redirect_url);
    exit;
  }

  /**
   * Handle delete checkpoint
   */
  private function handle_delete_checkpoint()
  {
    $checkpoint_id = isset($_POST['checkpoint_id']) ? intval($_POST['checkpoint_id']) : 0;

    if (!$checkpoint_id) {
      $this->add_admin_notice(__('Invalid checkpoint ID.', 'change-checkpoints'), 'error');
      wp_redirect(admin_url('admin.php?page=change-checkpoints'));
      exit;
    }

    $result = ccp()->checkpoint_manager->delete_checkpoint($checkpoint_id);

    if ($result) {
      $this->add_admin_notice(__('Checkpoint deleted.', 'change-checkpoints'), 'success');
    } else {
      $this->add_admin_notice(__('Failed to delete checkpoint.', 'change-checkpoints'), 'error');
    }

    wp_redirect(admin_url('admin.php?page=change-checkpoints'));
    exit;
  }

  /**
   * Handle bulk delete
   */
  private function handle_bulk_delete()
  {
    $action = isset($_POST['action']) ? sanitize_text_field($_POST['action']) : '';
    $action2 = isset($_POST['action2']) ? sanitize_text_field($_POST['action2']) : '';

    if ($action === 'delete' || $action2 === 'delete') {
      $checkpoint_ids = isset($_POST['checkpoint']) ? array_map('intval', $_POST['checkpoint']) : array();

      if (empty($checkpoint_ids)) {
        $this->add_admin_notice(__('No checkpoints selected.', 'change-checkpoints'), 'error');
        wp_redirect(admin_url('admin.php?page=change-checkpoints'));
        exit;
      }

      $deleted_count = 0;
      foreach ($checkpoint_ids as $checkpoint_id) {
        if (ccp()->checkpoint_manager->delete_checkpoint($checkpoint_id)) {
          $deleted_count++;
        }
      }

      if ($deleted_count > 0) {
        $this->add_admin_notice(
          sprintf(
            _n('%d checkpoint deleted.', '%d checkpoints deleted.', $deleted_count, 'change-checkpoints'),
            $deleted_count
          ),
          'success'
        );
      } else {
        $this->add_admin_notice(__('Failed to delete checkpoints.', 'change-checkpoints'), 'error');
      }
    } elseif ($action === 'delete_all' || $action2 === 'delete_all') {
      $result = ccp()->checkpoint_manager->delete_all_checkpoints();

      if ($result) {
        $this->add_admin_notice(__('All checkpoints deleted.', 'change-checkpoints'), 'success');
      } else {
        $this->add_admin_notice(__('Failed to delete all checkpoints.', 'change-checkpoints'), 'error');
      }
    }

    wp_redirect(admin_url('admin.php?page=change-checkpoints'));
    exit;
  }

  /**
   * AJAX: Create checkpoint
   */
  public function ajax_create_checkpoint()
  {
    check_ajax_referer('ccp_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_die(-1);
    }

    $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
    $note = isset($_POST['note']) ? wp_kses_post($_POST['note']) : '';

    $checkpoint_id = ccp()->checkpoint_manager->create_checkpoint($title, $note);

    if ($checkpoint_id) {
      wp_send_json_success(array(
        'message' => __('Checkpoint created and set as active.', 'change-checkpoints'),
        'checkpoint_id' => $checkpoint_id
      ));
    } else {
      wp_send_json_error(array(
        'message' => __('Failed to create checkpoint.', 'change-checkpoints')
      ));
    }
  }

  /**
   * AJAX: Close checkpoint
   */
  public function ajax_close_checkpoint()
  {
    check_ajax_referer('ccp_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_die(-1);
    }

    $checkpoint_id = isset($_POST['checkpoint_id']) ? intval($_POST['checkpoint_id']) : 0;

    if (!$checkpoint_id) {
      wp_send_json_error(array(
        'message' => __('Invalid checkpoint ID.', 'change-checkpoints')
      ));
    }

    $result = ccp()->checkpoint_manager->close_checkpoint($checkpoint_id);

    if ($result) {
      wp_send_json_success(array(
        'message' => __('Checkpoint closed. Changes will no longer be recorded until a new checkpoint is created.', 'change-checkpoints')
      ));
    } else {
      wp_send_json_error(array(
        'message' => __('Failed to close checkpoint.', 'change-checkpoints')
      ));
    }
  }

  /**
   * AJAX: Delete checkpoint
   */
  public function ajax_delete_checkpoint()
  {
    check_ajax_referer('ccp_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_die(-1);
    }

    $checkpoint_id = isset($_POST['checkpoint_id']) ? intval($_POST['checkpoint_id']) : 0;

    if (!$checkpoint_id) {
      wp_send_json_error(array(
        'message' => __('Invalid checkpoint ID.', 'change-checkpoints')
      ));
    }

    $result = ccp()->checkpoint_manager->delete_checkpoint($checkpoint_id);

    if ($result) {
      wp_send_json_success(array(
        'message' => __('Checkpoint deleted.', 'change-checkpoints')
      ));
    } else {
      wp_send_json_error(array(
        'message' => __('Failed to delete checkpoint.', 'change-checkpoints')
      ));
    }
  }

  /**
   * AJAX: Bulk delete checkpoints
   */
  public function ajax_bulk_delete()
  {
    check_ajax_referer('ccp_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_die(-1);
    }

    $action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';

    if ($action === 'delete_all') {
      $result = ccp()->checkpoint_manager->delete_all_checkpoints();

      if ($result) {
        wp_send_json_success(array(
          'message' => __('All checkpoints deleted.', 'change-checkpoints')
        ));
      } else {
        wp_send_json_error(array(
          'message' => __('Failed to delete all checkpoints.', 'change-checkpoints')
        ));
      }
    } else {
      $checkpoint_ids = isset($_POST['checkpoint_ids']) ? array_map('intval', $_POST['checkpoint_ids']) : array();

      if (empty($checkpoint_ids)) {
        wp_send_json_error(array(
          'message' => __('No checkpoints selected.', 'change-checkpoints')
        ));
      }

      $deleted_count = 0;
      foreach ($checkpoint_ids as $checkpoint_id) {
        if (ccp()->checkpoint_manager->delete_checkpoint($checkpoint_id)) {
          $deleted_count++;
        }
      }

      if ($deleted_count > 0) {
        wp_send_json_success(array(
          'message' => sprintf(
            _n('%d checkpoint deleted.', '%d checkpoints deleted.', $deleted_count, 'change-checkpoints'),
            $deleted_count
          )
        ));
      } else {
        wp_send_json_error(array(
          'message' => __('Failed to delete checkpoints.', 'change-checkpoints')
        ));
      }
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