<?php
/**
 * Dummy Plugin for Testing
 *
 * Minimal plugin that uses the Optti Framework for testing purposes.
 *
 * @package Optti\Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'DUMMY_PLUGIN_VERSION', '1.0.0' );
define( 'DUMMY_PLUGIN_FILE', __FILE__ );
define( 'DUMMY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DUMMY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DUMMY_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Bootstrap framework
require_once __DIR__ . '/../loader.php';

Optti_Framework::bootstrap( __FILE__, [
	'plugin_slug'  => 'dummy-optti-plugin',
	'version'      => DUMMY_PLUGIN_VERSION,
	'api_base_url' => 'https://backend.optti.dev',
	'asset_url'    => DUMMY_PLUGIN_URL . '../dist/',
	'asset_dir'    => DUMMY_PLUGIN_DIR . '../dist/',
] );

// Load plugin class
require_once __DIR__ . '/includes/class-dummy-plugin.php';

// Global plugin instance
global $dummy_plugin_instance;

/**
 * Register activation hook.
 */
function dummy_plugin_activate() {
	global $dummy_plugin_instance;
	if ( $dummy_plugin_instance ) {
		$dummy_plugin_instance->activate();
	}
}

/**
 * Register deactivation hook.
 */
function dummy_plugin_deactivate() {
	global $dummy_plugin_instance;
	if ( $dummy_plugin_instance ) {
		$dummy_plugin_instance->deactivate();
	}
}

register_activation_hook( __FILE__, 'dummy_plugin_activate' );
register_deactivation_hook( __FILE__, 'dummy_plugin_deactivate' );

/**
 * Initialize plugin.
 */
function dummy_plugin_init() {
	global $dummy_plugin_instance;
	$config = Optti_Framework::get_config();
	$dummy_plugin_instance = new \Optti\Tests\Dummy_Plugin( DUMMY_PLUGIN_FILE, $config );
}

dummy_plugin_init();

