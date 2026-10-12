<?php
/**
 * Rule: no alt text stored at all.
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
 * Fires when an image has no alt metadata whatsoever.
 *
 * An image whose alt attribute is present but empty is a *different* situation —
 * that may well be a correct decorative marking — and is handled by
 * Empty_Alt_Review_Rule instead.
 */
class Missing_Alt_Rule extends Abstract_Audit_Rule {

	/**
	 * Issue code.
	 *
	 * @return string
	 */
	public function get_code() {
		return Audit_Issue::MISSING_ALT;
	}

	/**
	 * Skip images the administrator already marked decorative.
	 *
	 * @param Image_Audit_Context $context Image under audit.
	 * @return bool
	 */
	public function applies_to( Image_Audit_Context $context ) {
		return ! $context->is_decorative();
	}

	/**
	 * True when there is no alt meta row at all.
	 *
	 * @param Image_Audit_Context $context Image under audit.
	 * @return bool
	 */
	public function evaluate( Image_Audit_Context $context ) {
		return ! $context->has_alt_meta();
	}
}
