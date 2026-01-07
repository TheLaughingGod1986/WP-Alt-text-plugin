<?php
/**
 * REST Generation Trait
 * Handles alt text generation REST endpoints
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

namespace BeepBeepAI\AltTextGenerator\Traits;

if (!defined('ABSPATH')) { exit; }

use BeepBeepAI\AltTextGenerator\Queue;
use BeepBeepAI\AltTextGenerator\Debug_Log;

trait REST_Generation {

    /**
     * Handle single image generation
     */
    public function handle_generate_single(\WP_REST_Request $request) {
        $attachment_id = absint($request->get_param('id'));

        if (!wp_attachment_is_image($attachment_id)) {
            return new \WP_Error(
                'invalid_attachment',
                __('Not a valid image attachment', 'beepbeep-ai-alt-text-generator'),
                ['status' => 400]
            );
        }

        $api_client = $this->core->get_api_client();
        if (!$api_client->is_authenticated()) {
            return new \WP_Error(
                'auth_required',
                __('Authentication required', 'beepbeep-ai-alt-text-generator'),
                ['status' => 401]
            );
        }

        if ($api_client->has_reached_limit()) {
            return new \WP_Error(
                'limit_reached',
                __('Monthly limit reached. Upgrade to continue.', 'beepbeep-ai-alt-text-generator'),
                ['status' => 403]
            );
        }

        $regenerate = $request->get_param('regenerate') === true || $request->get_param('regenerate') === 'true';
        $result = $this->core->generate_and_save($attachment_id, 'rest', 0, [], $regenerate);

        if (is_wp_error($result)) {
            $error_code = $result->get_error_code();
            $status_map = [
                'limit_reached' => 403,
                'auth_required' => 401,
                'invalid_image' => 400,
            ];
            $status = $status_map[$error_code] ?? 500;

            return new \WP_Error(
                $error_code,
                $result->get_error_message(),
                ['status' => $status]
            );
        }

        $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $snapshot = $this->core->prepare_attachment_snapshot($attachment_id);

        return rest_ensure_response([
            'success' => true,
            'alt_text' => $alt_text,
            'attachment' => $snapshot,
        ]);
    }

    /**
     * Handle save alt text
     */
    public function handle_save_alt(\WP_REST_Request $request) {
        $attachment_id = absint($request->get_param('id'));
        $alt_text = $request->get_param('alt_text');

        if (!is_string($alt_text)) {
            return new \WP_Error(
                'invalid_alt_text',
                __('Alt text must be a string', 'beepbeep-ai-alt-text-generator'),
                ['status' => 400]
            );
        }

        if (!wp_attachment_is_image($attachment_id)) {
            return new \WP_Error(
                'invalid_attachment',
                __('Not a valid image attachment', 'beepbeep-ai-alt-text-generator'),
                ['status' => 400]
            );
        }

        $alt_text = sanitize_text_field($alt_text);
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);

        $snapshot = $this->core->prepare_attachment_snapshot($attachment_id);

        return rest_ensure_response([
            'success' => true,
            'alt_text' => $alt_text,
            'attachment' => $snapshot,
        ]);
    }

    /**
     * Handle list attachments
     */
    public function handle_list(\WP_REST_Request $request) {
        $scope = $request->get_param('scope') ?: 'missing';
        $limit = min(500, absint($request->get_param('limit') ?: 100));
        $offset = absint($request->get_param('offset') ?: 0);

        if ($scope === 'missing') {
            $ids = $this->core->get_missing_attachment_ids($limit);
        } else {
            $ids = $this->core->get_all_attachment_ids($limit, $offset);
        }

        return rest_ensure_response([
            'ids' => $ids,
            'count' => count($ids),
            'scope' => $scope,
        ]);
    }
}
