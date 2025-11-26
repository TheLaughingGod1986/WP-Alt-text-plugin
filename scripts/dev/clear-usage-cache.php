<?php
/**
 * Clear Usage Cache and Force Refresh from API
 * Run from WordPress root: php wp-content/plugins/beepbeep-ai-alt-text-generator/clear-usage-cache.php
 */

// Try multiple paths to find wp-load.php
$wp_load_paths = [
    __DIR__ . '/../../../../wp-load.php',
    __DIR__ . '/../../../wp-load.php',
    '/var/www/html/wp-load.php',
    dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php',
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
    die("❌ Error: Could not find wp-load.php. Tried paths:\n" . implode("\n", $wp_load_paths) . "\n\nPlease run from WordPress root or ensure wp-load.php exists.\n");
}

if (!defined('ABSPATH')) {
    die("❌ Error: WordPress not loaded.\n");
}

// Load plugin
if (!defined('BEEPBEEP_AI_PLUGIN_DIR')) {
    require_once ABSPATH . 'wp-content/plugins/beepbeep-ai-alt-text-generator/beepbeep-ai-alt-text-generator.php';
}

echo "🔄 Clearing Usage Cache and Refreshing from API...\n";
echo str_repeat("=", 60) . "\n\n";

// Clear usage cache
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
\BeepBeepAI\AltTextGenerator\Usage_Tracker::clear_cache();
echo "✓ Cleared usage cache transient\n";

// Delete all related transients
$transients = [
    'bbai_usage_cache',
    'bbai_stats_cache',
];

foreach ($transients as $key) {
    delete_transient($key);
    delete_option('_transient_' . $key);
    delete_option('_transient_timeout_' . $key);
    echo "✓ Cleared transient: {$key}\n";
}

// Force refresh from API
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php';
$api_client = new \BeepBeepAI\AltTextGenerator\API_Client_V2();

if ($api_client->is_authenticated()) {
    echo "\n📡 Fetching fresh usage data from API...\n";
    $usage = $api_client->get_usage();
    
    if (is_array($usage) && !empty($usage)) {
        \BeepBeepAI\AltTextGenerator\Usage_Tracker::update_usage($usage);
        echo "✓ Refreshed from API:\n";
        echo "  Used: " . ($usage['used'] ?? 0) . "\n";
        echo "  Limit: " . ($usage['limit'] ?? 50) . "\n";
        echo "  Remaining: " . ($usage['remaining'] ?? 50) . "\n";
        echo "  Plan: " . ($usage['plan'] ?? 'free') . "\n";
    } else if (is_wp_error($usage)) {
        echo "⚠️  API Error: " . $usage->get_error_message() . "\n";
        echo "  Error Code: " . $usage->get_error_code() . "\n";
    } else {
        echo "⚠️  Could not get usage from API (empty response)\n";
    }
} else {
    echo "⚠️  Not authenticated - cannot refresh from API\n";
    echo "  Cache has been cleared, but you'll need to refresh in WordPress admin\n";
}

// Clear token quota cache if exists
if (file_exists(BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-token-quota-service.php')) {
    require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-token-quota-service.php';
    if (class_exists('\BeepBeepAI\AltTextGenerator\Token_Quota_Service')) {
        \BeepBeepAI\AltTextGenerator\Token_Quota_Service::clear_cache();
        echo "✓ Cleared token quota cache\n";
    }
}

// Invalidate stats cache
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
echo "✅ Cache Cleared and Refreshed!\n\n";
echo "Now refresh your WordPress admin page and try generating again.\n";
echo "The plugin should now show the correct credits (0/50).\n";

