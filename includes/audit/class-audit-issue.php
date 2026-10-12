<?php
/**
 * Image audit issue catalogue.
 *
 * Single source of truth for issue codes, severities, and the human-facing
 * copy shown next to each finding. Rules decide *whether* an issue applies;
 * this class describes *what the issue means*.
 *
 * @package BeepBeep_AI
 * @since   4.8.0
 */

namespace BeepBeepAI\AltTextGenerator\Audit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Issue code registry and severity helpers.
 */
class Audit_Issue {

	/* ─── Issue codes ─── */

	const MISSING_ALT               = 'missing_alt';
	const EMPTY_ALT_REVIEW_REQUIRED = 'empty_alt_review_required';
	const FILENAME_AS_ALT           = 'filename_as_alt';
	const GENERIC_ALT               = 'generic_alt';
	const DUPLICATE_ALT             = 'duplicate_alt';
	const ALT_TOO_SHORT             = 'alt_too_short';
	const ALT_TOO_LONG              = 'alt_too_long';
	const POSSIBLE_KEYWORD_STUFFING = 'possible_keyword_stuffing';
	const ORPHANED_ATTACHMENT       = 'orphaned_attachment';
	const NO_USAGE_CONTEXT          = 'no_usage_context';

	/* ─── Severities, weakest first ─── */

	const SEVERITY_INFORMATION = 'information';
	const SEVERITY_REVIEW      = 'review';
	const SEVERITY_WARNING     = 'warning';
	const SEVERITY_CRITICAL    = 'critical';

	/**
	 * Severity rank. Higher wins when a row carries several issues.
	 *
	 * @var array<string,int>
	 */
	private static $severity_rank = array(
		self::SEVERITY_INFORMATION => 1,
		self::SEVERITY_REVIEW      => 2,
		self::SEVERITY_WARNING     => 3,
		self::SEVERITY_CRITICAL    => 4,
	);

	/**
	 * Every known issue code, in display order.
	 *
	 * @return string[]
	 */
	public static function all_codes() {
		return array(
			self::MISSING_ALT,
			self::EMPTY_ALT_REVIEW_REQUIRED,
			self::FILENAME_AS_ALT,
			self::GENERIC_ALT,
			self::DUPLICATE_ALT,
			self::ALT_TOO_SHORT,
			self::ALT_TOO_LONG,
			self::POSSIBLE_KEYWORD_STUFFING,
			self::ORPHANED_ATTACHMENT,
			self::NO_USAGE_CONTEXT,
		);
	}

	/**
	 * All severities, weakest first.
	 *
	 * @return string[]
	 */
	public static function all_severities() {
		return array_keys( self::$severity_rank );
	}

	/**
	 * Whether a string is a severity this plugin understands.
	 *
	 * @param string $severity Severity slug.
	 * @return bool
	 */
	public static function is_valid_severity( $severity ) {
		return is_string( $severity ) && isset( self::$severity_rank[ $severity ] );
	}

	/**
	 * Numeric rank for a severity. Unknown severities rank lowest.
	 *
	 * @param string $severity Severity slug.
	 * @return int
	 */
	public static function severity_rank( $severity ) {
		if ( ! is_string( $severity ) || ! isset( self::$severity_rank[ $severity ] ) ) {
			return 0;
		}

		return self::$severity_rank[ $severity ];
	}

	/**
	 * The most serious severity across a set of issue codes.
	 *
	 * A row with no issues has severity "information" — it is reported, not alarming.
	 *
	 * @param string[] $codes Issue codes.
	 * @return string
	 */
	public static function worst_severity( array $codes ) {
		$worst = self::SEVERITY_INFORMATION;

		foreach ( $codes as $code ) {
			$definition = self::definition( $code );
			if ( null === $definition ) {
				continue;
			}

			if ( self::severity_rank( $definition['severity'] ) > self::severity_rank( $worst ) ) {
				$worst = $definition['severity'];
			}
		}

		return $worst;
	}

	/**
	 * Encode issue codes for storage.
	 *
	 * Codes are sorted and wrapped in delimiters (",a,b,") so a stored value can be
	 * matched with a single LIKE '%,code,%' without JSON functions. Returns an empty
	 * string when there are no issues, so "no issues" is falsy in SQL and PHP alike.
	 *
	 * @param string[] $codes Issue codes.
	 * @return string
	 */
	public static function encode_codes( array $codes ) {
		$valid = array();

		foreach ( $codes as $code ) {
			if ( is_string( $code ) && null !== self::definition( $code ) ) {
				$valid[ $code ] = true;
			}
		}

		if ( empty( $valid ) ) {
			return '';
		}

		$valid = array_keys( $valid );
		sort( $valid );

		return ',' . implode( ',', $valid ) . ',';
	}

	/**
	 * Decode a stored issue-code string.
	 *
	 * @param string $encoded Stored value.
	 * @return string[]
	 */
	public static function decode_codes( $encoded ) {
		if ( ! is_string( $encoded ) || '' === trim( $encoded, ', ' ) ) {
			return array();
		}

		$codes  = explode( ',', trim( $encoded, ',' ) );
		$result = array();

		foreach ( $codes as $code ) {
			$code = trim( $code );
			if ( '' !== $code && null !== self::definition( $code ) ) {
				$result[] = $code;
			}
		}

		return $result;
	}

	/**
	 * Full description of an issue code.
	 *
	 * @param string $code Issue code.
	 * @return array{code:string,label:string,severity:string,explanation:string,recommended_action:string,auto_fixable:bool,requires_review:bool}|null
	 */
	public static function definition( $code ) {
		if ( ! is_string( $code ) ) {
			return null;
		}

		$definitions = self::definitions();

		if ( ! isset( $definitions[ $code ] ) ) {
			return null;
		}

		/**
		 * Filter a single audit issue definition.
		 *
		 * @since 4.8.0
		 *
		 * @param array  $definition Issue definition.
		 * @param string $code       Issue code.
		 */
		return apply_filters( 'bbai_audit_issue_definition', $definitions[ $code ], $code );
	}

	/**
	 * The full issue catalogue.
	 *
	 * Copy here is deliberately cautious: this plugin reports what its own checks
	 * can see. It does not assert accessibility compliance.
	 *
	 * @return array<string,array>
	 */
	public static function definitions() {
		return array(
			self::MISSING_ALT               => array(
				'code'               => self::MISSING_ALT,
				'label'              => __( 'No alt text', 'beepbeep-ai-alt-text-generator' ),
				'severity'           => self::SEVERITY_CRITICAL,
				'explanation'        => __( 'This image has no alt text stored at all, so screen-reader users get nothing but the file name.', 'beepbeep-ai-alt-text-generator' ),
				'recommended_action' => __( 'Describe what the image shows, or mark it as decorative if it carries no meaning.', 'beepbeep-ai-alt-text-generator' ),
				'auto_fixable'       => true,
				'requires_review'    => false,
			),
			self::EMPTY_ALT_REVIEW_REQUIRED => array(
				'code'               => self::EMPTY_ALT_REVIEW_REQUIRED,
				'label'              => __( 'Empty alt text — review needed', 'beepbeep-ai-alt-text-generator' ),
				'severity'           => self::SEVERITY_REVIEW,
				'explanation'        => __( 'The alt attribute is set but empty. That is correct for decorative images and wrong for meaningful ones, and this plugin cannot tell which applies here.', 'beepbeep-ai-alt-text-generator' ),
				'recommended_action' => __( 'Decide whether the image is decorative. Mark it as decorative to keep the empty alt, or describe it.', 'beepbeep-ai-alt-text-generator' ),
				'auto_fixable'       => false,
				'requires_review'    => true,
			),
			self::FILENAME_AS_ALT           => array(
				'code'               => self::FILENAME_AS_ALT,
				'label'              => __( 'Alt text is the file name', 'beepbeep-ai-alt-text-generator' ),
				'severity'           => self::SEVERITY_WARNING,
				'explanation'        => __( 'The alt text repeats the file name, which usually describes the upload rather than the picture.', 'beepbeep-ai-alt-text-generator' ),
				'recommended_action' => __( 'Replace it with a short description of what the image shows.', 'beepbeep-ai-alt-text-generator' ),
				'auto_fixable'       => true,
				'requires_review'    => false,
			),
			self::GENERIC_ALT               => array(
				'code'               => self::GENERIC_ALT,
				'label'              => __( 'Generic alt text', 'beepbeep-ai-alt-text-generator' ),
				'severity'           => self::SEVERITY_WARNING,
				'explanation'        => __( 'The alt text only names the medium or uses placeholder words, so it does not describe this particular image.', 'beepbeep-ai-alt-text-generator' ),
				'recommended_action' => __( 'Say what is in the image instead of that it is an image.', 'beepbeep-ai-alt-text-generator' ),
				'auto_fixable'       => true,
				'requires_review'    => false,
			),
			self::DUPLICATE_ALT             => array(
				'code'               => self::DUPLICATE_ALT,
				'label'              => __( 'Alt text used on other images', 'beepbeep-ai-alt-text-generator' ),
				'severity'           => self::SEVERITY_WARNING,
				'explanation'        => __( 'Several images share this exact alt text. That is fine when the images really are the same, and unhelpful when they are not.', 'beepbeep-ai-alt-text-generator' ),
				'recommended_action' => __( 'Check the images that share this text and describe each one on its own terms.', 'beepbeep-ai-alt-text-generator' ),
				'auto_fixable'       => false,
				'requires_review'    => true,
			),
			self::ALT_TOO_SHORT             => array(
				'code'               => self::ALT_TOO_SHORT,
				'label'              => __( 'Alt text is very short', 'beepbeep-ai-alt-text-generator' ),
				'severity'           => self::SEVERITY_WARNING,
				'explanation'        => __( 'There are too few meaningful words here to convey what the image shows.', 'beepbeep-ai-alt-text-generator' ),
				'recommended_action' => __( 'Expand it to a short phrase covering the subject and setting.', 'beepbeep-ai-alt-text-generator' ),
				'auto_fixable'       => true,
				'requires_review'    => false,
			),
			self::ALT_TOO_LONG              => array(
				'code'               => self::ALT_TOO_LONG,
				'label'              => __( 'Alt text is very long', 'beepbeep-ai-alt-text-generator' ),
				'severity'           => self::SEVERITY_INFORMATION,
				'explanation'        => __( 'Long alt text can be tiring to listen to. Some images genuinely need it, such as charts and diagrams.', 'beepbeep-ai-alt-text-generator' ),
				'recommended_action' => __( 'Consider trimming it, or move the detail into a caption or nearby text.', 'beepbeep-ai-alt-text-generator' ),
				'auto_fixable'       => false,
				'requires_review'    => false,
			),
			self::POSSIBLE_KEYWORD_STUFFING => array(
				'code'               => self::POSSIBLE_KEYWORD_STUFFING,
				'label'              => __( 'Possible keyword stuffing', 'beepbeep-ai-alt-text-generator' ),
				'severity'           => self::SEVERITY_WARNING,
				'explanation'        => __( 'This alt text repeats words or reads as a list of keywords rather than a description. This check can be wrong, so it is worth a look.', 'beepbeep-ai-alt-text-generator' ),
				'recommended_action' => __( 'Rewrite it as a plain sentence describing the image.', 'beepbeep-ai-alt-text-generator' ),
				'auto_fixable'       => false,
				'requires_review'    => true,
			),
			self::ORPHANED_ATTACHMENT       => array(
				'code'               => self::ORPHANED_ATTACHMENT,
				'label'              => __( 'Not attached to any post', 'beepbeep-ai-alt-text-generator' ),
				'severity'           => self::SEVERITY_INFORMATION,
				'explanation'        => __( 'No post or page in this site links to this image, based on the checks this plugin runs. It may still be used in a way the audit cannot see.', 'beepbeep-ai-alt-text-generator' ),
				'recommended_action' => __( 'Confirm whether the image is still needed before spending effort on it.', 'beepbeep-ai-alt-text-generator' ),
				'auto_fixable'       => false,
				'requires_review'    => false,
			),
			self::NO_USAGE_CONTEXT          => array(
				'code'               => self::NO_USAGE_CONTEXT,
				'label'              => __( 'Usage not determined', 'beepbeep-ai-alt-text-generator' ),
				'severity'           => self::SEVERITY_INFORMATION,
				'explanation'        => __( 'The audit could not work out where this image is used without a slower scan. This is a limit of the check, not a problem with the image.', 'beepbeep-ai-alt-text-generator' ),
				'recommended_action' => __( 'No action needed. Use this only as a hint when prioritising.', 'beepbeep-ai-alt-text-generator' ),
				'auto_fixable'       => false,
				'requires_review'    => false,
			),
		);
	}
}
