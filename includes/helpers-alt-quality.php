<?php
/**
 * ALT text quality scoring helper.
 *
 * Used by the ALT Library table and coverage scan for consistent quality counts.
 *
 * @package BeepBeep_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'bbai_calculate_alt_quality_score' ) ) {
	/**
	 * Calculate alt text quality score (0-100).
	 *
	 * Delegates to the unified BBAI_Alt_Quality_Scorer engine.
	 *
	 * @param string $alt_text Alt text to analyze.
	 * @return int Quality score 0-100.
	 */
	function bbai_calculate_alt_quality_score( $alt_text ) {
		if ( empty( $alt_text ) || ! is_string( $alt_text ) ) {
			return 0;
		}

		if ( ! class_exists( 'BBAI_Alt_Quality_Scorer' ) ) {
			$scorer_path = defined( 'BEEPBEEP_AI_PLUGIN_DIR' )
				? BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-alt-quality-scorer.php'
				: dirname( __FILE__ ) . '/class-alt-quality-scorer.php';
			if ( file_exists( $scorer_path ) ) {
				require_once $scorer_path;
			}
		}

		if ( class_exists( 'BBAI_Alt_Quality_Scorer' ) ) {
			$result = BBAI_Alt_Quality_Scorer::score( $alt_text );
			return $result['score'];
		}

		// Fallback: should not happen.
		return 0;
	}
}

if ( ! function_exists( 'bbai_calculate_alt_quality' ) ) {
	/**
	 * Calculate full structured alt text quality result.
	 *
	 * @param string $alt_text Alt text to analyze.
	 * @param array  $context  Optional context: 'filename', 'title', 'caption', 'attachment_id'.
	 * @return array Full scoring result with score, label, breakdown, issues, suggestions.
	 */
	function bbai_calculate_alt_quality( $alt_text, $context = array() ) {
		if ( empty( $alt_text ) || ! is_string( $alt_text ) ) {
			return array(
				'score'       => 0,
				'label'       => 'Critical',
				'grade'       => 'F',
				'badge'       => 'needs-work',
				'breakdown'   => array(
					'descriptiveness' => 0,
					'relevance'       => 0,
					'accessibility'   => 0,
					'seo'             => 0,
					'conciseness'     => 0,
				),
				'issues'      => array( __( 'ALT text is missing.', 'beepbeep-ai-alt-text-generator' ) ),
				'suggestions' => array( __( 'Add a description that conveys the image content to screen-reader users.', 'beepbeep-ai-alt-text-generator' ) ),
				'hard_fail'   => true,
				'cap'         => 0,
			);
		}

		if ( ! class_exists( 'BBAI_Alt_Quality_Scorer' ) ) {
			$scorer_path = defined( 'BEEPBEEP_AI_PLUGIN_DIR' )
				? BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-alt-quality-scorer.php'
				: dirname( __FILE__ ) . '/class-alt-quality-scorer.php';
			if ( file_exists( $scorer_path ) ) {
				require_once $scorer_path;
			}
		}

		if ( class_exists( 'BBAI_Alt_Quality_Scorer' ) ) {
			return BBAI_Alt_Quality_Scorer::score( $alt_text, $context );
		}

		return array(
			'score'       => 0,
			'label'       => 'Critical',
			'grade'       => 'F',
			'badge'       => 'needs-work',
			'breakdown'   => array(
				'descriptiveness' => 0,
				'relevance'       => 0,
				'accessibility'   => 0,
				'seo'             => 0,
				'conciseness'     => 0,
			),
			'issues'      => array(),
			'suggestions' => array(),
			'hard_fail'   => false,
			'cap'         => null,
		);
	}
}
