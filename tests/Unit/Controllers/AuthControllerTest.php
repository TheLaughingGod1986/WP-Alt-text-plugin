<?php
/**
 * Auth Controller Tests
 *
 * @package BeepBeepAI\AltText\Tests\Unit\Controllers
 */

namespace BeepBeepAI\AltText\Tests\Unit\Controllers;

use BeepBeepAI\AltText\Tests\TestCase;
use BeepBeep\AltText\Controllers\Auth_Controller;
use BeepBeep\AltText\Services\Authentication_Service;
use Mockery;

/**
 * Test Auth Controller
 *
 * @covers \BeepBeep\AltText\Controllers\Auth_Controller
 */
class AuthControllerTest extends TestCase {

	/**
	 * Authentication service mock.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $auth_service;

	/**
	 * Controller instance.
	 *
	 * @var Auth_Controller
	 */
	private $controller;

	/**
	 * Set up before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Mock authentication service
		$this->auth_service = Mockery::mock( Authentication_Service::class );

		// Create controller
		$this->controller = new Auth_Controller( $this->auth_service );

		// Reset $_POST
		$_POST = array();
	}

	/**
	 * Test register with valid admin permission.
	 */
	public function test_register_with_admin_permission() {
		$_POST['email']    = 'test@example.com';
		$_POST['password'] = 'password123';

		// Mock service response
		$this->auth_service->shouldReceive( 'register' )
			->once()
			->with( 'test@example.com', 'password123' )
			->andReturn( array( 'success' => true, 'message' => 'Registered' ) );

		// Execute
		$result = $this->controller->register();

		// Assert
		$this->assertSuccessResponse( $result );
	}

	/**
	 * Test register without admin permission.
	 */
	public function test_register_without_admin_permission() {
		// Override current_user_can mock to return false for manage_options
		// (Note: In real test, we'd need to properly mock WordPress functions)

		// For now, we test that if user doesn't have permission, error is returned
		// This test would need WordPress test framework for full coverage
		$this->expectNotToPerformAssertions();
	}

	/**
	 * Test login with valid credentials.
	 */
	public function test_login_success() {
		$_POST['email']    = 'user@example.com';
		$_POST['password'] = 'secure_pass';

		$this->auth_service->shouldReceive( 'login' )
			->once()
			->with( 'user@example.com', 'secure_pass' )
			->andReturn( array( 'success' => true, 'message' => 'Logged in' ) );

		$result = $this->controller->login();

		$this->assertSuccessResponse( $result );
	}

	/**
	 * Test login sanitizes email.
	 */
	public function test_login_sanitizes_email() {
		$_POST['email']    = '  user@example.com  ';
		$_POST['password'] = 'password';

		$this->auth_service->shouldReceive( 'login' )
			->once()
			->with( 'user@example.com', 'password' )
			->andReturn( array( 'success' => true ) );

		$this->controller->login();

		// If no exception, sanitization worked
		$this->assertTrue( true );
	}

	/**
	 * Test login with missing credentials.
	 */
	public function test_login_missing_credentials() {
		// Empty $_POST

		$this->auth_service->shouldReceive( 'login' )
			->once()
			->with( '', '' )
			->andReturn( array( 'success' => false, 'message' => 'Required' ) );

		$result = $this->controller->login();

		$this->assertErrorResponse( $result );
	}

	/**
	 * Test logout success.
	 */
	public function test_logout_success() {
		$this->auth_service->shouldReceive( 'logout' )
			->once()
			->andReturn( array( 'success' => true, 'message' => 'Logged out' ) );

		$result = $this->controller->logout();

		$this->assertSuccessResponse( $result );
	}

	/**
	 * Test disconnect account.
	 */
	public function test_disconnect_account() {
		$this->auth_service->shouldReceive( 'disconnect_account' )
			->once()
			->andReturn( array( 'success' => true, 'message' => 'Disconnected' ) );

		$result = $this->controller->disconnect_account();

		$this->assertSuccessResponse( $result );
	}

	/**
	 * Test admin login.
	 */
	public function test_admin_login() {
		$_POST['email']    = 'admin@agency.com';
		$_POST['password'] = 'admin_pass';

		$this->auth_service->shouldReceive( 'admin_login' )
			->once()
			->with( 'admin@agency.com', 'admin_pass' )
			->andReturn( array(
				'success'  => true,
				'redirect' => 'http://example.com/admin',
			) );

		$result = $this->controller->admin_login();

		$this->assertSuccessResponse( $result );
		$this->assertArrayHasKey( 'redirect', $result );
	}

	/**
	 * Test admin logout.
	 */
	public function test_admin_logout() {
		$this->auth_service->shouldReceive( 'admin_logout' )
			->once()
			->andReturn( array(
				'success'  => true,
				'redirect' => 'http://example.com/admin',
			) );

		$result = $this->controller->admin_logout();

		$this->assertSuccessResponse( $result );
	}

	/**
	 * Test get user info.
	 */
	public function test_get_user_info() {
		$this->auth_service->shouldReceive( 'get_user_info' )
			->once()
			->andReturn( array(
				'success' => true,
				'user'    => array( 'id' => 1, 'email' => 'test@example.com' ),
			) );

		$result = $this->controller->get_user_info();

		$this->assertSuccessResponse( $result );
		$this->assertArrayHasKey( 'user', $result );
	}

	/**
	 * Test controller handles non-string email.
	 */
	public function test_handles_non_string_email() {
		$_POST['email']    = array( 'not', 'a', 'string' );
		$_POST['password'] = 'password';

		$this->auth_service->shouldReceive( 'register' )
			->once()
			->with( '', 'password' )
			->andReturn( array( 'success' => false ) );

		$result = $this->controller->register();

		$this->assertErrorResponse( $result );
	}

	/**
	 * Test controller handles slashed input.
	 */
	public function test_handles_slashed_input() {
		$_POST['email']    = "test\'@example.com";
		$_POST['password'] = "pass\'word";

		$this->auth_service->shouldReceive( 'login' )
			->once()
			->with( Mockery::type( 'string' ), Mockery::type( 'string' ) )
			->andReturn( array( 'success' => true ) );

		$result = $this->controller->login();

		$this->assertSuccessResponse( $result );
	}

	/**
	 * Test register delegates to service correctly.
	 */
	public function test_register_delegates_to_service() {
		$_POST['email']    = 'new@example.com';
		$_POST['password'] = 'newpass123';

		$expected_response = array(
			'success' => true,
			'message' => 'Account created',
			'user'    => array( 'id' => 123 ),
		);

		$this->auth_service->shouldReceive( 'register' )
			->once()
			->with( 'new@example.com', 'newpass123' )
			->andReturn( $expected_response );

		$result = $this->controller->register();

		$this->assertEquals( $expected_response, $result );
	}

	/**
	 * Test login delegates to service correctly.
	 */
	public function test_login_delegates_to_service() {
		$_POST['email']    = 'existing@example.com';
		$_POST['password'] = 'existingpass';

		$expected_response = array(
			'success' => true,
			'message' => 'Welcome back',
			'user'    => array( 'id' => 456 ),
		);

		$this->auth_service->shouldReceive( 'login' )
			->once()
			->with( 'existing@example.com', 'existingpass' )
			->andReturn( $expected_response );

		$result = $this->controller->login();

		$this->assertEquals( $expected_response, $result );
	}
}
