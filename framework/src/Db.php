<?php
/**
 * DB Class
 *
 * Provides database helper functions.
 * Extracted from Usage_Tracker and other database operations.
 *
 * @package Optti\Framework
 */

namespace Optti\Framework;

use Optti\Framework\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Db
 *
 * Database helper class.
 */
class Db {

	use Singleton;

	/**
	 * Get database table name with prefix.
	 *
	 * @param string $table_name Table name without prefix.
	 * @return string Full table name.
	 */
	public function get_table( $table_name ) {
		global $wpdb;
		return $wpdb->prefix . $table_name;
	}

	/**
	 * Execute a prepared query.
	 *
	 * @param string $query SQL query with placeholders.
	 * @param mixed  ...$args Arguments for placeholders.
	 * @return array|null|object Query results.
	 */
	public function query( $query, ...$args ) {
		global $wpdb;
		if ( empty( $args ) ) {
			return $wpdb->query( $query );
		}
		return $wpdb->query( $wpdb->prepare( $query, ...$args ) );
	}

	/**
	 * Get a single row.
	 *
	 * @param string $query SQL query with placeholders.
	 * @param mixed  ...$args Arguments for placeholders.
	 * @return array|null|object Row data or null.
	 */
	public function get_row( $query, ...$args ) {
		global $wpdb;
		if ( empty( $args ) ) {
			return $wpdb->get_row( $query, ARRAY_A );
		}
		return $wpdb->get_row( $wpdb->prepare( $query, ...$args ), ARRAY_A );
	}

	/**
	 * Get multiple rows.
	 *
	 * @param string $query SQL query with placeholders.
	 * @param mixed  ...$args Arguments for placeholders.
	 * @return array Array of rows.
	 */
	public function get_results( $query, ...$args ) {
		global $wpdb;
		if ( empty( $args ) ) {
			return $wpdb->get_results( $query, ARRAY_A );
		}
		return $wpdb->get_results( $wpdb->prepare( $query, ...$args ), ARRAY_A );
	}

	/**
	 * Get a single variable.
	 *
	 * @param string $query SQL query with placeholders.
	 * @param mixed  ...$args Arguments for placeholders.
	 * @return string|null Variable value or null.
	 */
	public function get_var( $query, ...$args ) {
		global $wpdb;
		if ( empty( $args ) ) {
			return $wpdb->get_var( $query );
		}
		return $wpdb->get_var( $wpdb->prepare( $query, ...$args ) );
	}

	/**
	 * Insert a row.
	 *
	 * @param string $table Table name (without prefix).
	 * @param array  $data Data to insert.
	 * @param array  $format Format array for $wpdb->prepare.
	 * @return int|false Insert ID or false on failure.
	 */
	public function insert( $table, $data, $format = null ) {
		global $wpdb;
		$table = $this->get_table( $table );
		return $wpdb->insert( $table, $data, $format );
	}

	/**
	 * Update rows.
	 *
	 * @param string $table Table name (without prefix).
	 * @param array  $data Data to update.
	 * @param array  $where Where conditions.
	 * @param array  $format Format array for data.
	 * @param array  $where_format Format array for where.
	 * @return int|false Number of rows updated or false on failure.
	 */
	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		global $wpdb;
		$table = $this->get_table( $table );
		return $wpdb->update( $table, $data, $where, $format, $where_format );
	}

	/**
	 * Delete rows.
	 *
	 * @param string $table Table name (without prefix).
	 * @param array  $where Where conditions.
	 * @param array  $where_format Format array for where.
	 * @return int|false Number of rows deleted or false on failure.
	 */
	public function delete( $table, $where, $where_format = null ) {
		global $wpdb;
		$table = $this->get_table( $table );
		return $wpdb->delete( $table, $where, $where_format );
	}

	/**
	 * Check if table exists.
	 *
	 * @param string $table_name Table name (without prefix).
	 * @return bool True if table exists.
	 */
	public function table_exists( $table_name ) {
		global $wpdb;
		$table = $this->get_table( $table_name );
		$result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return ! empty( $result );
	}
}

