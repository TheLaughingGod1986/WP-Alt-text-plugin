<?php
/**
 * Quick script to set local backend URL
 * Access via: http://localhost:8080/wp-content/plugins/beepbeep-ai-alt-text-generator/set-local-backend.php
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

// Get Mac's local IP address for Docker access
// Your Mac IP: 192.168.1.129 (found via ifconfig)
// Try multiple options in order of preference
$possible_ips = [
    '192.168.1.129',           // Your Mac's actual IP (most reliable)
    'host.docker.internal',    // Docker Desktop special hostname
    '172.17.0.1',              // Docker bridge default
];

// Use your Mac's IP first (most reliable)
$backend_url = 'http://192.168.1.129:10000';

// Set the local backend URL
$options = get_option('bbai_options', array());
$options['bbai_alt_api_host'] = $backend_url;
update_option('bbai_options', $options);

header('Content-Type: text/plain');
echo "✅ Local backend URL set!\n\n";
echo "API URL: $backend_url\n\n";
echo "Using your Mac's IP: 192.168.1.129\n";
echo "If this doesn't work, the script will try: " . implode(', ', array_slice($possible_ips, 1)) . "\n\n";
echo "Now refresh your WordPress admin page and try the forgot password form again.\n";
echo "You should see requests in your local backend logs.\n\n";
echo "To verify, visit:\n";
echo "http://localhost:8080/wp-content/plugins/beepbeep-ai-alt-text-generator/check-api-url.php\n\n";
echo "⚠️  IMPORTANT: This sets LOCAL backend for development only.\n";
echo "For production, visit reset-to-production-backend.php to use Render backend.\n";

