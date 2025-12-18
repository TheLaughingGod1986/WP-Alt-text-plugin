<?php
/**
 * Plugin Name: My Awesome Plugin
 * Plugin URI: https://example.com/my-plugin
 * Description: A brief description of what your plugin does
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: my-plugin
 * Domain Path: /languages
 *
 * @package MyPlugin
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants
 *
 * Define all plugin constants here for easy access throughout the plugin.
 */
define( 'MYPLUGIN_VERSION', '1.0.0' );
define( 'MYPLUGIN_PLUGIN_FILE', __FILE__ );
define( 'MYPLUGIN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MYPLUGIN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MYPLUGIN_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoload dependencies
 *
 * If you're using Composer, uncomment the line below.
 * Otherwise, manually require your class files.
 */
// require_once MYPLUGIN_PLUGIN_DIR . 'vendor/autoload.php';

/**
 * Load framework core
 *
 * These are the essential framework components that power the plugin architecture.
 */
require_once MYPLUGIN_PLUGIN_DIR . 'includes/core/class-container.php';
require_once MYPLUGIN_PLUGIN_DIR . 'includes/core/class-event-bus.php';
require_once MYPLUGIN_PLUGIN_DIR . 'includes/core/class-router.php';
require_once MYPLUGIN_PLUGIN_DIR . 'includes/core/class-service-provider.php';

/**
 * Load plugin classes
 */
require_once MYPLUGIN_PLUGIN_DIR . 'includes/class-plugin.php';
require_once MYPLUGIN_PLUGIN_DIR . 'includes/class-loader.php';
require_once MYPLUGIN_PLUGIN_DIR . 'includes/class-activator.php';
require_once MYPLUGIN_PLUGIN_DIR . 'includes/class-deactivator.php';

/**
 * Load services
 *
 * Services contain your business logic and should be independent of WordPress.
 */
foreach ( glob( MYPLUGIN_PLUGIN_DIR . 'includes/services/*.php' ) as $file ) {
	require_once $file;
}

/**
 * Load controllers
 *
 * Controllers handle HTTP requests and delegate to services.
 */
foreach ( glob( MYPLUGIN_PLUGIN_DIR . 'includes/controllers/*.php' ) as $file ) {
	require_once $file;
}

/**
 * Load bootstrap
 *
 * Bootstrap initializes the DI container and registers all services.
 */
require_once MYPLUGIN_PLUGIN_DIR . 'includes/bootstrap.php';

/**
 * Plugin activation hook
 *
 * Runs when the plugin is activated. Use for:
 * - Creating database tables
 * - Setting default options
 * - Flushing rewrite rules
 * - Scheduling cron events
 */
function myplugin_activate() {
	require_once MYPLUGIN_PLUGIN_DIR . 'includes/class-activator.php';
	\MyPlugin\Activator::activate();
}
register_activation_hook( __FILE__, 'myplugin_activate' );

/**
 * Plugin deactivation hook
 *
 * Runs when the plugin is deactivated. Use for:
 * - Clearing scheduled events
 * - Flushing rewrite rules
 * - Cleaning up temporary data
 */
function myplugin_deactivate() {
	require_once MYPLUGIN_PLUGIN_DIR . 'includes/class-deactivator.php';
	\MyPlugin\Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'myplugin_deactivate' );

/**
 * Initialize and run the plugin
 *
 * Creates the main plugin instance and starts execution.
 */
function myplugin_run() {
	$plugin = new \MyPlugin\Plugin();
	$plugin->run();
}

// Start the plugin
myplugin_run();
