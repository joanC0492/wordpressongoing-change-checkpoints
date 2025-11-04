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
  const DB_VERSION = '1.0.0';

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

    // Create checkpoints table
    $checkpoints_table = $wpdb->prefix . 'ccp_checkpoints';
    $checkpoints_sql = "CREATE TABLE $checkpoints_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(191) DEFAULT NULL,
            note longtext DEFAULT NULL,
            status enum('open','closed') NOT NULL DEFAULT 'open',
            created_at datetime NOT NULL,
            closed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

    // Create events table
    $events_table = $wpdb->prefix . 'ccp_events';
    $events_sql = "CREATE TABLE $events_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            checkpoint_id bigint(20) unsigned NOT NULL,
            object_kind enum('post','term') NOT NULL,
            object_subtype varchar(64) NOT NULL,
            object_id bigint(20) DEFAULT NULL,
            object_name varchar(255) DEFAULT NULL,
            action enum('create','update','delete') NOT NULL,
            details_json longtext DEFAULT NULL,
            author_id bigint(20) DEFAULT NULL,
            timestamp datetime NOT NULL,
            PRIMARY KEY (id),
            KEY checkpoint_id (checkpoint_id),
            KEY object_kind_subtype (object_kind, object_subtype),
            KEY timestamp (timestamp),
            FOREIGN KEY (checkpoint_id) REFERENCES $checkpoints_table(id) ON DELETE CASCADE
        ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($checkpoints_sql);
    dbDelta($events_sql);
  }

  /**
   * Get checkpoints table name
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
   * Create a new checkpoint
   */
  public function create_checkpoint($title = null, $note = null)
  {
    global $wpdb;

    $current_time = current_time('mysql', true);

    // Generate default title if not provided
    if (empty($title)) {
      $local_time = wp_date('Y-m-d H:i', strtotime($current_time));
      $title = sprintf(__('Checkpoint %s', 'change-checkpoints'), $local_time);
    }

    $result = $wpdb->insert(
      $this->get_checkpoints_table(),
      array(
        'title' => sanitize_text_field($title),
        'note' => wp_kses_post($note),
        'status' => 'open',
        'created_at' => $current_time
      ),
      array('%s', '%s', '%s', '%s')
    );

    if ($result === false) {
      return false;
    }

    return $wpdb->insert_id;
  }

  /**
   * Close a checkpoint
   */
  public function close_checkpoint($checkpoint_id)
  {
    global $wpdb;

    $result = $wpdb->update(
      $this->get_checkpoints_table(),
      array(
        'status' => 'closed',
        'closed_at' => current_time('mysql', true)
      ),
      array('id' => $checkpoint_id),
      array('%s', '%s'),
      array('%d')
    );

    return $result !== false;
  }

  /**
   * Get active checkpoint
   */
  public function get_active_checkpoint()
  {
    global $wpdb;

    $checkpoint = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM {$this->get_checkpoints_table()} WHERE status = %s ORDER BY created_at DESC LIMIT 1",
      'open'
    ));

    return $checkpoint;
  }

  /**
   * Get checkpoint by ID
   */
  public function get_checkpoint($checkpoint_id)
  {
    global $wpdb;

    $checkpoint = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM {$this->get_checkpoints_table()} WHERE id = %d",
      $checkpoint_id
    ));

    return $checkpoint;
  }

  /**
   * Get all checkpoints with pagination
   */
  public function get_checkpoints($args = array())
  {
    global $wpdb;

    $defaults = array(
      'per_page' => 20,
      'page' => 1,
      'orderby' => 'created_at',
      'order' => 'DESC',
      'search' => '',
      'status' => ''
    );

    $args = wp_parse_args($args, $defaults);

    $where_clauses = array();
    $where_values = array();

    // Search functionality
    if (!empty($args['search'])) {
      $where_clauses[] = "(title LIKE %s OR note LIKE %s OR DATE_FORMAT(created_at, '%Y-%m-%d') LIKE %s)";
      $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
      $where_values[] = $search_term;
      $where_values[] = $search_term;
      $where_values[] = $search_term;
    }

    // Status filter
    if (!empty($args['status']) && in_array($args['status'], array('open', 'closed'))) {
      $where_clauses[] = "status = %s";
      $where_values[] = $args['status'];
    }

    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    // Build the query
    $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
    $limit = intval($args['per_page']);
    $offset = (intval($args['page']) - 1) * $limit;

    $sql = "SELECT * FROM {$this->get_checkpoints_table()} 
                $where_sql 
                ORDER BY $orderby 
                LIMIT %d OFFSET %d";

    $where_values[] = $limit;
    $where_values[] = $offset;

    if (!empty($where_values)) {
      $checkpoints = $wpdb->get_results($wpdb->prepare($sql, $where_values));
    } else {
      $checkpoints = $wpdb->get_results($sql);
    }

    return $checkpoints;
  }

  /**
   * Get total checkpoints count
   */
  public function get_checkpoints_count($args = array())
  {
    global $wpdb;

    $where_clauses = array();
    $where_values = array();

    // Search functionality
    if (!empty($args['search'])) {
      $where_clauses[] = "(title LIKE %s OR note LIKE %s OR DATE_FORMAT(created_at, '%Y-%m-%d') LIKE %s)";
      $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
      $where_values[] = $search_term;
      $where_values[] = $search_term;
      $where_values[] = $search_term;
    }

    // Status filter
    if (!empty($args['status']) && in_array($args['status'], array('open', 'closed'))) {
      $where_clauses[] = "status = %s";
      $where_values[] = $args['status'];
    }

    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    $sql = "SELECT COUNT(*) FROM {$this->get_checkpoints_table()} $where_sql";

    if (!empty($where_values)) {
      $count = $wpdb->get_var($wpdb->prepare($sql, $where_values));
    } else {
      $count = $wpdb->get_var($sql);
    }

    return intval($count);
  }

  /**
   * Delete checkpoint and its events
   */
  public function delete_checkpoint($checkpoint_id)
  {
    global $wpdb;

    // Delete events first (foreign key constraint will handle this automatically, but let's be explicit)
    $wpdb->delete(
      $this->get_events_table(),
      array('checkpoint_id' => $checkpoint_id),
      array('%d')
    );

    // Delete checkpoint
    $result = $wpdb->delete(
      $this->get_checkpoints_table(),
      array('id' => $checkpoint_id),
      array('%d')
    );

    return $result !== false;
  }

  /**
   * Delete all checkpoints and events
   */
  public function delete_all_checkpoints()
  {
    global $wpdb;

    $events_result = $wpdb->query("TRUNCATE TABLE {$this->get_events_table()}");
    $checkpoints_result = $wpdb->query("TRUNCATE TABLE {$this->get_checkpoints_table()}");

    return $events_result !== false && $checkpoints_result !== false;
  }

  /**
   * Add an event to the current checkpoint
   */
  public function add_event($checkpoint_id, $object_kind, $object_subtype, $object_id, $object_name, $action, $details = null, $author_id = null)
  {
    global $wpdb;

    $result = $wpdb->insert(
      $this->get_events_table(),
      array(
        'checkpoint_id' => $checkpoint_id,
        'object_kind' => $object_kind,
        'object_subtype' => $object_subtype,
        'object_id' => $object_id,
        'object_name' => sanitize_text_field($object_name),
        'action' => $action,
        'details_json' => is_array($details) ? wp_json_encode($details) : $details,
        'author_id' => $author_id ?: get_current_user_id(),
        'timestamp' => current_time('mysql', true)
      ),
      array('%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s')
    );

    return $result !== false ? $wpdb->insert_id : false;
  }

  /**
   * Get events for a checkpoint
   */
  public function get_checkpoint_events($checkpoint_id, $args = array())
  {
    global $wpdb;

    $defaults = array(
      'orderby' => 'timestamp',
      'order' => 'DESC'
    );

    $args = wp_parse_args($args, $defaults);

    $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);

    $events = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM {$this->get_events_table()} 
             WHERE checkpoint_id = %d 
             ORDER BY $orderby",
      $checkpoint_id
    ));

    return $events;
  }

  /**
   * Get events count for a checkpoint
   */
  public function get_checkpoint_events_count($checkpoint_id)
  {
    global $wpdb;

    $count = $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM {$this->get_events_table()} WHERE checkpoint_id = %d",
      $checkpoint_id
    ));

    return intval($count);
  }
}