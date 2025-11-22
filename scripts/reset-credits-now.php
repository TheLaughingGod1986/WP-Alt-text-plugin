<?php
/**
 * Quick Credit Reset Script
 * This uses the plugin's built-in reset function
 */

// Try multiple WordPress paths
$wp_paths = [
    __DIR__ . '/../../../../wp-load.php',
    __DIR__ . '/../../../wp-load.php',
    dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php',
    '/var/www/html/wp-load.php',
    '/Users/benjaminoats/Library/CloudStorage/SynologyDrive-File-sync/Coding/wp-alt-text-ai/wp-load.php',
    getcwd() . '/wp-load.php',
    getcwd() . '/../wp-load.php',
    getcwd() . '/../../wp-load.php',
];

$wp_loaded = false;
foreach ($wp_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded || !defined('ABSPATH')) {
    die("❌ Error: Could not find WordPress. Please run this from your WordPress root directory.\n");
}

// Load plugin
require_once ABSPATH . 'wp-content/plugins/beepbeep-ai-alt-text-generator/admin/class-bbai-core.php';

if (!class_exists('BBAI_Core')) {
    die("❌ Error: Plugin not found. Make sure the plugin is installed.\n");
}

// Create core instance and reset
$core = new BBAI_Core();

// Manually reset credits (bypassing admin_post check)
if (!current_user_can('manage_options')) {
    // For CLI, we'll bypass the user check
    if (php_sapi_name() !== 'cli') {
        die("❌ Error: You must be logged in as an administrator.\n");
    }
}

echo "🔄 Resetting Credits...\n";
echo str_repeat("=", 60) . "\n\n";

// Clear usage cache
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
\BeepBeepAI\AltTextGenerator\Usage_Tracker::clear_cache();
echo "✓ Cleared usage cache\n";

// Clear token quota service cache
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-token-quota-service.php';
\BeepBeepAI\AltTextGenerator\Token_Quota_Service::clear_cache();
echo "✓ Cleared token quota cache\n";

// Reset usage to 0
$reset_ts = strtotime('first day of next month');
$usage_data = [
    'used' => 0,
    'limit' => 50,
    'remaining' => 50,
    'plan' => 'free',
    'resetDate' => date('Y-m-01', $reset_ts),
    'resetTimestamp' => $reset_ts,
];
\BeepBeepAI\AltTextGenerator\Usage_Tracker::update_usage($usage_data);
echo "✓ Reset usage to 0/50\n";

// Clear credit usage logs
global $wpdb;
$credit_usage_table = $wpdb->prefix . 'bbai_credit_usage';
if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $credit_usage_table)) === $credit_usage_table) {
    $wpdb->query("DELETE FROM `{$credit_usage_table}`");
    echo "✓ Cleared credit usage logs\n";
}

// Clear usage event logs
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/usage/class-usage-logs.php';
$usage_logs_table = \BeepBeepAI\AltTextGenerator\Usage\Usage_Logs::table();
if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $usage_logs_table)) === $usage_logs_table) {
    $wpdb->query("DELETE FROM `{$usage_logs_table}`");
    echo "✓ Cleared usage event logs\n";
}

// Clear token quota service local usage
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
$site_id = \BeepBeepAI\AltTextGenerator\get_site_identifier();
$quota_option_key = 'bbai_token_quota_' . md5($site_id);
delete_option($quota_option_key);
echo "✓ Cleared token quota option\n";

// Invalidate stats cache
$core->invalidate_stats_cache();
echo "✓ Invalidated stats cache\n";

echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ Credits Reset Complete!\n\n";
echo "Current Status:\n";
echo "  - Used: 0\n";
echo "  - Limit: 50\n";
echo "  - Remaining: 50\n";
echo "  - Plan: free\n\n";

