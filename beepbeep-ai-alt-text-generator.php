<?php
/**
 * Plugin Name: BeepBeep AI – Alt Text Generator
 * Description: Automatically generates SEO-optimized AI alt text for WordPress.
 * Version: 4.4.1
 * Requires at least: 6.2
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

// Define plugin constants
define( 'BEEPBEEP_AI_VERSION', '4.4.1' );
define( 'BBAI_VERSION', '4.4.1' ); // Legacy alias for compatibility
define( 'BEEPBEEP_AI_DB_VERSION', '1.0.0' );
define( 'BEEPBEEP_AI_PLUGIN_FILE', __FILE__ );
define( 'BBAI_PLUGIN_FILE', __FILE__ ); // Legacy alias
define( 'BEEPBEEP_AI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BBAI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) ); // Legacy alias
define( 'BEEPBEEP_AI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BBAI_PLUGIN_URL', plugin_dir_url( __FILE__ ) ); // Legacy alias
define( 'BEEPBEEP_AI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'BBAI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) ); // Legacy alias

if ( ! function_exists( 'bbai_is_authenticated' ) ) {
	/**
	 * Check if the plugin has stored auth credentials.
	 *
	 * @return bool
	 */
	function bbai_is_authenticated() {
		if ( ! function_exists( 'get_option' ) && function_exists( 'is_user_logged_in' ) ) {
			return is_user_logged_in();
		}

		$token        = get_option( 'beepbeepai_jwt_token', '' );
		$legacy_token = get_option( 'opptibbai_jwt_token', '' );
		$license_key  = get_option( 'beepbeepai_license_key', '' );
		$license_data = get_option( 'beepbeepai_license_data', [] );

		if ( ! empty( $token ) || ! empty( $legacy_token ) || ! empty( $license_key ) || ! empty( $license_data ) ) {
			return true;
		}

		return false;
	}
}

if ( ! function_exists( 'bbai_enqueue_logged_out_styles' ) ) {
	/**
	 * Enqueue logged-out onboarding styles only on BeepBeep AI admin screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	function bbai_enqueue_logged_out_styles( $hook ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
			$page_input     = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
			$current_page = $page_input;
			$hook         = is_string( $hook ) ? $hook : '';
			$is_bbai_page = strpos( $hook, 'toplevel_page_bbai' ) === 0
				|| strpos( $hook, 'bbai_page_bbai' ) === 0
				|| strpos( $hook, 'bbai_page_beepbeep-ai' ) === 0
			|| strpos( $hook, '_page_bbai' ) !== false
			|| strpos( $hook, '_page_beepbeep-ai' ) !== false
			|| ( ! empty( $current_page ) && ( strpos( $current_page, 'bbai' ) === 0 || $current_page === 'beepbeep-ai' ) );

		if ( ! $is_bbai_page || bbai_is_authenticated() ) {
			return;
		}

		$css_rel  = 'assets/admin/logged-out.css';
		$css_path = BEEPBEEP_AI_PLUGIN_DIR . $css_rel;

		wp_enqueue_style(
			'bbai-logged-out',
			BEEPBEEP_AI_PLUGIN_URL . $css_rel,
			[],
			file_exists( $css_path ) ? filemtime( $css_path ) : BEEPBEEP_AI_VERSION
		);
	}
}

add_action( 'admin_enqueue_scripts', 'bbai_enqueue_logged_out_styles', 20 );

// Load cache and DB schema helpers.
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-cache.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-db.php';

// Load helper functions first.
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-json.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-privacy.php';
if ( class_exists( '\BeepBeepAI\AltTextGenerator\Privacy' ) ) {
	\BeepBeepAI\AltTextGenerator\Privacy::init();
}

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
	\BeepBeepAI\AltTextGenerator\DB_Schema::install();
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

// Check for DB schema upgrades on admin pages (handles plugin updates without reactivation).
add_action( 'admin_init', [ '\BeepBeepAI\AltTextGenerator\DB_Schema', 'maybe_upgrade' ] );
