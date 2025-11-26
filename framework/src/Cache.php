<?php
/**
 * Cache Class
 *
 * Provides caching functionality using WordPress transients.
 *
 * @package Optti\Framework
 */

namespace Optti\Framework;

use Optti\Framework\Interfaces\CacheInterface;
use Optti\Framework\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cache
 *
 * Implements caching using WordPress transients.
 */
class Cache implements CacheInterface {

	use Singleton;

	/**
	 * Cache prefix.
	 *
	 * @var string
	 */
	protected $prefix = 'optti_';

	/**
	 * Initialize the cache.
	 */
	protected function __construct() {
		$this->prefix = apply_filters( 'optti_cache_prefix', $this->prefix );
	}

	/**
	 * Get a cached value.
	 *
	 * @param string $key Cache key.
	 * @param mixed  $default Default value if key doesn't exist.
	 * @return mixed Cached value or default.
	 */
	public function get( $key, $default = null ) {
		$key = $this->normalize_key( $key );
		$value = get_transient( $key );

		if ( false === $value ) {
			return $default;
		}

		return $value;
	}

	/**
	 * Set a cached value.
	 *
	 * @param string $key Cache key.
	 * @param mixed  $value Value to cache.
	 * @param int    $expiration Expiration time in seconds. Default 0 (no expiration).
	 * @return bool True on success, false on failure.
	 */
	public function set( $key, $value, $expiration = 0 ) {
		$key = $this->normalize_key( $key );

		// WordPress transients don't support 0 expiration, use a very long time instead.
		if ( 0 === $expiration ) {
			$expiration = YEAR_IN_SECONDS;
		}

		return set_transient( $key, $value, $expiration );
	}

	/**
	 * Delete a cached value.
	 *
	 * @param string $key Cache key.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $key ) {
		$key = $this->normalize_key( $key );
		return delete_transient( $key );
	}

	/**
	 * Clear all cached values.
	 *
	 * Note: This only clears transients with the prefix.
	 * For a full clear, you may need to use a plugin or direct database access.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function clear() {
		global $wpdb;

		// Delete all transients with our prefix.
		$prefix = '_transient_' . $this->prefix;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$prefix . '%',
				'_transient_timeout_' . $this->prefix . '%'
			)
		);

		return true;
	}

	/**
	 * Check if a key exists in cache.
	 *
	 * @param string $key Cache key.
	 * @return bool True if key exists, false otherwise.
	 */
	public function has( $key ) {
		$key = $this->normalize_key( $key );
		return false !== get_transient( $key );
	}

	/**
	 * Normalize cache key.
	 *
	 * @param string $key Cache key.
	 * @return string Normalized key.
	 */
	protected function normalize_key( $key ) {
		$key = sanitize_key( $key );

		// Add prefix if not already present.
		if ( 0 !== strpos( $key, $this->prefix ) ) {
			$key = $this->prefix . $key;
		}

		return $key;
	}
}

