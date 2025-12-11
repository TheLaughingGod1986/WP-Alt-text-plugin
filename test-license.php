<?php
/**
 * Test License Setup
 *
 * Usage: wp eval-file test-license.php
 * Or copy this into WordPress admin → Tools → Theme/Plugin Editor
 */

// Test license key
$test_license_key = '24c93235-1053-4922-b337-9866aeb76dcc';

echo "🔍 Checking License Configuration\n\n";

// Check current license key
$current_key = get_option('optti_license_key', '');
echo "Current License Key: " . ($current_key ?: 'NOT SET') . "\n";

// Check license data
$license_data = get_option('optti_license_data', null);
echo "License Data: " . ($license_data ? json_encode($license_data) : 'NOT SET') . "\n\n";

// Update if needed
if (empty($current_key)) {
    echo "📝 Setting test license key...\n";
    update_option('optti_license_key', $test_license_key);
    echo "✅ License key set: $test_license_key\n\n";
} else {
    echo "✅ License key already configured\n\n";
}

// Test API connection
echo "📡 Testing API Connection...\n";

$response = wp_remote_get('https://alttext-ai-backend.onrender.com/health', [
    'headers' => [
        'X-License-Key' => $test_license_key,
    ],
    'timeout' => 10,
]);

if (is_wp_error($response)) {
    echo "❌ API Error: " . $response->get_error_message() . "\n";
} else {
    $status = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    echo "Status: $status\n";
    echo "Response: $body\n";
}

echo "\n📋 Summary:\n";
echo "License Key: $test_license_key\n";
echo "Backend URL: https://alttext-ai-backend.onrender.com\n";
echo "\nNext: Test image generation in the plugin!\n";
