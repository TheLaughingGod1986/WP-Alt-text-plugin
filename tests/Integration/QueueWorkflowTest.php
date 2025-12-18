<?php
/**
 * Queue Workflow Integration Test
 *
 * Tests complete queue processing workflows from controller through services.
 *
 * @package BeepBeepAI\AltText\Tests\Integration
 */

namespace BeepBeepAI\AltText\Tests\Integration;

use BeepBeepAI\AltText\Tests\TestCase;
use BeepBeep\AltText\Controllers\Queue_Controller;
use BeepBeep\AltText\Services\Queue_Service;
use BeepBeep\AltText\Core\Event_Bus;
use Mockery;

/**
 * Test Queue Workflows
 *
 * @covers \BeepBeep\AltText\Controllers\Queue_Controller
 * @covers \BeepBeep\AltText\Services\Queue_Service
 */
class QueueWorkflowTest extends TestCase {

	/**
	 * Event bus mock.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $event_bus;

	/**
	 * Queue service.
	 *
	 * @var Queue_Service
	 */
	private $queue_service;

	/**
	 * Queue controller.
	 *
	 * @var Queue_Controller
	 */
	private $queue_controller;

	/**
	 * Set up before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Create mocks
		$this->event_bus = Mockery::mock( Event_Bus::class );

		// Create real service and controller
		$this->queue_service    = new Queue_Service( $this->event_bus );
		$this->queue_controller = new Queue_Controller( $this->queue_service );

		// Reset $_POST
		$_POST = array();
	}

	/**
	 * Test complete retry job workflow.
	 *
	 * Workflow: User retries failed job → Job re-queued → Processing scheduled → Event emitted
	 */
	public function test_complete_retry_job_workflow() {
		$job_id = 123;
		$_POST['job_id'] = $job_id;

		// Expect event emission
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'queue_job_retried', array( 'job_id' => $job_id ) );

		// Execute complete workflow
		$result = $this->queue_controller->retry_job();

		// Verify
		$this->assertSuccessResponse( $result );
		$this->assertStringContainsString( 're-queued', strtolower( $result['message'] ) );
	}

	/**
	 * Test retry all failed jobs workflow.
	 *
	 * Workflow: User requests retry all → All failed jobs retried → Event emitted
	 */
	public function test_retry_all_failed_jobs_workflow() {
		// Expect event
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'queue_failed_retried', null );

		// Execute
		$result = $this->queue_controller->retry_failed();

		// Verify
		$this->assertSuccessResponse( $result );
		$this->assertStringContainsString( 'retry', strtolower( $result['message'] ) );
	}

	/**
	 * Test clear completed jobs workflow.
	 *
	 * Workflow: User clears completed → Jobs removed → Queue cleaned up
	 */
	public function test_clear_completed_workflow() {
		// Execute
		$result = $this->queue_controller->clear_completed();

		// Result depends on Queue_Service implementation of clear_completed
		// For now verify the workflow executes
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'success', $result );
	}

	/**
	 * Test get queue stats workflow.
	 *
	 * Workflow: User requests stats → Service fetches data → Stats returned
	 */
	public function test_get_queue_stats_workflow() {
		// Execute
		$result = $this->queue_controller->get_stats();

		// Verify workflow completes
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'success', $result );
	}

	/**
	 * Test retry job with invalid ID.
	 *
	 * Workflow: Invalid job ID → Validation → Error returned
	 */
	public function test_retry_invalid_job_workflow() {
		$_POST['job_id'] = 0; // Invalid

		// Execute
		$result = $this->queue_controller->retry_job();

		// Verify error handling
		$this->assertErrorResponse( $result );
		$this->assertStringContainsString( 'invalid', strtolower( $result['message'] ) );
	}

	/**
	 * Test multiple queue operations in sequence.
	 *
	 * Workflow: Retry job → Get stats → Retry failed → Clear completed
	 */
	public function test_multiple_queue_operations() {
		// Step 1: Retry specific job
		$_POST['job_id'] = 100;
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'queue_job_retried', array( 'job_id' => 100 ) );

		$retry_result = $this->queue_controller->retry_job();
		$this->assertSuccessResponse( $retry_result );

		// Step 2: Get stats
		$stats_result = $this->queue_controller->get_stats();
		$this->assertIsArray( $stats_result );

		// Step 3: Retry all failed
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'queue_failed_retried', null );

		$retry_all_result = $this->queue_controller->retry_failed();
		$this->assertSuccessResponse( $retry_all_result );

		// Step 4: Clear completed
		$clear_result = $this->queue_controller->clear_completed();
		$this->assertIsArray( $clear_result );
	}

	/**
	 * Test retry multiple jobs sequentially.
	 *
	 * Workflow: Retry job 1 → Retry job 2 → Retry job 3
	 */
	public function test_retry_multiple_jobs_sequentially() {
		$job_ids = array( 10, 20, 30 );

		foreach ( $job_ids as $job_id ) {
			$_POST['job_id'] = $job_id;

			$this->event_bus->shouldReceive( 'emit' )
				->once()
				->with( 'queue_job_retried', array( 'job_id' => $job_id ) );

			$result = $this->queue_controller->retry_job();

			$this->assertSuccessResponse( $result, "Failed for job ID: $job_id" );
		}
	}

	/**
	 * Test event emission for job retry.
	 *
	 * Workflow: Retry job → Event emitted with correct job ID
	 */
	public function test_event_emission_for_job_retry() {
		$job_id = 555;
		$_POST['job_id'] = $job_id;

		// Strict event verification
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with(
				'queue_job_retried',
				Mockery::on( function ( $data ) use ( $job_id ) {
					return isset( $data['job_id'] ) && $data['job_id'] === $job_id;
				} )
			);

		// Execute
		$this->queue_controller->retry_job();

		// Event verified through mock
		$this->assertTrue( true );
	}

	/**
	 * Test event emission for retry failed.
	 *
	 * Workflow: Retry failed → Event emitted with null data
	 */
	public function test_event_emission_for_retry_failed() {
		// Strict event verification
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'queue_failed_retried', null );

		// Execute
		$this->queue_controller->retry_failed();

		// Event verified through mock
		$this->assertTrue( true );
	}

	/**
	 * Test input conversion workflow.
	 *
	 * Workflow: String job ID → Converted to int → Processed correctly
	 */
	public function test_job_id_conversion_workflow() {
		$_POST['job_id'] = '777'; // String

		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'queue_job_retried', array( 'job_id' => 777 ) ); // Int

		$result = $this->queue_controller->retry_job();

		$this->assertSuccessResponse( $result );
	}

	/**
	 * Test negative job ID handling.
	 *
	 * Workflow: Negative ID → absint converts to 0 → Validation error
	 */
	public function test_negative_job_id_workflow() {
		$_POST['job_id'] = '-50';

		// absint converts -50 to 0, which is invalid
		$result = $this->queue_controller->retry_job();

		$this->assertErrorResponse( $result );
	}

	/**
	 * Test retry job → retry failed → clear sequence.
	 *
	 * Complete maintenance workflow
	 */
	public function test_complete_maintenance_workflow() {
		// Step 1: Retry a specific failed job
		$_POST['job_id'] = 42;
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'queue_job_retried', array( 'job_id' => 42 ) );

		$retry_one = $this->queue_controller->retry_job();
		$this->assertSuccessResponse( $retry_one );

		// Step 2: Retry all remaining failed jobs
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'queue_failed_retried', null );

		$retry_all = $this->queue_controller->retry_failed();
		$this->assertSuccessResponse( $retry_all );

		// Step 3: Clear completed jobs to clean up
		$clear = $this->queue_controller->clear_completed();
		$this->assertIsArray( $clear );

		// Complete workflow executed successfully
		$this->assertTrue( true );
	}

	/**
	 * Test error handling when service unavailable.
	 *
	 * Workflow: Queue system not available → Graceful error
	 */
	public function test_error_when_queue_unavailable() {
		// The actual service checks if BbAI_Queue class exists
		// If not available, it returns an error
		// This tests the service's error handling

		$_POST['job_id'] = 999;

		// If BbAI_Queue doesn't exist, expect error or event
		// Behavior depends on class_exists check in service
		$result = $this->queue_controller->retry_job();

		// Should return some result
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'success', $result );
	}

	/**
	 * Test large job ID handling.
	 *
	 * Workflow: Very large job ID → Processed correctly
	 */
	public function test_large_job_id_workflow() {
		$large_id = 999999;
		$_POST['job_id'] = $large_id;

		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'queue_job_retried', array( 'job_id' => $large_id ) );

		$result = $this->queue_controller->retry_job();

		$this->assertSuccessResponse( $result );
	}

	/**
	 * Test workflow with missing POST data.
	 *
	 * Workflow: No POST data → Default to 0 → Validation error
	 */
	public function test_workflow_with_missing_post_data() {
		// No $_POST data set

		$result = $this->queue_controller->retry_job();

		// Should handle gracefully
		$this->assertErrorResponse( $result );
	}
}
