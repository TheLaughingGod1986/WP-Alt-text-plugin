<?php
/**
 * Plugin Name: BeepBeep AI – Alt Text Generator
 * Description: Bulk AI ALT text for WordPress and WooCommerce — fix missing descriptions, image SEO, and accessibility workflows.
 * Version: 4.6.1
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
define( 'BEEPBEEP_AI_VERSION', '4.6.1' );
define( 'BBAI_VERSION', '4.6.1' ); // Legacy alias for compatibility
define( 'BEEPBEEP_AI_DB_VERSION', '1.0.0' );
define( 'BEEPBEEP_AI_PLUGIN_FILE', __FILE__ );
define( 'BBAI_PLUGIN_FILE', __FILE__ ); // Legacy alias
define( 'BEEPBEEP_AI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BBAI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) ); // Legacy alias
define( 'BEEPBEEP_AI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BBAI_PLUGIN_URL', plugin_dir_url( __FILE__ ) ); // Legacy alias
define( 'BEEPBEEP_AI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'BBAI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) ); // Legacy alias

// When the admin runs on loopback, allow bypassing the SaaS API for ALT generation (see generate_and_save).
if ( ! defined( 'BEEPBEEP_AI_LOCAL_ALT_STUB' ) ) {
	$bbai_stub_host = '';
	if ( isset( $_SERVER['HTTP_HOST'] ) && is_string( $_SERVER['HTTP_HOST'] ) ) {
		$bbai_stub_host = strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) );
	}
	define(
		'BEEPBEEP_AI_LOCAL_ALT_STUB',
		$bbai_stub_host !== '' && 1 === preg_match( '/^(localhost|127\.0\.0\.1)(:\d+)?$/', $bbai_stub_host )
	);
}

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
		$page_input   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$current_page = $page_input;
		$hook         = is_string( $hook ) ? $hook : '';
		$is_bbai_page = strpos( $hook, 'toplevel_page_bbai' ) === 0
			|| strpos( $hook, 'bbai_page_bbai' ) === 0
			|| strpos( $hook, '_page_bbai' ) !== false
			|| ( ! empty( $current_page ) && strpos( $current_page, 'bbai' ) === 0 );

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

if ( ! function_exists( 'bbai_enable_wp_json_fallback_route' ) ) {
	/**
	 * Support /wp-json/* REST paths in environments where rewrite rules are unavailable.
	 *
	 * When pretty REST routes return 404 (common in some local stacks), map the request
	 * path into the equivalent ?rest_route=... query var before core REST dispatch runs.
	 *
	 * @param \WP $wp WordPress environment instance.
	 * @return void
	 */
	function bbai_enable_wp_json_fallback_route( $wp ) {
		if ( ! ( $wp instanceof \WP ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing check for rest_route query param.
		if ( ! empty( $wp->query_vars['rest_route'] ) || isset( $_GET['rest_route'] ) ) {
			return;
		}

		$request_uri_raw = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( '' === $request_uri_raw ) {
			return;
		}

		$request_uri = $request_uri_raw;
		$request_path = wp_parse_url( $request_uri, PHP_URL_PATH );
		if ( ! is_string( $request_path ) || '' === $request_path ) {
			return;
		}

		$request_path = '/' . ltrim( $request_path, '/' );

		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( is_string( $home_path ) && '' !== $home_path && '/' !== $home_path ) {
			$home_path = '/' . trim( $home_path, '/' );
			if ( strpos( $request_path, $home_path . '/' ) === 0 ) {
				$request_path = substr( $request_path, strlen( $home_path ) );
				$request_path = '/' . ltrim( (string) $request_path, '/' );
			}
		}

		if ( '/wp-json' === $request_path || '/wp-json/' === $request_path ) {
			$wp->query_vars['rest_route'] = '/';
			return;
		}

		$prefix = '/wp-json/';
		if ( strpos( $request_path, $prefix ) !== 0 ) {
			return;
		}

		$route = '/' . ltrim( substr( $request_path, strlen( $prefix ) ), '/' );
		if ( '/' === $route || '' === $route ) {
			$route = '/';
		}

		$wp->query_vars['rest_route'] = $route;
	}
}

add_action( 'parse_request', 'bbai_enable_wp_json_fallback_route', 5 );

// Load cache and DB schema helpers.
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-cache.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-db.php';

// Load unified quality scorer and helper functions.
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-alt-quality-scorer.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-json.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-alt-quality.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-debug.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-trial-quota.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/content/bbai-admin-copy.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-privacy.php';
if ( class_exists( '\BeepBeepAI\AltTextGenerator\Privacy' ) ) {
	\BeepBeepAI\AltTextGenerator\Privacy::init();
}

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-input-validator.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-telemetry.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/automation/phase17-content-pipeline.php';
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

if ( ! function_exists( 'beepbeepai_handle_usage_export_admin_post' ) ) {
	/**
	 * Fallback admin-post handler for usage CSV export.
	 *
	 * @return void
	 */
	function beepbeepai_handle_usage_export_admin_post() {
		if ( ! class_exists( '\BeepBeepAI\AltTextGenerator\Core' ) ) {
			wp_die( esc_html__( 'Export handler unavailable.', 'beepbeep-ai-alt-text-generator' ) );
		}

		$core = new \BeepBeepAI\AltTextGenerator\Core();
		$core->handle_usage_export();
		exit;
	}
}

if ( ! function_exists( 'beepbeepai_handle_debug_export_admin_post' ) ) {
	/**
	 * Fallback admin-post handler for debug log CSV export.
	 *
	 * @return void
	 */
	function beepbeepai_handle_debug_export_admin_post() {
		if ( ! class_exists( '\BeepBeepAI\AltTextGenerator\Core' ) ) {
			wp_die( esc_html__( 'Export handler unavailable.', 'beepbeep-ai-alt-text-generator' ) );
		}

		$core = new \BeepBeepAI\AltTextGenerator\Core();
		$core->handle_debug_log_export();
		exit;
	}
}

// Register export handlers at bootstrap for resilience across legacy/new action slugs.
add_action( 'admin_post_beepbeepai_usage_export', 'beepbeepai_handle_usage_export_admin_post', 0 );
add_action( 'admin_post_bbai_usage_export', 'beepbeepai_handle_usage_export_admin_post', 0 );
add_action( 'admin_post_beepbeepai_debug_export', 'beepbeepai_handle_debug_export_admin_post', 0 );
add_action( 'admin_post_bbai_debug_export', 'beepbeepai_handle_debug_export_admin_post', 0 );

/**
 * Register the activation hook.
 */
function beepbeepai_activate_current_site() {
	require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-activator.php';
	\BeepBeepAI\AltTextGenerator\Activator::activate();
	\BeepBeepAI\AltTextGenerator\DB_Schema::install();
}

/**
 * Register the activation hook.
 *
 * @param bool $network_wide Whether the plugin was network-activated.
 */
function beepbeepai_activate( $network_wide = false ) {
	if ( is_multisite() && $network_wide ) {
		$current_blog_id = get_current_blog_id();
		$site_ids        = get_sites(
			[
				'fields' => 'ids',
				'number' => 0,
			]
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			beepbeepai_activate_current_site();
		}

		switch_to_blog( $current_blog_id );
		return;
	}

	beepbeepai_activate_current_site();
}

/**
 * Register the deactivation hook.
 */
function beepbeepai_deactivate_current_site() {
	require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-deactivator.php';
	\BeepBeepAI\AltTextGenerator\Deactivator::deactivate();
}

/**
 * Register the deactivation hook.
 *
 * @param bool $network_wide Whether the plugin was network-deactivated.
 */
function beepbeepai_deactivate( $network_wide = false ) {
	if ( is_multisite() && $network_wide ) {
		$current_blog_id = get_current_blog_id();
		$site_ids        = get_sites(
			[
				'fields' => 'ids',
				'number' => 0,
			]
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			beepbeepai_deactivate_current_site();
		}

		switch_to_blog( $current_blog_id );
		return;
	}

	beepbeepai_deactivate_current_site();
}

/**
 * Initialize plugin data for sites created after a network activation.
 *
 * @param \WP_Site $new_site New site object.
 */
function beepbeepai_initialize_new_site( $new_site ) {
	if ( ! is_multisite() || ! ( $new_site instanceof \WP_Site ) ) {
		return;
	}

	if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	if ( ! function_exists( 'is_plugin_active_for_network' ) || ! is_plugin_active_for_network( BEEPBEEP_AI_PLUGIN_BASENAME ) ) {
		return;
	}

	switch_to_blog( (int) $new_site->blog_id );
	beepbeepai_activate_current_site();
	restore_current_blog();
}

register_activation_hook( __FILE__, 'beepbeepai_activate' );
register_deactivation_hook( __FILE__, 'beepbeepai_deactivate' );
add_action( 'wp_initialize_site', 'beepbeepai_initialize_new_site', 20 );

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
