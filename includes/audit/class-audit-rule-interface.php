<?php
/**
 * Contract every audit rule implements.
 *
 * @package BeepBeep_AI
 * @since   4.8.0
 */

namespace BeepBeepAI\AltTextGenerator\Audit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A single deterministic check against one image.
 */
interface Audit_Rule_Interface {

	/**
	 * The issue code this rule reports.
	 *
	 * @return string
	 */
	public function get_code();

	/**
	 * Whether this rule is worth running for the given image.
	 *
	 * Used for cheap pre-filtering and for rule precedence — for example, content
	 * quality rules do not apply to images that have no alt text at all.
	 *
	 * @param Image_Audit_Context $context Image under audit.
	 * @return bool
	 */
	public function applies_to( Image_Audit_Context $context );

	/**
	 * Whether the issue is present.
	 *
	 * Implementations must be deterministic and side-effect free.
	 *
	 * @param Image_Audit_Context $context Image under audit.
	 * @return bool True when the issue applies.
	 */
	public function evaluate( Image_Audit_Context $context );
}
