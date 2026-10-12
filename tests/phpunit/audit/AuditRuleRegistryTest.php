<?php
/**
 * Rule registry: precedence, extension points, and failure isolation.
 */

declare(strict_types=1);

use BeepBeepAI\AltTextGenerator\Audit\Abstract_Audit_Rule;
use BeepBeepAI\AltTextGenerator\Audit\Audit_Issue;
use BeepBeepAI\AltTextGenerator\Audit\Audit_Rule_Registry;
use BeepBeepAI\AltTextGenerator\Audit\Image_Audit_Context;
use PHPUnit\Framework\TestCase;

/**
 * A rule that always explodes, used to prove one bad rule cannot abort a scan.
 */
final class ExplodingAuditRule extends Abstract_Audit_Rule {

	public function get_code() {
		return Audit_Issue::GENERIC_ALT;
	}

	public function evaluate( Image_Audit_Context $context ) {
		throw new RuntimeException( 'boom' );
	}
}

final class AuditRuleRegistryTest extends TestCase {

	protected function setUp(): void {
		remove_all_filters( 'bbai_audit_rules' );
		remove_all_filters( 'bbai_audit_rule_thresholds' );
	}

	protected function tearDown(): void {
		remove_all_filters( 'bbai_audit_rules' );
		remove_all_filters( 'bbai_audit_rule_thresholds' );
	}

	/**
	 * @param array<string,mixed> $overrides Field overrides.
	 */
	private function context( array $overrides = array() ): Image_Audit_Context {
		return new Image_Audit_Context(
			array_merge(
				array(
					'attachment_id'       => 5,
					'alt'                 => null,
					'filename'            => 'photo',
					'usage_context_known' => true,
					'has_usage'           => true,
					'parent_post_id'      => 3,
				),
				$overrides
			)
		);
	}

	public function test_missing_alt_suppresses_content_quality_rules(): void {
		$registry = new Audit_Rule_Registry();

		$result = $registry->evaluate( $this->context( array( 'alt' => null ) ) );

		$this->assertContains( Audit_Issue::MISSING_ALT, $result['codes'] );
		$this->assertNotContains( Audit_Issue::ALT_TOO_SHORT, $result['codes'] );
		$this->assertNotContains( Audit_Issue::GENERIC_ALT, $result['codes'] );
		$this->assertNotContains( Audit_Issue::FILENAME_AS_ALT, $result['codes'] );
		$this->assertNotContains( Audit_Issue::EMPTY_ALT_REVIEW_REQUIRED, $result['codes'] );
		$this->assertSame( Audit_Issue::SEVERITY_CRITICAL, $result['severity'] );
	}

	public function test_empty_alt_suppresses_content_quality_rules(): void {
		$registry = new Audit_Rule_Registry();

		$result = $registry->evaluate( $this->context( array( 'alt' => '' ) ) );

		$this->assertSame( array( Audit_Issue::EMPTY_ALT_REVIEW_REQUIRED ), $result['codes'] );
		$this->assertSame( Audit_Issue::SEVERITY_REVIEW, $result['severity'] );
		$this->assertTrue( $result['requires_review'] );
	}

	public function test_a_clean_image_produces_no_issues(): void {
		$registry = new Audit_Rule_Registry();

		$result = $registry->evaluate(
			$this->context(
				array(
					'alt'                  => 'A grey heron standing in shallow water at dawn',
					'duplicate_group_size' => 1,
				)
			)
		);

		$this->assertSame( array(), $result['codes'] );
		$this->assertSame( Audit_Issue::SEVERITY_INFORMATION, $result['severity'] );
		$this->assertFalse( $result['requires_review'] );
	}

	public function test_severity_is_the_worst_across_several_issues(): void {
		$registry = new Audit_Rule_Registry();

		// Filename-style alt that is also too short: two warnings, plus nothing worse.
		$result = $registry->evaluate( $this->context( array( 'alt' => 'IMG_1234' ) ) );

		$this->assertContains( Audit_Issue::FILENAME_AS_ALT, $result['codes'] );
		$this->assertContains( Audit_Issue::ALT_TOO_SHORT, $result['codes'] );
		$this->assertSame( Audit_Issue::SEVERITY_WARNING, $result['severity'] );
	}

	public function test_codes_are_sorted_and_deduplicated(): void {
		$registry = new Audit_Rule_Registry();

		$result = $registry->evaluate( $this->context( array( 'alt' => 'IMG_1234' ) ) );
		$sorted  = $result['codes'];
		sort( $sorted );

		$this->assertSame( $sorted, $result['codes'] );
		$this->assertSame( array_unique( $result['codes'] ), $result['codes'] );
	}

	public function test_a_rule_that_throws_is_skipped_without_aborting_the_scan(): void {
		$registry = new Audit_Rule_Registry( array( new ExplodingAuditRule() ) );

		$result = $registry->evaluate( $this->context( array( 'alt' => 'A heron in shallow water' ) ) );

		$this->assertSame( array(), $result['codes'] );
	}

	public function test_rules_filter_can_add_and_replace_rules(): void {
		add_filter(
			'bbai_audit_rules',
			static function ( $rules ) {
				return array();
			}
		);

		$this->assertSame( array(), Audit_Rule_Registry::default_rules() );
	}

	public function test_non_rule_entries_are_discarded(): void {
		add_filter(
			'bbai_audit_rules',
			static function ( $rules ) {
				$rules[] = 'not a rule';
				$rules[] = new stdClass();
				return $rules;
			}
		);

		foreach ( Audit_Rule_Registry::default_rules() as $rule ) {
			$this->assertInstanceOf( '\BeepBeepAI\AltTextGenerator\Audit\Audit_Rule_Interface', $rule );
		}
	}

	public function test_thresholds_filter_reaches_the_rules(): void {
		add_filter(
			'bbai_audit_rule_thresholds',
			static function ( $thresholds ) {
				$thresholds[ Audit_Issue::ALT_TOO_LONG ] = array( 'max_length' => 10 );
				return $thresholds;
			}
		);

		$registry = new Audit_Rule_Registry();
		$result   = $registry->evaluate(
			$this->context( array( 'alt' => 'A grey heron standing in shallow water at dawn' ) )
		);

		$this->assertContains( Audit_Issue::ALT_TOO_LONG, $result['codes'] );
	}
}
