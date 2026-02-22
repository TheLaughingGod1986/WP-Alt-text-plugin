<?php
/**
 * Core Ajax Queue Trait
 * Handles queue management AJAX endpoints
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

namespace BeepBeepAI\AltTextGenerator\Traits;

if (!defined('ABSPATH')) { exit; }

use BeepBeepAI\AltTextGenerator\Queue;
use BeepBeepAI\AltTextGenerator\Usage_Tracker;

trait Core_Ajax_Queue {

    /**
     * AJAX handler: Dismiss API notice
     */
    public function ajax_dismiss_api_notice() {
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }
        if ( ! $this->user_can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ] );
            return;
        }

        update_option('bbai_api_notice_dismissed', true, false);
        delete_option('wp_alt_text_api_notice_dismissed');
        wp_send_json_success(['message' => __('Notice dismissed', 'beepbeep-ai-alt-text-generator')]);
    }

    /**
     * AJAX handler: Dismiss upgrade notice
     */
    public function ajax_dismiss_upgrade() {
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }

        if ( ! $this->user_can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ] );
            return;
        }

        Usage_Tracker::dismiss_upgrade_notice();
        setcookie('bbai_upgrade_dismissed', '1', time() + HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);

        wp_send_json_success( [ 'message' => __( 'Notice dismissed', 'beepbeep-ai-alt-text-generator' ) ] );
    }

    /**
     * AJAX handler: Retry single queue job
     */
    public function ajax_queue_retry_job() {
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }
        if ( ! $this->user_can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ] );
            return;
        }

        $job_id = isset($_POST['job_id']) ? absint(wp_unslash($_POST['job_id'])) : 0;
        if ($job_id <= 0) {
            wp_send_json_error(['message' => __('Invalid job ID.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        Queue::retry_job($job_id);
        Queue::schedule_processing(10);
        wp_send_json_success(['message' => __('Job re-queued.', 'beepbeep-ai-alt-text-generator')]);
    }

    /**
     * AJAX handler: Retry all failed jobs
     */
    public function ajax_queue_retry_failed() {
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }
        if ( ! $this->user_can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ] );
            return;
        }

        Queue::retry_failed();
        Queue::schedule_processing(10);
        wp_send_json_success(['message' => __('Retry scheduled for failed jobs.', 'beepbeep-ai-alt-text-generator')]);
    }

    /**
     * AJAX handler: Clear completed jobs
     */
    public function ajax_queue_clear_completed() {
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }
        if ( ! $this->user_can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ] );
            return;
        }

        Queue::clear_completed();
        wp_send_json_success(['message' => __('Cleared completed jobs.', 'beepbeep-ai-alt-text-generator')]);
    }

    /**
     * AJAX handler: Get queue stats
     */
    public function ajax_queue_stats() {
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }
        if ( ! $this->user_can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ] );
            return;
        }

        $stats = Queue::get_stats();
        $failures = Queue::get_failures();

        wp_send_json_success([
            'stats' => $stats,
            'failures' => $failures
        ]);
    }

    /**
     * AJAX handler: Track upgrade click
     */
    public function ajax_track_upgrade() {
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }
        if ( ! $this->user_can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ] );
            return;
        }

        $source_input = isset($_POST['source']) ? sanitize_key(wp_unslash($_POST['source'])) : 'dashboard';
        $allowed_sources = [ 'dashboard', 'bulk', 'bulk-regenerate', 'library', 'manual', 'onboarding', 'queue', 'unknown' ];
        $source = in_array($source_input, $allowed_sources, true) ? $source_input : 'dashboard';
        $event = [
            'source' => $source,
            'user_id' => get_current_user_id(),
            'time' => current_time('mysql'),
        ];

        update_option('bbai_last_upgrade_click', $event, false);
        do_action('bbai_upgrade_clicked', $event);

        wp_send_json_success(['recorded' => true]);
    }

    /**
     * AJAX handler: Refresh usage data
     */
    public function ajax_refresh_usage() {
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }

        if ( ! $this->user_can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ] );
            return;
        }

        Usage_Tracker::clear_cache();
        $usage = $this->api_client->get_usage();

        if ($usage) {
                $stats = Usage_Tracker::get_stats_display();
                wp_send_json_success($stats);
            } else {
                wp_send_json_error( [ 'message' => __( 'Failed to fetch usage data', 'beepbeep-ai-alt-text-generator' ) ] );
                return;
            }
    }
}
