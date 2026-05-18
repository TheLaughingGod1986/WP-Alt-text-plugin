<?php
/**
 * Email lifecycle hooks and queue foundation.
 *
 * This intentionally queues lifecycle intents only. Template rendering and ESP
 * delivery can subscribe to the hooks without coupling product events to mail.
 *
 * @package BeepBeep_AI
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Event-driven lifecycle email queue.
 */
class BBAI_Email_Lifecycle {

	private const CRON_HOOK = 'bbai_email_lifecycle_send';

	private const LAST_SENT_META_PREFIX = '_bbai_email_lifecycle_last_';

	private const DEFAULT_COOLDOWN = DAY_IN_SECONDS;

	/**
	 * Wire hooks.
	 */
	public static function init(): void {
		add_action( 'bbai_telemetry_event', [ self::class, 'on_telemetry_event' ], 20, 1 );
		add_action( 'bbai_retention_maybe_email_digest', [ self::class, 'on_retention_digest' ], 20, 1 );
		add_action( self::CRON_HOOK, [ self::class, 'send_queued' ], 10, 1 );
		add_action( 'admin_post_bbai_email_open', [ self::class, 'track_open' ] );
		add_action( 'admin_post_nopriv_bbai_email_open', [ self::class, 'track_open' ] );
		add_action( 'admin_post_bbai_email_click', [ self::class, 'track_click' ] );
		add_action( 'admin_post_nopriv_bbai_email_click', [ self::class, 'track_click' ] );
	}

	/**
	 * Map product telemetry into lifecycle email intents.
	 *
	 * @param array<string,mixed> $envelope Telemetry envelope.
	 */
	public static function on_telemetry_event( array $envelope ): void {
		$event = isset( $envelope['event'] ) ? sanitize_key( (string) $envelope['event'] ) : '';
		$user_id = isset( $envelope['user_id'] ) ? max( 0, (int) $envelope['user_id'] ) : get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}

		$props = isset( $envelope['properties'] ) && is_array( $envelope['properties'] ) ? $envelope['properties'] : [];
		switch ( $event ) {
			case 'signup_succeeded':
				self::queue( 'welcome', $user_id, [ 'trigger_event' => $event ], 5 * MINUTE_IN_SECONDS );
				break;

			case 'plugin_opened':
				$remaining = isset( $props['credits_remaining'] ) ? max( 0, (int) $props['credits_remaining'] ) : null;
				if ( null !== $remaining && $remaining > 0 ) {
					self::queue( 'free_credits_remaining', $user_id, [ 'credits_remaining' => $remaining ], 6 * HOUR_IN_SECONDS );
				}
				break;

			case 'generation_completed':
			case 'success_state_shown':
				$ready = isset( $props['review_count'] ) ? max( 0, (int) $props['review_count'] ) : max( 0, (int) ( $props['ready_for_review_count'] ?? 0 ) );
				if ( $ready > 0 ) {
					self::queue( 'images_ready_for_review', $user_id, [ 'ready_for_review_count' => $ready ], 15 * MINUTE_IN_SECONDS );
				}
				break;

			case 'quota_exhausted_state_shown':
			case 'quota_blocked':
				self::queue( 'nearly_out_of_credits', $user_id, [ 'trigger_event' => $event ], 30 * MINUTE_IN_SECONDS );
				break;

			case 'new_uploads_detected':
				$count = max( 0, (int) ( $props['new_uploads_count'] ?? $props['new_images_count'] ?? 0 ) );
				self::queue( 'new_uploads_detected', $user_id, [ 'new_uploads_count' => $count ], HOUR_IN_SECONDS );
				break;
		}
	}

	/**
	 * Retention model hook for users who left actionable images behind.
	 *
	 * @param array<string,mixed> $model Retention model.
	 */
	public static function on_retention_digest( array $model ): void {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}
		$issues = max( 0, (int) ( $model['telemetry']['issues'] ?? 0 ) );
		if ( $issues <= 0 ) {
			return;
		}
		self::queue(
			'free_credits_remaining',
			$user_id,
			[
				'trigger_event' => 'retention_digest',
				'issues'        => $issues,
			],
			2 * HOUR_IN_SECONDS
		);
	}

	/**
	 * Queue a lifecycle email intent with cooldown dedupe.
	 *
	 * @param string              $type Email intent type.
	 * @param int                 $user_id WP user id.
	 * @param array<string,mixed> $context Safe context.
	 * @param int                 $delay Delay in seconds.
	 */
	public static function queue( string $type, int $user_id, array $context = [], int $delay = 0 ): bool {
		$type = sanitize_key( $type );
		$user_id = max( 0, $user_id );
		if ( '' === $type || $user_id <= 0 ) {
			return false;
		}

		$allowed = [
			'welcome',
			'free_credits_remaining',
			'images_ready_for_review',
			'nearly_out_of_credits',
			'new_uploads_detected',
		];
		if ( ! in_array( $type, $allowed, true ) ) {
			return false;
		}

		$cooldown = (int) apply_filters( 'bbai_email_lifecycle_cooldown', self::DEFAULT_COOLDOWN, $type, $user_id );
		$last = (int) get_user_meta( $user_id, self::LAST_SENT_META_PREFIX . $type, true );
		if ( $last > 0 && ( time() - $last ) < $cooldown ) {
			return false;
		}

		$payload = [
			'type'       => $type,
			'user_id'    => $user_id,
			'context'    => self::sanitize_context( $context ),
			'queued_at'  => time(),
			'message_id' => wp_generate_uuid4(),
		];

		$timestamp = time() + max( 0, $delay );
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( $timestamp, self::CRON_HOOK, [ $payload ], 'bbai-email-lifecycle' );
		} else {
			wp_schedule_single_event( $timestamp, self::CRON_HOOK, [ $payload ] );
		}

		return true;
	}

	/**
	 * Process queued intent. Delivery implementations should hook this action.
	 *
	 * @param array<string,mixed> $payload Queue payload.
	 */
	public static function send_queued( array $payload ): void {
		$type = isset( $payload['type'] ) ? sanitize_key( (string) $payload['type'] ) : '';
		$user_id = isset( $payload['user_id'] ) ? max( 0, (int) $payload['user_id'] ) : 0;
		if ( '' === $type || $user_id <= 0 ) {
			return;
		}

		$payload['context'] = isset( $payload['context'] ) && is_array( $payload['context'] )
			? self::sanitize_context( $payload['context'] )
			: [];

		/**
		 * ESP/template integration point. Do not include raw email in telemetry.
		 *
		 * @param array<string,mixed> $payload Queue payload.
		 */
		do_action( 'bbai_email_lifecycle_send_requested', $payload );

		update_user_meta( $user_id, self::LAST_SENT_META_PREFIX . $type, time() );
		BBAI_Telemetry::emit(
			'email_sent',
			[
				'email_type' => $type,
				'message_id' => (string) ( $payload['message_id'] ?? '' ),
			]
		);
	}

	/**
	 * Track open beacon.
	 */
	public static function track_open(): void {
		// Email tracking pixels are public endpoints; no nonce is available in the request.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$message_id = isset( $_GET['mid'] ) ? sanitize_text_field( wp_unslash( $_GET['mid'] ) ) : '';
		BBAI_Telemetry::emit( 'email_opened', [ 'email_type' => $type, 'message_id' => $message_id ] );
		nocache_headers();
		header( 'Content-Type: image/gif' );
		echo base64_decode( 'R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Track click then redirect to a safe admin destination.
	 */
	public static function track_click(): void {
		// Email click tracking is a public endpoint; values are sanitized and destinations are allowlisted.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$message_id = isset( $_GET['mid'] ) ? sanitize_text_field( wp_unslash( $_GET['mid'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$dest = isset( $_GET['dest'] ) ? sanitize_key( wp_unslash( $_GET['dest'] ) ) : 'dashboard';
		BBAI_Telemetry::emit( 'email_clicked', [ 'email_type' => $type, 'message_id' => $message_id, 'destination' => $dest ] );

		$targets = [
			'dashboard' => admin_url( 'admin.php?page=bbai' ),
			'library'   => admin_url( 'admin.php?page=bbai-library&status=needs_review&filter=needs-review' ),
			'usage'     => admin_url( 'admin.php?page=bbai-credit-usage' ),
			'settings'  => admin_url( 'admin.php?page=bbai-settings' ),
		];
		wp_safe_redirect( $targets[ $dest ] ?? $targets['dashboard'] );
		exit;
	}

	/**
	 * @param array<string,mixed> $context Context.
	 * @return array<string,scalar>
	 */
	private static function sanitize_context( array $context ): array {
		$out = [];
		foreach ( $context as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key || preg_match( '/(^|_)(password|token|jwt|secret|nonce|api_?key|license_?key|email|site_?url)($|_)/', $key ) ) {
				continue;
			}
			if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
				$out[ $key ] = $value;
			} elseif ( is_string( $value ) ) {
				$out[ $key ] = mb_substr( sanitize_text_field( $value ), 0, 160 );
			}
		}
		return $out;
	}
}
