<?php
/**
 * Core Media Trait
 * Handles media statistics, attachment queries, and snapshot preparation
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

namespace BeepBeepAI\AltTextGenerator\Traits;

if (!defined('ABSPATH')) { exit; }

trait Core_Media {

    /**
     * Check if attachment is an image
     *
     * @param int $attachment_id Attachment ID
     * @return bool
     */
    private function is_image($attachment_id) {
        $mime = get_post_mime_type($attachment_id);
        return strpos((string)$mime, 'image/') === 0;
    }

    /**
     * Invalidate stats cache
     */
    public function invalidate_stats_cache() {
        wp_cache_delete('bbai_stats', 'bbai');
        delete_transient('bbai_stats_v3');
        $this->stats_cache = null;
    }

    /**
     * Get media library statistics
     *
     * @return array Statistics array
     */
    public function get_media_stats() {
        try {
            if (is_array($this->stats_cache)) {
                return $this->stats_cache;
            }

            $cache_key = 'bbai_stats';
            $cache_group = 'bbai';
            $cached = wp_cache_get($cache_key, $cache_group);
            if (false !== $cached && is_array($cached)) {
                $this->stats_cache = $cached;
                return $cached;
            }

            $transient_key = 'bbai_stats_v3';
            $cached = get_transient($transient_key);
            if (false !== $cached && is_array($cached)) {
                wp_cache_set($cache_key, $cached, $cache_group, 15 * MINUTE_IN_SECONDS);
                $this->stats_cache = $cached;
                return $cached;
            }

            global $wpdb;

            $image_mime_like = $wpdb->esc_like('image/') . '%';

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $total = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM ' . $wpdb->posts . ' WHERE post_type = %s AND post_status = %s AND post_mime_type LIKE %s',
                'attachment', 'inherit', $image_mime_like
            ));

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $with_alt = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(DISTINCT p.ID) FROM ' . $wpdb->posts . ' p INNER JOIN ' . $wpdb->postmeta . ' m ON p.ID = m.post_id WHERE p.post_type = %s AND p.post_status = %s AND p.post_mime_type LIKE %s AND m.meta_key = %s AND TRIM(m.meta_value) <> %s',
                'attachment',
                'inherit',
                $image_mime_like,
                '_wp_attachment_image_alt',
                ''
            ));

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $generated = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(DISTINCT post_id) FROM ' . $wpdb->postmeta . ' WHERE meta_key = %s',
                '_bbai_generated_at'
            ));

            $coverage = $total ? round(($with_alt / $total) * 100, 1) : 0;
            $missing = max(0, $total - $with_alt);

            $date_format_input = get_option('date_format');
            $time_format_input = get_option('time_format');
            $date_format = is_string($date_format_input) ? $date_format_input : '';
            $time_format = is_string($time_format_input) ? $time_format_input : '';
            $datetime_format = (!empty($date_format) && !empty($time_format)) ? $date_format . ' ' . $time_format : 'Y-m-d H:i:s';

            $opts = get_option(self::OPTION_KEY, []);
            $usage = $opts['usage'] ?? $this->default_usage();
            if (!empty($usage['last_request'])) {
                $usage['last_request_formatted'] = mysql2date($datetime_format, $usage['last_request']);
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $latest_generated_input = $wpdb->get_var($wpdb->prepare(
                'SELECT meta_value FROM ' . $wpdb->postmeta . ' WHERE meta_key = %s ORDER BY meta_value DESC LIMIT 1',
                '_bbai_generated_at'
            ));
            $latest_generated = $latest_generated_input ? mysql2date($datetime_format, $latest_generated_input) : '';

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $top_source_row = $wpdb->get_row(
                $wpdb->prepare(
                    'SELECT meta_value AS source, COUNT(*) AS count FROM ' . $wpdb->postmeta . ' WHERE meta_key = %s AND meta_value <> %s GROUP BY meta_value ORDER BY COUNT(*) DESC LIMIT 1',
                    '_bbai_source',
                    ''
                ),
                ARRAY_A
            );
            $top_source_key = sanitize_key($top_source_row['source'] ?? '');
            $top_source_count = intval($top_source_row['count'] ?? 0);

            $this->stats_cache = [
                'total' => $total,
                'with_alt' => $with_alt,
                'missing' => $missing,
                'generated' => $generated,
                'coverage' => $coverage,
                'usage' => $usage,
                'token_limit' => intval($opts['token_limit'] ?? 0),
                'latest_generated' => $latest_generated,
                'latest_generated_raw' => $latest_generated_input,
                'top_source_key' => $top_source_key,
                'top_source_count' => $top_source_count,
                'dry_run_enabled' => !empty($opts['dry_run']),
                'audit' => $this->get_usage_rows(10),
            ];

            wp_cache_set($cache_key, $this->stats_cache, $cache_group, 15 * MINUTE_IN_SECONDS);
            set_transient($transient_key, $this->stats_cache, 15 * MINUTE_IN_SECONDS);

            return $this->stats_cache;
        } catch (\Exception $e) {
            return [
                'total' => 0,
                'with_alt' => 0,
                'missing_alt' => 0,
                'ai_generated' => 0,
                'manual' => 0,
                'coverage' => 0,
            ];
        }
    }

    /**
     * Prepare attachment snapshot for API response
     *
     * @param int $attachment_id Attachment ID
     * @return array Snapshot data
     */
    public function prepare_attachment_snapshot($attachment_id) {
        $attachment_id = intval($attachment_id);
        if ($attachment_id <= 0) {
            return [];
        }

        $alt = (string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $tokens = intval(get_post_meta($attachment_id, '_bbai_tokens_total', true));
        $prompt = intval(get_post_meta($attachment_id, '_bbai_tokens_prompt', true));
        $completion = intval(get_post_meta($attachment_id, '_bbai_tokens_completion', true));
        $generated_input = get_post_meta($attachment_id, '_bbai_generated_at', true);
        $generated = $generated_input ? mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $generated_input) : '';
        $source_key = sanitize_key(get_post_meta($attachment_id, '_bbai_source', true) ?: 'unknown');
        if (!$source_key) {
            $source_key = 'unknown';
        }

        $analysis = $this->evaluate_alt_health($attachment_id, $alt);

        return [
            'id' => $attachment_id,
            'alt' => $alt,
            'tokens' => $tokens,
            'prompt' => $prompt,
            'completion' => $completion,
            'generated_raw' => $generated_input,
            'generated' => $generated,
            'source_key' => $source_key,
            'source_label' => $this->format_source_label($source_key),
            'source_description' => $this->format_source_description($source_key),
            'score' => $analysis['score'],
            'score_grade' => $analysis['grade'],
            'score_status' => $analysis['status'],
            'score_issues' => $analysis['issues'],
            'score_summary' => $analysis['review']['summary'] ?? '',
            'analysis' => $analysis,
        ];
    }

    /**
     * Get attachment IDs missing alt text
     *
     * @param int $limit Maximum number of IDs to return
     * @return array Array of attachment IDs
     */
    public function get_missing_attachment_ids($limit = 5) {
        global $wpdb;
        $limit = intval($limit);
        if ($limit <= 0) {
            $limit = 5;
        }

        $image_mime_like = $wpdb->esc_like('image/') . '%';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
            'SELECT p.ID FROM ' . $wpdb->posts . ' p LEFT JOIN ' . $wpdb->postmeta . ' m ON (p.ID = m.post_id AND m.meta_key = %s) WHERE p.post_type = %s AND p.post_status = %s AND p.post_mime_type LIKE %s AND (m.meta_value IS NULL OR TRIM(m.meta_value) = %s) ORDER BY p.ID DESC LIMIT %d',
            '_wp_attachment_image_alt',
            'attachment',
            'inherit',
            $image_mime_like,
            '',
            $limit
        )));
    }

    /**
     * Get all attachment IDs with pagination
     *
     * @param int $limit Maximum number of IDs to return
     * @param int $offset Offset for pagination
     * @return array Array of attachment IDs
     */
    public function get_all_attachment_ids($limit = 5, $offset = 0) {
        global $wpdb;
        $limit = max(1, intval($limit));
        $offset = max(0, intval($offset));

        $image_mime_like = $wpdb->esc_like('image/') . '%';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_col($wpdb->prepare(
            'SELECT p.ID FROM ' . $wpdb->posts . ' p LEFT JOIN ' . $wpdb->postmeta . ' gen ON gen.post_id = p.ID AND gen.meta_key = %s WHERE p.post_type = %s AND p.post_status = %s AND p.post_mime_type LIKE %s ORDER BY CASE WHEN gen.meta_value IS NOT NULL THEN gen.meta_value ELSE p.post_date END DESC, p.ID DESC LIMIT %d OFFSET %d',
            '_bbai_generated_at',
            'attachment',
            'inherit',
            $image_mime_like,
            $limit,
            $offset
        ));
        return array_map('intval', (array) $rows);
    }

    /**
     * Format source label for display
     *
     * @param string $key Source key
     * @return string Formatted label
     */
    private function format_source_label($key) {
        $map = $this->get_source_meta_map();
        return $map[$key]['label'] ?? ucfirst($key);
    }

    /**
     * Format source description
     *
     * @param string $key Source key
     * @return string Description
     */
    private function format_source_description($key) {
        $map = $this->get_source_meta_map();
        return $map[$key]['description'] ?? '';
    }

    /**
     * Get source meta key mapping
     *
     * @return array Source mapping
     */
    private function get_source_meta_map() {
        return [
            'auto' => [
                'label' => __('Auto (Upload)', 'beepbeep-ai-alt-text-generator'),
                'description' => __('Generated automatically on upload', 'beepbeep-ai-alt-text-generator'),
            ],
            'manual' => [
                'label' => __('Manual', 'beepbeep-ai-alt-text-generator'),
                'description' => __('Generated via manual trigger', 'beepbeep-ai-alt-text-generator'),
            ],
            'bulk' => [
                'label' => __('Bulk', 'beepbeep-ai-alt-text-generator'),
                'description' => __('Generated via bulk action', 'beepbeep-ai-alt-text-generator'),
            ],
            'ajax' => [
                'label' => __('Dashboard', 'beepbeep-ai-alt-text-generator'),
                'description' => __('Generated from dashboard', 'beepbeep-ai-alt-text-generator'),
            ],
            'inline' => [
                'label' => __('Inline', 'beepbeep-ai-alt-text-generator'),
                'description' => __('Generated inline from media library', 'beepbeep-ai-alt-text-generator'),
            ],
            'rest' => [
                'label' => __('REST API', 'beepbeep-ai-alt-text-generator'),
                'description' => __('Generated via REST API', 'beepbeep-ai-alt-text-generator'),
            ],
            'queue' => [
                'label' => __('Queue', 'beepbeep-ai-alt-text-generator'),
                'description' => __('Generated via background queue', 'beepbeep-ai-alt-text-generator'),
            ],
            'wpcli' => [
                'label' => __('WP-CLI', 'beepbeep-ai-alt-text-generator'),
                'description' => __('Generated via WP-CLI command', 'beepbeep-ai-alt-text-generator'),
            ],
            'dry-run' => [
                'label' => __('Dry Run', 'beepbeep-ai-alt-text-generator'),
                'description' => __('Test run without API call', 'beepbeep-ai-alt-text-generator'),
            ],
            'unknown' => [
                'label' => __('Unknown', 'beepbeep-ai-alt-text-generator'),
                'description' => __('Source not recorded', 'beepbeep-ai-alt-text-generator'),
            ],
        ];
    }
}
