<?php
/**
 * Cache generation helper for BeepBeep AI.
 *
 * Uses a generation counter pattern to enable efficient cache invalidation
 * without requiring wildcard/prefix deletion support from the object cache.
 *
 * @package BeepBeep_AI
 * @since 4.5.0
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BBAI_Cache {

	const GROUP = 'bbai';

	/**
	 * Default TTL for cached data (5 minutes).
	 */
	const DEFAULT_TTL = 300;

	/**
	 * Short TTL for volatile data like queue stats (2 minutes).
	 */
	const SHORT_TTL = 120;

	/**
	 * In-memory cache of generation counters to avoid repeated get_option calls.
	 *
	 * @var array<string, int>
	 */
	private static $generations = [];

	/**
	 * Get the current cache generation for a table slug.
	 *
	 * @param string $table_slug Short identifier (e.g. 'logs', 'queue', 'credit_usage').
	 * @return int Current generation number.
	 */
	public static function generation( $table_slug ) {
		if ( ! isset( self::$generations[ $table_slug ] ) ) {
			self::$generations[ $table_slug ] = (int) get_option( "bbai_cache_gen_{$table_slug}", 0 );
		}
		return self::$generations[ $table_slug ];
	}

	/**
	 * Increment the generation counter, invalidating all cached reads for a table.
	 *
	 * @param string $table_slug Short identifier.
	 * @return int New generation number.
	 */
	public static function bump( $table_slug ) {
		$gen = self::generation( $table_slug ) + 1;
		update_option( "bbai_cache_gen_{$table_slug}", $gen, true );
		self::$generations[ $table_slug ] = $gen;
		return $gen;
	}

	/**
	 * Build a cache key incorporating the current generation.
	 *
	 * @param string $table_slug Short identifier.
	 * @param string $suffix     Descriptive suffix (e.g. 'stats', md5 hash of args).
	 * @return string Full cache key.
	 */
	public static function key( $table_slug, $suffix ) {
		$gen = self::generation( $table_slug );
		return "bbai_{$table_slug}_g{$gen}_{$suffix}";
	}

	/**
	 * Get a cached value.
	 *
	 * @param string $table_slug Short identifier.
	 * @param string $suffix     Cache key suffix.
	 * @return mixed|false Cached value or false if not found.
	 */
	public static function get( $table_slug, $suffix ) {
		return wp_cache_get( self::key( $table_slug, $suffix ), self::GROUP );
	}

	/**
	 * Set a cached value.
	 *
	 * @param string $table_slug Short identifier.
	 * @param string $suffix     Cache key suffix.
	 * @param mixed  $value      Value to cache.
	 * @param int    $ttl        Time to live in seconds. Default 5 minutes.
	 * @return bool True on success.
	 */
	public static function set( $table_slug, $suffix, $value, $ttl = self::DEFAULT_TTL ) {
		return wp_cache_set( self::key( $table_slug, $suffix ), $value, self::GROUP, $ttl );
	}

	/**
	 * Delete a specific cached value.
	 *
	 * @param string $table_slug Short identifier.
	 * @param string $suffix     Cache key suffix.
	 * @return bool True on success.
	 */
	public static function delete( $table_slug, $suffix ) {
		return wp_cache_delete( self::key( $table_slug, $suffix ), self::GROUP );
	}

	/**
	 * Get all known table slugs used by the plugin.
	 * Used by uninstall to clean up generation counter options.
	 *
	 * @return string[] List of table slugs.
	 */
	public static function known_slugs() {
		return [ 'logs', 'queue', 'credit_usage', 'usage_logs', 'contact', 'library', 'stats' ];
	}
}
