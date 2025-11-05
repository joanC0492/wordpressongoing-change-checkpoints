<?php
/**
 * Plugin Name: WordPress Change Tracker
 * Plugin URI: https://wordpress.org/plugins/wordpressongoing-change-checkpoints
 * Description: Track all changes to Pages, Posts, Custom Post Types and Taxonomies automatically. View a complete log of all WordPress content modifications.
 * Version: 2.0.0
 * Author: Joan Cochachi
 * Author URI: https://wordpressongoing.com/
 * Text Domain: change-checkpoints
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
  exit;
}

// Define plugin constants
define('CCP_VERSION', '2.0.0');
define('CCP_PLUGIN_FILE', __FILE__);
define('CCP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CCP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CCP_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Change_Checkpoints_Plugin class
 */
class Change_Checkpoints_Plugin
{

  /**
   * Single instance of the plugin
   */
  private static $instance = null;

  /**
   * Database handler instance
   */
  public $database;

  /**
   * Admin interface instance
   */
  public $admin;

  /**
   * Event tracker instance
   */
  public $event_tracker;

  /**
   * Get single instance of the plugin
   */
  public static function get_instance()
  {
    if (null === self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Private constructor to prevent multiple instances
   */
  private function __construct()
  {
    $this->init();
  }

  /**
   * Initialize the plugin
   */
  private function init()
  {
    // Load required files
    $this->load_dependencies();

    // Initialize components
    $this->init_components();

    // Hook into WordPress
    $this->setup_hooks();

    // Load textdomain
    $this->load_textdomain();
  }

  /**
   * Load required files
   */
  private function load_dependencies()
  {
    // Core classes
    require_once CCP_PLUGIN_DIR . 'includes/class-ccp-database.php';
    require_once CCP_PLUGIN_DIR . 'includes/class-ccp-event-tracker.php';

    // Admin classes
    if (is_admin()) {
      require_once CCP_PLUGIN_DIR . 'admin/class-ccp-admin.php';
    }
  }

  /**
   * Initialize plugin components
   */
  private function init_components()
  {
    $this->database = new CCP_Database();
    $this->event_tracker = new CCP_Event_Tracker();

    if (is_admin()) {
      $this->admin = new CCP_Admin();
    }
  }

  /**
   * Setup WordPress hooks
   */
  private function setup_hooks()
  {
    // Plugin activation/deactivation hooks
    register_activation_hook(CCP_PLUGIN_FILE, array($this->database, 'create_tables'));
    register_deactivation_hook(CCP_PLUGIN_FILE, array($this, 'deactivate'));
    register_uninstall_hook(CCP_PLUGIN_FILE, array('Change_Checkpoints_Plugin', 'uninstall'));

    // Init hook
    add_action('init', array($this, 'on_init'));

    // Admin init
    if (is_admin()) {
      add_action('admin_init', array($this, 'admin_init'));
    }
  }

  /**
   * Load plugin textdomain for translations
   */
  private function load_textdomain()
  {
    add_action('plugins_loaded', function () {
      load_plugin_textdomain(
        'change-checkpoints',
        false,
        dirname(CCP_PLUGIN_BASENAME) . '/languages'
      );
    });
  }

  /**
   * WordPress init hook
   */
  public function on_init()
  {
    // Event tracking is now always initialized in the constructor
    // No need to check for active checkpoints
  }

  /**
   * Admin init hook
   */
  public function admin_init()
  {
    // Check user permissions
    if (!current_user_can('manage_options')) {
      return;
    }
  }

  /**
   * Plugin deactivation
   */
  public function deactivate()
  {
    // No need to close checkpoints anymore
    // Plugin will stop tracking when deactivated
  }

  /**
   * Plugin uninstall (static method)
   */
  public static function uninstall()
  {
    global $wpdb;

    // Remove database tables
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ccp_events");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ccp_checkpoints");

    // Remove options if any
    delete_option('ccp_db_version');
  }
}

/**
 * Initialize the plugin
 */
function ccp_init()
{
  return Change_Checkpoints_Plugin::get_instance();
}

// Start the plugin
ccp_init();

/**
 * Get the main plugin instance
 */
function ccp()
{
  return Change_Checkpoints_Plugin::get_instance();
}