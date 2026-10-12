<?php
/**
 * Issue catalogue: severities, encoding, and copy safety.
 */

declare(strict_types=1);

use BeepBeepAI\AltTextGenerator\Audit\Audit_Issue;
use PHPUnit\Framework\TestCase;

final class AuditIssueCatalogueTest extends TestCase {

	protected function setUp(): void {
		remove_all_filters( 'bbai_audit_issue_definition' );
	}

	protected function tearDown(): void {
		remove_all_filters( 'bbai_audit_issue_definition' );
	}

	public function test_every_code_has_a_complete_definition(): void {
		foreach ( Audit_Issue::all_codes() as $code ) {
			$definition = Audit_Issue::definition( $code );

			$this->assertIsArray( $definition, $code );

			foreach ( array( 'code', 'label', 'severity', 'explanation', 'recommended_action' ) as $key ) {
				$this->assertArrayHasKey( $key, $definition, $code . '.' . $key );
				$this->assertNotSame( '', $definition[ $key ], $code . '.' . $key . ' is empty' );
			}

			$this->assertArrayHasKey( 'auto_fixable', $definition );
			$this->assertArrayHasKey( 'requires_review', $definition );
			$this->assertSame( $code, $definition['code'] );
			$this->assertTrue( Audit_Issue::is_valid_severity( $definition['severity'] ), $code );
		}
	}

	public function test_unknown_codes_have_no_definition(): void {
		$this->assertNull( Audit_Issue::definition( 'not_a_real_code' ) );
		$this->assertNull( Audit_Issue::definition( '' ) );
	}

	public function test_severity_ordering(): void {
		$this->assertGreaterThan(
			Audit_Issue::severity_rank( Audit_Issue::SEVERITY_WARNING ),
			Audit_Issue::severity_rank( Audit_Issue::SEVERITY_CRITICAL )
		);
		$this->assertGreaterThan(
			Audit_Issue::severity_rank( Audit_Issue::SEVERITY_REVIEW ),
			Audit_Issue::severity_rank( Audit_Issue::SEVERITY_WARNING )
		);
		$this->assertGreaterThan(
			Audit_Issue::severity_rank( Audit_Issue::SEVERITY_INFORMATION ),
			Audit_Issue::severity_rank( Audit_Issue::SEVERITY_REVIEW )
		);
		$this->assertSame( 0, Audit_Issue::severity_rank( 'nonsense' ) );
	}

	public function test_worst_severity_picks_the_most_serious_issue(): void {
		$this->assertSame(
			Audit_Issue::SEVERITY_CRITICAL,
			Audit_Issue::worst_severity( array( Audit_Issue::ALT_TOO_LONG, Audit_Issue::MISSING_ALT ) )
		);

		$this->assertSame(
			Audit_Issue::SEVERITY_WARNING,
			Audit_Issue::worst_severity( array( Audit_Issue::NO_USAGE_CONTEXT, Audit_Issue::GENERIC_ALT ) )
		);

		// No issues is reported, not alarming.
		$this->assertSame( Audit_Issue::SEVERITY_INFORMATION, Audit_Issue::worst_severity( array() ) );

		// Unknown codes cannot inflate severity.
		$this->assertSame( Audit_Issue::SEVERITY_INFORMATION, Audit_Issue::worst_severity( array( 'bogus' ) ) );
	}

	public function test_codes_round_trip_through_storage_encoding(): void {
		$codes   = array( Audit_Issue::GENERIC_ALT, Audit_Issue::MISSING_ALT );
		$encoded = Audit_Issue::encode_codes( $codes );

		$this->assertSame( ',generic_alt,missing_alt,', $encoded );
		$this->assertSame( array( Audit_Issue::GENERIC_ALT, Audit_Issue::MISSING_ALT ), Audit_Issue::decode_codes( $encoded ) );
	}

	public function test_encoding_is_sorted_deduplicated_and_filters_unknown_codes(): void {
		$encoded = Audit_Issue::encode_codes(
			array( Audit_Issue::MISSING_ALT, 'bogus', Audit_Issue::MISSING_ALT, Audit_Issue::ALT_TOO_LONG )
		);

		$this->assertSame( ',alt_too_long,missing_alt,', $encoded );
	}

	public function test_no_issues_encodes_to_an_empty_string(): void {
		$this->assertSame( '', Audit_Issue::encode_codes( array() ) );
		$this->assertSame( '', Audit_Issue::encode_codes( array( 'bogus' ) ) );
		$this->assertSame( array(), Audit_Issue::decode_codes( '' ) );
		$this->assertSame( array(), Audit_Issue::decode_codes( ',' ) );
	}

	public function test_encoded_codes_are_delimited_so_like_matching_cannot_collide(): void {
		$encoded = Audit_Issue::encode_codes( array( Audit_Issue::ALT_TOO_LONG ) );

		// ",alt_too_long," must not match a search for ",alt_too_short,".
		$this->assertStringContainsString( ',alt_too_long,', $encoded );
		$this->assertStringNotContainsString( ',alt_too_short,', $encoded );
	}

	public function test_definition_filter_is_applied(): void {
		add_filter(
			'bbai_audit_issue_definition',
			static function ( $definition, $code ) {
				if ( Audit_Issue::MISSING_ALT === $code ) {
					$definition['label'] = 'Filtered label';
				}
				return $definition;
			}
		);

		$definition = Audit_Issue::definition( Audit_Issue::MISSING_ALT );

		$this->assertSame( 'Filtered label', $definition['label'] );
	}

	public function test_catalogue_copy_makes_no_compliance_claims(): void {
		$forbidden = array(
			'wcag compliant',
			'wcag-compliant',
			'guaranteed accessible',
			'legally compliant',
			'perfect accessibility',
			'ai-certified',
			'ai certified',
			'fully accessible',
		);

		foreach ( Audit_Issue::definitions() as $code => $definition ) {
			$copy = strtolower(
				$definition['label'] . ' ' . $definition['explanation'] . ' ' . $definition['recommended_action']
			);

			foreach ( $forbidden as $phrase ) {
				$this->assertStringNotContainsString( $phrase, $copy, $code . ' makes a compliance claim' );
			}
		}
	}

	public function test_empty_alt_copy_explains_that_decorative_images_may_be_empty(): void {
		$definition = Audit_Issue::definition( Audit_Issue::EMPTY_ALT_REVIEW_REQUIRED );
		$copy       = strtolower( $definition['explanation'] . ' ' . $definition['recommended_action'] );

		$this->assertStringContainsString( 'decorative', $copy );
	}
}
