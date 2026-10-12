<?php
/**
 * Rule: alt text long enough to be tiring to listen to.
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
 * Fires above 250 characters.
 *
 * Common guidance suggests around 125 characters, but plenty of charts, maps and
 * diagrams legitimately need more, so the threshold sits at double the usual
 * advice and the finding is informational rather than a warning.
 */
class Alt_Too_Long_Rule extends Abstract_Audit_Rule {

	/**
	 * Default thresholds.
	 *
	 * @return array<string,int>
	 */
	protected function default_thresholds() {
		return array(
			'max_length' => 250,
		);
	}

	/**
	 * Issue code.
	 *
	 * @return string
	 */
	public function get_code() {
		return Audit_Issue::ALT_TOO_LONG;
	}

	/**
	 * Only meaningful when there is alt text to measure.
	 *
	 * @param Image_Audit_Context $context Image under audit.
	 * @return bool
	 */
	public function applies_to( Image_Audit_Context $context ) {
		return $context->has_alt_text();
	}

	/**
	 * True when the alt text exceeds the maximum length.
	 *
	 * @param Image_Audit_Context $context Image under audit.
	 * @return bool
	 */
	public function evaluate( Image_Audit_Context $context ) {
		return $context->get_alt_length() > (int) $this->threshold( 'max_length', 250 );
	}
}
