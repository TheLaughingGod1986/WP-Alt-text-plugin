<?php
/**
 * Example Service
 *
 * Template for creating business logic services.
 * Services should be framework-agnostic and contain no WordPress-specific code.
 *
 * @package MyPlugin\Services
 * @since   1.0.0
 */

declare(strict_types=1);

namespace MyPlugin\Services;

use MyPlugin\Core\Event_Bus;

/**
 * Example_Service Class
 *
 * This is a template showing how to structure a service class.
 * Replace with your actual business logic.
 *
 * Services should:
 * - Contain business logic only (no HTTP handling)
 * - Be framework-agnostic (testable without WordPress)
 * - Use dependency injection for all dependencies
 * - Emit events for cross-cutting concerns
 * - Return standardized response arrays
 *
 * @since 1.0.0
 */
class Example_Service {

	/**
	 * Event bus instance.
	 *
	 * @since  1.0.0
	 * @var    Event_Bus
	 */
	private Event_Bus $event_bus;

	/**
	 * Constructor.
	 *
	 * Inject all dependencies via constructor.
	 * This enables easy testing and makes dependencies explicit.
	 *
	 * @since 1.0.0
	 *
	 * @param Event_Bus $event_bus Event bus for emitting events.
	 */
	public function __construct( Event_Bus $event_bus ) {
		$this->event_bus = $event_bus;
	}

	/**
	 * Process data.
	 *
	 * Example method showing typical service structure:
	 * 1. Validate input
	 * 2. Execute business logic
	 * 3. Emit events
	 * 4. Return standardized response
	 *
	 * @since 1.0.0
	 *
	 * @param string $input Input data to process.
	 * @return array{success: bool, message?: string, data?: mixed} Standardized response.
	 */
	public function process_data( string $input ): array {
		// 1. Validation
		if ( empty( $input ) ) {
			return array(
				'success' => false,
				'message' => __( 'Input cannot be empty', 'my-plugin' ),
			);
		}

		// 2. Business Logic
		try {
			$result = $this->do_processing( $input );

			// 3. Emit Event
			$this->event_bus->emit(
				'example_service.processed',
				array(
					'input'  => $input,
					'result' => $result,
				)
			);

			// 4. Return Success Response
			return array(
				'success' => true,
				'data'    => $result,
			);

		} catch ( \Exception $e ) {
			// Log error
			if ( function_exists( 'error_log' ) ) {
				error_log( 'Example Service Error: ' . $e->getMessage() );
			}

			// Return error response
			return array(
				'success' => false,
				'message' => __( 'Processing failed', 'my-plugin' ),
			);
		}
	}

	/**
	 * Get data by ID.
	 *
	 * Example retrieval method.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Item ID.
	 * @return array{success: bool, message?: string, data?: mixed} Response with data.
	 */
	public function get_data( int $id ): array {
		// Validate ID
		if ( $id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid ID', 'my-plugin' ),
			);
		}

		// Retrieve data (example - replace with actual logic)
		$data = $this->fetch_from_source( $id );

		if ( null === $data ) {
			return array(
				'success' => false,
				'message' => __( 'Data not found', 'my-plugin' ),
			);
		}

		return array(
			'success' => true,
			'data'    => $data,
		);
	}

	/**
	 * Create new data.
	 *
	 * Example creation method.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Data to create.
	 * @return array{success: bool, message?: string, id?: int} Response with created ID.
	 */
	public function create_data( array $data ): array {
		// Validate required fields
		$required = array( 'name', 'value' );
		foreach ( $required as $field ) {
			if ( ! isset( $data[ $field ] ) || empty( $data[ $field ] ) ) {
				return array(
					'success' => false,
					'message' => sprintf(
						/* translators: %s: Field name */
						__( 'Field "%s" is required', 'my-plugin' ),
						$field
					),
				);
			}
		}

		// Create data (example - replace with actual logic)
		$id = $this->save_to_source( $data );

		// Emit event
		$this->event_bus->emit(
			'example_service.created',
			array(
				'id'   => $id,
				'data' => $data,
			)
		);

		return array(
			'success' => true,
			'id'      => $id,
			'message' => __( 'Data created successfully', 'my-plugin' ),
		);
	}

	/**
	 * Perform actual processing.
	 *
	 * Private method containing the core logic.
	 * Separated for easier testing and organization.
	 *
	 * @since 1.0.0
	 *
	 * @param string $input Input to process.
	 * @return mixed Processing result.
	 */
	private function do_processing( string $input ) {
		// Replace with your actual logic
		return strtoupper( $input );
	}

	/**
	 * Fetch data from source.
	 *
	 * Example data retrieval (replace with actual implementation).
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Data ID.
	 * @return mixed|null Data or null if not found.
	 */
	private function fetch_from_source( int $id ) {
		// Replace with actual data retrieval logic
		// Example: database query, API call, etc.
		return array(
			'id'   => $id,
			'name' => 'Example Item',
		);
	}

	/**
	 * Save data to source.
	 *
	 * Example data persistence (replace with actual implementation).
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Data to save.
	 * @return int Created ID.
	 */
	private function save_to_source( array $data ): int {
		// Replace with actual save logic
		// Example: database insert, API call, etc.
		return 1; // Return created ID
	}
}
