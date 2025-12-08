<?php
/**
 * BeepBeep Alt Text Plugin Class
 *
 * Main plugin class extending PluginBase.
 *
 * @package BeepBeepAI\AltTextGenerator
 */

namespace BeepBeepAI\AltTextGenerator;

use Optti\Framework\PluginBase;
use Optti\Framework\REST_Controller;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BeepBeep_AltText_Plugin
 *
 * Main plugin class for BeepBeep AI Alt Text Generator.
 */
class BeepBeep_AltText_Plugin extends PluginBase {

	/**
	 * Get plugin name.
	 *
	 * @return string Plugin name.
	 */
	public function get_plugin_name() {
		// Use translation only if text domain is loaded (after init).
		if ( is_textdomain_loaded( 'beepbeep-ai-alt-text-generator' ) ) {
			return __( 'BeepBeep AI – Alt Text Generator', 'beepbeep-ai-alt-text-generator' );
		}
		return 'BeepBeep AI – Alt Text Generator';
	}

	/**
	 * Get plugin slug.
	 *
	 * @return string Plugin slug.
	 */
	public function get_plugin_slug() {
		return 'beepbeep-ai-alt-text-generator';
	}

	/**
	 * Get text domain for translations.
	 *
	 * @return string Text domain.
	 */
	protected function get_text_domain() {
		return 'beepbeep-ai-alt-text-generator';
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
		// Call parent to set up framework admin.
		parent::init_admin();

		// Load admin classes.
		require_once OPTTI_PLUGIN_DIR . 'admin/class-admin-menu.php';
		require_once OPTTI_PLUGIN_DIR . 'admin/class-admin-assets.php';
		require_once OPTTI_PLUGIN_DIR . 'admin/class-admin-notices.php';
		require_once OPTTI_PLUGIN_DIR . 'admin/class-page-renderer.php';

		// Initialize admin components.
		// Enable Admin_Menu to provide framework dashboard access.
		// The old BeepBeep AI menu will still work via class-opptiai-alt-core.php.
		\Optti\Admin\Admin_Menu::instance();
		\Optti\Admin\Admin_Assets::instance();
		\Optti\Admin\Admin_Notices::instance();
	}

	/**
	 * Register admin menus.
	 *
	 * @return void
	 */
	public function register_admin_menus() {
		// Admin menus are handled by Admin_Menu class.
		// This method can be overridden if needed.
	}

	/**
	 * REST controller instance.
	 *
	 * @var REST_Controller|null
	 */
	protected $rest_controller = null;

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		// Register framework REST controller with framework API endpoints enabled.
		if ( ! $this->rest_controller ) {
			$this->rest_controller = new class( $this ) extends REST_Controller {
				/**
				 * Check if framework APIs should be exposed.
				 *
				 * @return bool True to expose framework APIs.
				 */
				protected function should_expose_framework_apis() {
					return true;
				}

				/**
				 * Register plugin-specific routes.
				 *
				 * @return void
				 */
				protected function register_plugin_routes() {
					// Plugin-specific routes can be added here if needed.
				}
			};
		}

		// Register routes via the controller.
		if ( $this->rest_controller ) {
			$this->rest_controller->register_routes();
		}
	}
}
