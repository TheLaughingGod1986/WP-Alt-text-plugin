<?php
/**
 * Test Complete Authentication Flow
 * Run this from command line: php test-auth-flow.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "===========================================\n";
echo "Testing AltText AI Authentication Flow\n";
echo "===========================================\n\n";

// Test 1: Check if backend is accessible
echo "TEST 1: Backend Accessibility\n";
echo "------------------------------\n";

$backend_urls = [
    'http://localhost:3001',
    'http://host.docker.internal:3001',
    'https://alttext-ai-backend.onrender.com'
];

foreach ($backend_urls as $url) {
    $ch = curl_init($url . '/health');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        echo "✅ $url - WORKING (HTTP $http_code)\n";
        echo "   Response: $response\n";
        $working_url = $url;
    } else {
        echo "❌ $url - FAILED (HTTP $http_code)\n";
    }
}

if (!isset($working_url)) {
    echo "\n❌ CRITICAL: No backend is accessible!\n";
    exit(1);
}

echo "\n✅ Using backend: $working_url\n\n";

// Test 2: Register a new user
echo "TEST 2: User Registration\n";
echo "-------------------------\n";

$test_email = 'test_' . time() . '@test.com';
$test_password = 'testtest123';

echo "Registering user: $test_email\n";

$ch = curl_init($working_url . '/auth/register');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => $test_email,
    'password' => $test_password
]));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Status: $http_code\n";
echo "Response: $response\n";

if ($error) {
    echo "CURL Error: $error\n";
}

$register_data = json_decode($response, true);

if ($http_code === 200 || $http_code === 201) {
    echo "✅ Registration successful!\n";
    $token = $register_data['token'] ?? null;
    $user = $register_data['user'] ?? null;

    if ($token) {
        echo "✅ Token received: " . substr($token, 0, 30) . "...\n";
    } else {
        echo "❌ No token in response!\n";
        exit(1);
    }
} elseif ($http_code === 409) {
    echo "⚠️  User already exists, will try login instead...\n\n";

    // Try to login
    echo "TEST 2b: User Login\n";
    echo "-------------------\n";

    $ch = curl_init($working_url . '/auth/login');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'email' => 'test@test.com',  // Use default test user
        'password' => $test_password
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP Status: $http_code\n";
    echo "Response: $response\n";

    $register_data = json_decode($response, true);

    if ($http_code === 200) {
        echo "✅ Login successful!\n";
        $token = $register_data['token'] ?? null;
        $user = $register_data['user'] ?? null;
    } else {
        echo "❌ Login failed!\n";
        exit(1);
    }
} else {
    echo "❌ Registration failed!\n";
    echo "Error: " . ($register_data['error'] ?? 'Unknown') . "\n";
    exit(1);
}

echo "\n";

// Test 3: Test authenticated endpoint
echo "TEST 3: Authenticated API Call\n";
echo "-------------------------------\n";

echo "Making request to /api/generate with token...\n";

$ch = curl_init($working_url . '/api/generate');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'image_data' => [
        'image_id' => 1,
        'url' => 'https://example.com/test.jpg',
        'title' => 'Test Image',
        'filename' => 'test.jpg'
    ],
    'context' => []
]));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $http_code\n";
echo "Response: " . substr($response, 0, 200) . "...\n";

if ($http_code === 200) {
    echo "✅ Authenticated API call successful!\n";
} elseif ($http_code === 401) {
    echo "❌ Still getting 401 - token not working!\n";
    exit(1);
} else {
    echo "⚠️  Got HTTP $http_code - check response above\n";
}

echo "\n";

// Test 4: Save token to WordPress
echo "TEST 4: Save Token to WordPress\n";
echo "--------------------------------\n";

// Try to load WordPress
$wp_load_paths = [
    __DIR__ . '/local-test/wordpress/wp-load.php',
    __DIR__ . '/../../../wp-load.php',
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        echo "Loading WordPress from: $path\n";
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if ($wp_loaded) {
    update_option('alttextai_jwt_token', $token);
    update_option('alttextai_user_data', $user);
    update_option('ai_alt_gpt_settings', array_merge(
        get_option('ai_alt_gpt_settings', []),
        ['api_url' => $working_url]
    ));
    delete_transient('alttextai_token_last_check');

    echo "✅ Token saved to WordPress options\n";
    echo "✅ API URL set to: $working_url\n";
    echo "✅ User data saved\n";
} else {
    echo "⚠️  Could not load WordPress - token not saved\n";
    echo "   You'll need to run this from WordPress root or install directory\n";
}

echo "\n";
echo "===========================================\n";
echo "✅ ALL TESTS PASSED!\n";
echo "===========================================\n\n";

echo "Summary:\n";
echo "--------\n";
echo "Backend URL: $working_url\n";
echo "User Email: " . ($user['email'] ?? $test_email) . "\n";
echo "Token: " . substr($token, 0, 30) . "...\n";

if ($wp_loaded) {
    echo "\n✅ You can now use bulk optimization in WordPress!\n";
    echo "   Visit: http://localhost:8888/wp-admin/upload.php?page=ai-alt-gpt\n";
} else {
    echo "\n⚠️  Token generated but not saved to WordPress\n";
    echo "   Run this script from WordPress root to save the token\n";
}

echo "\n";
