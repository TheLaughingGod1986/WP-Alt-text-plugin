<?php
/**
 * Main plugin class following the WordPress Plugin Boilerplate conventions.
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {

	/**
	 * Loader responsible for maintaining WordPress hooks.
	 *
	 * @var Loader
	 */
	protected $loader;

	/**
	 * Unique identifier for the plugin.
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * Current plugin version.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Set up the plugin.
	 */
	public function __construct() {
		$this->version     = defined( 'BEEPBEEP_AI_VERSION' ) ? BEEPBEEP_AI_VERSION : '1.0.0';
		$this->plugin_name = 'beepbeep-ai-alt-text-generator';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
	}

	/**
	 * Load the dependencies required by this plugin.
	 */
	private function load_dependencies() {
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-loader.php';
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-admin.php';

		$this->loader = new Loader();
	}

	/**
	 * Register locale.
	 * WordPress.org loads plugin translations automatically from the /languages directory.
	 */
	private function set_locale() {
		// Intentionally left empty: translations are auto-loaded from /languages on WordPress.org.
	}

	/**
	 * Register all of the hooks related to the admin area functionality.
	 */
	private function define_admin_hooks() {
		$plugin_admin = new Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'init', $plugin_admin, 'bootstrap_core', 0 );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * Retrieve the plugin name.
	 *
	 * @return string
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Retrieve the loader instance.
	 *
	 * @return Loader
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the plugin version number.
	 *
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}
}
