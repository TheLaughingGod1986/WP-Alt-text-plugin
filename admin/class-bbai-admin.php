<?php
/**
 * Define the admin-specific bootstrap for the plugin.
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-core.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-rest-controller.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-admin-hooks.php';

class Admin {

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
	 * @var \BeepBeepAI\AltTextGenerator\Core|null
	 */
	private $core = null;

	/**
	 * Hook registrar.
	 *
	 * @var \BeepBeepAI\AltTextGenerator\Admin_Hooks|null
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

		// Use singleton instance to avoid multiple instantiations
		$this->core  = \BeepBeepAI\AltTextGenerator\Core::get_instance();
		$this->hooks = new \BeepBeepAI\AltTextGenerator\Admin_Hooks( $this->core );
		$this->hooks->register();
	}

	/**
	 * Ensure REST routes are registered even if bootstrap hasn't run yet.
	 * This is called directly on rest_api_init to guarantee routes are available.
	 */
	public function ensure_rest_routes() {
		$this->bootstrap_core();
	}

	/**
	 * Expose the core instance for integration tests or custom extensions.
	 *
	 * @return \BeepBeepAI\AltTextGenerator\Core|null
	 */
	public function get_core() {
		return $this->core;
	}
}
