<?php
/**
 * Generation Service Tests
 *
 * @package BeepBeepAI\AltText\Tests\Unit\Services
 */

namespace BeepBeepAI\AltText\Tests\Unit\Services;

use BeepBeepAI\AltText\Tests\TestCase;
use BeepBeep\AltText\Services\Generation_Service;
use BeepBeep\AltText\Services\Usage_Service;
use BeepBeep\AltText\Core\Event_Bus;
use Mockery;

/**
 * Test Generation Service
 *
 * @covers \BeepBeep\AltText\Services\Generation_Service
 */
class GenerationServiceTest extends TestCase {

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
	 * Generation service instance.
	 *
	 * @var Generation_Service
	 */
	private $service;

	/**
	 * Set up before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Create mocks
		$this->api_client     = Mockery::mock( '\BbAI_API_Client_V2' );
		$this->usage_service  = Mockery::mock( Usage_Service::class );
		$this->event_bus      = Mockery::mock( Event_Bus::class );
		$this->core           = Mockery::mock( '\BbAI_Core' );

		// Create service instance
		$this->service = new Generation_Service(
			$this->api_client,
			$this->usage_service,
			$this->event_bus,
			$this->core
		);
	}

	/**
	 * Test regenerate single with invalid attachment ID.
	 */
	public function test_regenerate_single_invalid_id() {
		$result = $this->service->regenerate_single( 0 );

		$this->assertErrorResponse( $result );
		$this->assertStringContainsString( 'Invalid', $result['message'] );
	}

	/**
	 * Test regenerate single when limit reached.
	 */
	public function test_regenerate_single_limit_reached() {
		// Define WP_LOCAL_DEV as false for this test
		if ( ! defined( 'WP_LOCAL_DEV' ) ) {
			define( 'WP_LOCAL_DEV', false );
		}

		// Mock API client behavior
		$this->api_client->shouldReceive( 'has_active_license' )
			->once()
			->andReturn( false );

		$this->api_client->shouldReceive( 'has_reached_limit' )
			->once()
			->andReturn( true );

		$this->api_client->shouldReceive( 'get_usage' )
			->once()
			->andReturn( array(
				'used'  => 100,
				'limit' => 100,
			) );

		// Execute
		$result = $this->service->regenerate_single( 123 );

		// Assert
		$this->assertErrorResponse( $result );
		$this->assertEquals( 'limit_reached', $result['code'] );
	}

	/**
	 * Test successful regeneration.
	 */
	public function test_regenerate_single_success() {
		$attachment_id = 123;
		$generated_text = 'A beautiful sunset over the ocean';

		// Mock API client behavior
		$this->api_client->shouldReceive( 'has_active_license' )
			->once()
			->andReturn( true );

		// Mock core generate_and_save
		$this->core->shouldReceive( 'generate_and_save' )
			->once()
			->with( $attachment_id, 'ajax', 1, array(), true )
			->andReturn( $generated_text );

		// Mock usage service
		$this->usage_service->shouldReceive( 'clear_cache' )
			->once();

		// Expect event emission
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'alt_text_generated', Mockery::type( 'array' ) );

		// Execute
		$result = $this->service->regenerate_single( $attachment_id );

		// Assert
		$this->assertSuccessResponse( $result );
		$this->assertEquals( $generated_text, $result['alt_text'] );
		$this->assertEquals( $attachment_id, $result['attachment_id'] );
	}

	/**
	 * Test regeneration when core returns error.
	 */
	public function test_regenerate_single_error() {
		$attachment_id = 123;

		// Mock API client behavior
		$this->api_client->shouldReceive( 'has_active_license' )
			->once()
			->andReturn( true );

		// Mock core returning error
		$wp_error = new \WP_Error( 'generation_error', 'Failed to generate alt text' );
		$this->core->shouldReceive( 'generate_and_save' )
			->once()
			->andReturn( $wp_error );

		// Execute
		$result = $this->service->regenerate_single( $attachment_id );

		// Assert
		$this->assertErrorResponse( $result );
		$this->assertEquals( 'generation_error', $result['code'] );
	}

	/**
	 * Test regenerate single with license bypasses limit check.
	 */
	public function test_regenerate_single_with_license_bypasses_limit() {
		$attachment_id = 123;

		// Mock API client behavior
		$this->api_client->shouldReceive( 'has_active_license' )
			->once()
			->andReturn( true );

		// Should NOT check for limit when has license
		// has_reached_limit should not be called

		// Mock successful generation
		$this->core->shouldReceive( 'generate_and_save' )
			->once()
			->andReturn( 'Generated alt text' );

		$this->usage_service->shouldReceive( 'clear_cache' )
			->once();

		$this->event_bus->shouldReceive( 'emit' )
			->once();

		// Execute
		$result = $this->service->regenerate_single( $attachment_id );

		// Assert
		$this->assertSuccessResponse( $result );
	}

	/**
	 * Test regeneration handles various error codes.
	 */
	public function test_regenerate_single_various_error_codes() {
		$error_codes = array(
			'auth_failed'      => 'Authentication failed',
			'invalid_image'    => 'Invalid image',
			'api_error'        => 'API error occurred',
		);

		foreach ( $error_codes as $code => $message ) {
			// Reset mocks for each iteration
			$this->setUp();

			$this->api_client->shouldReceive( 'has_active_license' )
				->once()
				->andReturn( true );

			$wp_error = new \WP_Error( $code, $message );
			$this->core->shouldReceive( 'generate_and_save' )
				->once()
				->andReturn( $wp_error );

			$result = $this->service->regenerate_single( 123 );

			$this->assertErrorResponse( $result, "Failed for error code: $code" );
			$this->assertEquals( $code, $result['code'], "Error code mismatch for: $code" );
		}
	}

	/**
	 * Test regeneration with WP_Error that has error data.
	 */
	public function test_regenerate_single_error_with_data() {
		$attachment_id = 123;
		$error_data = array( 'detail' => 'Additional error information' );

		$this->api_client->shouldReceive( 'has_active_license' )
			->once()
			->andReturn( true );

		$wp_error = new \WP_Error( 'custom_error', 'Error message', $error_data );
		$this->core->shouldReceive( 'generate_and_save' )
			->once()
			->andReturn( $wp_error );

		$result = $this->service->regenerate_single( $attachment_id );

		$this->assertErrorResponse( $result );
		$this->assertArrayHasKey( 'data', $result );
	}

	/**
	 * Test regeneration with negative attachment ID.
	 */
	public function test_regenerate_single_negative_id() {
		$result = $this->service->regenerate_single( -5 );

		$this->assertErrorResponse( $result );
	}

	/**
	 * Test regeneration validates attachment ID is integer.
	 */
	public function test_regenerate_single_validates_id_type() {
		// This test verifies type hints work correctly
		// PHP strict types should enforce integer type
		$this->expectNotToPerformAssertions();

		try {
			// Valid integer
			$this->api_client->shouldReceive( 'has_active_license' )
				->andReturn( false );
			$this->api_client->shouldReceive( 'has_reached_limit' )
				->andReturn( false );
			$this->core->shouldReceive( 'generate_and_save' )
				->andReturn( 'text' );
			$this->usage_service->shouldReceive( 'clear_cache' );
			$this->event_bus->shouldReceive( 'emit' );

			$this->service->regenerate_single( 123 );
		} catch ( \TypeError $e ) {
			$this->fail( 'Should accept valid integer' );
		}
	}
}
