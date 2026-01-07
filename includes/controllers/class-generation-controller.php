<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

namespace BeepBeep\AltText\Controllers;

use BeepBeep\AltText\Services\Generation_Service;

/**
 * Generation Controller
 *
 * Handles HTTP requests for alt text generation operations.
 * Thin controller layer that delegates to GenerationService.
 *
 * @package BeepBeep\AltText\Controllers
 * @since   5.0.0
 */
class Generation_Controller {
	/**
	 * Generation service.
	 *
	 * @var Generation_Service
	 */
	private Generation_Service $generation_service;

	/**
	 * Constructor.
	 *
	 * @since 5.0.0
	 *
	 * @param Generation_Service $generation_service Generation service.
	 */
	public function __construct( Generation_Service $generation_service ) {
		$this->generation_service = $generation_service;
	}

	/**
	 * Handle regenerate single request.
	 *
	 * @since 5.0.0
	 *
	 * @return array Response data.
	 */
	public function regenerate_single(): array {
		// Check permission.
		if ( ! $this->user_can_manage() ) {
			return array(
				'success' => false,
				'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ),
			);
		}

		$attachment_id_raw = isset( $_POST['attachment_id'] ) ? wp_unslash( $_POST['attachment_id'] ) : '';
		$attachment_id     = absint( $attachment_id_raw );

		return $this->generation_service->regenerate_single( $attachment_id );
	}

	/**
	 * Handle bulk queue request.
	 *
	 * @since 5.0.0
	 *
	 * @return array Response data.
	 */
	public function bulk_queue(): array {
		// Check permission.
		if ( ! $this->user_can_manage() ) {
			return array(
				'success' => false,
				'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ),
			);
		}

		$attachment_ids_raw = isset( $_POST['attachment_ids'] ) ? wp_unslash( $_POST['attachment_ids'] ) : '';
		$attachment_ids     = is_string( $attachment_ids_raw ) ? json_decode( $attachment_ids_raw, true ) : array();

		if ( ! is_array( $attachment_ids ) ) {
			$attachment_ids = array();
		}

		return $this->generation_service->bulk_queue( $attachment_ids );
	}

	/**
	 * Handle inline generate request.
	 *
	 * @since 5.0.0
	 *
	 * @return array Response data.
	 */
	public function inline_generate(): array {
		// Check permission.
		if ( ! $this->user_can_manage() ) {
			return array(
				'success' => false,
				'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ),
			);
		}

		$attachment_id_raw = isset( $_POST['attachment_id'] ) ? wp_unslash( $_POST['attachment_id'] ) : '';
		$attachment_id     = absint( $attachment_id_raw );

		return $this->generation_service->inline_generate( $attachment_id );
	}

	/**
	 * Check if current user can manage plugin.
	 *
	 * @since 5.0.0
	 *
	 * @return bool True if user can manage.
	 */
	private function user_can_manage(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'upload_files' );
	}
}
