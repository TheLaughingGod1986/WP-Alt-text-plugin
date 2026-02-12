<?php
/**
 * Credit Usage Logger for BeepBeep AI
 * Tracks per-user credit consumption and maintains detailed audit trail
 */

namespace BeepBeepAI\AltTextGenerator;

if (!defined('ABSPATH')) {
	exit;
}

class Credit_Usage_Logger {
	const TABLE_SLUG = 'bbai_credit_usage';
	private static $table_verified = false;

	/**
	 * Get the full table name with prefix.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SLUG;
	}

	/**
	 * Create the credit usage table if it doesn't exist.
	 */
	public static function create_table() {
		if (self::$table_verified) {
			return;
		}

		global $wpdb;
		$table_name_safe = esc_sql( self::table() );
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS `{$table_name_safe}` (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			attachment_id BIGINT(20) UNSIGNED NOT NULL,
			credits_used INT(11) UNSIGNED NOT NULL DEFAULT 0,
			token_cost DECIMAL(10,6) UNSIGNED DEFAULT NULL,
			model VARCHAR(50) NOT NULL DEFAULT '',
			source VARCHAR(50) NOT NULL DEFAULT 'manual',
			generated_at DATETIME NOT NULL,
			ip_address VARCHAR(45) DEFAULT NULL,
			user_agent_hash VARCHAR(64) DEFAULT NULL,
			deleted_user_original_id BIGINT(20) UNSIGNED DEFAULT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY attachment_id (attachment_id),
			KEY generated_at (generated_at),
			KEY source (source),
			KEY user_generated (user_id, generated_at),
			KEY recent_usage (generated_at DESC, user_id),
			KEY source_breakdown (source, generated_at),
			KEY image_history (attachment_id, generated_at DESC)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);
		self::$table_verified = true;
	}

	/**
	 * Check if the credit usage table exists.
	 *
	 * @return bool
	 */
	public static function table_exists() {
		global $wpdb;
		$exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', self::table() )
		);
		return $exists === self::table();
	}

	/**
	 * Log credit usage for a generation operation.
	 *
	 * @param int    $attachment_id The attachment ID that was processed.
	 * @param int    $user_id The WordPress user ID (0 for anonymous/system).
	 * @param int    $credits_used Number of credits/tokens consumed.
	 * @param float  $token_cost Optional. Actual API cost in dollars.
	 * @param string $model Optional. AI model used (e.g., 'gpt-4o').
	 * @param string $source Optional. Source of generation ('manual', 'auto', 'bulk', 'inline', 'queue').
	 * @return int|false The ID of the inserted record, or false on failure.
	 */
	public static function log_usage($attachment_id, $user_id = 0, $credits_used = 1, $token_cost = null, $model = '', $source = 'manual') {
		if (!self::table_exists()) {
			self::create_table();
		}

		global $wpdb;
		$table_name_safe = esc_sql( self::table() );

		// Sanitize inputs
		$attachment_id = absint($attachment_id);
		$user_id = absint($user_id);
		$credits_used = absint($credits_used);
		$token_cost = $token_cost !== null ? floatval($token_cost) : null;
		$model = sanitize_text_field($model);
		$source = sanitize_key($source);

		// Get IP address (respecting proxies)
		$ip_address = self::get_client_ip();
		if ($ip_address) {
			$ip_address = sanitize_text_field($ip_address);
		}

		// Hash user agent for privacy
		$user_agent_raw  = wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' );
		$user_agent      = is_string( $user_agent_raw ) ? sanitize_text_field( $user_agent_raw ) : '';
		$user_agent_hash = ! empty( $user_agent ) ? hash( 'sha256', $user_agent ) : null;

		$result = $wpdb->insert(
			$table_name_safe,
			[
				'user_id'       => $user_id,
				'attachment_id' => $attachment_id,
				'credits_used'  => $credits_used,
				'token_cost'    => $token_cost,
				'model'         => $model,
				'source'        => $source,
				'generated_at'  => current_time('mysql'),
				'ip_address'    => $ip_address,
				'user_agent_hash' => $user_agent_hash,
			],
			['%d', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s']
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get client IP address (respecting proxies).
	 *
	 * @return string|false
	 */
	private static function get_client_ip() {
		$ip_keys = [
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_REAL_IP',        // Nginx proxy
			'HTTP_X_FORWARDED_FOR',  // Standard proxy
			'REMOTE_ADDR',           // Direct connection
		];

		foreach ($ip_keys as $key) {
			$server_value = $_SERVER[$key] ?? '';
			if (!is_string($server_value) || $server_value === '') {
				continue;
			}
			$ip = sanitize_text_field(wp_unslash($server_value));
			// Handle comma-separated IPs (X-Forwarded-For can have multiple)
			if (strpos($ip, ',') !== false) {
				$ips = explode(',', $ip);
				$ip = trim($ips[0]);
			}
			// Validate IP format
			if (filter_var($ip, FILTER_VALIDATE_IP)) {
				return $ip;
			}
		}

		return false;
	}

	/**
	 * Normalize date filter input for SQL comparisons.
	 *
	 * @param mixed $value Raw date/date-time value.
	 * @param bool  $end_of_day Whether to use end-of-day time for date-only values.
	 * @return string|null Normalized MySQL DATETIME string or null when invalid/empty.
	 */
	private static function normalize_filter_datetime($value, $end_of_day = false) {
		if (!is_scalar($value)) {
			return null;
		}

		$raw = trim(sanitize_text_field((string) $value));
		if ($raw === '') {
			return null;
		}

		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
			return $raw . ($end_of_day ? ' 23:59:59' : ' 00:00:00');
		}

		if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $raw) === 1) {
			return $raw;
		}

		if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $raw) === 1) {
			return str_replace('T', ' ', $raw);
		}

		return null;
	}

	/**
	 * Build shared SQL filters and parameters for usage queries.
	 *
	 * @param array $args Query arguments.
	 * @return array{0: string[], 1: array} SQL conditions and prepared params.
	 */
	private static function build_usage_filters($args) {
		$conditions = [];
		$params = [];

		$date_from = self::normalize_filter_datetime($args['date_from'] ?? null, false);
		if ($date_from !== null) {
			$conditions[] = 'generated_at >= %s';
			$params[] = $date_from;
		}

		$date_to = self::normalize_filter_datetime($args['date_to'] ?? null, true);
		if ($date_to !== null) {
			$conditions[] = 'generated_at <= %s';
			$params[] = $date_to;
		}

		$source_raw = $args['source'] ?? '';
		$source = is_scalar($source_raw) ? sanitize_key((string) $source_raw) : '';
		if ($source !== '') {
			$conditions[] = 'source = %s';
			$params[] = $source;
		}

		return [$conditions, $params];
	}

	/**
	 * Get usage summary for a specific user.
	 *
	 * @param int $user_id The WordPress user ID (0 for anonymous/system).
	 * @param array $args Optional. Query arguments (date_from, date_to, source).
	 * @return array Usage statistics.
	 */
		public static function get_user_usage($user_id, $args = []) {
			if (!self::table_exists()) {
			return [
				'total_credits' => 0,
				'total_images'  => 0,
				'total_cost'    => 0.0,
				'last_activity' => null,
			];
		}

		global $wpdb;

		$defaults = [
			'date_from' => null,
			'date_to'   => null,
			'source'    => null,
		];
			$args = wp_parse_args($args, $defaults);

			$table = esc_sql( self::table() );
			list($filter_conditions, $filter_params) = self::build_usage_filters($args);

			$query = "SELECT SUM(credits_used) as total_credits, COUNT(DISTINCT attachment_id) as total_images, SUM(token_cost) as total_cost, MAX(generated_at) as last_activity FROM `{$table}` WHERE user_id = %d";
			$query_params = [absint($user_id)];

			if (!empty($filter_conditions)) {
				$query .= ' AND ' . implode(' AND ', $filter_conditions);
				$query_params = array_merge($query_params, $filter_params);
			}

			$result = $wpdb->get_row(
				$wpdb->prepare(
					$query,
					$query_params
				),
				ARRAY_A
		);

		if (!$result) {
			return [
				'total_credits' => 0,
				'total_images'  => 0,
				'total_cost'    => 0.0,
				'last_activity' => null,
			];
		}

		return [
			'total_credits' => intval($result['total_credits'] ?? 0),
			'total_images'  => intval($result['total_images'] ?? 0),
			'total_cost'    => floatval($result['total_cost'] ?? 0.0),
			'last_activity' => $result['last_activity'] ?? null,
		];
	}

	/**
	 * Get site-wide usage summary.
	 *
	 * @param array $args Optional. Query arguments (date_from, date_to, source).
	 * @return array Usage statistics.
	 */
		public static function get_site_usage($args = []) {
		if (!self::table_exists()) {
			return [
				'total_credits' => 0,
				'total_images'  => 0,
				'total_cost'    => 0.0,
				'user_count'    => 0,
			];
		}

		global $wpdb;

		$defaults = [
			'date_from' => null,
			'date_to'   => null,
			'source'    => null,
		];
			$args = wp_parse_args($args, $defaults);

			$table = esc_sql( self::table() );
			list($filter_conditions, $filter_params) = self::build_usage_filters($args);

			$query = "SELECT SUM(credits_used) as total_credits, COUNT(DISTINCT attachment_id) as total_images, SUM(token_cost) as total_cost, COUNT(DISTINCT user_id) as user_count FROM `{$table}` WHERE 1 = 1";
			if (!empty($filter_conditions)) {
				$query .= ' AND ' . implode(' AND ', $filter_conditions);
			}

			if (!empty($filter_params)) {
				$result = $wpdb->get_row(
					$wpdb->prepare(
						$query,
						$filter_params
					),
					ARRAY_A
				);
			} else {
				$result = $wpdb->get_row($query, ARRAY_A);
			}

		if (!$result) {
			return [
				'total_credits' => 0,
				'total_images'  => 0,
				'total_cost'    => 0.0,
				'user_count'    => 0,
			];
		}

		return [
			'total_credits' => intval($result['total_credits'] ?? 0),
			'total_images'  => intval($result['total_images'] ?? 0),
			'total_cost'    => floatval($result['total_cost'] ?? 0.0),
			'user_count'    => intval($result['user_count'] ?? 0),
		];
	}

	/**
	 * Get usage breakdown by user.
	 *
	 * @param array $args Optional. Query arguments (date_from, date_to, source, per_page, page).
	 * @return array Results with pagination info.
	 */
	public static function get_usage_by_user($args = []) {
		if (!self::table_exists()) {
			return [
				'users' => [],
				'total' => 0,
				'pages' => 0,
			];
		}

		global $wpdb;

		$defaults = [
			'date_from' => null,
			'date_to'   => null,
			'source'    => null,
			'user_id'   => null,
			'per_page'  => 50,
			'page'      => 1,
			'orderby'   => 'total_credits',
			'order'     => 'DESC',
		];
		$args = wp_parse_args($args, $defaults);

			$user_filter = ($args['user_id'] !== null && $args['user_id'] !== '') ? absint($args['user_id']) : 0;
			$order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
			$allowed_orderby = [
				'total_credits' => 'total_credits',
				'total_images'  => 'total_images',
				'last_activity' => 'last_activity',
			];
			$orderby_raw = ( isset( $args['orderby'] ) && is_string( $args['orderby'] ) ) ? sanitize_key( $args['orderby'] ) : '';
			$orderby = isset($allowed_orderby[$orderby_raw]) ? $allowed_orderby[$orderby_raw] : 'total_credits';
			$table = esc_sql( self::table() );

			list($filter_conditions, $filter_params) = self::build_usage_filters($args);
			$query = "SELECT user_id, SUM(credits_used) as total_credits, COUNT(DISTINCT attachment_id) as total_images, COUNT(DISTINCT attachment_id) as images_processed, SUM(token_cost) as total_cost, MAX(generated_at) as last_activity FROM `{$table}` WHERE 1 = 1";
			$query_params = [];

			if ($user_filter > 0) {
				$query .= ' AND user_id = %d';
				$query_params[] = $user_filter;
			}

			if (!empty($filter_conditions)) {
				$query .= ' AND ' . implode(' AND ', $filter_conditions);
				$query_params = array_merge($query_params, $filter_params);
			}

			$query .= " GROUP BY user_id ORDER BY {$orderby} {$order}";

			if (!empty($query_params)) {
				$all_results = $wpdb->get_results(
					$wpdb->prepare(
						$query,
						$query_params
					),
					ARRAY_A
				);
			} else {
				$all_results = $wpdb->get_results($query, ARRAY_A);
			}
		
		// Ensure we have an array
		if (!is_array($all_results)) {
			$all_results = [];
		}

		// Pagination
		$total = count($all_results);
		$per_page = max(1, absint($args['per_page']));
		$page = max(1, absint($args['page']));
		$offset = ($page - 1) * $per_page;
		$pages = ceil($total / $per_page);

		$users = array_slice($all_results, $offset, $per_page);

		// Enrich with user display names and format dates
		foreach ($users as &$user_data) {
			$user_id = intval($user_data['user_id']);
			
			// Ensure images_processed field exists (for template compatibility)
			if (!isset($user_data['images_processed'])) {
				$user_data['images_processed'] = isset($user_data['total_images']) ? intval($user_data['total_images']) : 0;
			} else {
				$user_data['images_processed'] = intval($user_data['images_processed']);
			}
			
			// Format last_activity date for display
			if (!empty($user_data['last_activity'])) {
				$last_activity_timestamp = strtotime($user_data['last_activity']);
				if ($last_activity_timestamp !== false) {
					$user_data['latest_activity'] = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_activity_timestamp);
				} else {
					$user_data['latest_activity'] = '';
				}
			} else {
				$user_data['latest_activity'] = '';
			}
			
			if ($user_id > 0) {
				$wp_user = get_user_by('ID', $user_id);
				if ($wp_user) {
					$user_data['display_name'] = $wp_user->display_name;
					$user_data['user_email'] = $wp_user->user_email;
				} else {
						// User was deleted - check if we have original ID
						$original_id = $wpdb->get_var(
							$wpdb->prepare(
								"SELECT deleted_user_original_id FROM `{$table}` WHERE user_id = %d LIMIT 1",
								$user_id
							)
						);
					$user_data['display_name'] = __('Unknown User', 'beepbeep-ai-alt-text-generator');
					$user_data['user_email'] = '';
					$user_data['deleted_user'] = true;
					if ($original_id) {
						$user_data['deleted_user_original_id'] = intval($original_id);
					}
				}
			} else {
				$user_data['display_name'] = __('System', 'beepbeep-ai-alt-text-generator');
				$user_data['user_email'] = '';
			}
		}

		return [
			'users' => $users,
			'total' => $total,
			'pages' => $pages,
			'page'  => $page,
			'per_page' => $per_page,
		];
	}

	/**
	 * Get detailed usage for a specific user.
	 *
	 * @param int   $user_id The WordPress user ID.
	 * @param array $args Optional. Query arguments (date_from, date_to, source, per_page, page).
	 * @return array Results with pagination info.
	 */
	public static function get_user_details($user_id, $args = []) {
		if (!self::table_exists()) {
			return [
				'items' => [],
				'total' => 0,
				'pages' => 0,
			];
		}

		global $wpdb;

		$defaults = [
			'date_from' => null,
			'date_to'   => null,
			'source'    => null,
			'per_page'  => 50,
			'page'      => 1,
		];
			$args = wp_parse_args($args, $defaults);

			$table = esc_sql( self::table() );
			list($filter_conditions, $filter_params) = self::build_usage_filters($args);
			$where_sql = 'user_id = %d';
			$where_params = [absint($user_id)];

			if (!empty($filter_conditions)) {
				$where_sql .= ' AND ' . implode(' AND ', $filter_conditions);
				$where_params = array_merge($where_params, $filter_params);
			}

			$summary = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT SUM(credits_used) as total_credits, COUNT(DISTINCT attachment_id) as total_images, COUNT(DISTINCT attachment_id) as images_processed, SUM(token_cost) as total_cost FROM `{$table}` WHERE {$where_sql}",
					$where_params
				),
				ARRAY_A
			);

		$total = intval(
				$wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}",
						$where_params
					)
				)
			);

		$per_page = max(1, absint($args['per_page']));
		$page = max(1, absint($args['page']));
		$offset = ($page - 1) * $per_page;
		$pages = ceil($total / $per_page);

			$items = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, user_id, attachment_id, credits_used, token_cost, model, source, generated_at, ip_address, deleted_user_original_id FROM `{$table}` WHERE {$where_sql} ORDER BY generated_at DESC LIMIT %d OFFSET %d",
					array_merge($where_params, [$per_page, $offset])
				),
				ARRAY_A
			);

		// Get user info
		$wp_user = get_user_by('ID', $user_id);
		$user_name = $wp_user ? $wp_user->display_name : __('Unknown User', 'beepbeep-ai-alt-text-generator');
		$user_email = $wp_user ? $wp_user->user_email : '';

		// Enrich with attachment info
		foreach ($items as &$item) {
			$attachment_id = intval($item['attachment_id']);
			$attachment = get_post($attachment_id);
			if ($attachment) {
				$item['attachment_url'] = wp_get_attachment_url($attachment_id);
				$item['attachment_title'] = get_the_title($attachment_id);
				$item['attachment_filename'] = wp_basename(get_attached_file($attachment_id) ?: '');
			}
		}

		return [
			'name' => $user_name,
			'email' => $user_email,
			'total_credits' => isset($summary['total_credits']) ? intval($summary['total_credits']) : 0,
			'images_processed' => isset($summary['images_processed']) ? intval($summary['images_processed']) : (isset($summary['total_images']) ? intval($summary['total_images']) : 0),
			'total_cost' => isset($summary['total_cost']) ? floatval($summary['total_cost']) : 0.0,
			'items' => $items,
			'total' => $total,
			'pages' => $pages,
			'page'  => $page,
			'per_page' => $per_page,
		];
	}

    /**
     * Anonymize credit usage records when a user is deleted.
     * Hook: delete_user
     *
     * @param int      $user_id The WordPress user ID being deleted.
     * @param int|null $reassign ID of user to reassign data to (not used for credit logs).
     */
    public static function anonymize_user_usage($user_id, $reassign = null) {
		if (!self::table_exists()) {
			return;
		}

		global $wpdb;
		$table = esc_sql( self::table() );

		// Update all records for this user to user_id = 0 and store original ID
		$wpdb->update(
			$table,
			[
				'user_id'                  => 0,
				'deleted_user_original_id' => absint($user_id),
			],
			['user_id' => absint($user_id)],
			['%d', '%d'],
			['%d']
		);
	}

	/**
	 * Delete old usage records.
	 *
	 * @param int $days Number of days to keep records.
	 * @return int Number of records deleted.
	 */
	public static function delete_older_than($days) {
		if (!self::table_exists()) {
			return 0;
		}

			global $wpdb;
			$threshold = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
			$table = esc_sql( self::table() );

			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM `{$table}` WHERE generated_at < %s",
					$threshold
				)
			);

		return intval($deleted);
	}

	/**
	 * Initialize hooks for user deletion anonymization.
	 */
	public static function init_hooks() {
		// Anonymize credit usage when a user is deleted
		add_action('delete_user', [__CLASS__, 'anonymize_user_usage'], 10, 2);
	}
}
