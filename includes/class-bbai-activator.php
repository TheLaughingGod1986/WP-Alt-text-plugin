<?php
/**
 * Fired during plugin activation.
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-core.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-opptiai-migration.php';

class Activator {

	/**
	 * Activate the plugin.
	 */
	public static function activate() {
		// Check for OptiAI Core plugin and migrate if needed
		// Use leading backslash to reference global namespace class
		// Skip migration check if already migrated to speed up activation
		$already_migrated = get_option( 'bbai_framework_migrated', false );
		if ( ! $already_migrated && class_exists( '\OptiAI_Migration' ) ) {
			$migration_needed = \OptiAI_Migration::migration_needed();
			if ( $migration_needed ) {
				\OptiAI_Migration::run_migration();
			}
		}

		// Check if OptiAI Core is required but not installed
		if ( ! defined( 'OPPTIAI_CORE_VERSION' ) ) {
			// Set transient to show notice
			set_transient( 'bbai_install_core_notice', true, 3600 );
		}

		// Set transient to show dashboard signup modal on first activation
		// Only show if user hasn't already submitted
		if ( ! get_option( 'bbai_dashboard_signup_submitted', false ) ) {
			set_transient( 'bbai_show_dashboard_signup', true, 3600 );
		}

		$core = new \BeepBeepAI\AltTextGenerator\Core();
		$core->activate();

		// Store activation timestamp for analytics (JS will fire on first load)
		update_option( 'bbai_activated_at', time() );

		// Sync identity if user is authenticated during plugin activation
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php';
		$api_client = new \BeepBeepAI\AltTextGenerator\API_Client_V2();
		if ( $api_client->is_authenticated() ) {
			$api_client->sync_identity();
		}
	}
}
