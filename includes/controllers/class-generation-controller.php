<?php
declare(strict_types=1);

namespace BeepBeep\AltText\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
		if ( $attachment_id <= 0 || ! current_user_can( 'edit_post', $attachment_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ),
			);
		}

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
		$attachment_ids = array_filter( array_map( 'absint', $attachment_ids ) );
		$attachment_ids = array_values(
			array_filter(
				$attachment_ids,
				static function ( int $attachment_id ): bool {
					return $attachment_id > 0 && current_user_can( 'edit_post', $attachment_id );
				}
			)
		);

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
		$nonce_raw = isset( $_POST['nonce'] ) ? wp_unslash( $_POST['nonce'] ) : '';
		$nonce     = is_string( $nonce_raw ) ? sanitize_text_field( $nonce_raw ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'bbai_inline_generate' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ),
			);
		}

		// Check permission.
		if ( ! $this->user_can_manage() ) {
			return array(
				'success' => false,
				'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ),
			);
		}

		$attachment_id_raw = isset( $_POST['attachment_id'] ) ? wp_unslash( $_POST['attachment_id'] ) : '';
		$attachment_id     = absint( $attachment_id_raw );
		if ( $attachment_id <= 0 || ! current_user_can( 'edit_post', $attachment_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ),
			);
		}

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
