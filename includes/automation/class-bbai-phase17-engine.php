<?php
/**
 * Phase 17 — Telemetry-driven automation, in-app tips, optional email hooks, milestones.
 *
 * Does not modify Phase 14 retention rules or Phase 15 monetisation matrices.
 *
 * @package BeepBeep_AI
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Phase17_Engine {

	private static $bootstrapped = false;

	public static function init(): void {
		if ( self::$bootstrapped ) {
			return;
		}
		self::$bootstrapped = true;

		add_action( 'admin_init', [ self::class, 'maybe_handle_dismiss' ], 5 );
		add_action( 'bbai_telemetry_event', [ self::class, 'on_telemetry' ], 15, 1 );
		add_action( 'admin_notices', [ self::class, 'maybe_automation_notice' ] );
		add_action( 'admin_footer', [ self::class, 'render_assistant_mount' ], 99 );
	}

	/**
	 * @param array<string, mixed> $envelope From BBAI_Telemetry::emit.
	 */
	public static function on_telemetry( array $envelope ): void {
		$event = isset( $envelope['event'] ) ? sanitize_key( (string) $envelope['event'] ) : '';
		$props = isset( $envelope['properties'] ) && is_array( $envelope['properties'] ) ? $envelope['properties'] : [];

		/**
		 * Full Phase 17 hook for external automation (CRM, email, Slack).
		 *
		 * @param string               $event    Event name.
		 * @param array<string, mixed> $props    Event properties.
		 * @param array<string, mixed> $envelope Full envelope.
		 */
		do_action( 'bbai_phase17_telemetry', $event, $props, $envelope );

		if ( ! apply_filters( 'bbai_phase17_in_app_automation_enabled', true ) ) {
			return;
		}

		$uid = get_current_user_id();
		if ( $uid <= 0 ) {
			return;
		}

		// Positive QA engagement → WordPress.org review prompt (once, dismissible).
		if ( 'alt_marked_reviewed' === $event ) {
			$delta = 1;
			if ( isset( $props['scope'] ) && 'batch' === sanitize_key( (string) $props['scope'] ) && isset( $props['approved_count'] ) ) {
				$delta = max( 1, absint( $props['approved_count'] ) );
			}
			$rcount = (int) get_user_meta( $uid, 'bbai_phase17_mark_reviewed_count', true );
			$rcount += $delta;
			update_user_meta( $uid, 'bbai_phase17_mark_reviewed_count', $rcount );

			$threshold = (int) apply_filters( 'bbai_phase17_review_prompt_review_threshold', 5 );
			if ( $threshold > 0 && $rcount >= $threshold && ! get_user_meta( $uid, 'bbai_phase17_review_prompt_dismissed', true ) ) {
				$review_key = 'bbai_phase17_review_prompt_' . $uid;
				if ( false === get_transient( $review_key ) ) {
					set_transient(
						$review_key,
						wp_json_encode(
							[
								'text'  => __( 'You’ve been polishing ALT quality — thanks for taking accessibility seriously. If BeepBeep saves you time, a quick review on WordPress.org helps other site owners find the plugin.', 'beepbeep-ai-alt-text-generator' ),
								'until' => time() + WEEK_IN_SECONDS,
								'tier'  => 'review_prompt',
								'links' => [
									[
										'url'   => 'https://wordpress.org/support/plugin/beepbeep-ai-alt-text-generator/reviews/#new-post',
										'label' => __( 'Leave a review on WordPress.org', 'beepbeep-ai-alt-text-generator' ),
									],
								],
							]
						),
						WEEK_IN_SECONDS
					);
				}
			}
		}

		// Library filter toward weak / needs-review → one-time nudge (tip only if no other tip is queued).
		if ( 'filter_changed' === $event ) {
			$state = isset( $props['filter_state'] ) ? sanitize_key( (string) $props['filter_state'] ) : '';
			if ( in_array( $state, [ 'needs_review', 'weak' ], true ) && ! get_user_meta( $uid, 'bbai_phase17_filter_weak_nudge_done', true ) ) {
				update_user_meta( $uid, 'bbai_phase17_filter_weak_nudge_done', 1 );
				$nudge_key = 'bbai_phase17_admin_tip_' . $uid;
				if ( false === get_transient( $nudge_key ) ) {
					set_transient(
						$nudge_key,
						wp_json_encode(
							[
								'text'  => __( 'Nice — you’re focusing on weaker ALT text. Regenerate row-by-row, use “Improve ALT” for a focused pass, or edit manually, then mark reviewed when it’s accurate.', 'beepbeep-ai-alt-text-generator' ),
								'until' => time() + DAY_IN_SECONDS,
								'tier'  => 'filter_needs_review',
							]
						),
						DAY_IN_SECONDS
					);
				}
			}
		}

		// Engagement milestones (generation-related client events re-emitted server-side).
		if ( in_array( $event, [ 'alt_generate_clicked', 'row_action_clicked', 'batch_complete', 'batchComplete' ], true ) ) {
			$count = (int) get_user_meta( $uid, 'bbai_phase17_gen_signal_count', true );
			++$count;
			update_user_meta( $uid, 'bbai_phase17_gen_signal_count', $count );

			$milestones = apply_filters( 'bbai_phase17_engagement_milestones', [ 10, 25, 50, 100 ] );
			foreach ( (array) $milestones as $m ) {
				$m = absint( $m );
				if ( $m <= 0 ) {
					continue;
				}
				if ( $count !== $m ) {
					continue;
				}
				$flag = 'bbai_phase17_milestone_shown_' . $m;
				if ( get_user_meta( $uid, $flag, true ) ) {
					continue;
				}
				update_user_meta( $uid, $flag, 1 );
				/**
				 * Fired once per user per milestone count.
				 *
				 * @param int $m   Milestone.
				 * @param int $uid User ID.
				 */
				do_action( 'bbai_phase17_engagement_milestone', $m, $uid );

				$payload = self::milestone_tip_payload( $m );
				if ( ! empty( $payload['text'] ) || ! empty( $payload['links'] ) ) {
					$data = array_merge(
						[
							'until' => time() + DAY_IN_SECONDS,
							'tier'  => 'milestone_' . $m,
						],
						$payload
					);
					set_transient(
						'bbai_phase17_admin_tip_' . $uid,
						wp_json_encode( $data ),
						DAY_IN_SECONDS
					);
				}
			}
		}

		// Upgrade interest → optional email (off by default).
		if ( 'upgrade_cta_clicked' === $event && apply_filters( 'bbai_phase17_email_on_upgrade_interest', false ) ) {
			$admin = get_option( 'admin_email' );
			if ( is_email( $admin ) ) {
				wp_mail(
					$admin,
					'[BeepBeep AI] Upgrade CTA clicked (site)',
					sprintf(
						"Event: upgrade_cta_clicked\nUser ID: %d\nProps: %s\n",
						$uid,
						wp_json_encode( $props )
					)
				);
			}
		}
	}

	/**
	 * @return array{text?:string,links?:array<int,array{url:string,label:string}>}
	 */
	private static function milestone_tip_payload( int $m ): array {
		switch ( $m ) {
			case 10:
				return [
					'text' => __( 'Nice momentum — you’re using bulk ALT fixes. Enable on-upload automation in Settings if you want new images covered automatically (plan permitting).', 'beepbeep-ai-alt-text-generator' ),
				];
			case 25:
				return [
					'text' => __( 'You’ve run many generations: check the “Needs review” filter occasionally to keep quality high.', 'beepbeep-ai-alt-text-generator' ),
				];
			case 50:
				return [
					'text' => __( 'Strong usage. If credits feel tight, review Usage & plan — higher tiers are built for steady libraries and stores.', 'beepbeep-ai-alt-text-generator' ),
				];
			case 100:
				return [
					'text'  => __( 'Power-user territory. Consider periodic scans so new uploads never slip through.', 'beepbeep-ai-alt-text-generator' ),
					'links' => [
						[
							'url'   => 'https://wordpress.org/support/plugin/beepbeep-ai-alt-text-generator/reviews/#new-post',
							'label' => __( 'Share feedback on WordPress.org (optional)', 'beepbeep-ai-alt-text-generator' ),
						],
					],
				];
			default:
				return [];
		}
	}

	public static function is_bbai_admin_screen(): bool {
		if ( ! is_admin() ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		return '' !== $page && strpos( $page, 'bbai' ) === 0;
	}

	public static function maybe_automation_notice(): void {
		if ( ! self::is_bbai_admin_screen() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$uid = get_current_user_id();
		if ( $uid <= 0 ) {
			return;
		}
		self::render_notice_from_transient( 'bbai_phase17_review_prompt_' . $uid, 'review' );
		self::render_notice_from_transient( 'bbai_phase17_admin_tip_' . $uid, 'tip' );
	}

	/**
	 * @param string $key     Transient name.
	 * @param string $kind    tip|review (dismiss behaviour).
	 */
	private static function render_notice_from_transient( string $key, string $kind ): void {
		$raw = get_transient( $key );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return;
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			delete_transient( $key );
			return;
		}
		$text = isset( $data['text'] ) ? (string) $data['text'] : '';
		$links = isset( $data['links'] ) && is_array( $data['links'] ) ? $data['links'] : [];
		if ( '' === $text && empty( $links ) ) {
			delete_transient( $key );
			return;
		}
		$dismiss = wp_nonce_url(
			add_query_arg(
				[
					'bbai_phase17_dismiss_tip' => '1',
					'bbai_phase17_tip_kind'    => $kind,
				],
				self::current_return_url()
			),
			'bbai_phase17_dismiss_tip'
		);
		echo '<div class="notice notice-success is-dismissible bbai-phase17-tip" data-bbai-phase17-tip="1" data-bbai-phase17-tip-kind="' . esc_attr( $kind ) . '">';
		if ( '' !== $text ) {
			echo '<p>' . esc_html( $text ) . '</p>';
		}
		foreach ( $links as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$u = isset( $row['url'] ) ? esc_url_raw( (string) $row['url'] ) : '';
			$l = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';
			if ( '' === $u || '' === $l ) {
				continue;
			}
			echo '<p><a class="button button-primary" href="' . esc_url( $u ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $l ) . '</a></p>';
		}
		echo '<p><a href="' . esc_url( $dismiss ) . '" class="button button-small">' . esc_html__( 'Dismiss', 'beepbeep-ai-alt-text-generator' ) . '</a></p></div>';
	}

	/**
	 * Dismiss tip early.
	 */
	public static function maybe_handle_dismiss(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['bbai_phase17_dismiss_tip'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'bbai_phase17_dismiss_tip' );
		$uid = get_current_user_id();
		if ( $uid > 0 ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$kind = isset( $_GET['bbai_phase17_tip_kind'] ) ? sanitize_key( wp_unslash( $_GET['bbai_phase17_tip_kind'] ) ) : 'tip';
			if ( 'review' === $kind ) {
				delete_transient( 'bbai_phase17_review_prompt_' . $uid );
				update_user_meta( $uid, 'bbai_phase17_review_prompt_dismissed', 1 );
			} else {
				delete_transient( 'bbai_phase17_admin_tip_' . $uid );
			}
		}
		wp_safe_redirect( self::current_return_url() );
		exit;
	}

	private static function current_return_url(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'bbai';
		if ( '' === $page || strpos( $page, 'bbai' ) !== 0 ) {
			$page = 'bbai';
		}
		return admin_url( 'admin.php?page=' . rawurlencode( $page ) );
	}

	public static function render_assistant_mount(): void {
		if ( ! self::is_bbai_admin_screen() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! apply_filters( 'bbai_phase17_assistant_ui_enabled', true ) ) {
			return;
		}
		echo '<div id="bbai-phase17-assistant" class="bbai-phase17-assistant" data-bbai-phase17-assistant="1" aria-live="polite"></div>';
	}
}
