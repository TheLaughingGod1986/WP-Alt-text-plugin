<?php
/**
 * Main plugin class following the WordPress Plugin Boilerplate conventions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Opptiai_Alt {

	/**
	 * Loader responsible for maintaining WordPress hooks.
	 *
	 * @var Opptiai_Alt_Loader
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
		$this->version     = defined( 'OPPTIAI_ALT_VERSION' ) ? OPPTIAI_ALT_VERSION : '1.0.0';
		$this->plugin_name = 'wp-alt-text-plugin';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
	}

	/**
	 * Load the dependencies required by this plugin.
	 */
	private function load_dependencies() {
		require_once OPPTIAI_ALT_PLUGIN_DIR . 'includes/class-opptiai-alt-loader.php';
		require_once OPPTIAI_ALT_PLUGIN_DIR . 'includes/class-opptiai-alt-i18n.php';
		require_once OPPTIAI_ALT_PLUGIN_DIR . 'admin/class-opptiai-alt-admin.php';

		$this->loader = new Opptiai_Alt_Loader();
	}

	/**
	 * Register locale.
	 */
	private function set_locale() {
		$plugin_i18n = new Opptiai_Alt_I18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality.
	 */
	private function define_admin_hooks() {
		$plugin_admin = new Opptiai_Alt_Admin( $this->get_plugin_name(), $this->get_version() );

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
	 * @return Opptiai_Alt_Loader
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
