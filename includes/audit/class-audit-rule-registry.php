<?php
/**
 * Registry that owns the rule set and evaluates it against an image.
 *
 * @package BeepBeep_AI
 * @since   4.8.0
 */

namespace BeepBeepAI\AltTextGenerator\Audit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeepBeepAI\AltTextGenerator\Audit\Rules\Alt_Too_Long_Rule;
use BeepBeepAI\AltTextGenerator\Audit\Rules\Alt_Too_Short_Rule;
use BeepBeepAI\AltTextGenerator\Audit\Rules\Duplicate_Alt_Rule;
use BeepBeepAI\AltTextGenerator\Audit\Rules\Empty_Alt_Review_Rule;
use BeepBeepAI\AltTextGenerator\Audit\Rules\Filename_Alt_Rule;
use BeepBeepAI\AltTextGenerator\Audit\Rules\Generic_Alt_Rule;
use BeepBeepAI\AltTextGenerator\Audit\Rules\Keyword_Stuffing_Rule;
use BeepBeepAI\AltTextGenerator\Audit\Rules\Missing_Alt_Rule;
use BeepBeepAI\AltTextGenerator\Audit\Rules\No_Usage_Context_Rule;
use BeepBeepAI\AltTextGenerator\Audit\Rules\Orphaned_Attachment_Rule;

/**
 * Holds the active rules and applies them.
 */
class Audit_Rule_Registry {

	/**
	 * Active rules.
	 *
	 * @var Audit_Rule_Interface[]
	 */
	private $rules = array();

	/**
	 * Build the registry.
	 *
	 * @param Audit_Rule_Interface[]|null $rules Explicit rule set, or null for the defaults.
	 */
	public function __construct( ?array $rules = null ) {
		$this->rules = null === $rules ? self::default_rules() : $this->filter_valid( $rules );
	}

	/**
	 * The stock rule set, after the extension filter has run.
	 *
	 * @return Audit_Rule_Interface[]
	 */
	public static function default_rules() {
		/**
		 * Filter the tunable thresholds for the built-in rules.
		 *
		 * Keyed by issue code, each value merged over that rule's defaults.
		 *
		 * @since 4.8.0
		 *
		 * @param array<string,array> $thresholds Threshold overrides.
		 */
		$thresholds = (array) apply_filters( 'bbai_audit_rule_thresholds', array() );

		$for = static function ( $code ) use ( $thresholds ) {
			return isset( $thresholds[ $code ] ) && is_array( $thresholds[ $code ] ) ? $thresholds[ $code ] : array();
		};

		$rules = array(
			new Missing_Alt_Rule( $for( Audit_Issue::MISSING_ALT ) ),
			new Empty_Alt_Review_Rule( $for( Audit_Issue::EMPTY_ALT_REVIEW_REQUIRED ) ),
			new Filename_Alt_Rule( $for( Audit_Issue::FILENAME_AS_ALT ) ),
			new Generic_Alt_Rule( $for( Audit_Issue::GENERIC_ALT ) ),
			new Duplicate_Alt_Rule( $for( Audit_Issue::DUPLICATE_ALT ) ),
			new Alt_Too_Short_Rule( $for( Audit_Issue::ALT_TOO_SHORT ) ),
			new Alt_Too_Long_Rule( $for( Audit_Issue::ALT_TOO_LONG ) ),
			new Keyword_Stuffing_Rule( $for( Audit_Issue::POSSIBLE_KEYWORD_STUFFING ) ),
			new Orphaned_Attachment_Rule( $for( Audit_Issue::ORPHANED_ATTACHMENT ) ),
			new No_Usage_Context_Rule( $for( Audit_Issue::NO_USAGE_CONTEXT ) ),
		);

		/**
		 * Filter the active audit rules.
		 *
		 * Add a rule by appending an Audit_Rule_Interface implementation. Anything
		 * that does not implement the interface is discarded.
		 *
		 * @since 4.8.0
		 *
		 * @param Audit_Rule_Interface[] $rules Rule instances.
		 */
		$rules = (array) apply_filters( 'bbai_audit_rules', $rules );

		$valid = array();
		foreach ( $rules as $rule ) {
			if ( $rule instanceof Audit_Rule_Interface ) {
				$valid[] = $rule;
			}
		}

		return $valid;
	}

	/**
	 * Active rules.
	 *
	 * @return Audit_Rule_Interface[]
	 */
	public function get_rules() {
		return $this->rules;
	}

	/**
	 * Run every applicable rule against one image.
	 *
	 * A rule that throws is skipped rather than allowed to abort the run: one
	 * malformed third-party rule must not stop a site-wide audit.
	 *
	 * @param Image_Audit_Context $context Image under audit.
	 * @return array{codes:string[],severity:string,requires_review:bool,auto_fixable:bool}
	 */
	public function evaluate( Image_Audit_Context $context ) {
		$codes           = array();
		$requires_review = false;
		$auto_fixable    = false;

		foreach ( $this->rules as $rule ) {
			try {
				if ( ! $rule->applies_to( $context ) ) {
					continue;
				}

				if ( ! $rule->evaluate( $context ) ) {
					continue;
				}
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				continue;
			}

			$code    = $rule->get_code();
			$codes[] = $code;

			$definition = Audit_Issue::definition( $code );
			if ( null === $definition ) {
				continue;
			}

			$requires_review = $requires_review || ! empty( $definition['requires_review'] );
			$auto_fixable    = $auto_fixable || ! empty( $definition['auto_fixable'] );
		}

		$codes = array_values( array_unique( $codes ) );
		sort( $codes );

		return array(
			'codes'           => $codes,
			'severity'        => Audit_Issue::worst_severity( $codes ),
			'requires_review' => $requires_review,
			'auto_fixable'    => $auto_fixable,
		);
	}

	/**
	 * Discard anything that is not a rule.
	 *
	 * @param array $rules Candidate rules.
	 * @return Audit_Rule_Interface[]
	 */
	private function filter_valid( array $rules ) {
		$valid = array();

		foreach ( $rules as $rule ) {
			if ( $rule instanceof Audit_Rule_Interface ) {
				$valid[] = $rule;
			}
		}

		return $valid;
	}
}
