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
	 * @param string $alt_text Alt text to analyze.
	 * @return int Quality score 0-100.
	 */
	function bbai_calculate_alt_quality_score( $alt_text ) {
		if ( empty( $alt_text ) || ! is_string( $alt_text ) ) {
			return 0;
		}

		$score       = 0;
		$word_count  = str_word_count( $alt_text );
		$char_count  = strlen( $alt_text );

		// Length score (optimal: 5-15 words, 50-150 chars)
		if ( $word_count >= 5 && $word_count <= 15 ) {
			$score += 30;
		} elseif ( $word_count >= 3 && $word_count <= 20 ) {
			$score += 20;
		} elseif ( $word_count > 0 ) {
			$score += 10;
		}

		if ( $char_count >= 50 && $char_count <= 150 ) {
			$score += 20;
		} elseif ( $char_count >= 30 && $char_count <= 200 ) {
			$score += 15;
		} elseif ( $char_count > 0 ) {
			$score += 5;
		}

		// Descriptiveness
		$descriptive_words = array( 'showing', 'depicting', 'displaying', 'featuring', 'containing', 'with', 'of', 'in', 'on', 'at' );
		$lower_alt          = strtolower( $alt_text );
		$descriptive_count  = 0;
		foreach ( $descriptive_words as $word ) {
			if ( strpos( $lower_alt, $word ) !== false ) {
				$descriptive_count++;
			}
		}
		$score += min( 20, $descriptive_count * 5 );

		// No generic words penalty
		$generic_words = array( 'image', 'picture', 'photo', 'graphic' );
		$has_generic   = false;
		foreach ( $generic_words as $word ) {
			if ( stripos( $alt_text, $word ) !== false && $word_count < 5 ) {
				$has_generic = true;
				break;
			}
		}
		if ( ! $has_generic ) {
			$score += 15;
		}

		// Redundant phrase penalty (e.g. "its a photo of", "a photo of", "an image of")
		$redundant_phrases = array(
			"it's a photo of", 'its a photo of', 'a photo of', 'an image of', 'a picture of',
			"it's an image of", 'its an image of', "it's a picture of", 'its a picture of',
		);
		$lower_alt = strtolower( $alt_text );
		foreach ( $redundant_phrases as $phrase ) {
			if ( str_starts_with( $lower_alt, $phrase ) ) {
				$score -= 40;
				break;
			}
		}

		// Specificity bonus
		if ( preg_match( '/\d+/', $alt_text ) ) {
			$score += 5;
		}
		if ( preg_match( '/[A-Z][a-z]+/', $alt_text ) ) {
			$score += 5;
		}

		// Penalty for very short or very long
		if ( $word_count < 3 ) {
			$score -= 35;
		}
		if ( $word_count > 25 ) {
			$score -= 10;
		}

		// Gibberish check: longest alphabetic word has no vowels — likely nonsensical
		$words_arr = preg_split( '/\s+/', trim( $alt_text ), -1, PREG_SPLIT_NO_EMPTY );
		$longest_alpha = '';
		foreach ( $words_arr as $w ) {
			$alpha = preg_replace( '/[^a-zA-Z]/', '', $w );
			if ( strlen( $alpha ) >= 3 && strlen( $alpha ) > strlen( $longest_alpha ) ) {
				$longest_alpha = $alpha;
			}
		}
		if ( strlen( $longest_alpha ) >= 3 && ! preg_match( '/[aeiou]/i', $longest_alpha ) ) {
			$score -= 40;
		}

		// Placeholder / non-descriptive check: test text, slang, or words that don't describe images
		$nondescriptive_words = array(
			'test', 'testing', 'tested', 'tests', 'asdf', 'qwerty', 'placeholder', 'sample',
			'example', 'demo', 'foo', 'bar', 'abc', 'xyz', 'temp', 'tmp', 'crap', 'stuff',
			'thing', 'things', 'something', 'anything', 'whatever', 'blah', 'meh', 'idk',
			'nada', 'random', 'garbage', 'junk', 'dummy', 'fake', 'lorem', 'ipsum',
		);
		$lower_words = array_map( 'strtolower', $words_arr );
		$bad_count = 0;
		foreach ( $nondescriptive_words as $bad ) {
			if ( in_array( $bad, $lower_words, true ) ) {
				$bad_count++;
			}
		}
		if ( $bad_count >= 1 && ( $bad_count >= 2 || count( $words_arr ) <= 4 ) ) {
			$score -= 50;
		}

		return max( 0, min( 100, $score ) );
	}
}
