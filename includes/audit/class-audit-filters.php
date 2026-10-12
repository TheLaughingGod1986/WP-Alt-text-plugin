<?php
/**
 * The filters offered above the audit issues table.
 *
 * Each filter is defined once here — label, matching rule and ordering — so the
 * table, the counts and the URL handling cannot drift apart.
 *
 * @package BeepBeep_AI
 * @since   4.8.0
 */

namespace BeepBeepAI\AltTextGenerator\Audit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Audit issue filters.
 */
class Audit_Filters {

	const ALL             = 'all';
	const MISSING_ALT     = 'missing_alt';
	const WEAK_ALT        = 'weak_alt';
	const DUPLICATE_ALT   = 'duplicate_alt';
	const REVIEW_REQUIRED = 'review_required';
	const CRITICAL        = 'critical';
	const WARNING         = 'warning';
	const INFORMATION     = 'information';
	const RESOLVED        = 'resolved';

	/**
	 * Issue codes that mean "there is alt text, but it reads poorly".
	 *
	 * @return string[]
	 */
	public static function weak_alt_codes() {
		return array(
			Audit_Issue::FILENAME_AS_ALT,
			Audit_Issue::GENERIC_ALT,
			Audit_Issue::ALT_TOO_SHORT,
			Audit_Issue::POSSIBLE_KEYWORD_STUFFING,
		);
	}

	/**
	 * Issue codes whose definition says a person must decide.
	 *
	 * Derived from the catalogue rather than hard-coded, so adding a
	 * review-required rule automatically shows up under this filter.
	 *
	 * @return string[]
	 */
	public static function review_required_codes() {
		$codes = array();

		foreach ( Audit_Issue::definitions() as $code => $definition ) {
			if ( ! empty( $definition['requires_review'] ) ) {
				$codes[] = $code;
			}
		}

		return $codes;
	}

	/**
	 * Every filter, in display order.
	 *
	 * @return array<string,array{label:string,description:string}>
	 */
	public static function all() {
		$filters = array(
			self::ALL             => array(
				'label'       => __( 'Needs attention', 'beepbeep-ai-alt-text-generator' ),
				'description' => __( 'Images with something you can act on. Notes that need no action are under Information.', 'beepbeep-ai-alt-text-generator' ),
			),
			self::MISSING_ALT     => array(
				'label'       => __( 'Missing alt', 'beepbeep-ai-alt-text-generator' ),
				'description' => __( 'Images with no alt text stored at all.', 'beepbeep-ai-alt-text-generator' ),
			),
			self::WEAK_ALT        => array(
				'label'       => __( 'Weak alt', 'beepbeep-ai-alt-text-generator' ),
				'description' => __( 'Alt text that repeats the file name, stays generic, is very short, or reads as a keyword list.', 'beepbeep-ai-alt-text-generator' ),
			),
			self::DUPLICATE_ALT   => array(
				'label'       => __( 'Duplicate alt', 'beepbeep-ai-alt-text-generator' ),
				'description' => __( 'The same alt text used on more than one image.', 'beepbeep-ai-alt-text-generator' ),
			),
			self::REVIEW_REQUIRED => array(
				'label'       => __( 'Review required', 'beepbeep-ai-alt-text-generator' ),
				'description' => __( 'Findings this plugin cannot settle on its own — including empty alt text, which is correct for decorative images.', 'beepbeep-ai-alt-text-generator' ),
			),
			self::CRITICAL        => array(
				'label'       => __( 'Critical', 'beepbeep-ai-alt-text-generator' ),
				'description' => __( 'The most serious findings.', 'beepbeep-ai-alt-text-generator' ),
			),
			self::WARNING         => array(
				'label'       => __( 'Warning', 'beepbeep-ai-alt-text-generator' ),
				'description' => __( 'Findings worth fixing once the critical ones are done.', 'beepbeep-ai-alt-text-generator' ),
			),
			self::INFORMATION     => array(
				'label'       => __( 'Information', 'beepbeep-ai-alt-text-generator' ),
				'description' => __( 'Notes rather than problems — for example images not attached to any post. Generating alt text will not clear these.', 'beepbeep-ai-alt-text-generator' ),
			),
			self::RESOLVED        => array(
				'label'       => __( 'Resolved', 'beepbeep-ai-alt-text-generator' ),
				'description' => __( 'Images with nothing outstanding, including ones marked decorative.', 'beepbeep-ai-alt-text-generator' ),
			),
		);

		/**
		 * Filter the audit issue filters.
		 *
		 * @since 4.8.0
		 *
		 * @param array $filters Filter definitions keyed by slug.
		 */
		return (array) apply_filters( 'bbai_audit_filters', $filters );
	}

	/**
	 * Filter slugs.
	 *
	 * @return string[]
	 */
	public static function slugs() {
		return array_keys( self::all() );
	}

	/**
	 * Whether a slug is a filter this plugin understands.
	 *
	 * @param string $slug Filter slug.
	 * @return bool
	 */
	public static function is_valid( $slug ) {
		return is_string( $slug ) && in_array( $slug, self::slugs(), true );
	}

	/**
	 * Coerce untrusted input to a known filter.
	 *
	 * @param mixed $slug Raw input.
	 * @return string
	 */
	public static function sanitize( $slug ) {
		$slug = is_string( $slug ) ? sanitize_key( $slug ) : '';

		return self::is_valid( $slug ) ? $slug : self::ALL;
	}

	/**
	 * Human label for a filter.
	 *
	 * @param string $slug Filter slug.
	 * @return string
	 */
	public static function label( $slug ) {
		$filters = self::all();

		return isset( $filters[ $slug ]['label'] ) ? (string) $filters[ $slug ]['label'] : (string) $slug;
	}

	/**
	 * Description shown under the active filter.
	 *
	 * @param string $slug Filter slug.
	 * @return string
	 */
	public static function description( $slug ) {
		$filters = self::all();

		return isset( $filters[ $slug ]['description'] ) ? (string) $filters[ $slug ]['description'] : '';
	}
}
