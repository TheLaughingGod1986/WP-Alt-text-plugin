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
        $nonce = isset($_POST["nonce"]) ? sanitize_text_field(wp_unslash($_POST["nonce"])) : "";
        if (!$nonce || !wp_verify_nonce($nonce, "beepbeepai_nonce")) {
            wp_send_json_error(["message" => __("Invalid nonce.", "opptiai-alt")], 403);
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt')]);
        }

        update_option('bbai_api_notice_dismissed', true, false);
        delete_option('wp_alt_text_api_notice_dismissed');
        wp_send_json_success(['message' => __('Notice dismissed', 'opptiai-alt')]);
    }

    /**
     * AJAX handler: Dismiss upgrade notice
     */
    public function ajax_dismiss_upgrade() {
        $nonce = isset($_POST["nonce"]) ? sanitize_text_field(wp_unslash($_POST["nonce"])) : "";
        if (!$nonce || !wp_verify_nonce($nonce, "beepbeepai_nonce")) {
            wp_send_json_error(["message" => __("Invalid nonce.", "opptiai-alt")], 403);
        }

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        Usage_Tracker::dismiss_upgrade_notice();
        setcookie('bbai_upgrade_dismissed', '1', time() + HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);

        wp_send_json_success(['message' => 'Notice dismissed']);
    }

    /**
     * AJAX handler: Retry single queue job
     */
    public function ajax_queue_retry_job() {
        $nonce = isset($_POST["nonce"]) ? sanitize_text_field(wp_unslash($_POST["nonce"])) : "";
        if (!$nonce || !wp_verify_nonce($nonce, "beepbeepai_nonce")) {
            wp_send_json_error(["message" => __("Invalid nonce.", "opptiai-alt")], 403);
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt')]);
        }

        $job_id_raw = isset($_POST['job_id']) ? wp_unslash($_POST['job_id']) : '';
        $job_id = absint($job_id_raw);
        if ($job_id <= 0) {
            wp_send_json_error(['message' => __('Invalid job ID.', 'opptiai-alt')]);
        }

        Queue::retry_job($job_id);
        Queue::schedule_processing(10);
        wp_send_json_success(['message' => __('Job re-queued.', 'opptiai-alt')]);
    }

    /**
     * AJAX handler: Retry all failed jobs
     */
    public function ajax_queue_retry_failed() {
        $nonce = isset($_POST["nonce"]) ? sanitize_text_field(wp_unslash($_POST["nonce"])) : "";
        if (!$nonce || !wp_verify_nonce($nonce, "beepbeepai_nonce")) {
            wp_send_json_error(["message" => __("Invalid nonce.", "opptiai-alt")], 403);
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt')]);
        }

        Queue::retry_failed();
        Queue::schedule_processing(10);
        wp_send_json_success(['message' => __('Retry scheduled for failed jobs.', 'opptiai-alt')]);
    }

    /**
     * AJAX handler: Clear completed jobs
     */
    public function ajax_queue_clear_completed() {
        $nonce = isset($_POST["nonce"]) ? sanitize_text_field(wp_unslash($_POST["nonce"])) : "";
        if (!$nonce || !wp_verify_nonce($nonce, "beepbeepai_nonce")) {
            wp_send_json_error(["message" => __("Invalid nonce.", "opptiai-alt")], 403);
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt')]);
        }

        Queue::clear_completed();
        wp_send_json_success(['message' => __('Cleared completed jobs.', 'opptiai-alt')]);
    }

    /**
     * AJAX handler: Get queue stats
     */
    public function ajax_queue_stats() {
        $nonce = isset($_POST["nonce"]) ? sanitize_text_field(wp_unslash($_POST["nonce"])) : "";
        if (!$nonce || !wp_verify_nonce($nonce, "beepbeepai_nonce")) {
            wp_send_json_error(["message" => __("Invalid nonce.", "opptiai-alt")], 403);
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt')]);
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
        $nonce = isset($_POST["nonce"]) ? sanitize_text_field(wp_unslash($_POST["nonce"])) : "";
        if (!$nonce || !wp_verify_nonce($nonce, "beepbeepai_nonce")) {
            wp_send_json_error(["message" => __("Invalid nonce.", "opptiai-alt")], 403);
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt')]);
        }

        $source_raw = isset($_POST['source']) ? wp_unslash($_POST['source']) : 'dashboard';
        $source = sanitize_key($source_raw);
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
        $nonce = isset($_POST["nonce"]) ? sanitize_text_field(wp_unslash($_POST["nonce"])) : "";
        if (!$nonce || !wp_verify_nonce($nonce, "beepbeepai_nonce")) {
            wp_send_json_error(["message" => __("Invalid nonce.", "opptiai-alt")], 403);
        }

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        Usage_Tracker::clear_cache();
        $usage = $this->api_client->get_usage();

        if ($usage) {
            $stats = Usage_Tracker::get_stats_display();
            wp_send_json_success($stats);
        } else {
            wp_send_json_error(['message' => 'Failed to fetch usage data']);
        }
    }
}
