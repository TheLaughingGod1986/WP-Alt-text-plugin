<?php
/**
 * Rule: alt text reads as a keyword list.
 *
 * @package BeepBeep_AI
 * @since   4.8.0
 */

namespace BeepBeepAI\AltTextGenerator\Audit\Rules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeepBeepAI\AltTextGenerator\Audit\Abstract_Audit_Rule;
use BeepBeepAI\AltTextGenerator\Audit\Audit_Issue;
use BeepBeepAI\AltTextGenerator\Audit\Image_Audit_Context;

/**
 * Fires on two conservative signals, either of which is enough:
 *
 *  1. a meaningful word repeats three or more times;
 *  2. the text is four or more comma/pipe separated fragments whose average
 *     length is three words or fewer — the shape of a keyword list.
 *
 * The issue is named "possible" because both signals have honest false
 * positives, and the finding asks for review rather than asserting a problem.
 */
class Keyword_Stuffing_Rule extends Abstract_Audit_Rule {

	/**
	 * Default thresholds.
	 *
	 * @return array<string,int|float>
	 */
	protected function default_thresholds() {
		return array(
			'min_tokens'              => 6,
			'max_repeats'             => 3,
			'min_fragments'           => 4,
			'max_mean_fragment_words' => 3.0,
		);
	}

	/**
	 * Issue code.
	 *
	 * @return string
	 */
	public function get_code() {
		return Audit_Issue::POSSIBLE_KEYWORD_STUFFING;
	}

	/**
	 * Only meaningful when there is alt text to inspect.
	 *
	 * @param Image_Audit_Context $context Image under audit.
	 * @return bool
	 */
	public function applies_to( Image_Audit_Context $context ) {
		return $context->has_alt_text();
	}

	/**
	 * True when either stuffing signal is present.
	 *
	 * @param Image_Audit_Context $context Image under audit.
	 * @return bool
	 */
	public function evaluate( Image_Audit_Context $context ) {
		$tokens = $context->get_alt_tokens();

		if ( count( $tokens ) < (int) $this->threshold( 'min_tokens', 6 ) ) {
			return false;
		}

		if ( $this->has_repeated_token( $context ) ) {
			return true;
		}

		return $this->looks_like_a_keyword_list( $context );
	}

	/**
	 * Whether any meaningful word appears at or above the repeat threshold.
	 *
	 * Stop words are excluded — "a man and a woman and a dog" is prose, not stuffing.
	 *
	 * @param Image_Audit_Context $context Image under audit.
	 * @return bool
	 */
	private function has_repeated_token( Image_Audit_Context $context ) {
		$meaningful = $context->get_meaningful_tokens();

		if ( empty( $meaningful ) ) {
			return false;
		}

		$counts      = array_count_values( $meaningful );
		$max_repeats = (int) $this->threshold( 'max_repeats', 3 );

		foreach ( $counts as $count ) {
			if ( $count >= $max_repeats ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the text is a run of short comma or pipe separated fragments.
	 *
	 * @param Image_Audit_Context $context Image under audit.
	 * @return bool
	 */
	private function looks_like_a_keyword_list( Image_Audit_Context $context ) {
		$raw       = trim( $context->get_alt() );
		$fragments = preg_split( '/[,|;]+/', $raw );

		if ( ! is_array( $fragments ) ) {
			return false;
		}

		$word_counts = array();

		foreach ( $fragments as $fragment ) {
			$normalised = Image_Audit_Context::normalise( $fragment );
			if ( '' === $normalised ) {
				continue;
			}
			$word_counts[] = count( explode( ' ', $normalised ) );
		}

		$fragment_count = count( $word_counts );

		if ( $fragment_count < (int) $this->threshold( 'min_fragments', 4 ) ) {
			return false;
		}

		$mean = array_sum( $word_counts ) / $fragment_count;

		return $mean <= (float) $this->threshold( 'max_mean_fragment_words', 3.0 );
	}
}
