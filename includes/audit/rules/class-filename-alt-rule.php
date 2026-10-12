<?php
/**
 * Rule: alt text is just the file name.
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
 * Fires when the alt text repeats the file name, or looks like a file name.
 *
 * Two signals, both conservative:
 *  1. the normalised alt equals the normalised file-name stem;
 *  2. the alt still carries an image file extension, or matches a camera-style
 *     pattern such as IMG_1234 or DSC-0042.
 */
class Filename_Alt_Rule extends Abstract_Audit_Rule {

	/**
	 * Patterns that mark a value as machine-generated rather than written.
	 *
	 * @var string[]
	 */
	private static $filename_patterns = array(
		'/^img[\s_-]*\d+$/',
		'/^dsc[\s_-]*\d+$/',
		'/^dscn[\s_-]*\d+$/',
		'/^p\d{7}$/',
		'/^\d{8}[\s_-]*\d+$/',
		'/^screenshot[\s_-]/',
		'/^screen shot \d/',
		'/^image[\s_-]*\d+$/',
		'/^photo[\s_-]*\d+$/',
		'/^untitled[\s_-]*\d*$/',
	);

	/**
	 * Issue code.
	 *
	 * @return string
	 */
	public function get_code() {
		return Audit_Issue::FILENAME_AS_ALT;
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
	 * True when the alt text is, or looks like, a file name.
	 *
	 * @param Image_Audit_Context $context Image under audit.
	 * @return bool
	 */
	public function evaluate( Image_Audit_Context $context ) {
		$alt = $context->get_normalised_alt();

		if ( '' === $alt ) {
			return false;
		}

		$filename = $context->get_normalised_filename();
		if ( '' !== $filename && $alt === $filename ) {
			return true;
		}

		// An extension surviving normalisation means the raw value was "beach.jpg".
		if ( preg_match( '/\.(jpe?g|png|gif|webp|avif|svg|bmp|tiff?)$/i', trim( $context->get_alt() ) ) ) {
			return true;
		}

		foreach ( self::$filename_patterns as $pattern ) {
			if ( preg_match( $pattern, $alt ) ) {
				return true;
			}
		}

		return false;
	}
}
