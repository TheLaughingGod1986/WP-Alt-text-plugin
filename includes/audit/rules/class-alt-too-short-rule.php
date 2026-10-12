<?php
/**
 * Rule: alt text too short to describe anything.
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
 * Fires when alt text has fewer than three meaningful words, or is shorter than
 * twelve characters.
 *
 * Both floors are low on purpose. "Red door" is short but real, and gets flagged
 * only for word count; the intent is to catch "img", "ok", "1" rather than to
 * push everyone towards long descriptions.
 */
class Alt_Too_Short_Rule extends Abstract_Audit_Rule {

	/**
	 * Default thresholds.
	 *
	 * @return array<string,int>
	 */
	protected function default_thresholds() {
		return array(
			'min_length' => 12,
			'min_tokens' => 3,
		);
	}

	/**
	 * Issue code.
	 *
	 * @return string
	 */
	public function get_code() {
		return Audit_Issue::ALT_TOO_SHORT;
	}

	/**
	 * Needs real alt text, and skips settled decorative images.
	 *
	 * @param Image_Audit_Context $context Image under audit.
	 * @return bool
	 */
	public function applies_to( Image_Audit_Context $context ) {
		return $context->has_alt_text() && ! $context->is_decorative();
	}

	/**
	 * True when the alt text falls under either floor.
	 *
	 * @param Image_Audit_Context $context Image under audit.
	 * @return bool
	 */
	public function evaluate( Image_Audit_Context $context ) {
		if ( $context->get_alt_length() < (int) $this->threshold( 'min_length', 12 ) ) {
			return true;
		}

		return count( $context->get_meaningful_tokens() ) < (int) $this->threshold( 'min_tokens', 3 );
	}
}
