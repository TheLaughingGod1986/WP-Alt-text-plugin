<?php
/**
 * Phase 16 — Distribution & growth: metrics anchor, compliant review prompts, telemetry hooks.
 *
 * Does not alter onboarding UI, monetisation, or product features — adds parallel growth layer only.
 *
 * @package BeepBeep_AI
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Growth engine bootstrap (admin).
 */
class Growth_Engine {

	public const OPTION_INSTALLED_AT      = 'bbai_growth_installed_at';
	public const USER_SNOOZE_META         = 'bbai_growth_review_snooze_until';
	public const USER_REVIEW_LEFT_META    = 'bbai_growth_review_left';

	/** @var bool */
	private static $bootstrapped = false;

	/**
	 * Register hooks (idempotent).
	 */
	public static function init(): void {
		if ( self::$bootstrapped ) {
			return;
		}
		self::$bootstrapped = true;

		add_action( 'admin_init', [ self::class, 'handle_dismiss_requests' ], 5 );
		add_action( 'admin_notices', [ self::class, 'maybe_output_review_notice' ] );
	}

	/**
	 * Record first install time once (called from Core::activate).
	 */
	public static function record_install_timestamp(): void {
		if ( false !== get_option( self::OPTION_INSTALLED_AT, false ) ) {
			return;
		}
		update_option( self::OPTION_INSTALLED_AT, time(), false );
	}

	/**
	 * @return int Unix timestamp or 0.
	 */
	public static function get_install_timestamp(): int {
		$t = (int) get_option( self::OPTION_INSTALLED_AT, 0 );
		return $t > 0 ? $t : 0;
	}

	/**
	 * Whether current admin screen is a BeepBeep AI plugin page.
	 */
	public static function is_bbai_admin_screen(): bool {
		if ( ! is_admin() ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Routing only.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		return '' !== $page && strpos( $page, 'bbai' ) === 0;
	}

	/**
	 * Snooze / permanent dismiss via query args + nonce.
	 */
	public static function handle_dismiss_requests(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['bbai_growth_review'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = sanitize_key( wp_unslash( $_GET['bbai_growth_review'] ) );
		if ( ! in_array( $action, [ 'snooze', 'done' ], true ) ) {
			return;
		}
		check_admin_referer( 'bbai_growth_review_' . $action );

		$uid = get_current_user_id();
		if ( $uid <= 0 ) {
			return;
		}

		if ( 'done' === $action ) {
			update_user_meta( $uid, self::USER_REVIEW_LEFT_META, 1 );
		} else {
			$days = (int) apply_filters( 'bbai_growth_review_snooze_days', 90 );
			$days = max( 30, min( 365, $days ) );
			update_user_meta( $uid, self::USER_SNOOZE_META, time() + $days * DAY_IN_SECONDS );
		}

		if ( function_exists( 'bbai_telemetry_emit' ) ) {
			bbai_telemetry_emit(
				'growth_review_notice_dismissed',
				[
					'action' => $action,
				]
			);
		}

		wp_safe_redirect( self::current_bbai_admin_return_url() );
		exit;
	}

	/**
	 * Satisfied-user heuristic: enough usage to ask for a review (no incentives).
	 */
	public static function user_shows_engagement(): bool {
		if ( ! class_exists( Usage_Tracker::class ) ) {
			return false;
		}
		$usage = Usage_Tracker::get_cached_usage();
		$used  = max( 0, (int) ( $usage['used'] ?? 0 ) );
		$min   = (int) apply_filters( 'bbai_growth_review_min_credits_used', 8 );
		if ( $used >= max( 1, $min ) ) {
			return true;
		}
		$uid = get_current_user_id();
		if ( $uid <= 0 ) {
			return false;
		}
		$first = (int) get_user_meta( $uid, 'bbai_telemetry_first_alt_at', true );
		return $first > 0;
	}

	/**
	 * Output a single admin notice on plugin screens (compliant, dismissible, snooze).
	 */
	public static function maybe_output_review_notice(): void {
		if ( ! apply_filters( 'bbai_growth_review_notice_enabled', true ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) || ! self::is_bbai_admin_screen() ) {
			return;
		}

		$uid = get_current_user_id();
		if ( $uid <= 0 ) {
			return;
		}

		if ( (int) get_user_meta( $uid, self::USER_REVIEW_LEFT_META, true ) ) {
			return;
		}

		$snooze_until = (int) get_user_meta( $uid, self::USER_SNOOZE_META, true );
		if ( $snooze_until > time() ) {
			return;
		}

		$installed = self::get_install_timestamp();
		if ( $installed <= 0 ) {
			return;
		}

		$min_days = (int) apply_filters( 'bbai_growth_review_min_days_since_install', 10 );
		$min_days = max( 3, min( 90, $min_days ) );
		if ( ( time() - $installed ) < $min_days * DAY_IN_SECONDS ) {
			return;
		}

		if ( ! self::user_shows_engagement() ) {
			return;
		}

		$review_url = 'https://wordpress.org/support/plugin/beepbeep-ai-alt-text-generator/reviews/?rate=5#new-post';
		$base_return = self::current_bbai_admin_return_url();
		$snooze_url  = wp_nonce_url(
			add_query_arg( 'bbai_growth_review', 'snooze', $base_return ),
			'bbai_growth_review_snooze'
		);
		$done_url    = wp_nonce_url(
			add_query_arg( 'bbai_growth_review', 'done', $base_return ),
			'bbai_growth_review_done'
		);

		$t_key = 'bbai_growth_notice_tel_' . $uid . '_' . gmdate( 'Ymd' );
		if ( function_exists( 'bbai_telemetry_emit' ) && ! get_transient( $t_key ) ) {
			set_transient( $t_key, 1, DAY_IN_SECONDS );
			bbai_telemetry_emit(
				'growth_review_admin_notice_shown',
				[
					'screen' => 'bbai_admin',
				]
			);
		}

		echo '<div class="notice notice-info bbai-growth-review-notice" data-bbai-growth-review-notice="1"><p>';
		echo esc_html(
			__(
				'If BeepBeep AI saves you time on ALT text and image SEO, a short WordPress.org review helps other site owners find the plugin. Thank you for considering it — no obligation.',
				'beepbeep-ai-alt-text-generator'
			)
		);
		echo '</p><p>';
		printf(
			'<a href="%1$s" class="button button-primary" target="_blank" rel="noopener noreferrer">%2$s</a> ',
			esc_url( $review_url ),
			esc_html__( 'Leave a review on WordPress.org', 'beepbeep-ai-alt-text-generator' )
		);
		printf(
			'<a href="%1$s" class="button button-link">%2$s</a> ',
			esc_url( $snooze_url ),
			esc_html__( 'Remind me later', 'beepbeep-ai-alt-text-generator' )
		);
		printf(
			'<a href="%1$s" class="button button-link">%2$s</a>',
			esc_url( $done_url ),
			esc_html__( 'I already left a review', 'beepbeep-ai-alt-text-generator' )
		);
		echo '</p></div>';
	}

	/**
	 * Safe return URL for the current BB admin tab (avoids arbitrary redirect).
	 */
	public static function current_bbai_admin_return_url(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'bbai';
		if ( '' === $page || strpos( $page, 'bbai' ) !== 0 ) {
			$page = 'bbai';
		}
		return admin_url( 'admin.php?page=' . rawurlencode( $page ) );
	}
}

