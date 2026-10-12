<?php
/**
 * Rule: alt attribute present but empty.
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
 * Fires when alt metadata exists but holds nothing but whitespace.
 *
 * This is reported as "review", not as a failure. An empty alt attribute is the
 * correct markup for a decorative image, and nothing available to this plugin
 * can tell a decorative image from a neglected one. The administrator decides.
 */
class Empty_Alt_Review_Rule extends Abstract_Audit_Rule {

	/**
	 * Issue code.
	 *
	 * @return string
	 */
	public function get_code() {
		return Audit_Issue::EMPTY_ALT_REVIEW_REQUIRED;
	}

	/**
	 * Skip images already settled as decorative.
	 *
	 * @param Image_Audit_Context $context Image under audit.
	 * @return bool
	 */
	public function applies_to( Image_Audit_Context $context ) {
		return ! $context->is_decorative();
	}

	/**
	 * True when the alt row exists and is blank.
	 *
	 * @param Image_Audit_Context $context Image under audit.
	 * @return bool
	 */
	public function evaluate( Image_Audit_Context $context ) {
		return $context->has_alt_meta() && ! $context->has_alt_text();
	}
}
