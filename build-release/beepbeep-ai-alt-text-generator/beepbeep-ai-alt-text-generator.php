<?php
/**
 * Plugin Name: BeepBeep AI – Alt Text Generator
 * Description: Automatically generates SEO-optimized AI alt text for WordPress.
 * Version: 4.4.0
 * Author: beepbeepv2
 * Author URI: https://oppti.dev
 * Plugin URI: https://wordpress.org/plugins/beepbeep-ai-alt-text-generator/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: beepbeep-ai-alt-text-generator
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Suppress PHP 8.1+ deprecation warnings from WordPress core to prevent "headers already sent" errors
// These warnings come from WordPress core itself, not our plugin code
if (PHP_VERSION_ID >= 80100 && defined('WP_DEBUG') && WP_DEBUG) {
	// Set custom error handler to filter deprecation warnings
	set_error_handler(function($errno, $errstr, $errfile, $errline) {
		// Suppress deprecation warnings from WordPress core
		if (($errno === E_DEPRECATED || $errno === E_STRICT) && 
		    strpos($errfile, 'wp-includes') !== false) {
			return true; // Suppress the warning
		}
		// Let other errors through
		return false;
	}, E_DEPRECATED | E_STRICT);
}

// Define plugin constants
define( 'BEEPBEEP_AI_VERSION', '4.4.0' );
define( 'BBAI_VERSION', '4.4.0' ); // Legacy alias for compatibility
define( 'BEEPBEEP_AI_PLUGIN_FILE', __FILE__ );
define( 'BBAI_PLUGIN_FILE', __FILE__ ); // Legacy alias
define( 'BEEPBEEP_AI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BBAI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) ); // Legacy alias
define( 'BEEPBEEP_AI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BBAI_PLUGIN_URL', plugin_dir_url( __FILE__ ) ); // Legacy alias
define( 'BEEPBEEP_AI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'BBAI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) ); // Legacy alias

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-input-validator.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-queue.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-debug-log.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-seo-quality-checker.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-schema-markup.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-core.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-admin-hooks.php';

// Load v5.0 architecture (services, controllers, DI container).
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/core/class-container.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/core/class-event-bus.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/core/class-router.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/core/class-service-provider.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-authentication-service.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-license-service.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-usage-service.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-generation-service.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-queue-service.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/controllers/class-auth-controller.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/controllers/class-license-controller.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/controllers/class-generation-controller.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/controllers/class-queue-controller.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/bootstrap-v5.php';

/**
 * Register the activation hook.
 */
function beepbeepai_activate() {
	require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-activator.php';
	\BeepBeepAI\AltTextGenerator\Activator::activate();
}

/**
 * Register the deactivation hook.
 */
function beepbeepai_deactivate() {
	require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-deactivator.php';
	\BeepBeepAI\AltTextGenerator\Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'beepbeepai_activate' );
register_deactivation_hook( __FILE__, 'beepbeepai_deactivate' );

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai.php';

/**
 * Kick off the plugin.
 */
function beepbeepai_run() {
	$plugin = new \BeepBeepAI\AltTextGenerator\Plugin();
	$plugin->run();
}

beepbeepai_run();
