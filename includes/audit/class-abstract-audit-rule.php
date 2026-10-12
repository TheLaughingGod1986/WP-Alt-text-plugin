<?php
/**
 * Shared behaviour for audit rules.
 *
 * @package BeepBeep_AI
 * @since   4.8.0
 */

namespace BeepBeepAI\AltTextGenerator\Audit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base rule: threshold plumbing and metadata lookups.
 */
abstract class Abstract_Audit_Rule implements Audit_Rule_Interface {

	/**
	 * Thresholds for this rule, merged over the defaults at construction.
	 *
	 * @var array<string,mixed>
	 */
	protected $thresholds = array();

	/**
	 * Build the rule.
	 *
	 * @param array<string,mixed> $thresholds Threshold overrides.
	 */
	public function __construct( array $thresholds = array() ) {
		$this->thresholds = array_merge( $this->default_thresholds(), $thresholds );
	}

	/**
	 * Default thresholds. Rules with no tunable values return an empty array.
	 *
	 * @return array<string,mixed>
	 */
	protected function default_thresholds() {
		return array();
	}

	/**
	 * Read a threshold.
	 *
	 * @param string $key      Threshold name.
	 * @param mixed  $fallback Value when the threshold is unset.
	 * @return mixed
	 */
	protected function threshold( $key, $fallback = null ) {
		return array_key_exists( $key, $this->thresholds ) ? $this->thresholds[ $key ] : $fallback;
	}

	/**
	 * All thresholds in force for this rule.
	 *
	 * @return array<string,mixed>
	 */
	public function get_thresholds() {
		return $this->thresholds;
	}

	/**
	 * Severity of the issue this rule reports.
	 *
	 * @return string
	 */
	public function get_severity() {
		$definition = Audit_Issue::definition( $this->get_code() );

		return null === $definition ? Audit_Issue::SEVERITY_INFORMATION : $definition['severity'];
	}

	/**
	 * Whether generation can plausibly fix this issue on its own.
	 *
	 * @return bool
	 */
	public function is_auto_fixable() {
		$definition = Audit_Issue::definition( $this->get_code() );

		return null !== $definition && ! empty( $definition['auto_fixable'] );
	}

	/**
	 * Whether a person needs to make the call.
	 *
	 * @return bool
	 */
	public function requires_human_review() {
		$definition = Audit_Issue::definition( $this->get_code() );

		return null !== $definition && ! empty( $definition['requires_review'] );
	}

	/**
	 * Most rules run against every image.
	 *
	 * @param Image_Audit_Context $context Image under audit.
	 * @return bool
	 */
	public function applies_to( Image_Audit_Context $context ) {
		unset( $context );

		return true;
	}
}
