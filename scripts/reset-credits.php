<?php
/**
 * Reset Site Credits for Testing
 * Clears all local credit caches and resets usage to 0
 */

$wp_load_paths = [
    __DIR__ . '/../../../../wp-load.php',
    '/var/www/html/wp-load.php',
];

foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

if (!defined('ABSPATH')) {
    die("Error: WordPress not loaded. Make sure wp-load.php exists.\n");
}

echo "🔄 Resetting Site Credits for Testing\n";
echo str_repeat("=", 60) . "\n\n";

// 1. Clear usage cache transient
echo "1. Clearing usage cache...\n";
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
\BeepBeepAI\AltTextGenerator\Usage_Tracker::clear_cache();
echo "   ✓ Usage cache cleared\n\n";

// 2. Clear token quota service cache
echo "2. Clearing token quota cache...\n";
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-token-quota-service.php';
\BeepBeepAI\AltTextGenerator\Token_Quota_Service::clear_cache();
echo "   ✓ Token quota cache cleared\n\n";

// 3. Reset usage cache to 0 credits used
echo "3. Resetting usage to 0...\n";
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
echo "   ✓ Usage reset to: 0 used / 50 limit / 50 remaining\n\n";

// 4. Clear credit usage logs (optional - comment out if you want to keep history)
echo "4. Clearing credit usage logs...\n";
global $wpdb;
$credit_usage_table = $wpdb->prefix . 'bbai_credit_usage';
if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $credit_usage_table)) === $credit_usage_table) {
    $deleted = $wpdb->query("DELETE FROM `{$credit_usage_table}`");
    echo "   ✓ Deleted {$deleted} credit usage log entries\n\n";
} else {
    echo "   ⚠️  Credit usage table not found (skipping)\n\n";
}

// 5. Clear usage logs (optional - comment out if you want to keep history)
echo "5. Clearing usage event logs...\n";
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/usage/class-usage-logs.php';
$usage_logs_table = \BeepBeepAI\AltTextGenerator\Usage\Usage_Logs::table();
if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $usage_logs_table)) === $usage_logs_table) {
    $deleted = $wpdb->query("DELETE FROM `{$usage_logs_table}`");
    echo "   ✓ Deleted {$deleted} usage event log entries\n\n";
} else {
    echo "   ⚠️  Usage logs table not found (skipping)\n\n";
}

// 6. Clear token quota service local usage
echo "6. Resetting token quota service...\n";
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
$site_id = \BeepBeepAI\AltTextGenerator\get_site_identifier();
$quota_option_key = 'bbai_token_quota_' . md5($site_id);
delete_option($quota_option_key);
echo "   ✓ Token quota service reset\n\n";

echo str_repeat("=", 60) . "\n";
echo "✅ Credits Reset Complete!\n\n";
echo "Current status:\n";
echo "  - Used: 0\n";
echo "  - Limit: 50\n";
echo "  - Remaining: 50\n";
echo "  - Plan: free\n\n";
echo "Note: This only resets LOCAL caches. If you're authenticated,\n";
echo "the backend API may still show different usage. To fully reset,\n";
echo "you may need to reset on the backend as well.\n\n";

