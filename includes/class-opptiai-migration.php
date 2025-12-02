<?php
/**
 * OptiAI Framework Migration Helper
 *
 * Handles migration from embedded framework to shared core
 *
 * @package BeepBeepAI\AltTextGenerator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migration Class
 */
class OptiAI_Migration {
	/**
	 * Check if OptiAI Core plugin is available
	 *
	 * @return bool
	 */
	public static function is_core_available() {
		return defined( 'OPPTIAI_CORE_VERSION' ) && function_exists( 'opptiai_framework' );
	}

	/**
	 * Migrate settings from plugin to framework
	 *
	 * @return bool Success.
	 */
	public static function migrate_settings() {
		if ( ! self::is_core_available() ) {
			return false;
		}

		$framework = opptiai_framework();
		if ( ! isset( $framework->settings ) ) {
			return false;
		}

		// Get existing plugin settings
		$plugin_settings = get_option( 'bbai_settings', array() );
		if ( empty( $plugin_settings ) ) {
			return false;
		}

		// Migrate to framework settings
		$framework_settings = array();

		// Map plugin settings to framework settings
		if ( isset( $plugin_settings['api_url'] ) ) {
			$framework_settings['api_url'] = $plugin_settings['api_url'];
		}
		if ( isset( $plugin_settings['auto_generate'] ) ) {
			$framework_settings['auto_generate'] = (bool) $plugin_settings['auto_generate'];
		}
		if ( isset( $plugin_settings['max_words'] ) ) {
			$framework_settings['max_words'] = intval( $plugin_settings['max_words'] );
		}
		if ( isset( $plugin_settings['language'] ) ) {
			$framework_settings['language'] = sanitize_text_field( $plugin_settings['language'] );
		}

		// Save to framework
		if ( ! empty( $framework_settings ) ) {
			$framework->settings->set_all( $framework_settings );
		}

		// Mark migration as complete
		update_option( 'bbai_framework_migrated', true, false );
		update_option( 'bbai_framework_migration_date', current_time( 'mysql' ), false );

		return true;
	}

	/**
	 * Migrate usage data to framework
	 *
	 * @return bool Success.
	 */
	public static function migrate_usage() {
		if ( ! self::is_core_available() ) {
			return false;
		}

		$framework = opptiai_framework();
		if ( ! isset( $framework->licensing ) ) {
			return false;
		}

		// Get cached usage from legacy tracker
		$legacy_usage = get_transient( 'bbai_usage_cache' );
		if ( $legacy_usage && is_array( $legacy_usage ) ) {
			$framework->licensing->update_usage( $legacy_usage );
			return true;
		}

		return false;
	}

	/**
	 * Run full migration
	 *
	 * @return array Migration results.
	 */
	public static function run_migration() {
		$results = array(
			'settings' => false,
			'usage'    => false,
		);

		if ( ! self::is_core_available() ) {
			return $results;
		}

		// Check if already migrated
		$already_migrated = get_option( 'bbai_framework_migrated', false );
		if ( $already_migrated ) {
			return $results; // Already migrated
		}

		$results['settings'] = self::migrate_settings();
		$results['usage']    = self::migrate_usage();

		return $results;
	}

	/**
	 * Check if migration is needed
	 *
	 * @return bool
	 */
	public static function migration_needed() {
		// Migration needed if core is available but not yet migrated
		return self::is_core_available() && ! get_option( 'bbai_framework_migrated', false );
	}
}
