<?php
/**
 * Clear API Error Log
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

echo "Clearing API Error Log\n";
echo str_repeat("=", 50) . "\n\n";

// Get current error count
$errors = get_option('beepbeepai_api_error_log', []);
$count = is_array($errors) ? count($errors) : 0;

echo "Current error count: $count\n\n";

// Clear the log
delete_option('beepbeepai_api_error_log');

echo "✓ API error log cleared\n\n";

// Verify it's cleared
$errors_after = get_option('beepbeepai_api_error_log', []);
if (empty($errors_after)) {
    echo "✓ Verified: Error log is now empty\n";
} else {
    echo "⚠️  Warning: Error log still has data\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "\nNow try generating alt text or refreshing the dashboard.\n";
echo "Any new errors will appear fresh in the log.\n";


