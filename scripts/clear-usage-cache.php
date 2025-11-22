<?php
/**
 * Clear Usage Cache and Force Refresh from API
 */

$wp_load_paths = [
    __DIR__ . '/../../../../wp-load.php',
    '/var/www/html/wp-load.php',
    '/var/www/html/wp-content/plugins/opptiai-alt/../../../../wp-load.php',
];

foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

echo "Clearing usage cache and refreshing from API...\n\n";

// Clear cache
BbAI_Usage_Tracker::clear_cache();
echo "✓ Cache cleared\n";

// Force refresh from API
require_once OPPTIAI_ALT_PLUGIN_DIR . 'includes/class-api-client-v2.php';
$api_client = new BbAI_API_Client_V2();

if ($api_client->is_authenticated()) {
    $usage = $api_client->get_usage();
    if (is_array($usage) && !empty($usage)) {
        BbAI_Usage_Tracker::update_usage($usage);
        echo "✓ Refreshed from API:\n";
        echo "  Used: " . ($usage['used'] ?? 0) . "\n";
        echo "  Limit: " . ($usage['limit'] ?? 50) . "\n";
        echo "  Remaining: " . ($usage['remaining'] ?? 50) . "\n";
        echo "  Plan: " . ($usage['plan'] ?? 'free') . "\n";
    } else {
        echo "✗ Failed to get usage from API\n";
        if (is_wp_error($usage)) {
            echo "  Error: " . $usage->get_error_message() . "\n";
        }
    }
} else {
    echo "⚠️  Not authenticated - cannot refresh from API\n";
}

echo "\nDone!\n";
