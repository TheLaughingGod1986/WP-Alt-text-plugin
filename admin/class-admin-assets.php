<?php
/**
 * Admin Assets Class
 *
 * Handles CSS and JavaScript asset enqueuing for admin pages.
 *
 * @package Optti\Admin
 */

namespace Optti\Admin;

use Optti\Framework\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Assets
 *
 * Manages admin assets.
 */
class Admin_Assets {

	use Singleton;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Initialize admin assets.
	 */
	protected function __construct() {
		$this->version = defined( 'OPTTI_VERSION' ) ? OPTTI_VERSION : '1.0.0';
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		// Only load on Optti admin pages.
		if ( strpos( $hook, 'optti' ) === false ) {
			return;
		}

		// Enqueue styles.
		$this->enqueue_styles();

		// Enqueue scripts.
		$this->enqueue_scripts();

		// Localize scripts.
		$this->localize_scripts();
	}

	/**
	 * Enqueue admin styles.
	 *
	 * @return void
	 */
	protected function enqueue_styles() {
		$base_url = OPTTI_PLUGIN_URL;
		$use_debug_assets = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
		$css_base = $use_debug_assets ? 'assets/src/css/' : 'assets/dist/css/';
		
		$asset_path = function( $name, $debug ) use ( $css_base ) {
			$extension = $debug ? '.css' : '.min.css';
			$minified_path = $css_base . $name . $extension;
			// If minified file doesn't exist, fall back to source file
			if ( ! $debug && ! file_exists( OPTTI_PLUGIN_DIR . $minified_path ) ) {
				$source_base = str_replace( 'assets/dist/', 'assets/src/', $css_base );
				return $source_base . $name . '.css';
			}
			return $minified_path;
		};

		// Enqueue design system (FIRST - foundation for all styles)
		wp_enqueue_style(
			'bbai-design-system',
			$base_url . $asset_path( 'design-system', $use_debug_assets ),
			[],
			$this->version
		);

		// Enqueue reusable components (SECOND - uses design tokens)
		wp_enqueue_style(
			'bbai-components',
			$base_url . $asset_path( 'components', $use_debug_assets ),
			[ 'bbai-design-system' ],
			$this->version
		);

		// Enqueue page-specific styles (use design system + components)
		wp_enqueue_style(
			'bbai-dashboard',
			$base_url . $asset_path( 'bbai-dashboard', $use_debug_assets ),
			[ 'bbai-components' ],
			$this->version
		);
		
		wp_enqueue_style(
			'bbai-modern',
			$base_url . $asset_path( 'modern-style', $use_debug_assets ),
			[ 'bbai-components' ],
			$this->version
		);
		
		wp_enqueue_style(
			'bbai-ui',
			$base_url . $asset_path( 'ui', $use_debug_assets ),
			[ 'bbai-modern' ],
			$this->version
		);
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @return void
	 */
	protected function enqueue_scripts() {
		$base_url = OPTTI_PLUGIN_URL;
		$use_debug_assets = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
		$js_base = $use_debug_assets ? 'assets/src/js/' : 'assets/dist/js/';
		
		$asset_path = function( $name, $debug ) use ( $js_base ) {
			$extension = $debug ? '.js' : '.min.js';
			$minified_path = $js_base . $name . $extension;
			// If minified file doesn't exist, fall back to source file
			if ( ! $debug && ! file_exists( OPTTI_PLUGIN_DIR . $minified_path ) ) {
				$source_base = str_replace( 'assets/dist/', 'assets/src/', $js_base );
				return $source_base . $name . '.js';
			}
			return $minified_path;
		};

		// Enqueue dashboard script (loads first, provides BBAI_DASH config)
		wp_enqueue_script(
			'bbai-dashboard',
			$base_url . $asset_path( 'bbai-dashboard', $use_debug_assets ),
			[ 'jquery' ],
			$this->version,
			true
		);

		// Enqueue admin script (depends on dashboard for config)
		wp_enqueue_script(
			'bbai-admin',
			$base_url . $asset_path( 'bbai-admin', $use_debug_assets ),
			[ 'jquery', 'bbai-dashboard' ],
			$this->version,
			true
		);
	}

	/**
	 * Localize scripts with data.
	 *
	 * @return void
	 */
	protected function localize_scripts() {
		// Use the same localization as BeepBeep AI pages for consistency
		// Get Core instance to access the same data
		if ( ! class_exists( '\BeepBeepAI\AltTextGenerator\Core' ) ) {
			return;
		}
		
		$core = new \BeepBeepAI\AltTextGenerator\Core();
		$checkout_prices = method_exists( $core, 'get_checkout_price_ids' ) ? $core->get_checkout_price_ids() : [];
		
		$l10n_common = [
			'reviewCue'           => __( 'Visit the ALT Library to double-check the wording.', 'wp-alt-text-plugin' ),
			'statusReady'         => '',
			'previewAltHeading'   => __( 'Review generated ALT text', 'wp-alt-text-plugin' ),
			'previewAltHint'      => __( 'Review the generated description before applying it to your media item.', 'wp-alt-text-plugin' ),
			'previewAltApply'     => __( 'Use this ALT', 'wp-alt-text-plugin' ),
			'previewAltCancel'    => __( 'Keep current ALT', 'wp-alt-text-plugin' ),
			'previewAltDismissed' => __( 'Preview dismissed. Existing ALT kept.', 'wp-alt-text-plugin' ),
			'previewAltShortcut'  => __( 'Shift + Enter for newline.', 'wp-alt-text-plugin' ),
		];
		
		// Localize bbai-dashboard script (provides BBAI_DASH config)
		wp_localize_script(
			'bbai-dashboard',
			'BBAI_DASH',
			[
				'rest'      => esc_url_raw( rest_url( 'bbai/v1/' ) ),
				'restAlt'   => esc_url_raw( rest_url( 'bbai/v1/alt/' ) ),
				'restStats' => esc_url_raw( rest_url( 'bbai/v1/stats' ) ),
				'restUsage' => esc_url_raw( rest_url( 'bbai/v1/usage' ) ),
				'restMissing' => esc_url_raw( add_query_arg( [ 'scope' => 'missing' ], rest_url( 'bbai/v1/list' ) ) ),
				'restAll'    => esc_url_raw( add_query_arg( [ 'scope' => 'all' ], rest_url( 'bbai/v1/list' ) ) ),
				'restQueue'  => esc_url_raw( rest_url( 'bbai/v1/queue' ) ),
				'restRoot'   => esc_url_raw( rest_url() ),
				'restPlans'  => esc_url_raw( rest_url( 'bbai/v1/plans' ) ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'l10n'       => $l10n_common,
				'upgradeUrl' => esc_url( method_exists( $core, 'get_upgrade_url' ) ? $core->get_upgrade_url() : '' ),
				'billingPortalUrl' => esc_url( method_exists( $core, 'get_billing_portal_url' ) ? $core->get_billing_portal_url() : '' ),
				'checkoutPrices' => $checkout_prices,
				'canManage' => method_exists( $core, 'user_can_manage' ) ? $core->user_can_manage() : false,
			]
		);
		
		// Localize bbai-admin script
		wp_localize_script(
			'bbai-admin',
			'BBAI',
			[
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'rest'      => esc_url_raw( rest_url( 'bbai/v1/' ) ),
				'restAlt'   => esc_url_raw( rest_url( 'bbai/v1/alt/' ) ),
				'restStats' => esc_url_raw( rest_url( 'bbai/v1/stats' ) ),
				'restUsage' => esc_url_raw( rest_url( 'bbai/v1/usage' ) ),
				'restMissing' => esc_url_raw( add_query_arg( [ 'scope' => 'missing' ], rest_url( 'bbai/v1/list' ) ) ),
				'restAll'    => esc_url_raw( add_query_arg( [ 'scope' => 'all' ], rest_url( 'bbai/v1/list' ) ) ),
				'restQueue'  => esc_url_raw( rest_url( 'bbai/v1/queue' ) ),
				'restRoot'   => esc_url_raw( rest_url() ),
				'restPlans'  => esc_url_raw( rest_url( 'bbai/v1/plans' ) ),
				'l10n'       => $l10n_common,
				'upgradeUrl' => esc_url( method_exists( $core, 'get_upgrade_url' ) ? $core->get_upgrade_url() : '' ),
				'billingPortalUrl' => esc_url( method_exists( $core, 'get_billing_portal_url' ) ? $core->get_billing_portal_url() : '' ),
				'checkoutPrices' => $checkout_prices,
				'canManage' => method_exists( $core, 'user_can_manage' ) ? $core->user_can_manage() : false,
				'inlineBatchSize' => defined( 'BBAI_INLINE_BATCH' ) ? max( 1, intval( BBAI_INLINE_BATCH ) ) : 1,
			]
		);
		
		// Add bbai_ajax for regenerate functionality
		$admin_options = get_option( 'bbai_settings', [] );
		$production_url = 'https://alttext-ai-backend.onrender.com';
		$admin_api_url = isset( $admin_options['api_url'] ) && ! empty( $admin_options['api_url'] ) ? $admin_options['api_url'] : $production_url;
		
		wp_localize_script(
			'bbai-admin',
			'bbai_ajax',
			[
				'ajaxurl'   => admin_url( 'admin-ajax.php' ),
				'ajax_url'  => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'bbai_upgrade_nonce' ),
				'api_url'   => $admin_api_url,
				'can_manage' => method_exists( $core, 'user_can_manage' ) ? $core->user_can_manage() : false,
			]
		);
		
		// Add Optti API configuration
		wp_localize_script(
			'bbai-admin',
			'opttiApi',
			[
				'baseUrl' => 'https://alttext-ai-backend.onrender.com',
				'plugin'  => 'beepbeep-ai',
				'site'    => home_url(),
			]
		);
	}

	/**
	 * Enqueue a specific asset.
	 *
	 * @param string $handle Asset handle.
	 * @param string $src Asset URL.
	 * @param array  $deps Dependencies.
	 * @param string $version Version.
	 * @param bool   $in_footer Load in footer.
	 * @return void
	 */
	public function enqueue_script( $handle, $src, $deps = [], $version = null, $in_footer = true ) {
		wp_enqueue_script(
			$handle,
			$src,
			$deps,
			$version ?: $this->version,
			$in_footer
		);
	}

	/**
	 * Enqueue a specific style.
	 *
	 * @param string $handle Asset handle.
	 * @param string $src Asset URL.
	 * @param array  $deps Dependencies.
	 * @param string $version Version.
	 * @return void
	 */
	public function enqueue_style( $handle, $src, $deps = [], $version = null ) {
		wp_enqueue_style(
			$handle,
			$src,
			$deps,
			$version ?: $this->version
		);
	}
}

