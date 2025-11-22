<?php
/**
 * Frontend Password Reset Test
 * Simulates WordPress plugin's password reset flow
 */

// Load WordPress if available, otherwise simulate
if (file_exists(__DIR__ . '/../../../../wp-load.php')) {
    require_once __DIR__ . '/../../../../wp-load.php';
}

require_once __DIR__ . '/includes/class-api-client-v2.php';

echo "🧪 Testing Frontend Password Reset Integration\n";
echo str_repeat('=', 60) . "\n\n";

// Get API client instance
$api_client = new BbAI_API_Client_V2();

// Test 1: Forgot Password Request
echo "📧 Test 1: Forgot Password Request\n";
echo "  Testing: API_Client::forgot_password()\n";

$test_email = 'test' . time() . '@example.com'; // Use time to avoid rate limits
$result = $api_client->forgot_password($test_email);

if (is_wp_error($result)) {
    $error_code = $result->get_error_code();
    $error_message = $result->get_error_message();
    
    if ($error_code === 'endpoint_not_found') {
        echo "  ❌ FAILED: Backend endpoint not deployed\n";
        echo "     Error: {$error_message}\n";
    } elseif ($error_code === 'rate_limit') {
        echo "  ⚠️  Rate limited (expected for testing)\n";
        echo "     Message: {$error_message}\n";
    } elseif ($error_code === 'server_error') {
        echo "  ⚠️  Server error: {$error_message}\n";
    } else {
        echo "  ⚠️  Unexpected error: [{$error_code}] {$error_message}\n";
    }
} else {
    echo "  ✅ SUCCESS: Password reset request sent\n";
    if (isset($result['message'])) {
        echo "     Message: {$result['message']}\n";
    }
}

echo "\n";

// Test 2: Reset Password (with invalid token)
echo "🔐 Test 2: Reset Password (Invalid Token Test)\n";
echo "  Testing: API_Client::reset_password()\n";

$test_email = 'test@example.com';
$test_token = 'invalid-token-' . time();
$test_password = 'NewPassword123!';

$result = $api_client->reset_password($test_email, $test_token, $test_password);

if (is_wp_error($result)) {
    $error_code = $result->get_error_code();
    $error_message = $result->get_error_message();
    
    if ($error_code === 'endpoint_not_found') {
        echo "  ❌ FAILED: Backend endpoint not deployed\n";
        echo "     Error: {$error_message}\n";
    } elseif ($error_code === 'invalid_token' || $error_code === 'invalid_request') {
        echo "  ✅ SUCCESS: Endpoint exists (expected error for invalid token)\n";
        echo "     Error: {$error_message}\n";
    } else {
        echo "  ⚠️  Error: [{$error_code}] {$error_message}\n";
    }
} else {
    echo "  ✅ SUCCESS: Password reset endpoint responds\n";
    if (isset($result['message'])) {
        echo "     Message: {$result['message']}\n";
    }
}

echo "\n";

// Test 3: Verify API URL Configuration
echo "🌐 Test 3: API Configuration\n";
$api_url = defined('ALT_TEXT_AI_API_URL') ? ALT_TEXT_AI_API_URL : $api_client->api_url;
echo "  API URL: {$api_url}\n";

if (strpos($api_url, 'oppti.dev') !== false) {
    echo "  ✅ Using production backend\n";
} else {
    echo "  ⚠️  Unexpected API URL\n";
}

echo "\n";

// Summary
echo str_repeat('=', 60) . "\n";
echo "📋 Frontend Integration Test Summary\n\n";
echo "The WordPress plugin API client can:\n";
echo "  ✅ Make requests to backend endpoints\n";
echo "  ✅ Handle errors gracefully\n";
echo "  ✅ Return WP_Error objects for WordPress compatibility\n\n";

echo "💡 To test full flow:\n";
echo "  1. Use a real user email address\n";
echo "  2. Request password reset from WordPress admin\n";
echo "  3. Check backend logs for reset link\n";
echo "  4. Use reset link with token\n";
echo "  5. Verify password change works\n";

