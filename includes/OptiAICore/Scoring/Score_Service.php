<?php
/**
 * Module-agnostic weighted scoring engine.
 *
 * Runs a rule set against one item, turns the worst-case findings into a
 * 0-100 item score, and rolls per-item scores up into a site-level score for
 * a module. This is the one piece of arithmetic every OptiAI dashboard's Hero
 * Score is built on top of — it must stay deterministic and free (no AI
 * calls), so the health check works for every user, paid or not.
 *
 * @package OptiAI_Core
 */

namespace OptiAI\Core\Scoring;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Score_Service {

	/**
	 * Rule instances to run, in registration order.
	 *
	 * @var Rule_Interface[]
	 */
	private $rules;

	/**
	 * @param Rule_Interface[] $rules Active rules for this module.
	 */
	public function __construct( array $rules ) {
		$this->rules = array_values( array_filter(
			$rules,
			static function ( $rule ) {
				return $rule instanceof Rule_Interface;
			}
		) );
	}

	/**
	 * Evaluate every rule against one item.
	 *
	 * A rule that throws is skipped rather than aborting the whole scan —
	 * one bad rule must not take down a site-wide audit.
	 *
	 * @param mixed $context Module-defined context for the item.
	 * @return array{score:int,status:string,issues:Issue[]}
	 */
	public function evaluate_item( $context ) {
		$issues = array();

		foreach ( $this->rules as $rule ) {
			try {
				if ( ! $rule->applies_to( $context ) ) {
					continue;
				}
				$issue = $rule->evaluate( $context );
				if ( $issue instanceof Issue ) {
					$issues[] = $issue;
				}
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				continue;
			}
		}

		$score = 100;
		foreach ( $issues as $issue ) {
			$score -= $issue->deduction;
		}
		$score = max( 0, min( 100, $score ) );

		return array(
			'score'  => $score,
			'status' => self::status_for_score( $score ),
			'issues' => $issues,
		);
	}

	/**
	 * Site-level score for a module: 100 minus the average deduction across
	 * every scanned item. An item with no issues contributes 0 deduction, so
	 * a perfectly clean library scores 100 and one where every item is
	 * missing entirely (100-point deduction each) scores 0.
	 *
	 * @param int[] $item_scores Per-item 0-100 scores already computed.
	 * @return int
	 */
	public static function site_score( array $item_scores ) {
		if ( empty( $item_scores ) ) {
			return 0;
		}
		$total_deduction = 0;
		foreach ( $item_scores as $score ) {
			$total_deduction += 100 - max( 0, min( 100, (int) $score ) );
		}
		$avg_deduction = $total_deduction / count( $item_scores );
		return (int) round( max( 0, min( 100, 100 - $avg_deduction ) ) );
	}

	/**
	 * Human status band for a score, shared across every OptiAI dashboard.
	 *
	 * @param int $score 0-100.
	 * @return string One of: critical, needs-improvement, good, excellent.
	 */
	public static function status_for_score( $score ) {
		$score = (int) $score;
		if ( $score <= 39 ) {
			return 'critical';
		}
		if ( $score <= 59 ) {
			return 'needs-improvement';
		}
		if ( $score <= 74 ) {
			return 'fair';
		}
		if ( $score <= 89 ) {
			return 'good';
		}
		return 'excellent';
	}
}
