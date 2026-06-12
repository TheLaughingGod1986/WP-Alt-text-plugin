<?php
/**
 * Plan Detection Helpers for BeepBeep AI
 * Centralized helper functions for detecting user plan types
 *
 * @package BeepBeep_AI
 * @since 5.5.0
 */

namespace BeepBeepAI\AltTextGenerator\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plan_Helpers
 * Provides centralized plan detection functionality
 */
class Plan_Helpers {

	/**
	 * Cached plan data to avoid multiple lookups
	 *
	 * @var array|null
	 */
	private static $cached_plan_data = null;

	private const STARTER_MONTHLY_LIMIT = 100;
	private const GROWTH_MONTHLY_LIMIT  = 1000;

	/**
	 * True when a slug represents a paid plan.
	 *
	 * @param string $plan_slug Plan slug.
	 * @return bool
	 */
	private static function is_paid_plan_slug( string $plan_slug ): bool {
		return in_array( sanitize_key( $plan_slug ), array( 'starter', 'pro', 'growth', 'agency', 'enterprise' ), true );
	}

	/**
	 * True when a paid plan includes upload automation / Autopilot.
	 *
	 * Starter is paid, but remains manual generation only.
	 *
	 * @param string $plan_slug Plan slug.
	 * @return bool
	 */
	public static function plan_can_use_autopilot( string $plan_slug ): bool {
		return in_array( sanitize_key( $plan_slug ), array( 'pro', 'growth', 'agency', 'enterprise' ), true );
	}

	/**
	 * Minimum allowance required before trusting a paid plan claim.
	 *
	 * @param string $plan_slug Plan slug.
	 * @return int
	 */
	private static function minimum_monthly_limit_for_plan( string $plan_slug ): int {
		$plan_slug = sanitize_key( $plan_slug );
		if ( 'starter' === $plan_slug ) {
			return self::STARTER_MONTHLY_LIMIT;
		}
		if ( in_array( $plan_slug, array( 'pro', 'growth', 'agency', 'enterprise' ), true ) ) {
			return self::GROWTH_MONTHLY_LIMIT;
		}

		return 0;
	}

	/**
	 * Demote stale paid claims that carry free-sized usage.
	 *
	 * @param string $plan_slug Plan slug.
	 * @param int    $limit     Monthly allowance.
	 * @return string
	 */
	private static function normalize_plan_for_limit( string $plan_slug, int $limit ): string {
		$plan_slug = sanitize_key( $plan_slug );
		$minimum   = self::minimum_monthly_limit_for_plan( $plan_slug );
		if ( $minimum > 0 && $limit > 0 && $limit < $minimum ) {
			return 'free';
		}

		return '' !== $plan_slug ? $plan_slug : 'free';
	}

	/**
	 * Read the broadest monthly allowance from a remote payload.
	 *
	 * @param array $payload Payload.
	 * @return int
	 */
	private static function read_limit_from_payload( array $payload ): int {
		foreach ( array( $payload, $payload['quota'] ?? array(), $payload['usage'] ?? array(), $payload['entitlement_state'] ?? array(), $payload['organization'] ?? array() ) as $source ) {
			if ( ! is_array( $source ) ) {
				continue;
			}
			foreach ( array( 'token_limit', 'limit', 'credits_total', 'creditsTotal', 'creditsLimit', 'total_limit', 'monthly_limit', 'monthly_credits', 'monthly_included_credits' ) as $key ) {
				if ( isset( $source[ $key ] ) && is_numeric( $source[ $key ] ) ) {
					return max( 0, (int) $source[ $key ] );
				}
			}
		}

		return 0;
	}

	/**
	 * Determine whether cached license data carries explicit paid entitlement proof.
	 *
	 * @param array $license_data License data.
	 * @return bool
	 */
	private static function has_paid_entitlement_signal( array $license_data ): bool {
		$org = isset( $license_data['organization'] ) && is_array( $license_data['organization'] )
			? $license_data['organization']
			: array();

		foreach ( array( $license_data, $org ) as $source ) {
			foreach ( array( 'has_paid_entitlement', 'is_paid' ) as $key ) {
				if ( isset( $source[ $key ] ) && true === $source[ $key ] ) {
					return true;
				}
			}

			foreach ( array( 'stripe_subscription_id', 'subscription_id' ) as $key ) {
				if ( isset( $source[ $key ] ) && is_scalar( $source[ $key ] ) && '' !== trim( (string) $source[ $key ] ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get the current plan slug
	 *
	 * @return string Plan slug (free, growth, pro, agency)
	 */
	public static function get_plan_slug() {
		$data = self::get_plan_data();
		return $data['plan_slug'];
	}

	/**
	 * Get all plan data including flags
	 *
	 * @param bool $force_refresh Force refresh of cached data
	 * @return array Plan data with keys: plan_slug, is_free, is_growth, is_agency, is_pro, is_paid, can_autopilot
	 */
	public static function get_plan_data( $force_refresh = false ) {
		if ( null !== self::$cached_plan_data && ! $force_refresh ) {
			return self::$cached_plan_data;
		}

		$plan_slug   = 'free';
		$usage_limit = 0;

		// Try to get plan from Usage_Tracker
		if ( class_exists( 'BeepBeepAI\\AltTextGenerator\\Usage_Tracker' ) ) {
			$usage_data = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_cached_usage();
			if ( is_array( $usage_data ) && isset( $usage_data['plan'] ) && ! empty( $usage_data['plan'] ) ) {
				$usage_limit = self::read_limit_from_payload( $usage_data );
				$plan_slug   = self::normalize_plan_for_limit( strtolower( $usage_data['plan'] ), $usage_limit );
			}
		}

		// Check for license-based plan
		if ( class_exists( 'BeepBeepAI\\AltTextGenerator\\API_Client_V2' ) ) {
			try {
				$api_client  = \BeepBeepAI\AltTextGenerator\API_Client_V2::get_instance();
				$license_key = $api_client->get_license_key();
				$has_license = ! empty( $license_key );

				// If using license and plan is still free, check license data.
				if ( $has_license && 'free' === $plan_slug ) {
					$license_data = $api_client->get_license_data();
					if ( $license_data && isset( $license_data['organization'] ) ) {
						$license_plan       = strtolower( $license_data['organization']['plan'] ?? 'free' );
						$license_limit      = self::read_limit_from_payload( (array) $license_data );
						$license_limit      = $license_limit > 0 ? $license_limit : $usage_limit;
						$normalized_license = self::normalize_plan_for_limit( $license_plan, $license_limit );
						if ( ! self::is_paid_plan_slug( $license_plan ) || ( 'free' !== $normalized_license && self::has_paid_entitlement_signal( (array) $license_data ) ) ) {
							$plan_slug = $normalized_license;
						}
					}
				}
			} catch ( \Exception $e ) {
				unset( $e ); // Silently skip; fall back to current plan_slug.
			}
		}

		// Calculate plan flags
		$is_free   = ( 'free' === $plan_slug );
		$is_growth = ( 'pro' === $plan_slug || 'growth' === $plan_slug );
		$is_agency = ( 'agency' === $plan_slug );
		$is_pro    = self::is_paid_plan_slug( $plan_slug ); // Any paid plan
		$is_paid   = $is_pro;

		self::$cached_plan_data = array(
			'plan_slug'     => $plan_slug,
			'is_free'       => $is_free,
			'is_growth'     => $is_growth,
			'is_agency'     => $is_agency,
			'is_pro'        => $is_pro,
			'is_paid'       => $is_paid,
			'can_autopilot' => self::plan_can_use_autopilot( $plan_slug ),
		);

		return self::$cached_plan_data;
	}

	/**
	 * Check if user is on free plan
	 *
	 * @return bool
	 */
	public static function is_free() {
		$data = self::get_plan_data();
		return $data['is_free'];
	}

	/**
	 * Check if user is on growth/pro plan (not agency)
	 *
	 * @return bool
	 */
	public static function is_growth() {
		$data = self::get_plan_data();
		return $data['is_growth'];
	}

	/**
	 * Check if user is on agency plan
	 *
	 * @return bool
	 */
	public static function is_agency() {
		$data = self::get_plan_data();
		return $data['is_agency'];
	}

	/**
	 * Check if user is on any paid plan (pro, growth, or agency)
	 *
	 * @return bool
	 */
	public static function is_paid() {
		$data = self::get_plan_data();
		return $data['is_pro'];
	}

	/**
	 * Phase 15 canonical monetisation tier: free | growth | pro.
	 * Maps API slugs: pro/growth → growth; agency/enterprise → pro.
	 *
	 * @return string 'free'|'growth'|'pro'
	 */
	public static function get_canonical_monetisation_tier() {
		if ( ! function_exists( 'bbai_monetisation_canonical_tier_from_slug' ) ) {
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/monetisation-phase15.php';
		}
		return bbai_monetisation_canonical_tier_from_slug( self::get_plan_slug() );
	}

	/**
	 * True when on the top commercial tier (agency / enterprise in API terms).
	 */
	public static function is_monetisation_pro_tier() {
		return self::get_canonical_monetisation_tier() === 'pro';
	}

	/**
	 * True for Growth or Pro canonical tier (any paid subscription in Phase 15 vocabulary).
	 */
	public static function is_monetisation_growth_or_higher() {
		return ! self::is_free();
	}

	/**
	 * Clear the cached plan data
	 * Useful when plan changes during a session
	 */
	public static function clear_cache() {
		self::$cached_plan_data = null;
	}

	/**
	 * Get the plan badge text
	 *
	 * @return string Badge text for display
	 */
	public static function get_plan_badge_text() {
		$plan_slug = self::get_plan_slug();

		switch ( $plan_slug ) {
			case 'agency':
				return __( 'AGENCY', 'beepbeep-ai-alt-text-generator' );
			case 'pro':
			case 'growth':
				return __( 'PRO', 'beepbeep-ai-alt-text-generator' );
			default:
				return __( 'FREE', 'beepbeep-ai-alt-text-generator' );
		}
	}

	/**
	 * Get the plan badge variant (CSS class suffix)
	 *
	 * @return string Badge variant (free, pro, agency)
	 */
	public static function get_plan_badge_variant() {
		$plan_slug = self::get_plan_slug();

		switch ( $plan_slug ) {
			case 'agency':
				return 'agency';
			case 'pro':
			case 'growth':
				return 'pro';
			default:
				return 'free';
		}
	}

	/**
	 * Export plan variables for use in PHP templates
	 * Returns an array that can be extracted into local scope
	 *
	 * @return array Plan variables
	 */
	public static function get_template_vars() {
		$data = self::get_plan_data();
		return array(
			'plan_slug'                   => $data['plan_slug'],
			'is_free'                     => $data['is_free'],
			'is_growth'                   => $data['is_growth'],
			'is_agency'                   => $data['is_agency'],
			'is_pro'                      => $data['is_pro'],
			'canonical_monetisation_tier' => self::get_canonical_monetisation_tier(),
			'plan_badge_text'             => self::get_plan_badge_text(),
			'plan_badge_variant'          => self::get_plan_badge_variant(),
		);
	}
}
