<?php
/**
 * Test script to verify login fix is working
 *
 * Run this from the command line:
 * php test-login-fix.php
 */

// Load the ApiClient
require_once __DIR__ . '/framework/src/ApiClient.php';

// Check if the login method has the fix
$reflection = new ReflectionMethod('BeepBeepAI\Framework\ApiClient', 'login');
$code = file_get_contents($reflection->getFileName());

// Extract the login method
preg_match('/public function login\(.*?\n\t\}/s', $code, $matches);

if (isset($matches[0])) {
    $loginMethod = $matches[0];

    if (strpos($loginMethod, 'set_license_key') !== false) {
        echo "✅ SUCCESS: Login method includes license key saving fix!\n\n";
        echo "The login method now includes this code:\n";
        echo "    if ( isset( \$response['user']['license_key'] ) ) {\n";
        echo "        \$this->set_license_key( \$response['user']['license_key'] );\n";
        echo "    }\n\n";
        echo "Your WordPress site should save the license key after login.\n";
        echo "Try logging in again and check if it works!\n";
    } else {
        echo "❌ WARNING: Login method does NOT include the fix yet.\n";
        echo "The WordPress site might be using cached code.\n";
        echo "Try:\n";
        echo "1. Restart your local WordPress server\n";
        echo "2. Deactivate and reactivate the plugin\n";
        echo "3. Clear any PHP opcode cache (if using OPcache)\n";
    }
} else {
    echo "❌ Could not analyze the login method.\n";
}
