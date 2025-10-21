<?php
/**
 * Lightweight job queue for AltText AI background processing.
 */

if (!defined('ABSPATH')) {
    exit;
}

class AltText_AI_Queue {
    const TABLE_SLUG = 'alttextai_queue';
    const CRON_HOOK  = 'ai_alt_process_queue';

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
        $table = self::table();
        $charset = $wpdb->get_charset_collate();

        $sql = "
            CREATE TABLE {$table} (
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

        $table = self::table();
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE attachment_id = %d AND status IN ('pending','processing') LIMIT 1",
            $attachment_id
        ));

        if ($exists) {
            return false;
        }

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
            self::schedule_processing();
            return true;
        }

        return false;
    }

    /**
     * Bulk enqueue attachments.
     */
    public static function enqueue_many(array $ids, $source = 'bulk') {
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
        $table = self::table();
        $limit = max(1, intval($limit));

        $candidates = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = 'pending' ORDER BY id ASC LIMIT %d",
                $limit * 3
            ),
            ARRAY_A
        );

        if (!$candidates) {
            return [];
        }

        $claimed = [];
        foreach ($candidates as $row) {
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
        $table = self::table();
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
    }

    /**
     * Retry a single job by ID.
     */
    public static function retry_job($job_id) {
        global $wpdb;
        $table = self::table();
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
    }

    /**
     * Mark job for retry.
     */
    public static function mark_retry($job_id, $message = '') {
        global $wpdb;
        $table = self::table();
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
    }

    /**
     * Mark job as failed.
     */
    public static function mark_failed($job_id, $message) {
        global $wpdb;
        $table = self::table();
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
    }

    /**
     * Retry all failed jobs.
     */
    public static function retry_failed() {
        global $wpdb;
        $table = self::table();
        $wpdb->query(
            "UPDATE {$table}
             SET status = 'pending', locked_at = NULL
             WHERE status = 'failed'"
        );
    }

    /**
     * Clear completed jobs (optionally only older than age).
     */
    public static function clear_completed($age_seconds = 0) {
        global $wpdb;
        $table = self::table();
        $sql = "DELETE FROM {$table} WHERE status = 'completed'";
        if ($age_seconds > 0) {
            $threshold = gmdate('Y-m-d H:i:s', time() - intval($age_seconds));
            $wpdb->query($wpdb->prepare("{$sql} AND completed_at IS NOT NULL AND completed_at < %s", $threshold));
        } else {
            $wpdb->query($sql);
        }
    }

    /**
     * Reset stale processing jobs back to pending.
     */
    public static function reset_stale($timeout = 600) {
        global $wpdb;
        $table = self::table();
        $threshold = gmdate('Y-m-d H:i:s', time() - max(60, intval($timeout)));

        $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET status = 'pending', locked_at = NULL
             WHERE status = 'processing' AND locked_at IS NOT NULL AND locked_at < %s",
            $threshold
        ));
    }

    /**
     * Get queue statistics.
     */
    public static function get_stats() {
        global $wpdb;
        $table = self::table();

        $counts = $wpdb->get_results("SELECT status, COUNT(*) as total FROM {$table} GROUP BY status", OBJECT_K);
        $pending     = isset($counts['pending']) ? intval($counts['pending']->total) : 0;
        $processing  = isset($counts['processing']) ? intval($counts['processing']->total) : 0;
        $failed      = isset($counts['failed']) ? intval($counts['failed']->total) : 0;
        $completed   = isset($counts['completed']) ? intval($counts['completed']->total) : 0;

        $recent_completed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE status = 'completed' AND completed_at IS NOT NULL AND completed_at > %s",
            gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS)
        ));

        return [
            'pending'    => $pending,
            'processing' => $processing,
            'failed'     => $failed,
            'completed'  => $completed,
            'completed_recent' => intval($recent_completed),
            'has_jobs'   => ($pending + $processing) > 0,
        ];
    }

    /**
     * Fetch recent queue entries for display.
     */
    public static function get_recent($limit = 20) {
        global $wpdb;
        $table = self::table();
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d",
                max(1, intval($limit))
            ),
            ARRAY_A
        );
    }

    /**
     * Fetch recent failed jobs.
     */
    public static function get_recent_failures($limit = 10) {
        global $wpdb;
        $table = self::table();
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = 'failed' ORDER BY id DESC LIMIT %d",
                max(1, intval($limit))
            ),
            ARRAY_A
        );
    }

    /**
     * Delete completed jobs older than given seconds.
     */
    public static function purge_completed($age_seconds = 86400) {
        global $wpdb;
        $table = self::table();
        $threshold = gmdate('Y-m-d H:i:s', time() - max(300, intval($age_seconds)));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE status = 'completed' AND completed_at IS NOT NULL AND completed_at < %s",
            $threshold
        ));
    }
}
