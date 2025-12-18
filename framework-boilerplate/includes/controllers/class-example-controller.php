<?php
/**
 * Example Controller
 *
 * Template for creating HTTP controllers.
 * Controllers handle requests and delegate to services.
 *
 * @package MyPlugin\Controllers
 * @since   1.0.0
 */

declare(strict_types=1);

namespace MyPlugin\Controllers;

use MyPlugin\Services\Example_Service;

/**
 * Example_Controller Class
 *
 * This is a template showing how to structure a controller class.
 * Replace with your actual HTTP handling logic.
 *
 * Controllers should:
 * - Be thin (minimal logic)
 * - Handle HTTP layer only (input/output)
 * - Sanitize and validate input
 * - Check permissions
 * - Delegate to services for business logic
 * - Return standardized arrays (converted to JSON by router)
 *
 * @since 1.0.0
 */
class Example_Controller {

	/**
	 * Example service instance.
	 *
	 * @since  1.0.0
	 * @var    Example_Service
	 */
	private Example_Service $example_service;

	/**
	 * Constructor.
	 *
	 * Inject service dependencies via constructor.
	 * The DI container will automatically provide these.
	 *
	 * @since 1.0.0
	 *
	 * @param Example_Service $example_service Example service instance.
	 */
	public function __construct( Example_Service $example_service ) {
		$this->example_service = $example_service;
	}

	/**
	 * Handle action request.
	 *
	 * Example AJAX handler method.
	 * Registered in bootstrap.php via router.
	 *
	 * @since 1.0.0
	 *
	 * @return array Response data (automatically converted to JSON).
	 */
	public function handle_action(): array {
		// 1. Permission Check
		if ( ! current_user_can( 'edit_posts' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Permission denied', 'my-plugin' ),
			);
		}

		// 2. Sanitize Input
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$input_raw = isset( $_POST['input'] ) ? wp_unslash( $_POST['input'] ) : '';
		$input     = is_string( $input_raw ) ? sanitize_text_field( $input_raw ) : '';

		// 3. Validate Input
		if ( empty( $input ) ) {
			return array(
				'success' => false,
				'message' => __( 'Input is required', 'my-plugin' ),
			);
		}

		// 4. Delegate to Service
		return $this->example_service->process_data( $input );
	}

	/**
	 * Get items.
	 *
	 * Example REST API handler for retrieving items.
	 * Registered in bootstrap.php via router.
	 *
	 * GET /wp-json/myplugin/v1/items
	 *
	 * @since 1.0.0
	 *
	 * @return array Response data.
	 */
	public function get_items(): array {
		// Permission check
		if ( ! current_user_can( 'read' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Unauthorized', 'my-plugin' ),
			);
		}

		// Example: Return mock data
		// Replace with actual service call
		return array(
			'success' => true,
			'data'    => array(
				array(
					'id'   => 1,
					'name' => 'Item 1',
				),
				array(
					'id'   => 2,
					'name' => 'Item 2',
				),
			),
		);
	}

	/**
	 * Get single item.
	 *
	 * Example REST API handler with URL parameter.
	 *
	 * GET /wp-json/myplugin/v1/items/123
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return array Response data.
	 */
	public function get_item( \WP_REST_Request $request ): array {
		// Permission check
		if ( ! current_user_can( 'read' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Unauthorized', 'my-plugin' ),
			);
		}

		// Get and validate ID from URL
		$id = (int) $request->get_param( 'id' );
		if ( $id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid ID', 'my-plugin' ),
			);
		}

		// Delegate to service
		return $this->example_service->get_data( $id );
	}

	/**
	 * Create item.
	 *
	 * Example REST API handler for creating items.
	 *
	 * POST /wp-json/myplugin/v1/items
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return array Response data.
	 */
	public function create_item( \WP_REST_Request $request ): array {
		// Permission check
		if ( ! current_user_can( 'edit_posts' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Unauthorized', 'my-plugin' ),
			);
		}

		// Get and sanitize request body
		$data = $request->get_json_params();

		if ( ! is_array( $data ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid request data', 'my-plugin' ),
			);
		}

		// Sanitize data
		$sanitized_data = array(
			'name'  => isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '',
			'value' => isset( $data['value'] ) ? sanitize_text_field( $data['value'] ) : '',
		);

		// Delegate to service
		return $this->example_service->create_data( $sanitized_data );
	}

	/**
	 * Admin-only action.
	 *
	 * Example of a controller method restricted to administrators.
	 *
	 * @since 1.0.0
	 *
	 * @return array Response data.
	 */
	public function admin_action(): array {
		// Strict permission check for admins only
		if ( ! current_user_can( 'manage_options' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Administrator access required', 'my-plugin' ),
			);
		}

		// Process admin action
		// ...

		return array(
			'success' => true,
			'message' => __( 'Admin action completed', 'my-plugin' ),
		);
	}

	/**
	 * Public action (no auth required).
	 *
	 * Example of a public endpoint that doesn't require authentication.
	 * Use sparingly and ensure proper validation.
	 *
	 * @since 1.0.0
	 *
	 * @return array Response data.
	 */
	public function public_action(): array {
		// No permission check - this is public

		// Still sanitize input
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$data_raw = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : '';
		$data     = is_string( $data_raw ) ? sanitize_text_field( $data_raw ) : '';

		// Process public action
		// ...

		return array(
			'success' => true,
			'message' => __( 'Public action completed', 'my-plugin' ),
		);
	}
}
