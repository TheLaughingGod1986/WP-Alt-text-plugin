<?php
/**
 * Debug Usage Cache
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

require_once OPPTIAI_ALT_PLUGIN_DIR . 'includes/class-usage-tracker.php';
require_once OPPTIAI_ALT_PLUGIN_DIR . 'includes/class-api-client-v2.php';

echo "Debugging Usage Cache\n";
echo str_repeat("=", 50) . "\n\n";

// Check raw cache
$cache_key = 'beepbeepai_usage_cache';
$cached = get_transient($cache_key);
echo "Raw Cache (transient):\n";
print_r($cached);
echo "\n";

// Check get_cached_usage
$cached_usage = BbAI_Usage_Tracker::get_cached_usage();
echo "get_cached_usage():\n";
print_r($cached_usage);
echo "\n";

// Check get_stats_display
$stats = BbAI_Usage_Tracker::get_stats_display();
echo "get_stats_display():\n";
print_r($stats);
echo "\n";

// Check API directly
$api_client = new BbAI_API_Client_V2();
echo "API get_usage():\n";
$api_usage = $api_client->get_usage();
if (is_wp_error($api_usage)) {
    echo "ERROR: " . $api_usage->get_error_message() . "\n";
} else {
    print_r($api_usage);
}

echo "\n" . str_repeat("=", 50) . "\n";


