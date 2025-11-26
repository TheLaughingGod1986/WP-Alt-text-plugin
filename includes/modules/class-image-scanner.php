<?php
/**
 * Image Scanner Module
 *
 * Scans media library for images and identifies those needing alt text.
 *
 * @package Optti\Modules
 */

namespace Optti\Modules;

use Optti\Framework\Interfaces\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Image_Scanner
 *
 * Module for scanning images in media library.
 */
class Image_Scanner implements ModuleInterface {

	/**
	 * Module ID.
	 */
	const MODULE_ID = 'image_scanner';

	/**
	 * Get module identifier.
	 *
	 * @return string Module ID.
	 */
	public function get_id() {
		return self::MODULE_ID;
	}

	/**
	 * Get module name.
	 *
	 * @return string Module name.
	 */
	public function get_name() {
		return 'Image Scanner';
	}

	/**
	 * Initialize the module.
	 *
	 * @return void
	 */
	public function init() {
		// Register REST endpoints.
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	/**
	 * Check if module is active.
	 *
	 * @return bool True if active.
	 */
	public function is_active() {
		return true; // Always active for this plugin.
	}

	/**
	 * Get images missing alt text.
	 *
	 * @param int $limit Maximum number of images to return.
	 * @param int $offset Offset for pagination.
	 * @return array Array of attachment IDs.
	 */
	public function get_missing_alt_text( $limit = 50, $offset = 0 ) {
		global $wpdb;

		$query = "
			SELECT p.ID
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
			WHERE p.post_type = 'attachment'
			AND p.post_mime_type LIKE 'image/%'
			AND (pm.meta_value IS NULL OR pm.meta_value = '')
			ORDER BY p.ID DESC
			LIMIT %d OFFSET %d
		";

		$ids = $wpdb->get_col( $wpdb->prepare( $query, $limit, $offset ) );

		return array_map( 'intval', $ids );
	}

	/**
	 * Get all images.
	 *
	 * @param int $limit Maximum number of images to return.
	 * @param int $offset Offset for pagination.
	 * @return array Array of attachment IDs.
	 */
	public function get_all_images( $limit = 50, $offset = 0 ) {
		$args = [
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'orderby'        => 'ID',
			'order'          => 'DESC',
			'fields'         => 'ids',
		];

		$query = new \WP_Query( $args );
		return $query->posts;
	}

	/**
	 * Get images with alt text.
	 *
	 * @param int $limit Maximum number of images to return.
	 * @param int $offset Offset for pagination.
	 * @return array Array of attachment IDs.
	 */
	public function get_with_alt_text( $limit = 50, $offset = 0 ) {
		global $wpdb;

		$query = "
			SELECT p.ID
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
			WHERE p.post_type = 'attachment'
			AND p.post_mime_type LIKE 'image/%'
			AND pm.meta_value IS NOT NULL
			AND pm.meta_value != ''
			ORDER BY p.ID DESC
			LIMIT %d OFFSET %d
		";

		$ids = $wpdb->get_col( $wpdb->prepare( $query, $limit, $offset ) );

		return array_map( 'intval', $ids );
	}

	/**
	 * Get scan statistics.
	 *
	 * @param bool $force_refresh Force refresh cache.
	 * @return array Statistics.
	 */
	public function get_stats( $force_refresh = false ) {
		$cache_key = 'optti_scan_stats';
		$cache = \Optti\Framework\Cache::instance();

		// Return cached stats if available and not forcing refresh.
		if ( ! $force_refresh ) {
			$cached = $cache->get( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		global $wpdb;

		// Optimize: Use single query with conditional aggregation.
		$stats = $wpdb->get_row(
			"SELECT 
				COUNT(DISTINCT p.ID) as total_images,
				COUNT(DISTINCT CASE WHEN pm.meta_value IS NOT NULL AND pm.meta_value != '' THEN p.ID END) as with_alt
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
			WHERE p.post_type = 'attachment'
			AND p.post_mime_type LIKE 'image/%'",
			ARRAY_A
		);

		if ( ! $stats ) {
			$stats = [
				'total_images' => 0,
				'with_alt'     => 0,
			];
		}

		$total_images = intval( $stats['total_images'] ?? 0 );
		$with_alt = intval( $stats['with_alt'] ?? 0 );
		$missing_alt = $total_images - $with_alt;

		$result = [
			'total'      => $total_images,
			'with_alt'   => $with_alt,
			'missing_alt' => max( 0, $missing_alt ),
			'coverage'   => $total_images > 0 ? round( ( $with_alt / $total_images ) * 100, 2 ) : 0,
		];

		// Cache for 15 minutes.
		$cache->set( $cache_key, $result, 15 * MINUTE_IN_SECONDS );

		return $result;
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route( 'optti/v1', '/scan/missing', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'rest_get_missing' ],
			'permission_callback' => [ $this, 'rest_permission_check' ],
		] );

		register_rest_route( 'optti/v1', '/scan/stats', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'rest_get_stats' ],
			'permission_callback' => [ $this, 'rest_permission_check' ],
		] );
	}

	/**
	 * REST endpoint: Get missing alt text images.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response.
	 */
	public function rest_get_missing( $request ) {
		$limit  = $request->get_param( 'limit' ) ?: 50;
		$offset = $request->get_param( 'offset' ) ?: 0;

		$ids = $this->get_missing_alt_text( $limit, $offset );

		return new \WP_REST_Response( [
			'ids'    => $ids,
			'count'  => count( $ids ),
			'limit'  => $limit,
			'offset' => $offset,
		] );
	}

	/**
	 * REST endpoint: Get scan statistics.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response.
	 */
	public function rest_get_stats( $request ) {
		$force_refresh = $request->get_param( 'refresh' ) === 'true';
		$stats = $this->get_stats( $force_refresh );

		return new \WP_REST_Response( $stats );
	}

	/**
	 * REST permission check.
	 *
	 * @return bool True if allowed.
	 */
	public function rest_permission_check() {
		return current_user_can( 'manage_options' );
	}
}

