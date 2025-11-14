<?php
/**
 * Check WordPress Users
 */

$wp_load_paths = [
    __DIR__ . '/../../../../wp-load.php',
    __DIR__ . '/../../../wp-load.php',
    '/var/www/html/wp-load.php',
    '/var/www/html/wp-content/plugins/opptiai-alt/../../../../wp-load.php',
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die("Error: Could not find wp-load.php\n");
}

// Get all users
$users = get_users(['role' => 'administrator']);

echo "WordPress Admin Users:\n";
echo str_repeat("=", 50) . "\n";

foreach ($users as $user) {
    echo "ID: {$user->ID}\n";
    echo "Username: {$user->user_login}\n";
    echo "Email: {$user->user_email}\n";
    echo "Display Name: {$user->display_name}\n";
    echo "Roles: " . implode(', ', $user->roles) . "\n";
    echo str_repeat("-", 50) . "\n";
}

// Test authentication
echo "\nTesting authentication:\n";
$test_user = wp_authenticate('black', 'black');
if (is_wp_error($test_user)) {
    echo "❌ Authentication failed: " . $test_user->get_error_message() . "\n";
} else {
    echo "✓ Authentication successful for user: {$test_user->user_login}\n";
}
