<?php
/**
 * Cache Manager Class
 *
 * Provides centralized cache management and invalidation.
 *
 * @package Optti\Framework
 */

namespace Optti\Framework;

use Optti\Framework\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cache_Manager
 *
 * Manages cache invalidation and clearing.
 */
class Cache_Manager {

	use Singleton;

	/**
	 * Cache keys used by the plugin.
	 *
	 * @var array
	 */
	protected $cache_keys = [
		'media_stats'      => 'optti_media_stats',
		'scan_stats'       => 'optti_scan_stats',
		'usage_stats'      => 'optti_usage_stats',
		'top_improved'     => 'optti_top_improved',
		'user_info'        => 'optti_user_info',
		'subscription_info' => 'optti_subscription_info',
		'plans'            => 'optti_plans',
	];

	/**
	 * Clear all plugin caches.
	 *
	 * @return void
	 */
	public function clear_all() {
		$cache = Cache::instance();

		foreach ( $this->cache_keys as $key ) {
			$cache->delete( $key );
		}

		// Clear top improved caches with different limits.
		for ( $i = 5; $i <= 20; $i += 5 ) {
			$cache->delete( 'optti_top_improved_' . $i );
		}
	}

	/**
	 * Clear media-related caches.
	 *
	 * @return void
	 */
	public function clear_media_caches() {
		$cache = Cache::instance();
		$cache->delete( $this->cache_keys['media_stats'] );
		$cache->delete( $this->cache_keys['scan_stats'] );

		// Clear top improved caches.
		for ( $i = 5; $i <= 20; $i += 5 ) {
			$cache->delete( 'optti_top_improved_' . $i );
		}
	}

	/**
	 * Clear usage-related caches.
	 *
	 * @return void
	 */
	public function clear_usage_caches() {
		$cache = Cache::instance();
		$cache->delete( $this->cache_keys['usage_stats'] );
	}

	/**
	 * Clear API-related caches.
	 *
	 * @return void
	 */
	public function clear_api_caches() {
		$cache = Cache::instance();
		$cache->delete( $this->cache_keys['user_info'] );
		$cache->delete( $this->cache_keys['subscription_info'] );
		$cache->delete( $this->cache_keys['plans'] );
	}

	/**
	 * Clear cache by key.
	 *
	 * @param string $key Cache key.
	 * @return void
	 */
	public function clear( $key ) {
		$cache = Cache::instance();
		$cache->delete( $key );
	}
}

