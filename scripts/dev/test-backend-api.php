<?php
/**
 * Test Backend API Endpoints Directly
 */

$base_url = 'https://oppti.dev/api';

echo "Testing Backend API Endpoints\n";
echo str_repeat("=", 50) . "\n\n";

// Get token from WordPress
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

$token = get_option('beepbeepai_jwt_token', '');
if (empty($token)) {
    $token = get_option('beepbeepai_jwt_token', '');
}

if (empty($token)) {
    echo "❌ No token found. Please authenticate first.\n";
    exit(1);
}

echo "✓ Token found\n\n";

// Test /usage endpoint
echo "Testing /usage endpoint...\n";
$ch = curl_init($base_url . '/usage');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "  Status Code: $http_code\n";
if ($http_code === 200) {
    $data = json_decode($response, true);
    echo "  ✓ SUCCESS\n";
    echo "  Used: " . ($data['usage']['used'] ?? 'N/A') . "\n";
    echo "  Limit: " . ($data['usage']['limit'] ?? 'N/A') . "\n";
    echo "  Remaining: " . ($data['usage']['remaining'] ?? 'N/A') . "\n";
} else {
    echo "  ❌ FAILED\n";
    echo "  Response: " . substr($response, 0, 200) . "\n";
}

echo "\n";

// Test /api/generate endpoint (mock)
echo "Testing /api/generate endpoint (with mock data)...\n";
$ch = curl_init($base_url . '/api/generate');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'image_url' => 'https://example.com/test.jpg',
    'attachment_id' => 999
]));
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "  Status Code: $http_code\n";
if ($http_code === 200 || $http_code === 201) {
    echo "  ✓ SUCCESS\n";
    $data = json_decode($response, true);
    echo "  Response: " . substr(json_encode($data), 0, 200) . "\n";
} else {
    echo "  ❌ FAILED\n";
    echo "  Response: " . substr($response, 0, 300) . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";

