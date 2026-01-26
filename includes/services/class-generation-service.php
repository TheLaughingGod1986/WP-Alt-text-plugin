<?php
declare(strict_types=1);

namespace BeepBeep\AltText\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeepBeep\AltText\Core\Event_Bus;

/**
 * Generation Service
 *
 * Handles alt text generation for media attachments.
 * Provides clean interface for single, inline, and batch generation.
 *
 * @package BeepBeep\AltText\Services
 * @since   5.0.0
 */
class Generation_Service {
	/**
	 * API client instance.
	 *
	 * @var \BbAI_API_Client_V2
	 */
	private \BbAI_API_Client_V2 $api_client;

	/**
	 * Usage service instance.
	 *
	 * @var Usage_Service
	 */
	private Usage_Service $usage_service;

	/**
	 * Event bus instance.
	 *
	 * @var Event_Bus
	 */
	private Event_Bus $event_bus;

	/**
	 * Core instance (temporary - will be fully extracted in Phase 3).
	 *
	 * @var \BbAI_Core
	 */
	private \BbAI_Core $core;

	/**
	 * Constructor.
	 *
	 * @since 5.0.0
	 *
	 * @param \BbAI_API_Client_V2 $api_client     API client.
	 * @param Usage_Service       $usage_service  Usage service.
	 * @param Event_Bus           $event_bus      Event bus.
	 * @param \BbAI_Core          $core           Core instance (temporary).
	 */
	public function __construct(
		\BbAI_API_Client_V2 $api_client,
		Usage_Service $usage_service,
		Event_Bus $event_bus,
		\BbAI_Core $core
	) {
		$this->api_client    = $api_client;
		$this->usage_service = $usage_service;
		$this->event_bus     = $event_bus;
		$this->core          = $core;
	}

	/**
	 * Regenerate alt text for single attachment.
	 *
	 * @since 5.0.0
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array{success: bool, message: string, alt_text?: string, code?: string, data?: array, usage?: array} Generation result.
	 */
	public function regenerate_single( int $attachment_id ): array {
		// Validate attachment.
		if ( ! $attachment_id ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid attachment ID', 'beepbeep-ai-alt-text-generator' ),
			);
		}

		$has_license = $this->api_client->has_active_license();

		// Check quota limits.
		if ( ! $has_license && ( ! defined( 'WP_LOCAL_DEV' ) || ! WP_LOCAL_DEV ) ) {
			if ( $this->api_client->has_reached_limit() ) {
				$usage = $this->api_client->get_usage();
				if ( is_wp_error( $usage ) ) {
					$usage = class_exists( '\BbAI_Usage_Tracker' )
						? \BbAI_Usage_Tracker::get_cached_usage( false )
						: null;
				}

				return array(
					'success' => false,
					'message' => __( 'Monthly limit reached', 'beepbeep-ai-alt-text-generator' ),
					'code'    => 'limit_reached',
					'usage'   => is_array( $usage ) ? $usage : null,
				);
			}
		}

		// Generate alt text (delegate to core for now).
		$result = $this->core->generate_and_save( $attachment_id, 'ajax', 1, array(), true );

		if ( is_wp_error( $result ) ) {
			$error_code    = $result->get_error_code();
			$error_message = $this->get_user_friendly_error_message( $error_code, $result->get_error_message() );

			return array(
				'success' => false,
				'message' => $error_message,
				'code'    => $error_code,
				'data'    => $result->get_error_data(),
			);
		}

		// Clear cache and emit event.
		$this->usage_service->clear_cache();
		$this->event_bus->emit(
			'alt_text_generated',
			array(
				'attachment_id' => $attachment_id,
				'alt_text'      => $result,
				'source'        => 'ajax',
			)
		);

		return array(
			'success'       => true,
			'message'       => __( 'Alt text generated successfully.', 'beepbeep-ai-alt-text-generator' ),
			'alt_text'      => $result,
			'attachment_id' => $attachment_id,
			'data'          => array(
				'alt_text' => $result,
			),
		);
	}

	/**
	 * Queue attachments for bulk generation.
	 *
	 * @since 5.0.0
	 *
	 * @param array $attachment_ids Array of attachment IDs.
	 * @return array{success: bool, message: string, queued?: int, already_queued?: int, invalid?: int} Queue result.
	 */
	public function bulk_queue( array $attachment_ids ): array {
		if ( empty( $attachment_ids ) ) {
			return array(
				'success' => false,
				'message' => __( 'No attachment IDs provided', 'beepbeep-ai-alt-text-generator' ),
			);
		}

		$has_license = $this->api_client->has_active_license();

		// Check quota for non-license users.
		if ( ! $has_license && ( ! defined( 'WP_LOCAL_DEV' ) || ! WP_LOCAL_DEV ) ) {
			$usage = $this->api_client->get_usage();

			// If usage check fails due to authentication, warn but allow queueing.
			if ( is_wp_error( $usage ) && 'not_authenticated' === $usage->get_error_code() ) {
				// Allow queue but backend will handle auth.
			}
		}

		$queued         = 0;
		$already_queued = 0;
		$invalid        = 0;

		foreach ( $attachment_ids as $id ) {
			$attachment_id = absint( $id );

			if ( ! $attachment_id ) {
				$invalid++;
				continue;
			}

			// Check if already queued.
			if ( class_exists( '\BbAI_Queue' ) ) {
				$existing_job = \BbAI_Queue::get_job_by_attachment( $attachment_id );
				if ( $existing_job && 'pending' === $existing_job['status'] ) {
					$already_queued++;
					continue;
				}

				// Queue the job.
				$enqueued = \BbAI_Queue::enqueue( $attachment_id, 'bulk' );
				if ( $enqueued ) {
					$queued++;
				} else {
					$invalid++;
				}
			}
		}

		// Emit event.
		$this->event_bus->emit(
			'bulk_queued',
			array(
				'queued'         => $queued,
				'already_queued' => $already_queued,
				'invalid'        => $invalid,
			)
		);

		return array(
			'success'        => true,
			'message'        => sprintf(
				/* translators: %d: number of images queued */
				_n( '%d image queued for processing', '%d images queued for processing', $queued, 'beepbeep-ai-alt-text-generator' ),
				$queued
			),
			'queued'         => $queued,
			'already_queued' => $already_queued,
			'invalid'        => $invalid,
		);
	}

	/**
	 * Generate alt text inline (synchronous).
	 *
	 * @since 5.0.0
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array{success: bool, message: string, alt?: string, code?: string} Generation result.
	 */
	public function inline_generate( int $attachment_id ): array {
		if ( ! $attachment_id ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid attachment ID', 'beepbeep-ai-alt-text-generator' ),
			);
		}

		// Generate inline (delegate to core).
		$result = $this->core->generate_and_save( $attachment_id, 'inline', 0, array(), false );

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
				'code'    => $result->get_error_code(),
			);
		}

		// Emit event.
		$this->event_bus->emit(
			'alt_text_generated',
			array(
				'attachment_id' => $attachment_id,
				'alt_text'      => $result,
				'source'        => 'inline',
			)
		);

		return array(
			'success' => true,
			'message' => __( 'Alt text generated', 'beepbeep-ai-alt-text-generator' ),
			'alt'     => $result,
		);
	}

	/**
	 * Get user-friendly error message.
	 *
	 * @since 5.0.0
	 *
	 * @param string $error_code    Error code.
	 * @param string $error_message Original error message.
	 * @return string User-friendly message.
	 */
	private function get_user_friendly_error_message( string $error_code, string $error_message ): string {
		$messages = array(
			'missing_alt_text'      => __( 'The API returned a response but no alt text was generated. This may be a temporary issue. Please try again.', 'beepbeep-ai-alt-text-generator' ),
			'api_response_invalid'  => __( 'The API response was invalid. Please try again in a moment.', 'beepbeep-ai-alt-text-generator' ),
			'quota_check_mismatch'  => __( 'Credits appear available but the backend reported a limit. Please try again in a moment.', 'beepbeep-ai-alt-text-generator' ),
			'limit_reached'         => __( 'Monthly quota exhausted. Please upgrade to continue generating alt text.', 'beepbeep-ai-alt-text-generator' ),
			'quota_exhausted'       => __( 'Monthly quota exhausted. Please upgrade to continue generating alt text.', 'beepbeep-ai-alt-text-generator' ),
			'api_timeout'           => __( 'The request timed out. Please try again.', 'beepbeep-ai-alt-text-generator' ),
			'api_unreachable'       => __( 'Unable to reach the server. Please check your internet connection and try again.', 'beepbeep-ai-alt-text-generator' ),
		);

		return $messages[ $error_code ] ?? $error_message;
	}
}
