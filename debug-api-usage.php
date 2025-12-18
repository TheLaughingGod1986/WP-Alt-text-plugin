<?php
/**
 * Debug script to check what the API returns for usage
 * Run via: wp eval-file debug-api-usage.php (in Local's shell)
 */

// Try to find WordPress
$wp_paths = [
    '/Users/benjaminoats/Local Sites/sandbox/app/public/wp-load.php',
];

$wp_loaded = false;
foreach ($wp_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die("Could not find WordPress.\n");
}

if (!defined('BEEPBEEP_AI_PLUGIN_DIR')) {
    // Find the plugin directory
    $plugin_dir = WP_PLUGIN_DIR . '/beepbeep-ai-alt-text-generator/';
    if (!is_dir($plugin_dir)) {
        $plugin_dir = __DIR__ . '/';
    }
    define('BEEPBEEP_AI_PLUGIN_DIR', $plugin_dir);
}

echo "=== API Usage Debug ===\n\n";

// Load API client
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php';
$api_client = new \BeepBeepAI\AltTextGenerator\API_Client_V2();

echo "1. Calling API get_usage()...\n";
$usage = $api_client->get_usage();

echo "\n2. Raw API Response:\n";
print_r($usage);

echo "\n3. Token Quota Service:\n";
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-token-quota-service.php';
\BeepBeepAI\AltTextGenerator\Token_Quota_Service::clear_cache();
$quota = \BeepBeepAI\AltTextGenerator\Token_Quota_Service::get_site_quota(true);
print_r($quota);

echo "\n4. Usage Tracker Stats:\n";
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
\BeepBeepAI\AltTextGenerator\Usage_Tracker::clear_cache();
$stats = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_stats_display(true);
echo "Used: " . ($stats['used'] ?? 'N/A') . "\n";
echo "Limit: " . ($stats['limit'] ?? 'N/A') . "\n";
echo "Remaining: " . ($stats['remaining'] ?? 'N/A') . "\n";
echo "Plan: " . ($stats['plan'] ?? 'N/A') . "\n";

echo "\n✅ Done!\n";
