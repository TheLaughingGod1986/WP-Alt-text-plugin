<?php
/**
 * Create Test User and JWT Token
 *
 * This script creates a test user in the Phase 2 backend and stores the JWT token in WordPress.
 */

// Load WordPress
require_once __DIR__ . '/local-test/wordpress/wp-load.php';

// Backend API URL
$api_url = 'http://host.docker.internal:3001';

echo "Creating test user...\n\n";

// Create a test user via the backend API
$test_email = 'test@test.com';
$test_password = 'testtest123';

$response = wp_remote_post($api_url . '/auth/register', [
    'headers' => ['Content-Type' => 'application/json'],
    'body' => json_encode([
        'email' => $test_email,
        'password' => $test_password
    ]),
    'timeout' => 30
]);

if (is_wp_error($response)) {
    echo "❌ Error: " . $response->get_error_message() . "\n";
    exit(1);
}

$status_code = wp_remote_retrieve_response_code($response);
$body = wp_remote_retrieve_body($response);
$data = json_decode($body, true);

echo "Status Code: $status_code\n";
echo "Response: " . print_r($data, true) . "\n\n";

if ($status_code === 200 || $status_code === 201) {
    // Success! Store the token
    $token = $data['token'] ?? null;
    $user = $data['user'] ?? null;

    if ($token) {
        update_option('alttextai_jwt_token', $token);
        update_option('alttextai_user_data', $user);
        delete_transient('alttextai_token_last_check');

        echo "✅ SUCCESS!\n\n";
        echo "User created and authenticated:\n";
        echo "Email: $test_email\n";
        echo "Token: " . substr($token, 0, 20) . "...\n\n";
        echo "Token has been saved to WordPress.\n";
        echo "You can now use bulk optimization!\n\n";
        echo "Visit: http://localhost:8888/wp-admin/upload.php?page=ai-alt-gpt\n";
    } else {
        echo "❌ Error: No token in response\n";
    }
} elseif ($status_code === 409) {
    // User already exists, try to login
    echo "User already exists, attempting login...\n\n";

    $login_response = wp_remote_post($api_url . '/auth/login', [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode([
            'email' => $test_email,
            'password' => $test_password
        ]),
        'timeout' => 30
    ]);

    if (!is_wp_error($login_response)) {
        $login_status = wp_remote_retrieve_response_code($login_response);
        $login_body = wp_remote_retrieve_body($login_response);
        $login_data = json_decode($login_body, true);

        if ($login_status === 200) {
            $token = $login_data['token'] ?? null;
            $user = $login_data['user'] ?? null;

            if ($token) {
                update_option('alttextai_jwt_token', $token);
                update_option('alttextai_user_data', $user);
                delete_transient('alttextai_token_last_check');

                echo "✅ SUCCESS!\n\n";
                echo "Logged in successfully:\n";
                echo "Email: $test_email\n";
                echo "Token: " . substr($token, 0, 20) . "...\n\n";
                echo "Token has been saved to WordPress.\n";
                echo "You can now use bulk optimization!\n\n";
                echo "Visit: http://localhost:8888/wp-admin/upload.php?page=ai-alt-gpt\n";
            }
        } else {
            echo "❌ Login failed: $login_status\n";
            echo "Response: " . $login_body . "\n";
        }
    }
} else {
    echo "❌ Failed to create user\n";
    echo "Status: $status_code\n";
    echo "Error: " . ($data['error'] ?? 'Unknown error') . "\n";
}
