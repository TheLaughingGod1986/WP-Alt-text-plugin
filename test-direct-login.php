<?php
/**
 * Test direct login to see the actual error
 *
 * Run this: docker exec wp-alt-text-plugin-wordpress-1 php /var/www/html/wp-content/plugins/beepbeep-ai-alt-text-generator/test-direct-login.php
 */

// Load WordPress
require_once __DIR__ . '/../../../wp-load.php';

echo "\n=== Testing Direct Login ===\n\n";

// Load the framework
require_once __DIR__ . '/framework/loader.php';

// Create API client
$api_client = new \BeepBeepAI\Framework\ApiClient();

echo "Testing login with:\n";
echo "  Email: benoats@gmail.com\n";
echo "  Password: TempPass123\n\n";

$result = $api_client->login('benoats@gmail.com', 'TempPass123');

if (is_wp_error($result)) {
    echo "❌ LOGIN FAILED (WP_Error)\n";
    echo "  Error Code: " . $result->get_error_code() . "\n";
    echo "  Error Message: " . $result->get_error_message() . "\n";
    echo "  Error Data: " . print_r($result->get_error_data(), true) . "\n";
} else {
    echo "✅ LOGIN SUCCESSFUL\n";
    echo "  Response: " . print_r($result, true) . "\n";

    // Check if token was saved
    $token = $api_client->get_token();
    $license_key = $api_client->get_license_key();

    echo "\n=== Stored Credentials After Login ===\n";
    echo "  JWT Token: " . ($token ? "✅ SET (length: " . strlen($token) . ")" : "❌ NOT SET") . "\n";
    echo "  License Key: " . ($license_key ? "✅ SET" : "❌ NOT SET") . "\n";
}

echo "\n";
