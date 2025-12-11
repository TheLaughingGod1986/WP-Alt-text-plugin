<?php
/**
 * Usage Tracker for AltText AI
 * Caches usage data locally and handles upgrade prompts
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class Usage_Tracker {

	const CACHE_KEY    = 'bbai_usage_cache';
	const CACHE_EXPIRY = 300; // 5 minutes

	/**
	 * Allocate free credits on first generation request.
	 *
	 * IMPORTANT: Free credits are allocated ONCE PER SITE, not per user.
	 * - A site on free plan gets exactly 50 credits per month total
	 * - All users on the site share these 50 credits
	 * - Multiple users cannot trigger multiple allocations
	 * - Only when a user subscribes to Pro/Agency does the site get more credits
	 *
	 * This uses WordPress site-wide options (not per-user) to ensure:
	 * 1. Only one allocation per site (checked via get_option)
	 * 2. Usage is tracked per-site via X-Site-Hash header sent to backend
	 * 3. All users see the same usage quota
	 *
	 * @return bool True if credits were allocated, false if already allocated.
	 */
	public static function allocate_free_credits_if_needed() {
		// Check if free credits have already been allocated for this site
		// get_option() is site-wide, not per-user, so this check works across all users
		$free_credits_allocated = get_option( 'beepbeepai_free_credits_allocated', false );

		if ( $free_credits_allocated ) {
			// Already allocated for this site - return early
			// This prevents multiple users from triggering multiple allocations
			return false;
		}

		// Mark as allocated (one-time per site, shared across all users)
		// This option is site-wide, so all users will see it as allocated
		update_option( 'beepbeepai_free_credits_allocated', true, false );

		// Update usage cache with free credits (50 credits per month)
		// This cache is site-wide (set_transient), so all users see the same usage
		$reset_ts   = strtotime( 'first day of next month' );
		$usage_data = array(
			'used'           => 0,
			'limit'          => 50,  // Free plan: exactly 50 credits per month per site
			'remaining'      => 50,
			'plan'           => 'free',
			'resetDate'      => gmdate( 'Y-m-01', $reset_ts ),
			'resetTimestamp' => $reset_ts,
		);
		self::update_usage( $usage_data );

		return true;
	}

	/**
	 * Update cached usage data
	 */
	public static function update_usage( $usage_data ) {
		if ( ! is_array( $usage_data ) ) {
			return; }
		// Accept nested shape { usage: { ... } } from backend.
		if ( isset( $usage_data['usage'] ) && is_array( $usage_data['usage'] ) ) {
			$usage_data = $usage_data['usage'];
		}
		// Accept subscription wrapper.
		if ( isset( $usage_data['subscription'] ) && is_array( $usage_data['subscription'] ) ) {
			$sub        = $usage_data['subscription'];
			$usage_data = array_merge( $usage_data, array(
				'used'        => $sub['used'] ?? null,
				'limit'       => $sub['quota'] ?? null,
				'remaining'   => $sub['remaining'] ?? null,
				'plan_type'   => $sub['plan'] ?? $sub['status'] ?? null,
				'period_end'  => $sub['periodEnd'] ?? null,
				'period_start'=> $sub['periodStart'] ?? null,
			) );
		}
		// Normalize new API fields to legacy keys.
		$used_credits      = isset( $usage_data['credits_used'] ) ? intval( $usage_data['credits_used'] ) : ( isset( $usage_data['used'] ) ? intval( $usage_data['used'] ) : 0 );
		$remaining_credits = isset( $usage_data['credits_remaining'] ) ? intval( $usage_data['credits_remaining'] ) : ( isset( $usage_data['remaining'] ) ? intval( $usage_data['remaining'] ) : 0 );

		// Derive limit from explicit fields or used+remaining.
		if ( isset( $usage_data['total_limit'] ) ) {
			$limit = intval( $usage_data['total_limit'] );
		} elseif ( isset( $usage_data['limit'] ) ) {
			$limit = intval( $usage_data['limit'] );
		} else {
			$limit = max( 0, $used_credits + $remaining_credits );
		}
		if ( $limit <= 0 ) {
			$limit = 50;
		}

		$used      = max( 0, min( $used_credits, $limit ) );
		$remaining = $remaining_credits > 0 ? $remaining_credits : max( 0, $limit - $used );

		$current_ts = current_time( 'timestamp' );
		$reset_raw  = $usage_data['resetDate'] ?? ( $usage_data['period_end'] ?? '' );
		$reset_ts   = isset( $usage_data['resetTimestamp'] ) ? intval( $usage_data['resetTimestamp'] ) : 0;
		if ( $reset_ts <= 0 && $reset_raw ) {
			$reset_ts = strtotime( $reset_raw );
		}
		if ( $reset_ts <= 0 ) {
			$reset_ts = strtotime( 'first day of next month', $current_ts );
		}
		$seconds_until_reset = max( 0, $reset_ts - $current_ts );

		$normalized = array(
			'used'                => $used,
			'limit'               => $limit,
			'remaining'           => $remaining,
			'plan'                => $usage_data['plan'] ?? ( $usage_data['plan_type'] ?? 'free' ),
			'resetDate'           => $reset_raw ?: gmdate( 'Y-m-01', strtotime( '+1 month', $current_ts ) ),
			'reset_timestamp'     => $reset_ts,
			'seconds_until_reset' => $seconds_until_reset,
			'_cache_timestamp'    => $current_ts, // Store cache timestamp for age calculation
		);
		set_transient( self::CACHE_KEY, $normalized, self::CACHE_EXPIRY );

		// Clear other usage caches so dashboards pick up fresh numbers immediately.
		if ( class_exists( '\Optti\Framework\LicenseManager' ) ) {
			$license = \Optti\Framework\LicenseManager::instance();
			if ( method_exists( $license, 'clear_quota_cache' ) ) {
				$license->clear_quota_cache();
			}
		}
		if ( class_exists( '\Optti\Framework\Cache' ) ) {
			\Optti\Framework\Cache::instance()->delete( 'usage_stats' );
		}
	}

	/**
	 * Get cached usage data
	 */
	public static function get_cached_usage( $force_refresh = false ) {
		// PRIORITY 1: Check for active license first - license overrides personal account
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php';
		$api_client = new API_Client_V2();

		if ( $api_client->has_active_license() ) {
			$license_data = $api_client->get_license_data();
			if ( $license_data && isset( $license_data['organization'] ) ) {
				$org  = $license_data['organization'];
				$plan = strtolower( $org['plan'] ?? 'free' );

				// Parse reset date
				$reset_ts = strtotime( 'first day of next month' );
				if ( ! empty( $org['resetDate'] ) ) {
					$parsed = strtotime( $org['resetDate'] );
					if ( $parsed > 0 ) {
						$reset_ts = $parsed;
					}
				}

				$current_ts = current_time( 'timestamp' );

				// Get plan-specific limits
				$plan_limits = array(
					'free'   => 50,
					'pro'    => 1000,
					'agency' => 10000,
				);
				$limit       = $plan_limits[ $plan ] ?? 50;

				// Get usage from organization data
				$tokens_remaining = isset( $org['tokensRemaining'] ) ? max( 0, intval( $org['tokensRemaining'] ) ) : $limit;
				$tokens_used      = isset( $org['tokensUsed'] ) ? max( 0, intval( $org['tokensUsed'] ) ) : 0;

				// Calculate used: prefer tokensUsed if available, otherwise calculate from remaining
				if ( $tokens_used > 0 ) {
					$used = $tokens_used;
				} else {
					$used = max( 0, $limit - $tokens_remaining );
				}

				// Ensure used doesn't exceed limit
				if ( $used > $limit ) {
					$used             = $limit;
					$tokens_remaining = 0;
				} else {
					$tokens_remaining = max( 0, $limit - $used );
				}

				// Return organization quota instead of personal account
				return array(
					'used'                => $used,
					'limit'               => $limit,
					'remaining'           => $tokens_remaining,
					'plan'                => $plan,
					'resetDate'           => gmdate( 'Y-m-01', $reset_ts ),
					'reset_timestamp'     => $reset_ts,
					'seconds_until_reset' => max( 0, $reset_ts - $current_ts ),
				);
			}
		}

		// PRIORITY 2: If no license, fall back to personal account data
		// If force refresh, clear cache first
		if ( $force_refresh ) {
			delete_transient( self::CACHE_KEY );
		}

		$cached = get_transient( self::CACHE_KEY );

		if ( $cached === false ) {
			// Check if free credits have been allocated for this site
			$free_credits_allocated = get_option( 'beepbeepai_free_credits_allocated', false );

			// Default values if no cache exists
			$reset_ts = strtotime( 'first day of next month' );

			// Only show free credits if they've been allocated (first generation request)
			// This prevents showing 50 credits before first use
			if ( $free_credits_allocated ) {
				return array(
					'used'                => 0,
					'limit'               => 50,
					'remaining'           => 50,
					'plan'                => 'free',
					'resetDate'           => gmdate( 'Y-m-01', $reset_ts ),
					'reset_timestamp'     => $reset_ts,
					'seconds_until_reset' => max( 0, $reset_ts - current_time( 'timestamp' ) ),
				);
			} else {
				// Free credits not yet allocated - show as unavailable
				return array(
					'used'                => 0,
					'limit'               => 0,
					'remaining'           => 0,
					'plan'                => 'free',
					'resetDate'           => gmdate( 'Y-m-01', $reset_ts ),
					'reset_timestamp'     => $reset_ts,
					'seconds_until_reset' => max( 0, $reset_ts - current_time( 'timestamp' ) ),
				);
			}
		}

		return $cached;
	}

	/**
	 * Clear cached usage
	 */
	public static function clear_cache() {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Check if user should see upgrade prompt
	 */
	public static function should_show_upgrade_prompt() {
		$usage      = self::get_cached_usage();
		$percentage = ( $usage['used'] / max( $usage['limit'], 1 ) ) * 100;

		// Show at 80% usage
		return $percentage >= 80;
	}

	/**
	 * Check if user is at limit
	 */
	public static function is_at_limit() {
		$usage = self::get_cached_usage();
		return $usage['remaining'] <= 0;
	}

	/**
	 * Get usage stats for display
	 */
	public static function get_stats_display( $force_refresh = false ) {
		$usage = self::get_cached_usage( $force_refresh );
		$limit = max( 1, intval( $usage['limit'] ) );
		$used  = max( 0, intval( $usage['used'] ) );
		if ( $used > $limit ) {
			$used = $limit; }
		$remaining        = max( 0, $limit - $used );
		$percentage_exact = $limit > 0 ? ( $used / $limit ) * 100 : 0;
		$percentage_exact = min( 100, max( 0, $percentage_exact ) );

		// Calculate days until reset
		$reset_timestamp   = isset( $usage['reset_timestamp'] ) ? intval( $usage['reset_timestamp'] ) : 0;
		$current_timestamp = current_time( 'timestamp' );

		if ( $reset_timestamp <= 0 && ! empty( $usage['resetDate'] ) ) {
			// Try parsing the reset date - handle both Y-m-d and other formats
			$reset_date_str   = $usage['resetDate'];
			$parsed_timestamp = strtotime( $reset_date_str );

			// Validate the parsed timestamp - it should be in the future and not more than 2 months away
			$max_future = strtotime( '+2 months', $current_timestamp );
			if ( $parsed_timestamp > 0 && $parsed_timestamp > $current_timestamp && $parsed_timestamp <= $max_future ) {
				$reset_timestamp = $parsed_timestamp;
			} else {
				// Invalid date, use first of next month
				$reset_timestamp = strtotime( 'first day of next month', $current_timestamp );
			}
		}

		// Fallback to next month if no reset date is set or invalid
		if ( $reset_timestamp <= 0 || $reset_timestamp <= $current_timestamp ) {
			$reset_timestamp = strtotime( 'first day of next month', $current_timestamp );
		}

		// Ensure reset is at midnight (start of day)
		$reset_timestamp = strtotime( 'midnight', $reset_timestamp );

		$seconds_until_reset = max( 0, $reset_timestamp - $current_timestamp );
		$days_until_reset    = (int) floor( $seconds_until_reset / DAY_IN_SECONDS );

		// Get plan with fallback
		$plan = isset( $usage['plan'] ) && ! empty( $usage['plan'] ) ? $usage['plan'] : 'free';

		// Get reset date with fallback
		$reset_date_display = $reset_timestamp ? gmdate( 'F j, Y', $reset_timestamp ) : '';
		if ( empty( $reset_date_display ) && ! empty( $usage['resetDate'] ) ) {
			$parsed_reset       = strtotime( $usage['resetDate'] );
			$reset_date_display = $parsed_reset > 0 ? gmdate( 'F j, Y', $parsed_reset ) : gmdate( 'F j, Y', strtotime( 'first day of next month' ) );
		}
		if ( empty( $reset_date_display ) ) {
			$reset_date_display = gmdate( 'F j, Y', strtotime( 'first day of next month' ) );
		}

		return array(
			'used'                => $used,
			'limit'               => $limit,
			'remaining'           => $remaining,
			'percentage'          => $percentage_exact,
			'percentage_exact'    => $percentage_exact,
			'percentage_display'  => self::format_percentage_label( $percentage_exact ),
			'plan'                => $plan,
			'plan_label'          => ucfirst( $plan ),
			'reset_date'          => $reset_date_display,
			'reset_timestamp'     => $reset_timestamp,
			'days_until_reset'    => $days_until_reset,
			'seconds_until_reset' => $seconds_until_reset,
			'is_free'             => $plan === 'free',
			'is_pro'              => $plan === 'pro',
		);
	}

	/**
	 * Get upgrade URL
	 */
	public static function get_upgrade_url() {
		// Try framework config first
		$default = 'https://oppti.dev/pricing';
		if ( function_exists( 'opptiai_framework' ) ) {
			$framework = opptiai_framework();
			if ( $framework && isset( $framework->config ) ) {
				$config_url = $framework->config->get( 'pricing', $default );
				if ( ! empty( $config_url ) ) {
					$default = $config_url;
				}
			}
		}
		$stored = get_option( 'bbai_upgrade_url', $default );
		return apply_filters( 'bbai_upgrade_url', $stored ?: $default );
	}

	/**
	 * Get billing portal URL (Stripe customer portal, etc.)
	 */
	public static function get_billing_portal_url() {
		$stored = get_option( 'bbai_billing_portal_url', '' );
		return apply_filters( 'bbai_billing_portal_url', $stored );
	}

	/**
	 * Dismiss upgrade notice for current session
	 */
	public static function dismiss_upgrade_notice() {
		set_transient( 'bbai_upgrade_dismissed', true, HOUR_IN_SECONDS );
	}

	/**
	 * Check if upgrade notice is dismissed
	 */
	public static function is_upgrade_dismissed() {
		return get_transient( 'bbai_upgrade_dismissed' ) === true;
	}

	/**
	 * Refresh usage data from API and update cache
	 */
	public static function refresh_from_api( $api_client = null ) {
		if ( ! $api_client ) {
			// Try to get API client from global instance
			global $beepbeepai_plugin;
			if ( isset( $beepbeepai_plugin ) && isset( $beepbeepai_plugin->api_client ) ) {
				$api_client = $beepbeepai_plugin->api_client;
			}
		}

		if ( ! $api_client ) {
			return false;
		}

		$live_usage = $api_client->get_usage();
		if ( is_array( $live_usage ) && ! empty( $live_usage ) ) {
			self::update_usage( $live_usage );
			return true;
		}

		return false;
	}

	/**
	 * Format percentage label with dynamic precision for small numbers.
	 */
	public static function format_percentage_label( $percentage_value ) {
		$value = floatval( $percentage_value );

		if ( $value <= 0 ) {
			return '0';
		}

		if ( $value >= 100 ) {
			return '100';
		}

		if ( $value < 0.01 ) {
			return '<0.01';
		}

		if ( $value < 0.1 ) {
			return number_format_i18n( $value, 2 );
		}

		if ( $value < 1 ) {
			return number_format_i18n( $value, 1 );
		}

		if ( $value < 10 ) {
			return number_format_i18n( $value, 1 );
		}

		return number_format_i18n( $value, 0 );
	}
}
