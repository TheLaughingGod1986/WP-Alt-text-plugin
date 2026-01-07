<?php
/**
 * REST Usage Trait
 * Handles usage and stats REST endpoints
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

namespace BeepBeepAI\AltTextGenerator\Traits;

if (!defined('ABSPATH')) { exit; }

use BeepBeepAI\AltTextGenerator\Usage_Tracker;

trait REST_Usage {

    /**
     * Handle stats request
     */
    public function handle_stats(\WP_REST_Request $request) {
        $stats = $this->core->get_media_stats();
        return rest_ensure_response($stats);
    }

    /**
     * Handle usage request
     */
    public function handle_usage() {
        $usage = Usage_Tracker::get_usage();
        if (is_wp_error($usage)) {
            return rest_ensure_response([
                'plan' => 'free',
                'limit' => 10,
                'used' => 0,
                'remaining' => 10,
            ]);
        }
        return rest_ensure_response($usage);
    }

    /**
     * Handle plans request
     */
    public function handle_plans() {
        $api_client = $this->core->get_api_client();
        $plans = $api_client->get_plans();
        if (is_wp_error($plans)) {
            return rest_ensure_response([]);
        }
        return rest_ensure_response($plans);
    }

    /**
     * Handle usage summary request
     */
    public function handle_usage_summary(\WP_REST_Request $request) {
        $period = $request->get_param('period') ?: 'month';

        if (class_exists('\BeepBeepAI\AltTextGenerator\Usage\Usage_Logs')) {
            $summary = \BeepBeepAI\AltTextGenerator\Usage\Usage_Logs::get_summary($period);
            return rest_ensure_response($summary);
        }

        return rest_ensure_response([
            'total_generations' => 0,
            'unique_users' => 0,
            'period' => $period,
        ]);
    }

    /**
     * Handle usage by user request
     */
    public function handle_usage_by_user(\WP_REST_Request $request) {
        $period = $request->get_param('period') ?: 'month';
        $limit = min(100, absint($request->get_param('limit') ?: 50));

        if (class_exists('\BeepBeepAI\AltTextGenerator\Usage\Usage_Logs')) {
            $data = \BeepBeepAI\AltTextGenerator\Usage\Usage_Logs::get_by_user($period, $limit);
            return rest_ensure_response($data);
        }

        return rest_ensure_response([]);
    }

    /**
     * Handle usage events request
     */
    public function handle_usage_events(\WP_REST_Request $request) {
        $limit = min(500, absint($request->get_param('limit') ?: 100));
        $offset = absint($request->get_param('offset') ?: 0);

        if (class_exists('\BeepBeepAI\AltTextGenerator\Usage\Usage_Logs')) {
            $events = \BeepBeepAI\AltTextGenerator\Usage\Usage_Logs::get_events($limit, $offset);
            return rest_ensure_response($events);
        }

        return rest_ensure_response([]);
    }

    /**
     * Handle user usage request
     */
    public function handle_user_usage(\WP_REST_Request $request) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new \WP_Error(
                'not_logged_in',
                __('Must be logged in', 'beepbeep-ai-alt-text-generator'),
                ['status' => 401]
            );
        }

        $period = $request->get_param('period') ?: 'month';

        if (class_exists('\BeepBeepAI\AltTextGenerator\Usage\Usage_Logs')) {
            $usage = \BeepBeepAI\AltTextGenerator\Usage\Usage_Logs::get_user_usage($user_id, $period);
            return rest_ensure_response($usage);
        }

        return rest_ensure_response([
            'user_id' => $user_id,
            'generations' => 0,
            'period' => $period,
        ]);
    }

    /**
     * Permission callback for viewing usage
     */
    public function can_view_usage() {
        return is_user_logged_in() && current_user_can('upload_files');
    }

    /**
     * Permission callback for viewing team usage
     */
    public function can_view_team_usage() {
        return is_user_logged_in() && current_user_can('manage_options');
    }
}
