<?php
/**
 * OpptiAI Framework - Bootstrap
 *
 * Initializes the OpptiAI Framework and loads all components
 *
 * @package OpptiAI\Framework
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define framework constants
if ( ! defined( 'OPPTIAI_FRAMEWORK_DIR' ) ) {
	define( 'OPPTIAI_FRAMEWORK_DIR', __DIR__ );
}

if ( ! defined( 'OPPTIAI_FRAMEWORK_URL' ) ) {
	define( 'OPPTIAI_FRAMEWORK_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'OPPTIAI_FRAMEWORK_VERSION' ) ) {
	define( 'OPPTIAI_FRAMEWORK_VERSION', '1.0.0' );
}

/**
 * OpptiAI Framework Autoloader
 *
 * Automatically loads framework classes
 */
spl_autoload_register(
	function ( $class ) {
		// Only handle OpptiAI\Framework classes
		if ( 0 !== strpos( $class, 'OpptiAI\\Framework\\' ) ) {
			return;
		}

		// Remove namespace prefix
		$class_name = str_replace( 'OpptiAI\\Framework\\', '', $class );

		// Convert namespace separators to directory separators
		$class_name = str_replace( '\\', DIRECTORY_SEPARATOR, $class_name );

		// Map class to file path
		$file_paths = array(
			// Try class-prefixed file first
			OPPTIAI_FRAMEWORK_DIR . DIRECTORY_SEPARATOR . strtolower( dirname( $class_name ) ) . DIRECTORY_SEPARATOR . 'class-' . strtolower( basename( $class_name ) ) . '.php',
			// Try direct file name
			OPPTIAI_FRAMEWORK_DIR . DIRECTORY_SEPARATOR . strtolower( $class_name ) . '.php',
		);

		foreach ( $file_paths as $file ) {
			if ( file_exists( $file ) ) {
				require_once $file;
				return;
			}
		}
	}
);

// Load helper files
require_once OPPTIAI_FRAMEWORK_DIR . '/helpers/sanitizer.php';
require_once OPPTIAI_FRAMEWORK_DIR . '/helpers/escaper.php';
require_once OPPTIAI_FRAMEWORK_DIR . '/helpers/validator.php';

// Load security
require_once OPPTIAI_FRAMEWORK_DIR . '/security/class-permissions.php';

// Load auth
require_once OPPTIAI_FRAMEWORK_DIR . '/auth/class-auth.php';

// Load API
require_once OPPTIAI_FRAMEWORK_DIR . '/api/class-api-client.php';

// Load settings
require_once OPPTIAI_FRAMEWORK_DIR . '/settings/class-settings.php';

// Load UI components
require_once OPPTIAI_FRAMEWORK_DIR . '/ui/components.php';
require_once OPPTIAI_FRAMEWORK_DIR . '/ui/class-layout.php';

// Load plugin module system
require_once OPPTIAI_FRAMEWORK_DIR . '/class-plugin.php';

/**
 * Initialize framework assets
 *
 * @return void
 */
function opptiai_framework_enqueue_assets() {
	// Only load on admin pages
	if ( ! is_admin() ) {
		return;
	}

	// Get current screen
	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}

	// Only load on OpptiAI pages
	if ( false === strpos( $screen->id, 'opptiai' ) && false === strpos( $screen->id, 'alttext' ) ) {
		return;
	}

	// Enqueue framework CSS
	wp_enqueue_style(
		'opptiai-framework-admin',
		OPPTIAI_FRAMEWORK_URL . 'ui/admin-ui.css',
		array(),
		OPPTIAI_FRAMEWORK_VERSION
	);

	// Enqueue framework JS
	wp_enqueue_script(
		'opptiai-framework-admin',
		OPPTIAI_FRAMEWORK_URL . 'ui/admin-ui.js',
		array( 'jquery' ),
		OPPTIAI_FRAMEWORK_VERSION,
		true
	);

	// Localize script with framework data
	wp_localize_script(
		'opptiai-framework-admin',
		'opptiaiFramework',
		array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'opptiai_framework' ),
			'i18n'      => array(
				'confirm'       => __( 'Are you sure?', 'wp-alt-text-plugin' ),
				'error'         => __( 'An error occurred. Please try again.', 'wp-alt-text-plugin' ),
				'success'       => __( 'Success!', 'wp-alt-text-plugin' ),
				'loading'       => __( 'Loading...', 'wp-alt-text-plugin' ),
				'save'          => __( 'Save Changes', 'wp-alt-text-plugin' ),
				'cancel'        => __( 'Cancel', 'wp-alt-text-plugin' ),
				'delete'        => __( 'Delete', 'wp-alt-text-plugin' ),
				'close'         => __( 'Close', 'wp-alt-text-plugin' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'opptiai_framework_enqueue_assets' );

/**
 * Add admin capability on plugin activation
 *
 * @return void
 */
function opptiai_framework_activate() {
	\OpptiAI\Framework\Security\Permissions::add_capability_to_admin();
}

/**
 * Remove admin capability on plugin deactivation
 *
 * @return void
 */
function opptiai_framework_deactivate() {
	\OpptiAI\Framework\Security\Permissions::remove_capability();
}

// Framework initialized
do_action( 'opptiai_framework_loaded' );
