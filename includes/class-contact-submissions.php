<?php
/**
 * Contact Submissions Manager for BeepBeep AI
 * Stores and retrieves contact form submissions
 *
 * @package BeepBeepAI\AltTextGenerator
 * @since 4.4.0
 */

namespace BeepBeepAI\AltTextGenerator;

if (!defined('ABSPATH')) {
	exit;
}

class Contact_Submissions {
	const TABLE_SLUG = 'bbai_contact_submissions';
	private static $table_verified = false;
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter -- SQL identifiers are controlled by plugin schema; runtime values are prepared.

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
	 * Create the contact submissions table if it doesn't exist.
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
			name VARCHAR(255) NOT NULL,
			email VARCHAR(255) NOT NULL,
			subject VARCHAR(500) NOT NULL,
			message TEXT NOT NULL,
			wp_version VARCHAR(50) DEFAULT NULL,
			plugin_version VARCHAR(50) DEFAULT NULL,
			site_url VARCHAR(500) DEFAULT NULL,
			site_hash VARCHAR(64) DEFAULT NULL,
			license_key VARCHAR(255) DEFAULT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'new',
			read_at DATETIME NULL,
			replied_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY email (email),
			KEY status (status),
			KEY created_at (created_at),
			KEY status_created (status, created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);
		self::$table_verified = true;
	}

	/**
	 * Save a contact form submission.
	 *
	 * @param array $data {
	 *     Contact form data.
	 *
	 *     @type string $name           User's name.
	 *     @type string $email          User's email.
	 *     @type string $subject        Email subject.
	 *     @type string $message        Message content.
	 *     @type string $wp_version     WordPress version (optional).
	 *     @type string $plugin_version Plugin version (optional).
	 *     @type string $site_url       Site URL (optional).
	 *     @type string $site_hash      Site hash (optional).
	 *     @type string $license_key   License key (optional).
	 * }
	 * @return int|false Submission ID on success, false on failure.
	 */
	public static function save_submission($data) {
		global $wpdb;

		$table_name = esc_sql( self::table() );
		$user_id = get_current_user_id();

		$result = $wpdb->insert(
			$table_name,
			[
				'user_id'       => $user_id,
				'name'          => sanitize_text_field($data['name'] ?? ''),
				'email'         => sanitize_email($data['email'] ?? ''),
				'subject'       => sanitize_text_field($data['subject'] ?? ''),
				'message'       => sanitize_textarea_field($data['message'] ?? ''),
				'wp_version'    => sanitize_text_field($data['wp_version'] ?? null),
				'plugin_version' => sanitize_text_field($data['plugin_version'] ?? null),
				'site_url'      => esc_url_raw($data['site_url'] ?? null),
				'site_hash'     => sanitize_text_field($data['site_hash'] ?? null),
				'license_key'   => sanitize_text_field($data['license_key'] ?? null),
				'status'        => 'new',
				'created_at'    => current_time('mysql'),
			],
			['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get submissions with pagination and filters.
	 *
	 * @param array $args {
	 *     Query arguments.
	 *
	 *     @type int    $per_page  Number of items per page.
	 *     @type int    $page      Current page number.
	 *     @type string $status    Filter by status (new, read, replied).
	 *     @type string $search    Search in name, email, subject, or message.
	 *     @type string $orderby   Order by field (created_at, name, email, status).
	 *     @type string $order     Order direction (ASC, DESC).
	 * }
	 * @return array {
	 *     @type array  $items     Array of submission objects.
	 *     @type int    $total     Total number of submissions.
	 *     @type int    $pages     Total number of pages.
	 * }
	 */
	public static function get_submissions( $args = [] ) {
		global $wpdb;

		$defaults = [
			'per_page' => 20,
			'page'     => 1,
			'status'   => '',
			'search'   => '',
			'orderby'  => 'created_at',
			'order'    => 'DESC',
		];

		$args     = wp_parse_args( $args, $defaults );
		$per_page = absint( $args['per_page'] );
		if ( $per_page <= 0 ) {
			$per_page = 20;
		}

		$page   = max( 1, absint( $args['page'] ) );
		$offset = ( $page - 1 ) * $per_page;

		$status = '';
		if ( ! empty( $args['status'] ) && is_string( $args['status'] ) ) {
			$status = sanitize_text_field( $args['status'] );
		}

		$search_term = '';
		if ( ! empty( $args['search'] ) && is_string( $args['search'] ) ) {
			$search_term = sanitize_text_field( $args['search'] );
		}
		$search_like = '%' . $wpdb->esc_like( $search_term ) . '%';

		// Validate orderby — strict allowlist mapping to real column names.
		$allowed_orderby = [
			'created_at' => 'created_at',
			'name'       => 'name',
			'email'      => 'email',
			'status'     => 'status',
		];
		$orderby = isset( $allowed_orderby[ $args['orderby'] ] ) ? $allowed_orderby[ $args['orderby'] ] : 'created_at';

		$order = ( ! empty( $args['order'] ) && is_string( $args['order'] ) && strtoupper( $args['order'] ) === 'ASC' )
			? 'ASC'
			: 'DESC';

		$base_params = [
			$status,
			'',
			$status,
			$search_term,
			'',
			$search_like,
			$search_like,
			$search_like,
			$search_like,
		];

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM `' . self::table() . '` WHERE (%s = %s OR status = %s) AND (%s = %s OR name LIKE %s OR email LIKE %s OR subject LIKE %s OR message LIKE %s)',
				$base_params
			)
		);

		$query_params = array_merge( $base_params, [ $per_page, $offset ] );

		if ( 'name' === $orderby && 'ASC' === $order ) {
			$items = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM `' . self::table() . '` WHERE (%s = %s OR status = %s) AND (%s = %s OR name LIKE %s OR email LIKE %s OR subject LIKE %s OR message LIKE %s) ORDER BY name ASC LIMIT %d OFFSET %d',
					$query_params
				),
				OBJECT
			);
		} elseif ( 'name' === $orderby ) {
			$items = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM `' . self::table() . '` WHERE (%s = %s OR status = %s) AND (%s = %s OR name LIKE %s OR email LIKE %s OR subject LIKE %s OR message LIKE %s) ORDER BY name DESC LIMIT %d OFFSET %d',
					$query_params
				),
				OBJECT
			);
		} elseif ( 'email' === $orderby && 'ASC' === $order ) {
			$items = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM `' . self::table() . '` WHERE (%s = %s OR status = %s) AND (%s = %s OR name LIKE %s OR email LIKE %s OR subject LIKE %s OR message LIKE %s) ORDER BY email ASC LIMIT %d OFFSET %d',
					$query_params
				),
				OBJECT
			);
		} elseif ( 'email' === $orderby ) {
			$items = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM `' . self::table() . '` WHERE (%s = %s OR status = %s) AND (%s = %s OR name LIKE %s OR email LIKE %s OR subject LIKE %s OR message LIKE %s) ORDER BY email DESC LIMIT %d OFFSET %d',
					$query_params
				),
				OBJECT
			);
		} elseif ( 'status' === $orderby && 'ASC' === $order ) {
			$items = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM `' . self::table() . '` WHERE (%s = %s OR status = %s) AND (%s = %s OR name LIKE %s OR email LIKE %s OR subject LIKE %s OR message LIKE %s) ORDER BY status ASC LIMIT %d OFFSET %d',
					$query_params
				),
				OBJECT
			);
		} elseif ( 'status' === $orderby ) {
			$items = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM `' . self::table() . '` WHERE (%s = %s OR status = %s) AND (%s = %s OR name LIKE %s OR email LIKE %s OR subject LIKE %s OR message LIKE %s) ORDER BY status DESC LIMIT %d OFFSET %d',
					$query_params
				),
				OBJECT
			);
		} elseif ( 'ASC' === $order ) {
			$items = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM `' . self::table() . '` WHERE (%s = %s OR status = %s) AND (%s = %s OR name LIKE %s OR email LIKE %s OR subject LIKE %s OR message LIKE %s) ORDER BY created_at ASC LIMIT %d OFFSET %d',
					$query_params
				),
				OBJECT
			);
		} else {
			$items = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM `' . self::table() . '` WHERE (%s = %s OR status = %s) AND (%s = %s OR name LIKE %s OR email LIKE %s OR subject LIKE %s OR message LIKE %s) ORDER BY created_at DESC LIMIT %d OFFSET %d',
					$query_params
				),
				OBJECT
			);
		}

		$pages = $total > 0 ? ceil( $total / $per_page ) : 0;

		return [
			'items' => $items ?: [],
			'total' => $total,
			'pages' => $pages,
		];
	}

	/**
	 * Get a single submission by ID.
	 *
	 * @param int $id Submission ID.
	 * @return object|null Submission object or null if not found.
	 */
	public static function get_submission($id) {
		global $wpdb;
		$id = absint($id);

		$submission = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM `' . self::table() . '` WHERE id = %d',
				$id
			),
			OBJECT
		);

		return $submission ?: null;
	}

	/**
	 * Update submission status.
	 *
	 * @param int    $id     Submission ID.
	 * @param string $status New status (new, read, replied).
	 * @return bool True on success, false on failure.
	 */
	public static function update_status($id, $status) {
		global $wpdb;

		$table_name = self::table();
		$id = absint($id);
		$allowed_statuses = ['new', 'read', 'replied'];
		$status = in_array($status, $allowed_statuses, true) ? $status : 'new';

		$update_data = ['status' => $status];

		if ($status === 'read' && $status !== 'replied') {
			$update_data['read_at'] = current_time('mysql');
		} elseif ($status === 'replied') {
			$update_data['replied_at'] = current_time('mysql');
			$read_at = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT read_at FROM `' . self::table() . '` WHERE id = %d',
					$id
				)
			);
			if ( empty( $read_at ) ) {
				$update_data['read_at'] = current_time('mysql');
			}
		}

		$result = $wpdb->update(
			$table_name,
			$update_data,
			['id' => $id],
			['%s', '%s'],
			['%d']
		);

		return $result !== false;
	}

	/**
	 * Delete a submission.
	 *
	 * @param int $id Submission ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_submission($id) {
		global $wpdb;

		$table_name = self::table();
		$id = absint($id);

		$result = $wpdb->delete(
			$table_name,
			['id' => $id],
			['%d']
		);

		return $result !== false;
	}

	/**
	 * Get count of unread submissions.
	 *
	 * @return int Number of unread submissions.
	 */
	public static function get_unread_count() {
		global $wpdb;
		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM `' . self::table() . '` WHERE status = %s',
				'new'
			)
		);

		return (int) $count;
	}
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter
}
