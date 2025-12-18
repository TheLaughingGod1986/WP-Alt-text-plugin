<?php
/**
 * Authentication Flow Integration Test
 *
 * Tests complete authentication workflows from controller to service.
 *
 * @package BeepBeepAI\AltText\Tests\Integration
 */

namespace BeepBeepAI\AltText\Tests\Integration;

use BeepBeepAI\AltText\Tests\TestCase;
use BeepBeep\AltText\Controllers\Auth_Controller;
use BeepBeep\AltText\Services\Authentication_Service;
use BeepBeep\AltText\Core\Event_Bus;
use Mockery;

/**
 * Test Authentication Workflows
 *
 * @covers \BeepBeep\AltText\Controllers\Auth_Controller
 * @covers \BeepBeep\AltText\Services\Authentication_Service
 */
class AuthenticationFlowTest extends TestCase {

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
	 * Authentication service.
	 *
	 * @var Authentication_Service
	 */
	private $auth_service;

	/**
	 * Authentication controller.
	 *
	 * @var Auth_Controller
	 */
	private $auth_controller;

	/**
	 * Set up before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Create mocks
		$this->api_client = Mockery::mock( '\BbAI_API_Client_V2' );
		$this->event_bus  = Mockery::mock( Event_Bus::class );

		// Create real service and controller instances
		$this->auth_service    = new Authentication_Service( $this->api_client, $this->event_bus );
		$this->auth_controller = new Auth_Controller( $this->auth_service );

		// Reset $_POST
		$_POST = array();
	}

	/**
	 * Test complete registration flow.
	 *
	 * Workflow: User submits registration → API validates → Account created → Event emitted
	 */
	public function test_complete_registration_flow() {
		// Step 1: User submits registration form
		$_POST['email']    = 'newuser@example.com';
		$_POST['password'] = 'secure_password_123';

		// Step 2: Mock API checks (no existing token)
		$this->api_client->shouldReceive( 'get_token' )
			->once()
			->andReturn( '' );

		// Step 3: Mock successful registration
		$this->api_client->shouldReceive( 'register' )
			->once()
			->with( 'newuser@example.com', 'secure_password_123' )
			->andReturn( array(
				'user' => array(
					'id'    => 123,
					'email' => 'newuser@example.com',
					'plan'  => 'free',
				),
			) );

		// Step 4: Expect event emission
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'user_registered', Mockery::type( 'array' ) );

		// Execute full flow through controller → service → API
		$result = $this->auth_controller->register();

		// Verify complete flow succeeded
		$this->assertSuccessResponse( $result );
		$this->assertArrayHasKey( 'user', $result );
		$this->assertEquals( 'newuser@example.com', $result['user']['email'] );
		$this->assertStringContainsString( 'created', strtolower( $result['message'] ) );
	}

	/**
	 * Test registration rejection when free plan exists.
	 *
	 * Workflow: User tries to register → Existing free plan detected → Registration blocked
	 */
	public function test_registration_blocked_by_existing_free_plan() {
		$_POST['email']    = 'another@example.com';
		$_POST['password'] = 'password';

		// Mock existing token with free plan
		$this->api_client->shouldReceive( 'get_token' )
			->once()
			->andReturn( 'existing_token' );

		$this->api_client->shouldReceive( 'get_usage' )
			->once()
			->andReturn( array(
				'plan'  => 'free',
				'used'  => 50,
				'limit' => 100,
			) );

		// Execute
		$result = $this->auth_controller->register();

		// Verify rejection
		$this->assertErrorResponse( $result );
		$this->assertEquals( 'free_plan_exists', $result['code'] );
		$this->assertStringContainsString( 'free', strtolower( $result['message'] ) );
	}

	/**
	 * Test complete login flow.
	 *
	 * Workflow: User submits login → Credentials validated → Session created → Event emitted
	 */
	public function test_complete_login_flow() {
		$_POST['email']    = 'existing@example.com';
		$_POST['password'] = 'user_password';

		// Mock successful login
		$this->api_client->shouldReceive( 'login' )
			->once()
			->with( 'existing@example.com', 'user_password' )
			->andReturn( array(
				'user' => array(
					'id'    => 456,
					'email' => 'existing@example.com',
				),
			) );

		// Expect event
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'user_logged_in', Mockery::type( 'array' ) );

		// Execute
		$result = $this->auth_controller->login();

		// Verify
		$this->assertSuccessResponse( $result );
		$this->assertArrayHasKey( 'user', $result );
		$this->assertEquals( 456, $result['user']['id'] );
	}

	/**
	 * Test failed login flow.
	 *
	 * Workflow: User submits wrong password → API rejects → Error returned
	 */
	public function test_failed_login_flow() {
		$_POST['email']    = 'user@example.com';
		$_POST['password'] = 'wrong_password';

		// Mock failed login
		$wp_error = new \WP_Error( 'invalid_credentials', 'Invalid email or password' );
		$this->api_client->shouldReceive( 'login' )
			->once()
			->andReturn( $wp_error );

		// Execute
		$result = $this->auth_controller->login();

		// Verify failure
		$this->assertErrorResponse( $result );
		$this->assertStringContainsString( 'Invalid', $result['message'] );
	}

	/**
	 * Test complete logout flow.
	 *
	 * Workflow: User logs out → Token cleared → Event emitted
	 */
	public function test_complete_logout_flow() {
		// Mock token clearing
		$this->api_client->shouldReceive( 'clear_token' )
			->once();

		// Expect event
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'user_logged_out', null );

		// Execute
		$result = $this->auth_controller->logout();

		// Verify
		$this->assertSuccessResponse( $result );
		$this->assertStringContainsString( 'logged out', strtolower( $result['message'] ) );
	}

	/**
	 * Test register → login → logout sequence.
	 *
	 * Complete workflow: New user registers → Logs in → Logs out
	 */
	public function test_complete_user_lifecycle() {
		// Step 1: Register
		$_POST['email']    = 'lifecycle@example.com';
		$_POST['password'] = 'test123';

		$this->api_client->shouldReceive( 'get_token' )->once()->andReturn( '' );
		$this->api_client->shouldReceive( 'register' )
			->once()
			->andReturn( array( 'user' => array( 'id' => 789 ) ) );
		$this->event_bus->shouldReceive( 'emit' )->once()->with( 'user_registered', Mockery::any() );

		$register_result = $this->auth_controller->register();
		$this->assertSuccessResponse( $register_result );

		// Step 2: Login
		$this->api_client->shouldReceive( 'login' )
			->once()
			->andReturn( array( 'user' => array( 'id' => 789 ) ) );
		$this->event_bus->shouldReceive( 'emit' )->once()->with( 'user_logged_in', Mockery::any() );

		$login_result = $this->auth_controller->login();
		$this->assertSuccessResponse( $login_result );

		// Step 3: Logout
		$this->api_client->shouldReceive( 'clear_token' )->once();
		$this->event_bus->shouldReceive( 'emit' )->once()->with( 'user_logged_out', null );

		$logout_result = $this->auth_controller->logout();
		$this->assertSuccessResponse( $logout_result );
	}

	/**
	 * Test disconnect account flow.
	 *
	 * Workflow: User disconnects → All data cleared → Event emitted
	 */
	public function test_disconnect_account_flow() {
		// Mock all clearing operations
		$this->api_client->shouldReceive( 'clear_token' )->once();
		$this->api_client->shouldReceive( 'clear_license_key' )->once();

		// Expect event
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'account_disconnected', null );

		// Execute
		$result = $this->auth_controller->disconnect_account();

		// Verify
		$this->assertSuccessResponse( $result );
		$this->assertStringContainsString( 'disconnect', strtolower( $result['message'] ) );
	}

	/**
	 * Test get user info after authentication.
	 *
	 * Workflow: User authenticates → Requests info → Data returned
	 */
	public function test_get_user_info_flow() {
		// Mock authenticated state
		$this->api_client->shouldReceive( 'is_authenticated' )
			->once()
			->andReturn( true );

		// Mock user info retrieval
		$this->api_client->shouldReceive( 'get_user_info' )
			->once()
			->andReturn( array(
				'id'    => 100,
				'email' => 'info@example.com',
				'plan'  => 'pro',
			) );

		$this->api_client->shouldReceive( 'get_usage' )
			->once()
			->andReturn( array(
				'used'  => 500,
				'limit' => 10000,
			) );

		// Execute
		$result = $this->auth_controller->get_user_info();

		// Verify
		$this->assertSuccessResponse( $result );
		$this->assertArrayHasKey( 'user', $result );
		$this->assertArrayHasKey( 'usage', $result );
		$this->assertEquals( 'info@example.com', $result['user']['email'] );
	}

	/**
	 * Test admin login with agency license.
	 *
	 * Workflow: Check license → Admin logs in → Session created
	 */
	public function test_admin_login_with_agency_flow() {
		$_POST['email']    = 'admin@agency.com';
		$_POST['password'] = 'admin_pass';

		// Mock agency license check
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

		// Mock successful admin login
		$this->api_client->shouldReceive( 'login' )
			->once()
			->andReturn( array( 'user' => array( 'id' => 1 ) ) );

		// Expect event
		$this->event_bus->shouldReceive( 'emit' )
			->once()
			->with( 'admin_logged_in', Mockery::type( 'array' ) );

		// Execute
		$result = $this->auth_controller->admin_login();

		// Verify
		$this->assertSuccessResponse( $result );
		$this->assertArrayHasKey( 'redirect', $result );
	}

	/**
	 * Test input sanitization through full flow.
	 *
	 * Workflow: Malicious input → Sanitized → Safe processing
	 */
	public function test_input_sanitization_through_flow() {
		// Potentially malicious input
		$_POST['email']    = '<script>alert("xss")</script>@example.com';
		$_POST['password'] = "'; DROP TABLE users; --";

		// Mock - service should receive sanitized email
		$this->api_client->shouldReceive( 'get_token' )->once()->andReturn( '' );
		$this->api_client->shouldReceive( 'register' )
			->once()
			->with(
				'scriptalert(xss)@example.com', // sanitized email
				Mockery::any() // password passed through
			)
			->andReturn( array( 'user' => array( 'id' => 1 ) ) );

		$this->event_bus->shouldReceive( 'emit' )->once();

		// Execute
		$result = $this->auth_controller->register();

		// Verify sanitization worked and flow completed
		$this->assertSuccessResponse( $result );
	}
}
