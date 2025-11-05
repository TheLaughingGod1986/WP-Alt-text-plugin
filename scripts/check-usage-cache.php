<?php
/**
 * Check usage cache in database
 */

$wp_load_paths = [
    __DIR__ . '/../../../../wp-load.php',
    '/var/www/html/wp-load.php',
    '/var/www/html/wp-content/plugins/ai-alt-gpt/../../../../wp-load.php',
];

foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

echo "Usage Cache Check:\n";
echo str_repeat("=", 50) . "\n\n";

// Check transient
$cached = get_transient('alttextai_usage_cache');
if ($cached !== false) {
    echo "✓ Transient found in database:\n";
    echo "  Key: alttextai_usage_cache\n";
    echo "  Used: " . ($cached['used'] ?? 'N/A') . "\n";
    echo "  Limit: " . ($cached['limit'] ?? 'N/A') . "\n";
    echo "  Remaining: " . ($cached['remaining'] ?? 'N/A') . "\n";
    echo "  Plan: " . ($cached['plan'] ?? 'N/A') . "\n";
    echo "  Reset Date: " . ($cached['resetDate'] ?? 'N/A') . "\n";
    echo "\n";
} else {
    echo "✗ No transient found (cache expired or never set)\n\n";
}

// Check raw database
global $wpdb;
$transient_key = '_transient_alttextai_usage_cache';
$timeout_key = '_transient_timeout_alttextai_usage_cache';

$transient_value = $wpdb->get_var($wpdb->prepare(
    "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
    $transient_key
));

$timeout_value = $wpdb->get_var($wpdb->prepare(
    "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
    $timeout_key
));

if ($transient_value) {
    $unserialized = maybe_unserialize($transient_value);
    echo "Raw Database Entry:\n";
    echo "  Transient Value: " . print_r($unserialized, true) . "\n";
    echo "  Expires At: " . ($timeout_value ? date('Y-m-d H:i:s', $timeout_value) : 'Never') . "\n";
    echo "  Current Time: " . date('Y-m-d H:i:s', current_time('timestamp')) . "\n";
    if ($timeout_value && $timeout_value < current_time('timestamp')) {
        echo "  ⚠ Status: EXPIRED\n";
    } else {
        echo "  ✓ Status: ACTIVE\n";
    }
} else {
    echo "No raw database entry found\n";
}

echo "\n";
echo "Note: Usage data is cached for 5 minutes.\n";
echo "The actual usage is stored on the backend API server.\n";
