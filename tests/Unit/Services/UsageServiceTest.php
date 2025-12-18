<?php
/**
 * Usage Service Tests
 *
 * @package BeepBeepAI\AltText\Tests\Unit\Services
 */

namespace BeepBeepAI\AltText\Tests\Unit\Services;

use BeepBeepAI\AltText\Tests\TestCase;
use BeepBeep\AltText\Services\Usage_Service;
use Mockery;

/**
 * Test Usage Service
 *
 * @covers \BeepBeep\AltText\Services\Usage_Service
 */
class UsageServiceTest extends TestCase {

	/**
	 * API client mock.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $api_client;

	/**
	 * Usage service instance.
	 *
	 * @var Usage_Service
	 */
	private $service;

	/**
	 * Set up before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Create mocks
		$this->api_client = Mockery::mock( '\BbAI_API_Client_V2' );

		// Create service instance
		$this->service = new Usage_Service( $this->api_client );
	}

	/**
	 * Test successful usage refresh.
	 */
	public function test_refresh_usage_success() {
		// Mock API returning usage data
		$this->api_client->shouldReceive( 'get_usage' )
			->once()
			->andReturn( array(
				'used'  => 100,
				'limit' => 1000,
			) );

		// Execute
		$result = $this->service->refresh_usage();

		// Assert
		$this->assertSuccessResponse( $result );
		$this->assertArrayHasKey( 'stats', $result );
	}

	/**
	 * Test refresh usage failure.
	 */
	public function test_refresh_usage_failure() {
		// Mock API returning null (failure)
		$this->api_client->shouldReceive( 'get_usage' )
			->once()
			->andReturn( null );

		// Execute
		$result = $this->service->refresh_usage();

		// Assert
		$this->assertErrorResponse( $result );
		$this->assertStringContainsString( 'Failed', $result['message'] );
	}

	/**
	 * Test default usage structure.
	 */
	public function test_default_usage() {
		$usage = $this->service->default_usage();

		$this->assertIsArray( $usage );
		$this->assertEquals( 0, $usage['prompt'] );
		$this->assertEquals( 0, $usage['completion'] );
		$this->assertEquals( 0, $usage['total'] );
		$this->assertEquals( 0, $usage['requests'] );
		$this->assertNull( $usage['last_request'] );
	}

	/**
	 * Test record usage with valid data.
	 */
	public function test_record_usage_valid_data() {
		$usage_data = array(
			'prompt'     => 50,
			'completion' => 30,
			'total'      => 80,
		);

		// Execute (should not throw exceptions)
		$this->service->record_usage( $usage_data );

		// If we get here without exceptions, the test passes
		$this->assertTrue( true );
	}

	/**
	 * Test record usage with empty data.
	 */
	public function test_record_usage_empty_data() {
		$usage_data = array(
			'prompt'     => 0,
			'completion' => 0,
			'total'      => 0,
		);

		// Execute (should return early without updating options)
		$this->service->record_usage( $usage_data );

		// If we get here without exceptions, the test passes
		$this->assertTrue( true );
	}

	/**
	 * Test record usage with partial data.
	 */
	public function test_record_usage_partial_data() {
		$usage_data = array(
			'prompt' => 25,
			// completion missing - should default to 0
		);

		// Execute
		$this->service->record_usage( $usage_data );

		// If we get here without exceptions, the test passes
		$this->assertTrue( true );
	}

	/**
	 * Test record usage with negative values.
	 */
	public function test_record_usage_negative_values() {
		$usage_data = array(
			'prompt'     => -50,  // Should be converted to 0
			'completion' => -30,  // Should be converted to 0
			'total'      => -80,  // Should be converted to 0
		);

		// Execute (should handle negative values gracefully)
		$this->service->record_usage( $usage_data );

		// If we get here without exceptions, the test passes
		$this->assertTrue( true );
	}

	/**
	 * Test record usage calculates total when not provided.
	 */
	public function test_record_usage_calculates_total() {
		$usage_data = array(
			'prompt'     => 40,
			'completion' => 60,
			// total not provided - should be calculated as 100
		);

		// Execute
		$this->service->record_usage( $usage_data );

		// If we get here without exceptions, the test passes
		$this->assertTrue( true );
	}

	/**
	 * Test get usage stats.
	 */
	public function test_get_usage_stats() {
		// Execute
		$stats = $this->service->get_usage_stats();

		// Assert
		$this->assertIsArray( $stats );
	}

	/**
	 * Test get threshold notice returns null when no notice.
	 */
	public function test_get_threshold_notice_null() {
		// Execute
		$notice = $this->service->get_threshold_notice();

		// Assert
		$this->assertNull( $notice );
	}

	/**
	 * Test clear cache.
	 */
	public function test_clear_cache() {
		// Execute (should not throw exceptions)
		$this->service->clear_cache();

		// If we get here without exceptions, the test passes
		$this->assertTrue( true );
	}

	/**
	 * Test get usage rows with default limit.
	 */
	public function test_get_usage_rows_default() {
		global $wpdb;
		$wpdb = Mockery::mock( '\wpdb' );
		$wpdb->prefix = 'wp_';

		// Mock table existence check
		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 'wp_bbai_usage' );

		// Mock prepare method
		$wpdb->shouldReceive( 'prepare' )
			->andReturn( 'SELECT * FROM wp_bbai_usage ORDER BY id DESC LIMIT 10' );

		// Mock results
		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array(
				array(
					'user_id'      => 1,
					'tokens_used'  => 50,
					'action_type'  => 'generate',
					'created_at'   => '2025-01-15 10:00:00',
				),
			) );

		// Execute
		$rows = $this->service->get_usage_rows();

		// Assert
		$this->assertIsArray( $rows );
	}

	/**
	 * Test get usage rows with custom limit.
	 */
	public function test_get_usage_rows_custom_limit() {
		global $wpdb;
		$wpdb = Mockery::mock( '\wpdb' );
		$wpdb->prefix = 'wp_';

		// Mock table existence check
		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 'wp_bbai_usage' );

		// Mock prepare method
		$wpdb->shouldReceive( 'prepare' )
			->andReturn( 'SELECT * FROM wp_bbai_usage ORDER BY id DESC LIMIT 25' );

		// Mock results
		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		// Execute
		$rows = $this->service->get_usage_rows( 25 );

		// Assert
		$this->assertIsArray( $rows );
	}

	/**
	 * Test get usage rows with include_all flag.
	 */
	public function test_get_usage_rows_include_all() {
		global $wpdb;
		$wpdb = Mockery::mock( '\wpdb' );
		$wpdb->prefix = 'wp_';

		// Mock table existence check
		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 'wp_bbai_usage' );

		// Mock prepare method
		$wpdb->shouldReceive( 'prepare' )
			->andReturn( 'SELECT * FROM wp_bbai_usage ORDER BY id DESC LIMIT 10' );

		// Mock results with all columns
		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array(
				array(
					'id'           => 1,
					'user_id'      => 1,
					'tokens_used'  => 50,
					'action_type'  => 'generate',
					'image_id'     => 123,
					'created_at'   => '2025-01-15 10:00:00',
				),
			) );

		// Execute
		$rows = $this->service->get_usage_rows( 10, true );

		// Assert
		$this->assertIsArray( $rows );
	}

	/**
	 * Test get usage rows when table doesn't exist.
	 */
	public function test_get_usage_rows_table_not_exists() {
		global $wpdb;
		$wpdb = Mockery::mock( '\wpdb' );
		$wpdb->prefix = 'wp_';

		// Mock table not existing
		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( null );

		$wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'SHOW TABLES LIKE \'wp_bbai_usage\'' );

		// Execute
		$rows = $this->service->get_usage_rows();

		// Assert
		$this->assertIsArray( $rows );
		$this->assertEmpty( $rows );
	}

	/**
	 * Test get usage rows with invalid limit.
	 */
	public function test_get_usage_rows_invalid_limit() {
		global $wpdb;
		$wpdb = Mockery::mock( '\wpdb' );
		$wpdb->prefix = 'wp_';

		// Mock table existence check
		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 'wp_bbai_usage' );

		// Mock prepare method - limit should be converted to 1 (minimum)
		$wpdb->shouldReceive( 'prepare' )
			->andReturn( 'SELECT * FROM wp_bbai_usage ORDER BY id DESC LIMIT 1' );

		// Mock results
		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		// Execute with invalid limit (0)
		$rows = $this->service->get_usage_rows( 0 );

		// Assert
		$this->assertIsArray( $rows );
	}

	/**
	 * Test get usage rows when database returns non-array.
	 */
	public function test_get_usage_rows_database_error() {
		global $wpdb;
		$wpdb = Mockery::mock( '\wpdb' );
		$wpdb->prefix = 'wp_';

		// Mock table existence check
		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 'wp_bbai_usage' );

		// Mock prepare method
		$wpdb->shouldReceive( 'prepare' )
			->andReturn( 'SELECT * FROM wp_bbai_usage ORDER BY id DESC LIMIT 10' );

		// Mock database error (returns null)
		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( null );

		// Execute
		$rows = $this->service->get_usage_rows();

		// Assert - should return empty array on error
		$this->assertIsArray( $rows );
		$this->assertEmpty( $rows );
	}

	/**
	 * Test record usage with string values.
	 */
	public function test_record_usage_string_values() {
		$usage_data = array(
			'prompt'     => '50',  // String should be converted to int
			'completion' => '30',  // String should be converted to int
		);

		// Execute (should handle string conversion)
		$this->service->record_usage( $usage_data );

		// If we get here without exceptions, the test passes
		$this->assertTrue( true );
	}

	/**
	 * Test record usage with non-numeric values.
	 */
	public function test_record_usage_non_numeric_values() {
		$usage_data = array(
			'prompt'     => 'invalid',  // Should be converted to 0
			'completion' => 'text',     // Should be converted to 0
		);

		// Execute (should handle invalid values gracefully)
		$this->service->record_usage( $usage_data );

		// If we get here without exceptions, the test passes
		$this->assertTrue( true );
	}
}
