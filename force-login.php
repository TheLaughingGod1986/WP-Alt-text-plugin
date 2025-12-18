<?php
/**
 * Force login to bypass any JavaScript issues
 * Run: docker exec wp-alt-text-plugin-wordpress-1 php /var/www/html/wp-content/plugins/beepbeep-ai-alt-text-generator/force-login.php
 */

// Load WordPress
require_once __DIR__ . '/../../../wp-load.php';

echo "\n=== Force Login ===\n\n";

$api_client = new \BeepBeepAI\AltTextGenerator\API_Client_V2();

// Clear any existing credentials
$api_client->clear_token();
delete_option('optti_user_data');
echo "Cleared existing credentials\n\n";

// Login
echo "Logging in...\n";
$result = $api_client->login('benoats@gmail.com', 'TempPass123');

if (is_wp_error($result)) {
    echo "❌ Login failed: " . $result->get_error_message() . "\n";
    exit(1);
}

echo "✅ Login successful!\n\n";

// Verify credentials were saved
$token = get_option('optti_jwt_token', '');
$user_data = get_option('optti_user_data', null);

echo "=== Verification ===\n";
echo "JWT Token: " . ($token ? "✅ SAVED" : "❌ NOT SAVED") . "\n";
echo "User Data: " . ($user_data ? "✅ SAVED" : "❌ NOT SAVED") . "\n";

if ($user_data) {
    echo "\nUser Info:\n";
    echo "  Email: " . ($user_data['email'] ?? 'N/A') . "\n";
    echo "  Plan: " . ($user_data['plan'] ?? 'N/A') . "\n";
}

echo "\n✅ You are now logged in!\n";
echo "Refresh your browser to see the authenticated state.\n\n";
