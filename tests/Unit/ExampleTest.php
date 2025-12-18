<?php
/**
 * Example Test to verify PHPUnit setup
 *
 * @package BeepBeepAI\AltText\Tests\Unit
 */

namespace BeepBeepAI\AltText\Tests\Unit;

use BeepBeepAI\AltText\Tests\TestCase;

/**
 * Example Test Class
 */
class ExampleTest extends TestCase {

	/**
	 * Test that true is true.
	 */
	public function test_true_is_true() {
		$this->assertTrue( true );
	}

	/**
	 * Test array assertion helpers.
	 */
	public function test_array_helpers() {
		$response = $this->mockApiResponse( true, array( 'test' => 'data' ), 'Success' );

		$this->assertSuccessResponse( $response );
		$this->assertEquals( 'data', $response['data']['test'] );
	}

	/**
	 * Test error response helper.
	 */
	public function test_error_response_helper() {
		$response = $this->mockApiResponse( false, array(), 'Error occurred' );

		$this->assertErrorResponse( $response );
		$this->assertEquals( 'Error occurred', $response['message'] );
	}
}
