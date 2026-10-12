<?php
/**
 * Shared word lists for the audit rules.
 *
 * These lists are owned by BBAI_Alt_Quality_Scorer. This class is a thin,
 * memoised adapter so the audit rules and the generation-time scorer agree on
 * what counts as a placeholder or a generic word, rather than drifting apart.
 *
 * @package BeepBeep_AI
 * @since   4.8.0
 */

namespace BeepBeepAI\AltTextGenerator\Audit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BBAI_Alt_Quality_Scorer;

/**
 * Vocabulary accessor.
 */
class Audit_Vocabulary {

	/**
	 * Memoised lists, keyed by list name.
	 *
	 * @var array<string,array>
	 */
	private static $cache = array();

	/**
	 * Words that name the medium rather than the subject.
	 *
	 * @return string[]
	 */
	public static function generic_image_words() {
		return self::from_scorer( 'generic_image_words', 'get_generic_image_words' );
	}

	/**
	 * Placeholder and junk words.
	 *
	 * @return string[]
	 */
	public static function placeholder_words() {
		return self::from_scorer( 'placeholder_words', 'get_placeholder_words' );
	}

	/**
	 * Lead-in phrases that add nothing.
	 *
	 * @return string[]
	 */
	public static function redundant_prefixes() {
		return self::from_scorer( 'redundant_prefixes', 'get_redundant_prefixes' );
	}

	/**
	 * Function words.
	 *
	 * @return string[]
	 */
	public static function stop_words() {
		return self::from_scorer( 'stop_words', 'get_stop_words' );
	}

	/**
	 * Tokens that never count as meaningful, as a lookup map for O(1) checks.
	 *
	 * @return array<string,bool>
	 */
	public static function ignored_tokens() {
		if ( isset( self::$cache['ignored_tokens'] ) ) {
			return self::$cache['ignored_tokens'];
		}

		$map = array();

		foreach ( array_merge( self::stop_words(), self::generic_image_words(), self::placeholder_words() ) as $word ) {
			$normalised = Image_Audit_Context::normalise( $word );
			if ( '' !== $normalised ) {
				$map[ $normalised ] = true;
			}
		}

		self::$cache['ignored_tokens'] = $map;

		return $map;
	}

	/**
	 * Words that, on their own, describe nothing about a specific image.
	 *
	 * @return array<string,bool>
	 */
	public static function non_descriptive_tokens() {
		if ( isset( self::$cache['non_descriptive_tokens'] ) ) {
			return self::$cache['non_descriptive_tokens'];
		}

		$map = array();

		foreach ( array_merge( self::generic_image_words(), self::placeholder_words(), self::stop_words() ) as $word ) {
			$normalised = Image_Audit_Context::normalise( $word );
			if ( '' !== $normalised ) {
				$map[ $normalised ] = true;
			}
		}

		self::$cache['non_descriptive_tokens'] = $map;

		return $map;
	}

	/**
	 * Reset memoised lists. Test helper.
	 *
	 * @return void
	 */
	public static function reset_cache() {
		self::$cache = array();
	}

	/**
	 * Pull a list from the scorer, memoised.
	 *
	 * @param string $key    Cache key.
	 * @param string $method Scorer accessor name.
	 * @return string[]
	 */
	private static function from_scorer( $key, $method ) {
		if ( isset( self::$cache[ $key ] ) ) {
			return self::$cache[ $key ];
		}

		$list = array();

		if ( ! class_exists( 'BBAI_Alt_Quality_Scorer' ) && defined( 'BEEPBEEP_AI_PLUGIN_DIR' ) ) {
			$scorer_path = BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-alt-quality-scorer.php';
			if ( file_exists( $scorer_path ) ) {
				require_once $scorer_path;
			}
		}

		if ( class_exists( 'BBAI_Alt_Quality_Scorer' ) && method_exists( 'BBAI_Alt_Quality_Scorer', $method ) ) {
			$list = (array) BBAI_Alt_Quality_Scorer::$method();
		}

		self::$cache[ $key ] = $list;

		return $list;
	}
}
