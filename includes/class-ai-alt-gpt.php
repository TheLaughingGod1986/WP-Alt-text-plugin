<?php
/**
 * Main plugin class following the WordPress Plugin Boilerplate conventions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ai_Alt_Gpt {

	/**
	 * Loader responsible for maintaining WordPress hooks.
	 *
	 * @var Ai_Alt_Gpt_Loader
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
		$this->version     = defined( 'AI_ALT_GPT_VERSION' ) ? AI_ALT_GPT_VERSION : '1.0.0';
		$this->plugin_name = 'ai-alt-gpt';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the dependencies required by this plugin.
	 */
	private function load_dependencies() {
		require_once AI_ALT_GPT_PLUGIN_DIR . 'includes/class-ai-alt-gpt-loader.php';
		require_once AI_ALT_GPT_PLUGIN_DIR . 'includes/class-ai-alt-gpt-i18n.php';
		require_once AI_ALT_GPT_PLUGIN_DIR . 'admin/class-ai-alt-gpt-admin.php';
		require_once AI_ALT_GPT_PLUGIN_DIR . 'public/class-ai-alt-gpt-public.php';

		$this->loader = new Ai_Alt_Gpt_Loader();
	}

	/**
	 * Register locale.
	 */
	private function set_locale() {
		$plugin_i18n = new Ai_Alt_Gpt_I18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality.
	 */
	private function define_admin_hooks() {
		$plugin_admin = new Ai_Alt_Gpt_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'init', $plugin_admin, 'bootstrap_core', 0 );
	}

	/**
	 * Register hooks for the public-facing functionality.
	 */
	private function define_public_hooks() {
		// Current plugin does not ship public-facing assets, so no hooks required.
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
	 * @return Ai_Alt_Gpt_Loader
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
