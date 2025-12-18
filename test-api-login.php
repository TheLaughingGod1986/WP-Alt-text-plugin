<?php
/**
 * Test API_Client_V2 login
 * Run: docker exec wp-alt-text-plugin-wordpress-1 php /var/www/html/wp-content/plugins/beepbeep-ai-alt-text-generator/test-api-login.php
 */

// Load WordPress
require_once __DIR__ . '/../../../wp-load.php';

echo "\n=== Testing API_Client_V2 Login ===\n\n";

// Check if class exists
if (!class_exists('\BeepBeepAI\AltTextGenerator\API_Client_V2')) {
    echo "❌ API_Client_V2 class not found\n";
    echo "Attempting to load plugin...\n";
    require_once __DIR__ . '/beepbeep-ai-alt-text-generator.php';
}

$api_client = new \BeepBeepAI\AltTextGenerator\API_Client_V2();

echo "Testing login with:\n";
echo "  Email: benoats@gmail.com\n";
echo "  Password: TempPass123\n\n";

// Clear any existing token first
$api_client->clear_token();
echo "Cleared existing token\n\n";

// Attempt login
$result = $api_client->login('benoats@gmail.com', 'TempPass123');

echo "\n=== Login Result ===\n";
if (is_wp_error($result)) {
    echo "❌ LOGIN FAILED (WP_Error)\n";
    echo "  Error Code: " . $result->get_error_code() . "\n";
    echo "  Error Message: " . $result->get_error_message() . "\n";
    echo "  Error Data: " . print_r($result->get_error_data(), true) . "\n";
} else {
    echo "✅ LOGIN SUCCESSFUL\n";
    echo "  Response: " . print_r($result, true) . "\n";
}

// Check stored credentials
echo "\n=== Checking Stored Credentials ===\n";
$token = get_option('optti_jwt_token', '');
$user_data = get_option('optti_user_data', null);
$license_key = get_option('optti_license_key', '');

echo "JWT Token: " . ($token ? "✅ SET (length: " . strlen($token) . ")" : "❌ NOT SET") . "\n";
echo "User Data: " . ($user_data ? "✅ SET" : "❌ NOT SET") . "\n";
if ($user_data && is_array($user_data)) {
    echo "  Email: " . ($user_data['email'] ?? 'N/A') . "\n";
    echo "  Plan: " . ($user_data['plan'] ?? 'N/A') . "\n";
    echo "  License Key in User Data: " . ($user_data['license_key'] ?? 'NOT PRESENT') . "\n";
}
echo "License Key: " . ($license_key ? "✅ SET" : "❌ NOT SET") . "\n";

// Try to use the API client to get usage
echo "\n=== Testing Usage Endpoint ===\n";
$usage = $api_client->get_usage();
if (is_wp_error($usage)) {
    echo "❌ USAGE FAILED\n";
    echo "  Error: " . $usage->get_error_message() . "\n";
} else {
    echo "✅ USAGE SUCCESS\n";
    echo "  Usage: " . print_r($usage, true) . "\n";
}

echo "\n";
