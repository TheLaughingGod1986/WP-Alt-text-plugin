<?php
/**
 * Check License Storage Diagnostic
 *
 * Run this to see what's stored in WordPress database
 * Usage: Add to functions.php temporarily, or run via wp-cli
 */

// Check all possible license-related options
$options_to_check = [
    'optti_license_key',
    'optti_license_data',
    'beepbeepai_license_key',  // Legacy
    'beepbeepai_jwt_token',     // Legacy
];

echo "=== LICENSE STORAGE DIAGNOSTIC ===\n\n";

foreach ($options_to_check as $option_name) {
    $value = get_option($option_name, null);

    if ($value === null) {
        echo "❌ $option_name: NOT SET\n";
    } else if ($value === '') {
        echo "⚠️  $option_name: EMPTY STRING\n";
    } else {
        // Don't show full value for security
        $preview = is_string($value) ? substr($value, 0, 20) . '...' : json_encode($value);
        echo "✅ $option_name: " . $preview . "\n";
    }
}

echo "\n=== TESTING API CLIENT ===\n\n";

// Test the framework API client
if (class_exists('\Optti\Framework\ApiClient')) {
    $api = \Optti\Framework\ApiClient::instance();
    $license_key = $api->get_license_key();

    if (empty($license_key)) {
        echo "❌ ApiClient::get_license_key() returns EMPTY\n";
    } else {
        echo "✅ ApiClient::get_license_key(): " . substr($license_key, 0, 20) . "...\n";
    }
} else {
    echo "❌ ApiClient class not found\n";
}

echo "\n=== RECOMMENDED ACTIONS ===\n\n";

$license_key = get_option('optti_license_key');
if (empty($license_key)) {
    echo "1. Set the license key:\n";
    echo "   update_option('optti_license_key', '24c93235-1053-4922-b337-9866aeb76dcc');\n\n";
    echo "2. Or activate via plugin settings page\n\n";

    // Auto-fix option (commented out for safety)
    echo "3. Uncomment below to auto-fix:\n";
    echo "   // update_option('optti_license_key', '24c93235-1053-4922-b337-9866aeb76dcc');\n";
} else {
    echo "✅ License key is stored correctly!\n";
}
