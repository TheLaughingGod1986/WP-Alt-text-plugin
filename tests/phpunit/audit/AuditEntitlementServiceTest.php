<?php
/**
 * Plan gating for the audit.
 */

declare(strict_types=1);

use BeepBeepAI\AltTextGenerator\Audit\Audit_Entitlement_Service;
use PHPUnit\Framework\TestCase;

final class AuditEntitlementServiceTest extends TestCase {

	protected function setUp(): void {
		remove_all_filters( 'bbai_audit_capability' );
		remove_all_filters( 'bbai_audit_free_result_limit' );
	}

	protected function tearDown(): void {
		remove_all_filters( 'bbai_audit_capability' );
		remove_all_filters( 'bbai_audit_free_result_limit' );
	}

	/**
	 * Pretend to be a given plan by filtering the capability resolution's input.
	 */
	private function as_plan( bool $paid ): void {
		add_filter(
			'bbai_audit_capability',
			static function ( $allowed, $capability ) use ( $paid ) {
				if ( in_array(
					$capability,
					array(
						Audit_Entitlement_Service::VIEW_ALL_AUDIT_RESULTS,
						Audit_Entitlement_Service::USE_BULK_REMEDIATION,
					),
					true
				) ) {
					return $paid;
				}

				return $allowed;
			}
		);
	}

	public function test_every_capability_named_in_the_brief_exists(): void {
		$capabilities = Audit_Entitlement_Service::capabilities();

		foreach ( array( 'run_full_audit', 'view_all_audit_results', 'use_bulk_remediation', 'enable_continuous_monitoring' ) as $expected ) {
			$this->assertContains( $expected, $capabilities, $expected . ' missing' );
		}
	}

	public function test_anyone_may_run_an_audit_of_their_own_site(): void {
		$this->as_plan( false );

		$this->assertTrue( Audit_Entitlement_Service::can( Audit_Entitlement_Service::RUN_FULL_AUDIT ) );
	}

	public function test_free_plans_cannot_see_every_result_or_use_bulk_actions(): void {
		$this->as_plan( false );

		$this->assertFalse( Audit_Entitlement_Service::can( Audit_Entitlement_Service::VIEW_ALL_AUDIT_RESULTS ) );
		$this->assertFalse( Audit_Entitlement_Service::can( Audit_Entitlement_Service::USE_BULK_REMEDIATION ) );
	}

	public function test_paid_plans_unlock_the_full_list_and_bulk_actions(): void {
		$this->as_plan( true );

		$this->assertTrue( Audit_Entitlement_Service::can( Audit_Entitlement_Service::VIEW_ALL_AUDIT_RESULTS ) );
		$this->assertTrue( Audit_Entitlement_Service::can( Audit_Entitlement_Service::USE_BULK_REMEDIATION ) );
	}

	public function test_continuous_monitoring_is_off_for_everyone_because_it_is_not_built(): void {
		$this->as_plan( true );

		$this->assertFalse( Audit_Entitlement_Service::can( Audit_Entitlement_Service::ENABLE_CONTINUOUS_MONITOR ) );
	}

	public function test_unknown_capabilities_are_denied(): void {
		$this->assertFalse( Audit_Entitlement_Service::can( 'do_whatever_i_like' ) );
		$this->assertFalse( Audit_Entitlement_Service::can( '' ) );
	}

	public function test_a_free_plan_result_limit_applies(): void {
		$this->as_plan( false );

		$this->assertSame( Audit_Entitlement_Service::FREE_RESULT_LIMIT, Audit_Entitlement_Service::result_limit() );
		$this->assertTrue( Audit_Entitlement_Service::is_limited( 100 ) );
		$this->assertFalse( Audit_Entitlement_Service::is_limited( 10 ) );
		$this->assertFalse( Audit_Entitlement_Service::is_limited( Audit_Entitlement_Service::FREE_RESULT_LIMIT ) );
	}

	public function test_a_paid_plan_has_no_result_limit(): void {
		$this->as_plan( true );

		$this->assertSame( 0, Audit_Entitlement_Service::result_limit() );
		$this->assertFalse( Audit_Entitlement_Service::is_limited( 100000 ) );
	}

	public function test_the_free_limit_is_a_single_filterable_constant(): void {
		$this->as_plan( false );

		add_filter(
			'bbai_audit_free_result_limit',
			static function () {
				return 5;
			}
		);

		$this->assertSame( 5, Audit_Entitlement_Service::result_limit() );
		$this->assertTrue( Audit_Entitlement_Service::is_limited( 6 ) );
	}

	public function test_plan_detection_degrades_safely_when_unavailable(): void {
		// Plan_Helpers is not loaded in this stub environment, so the service must
		// still return a usable shape rather than throwing.
		$plan = Audit_Entitlement_Service::plan();

		$this->assertArrayHasKey( 'plan_slug', $plan );
		$this->assertArrayHasKey( 'is_paid', $plan );
		$this->assertSame( 'free', Audit_Entitlement_Service::plan_slug() );
	}

	public function test_capability_decisions_are_filterable(): void {
		add_filter(
			'bbai_audit_capability',
			static function ( $allowed, $capability ) {
				return Audit_Entitlement_Service::ENABLE_CONTINUOUS_MONITOR === $capability ? true : $allowed;
			}
		);

		$this->assertTrue( Audit_Entitlement_Service::can( Audit_Entitlement_Service::ENABLE_CONTINUOUS_MONITOR ) );
	}
}
