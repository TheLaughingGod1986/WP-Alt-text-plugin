<?php
/**
 * Plugin Activator
 *
 * Fired during plugin activation.
 * This class defines all code necessary to run during the plugin's activation.
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
 * Activator Class
 *
 * Handles plugin activation tasks such as:
 * - Creating database tables
 * - Setting default options
 * - Flushing rewrite rules
 * - Scheduling cron events
 *
 * @since 1.0.0
 */
class Activator {

	/**
	 * Plugin activation handler.
	 *
	 * Runs when the plugin is activated. Use this method to:
	 * - Create custom database tables
	 * - Add default options
	 * - Schedule wp-cron events
	 * - Flush rewrite rules (if custom post types/taxonomies)
	 * - Check system requirements
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		// Check requirements
		self::check_requirements();

		// Create database tables
		self::create_tables();

		// Set default options
		self::set_default_options();

		// Schedule cron events
		self::schedule_events();

		// Flush rewrite rules if you have custom post types/taxonomies
		// flush_rewrite_rules();

		// Store activation timestamp
		update_option( 'myplugin_activated_at', time() );
		update_option( 'myplugin_version', MYPLUGIN_VERSION );
	}

	/**
	 * Check system requirements.
	 *
	 * Verify that the server meets minimum requirements.
	 *
	 * @since 1.0.0
	 */
	private static function check_requirements() {
		global $wp_version;

		$requirements = array(
			'php'       => '7.4',
			'wordpress' => '5.8',
		);

		// Check PHP version
		if ( version_compare( PHP_VERSION, $requirements['php'], '<' ) ) {
			wp_die(
				sprintf(
					/* translators: %s: Required PHP version */
					esc_html__( 'My Plugin requires PHP version %s or higher.', 'my-plugin' ),
					$requirements['php']
				)
			);
		}

		// Check WordPress version
		if ( version_compare( $wp_version, $requirements['wordpress'], '<' ) ) {
			wp_die(
				sprintf(
					/* translators: %s: Required WordPress version */
					esc_html__( 'My Plugin requires WordPress version %s or higher.', 'my-plugin' ),
					$requirements['wordpress']
				)
			);
		}
	}

	/**
	 * Create custom database tables.
	 *
	 * Use dbDelta for creating tables as it handles updates gracefully.
	 *
	 * @since 1.0.0
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Example table creation
		/*
		$table_name = $wpdb->prefix . 'myplugin_data';

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			data longtext NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		*/
	}

	/**
	 * Set default plugin options.
	 *
	 * Add options with default values if they don't exist.
	 *
	 * @since 1.0.0
	 */
	private static function set_default_options() {
		$defaults = array(
			'myplugin_setting_1' => 'default_value',
			'myplugin_setting_2' => true,
			'myplugin_setting_3' => array(),
		);

		foreach ( $defaults as $option => $value ) {
			if ( get_option( $option ) === false ) {
				add_option( $option, $value );
			}
		}
	}

	/**
	 * Schedule cron events.
	 *
	 * Schedule recurring tasks using WordPress cron.
	 *
	 * @since 1.0.0
	 */
	private static function schedule_events() {
		// Example: Schedule daily cleanup task
		/*
		if ( ! wp_next_scheduled( 'myplugin_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'myplugin_daily_cleanup' );
		}
		*/
	}
}
