<?php
/**
 * Credit Usage Logger for BeepBeep AI
 * Tracks per-user credit consumption and maintains detailed audit trail
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Credit_Usage_Logger {
	const TABLE_SLUG               = 'bbai_credit_usage';
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
		if ( self::$table_verified ) {
			return;
		}

		global $wpdb;
		$table_name      = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		// Use esc_sql for table name in DDL
		$table_name_safe = esc_sql( $table_name );

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
			KEY user_generated (user_id, generated_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		self::$table_verified = true;
	}

	/**
	 * Check if the credit usage table exists.
	 *
	 * @return bool
	 */
	public static function table_exists() {
		global $wpdb;
		$table  = self::table();
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $exists === $table;
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
	public static function log_usage( $attachment_id, $user_id = 0, $credits_used = 1, $token_cost = null, $model = '', $source = 'manual' ) {
		if ( ! self::table_exists() ) {
			self::create_table();
		}

		global $wpdb;
		$table_name      = self::table();
		$table_name_safe = esc_sql( $table_name );

		// Sanitize inputs
		$attachment_id = absint( $attachment_id );
		$user_id       = absint( $user_id );
		$credits_used  = absint( $credits_used );
		$token_cost    = $token_cost !== null ? floatval( $token_cost ) : null;
		$model         = sanitize_text_field( $model );
		$source        = sanitize_key( $source );

		// Get IP address (respecting proxies)
		$ip_address = self::get_client_ip();
		if ( $ip_address ) {
			$ip_address = sanitize_text_field( $ip_address );
		}

		// Hash user agent for privacy
		$user_agent      = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
		$user_agent_hash = ! empty( $user_agent ) ? hash( 'sha256', $user_agent ) : null;

		$result = $wpdb->insert(
			$table_name_safe,
			array(
				'user_id'         => $user_id,
				'attachment_id'   => $attachment_id,
				'credits_used'    => $credits_used,
				'token_cost'      => $token_cost,
				'model'           => $model,
				'source'          => $source,
				'generated_at'    => current_time( 'mysql' ),
				'ip_address'      => $ip_address,
				'user_agent_hash' => $user_agent_hash,
			),
			array( '%d', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get client IP address (respecting proxies).
	 *
	 * @return string|false
	 */
	private static function get_client_ip() {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_REAL_IP',        // Nginx proxy
			'HTTP_X_FORWARDED_FOR',  // Standard proxy
			'REMOTE_ADDR',           // Direct connection
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// Handle comma-separated IPs (X-Forwarded-For can have multiple)
				if ( strpos( $ip, ',' ) !== false ) {
					$ips = explode( ',', $ip );
					$ip  = trim( $ips[0] );
				}
				// Validate IP format
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return false;
	}

	/**
	 * Get usage summary for a specific user.
	 *
	 * @param int   $user_id The WordPress user ID (0 for anonymous/system).
	 * @param array $args Optional. Query arguments (date_from, date_to, source).
	 * @return array Usage statistics.
	 */
	public static function get_user_usage( $user_id, $args = array() ) {
		if ( ! self::table_exists() ) {
			return array(
				'total_credits' => 0,
				'total_images'  => 0,
				'total_cost'    => 0.0,
				'last_activity' => null,
			);
		}

		global $wpdb;
		$table         = self::table();
		$table_escaped = esc_sql( $table );

		$defaults = array(
			'date_from' => null,
			'date_to'   => null,
			'source'    => null,
		);
		$args     = wp_parse_args( $args, $defaults );

		$where  = array( 'user_id = %d' );
		$params = array( absint( $user_id ) );

		if ( $args['date_from'] ) {
			$date_from = sanitize_text_field( $args['date_from'] );
			$where[]   = 'generated_at >= %s';
			$params[]  = $date_from . ' 00:00:00';
		}
		if ( $args['date_to'] ) {
			$date_to  = sanitize_text_field( $args['date_to'] );
			$where[]  = 'generated_at <= %s';
			$params[] = $date_to . ' 23:59:59';
		}
		if ( $args['source'] ) {
			$where[]  = 'source = %s';
			$params[] = sanitize_key( $args['source'] );
		}

		$where_sql = 'WHERE ' . implode( ' AND ', $where );

		// Get totals
		$query = "SELECT 
				SUM(credits_used) as total_credits,
				COUNT(DISTINCT attachment_id) as total_images,
				SUM(token_cost) as total_cost,
				MAX(generated_at) as last_activity
			FROM `{$table_escaped}` 
			{$where_sql}";

		$result = $wpdb->get_row( $wpdb->prepare( $query, $params ), ARRAY_A );

		if ( ! $result ) {
			return array(
				'total_credits' => 0,
				'total_images'  => 0,
				'total_cost'    => 0.0,
				'last_activity' => null,
			);
		}

		return array(
			'total_credits' => intval( $result['total_credits'] ?? 0 ),
			'total_images'  => intval( $result['total_images'] ?? 0 ),
			'total_cost'    => floatval( $result['total_cost'] ?? 0.0 ),
			'last_activity' => $result['last_activity'] ?? null,
		);
	}

	/**
	 * Get site-wide usage summary.
	 *
	 * @param array $args Optional. Query arguments (date_from, date_to, source).
	 * @return array Usage statistics.
	 */
	public static function get_site_usage( $args = array() ) {
		if ( ! self::table_exists() ) {
			return array(
				'total_credits' => 0,
				'total_images'  => 0,
				'total_cost'    => 0.0,
				'user_count'    => 0,
			);
		}

		global $wpdb;
		$table         = self::table();
		$table_escaped = esc_sql( $table );

		$defaults = array(
			'date_from' => null,
			'date_to'   => null,
			'source'    => null,
		);
		$args     = wp_parse_args( $args, $defaults );

		$where  = array();
		$params = array();

		if ( $args['date_from'] ) {
			$date_from = sanitize_text_field( $args['date_from'] );
			$where[]   = 'generated_at >= %s';
			$params[]  = $date_from . ' 00:00:00';
		}
		if ( $args['date_to'] ) {
			$date_to  = sanitize_text_field( $args['date_to'] );
			$where[]  = 'generated_at <= %s';
			$params[] = $date_to . ' 23:59:59';
		}
		if ( $args['source'] ) {
			$where[]  = 'source = %s';
			$params[] = sanitize_key( $args['source'] );
		}

		$where_sql = count( $where ) > 0 ? 'WHERE ' . implode( ' AND ', $where ) : '';

		// Get totals
		$query = "SELECT 
				SUM(credits_used) as total_credits,
				COUNT(DISTINCT attachment_id) as total_images,
				SUM(token_cost) as total_cost,
				COUNT(DISTINCT user_id) as user_count
			FROM `{$table_escaped}` 
			{$where_sql}";

		$result = $wpdb->get_row( count( $params ) > 0 ? $wpdb->prepare( $query, $params ) : $query, ARRAY_A );

		if ( ! $result ) {
			return array(
				'total_credits' => 0,
				'total_images'  => 0,
				'total_cost'    => 0.0,
				'user_count'    => 0,
			);
		}

		return array(
			'total_credits' => intval( $result['total_credits'] ?? 0 ),
			'total_images'  => intval( $result['total_images'] ?? 0 ),
			'total_cost'    => floatval( $result['total_cost'] ?? 0.0 ),
			'user_count'    => intval( $result['user_count'] ?? 0 ),
		);
	}

	/**
	 * Get usage breakdown by user.
	 *
	 * @param array $args Optional. Query arguments (date_from, date_to, source, per_page, page).
	 * @return array Results with pagination info.
	 */
	public static function get_usage_by_user( $args = array() ) {
		if ( ! self::table_exists() ) {
			return array(
				'users' => array(),
				'total' => 0,
				'pages' => 0,
			);
		}

		global $wpdb;
		$table         = self::table();
		$table_escaped = esc_sql( $table );

		$defaults = array(
			'date_from' => null,
			'date_to'   => null,
			'source'    => null,
			'user_id'   => null,
			'per_page'  => 50,
			'page'      => 1,
			'orderby'   => 'total_credits',
			'order'     => 'DESC',
		);
		$args     = wp_parse_args( $args, $defaults );

		$where  = array();
		$params = array();

		if ( $args['user_id'] !== null && $args['user_id'] !== '' ) {
			$where[]  = 'user_id = %d';
			$params[] = absint( $args['user_id'] );
		}

		if ( $args['date_from'] ) {
			$date_from = sanitize_text_field( $args['date_from'] );
			$where[]   = 'generated_at >= %s';
			$params[]  = $date_from . ' 00:00:00';
		}
		if ( $args['date_to'] ) {
			$date_to  = sanitize_text_field( $args['date_to'] );
			$where[]  = 'generated_at <= %s';
			$params[] = $date_to . ' 23:59:59';
		}
		if ( $args['source'] ) {
			$where[]  = 'source = %s';
			$params[] = sanitize_key( $args['source'] );
		}

		$where_sql = count( $where ) > 0 ? 'WHERE ' . implode( ' AND ', $where ) : '';

		// Validate orderby
		$allowed_orderby = array( 'total_credits', 'total_images', 'last_activity' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby ) ? $args['orderby'] : 'total_credits';
		$orderby_safe    = esc_sql( $orderby );

		// Validate order
		$order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		// Get grouped results - always use prepare for safety even with no params
		$query = "SELECT 
				user_id,
				SUM(credits_used) as total_credits,
				COUNT(DISTINCT attachment_id) as total_images,
				SUM(token_cost) as total_cost,
				MAX(generated_at) as last_activity
			FROM `{$table_escaped}` 
			{$where_sql}
			GROUP BY user_id
			ORDER BY {$orderby_safe} {$order}";

		// Execute query with prepare if we have params, otherwise execute directly
		if ( count( $params ) > 0 ) {
			$all_results = $wpdb->get_results( $wpdb->prepare( $query, $params ), ARRAY_A );
		} else {
			// No params - safe to execute directly since table name is escaped
			$all_results = $wpdb->get_results( $query, ARRAY_A );
		}

		// Ensure we have an array
		if ( ! is_array( $all_results ) ) {
			$all_results = array();
		}

		// Pagination
		$total    = count( $all_results );
		$per_page = absint( $args['per_page'] );
		$page     = absint( $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;
		$pages    = ceil( $total / $per_page );

		$users = array_slice( $all_results, $offset, $per_page );

		// Enrich with user display names
		foreach ( $users as &$user_data ) {
			$user_id = intval( $user_data['user_id'] );
			if ( $user_id > 0 ) {
				$wp_user = get_user_by( 'ID', $user_id );
				if ( $wp_user ) {
					$user_data['display_name'] = $wp_user->display_name;
					$user_data['user_email']   = $wp_user->user_email;
				} else {
					// User was deleted - check if we have original ID
					$original_id               = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT deleted_user_original_id FROM `{$table_escaped}` WHERE user_id = %d LIMIT 1",
							$user_id
						)
					);
					$user_data['display_name'] = __( 'Unknown User', 'beepbeep-ai-alt-text-generator' );
					$user_data['user_email']   = '';
					$user_data['deleted_user'] = true;
					if ( $original_id ) {
						$user_data['deleted_user_original_id'] = intval( $original_id );
					}
				}
			} else {
				$user_data['display_name'] = __( 'System', 'beepbeep-ai-alt-text-generator' );
				$user_data['user_email']   = '';
			}
		}

		return array(
			'users'    => $users,
			'total'    => $total,
			'pages'    => $pages,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Get detailed usage for a specific user.
	 *
	 * @param int   $user_id The WordPress user ID.
	 * @param array $args Optional. Query arguments (date_from, date_to, source, per_page, page).
	 * @return array Results with pagination info.
	 */
	public static function get_user_details( $user_id, $args = array() ) {
		if ( ! self::table_exists() ) {
			return array(
				'items' => array(),
				'total' => 0,
				'pages' => 0,
			);
		}

		global $wpdb;
		$table         = self::table();
		$table_escaped = esc_sql( $table );

		$defaults = array(
			'date_from' => null,
			'date_to'   => null,
			'source'    => null,
			'per_page'  => 50,
			'page'      => 1,
		);
		$args     = wp_parse_args( $args, $defaults );

		$where  = array( 'user_id = %d' );
		$params = array( absint( $user_id ) );

		if ( $args['date_from'] ) {
			$date_from = sanitize_text_field( $args['date_from'] );
			$where[]   = 'generated_at >= %s';
			$params[]  = $date_from . ' 00:00:00';
		}
		if ( $args['date_to'] ) {
			$date_to  = sanitize_text_field( $args['date_to'] );
			$where[]  = 'generated_at <= %s';
			$params[] = $date_to . ' 23:59:59';
		}
		if ( $args['source'] ) {
			$where[]  = 'source = %s';
			$params[] = sanitize_key( $args['source'] );
		}

		$where_sql = 'WHERE ' . implode( ' AND ', $where );

		// Count total unique images (not total records)
		$count_query = "SELECT COUNT(DISTINCT attachment_id) FROM `{$table_escaped}` {$where_sql}";
		$total       = intval( $wpdb->get_var( $wpdb->prepare( $count_query, $params ) ) );

		// Get paginated results - GROUP BY attachment_id to combine regenerations
		$per_page = absint( $args['per_page'] );
		$page     = absint( $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;
		$pages    = ceil( $total / $per_page );

		// Group by attachment_id and sum credits, get most recent generation date
		$query = "SELECT 
				attachment_id,
				SUM(credits_used) as credits_used,
				SUM(token_cost) as token_cost,
				MAX(generated_at) as generated_at,
				COUNT(*) as generation_count
			FROM `{$table_escaped}` 
			{$where_sql}
			GROUP BY attachment_id
			ORDER BY MAX(generated_at) DESC
			LIMIT %d OFFSET %d";

		$params[] = $per_page;
		$params[] = $offset;

		$items = $wpdb->get_results( $wpdb->prepare( $query, $params ), ARRAY_A );

		// Enrich with attachment info
		foreach ( $items as &$item ) {
			$attachment_id = intval( $item['attachment_id'] );
			$attachment    = get_post( $attachment_id );
			if ( $attachment ) {
				$item['attachment_url']      = wp_get_attachment_url( $attachment_id );
				$item['attachment_title']    = get_the_title( $attachment_id );
				$item['attachment_filename'] = wp_basename( get_attached_file( $attachment_id ) ?: '' );
			}
		}

		return array(
			'items'    => $items,
			'total'    => $total,
			'pages'    => $pages,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Anonymize credit usage records when a user is deleted.
	 * Hook: delete_user
	 *
	 * @param int      $user_id The WordPress user ID being deleted.
	 * @param int|null $reassign ID of user to reassign data to (not used for credit logs).
	 */
	public static function anonymize_user_usage( $user_id, $reassign = null ) {
		if ( ! self::table_exists() ) {
			return;
		}

		global $wpdb;
		$table         = self::table();
		$table_escaped = esc_sql( $table );

		// Update all records for this user to user_id = 0 and store original ID
		$wpdb->update(
			$table,
			array(
				'user_id'                  => 0,
				'deleted_user_original_id' => absint( $user_id ),
			),
			array( 'user_id' => absint( $user_id ) ),
			array( '%d', '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Delete old usage records.
	 *
	 * @param int $days Number of days to keep records.
	 * @return int Number of records deleted.
	 */
	public static function delete_older_than( $days ) {
		if ( ! self::table_exists() ) {
			return 0;
		}

		global $wpdb;
		$table         = self::table();
		$table_escaped = esc_sql( $table );

		$threshold = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `{$table_escaped}` WHERE generated_at < %s",
				$threshold
			)
		);

		return intval( $deleted );
	}

	/**
	 * Get the last generation record.
	 *
	 * @return array|null Last generation record or null if none exists.
	 */
	public static function get_last_generation() {
		if ( ! self::table_exists() ) {
			return null;
		}

		global $wpdb;
		$table         = self::table();
		$table_escaped = esc_sql( $table );

		$last_record = $wpdb->get_row(
			"SELECT * FROM `{$table_escaped}` ORDER BY generated_at DESC LIMIT 1",
			ARRAY_A
		);

		if ( ! $last_record ) {
			return null;
		}

		// Enrich with attachment info
		$attachment_id = intval( $last_record['attachment_id'] );
		$attachment    = get_post( $attachment_id );
		if ( $attachment ) {
			$last_record['attachment_url']      = wp_get_attachment_url( $attachment_id );
			$last_record['attachment_title']    = get_the_title( $attachment_id );
			$last_record['attachment_filename']  = wp_basename( get_attached_file( $attachment_id ) ?: '' );
			$last_record['alt_text']             = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		}

		// Enrich with user info
		$user_id = intval( $last_record['user_id'] );
		if ( $user_id > 0 ) {
			$user = get_user_by( 'ID', $user_id );
			if ( $user ) {
				$last_record['user_display_name'] = $user->display_name;
				$last_record['user_email']        = $user->user_email;
			}
		}

		return $last_record;
	}

	/**
	 * Initialize hooks for user deletion anonymization.
	 */
	public static function init_hooks() {
		// Anonymize credit usage when a user is deleted
		add_action( 'delete_user', array( __CLASS__, 'anonymize_user_usage' ), 10, 2 );
	}
}
