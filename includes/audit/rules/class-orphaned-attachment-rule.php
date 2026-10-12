<?php
/**
 * Rule: attachment not used anywhere the audit can see.
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
 * Fires only when usage *was* determined and came back empty.
 *
 * If the audit could not establish usage at all, that is No_Usage_Context_Rule's
 * job. Keeping the two apart stops the audit claiming an image is unused when it
 * simply did not look hard enough — phase one deliberately does not crawl
 * rendered HTML.
 */
class Orphaned_Attachment_Rule extends Abstract_Audit_Rule {

	/**
	 * Issue code.
	 *
	 * @return string
	 */
	public function get_code() {
		return Audit_Issue::ORPHANED_ATTACHMENT;
	}

	/**
	 * Only applies when usage was actually established.
	 *
	 * @param Image_Audit_Context $context Image under audit.
	 * @return bool
	 */
	public function applies_to( Image_Audit_Context $context ) {
		return $context->is_usage_context_known();
	}

	/**
	 * True when nothing references the image.
	 *
	 * @param Image_Audit_Context $context Image under audit.
	 * @return bool
	 */
	public function evaluate( Image_Audit_Context $context ) {
		return 0 === $context->get_parent_post_id() && ! $context->has_usage();
	}
}
