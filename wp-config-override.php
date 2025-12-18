<?php
/**
 * WordPress Configuration Override for Local Development
 * 
 * Add this to your wp-config.php file, or include it at the end:
 * require_once(__DIR__ . '/wp-content/plugins/beepbeep-ai-alt-text-generator/wp-config-override.php');
 * 
 * This configures the plugin to use your local backend for testing.
 */

// Override API URL to use local backend
if (!defined('ALT_API_HOST')) {
    define('ALT_API_HOST', 'http://host.docker.internal:10000');
}

// Enable WordPress debug logging
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}
if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', true);
}
if (!defined('WP_DEBUG_DISPLAY')) {
    define('WP_DEBUG_DISPLAY', false); // Don't display errors on screen
}

