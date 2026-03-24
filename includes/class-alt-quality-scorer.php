<?php
/**
 * Unified ALT text quality scoring engine.
 *
 * Single source of truth for scoring ALT text across the plugin.
 * Implements a three-layer architecture:
 *   Layer 1 — Hard-fail gate (deterministic, instant)
 *   Layer 2 — Weighted multi-factor scoring (deterministic)
 *   Layer 3 — Optional LLM review (async, cached)
 *
 * @package BeepBeep_AI
 * @since   5.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BBAI_Alt_Quality_Scorer {

	/* ─── Score labels ─── */

	const LABEL_EXCELLENT        = 'Excellent';
	const LABEL_GOOD             = 'Good';
	const LABEL_NEEDS_IMPROVEMENT = 'Needs improvement';
	const LABEL_POOR             = 'Poor';
	const LABEL_CRITICAL         = 'Critical';

	/* ─── Hard-fail score caps ─── */

	const CAP_EMPTY              = 0;
	const CAP_PLACEHOLDER        = 5;
	const CAP_GIBBERISH          = 10;
	const CAP_GENERIC_ONLY       = 10;
	const CAP_SINGLE_WORD        = 15;
	const CAP_TOO_FEW_WORDS      = 20;
	const CAP_NONSENSE           = 15;

	/* ─── Known word lists ─── */

	private static $placeholder_words = array(
		'test', 'testing', 'tested', 'tests', 'asdf', 'qwerty', 'placeholder',
		'sample', 'example', 'demo', 'foo', 'bar', 'abc', 'xyz', 'temp', 'tmp',
		'crap', 'stuff', 'thing', 'things', 'something', 'anything', 'whatever',
		'blah', 'meh', 'idk', 'nada', 'random', 'garbage', 'junk', 'dummy',
		'fake', 'lorem', 'ipsum', 'untitled', 'none', 'null', 'n/a', 'na',
		'todo', 'fixme', 'xxx', 'yyy', 'zzz', 'aaa', 'bbb',
	);

	private static $generic_image_words = array(
		'image', 'picture', 'photo', 'photograph', 'graphic', 'icon',
		'screenshot', 'img', 'pic', 'thumbnail', 'banner', 'logo',
	);

	private static $redundant_prefixes = array(
		"it's a photo of", 'its a photo of', 'a photo of', 'an image of',
		'a picture of', "it's an image of", 'its an image of',
		"it's a picture of", 'its a picture of', 'image of', 'picture of',
		'photo of', 'photograph of', 'graphic of', 'illustration of',
		'image showing', 'picture showing', 'photo showing',
	);

	private static $filename_patterns = array(
		'/^IMG[-_]\d+/i',
		'/^DSC[-_]\d+/i',
		'/^\d{8}[-_]\d+/i',
		'/^screenshot[-_]/i',
		'/^image[-_]\d+/i',
		'/\.(jpg|jpeg|png|gif|webp|svg|bmp|tiff?)$/i',
	);

	/* ─── Public API ─── */

	/**
	 * Score ALT text and return a structured result.
	 *
	 * @param string $alt_text      The ALT text to evaluate.
	 * @param array  $context       Optional context: 'filename', 'title', 'caption', 'attachment_id'.
	 * @return array {
	 *     @type int    $score       0–100 final score.
	 *     @type string $label       Human-readable label (Excellent … Critical).
	 *     @type string $grade       Letter grade A–F.
	 *     @type string $badge       CSS badge key.
	 *     @type array  $breakdown   Weighted sub-scores (each 0–100).
	 *     @type array  $issues      User-facing issue strings.
	 *     @type array  $suggestions Actionable improvement suggestions.
	 *     @type bool   $hard_fail   Whether a hard-fail rule triggered.
	 *     @type int    $cap         Hard-fail cap applied (null if none).
	 * }
	 */
	public static function score( $alt_text, $context = array() ) {
		$alt_text = is_string( $alt_text ) ? trim( $alt_text ) : '';

		if ( $alt_text === '' ) {
			return self::build_result( 0, array(), array(
				__( 'ALT text is missing.', 'beepbeep-ai-alt-text-generator' ),
			), array(
				__( 'Add a description that conveys the image content to screen-reader users.', 'beepbeep-ai-alt-text-generator' ),
			), true, self::CAP_EMPTY );
		}

		$issues      = array();
		$suggestions = array();

		/* ──────────────────────────────────────────────
		 * Layer 1 — Hard-fail gate
		 * ────────────────────────────────────────────── */
		$hard_fail = self::check_hard_fails( $alt_text, $context, $issues, $suggestions );

		if ( $hard_fail !== false ) {
			// Build minimal breakdown — everything is bad.
			$breakdown = self::zero_breakdown();
			return self::build_result( $hard_fail, $breakdown, $issues, $suggestions, true, $hard_fail );
		}

		/* ──────────────────────────────────────────────
		 * Layer 2 — Weighted multi-factor scoring
		 * ────────────────────────────────────────────── */
		$breakdown = self::calculate_breakdown( $alt_text, $context, $issues, $suggestions );

		$weighted_score = (int) round(
			$breakdown['descriptiveness'] * 0.30
			+ $breakdown['relevance']     * 0.25
			+ $breakdown['accessibility'] * 0.20
			+ $breakdown['seo']           * 0.15
			+ $breakdown['conciseness']   * 0.10
		);

		$score = max( 0, min( 100, $weighted_score ) );

		return self::build_result( $score, $breakdown, $issues, $suggestions, false, null );
	}

	/**
	 * Quick boolean: is this ALT text acceptable (score >= 70)?
	 *
	 * @param string $alt_text ALT text.
	 * @return bool
	 */
	public static function is_acceptable( $alt_text ) {
		$result = self::score( $alt_text );
		return $result['score'] >= 70;
	}

	/**
	 * Build the system prompt for LLM-based review.
	 *
	 * This enforces the same rubric the deterministic scorer uses,
	 * so the LLM cannot inflate scores beyond what the rules allow.
	 *
	 * @return string
	 */
	public static function get_review_system_prompt() {
		return <<<'PROMPT'
You are an expert in web accessibility (WCAG), technical SEO, and AI content evaluation.

Score the provided ALT text out of 100 using these weighted criteria:

1. Descriptiveness (30%) — Does it clearly describe the image subject, action, or content?
2. Relevance (25%) — Does it match the image context? Avoid hallucination.
3. Accessibility clarity (20%) — Would a screen reader user understand the image?
4. SEO usefulness (15%) — Meaningful natural keywords without stuffing?
5. Conciseness (10%) — Brief but informative? Ideal: 5–20 words.

HARD-FAIL RULES — if ANY are true, cap the score at 20 maximum:
- Fewer than 3 meaningful words
- Generic text only (e.g. "image", "photo", "picture")
- Clearly unrelated to the image
- Nonsense or random words (e.g. "jojo", "asdf")
- Obvious placeholder text (e.g. "test", "sample")
- Single word that does not describe an image

Do NOT inflate scores. Prioritise accessibility over SEO.

Return ONLY valid JSON, no other text:
{
  "score": <0-100>,
  "label": "<Excellent|Good|Needs improvement|Poor|Critical>",
  "breakdown": {
    "descriptiveness": <0-100>,
    "relevance": <0-100>,
    "accessibility": <0-100>,
    "seo": <0-100>,
    "conciseness": <0-100>
  },
  "issues": ["<issue>"],
  "suggestions": ["<suggestion>"]
}
PROMPT;
	}

	/**
	 * Build the user prompt for LLM review of a specific ALT text.
	 *
	 * @param string $alt_text      ALT text to review.
	 * @param array  $image_context Optional context (filename, title, page).
	 * @return string
	 */
	public static function get_review_user_prompt( $alt_text, $image_context = array() ) {
		$context_str = '';
		if ( ! empty( $image_context ) ) {
			$parts = array();
			if ( ! empty( $image_context['filename'] ) ) {
				$parts[] = 'Filename: ' . $image_context['filename'];
			}
			if ( ! empty( $image_context['title'] ) ) {
				$parts[] = 'Title: ' . $image_context['title'];
			}
			if ( ! empty( $image_context['caption'] ) ) {
				$parts[] = 'Caption: ' . $image_context['caption'];
			}
			if ( $parts ) {
				$context_str = "\n\nImage context:\n" . implode( "\n", $parts );
			}
		}

		return sprintf( 'ALT text: "%s"%s', $alt_text, $context_str );
	}

	/* ──────────────────────────────────────────────
	 * Layer 1 — Hard-fail checks
	 * ────────────────────────────────────────────── */

	/**
	 * Check for hard-fail conditions.
	 *
	 * @param string $alt_text    ALT text.
	 * @param array  $context     Context array.
	 * @param array  &$issues     Populated with issues.
	 * @param array  &$suggestions Populated with suggestions.
	 * @return int|false Score cap if hard-fail triggered, false otherwise.
	 */
	private static function check_hard_fails( $alt_text, $context, &$issues, &$suggestions ) {
		$lower   = strtolower( $alt_text );
		$words   = preg_split( '/\s+/', trim( $alt_text ), -1, PREG_SPLIT_NO_EMPTY );
		$lc_words = array_map( 'strtolower', $words );
		$word_count = count( $words );

		// ── Placeholder / exact-match nonsense ──
		$placeholder_exact = '/^(test|testing|sample|example|dummy|placeholder|alt(\s+text)?|image|photo|picture|n\/a|none|lorem|todo|fixme)$/i';
		if ( preg_match( $placeholder_exact, trim( $lower ) ) ) {
			$issues[]      = __( 'ALT text is placeholder content and must be rewritten.', 'beepbeep-ai-alt-text-generator' );
			$suggestions[] = __( 'Describe the main subject of the image clearly.', 'beepbeep-ai-alt-text-generator' );
			return self::CAP_PLACEHOLDER;
		}

		// ── Single word ──
		if ( $word_count === 1 ) {
			// Allow only if it's a recognized proper noun / brand — but we can't know that here.
			// A single word is never adequate ALT text.
			$issues[]      = __( 'ALT text is a single word — too brief to be meaningful.', 'beepbeep-ai-alt-text-generator' );
			$suggestions[] = __( 'Describe what the image shows using at least a short phrase (e.g. "Golden retriever playing in park").', 'beepbeep-ai-alt-text-generator' );

			// Check if it's also a placeholder word.
			if ( in_array( $lc_words[0], self::$placeholder_words, true ) ) {
				$issues[]      = __( 'The word does not describe an image.', 'beepbeep-ai-alt-text-generator' );
				return self::CAP_PLACEHOLDER;
			}
			// Check if it's just a generic image word.
			if ( in_array( $lc_words[0], self::$generic_image_words, true ) ) {
				$issues[]      = __( 'A generic word like "image" or "photo" tells the user nothing about the content.', 'beepbeep-ai-alt-text-generator' );
				return self::CAP_GENERIC_ONLY;
			}

			return self::CAP_SINGLE_WORD;
		}

		// ── Two words, both non-descriptive ──
		if ( $word_count === 2 ) {
			$all_non = true;
			foreach ( $lc_words as $w ) {
				$is_placeholder = in_array( $w, self::$placeholder_words, true );
				$is_generic     = in_array( $w, self::$generic_image_words, true );
				$is_stopword    = in_array( $w, array( 'a', 'an', 'the', 'my', 'our', 'this', 'that', 'is', 'of' ), true );
				if ( ! $is_placeholder && ! $is_generic && ! $is_stopword ) {
					$all_non = false;
					break;
				}
			}
			if ( $all_non ) {
				$issues[]      = __( 'ALT text contains only generic or filler words.', 'beepbeep-ai-alt-text-generator' );
				$suggestions[] = __( 'Replace with a descriptive phrase that conveys the image content.', 'beepbeep-ai-alt-text-generator' );
				return self::CAP_GENERIC_ONLY;
			}
		}

		// ── Fewer than 3 meaningful words ──
		$meaningful_count = self::count_meaningful_words( $lc_words );
		if ( $meaningful_count < 2 ) {
			$issues[]      = __( 'ALT text lacks enough meaningful words to describe the image.', 'beepbeep-ai-alt-text-generator' );
			$suggestions[] = __( 'Add descriptive nouns and adjectives (e.g. "Red bicycle leaning against brick wall").', 'beepbeep-ai-alt-text-generator' );
			return self::CAP_TOO_FEW_WORDS;
		}

		// ── Gibberish: no vowels in longest alphabetic word ──
		$longest_alpha = '';
		foreach ( $words as $w ) {
			$alpha = preg_replace( '/[^a-zA-Z]/', '', $w );
			if ( strlen( $alpha ) >= 3 && strlen( $alpha ) > strlen( $longest_alpha ) ) {
				$longest_alpha = $alpha;
			}
		}
		if ( strlen( $longest_alpha ) >= 3 && ! preg_match( '/[aeiou]/i', $longest_alpha ) ) {
			$issues[]      = __( 'ALT text appears to contain gibberish or nonsensical text.', 'beepbeep-ai-alt-text-generator' );
			$suggestions[] = __( 'Replace with a real description of what the image depicts.', 'beepbeep-ai-alt-text-generator' );
			return self::CAP_GIBBERISH;
		}

		// ── Majority placeholder words in short text ──
		$placeholder_count = 0;
		foreach ( $lc_words as $w ) {
			if ( in_array( $w, self::$placeholder_words, true ) ) {
				$placeholder_count++;
			}
		}
		if ( $placeholder_count > 0 && ( $placeholder_count >= 2 || ( $word_count <= 4 && $placeholder_count >= 1 ) ) ) {
			$issues[]      = __( 'ALT text contains placeholder or non-descriptive wording.', 'beepbeep-ai-alt-text-generator' );
			$suggestions[] = __( 'Rewrite with specific details about the image subject.', 'beepbeep-ai-alt-text-generator' );
			return self::CAP_NONSENSE;
		}

		// ── Filename as ALT text ──
		foreach ( self::$filename_patterns as $pattern ) {
			if ( preg_match( $pattern, trim( $alt_text ) ) ) {
				$issues[]      = __( 'ALT text looks like a filename, not a description.', 'beepbeep-ai-alt-text-generator' );
				$suggestions[] = __( 'Describe the image content instead of using the file name.', 'beepbeep-ai-alt-text-generator' );
				return self::CAP_TOO_FEW_WORDS;
			}
		}

		// ── Matches the attachment filename exactly ──
		if ( ! empty( $context['filename'] ) ) {
			$normalized_alt  = self::normalize_for_comparison( $alt_text );
			$normalized_file = self::normalize_for_comparison( pathinfo( $context['filename'], PATHINFO_FILENAME ) );
			if ( $normalized_file !== '' && $normalized_alt === $normalized_file ) {
				$issues[]      = __( 'ALT text is identical to the filename — rewrite it to describe the image.', 'beepbeep-ai-alt-text-generator' );
				$suggestions[] = __( 'Write what the image actually shows instead of repeating the file name.', 'beepbeep-ai-alt-text-generator' );
				return self::CAP_TOO_FEW_WORDS;
			}
		}

		return false; // No hard-fail.
	}

	/* ──────────────────────────────────────────────
	 * Layer 2 — Weighted breakdown scoring
	 * ────────────────────────────────────────────── */

	/**
	 * Calculate per-dimension scores (each 0–100).
	 *
	 * @param string $alt_text    ALT text.
	 * @param array  $context     Context.
	 * @param array  &$issues     Populated with issues.
	 * @param array  &$suggestions Populated with suggestions.
	 * @return array Breakdown with keys: descriptiveness, relevance, accessibility, seo, conciseness.
	 */
	private static function calculate_breakdown( $alt_text, $context, &$issues, &$suggestions ) {
		$lower      = strtolower( $alt_text );
		$words      = preg_split( '/\s+/', trim( $alt_text ), -1, PREG_SPLIT_NO_EMPTY );
		$lc_words   = array_map( 'strtolower', $words );
		$word_count = count( $words );
		$char_count = function_exists( 'mb_strlen' ) ? mb_strlen( $alt_text ) : strlen( $alt_text );

		/* ── 1. Descriptiveness (0–100) ── */
		$desc = 50; // Base.

		// Word count rewards.
		if ( $word_count >= 5 && $word_count <= 20 ) {
			$desc += 25;
		} elseif ( $word_count >= 3 ) {
			$desc += 10;
		} else {
			$desc -= 30;
		}

		// Descriptive markers: verbs/prepositions that indicate a real description.
		$descriptive_markers = array( 'showing', 'depicting', 'displaying', 'featuring', 'wearing',
			'holding', 'standing', 'sitting', 'walking', 'running', 'looking', 'smiling',
			'pointing', 'flying', 'floating', 'hanging', 'lying', 'resting' );
		$has_action = false;
		foreach ( $descriptive_markers as $marker ) {
			if ( strpos( $lower, $marker ) !== false ) {
				$has_action = true;
				break;
			}
		}
		if ( $has_action ) {
			$desc += 15;
		}

		// Has at least one word >= 5 chars (likely a real noun or adjective).
		$has_substantial = false;
		foreach ( $words as $w ) {
			if ( strlen( preg_replace( '/[^a-zA-Z]/', '', $w ) ) >= 5 ) {
				$has_substantial = true;
				break;
			}
		}
		if ( $has_substantial ) {
			$desc += 10;
		} else {
			$desc -= 15;
			$issues[]      = __( 'Lacks descriptive language — include meaningful nouns or adjectives.', 'beepbeep-ai-alt-text-generator' );
			$suggestions[] = __( 'Use specific words like "sunset", "laptop", "conference room" instead of vague terms.', 'beepbeep-ai-alt-text-generator' );
		}

		$desc = max( 0, min( 100, $desc ) );

		/* ── 2. Relevance (0–100) ── */
		$rel = 70; // Assume relevant unless signals say otherwise.

		// Penalise generic image words.
		$generic_count = 0;
		foreach ( $lc_words as $w ) {
			if ( in_array( $w, self::$generic_image_words, true ) ) {
				$generic_count++;
			}
		}
		if ( $generic_count > 0 && $word_count < 5 ) {
			$rel -= 20;
			$issues[] = __( 'Contains generic filler words like "image" or "photo" — describe the subject directly.', 'beepbeep-ai-alt-text-generator' );
		}

		// Redundant prefix.
		foreach ( self::$redundant_prefixes as $prefix ) {
			if ( str_starts_with( $lower, $prefix ) ) {
				$rel -= 25;
				$issues[]      = __( 'Starts with a redundant phrase like "photo of" — describe the subject directly.', 'beepbeep-ai-alt-text-generator' );
				$suggestions[] = __( 'Remove the "image of" / "photo of" prefix and start with the subject.', 'beepbeep-ai-alt-text-generator' );
				break;
			}
		}

		// Context matching — bonus if alt references context words.
		if ( ! empty( $context['title'] ) ) {
			$title_norm = self::normalize_for_comparison( $context['title'] );
			$alt_norm   = self::normalize_for_comparison( $alt_text );
			if ( $title_norm !== '' && $alt_norm === $title_norm ) {
				$rel -= 10;
				$issues[] = __( 'Identical to the attachment title — add more unique detail.', 'beepbeep-ai-alt-text-generator' );
			}
		}

		$rel = max( 0, min( 100, $rel ) );

		/* ── 3. Accessibility clarity (0–100) ── */
		$acc = 70; // Base.

		// Ideal character range for screen readers: 30–160.
		if ( $char_count >= 30 && $char_count <= 160 ) {
			$acc += 20;
		} elseif ( $char_count < 30 ) {
			$acc -= 15;
			$issues[]      = __( 'Too short for a screen reader to convey useful information.', 'beepbeep-ai-alt-text-generator' );
			$suggestions[] = __( 'Expand the description to at least 30 characters with concrete details.', 'beepbeep-ai-alt-text-generator' );
		} elseif ( $char_count > 160 ) {
			$acc -= 10;
			$issues[] = __( 'Very long — trim to keep it concise for screen-reader users (under 160 characters).', 'beepbeep-ai-alt-text-generator' );
		}

		// Starts with capital letter — proper sentence structure.
		if ( preg_match( '/^[A-Z]/', $alt_text ) ) {
			$acc += 5;
		}

		// No special characters that screen readers struggle with.
		if ( preg_match( '/[<>{}|\\\\]/', $alt_text ) ) {
			$acc -= 10;
			$issues[] = __( 'Contains special characters that may confuse screen readers.', 'beepbeep-ai-alt-text-generator' );
		}

		$acc = max( 0, min( 100, $acc ) );

		/* ── 4. SEO usefulness (0–100) ── */
		$seo = 60; // Base.

		// Ideal length for Google Images: ≤125 chars.
		if ( $char_count > 0 && $char_count <= 125 ) {
			$seo += 20;
		} elseif ( $char_count > 125 ) {
			$seo -= 15;
			$issues[] = sprintf(
				/* translators: %d: character count */
				__( 'At %d characters, this exceeds the 125-char SEO sweet spot for Google Images.', 'beepbeep-ai-alt-text-generator' ),
				$char_count
			);
		}

		// Has a proper noun / capitalised word (likely a topic keyword).
		if ( preg_match( '/\b[A-Z][a-z]{2,}/', $alt_text ) ) {
			$seo += 10;
		}

		// Contains a number (specificity signal).
		if ( preg_match( '/\d+/', $alt_text ) ) {
			$seo += 5;
		}

		// Keyword stuffing detection: same word repeated 3+ times.
		$freq = array_count_values( $lc_words );
		$max_freq = max( $freq );
		if ( $max_freq >= 3 && $word_count > 3 ) {
			$seo -= 30;
			$issues[]      = __( 'Possible keyword stuffing — a word is repeated too many times.', 'beepbeep-ai-alt-text-generator' );
			$suggestions[] = __( 'Use natural language instead of repeating the same keyword.', 'beepbeep-ai-alt-text-generator' );
		}

		$seo = max( 0, min( 100, $seo ) );

		/* ── 5. Conciseness (0–100) ── */
		$conc = 50; // Base.

		if ( $word_count >= 5 && $word_count <= 15 ) {
			$conc += 40; // Sweet spot.
		} elseif ( $word_count >= 3 && $word_count <= 20 ) {
			$conc += 25;
		} elseif ( $word_count > 25 ) {
			$conc -= 20;
			$issues[]      = __( 'ALT text is unusually long — consider trimming to the essential details.', 'beepbeep-ai-alt-text-generator' );
			$suggestions[] = __( 'Keep ALT text under 20 words for optimal readability.', 'beepbeep-ai-alt-text-generator' );
		} else {
			$conc -= 10;
		}

		if ( $char_count >= 50 && $char_count <= 150 ) {
			$conc += 10;
		}

		$conc = max( 0, min( 100, $conc ) );

		return array(
			'descriptiveness' => $desc,
			'relevance'       => $rel,
			'accessibility'   => $acc,
			'seo'             => $seo,
			'conciseness'     => $conc,
		);
	}

	/* ──────────────────────────────────────────────
	 * Result builders & helpers
	 * ────────────────────────────────────────────── */

	/**
	 * Build a standardised result array.
	 */
	private static function build_result( $score, $breakdown, $issues, $suggestions, $hard_fail, $cap ) {
		$score = max( 0, min( 100, (int) $score ) );

		// Ensure at least one suggestion if score is below 70.
		if ( $score < 70 && empty( $suggestions ) ) {
			$suggestions[] = __( 'Describe the main subject of the image clearly and specifically.', 'beepbeep-ai-alt-text-generator' );
		}

		// Deduplicate.
		$issues      = array_values( array_unique( array_filter( $issues ) ) );
		$suggestions = array_values( array_unique( array_filter( $suggestions ) ) );

		return array(
			'score'       => $score,
			'label'       => self::label_from_score( $score ),
			'grade'       => self::grade_from_score( $score ),
			'badge'       => self::badge_from_score( $score ),
			'breakdown'   => $breakdown ?: self::zero_breakdown(),
			'issues'      => $issues,
			'suggestions' => $suggestions,
			'hard_fail'   => (bool) $hard_fail,
			'cap'         => $cap,
		);
	}

	/**
	 * Zero breakdown for hard-fail cases.
	 */
	private static function zero_breakdown() {
		return array(
			'descriptiveness' => 0,
			'relevance'       => 0,
			'accessibility'   => 0,
			'seo'             => 0,
			'conciseness'     => 0,
		);
	}

	/**
	 * Label from score.
	 */
	public static function label_from_score( $score ) {
		if ( $score >= 90 ) return self::LABEL_EXCELLENT;
		if ( $score >= 70 ) return self::LABEL_GOOD;
		if ( $score >= 50 ) return self::LABEL_NEEDS_IMPROVEMENT;
		if ( $score >= 20 ) return self::LABEL_POOR;
		return self::LABEL_CRITICAL;
	}

	/**
	 * Letter grade from score.
	 */
	public static function grade_from_score( $score ) {
		if ( $score >= 90 ) return 'A';
		if ( $score >= 70 ) return 'B';
		if ( $score >= 50 ) return 'C';
		if ( $score >= 30 ) return 'D';
		return 'F';
	}

	/**
	 * Badge CSS key from score.
	 */
	public static function badge_from_score( $score ) {
		if ( $score >= 90 ) return 'excellent';
		if ( $score >= 70 ) return 'good';
		if ( $score >= 50 ) return 'fair';
		if ( $score >= 30 ) return 'poor';
		return 'needs-work';
	}

	/**
	 * Count meaningful words (excluding stop words, generic words, articles).
	 */
	private static function count_meaningful_words( $lc_words ) {
		$stop_words = array(
			'a', 'an', 'the', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
			'of', 'in', 'on', 'at', 'to', 'for', 'with', 'and', 'or', 'but',
			'my', 'our', 'your', 'his', 'her', 'its', 'their', 'this', 'that',
			'it', 'he', 'she', 'we', 'they', 'i', 'me', 'him', 'us', 'them',
		);
		$count = 0;
		foreach ( $lc_words as $w ) {
			if ( strlen( $w ) < 2 ) continue;
			if ( in_array( $w, $stop_words, true ) ) continue;
			if ( in_array( $w, self::$generic_image_words, true ) ) continue;
			$count++;
		}
		return $count;
	}

	/**
	 * Normalise a string for comparison (lowercase, strip non-alnum, collapse whitespace).
	 */
	private static function normalize_for_comparison( $value ) {
		$value = strtolower( (string) $value );
		$value = preg_replace( '/[^a-z0-9]+/', ' ', $value );
		return trim( preg_replace( '/\s+/', ' ', $value ) );
	}
}
