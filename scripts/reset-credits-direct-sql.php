<?php
/**
 * Direct SQL Reset via WordPress Database
 * This script connects to WordPress's MySQL database and resets credits
 * Run this from your WordPress root directory or via WP-CLI
 */

// Try to load WordPress
$wp_load_paths = [
    __DIR__ . '/../../../../wp-load.php',
    dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php',
    '/var/www/html/wp-load.php',
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded || !defined('ABSPATH')) {
    die("Error: WordPress not loaded. Make sure you're running this from your WordPress installation.\n");
}

if (!current_user_can('manage_options')) {
    die("Error: You must be logged in as an administrator.\n");
}

echo "🔄 Resetting Credits via WordPress Database...\n";
echo str_repeat("=", 60) . "\n\n";

global $wpdb;

// 1. Clear usage cache
echo "1. Clearing usage cache...\n";
$deleted = $wpdb->query("
    DELETE FROM {$wpdb->options} 
    WHERE option_name = '_transient_bbai_usage_cache' 
       OR option_name = '_transient_timeout_bbai_usage_cache'
       OR option_name LIKE 'bbai_token_quota_%'
");
echo "   ✓ Deleted {$deleted} cache entries\n\n";

// 2. Set usage to 0/50
echo "2. Setting usage to 0/50...\n";
$reset_ts = strtotime('first day of next month');
$usage_data = serialize([
    'used' => 0,
    'limit' => 50,
    'remaining' => 50,
    'plan' => 'free',
    'resetDate' => date('Y-m-01', $reset_ts),
    'reset_timestamp' => $reset_ts,
    'seconds_until_reset' => $reset_ts - time(),
]);

$wpdb->replace(
    $wpdb->options,
    [
        'option_name' => '_transient_bbai_usage_cache',
        'option_value' => $usage_data,
        'autoload' => 'no',
    ],
    ['%s', '%s', '%s']
);

$wpdb->replace(
    $wpdb->options,
    [
        'option_name' => '_transient_timeout_bbai_usage_cache',
        'option_value' => time() + 300,
        'autoload' => 'no',
    ],
    ['%s', '%d', '%s']
);
echo "   ✓ Usage set to 0/50\n\n";

// 3. Clear credit usage logs
echo "3. Clearing credit usage logs...\n";
$credit_table = $wpdb->prefix . 'bbai_credit_usage';
if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $credit_table)) === $credit_table) {
    $deleted = $wpdb->query("TRUNCATE TABLE `{$credit_table}`");
    echo "   ✓ Cleared credit usage logs\n\n";
} else {
    echo "   ⚠️  Credit usage table not found (skipping)\n\n";
}

// 4. Clear usage event logs
echo "4. Clearing usage event logs...\n";
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/usage/class-usage-logs.php';
$usage_logs_table = \BeepBeepAI\AltTextGenerator\Usage\Usage_Logs::table();
if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $usage_logs_table)) === $usage_logs_table) {
    $deleted = $wpdb->query("TRUNCATE TABLE `{$usage_logs_table}`");
    echo "   ✓ Cleared usage event logs\n\n";
} else {
    echo "   ⚠️  Usage logs table not found (skipping)\n\n";
}

echo str_repeat("=", 60) . "\n";
echo "✅ Credits Reset Complete!\n";
echo "   Used: 0\n";
echo "   Limit: 50\n";
echo "   Remaining: 50\n\n";

