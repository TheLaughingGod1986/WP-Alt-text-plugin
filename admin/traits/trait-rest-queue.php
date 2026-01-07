<?php
/**
 * REST Queue Trait
 * Handles queue and logs REST endpoints
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

namespace BeepBeepAI\AltTextGenerator\Traits;

if (!defined('ABSPATH')) { exit; }

use BeepBeepAI\AltTextGenerator\Queue;
use BeepBeepAI\AltTextGenerator\Debug_Log;

trait REST_Queue {

    /**
     * Handle queue status request
     */
    public function handle_queue() {
        $stats = Queue::get_stats();
        $recent = Queue::get_recent(20);

        $jobs = array_map(function ($row) {
            return $this->sanitize_job_row($row);
        }, $recent);

        return rest_ensure_response([
            'stats' => $stats,
            'jobs' => $jobs,
        ]);
    }

    /**
     * Sanitize queue job row for response
     */
    private function sanitize_job_row(array $row) {
        return [
            'id' => intval($row['id'] ?? 0),
            'attachment_id' => intval($row['attachment_id'] ?? 0),
            'status' => sanitize_key($row['status'] ?? 'pending'),
            'source' => sanitize_key($row['source'] ?? 'unknown'),
            'attempts' => intval($row['attempts'] ?? 0),
            'error_message' => sanitize_text_field($row['error_message'] ?? ''),
            'created_at' => $row['created_at'] ?? '',
            'updated_at' => $row['updated_at'] ?? '',
        ];
    }

    /**
     * Handle logs request
     */
    public function handle_logs(\WP_REST_Request $request) {
        $page = max(1, absint($request->get_param('page') ?: 1));
        $per_page = min(100, absint($request->get_param('per_page') ?: 50));
        $level = $request->get_param('level');
        $source = $request->get_param('source');

        $filters = [
            'page' => $page,
            'per_page' => $per_page,
        ];

        if ($level && in_array($level, ['debug', 'info', 'warning', 'error'], true)) {
            $filters['level'] = $level;
        }

        if ($source) {
            $filters['source'] = sanitize_key($source);
        }

        if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
            $result = Debug_Log::get_logs($filters);
            return rest_ensure_response($result);
        }

        return rest_ensure_response([
            'logs' => [],
            'pagination' => [
                'current_page' => $page,
                'per_page' => $per_page,
                'total' => 0,
                'total_pages' => 0,
            ],
        ]);
    }

    /**
     * Handle clear logs request
     */
    public function handle_logs_clear(\WP_REST_Request $request) {
        $before_days = absint($request->get_param('before_days') ?: 0);

        if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
            if ($before_days > 0) {
                $deleted = Debug_Log::clear_old_logs($before_days);
            } else {
                $deleted = Debug_Log::clear_all_logs();
            }

            return rest_ensure_response([
                'success' => true,
                'deleted' => $deleted,
            ]);
        }

        return rest_ensure_response([
            'success' => false,
            'deleted' => 0,
        ]);
    }

    /**
     * Handle events request
     */
    public function handle_events(\WP_REST_Request $request) {
        $limit = min(100, absint($request->get_param('limit') ?: 50));
        $offset = absint($request->get_param('offset') ?: 0);

        if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
            $events = Debug_Log::get_logs([
                'per_page' => $limit,
                'page' => floor($offset / $limit) + 1,
                'level' => 'info',
            ]);
            return rest_ensure_response($events);
        }

        return rest_ensure_response([
            'logs' => [],
            'pagination' => [],
        ]);
    }

    /**
     * Handle log event request
     */
    public function handle_log_event(\WP_REST_Request $request) {
        $level = $request->get_param('level') ?: 'info';
        $message = $request->get_param('message');
        $context = $request->get_param('context') ?: [];
        $source = $request->get_param('source') ?: 'client';

        if (!$message || !is_string($message)) {
            return new \WP_Error(
                'invalid_message',
                __('Message is required', 'beepbeep-ai-alt-text-generator'),
                ['status' => 400]
            );
        }

        if (!in_array($level, ['debug', 'info', 'warning', 'error'], true)) {
            $level = 'info';
        }

        if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
            Debug_Log::log($level, sanitize_text_field($message), is_array($context) ? $context : [], sanitize_key($source));
        }

        return rest_ensure_response(['success' => true]);
    }
}
