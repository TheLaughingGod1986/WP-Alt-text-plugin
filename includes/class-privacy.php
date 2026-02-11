<?php
/**
 * Privacy tools integration for WordPress personal data export/erasure.
 *
 * @package BeepBeepAI\AltTextGenerator
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Privacy {
	private const EXPORTER_ID = 'beepbeep-ai-alt-text-generator';
	private const ERASER_ID   = 'beepbeep-ai-alt-text-generator';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter -- SQL identifiers are controlled by plugin schema; runtime values are prepared.

	/**
	 * Register exporters and erasers.
	 */
	public static function init(): void {
		add_filter( 'wp_privacy_personal_data_exporters', [ __CLASS__, 'register_exporter' ] );
		add_filter( 'wp_privacy_personal_data_erasers', [ __CLASS__, 'register_eraser' ] );
	}

	/**
	 * Register personal data exporter.
	 *
	 * @param array $exporters Existing exporters.
	 * @return array
	 */
	public static function register_exporter( array $exporters ): array {
		$exporters[ self::EXPORTER_ID ] = [
			'exporter_friendly_name' => __( 'BeepBeep AI – Alt Text Generator', 'opptiai-alt' ),
			'callback'               => [ __CLASS__, 'exporter' ],
		];

		return $exporters;
	}

	/**
	 * Register personal data eraser.
	 *
	 * @param array $erasers Existing erasers.
	 * @return array
	 */
	public static function register_eraser( array $erasers ): array {
		$erasers[ self::ERASER_ID ] = [
			'eraser_friendly_name' => __( 'BeepBeep AI – Alt Text Generator', 'opptiai-alt' ),
			'callback'             => [ __CLASS__, 'eraser' ],
		];

		return $erasers;
	}

	/**
	 * Export personal data for a given email address.
	 *
	 * @param string $email_address Email address.
	 * @param int    $page          Page number.
	 * @return array
	 */
	public static function exporter( string $email_address, int $page = 1 ): array {
		$data = [];
		$done = true;

		$user = get_user_by( 'email', $email_address );
		$user_id = $user ? (int) $user->ID : 0;

		$data = array_merge( $data, self::export_contact_submissions( $email_address, $user_id, $page, $done ) );
		if ( $page <= 1 ) {
			$data = array_merge( $data, self::export_account_data( $email_address ) );
			$data = array_merge( $data, self::export_usage_summary( $user_id ) );
		}

		return [
			'data' => $data,
			'done' => $done,
		];
	}

	/**
	 * Erase personal data for a given email address.
	 *
	 * @param string $email_address Email address.
	 * @param int    $page          Page number.
	 * @return array
	 */
	public static function eraser( string $email_address, int $page = 1 ): array {
		$items_removed  = false;
		$items_retained = false;
		$messages       = [];
		$done           = true;

		$user = get_user_by( 'email', $email_address );
		$user_id = $user ? (int) $user->ID : 0;

		$contact_deleted = self::erase_contact_submissions( $email_address, $user_id, $page, $done );
		if ( $contact_deleted ) {
			$items_removed = true;
		}

		if ( $user_id > 0 ) {
			$usage_removed = self::anonymize_usage_logs( $user_id );
			if ( $usage_removed ) {
				$items_removed = true;
			}
		}

		$account_removed = self::erase_account_data( $email_address );
		if ( $account_removed ) {
			$items_removed = true;
		}

		if ( ! $items_removed && ! $items_retained ) {
			$messages[] = __( 'No BeepBeep AI data found for this email address.', 'opptiai-alt' );
		}

		return [
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => $messages,
			'done'           => $done,
		];
	}

	/**
	 * Export contact submissions (paged).
	 */
	private static function export_contact_submissions( string $email_address, int $user_id, int $page, bool &$done ): array {
		global $wpdb;

		self::load_class( '\BeepBeepAI\AltTextGenerator\Contact_Submissions', 'includes/class-contact-submissions.php' );
		if ( ! class_exists( '\BeepBeepAI\AltTextGenerator\Contact_Submissions' ) ) {
			return [];
		}
		$exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', Contact_Submissions::table() )
		);
		if ( $exists !== Contact_Submissions::table() ) {
			return [];
		}

		$page_size = 50;
		$offset = ( max( 1, $page ) - 1 ) * $page_size;

		if ( $email_address === '' && $user_id <= 0 ) {
			return [];
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM `' . Contact_Submissions::table() . '` WHERE ((%s <> %s AND email = %s) OR (%d > %d AND user_id = %d)) ORDER BY created_at DESC LIMIT %d OFFSET %d',
				$email_address,
				'',
				$email_address,
				$user_id,
				0,
				$user_id,
				$page_size,
				$offset
			)
		);
		if ( empty( $rows ) ) {
			return [];
		}

		if ( count( $rows ) === $page_size ) {
			$done = false;
		}

		$items = [];
		foreach ( $rows as $row ) {
			$items[] = [
				'group_id'    => 'bbai_contact_submissions',
				'group_label' => __( 'BeepBeep AI Contact Submissions', 'opptiai-alt' ),
				'item_id'     => 'submission-' . absint( $row->id ),
				'data'        => [
					[ 'name' => __( 'Name', 'opptiai-alt' ), 'value' => sanitize_text_field( $row->name ?? '' ) ],
					[ 'name' => __( 'Email', 'opptiai-alt' ), 'value' => sanitize_email( $row->email ?? '' ) ],
					[ 'name' => __( 'Subject', 'opptiai-alt' ), 'value' => sanitize_text_field( $row->subject ?? '' ) ],
					[ 'name' => __( 'Message', 'opptiai-alt' ), 'value' => sanitize_textarea_field( $row->message ?? '' ) ],
					[ 'name' => __( 'Submitted At', 'opptiai-alt' ), 'value' => sanitize_text_field( $row->created_at ?? '' ) ],
					[ 'name' => __( 'Site URL', 'opptiai-alt' ), 'value' => esc_url_raw( $row->site_url ?? '' ) ],
				],
			];
		}

		return $items;
	}

	/**
	 * Export connected account data stored in options.
	 */
	private static function export_account_data( string $email_address ): array {
		$items = [];

		$user_data_sources = [
			get_option( 'beepbeepai_user_data', null ),
			get_option( 'opptibbai_user_data', null ),
		];

		foreach ( $user_data_sources as $user_data ) {
			if ( ! is_array( $user_data ) ) {
				continue;
			}
			$stored_email = isset( $user_data['email'] ) ? sanitize_email( $user_data['email'] ) : '';
			if ( $stored_email && strcasecmp( $stored_email, $email_address ) === 0 ) {
				$items[] = [
					'group_id'    => 'bbai_account_data',
					'group_label' => __( 'BeepBeep AI Account Data', 'opptiai-alt' ),
					'item_id'     => 'account-data',
					'data'        => [
						[ 'name' => __( 'Email', 'opptiai-alt' ), 'value' => $stored_email ],
						[ 'name' => __( 'Name', 'opptiai-alt' ), 'value' => sanitize_text_field( $user_data['name'] ?? '' ) ],
						[ 'name' => __( 'Plan', 'opptiai-alt' ), 'value' => sanitize_text_field( $user_data['plan'] ?? '' ) ],
					],
				];
				break;
			}
		}

		return $items;
	}

	/**
	 * Export usage summary for a user.
	 */
	private static function export_usage_summary( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return [];
		}

		global $wpdb;

		$items = [];

		self::load_class( '\BeepBeepAI\AltTextGenerator\Credit_Usage_Logger', 'includes/class-credit-usage-logger.php' );
		if ( class_exists( '\BeepBeepAI\AltTextGenerator\Credit_Usage_Logger' ) ) {
			$exists = $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', Credit_Usage_Logger::table() )
			);
			if ( $exists === Credit_Usage_Logger::table() ) {
				$summary = $wpdb->get_row(
					$wpdb->prepare(
						'SELECT COUNT(*) AS events, SUM(credits_used) AS credits, MAX(generated_at) AS last_event FROM `' . Credit_Usage_Logger::table() . '` WHERE user_id = %d',
						$user_id
					),
					ARRAY_A
				);
				if ( ! empty( $summary ) && (int) $summary['events'] > 0 ) {
					$items[] = [
						'group_id'    => 'bbai_credit_usage',
						'group_label' => __( 'BeepBeep AI Credit Usage', 'opptiai-alt' ),
						'item_id'     => 'credit-usage-' . $user_id,
						'data'        => [
							[ 'name' => __( 'Events', 'opptiai-alt' ), 'value' => (string) intval( $summary['events'] ) ],
							[ 'name' => __( 'Credits Used', 'opptiai-alt' ), 'value' => (string) intval( $summary['credits'] ?? 0 ) ],
							[ 'name' => __( 'Last Activity', 'opptiai-alt' ), 'value' => sanitize_text_field( $summary['last_event'] ?? '' ) ],
						],
					];
				}
			}
		}

		self::load_class( '\BeepBeepAI\AltTextGenerator\Usage\Usage_Logs', 'includes/usage/class-usage-logs.php' );
		if ( class_exists( '\BeepBeepAI\AltTextGenerator\Usage\Usage_Logs' ) ) {
			$exists = $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', \BeepBeepAI\AltTextGenerator\Usage\Usage_Logs::table() )
			);
			if ( $exists === \BeepBeepAI\AltTextGenerator\Usage\Usage_Logs::table() ) {
				$summary = $wpdb->get_row(
					$wpdb->prepare(
						'SELECT COUNT(*) AS events, SUM(tokens_used) AS tokens, MAX(created_at) AS last_event FROM `' . \BeepBeepAI\AltTextGenerator\Usage\Usage_Logs::table() . '` WHERE user_id = %d',
						$user_id
					),
					ARRAY_A
				);
				if ( ! empty( $summary ) && (int) $summary['events'] > 0 ) {
					$items[] = [
						'group_id'    => 'bbai_usage_logs',
						'group_label' => __( 'BeepBeep AI Usage Logs', 'opptiai-alt' ),
						'item_id'     => 'usage-logs-' . $user_id,
						'data'        => [
							[ 'name' => __( 'Events', 'opptiai-alt' ), 'value' => (string) intval( $summary['events'] ) ],
							[ 'name' => __( 'Tokens Used', 'opptiai-alt' ), 'value' => (string) intval( $summary['tokens'] ?? 0 ) ],
							[ 'name' => __( 'Last Activity', 'opptiai-alt' ), 'value' => sanitize_text_field( $summary['last_event'] ?? '' ) ],
						],
					];
				}
			}
		}

		return $items;
	}

	/**
	 * Erase contact submissions (paged).
	 */
	private static function erase_contact_submissions( string $email_address, int $user_id, int $page, bool &$done ): bool {
		global $wpdb;

		self::load_class( '\BeepBeepAI\AltTextGenerator\Contact_Submissions', 'includes/class-contact-submissions.php' );
		if ( ! class_exists( '\BeepBeepAI\AltTextGenerator\Contact_Submissions' ) ) {
			return false;
		}
		$exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', Contact_Submissions::table() )
		);
		if ( $exists !== Contact_Submissions::table() ) {
			return false;
		}

		$page_size = 50;

		if ( $email_address === '' && $user_id <= 0 ) {
			return false;
		}

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM `' . Contact_Submissions::table() . '` WHERE ((%s <> %s AND email = %s) OR (%d > %d AND user_id = %d)) ORDER BY created_at DESC LIMIT %d',
				$email_address,
				'',
				$email_address,
				$user_id,
				0,
				$user_id,
				$page_size
			)
		);
		if ( empty( $ids ) ) {
			return false;
		}

		if ( count( $ids ) === $page_size ) {
			$done = false;
		}

		foreach ( $ids as $id ) {
			$wpdb->delete(
				Contact_Submissions::table(),
				[ 'id' => absint( $id ) ],
				[ '%d' ]
			);
		}

		return true;
	}

	/**
	 * Anonymize usage logs for a given user.
	 */
	private static function anonymize_usage_logs( int $user_id ): bool {
		global $wpdb;

		$updated = false;

		self::load_class( '\BeepBeepAI\AltTextGenerator\Credit_Usage_Logger', 'includes/class-credit-usage-logger.php' );
		if ( class_exists( '\BeepBeepAI\AltTextGenerator\Credit_Usage_Logger' ) ) {
			$table = esc_sql( Credit_Usage_Logger::table() );
			$exists = $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
			);
			if ( $exists === $table ) {
				$rows = $wpdb->update(
					$table,
					[
						'user_id' => 0,
						'deleted_user_original_id' => $user_id,
					],
					[ 'user_id' => $user_id ],
					[ '%d', '%d' ],
					[ '%d' ]
				);
				if ( $rows !== false && $rows > 0 ) {
					$updated = true;
				}
			}
		}

		self::load_class( '\BeepBeepAI\AltTextGenerator\Usage\Usage_Logs', 'includes/usage/class-usage-logs.php' );
		if ( class_exists( '\BeepBeepAI\AltTextGenerator\Usage\Usage_Logs' ) ) {
			$table = esc_sql( \BeepBeepAI\AltTextGenerator\Usage\Usage_Logs::table() );
			$exists = $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
			);
			if ( $exists === $table ) {
				$rows = $wpdb->update(
					$table,
					[ 'user_id' => 0 ],
					[ 'user_id' => $user_id ],
					[ '%d' ],
					[ '%d' ]
				);
				if ( $rows !== false && $rows > 0 ) {
					$updated = true;
				}
			}
		}

		return $updated;
	}

	/**
	 * Erase connected account data stored in options.
	 */
	private static function erase_account_data( string $email_address ): bool {
		$removed = false;

		$user_data_sources = [
			'beepbeepai_user_data' => get_option( 'beepbeepai_user_data', null ),
			'opptibbai_user_data'  => get_option( 'opptibbai_user_data', null ),
		];

		foreach ( $user_data_sources as $key => $user_data ) {
			if ( ! is_array( $user_data ) ) {
				continue;
			}
			$stored_email = isset( $user_data['email'] ) ? sanitize_email( $user_data['email'] ) : '';
			if ( $stored_email && strcasecmp( $stored_email, $email_address ) === 0 ) {
				delete_option( 'beepbeepai_user_data' );
				delete_option( 'beepbeepai_jwt_token' );
				delete_option( 'opptibbai_user_data' );
				delete_option( 'opptibbai_jwt_token' );
				$removed = true;
				break;
			}
		}

		return $removed;
	}

	/**
	 * Load a class file if it has not been loaded yet.
	 */
	private static function load_class( string $class, string $relative_path ): void {
		if ( class_exists( $class ) ) {
			return;
		}
		if ( defined( 'BEEPBEEP_AI_PLUGIN_DIR' ) ) {
			$path = BEEPBEEP_AI_PLUGIN_DIR . ltrim( $relative_path, '/' );
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	}
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter
}
