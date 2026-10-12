<?php
/**
 * Rule: alt text names the medium instead of the subject.
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
use BeepBeepAI\AltTextGenerator\Audit\Audit_Vocabulary;
use BeepBeepAI\AltTextGenerator\Audit\Image_Audit_Context;

/**
 * Fires when nothing is left after removing lead-in phrases, medium words and
 * placeholder junk — for example "image", "a photo of an image", "test", "logo".
 *
 * Deliberately strict: a single real word ("logo of Acme Ltd") is enough to pass,
 * because the point is to catch alt text that describes no particular image, not
 * to grade prose.
 */
class Generic_Alt_Rule extends Abstract_Audit_Rule {

	/**
	 * Issue code.
	 *
	 * @return string
	 */
	public function get_code() {
		return Audit_Issue::GENERIC_ALT;
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
	 * True when no descriptive token survives.
	 *
	 * @param Image_Audit_Context $context Image under audit.
	 * @return bool
	 */
	public function evaluate( Image_Audit_Context $context ) {
		$stripped = $context->get_alt_without_redundant_prefix();

		if ( '' === $stripped ) {
			return true;
		}

		$non_descriptive = Audit_Vocabulary::non_descriptive_tokens();
		$tokens          = explode( ' ', $stripped );

		foreach ( $tokens as $token ) {
			if ( '' === $token ) {
				continue;
			}

			// A bare number describes nothing on its own.
			if ( ctype_digit( $token ) ) {
				continue;
			}

			if ( ! isset( $non_descriptive[ $token ] ) ) {
				return false;
			}
		}

		return true;
	}
}
