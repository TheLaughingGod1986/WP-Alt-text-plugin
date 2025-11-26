<?php
/**
 * Plugin Base Class
 *
 * Abstract base class for all Optti plugins.
 * Plugins should extend this class and implement abstract methods.
 *
 * @package Optti\Framework
 */

namespace Optti\Framework;

use Optti\Framework\Interfaces\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PluginBase
 *
 * Base class for all Optti plugins.
 */
abstract class PluginBase {

	/**
	 * Framework configuration.
	 *
	 * @var array
	 */
	protected $config = [];

	/**
	 * Registered modules.
	 *
	 * @var ModuleInterface[]
	 */
	protected $modules = [];

	/**
	 * Plugin file path.
	 *
	 * @var string
	 */
	protected $plugin_file;

	/**
	 * Initialize the plugin.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @param array  $config Configuration array.
	 */
	public function __construct( $plugin_file, $config = [] ) {
		$this->plugin_file = $plugin_file;
		$this->config      = wp_parse_args( $config, [
			'plugin_slug' => '',
			'version'     => '1.0.0',
			'api_base_url' => 'https://alttext-ai-backend.onrender.com',
			'capability'   => 'manage_options',
			'asset_url'   => '',
			'asset_dir'    => '',
		] );

		$this->load_dependencies();
		$this->init_services();
	}

	/**
	 * Load framework dependencies.
	 *
	 * @return void
	 */
	protected function load_dependencies() {
		// Core classes are autoloaded, but ensure logger table exists.
		Logger::create_table();
	}

	/**
	 * Initialize framework services.
	 *
	 * @return void
	 */
	protected function init_services() {
		// Load text domain on init action with priority 0 (WordPress 6.7.0+ requirement).
		add_action( 'init', [ $this, 'load_textdomain' ], 0 );

		// Initialize license service on init (after translations are loaded).
		add_action( 'init', [ $this, 'init_license' ], 1 );

		// Initialize admin system if in admin.
		if ( is_admin() ) {
			$this->init_admin();
		}

		// Register plugin-specific modules.
		$this->register_modules();

		// Log initialization.
		Logger::log( 'info', 'Optti Plugin initialized', [
			'plugin'  => $this->get_plugin_slug(),
			'version' => $this->get_version(),
		], 'core' );
	}

	/**
	 * Initialize license service (called on init action).
	 *
	 * @return void
	 */
	public function init_license() {
		LicenseManager::instance()->init();
	}

	/**
	 * Load plugin text domain.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		$text_domain = $this->get_text_domain();
		if ( ! empty( $text_domain ) && ! is_textdomain_loaded( $text_domain ) ) {
			$plugin_dir = dirname( plugin_basename( $this->plugin_file ) );
			\load_plugin_textdomain(
				$text_domain,
				false,
				$plugin_dir . '/languages'
			);
		}
	}

	/**
	 * Register all plugin modules.
	 * Override this method in child classes to register plugin-specific modules.
	 *
	 * @return void
	 */
	protected function register_modules() {
		// Plugin-specific modules should be registered here.
	}

	/**
	 * Initialize admin system.
	 *
	 * @return void
	 */
	protected function init_admin() {
		// Enqueue admin assets.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

		// Register admin menus.
		add_action( 'admin_menu', [ $this, 'register_admin_menus' ] );

		// Register REST routes.
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// Register AJAX handlers.
		$this->register_ajax_handlers();
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets() {
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, $this->get_plugin_slug() ) === false ) {
			return;
		}

		// Enqueue framework assets.
		$asset_url = $this->config['asset_url'] ?? '';
		if ( ! empty( $asset_url ) ) {
			wp_enqueue_style(
				'optti-framework-admin',
				trailingslashit( $asset_url ) . 'admin.css',
				[],
				$this->get_version()
			);

			wp_enqueue_script(
				'optti-framework-admin',
				trailingslashit( $asset_url ) . 'admin.js',
				[ 'jquery' ],
				$this->get_version(),
				true
			);

			// Localize script.
			wp_localize_script( 'optti-framework-admin', 'OPTTI_PLUGIN', [
				'apiBase'     => $this->config['api_base_url'] ?? 'https://alttext-ai-backend.onrender.com',
				'pluginSlug'  => $this->get_plugin_slug(),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'userInfo'    => $this->get_user_info(),
				'hasLicense'  => LicenseManager::instance()->has_active_license(),
			] );
			
			// Add Optti API configuration
			wp_localize_script( 'optti-framework-admin', 'opttiApi', [
				'baseUrl' => $this->config['api_base_url'] ?? 'https://alttext-ai-backend.onrender.com',
				'plugin' => $this->get_plugin_slug(),
				'site'   => home_url()
			] );
		}
	}

	/**
	 * Register admin menus.
	 * Override this method in child classes to register plugin-specific menus.
	 *
	 * @return void
	 */
	public function register_admin_menus() {
		// Plugin-specific menus should be registered here.
	}

	/**
	 * REST controller instance.
	 *
	 * @var REST_Controller|null
	 */
	protected $rest_controller = null;

	/**
	 * Register REST routes.
	 * Override this method in child classes to register plugin-specific REST routes.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		// If a REST controller is set, use it.
		if ( $this->rest_controller ) {
			$this->rest_controller->register_routes();
			return;
		}

		// Otherwise, plugin-specific REST routes should be registered here.
	}

	/**
	 * Set REST controller.
	 *
	 * @param REST_Controller $controller REST controller instance.
	 * @return void
	 */
	protected function set_rest_controller( REST_Controller $controller ) {
		$this->rest_controller = $controller;
	}

	/**
	 * Register AJAX handlers.
	 * Override this method in child classes to register plugin-specific AJAX handlers.
	 *
	 * @return void
	 */
	protected function register_ajax_handlers() {
		// Plugin-specific AJAX handlers should be registered here.
	}

	/**
	 * Register a module.
	 *
	 * @param ModuleInterface $module Module instance.
	 * @return void
	 */
	public function register_module( ModuleInterface $module ) {
		$module_id = $module->get_id();

		if ( isset( $this->modules[ $module_id ] ) ) {
			Logger::log( 'warning', 'Module already registered', [
				'module_id' => $module_id,
			], 'core' );
			return;
		}

		$this->modules[ $module_id ] = $module;

		// Initialize module if active.
		if ( $module->is_active() ) {
			$module->init();
		}

		Logger::log( 'debug', 'Module registered', [
			'module_id' => $module_id,
			'name'      => $module->get_name(),
		], 'core' );
	}

	/**
	 * Get a registered module.
	 *
	 * @param string $module_id Module ID.
	 * @return ModuleInterface|null Module instance or null.
	 */
	public function get_module( $module_id ) {
		return $this->modules[ $module_id ] ?? null;
	}

	/**
	 * Get all registered modules.
	 *
	 * @return ModuleInterface[] Array of modules.
	 */
	public function get_modules() {
		return $this->modules;
	}

	/**
	 * Get plugin version.
	 *
	 * @return string Version.
	 */
	public function get_version() {
		return $this->config['version'] ?? '1.0.0';
	}

	/**
	 * Get user info for localization.
	 *
	 * @return array User info.
	 */
	protected function get_user_info() {
		$api = ApiClient::instance();
		$user_data = $api->get_user_data();
		return $user_data ?: [];
	}

	/**
	 * Get usage stats.
	 *
	 * @param string|null $license_key License key.
	 * @param string|null $site_url Site URL.
	 * @return array|\WP_Error Usage data.
	 */
	public function get_usage( $license_key = null, $site_url = null ) {
		$api = ApiClient::instance();
		return $api->get_usage( $license_key, $site_url );
	}

	/**
	 * Get billing portal URL.
	 *
	 * @param int|null $user_id User ID.
	 * @return string|\WP_Error Portal URL.
	 */
	public function get_billing_portal_url( $user_id = null ) {
		$api = ApiClient::instance();
		return $api->get_billing_portal_url( $user_id );
	}

	/**
	 * Record analytics event.
	 *
	 * @param string      $event_name Event name.
	 * @param array       $payload Event payload.
	 * @param string|null $license_key License key.
	 * @return array|\WP_Error Response.
	 */
	public function record_analytics_event( $event_name, $payload = [], $license_key = null ) {
		$api = ApiClient::instance();
		$license_key = $license_key ?: LicenseManager::instance()->get_license_key();
		return $api->record_event( $license_key, $event_name, $payload, $this->get_plugin_slug() );
	}

	/**
	 * Activate the plugin.
	 *
	 * @return void
	 */
	public function activate() {
		// Create database tables.
		Logger::create_table();

		// Create performance indexes.
		DbOptimizer::instance()->create_indexes();

		// Run activation hooks for all modules.
		foreach ( $this->modules as $module ) {
			if ( method_exists( $module, 'activate' ) ) {
				$module->activate();
			}
		}

		Logger::log( 'info', 'Plugin activated', [
			'plugin' => $this->get_plugin_slug(),
		], 'core' );
	}

	/**
	 * Deactivate the plugin.
	 *
	 * @return void
	 */
	public function deactivate() {
		// Run deactivation hooks for all modules.
		foreach ( $this->modules as $module ) {
			if ( method_exists( $module, 'deactivate' ) ) {
				$module->deactivate();
			}
		}

		Logger::log( 'info', 'Plugin deactivated', [
			'plugin' => $this->get_plugin_slug(),
		], 'core' );
	}

	/**
	 * Get plugin configuration.
	 *
	 * @return array Configuration.
	 */
	public function get_config() {
		return $this->config;
	}

	/**
	 * Get plugin file path.
	 *
	 * @return string Plugin file path.
	 */
	public function get_plugin_file() {
		return $this->plugin_file;
	}

	// Abstract methods that must be implemented by child classes.

	/**
	 * Get plugin name.
	 *
	 * @return string Plugin name.
	 */
	abstract public function get_plugin_name();

	/**
	 * Get plugin slug.
	 *
	 * @return string Plugin slug.
	 */
	abstract public function get_plugin_slug();

	/**
	 * Get text domain for translations.
	 *
	 * @return string Text domain.
	 */
	abstract protected function get_text_domain();
}

