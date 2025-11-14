<?php
/**
 * Define the admin-specific bootstrap for the plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once OPPTIAI_ALT_PLUGIN_DIR . 'admin/class-opptiai-alt-core.php';
require_once OPPTIAI_ALT_PLUGIN_DIR . 'admin/class-opptiai-alt-rest-controller.php';
require_once OPPTIAI_ALT_PLUGIN_DIR . 'admin/class-opptiai-alt-admin-hooks.php';

class Opptiai_Alt_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Core implementation instance.
	 *
	 * @var Opptiai_Alt_Core|null
	 */
	private $core = null;

	/**
	 * Hook registrar.
	 *
	 * @var Opptiai_Alt_Admin_Hooks|null
	 */
	private $hooks = null;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Bootstrap the legacy implementation.
	 *
	 * This ensures the existing feature-rich class is instantiated once
	 * WordPress has fully loaded.
	 */
	public function bootstrap_core() {
		if ( null !== $this->core ) {
			return;
		}

		$this->core  = new Opptiai_Alt_Core();
		$this->hooks = new Opptiai_Alt_Admin_Hooks( $this->core );
		$this->hooks->register();
	}

	/**
	 * Expose the core instance for integration tests or custom extensions.
	 *
	 * @return Opptiai_Alt_Core|null
	 */
	public function get_core() {
		return $this->core;
	}
}
