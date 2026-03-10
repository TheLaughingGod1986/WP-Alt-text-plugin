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

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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

        BBAI_Cache::bump( 'logs' );
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

        // Check cache for this specific filter combination.
        $cache_suffix = md5( wp_json_encode( $args ) );
        $cached = BBAI_Cache::get( 'logs', $cache_suffix );
        if ( false !== $cached && is_array( $cached ) ) {
            return $cached;
        }

        $level = self::normalize_filter_level( $args['level'] ?? '' );

        $like = '';
        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
        }

        $date_from = self::normalize_filter_date( isset( $args['date_from'] ) ? sanitize_text_field( $args['date_from'] ) : '' );
        $date_to   = self::normalize_filter_date( isset( $args['date_to'] ) ? sanitize_text_field( $args['date_to'] ) : '' );
        $date      = '';
        if ( ! $date_from && ! $date_to && ! empty( $args['date'] ) ) {
            $date = self::normalize_filter_date( sanitize_text_field( $args['date'] ) );
        }

        $table = self::table();

        $exact_date_enabled = '' !== $date ? 1 : 0;
        $range_date_enabled = '' === $date ? 1 : 0;
        $exact_date_value   = '' !== $date ? $date : '1000-01-01';
        $date_from_value    = '' !== $date_from ? $date_from : '1000-01-01';
        $date_to_value      = '' !== $date_to ? $date_to : '9999-12-31';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM %i
                 WHERE ( %s = '' OR level = %s )
                   AND ( %s = '' OR message LIKE %s OR context LIKE %s )
                   AND (
                       ( %d = 1 AND DATE(created_at) = %s )
                       OR
                       ( %d = 1 AND ( %s = '' OR DATE(created_at) >= %s ) AND ( %s = '' OR DATE(created_at) <= %s ) )
                   )
                 ORDER BY created_at DESC
                 LIMIT %d OFFSET %d",
                $table,
                $level,
                $level,
                $like,
                $like,
                $like,
                $exact_date_enabled,
                $exact_date_value,
                $range_date_enabled,
                $date_from_value,
                $date_from_value,
                $date_to_value,
                $date_to_value,
                $per_page,
                $offset
            ),
            ARRAY_A
        );

        $total_items = intval(
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM %i
                     WHERE ( %s = '' OR level = %s )
                       AND ( %s = '' OR message LIKE %s OR context LIKE %s )
                       AND (
                           ( %d = 1 AND DATE(created_at) = %s )
                           OR
                           ( %d = 1 AND ( %s = '' OR DATE(created_at) >= %s ) AND ( %s = '' OR DATE(created_at) <= %s ) )
                       )",
                    $table,
                    $level,
                    $level,
                    $like,
                    $like,
                    $like,
                    $exact_date_enabled,
                    $exact_date_value,
                    $range_date_enabled,
                    $date_from_value,
                    $date_from_value,
                    $date_to_value,
                    $date_to_value
                )
            )
        );
        $total_pages = $per_page > 0 ? ceil($total_items / $per_page) : 1;

        $logs = array_map([self::class, 'format_log'], $rows);

        $result = [
            'logs' => $logs,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total_pages' => max(1, $total_pages),
                'total_items' => $total_items,
            ],
            'stats' => self::get_stats(),
        ];

        BBAI_Cache::set( 'logs', $cache_suffix, $result, BBAI_Cache::DEFAULT_TTL );
        return $result;
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

        $cached = BBAI_Cache::get( 'logs', 'stats' );
        if ( false !== $cached && is_array( $cached ) ) {
            return $cached;
        }

	        global $wpdb;
		        $table = esc_sql( self::table() );
		        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		        $totals = $wpdb->get_results(
		            $wpdb->prepare(
		                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		                "SELECT level, COUNT(*) as total FROM `{$table}` WHERE %d = %d GROUP BY level",
		                1,
		                1
		            ),
		            OBJECT_K
		        );

	        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	        $total_logs = intval(
	            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	            $wpdb->get_var(
	                $wpdb->prepare(
	                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	                    "SELECT COUNT(*) FROM `{$table}` WHERE %d = %d",
	                    1,
	                    1
	                )
	            )
        );

	        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	        $last_event = $wpdb->get_var(
	            $wpdb->prepare(
	                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
	                "SELECT created_at FROM `{$table}` WHERE %d = %d ORDER BY created_at DESC LIMIT 1",
	                1,
	                1
	            )
	        );

	        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	        $last_api_call = $wpdb->get_var(
	            $wpdb->prepare(
	                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	                "SELECT created_at FROM `{$table}` WHERE source = %s ORDER BY created_at DESC LIMIT 1",
	                'api'
	            )
	        );

        $result = [
            'total'    => $total_logs,
            'warnings' => isset($totals['warning']) ? intval($totals['warning']->total) : 0,
            'errors'   => isset($totals['error']) ? intval($totals['error']->total) : 0,
            'last_event' => $last_event ? mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $last_event) : null,
            'last_api' => $last_api_call ? mysql2date('g:i A', $last_api_call) : null,
        ];

        BBAI_Cache::set( 'logs', 'stats', $result, BBAI_Cache::DEFAULT_TTL );
        return $result;
    }

    /**
     * Return the most recent warning/error entries for support triage.
     *
     * @param int $limit Maximum number of rows.
     * @return array<int, array<string, mixed>>
     */
    public static function get_recent_errors($limit = 5) {
        if (!self::table_exists()) {
            return [];
        }

        $limit = max(1, min(20, absint($limit)));
        $cache_suffix = 'recent_errors_' . $limit;
        $cached = BBAI_Cache::get('logs', $cache_suffix);
        if (false !== $cached && is_array($cached)) {
            return $cached;
        }

        global $wpdb;
        $table = esc_sql(self::table());
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM `{$table}` WHERE level IN (%s, %s) ORDER BY created_at DESC LIMIT %d",
                'error',
                'warning',
                $limit
            ),
            ARRAY_A
        );

        $result = array_map([self::class, 'format_log'], is_array($rows) ? $rows : []);
        BBAI_Cache::set('logs', $cache_suffix, $result, BBAI_Cache::DEFAULT_TTL);
        return $result;
    }

    /**
     * Return high-level API connection signals for the Debug page status card.
     *
     * @return array<string, mixed>
     */
    public static function get_api_service_status() {
        if (!self::table_exists()) {
            return self::default_api_service_status();
        }

        $cache_suffix = 'api_service_status';
        $cached = BBAI_Cache::get('logs', $cache_suffix);
        if (false !== $cached && is_array($cached)) {
            return $cached;
        }

        global $wpdb;
        $table = esc_sql(self::table());

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $latest_api = $wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT level, message, created_at FROM `{$table}` WHERE source = %s ORDER BY created_at DESC LIMIT 1",
                'api'
            ),
            ARRAY_A
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $latest_api_error = $wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT message, created_at FROM `{$table}` WHERE source = %s AND level = %s ORDER BY created_at DESC LIMIT 1",
                'api',
                'error'
            ),
            ARRAY_A
        );

        $connection_status = 'failed';
        if (is_array($latest_api) && !empty($latest_api)) {
            $level = strtolower((string) ($latest_api['level'] ?? ''));
            $message = strtolower((string) ($latest_api['message'] ?? ''));
            $looks_failed = $level === 'error'
                || preg_match('/failed|error|timeout|unreachable|refused|invalid|not found|denied|cannot/i', $message);
            $connection_status = $looks_failed ? 'failed' : 'connected';
        }

        $last_request_ts = is_array($latest_api) && !empty($latest_api['created_at']) ? (string) $latest_api['created_at'] : null;
        $last_error_ts = is_array($latest_api_error) && !empty($latest_api_error['created_at']) ? (string) $latest_api_error['created_at'] : null;
        $average_response_time = self::resolve_average_response_time_ms(100);

        $result = [
            'connection_status' => $connection_status,
            'last_api_request' => $last_request_ts ? mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $last_request_ts) : null,
            'last_api_request_timestamp' => $last_request_ts ? sanitize_text_field($last_request_ts) : null,
            'average_response_time_ms' => $average_response_time,
            'last_api_error' => $last_error_ts ? mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $last_error_ts) : null,
            'last_api_error_timestamp' => $last_error_ts ? sanitize_text_field($last_error_ts) : null,
            'last_api_error_message' => is_array($latest_api_error) && !empty($latest_api_error['message'])
                ? sanitize_text_field((string) $latest_api_error['message'])
                : null,
        ];

        BBAI_Cache::set('logs', $cache_suffix, $result, BBAI_Cache::DEFAULT_TTL);
        return $result;
    }

    /**
     * Fallback API status payload for sites with no logs table/data.
     *
     * @return array<string, mixed>
     */
    private static function default_api_service_status() {
        return [
            'connection_status' => 'failed',
            'last_api_request' => null,
            'last_api_request_timestamp' => null,
            'average_response_time_ms' => null,
            'last_api_error' => null,
            'last_api_error_timestamp' => null,
            'last_api_error_message' => null,
        ];
    }

    /**
     * Calculate average request time in milliseconds using recent log context payloads.
     *
     * @param int $sample_size Number of recent rows to inspect.
     * @return int|null
     */
    private static function resolve_average_response_time_ms($sample_size = 100) {
        if (!self::table_exists()) {
            return null;
        }

        $sample_size = max(10, min(200, absint($sample_size)));
        global $wpdb;
        $table = esc_sql(self::table());

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT context FROM `{$table}` WHERE context IS NOT NULL AND context <> '' ORDER BY created_at DESC LIMIT %d",
                $sample_size
            ),
            ARRAY_A
        );

        if (!is_array($rows) || empty($rows)) {
            return null;
        }

        $timings = [];
        foreach ($rows as $row) {
            $context = self::decode_context_data($row['context'] ?? '');
            if (!is_array($context) || empty($context)) {
                continue;
            }

            $timing = self::extract_context_timing_ms($context);
            if ($timing !== null) {
                $timings[] = $timing;
            }
        }

        if (empty($timings)) {
            return null;
        }

        return (int) round(array_sum($timings) / count($timings));
    }

    /**
     * Extract one timing candidate (in milliseconds) from a context payload.
     *
     * @param array<string, mixed> $context Context payload.
     * @return float|null
     */
    private static function extract_context_timing_ms($context) {
        $candidates = [];
        $timing_keys = [
            'generation_time_ms',
            'response_time_ms',
            'duration_ms',
            'latency_ms',
            'elapsed_ms',
            'response_time',
            'duration',
        ];

        foreach ($timing_keys as $key) {
            if (isset($context[$key]) && is_numeric($context[$key])) {
                $candidates[] = (float) $context[$key];
            }
        }

        if (isset($context['meta']) && is_array($context['meta'])) {
            foreach ($timing_keys as $key) {
                if (isset($context['meta'][$key]) && is_numeric($context['meta'][$key])) {
                    $candidates[] = (float) $context['meta'][$key];
                }
            }
        }

        if (isset($context['timing']) && is_array($context['timing'])) {
            foreach (['ms', 'duration_ms', 'response_time_ms', 'elapsed_ms'] as $key) {
                if (isset($context['timing'][$key]) && is_numeric($context['timing'][$key])) {
                    $candidates[] = (float) $context['timing'][$key];
                }
            }
        }

        foreach ($candidates as $value) {
            if ($value > 0 && $value < 600000) {
                return $value;
            }
        }

        return null;
    }

    public static function clear_logs() {
        if (!self::table_exists()) {
            return;
        }

	        global $wpdb;
		        $table = esc_sql( self::table() );
		        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		        $wpdb->query(
		            $wpdb->prepare(
		                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		                "DELETE FROM `{$table}` WHERE %d = %d",
		                1,
		                1
		            )
		        );

        BBAI_Cache::bump( 'logs' );
    }

    public static function delete_older_than($days = 30) {
        if (!self::table_exists()) {
            return;
        }

	        global $wpdb;
		        $table = esc_sql( self::table() );
		        $threshold = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
		        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		        $wpdb->query(
		            $wpdb->prepare(
		                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		                "DELETE FROM `{$table}` WHERE created_at < %s",
		                $threshold
		            )
		        );

        BBAI_Cache::bump( 'logs' );
	    }

    private static function allowed_levels() {
        return ['debug', 'info', 'warning', 'error'];
    }

    private static function normalize_level($level) {
        $level = strtolower($level ?: 'info');
        return in_array($level, self::allowed_levels(), true) ? $level : 'info';
    }

    /**
     * Normalize level filter values to allowed DB values or empty string.
     *
     * @param mixed $level Level filter input.
     * @return string
     */
    private static function normalize_filter_level( $level ) {
        if ( ! is_scalar( $level ) ) {
            return '';
        }

        $normalized = sanitize_key( (string) $level );
        if ( '' === $normalized || 'all' === $normalized ) {
            return '';
        }

        if ( 'warn' === $normalized ) {
            $normalized = 'warning';
        } elseif ( 'err' === $normalized || 'fatal' === $normalized ) {
            $normalized = 'error';
        }

        return in_array( $normalized, self::allowed_levels(), true ) ? $normalized : '';
    }

    /**
     * Normalize date filter values to YYYY-MM-DD or empty string.
     *
     * @param mixed $date Date input.
     * @return string
     */
    private static function normalize_filter_date( $date ) {
        $date = is_string( $date ) ? trim( $date ) : '';
        if ( '' === $date ) {
            return '';
        }

        $parsed = \DateTime::createFromFormat( 'Y-m-d', $date );
        if ( ! $parsed || $parsed->format( 'Y-m-d' ) !== $date ) {
            return '';
        }

        return $date;
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
            } elseif ($key_lower === 'error' && $value === null) {
                // Omit error: null - backend often returns this on success, which gets misdisplayed as an error
                continue;
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
        $context = self::decode_context_data($row['context'] ?? '');

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

    /**
     * Decode stored JSON context into an array.
     *
     * @param mixed $context_value Raw DB context value.
     * @return array<string, mixed>
     */
    private static function decode_context_data($context_value) {
        if (!is_string($context_value) || $context_value === '') {
            return [];
        }

        if (!function_exists('bbai_json_decode_array') && defined('BEEPBEEP_AI_PLUGIN_DIR')) {
            require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-json.php';
        }

        $decoded_context = function_exists('bbai_json_decode_array') ? bbai_json_decode_array($context_value) : null;
        return is_array($decoded_context) ? $decoded_context : [];
    }

    public static function table_exists() {
        if (self::$table_verified) {
            return true;
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $exists = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', self::table() )
        );
        self::$table_verified = !empty($exists);
        return self::$table_verified;
    }
}
