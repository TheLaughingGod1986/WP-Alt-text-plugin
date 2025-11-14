<?php
/**
 * Quick script to check usage data from database
 * Run from WordPress root: php wp-content/plugins/opptiai-alt-text-generator/check-usage.php
 */

// Load WordPress
require_once(dirname(__FILE__) . '/../../../../wp-load.php');

if (!defined('ABSPATH')) {
    die('WordPress not loaded');
}

echo "=== AltText AI Usage Check ===\n\n";

// Check transient cache
$cache_key = 'opptiai_alt_usage_cache';
$cached_usage = get_transient($cache_key);

if ($cached_usage !== false) {
    echo "📊 Cached Usage Data (from transient):\n";
    echo "   Used: " . ($cached_usage['used'] ?? 0) . "\n";
    echo "   Limit: " . ($cached_usage['limit'] ?? 50) . "\n";
    echo "   Remaining: " . ($cached_usage['remaining'] ?? 0) . "\n";
    echo "   Plan: " . ($cached_usage['plan'] ?? 'free') . "\n";
    echo "   Reset Date: " . ($cached_usage['resetDate'] ?? 'N/A') . "\n";
    echo "   Percentage: " . round((($cached_usage['used'] ?? 0) / max($cached_usage['limit'] ?? 50, 1)) * 100) . "%\n";
    
    if (($cached_usage['used'] ?? 0) >= ($cached_usage['limit'] ?? 50)) {
        echo "\n⚠️  STATUS: All credits used!\n";
    } else {
        echo "\n✅ STATUS: Credits remaining\n";
    }
} else {
    echo "❌ No cached usage data found in transient.\n";
    echo "   The cache may have expired (5 minute expiry) or usage hasn't been fetched yet.\n";
}

// Try to get live data from API if possible
echo "\n=== Attempting Live API Check ===\n";

// Check if we can access the API client
if (class_exists('Opptiai_Alt_Api_Client_V2')) {
    // Try to get the plugin instance
    global $alttextai_plugin;
    
    if (isset($alttextai_plugin) && isset($alttextai_plugin->api_client)) {
        $api_client = $alttextai_plugin->api_client;
        
        if ($api_client && method_exists($api_client, 'get_usage')) {
            echo "Fetching live usage from API...\n";
            $live_usage = $api_client->get_usage();
            
            if (is_array($live_usage) && !empty($live_usage)) {
                echo "\n📡 Live API Usage Data:\n";
                echo "   Used: " . ($live_usage['used'] ?? 0) . "\n";
                echo "   Limit: " . ($live_usage['limit'] ?? 50) . "\n";
                echo "   Remaining: " . ($live_usage['remaining'] ?? 0) . "\n";
                echo "   Plan: " . ($live_usage['plan'] ?? 'free') . "\n";
                echo "   Reset Date: " . ($live_usage['resetDate'] ?? 'N/A') . "\n";
                
                if (($live_usage['used'] ?? 0) >= ($live_usage['limit'] ?? 50)) {
                    echo "\n⚠️  STATUS: All credits used!\n";
                } else {
                    echo "\n✅ STATUS: Credits remaining\n";
                }
            } else {
                echo "❌ Could not fetch live usage data from API.\n";
                if (is_wp_error($live_usage)) {
                    echo "   Error: " . $live_usage->get_error_message() . "\n";
                }
            }
        } else {
            echo "❌ API client method not available.\n";
        }
    } else {
        echo "❌ Plugin instance not found. Make sure the plugin is active.\n";
    }
} else {
    echo "❌ API client class not found.\n";
}

// Check database tables if they exist
echo "\n=== Database Tables Check ===\n";
global $wpdb;

$usage_events_table = $wpdb->prefix . 'alttextai_usage_events';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$usage_events_table'");

if ($table_exists) {
    $total_events = $wpdb->get_var("SELECT COUNT(*) FROM $usage_events_table");
    echo "✅ Usage events table exists\n";
    echo "   Total events recorded: $total_events\n";
    
    // Count events in current month
    $current_month = date('Y-m');
    $month_events = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $usage_events_table WHERE DATE_FORMAT(created_at, '%%Y-%%m') = %s",
        $current_month
    ));
    echo "   Events this month ($current_month): $month_events\n";
} else {
    echo "ℹ️  Usage events table does not exist (this is optional, used for detailed tracking).\n";
}

echo "\n=== Summary ===\n";
$final_used = $cached_usage['used'] ?? 0;
$final_limit = $cached_usage['limit'] ?? 50;

if ($final_used >= $final_limit) {
    echo "⚠️  You have used ALL $final_limit credits.\n";
    echo "   Upgrade to Pro for unlimited generations!\n";
} else {
    echo "✅ You have used $final_used of $final_limit credits.\n";
    echo "   Remaining: " . ($final_limit - $final_used) . " credits\n";
}

echo "\n";

