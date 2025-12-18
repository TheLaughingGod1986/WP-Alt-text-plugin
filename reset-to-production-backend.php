<?php
/**
 * Reset to production backend URL
 * Access via: http://localhost:8080/wp-content/plugins/beepbeep-ai-alt-text-generator/reset-to-production-backend.php
 * 
 * Use this to switch back to production backend after testing locally
 */

// Try to load WordPress
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

if (!defined('ABSPATH')) {
    die('WordPress not loaded. Make sure you access this via WordPress URL.');
}

// Check user permissions
if (!current_user_can('manage_options')) {
    wp_die('Sorry, you are not allowed to access this page.');
}

// Remove the local backend override - will use production
$options = get_option('bbai_options', array());
unset($options['bbai_alt_api_host']);
update_option('bbai_options', $options);

header('Content-Type: text/plain');
echo "✅ Reset to production backend!\n\n";
echo "The plugin will now use: https://alttext-ai-backend.onrender.com\n\n";
echo "Refresh your WordPress admin page for changes to take effect.\n";

