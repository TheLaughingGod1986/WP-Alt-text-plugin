<?php
/**
 * Force clear all authentication - Direct execution file
 * 
 * Access this directly in your browser at:
 * http://localhost:8080/wp-content/plugins/beepbeep-ai-alt-text-generator/force-clear-auth.php
 * 
 * Or run via WP-CLI:
 * docker exec -it <container> wp eval-file /var/www/html/wp-content/plugins/beepbeep-ai-alt-text-generator/force-clear-auth.php
 */

// Try to load WordPress
$wp_load_paths = array(
    __DIR__ . '/../../../wp-load.php',           // Standard plugin location
    __DIR__ . '/../../../../wp-load.php',        // Nested location
    '/var/www/html/wp-load.php',                 // Docker default
);

$loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    die("Could not find wp-load.php. Please run this from the WordPress context.");
}

// Check if admin
if (!current_user_can('manage_options')) {
    // Allow if running from CLI
    if (php_sapi_name() !== 'cli') {
        wp_die('You must be an administrator to run this script.');
    }
}

echo "<h1>BeepBeep AI - Force Clear Authentication</h1>\n";
echo "<pre>\n";

// All option keys to clear
$options_to_clear = array(
    // New unified keys
    'optti_jwt_token',
    'optti_user_data', 
    'optti_license_key',
    'optti_license_data',
    'optti_site_id',
    'optti_license_snapshot',
    'optti_license_last_check',
    
    // Legacy beepbeepai keys
    'beepbeepai_jwt_token',
    'beepbeepai_user_data',
    'beepbeepai_license_key',
    'beepbeepai_license_data',
    'beepbeepai_site_id',
    
    // Legacy bbai keys
    'bbai_jwt_token',
    'bbai_user_data',
    'bbai_license_key', 
    'bbai_license_data',
    'bbai_site_id',
    
    // Other legacy
    'opptibbai_user_data',
    'opptibbai_site_id',
);

echo "=== Clearing all authentication options ===\n\n";

$cleared_count = 0;
foreach ($options_to_clear as $option_key) {
    $value = get_option($option_key, null);
    $had_value = ($value !== null && $value !== '' && $value !== false);
    delete_option($option_key);
    
    if ($had_value) {
        echo "✅ Cleared: {$option_key}\n";
        $cleared_count++;
    } else {
        echo "⏭️  Already empty: {$option_key}\n";
    }
}

// Also clear transients
$transients_to_clear = array(
    'bbai_token_last_check',
    'bbai_usage_cache',
    'beepbeepai_usage_cache',
    'beepbeepai_token_last_check',
    'optti_user_info',
    'optti_subscription_info',
    'optti_plans',
    'opptibbai_usage_cache',
    'opptibbai_token_last_check',
);

echo "\n=== Clearing transients ===\n\n";
foreach ($transients_to_clear as $transient) {
    delete_transient($transient);
    echo "✅ Cleared transient: {$transient}\n";
}

echo "\n=== Summary ===\n";
echo "Cleared {$cleared_count} option(s)\n";
echo "\n✅ SUCCESS: All authentication has been cleared!\n";
echo "\nYou can now:\n";
echo "1. Go to the plugin settings page\n";
echo "2. Log in with your account credentials\n";
echo "3. Or activate your license key\n";
echo "</pre>\n";

// Redirect after 5 seconds if in browser
if (php_sapi_name() !== 'cli') {
    $admin_url = admin_url('admin.php?page=beepbeep-ai');
    echo "<p>Redirecting to plugin settings in 5 seconds...</p>";
    echo "<p><a href='{$admin_url}'>Click here if not redirected</a></p>";
    echo "<script>setTimeout(function(){ window.location.href = '{$admin_url}'; }, 5000);</script>";
}

