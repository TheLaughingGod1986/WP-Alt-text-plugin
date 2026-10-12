<?php
/**
 * Orchestration: start, refuse, resume, tick, cancel.
 */

declare(strict_types=1);

use BeepBeepAI\AltTextGenerator\Audit\Audit_Batch_Processor;
use BeepBeepAI\AltTextGenerator\Audit\Audit_Progress_Service;
use BeepBeepAI\AltTextGenerator\Audit\Image_Audit_Repository;
use BeepBeepAI\AltTextGenerator\Audit\Image_Audit_Service;
use BeepBeepAI\AltTextGenerator\Audit\Image_Discovery_Service;
use PHPUnit\Framework\TestCase;

/**
 * Discovery double with a scripted library size and duplicate map.
 */
final class ScriptedDiscoveryService extends Image_Discovery_Service {

	public $total      = 0;
	public $batches    = array();
	public $rows       = array();
	public $duplicates = array(
		'map'       => array(),
		'truncated' => false,
	);

	public function count_images() {
		return $this->total;
	}

	public function build_duplicate_map() {
		return $this->duplicates;
	}

	public function get_batch( $after_attachment_id, $size, array $duplicate_map = array() ) {
		return empty( $this->batches ) ? array() : array_shift( $this->batches );
	}

	public function get_batch_rows( $after_attachment_id, $size ) {
		return $this->rows;
	}

	public function resolve_usage( array $ids ) {
		return array(
			'known'    => true,
			'featured' => array(),
		);
	}
}

final class ImageAuditServiceTest extends TestCase {

	/** @var ScriptedDiscoveryService */
	private $discovery;

	/** @var Image_Audit_Service */
	private $service;

	protected function setUp(): void {
		$GLOBALS['wpdb']              = new BBAI_Fake_WPDB();
		$GLOBALS['bbai_test_options'] = array( 'beepbeepai_site_id' => 'test_site_install_id_0123456789ab' );

		// Convince the repository its table is present.
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-db.php';
		$GLOBALS['wpdb']->var_result = 'wptest_bbai_image_audit';
		Image_Audit_Repository::table_exists( true );

		$this->discovery = new ScriptedDiscoveryService();
		$this->service   = new Image_Audit_Service(
			$this->discovery,
			new Audit_Batch_Processor( $this->discovery )
		);
	}

	/* ─── Starting ─── */

	public function test_starting_a_run_reports_the_scope_and_takes_the_lock(): void {
		$this->discovery->total = 250;

		$result = $this->service->start( 3 );

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( 250, $result['total_images'] );
		$this->assertSame( Audit_Progress_Service::STATUS_RUNNING, $result['status'] );
		$this->assertNotSame( '', $result['run_id'] );
		$this->assertNotNull( Audit_Progress_Service::get_lock() );
	}

	public function test_an_empty_library_completes_without_a_batch(): void {
		$this->discovery->total = 0;

		$result = $this->service->start( 3 );

		$this->assertSame( Audit_Progress_Service::STATUS_COMPLETED, $result['status'] );
		$this->assertTrue( $result['done'] );
		$this->assertNull( Audit_Progress_Service::get_lock(), 'A finished run must not hold the lock' );
	}

	public function test_a_second_start_is_refused_rather_than_silently_restarting(): void {
		$this->discovery->total = 500;
		$this->service->start( 3 );

		$again = $this->service->start( 4 );

		$this->assertTrue( is_wp_error( $again ) );
		$this->assertSame( 'bbai_audit_in_progress', $again->get_error_code() );
		$this->assertSame( 409, $again->get_error_data()['status'] );
		// The refusal carries the existing run so the UI can offer Resume.
		$this->assertArrayHasKey( 'state', $again->get_error_data() );
	}

	public function test_an_explicit_restart_discards_the_partial_run(): void {
		$this->discovery->total = 500;
		$first                  = $this->service->start( 3 );

		$restarted = $this->service->start( 3, true );

		$this->assertFalse( is_wp_error( $restarted ) );
		$this->assertNotSame( $first['run_id'], $restarted['run_id'] );
	}

	public function test_a_restart_cannot_steal_a_lock_belonging_to_a_different_run(): void {
		$this->discovery->total = 500;
		$this->service->start( 3 );

		// Another live run holds the lock — a "start over" here must still be refused.
		Audit_Progress_Service::release_lock();
		Audit_Progress_Service::acquire_lock( 'someone-elses-run', 9 );

		$result = $this->service->start( 3, true );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'bbai_audit_locked', $result->get_error_code() );
		$this->assertSame( 'someone-elses-run', Audit_Progress_Service::get_lock()['run_id'] );
	}

	public function test_a_stale_run_does_not_block_a_new_one(): void {
		$this->discovery->total = 500;
		$this->service->start( 3 );

		// Simulate the browser tab closing mid-scan.
		$state               = Audit_Progress_Service::get_state();
		$state['updated_at'] = time() - ( Audit_Progress_Service::LOCK_TTL + 60 );
		update_option( Audit_Progress_Service::STATE_OPTION, $state );

		$lock               = Audit_Progress_Service::get_lock();
		$lock['expires_at'] = time() - 1;
		update_option( Audit_Progress_Service::LOCK_OPTION, $lock );

		$this->assertFalse( is_wp_error( $this->service->start( 4 ) ) );
	}

	public function test_a_missing_table_is_reported_rather_than_fataling(): void {
		$GLOBALS['wpdb']->var_result = null;
		Image_Audit_Repository::table_exists( true );

		$result = $this->service->start( 3 );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'bbai_audit_no_table', $result->get_error_code() );
	}

	public function test_starting_stores_the_duplicate_map_for_the_run(): void {
		$this->discovery->total      = 10;
		$this->discovery->duplicates = array(
			'map'       => array( 'hash-a' => 4 ),
			'truncated' => true,
		);

		$result = $this->service->start( 3 );

		$this->assertSame( array( 'hash-a' => 4 ), Audit_Progress_Service::get_duplicate_map()['map'] );
		$this->assertTrue( $result['duplicate_truncated'] );
	}

	/* ─── Resuming ─── */

	public function test_an_interrupted_run_can_be_resumed(): void {
		$this->discovery->total = 500;
		$started                = $this->service->start( 3 );

		$state                     = Audit_Progress_Service::get_state();
		$state['processed_images'] = 100;
		$state                     = Audit_Progress_Service::fail_run( $state, 'interrupted' );

		$resumed = $this->service->resume( 3 );

		$this->assertFalse( is_wp_error( $resumed ) );
		$this->assertSame( Audit_Progress_Service::STATUS_RUNNING, $resumed['status'] );
		$this->assertSame( $started['run_id'], $resumed['run_id'], 'Resuming must continue the same run' );
		$this->assertSame( 100, $resumed['processed_images'] );
	}

	public function test_resuming_with_nothing_to_resume_is_refused(): void {
		$result = $this->service->resume( 3 );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'bbai_audit_not_resumable', $result->get_error_code() );
	}

	/* ─── Ticking ─── */

	public function test_a_tick_for_a_different_run_is_rejected(): void {
		$this->discovery->total = 500;
		$this->service->start( 3 );

		$result = $this->service->tick( 'some-other-run' );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'bbai_audit_run_mismatch', $result->get_error_code() );
	}

	public function test_a_tick_on_a_finished_run_just_reports_state(): void {
		$this->discovery->total = 0;
		$started                = $this->service->start( 3 );

		$result = $this->service->tick( $started['run_id'] );

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertTrue( $result['done'] );
	}

	public function test_running_out_of_images_completes_the_run_and_releases_the_lock(): void {
		$this->discovery->total   = 5;
		$this->discovery->batches = array( array() );

		$started = $this->service->start( 3 );
		$result  = $this->service->tick( $started['run_id'] );

		$this->assertSame( Audit_Progress_Service::STATUS_COMPLETED, $result['status'] );
		$this->assertNull( Audit_Progress_Service::get_lock() );
		$this->assertGreaterThan( 0, Audit_Progress_Service::last_completed_at() );
	}

	public function test_completing_a_run_stores_a_summary(): void {
		$this->discovery->total          = 5;
		$this->discovery->batches        = array( array() );
		$GLOBALS['wpdb']->results_result = array();

		$started = $this->service->start( 3 );
		$this->service->tick( $started['run_id'] );

		$summary = Audit_Progress_Service::get_summary();

		$this->assertIsArray( $summary );
		$this->assertArrayHasKey( 'score', $summary );
		$this->assertArrayHasKey( 'missing_alt_count', $summary );
		$this->assertSame( Audit_Progress_Service::STATUS_COMPLETED, $summary['run_status'] );
	}

	/* ─── Cancelling ─── */

	public function test_cancelling_stops_the_run_and_frees_the_lock(): void {
		$this->discovery->total = 500;
		$this->service->start( 3 );

		$result = $this->service->cancel();

		$this->assertSame( Audit_Progress_Service::STATUS_CANCELLED, $result['status'] );
		$this->assertTrue( $result['done'] );
		$this->assertNull( Audit_Progress_Service::get_lock() );
	}

	public function test_cancelling_keeps_partial_findings_and_refreshes_the_summary(): void {
		$this->discovery->total = 500;
		$this->service->start( 3 );

		$this->service->cancel();

		$this->assertIsArray( Audit_Progress_Service::get_summary() );
	}

	public function test_cancelling_when_nothing_runs_is_harmless(): void {
		$result = $this->service->cancel();

		$this->assertSame( Audit_Progress_Service::STATUS_NOT_STARTED, $result['status'] );
	}

	/* ─── Single-image rescan ─── */

	public function test_rescanning_an_image_updates_its_finding(): void {
		$this->discovery->rows = array(
			array(
				'ID'             => 42,
				'post_title'     => 'Heron',
				'post_parent'    => 3,
				'post_mime_type' => 'image/jpeg',
				'alt_text'       => 'A grey heron standing in shallow water at dawn',
				'attached_file'  => '2026/01/heron.jpg',
			),
		);

		$result = $this->service->rescan_attachment( 42 );

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( 42, $result['attachment_id'] );
		$this->assertTrue( $result['resolved'], 'good alt text leaves no findings' );

		$writes = array_filter(
			$GLOBALS['wpdb']->queries,
			static function ( $query ) {
				return false !== strpos( $query, 'INSERT INTO' );
			}
		);
		$this->assertNotEmpty( $writes, 'the finding must be written back' );
	}

	public function test_rescanning_still_reports_outstanding_findings(): void {
		$this->discovery->rows = array(
			array(
				'ID'             => 42,
				'post_parent'    => 3,
				'post_mime_type' => 'image/jpeg',
				'alt_text'       => null,
				'attached_file'  => '2026/01/heron.jpg',
			),
		);

		$result = $this->service->rescan_attachment( 42 );

		$this->assertFalse( $result['resolved'] );
		$this->assertContains( 'missing_alt', $result['issue_codes'] );
		$this->assertSame( 'critical', $result['severity'] );
	}

	public function test_rescanning_a_deleted_image_removes_its_row(): void {
		$this->discovery->rows = array();

		$result = $this->service->rescan_attachment( 42 );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'bbai_audit_attachment_missing', $result->get_error_code() );
		$this->assertCount( 1, $GLOBALS['wpdb']->deletes, 'a ghost row must be cleaned up' );
	}

	public function test_rescanning_rejects_an_invalid_id(): void {
		$result = $this->service->rescan_attachment( 0 );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'bbai_audit_invalid_attachment', $result->get_error_code() );
	}

	/* ─── Reporting ─── */

	public function test_describe_exposes_what_the_progress_ui_needs(): void {
		$this->discovery->total = 300;
		$result                 = $this->service->start( 3 );

		foreach ( array( 'run_id', 'status', 'done', 'can_resume', 'is_stale', 'percent', 'total_images', 'processed_images', 'batch_number', 'errors', 'counters', 'last_completed_at' ) as $key ) {
			$this->assertArrayHasKey( $key, $result, $key . ' missing from progress payload' );
		}
	}

	public function test_a_summary_is_available_before_any_run(): void {
		$GLOBALS['wpdb']->results_result = array();

		$summary = $this->service->get_summary();

		$this->assertIsArray( $summary );
		$this->assertSame( 0, $summary['scanned_images'] );
	}
}
