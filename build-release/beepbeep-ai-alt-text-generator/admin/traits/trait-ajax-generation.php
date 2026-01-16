<?php
/**
 * AJAX Generation Trait
 * Handles alt text generation AJAX endpoints
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

namespace BeepBeepAI\AltTextGenerator\Traits;

if (!defined('ABSPATH')) { exit; }

use BeepBeepAI\AltTextGenerator\Queue;
use BeepBeepAI\AltTextGenerator\Usage_Tracker;

trait Ajax_Generation {

    /**
     * AJAX handler: Regenerate single image
     */
    public function ajax_regenerate_single() {
        check_ajax_referer('beepbeepai_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
        }

        $attachment_id_raw = isset($_POST['attachment_id']) ? wp_unslash($_POST['attachment_id']) : '';
        $attachment_id = is_string($attachment_id_raw) ? absint($attachment_id_raw) : 0;

        if (empty($attachment_id)) {
            wp_send_json_error(['message' => __('Attachment ID is required', 'beepbeep-ai-alt-text-generator')]);
        }

        if (!wp_attachment_is_image($attachment_id)) {
            wp_send_json_error(['message' => __('Not a valid image attachment', 'beepbeep-ai-alt-text-generator')]);
        }

        if (!$this->api_client->is_authenticated()) {
            wp_send_json_error([
                'message' => __('Authentication required. Please log in to regenerate alt text.', 'beepbeep-ai-alt-text-generator'),
                'code' => 'auth_required'
            ]);
        }

        $usage = Usage_Tracker::get_usage();
        if (!is_wp_error($usage)) {
            $remaining = isset($usage['remaining']) ? intval($usage['remaining']) : null;
            $plan = isset($usage['plan']) ? strtolower($usage['plan']) : 'free';
            $is_premium = ($plan === 'pro' || $plan === 'agency');

            if (!$is_premium && $remaining !== null && $remaining <= 0) {
                wp_send_json_error([
                    'message' => __('Monthly limit reached. Upgrade to continue.', 'beepbeep-ai-alt-text-generator'),
                    'code' => 'limit_reached',
                    'usage' => $usage
                ]);
            }
        }

        $result = $this->generate_and_save($attachment_id, 'regenerate', 0, [], true);

        if (is_wp_error($result)) {
            $error_code = $result->get_error_code();
            $error_message = $result->get_error_message();

            if ($error_code === 'limit_reached' || (is_string($error_message) && strpos(strtolower($error_message), 'limit') !== false)) {
                wp_send_json_error([
                    'message' => $error_message,
                    'code' => 'limit_reached'
                ]);
            }

            wp_send_json_error(['message' => $error_message]);
        }

        $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);

        wp_send_json_success([
            'message' => __('Alt text regenerated successfully', 'beepbeep-ai-alt-text-generator'),
            'altText' => $alt_text,
            'alt_text' => $alt_text
        ]);
    }

    /**
     * AJAX handler: Bulk queue images
     */
    public function ajax_bulk_queue() {
        check_ajax_referer('beepbeepai_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
        }

        $attachment_ids_raw = isset($_POST['attachment_ids']) ? wp_unslash($_POST['attachment_ids']) : [];
        $attachment_ids = is_array($attachment_ids_raw) ? array_map('absint', $attachment_ids_raw) : [];
        $attachment_ids = array_filter($attachment_ids);

        if (empty($attachment_ids)) {
            wp_send_json_error(['message' => __('No images provided', 'beepbeep-ai-alt-text-generator')]);
        }

        $source_raw = isset($_POST['source']) ? wp_unslash($_POST['source']) : 'bulk';
        $source = is_string($source_raw) ? sanitize_text_field($source_raw) : 'bulk';

        if (!$this->api_client->is_authenticated()) {
            wp_send_json_error([
                'message' => __('Authentication required. Please log in first.', 'beepbeep-ai-alt-text-generator'),
                'code' => 'auth_required'
            ]);
        }

        $usage = Usage_Tracker::get_usage();
        if (!is_wp_error($usage)) {
            $remaining = isset($usage['remaining']) ? intval($usage['remaining']) : null;
            $plan = isset($usage['plan']) ? strtolower($usage['plan']) : 'free';
            $is_premium = ($plan === 'pro' || $plan === 'agency');
            $count = count($attachment_ids);

            if (!$is_premium && $remaining !== null && $remaining <= 0) {
                wp_send_json_error([
                    'message' => __('Monthly limit reached. Upgrade to continue.', 'beepbeep-ai-alt-text-generator'),
                    'code' => 'limit_reached',
                    'usage' => $usage
                ]);
            }

            if (!$is_premium && $remaining !== null && $remaining < $count) {
                wp_send_json_error([
                    'message' => sprintf(
                        __('Not enough credits. You have %d remaining but need %d.', 'beepbeep-ai-alt-text-generator'),
                        $remaining,
                        $count
                    ),
                    'code' => 'insufficient_credits',
                    'remaining' => $remaining,
                    'requested' => $count
                ]);
            }
        }

        $queued = 0;
        $skipped = 0;
        $errors = [];

        foreach ($attachment_ids as $attachment_id) {
            if (!wp_attachment_is_image($attachment_id)) {
                $skipped++;
                continue;
            }

            $result = $this->queue_attachment($attachment_id, $source);
            if (is_wp_error($result)) {
                $errors[] = $result->get_error_message();
                $skipped++;
            } else {
                $queued++;
            }
        }

        if ($queued === 0 && !empty($errors)) {
            wp_send_json_error([
                'message' => $errors[0],
                'errors' => $errors
            ]);
        }

        wp_send_json_success([
            'queued' => $queued,
            'skipped' => $skipped,
            'message' => sprintf(
                __('%d images queued for processing', 'beepbeep-ai-alt-text-generator'),
                $queued
            )
        ]);
    }

    /**
     * AJAX handler: Inline generate for bulk operations
     */
    public function ajax_inline_generate() {
        check_ajax_referer('beepbeepai_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
        }

        $attachment_ids_raw = isset($_POST['attachment_ids']) ? wp_unslash($_POST['attachment_ids']) : [];
        $attachment_ids = is_array($attachment_ids_raw) ? array_map('absint', $attachment_ids_raw) : [];
        $attachment_ids = array_filter($attachment_ids);

        if (empty($attachment_ids)) {
            wp_send_json_error(['message' => __('No images provided', 'beepbeep-ai-alt-text-generator')]);
        }

        if (!$this->api_client->is_authenticated()) {
            wp_send_json_error([
                'message' => __('Authentication required', 'beepbeep-ai-alt-text-generator'),
                'code' => 'auth_required'
            ]);
        }

        $results = [];
        foreach ($attachment_ids as $attachment_id) {
            if (!wp_attachment_is_image($attachment_id)) {
                $results[] = [
                    'id' => $attachment_id,
                    'success' => false,
                    'message' => __('Not a valid image', 'beepbeep-ai-alt-text-generator')
                ];
                continue;
            }

            $title = get_the_title($attachment_id);
            $result = $this->generate_and_save($attachment_id, 'inline', 0, [], false);

            if (is_wp_error($result)) {
                $results[] = [
                    'id' => $attachment_id,
                    'success' => false,
                    'message' => $result->get_error_message(),
                    'code' => $result->get_error_code(),
                    'title' => $title
                ];
            } else {
                $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
                $results[] = [
                    'id' => $attachment_id,
                    'success' => true,
                    'alt_text' => $alt_text,
                    'title' => $title
                ];
            }
        }

        wp_send_json_success([
            'results' => $results,
            'processed' => count($results)
        ]);
    }
}
