<?php
/**
 * Database Optimizer Class
 *
 * Handles database optimization including index creation.
 *
 * @package Optti\Framework
 */

namespace Optti\Framework;

use Optti\Framework\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DB_Optimizer
 *
 * Manages database optimization.
 */
class DB_Optimizer {

	use Singleton;

	/**
	 * Create performance indexes for postmeta table.
	 *
	 * @return void
	 */
	public function create_indexes() {
		global $wpdb;

		// Check if indexes already exist.
		$indexes_created = get_option( 'optti_performance_indexes_created', false );
		if ( $indexes_created ) {
			return;
		}

		// Index for _beepbeepai_generated_at (used in sorting and stats).
		$this->create_index_if_not_exists(
			$wpdb->postmeta,
			'idx_beepbeepai_generated_at',
			'meta_key(50), meta_value(50)',
			'_beepbeepai_generated_at'
		);

		// Index for _beepbeepai_source (used in stats aggregation).
		$this->create_index_if_not_exists(
			$wpdb->postmeta,
			'idx_beepbeepai_source',
			'meta_key(50), meta_value(50)',
			'_beepbeepai_source'
		);

		// Index for _wp_attachment_image_alt (used in coverage stats).
		$this->create_index_if_not_exists(
			$wpdb->postmeta,
			'idx_wp_attachment_alt',
			'meta_key(50), meta_value(100)',
			'_wp_attachment_image_alt'
		);

		// Composite index for attachment queries.
		$this->create_index_if_not_exists(
			$wpdb->posts,
			'idx_posts_attachment_image',
			'post_type(20), post_mime_type(50)',
			'attachment'
		);

		// Mark indexes as created.
		update_option( 'optti_performance_indexes_created', true, false );
	}

	/**
	 * Create index if it doesn't exist.
	 *
	 * @param string $table Table name.
	 * @param string $index_name Index name.
	 * @param string $columns Column definition.
	 * @param string $meta_key Meta key to check (for postmeta indexes).
	 * @return void
	 */
	protected function create_index_if_not_exists( $table, $index_name, $columns, $meta_key = '' ) {
		global $wpdb;

		// Check if index exists.
		$index_exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) 
			FROM information_schema.statistics 
			WHERE table_schema = DATABASE() 
			AND table_name = %s 
			AND index_name = %s",
			$table,
			$index_name
		) );

		if ( $index_exists > 0 ) {
			return;
		}

		// Create index.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is WordPress core table.
		$wpdb->query( "CREATE INDEX {$index_name} ON {$table} ({$columns})" );
	}

	/**
	 * Optimize database tables.
	 *
	 * @return void
	 */
	public function optimize_tables() {
		global $wpdb;

		// Optimize postmeta table (can be large).
		$wpdb->query( "OPTIMIZE TABLE {$wpdb->postmeta}" );

		// Optimize posts table.
		$wpdb->query( "OPTIMIZE TABLE {$wpdb->posts}" );
	}
}

