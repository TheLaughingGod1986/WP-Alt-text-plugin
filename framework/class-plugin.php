<?php
/**
 * Plugin Class
 *
 * Main plugin loader and module registry.
 * This is the core of the Optti framework.
 *
 * @package Optti\Framework
 */

namespace Optti\Framework;

use Optti\Framework\Interfaces\Module;
use Optti\Framework\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 *
 * Main plugin class that orchestrates all components.
 */
class Plugin {

	use Singleton;

	/**
	 * Registered modules.
	 *
	 * @var Module[]
	 */
	protected $modules = [];

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Plugin name.
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * Initialize the plugin.
	 */
	protected function __construct() {
		$this->version     = defined( 'OPTTI_VERSION' ) ? OPTTI_VERSION : '1.0.0';
		$this->plugin_name = 'optti-framework';

		$this->load_dependencies();
		$this->init_services();
	}

	/**
	 * Load framework dependencies.
	 *
	 * @return void
	 */
	protected function load_dependencies() {
		// Core classes.
		require_once __DIR__ . '/class-api.php';
		require_once __DIR__ . '/class-logger.php';
		require_once __DIR__ . '/class-cache.php';
		require_once __DIR__ . '/class-db.php';
		require_once __DIR__ . '/class-license.php';

		// Initialize logger table.
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

		// Register modules.
		$this->register_modules();

		// Log initialization.
		Logger::log( 'info', 'Optti Framework initialized', [
			'version' => $this->version,
		], 'core' );
	}

	/**
	 * Initialize license service (called on init action).
	 *
	 * @return void
	 */
	public function init_license() {
		License::instance()->init();
	}

	/**
	 * Load plugin text domain.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		// Only load if not already loaded to prevent duplicate loading warnings
		if ( ! is_textdomain_loaded( 'beepbeep-ai-alt-text-generator' ) ) {
			\load_plugin_textdomain(
				'beepbeep-ai-alt-text-generator',
				false,
				\dirname( OPTTI_PLUGIN_BASENAME ) . '/languages'
			);
		}
	}

	/**
	 * Register all plugin modules.
	 *
	 * @return void
	 */
	protected function register_modules() {
		// Load module classes.
		require_once OPTTI_PLUGIN_DIR . 'includes/modules/class-alt-generator.php';
		require_once OPTTI_PLUGIN_DIR . 'includes/modules/class-image-scanner.php';
		require_once OPTTI_PLUGIN_DIR . 'includes/modules/class-bulk-processor.php';
		require_once OPTTI_PLUGIN_DIR . 'includes/modules/class-metrics.php';

		// Register modules.
		$this->register_module( new \Optti\Modules\Alt_Generator() );
		$this->register_module( new \Optti\Modules\Image_Scanner() );
		$this->register_module( new \Optti\Modules\Bulk_Processor() );
		$this->register_module( new \Optti\Modules\Metrics() );
	}

	/**
	 * Initialize admin system.
	 *
	 * @return void
	 */
	protected function init_admin() {
		// Load admin classes.
		require_once OPTTI_PLUGIN_DIR . 'admin/class-admin-menu.php';
		require_once OPTTI_PLUGIN_DIR . 'admin/class-admin-assets.php';
		require_once OPTTI_PLUGIN_DIR . 'admin/class-admin-notices.php';
		require_once OPTTI_PLUGIN_DIR . 'admin/class-page-renderer.php';

		// Initialize admin components.
		// NOTE: Admin_Menu is disabled to prevent conflict with BeepBeep AI menu
		// \Optti\Admin\Admin_Menu::instance();
		\Optti\Admin\Admin_Assets::instance();
		\Optti\Admin\Admin_Notices::instance();
	}

	/**
	 * Register a module.
	 *
	 * @param Module $module Module instance.
	 * @return void
	 */
	public function register_module( Module $module ) {
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
	 * @return Module|null Module instance or null.
	 */
	public function get_module( $module_id ) {
		return $this->modules[ $module_id ] ?? null;
	}

	/**
	 * Get all registered modules.
	 *
	 * @return Module[] Array of modules.
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
		return $this->version;
	}

	/**
	 * Get plugin name.
	 *
	 * @return string Plugin name.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
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
		require_once __DIR__ . '/class-db-optimizer.php';
		DB_Optimizer::instance()->create_indexes();

		// Run activation hooks for all modules.
		foreach ( $this->modules as $module ) {
			if ( method_exists( $module, 'activate' ) ) {
				$module->activate();
			}
		}

		Logger::log( 'info', 'Plugin activated', [], 'core' );
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

		Logger::log( 'info', 'Plugin deactivated', [], 'core' );
	}
}

