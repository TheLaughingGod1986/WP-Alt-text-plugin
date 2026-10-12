<?php
/**
 * Value object describing one image at audit time.
 *
 * This class is deliberately free of WordPress calls: everything a rule needs
 * is passed in by the discovery layer, so rules stay pure and unit-testable.
 *
 * @package BeepBeep_AI
 * @since   4.8.0
 */

namespace BeepBeepAI\AltTextGenerator\Audit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Immutable snapshot of an image plus the derived text metrics rules need.
 */
class Image_Audit_Context {

	/**
	 * Attachment ID.
	 *
	 * @var int
	 */
	private $attachment_id;

	/**
	 * Raw alt text, or null when no alt meta row exists at all.
	 *
	 * The null/empty-string distinction is what separates "never given alt text"
	 * from "deliberately given empty alt text", so it must survive intact.
	 *
	 * @var string|null
	 */
	private $alt;

	/**
	 * File name without directory or extension.
	 *
	 * @var string
	 */
	private $filename;

	/**
	 * Attachment title.
	 *
	 * @var string
	 */
	private $title;

	/**
	 * Attachment caption.
	 *
	 * @var string
	 */
	private $caption;

	/**
	 * Attachment description.
	 *
	 * @var string
	 */
	private $description;

	/**
	 * Parent post ID, 0 when unattached.
	 *
	 * @var int
	 */
	private $parent_post_id;

	/**
	 * Whether the audit established where this image is used.
	 *
	 * @var bool
	 */
	private $usage_context_known;

	/**
	 * Whether a usage was actually found (only meaningful when usage is known).
	 *
	 * @var bool
	 */
	private $has_usage;

	/**
	 * Short human-readable usage summary.
	 *
	 * @var string
	 */
	private $context_summary;

	/**
	 * Whether an administrator marked this image decorative.
	 *
	 * @var bool
	 */
	private $is_decorative;

	/**
	 * How many images share this normalised alt value (1 = unique).
	 *
	 * @var int
	 */
	private $duplicate_group_size;

	/**
	 * MIME type.
	 *
	 * @var string
	 */
	private $mime_type;

	/**
	 * Upload date, MySQL format.
	 *
	 * @var string
	 */
	private $uploaded_at;

	/**
	 * Memoised derived values.
	 *
	 * @var array<string,mixed>
	 */
	private $derived = array();

	/**
	 * Build a context from an associative array.
	 *
	 * @param array<string,mixed> $data Raw values.
	 */
	public function __construct( array $data = array() ) {
		$this->attachment_id = isset( $data['attachment_id'] ) ? (int) $data['attachment_id'] : 0;

		// Null means "no alt meta row"; '' means "alt meta row exists and is empty".
		$this->alt = array_key_exists( 'alt', $data ) && null !== $data['alt']
			? (string) $data['alt']
			: null;

		$this->filename             = isset( $data['filename'] ) ? (string) $data['filename'] : '';
		$this->title                = isset( $data['title'] ) ? (string) $data['title'] : '';
		$this->caption              = isset( $data['caption'] ) ? (string) $data['caption'] : '';
		$this->description          = isset( $data['description'] ) ? (string) $data['description'] : '';
		$this->parent_post_id       = isset( $data['parent_post_id'] ) ? max( 0, (int) $data['parent_post_id'] ) : 0;
		$this->usage_context_known  = ! empty( $data['usage_context_known'] );
		$this->has_usage            = ! empty( $data['has_usage'] );
		$this->context_summary      = isset( $data['context_summary'] ) ? (string) $data['context_summary'] : '';
		$this->is_decorative        = ! empty( $data['is_decorative'] );
		$this->duplicate_group_size = isset( $data['duplicate_group_size'] ) ? max( 0, (int) $data['duplicate_group_size'] ) : 0;
		$this->mime_type            = isset( $data['mime_type'] ) ? (string) $data['mime_type'] : '';
		$this->uploaded_at          = isset( $data['uploaded_at'] ) ? (string) $data['uploaded_at'] : '';
	}

	/* ─── Raw accessors ─── */

	/**
	 * Attachment ID.
	 *
	 * @return int
	 */
	public function get_attachment_id() {
		return $this->attachment_id;
	}

	/**
	 * Raw alt text, or empty string when no alt meta row exists.
	 *
	 * @return string
	 */
	public function get_alt() {
		return null === $this->alt ? '' : $this->alt;
	}

	/**
	 * Whether an alt meta row exists, whatever its value.
	 *
	 * @return bool
	 */
	public function has_alt_meta() {
		return null !== $this->alt;
	}

	/**
	 * Whether the image has alt text with actual content in it.
	 *
	 * @return bool
	 */
	public function has_alt_text() {
		return '' !== trim( $this->get_alt() );
	}

	/**
	 * File name stem.
	 *
	 * @return string
	 */
	public function get_filename() {
		return $this->filename;
	}

	/**
	 * Attachment title.
	 *
	 * @return string
	 */
	public function get_title() {
		return $this->title;
	}

	/**
	 * Attachment caption.
	 *
	 * @return string
	 */
	public function get_caption() {
		return $this->caption;
	}

	/**
	 * Attachment description.
	 *
	 * @return string
	 */
	public function get_description() {
		return $this->description;
	}

	/**
	 * Parent post ID.
	 *
	 * @return int
	 */
	public function get_parent_post_id() {
		return $this->parent_post_id;
	}

	/**
	 * Whether usage could be determined.
	 *
	 * @return bool
	 */
	public function is_usage_context_known() {
		return $this->usage_context_known;
	}

	/**
	 * Whether the image is used somewhere the audit can see.
	 *
	 * @return bool
	 */
	public function has_usage() {
		return $this->has_usage;
	}

	/**
	 * Short usage summary for display.
	 *
	 * @return string
	 */
	public function get_context_summary() {
		return $this->context_summary;
	}

	/**
	 * Whether an administrator marked this image decorative.
	 *
	 * @return bool
	 */
	public function is_decorative() {
		return $this->is_decorative;
	}

	/**
	 * Number of images sharing this alt value.
	 *
	 * @return int
	 */
	public function get_duplicate_group_size() {
		return $this->duplicate_group_size;
	}

	/**
	 * MIME type.
	 *
	 * @return string
	 */
	public function get_mime_type() {
		return $this->mime_type;
	}

	/**
	 * Upload date.
	 *
	 * @return string
	 */
	public function get_uploaded_at() {
		return $this->uploaded_at;
	}

	/* ─── Derived text metrics ─── */

	/**
	 * Alt text length in characters, ignoring surrounding whitespace.
	 *
	 * @return int
	 */
	public function get_alt_length() {
		return $this->memoise(
			'alt_length',
			function () {
				$alt = trim( $this->get_alt() );
				return function_exists( 'mb_strlen' ) ? (int) mb_strlen( $alt ) : strlen( $alt );
			}
		);
	}

	/**
	 * Normalised alt text — lowercase, punctuation flattened, whitespace collapsed.
	 *
	 * @return string
	 */
	public function get_normalised_alt() {
		return $this->memoise(
			'normalised_alt',
			function () {
				return self::normalise( $this->get_alt() );
			}
		);
	}

	/**
	 * Normalised file name stem.
	 *
	 * @return string
	 */
	public function get_normalised_filename() {
		return $this->memoise(
			'normalised_filename',
			function () {
				return self::normalise( $this->get_filename() );
			}
		);
	}

	/**
	 * SHA-1 of the normalised alt text. Empty alt hashes to an empty string.
	 *
	 * @return string
	 */
	public function get_alt_hash() {
		return $this->memoise(
			'alt_hash',
			function () {
				$normalised = $this->get_normalised_alt();
				return '' === $normalised ? '' : sha1( $normalised );
			}
		);
	}

	/**
	 * All word tokens in the normalised alt text.
	 *
	 * @return string[]
	 */
	public function get_alt_tokens() {
		return $this->memoise(
			'alt_tokens',
			function () {
				$normalised = $this->get_normalised_alt();
				if ( '' === $normalised ) {
					return array();
				}

				return explode( ' ', $normalised );
			}
		);
	}

	/**
	 * Tokens that actually carry meaning: no stop words, no generic medium words,
	 * no placeholder junk, nothing shorter than two characters.
	 *
	 * @return string[]
	 */
	public function get_meaningful_tokens() {
		return $this->memoise(
			'meaningful_tokens',
			function () {
				$ignored = Audit_Vocabulary::ignored_tokens();
				$tokens  = array();

				foreach ( $this->get_alt_tokens() as $token ) {
					if ( strlen( $token ) < 2 ) {
						continue;
					}
					if ( isset( $ignored[ $token ] ) ) {
						continue;
					}
					$tokens[] = $token;
				}

				return $tokens;
			}
		);
	}

	/**
	 * Alt text with any leading "photo of" style phrase removed.
	 *
	 * @return string
	 */
	public function get_alt_without_redundant_prefix() {
		return $this->memoise(
			'alt_without_prefix',
			function () {
				$normalised = $this->get_normalised_alt();
				if ( '' === $normalised ) {
					return '';
				}

				foreach ( Audit_Vocabulary::redundant_prefixes() as $prefix ) {
					$prefix = self::normalise( $prefix );
					if ( '' === $prefix ) {
						continue;
					}

					if ( 0 === strpos( $normalised, $prefix . ' ' ) ) {
						return trim( substr( $normalised, strlen( $prefix ) + 1 ) );
					}

					if ( $normalised === $prefix ) {
						return '';
					}
				}

				return $normalised;
			}
		);
	}

	/**
	 * Normalise a string for comparison.
	 *
	 * Lowercases, replaces every run of non-alphanumeric characters with a single
	 * space, and trims. Intentionally matches the existing coverage-scan
	 * normalisation so audit numbers line up with the legacy dashboard.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function normalise( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}

		$value = strtolower( $value );
		$value = (string) preg_replace( '/[^a-z0-9]+/i', ' ', $value );
		$value = (string) preg_replace( '/\s+/', ' ', $value );

		return trim( $value );
	}

	/**
	 * Memoise a derived value.
	 *
	 * @param string   $key      Cache key.
	 * @param callable $callback Producer.
	 * @return mixed
	 */
	private function memoise( $key, $callback ) {
		if ( ! array_key_exists( $key, $this->derived ) ) {
			$this->derived[ $key ] = $callback();
		}

		return $this->derived[ $key ];
	}
}
