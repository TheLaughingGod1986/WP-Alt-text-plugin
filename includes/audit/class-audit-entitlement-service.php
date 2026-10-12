<?php
/**
 * What each plan may do with the image audit.
 *
 * A thin adapter over the existing plan detection, so plan names appear in this
 * one file rather than being sprinkled through the audit code. Billing is not
 * touched here — this only answers "may they", never "charge them".
 *
 * @package BeepBeep_AI
 * @since   4.8.0
 */

namespace BeepBeepAI\AltTextGenerator\Audit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeepBeepAI\AltTextGenerator\Admin\Plan_Helpers;

/**
 * Audit capability checks.
 */
class Audit_Entitlement_Service {

	const RUN_FULL_AUDIT            = 'run_full_audit';
	const VIEW_ALL_AUDIT_RESULTS    = 'view_all_audit_results';
	const USE_BULK_REMEDIATION      = 'use_bulk_remediation';
	const ENABLE_CONTINUOUS_MONITOR = 'enable_continuous_monitoring';

	/**
	 * How many findings a free site can see before the upgrade prompt.
	 */
	const FREE_RESULT_LIMIT = 25;

	/**
	 * Every capability this service understands.
	 *
	 * @return string[]
	 */
	public static function capabilities() {
		return array(
			self::RUN_FULL_AUDIT,
			self::VIEW_ALL_AUDIT_RESULTS,
			self::USE_BULK_REMEDIATION,
			self::ENABLE_CONTINUOUS_MONITOR,
		);
	}

	/**
	 * Whether the current plan allows a capability.
	 *
	 * @param string $capability Capability slug.
	 * @return bool
	 */
	public static function can( $capability ) {
		$capability = is_string( $capability ) ? $capability : '';
		$plan       = self::plan();

		switch ( $capability ) {
			case self::RUN_FULL_AUDIT:
				// Everyone can measure their own site. Seeing the whole result is
				// what differs, not the ability to look.
				$allowed = true;
				break;

			case self::VIEW_ALL_AUDIT_RESULTS:
			case self::USE_BULK_REMEDIATION:
				$allowed = ! empty( $plan['is_paid'] );
				break;

			case self::ENABLE_CONTINUOUS_MONITOR:
				// Not built yet. The capability exists so the UI can show an
				// honest, disabled placeholder rather than pretending otherwise.
				$allowed = false;
				break;

			default:
				$allowed = false;
				break;
		}

		/**
		 * Filter an image audit capability check.
		 *
		 * @since 4.8.0
		 *
		 * @param bool   $allowed    Whether the capability is granted.
		 * @param string $capability Capability slug.
		 * @param array  $plan       Resolved plan data.
		 */
		return (bool) apply_filters( 'bbai_audit_capability', $allowed, $capability, $plan );
	}

	/**
	 * How many findings the current plan may see, or 0 for no limit.
	 *
	 * @return int
	 */
	public static function result_limit() {
		if ( self::can( self::VIEW_ALL_AUDIT_RESULTS ) ) {
			return 0;
		}

		/**
		 * Filter how many audit findings a free site can see.
		 *
		 * @since 4.8.0
		 *
		 * @param int $limit Maximum findings, 0 for unlimited.
		 */
		return max( 0, (int) apply_filters( 'bbai_audit_free_result_limit', self::FREE_RESULT_LIMIT ) );
	}

	/**
	 * Whether a result set is being cut short by the plan.
	 *
	 * @param int $total Total matching findings.
	 * @return bool
	 */
	public static function is_limited( $total ) {
		$limit = self::result_limit();

		return $limit > 0 && (int) $total > $limit;
	}

	/**
	 * Resolved plan data, with a safe shape when plan detection is unavailable.
	 *
	 * @return array<string,mixed>
	 */
	public static function plan() {
		if ( ! class_exists( '\BeepBeepAI\AltTextGenerator\Admin\Plan_Helpers' ) ) {
			return array(
				'plan_slug' => 'free',
				'is_free'   => true,
				'is_paid'   => false,
			);
		}

		$plan = Plan_Helpers::get_plan_data();

		return is_array( $plan ) ? $plan : array(
			'plan_slug' => 'free',
			'is_free'   => true,
			'is_paid'   => false,
		);
	}

	/**
	 * Current plan slug, for telemetry and CTA targeting.
	 *
	 * @return string
	 */
	public static function plan_slug() {
		$plan = self::plan();

		return isset( $plan['plan_slug'] ) ? (string) $plan['plan_slug'] : 'free';
	}
}
