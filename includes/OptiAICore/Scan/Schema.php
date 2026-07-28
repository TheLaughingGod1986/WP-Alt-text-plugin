<?php
/**
 * Shared scan-storage schema used by every OptiAI module on a site.
 *
 * Both plugins ship this same class. Whichever OptiAI plugin activates first
 * creates the tables; dbDelta() is safe to re-run, so every subsequent
 * plugin activation (or version bump) just reconciles the schema instead of
 * failing on "table already exists" — the same idiom this codebase already
 * uses for detecting a sibling plugin's custom tables (e.g. AIOSEO detection
 * in the Titles plugin's Scanner).
 *
 * @package OptiAI_Core
 */

namespace OptiAI\Core\Scan;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema {

	const SCHEMA_VERSION = 1;

	/**
	 * @return string Unprefixed table name for scanned items.
	 */
	public static function items_table_slug() {
		return 'optiai_scan_items';
	}

	/**
	 * @return string Unprefixed table name for scan-run history.
	 */
	public static function runs_table_slug() {
		return 'optiai_scan_runs';
	}

	/**
	 * @return string Unprefixed table name for the optimisation undo log.
	 */
	public static function history_table_slug() {
		return 'optiai_history';
	}

	/**
	 * @return string Fully-prefixed items table name.
	 */
	public static function items_table() {
		global $wpdb;
		return $wpdb->prefix . self::items_table_slug();
	}

	/**
	 * @return string Fully-prefixed runs table name.
	 */
	public static function runs_table() {
		global $wpdb;
		return $wpdb->prefix . self::runs_table_slug();
	}

	/**
	 * @return string Fully-prefixed history table name.
	 */
	public static function history_table() {
		global $wpdb;
		return $wpdb->prefix . self::history_table_slug();
	}

	/**
	 * Create or reconcile both shared tables. Call this from each plugin's
	 * activation hook (and once after a Core version bump); it is safe to
	 * call from multiple plugins without conflict.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$items_table     = self::items_table();
		$runs_table      = self::runs_table();
		$history_table   = self::history_table();

		$items_sql = "CREATE TABLE {$items_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			module VARCHAR(40) NOT NULL,
			site_item_id VARCHAR(191) NOT NULL,
			item_type VARCHAR(60) NOT NULL DEFAULT '',
			score SMALLINT NOT NULL DEFAULT 0,
			status VARCHAR(30) NOT NULL DEFAULT 'unknown',
			issues_json LONGTEXT NULL,
			current_value LONGTEXT NULL,
			content_hash VARCHAR(64) NULL,
			last_scanned_at DATETIME NULL,
			last_optimised_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY module_item (module, site_item_id, item_type),
			KEY module_status (module, status),
			KEY module_score (module, score)
		) {$charset_collate};";

		$runs_sql = "CREATE TABLE {$runs_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			module VARCHAR(40) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'running',
			items_scanned INT UNSIGNED NOT NULL DEFAULT 0,
			issues_found INT UNSIGNED NOT NULL DEFAULT 0,
			average_score SMALLINT NOT NULL DEFAULT 0,
			started_at DATETIME NOT NULL,
			completed_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY module_started (module, started_at)
		) {$charset_collate};";

		$history_sql = "CREATE TABLE {$history_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			module VARCHAR(40) NOT NULL,
			site_item_id VARCHAR(191) NOT NULL,
			old_value LONGTEXT NULL,
			new_value LONGTEXT NULL,
			score_before SMALLINT NULL,
			score_after SMALLINT NULL,
			credits_used SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY module_item (module, site_item_id),
			KEY module_created (module, created_at)
		) {$charset_collate};";

		dbDelta( $items_sql );
		dbDelta( $runs_sql );
		dbDelta( $history_sql );

		update_option( 'optiai_core_schema_version', self::SCHEMA_VERSION );
	}

	/**
	 * Cheap existence check without touching dbDelta (used on the read path
	 * so a module never fatals if its own plugin activation hook has not
	 * run yet, e.g. right after a fresh install).
	 *
	 * @return bool
	 */
	public static function items_table_exists() {
		global $wpdb;
		$table = self::items_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- table name only, no user input.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $found === $table;
	}
}
