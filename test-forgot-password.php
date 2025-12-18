<?php
/**
 * Test script for forgot password endpoint
 * Run this from command line: php test-forgot-password.php
 */

// Test the backend forgot password endpoint directly
// For Docker: use host.docker.internal to access host machine's localhost
// For direct access: use localhost
$api_url = 'http://localhost:10000'; // Change if your backend is on different port
$endpoint = '/auth/forgot-password';
$email = 'benoats@gmail.com'; // Change to your test email

$url = rtrim($api_url, '/') . '/' . ltrim($endpoint, '/');

$data = array(
    'email' => $email,
    'siteUrl' => 'http://localhost:8080/wp-admin/admin.php?page=optti'
);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json'
));
curl_setopt($ch, CURLOPT_VERBOSE, true);

echo "Testing forgot password endpoint...\n";
echo "URL: $url\n";
echo "Data: " . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);

if ($curl_error) {
    echo "CURL Error: $curl_error\n";
} else {
    echo "HTTP Status: $http_code\n";
    echo "Response: $response\n\n";
    
    $decoded = json_decode($response, true);
    if ($decoded) {
        echo "Decoded Response:\n";
        echo json_encode($decoded, JSON_PRETTY_PRINT) . "\n";
    }
}

curl_close($ch);

