<?php
/**
 * Plugin Deactivator
 *
 * Fired during plugin deactivation.
 * This class defines all code necessary to run during the plugin's deactivation.
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
 * Deactivator Class
 *
 * Handles plugin deactivation tasks such as:
 * - Clearing scheduled events
 * - Flushing rewrite rules
 * - Cleaning up temporary data
 *
 * NOTE: Do NOT delete user data on deactivation.
 * Only delete data on uninstall (use uninstall.php for that).
 *
 * @since 1.0.0
 */
class Deactivator {

	/**
	 * Plugin deactivation handler.
	 *
	 * Runs when the plugin is deactivated. Use this method to:
	 * - Clear scheduled wp-cron events
	 * - Flush rewrite rules
	 * - Clean up temporary/transient data
	 *
	 * IMPORTANT: Do NOT delete user data here.
	 * Only clean up temporary data and scheduled tasks.
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		// Clear scheduled events
		self::clear_scheduled_events();

		// Flush rewrite rules if you have custom post types/taxonomies
		// flush_rewrite_rules();

		// Clear transients
		self::clear_transients();

		// Store deactivation timestamp
		update_option( 'myplugin_deactivated_at', time() );
	}

	/**
	 * Clear scheduled cron events.
	 *
	 * Remove all wp-cron events scheduled by this plugin.
	 *
	 * @since 1.0.0
	 */
	private static function clear_scheduled_events() {
		// Example: Clear daily cleanup event
		/*
		$timestamp = wp_next_scheduled( 'myplugin_daily_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'myplugin_daily_cleanup' );
		}
		*/

		// Clear all plugin events
		/*
		wp_clear_scheduled_hook( 'myplugin_daily_cleanup' );
		wp_clear_scheduled_hook( 'myplugin_hourly_sync' );
		*/
	}

	/**
	 * Clear plugin transients.
	 *
	 * Delete all transients created by the plugin.
	 *
	 * @since 1.0.0
	 */
	private static function clear_transients() {
		global $wpdb;

		// Example: Delete specific transients
		/*
		delete_transient( 'myplugin_cache' );
		delete_transient( 'myplugin_api_response' );
		*/

		// Example: Delete all plugin transients (use with caution)
		/*
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE %s
				OR option_name LIKE %s",
				'_transient_myplugin_%',
				'_transient_timeout_myplugin_%'
			)
		);
		*/
	}
}
