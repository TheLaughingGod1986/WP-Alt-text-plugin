<?php
/**
 * Contract every scoring rule (Alt Text, Metadata, future modules) implements.
 *
 * @package OptiAI_Core
 */

namespace OptiAI\Core\Scoring;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A single, independently-testable check run against one scanned item.
 */
interface Rule_Interface {

	/**
	 * Stable machine code for this rule's issue, e.g. "missing_alt_text".
	 *
	 * @return string
	 */
	public function get_code();

	/**
	 * Whether this rule is relevant to the given item at all (e.g. skip
	 * length checks on an item explicitly marked decorative/excluded).
	 *
	 * @param mixed $context Module-defined context object for the item.
	 * @return bool
	 */
	public function applies_to( $context );

	/**
	 * Run the check. Return an Issue when the rule fires, or null when the
	 * item passes.
	 *
	 * @param mixed $context Module-defined context object for the item.
	 * @return Issue|null
	 */
	public function evaluate( $context );
}
