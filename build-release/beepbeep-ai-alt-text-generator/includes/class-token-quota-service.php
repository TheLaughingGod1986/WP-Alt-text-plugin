<?php
/**
 * Token Quota Service
 * Manages site-wide shared token quota with abuse prevention
 */

namespace BeepBeepAI\AltTextGenerator;

if (!defined('ABSPATH')) {
	exit;
}

class Token_Quota_Service {
	
	const CACHE_KEY = 'bbai_quota_cache';
	const CACHE_EXPIRY = 300; // 5 minutes
	
	/**
	 * Get site quota information
	 * 
	 * @param bool $force_refresh Force refresh from backend
	 * @return array|WP_Error Quota data or error
	 */
	public static function get_site_quota($force_refresh = false) {
		// Check cache first unless forcing refresh
		if (!$force_refresh) {
			$cached = get_transient(self::CACHE_KEY);
			if ($cached !== false && is_array($cached)) {
				return $cached;
			}
		}
		
		// Get from API client
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php';
		$api_client = new API_Client_V2();
		
		$usage = $api_client->get_usage();
		
		if (is_wp_error($usage)) {
			// Return cached data if available, even if stale
			$cached = get_transient(self::CACHE_KEY);
			if ($cached !== false && is_array($cached)) {
				return $cached;
			}
			return $usage;
		}
		
		// Format quota data
		$quota = [
			'plan_type' => $usage['plan'] ?? 'free',
			'limit' => isset($usage['limit']) ? max(0, intval($usage['limit'])) : 50,
			'used' => isset($usage['used']) ? max(0, intval($usage['used'])) : 0,
			'remaining' => 0, // Will be calculated below
			'resets_at' => 0,
		];
		
		// Calculate reset timestamp
		if (!empty($usage['resetDate'])) {
			$reset_ts = strtotime($usage['resetDate']);
			if ($reset_ts > 0) {
				$quota['resets_at'] = $reset_ts;
			}
		}
		
		if ($quota['resets_at'] <= 0 && !empty($usage['resetTimestamp'])) {
			$quota['resets_at'] = intval($usage['resetTimestamp']);
		}
		
		if ($quota['resets_at'] <= 0) {
			$quota['resets_at'] = strtotime('first day of next month');
		}
		
		// Always calculate remaining from limit - used for accuracy
		// The API's remaining value might be stale or incorrect
		$quota['remaining'] = max(0, $quota['limit'] - $quota['used']);
		
		// Cache the result
		set_transient(self::CACHE_KEY, $quota, self::CACHE_EXPIRY);
		
		return $quota;
	}
	
	/**
	 * Check if site can consume specified tokens
	 * Uses cached usage first (more reliable) and falls back to fresh API check
	 * 
	 * @param int $tokens Number of tokens to check
	 * @return bool True if can consume, false otherwise
	 */
	public static function can_consume($tokens) {
		$tokens = max(0, intval($tokens));
		
		if ($tokens <= 0) {
			return true; // No tokens needed
		}
		
		// ALWAYS check cached usage first - it's more reliable than API calls
		// This prevents false "quota exhausted" errors when API is slow/unreliable
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
		$cached_usage = Usage_Tracker::get_cached_usage(false);
		
		// If cached usage is valid and shows credits available, allow generation
		if (is_array($cached_usage) && isset($cached_usage['remaining']) && is_numeric($cached_usage['remaining'])) {
			$remaining = intval($cached_usage['remaining']);
			// Only block if cached usage explicitly shows insufficient credits
			if ($remaining >= $tokens) {
				return true; // Cached shows enough credits - allow generation
			}
			if ($remaining > 0 && $remaining < $tokens) {
				// Have some credits but not enough for this operation - still allow
				// Backend will handle the actual consumption and error handling
				return true;
			}
			if ($remaining === 0) {
				// Cached shows 0 credits - verify with fresh API check
				$quota = self::get_site_quota(true); // Force refresh
				
				if (is_wp_error($quota)) {
					// On error, allow generation (backend will enforce limits)
					return true;
				}
				
				// Check if remaining is sufficient after fresh check
				return isset($quota['remaining']) && intval($quota['remaining']) > 0;
			}
		}
		
		// No valid cached usage, try quota service
		$quota = self::get_site_quota();
		
		if (is_wp_error($quota)) {
			// On error, allow generation (backend will enforce limits)
			return true;
		}
		
		// Check if remaining is sufficient
		return isset($quota['remaining']) && intval($quota['remaining']) > 0;
	}
	
	/**
	 * Record local usage (for UI responsiveness)
	 * This is secondary to backend tracking
	 * 
	 * @param int $tokens Number of tokens used
	 * @return bool Success
	 */
	public static function record_local_usage($tokens) {
		$tokens = max(0, intval($tokens));
		
		if ($tokens <= 0) {
			return false;
		}
		
		$quota = self::get_site_quota();
		
		if (is_wp_error($quota) || !is_array($quota)) {
			return false;
		}
		
		// Update local cache
		$quota['used'] = ($quota['used'] ?? 0) + $tokens;
		$quota['remaining'] = max(0, ($quota['remaining'] ?? 0) - $tokens);
		
		// Cache for shorter time since it's now stale
		set_transient(self::CACHE_KEY, $quota, 60); // 1 minute
		
		return true;
	}
	
	/**
	 * Sync quota with backend
	 * 
	 * @return array|WP_Error Updated quota or error
	 */
	public static function sync_with_backend() {
		// Clear cache and fetch fresh
		delete_transient(self::CACHE_KEY);
		return self::get_site_quota(true);
	}
	
	/**
	 * Clear quota cache
	 */
	public static function clear_cache() {
		delete_transient(self::CACHE_KEY);
		// Also clear usage tracker cache
		Usage_Tracker::clear_cache();
	}
}

