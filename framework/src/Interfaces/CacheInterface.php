<?php
/**
 * Cache Interface
 *
 * Defines the contract for caching services.
 *
 * @package Optti\Framework\Interfaces
 */

namespace Optti\Framework\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface CacheInterface
 *
 * All cache implementations must implement this interface.
 */
interface CacheInterface {

	/**
	 * Get a cached value.
	 *
	 * @param string $key Cache key.
	 * @param mixed  $default Default value if key doesn't exist.
	 * @return mixed Cached value or default.
	 */
	public function get( $key, $default = null );

	/**
	 * Set a cached value.
	 *
	 * @param string $key Cache key.
	 * @param mixed  $value Value to cache.
	 * @param int    $expiration Expiration time in seconds. Default 0 (no expiration).
	 * @return bool True on success, false on failure.
	 */
	public function set( $key, $value, $expiration = 0 );

	/**
	 * Delete a cached value.
	 *
	 * @param string $key Cache key.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $key );

	/**
	 * Clear all cached values.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function clear();

	/**
	 * Check if a key exists in cache.
	 *
	 * @param string $key Cache key.
	 * @return bool True if key exists, false otherwise.
	 */
	public function has( $key );
}

