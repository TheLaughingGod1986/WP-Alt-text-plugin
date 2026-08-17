<?php
/**
 * Centralized DB schema manager for BeepBeep AI.
 *
 * Handles table creation, versioned upgrades, and performance indexes.
 * All schema operations run ONLY during activation or version upgrades,
 * never during normal page loads.
 *
 * @package BeepBeep_AI
 * @since 4.5.0
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DB_Schema {

	const OPTION_KEY = 'bbai_db_version';

	/**
	 * Run all schema operations needed to reach current DB version.
	 * Called from activation hook and from admin_init upgrade check.
	 * Idempotent — safe to call multiple times.
	 */
	public static function install() {
		$installed = get_option( self::OPTION_KEY, '0.0.0' );

		if ( version_compare( $installed, BEEPBEEP_AI_DB_VERSION, '>=' ) ) {
			return;
		}

		if ( version_compare( $installed, '1.0.0', '<' ) ) {
			self::upgrade_to_1_0_0();
		}

		// Future: if ( version_compare( $installed, '1.1.0', '<' ) ) { self::upgrade_to_1_1_0(); }

		update_option( self::OPTION_KEY, BEEPBEEP_AI_DB_VERSION, true );
	}

	/**
	 * Check if upgrade is needed and run it.
	 * Hooked to admin_init for plugin-update scenarios where activation hook does not fire.
	 */
	public static function maybe_upgrade() {
		$installed = get_option( self::OPTION_KEY, '0.0.0' );
		if ( version_compare( $installed, BEEPBEEP_AI_DB_VERSION, '<' ) ) {
			self::install();
		}
	}

	/**
	 * Version 1.0.0: Create all 5 custom tables, run site_id migration,
	 * and create performance indexes on core WP tables.
	 */
	private static function upgrade_to_1_0_0() {
		// Ensure dependencies are loaded.
		self::require_table_classes();

		// Create tables via dbDelta (idempotent).
		Queue::create_table();
		Debug_Log::create_table();
		Credit_Usage_Logger::create_table();
		Usage\Usage_Logs::create_table();
		Contact_Submissions::create_table();

		// Migrate usage_logs table to include site_id column.
		self::migrate_usage_logs_site_id();

		// Create performance indexes on wp_posts and wp_postmeta.
		self::create_performance_indexes();
	}

	/**
	 * Add site_id column and indexes to the usage_logs table if missing.
	 * Consolidated from Core::migrate_usage_logs_table() and Usage_Logs::ensure_columns_exist().
	 */
	private static function migrate_usage_logs_site_id() {
		global $wpdb;

		$table_name = esc_sql( Usage\Usage_Logs::table() );

		if ( ! self::column_exists( $table_name, 'site_id' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
					"ALTER TABLE `{$table_name}` ADD COLUMN `site_id` VARCHAR(64) NOT NULL DEFAULT %s AFTER `user_id`",
					''
				)
			);

			// Populate existing rows with current site identifier.
			if ( file_exists( BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php' ) ) {
				require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
				$site_id = get_site_identifier();

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query(
					$wpdb->prepare(
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						"UPDATE `{$table_name}` SET site_id = %s WHERE site_id = %s",
						$site_id,
						''
					)
				);
			}
		}

		// Add indexes if missing.
		if ( ! self::index_exists( $table_name, 'site_id' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
					"ALTER TABLE `{$table_name}` ADD INDEX `site_id` (`site_id`) /* %d */",
					1
				)
			);
		}

		if ( ! self::index_exists( $table_name, 'site_created' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
					"ALTER TABLE `{$table_name}` ADD INDEX `site_created` (`site_id`, `created_at`) /* %d */",
					1
				)
			);
		}
	}

	/**
	 * Create performance indexes on wp_posts and wp_postmeta.
	 * Consolidated from Core::create_performance_indexes().
	 */
	private static function create_performance_indexes() {
		global $wpdb;

		$postmeta_table = esc_sql( $wpdb->postmeta );
		$posts_table    = esc_sql( $wpdb->posts );

		// Index for _bbai_generated_at (used in sorting and stats).
		if ( ! self::index_exists( $postmeta_table, 'idx_bbai_generated_at' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
					"CREATE INDEX idx_bbai_generated_at ON `{$postmeta_table}` (meta_key(50), meta_value(50)) /* %d */",
					1
				)
			);
		}

		// Index for _bbai_source (used in stats aggregation).
		if ( ! self::index_exists( $postmeta_table, 'idx_bbai_source' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
					"CREATE INDEX idx_bbai_source ON `{$postmeta_table}` (meta_key(50), meta_value(50)) /* %d */",
					1
				)
			);
		}

		// Index for _wp_attachment_image_alt (used in coverage stats).
		if ( ! self::index_exists( $postmeta_table, 'idx_wp_attachment_alt' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
					"CREATE INDEX idx_wp_attachment_alt ON `{$postmeta_table}` (meta_key(50), meta_value(100)) /* %d */",
					1
				)
			);
		}

		// Composite index for attachment queries.
		if ( ! self::index_exists( $posts_table, 'idx_posts_attachment_image' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
					"CREATE INDEX idx_posts_attachment_image ON `{$posts_table}` (post_type(20), post_mime_type(20), post_status(20)) /* %d */",
					1
				)
			);
		}
	}

	// -------------------------------------------------------------------------
	// Helper methods
	// -------------------------------------------------------------------------

	/**
	 * Check if a table exists in the database.
	 *
	 * @param string $table Full table name (already escaped).
	 * @return bool
	 */
	public static function table_exists( $table ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);
		return ! empty( $found );
	}

	/**
	 * Check if a column exists in a table.
	 *
	 * @param string $table  Full table name (already escaped).
	 * @param string $column Column name.
	 * @return bool
	 */
	public static function column_exists( $table, $column ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$result = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SHOW COLUMNS FROM `{$table}` LIKE %s",
				$column
			)
		);
		return ! empty( $result );
	}

	/**
	 * Check if an index exists on a table.
	 *
	 * @param string $table      Full table name (already escaped).
	 * @param string $index_name Index name.
	 * @return bool
	 */
	public static function index_exists( $table, $index_name ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$result = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SHOW INDEXES FROM `{$table}` WHERE Key_name = %s",
				$index_name
			)
		);
		return ! empty( $result );
	}

	/**
	 * Ensure required class files are loaded.
	 */
	private static function require_table_classes() {
		$base = defined( 'BEEPBEEP_AI_PLUGIN_DIR' ) ? BEEPBEEP_AI_PLUGIN_DIR : plugin_dir_path( dirname( __FILE__ ) );

		$files = [
			'includes/class-queue.php',
			'includes/class-debug-log.php',
			'includes/class-credit-usage-logger.php',
			'includes/usage/class-usage-logs.php',
			'includes/class-contact-submissions.php',
		];

		foreach ( $files as $file ) {
			$path = $base . $file;
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	}
}
