<?php
/**
 * WordPress Admin Setup Script
 * Sets the admin username and password to "black"
 */

// Try multiple paths to wp-load.php
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

// Check if WordPress is already installed
if (!get_option('siteurl')) {
    die("Error: WordPress is not installed yet. Please complete the WordPress installation first.\n");
}

$username = 'black';
$password = 'black';
$email = 'black@example.com';

// Check if user already exists
$user = get_user_by('login', $username);

if ($user) {
    // Update existing user - use wp_set_password for proper hashing
    wp_set_password($password, $user->ID);
    wp_update_user([
        'ID' => $user->ID,
        'user_email' => $email,
    ]);
    
    // Ensure user is an administrator
    $user = new WP_User($user->ID);
    $user->set_role('administrator');
    
    // Clear user cache
    clean_user_cache($user);
    
    echo "✓ Updated existing admin user '{$username}' with password '{$password}'\n";
    echo "  User ID: {$user->ID}\n";
} else {
    // First, try to get the default admin user (usually ID 1)
    $admin_user = get_user_by('id', 1);
    
    if ($admin_user) {
        // Update the default admin user
        wp_set_password($password, 1);
        wp_update_user([
            'ID' => 1,
            'user_login' => $username,
            'user_nicename' => $username,
            'display_name' => $username,
            'user_email' => $email,
        ]);
        
        $admin_user = new WP_User(1);
        $admin_user->set_role('administrator');
        
        clean_user_cache($admin_user);
        
        echo "✓ Updated default admin user (ID 1) to username '{$username}' with password '{$password}'\n";
    } else {
        // Create new admin user
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            die("Error creating user: " . $user_id->get_error_message() . "\n");
        }
        
        // Set as administrator
        $user = new WP_User($user_id);
        $user->set_role('administrator');
        
        clean_user_cache($user);
        
        echo "✓ Created new admin user '{$username}' with password '{$password}'\n";
        echo "  User ID: {$user_id}\n";
    }
}

// Also update the default admin user (ID 1) if it exists and is different
$default_admin = get_user_by('id', 1);
if ($default_admin && $default_admin->user_login !== $username) {
    wp_set_password($password, 1);
    wp_update_user([
        'ID' => 1,
        'user_login' => $username,
        'user_nicename' => $username,
        'display_name' => $username,
        'user_email' => $email,
    ]);
    $default_admin = new WP_User(1);
    $default_admin->set_role('administrator');
    clean_user_cache($default_admin);
    echo "✓ Also updated default admin user (ID 1) to '{$username}'\n";
}

// Verify the password works
$verify_user = wp_authenticate($username, $password);
if (is_wp_error($verify_user)) {
    echo "⚠ Warning: Password verification failed: " . $verify_user->get_error_message() . "\n";
} else {
    echo "✓ Password verification successful\n";
}

echo "\n";
echo "WordPress Admin Credentials:\n";
echo "  Username: {$username}\n";
echo "  Password: {$password}\n";
echo "  Login URL: " . admin_url() . "\n";
echo "\n";
