<?php
declare(strict_types=1);

namespace BeepBeep\AltText\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeepBeep\AltText\Core\Event_Bus;

/**
 * Queue Service
 *
 * Handles background job queue management for alt text generation.
 * Provides interface for queue operations, retries, and statistics.
 *
 * @package BeepBeep\AltText\Services
 * @since   5.0.0
 */
class Queue_Service {
	/**
	 * Event bus instance.
	 *
	 * @var Event_Bus
	 */
	private Event_Bus $event_bus;

	/**
	 * Constructor.
	 *
	 * @since 5.0.0
	 *
	 * @param Event_Bus $event_bus Event bus.
	 */
	public function __construct( Event_Bus $event_bus ) {
		$this->event_bus = $event_bus;
	}

	/**
	 * Retry a specific job.
	 *
	 * @since 5.0.0
	 *
	 * @param int $job_id Job ID to retry.
	 * @return array{success: bool, message: string} Retry result.
	 */
	public function retry_job( int $job_id ): array {
		if ( $job_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid job ID.', 'opptiai-alt' ),
			);
		}

		if ( class_exists( '\BbAI_Queue' ) ) {
			\BbAI_Queue::retry_job( $job_id );
			\BbAI_Queue::schedule_processing( 10 );

			// Emit event.
			$this->event_bus->emit(
				'queue_job_retried',
				array( 'job_id' => $job_id )
			);

			return array(
				'success' => true,
				'message' => __( 'Job re-queued.', 'opptiai-alt' ),
			);
		}

		return array(
			'success' => false,
			'message' => __( 'Queue system not available.', 'opptiai-alt' ),
		);
	}

	/**
	 * Retry all failed jobs.
	 *
	 * @since 5.0.0
	 *
	 * @return array{success: bool, message: string} Retry result.
	 */
	public function retry_failed(): array {
		if ( class_exists( '\BbAI_Queue' ) ) {
			\BbAI_Queue::retry_failed();
			\BbAI_Queue::schedule_processing( 10 );

			// Emit event.
			$this->event_bus->emit( 'queue_failed_retried', null );

			return array(
				'success' => true,
				'message' => __( 'Retry scheduled for failed jobs.', 'opptiai-alt' ),
			);
		}

		return array(
			'success' => false,
			'message' => __( 'Queue system not available.', 'opptiai-alt' ),
		);
	}

	/**
	 * Clear all completed jobs.
	 *
	 * @since 5.0.0
	 *
	 * @return array{success: bool, message: string} Clear result.
	 */
	public function clear_completed(): array {
		if ( class_exists( '\BbAI_Queue' ) ) {
			\BbAI_Queue::clear_completed();

			// Emit event.
			$this->event_bus->emit( 'queue_completed_cleared', null );

			return array(
				'success' => true,
				'message' => __( 'Cleared completed jobs.', 'opptiai-alt' ),
			);
		}

		return array(
			'success' => false,
			'message' => __( 'Queue system not available.', 'opptiai-alt' ),
		);
	}

	/**
	 * Get queue statistics.
	 *
	 * @since 5.0.0
	 *
	 * @return array{success: bool, stats?: array, failures?: array} Queue stats.
	 */
	public function get_stats(): array {
		if ( class_exists( '\BbAI_Queue' ) ) {
			$stats    = \BbAI_Queue::get_stats();
			$failures = \BbAI_Queue::get_failures();

			return array(
				'success'  => true,
				'stats'    => $stats,
				'failures' => $failures,
			);
		}

		return array(
			'success' => false,
			'stats'   => array(),
		);
	}

	/**
	 * Get job by attachment ID.
	 *
	 * @since 5.0.0
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array|null Job data or null.
	 */
	public function get_job_by_attachment( int $attachment_id ): ?array {
		if ( class_exists( '\BbAI_Queue' ) ) {
			return \BbAI_Queue::get_job_by_attachment( $attachment_id );
		}

		return null;
	}

	/**
	 * Enqueue attachment for processing.
	 *
	 * @since 5.0.0
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $source        Source of request.
	 * @return bool True if enqueued.
	 */
	public function enqueue( int $attachment_id, string $source = 'manual' ): bool {
		if ( class_exists( '\BbAI_Queue' ) ) {
			$result = \BbAI_Queue::enqueue( $attachment_id, $source );

			if ( $result ) {
				// Emit event.
				$this->event_bus->emit(
					'queue_job_enqueued',
					array(
						'attachment_id' => $attachment_id,
						'source'        => $source,
					)
				);
			}

			return $result;
		}

		return false;
	}

	/**
	 * Schedule queue processing.
	 *
	 * @since 5.0.0
	 *
	 * @param int $delay Delay in seconds.
	 * @return void
	 */
	public function schedule_processing( int $delay = 10 ): void {
		if ( class_exists( '\BbAI_Queue' ) ) {
			\BbAI_Queue::schedule_processing( $delay );
		}
	}

	/**
	 * Get all pending jobs.
	 *
	 * @since 5.0.0
	 *
	 * @param int $limit Maximum number of jobs to return.
	 * @return array Pending jobs.
	 */
	public function get_pending_jobs( int $limit = 50 ): array {
		if ( class_exists( '\BbAI_Queue' ) ) {
			return \BbAI_Queue::get_pending( $limit );
		}

		return array();
	}

	/**
	 * Get all failed jobs.
	 *
	 * @since 5.0.0
	 *
	 * @param int $limit Maximum number of jobs to return.
	 * @return array Failed jobs.
	 */
	public function get_failed_jobs( int $limit = 50 ): array {
		if ( class_exists( '\BbAI_Queue' ) ) {
			return \BbAI_Queue::get_failures( $limit );
		}

		return array();
	}
}
