<?php
/**
 * Test API Authentication Token
 * Checks if the stored JWT token is valid
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

require_once AI_ALT_GPT_PLUGIN_DIR . 'includes/class-api-client-v2.php';

echo "Testing API Authentication\n";
echo str_repeat("=", 50) . "\n\n";

$api_client = new AltText_AI_API_Client_V2();

// Check if token exists
$token = get_option('alttextai_jwt_token', '');
if (empty($token)) {
    echo "❌ No token found in WordPress options\n";
    exit(1);
}

echo "✓ Token found in WordPress\n";
echo "  Token preview: " . substr($token, 0, 20) . "...\n\n";

// Check if authenticated
$is_authenticated = $api_client->is_authenticated();
echo "Is Authenticated: " . ($is_authenticated ? "✓ Yes" : "✗ No") . "\n\n";

// Try to get user info
echo "Testing /auth/me endpoint...\n";
$user_info = $api_client->get_user_info();

if (is_wp_error($user_info)) {
    echo "❌ Error getting user info:\n";
    echo "   Code: " . $user_info->get_error_code() . "\n";
    echo "   Message: " . $user_info->get_error_message() . "\n";
    exit(1);
}

echo "✓ User info retrieved:\n";
echo "   Email: " . ($user_info['email'] ?? 'N/A') . "\n";
echo "   Plan: " . ($user_info['plan'] ?? 'N/A') . "\n";
echo "   ID: " . ($user_info['id'] ?? 'N/A') . "\n\n";

// Try to get usage
echo "Testing /usage endpoint...\n";
$usage = $api_client->get_usage();

if (is_wp_error($usage)) {
    echo "❌ Error getting usage:\n";
    echo "   Code: " . $usage->get_error_code() . "\n";
    echo "   Message: " . $usage->get_error_message() . "\n";
    exit(1);
}

echo "✓ Usage retrieved:\n";
echo "   Used: " . ($usage['used'] ?? 0) . "\n";
echo "   Limit: " . ($usage['limit'] ?? 50) . "\n";
echo "   Remaining: " . ($usage['remaining'] ?? 50) . "\n";
echo "   Plan: " . ($usage['plan'] ?? 'free') . "\n\n";

echo str_repeat("=", 50) . "\n";
echo "✅ API Authentication is VALID\n";

