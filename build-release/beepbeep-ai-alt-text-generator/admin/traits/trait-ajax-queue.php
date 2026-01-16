<?php
/**
 * AJAX Queue Trait
 * Handles queue-related AJAX endpoints
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

namespace BeepBeepAI\AltTextGenerator\Traits;

if (!defined('ABSPATH')) { exit; }

trait Ajax_Queue {

    /**
     * AJAX handler: Dismiss API notice
     */
    public function ajax_dismiss_api_notice() {
        check_ajax_referer('beepbeepai_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
        }

        update_option('bbai_api_notice_dismissed', true, false);
        wp_send_json_success();
    }

    /**
     * AJAX handler: Dismiss upgrade notice
     */
    public function ajax_dismiss_upgrade() {
        check_ajax_referer('beepbeepai_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
        }

        set_transient('bbai_upgrade_dismissed', true, DAY_IN_SECONDS);
        wp_send_json_success();
    }

    /**
     * AJAX handler: Retry a failed queue job
     */
    public function ajax_queue_retry_job() {
        check_ajax_referer('beepbeepai_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
        }

        $job_id_raw = isset($_POST['job_id']) ? wp_unslash($_POST['job_id']) : '';
        $job_id = is_string($job_id_raw) ? absint($job_id_raw) : 0;
        if (empty($job_id)) {
            wp_send_json_error(['message' => __('Job ID is required', 'beepbeep-ai-alt-text-generator')]);
        }

        $result = Queue::retry_job($job_id);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('Job queued for retry', 'beepbeep-ai-alt-text-generator')]);
    }

    /**
     * AJAX handler: Retry all failed jobs
     */
    public function ajax_queue_retry_failed() {
        check_ajax_referer('beepbeepai_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
        }

        $count = Queue::retry_all_failed();
        wp_send_json_success(['count' => $count, 'message' => sprintf(__('%d jobs queued for retry', 'beepbeep-ai-alt-text-generator'), $count)]);
    }

    /**
     * AJAX handler: Clear completed jobs
     */
    public function ajax_queue_clear_completed() {
        check_ajax_referer('beepbeepai_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
        }

        $count = Queue::clear_completed();
        wp_send_json_success(['count' => $count, 'message' => sprintf(__('%d completed jobs cleared', 'beepbeep-ai-alt-text-generator'), $count)]);
    }

    /**
     * AJAX handler: Get queue stats
     */
    public function ajax_queue_stats() {
        check_ajax_referer('beepbeepai_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
        }

        $stats = Queue::get_stats();
        wp_send_json_success($stats);
    }

    /**
     * AJAX handler: Track upgrade click
     */
    public function ajax_track_upgrade() {
        check_ajax_referer('beepbeepai_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
        }

        $plan_raw = isset($_POST['plan']) ? wp_unslash($_POST['plan']) : '';
        $plan = is_string($plan_raw) ? sanitize_text_field($plan_raw) : 'unknown';

        update_option('bbai_last_upgrade_click', [
            'plan' => $plan,
            'time' => current_time('mysql')
        ], false);

        wp_send_json_success();
    }

    /**
     * AJAX handler: Refresh usage stats
     */
    public function ajax_refresh_usage() {
        check_ajax_referer('beepbeepai_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
        }

        $usage = $this->api_client->get_usage(true);
        if (is_wp_error($usage)) {
            wp_send_json_error(['message' => $usage->get_error_message()]);
        }

        wp_send_json_success($usage);
    }
}
