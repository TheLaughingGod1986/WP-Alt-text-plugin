<?php
/**
 * Single source of truth for "is this a paid plan?".
 *
 * Every paid plan (starter, growth, pro, agency, enterprise) is paid. Feature
 * gates that are tier-specific (for example Autopilot, which Starter does not
 * include) must not use this helper; they keep their own checks.
 *
 * @package BeepBeepAI\AltTextGenerator
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Plan_Tier', false ) ) {
	final class Plan_Tier {
		/**
		 * Paid plan slugs, as reported by the backend or stored locally.
		 */
		public const PAID_PLANS = array( 'starter', 'growth', 'pro', 'agency', 'enterprise' );

		/**
		 * Every plan slug the plugin understands.
		 */
		public const KNOWN_PLANS = array( 'free', 'trial', 'starter', 'growth', 'pro', 'agency', 'enterprise' );

		/**
		 * Plans that skip the out-of-credits lock on the Library tab. Unchanged from
		 * 4.6.142. Starter has a monthly limit and must hit the lock (CoS, 4.6.143).
		 */
		public const LIBRARY_CREDIT_LOCK_EXEMPT = array( 'pro', 'growth', 'agency' );

		/**
		 * Plans for which has_reached_limit() never reports the limit. Unchanged
		 * from 4.6.142; Starter is not exempt.
		 */
		public const LIMIT_CHECK_EXEMPT = array( 'pro', 'agency' );

		/**
		 * Normalise a plan value to a lowercase slug ('' when not a scalar).
		 *
		 * @param mixed $plan Plan value.
		 */
		public static function slug( $plan ): string {
			if ( ! is_scalar( $plan ) ) {
				return '';
			}
			$plan = strtolower( trim( (string) $plan ) );
			$plan = (string) preg_replace( '/[^a-z0-9_-]/', '', $plan );
			if ( 'anonymous_trial' === $plan ) {
				return 'trial';
			}
			return $plan;
		}

		/**
		 * Whether the plan is any paid plan.
		 *
		 * @param mixed $plan Plan value.
		 */
		public static function is_paid( $plan ): bool {
			return in_array( self::slug( $plan ), self::PAID_PLANS, true );
		}

		/**
		 * Whether the plan skips the Library tab out-of-credits lock.
		 *
		 * @param mixed $plan Plan value.
		 */
		public static function skips_library_credit_lock( $plan ): bool {
			return in_array( self::slug( $plan ), self::LIBRARY_CREDIT_LOCK_EXEMPT, true );
		}

		/**
		 * Whether has_reached_limit() skips the limit check for this plan.
		 *
		 * @param mixed $plan Plan value.
		 */
		public static function skips_limit_check( $plan ): bool {
			return in_array( self::slug( $plan ), self::LIMIT_CHECK_EXEMPT, true );
		}
	}
}
