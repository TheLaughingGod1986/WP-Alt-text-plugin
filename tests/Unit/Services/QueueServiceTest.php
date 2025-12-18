<?php
/**
 * Queue Service Tests
 *
 * @package BeepBeepAI\AltText\Tests\Unit\Services
 */

namespace BeepBeepAI\AltText\Tests\Unit\Services;

use BeepBeepAI\AltText\Tests\TestCase;
use BeepBeep\AltText\Services\Queue_Service;
use BeepBeep\AltText\Core\Event_Bus;
use Mockery;

/**
 * Test Queue Service
 *
 * @covers \BeepBeep\AltText\Services\Queue_Service
 */
class QueueServiceTest extends TestCase {

	/**
	 * Event bus mock.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $event_bus;

	/**
	 * Queue service instance.
	 *
	 * @var Queue_Service
	 */
	private $service;

	/**
	 * Set up before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Create mocks
		$this->event_bus = Mockery::mock( Event_Bus::class );

		// Create service instance
		$this->service = new Queue_Service( $this->event_bus );
	}

	/**
	 * Test retry job with invalid ID.
	 */
	public function test_retry_job_invalid_id() {
		$result = $this->service->retry_job( 0 );

		$this->assertErrorResponse( $result );
		$this->assertStringContainsString( 'Invalid', $result['message'] );
	}

	/**
	 * Test retry job with negative ID.
	 */
	public function test_retry_job_negative_id() {
		$result = $this->service->retry_job( -1 );

		$this->assertErrorResponse( $result );
		$this->assertStringContainsString( 'Invalid', $result['message'] );
	}

	/**
	 * Test retry job success.
	 */
	public function test_retry_job_success() {
		$job_id = 123;

		// Expect event emission
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'queue_job_retried', array( 'job_id' => $job_id ) );

		// Execute
		$result = $this->service->retry_job( $job_id );

		// Assert
		$this->assertSuccessResponse( $result );
		$this->assertStringContainsString( 're-queued', strtolower( $result['message'] ) );
	}

	/**
	 * Test retry failed jobs success.
	 */
	public function test_retry_failed_success() {
		// Expect event emission
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'queue_failed_retried', null );

		// Execute
		$result = $this->service->retry_failed();

		// Assert
		$this->assertSuccessResponse( $result );
		$this->assertStringContainsString( 'retry', strtolower( $result['message'] ) );
	}

	/**
	 * Test retry multiple job IDs.
	 */
	public function test_retry_multiple_jobs() {
		$job_ids = array( 1, 2, 3, 4, 5 );

		foreach ( $job_ids as $job_id ) {
			// Reset event bus expectations for each iteration
			$this->event_bus->shouldReceive( 'emit' )
				->once()
				->with( 'queue_job_retried', array( 'job_id' => $job_id ) );

			$result = $this->service->retry_job( $job_id );
			$this->assertSuccessResponse( $result, "Failed for job ID: $job_id" );
		}
	}

	/**
	 * Test retry job with very large ID.
	 */
	public function test_retry_job_large_id() {
		$large_id = 999999;

		$this->event_bus->shouldReceive( 'emit' )
			->once();

		$result = $this->service->retry_job( $large_id );

		$this->assertSuccessResponse( $result );
	}

	/**
	 * Test service handles sequential retry operations.
	 */
	public function test_sequential_retry_operations() {
		// First retry a specific job
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'queue_job_retried', Mockery::type( 'array' ) );

		$result1 = $this->service->retry_job( 100 );
		$this->assertSuccessResponse( $result1 );

		// Then retry all failed jobs
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'queue_failed_retried', null );

		$result2 = $this->service->retry_failed();
		$this->assertSuccessResponse( $result2 );
	}

	/**
	 * Test retry job boundary values.
	 */
	public function test_retry_job_boundary_values() {
		// Test boundary at 1 (minimum valid)
		$this->event_bus->shouldReceive( 'emit' )
			->once();

		$result = $this->service->retry_job( 1 );
		$this->assertSuccessResponse( $result );

		// Test boundary at 0 (invalid)
		$result = $this->service->retry_job( 0 );
		$this->assertErrorResponse( $result );
	}

	/**
	 * Test that service properly emits events.
	 */
	public function test_service_emits_correct_events() {
		$job_id = 42;

		// Strict expectation on event emission
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with(
				'queue_job_retried',
				Mockery::on( function ( $arg ) use ( $job_id ) {
					return isset( $arg['job_id'] ) && $arg['job_id'] === $job_id;
				} )
			);

		$this->service->retry_job( $job_id );

		// Mockery will automatically verify expectations in tearDown
	}

	/**
	 * Test retry failed emits correct event.
	 */
	public function test_retry_failed_emits_correct_event() {
		// Strict expectation on event emission
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'queue_failed_retried', null );

		$this->service->retry_failed();

		// Mockery will automatically verify expectations in tearDown
	}

	/**
	 * Test response message structure.
	 */
	public function test_response_message_structure() {
		$this->event_bus->shouldReceive( 'emit' )->once();

		$result = $this->service->retry_job( 1 );

		$this->assertArrayHasKeys( array( 'success', 'message' ), $result );
		$this->assertIsString( $result['message'] );
		$this->assertNotEmpty( $result['message'] );
	}
}
