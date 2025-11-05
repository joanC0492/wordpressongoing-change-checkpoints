<?php
/**
 * Temporary script to force database update
 * Run this once and then delete this file
 */

// WordPress bootstrap
require_once('../../../wp-load.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Access denied');
}

global $wpdb;

$events_table = $wpdb->prefix . 'ccp_events';

echo "<h2>Forcing Database Update...</h2>";

// Show current structure
echo "<h3>Current table structure:</h3>";
$columns = $wpdb->get_results("DESCRIBE $events_table");
foreach ($columns as $column) {
    echo "<p><strong>{$column->Field}:</strong> {$column->Type}</p>";
}

echo "<h3>Converting ENUM to VARCHAR for better flexibility...</h3>";

// Convert ENUM to VARCHAR
$result1 = $wpdb->query("ALTER TABLE $events_table MODIFY COLUMN object_kind varchar(32) NOT NULL");
$result2 = $wpdb->query("ALTER TABLE $events_table MODIFY COLUMN action varchar(32) NOT NULL");

echo "<p>object_kind conversion: " . ($result1 !== false ? "SUCCESS" : "FAILED") . "</p>";
echo "<p>action conversion: " . ($result2 !== false ? "SUCCESS" : "FAILED") . "</p>";

if ($wpdb->last_error) {
    echo "<p><strong>Error:</strong> " . $wpdb->last_error . "</p>";
}

// Update version option
update_option('ccp_db_version', '2.2.0');
echo "<p>Version updated to 2.2.0</p>";

echo "<h3>New table structure:</h3>";
$columns = $wpdb->get_results("DESCRIBE $events_table");
foreach ($columns as $column) {
    echo "<p><strong>{$column->Field}:</strong> {$column->Type}</p>";
}

echo "<h2>✅ Update Complete!</h2>";
echo "<p><strong>You can now delete this file and test theme switching.</strong></p>";
?>