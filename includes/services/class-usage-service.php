<?php
declare(strict_types=1);

namespace BeepBeep\AltText\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Usage Service
 *
 * Handles usage tracking, quota management, and threshold notifications.
 * Extracted from monolithic BbAI_Core class for better separation of concerns.
 *
 * @package BeepBeep\AltText\Services
 * @since   5.0.0
 */
class Usage_Service {
	/**
	 * API client instance.
	 *
	 * @var \BbAI_API_Client_V2
	 */
	private \BbAI_API_Client_V2 $api_client;

	/**
	 * Option key for plugin settings.
	 *
	 * @var string
	 */
	private const OPTION_KEY = 'beepbeepai_settings';

	/**
	 * Constructor.
	 *
	 * @since 5.0.0
	 *
	 * @param \BbAI_API_Client_V2 $api_client API client.
	 */
	public function __construct( \BbAI_API_Client_V2 $api_client ) {
		$this->api_client = $api_client;
	}

	/**
	 * Refresh usage data.
	 *
	 * Clears cache and fetches fresh usage from API.
	 *
	 * @since 5.0.0
	 *
	 * @return array{success: bool, message?: string, stats?: array} Refresh result.
	 */
	public function refresh_usage(): array {
		// Clear cache.
		if ( class_exists( '\BbAI_Usage_Tracker' ) ) {
			\BbAI_Usage_Tracker::clear_cache();
		}

		// Fetch fresh data.
		$usage = $this->api_client->get_usage();

		if ( $usage ) {
			$stats = \BbAI_Usage_Tracker::get_stats_display();
			return array(
				'success' => true,
				'stats'   => $stats,
			);
		}

		return array(
			'success' => false,
			'message' => __( 'Failed to fetch usage data', 'beepbeep-ai-alt-text-generator' ),
		);
	}

	/**
	 * Get default usage structure.
	 *
	 * @since 5.0.0
	 *
	 * @return array{prompt: int, completion: int, total: int, requests: int, last_request: null} Default usage.
	 */
	public function default_usage(): array {
		return array(
			'prompt'       => 0,
			'completion'   => 0,
			'total'        => 0,
			'requests'     => 0,
			'last_request' => null,
		);
	}

	/**
	 * Record usage locally.
	 *
	 * Updates local usage counters and triggers threshold notifications.
	 *
	 * @since 5.0.0
	 *
	 * @param array $usage Usage data (prompt, completion, total tokens).
	 * @return void
	 */
	public function record_usage( array $usage ): void {
		$prompt     = isset( $usage['prompt'] ) ? max( 0, intval( $usage['prompt'] ) ) : 0;
		$completion = isset( $usage['completion'] ) ? max( 0, intval( $usage['completion'] ) ) : 0;
		$total      = isset( $usage['total'] ) ? max( 0, intval( $usage['total'] ) ) : ( $prompt + $completion );

		if ( ! $prompt && ! $completion && ! $total ) {
			return;
		}

		$opts    = get_option( self::OPTION_KEY, array() );
		$current = $opts['usage'] ?? $this->default_usage();

		$current['prompt']       += $prompt;
		$current['completion']   += $completion;
		$current['total']        += $total;
		$current['requests']     += 1;
		$current['last_request']  = current_time( 'mysql' );

		$opts['usage']            = $current;
		$opts['token_alert_sent'] = $opts['token_alert_sent'] ?? false;
		$opts['token_limit']      = $opts['token_limit'] ?? 0;

		// Check if threshold reached.
		if ( ! empty( $opts['token_limit'] ) && ! $opts['token_alert_sent'] && $current['total'] >= $opts['token_limit'] ) {
			$opts['token_alert_sent'] = true;
			set_transient(
				'beepbeepai_token_notice',
				array(
					'total' => $current['total'],
					'limit' => $opts['token_limit'],
				),
				DAY_IN_SECONDS
			);

			$this->send_threshold_notification( $current['total'], $opts['token_limit'], $opts['notify_email'] ?? get_option( 'admin_email' ) );
		}

		update_option( self::OPTION_KEY, $opts );
	}

	/**
	 * Get usage statistics.
	 *
	 * @since 5.0.0
	 *
	 * @return array Usage statistics.
	 */
	public function get_usage_stats(): array {
		if ( class_exists( '\BbAI_Usage_Tracker' ) ) {
			return \BbAI_Usage_Tracker::get_stats_display();
		}

		return array();
	}

	/**
	 * Check if usage threshold should display notice.
	 *
	 * @since 5.0.0
	 *
	 * @return array|null Threshold notice data or null.
	 */
	public function get_threshold_notice(): ?array {
		$data = get_transient( 'beepbeepai_token_notice' );
		if ( ! $data ) {
			// Fallback to legacy transient name during transition.
			$data = get_transient( 'bbai_token_notice' );
		}

		return $data ?: null;
	}

	/**
	 * Clear usage cache.
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		if ( class_exists( '\BbAI_Usage_Tracker' ) ) {
			\BbAI_Usage_Tracker::clear_cache();
		}
		delete_transient( 'bbai_usage_cache' );
		delete_transient( 'opptibbai_usage_cache' );
	}

	/**
	 * Get usage audit rows from database.
	 *
	 * @since 5.0.0
	 *
	 * @param int  $limit       Number of rows to fetch.
	 * @param bool $include_all Include all columns.
	 * @return array Usage audit rows.
	 */
	public function get_usage_rows( int $limit = 10, bool $include_all = false ): array {
		global $wpdb;
		$limit = max( 1, intval( $limit ) );

		$table_name = $wpdb->prefix . 'bbai_usage';

		// Check if table exists.
		$table_exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		if ( ! $table_exists ) {
			return array();
		}

		if ( $include_all ) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT * FROM {$table_name} ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$limit
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT user_id, tokens_used, action_type, created_at FROM {$table_name} ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$limit
				),
				ARRAY_A
			);
		}

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Send threshold notification email.
	 *
	 * @since 5.0.0
	 *
	 * @param int    $total Total tokens used.
	 * @param int    $limit Token limit.
	 * @param string $email Email address.
	 * @return void
	 */
	private function send_threshold_notification( int $total, int $limit, string $email ): void {
		$subject = __( 'AI Alt Text token usage alert', 'beepbeep-ai-alt-text-generator' );
		$message = sprintf(
			/* translators: 1: total tokens, 2: token limit */
			__( 'Your site has now used %1$d tokens, reaching the configured limit of %2$d.', 'beepbeep-ai-alt-text-generator' ),
			$total,
			$limit
		);

		wp_mail( $email, $subject, $message );
	}
}
