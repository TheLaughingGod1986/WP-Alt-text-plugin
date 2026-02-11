<?php
/**
 * Debug logging utility for AltText AI.
 */

namespace BeepBeepAI\AltTextGenerator;

if (!defined('ABSPATH')) { exit; }

class Debug_Log {
    const TABLE_SLUG = 'bbai_logs';
    const MAX_MESSAGE_LENGTH = 2000;
    const MAX_CONTEXT_LENGTH = 4000;
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter -- SQL identifiers are controlled by plugin schema; runtime values are prepared.

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
        $table = esc_sql( self::table() );
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE `{$table}` (
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
        $meta = is_string($meta) && $meta !== '' ? sanitize_text_field($meta) : '';
        
        // Get current user ID if not provided
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        $user_id = $user_id > 0 ? intval($user_id) : null;

        global $wpdb;
        $table = esc_sql( self::table() );
        $wpdb->insert(
            $table,
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
            'date_from' => '',
            'date_to'   => '',
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
                    'last_event' => null,
                    'last_api' => null,
                ],
            ];
        }
        $per_page = max(1, min(100, intval($args['per_page'])));
        $page = max(1, intval($args['page']));
        $offset = ($page - 1) * $per_page;

        $level = '';
        if (!empty($args['level']) && in_array($args['level'], self::allowed_levels(), true)) {
            $level = $args['level'];
        }

        $like = '';
        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
        }

        $date_from = !empty($args['date_from']) ? sanitize_text_field($args['date_from']) : '';
        $date_to = !empty($args['date_to']) ? sanitize_text_field($args['date_to']) : '';
        $date = '';
        if (!$date_from && !$date_to && !empty($args['date'])) {
            $date = sanitize_text_field($args['date']);
        }
        $has_exact_date = $date !== '' ? 1 : 0;

	        $query_params = [
	            $level,
	            '',
	            $level,
            $like,
            '',
            $like,
            $like,
            $has_exact_date,
            1,
            $date,
            $has_exact_date,
            0,
            $date_from,
            '',
            $date_from,
            $date_to,
            '',
            $date_to,
	            $per_page,
	            $offset,
	        ];
	        $rows = $wpdb->get_results(
	            $wpdb->prepare(
	                'SELECT * FROM `' . self::table() . '` WHERE (%s = %s OR level = %s) AND (%s = %s OR message LIKE %s OR context LIKE %s) AND ((%d = %d AND DATE(created_at) = %s) OR (%d = %d AND (%s = %s OR DATE(created_at) >= %s) AND (%s = %s OR DATE(created_at) <= %s))) ORDER BY created_at DESC LIMIT %d OFFSET %d',
	                $query_params
	            ),
	            ARRAY_A
	        );

	        $total_items = intval(
	            $wpdb->get_var(
	                $wpdb->prepare(
	                    'SELECT COUNT(*) FROM `' . self::table() . '` WHERE (%s = %s OR level = %s) AND (%s = %s OR message LIKE %s OR context LIKE %s) AND ((%d = %d AND DATE(created_at) = %s) OR (%d = %d AND (%s = %s OR DATE(created_at) >= %s) AND (%s = %s OR DATE(created_at) <= %s)))',
	                    array_slice( $query_params, 0, 18 )
	                )
	            )
	        );
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
                'last_event' => null,
                'last_api' => null,
            ];
        }

        global $wpdb;
	        $totals = $wpdb->get_results(
	            $wpdb->prepare(
	                'SELECT level, COUNT(*) as total FROM `' . self::table() . '` WHERE %d = %d GROUP BY level',
	                1,
	                1
	            ),
            OBJECT_K
        );

	        $total_logs = intval(
	            $wpdb->get_var(
	                $wpdb->prepare(
	                    'SELECT COUNT(*) FROM `' . self::table() . '` WHERE %d = %d',
	                    1,
	                    1
	                )
	            )
        );

	        $last_event = $wpdb->get_var(
	            $wpdb->prepare(
	                'SELECT created_at FROM `' . self::table() . '` WHERE %d = %d ORDER BY created_at DESC LIMIT 1',
	                1,
	                1
	            )
	        );

	        $last_api_call = $wpdb->get_var(
	            $wpdb->prepare(
	                'SELECT created_at FROM `' . self::table() . '` WHERE source = %s ORDER BY created_at DESC LIMIT 1',
	                'api'
	            )
	        );

        return [
            'total'    => $total_logs,
            'warnings' => isset($totals['warning']) ? intval($totals['warning']->total) : 0,
            'errors'   => isset($totals['error']) ? intval($totals['error']->total) : 0,
            'last_event' => $last_event ? mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $last_event) : null,
            'last_api' => $last_api_call ? mysql2date('g:i A', $last_api_call) : null,
        ];
    }

    public static function clear_logs() {
        if (!self::table_exists()) {
            return;
        }

	        global $wpdb;
	        $wpdb->query(
	            $wpdb->prepare(
	                'DELETE FROM `' . self::table() . '` WHERE %d = %d',
	                1,
	                1
	            )
	        );
    }

    public static function delete_older_than($days = 30) {
        if (!self::table_exists()) {
            return;
        }

	        global $wpdb;
	        $threshold = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
	        $wpdb->query(
	            $wpdb->prepare(
	                'DELETE FROM `' . self::table() . '` WHERE created_at < %s',
	                $threshold
	            )
	        );
	    }

    private static function allowed_levels() {
        return ['debug', 'info', 'warning', 'error'];
    }

    private static function normalize_level($level) {
        $level = strtolower($level ?: 'info');
        return in_array($level, self::allowed_levels(), true) ? $level : 'info';
    }

    private static function sanitize_context($context) {
        // List of sensitive keys to redact
        $sensitive_keys = ['password', 'pass', 'pwd', 'secret', 'token', 'api_key', 'apikey', 'auth', 'authorization', 'jwt', 'bearer'];

        $clean = [];
        foreach ($context as $key => $value) {
            $key = sanitize_text_field((string) $key);
            $key_lower = strtolower($key);

            // Redact sensitive data
            $is_sensitive = false;
            foreach ($sensitive_keys as $sensitive) {
                if (strpos($key_lower, $sensitive) !== false) {
                    $is_sensitive = true;
                    break;
                }
            }

            if ($is_sensitive) {
                $clean[$key] = '[REDACTED]';
            } elseif (is_scalar($value)) {
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
        
        // Safely decode context JSON with sanitization.
        $context = [];
        if ( ! empty( $row['context'] ) ) {
            if ( ! function_exists( 'bbai_json_decode_array' ) && defined( 'BEEPBEEP_AI_PLUGIN_DIR' ) ) {
                require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-json.php';
            }
            $decoded_context = function_exists( 'bbai_json_decode_array' ) ? bbai_json_decode_array( $row['context'] ) : null;
            $context = is_array( $decoded_context ) ? $decoded_context : [];
        }

        return [
            'id'        => intval($row['id']),
            'level'     => sanitize_key( $row['level'] ),
            'message'   => sanitize_text_field( $row['message'] ),
            'source'    => sanitize_text_field( $row['source'] ),
            'meta'      => sanitize_text_field( $row['meta'] ),
            'user_id'   => $user_id,
            'user'      => $user_info,
            'created_at'=> mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $row['created_at']),
            'timestamp' => sanitize_text_field( $row['created_at'] ),
            'context'   => $context,
        ];
    }

    public static function table_exists() {
        if (self::$table_verified) {
            return true;
        }

        global $wpdb;
        $exists = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', self::table() )
        );
        self::$table_verified = !empty($exists);
        return self::$table_verified;
    }
    // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter
}
