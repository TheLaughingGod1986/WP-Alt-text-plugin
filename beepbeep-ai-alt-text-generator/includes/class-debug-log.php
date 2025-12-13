<?php
/**
 * Debug logging utility for AltText AI.
 */

if (!defined('ABSPATH')) { exit; }

class BbAI_Debug_Log {
    const TABLE_SLUG = 'bbai_logs';
    const MAX_MESSAGE_LENGTH = 2000;
    const MAX_CONTEXT_LENGTH = 4000;

    private static $table_verified = false;

    /**
     * Return fully qualified table name.
     */
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SLUG;
    }

    /**
     * Create logs table if needed.
     */
    public static function create_table() {
        global $wpdb;
        $table = self::table();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            level VARCHAR(20) NOT NULL DEFAULT 'info',
            message TEXT NOT NULL,
            context LONGTEXT NULL,
            source VARCHAR(50) NOT NULL DEFAULT 'core',
            meta VARCHAR(255) DEFAULT '',
            user_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY level_created (level, created_at),
            KEY created_at (created_at),
            KEY user_id (user_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        self::$table_verified = true;
    }

    /**
     * Persist a log entry.
     */
    public static function log($level, $message, $context = [], $source = 'core', $meta = '', $user_id = null) {
        if (!self::table_exists()) {
            return;
        }

        $level = self::normalize_level($level);
        $message = wp_strip_all_tags((string) $message);
        if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            $message = mb_substr($message, 0, self::MAX_MESSAGE_LENGTH) . '…';
        }

        $context_string = '';
        if (!empty($context) && is_array($context)) {
            $context_string = wp_json_encode(self::sanitize_context($context));
            if ($context_string && strlen($context_string) > self::MAX_CONTEXT_LENGTH) {
                $context_string = substr($context_string, 0, self::MAX_CONTEXT_LENGTH) . '…';
            }
        }

        $source = sanitize_key($source ?: 'core');
        $meta = sanitize_text_field($meta);

        // Get current user ID if not provided
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }
        $user_id = $user_id > 0 ? intval($user_id) : null;

        global $wpdb;
        $wpdb->insert(
            self::table(),
            [
                'level'      => $level,
                'message'    => $message,
                'context'    => $context_string,
                'source'     => $source,
                'meta'       => $meta,
                'user_id'    => $user_id,
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%s']
        );
    }

    /**
     * Fetch logs with pagination and filters.
     */
    public static function get_logs($args = []) {
        global $wpdb;
        $defaults = [
            'level'    => '',
            'search'   => '',
            'date'     => '',
            'per_page' => 10,
            'page'     => 1,
        ];
        $args = wp_parse_args($args, $defaults);
        if (!self::table_exists()) {
            return [
                'logs' => [],
                'pagination' => [
                    'page' => max(1, intval($args['page'])),
                    'per_page' => max(1, intval($args['per_page'])),
                    'total_pages' => 1,
                    'total_items' => 0,
                ],
                'stats' => [
                    'total' => 0,
                    'warnings' => 0,
                    'errors' => 0,
                    'last_api' => null,
                ],
            ];
        }
        $per_page = max(1, min(100, intval($args['per_page'])));
        $page = max(1, intval($args['page']));
        $offset = ($page - 1) * $per_page;

        $where = [];
        $params = [];

        if (!empty($args['level']) && in_array($args['level'], self::allowed_levels(), true)) {
            $where[] = 'level = %s';
            $params[] = $args['level'];
        }

        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(message LIKE %s OR context LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($args['date'])) {
            $date = sanitize_text_field($args['date']);
            $where[] = 'DATE(created_at) = %s';
            $params[] = $date;
        }

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $table = self::table();
        // Table names are sanitized in table() method, safe to use directly with esc_sql
        $table_escaped = esc_sql($table);

        $query = "SELECT * FROM {$table_escaped} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        array_push($params, $per_page, $offset);
        $prepared = $wpdb->prepare($query, $params);
        $rows = $wpdb->get_results($prepared, ARRAY_A);

        $count_query = "SELECT COUNT(*) FROM {$table_escaped} {$where_sql}";
        $count_prepared = $where ? $wpdb->prepare($count_query, array_slice($params, 0, count($params) - 2)) : $count_query;
        $total_items = intval($wpdb->get_var($count_prepared));
        $total_pages = $per_page > 0 ? ceil($total_items / $per_page) : 1;

        $logs = array_map([self::class, 'format_log'], $rows);

        return [
            'logs' => $logs,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total_pages' => max(1, $total_pages),
                'total_items' => $total_items,
            ],
            'stats' => self::get_stats(),
        ];
    }

    /**
     * Return aggregate stats for dashboard cards.
     */
    public static function get_stats() {
        if (!self::table_exists()) {
            return [
                'total' => 0,
                'warnings' => 0,
                'errors' => 0,
                'last_api' => null,
            ];
        }

        global $wpdb;
        $table = self::table();
        // Table names are sanitized in table() method, safe to use directly
        $table_escaped = esc_sql($table);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated and escaped
        $totals = $wpdb->get_results("SELECT level, COUNT(*) as total FROM `{$table_escaped}` GROUP BY level", OBJECT_K);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated and escaped
        $total_logs = intval($wpdb->get_var("SELECT COUNT(*) FROM `{$table_escaped}`"));
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated and escaped
        $last_api_call = $wpdb->get_var($wpdb->prepare("SELECT created_at FROM `{$table_escaped}` WHERE source = %s ORDER BY created_at DESC LIMIT 1", 'api'));

        return [
            'total'    => $total_logs,
            'warnings' => isset($totals['warning']) ? intval($totals['warning']->total) : 0,
            'errors'   => isset($totals['error']) ? intval($totals['error']->total) : 0,
            'last_api' => $last_api_call ? mysql2date('g:i A', $last_api_call) : null,
        ];
    }

    public static function clear_logs() {
        if (!self::table_exists()) {
            return;
        }

        global $wpdb;
        $table = self::table();
        // Table names are sanitized in table() method, safe to use directly with esc_sql
        $table_escaped = esc_sql($table);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated and escaped
        $wpdb->query("DELETE FROM `{$table_escaped}`");
    }

    public static function delete_older_than($days = 30) {
        if (!self::table_exists()) {
            return;
        }

        global $wpdb;
        $table = self::table();
        // Table names are sanitized in table() method, safe to use directly with esc_sql
        $table_escaped = esc_sql($table);
        $threshold = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated and escaped
        $wpdb->query($wpdb->prepare("DELETE FROM `{$table_escaped}` WHERE created_at < %s", $threshold));
    }

    private static function allowed_levels() {
        return ['debug', 'info', 'warning', 'error'];
    }

    private static function normalize_level($level) {
        $level = strtolower($level ?: 'info');
        return in_array($level, self::allowed_levels(), true) ? $level : 'info';
    }

    private static function sanitize_context($context) {
        $clean = [];
        foreach ($context as $key => $value) {
            $key = sanitize_text_field((string) $key);
            if (is_scalar($value)) {
                $clean[$key] = is_bool($value) ? $value : sanitize_text_field((string) $value);
            } elseif (is_array($value)) {
                $clean[$key] = self::sanitize_context($value);
            } elseif (is_object($value)) {
                $clean[$key] = self::sanitize_context((array) $value);
            }
        }
        return $clean;
    }

    private static function format_log($row) {
        $user_id = isset($row['user_id']) && $row['user_id'] > 0 ? intval($row['user_id']) : null;
        $user_info = null;
        if ($user_id) {
            $user = get_userdata($user_id);
            if ($user) {
                $user_info = [
                    'id' => $user_id,
                    'name' => $user->display_name ?: $user->user_login,
                    'email' => $user->user_email,
                ];
            }
        }
        
        return [
            'id'        => intval($row['id']),
            'level'     => $row['level'],
            'message'   => $row['message'],
            'source'    => $row['source'],
            'meta'      => $row['meta'],
            'user_id'   => $user_id,
            'user'      => $user_info,
            'created_at'=> mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $row['created_at']),
            'timestamp' => $row['created_at'],
            'context'   => $row['context'] ? json_decode($row['context'], true) : [],
        ];
    }

    public static function table_exists() {
        if (self::$table_verified) {
            return true;
        }

        global $wpdb;
        $table = self::table();
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        self::$table_verified = !empty($exists);
        return self::$table_verified;
    }
}
