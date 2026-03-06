<?php
/**
 * Trial Quota Service
 * Manages local-only trial quota for unauthenticated users.
 * Allows a limited number of free generations before requiring account creation.
 *
 * @package BeepBeepAI\AltTextGenerator
 * @since 6.2.0
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trial_Quota {

	/**
	 * Default number of free trial generations.
	 */
	const TRIAL_LIMIT = 10;

	/**
	 * Option name prefix for trial usage counter.
	 */
	const OPTION_PREFIX = 'bbai_trial_usage_';

	/**
	 * Get the site hash used for keying trial usage.
	 *
	 * @return string Site fingerprint hash.
	 */
	private static function get_site_hash(): string {
		if ( ! function_exists( '\BeepBeepAI\AltTextGenerator\bbai_get_trial_site_hash' ) ) {
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-trial-quota.php';
		}

		return bbai_get_trial_site_hash();
	}

	/**
	 * Get the option key for this site's trial usage.
	 *
	 * @return string Option name.
	 */
	private static function option_key(): string {
		return self::OPTION_PREFIX . sanitize_key( self::get_site_hash() );
	}

	/**
	 * Get number of trial generations used.
	 *
	 * @return int Number of generations used.
	 */
	public static function get_used(): int {
		$used = absint( get_option( self::option_key(), 0 ) );
		return min( self::get_limit(), max( 0, $used ) );
	}

	/**
	 * Get number of trial generations remaining.
	 *
	 * @return int Remaining generations (0 if exhausted).
	 */
	public static function get_remaining(): int {
		$limit = self::get_limit();
		$used  = self::get_used();
		return max( 0, $limit - $used );
	}

	/**
	 * Get the trial limit (filterable).
	 *
	 * @return int Trial limit.
	 */
	public static function get_limit(): int {
		return absint( apply_filters( 'bbai_trial_limit', self::TRIAL_LIMIT ) );
	}

	/**
	 * Check if the trial quota is exhausted.
	 *
	 * @return bool True if no trial generations remain.
	 */
	public static function is_exhausted(): bool {
		return self::get_remaining() <= 0;
	}

	/**
	 * Increment trial usage by one.
	 * Should be called AFTER a successful generation.
	 *
	 * @return int New used count.
	 */
	public static function increment(): int {
		$key  = self::option_key();
		$used = min( self::get_limit(), absint( get_option( $key, 0 ) ) + 1 );
		update_option( $key, $used, false );
		return $used;
	}

	/**
	 * Check whether the current user should be gated by the trial system.
	 * Returns true if the user is NOT authenticated (no account/license).
	 *
	 * @return bool True if trial gating applies.
	 */
	public static function is_trial_user(): bool {
		if ( function_exists( 'bbai_is_authenticated' ) && \bbai_is_authenticated() ) {
			return false;
		}

		// If the API client is available, check authentication.
		if ( ! class_exists( __NAMESPACE__ . '\API_Client_V2' ) ) {
			$api_path = defined( 'BEEPBEEP_AI_PLUGIN_DIR' )
				? BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php'
				: '';
			if ( $api_path && file_exists( $api_path ) ) {
				require_once $api_path;
			}
		}

		if ( class_exists( __NAMESPACE__ . '\API_Client_V2' ) ) {
			$api = API_Client_V2::get_instance();
			// User has a license → not a trial user.
			if ( $api->has_active_license() ) {
				return false;
			}
			// User is authenticated (JWT) → not a trial user.
			if ( $api->is_authenticated() ) {
				return false;
			}

			$stored_token  = get_option( 'beepbeepai_jwt_token', '' );
			$legacy_token  = get_option( 'opptibbai_jwt_token', '' );
			$stored_license = $api->get_license_key();
			if ( ! empty( $stored_token ) || ! empty( $legacy_token ) || ! empty( $stored_license ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Gate check: can the current site perform a trial generation?
	 *
	 * @return true|\WP_Error True if allowed, WP_Error if blocked.
	 */
	public static function check() {
		if ( ! self::is_trial_user() ) {
			return true; // Authenticated users bypass trial gating.
		}

		if ( ! self::is_exhausted() ) {
			return true; // Trial generations remain.
		}

		return new \WP_Error(
			'bbai_trial_exhausted',
			__( "You've used your 10 free generations. Create a free account to unlock 50 more credits per month.", 'beepbeep-ai-alt-text-generator' ),
			[
				'code'      => 'bbai_trial_exhausted',
				'remaining' => 0,
				'limit'     => self::get_limit(),
				'used'      => self::get_used(),
				'site_hash' => self::get_site_hash(),
			]
		);
	}

	/**
	 * Get trial status data for the UI.
	 *
	 * @return array Trial status information.
	 */
	public static function get_status(): array {
		return [
			'is_trial'  => self::is_trial_user(),
			'limit'     => self::get_limit(),
			'used'      => self::get_used(),
			'remaining' => self::get_remaining(),
			'exhausted' => self::is_exhausted(),
		];
	}

	/**
	 * Reset trial usage (admin use / testing only).
	 *
	 * @return bool True on success.
	 */
	public static function reset(): bool {
		return delete_option( self::option_key() );
	}
}
