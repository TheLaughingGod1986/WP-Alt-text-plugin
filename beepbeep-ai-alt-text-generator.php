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

// Define plugin constants
define( 'OPTTI_VERSION', '4.2.3' );
define( 'OPTTI_PLUGIN_FILE', __FILE__ );
define( 'OPTTI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OPTTI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'OPTTI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Namespace framework options/cache to avoid collisions with other Optti-based plugins.
if ( ! defined( 'OPTTI_OPTION_PREFIX' ) ) {
	define( 'OPTTI_OPTION_PREFIX', 'bbai_' );
}
if ( ! function_exists( 'optti_option_prefix' ) ) {
	function optti_option_prefix() {
		return defined( 'OPTTI_OPTION_PREFIX' ) ? OPTTI_OPTION_PREFIX : 'optti_';
	}
}
add_filter(
	'optti_cache_prefix',
	static function () {
		return optti_option_prefix();
	}
);

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

// Suppress deprecation notices only during framework bootstrap to avoid noisy output with WP_DEBUG_DISPLAY on.
$__bbai_prev_handler = set_error_handler(
	static function ( $errno ) {
		if ( $errno === E_DEPRECATED ) {
			return true;
		}
		return false;
	},
	E_DEPRECATED
);

try {
	Optti_Framework::bootstrap(
		__FILE__,
		array(
			'plugin_slug'  => 'beepbeep-ai-alt-text-generator',
			'version'      => OPTTI_VERSION,
			'api_base_url' => 'https://alttext-ai-backend.onrender.com',
			'asset_url'    => OPTTI_PLUGIN_URL . 'framework/dist/',
			'asset_dir'    => OPTTI_PLUGIN_DIR . 'framework/dist/',
		)
	);
} finally {
	if ( $__bbai_prev_handler ) {
		set_error_handler( $__bbai_prev_handler );
	} else {
		restore_error_handler();
	}
}

// Load translations at init to comply with WordPress expectations.
add_action(
	'init',
	static function () {
		load_plugin_textdomain(
			'beepbeep-ai-alt-text-generator',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	},
	0
);

// Load required dependencies (still needed by framework)
// Defer loading to admin_init for frontend performance - these are only needed in admin/AJAX/REST contexts.
if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-debug-log.php';
}

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
	add_action(
		'init',
		function () {
			require_once OPTTI_PLUGIN_DIR . 'includes/class-bbai.php';
			require_once OPTTI_PLUGIN_DIR . 'admin/class-bbai-admin.php';

			$legacy_plugin = new \BeepBeepAI\AltTextGenerator\Plugin();
			$legacy_plugin->run();
		},
		0
	);
}

// Ensure REST routes are registered on rest_api_init (may fire before init completes)
// This is a safety net to guarantee routes are available
add_action(
	'rest_api_init',
	function () {
		if ( ! class_exists( '\BeepBeepAI\AltTextGenerator\Admin' ) ) {
			require_once OPTTI_PLUGIN_DIR . 'includes/class-bbai.php';
			require_once OPTTI_PLUGIN_DIR . 'admin/class-bbai-admin.php';
		}

		$admin_instance = new \BeepBeepAI\AltTextGenerator\Admin( 'beepbeep-ai-alt-text-generator', OPTTI_VERSION );
		$admin_instance->ensure_rest_routes();
	},
	0
);

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
