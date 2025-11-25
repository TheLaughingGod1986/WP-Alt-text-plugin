<?php
/**
 * Backwards Compatibility Stub
 *
 * Optionally loads shared OptiAI Core plugin if available.
 * Plugin works standalone without requiring the core plugin.
 *
 * @package BeepBeepAI\AltTextGenerator
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if shared core exists and is active
$core_path = WP_PLUGIN_DIR . '/opptiai-core/framework/loader.php';
if (file_exists($core_path) && defined('OPPTIAI_CORE_VERSION')) {
    // Use shared framework if available
    require_once $core_path;
    return;
}

// Check if core plugin is active (even if path check fails)
if (defined('OPPTIAI_CORE_VERSION')) {
    // Core is loaded, framework should be available
    if (function_exists('opptiai_framework')) {
        return;
    }
}

// Fallback: Check for embedded framework (for old installations)
$embedded_path = __DIR__ . '/embedded-framework/loader.php';
if (file_exists($embedded_path)) {
    require_once $embedded_path;
    return;
}

// No framework available - plugin will continue with legacy classes
// This is perfectly fine - the plugin works standalone without the framework
// The framework is optional and provides shared functionality if available

