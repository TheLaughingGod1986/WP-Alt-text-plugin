<?php
/**
 * Dummy Plugin Class
 *
 * Minimal plugin class for testing framework functionality.
 *
 * @package Optti\Tests
 */

namespace Optti\Tests;

use Optti\Framework\PluginBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Dummy_Plugin
 *
 * Minimal plugin implementation for testing.
 */
class Dummy_Plugin extends PluginBase {

	/**
	 * Get plugin name.
	 *
	 * @return string Plugin name.
	 */
	public function get_plugin_name() {
		return __( 'Dummy Optti Plugin', 'dummy-optti-plugin' );
	}

	/**
	 * Get plugin slug.
	 *
	 * @return string Plugin slug.
	 */
	public function get_plugin_slug() {
		return 'dummy-optti-plugin';
	}

	/**
	 * Get text domain for translations.
	 *
	 * @return string Text domain.
	 */
	protected function get_text_domain() {
		return 'dummy-optti-plugin';
	}

	/**
	 * Register admin menus.
	 *
	 * @return void
	 */
	public function register_admin_menus() {
		add_menu_page(
			__( 'Dummy Plugin', 'dummy-optti-plugin' ),
			__( 'Dummy Plugin', 'dummy-optti-plugin' ),
			'manage_options',
			'dummy-optti-plugin',
			[ $this, 'render_dashboard_page' ],
			'dashicons-admin-generic',
			30
		);
	}

	/**
	 * Render dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard_page() {
		echo '<div class="wrap">';
		echo '<h1>Dummy Optti Plugin</h1>';
		echo '<p>This is a minimal plugin for testing the Optti Framework.</p>';
		echo '</div>';
	}
}

