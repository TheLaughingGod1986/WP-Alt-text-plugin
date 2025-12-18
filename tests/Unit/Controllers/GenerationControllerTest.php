<?php
/**
 * Generation Controller Tests
 *
 * @package BeepBeepAI\AltText\Tests\Unit\Controllers
 */

namespace BeepBeepAI\AltText\Tests\Unit\Controllers;

use BeepBeepAI\AltText\Tests\TestCase;
use BeepBeep\AltText\Controllers\Generation_Controller;
use BeepBeep\AltText\Services\Generation_Service;
use Mockery;

/**
 * Test Generation Controller
 *
 * @covers \BeepBeep\AltText\Controllers\Generation_Controller
 */
class GenerationControllerTest extends TestCase {

	/**
	 * Generation service mock.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $generation_service;

	/**
	 * Controller instance.
	 *
	 * @var Generation_Controller
	 */
	private $controller;

	/**
	 * Set up before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Mock generation service
		$this->generation_service = Mockery::mock( Generation_Service::class );

		// Create controller
		$this->controller = new Generation_Controller( $this->generation_service );

		// Reset $_POST
		$_POST = array();
	}

	/**
	 * Test regenerate single attachment.
	 */
	public function test_regenerate_single() {
		$attachment_id = 123;
		$_POST['attachment_id'] = $attachment_id;

		$this->generation_service->shouldReceive( 'regenerate_single' )
			->once()
			->with( $attachment_id )
			->andReturn( array(
				'success'  => true,
				'alt_text' => 'A beautiful sunset',
			) );

		$result = $this->controller->regenerate_single();

		$this->assertSuccessResponse( $result );
		$this->assertEquals( 'A beautiful sunset', $result['alt_text'] );
	}

	/**
	 * Test regenerate single converts string to int.
	 */
	public function test_regenerate_single_converts_string_to_int() {
		$_POST['attachment_id'] = '456';

		$this->generation_service->shouldReceive( 'regenerate_single' )
			->once()
			->with( 456 )
			->andReturn( array( 'success' => true ) );

		$result = $this->controller->regenerate_single();

		$this->assertSuccessResponse( $result );
	}

	/**
	 * Test regenerate single handles invalid ID.
	 */
	public function test_regenerate_single_invalid_id() {
		$_POST['attachment_id'] = 'invalid';

		$this->generation_service->shouldReceive( 'regenerate_single' )
			->once()
			->with( 0 )
			->andReturn( array(
				'success' => false,
				'message' => 'Invalid attachment ID',
			) );

		$result = $this->controller->regenerate_single();

		$this->assertErrorResponse( $result );
	}

	/**
	 * Test regenerate single with missing ID.
	 */
	public function test_regenerate_single_missing_id() {
		// No $_POST data

		$this->generation_service->shouldReceive( 'regenerate_single' )
			->once()
			->with( 0 )
			->andReturn( array( 'success' => false ) );

		$result = $this->controller->regenerate_single();

		$this->assertErrorResponse( $result );
	}

	/**
	 * Test bulk queue.
	 */
	public function test_bulk_queue() {
		$attachment_ids = array( 1, 2, 3, 4, 5 );
		$_POST['attachment_ids'] = json_encode( $attachment_ids );

		$this->generation_service->shouldReceive( 'bulk_queue' )
			->once()
			->with( $attachment_ids )
			->andReturn( array(
				'success' => true,
				'queued'  => 5,
			) );

		$result = $this->controller->bulk_queue();

		$this->assertSuccessResponse( $result );
		$this->assertEquals( 5, $result['queued'] );
	}

	/**
	 * Test bulk queue with invalid JSON.
	 */
	public function test_bulk_queue_invalid_json() {
		$_POST['attachment_ids'] = 'not valid json';

		$this->generation_service->shouldReceive( 'bulk_queue' )
			->once()
			->with( array() )
			->andReturn( array(
				'success' => false,
				'message' => 'No attachments provided',
			) );

		$result = $this->controller->bulk_queue();

		$this->assertErrorResponse( $result );
	}

	/**
	 * Test bulk queue with empty array.
	 */
	public function test_bulk_queue_empty_array() {
		$_POST['attachment_ids'] = json_encode( array() );

		$this->generation_service->shouldReceive( 'bulk_queue' )
			->once()
			->with( array() )
			->andReturn( array(
				'success' => false,
				'message' => 'No attachments to queue',
			) );

		$result = $this->controller->bulk_queue();

		$this->assertErrorResponse( $result );
	}

	/**
	 * Test bulk queue handles non-string input.
	 */
	public function test_bulk_queue_non_string_input() {
		$_POST['attachment_ids'] = array( 1, 2, 3 ); // Array instead of JSON string

		$this->generation_service->shouldReceive( 'bulk_queue' )
			->once()
			->with( array() )
			->andReturn( array( 'success' => false ) );

		$result = $this->controller->bulk_queue();

		$this->assertErrorResponse( $result );
	}

	/**
	 * Test inline generate.
	 */
	public function test_inline_generate() {
		$attachment_id = 789;
		$_POST['attachment_id'] = $attachment_id;

		$this->generation_service->shouldReceive( 'inline_generate' )
			->once()
			->with( $attachment_id )
			->andReturn( array(
				'success'  => true,
				'alt_text' => 'Generated inline',
			) );

		$result = $this->controller->inline_generate();

		$this->assertSuccessResponse( $result );
	}

	/**
	 * Test inline generate converts string to int.
	 */
	public function test_inline_generate_converts_string() {
		$_POST['attachment_id'] = '999';

		$this->generation_service->shouldReceive( 'inline_generate' )
			->once()
			->with( 999 )
			->andReturn( array( 'success' => true ) );

		$result = $this->controller->inline_generate();

		$this->assertSuccessResponse( $result );
	}

	/**
	 * Test inline generate handles negative ID.
	 */
	public function test_inline_generate_negative_id() {
		$_POST['attachment_id'] = '-5';

		// absint converts negative to 0
		$this->generation_service->shouldReceive( 'inline_generate' )
			->once()
			->with( 0 )
			->andReturn( array( 'success' => false ) );

		$result = $this->controller->inline_generate();

		$this->assertErrorResponse( $result );
	}

	/**
	 * Test all methods delegate to service.
	 */
	public function test_methods_delegate_correctly() {
		// Regenerate single
		$_POST['attachment_id'] = '100';
		$this->generation_service->shouldReceive( 'regenerate_single' )
			->once()
			->with( 100 )
			->andReturn( array( 'success' => true ) );
		$this->controller->regenerate_single();

		// Bulk queue
		$_POST['attachment_ids'] = json_encode( array( 1, 2 ) );
		$this->generation_service->shouldReceive( 'bulk_queue' )
			->once()
			->with( array( 1, 2 ) )
			->andReturn( array( 'success' => true ) );
		$this->controller->bulk_queue();

		// Inline generate
		$_POST['attachment_id'] = '200';
		$this->generation_service->shouldReceive( 'inline_generate' )
			->once()
			->with( 200 )
			->andReturn( array( 'success' => true ) );
		$this->controller->inline_generate();

		// All expectations verified in tearDown
		$this->assertTrue( true );
	}

	/**
	 * Test controller returns service responses unchanged.
	 */
	public function test_returns_service_responses() {
		$_POST['attachment_id'] = '123';

		$expected_response = array(
			'success'       => true,
			'alt_text'      => 'Test alt text',
			'attachment_id' => 123,
			'data'          => array( 'extra' => 'info' ),
		);

		$this->generation_service->shouldReceive( 'regenerate_single' )
			->once()
			->andReturn( $expected_response );

		$result = $this->controller->regenerate_single();

		$this->assertEquals( $expected_response, $result );
	}
}
