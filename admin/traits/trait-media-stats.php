<?php
/**
 * Media Stats Trait
 * Handles media statistics and attachment snapshots
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

namespace BeepBeepAI\AltTextGenerator\Traits;

if (!defined('ABSPATH')) { exit; }

trait Media_Stats {

    /**
     * Invalidate stats cache
     */
    public function invalidate_stats_cache() {
        wp_cache_delete('bbai_stats', 'bbai');
        delete_transient('bbai_stats_v3');
        $this->stats_cache = null;
    }

    /**
     * Get media statistics
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

            $total = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'inherit' AND post_mime_type LIKE %s",
                'attachment', 'image/%'
            ));

            $with_alt = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
                 WHERE p.post_type = %s
                   AND p.post_status = %s
                   AND p.post_mime_type LIKE %s
                   AND m.meta_key = %s
                   AND TRIM(m.meta_value) <> ''",
                'attachment', 'inherit', 'image/%', '_wp_attachment_image_alt'
            ));

            $generated = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s",
                '_bbai_generated_at'
            ));

            $coverage = $total ? round(($with_alt / $total) * 100, 1) : 0;
            $missing = max(0, $total - $with_alt);

            $date_format_raw = get_option('date_format');
            $time_format_raw = get_option('time_format');
            $date_format = is_string($date_format_raw) ? $date_format_raw : '';
            $time_format = is_string($time_format_raw) ? $time_format_raw : '';
            $datetime_format = (!empty($date_format) && !empty($time_format)) ? $date_format . ' ' . $time_format : 'Y-m-d H:i:s';

            $opts = get_option(self::OPTION_KEY, []);
            $usage = $opts['usage'] ?? $this->default_usage();
            if (!empty($usage['last_request'])) {
                $usage['last_request_formatted'] = mysql2date($datetime_format, $usage['last_request']);
            }

            $latest_generated_raw = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY meta_value DESC LIMIT 1",
                '_bbai_generated_at'
            ));
            $latest_generated = $latest_generated_raw ? mysql2date($datetime_format, $latest_generated_raw) : '';

            $top_source_row = $wpdb->get_row(
                "SELECT meta_value AS source, COUNT(*) AS count
                 FROM {$wpdb->postmeta}
                 WHERE meta_key = '_bbai_source' AND meta_value <> ''
                 GROUP BY meta_value
                 ORDER BY COUNT(*) DESC
                 LIMIT 1",
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
                'latest_generated_raw' => $latest_generated_raw,
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
     * Prepare attachment snapshot
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
        $generated_raw = get_post_meta($attachment_id, '_bbai_generated_at', true);
        $generated = $generated_raw ? mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $generated_raw) : '';
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
            'generated_raw' => $generated_raw,
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
     * Get missing attachment IDs
     */
    public function get_missing_attachment_ids($limit = 5) {
        global $wpdb;

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} m ON p.ID = m.post_id AND m.meta_key = '_wp_attachment_image_alt'
             WHERE p.post_type = 'attachment'
               AND p.post_status = 'inherit'
               AND p.post_mime_type LIKE 'image/%%'
               AND (m.meta_value IS NULL OR TRIM(m.meta_value) = '')
             ORDER BY p.ID DESC
             LIMIT %d",
            $limit
        ));

        return array_map('intval', $ids);
    }

    /**
     * Get all attachment IDs
     */
    public function get_all_attachment_ids($limit = 5, $offset = 0) {
        global $wpdb;

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID
             FROM {$wpdb->posts}
             WHERE post_type = 'attachment'
               AND post_status = 'inherit'
               AND post_mime_type LIKE 'image/%%'
             ORDER BY ID DESC
             LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));

        return array_map('intval', $ids);
    }
}
