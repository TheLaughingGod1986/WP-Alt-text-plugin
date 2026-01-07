<?php
/**
 * Clear usage cache
 */
require_once(__DIR__ . '/../../../wp-load.php');

global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%bbai%usage%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%transient%bbai%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%bbai_cached_usage%'");

echo "✅ Cache cleared! Refresh your dashboard.\n";
