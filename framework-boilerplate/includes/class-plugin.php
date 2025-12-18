<?php
/**
 * Main Plugin Class
 *
 * Follows WordPress Plugin Boilerplate conventions.
 * This class orchestrates the plugin initialization and hook registration.
 *
 * @package MyPlugin
 * @since   1.0.0
 */

namespace MyPlugin;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Class
 *
 * The main plugin class that coordinates all plugin functionality.
 * Responsible for:
 * - Loading dependencies
 * - Setting up internationalization
 * - Registering WordPress hooks
 *
 * @since 1.0.0
 */
class Plugin {

	/**
	 * Hook loader instance.
	 *
	 * @since  1.0.0
	 * @var    Loader
	 */
	protected $loader;

	/**
	 * Unique plugin identifier.
	 *
	 * @since  1.0.0
	 * @var    string
	 */
	protected $plugin_name;

	/**
	 * Current plugin version.
	 *
	 * @since  1.0.0
	 * @var    string
	 */
	protected $version;

	/**
	 * Initialize the plugin.
	 *
	 * Sets the plugin name and version, loads dependencies,
	 * sets up internationalization, and registers hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->version     = defined( 'MYPLUGIN_VERSION' ) ? MYPLUGIN_VERSION : '1.0.0';
		$this->plugin_name = 'my-plugin';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load required dependencies.
	 *
	 * Create an instance of the loader which will be used to register
	 * the hooks with WordPress.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function load_dependencies() {
		$this->loader = new Loader();
	}

	/**
	 * Set up plugin internationalization.
	 *
	 * Loads plugin text domain for translations.
	 * WordPress.org automatically loads translations from /languages directory.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function set_locale() {
		$this->loader->add_action(
			'plugins_loaded',
			$this,
			'load_plugin_textdomain'
		);
	}

	/**
	 * Load plugin text domain.
	 *
	 * @since 1.0.0
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'my-plugin',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}

	/**
	 * Register admin area hooks.
	 *
	 * Define hooks for admin-specific functionality.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function define_admin_hooks() {
		// Example: Enqueue admin scripts
		// $this->loader->add_action('admin_enqueue_scripts', $this, 'enqueue_admin_scripts');

		// Example: Add admin menu
		// $this->loader->add_action('admin_menu', $this, 'add_admin_menu');
	}

	/**
	 * Register public-facing hooks.
	 *
	 * Define hooks for front-end functionality.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function define_public_hooks() {
		// Example: Enqueue public scripts
		// $this->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_public_scripts');

		// Example: Register shortcodes
		// $this->loader->add_action('init', $this, 'register_shortcodes');
	}

	/**
	 * Execute all registered hooks.
	 *
	 * Runs the loader to execute all WordPress hooks that have been registered.
	 *
	 * @since 1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * Get the plugin name.
	 *
	 * @since  1.0.0
	 * @return string The plugin name.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Get the loader instance.
	 *
	 * Reference to the class that orchestrates the hooks.
	 *
	 * @since  1.0.0
	 * @return Loader Hook loader instance.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Get the plugin version.
	 *
	 * @since  1.0.0
	 * @return string The plugin version.
	 */
	public function get_version() {
		return $this->version;
	}
}
