<?php
declare(strict_types=1);

namespace BeepBeepAI\AltText\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeepBeepAI\AltText\Services\Generation_Service;

/**
 * Generation Controller
 *
 * Handles HTTP requests for alt text generation operations.
 * Thin controller layer that delegates to GenerationService.
 *
 * @package BeepBeepAI\AltText\Controllers
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
		$action = 'bbai_regenerate_single';
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
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

		$attachment_id     = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
		if ( $attachment_id <= 0 || ! current_user_can( 'edit_post', $attachment_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ),
			);
		}

		$request_key = isset( $_POST['request_key'] ) ? sanitize_text_field( wp_unslash( $_POST['request_key'] ) ) : '';
		$this->maybe_log_regenerate_debug(
			'V5 regenerate request accepted',
			array(
				'request_key'    => $request_key,
				'attachment_id'  => $attachment_id,
				'attachment_url' => wp_get_attachment_url( $attachment_id ),
			)
		);

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
		$action = 'bbai_bulk_queue';
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
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

		$attachment_ids_input = isset( $_POST['attachment_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['attachment_ids'] ) ) : '';
		$attachment_ids     = $attachment_ids_input ? json_decode( $attachment_ids_input, true ) : array();

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
		$action = 'bbai_inline_generate';
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
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

		$attachment_id     = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
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
		return current_user_can( 'manage_options' );
	}

	/**
	 * Log regenerate flow debug details when enabled.
	 *
	 * @since 5.0.0
	 *
	 * @param string $message Log message.
	 * @param array  $context Optional context payload.
	 * @return void
	 */
	private function maybe_log_regenerate_debug( string $message, array $context = array() ): void {
		if ( ! defined( 'BBAI_DEBUG_REGENERATE_FLOW' ) || ! BBAI_DEBUG_REGENERATE_FLOW ) {
			return;
		}

		if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
			\BeepBeepAI\AltTextGenerator\Debug_Log::log( 'info', $message, $context, 'generation' );
		}
	}
}
