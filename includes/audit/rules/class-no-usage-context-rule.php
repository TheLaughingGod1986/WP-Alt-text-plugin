<?php
/**
 * Rule: usage of this image could not be determined.
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
 * Fires when the audit had no cheap way to tell where the image is used.
 *
 * This reports a limit of the check, not a defect in the image, which is why it
 * is informational and carries no scoring weight beyond the informational tier.
 */
class No_Usage_Context_Rule extends Abstract_Audit_Rule {

	/**
	 * Issue code.
	 *
	 * @return string
	 */
	public function get_code() {
		return Audit_Issue::NO_USAGE_CONTEXT;
	}

	/**
	 * True when usage was never established.
	 *
	 * @param Image_Audit_Context $context Image under audit.
	 * @return bool
	 */
	public function evaluate( Image_Audit_Context $context ) {
		return ! $context->is_usage_context_known();
	}
}
