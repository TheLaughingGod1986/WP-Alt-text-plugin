<?php
/**
 * Admin Menu Class
 *
 * Handles WordPress admin menu registration and management.
 *
 * @package Optti\Admin
 */

namespace Optti\Admin;

use Optti\Framework\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Menu
 *
 * Manages admin menu registration.
 */
class Admin_Menu {

	use Singleton;

	/**
	 * Menu pages.
	 *
	 * @var array
	 */
	protected $pages = array();

	/**
	 * Initialize the admin menu.
	 */
	protected function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
	}

	/**
	 * Register admin menus.
	 *
	 * @return void
	 */
	public function register_menus() {
		// Register main menu page.
		// Use translation only if text domain is loaded (after init).
		$menu_title = is_textdomain_loaded( 'beepbeep-ai-alt-text-generator' )
			? __( 'Optti', 'beepbeep-ai-alt-text-generator' )
			: 'Optti';
		$page_title = is_textdomain_loaded( 'beepbeep-ai-alt-text-generator' )
			? __( 'Optti', 'beepbeep-ai-alt-text-generator' )
			: 'Optti';

		$main_page = add_menu_page(
			$page_title,
			$menu_title,
			'manage_options',
			'optti',
			array( $this, 'render_page' ),
			'dashicons-images-alt2',
			30
		);

		// Register submenu pages.
		$this->register_submenu_pages();
	}

	/**
	 * Register submenu pages.
	 *
	 * @return void
	 */
	protected function register_submenu_pages() {
		// Helper to safely translate strings (only after init).
		$translate = function ( $text ) {
			return is_textdomain_loaded( 'beepbeep-ai-alt-text-generator' )
				? __( $text, 'beepbeep-ai-alt-text-generator' )
				: $text;
		};

		// Dashboard - redirects to legacy dashboard.
		add_submenu_page(
			'optti',
			$translate( 'Dashboard' ),
			$translate( 'Dashboard' ),
			'manage_options',
			'optti',
			array( $this, 'render_page' )
		);

		// ALT Library - redirects to legacy library tab.
		add_submenu_page(
			'optti',
			$translate( 'ALT Library' ),
			$translate( 'ALT Library' ),
			'manage_options',
			'optti-library',
			array( $this, 'render_page' )
		);

		// Credit Usage - redirects to legacy credit-usage tab.
		add_submenu_page(
			'optti',
			$translate( 'Credit Usage' ),
			$translate( 'Credit Usage' ),
			'manage_options',
			'optti-credit-usage',
			array( $this, 'render_page' )
		);

		// Settings - redirects to legacy settings tab.
		add_submenu_page(
			'optti',
			$translate( 'Settings' ),
			$translate( 'Settings' ),
			'manage_options',
			'optti-settings',
			array( $this, 'render_page' )
		);

		// License - redirects to legacy dashboard (license info shown there).
		add_submenu_page(
			'optti',
			$translate( 'License' ),
			$translate( 'License' ),
			'manage_options',
			'optti-license',
			array( $this, 'render_page' )
		);

		// Analytics - redirects to legacy dashboard (analytics shown there).
		add_submenu_page(
			'optti',
			$translate( 'Analytics' ),
			$translate( 'Analytics' ),
			'manage_options',
			'optti-analytics',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public function render_page() {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 'optti';

		// Check if we can access the legacy Core class.
		// If not, show an error message.
		if ( ! class_exists( '\BeepBeepAI\AltTextGenerator\Core' ) ) {
			wp_die(
				'<h1>' . esc_html__( 'Plugin Error', 'beepbeep-ai-alt-text-generator' ) . '</h1>' .
				'<p>' . esc_html__( 'The BeepBeep AI Core class is not available. Please deactivate and reactivate the plugin.', 'beepbeep-ai-alt-text-generator' ) . '</p>',
				esc_html__( 'Plugin Error', 'beepbeep-ai-alt-text-generator' ),
				array( 'response' => 500 )
			);
		}

		// Try to get the Core instance from the Admin class.
		// The Admin class should have bootstrapped the Core.
		global $wp_filter;
		$core = null;

		// Use singleton instance to avoid multiple instantiations
		try {
			$core = \BeepBeepAI\AltTextGenerator\Core::get_instance();
		} catch ( \Exception $e ) {
			wp_die(
				'<h1>' . esc_html__( 'Plugin Error', 'beepbeep-ai-alt-text-generator' ) . '</h1>' .
				'<p>' . esc_html__( 'Failed to initialize the plugin core. Please check your error logs.', 'beepbeep-ai-alt-text-generator' ) . '</p>',
				esc_html__( 'Plugin Error', 'beepbeep-ai-alt-text-generator' ),
				array( 'response' => 500 )
			);
		}

		// Map framework menu pages to legacy menu tabs.
		$tab_map = array(
			'optti'              => '', // Dashboard (no tab)
			'optti-library'      => 'library',
			'optti-credit-usage' => 'credit-usage',
			'optti-settings'     => 'settings',
			'optti-license'      => '', // License (shown on dashboard)
			'optti-analytics'    => '', // Analytics (shown on dashboard)
		);

		$tab = $tab_map[ $page ] ?? '';

		// Set the tab in $_GET so the legacy renderer can see it.
		if ( ! empty( $tab ) ) {
			$_GET['tab'] = $tab;
		}

		// Render the legacy settings page directly.
		// This bypasses the redirect and permission issues.
		if ( method_exists( $core, 'render_settings_page' ) ) {
			$core->render_settings_page();
		} else {
			wp_die(
				'<h1>' . esc_html__( 'Plugin Error', 'beepbeep-ai-alt-text-generator' ) . '</h1>' .
				'<p>' . esc_html__( 'The plugin core does not have a render method. Please contact support.', 'beepbeep-ai-alt-text-generator' ) . '</p>',
				esc_html__( 'Plugin Error', 'beepbeep-ai-alt-text-generator' ),
				array( 'response' => 500 )
			);
		}
	}

	/**
	 * Register a custom page.
	 *
	 * @param string   $slug Page slug.
	 * @param string   $title Page title.
	 * @param string   $capability Required capability.
	 * @param callable $callback Render callback.
	 * @return void
	 */
	public function register_page( $slug, $title, $capability, $callback ) {
		$this->pages[ $slug ] = array(
			'title'      => $title,
			'capability' => $capability,
			'callback'   => $callback,
		);
	}
}
