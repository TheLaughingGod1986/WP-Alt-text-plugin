<?php
/**
 * License Controller Tests
 *
 * @package BeepBeepAI\AltText\Tests\Unit\Controllers
 */

namespace BeepBeepAI\AltText\Tests\Unit\Controllers;

use BeepBeepAI\AltText\Tests\TestCase;
use BeepBeep\AltText\Controllers\License_Controller;
use BeepBeep\AltText\Services\License_Service;
use Mockery;

/**
 * Test License Controller
 *
 * @covers \BeepBeep\AltText\Controllers\License_Controller
 */
class LicenseControllerTest extends TestCase {

	/**
	 * License service mock.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $license_service;

	/**
	 * Controller instance.
	 *
	 * @var License_Controller
	 */
	private $controller;

	/**
	 * Set up before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Mock license service
		$this->license_service = Mockery::mock( License_Service::class );

		// Create controller
		$this->controller = new License_Controller( $this->license_service );

		// Reset $_POST
		$_POST = array();
	}

	/**
	 * Test activate license with valid key.
	 */
	public function test_activate_license_success() {
		$license_key = '12345678-1234-1234-1234-123456789abc';
		$_POST['license_key'] = $license_key;

		$this->license_service->shouldReceive( 'activate' )
			->once()
			->with( $license_key )
			->andReturn( array(
				'success'      => true,
				'message'      => 'License activated',
				'organization' => array( 'name' => 'Test Org' ),
			) );

		$result = $this->controller->activate_license();

		$this->assertSuccessResponse( $result );
		$this->assertArrayHasKey( 'organization', $result );
	}

	/**
	 * Test activate license sanitizes input.
	 */
	public function test_activate_license_sanitizes_input() {
		$_POST['license_key'] = '  12345678-1234-1234-1234-123456789abc  ';

		$this->license_service->shouldReceive( 'activate' )
			->once()
			->with( '12345678-1234-1234-1234-123456789abc' )
			->andReturn( array( 'success' => true ) );

		$result = $this->controller->activate_license();

		$this->assertSuccessResponse( $result );
	}

	/**
	 * Test activate license with empty key.
	 */
	public function test_activate_license_empty_key() {
		$_POST['license_key'] = '';

		$this->license_service->shouldReceive( 'activate' )
			->once()
			->with( '' )
			->andReturn( array(
				'success' => false,
				'message' => 'License key is required',
			) );

		$result = $this->controller->activate_license();

		$this->assertErrorResponse( $result );
	}

	/**
	 * Test activate license handles non-string input.
	 */
	public function test_activate_license_non_string_input() {
		$_POST['license_key'] = array( 'invalid' );

		$this->license_service->shouldReceive( 'activate' )
			->once()
			->with( '' )
			->andReturn( array( 'success' => false ) );

		$result = $this->controller->activate_license();

		$this->assertErrorResponse( $result );
	}

	/**
	 * Test deactivate license.
	 */
	public function test_deactivate_license() {
		$this->license_service->shouldReceive( 'deactivate' )
			->once()
			->andReturn( array(
				'success' => true,
				'message' => 'License deactivated',
			) );

		$result = $this->controller->deactivate_license();

		$this->assertSuccessResponse( $result );
	}

	/**
	 * Test get license sites.
	 */
	public function test_get_license_sites() {
		$sites_data = array(
			array( 'id' => 1, 'url' => 'https://site1.com' ),
			array( 'id' => 2, 'url' => 'https://site2.com' ),
		);

		$this->license_service->shouldReceive( 'get_license_sites' )
			->once()
			->andReturn( array(
				'success' => true,
				'sites'   => $sites_data,
			) );

		$result = $this->controller->get_license_sites();

		$this->assertSuccessResponse( $result );
		$this->assertArrayHasKey( 'sites', $result );
		$this->assertCount( 2, $result['sites'] );
	}

	/**
	 * Test disconnect license site.
	 */
	public function test_disconnect_license_site() {
		$site_id = 'site-123';
		$_POST['site_id'] = $site_id;

		$this->license_service->shouldReceive( 'disconnect_site' )
			->once()
			->with( $site_id )
			->andReturn( array(
				'success' => true,
				'message' => 'Site disconnected',
			) );

		$result = $this->controller->disconnect_license_site();

		$this->assertSuccessResponse( $result );
	}

	/**
	 * Test disconnect site sanitizes input.
	 */
	public function test_disconnect_site_sanitizes_input() {
		$_POST['site_id'] = '  site-456  ';

		$this->license_service->shouldReceive( 'disconnect_site' )
			->once()
			->with( 'site-456' )
			->andReturn( array( 'success' => true ) );

		$result = $this->controller->disconnect_license_site();

		$this->assertSuccessResponse( $result );
	}

	/**
	 * Test disconnect site with empty ID.
	 */
	public function test_disconnect_site_empty_id() {
		$_POST['site_id'] = '';

		$this->license_service->shouldReceive( 'disconnect_site' )
			->once()
			->with( '' )
			->andReturn( array(
				'success' => false,
				'message' => 'Site ID is required',
			) );

		$result = $this->controller->disconnect_license_site();

		$this->assertErrorResponse( $result );
	}

	/**
	 * Test all methods delegate correctly to service.
	 */
	public function test_methods_delegate_to_service() {
		// Test that controller is truly thin and just delegates

		// Activate
		$_POST['license_key'] = 'test-key';
		$this->license_service->shouldReceive( 'activate' )
			->once()
			->andReturn( array( 'success' => true ) );
		$this->controller->activate_license();

		// Deactivate
		$this->license_service->shouldReceive( 'deactivate' )
			->once()
			->andReturn( array( 'success' => true ) );
		$this->controller->deactivate_license();

		// Get sites
		$this->license_service->shouldReceive( 'get_license_sites' )
			->once()
			->andReturn( array( 'success' => true ) );
		$this->controller->get_license_sites();

		// Disconnect site
		$_POST['site_id'] = 'test-site';
		$this->license_service->shouldReceive( 'disconnect_site' )
			->once()
			->andReturn( array( 'success' => true ) );
		$this->controller->disconnect_license_site();

		// All expectations verified in tearDown
		$this->assertTrue( true );
	}

	/**
	 * Test controller handles missing POST data gracefully.
	 */
	public function test_handles_missing_post_data() {
		// No $_POST data set

		$this->license_service->shouldReceive( 'activate' )
			->once()
			->with( '' )
			->andReturn( array( 'success' => false ) );

		$result = $this->controller->activate_license();

		$this->assertErrorResponse( $result );
	}
}
