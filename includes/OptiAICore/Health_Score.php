<?php
/**
 * Turns a site-level score into the label/disclaimer/trend every OptiAI
 * dashboard Hero Score card shows.
 *
 * @package OptiAI_Core
 */

namespace OptiAI\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Health_Score {

	/**
	 * Score band label, per the shared 0-100 bands used across every module.
	 *
	 * @param int $score 0-100.
	 * @return string
	 */
	public static function label( $score ) {
		$score = (int) $score;
		if ( $score <= 39 ) {
			return __( 'Poor', 'optiai-core' );
		}
		if ( $score <= 59 ) {
			return __( 'Needs work', 'optiai-core' );
		}
		if ( $score <= 74 ) {
			return __( 'Fair', 'optiai-core' );
		}
		if ( $score <= 89 ) {
			return __( 'Good', 'optiai-core' );
		}
		return __( 'Excellent', 'optiai-core' );
	}

	/**
	 * Difference between the current and previous score, signed.
	 *
	 * @param int      $current  Current score.
	 * @param int|null $previous Previous score, or null when there is none
	 *                           (first scan) — trend is not shown in that case.
	 * @return int|null
	 */
	public static function trend( $current, $previous ) {
		if ( null === $previous ) {
			return null;
		}
		return (int) $current - (int) $previous;
	}

	/**
	 * The disclaimer every score card must show — a health indicator, not a
	 * ranking or traffic guarantee.
	 *
	 * @return string
	 */
	public static function disclaimer() {
		return __(
			'This is an OptiAI health indicator based on the checks in this plugin, not a formal certification or a ranking guarantee. Improving these issues may strengthen accessibility, search visibility and content quality.',
			'optiai-core'
		);
	}

	/**
	 * Rough, clearly-labelled "estimated improvement" preview for a bulk
	 * action — sums each affected item's points-back-to-100 potential,
	 * capped at 100, never presented as guaranteed.
	 *
	 * @param int   $current_site_score Current site score.
	 * @param int[] $affected_item_scores Current per-item scores of the items about to be optimised.
	 * @return int Estimated new site score after those items reach 100.
	 */
	public static function estimate_after_optimising( $current_site_score, array $affected_item_scores ) {
		if ( empty( $affected_item_scores ) ) {
			return (int) $current_site_score;
		}
		// Treat the optimised items as if they scored 100, keep every other
		// item's contribution unchanged, and recompute the average deduction.
		// This needs the full item-score population in the general case; the
		// module passes it in via Score_Service::site_score() with the
		// affected items patched to 100 for an exact figure. This helper
		// covers the common "just these items" estimate when only the
		// affected subset is known.
		$improvement_points = 0;
		foreach ( $affected_item_scores as $score ) {
			$improvement_points += 100 - max( 0, min( 100, (int) $score ) );
		}
		$avg_gain = $improvement_points / max( 1, count( $affected_item_scores ) );
		// Conservative: assume the affected subset is a modest slice of the
		// full scanned population, so cap the visible site-score bump.
		$estimated = (int) $current_site_score + (int) round( $avg_gain * 0.15 );
		return max( 0, min( 100, $estimated ) );
	}
}
