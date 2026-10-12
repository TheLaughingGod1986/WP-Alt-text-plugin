<?php
/**
 * Run state, locking, resume and stale recovery.
 */

declare(strict_types=1);

use BeepBeepAI\AltTextGenerator\Audit\Audit_Progress_Service;
use PHPUnit\Framework\TestCase;

final class AuditProgressServiceTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['bbai_test_options'] = array();
	}

	/* ─── State ─── */

	public function test_a_fresh_site_reports_not_started(): void {
		$state = Audit_Progress_Service::get_state();

		$this->assertSame( Audit_Progress_Service::STATUS_NOT_STARTED, $state['status'] );
		$this->assertSame( 0, $state['total_images'] );
		$this->assertSame( 0, $state['processed_images'] );
		$this->assertFalse( Audit_Progress_Service::is_running( $state ) );
		$this->assertFalse( Audit_Progress_Service::can_resume( $state ) );
	}

	public function test_corrupt_stored_state_falls_back_to_a_clean_state(): void {
		update_option( Audit_Progress_Service::STATE_OPTION, 'not an array' );
		$this->assertSame( Audit_Progress_Service::STATUS_NOT_STARTED, Audit_Progress_Service::get_state()['status'] );

		update_option( Audit_Progress_Service::STATE_OPTION, array( 'status' => 'nonsense' ) );
		$this->assertSame( Audit_Progress_Service::STATUS_NOT_STARTED, Audit_Progress_Service::get_state()['status'] );
	}

	public function test_starting_a_run_records_the_scope(): void {
		$state = Audit_Progress_Service::start_run( 500, 7, 'run-a' );

		$this->assertSame( Audit_Progress_Service::STATUS_RUNNING, $state['status'] );
		$this->assertSame( 500, $state['total_images'] );
		$this->assertSame( 7, $state['started_by'] );
		$this->assertSame( 'run-a', $state['run_id'] );
		$this->assertTrue( Audit_Progress_Service::is_running( $state ) );
	}

	public function test_a_run_over_an_empty_library_completes_immediately(): void {
		$state = Audit_Progress_Service::start_run( 0, 1, 'run-empty' );

		$this->assertSame( Audit_Progress_Service::STATUS_COMPLETED, $state['status'] );
		$this->assertGreaterThan( 0, $state['completed_at'] );
	}

	/* ─── Progress ─── */

	public function test_percent_tracks_processed_over_total(): void {
		$state = Audit_Progress_Service::start_run( 200, 1, 'run-b' );

		$state['processed_images'] = 50;
		$this->assertSame( 25, Audit_Progress_Service::percent( $state ) );

		$state['processed_images'] = 200;
		$this->assertSame( 100, Audit_Progress_Service::percent( $state ) );
	}

	public function test_percent_cannot_exceed_one_hundred(): void {
		$state                     = Audit_Progress_Service::start_run( 10, 1, 'run-c' );
		$state['processed_images'] = 999;

		$this->assertSame( 100, Audit_Progress_Service::percent( $state ) );
	}

	public function test_percent_on_an_empty_library_reflects_completion(): void {
		$state = Audit_Progress_Service::start_run( 0, 1, 'run-d' );

		$this->assertSame( 100, Audit_Progress_Service::percent( $state ) );
	}

	/* ─── Resume ─── */

	public function test_an_incomplete_run_can_be_resumed(): void {
		$state                     = Audit_Progress_Service::start_run( 100, 1, 'run-e' );
		$state['processed_images'] = 40;

		$this->assertTrue( Audit_Progress_Service::can_resume( $state ) );
	}

	public function test_a_finished_run_cannot_be_resumed(): void {
		$state                     = Audit_Progress_Service::start_run( 100, 1, 'run-f' );
		$state['processed_images'] = 100;

		$this->assertFalse( Audit_Progress_Service::can_resume( $state ) );
	}

	public function test_a_failed_run_can_be_resumed_from_its_cursor(): void {
		$state                       = Audit_Progress_Service::start_run( 100, 1, 'run-g' );
		$state['processed_images']   = 30;
		$state['last_attachment_id'] = 4321;

		$failed = Audit_Progress_Service::fail_run( $state, 'stopped' );

		$this->assertSame( Audit_Progress_Service::STATUS_FAILED, $failed['status'] );
		$this->assertSame( 4321, $failed['last_attachment_id'] );
		$this->assertTrue( Audit_Progress_Service::can_resume( $failed ) );
	}

	public function test_a_cancelled_run_cannot_be_resumed(): void {
		$state                     = Audit_Progress_Service::start_run( 100, 1, 'run-h' );
		$state['processed_images'] = 30;

		$cancelled = Audit_Progress_Service::cancel_run( $state );

		$this->assertSame( Audit_Progress_Service::STATUS_CANCELLED, $cancelled['status'] );
		$this->assertFalse( Audit_Progress_Service::can_resume( $cancelled ) );
	}

	/* ─── Locking ─── */

	public function test_a_lock_can_be_taken_once(): void {
		$this->assertTrue( Audit_Progress_Service::acquire_lock( 'run-1', 1 ) );
		$this->assertFalse( Audit_Progress_Service::acquire_lock( 'run-2', 2 ) );
	}

	public function test_the_same_run_may_reacquire_its_own_lock(): void {
		Audit_Progress_Service::acquire_lock( 'run-1', 1 );

		// A retried request for the same run must not be treated as a collision.
		$this->assertTrue( Audit_Progress_Service::acquire_lock( 'run-1', 1 ) );
	}

	public function test_releasing_a_lock_frees_it(): void {
		Audit_Progress_Service::acquire_lock( 'run-1', 1 );
		Audit_Progress_Service::release_lock();

		$this->assertNull( Audit_Progress_Service::get_lock() );
		$this->assertTrue( Audit_Progress_Service::acquire_lock( 'run-2', 2 ) );
	}

	public function test_an_expired_lock_is_cleared_and_can_be_taken_over(): void {
		Audit_Progress_Service::acquire_lock( 'run-abandoned', 1 );

		// Simulate a request that died: expiry in the past, no heartbeat since.
		$lock               = Audit_Progress_Service::get_lock();
		$lock['expires_at'] = time() - 1;
		update_option( Audit_Progress_Service::LOCK_OPTION, $lock );

		$this->assertTrue( Audit_Progress_Service::clear_stale_lock() );
		$this->assertTrue( Audit_Progress_Service::acquire_lock( 'run-new', 2 ) );
		$this->assertSame( 'run-new', Audit_Progress_Service::get_lock()['run_id'] );
	}

	public function test_a_live_lock_is_not_cleared_as_stale(): void {
		Audit_Progress_Service::acquire_lock( 'run-live', 1 );

		$this->assertFalse( Audit_Progress_Service::clear_stale_lock() );
		$this->assertSame( 'run-live', Audit_Progress_Service::get_lock()['run_id'] );
	}

	public function test_refreshing_pushes_the_expiry_forward(): void {
		Audit_Progress_Service::acquire_lock( 'run-live', 1 );

		$lock               = Audit_Progress_Service::get_lock();
		$lock['expires_at'] = time() + 5;
		update_option( Audit_Progress_Service::LOCK_OPTION, $lock );

		Audit_Progress_Service::refresh_lock( 'run-live' );

		$this->assertGreaterThan( time() + 5, Audit_Progress_Service::get_lock()['expires_at'] );
	}

	public function test_refreshing_someone_elses_lock_does_nothing(): void {
		Audit_Progress_Service::acquire_lock( 'run-live', 1 );
		$before = Audit_Progress_Service::get_lock()['expires_at'];

		Audit_Progress_Service::refresh_lock( 'a-different-run' );

		$this->assertSame( $before, Audit_Progress_Service::get_lock()['expires_at'] );
	}

	public function test_completing_a_run_releases_the_lock_and_records_the_time(): void {
		$state = Audit_Progress_Service::start_run( 10, 1, 'run-i' );
		Audit_Progress_Service::acquire_lock( 'run-i', 1 );

		$completed = Audit_Progress_Service::complete_run( $state );

		$this->assertSame( Audit_Progress_Service::STATUS_COMPLETED, $completed['status'] );
		$this->assertNull( Audit_Progress_Service::get_lock() );
		$this->assertGreaterThan( 0, Audit_Progress_Service::last_completed_at() );
	}

	public function test_cancelling_and_failing_both_release_the_lock(): void {
		$state = Audit_Progress_Service::start_run( 10, 1, 'run-j' );

		Audit_Progress_Service::acquire_lock( 'run-j', 1 );
		Audit_Progress_Service::cancel_run( $state );
		$this->assertNull( Audit_Progress_Service::get_lock() );

		Audit_Progress_Service::acquire_lock( 'run-j', 1 );
		Audit_Progress_Service::fail_run( $state, 'boom' );
		$this->assertNull( Audit_Progress_Service::get_lock() );
	}

	/* ─── Staleness ─── */

	public function test_a_run_with_no_recent_heartbeat_reads_as_stale(): void {
		$state               = Audit_Progress_Service::start_run( 100, 1, 'run-k' );
		$state['updated_at'] = time() - ( Audit_Progress_Service::LOCK_TTL + 60 );

		$this->assertTrue( Audit_Progress_Service::is_stale( $state ) );
	}

	public function test_an_active_run_is_not_stale(): void {
		$this->assertFalse( Audit_Progress_Service::is_stale( Audit_Progress_Service::start_run( 100, 1, 'run-l' ) ) );
	}

	public function test_a_finished_run_is_never_stale(): void {
		$state = Audit_Progress_Service::start_run( 10, 1, 'run-m' );
		$state = Audit_Progress_Service::complete_run( $state );

		$state['updated_at'] = time() - 100000;

		$this->assertFalse( Audit_Progress_Service::is_stale( $state ) );
	}

	/* ─── Duplicate map ─── */

	public function test_the_duplicate_map_round_trips(): void {
		Audit_Progress_Service::save_duplicate_map( array( 'abc' => 4 ), true );

		$stored = Audit_Progress_Service::get_duplicate_map();

		$this->assertSame( array( 'abc' => 4 ), $stored['map'] );
		$this->assertTrue( $stored['truncated'] );
	}

	public function test_a_missing_duplicate_map_reads_as_empty(): void {
		$stored = Audit_Progress_Service::get_duplicate_map();

		$this->assertSame( array(), $stored['map'] );
		$this->assertFalse( $stored['truncated'] );
	}

	public function test_finishing_a_run_discards_the_duplicate_map(): void {
		Audit_Progress_Service::save_duplicate_map( array( 'abc' => 4 ), false );

		Audit_Progress_Service::complete_run( Audit_Progress_Service::start_run( 10, 1, 'run-n' ) );

		$this->assertSame( array(), Audit_Progress_Service::get_duplicate_map()['map'] );
	}

	/* ─── Reset ─── */

	public function test_reset_clears_everything(): void {
		Audit_Progress_Service::start_run( 10, 1, 'run-o' );
		Audit_Progress_Service::acquire_lock( 'run-o', 1 );
		Audit_Progress_Service::save_summary( array( 'score' => 50 ) );
		Audit_Progress_Service::save_duplicate_map( array( 'x' => 2 ), false );

		Audit_Progress_Service::reset();

		$this->assertSame( Audit_Progress_Service::STATUS_NOT_STARTED, Audit_Progress_Service::get_state()['status'] );
		$this->assertNull( Audit_Progress_Service::get_summary() );
		$this->assertNull( Audit_Progress_Service::get_lock() );
		$this->assertSame( 0, Audit_Progress_Service::last_completed_at() );
	}
}
