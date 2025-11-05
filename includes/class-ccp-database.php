<?php
/**
 * Database management class for Change Checkpoints
 *
 * @package Change_Checkpoints
 */

// Prevent direct access
if (!defined('ABSPATH')) {
  exit;
}

/**
 * CCP_Database class
 */
class CCP_Database
{

  /**
   * Database version
   */
  const DB_VERSION = '2.2.0';

  /**
   * Option name for database version
   */
  const DB_VERSION_OPTION = 'ccp_db_version';

  /**
   * Constructor
   */
  public function __construct()
  {
    // Check if we need to create/update tables
    add_action('plugins_loaded', array($this, 'check_database_version'));
  }

  /**
   * Check database version and create/update tables if needed
   */
  public function check_database_version()
  {
    $installed_version = get_option(self::DB_VERSION_OPTION, '0');

    if (version_compare($installed_version, self::DB_VERSION, '<')) {
      $this->create_tables();
      update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }
  }

  /**
   * Create database tables
   */
  public function create_tables()
  {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $events_table = $wpdb->prefix . 'ccp_events';

    // Check if table exists and get current version
    $installed_version = get_option(self::DB_VERSION_OPTION, '0');
    
    if (version_compare($installed_version, '2.2.0', '<')) {
      // Convert ENUM to VARCHAR for better flexibility
      $wpdb->query("ALTER TABLE $events_table MODIFY COLUMN object_kind varchar(32) NOT NULL");
      $wpdb->query("ALTER TABLE $events_table MODIFY COLUMN action varchar(32) NOT NULL");
    }

    // Create/update events table
    $events_sql = "CREATE TABLE $events_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            object_kind varchar(32) NOT NULL,
            object_subtype varchar(64) NOT NULL,
            object_id bigint(20) DEFAULT NULL,
            object_name varchar(255) DEFAULT NULL,
            action varchar(32) NOT NULL,
            details_json longtext DEFAULT NULL,
            author_id bigint(20) DEFAULT NULL,
            timestamp datetime NOT NULL,
            PRIMARY KEY (id),
            KEY object_kind_subtype (object_kind, object_subtype),
            KEY timestamp (timestamp),
            KEY author_id (author_id)
        ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($events_sql);

    // Drop old checkpoints table if it exists
    $checkpoints_table = $wpdb->prefix . 'ccp_checkpoints';
    $wpdb->query("DROP TABLE IF EXISTS $checkpoints_table");
  }

  /**
   * Get checkpoints table name (deprecated - will be removed)
   */
  public function get_checkpoints_table()
  {
    global $wpdb;
    return $wpdb->prefix . 'ccp_checkpoints';
  }

  /**
   * Get events table name
   */
  public function get_events_table()
  {
    global $wpdb;
    return $wpdb->prefix . 'ccp_events';
  }

  /**
   * Add an event
   */
  public function add_event($object_kind, $object_subtype, $object_id, $object_name, $action, $details = null, $author_id = null)
  {
    global $wpdb;

    $result = $wpdb->insert(
      $this->get_events_table(),
      array(
        'object_kind' => $object_kind,
        'object_subtype' => $object_subtype,
        'object_id' => $object_id,
        'object_name' => sanitize_text_field($object_name),
        'action' => $action,
        'details_json' => is_array($details) ? wp_json_encode($details) : $details,
        'author_id' => $author_id ?: get_current_user_id(),
        'timestamp' => current_time('mysql', true)
      ),
      array('%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s')
    );

    return $result !== false ? $wpdb->insert_id : false;
  }

  /**
   * Get all events with pagination
   */
  public function get_events($args = array())
  {
    global $wpdb;

    $defaults = array(
      'per_page' => 50,
      'page' => 1,
      'orderby' => 'timestamp',
      'order' => 'DESC',
      'search' => '',
      'object_kind' => '',
      'object_subtype' => ''
    );

    $args = wp_parse_args($args, $defaults);

    $where_clauses = array();
    $where_values = array();

    // Search functionality
    if (!empty($args['search'])) {
      $where_clauses[] = "(object_name LIKE %s OR object_subtype LIKE %s OR DATE_FORMAT(timestamp, '%Y-%m-%d') LIKE %s)";
      $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
      $where_values[] = $search_term;
      $where_values[] = $search_term;
      $where_values[] = $search_term;
    }

    // Object kind filter
    if (!empty($args['object_kind']) && in_array($args['object_kind'], array('post', 'term', 'media', 'theme'))) {
      $where_clauses[] = "object_kind = %s";
      $where_values[] = $args['object_kind'];
    }

    // Object subtype filter
    if (!empty($args['object_subtype'])) {
      $where_clauses[] = "object_subtype = %s";
      $where_values[] = $args['object_subtype'];
    }

    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    // Build the query
    $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
    $limit = intval($args['per_page']);
    $offset = (intval($args['page']) - 1) * $limit;

    $sql = "SELECT * FROM {$this->get_events_table()} 
                $where_sql 
                ORDER BY $orderby 
                LIMIT %d OFFSET %d";

    $where_values[] = $limit;
    $where_values[] = $offset;

    if (!empty($where_values)) {
      $events = $wpdb->get_results($wpdb->prepare($sql, $where_values));
    } else {
      $events = $wpdb->get_results($sql);
    }

    return $events;
  }

  /**
   * Get total events count
   */
  public function get_events_count($args = array())
  {
    global $wpdb;

    $where_clauses = array();
    $where_values = array();

    // Search functionality
    if (!empty($args['search'])) {
      $where_clauses[] = "(object_name LIKE %s OR object_subtype LIKE %s OR DATE_FORMAT(timestamp, '%Y-%m-%d') LIKE %s)";
      $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
      $where_values[] = $search_term;
      $where_values[] = $search_term;
      $where_values[] = $search_term;
    }

    // Object kind filter
    if (!empty($args['object_kind']) && in_array($args['object_kind'], array('post', 'term', 'media', 'theme'))) {
      $where_clauses[] = "object_kind = %s";
      $where_values[] = $args['object_kind'];
    }

    // Object subtype filter
    if (!empty($args['object_subtype'])) {
      $where_clauses[] = "object_subtype = %s";
      $where_values[] = $args['object_subtype'];
    }

    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    $sql = "SELECT COUNT(*) FROM {$this->get_events_table()} $where_sql";

    if (!empty($where_values)) {
      $count = $wpdb->get_var($wpdb->prepare($sql, $where_values));
    } else {
      $count = $wpdb->get_var($sql);
    }

    return intval($count);
  }

  /**
   * Force database update (for development/troubleshooting)
   */
  public function force_database_update()
  {
    global $wpdb;
    
    $events_table = $wpdb->prefix . 'ccp_events';
    
    // Convert ENUM to VARCHAR for better flexibility
    $wpdb->query("ALTER TABLE $events_table MODIFY COLUMN object_kind varchar(32) NOT NULL");
    $wpdb->query("ALTER TABLE $events_table MODIFY COLUMN action varchar(32) NOT NULL");
    
    // Update version option
    update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    
    return true;
  }

  /**
   * Delete all events
   */
  public function delete_all_events()
  {
    global $wpdb;

    $result = $wpdb->query("TRUNCATE TABLE {$this->get_events_table()}");

    return $result !== false;
  }
}