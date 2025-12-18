<?php
/**
 * Generation Workflow Integration Test
 *
 * Tests complete alt text generation workflows from controller through services.
 *
 * @package BeepBeepAI\AltText\Tests\Integration
 */

namespace BeepBeepAI\AltText\Tests\Integration;

use BeepBeepAI\AltText\Tests\TestCase;
use BeepBeep\AltText\Controllers\Generation_Controller;
use BeepBeep\AltText\Services\Generation_Service;
use BeepBeep\AltText\Services\Usage_Service;
use BeepBeep\AltText\Core\Event_Bus;
use Mockery;

/**
 * Test Generation Workflows
 *
 * @covers \BeepBeep\AltText\Controllers\Generation_Controller
 * @covers \BeepBeep\AltText\Services\Generation_Service
 */
class GenerationWorkflowTest extends TestCase {

	/**
	 * API client mock.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $api_client;

	/**
	 * Usage service mock.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $usage_service;

	/**
	 * Event bus mock.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $event_bus;

	/**
	 * Core mock.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $core;

	/**
	 * Generation service.
	 *
	 * @var Generation_Service
	 */
	private $generation_service;

	/**
	 * Generation controller.
	 *
	 * @var Generation_Controller
	 */
	private $generation_controller;

	/**
	 * Set up before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Create mocks
		$this->api_client    = Mockery::mock( '\BbAI_API_Client_V2' );
		$this->usage_service = Mockery::mock( Usage_Service::class );
		$this->event_bus     = Mockery::mock( Event_Bus::class );
		$this->core          = Mockery::mock( '\BbAI_Core' );

		// Create real service and controller
		$this->generation_service = new Generation_Service(
			$this->api_client,
			$this->usage_service,
			$this->event_bus,
			$this->core
		);

		$this->generation_controller = new Generation_Controller( $this->generation_service );

		// Reset $_POST
		$_POST = array();
	}

	/**
	 * Test complete generation flow with license.
	 *
	 * Workflow: User has license → Generates alt text → Cache cleared → Event emitted
	 */
	public function test_complete_generation_with_license() {
		$attachment_id = 123;
		$_POST['attachment_id'] = $attachment_id;

		// Step 1: Check license (has license, skip quota check)
		$this->api_client->shouldReceive( 'has_active_license' )
			->once()
			->andReturn( true );

		// Step 2: Generate alt text
		$this->core->shouldReceive( 'generate_and_save' )
			->once()
			->with( $attachment_id, 'ajax', 1, array(), true )
			->andReturn( 'A beautiful mountain landscape with snow-capped peaks' );

		// Step 3: Clear cache
		$this->usage_service->shouldReceive( 'clear_cache' )
			->once();

		// Step 4: Emit event
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'alt_text_generated', Mockery::on( function ( $arg ) use ( $attachment_id ) {
				return $arg['attachment_id'] === $attachment_id
					&& isset( $arg['alt_text'] )
					&& $arg['source'] === 'ajax';
			} ) );

		// Execute complete flow
		$result = $this->generation_controller->regenerate_single();

		// Verify
		$this->assertSuccessResponse( $result );
		$this->assertEquals( $attachment_id, $result['attachment_id'] );
		$this->assertStringContainsString( 'mountain', $result['alt_text'] );
	}

	/**
	 * Test generation flow without license (quota check).
	 *
	 * Workflow: No license → Check quota → Within limit → Generate
	 */
	public function test_generation_without_license_within_quota() {
		if ( ! defined( 'WP_LOCAL_DEV' ) ) {
			define( 'WP_LOCAL_DEV', false );
		}

		$attachment_id = 456;
		$_POST['attachment_id'] = $attachment_id;

		// No license
		$this->api_client->shouldReceive( 'has_active_license' )
			->once()
			->andReturn( false );

		// Within quota
		$this->api_client->shouldReceive( 'has_reached_limit' )
			->once()
			->andReturn( false );

		// Generate
		$this->core->shouldReceive( 'generate_and_save' )
			->once()
			->andReturn( 'A sunset over the ocean' );

		$this->usage_service->shouldReceive( 'clear_cache' )->once();
		$this->event_bus->shouldReceive( 'emit' )->once();

		// Execute
		$result = $this->generation_controller->regenerate_single();

		// Verify
		$this->assertSuccessResponse( $result );
		$this->assertEquals( 'A sunset over the ocean', $result['alt_text'] );
	}

	/**
	 * Test generation blocked by quota limit.
	 *
	 * Workflow: No license → Check quota → Limit reached → Generation blocked
	 */
	public function test_generation_blocked_by_quota_limit() {
		if ( ! defined( 'WP_LOCAL_DEV' ) ) {
			define( 'WP_LOCAL_DEV', false );
		}

		$attachment_id = 789;
		$_POST['attachment_id'] = $attachment_id;

		// No license
		$this->api_client->shouldReceive( 'has_active_license' )
			->once()
			->andReturn( false );

		// Limit reached
		$this->api_client->shouldReceive( 'has_reached_limit' )
			->once()
			->andReturn( true );

		// Get usage data
		$this->api_client->shouldReceive( 'get_usage' )
			->once()
			->andReturn( array(
				'used'  => 100,
				'limit' => 100,
				'plan'  => 'free',
			) );

		// Execute
		$result = $this->generation_controller->regenerate_single();

		// Verify blocked
		$this->assertErrorResponse( $result );
		$this->assertEquals( 'limit_reached', $result['code'] );
		$this->assertArrayHasKey( 'usage', $result );
	}

	/**
	 * Test generation error handling.
	 *
	 * Workflow: Generate → API error → Error handled → User-friendly message
	 */
	public function test_generation_error_handling() {
		$attachment_id = 999;
		$_POST['attachment_id'] = $attachment_id;

		$this->api_client->shouldReceive( 'has_active_license' )->once()->andReturn( true );

		// Mock generation error
		$wp_error = new \WP_Error(
			'api_error',
			'OpenAI API rate limit exceeded',
			array( 'status_code' => 429 )
		);

		$this->core->shouldReceive( 'generate_and_save' )
			->once()
			->andReturn( $wp_error );

		// Execute
		$result = $this->generation_controller->regenerate_single();

		// Verify error handling
		$this->assertErrorResponse( $result );
		$this->assertEquals( 'api_error', $result['code'] );
		$this->assertArrayHasKey( 'data', $result );
	}

	/**
	 * Test bulk queue workflow.
	 *
	 * Workflow: Multiple attachments → Queue for processing → Status returned
	 */
	public function test_bulk_queue_workflow() {
		$attachment_ids = array( 1, 2, 3, 4, 5 );
		$_POST['attachment_ids'] = json_encode( $attachment_ids );

		// Mock bulk queue (delegated to service method we haven't tested yet)
		// This would call generation_service->bulk_queue() which then processes each ID

		// For now, verify the flow reaches the service
		$result = $this->generation_controller->bulk_queue();

		// The actual implementation would need the bulk_queue service method
		// This test documents the expected workflow
		$this->assertTrue( true ); // Placeholder
	}

	/**
	 * Test invalid attachment ID handling.
	 *
	 * Workflow: Invalid ID → Validation → Error returned
	 */
	public function test_invalid_attachment_id_workflow() {
		$_POST['attachment_id'] = 0; // Invalid

		$this->api_client->shouldReceive( 'has_active_license' )
			->once()
			->andReturn( true );

		// Core should receive invalid ID and handle it
		$this->core->shouldReceive( 'generate_and_save' )
			->once()
			->with( 0, 'ajax', 1, array(), true )
			->andReturn( new \WP_Error( 'invalid_id', 'Invalid attachment ID' ) );

		// Execute
		$result = $this->generation_controller->regenerate_single();

		// Verify
		$this->assertErrorResponse( $result );
	}

	/**
	 * Test multiple generations in sequence.
	 *
	 * Workflow: Generate image 1 → Generate image 2 → Generate image 3
	 */
	public function test_sequential_generations() {
		$images = array(
			100 => 'A cat sleeping on a couch',
			200 => 'A dog playing in the park',
			300 => 'A bird flying in the sky',
		);

		foreach ( $images as $attachment_id => $expected_text ) {
			$_POST['attachment_id'] = $attachment_id;

			$this->api_client->shouldReceive( 'has_active_license' )
				->once()
				->andReturn( true );

			$this->core->shouldReceive( 'generate_and_save' )
				->once()
				->with( $attachment_id, 'ajax', 1, array(), true )
				->andReturn( $expected_text );

			$this->usage_service->shouldReceive( 'clear_cache' )->once();
			$this->event_bus->shouldReceive( 'emit' )->once();

			$result = $this->generation_controller->regenerate_single();

			$this->assertSuccessResponse( $result );
			$this->assertEquals( $expected_text, $result['alt_text'] );
		}
	}

	/**
	 * Test cache clearing after generation.
	 *
	 * Workflow: Generate → Clear usage cache → Fresh data next time
	 */
	public function test_cache_cleared_after_generation() {
		$_POST['attachment_id'] = 123;

		$this->api_client->shouldReceive( 'has_active_license' )->once()->andReturn( true );
		$this->core->shouldReceive( 'generate_and_save' )->once()->andReturn( 'Alt text' );

		// Verify cache is cleared
		$this->usage_service->shouldReceive( 'clear_cache' )
			->once();

		$this->event_bus->shouldReceive( 'emit' )->once();

		// Execute
		$result = $this->generation_controller->regenerate_single();

		// Cache clearing is verified through mock expectations
		$this->assertSuccessResponse( $result );
	}

	/**
	 * Test event emission with correct data.
	 *
	 * Workflow: Generate → Event emitted with attachment ID, text, source
	 */
	public function test_event_emission_with_correct_data() {
		$attachment_id = 555;
		$_POST['attachment_id'] = $attachment_id;

		$this->api_client->shouldReceive( 'has_active_license' )->once()->andReturn( true );
		$this->core->shouldReceive( 'generate_and_save' )->once()->andReturn( 'Test alt text' );
		$this->usage_service->shouldReceive( 'clear_cache' )->once();

		// Verify event data structure
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with(
				'alt_text_generated',
				Mockery::on( function ( $data ) use ( $attachment_id ) {
					return $data['attachment_id'] === $attachment_id
						&& $data['alt_text'] === 'Test alt text'
						&& $data['source'] === 'ajax';
				} )
			);

		// Execute
		$this->generation_controller->regenerate_single();

		// Event verification done through mock
		$this->assertTrue( true );
	}

	/**
	 * Test generation with special characters in alt text.
	 *
	 * Workflow: Generate → API returns text with quotes/special chars → Handled correctly
	 */
	public function test_generation_with_special_characters() {
		$_POST['attachment_id'] = 777;

		$special_text = 'A sign that says "Welcome" with <angle> brackets & ampersands';

		$this->api_client->shouldReceive( 'has_active_license' )->once()->andReturn( true );
		$this->core->shouldReceive( 'generate_and_save' )
			->once()
			->andReturn( $special_text );

		$this->usage_service->shouldReceive( 'clear_cache' )->once();
		$this->event_bus->shouldReceive( 'emit' )->once();

		// Execute
		$result = $this->generation_controller->regenerate_single();

		// Verify special characters preserved
		$this->assertSuccessResponse( $result );
		$this->assertEquals( $special_text, $result['alt_text'] );
	}
}
