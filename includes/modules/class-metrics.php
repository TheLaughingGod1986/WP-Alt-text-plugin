<?php
/**
 * Metrics Module
 *
 * Handles usage tracking, analytics, and metrics.
 *
 * @package Optti\Modules
 */

namespace Optti\Modules;

use Optti\Framework\Interfaces\ModuleInterface;
use Optti\Framework\LicenseManager;
use Optti\Framework\ApiClient;
use Optti\Framework\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Metrics
 *
 * Module for metrics and analytics.
 */
class Metrics implements ModuleInterface {

	/**
	 * Module ID.
	 */
	const MODULE_ID = 'metrics';

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
		return 'Metrics';
	}

	/**
	 * Initialize the module.
	 *
	 * @return void
	 */
	public function init() {
		// Register REST endpoints.
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// Register AJAX handlers.
		add_action( 'wp_ajax_optti_refresh_usage', [ $this, 'ajax_refresh_usage' ] );

		// Clear cache when alt text is updated.
		add_action( 'updated_post_meta', [ $this, 'maybe_clear_cache' ], 10, 4 );
		add_action( 'added_post_meta', [ $this, 'maybe_clear_cache' ], 10, 4 );
		add_action( 'deleted_post_meta', [ $this, 'maybe_clear_cache' ], 10, 4 );

		// Clear cache when attachment is deleted.
		add_action( 'delete_attachment', [ $this, 'clear_media_cache' ] );
	}

	/**
	 * Clear cache when relevant meta is updated.
	 *
	 * @param int    $meta_id Meta ID.
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	public function maybe_clear_cache( $meta_id, $post_id, $meta_key, $meta_value ) {
		// Only clear cache for image attachments.
		if ( get_post_type( $post_id ) !== 'attachment' ) {
			return;
		}

		$mime_type = get_post_mime_type( $post_id );
		if ( strpos( $mime_type, 'image/' ) !== 0 ) {
			return;
		}

		// Clear cache if alt text or generation meta is updated.
		if ( in_array( $meta_key, [ '_wp_attachment_image_alt', '_beepbeepai_generated_at' ], true ) ) {
			$this->clear_media_cache();
		}
	}

	/**
	 * Clear media-related caches.
	 *
	 * @return void
	 */
	public function clear_media_cache() {
		$cache_manager = \Optti\Framework\CacheManager::instance();
		$cache_manager->clear_media_caches();
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
	 * Get usage statistics.
	 *
	 * @param bool $force_refresh Force refresh from API.
	 * @return array Usage statistics.
	 */
	public function get_usage_stats( $force_refresh = false ) {
		$cache_key = 'optti_usage_stats';
		$cache = \Optti\Framework\Cache::instance();

		// Return cached stats if available and not forcing refresh.
		if ( ! $force_refresh ) {
			$cached = $cache->get( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$license = \Optti\Framework\LicenseManager::instance();
		$quota = $license->get_quota( $force_refresh );

		if ( is_wp_error( $quota ) ) {
			return [
				'error' => $quota->get_error_message(),
			];
		}

		$result = [
			'used'      => $quota['used'] ?? 0,
			'limit'     => $quota['limit'] ?? 0,
			'remaining' => $quota['remaining'] ?? 0,
			'plan'      => $quota['plan_type'] ?? 'free',
			'resets_at' => $quota['resets_at'] ?? 0,
			'percentage' => $quota['limit'] > 0 ? round( ( $quota['used'] / $quota['limit'] ) * 100, 2 ) : 0,
		];

		// Cache for 5 minutes (usage data changes frequently).
		$cache->set( $cache_key, $result, 5 * MINUTE_IN_SECONDS );

		return $result;
	}

	/**
	 * Get media library statistics.
	 *
	 * @param bool $force_refresh Force refresh cache.
	 * @return array Media statistics.
	 */
	public function get_media_stats( $force_refresh = false ) {
		$cache_key = 'optti_media_stats';
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
				COUNT(DISTINCT CASE WHEN pm_alt.meta_value IS NOT NULL AND pm_alt.meta_value != '' THEN p.ID END) as with_alt,
				COUNT(DISTINCT CASE WHEN pm_gen.meta_value IS NOT NULL THEN p.ID END) as generated_by_plugin
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm_alt ON p.ID = pm_alt.post_id AND pm_alt.meta_key = '_wp_attachment_image_alt'
			LEFT JOIN {$wpdb->postmeta} pm_gen ON p.ID = pm_gen.post_id AND pm_gen.meta_key = '_beepbeepai_generated_at'
			WHERE p.post_type = 'attachment'
			AND p.post_mime_type LIKE 'image/%'",
			ARRAY_A
		);

		if ( ! $stats ) {
			$stats = [
				'total_images'        => 0,
				'with_alt'            => 0,
				'generated_by_plugin' => 0,
			];
		}

		$total_images = intval( $stats['total_images'] ?? 0 );
		$with_alt = intval( $stats['with_alt'] ?? 0 );
		$generated_by_plugin = intval( $stats['generated_by_plugin'] ?? 0 );
		$missing_alt = $total_images - $with_alt;

		$result = [
			'total_images'        => $total_images,
			'with_alt_text'       => $with_alt,
			'missing_alt'         => max( 0, $missing_alt ),
			'generated_by_plugin' => $generated_by_plugin,
			'coverage'            => $total_images > 0 ? round( ( $with_alt / $total_images ) * 100, 2 ) : 0,
		];

		// Cache for 15 minutes.
		$cache->set( $cache_key, $result, 15 * MINUTE_IN_SECONDS );

		return $result;
	}

	/**
	 * Get top improved images.
	 *
	 * @param int $limit Number of images to return.
	 * @return array Array of image data.
	 */
	public function get_top_improved( $limit = 10 ) {
		$cache_key = 'optti_top_improved_' . $limit;
		$cache = \Optti\Framework\Cache::instance();

		// Return cached results if available.
		$cached = $cache->get( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		$query = "
			SELECT p.ID, p.post_title, pm1.meta_value as alt_text, pm2.meta_value as generated_at
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_wp_attachment_image_alt'
			INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_beepbeepai_generated_at'
			WHERE p.post_type = 'attachment'
			AND p.post_mime_type LIKE 'image/%'
			AND pm1.meta_value IS NOT NULL
			AND pm1.meta_value != ''
			ORDER BY pm2.meta_value DESC
			LIMIT %d
		";

		$results = $wpdb->get_results( $wpdb->prepare( $query, $limit ), ARRAY_A );

		$result = array_map( function( $row ) {
			return [
				'id'           => intval( $row['ID'] ),
				'title'        => $row['post_title'],
				'alt_text'     => $row['alt_text'],
				'generated_at' => $row['generated_at'],
			];
		}, $results );

		// Cache for 30 minutes (top images don't change frequently).
		$cache->set( $cache_key, $result, 30 * MINUTE_IN_SECONDS );

		return $result;
	}

	/**
	 * Calculate estimated SEO gain.
	 *
	 * @return array SEO metrics.
	 */
	public function get_seo_metrics() {
		$media_stats = $this->get_media_stats();
		$usage_stats = $this->get_usage_stats();

		// Handle null or error cases.
		if ( ! is_array( $media_stats ) || empty( $media_stats ) ) {
			return [
				'seo_score'         => 0,
				'accessibility_grade' => 'F',
				'images_improved'   => 0,
				'coverage'          => 0,
			];
		}

		// Calculate estimated SEO improvement.
		$images_with_alt = intval( $media_stats['with_alt_text'] ?? 0 );
		$total_images = intval( $media_stats['total_images'] ?? 0 );
		$coverage = floatval( $media_stats['coverage'] ?? 0 );

		// Estimate SEO score improvement (simplified calculation).
		$seo_score = min( 100, round( $coverage * 0.8 ) ); // Max 100, coverage-based.

		return [
			'seo_score'         => $seo_score,
			'accessibility_grade' => $this->get_accessibility_grade( $coverage ),
			'images_improved'   => intval( $media_stats['generated_by_plugin'] ?? 0 ),
			'coverage'          => $coverage,
		];
	}

	/**
	 * Get accessibility grade.
	 *
	 * @param float $coverage Coverage percentage.
	 * @return string Grade (A-F).
	 */
	protected function get_accessibility_grade( $coverage ) {
		if ( $coverage >= 90 ) {
			return 'A';
		} elseif ( $coverage >= 75 ) {
			return 'B';
		} elseif ( $coverage >= 60 ) {
			return 'C';
		} elseif ( $coverage >= 40 ) {
			return 'D';
		} elseif ( $coverage >= 20 ) {
			return 'E';
		}
		return 'F';
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route( 'optti/v1', '/metrics/usage', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'rest_get_usage' ],
			'permission_callback' => [ $this, 'rest_permission_check' ],
		] );

		register_rest_route( 'optti/v1', '/metrics/media', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'rest_get_media' ],
			'permission_callback' => [ $this, 'rest_permission_check' ],
		] );

		register_rest_route( 'optti/v1', '/metrics/seo', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'rest_get_seo' ],
			'permission_callback' => [ $this, 'rest_permission_check' ],
		] );
	}

	/**
	 * REST endpoint: Get usage statistics.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response.
	 */
	public function rest_get_usage( $request ) {
		$force_refresh = $request->get_param( 'refresh' ) === 'true';
		$stats = $this->get_usage_stats( $force_refresh );

		return new \WP_REST_Response( $stats );
	}

	/**
	 * REST endpoint: Get media statistics.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response.
	 */
	public function rest_get_media( $request ) {
		$force_refresh = $request->get_param( 'refresh' ) === 'true';
		$stats = $this->get_media_stats( $force_refresh );

		return new \WP_REST_Response( $stats );
	}

	/**
	 * REST endpoint: Get SEO metrics.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response.
	 */
	public function rest_get_seo( $request ) {
		$metrics = $this->get_seo_metrics();

		return new \WP_REST_Response( $metrics );
	}

	/**
	 * AJAX handler: Refresh usage data.
	 *
	 * @return void
	 */
	public function ajax_refresh_usage() {
		check_ajax_referer( 'optti_refresh_usage', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ] );
		}

		$stats = $this->get_usage_stats( true );

		if ( isset( $stats['error'] ) ) {
			wp_send_json_error( [ 'message' => $stats['error'] ] );
		}

		wp_send_json_success( $stats );
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

