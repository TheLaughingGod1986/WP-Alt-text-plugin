<?php
/**
 * License Service Tests
 *
 * @package BeepBeepAI\AltText\Tests\Unit\Services
 */

namespace BeepBeepAI\AltText\Tests\Unit\Services;

use BeepBeepAI\AltText\Tests\TestCase;
use BeepBeep\AltText\Services\License_Service;
use BeepBeep\AltText\Core\Event_Bus;
use Mockery;

/**
 * Test License Service
 *
 * @covers \BeepBeep\AltText\Services\License_Service
 */
class LicenseServiceTest extends TestCase {

	/**
	 * API client mock.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $api_client;

	/**
	 * Event bus mock.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $event_bus;

	/**
	 * License service instance.
	 *
	 * @var License_Service
	 */
	private $service;

	/**
	 * Set up before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Create mocks
		$this->api_client = Mockery::mock( '\BbAI_API_Client_V2' );
		$this->event_bus  = Mockery::mock( Event_Bus::class );

		// Create service instance
		$this->service = new License_Service( $this->api_client, $this->event_bus );
	}

	/**
	 * Test successful license activation.
	 */
	public function test_activate_success() {
		$license_key = '12345678-1234-1234-1234-123456789abc';

		// Mock API client behavior
		$this->api_client->shouldReceive( 'activate_license' )
			->once()
			->with( $license_key )
			->andReturn( array(
				'organization' => array(
					'id'   => 1,
					'name' => 'Test Organization',
					'plan' => 'agency',
				),
				'site' => array(
					'id'  => 123,
					'url' => 'https://example.com',
				),
			) );

		// Expect event emission
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'license_activated', Mockery::type( 'array' ) );

		// Execute
		$result = $this->service->activate( $license_key );

		// Assert
		$this->assertSuccessResponse( $result );
		$this->assertArrayHasKey( 'organization', $result );
		$this->assertArrayHasKey( 'site', $result );
		$this->assertEquals( 'Test Organization', $result['organization']['name'] );
	}

	/**
	 * Test activation with empty license key.
	 */
	public function test_activate_empty_key() {
		$result = $this->service->activate( '' );

		$this->assertErrorResponse( $result );
		$this->assertStringContainsString( 'required', $result['message'] );
	}

	/**
	 * Test activation with invalid UUID format.
	 */
	public function test_activate_invalid_format() {
		$result = $this->service->activate( 'invalid-key-format' );

		$this->assertErrorResponse( $result );
		$this->assertStringContainsString( 'Invalid', $result['message'] );
	}

	/**
	 * Test activation with various invalid UUID formats.
	 */
	public function test_activate_invalid_uuid_formats() {
		$invalid_keys = array(
			'12345678-1234-1234-1234',           // Too short
			'12345678-1234-1234-1234-123456789abcd', // Too long
			'GGGGGGGG-1234-1234-1234-123456789abc', // Invalid hex
			'12345678_1234_1234_1234_123456789abc', // Wrong separator
		);

		foreach ( $invalid_keys as $key ) {
			$result = $this->service->activate( $key );
			$this->assertErrorResponse( $result, "Failed for key: $key" );
		}
	}

	/**
	 * Test activation when API returns error.
	 */
	public function test_activate_api_error() {
		$license_key = '12345678-1234-1234-1234-123456789abc';

		// Mock API error
		$wp_error = new \WP_Error( 'license_error', 'License key is invalid or already activated' );
		$this->api_client->shouldReceive( 'activate_license' )
			->once()
			->andReturn( $wp_error );

		// Execute
		$result = $this->service->activate( $license_key );

		// Assert
		$this->assertErrorResponse( $result );
		$this->assertStringContainsString( 'invalid', strtolower( $result['message'] ) );
	}

	/**
	 * Test successful license deactivation.
	 */
	public function test_deactivate_success() {
		// Mock API client behavior
		$this->api_client->shouldReceive( 'deactivate_license' )
			->once()
			->andReturn( array( 'success' => true ) );

		// Expect event emission
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'license_deactivated', null );

		// Execute
		$result = $this->service->deactivate();

		// Assert
		$this->assertSuccessResponse( $result );
		$this->assertStringContainsString( 'deactivated', strtolower( $result['message'] ) );
	}

	/**
	 * Test deactivation when API returns error.
	 */
	public function test_deactivate_api_error() {
		// Mock API error
		$wp_error = new \WP_Error( 'deactivation_error', 'Failed to deactivate license' );
		$this->api_client->shouldReceive( 'deactivate_license' )
			->once()
			->andReturn( $wp_error );

		// Execute
		$result = $this->service->deactivate();

		// Assert
		$this->assertErrorResponse( $result );
		$this->assertEquals( 'Failed to deactivate license', $result['message'] );
	}

	/**
	 * Test get license sites when authenticated.
	 */
	public function test_get_license_sites_authenticated() {
		$sites_data = array(
			array(
				'id'         => 1,
				'url'        => 'https://site1.com',
				'activated'  => '2025-01-01',
				'last_seen'  => '2025-01-15',
			),
			array(
				'id'         => 2,
				'url'        => 'https://site2.com',
				'activated'  => '2025-01-05',
				'last_seen'  => '2025-01-16',
			),
		);

		// Mock authentication
		$this->api_client->shouldReceive( 'is_authenticated' )
			->once()
			->andReturn( true );

		// Mock API response
		$this->api_client->shouldReceive( 'get_license_sites' )
			->once()
			->andReturn( $sites_data );

		// Execute
		$result = $this->service->get_license_sites();

		// Assert
		$this->assertSuccessResponse( $result );
		$this->assertArrayHasKey( 'sites', $result );
		$this->assertCount( 2, $result['sites'] );
		$this->assertEquals( 'https://site1.com', $result['sites'][0]['url'] );
	}

	/**
	 * Test get license sites when not authenticated.
	 */
	public function test_get_license_sites_not_authenticated() {
		// Mock not authenticated
		$this->api_client->shouldReceive( 'is_authenticated' )
			->once()
			->andReturn( false );

		// Execute
		$result = $this->service->get_license_sites();

		// Assert
		$this->assertErrorResponse( $result );
		$this->assertStringContainsString( 'log in', strtolower( $result['message'] ) );
	}

	/**
	 * Test get license sites when API returns error.
	 */
	public function test_get_license_sites_api_error() {
		// Mock authentication
		$this->api_client->shouldReceive( 'is_authenticated' )
			->once()
			->andReturn( true );

		// Mock API error
		$wp_error = new \WP_Error( 'api_error', 'Failed to fetch sites' );
		$this->api_client->shouldReceive( 'get_license_sites' )
			->once()
			->andReturn( $wp_error );

		// Execute
		$result = $this->service->get_license_sites();

		// Assert
		$this->assertErrorResponse( $result );
		$this->assertEquals( 'Failed to fetch sites', $result['message'] );
	}

	/**
	 * Test successful site disconnection.
	 */
	public function test_disconnect_site_success() {
		$site_id = 'site-123';

		// Mock authentication
		$this->api_client->shouldReceive( 'is_authenticated' )
			->once()
			->andReturn( true );

		// Mock API response
		$this->api_client->shouldReceive( 'disconnect_license_site' )
			->once()
			->with( $site_id )
			->andReturn( array( 'disconnected' => true ) );

		// Expect event emission
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'license_site_disconnected', array( 'site_id' => $site_id ) );

		// Execute
		$result = $this->service->disconnect_site( $site_id );

		// Assert
		$this->assertSuccessResponse( $result );
		$this->assertArrayHasKey( 'data', $result );
		$this->assertStringContainsString( 'disconnected', strtolower( $result['message'] ) );
	}

	/**
	 * Test disconnect site when not authenticated.
	 */
	public function test_disconnect_site_not_authenticated() {
		// Mock not authenticated
		$this->api_client->shouldReceive( 'is_authenticated' )
			->once()
			->andReturn( false );

		// Execute
		$result = $this->service->disconnect_site( 'site-123' );

		// Assert
		$this->assertErrorResponse( $result );
		$this->assertStringContainsString( 'log in', strtolower( $result['message'] ) );
	}

	/**
	 * Test disconnect site with empty site ID.
	 */
	public function test_disconnect_site_empty_id() {
		// Mock authentication
		$this->api_client->shouldReceive( 'is_authenticated' )
			->once()
			->andReturn( true );

		// Execute
		$result = $this->service->disconnect_site( '' );

		// Assert
		$this->assertErrorResponse( $result );
		$this->assertStringContainsString( 'required', strtolower( $result['message'] ) );
	}

	/**
	 * Test disconnect site when API returns error.
	 */
	public function test_disconnect_site_api_error() {
		$site_id = 'site-123';

		// Mock authentication
		$this->api_client->shouldReceive( 'is_authenticated' )
			->once()
			->andReturn( true );

		// Mock API error
		$wp_error = new \WP_Error( 'disconnect_error', 'Site not found' );
		$this->api_client->shouldReceive( 'disconnect_license_site' )
			->once()
			->andReturn( $wp_error );

		// Execute
		$result = $this->service->disconnect_site( $site_id );

		// Assert
		$this->assertErrorResponse( $result );
		$this->assertEquals( 'Site not found', $result['message'] );
	}

	/**
	 * Test has active license returns true.
	 */
	public function test_has_active_license_true() {
		$this->api_client->shouldReceive( 'has_active_license' )
			->once()
			->andReturn( true );

		$this->assertTrue( $this->service->has_active_license() );
	}

	/**
	 * Test has active license returns false.
	 */
	public function test_has_active_license_false() {
		$this->api_client->shouldReceive( 'has_active_license' )
			->once()
			->andReturn( false );

		$this->assertFalse( $this->service->has_active_license() );
	}

	/**
	 * Test get license data with valid license.
	 */
	public function test_get_license_data_valid() {
		$license_data = array(
			'key'          => '12345678-1234-1234-1234-123456789abc',
			'organization' => array(
				'id'   => 1,
				'name' => 'Test Org',
				'plan' => 'agency',
			),
			'expires'      => '2026-01-01',
		);

		$this->api_client->shouldReceive( 'get_license_data' )
			->once()
			->andReturn( $license_data );

		$result = $this->service->get_license_data();

		$this->assertIsArray( $result );
		$this->assertEquals( 'Test Org', $result['organization']['name'] );
	}

	/**
	 * Test get license data with no license.
	 */
	public function test_get_license_data_null() {
		$this->api_client->shouldReceive( 'get_license_data' )
			->once()
			->andReturn( null );

		$result = $this->service->get_license_data();

		$this->assertNull( $result );
	}

	/**
	 * Test get organization with valid license data.
	 */
	public function test_get_organization_valid() {
		$license_data = array(
			'organization' => array(
				'id'   => 1,
				'name' => 'Test Organization',
				'plan' => 'pro',
			),
		);

		$this->api_client->shouldReceive( 'get_license_data' )
			->once()
			->andReturn( $license_data );

		$result = $this->service->get_organization();

		$this->assertIsArray( $result );
		$this->assertEquals( 'Test Organization', $result['name'] );
		$this->assertEquals( 'pro', $result['plan'] );
	}

	/**
	 * Test get organization with no license data.
	 */
	public function test_get_organization_null() {
		$this->api_client->shouldReceive( 'get_license_data' )
			->once()
			->andReturn( null );

		$result = $this->service->get_organization();

		$this->assertNull( $result );
	}

	/**
	 * Test get organization when license has no organization.
	 */
	public function test_get_organization_missing() {
		$license_data = array(
			'key' => '12345678-1234-1234-1234-123456789abc',
			// No organization key
		);

		$this->api_client->shouldReceive( 'get_license_data' )
			->once()
			->andReturn( $license_data );

		$result = $this->service->get_organization();

		$this->assertNull( $result );
	}

	/**
	 * Test valid UUID formats are accepted.
	 */
	public function test_valid_uuid_formats_accepted() {
		$valid_keys = array(
			'12345678-1234-1234-1234-123456789abc',
			'ABCDEF12-3456-7890-ABCD-EF1234567890',
			'abcdef12-3456-7890-abcd-ef1234567890',
		);

		foreach ( $valid_keys as $key ) {
			$this->api_client->shouldReceive( 'activate_license' )
				->once()
				->with( $key )
				->andReturn( array( 'organization' => array(), 'site' => array() ) );

			$this->event_bus->shouldReceive( 'emit' )
				->once();

			$result = $this->service->activate( $key );
			$this->assertSuccessResponse( $result, "Failed for valid key: $key" );
		}
	}
}
