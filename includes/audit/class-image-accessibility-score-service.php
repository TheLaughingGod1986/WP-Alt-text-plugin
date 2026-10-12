<?php
/**
 * Site-level image accessibility health score.
 *
 * @package BeepBeep_AI
 * @since   4.8.0
 */

namespace BeepBeepAI\AltTextGenerator\Audit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns audit counters into a transparent 0–100 health score.
 *
 * The formula, in full:
 *
 *   Every scanned image contributes a deduction set by its most serious issue:
 *
 *     critical    100   no alt text at all
 *     warning      40   filename, generic, duplicate, short, possible stuffing
 *     review       15   empty alt text that a person needs to judge
 *     information   5   long alt, unattached, usage unknown
 *     no issues     0
 *
 *   Images the administrator has marked decorative, dismissed or resolved
 *   contribute 0 — they are settled, not outstanding.
 *
 *   score = 100 − (total deductions ÷ images scanned)
 *
 * So a site where every image is missing alt text scores 0, a site with no
 * findings scores 100, and one where a quarter of images are missing alt text
 * and the rest are clean scores 75.
 *
 * This is an OpptiAI health indicator based on the checks in this plugin. It is
 * not a formal accessibility certification, and it is deliberately arithmetic
 * that a person can check by hand.
 */
class Image_Accessibility_Score_Service {

	/**
	 * Formula version. Bump when the weights or shape change so stored scores
	 * can be told apart.
	 */
	const FORMULA_VERSION = 1;

	/**
	 * Default per-severity deductions.
	 *
	 * @return array<string,int>
	 */
	public static function default_weights() {
		return array(
			Audit_Issue::SEVERITY_CRITICAL    => 100,
			Audit_Issue::SEVERITY_WARNING     => 40,
			Audit_Issue::SEVERITY_REVIEW      => 15,
			Audit_Issue::SEVERITY_INFORMATION => 5,
		);
	}

	/**
	 * The weights actually in force.
	 *
	 * @return array<string,int>
	 */
	public static function get_weights() {
		$weights = self::default_weights();

		/**
		 * Filter the per-severity deductions used by the health score.
		 *
		 * Values are clamped to 0–100. Replace the whole scoring service via
		 * `bbai_audit_score` if you need a different shape entirely.
		 *
		 * @since 4.8.0
		 *
		 * @param array<string,int> $weights Severity => deduction.
		 */
		$filtered = (array) apply_filters( 'bbai_audit_score_weights', $weights );

		foreach ( $weights as $severity => $default ) {
			$value                = isset( $filtered[ $severity ] ) && is_numeric( $filtered[ $severity ] )
				? (int) $filtered[ $severity ]
				: $default;
			$weights[ $severity ] = max( 0, min( 100, $value ) );
		}

		return $weights;
	}

	/**
	 * Calculate the score and headline figures.
	 *
	 * @param array<string,int> $counters {
	 *     Counts from the audit run.
	 *
	 *     @type int $total_images    Images in scope.
	 *     @type int $scanned_images  Images actually scanned so far.
	 *     @type int $critical        Images whose worst severity is critical.
	 *     @type int $warning         Images whose worst severity is warning.
	 *     @type int $review          Images whose worst severity is review.
	 *     @type int $information     Images whose worst severity is information.
	 *     @type int $settled         Decorative, dismissed or resolved images.
	 * }
	 * @return array<string,mixed>
	 */
	public static function calculate( array $counters ) {
		$weights = self::get_weights();

		$total_images   = max( 0, (int) ( $counters['total_images'] ?? 0 ) );
		$scanned_images = max( 0, (int) ( $counters['scanned_images'] ?? 0 ) );
		$scanned_images = $total_images > 0 ? min( $scanned_images, $total_images ) : $scanned_images;

		$critical    = max( 0, (int) ( $counters['critical'] ?? 0 ) );
		$warning     = max( 0, (int) ( $counters['warning'] ?? 0 ) );
		$review      = max( 0, (int) ( $counters['review'] ?? 0 ) );
		$information = max( 0, (int) ( $counters['information'] ?? 0 ) );
		$settled     = max( 0, (int) ( $counters['settled'] ?? 0 ) );

		$images_with_issues = $critical + $warning + $review + $information;

		/*
		 * What the user is asked to act on excludes informational notes: those are
		 * observations ("not attached to any post") that no amount of alt-text work
		 * will clear, so counting them as outstanding would mean the number never
		 * reaches zero. They still carry a small score deduction.
		 */
		$images_needing_attention = $critical + $warning + $review;
		$images_passing           = max( 0, $scanned_images - $images_with_issues );

		if ( $scanned_images > 0 ) {
			$deductions = ( $critical * $weights[ Audit_Issue::SEVERITY_CRITICAL ] )
				+ ( $warning * $weights[ Audit_Issue::SEVERITY_WARNING ] )
				+ ( $review * $weights[ Audit_Issue::SEVERITY_REVIEW ] )
				+ ( $information * $weights[ Audit_Issue::SEVERITY_INFORMATION ] );

			$score = (int) round( 100 - ( $deductions / $scanned_images ) );
			$score = max( 0, min( 100, $score ) );
		} else {
			// Nothing scanned yet: report a full score rather than a scary zero,
			// and let the caller show the "not scanned" state from scan_completion_percent.
			$score = 100;
		}

		$result = array(
			'score'                   => $score,
			'total_images'            => $total_images,
			'scanned_images'          => $scanned_images,
			'images_passing'          => $images_passing,
			'images_with_issues'      => $images_with_issues,
			'images_needing_attention' => $images_needing_attention,
			'critical_count'          => $critical,
			'warning_count'           => $warning,
			'review_count'            => $review,
			'information_count'       => $information,
			'settled_count'           => $settled,
			'scan_completion_percent' => $total_images > 0
				? max( 0, min( 100, (int) round( ( $scanned_images / $total_images ) * 100 ) ) )
				: 0,
			'formula_version'         => self::FORMULA_VERSION,
			'weights'                 => $weights,
		);

		/**
		 * Filter the calculated health score.
		 *
		 * Replace the whole result to swap in a different scoring model. Keep the
		 * key set intact — the dashboard reads these names directly.
		 *
		 * @since 4.8.0
		 *
		 * @param array<string,mixed> $result   Calculated score payload.
		 * @param array<string,int>   $counters Raw counters the score came from.
		 */
		return (array) apply_filters( 'bbai_audit_score', $result, $counters );
	}

	/**
	 * Coarse band for a score, for telemetry and badge colouring.
	 *
	 * @param int $score Score 0–100.
	 * @return string
	 */
	public static function band( $score ) {
		$score = max( 0, min( 100, (int) $score ) );

		if ( $score >= 90 ) {
			return '90-100';
		}
		if ( $score >= 75 ) {
			return '75-89';
		}
		if ( $score >= 50 ) {
			return '50-74';
		}
		if ( $score >= 25 ) {
			return '25-49';
		}

		return '0-24';
	}

	/**
	 * The disclaimer that must appear wherever the score is shown.
	 *
	 * @return string
	 */
	public static function disclaimer() {
		return __(
			'This score is an OpptiAI health indicator based on the checks available in this plugin. It is not a formal accessibility certification.',
			'beepbeep-ai-alt-text-generator'
		);
	}
}
