<?php
/**
 * Check which API URL the plugin is using
 * Access via: http://localhost:8080/wp-content/plugins/beepbeep-ai-alt-text-generator/check-api-url.php
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

header('Content-Type: text/plain');

echo "API URL Configuration Check\n";
echo str_repeat("=", 60) . "\n\n";

// Check constant
echo "1. ALT_API_HOST constant: ";
if (defined('ALT_API_HOST')) {
    echo defined('ALT_API_HOST') . "\n";
} else {
    echo "NOT DEFINED\n";
}

// Check environment variable
echo "2. ALT_API_HOST env var: ";
$env_host = getenv('ALT_API_HOST');
echo $env_host !== false ? $env_host : "NOT SET";
echo "\n";

// Check WordPress option
echo "3. bbai_options['bbai_alt_api_host']: ";
$options = get_option('bbai_options', array());
echo isset($options['bbai_alt_api_host']) ? $options['bbai_alt_api_host'] : "NOT SET";
echo "\n";

// Check what API client is actually using
echo "\n4. API Client actual URL: ";
if (class_exists('BeepBeepAI\AltTextGenerator\API_Client_V2')) {
    require_once __DIR__ . '/includes/class-api-client-v2.php';
    $api_client = new \BeepBeepAI\AltTextGenerator\API_Client_V2();
    
    // Use reflection to get private property
    $reflection = new ReflectionClass($api_client);
    $property = $reflection->getProperty('api_url');
    $property->setAccessible(true);
    $actual_url = $property->getValue($api_client);
    
    echo $actual_url . "\n";
} else {
    echo "API Client class not found\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Expected: http://host.docker.internal:10000 (for Docker)\n";
echo "Or: http://localhost:10000 (if WordPress not in Docker)\n";

