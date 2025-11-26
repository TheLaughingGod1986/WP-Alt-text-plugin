<?php
/**
 * Plugin Name: BeepBeep AI – Alt Text Generator
 * Description: AI-powered image alt text generator that boosts WordPress SEO, accessibility, and image rankings automatically.
 * Version: 4.2.3
 * Author: beepbeepv2
 * Author URI: https://oppti.dev/
 * Plugin URI: https://oppti.dev/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: beepbeep-ai-alt-text-generator
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prevent headers already sent errors from PHP 8.1 deprecation warnings
// These warnings come from WordPress core when null values are passed to strpos/str_replace
if ( ! headers_sent() && ! wp_doing_ajax() && ! wp_doing_cron() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
	// Start output buffering to prevent headers already sent errors
	ob_start();
	
	// Set custom error handler that suppresses only E_DEPRECATED warnings from WordPress core
	// This prevents display of PHP 8.1 compatibility warnings from WordPress core
	set_error_handler( function( $errno, $errstr, $errfile, $errline ) {
		// Only suppress E_DEPRECATED warnings (8192) from WordPress core files
		if ( $errno === E_DEPRECATED && $errfile !== null && is_string( $errfile ) && $errfile !== '' ) {
			$errfile_normalized = str_replace( '\\', '/', $errfile );
			// Check if warning is from WordPress core (wp-includes or wp-admin)
			if ( $errfile_normalized !== '' && 
			     ( strpos( $errfile_normalized, '/wp-includes/' ) !== false || 
			       strpos( $errfile_normalized, '/wp-admin/' ) !== false ) ) {
				// Suppress this WordPress core deprecation warning
				return true;
			}
		}
		// Pass through all other errors
		return false;
	}, E_DEPRECATED );
}

// Define plugin constants
define( 'OPTTI_VERSION', '5.0.0' );
define( 'OPTTI_PLUGIN_FILE', __FILE__ );
define( 'OPTTI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OPTTI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'OPTTI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Legacy constants for backward compatibility
define( 'BEEPBEEP_AI_VERSION', OPTTI_VERSION );
define( 'BBAI_VERSION', OPTTI_VERSION );
define( 'BEEPBEEP_AI_PLUGIN_FILE', OPTTI_PLUGIN_FILE );
define( 'BBAI_PLUGIN_FILE', OPTTI_PLUGIN_FILE );
define( 'BEEPBEEP_AI_PLUGIN_DIR', OPTTI_PLUGIN_DIR );
define( 'BBAI_PLUGIN_DIR', OPTTI_PLUGIN_DIR );
define( 'BEEPBEEP_AI_PLUGIN_URL', OPTTI_PLUGIN_URL );
define( 'BBAI_PLUGIN_URL', OPTTI_PLUGIN_URL );
define( 'BEEPBEEP_AI_PLUGIN_BASENAME', OPTTI_PLUGIN_BASENAME );
define( 'BBAI_PLUGIN_BASENAME', OPTTI_PLUGIN_BASENAME );

// Bootstrap Optti Framework
require_once OPTTI_PLUGIN_DIR . 'framework/loader.php';

Optti_Framework::bootstrap( __FILE__, [
	'plugin_slug'  => 'beepbeep-ai-alt-text-generator',
	'version'      => OPTTI_VERSION,
	'api_base_url' => 'https://alttext-ai-backend.onrender.com',
	'asset_url'    => OPTTI_PLUGIN_URL . 'framework/dist/',
	'asset_dir'    => OPTTI_PLUGIN_DIR . 'framework/dist/',
] );

// Load translations at init to comply with WordPress expectations.
add_action(
	'init',
	static function() {
		load_plugin_textdomain(
			'beepbeep-ai-alt-text-generator',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	},
	0
);

// Load required dependencies (still needed by framework)
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-queue.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-debug-log.php';

// Load BeepBeep plugin class
require_once OPTTI_PLUGIN_DIR . 'includes/class-beepbeep-plugin.php';

// Bootstrap legacy Core class for menu registration, AJAX handlers, and REST API (backward compatibility)
// This ensures the Media > BeepBeep AI menu is registered, AJAX handlers work, and REST routes are available.
// Bootstrap immediately if in admin, AJAX, or REST API, otherwise on init.
if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
	require_once OPTTI_PLUGIN_DIR . 'includes/class-bbai.php';
	require_once OPTTI_PLUGIN_DIR . 'admin/class-bbai-admin.php';
	
	// Initialize legacy plugin system to register admin menu, AJAX handlers, and REST routes.
	$legacy_plugin = new \BeepBeepAI\AltTextGenerator\Plugin();
	$legacy_plugin->run();
} else {
	// For frontend, bootstrap on init.
	add_action( 'init', function() {
		require_once OPTTI_PLUGIN_DIR . 'includes/class-bbai.php';
		require_once OPTTI_PLUGIN_DIR . 'admin/class-bbai-admin.php';
		
		$legacy_plugin = new \BeepBeepAI\AltTextGenerator\Plugin();
		$legacy_plugin->run();
	}, 0 );
}

// Ensure REST routes are registered on rest_api_init (may fire before init completes)
// This is a safety net to guarantee routes are available
add_action( 'rest_api_init', function() {
	if ( ! class_exists( '\BeepBeepAI\AltTextGenerator\Admin' ) ) {
		require_once OPTTI_PLUGIN_DIR . 'includes/class-bbai.php';
		require_once OPTTI_PLUGIN_DIR . 'admin/class-bbai-admin.php';
	}
	
	$admin_instance = new \BeepBeepAI\AltTextGenerator\Admin( 'beepbeep-ai-alt-text-generator', OPTTI_VERSION );
	$admin_instance->ensure_rest_routes();
}, 0 );

// Global plugin instance (for backward compatibility and module access)
global $optti_beepbeep_plugin;

/**
 * Register the activation hook.
 */
function optti_activate() {
	global $optti_beepbeep_plugin;
	
	// Load legacy activator for backward compatibility during migration.
	require_once OPTTI_PLUGIN_DIR . 'includes/class-bbai-activator.php';
	\BeepBeepAI\AltTextGenerator\Activator::activate();
	
	// Also activate framework.
	if ( $optti_beepbeep_plugin ) {
		$optti_beepbeep_plugin->activate();
	}
}

/**
 * Register the deactivation hook.
 */
function optti_deactivate() {
	global $optti_beepbeep_plugin;
	
	// Load legacy deactivator for backward compatibility during migration.
	require_once OPTTI_PLUGIN_DIR . 'includes/class-bbai-deactivator.php';
	\BeepBeepAI\AltTextGenerator\Deactivator::deactivate();
	
	// Also deactivate framework.
	if ( $optti_beepbeep_plugin ) {
		$optti_beepbeep_plugin->deactivate();
	}
}

register_activation_hook( __FILE__, 'optti_activate' );
register_deactivation_hook( __FILE__, 'optti_deactivate' );

// Legacy function names for backward compatibility.
function beepbeepai_activate() {
	optti_activate();
}

function beepbeepai_deactivate() {
	optti_deactivate();
}

/**
 * Kick off the plugin.
 */
function optti_run() {
	global $optti_beepbeep_plugin;
	
	// Initialize BeepBeep plugin.
	$optti_beepbeep_plugin = new \BeepBeepAI\AltTextGenerator\BeepBeep_AltText_Plugin(
		OPTTI_PLUGIN_FILE,
		Optti_Framework::get_config()
	);
}

// Legacy function for backward compatibility.
function beepbeepai_run() {
	optti_run();
}

// Initialize the plugin.
optti_run();
