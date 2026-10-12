<?php
/**
 * Batch processing: cursor movement, tallies, and per-image failure isolation.
 */

declare(strict_types=1);

use BeepBeepAI\AltTextGenerator\Audit\Audit_Batch_Processor;
use BeepBeepAI\AltTextGenerator\Audit\Audit_Issue;
use BeepBeepAI\AltTextGenerator\Audit\Audit_Progress_Service;
use BeepBeepAI\AltTextGenerator\Audit\Audit_Rule_Registry;
use BeepBeepAI\AltTextGenerator\Audit\Image_Audit_Context;
use BeepBeepAI\AltTextGenerator\Audit\Image_Discovery_Service;
use PHPUnit\Framework\TestCase;

/**
 * Serves scripted batches instead of querying the database.
 */
final class FakeDiscoveryService extends Image_Discovery_Service {

	/** @var array<int,Image_Audit_Context[]> Batches, consumed in order. */
	public $batches = array();

	/** @var array<int,int> Cursors the processor asked for. */
	public $requested_cursors = array();

	public function get_batch( $after_attachment_id, $size, array $duplicate_map = array() ) {
		$this->requested_cursors[] = (int) $after_attachment_id;

		return empty( $this->batches ) ? array() : array_shift( $this->batches );
	}
}

/**
 * A registry whose evaluation blows up for one nominated attachment.
 */
final class ExplodingRegistry extends Audit_Rule_Registry {

	public $explode_on = 0;

	public function evaluate( Image_Audit_Context $context ) {
		if ( $context->get_attachment_id() === $this->explode_on ) {
			throw new RuntimeException( 'rule blew up' );
		}

		return parent::evaluate( $context );
	}
}

final class AuditBatchProcessorTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['bbai_test_options'] = array();
		$GLOBALS['wpdb']              = new BBAI_Fake_WPDB();
	}

	/**
	 * @param array<string,mixed> $overrides Context overrides.
	 */
	private function image( int $id, array $overrides = array() ): Image_Audit_Context {
		return new Image_Audit_Context(
			array_merge(
				array(
					'attachment_id'       => $id,
					'alt'                 => 'A grey heron standing in shallow water',
					'filename'            => 'heron',
					'usage_context_known' => true,
					'has_usage'           => true,
					'parent_post_id'      => 3,
				),
				$overrides
			)
		);
	}

	private function running_state( int $total, string $run_id = 'run-x' ): array {
		return Audit_Progress_Service::start_run( $total, 1, $run_id );
	}

	public function test_a_batch_advances_the_cursor_to_the_last_image_seen(): void {
		$discovery          = new FakeDiscoveryService();
		$discovery->batches = array( array( $this->image( 90 ), $this->image( 80 ), $this->image( 70 ) ) );

		$processor = new Audit_Batch_Processor( $discovery, new Audit_Rule_Registry() );
		$state     = $processor->process_batch( $this->running_state( 10 ) );

		$this->assertSame( 70, $state['last_attachment_id'] );
		$this->assertSame( 3, $state['processed_images'] );
		$this->assertSame( 1, $state['batch_number'] );
	}

	public function test_the_next_batch_resumes_from_the_stored_cursor(): void {
		$discovery          = new FakeDiscoveryService();
		$discovery->batches = array(
			array( $this->image( 90 ), $this->image( 80 ) ),
			array( $this->image( 70 ), $this->image( 60 ) ),
		);

		$processor = new Audit_Batch_Processor( $discovery, new Audit_Rule_Registry() );

		$state = $processor->process_batch( $this->running_state( 10 ) );
		$state = $processor->process_batch( $state );

		// First call starts from the top; the second continues below image 80.
		$this->assertSame( array( 0, 80 ), $discovery->requested_cursors );
		$this->assertSame( 60, $state['last_attachment_id'] );
		$this->assertSame( 4, $state['processed_images'] );
	}

	public function test_an_empty_batch_marks_the_run_finished(): void {
		$discovery = new FakeDiscoveryService();
		$processor = new Audit_Batch_Processor( $discovery, new Audit_Rule_Registry() );

		// The projected total can be stale if images were deleted mid-scan; running
		// out of rows must still finish the run rather than loop forever.
		$state = $processor->process_batch( $this->running_state( 500 ) );

		$this->assertTrue( $processor->is_finished( $state ) );
	}

	public function test_a_run_that_is_not_running_is_left_alone(): void {
		$discovery          = new FakeDiscoveryService();
		$discovery->batches = array( array( $this->image( 10 ) ) );

		$processor = new Audit_Batch_Processor( $discovery, new Audit_Rule_Registry() );

		$state           = $this->running_state( 10 );
		$state['status'] = Audit_Progress_Service::STATUS_CANCELLED;

		$this->assertSame( $state, $processor->process_batch( $state ) );
		$this->assertSame( array(), $discovery->requested_cursors );
	}

	public function test_a_failing_image_is_counted_but_does_not_stop_the_batch(): void {
		$discovery          = new FakeDiscoveryService();
		$discovery->batches = array( array( $this->image( 90 ), $this->image( 80 ), $this->image( 70 ) ) );

		$registry             = new ExplodingRegistry();
		$registry->explode_on = 80;

		$processor = new Audit_Batch_Processor( $discovery, $registry );
		$state     = $processor->process_batch( $this->running_state( 10 ) );

		$this->assertSame( 1, $state['errors'] );
		$this->assertSame( 3, $state['processed_images'] );
		$this->assertSame( 70, $state['last_attachment_id'] );
	}

	public function test_the_cursor_moves_past_a_failing_image_so_the_run_cannot_wedge(): void {
		$discovery          = new FakeDiscoveryService();
		$discovery->batches = array( array( $this->image( 90 ) ) );

		$registry             = new ExplodingRegistry();
		$registry->explode_on = 90;

		$processor = new Audit_Batch_Processor( $discovery, $registry );
		$state     = $processor->process_batch( $this->running_state( 10 ) );

		$this->assertSame( 90, $state['last_attachment_id'], 'A failing image must still advance the cursor' );
	}

	public function test_counters_tally_by_worst_severity(): void {
		$discovery          = new FakeDiscoveryService();
		$discovery->batches = array(
			array(
				$this->image( 90, array( 'alt' => null ) ),          // critical.
				$this->image( 80, array( 'alt' => 'IMG_1234' ) ),    // warning.
				$this->image( 70, array( 'alt' => '' ) ),            // review.
				$this->image( 60 ),                                  // clean.
			),
		);

		$processor = new Audit_Batch_Processor( $discovery, new Audit_Rule_Registry() );
		$state     = $processor->process_batch( $this->running_state( 10 ) );

		$this->assertSame( 1, $state['counters'][ Audit_Issue::SEVERITY_CRITICAL ] );
		$this->assertSame( 1, $state['counters'][ Audit_Issue::SEVERITY_WARNING ] );
		$this->assertSame( 1, $state['counters'][ Audit_Issue::SEVERITY_REVIEW ] );
		$this->assertSame( 1, $state['counters']['passing'] );
	}

	public function test_decorative_images_tally_as_settled_not_as_issues(): void {
		$discovery          = new FakeDiscoveryService();
		$discovery->batches = array(
			array( $this->image( 90, array( 'alt' => '', 'is_decorative' => true ) ) ),
		);

		$processor = new Audit_Batch_Processor( $discovery, new Audit_Rule_Registry() );
		$state     = $processor->process_batch( $this->running_state( 10 ) );

		$this->assertSame( 1, $state['counters']['settled'] );
		$this->assertSame( 0, $state['counters'][ Audit_Issue::SEVERITY_REVIEW ] );
		$this->assertSame( 0, $state['counters']['passing'] );
	}

	public function test_findings_are_written_in_a_single_upsert_per_batch(): void {
		$discovery          = new FakeDiscoveryService();
		$discovery->batches = array( array( $this->image( 90 ), $this->image( 80 ), $this->image( 70 ) ) );

		$processor = new Audit_Batch_Processor( $discovery, new Audit_Rule_Registry() );
		$processor->process_batch( $this->running_state( 10 ) );

		$writes = array_filter(
			$GLOBALS['wpdb']->queries,
			static function ( $query ) {
				return false !== strpos( $query, 'INSERT INTO' );
			}
		);

		$this->assertCount( 1, $writes, 'A batch must write once, not once per image' );
		$this->assertStringContainsString( 'ON DUPLICATE KEY UPDATE', (string) reset( $writes ) );
	}

	public function test_images_with_no_id_are_skipped(): void {
		$discovery          = new FakeDiscoveryService();
		$discovery->batches = array( array( $this->image( 0 ), $this->image( 50 ) ) );

		$processor = new Audit_Batch_Processor( $discovery, new Audit_Rule_Registry() );
		$state     = $processor->process_batch( $this->running_state( 10 ) );

		$this->assertSame( 1, $state['processed_images'] );
		$this->assertSame( 50, $state['last_attachment_id'] );
	}

	public function test_is_finished_compares_processed_against_total(): void {
		$processor = new Audit_Batch_Processor( new FakeDiscoveryService(), new Audit_Rule_Registry() );

		$state                     = $this->running_state( 10 );
		$state['processed_images'] = 9;
		$this->assertFalse( $processor->is_finished( $state ) );

		$state['processed_images'] = 10;
		$this->assertTrue( $processor->is_finished( $state ) );
	}
}
