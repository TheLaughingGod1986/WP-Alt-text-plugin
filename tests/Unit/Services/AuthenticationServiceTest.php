<?php
/**
 * Authentication Service Tests
 *
 * @package BeepBeepAI\AltText\Tests\Unit\Services
 */

namespace BeepBeepAI\AltText\Tests\Unit\Services;

use BeepBeepAI\AltText\Tests\TestCase;
use BeepBeep\AltText\Services\Authentication_Service;
use BeepBeep\AltText\Core\Event_Bus;
use Mockery;

/**
 * Test Authentication Service
 *
 * @covers \BeepBeep\AltText\Services\Authentication_Service
 */
class AuthenticationServiceTest extends TestCase {

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
	 * Authentication service instance.
	 *
	 * @var Authentication_Service
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
		$this->service = new Authentication_Service( $this->api_client, $this->event_bus );
	}

	/**
	 * Test successful user registration.
	 */
	public function test_register_success() {
		$email    = 'test@example.com';
		$password = 'password123';

		// Mock API client behavior
		$this->api_client->shouldReceive( 'get_token' )
			->once()
			->andReturn( '' );

		$this->api_client->shouldReceive( 'register' )
			->once()
			->with( $email, $password )
			->andReturn( array(
				'user' => array(
					'id'    => 123,
					'email' => $email,
				),
			) );

		// Expect event emission
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'user_registered', Mockery::type( 'array' ) );

		// Execute
		$result = $this->service->register( $email, $password );

		// Assert
		$this->assertSuccessResponse( $result );
		$this->assertArrayHasKey( 'user', $result );
		$this->assertEquals( $email, $result['user']['email'] );
	}

	/**
	 * Test registration with empty email.
	 */
	public function test_register_empty_email() {
		$result = $this->service->register( '', 'password123' );

		$this->assertErrorResponse( $result );
		$this->assertStringContainsString( 'required', $result['message'] );
	}

	/**
	 * Test registration with empty password.
	 */
	public function test_register_empty_password() {
		$result = $this->service->register( 'test@example.com', '' );

		$this->assertErrorResponse( $result );
		$this->assertStringContainsString( 'required', $result['message'] );
	}

	/**
	 * Test registration when free plan already exists.
	 */
	public function test_register_free_plan_exists() {
		$email    = 'test@example.com';
		$password = 'password123';

		// Mock existing token
		$this->api_client->shouldReceive( 'get_token' )
			->once()
			->andReturn( 'existing_token' );

		// Mock usage showing free plan
		$this->api_client->shouldReceive( 'get_usage' )
			->once()
			->andReturn( array(
				'plan' => 'free',
			) );

		// Execute
		$result = $this->service->register( $email, $password );

		// Assert
		$this->assertErrorResponse( $result );
		$this->assertEquals( 'free_plan_exists', $result['code'] );
		$this->assertStringContainsString( 'free account', strtolower( $result['message'] ) );
	}

	/**
	 * Test registration when API returns error.
	 */
	public function test_register_api_error() {
		$email    = 'test@example.com';
		$password = 'password123';

		// Mock API client behavior
		$this->api_client->shouldReceive( 'get_token' )
			->once()
			->andReturn( '' );

		// Mock API error
		$wp_error = new \WP_Error( 'api_error', 'API connection failed' );
		$this->api_client->shouldReceive( 'register' )
			->once()
			->andReturn( $wp_error );

		// Execute
		$result = $this->service->register( $email, $password );

		// Assert
		$this->assertErrorResponse( $result );
		$this->assertEquals( 'API connection failed', $result['message'] );
	}

	/**
	 * Test successful user login.
	 */
	public function test_login_success() {
		$email    = 'test@example.com';
		$password = 'password123';

		// Mock API client behavior
		$this->api_client->shouldReceive( 'login' )
			->once()
			->with( $email, $password )
			->andReturn( array(
				'user' => array(
					'id'    => 123,
					'email' => $email,
				),
			) );

		// Expect event emission
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'user_logged_in', Mockery::type( 'array' ) );

		// Execute
		$result = $this->service->login( $email, $password );

		// Assert
		$this->assertSuccessResponse( $result );
		$this->assertArrayHasKey( 'user', $result );
	}

	/**
	 * Test login with empty credentials.
	 */
	public function test_login_empty_credentials() {
		$result = $this->service->login( '', '' );

		$this->assertErrorResponse( $result );
		$this->assertStringContainsString( 'required', $result['message'] );
	}

	/**
	 * Test login with invalid credentials.
	 */
	public function test_login_invalid_credentials() {
		$email    = 'test@example.com';
		$password = 'wrong_password';

		// Mock API error
		$wp_error = new \WP_Error( 'invalid_credentials', 'Invalid email or password' );
		$this->api_client->shouldReceive( 'login' )
			->once()
			->andReturn( $wp_error );

		// Execute
		$result = $this->service->login( $email, $password );

		// Assert
		$this->assertErrorResponse( $result );
		$this->assertStringContainsString( 'Invalid email or password', $result['message'] );
	}

	/**
	 * Test successful logout.
	 */
	public function test_logout_success() {
		// Mock API client behavior
		$this->api_client->shouldReceive( 'clear_token' )
			->once();

		// Expect event emission
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'user_logged_out', null );

		// Execute
		$result = $this->service->logout();

		// Assert
		$this->assertSuccessResponse( $result );
		$this->assertStringContainsString( 'Logged out', $result['message'] );
	}

	/**
	 * Test disconnect account.
	 */
	public function test_disconnect_account() {
		// Mock API client behavior
		$this->api_client->shouldReceive( 'clear_token' )
			->once();

		$this->api_client->shouldReceive( 'clear_license_key' )
			->once();

		// Expect event emission
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'account_disconnected', null );

		// Execute
		$result = $this->service->disconnect_account();

		// Assert
		$this->assertSuccessResponse( $result );
		$this->assertStringContainsString( 'disconnected', strtolower( $result['message'] ) );
	}

	/**
	 * Test get user info when authenticated.
	 */
	public function test_get_user_info_authenticated() {
		$user_data = array(
			'id'    => 123,
			'email' => 'test@example.com',
		);

		$usage_data = array(
			'used'  => 10,
			'limit' => 100,
		);

		// Mock API client behavior
		$this->api_client->shouldReceive( 'is_authenticated' )
			->once()
			->andReturn( true );

		$this->api_client->shouldReceive( 'get_user_info' )
			->once()
			->andReturn( $user_data );

		$this->api_client->shouldReceive( 'get_usage' )
			->once()
			->andReturn( $usage_data );

		// Execute
		$result = $this->service->get_user_info();

		// Assert
		$this->assertSuccessResponse( $result );
		$this->assertArrayHasKey( 'user', $result );
		$this->assertArrayHasKey( 'usage', $result );
		$this->assertEquals( $user_data, $result['user'] );
		$this->assertEquals( $usage_data, $result['usage'] );
	}

	/**
	 * Test get user info when not authenticated.
	 */
	public function test_get_user_info_not_authenticated() {
		// Mock API client behavior
		$this->api_client->shouldReceive( 'is_authenticated' )
			->once()
			->andReturn( false );

		// Execute
		$result = $this->service->get_user_info();

		// Assert
		$this->assertErrorResponse( $result );
		$this->assertEquals( 'not_authenticated', $result['code'] );
	}

	/**
	 * Test is authenticated returns true.
	 */
	public function test_is_authenticated_true() {
		$this->api_client->shouldReceive( 'is_authenticated' )
			->once()
			->andReturn( true );

		$this->assertTrue( $this->service->is_authenticated() );
	}

	/**
	 * Test is authenticated returns false.
	 */
	public function test_is_authenticated_false() {
		$this->api_client->shouldReceive( 'is_authenticated' )
			->once()
			->andReturn( false );

		$this->assertFalse( $this->service->is_authenticated() );
	}

	/**
	 * Test admin login with valid agency license.
	 */
	public function test_admin_login_with_agency_license() {
		$email    = 'admin@agency.com';
		$password = 'admin123';

		// Mock license verification
		$this->api_client->shouldReceive( 'has_active_license' )
			->once()
			->andReturn( true );

		$this->api_client->shouldReceive( 'get_license_data' )
			->once()
			->andReturn( array(
				'organization' => array(
					'plan' => 'agency',
				),
			) );

		// Mock successful login
		$this->api_client->shouldReceive( 'login' )
			->once()
			->with( $email, $password )
			->andReturn( array( 'user' => array( 'id' => 456 ) ) );

		// Expect event emission
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'admin_logged_in', Mockery::type( 'array' ) );

		// Execute
		$result = $this->service->admin_login( $email, $password );

		// Assert
		$this->assertSuccessResponse( $result );
		$this->assertArrayHasKey( 'redirect', $result );
	}

	/**
	 * Test admin login without agency license.
	 */
	public function test_admin_login_without_agency_license() {
		$email    = 'user@example.com';
		$password = 'password123';

		// Mock no license
		$this->api_client->shouldReceive( 'has_active_license' )
			->once()
			->andReturn( false );

		// Execute
		$result = $this->service->admin_login( $email, $password );

		// Assert
		$this->assertErrorResponse( $result );
		$this->assertStringContainsString( 'agency', strtolower( $result['message'] ) );
	}

	/**
	 * Test admin logout.
	 */
	public function test_admin_logout() {
		// Expect event emission
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'admin_logged_out', null );

		// Execute
		$result = $this->service->admin_logout();

		// Assert
		$this->assertSuccessResponse( $result );
		$this->assertArrayHasKey( 'redirect', $result );
	}
}
