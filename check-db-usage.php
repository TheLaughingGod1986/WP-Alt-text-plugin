<?php
/**
 * Direct Database Usage Check
 * This script will try multiple paths to load WordPress
 */

$wp_load_paths = [
    __DIR__ . '/../../../../wp-load.php',
    __DIR__ . '/../../../wp-load.php',
    dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php',
    '/var/www/html/wp-load.php',
    '/Users/benjaminoats/Library/CloudStorage/SynologyDrive-File-sync/Coding/wp-alt-text-ai/wp-load.php',
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die("Error: Could not find wp-load.php. Please run this script from your WordPress root directory.\n");
}

if (!defined('ABSPATH')) {
    die("Error: WordPress not loaded properly.\n");
}

echo "=== AltText AI Database Usage Check ===\n\n";

global $wpdb;

// Check the usage cache transient
$cache_key = 'opptiai_alt_usage_cache';
$transient_key = '_transient_' . $cache_key;
$timeout_key = '_transient_timeout_' . $cache_key;

$transient_value = $wpdb->get_var($wpdb->prepare(
    "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
    $transient_key
));

$timeout_value = $wpdb->get_var($wpdb->prepare(
    "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
    $timeout_key
));

if ($transient_value) {
    $usage_data = maybe_unserialize($transient_value);
    
    echo "📊 Usage Data Found in Database:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "   Used:      " . ($usage_data['used'] ?? 0) . "\n";
    echo "   Limit:     " . ($usage_data['limit'] ?? 50) . "\n";
    echo "   Remaining: " . ($usage_data['remaining'] ?? 0) . "\n";
    echo "   Plan:      " . ($usage_data['plan'] ?? 'free') . "\n";
    echo "   Reset Date: " . ($usage_data['resetDate'] ?? 'N/A') . "\n";
    
    $used = intval($usage_data['used'] ?? 0);
    $limit = intval($usage_data['limit'] ?? 50);
    $percentage = $limit > 0 ? round(($used / $limit) * 100) : 0;
    
    echo "   Percentage: {$percentage}%\n";
    
    if ($timeout_value) {
        $expires_at = date('Y-m-d H:i:s', intval($timeout_value));
        $current_time = current_time('mysql');
        $is_expired = intval($timeout_value) < current_time('timestamp');
        
        echo "   Cache Expires: {$expires_at}\n";
        echo "   Cache Status: " . ($is_expired ? "⚠️  EXPIRED" : "✅ ACTIVE") . "\n";
    }
    
    echo "\n";
    
    if ($used >= $limit) {
        echo "⚠️  STATUS: ALL {$limit} CREDITS HAVE BEEN USED!\n";
        echo "   You need to upgrade to Pro or wait for the monthly reset.\n";
    } else {
        $remaining = $limit - $used;
        echo "✅ STATUS: {$remaining} credits remaining out of {$limit}\n";
    }
    
} else {
    echo "❌ No usage cache found in database.\n";
    echo "   This could mean:\n";
    echo "   - The cache has expired (5 minute expiry)\n";
    echo "   - Usage hasn't been fetched from the API yet\n";
    echo "   - You need to visit the plugin dashboard to trigger a cache refresh\n";
}

// Also check usage events table if it exists
echo "\n=== Usage Events Table Check ===\n";
$events_table = $wpdb->prefix . 'alttextai_usage_events';
$table_exists = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM information_schema.tables 
     WHERE table_schema = DATABASE() 
     AND table_name = %s",
    $events_table
));

if ($table_exists) {
    $current_month = date('Y-m');
    $month_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$events_table} 
         WHERE DATE_FORMAT(created_at, '%%Y-%%m') = %s",
        $current_month
    ));
    
    echo "✅ Events table exists\n";
    echo "   Events this month ({$current_month}): {$month_count}\n";
    
    // Get install ID if available
    $install_id = get_option('alttextai_install_id', '');
    if ($install_id) {
        $install_events = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$events_table} 
             WHERE install_id = %s 
             AND DATE_FORMAT(created_at, '%%Y-%%m') = %s",
            $install_id,
            $current_month
        ));
        echo "   Events for this installation: {$install_events}\n";
    }
} else {
    echo "ℹ️  Usage events table does not exist (optional feature)\n";
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Note: The usage data is cached for 5 minutes.\n";
echo "The authoritative source is the backend API server.\n";
echo "\n";

