<?php
/**
 * Usage Logs Manager for BeepBeep AI
 * Tracks per-user token usage events for multi-user visualization
 */

namespace BeepBeepAI\AltTextGenerator\Usage;

if (!defined('ABSPATH')) {
	exit;
}

class Usage_Logs {
	const TABLE_SLUG = 'bbai_usage_logs';
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
	 * Create the usage logs table if it doesn't exist.
	 */
	public static function create_table() {
		if (self::$table_verified) {
			// Still check for missing columns even if table was already verified
			self::ensure_columns_exist();
			return;
		}

		global $wpdb;
		$table_name_safe = esc_sql( self::table() );
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS `{$table_name_safe}` (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			site_id VARCHAR(64) NOT NULL DEFAULT '',
			tokens_used INT(11) UNSIGNED NOT NULL DEFAULT 0,
			action_type VARCHAR(50) NOT NULL DEFAULT 'generate',
			image_id BIGINT(20) UNSIGNED DEFAULT NULL,
			post_id BIGINT(20) UNSIGNED DEFAULT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY site_id (site_id),
			KEY created_at (created_at),
			KEY action_type (action_type),
			KEY user_created (user_id, created_at),
			KEY site_created (site_id, created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);
		
		// Ensure all columns exist (dbDelta doesn't always add missing columns)
		self::ensure_columns_exist();
		
		self::$table_verified = true;
	}
	
	/**
	 * Ensure all required columns exist in the table.
	 */
		private static function ensure_columns_exist() {
			if (!self::table_exists()) {
				return;
			}

			global $wpdb;
			$table_name = esc_sql( self::table() );
			$columns = $wpdb->get_col(
				$wpdb->prepare(
					"SHOW COLUMNS FROM `{$table_name}` LIKE %s",
					'site_id'
				)
			);

			if (empty($columns)) {
				$wpdb->query(
					$wpdb->prepare(
						"ALTER TABLE `{$table_name}` ADD COLUMN `site_id` VARCHAR(64) NOT NULL DEFAULT %s AFTER `user_id`",
						''
					)
				);

				$indexes = $wpdb->get_col(
					$wpdb->prepare(
						"SHOW INDEXES FROM `{$table_name}` WHERE Key_name = %s",
						'site_id'
					)
				);
				if (empty($indexes)) {
					$wpdb->query(
						$wpdb->prepare(
							"ALTER TABLE `{$table_name}` ADD INDEX `site_id` (`site_id`) /* %d */",
							1
						)
					);
				}

				$composite_indexes = $wpdb->get_col(
					$wpdb->prepare(
						"SHOW INDEXES FROM `{$table_name}` WHERE Key_name = %s",
						'site_created'
					)
				);
				if (empty($composite_indexes)) {
					$wpdb->query(
						$wpdb->prepare(
							"ALTER TABLE `{$table_name}` ADD INDEX `site_created` (`site_id`, `created_at`) /* %d */",
							1
						)
					);
				}
		}
	}

	/**
	 * Check if the usage logs table exists.
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
	 * Record a usage event.
	 *
	 * @param int    $user_id User ID (0 for system/anonymous).
	 * @param int    $tokens_used Number of tokens used.
	 * @param string $action_type Action type (generate, regenerate, bulk, api).
	 * @param array  $context Optional context data (image_id, post_id, etc.).
	 * @return int|false The ID of the inserted record, or false on failure.
	 */
	public static function record_usage_event($user_id, $tokens_used, $action_type = 'generate', $context = []) {
		if (!self::table_exists()) {
			self::create_table();
		} else {
			// Ensure columns exist even if table was already created
			self::ensure_columns_exist();
		}

		global $wpdb;
			$table_name = esc_sql( self::table() );

		// Sanitize inputs
		$user_id = absint($user_id);
		$tokens_used = absint($tokens_used);
		$action_type = sanitize_key($action_type);
		$image_id = isset($context['image_id']) ? absint($context['image_id']) : null;
		$post_id = isset($context['post_id']) ? absint($context['post_id']) : null;
		
		// Get site identifier
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
		$site_id = \BeepBeepAI\AltTextGenerator\get_site_identifier();
		$site_id = sanitize_text_field(substr($site_id, 0, 64));

		// Validate action type
		$allowed_actions = ['generate', 'regenerate', 'bulk', 'api', 'bulk-generate'];
		if (!in_array($action_type, $allowed_actions, true)) {
			$action_type = 'generate';
		}

			$columns = $wpdb->get_col(
				$wpdb->prepare(
					"SHOW COLUMNS FROM `{$table_name}` LIKE %s",
					'site_id'
				)
			);
		$has_site_id = !empty($columns);
		
	// Prepare insert data with matching format per column.
	// wpdb::insert matches format to data by array order, so keep them aligned.
	$insert_data   = [];
	$insert_format = [];

	$insert_data['user_id']     = $user_id;
	$insert_format[]            = '%d';

	if ($has_site_id) {
		$insert_data['site_id'] = $site_id;
		$insert_format[]        = '%s';
	}

	$insert_data['tokens_used'] = $tokens_used;
	$insert_format[]            = '%d';

	$insert_data['action_type'] = $action_type;
	$insert_format[]            = '%s';

	$insert_data['image_id']    = $image_id;
	$insert_format[]            = '%d';

	$insert_data['post_id']     = $post_id;
	$insert_format[]            = '%d';

	$insert_data['created_at']  = current_time( 'mysql' );
	$insert_format[]            = '%s';

	$inserted = $wpdb->insert(
		$table_name,
		$insert_data,
		$insert_format
	);

		if ($inserted) {
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Get monthly total usage for current month.
	 *
	 * @return int Total tokens used this month.
	 */
		public static function get_monthly_total_usage() {
		if (!self::table_exists()) {
			return 0;
		}

		global $wpdb;
			$table_name = esc_sql( self::table() );

		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
		$site_id = \BeepBeepAI\AltTextGenerator\get_site_identifier();
		$site_id = sanitize_text_field(substr($site_id, 0, 64));

		$current_month_start = wp_date('Y-m-01 00:00:00');
		$current_month_end = wp_date('Y-m-t 23:59:59');

			$total = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(tokens_used) FROM `{$table_name}` WHERE site_id = %s AND created_at >= %s AND created_at <= %s",
					$site_id,
					$current_month_start,
					$current_month_end
			)
		);

		return absint($total ?: 0);
	}

	/**
	 * Get monthly usage breakdown by user.
	 *
	 * @return array Array of user usage data.
	 */
		public static function get_monthly_usage_by_user() {
		if (!self::table_exists()) {
			return [];
		}

		global $wpdb;
			$table_name = esc_sql( self::table() );

		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
		$site_id = \BeepBeepAI\AltTextGenerator\get_site_identifier();
		$site_id = sanitize_text_field(substr($site_id, 0, 64));

		$current_month_start = wp_date('Y-m-01 00:00:00');
		$current_month_end = wp_date('Y-m-t 23:59:59');

			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT user_id, SUM(tokens_used) as tokens_used, MAX(created_at) as last_used FROM `{$table_name}` WHERE site_id = %s AND created_at >= %s AND created_at <= %s GROUP BY user_id ORDER BY tokens_used DESC",
					$site_id,
					$current_month_start,
					$current_month_end
			),
			ARRAY_A
		);

		if (!is_array($results)) {
			return [];
		}

		// Enrich with user data
		$enriched = [];
		foreach ($results as $row) {
			$user_id = intval($row['user_id']);
			$wp_user = $user_id > 0 ? get_user_by('ID', $user_id) : null;

			// Determine username and display name
			if ($wp_user instanceof \WP_User) {
				$username = $wp_user->user_login;
				$display_name = $wp_user->display_name ?: $wp_user->user_login;
			} elseif ($user_id > 0) {
				// User ID exists but user not found - might be deleted
				/* translators: %d: WordPress user ID for a deleted/missing user. */
				$username = sprintf(__('User #%d', 'beepbeep-ai-alt-text-generator'), $user_id);
				/* translators: %d: WordPress user ID for a deleted/missing user. */
				$display_name = sprintf(__('User #%d', 'beepbeep-ai-alt-text-generator'), $user_id);
			} else {
				// System/anonymous user
				$username = __('System', 'beepbeep-ai-alt-text-generator');
				$display_name = __('System', 'beepbeep-ai-alt-text-generator');
			}

			$enriched[] = [
				'user_id'    => $user_id,
				'username'   => $username,
				'display_name' => $display_name,
				'tokens_used' => absint($row['tokens_used']),
				'last_used'  => $row['last_used'],
			];
		}

		return $enriched;
	}

	/**
	 * Get usage events with filters.
	 *
	 * @param array $filters Filter options (user_id, date_from, date_to, action_type, per_page, page).
	 * @return array Events with pagination info.
	 */
		public static function get_usage_events($filters = []) {
		if (!self::table_exists()) {
			return [
				'events' => [],
				'total'  => 0,
				'pages'  => 0,
			];
		}

		global $wpdb;
			$table_name = esc_sql( self::table() );

		$defaults = [
			'user_id'    => null,
			'date_from'  => null,
			'date_to'    => null,
			'action_type' => null,
			'per_page'   => 50,
			'page'       => 1,
			'skip_site_filter' => false,
		];
		$filters = wp_parse_args($filters, $defaults);

		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
		$site_id = \BeepBeepAI\AltTextGenerator\get_site_identifier();
		$site_id = sanitize_text_field(substr($site_id, 0, 64));
		$skip_site_filter = !empty($filters['skip_site_filter']);

		$user_id = ($filters['user_id'] !== null && $filters['user_id'] !== '') ? absint($filters['user_id']) : 0;
		$has_user_filter = $user_id > 0 ? 1 : 0;
		$date_from = !empty($filters['date_from']) ? sanitize_text_field($filters['date_from']) . ' 00:00:00' : '';
		$date_to = !empty($filters['date_to']) ? sanitize_text_field($filters['date_to']) . ' 23:59:59' : '';
		$action_type = !empty($filters['action_type']) ? sanitize_key($filters['action_type']) : '';

		// Build WHERE clause conditionally
		if ($skip_site_filter) {
			$total = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM `{$table_name}` WHERE (%d = %d OR user_id = %d) AND (%s = %s OR created_at >= %s) AND (%s = %s OR created_at <= %s) AND (%s = %s OR action_type = %s)",
					$has_user_filter,
					0,
					$user_id,
					$date_from,
					'',
					$date_from,
					$date_to,
					'',
					$date_to,
					$action_type,
					'',
					$action_type
				)
			);
		} else {
			$total = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM `{$table_name}` WHERE site_id = %s AND (%d = %d OR user_id = %d) AND (%s = %s OR created_at >= %s) AND (%s = %s OR created_at <= %s) AND (%s = %s OR action_type = %s)",
					$site_id,
					$has_user_filter,
					0,
					$user_id,
					$date_from,
					'',
					$date_from,
					$date_to,
					'',
					$date_to,
					$action_type,
					'',
					$action_type
				)
			);
		}
		$total = absint($total ?: 0);

		// Pagination
		$per_page = max(1, absint($filters['per_page']));
		$page = max(1, absint($filters['page']));
		$offset = ($page - 1) * $per_page;
		$pages = ceil($total / $per_page);

		// Get events
		if ($skip_site_filter) {
			$events = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM `{$table_name}` WHERE (%d = %d OR user_id = %d) AND (%s = %s OR created_at >= %s) AND (%s = %s OR created_at <= %s) AND (%s = %s OR action_type = %s) ORDER BY created_at DESC LIMIT %d OFFSET %d",
					$has_user_filter,
					0,
					$user_id,
					$date_from,
					'',
					$date_from,
					$date_to,
					'',
					$date_to,
					$action_type,
					'',
					$action_type,
					$per_page,
					$offset
				),
				ARRAY_A
			);
		} else {
			$events = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM `{$table_name}` WHERE site_id = %s AND (%d = %d OR user_id = %d) AND (%s = %s OR created_at >= %s) AND (%s = %s OR created_at <= %s) AND (%s = %s OR action_type = %s) ORDER BY created_at DESC LIMIT %d OFFSET %d",
					$site_id,
					$has_user_filter,
					0,
					$user_id,
					$date_from,
					'',
					$date_from,
					$date_to,
					'',
					$date_to,
					$action_type,
					'',
					$action_type,
					$per_page,
					$offset
				),
				ARRAY_A
			);
		}

		if (!is_array($events)) {
			$events = [];
		}

		// Enrich with user data
		$enriched = [];
		foreach ($events as $event) {
			$user_id = intval($event['user_id']);
			$wp_user = $user_id > 0 ? get_user_by('ID', $user_id) : null;

			// Determine username and display name
			if ($wp_user instanceof \WP_User) {
				$username = $wp_user->user_login;
				$display_name = $wp_user->display_name ?: $wp_user->user_login;
			} elseif ($user_id > 0) {
				// User ID exists but user not found - might be deleted
				// Try to get from deleted users table or show user ID
				/* translators: %d: WordPress user ID for a deleted/missing user. */
				$username = sprintf(__('User #%d', 'beepbeep-ai-alt-text-generator'), $user_id);
				/* translators: %d: WordPress user ID for a deleted/missing user. */
				$display_name = sprintf(__('User #%d', 'beepbeep-ai-alt-text-generator'), $user_id);
			} else {
				// System/anonymous user
				$username = __('System', 'beepbeep-ai-alt-text-generator');
				$display_name = __('System', 'beepbeep-ai-alt-text-generator');
			}

			$enriched[] = [
				'id'          => absint($event['id']),
				'user_id'    => $user_id,
				'username'   => $username,
				'display_name' => $display_name,
				'tokens_used' => absint($event['tokens_used']),
				'action_type' => sanitize_text_field($event['action_type']),
				'image_id'    => !empty($event['image_id']) ? absint($event['image_id']) : null,
				'post_id'     => !empty($event['post_id']) ? absint($event['post_id']) : null,
				'created_at'  => $event['created_at'],
			];
		}

		return [
			'events' => $enriched,
			'total'  => $total,
			'pages'  => $pages,
			'page'   => $page,
		];
	}
}
