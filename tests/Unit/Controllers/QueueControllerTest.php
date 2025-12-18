<?php
/**
 * Queue Controller Tests
 *
 * @package BeepBeepAI\AltText\Tests\Unit\Controllers
 */

namespace BeepBeepAI\AltText\Tests\Unit\Controllers;

use BeepBeepAI\AltText\Tests\TestCase;
use BeepBeep\AltText\Controllers\Queue_Controller;
use BeepBeep\AltText\Services\Queue_Service;
use Mockery;

/**
 * Test Queue Controller
 *
 * @covers \BeepBeep\AltText\Controllers\Queue_Controller
 */
class QueueControllerTest extends TestCase {

	/**
	 * Queue service mock.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $queue_service;

	/**
	 * Controller instance.
	 *
	 * @var Queue_Controller
	 */
	private $controller;

	/**
	 * Set up before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Mock queue service
		$this->queue_service = Mockery::mock( Queue_Service::class );

		// Create controller
		$this->controller = new Queue_Controller( $this->queue_service );

		// Reset $_POST
		$_POST = array();
	}

	/**
	 * Test retry job.
	 */
	public function test_retry_job() {
		$job_id = 123;
		$_POST['job_id'] = $job_id;

		$this->queue_service->shouldReceive( 'retry_job' )
			->once()
			->with( $job_id )
			->andReturn( array(
				'success' => true,
				'message' => 'Job re-queued',
			) );

		$result = $this->controller->retry_job();

		$this->assertSuccessResponse( $result );
	}

	/**
	 * Test retry job converts string to int.
	 */
	public function test_retry_job_converts_string() {
		$_POST['job_id'] = '456';

		$this->queue_service->shouldReceive( 'retry_job' )
			->once()
			->with( 456 )
			->andReturn( array( 'success' => true ) );

		$result = $this->controller->retry_job();

		$this->assertSuccessResponse( $result );
	}

	/**
	 * Test retry job with invalid ID.
	 */
	public function test_retry_job_invalid_id() {
		$_POST['job_id'] = 'invalid';

		$this->queue_service->shouldReceive( 'retry_job' )
			->once()
			->with( 0 )
			->andReturn( array(
				'success' => false,
				'message' => 'Invalid job ID',
			) );

		$result = $this->controller->retry_job();

		$this->assertErrorResponse( $result );
	}

	/**
	 * Test retry job with missing ID.
	 */
	public function test_retry_job_missing_id() {
		// No $_POST data

		$this->queue_service->shouldReceive( 'retry_job' )
			->once()
			->with( 0 )
			->andReturn( array( 'success' => false ) );

		$result = $this->controller->retry_job();

		$this->assertErrorResponse( $result );
	}

	/**
	 * Test retry failed jobs.
	 */
	public function test_retry_failed() {
		$this->queue_service->shouldReceive( 'retry_failed' )
			->once()
			->andReturn( array(
				'success' => true,
				'message' => 'Retry scheduled for failed jobs',
			) );

		$result = $this->controller->retry_failed();

		$this->assertSuccessResponse( $result );
	}

	/**
	 * Test clear completed jobs.
	 */
	public function test_clear_completed() {
		$this->queue_service->shouldReceive( 'clear_completed' )
			->once()
			->andReturn( array(
				'success' => true,
				'message' => 'Completed jobs cleared',
			) );

		$result = $this->controller->clear_completed();

		$this->assertSuccessResponse( $result );
	}

	/**
	 * Test get queue stats.
	 */
	public function test_get_stats() {
		$stats = array(
			'total'     => 100,
			'completed' => 80,
			'failed'    => 5,
			'pending'   => 15,
		);

		$this->queue_service->shouldReceive( 'get_stats' )
			->once()
			->andReturn( array(
				'success' => true,
				'stats'   => $stats,
			) );

		$result = $this->controller->get_stats();

		$this->assertSuccessResponse( $result );
		$this->assertArrayHasKey( 'stats', $result );
		$this->assertEquals( 100, $result['stats']['total'] );
	}

	/**
	 * Test all methods delegate to service.
	 */
	public function test_methods_delegate_correctly() {
		// Retry job
		$_POST['job_id'] = '100';
		$this->queue_service->shouldReceive( 'retry_job' )
			->once()
			->with( 100 )
			->andReturn( array( 'success' => true ) );
		$this->controller->retry_job();

		// Retry failed
		$this->queue_service->shouldReceive( 'retry_failed' )
			->once()
			->andReturn( array( 'success' => true ) );
		$this->controller->retry_failed();

		// Clear completed
		$this->queue_service->shouldReceive( 'clear_completed' )
			->once()
			->andReturn( array( 'success' => true ) );
		$this->controller->clear_completed();

		// Get stats
		$this->queue_service->shouldReceive( 'get_stats' )
			->once()
			->andReturn( array( 'success' => true ) );
		$this->controller->get_stats();

		// All expectations verified in tearDown
		$this->assertTrue( true );
	}

	/**
	 * Test retry job with negative ID.
	 */
	public function test_retry_job_negative_id() {
		$_POST['job_id'] = '-10';

		// absint converts negative to 0
		$this->queue_service->shouldReceive( 'retry_job' )
			->once()
			->with( 0 )
			->andReturn( array( 'success' => false ) );

		$result = $this->controller->retry_job();

		$this->assertErrorResponse( $result );
	}

	/**
	 * Test controller returns service responses unchanged.
	 */
	public function test_returns_service_responses() {
		$_POST['job_id'] = '999';

		$expected_response = array(
			'success' => true,
			'message' => 'Job successfully retried',
			'job'     => array( 'id' => 999, 'status' => 'queued' ),
		);

		$this->queue_service->shouldReceive( 'retry_job' )
			->once()
			->andReturn( $expected_response );

		$result = $this->controller->retry_job();

		$this->assertEquals( $expected_response, $result );
	}

	/**
	 * Test multiple operations in sequence.
	 */
	public function test_multiple_operations_sequence() {
		// First retry a job
		$_POST['job_id'] = '50';
		$this->queue_service->shouldReceive( 'retry_job' )
			->once()
			->with( 50 )
			->andReturn( array( 'success' => true ) );
		$this->controller->retry_job();

		// Then retry all failed
		$this->queue_service->shouldReceive( 'retry_failed' )
			->once()
			->andReturn( array( 'success' => true ) );
		$this->controller->retry_failed();

		// Then get stats
		$this->queue_service->shouldReceive( 'get_stats' )
			->once()
			->andReturn( array( 'success' => true, 'stats' => array() ) );
		$this->controller->get_stats();

		// Finally clear completed
		$this->queue_service->shouldReceive( 'clear_completed' )
			->once()
			->andReturn( array( 'success' => true ) );
		$this->controller->clear_completed();

		$this->assertTrue( true );
	}

	/**
	 * Test absint function behavior with edge cases.
	 */
	public function test_absint_edge_cases() {
		$test_cases = array(
			'0'       => 0,
			''        => 0,
			'abc'     => 0,
			'-50'     => 0,
			'3.14'    => 3,
			'100'     => 100,
			'  42  '  => 42,
		);

		foreach ( $test_cases as $input => $expected ) {
			$_POST['job_id'] = $input;

			$this->queue_service->shouldReceive( 'retry_job' )
				->once()
				->with( $expected )
				->andReturn( array( 'success' => $expected > 0 ) );

			$this->controller->retry_job();
		}

		$this->assertTrue( true );
	}
}
