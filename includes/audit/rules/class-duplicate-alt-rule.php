<?php
/**
 * Rule: the same alt text appears on several images.
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
 * Fires when a non-trivial alt value is shared by two or more images.
 *
 * The length and word-count floors matter: without them every "Acme logo" in a
 * footer would be reported, which is noise rather than a finding. The duplicate
 * group size is supplied by the batch processor from one query per run, so this
 * rule performs no lookups of its own.
 */
class Duplicate_Alt_Rule extends Abstract_Audit_Rule {

	/**
	 * Default thresholds.
	 *
	 * @return array<string,int>
	 */
	protected function default_thresholds() {
		return array(
			'min_length'     => 15,
			'min_tokens'     => 3,
			'min_group_size' => 2,
		);
	}

	/**
	 * Issue code.
	 *
	 * @return string
	 */
	public function get_code() {
		return Audit_Issue::DUPLICATE_ALT;
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
	 * True when this alt value is shared and substantial enough to matter.
	 *
	 * @param Image_Audit_Context $context Image under audit.
	 * @return bool
	 */
	public function evaluate( Image_Audit_Context $context ) {
		if ( $context->get_duplicate_group_size() < (int) $this->threshold( 'min_group_size', 2 ) ) {
			return false;
		}

		if ( $context->get_alt_length() < (int) $this->threshold( 'min_length', 15 ) ) {
			return false;
		}

		return count( $context->get_meaningful_tokens() ) >= (int) $this->threshold( 'min_tokens', 3 );
	}
}
