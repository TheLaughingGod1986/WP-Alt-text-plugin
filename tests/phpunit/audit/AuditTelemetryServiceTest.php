<?php
/**
 * Audit telemetry: buckets, de-duplication, and what must never leave the site.
 */

declare(strict_types=1);

use BeepBeepAI\AltTextGenerator\Audit\Audit_Telemetry_Service;
use PHPUnit\Framework\TestCase;

final class AuditTelemetryServiceTest extends TestCase {

	/** @var array<int,array> */
	private $captured = array();

	protected function setUp(): void {
		Audit_Telemetry_Service::reset();
		remove_all_actions( 'bbai_telemetry_event' );

		$this->captured = array();

		add_action(
			'bbai_telemetry_event',
			function ( $envelope ) {
				$this->captured[] = $envelope;
			}
		);
	}

	protected function tearDown(): void {
		Audit_Telemetry_Service::reset();
		remove_all_actions( 'bbai_telemetry_event' );
	}

	/**
	 * Properties of the most recent captured event.
	 */
	private function last_properties(): array {
		$last = end( $this->captured );

		return is_array( $last ) && isset( $last['properties'] ) ? $last['properties'] : array();
	}

	/* ─── Event surface ─── */

	public function test_every_event_named_in_the_brief_is_supported(): void {
		$expected = array(
			'image_audit_started',
			'image_audit_batch_completed',
			'image_audit_completed',
			'image_audit_failed',
			'image_audit_cancelled',
			'image_audit_results_viewed',
			'image_audit_filter_used',
			'image_issue_action_clicked',
			'image_marked_decorative',
			'image_issue_resolved',
			'image_audit_upgrade_cta_clicked',
		);

		foreach ( $expected as $event ) {
			$this->assertContains( $event, Audit_Telemetry_Service::events(), $event . ' missing' );
		}
	}

	public function test_unknown_events_are_refused(): void {
		$this->assertFalse( Audit_Telemetry_Service::emit( 'something_made_up', array() ) );
		$this->assertSame( array(), $this->captured );
	}

	public function test_every_event_declares_its_allowed_properties(): void {
		$allowed = Audit_Telemetry_Service::allowed_properties();

		foreach ( Audit_Telemetry_Service::events() as $event ) {
			$this->assertArrayHasKey( $event, $allowed, $event . ' has no property whitelist' );
		}
	}

	/* ─── Privacy ─── */

	public function test_properties_outside_the_whitelist_are_dropped(): void {
		Audit_Telemetry_Service::emit(
			Audit_Telemetry_Service::COMPLETED,
			array(
				'score_bucket'  => '75_89',
				'image_url'     => 'https://example.com/wp-content/uploads/private.jpg',
				'alt_text'      => 'A photograph of the CEO at home',
				'file_name'     => 'ceo-home-address.jpg',
				'api_key'       => 'sk-secret',
				'user_email'    => 'someone@example.com',
				'post_content'  => 'Draft copy nobody should see',
			)
		);

		$properties = $this->last_properties();

		$this->assertSame( '75_89', $properties['score_bucket'] );

		foreach ( array( 'image_url', 'alt_text', 'file_name', 'api_key', 'user_email', 'post_content' ) as $forbidden ) {
			$this->assertArrayNotHasKey( $forbidden, $properties, $forbidden . ' must never be sent' );
		}
	}

	public function test_no_event_whitelists_a_free_text_or_identifying_property(): void {
		$banned = array( 'url', 'alt', 'alt_text', 'file_name', 'filename', 'path', 'title', 'email', 'user', 'api_key', 'token', 'content', 'message' );

		foreach ( Audit_Telemetry_Service::allowed_properties() as $event => $keys ) {
			foreach ( $keys as $key ) {
				$this->assertNotContains( $key, $banned, $event . ' whitelists a sensitive property: ' . $key );
			}
		}
	}

	public function test_string_properties_are_reduced_to_slugs(): void {
		Audit_Telemetry_Service::emit(
			Audit_Telemetry_Service::ACTION_CLICKED,
			array(
				'action_type' => 'Generate <script>alert(1)</script>',
				'issue_type'  => 'missing_alt',
			)
		);

		$properties = $this->last_properties();

		$this->assertStringNotContainsString( '<', $properties['action_type'] );
		$this->assertStringNotContainsString( ' ', $properties['action_type'] );
		$this->assertSame( 'missing_alt', $properties['issue_type'] );
	}

	public function test_long_strings_are_truncated(): void {
		Audit_Telemetry_Service::emit(
			Audit_Telemetry_Service::FAILED,
			array( 'error_type' => str_repeat( 'a', 500 ) )
		);

		$this->assertLessThanOrEqual( 40, strlen( $this->last_properties()['error_type'] ) );
	}

	/* ─── De-duplication ─── */

	public function test_an_event_fires_once_per_key(): void {
		$this->assertTrue( Audit_Telemetry_Service::emit( Audit_Telemetry_Service::STARTED, array(), 'run-1' ) );
		$this->assertFalse( Audit_Telemetry_Service::emit( Audit_Telemetry_Service::STARTED, array(), 'run-1' ) );

		$this->assertCount( 1, $this->captured );
	}

	public function test_a_different_key_fires_again(): void {
		Audit_Telemetry_Service::emit( Audit_Telemetry_Service::STARTED, array(), 'run-1' );
		Audit_Telemetry_Service::emit( Audit_Telemetry_Service::STARTED, array(), 'run-2' );

		$this->assertCount( 2, $this->captured );
	}

	public function test_keyless_events_fire_once_per_request(): void {
		Audit_Telemetry_Service::emit( Audit_Telemetry_Service::RESULTS_VIEWED, array() );
		Audit_Telemetry_Service::emit( Audit_Telemetry_Service::RESULTS_VIEWED, array() );

		$this->assertCount( 1, $this->captured, 'a page view must not report itself twice' );
	}

	/* ─── Batch sampling ─── */

	public function test_batch_progress_is_sampled_not_reported_every_batch(): void {
		$reported = 0;

		// A 10,000 image library runs 100 batches; telemetry must not scale with it.
		for ( $batch = 1; $batch <= 100; $batch++ ) {
			if ( Audit_Telemetry_Service::should_report_batch( $batch ) ) {
				++$reported;
			}
		}

		$this->assertLessThanOrEqual( 6, $reported, 'batch events must stay flat as libraries grow' );
		$this->assertGreaterThan( 0, $reported );
	}

	public function test_the_first_batch_always_reports(): void {
		// Batch one is the one that proves a scan got past setup.
		$this->assertTrue( Audit_Telemetry_Service::should_report_batch( 1 ) );
		$this->assertFalse( Audit_Telemetry_Service::should_report_batch( 2 ) );
		$this->assertFalse( Audit_Telemetry_Service::should_report_batch( 0 ) );
		$this->assertFalse( Audit_Telemetry_Service::should_report_batch( -5 ) );
	}

	public function test_batch_sampling_interval_is_filterable(): void {
		add_filter(
			'bbai_audit_batch_event_interval',
			static function () {
				return 10;
			}
		);

		$this->assertTrue( Audit_Telemetry_Service::should_report_batch( 10 ) );
		$this->assertFalse( Audit_Telemetry_Service::should_report_batch( 25 ) );

		remove_all_filters( 'bbai_audit_batch_event_interval' );
	}

	public function test_batch_events_are_keyed_per_batch(): void {
		Audit_Telemetry_Service::emit( Audit_Telemetry_Service::BATCH_COMPLETED, array( 'batch_number' => 1 ), 'run-1:1' );
		Audit_Telemetry_Service::emit( Audit_Telemetry_Service::BATCH_COMPLETED, array( 'batch_number' => 2 ), 'run-1:2' );
		Audit_Telemetry_Service::emit( Audit_Telemetry_Service::BATCH_COMPLETED, array( 'batch_number' => 2 ), 'run-1:2' );

		$this->assertCount( 2, $this->captured );
	}

	/* ─── Buckets ─── */

	public function test_size_buckets(): void {
		$this->assertSame( '0', Audit_Telemetry_Service::size_bucket( 0 ) );
		$this->assertSame( '1_50', Audit_Telemetry_Service::size_bucket( 1 ) );
		$this->assertSame( '1_50', Audit_Telemetry_Service::size_bucket( 50 ) );
		$this->assertSame( '51_500', Audit_Telemetry_Service::size_bucket( 51 ) );
		$this->assertSame( '501_5000', Audit_Telemetry_Service::size_bucket( 5000 ) );
		$this->assertSame( '5000_plus', Audit_Telemetry_Service::size_bucket( 5001 ) );
	}

	public function test_issue_buckets(): void {
		$this->assertSame( '0', Audit_Telemetry_Service::issue_bucket( 0 ) );
		$this->assertSame( '1_10', Audit_Telemetry_Service::issue_bucket( 10 ) );
		$this->assertSame( '11_100', Audit_Telemetry_Service::issue_bucket( 11 ) );
		$this->assertSame( '101_1000', Audit_Telemetry_Service::issue_bucket( 1000 ) );
		$this->assertSame( '1000_plus', Audit_Telemetry_Service::issue_bucket( 1001 ) );
	}

	public function test_duration_buckets(): void {
		$this->assertSame( 'under_5s', Audit_Telemetry_Service::duration_bucket( 4 ) );
		$this->assertSame( '5_30s', Audit_Telemetry_Service::duration_bucket( 30 ) );
		$this->assertSame( '30_120s', Audit_Telemetry_Service::duration_bucket( 120 ) );
		$this->assertSame( '2_10m', Audit_Telemetry_Service::duration_bucket( 600 ) );
		$this->assertSame( 'over_10m', Audit_Telemetry_Service::duration_bucket( 601 ) );
	}

	public function test_percent_buckets_are_coarse(): void {
		$this->assertSame( '0', Audit_Telemetry_Service::percent_bucket( 0 ) );
		$this->assertSame( '40', Audit_Telemetry_Service::percent_bucket( 47 ) );
		$this->assertSame( '100', Audit_Telemetry_Service::percent_bucket( 100 ) );
		$this->assertSame( '0', Audit_Telemetry_Service::percent_bucket( -20 ) );
	}

	public function test_score_buckets_track_the_score_service_bands(): void {
		$this->assertSame( '90_100', Audit_Telemetry_Service::score_bucket( 95 ) );
		$this->assertSame( '0_24', Audit_Telemetry_Service::score_bucket( 3 ) );
	}

	public function test_buckets_never_reveal_an_exact_count(): void {
		// A one-image site and a fifty-image site must be indistinguishable.
		$this->assertSame(
			Audit_Telemetry_Service::size_bucket( 1 ),
			Audit_Telemetry_Service::size_bucket( 50 )
		);
	}
}
