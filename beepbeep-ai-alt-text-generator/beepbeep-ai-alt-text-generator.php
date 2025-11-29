<?php
/**
 * Plugin Name: BeepBeep AI – Alt Text Generator
 * Plugin URI: https://oppti.dev/
 * Description: AI-powered image alt text generator that boosts WordPress SEO, accessibility, and image rankings automatically.
 * Version: 4.2.3
 * Author: beepbeepv2
 * Author URI: https://oppti.dev/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: beepbeep-ai-alt-text-generator
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Tags: SEO, accessibility, alt text, images, AI, automation, wcag, media library, image SEO, alt text SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Suppress WordPress core PHP 8.1+ deprecation warnings that appear before headers
// These warnings are from WordPress core files (wp-includes/functions.php), not the plugin
// We suppress only E_DEPRECATED from WordPress core directories to prevent display issues
if ( ! headers_sent() && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
	// Start output buffering early
	ob_start();
	
	// Suppress only deprecation warnings from WordPress core files
	$previous_handler = set_error_handler( function( $errno, $errstr, $errfile, $errline ) use ( &$previous_handler ) {
		// Only suppress E_DEPRECATED (8192) from WordPress core directories
		if ( $errno === E_DEPRECATED ) {
			$errfile_normalized = str_replace( '\\', '/', $errfile );
			// Check if error is from WordPress core (wp-includes or wp-admin)
			if ( strpos( $errfile_normalized, '/wp-includes/' ) !== false || strpos( $errfile_normalized, '/wp-admin/' ) !== false ) {
				// Suppress this WordPress core deprecation warning
				return true;
			}
		}
		// Pass through to previous handler or default behavior
		if ( $previous_handler ) {
			return call_user_func( $previous_handler, $errno, $errstr, $errfile, $errline );
		}
		return false;
	}, E_DEPRECATED );
}

define( 'BBAI_VERSION', '4.2.3' );
define( 'BBAI_PLUGIN_FILE', __FILE__ );
define( 'BBAI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BBAI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BBAI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once BBAI_PLUGIN_DIR . 'includes/class-api-client-v2.php';
require_once BBAI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
require_once BBAI_PLUGIN_DIR . 'includes/class-queue.php';
require_once BBAI_PLUGIN_DIR . 'includes/class-debug-log.php';
require_once BBAI_PLUGIN_DIR . 'admin/class-bbai-core.php';
require_once BBAI_PLUGIN_DIR . 'admin/class-bbai-admin-hooks.php';

/**
 * Register the activation hook.
 */
function bbai_activate() {
	require_once BBAI_PLUGIN_DIR . 'includes/class-bbai-activator.php';
	BbAI_Activator::activate();
}

/**
 * Register the deactivation hook.
 */
function bbai_deactivate() {
	require_once BBAI_PLUGIN_DIR . 'includes/class-bbai-deactivator.php';
	BbAI_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'bbai_activate' );
register_deactivation_hook( __FILE__, 'bbai_deactivate' );

require_once BBAI_PLUGIN_DIR . 'includes/class-bbai.php';

/**
 * Kick off the plugin.
 */
function bbai_run() {
	$plugin = new BbAI();
	$plugin->run();
}

bbai_run();
