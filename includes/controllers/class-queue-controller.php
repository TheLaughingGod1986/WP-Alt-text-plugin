<?php
declare(strict_types=1);

namespace BeepBeep\AltText\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeepBeep\AltText\Services\Queue_Service;

/**
 * Queue Controller
 *
 * Handles HTTP requests for queue operations.
 * Thin controller layer that delegates to QueueService.
 *
 * @package BeepBeep\AltText\Controllers
 * @since   5.0.0
 */
class Queue_Controller {
	/**
	 * Queue service.
	 *
	 * @var Queue_Service
	 */
	private Queue_Service $queue_service;

	/**
	 * Constructor.
	 *
	 * @since 5.0.0
	 *
	 * @param Queue_Service $queue_service Queue service.
	 */
	public function __construct( Queue_Service $queue_service ) {
		$this->queue_service = $queue_service;
	}

	/**
	 * Handle retry job request.
	 *
	 * @since 5.0.0
	 *
	 * @return array Response data.
	 */
	public function retry_job(): array {
		$action = 'bbai_queue_retry_job';
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

		$job_id_raw = isset( $_POST['job_id'] ) ? wp_unslash( $_POST['job_id'] ) : '';
		$job_id     = absint( $job_id_raw );

		return $this->queue_service->retry_job( $job_id );
	}

	/**
	 * Handle retry failed jobs request.
	 *
	 * @since 5.0.0
	 *
	 * @return array Response data.
	 */
	public function retry_failed(): array {
		$action = 'bbai_queue_retry_failed';
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

		return $this->queue_service->retry_failed();
	}

	/**
	 * Handle clear completed jobs request.
	 *
	 * @since 5.0.0
	 *
	 * @return array Response data.
	 */
	public function clear_completed(): array {
		$action = 'bbai_queue_clear_completed';
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

		return $this->queue_service->clear_completed();
	}

	/**
	 * Handle get queue stats request.
	 *
	 * @since 5.0.0
	 *
	 * @return array Response data.
	 */
	public function get_stats(): array {
		$action = 'bbai_queue_stats';
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

		return $this->queue_service->get_stats();
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
