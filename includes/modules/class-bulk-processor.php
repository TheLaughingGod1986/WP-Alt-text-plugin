<?php
/**
 * Bulk Processor Module
 *
 * Handles bulk processing of images for alt text generation.
 *
 * @package Optti\Modules
 */

namespace Optti\Modules;

use Optti\Framework\Interfaces\ModuleInterface;
use Optti\Framework\LicenseManager;
use Optti\Framework\Logger;
use BeepBeepAI\AltTextGenerator\Queue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Bulk_Processor
 *
 * Module for bulk processing images.
 */
class Bulk_Processor implements ModuleInterface {

	/**
	 * Module ID.
	 */
	const MODULE_ID = 'bulk_processor';

	/**
	 * Get module identifier.
	 *
	 * @return string Module ID.
	 */
	public function get_id() {
		return self::MODULE_ID;
	}

	/**
	 * Get module name.
	 *
	 * @return string Module name.
	 */
	public function get_name() {
		return 'Bulk Processor';
	}

	/**
	 * Initialize the module.
	 *
	 * @return void
	 */
	public function init() {
		// Register bulk actions.
		add_filter( 'bulk_actions-upload', [ $this, 'register_bulk_actions' ] );
		add_filter( 'handle_bulk_actions-upload', [ $this, 'handle_bulk_actions' ], 10, 3 );

		// Register AJAX handlers.
		add_action( 'wp_ajax_optti_bulk_queue', [ $this, 'ajax_bulk_queue' ] );
		add_action( 'wp_ajax_optti_bulk_status', [ $this, 'ajax_bulk_status' ] );

		// Register cron hook.
		add_action( Queue::CRON_HOOK, [ $this, 'process_queue' ] );
	}

	/**
	 * Check if module is active.
	 *
	 * @return bool True if active.
	 */
	public function is_active() {
		return true; // Always active for this plugin.
	}

	/**
	 * Register bulk actions.
	 *
	 * @param array $actions Existing bulk actions.
	 * @return array Modified bulk actions.
	 */
	public function register_bulk_actions( $actions ) {
		$actions['optti_generate_alt'] = __( 'Generate Alt Text', 'beepbeep-ai-alt-text-generator' );
		$actions['optti_regenerate_alt'] = __( 'Regenerate Alt Text', 'beepbeep-ai-alt-text-generator' );
		return $actions;
	}

	/**
	 * Handle bulk actions.
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $action Action name.
	 * @param array  $post_ids Post IDs.
	 * @return string Redirect URL.
	 */
	public function handle_bulk_actions( $redirect_to, $action, $post_ids ) {
		if ( ! in_array( $action, [ 'optti_generate_alt', 'optti_regenerate_alt' ], true ) ) {
			return $redirect_to;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return $redirect_to;
		}

		$regenerate = ( 'optti_regenerate_alt' === $action );
		$queued = $this->queue_images( $post_ids, $regenerate ? 'bulk-regenerate' : 'bulk-generate' );

		$redirect_to = add_query_arg( 'optti_bulk_queued', $queued, $redirect_to );
		return $redirect_to;
	}

	/**
	 * Queue images for processing.
	 *
	 * @param array  $attachment_ids Attachment IDs.
	 * @param string $source Source identifier.
	 * @return int Number of images queued.
	 */
	public function queue_images( $attachment_ids, $source = 'bulk' ) {
		if ( empty( $attachment_ids ) || ! is_array( $attachment_ids ) ) {
			return 0;
		}

		// Check quota.
		$license = LicenseManager::instance();
		$count = count( $attachment_ids );
		if ( ! $license->can_consume( $count ) ) {
			Logger::log( 'warning', 'Bulk queue blocked - quota exhausted', [
				'count' => $count,
			], 'bulk_processor' );
			return 0;
		}

		// Clear existing entries for regeneration.
		if ( 'bulk-regenerate' === $source ) {
			Queue::clear_for_attachments( $attachment_ids );
		}

		$queued = 0;
		foreach ( $attachment_ids as $attachment_id ) {
			if ( Queue::enqueue( $attachment_id, $source ) ) {
				$queued++;
			}
		}

		Logger::log( 'info', 'Bulk queue operation', [
			'queued'   => $queued,
			'requested' => $count,
			'source'   => $source,
		], 'bulk_processor' );

		return $queued;
	}

	/**
	 * Process queue.
	 *
	 * @return void
	 */
	public function process_queue() {
		$batch_size = apply_filters( 'optti_queue_batch_size', 5 );
		// Get plugin instance from global.
		global $optti_beepbeep_plugin;
		$alt_generator = $optti_beepbeep_plugin ? $optti_beepbeep_plugin->get_module( 'alt_generator' ) : null;

		if ( ! $alt_generator ) {
			return;
		}

		$claimed = Queue::claim_batch( $batch_size );

		if ( empty( $claimed ) ) {
			return;
		}

		foreach ( $claimed as $job ) {
			$attachment_id = intval( $job->attachment_id );
			$source = $job->source ?? 'auto';
			$job_id = intval( $job->id );

			// Generate alt text.
			$result = $alt_generator->generate( $attachment_id, $source, false );

			if ( is_wp_error( $result ) ) {
				Queue::mark_failed( $job_id, $result->get_error_message() );
			} else {
				Queue::mark_complete( $job_id );
			}
		}

		// Schedule next batch if more pending.
		$stats = Queue::get_stats();
		if ( isset( $stats['pending'] ) && $stats['pending'] > 0 ) {
			Queue::schedule_processing();
		}
	}

	/**
	 * AJAX handler: Bulk queue images.
	 *
	 * @return void
	 */
	public function ajax_bulk_queue() {
		check_ajax_referer( 'optti_bulk_queue', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ] );
		}

		$ids = isset( $_POST['ids'] ) ? array_map( 'intval', (array) $_POST['ids'] ) : [];
		$regenerate = isset( $_POST['regenerate'] ) && (bool) $_POST['regenerate'];

		if ( empty( $ids ) ) {
			wp_send_json_error( [ 'message' => __( 'No images selected', 'beepbeep-ai-alt-text-generator' ) ] );
		}

		$source = $regenerate ? 'bulk-regenerate' : 'bulk-generate';
		$queued = $this->queue_images( $ids, $source );

		wp_send_json_success( [
			'queued' => $queued,
			'total'  => count( $ids ),
			'message' => sprintf(
				_n( '%d image queued for processing', '%d images queued for processing', $queued, 'beepbeep-ai-alt-text-generator' ),
				$queued
			),
		] );
	}

	/**
	 * AJAX handler: Get bulk processing status.
	 *
	 * @return void
	 */
	public function ajax_bulk_status() {
		check_ajax_referer( 'optti_bulk_status', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ] );
		}

		$stats = Queue::get_stats();

		wp_send_json_success( $stats );
	}
}

