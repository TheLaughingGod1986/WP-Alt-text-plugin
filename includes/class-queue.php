<?php
/**
 * Lightweight job queue for AltText AI background processing.
 */

namespace BeepBeepAI\AltTextGenerator;

if (!defined('ABSPATH')) {
    exit;
}

class Queue {
    const TABLE_SLUG = 'bbai_queue';
    const CRON_HOOK  = 'bbai_process_queue';

    /**
     * Get queue table name.
     */
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SLUG;
    }

    /**
     * Create queue table on activation.
     */
    public static function create_table() {
        global $wpdb;
        $table = esc_sql( self::table() );
        $charset = $wpdb->get_charset_collate();

        $sql = "
            CREATE TABLE `{$table}` (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                attachment_id BIGINT UNSIGNED NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                source VARCHAR(50) NOT NULL DEFAULT 'auto',
                last_error TEXT NULL,
                enqueued_at DATETIME NOT NULL,
                locked_at DATETIME NULL,
                completed_at DATETIME NULL,
                PRIMARY KEY (id),
                KEY status (status),
                KEY attachment_status (attachment_id, status)
            ) {$charset};
        ";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
        dbDelta($sql);
    }

    /**
     * Schedule queue processing event.
     */
    public static function schedule_processing($delay = 30) {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_single_event(time() + max(5, (int) $delay), self::CRON_HOOK);
        }
    }

    /**
     * Enqueue a single attachment.
     */
    public static function enqueue($attachment_id, $source = 'auto') {
        global $wpdb;
        $attachment_id = intval($attachment_id);
        if ($attachment_id <= 0) {
            return false;
        }

        $table = esc_sql( self::table() );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- must be real-time
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT id FROM `{$table}` WHERE attachment_id = %d AND status IN (%s,%s) LIMIT 1",
                $attachment_id,
                'pending',
                'processing'
            )
        );

        if ($exists) {
            self::schedule_processing();
            return true;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $inserted = $wpdb->insert(
            $table,
            [
                'attachment_id' => $attachment_id,
                'status'        => 'pending',
                'source'        => sanitize_key($source),
                'enqueued_at'   => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s']
        );

        if ($inserted) {
            BBAI_Cache::bump( 'queue' );
            self::schedule_processing();
            return true;
        }

        return false;
    }

    /**
     * Clear existing queue entries for specific attachment IDs.
     */
    public static function clear_for_attachments(array $ids) {
        global $wpdb;
        if (empty($ids)) {
            return 0;
        }
        
        $table = esc_sql( self::table() );
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids, function($id) {
            return $id > 0;
        });
        
        if (empty($ids)) {
            return 0;
        }
        
        // Build safe IN clause with dynamic placeholders via array_fill()
        // All IDs must be sanitized before passing to prepare()
        $ids_clean = array_map('absint', $ids);
        
        if (empty($ids_clean)) {
            return 0;
        }
        
        // Clear ALL entries (pending, processing, completed, failed) to allow regeneration.
        $deleted = 0;
        foreach ($ids_clean as $attachment_id) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $rows = $wpdb->delete(
                $table,
                ['attachment_id' => $attachment_id],
                ['%d']
            );
            if (false !== $rows) {
                $deleted += (int) $rows;
            }
        }

        if ( $deleted > 0 ) {
            BBAI_Cache::bump( 'queue' );
        }
        return $deleted;
    }

    /**
     * Bulk enqueue attachments.
     */
    public static function enqueue_many(array $ids, $source = 'bulk') {
        // For regeneration, clear existing queue entries first
        if ($source === 'bulk-regenerate') {
            self::clear_for_attachments($ids);
        }
        
        $count = 0;
        foreach ($ids as $id) {
            if (self::enqueue($id, $source)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Claim a batch of jobs for processing.
     */
    public static function claim_batch($limit = 5) {
        global $wpdb;
        $table = esc_sql( self::table() );
        $limit = max(1, intval($limit));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- concurrency-sensitive
        $candidates = $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM `{$table}` WHERE status = %s ORDER BY id ASC LIMIT %d",
                'pending',
                $limit * 3
            ),
            ARRAY_A
        );

        if (!$candidates) {
            return [];
        }

        $claimed = [];
        foreach ($candidates as $row) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- concurrency-sensitive
            $updated = $wpdb->update(
                $table,
                [
                    'status'     => 'processing',
                    'locked_at'  => current_time('mysql'),
                    'attempts'   => intval($row['attempts']) + 1,
                ],
                [
                    'id'     => intval($row['id']),
                    'status' => 'pending',
                ],
                ['%s', '%s', '%d'],
                ['%d', '%s']
            );

            if ($updated) {
                $row['status']    = 'processing';
                $row['attempts']  = intval($row['attempts']) + 1;
                $row['locked_at'] = current_time('mysql');
                $claimed[] = (object) $row;
                if (count($claimed) >= $limit) {
                    break;
                }
            }
        }

        return $claimed;
    }

    /**
     * Mark job as completed.
     */
    public static function mark_complete($job_id) {
        global $wpdb;
        $table = esc_sql( self::table() );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $table,
            [
                'status'       => 'completed',
                'locked_at'    => null,
                'completed_at' => current_time('mysql'),
                'last_error'   => null,
            ],
            ['id' => intval($job_id)],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );
        BBAI_Cache::bump( 'queue' );
    }

    /**
     * Retry a single job by ID.
     */
    public static function retry_job($job_id) {
        global $wpdb;
        $table = esc_sql( self::table() );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $table,
            [
                'status'     => 'pending',
                'locked_at'  => null,
                'last_error' => null,
            ],
            ['id' => intval($job_id)],
            ['%s', '%s', '%s'],
            ['%d']
        );
        BBAI_Cache::bump( 'queue' );
    }

    /**
     * Mark job for retry.
     */
    public static function mark_retry($job_id, $message = '') {
        global $wpdb;
        $table = esc_sql( self::table() );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $table,
            [
                'status'     => 'pending',
                'locked_at'  => null,
                'last_error' => wp_trim_words(wp_strip_all_tags((string) $message), 120, '…'),
            ],
            ['id' => intval($job_id)],
            ['%s', '%s', '%s'],
            ['%d']
        );
        BBAI_Cache::bump( 'queue' );
    }

    /**
     * Mark job as failed.
     */
    public static function mark_failed($job_id, $message) {
        global $wpdb;
        $table = esc_sql( self::table() );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $table,
            [
                'status'     => 'failed',
                'locked_at'  => null,
                'last_error' => wp_trim_words(wp_strip_all_tags((string) $message), 120, '…'),
            ],
            ['id' => intval($job_id)],
            ['%s', '%s', '%s'],
            ['%d']
        );
        BBAI_Cache::bump( 'queue' );
    }

    /**
     * Retry all failed jobs.
     */
    public static function retry_failed() {
        global $wpdb;
        $table = esc_sql( self::table() );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "UPDATE `{$table}` SET status = %s, locked_at = NULL, last_error = NULL WHERE status = %s",
                'pending',
                'failed'
            )
        );
        BBAI_Cache::bump( 'queue' );
    }

    /**
     * Clear completed jobs (optionally only older than age).
     */
    public static function clear_completed($age_seconds = 0) {
        global $wpdb;
        $table = esc_sql( self::table() );
        if ($age_seconds > 0) {
            $threshold = gmdate('Y-m-d H:i:s', time() - intval($age_seconds));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query(
                $wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    "DELETE FROM `{$table}` WHERE status = %s AND completed_at IS NOT NULL AND completed_at < %s",
                    'completed',
                    $threshold
                )
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query(
                $wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    "DELETE FROM `{$table}` WHERE status = %s",
                    'completed'
                )
            );
        }
        BBAI_Cache::bump( 'queue' );
    }

    /**
     * Clean up pending jobs for attachments that already have alt text.
     * This removes stale jobs that are no longer needed.
     */
    public static function cleanup_redundant_jobs() {
        global $wpdb;
        $table = esc_sql( self::table() );
        // Get all pending jobs
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- operational
        $pending_jobs = $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT id, attachment_id FROM `{$table}` WHERE status = %s",
                'pending'
            ),
            ARRAY_A
        );

        if (empty($pending_jobs)) {
            return 0;
        }

        $cleaned = 0;
        foreach ($pending_jobs as $job) {
            $attachment_id = intval($job['attachment_id']);
            $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);

            // If the image already has alt text, mark this job as completed
            if (!empty($alt_text)) {
                self::mark_complete($job['id']);
                $cleaned++;
            }
        }

        return $cleaned;
    }

    /**
     * Reset stale processing jobs back to pending.
     */
    public static function reset_stale($timeout = 600) {
        global $wpdb;
        $table = esc_sql( self::table() );
        $threshold = gmdate('Y-m-d H:i:s', time() - max(60, intval($timeout)));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "UPDATE `{$table}` SET status = %s, locked_at = NULL WHERE status = %s AND locked_at IS NOT NULL AND locked_at < %s",
                'pending',
                'processing',
                $threshold
            )
        );
        BBAI_Cache::bump( 'queue' );
    }

    /**
     * Get queue statistics.
     */
    public static function get_stats() {
        $cached = BBAI_Cache::get( 'queue', 'stats' );
        if ( false !== $cached && is_array( $cached ) ) {
            return $cached;
        }

        global $wpdb;
        $table = esc_sql( self::table() );
        // Auto-cleanup redundant pending jobs (images that already have alt text)
        // This runs every hour to keep the queue clean
        $last_cleanup = get_transient('bbai_queue_last_cleanup');
        if (false === $last_cleanup) {
            self::cleanup_redundant_jobs();
            set_transient('bbai_queue_last_cleanup', time(), HOUR_IN_SECONDS);
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $counts = $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT status, COUNT(*) as total FROM `{$table}` WHERE %d = %d GROUP BY status",
                1,
                1
            ),
            OBJECT_K
        );
        $pending     = isset($counts['pending']) ? intval($counts['pending']->total) : 0;
        $processing  = isset($counts['processing']) ? intval($counts['processing']->total) : 0;
        $failed      = isset($counts['failed']) ? intval($counts['failed']->total) : 0;
        $completed   = isset($counts['completed']) ? intval($counts['completed']->total) : 0;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $recent_completed = $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT COUNT(*) FROM `{$table}` WHERE status = %s AND completed_at IS NOT NULL AND completed_at > %s",
                'completed',
                gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS)
            )
        );

        $result = [
            'pending'    => $pending,
            'processing' => $processing,
            'failed'     => $failed,
            'completed'  => $completed,
            'completed_recent' => intval($recent_completed),
            'has_jobs'   => ($pending + $processing) > 0,
        ];

        BBAI_Cache::set( 'queue', 'stats', $result, BBAI_Cache::SHORT_TTL );
        return $result;
    }

    /**
     * Get failed jobs with details.
     */
    public static function get_failures() {
        return self::get_recent_failures(10);
    }

    /**
     * Fetch recent queue entries for display.
     */
    public static function get_recent($limit = 20) {
        $limit = max(1, intval($limit));
        $cache_suffix = 'recent_' . $limit;
        $cached = BBAI_Cache::get( 'queue', $cache_suffix );
        if ( false !== $cached ) {
            return $cached;
        }

        global $wpdb;
        $table = esc_sql( self::table() );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM `{$table}` ORDER BY id DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        BBAI_Cache::set( 'queue', $cache_suffix, $result, BBAI_Cache::SHORT_TTL );
        return $result;
    }

    /**
     * Fetch recent failed jobs.
     */
    public static function get_recent_failures($limit = 10) {
        $limit = max(1, intval($limit));
        $cache_suffix = 'failures_' . $limit;
        $cached = BBAI_Cache::get( 'queue', $cache_suffix );
        if ( false !== $cached ) {
            return $cached;
        }

        global $wpdb;
        $table = esc_sql( self::table() );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT id, attachment_id, status, attempts, source, last_error, enqueued_at, locked_at, completed_at FROM `{$table}` WHERE status = %s ORDER BY id DESC LIMIT %d",
                'failed',
                $limit
            ),
            ARRAY_A
        ) ?: [];

        BBAI_Cache::set( 'queue', $cache_suffix, $result, BBAI_Cache::SHORT_TTL );
        return $result;
    }

    /**
     * Delete completed jobs older than given seconds.
     */
    public static function purge_completed($age_seconds = 86400) {
        global $wpdb;
        $table = esc_sql( self::table() );
        $threshold = gmdate('Y-m-d H:i:s', time() - max(300, intval($age_seconds)));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "DELETE FROM `{$table}` WHERE status = %s AND completed_at IS NOT NULL AND completed_at < %s",
                'completed',
                $threshold
            )
        );
        BBAI_Cache::bump( 'queue' );
    }
}
