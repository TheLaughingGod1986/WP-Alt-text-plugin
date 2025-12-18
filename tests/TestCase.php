<?php
/**
 * Base test case for all tests.
 *
 * @package BeepBeepAI\AltText\Tests
 */

namespace BeepBeepAI\AltText\Tests;

use PHPUnit\Framework\TestCase as PHPUnit_TestCase;
use Mockery;

/**
 * Base test case class.
 */
abstract class TestCase extends PHPUnit_TestCase {

    /**
     * Set up before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        Mockery::getConfiguration()->allowMockingNonExistentMethods(true);
    }

    /**
     * Tear down after each test.
     */
    protected function tearDown(): void {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Create a mock API client response.
     *
     * @param bool  $success Whether the response is successful.
     * @param array $data Response data.
     * @param string $message Response message.
     * @return array
     */
    protected function mockApiResponse( $success = true, $data = array(), $message = '' ) {
        return array(
            'success' => $success,
            'data'    => $data,
            'message' => $message,
        );
    }

    /**
     * Create a mock WordPress HTTP response.
     *
     * @param int   $code Response code.
     * @param array $body Response body.
     * @return array
     */
    protected function mockHttpResponse( $code = 200, $body = array() ) {
        return array(
            'response' => array( 'code' => $code ),
            'body'     => wp_json_encode( $body ),
        );
    }

    /**
     * Assert that an array has the expected keys.
     *
     * @param array $expected_keys Expected keys.
     * @param array $array Array to check.
     * @param string $message Optional message.
     */
    protected function assertArrayHasKeys( array $expected_keys, array $array, $message = '' ) {
        foreach ( $expected_keys as $key ) {
            $this->assertArrayHasKey( $key, $array, $message . " Missing key: $key" );
        }
    }

    /**
     * Assert that a response is a successful API response.
     *
     * @param array $response Response to check.
     * @param string $message Optional message.
     */
    protected function assertSuccessResponse( array $response, $message = '' ) {
        $this->assertIsArray( $response, $message );
        $this->assertArrayHasKey( 'success', $response, $message );
        $this->assertTrue( $response['success'], $message );
    }

    /**
     * Assert that a response is an error API response.
     *
     * @param array $response Response to check.
     * @param string $message Optional message.
     */
    protected function assertErrorResponse( array $response, $message = '' ) {
        $this->assertIsArray( $response, $message );
        $this->assertArrayHasKey( 'success', $response, $message );
        $this->assertFalse( $response['success'], $message );
    }
}
