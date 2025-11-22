<?php
/**
 * Simple Credit Reset Script
 * Run from WordPress root: php wp-content/plugins/beepbeep-ai-alt-text-generator/reset-credits-simple.php
 */

// Load WordPress
$wp_load = __DIR__ . '/../../../../wp-load.php';
if (!file_exists($wp_load)) {
    die("❌ Error: Could not find wp-load.php. Please run this from WordPress root:\n   php wp-content/plugins/beepbeep-ai-alt-text-generator/reset-credits-simple.php\n");
}

require_once $wp_load;

if (!defined('ABSPATH')) {
    die("❌ Error: WordPress not loaded correctly.\n");
}

// Load plugin files
if (!defined('BEEPBEEP_AI_PLUGIN_DIR')) {
    require_once ABSPATH . 'wp-content/plugins/beepbeep-ai-alt-text-generator/beepbeep-ai-alt-text-generator.php';
}

echo "🔄 Resetting Credits to 0...\n";
echo str_repeat("=", 60) . "\n\n";

// Clear usage cache
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
\BeepBeepAI\AltTextGenerator\Usage_Tracker::clear_cache();
echo "✓ Cleared usage cache\n";

// Clear token quota service cache
if (file_exists(BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-token-quota-service.php')) {
    require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-token-quota-service.php';
    if (class_exists('\BeepBeepAI\AltTextGenerator\Token_Quota_Service')) {
        \BeepBeepAI\AltTextGenerator\Token_Quota_Service::clear_cache();
        echo "✓ Cleared token quota cache\n";
    }
}

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
    $table_escaped = esc_sql($credit_usage_table);
    $wpdb->query("DELETE FROM `{$table_escaped}`");
    echo "✓ Cleared credit usage logs\n";
}

// Clear usage event logs
if (file_exists(BEEPBEEP_AI_PLUGIN_DIR . 'includes/usage/class-usage-logs.php')) {
    require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/usage/class-usage-logs.php';
    if (class_exists('\BeepBeepAI\AltTextGenerator\Usage\Usage_Logs')) {
        $usage_logs_table = \BeepBeepAI\AltTextGenerator\Usage\Usage_Logs::table();
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $usage_logs_table)) === $usage_logs_table) {
            $table_escaped = esc_sql($usage_logs_table);
            $wpdb->query("DELETE FROM `{$table_escaped}`");
            echo "✓ Cleared usage event logs\n";
        }
    }
}

// Clear token quota service local usage
if (file_exists(BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php')) {
    require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
    if (function_exists('\BeepBeepAI\AltTextGenerator\get_site_identifier')) {
        $site_id = \BeepBeepAI\AltTextGenerator\get_site_identifier();
        $quota_option_key = 'bbai_token_quota_' . md5($site_id);
        delete_option($quota_option_key);
        echo "✓ Cleared token quota option\n";
    }
}

// Clear all related transients
$transients_to_clear = [
    'bbai_usage_cache',
    'bbai_token_quota_*',
    'bbai_stats_cache',
];

foreach ($transients_to_clear as $pattern) {
    if (strpos($pattern, '*') !== false) {
        // Handle wildcard patterns
        global $wpdb;
        $escaped = esc_sql(str_replace('*', '%', $pattern));
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $escaped));
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_' . $escaped));
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_timeout_' . $escaped));
    } else {
        delete_transient($pattern);
        delete_option('_transient_' . $pattern);
        delete_option('_transient_timeout_' . $pattern);
    }
}
echo "✓ Cleared all related transients\n";

// Invalidate stats cache if core class exists
if (file_exists(BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-core.php')) {
    require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-core.php';
    if (class_exists('BBAI_Core')) {
        $core = new BBAI_Core();
        if (method_exists($core, 'invalidate_stats_cache')) {
            $core->invalidate_stats_cache();
            echo "✓ Invalidated stats cache\n";
        }
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ Credits Reset Complete!\n\n";
echo "Current Status:\n";
echo "  - Used: 0\n";
echo "  - Limit: 50\n";
echo "  - Remaining: 50\n";
echo "  - Plan: free\n\n";
echo "You can now test the plugin with full credits.\n";

