<?php
/**
 * Review Prompt Service.
 *
 * Single source of truth for the WP.org review-prompt eligibility,
 * persistence, and action recording. Designed to be called from both
 * PHP templates (for localization) and AJAX handlers (for recording actions).
 *
 * Storage uses wp_options (site-wide, not per-user) because the review ask
 * is admin-scoped and irrelevant to front-end users.
 *
 * @package BeepBeep_AI
 * @since   5.3.0
 */

namespace BeepBeepAI\AltTextGenerator\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Review_Prompt_Service {

	// ── Option keys ──────────────────────────────────────────────────────────

	/** Unix timestamp — do not show before this time. 0 = no snooze. */
	const OPT_DISMISSED_UNTIL = 'bbai_review_dismissed_until';

	/** bool — user explicitly said they already left a review. */
	const OPT_ALREADY_REVIEWED = 'bbai_review_already_reviewed';

	/** Unix timestamp — when the modal was last shown. */
	const OPT_LAST_SHOWN = 'bbai_review_last_shown';

	/** int — number of successful generation sessions started. */
	const OPT_SESSION_COUNT = 'bbai_review_generation_sessions';

	/** array — list of YYYY-MM-DD strings for days plugin was actively used. */
	const OPT_ACTIVE_DAYS = 'bbai_review_active_days';

	// ── Snooze durations ─────────────────────────────────────────────────────

	const SNOOZE_DISMISS_DAYS      = 7;
	const SNOOZE_REMIND_LATER_DAYS = 14;
	const SNOOZE_ALREADY_REVIEWED_DAYS = 90;

	// ── WP.org review URL ────────────────────────────────────────────────────

	const REVIEW_URL = 'https://wordpress.org/support/plugin/beepbeep-ai-alt-text-generator/reviews/?rate=5#new-post';

	// ── Active days cap (prevent unbounded growth) ───────────────────────────

	const ACTIVE_DAYS_MAX = 60;

	// ──────────────────────────────────────────────────────────────────────────
	// Eligibility
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * Determine if the user has crossed at least one activation milestone.
	 *
	 * @param array{total?: int, optimised?: int} $ctx Live coverage counts.
	 * @return bool
	 */
	public static function is_eligible( array $ctx = [] ): bool {
		$total     = max( 0, (int) ( $ctx['total']    ?? 0 ) );
		$optimised = max( 0, (int) ( $ctx['optimised'] ?? 0 ) );

		// Milestone 1: ≥50 % of detected library optimised.
		if ( $total > 0 && ( $optimised / $total ) >= 0.5 ) {
			return true;
		}

		// Milestone 2: at least 25 images optimised (absolute, library-size-agnostic).
		if ( $optimised >= 25 ) {
			return true;
		}

		// Milestone 3: at least 3 generation sessions completed.
		$sessions = (int) get_option( self::OPT_SESSION_COUNT, 0 );
		if ( $sessions >= 3 ) {
			return true;
		}

		// Milestone 4: usage on at least 2 distinct calendar days.
		$days = self::get_active_days();
		if ( count( $days ) >= 2 ) {
			return true;
		}

		return false;
	}

	/**
	 * Should the modal be shown right now?
	 *
	 * Combines eligibility with dismissal / snooze state.
	 *
	 * @param array $ctx Live coverage counts.
	 * @return bool
	 */
	public static function should_show( array $ctx = [] ): bool {
		if ( ! self::is_eligible( $ctx ) ) {
			return false;
		}

		if ( (bool) get_option( self::OPT_ALREADY_REVIEWED, false ) ) {
			return false;
		}

		$dismissed_until = (int) get_option( self::OPT_DISMISSED_UNTIL, 0 );
		if ( $dismissed_until > time() ) {
			return false;
		}

		return true;
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Action recording
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * Record a modal interaction.
	 *
	 * @param string $action  One of: shown | leave_review | remind_later | already_reviewed | dismiss.
	 * @return void
	 */
	public static function record_action( string $action ): void {
		$now = time();

		switch ( $action ) {
			case 'shown':
				update_option( self::OPT_LAST_SHOWN, $now, false );
				break;

			case 'leave_review':
				update_option( self::OPT_ALREADY_REVIEWED, true, false );
				update_option( self::OPT_LAST_SHOWN, $now, false );
				break;

			case 'already_reviewed':
				update_option( self::OPT_ALREADY_REVIEWED, true, false );
				update_option( self::OPT_DISMISSED_UNTIL, $now + ( self::SNOOZE_ALREADY_REVIEWED_DAYS * DAY_IN_SECONDS ), false );
				break;

			case 'remind_later':
				update_option( self::OPT_DISMISSED_UNTIL, $now + ( self::SNOOZE_REMIND_LATER_DAYS * DAY_IN_SECONDS ), false );
				break;

			case 'dismiss':
				update_option( self::OPT_DISMISSED_UNTIL, $now + ( self::SNOOZE_DISMISS_DAYS * DAY_IN_SECONDS ), false );
				break;
		}
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Generation session tracking
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * Increment the generation session counter and record today as an active day.
	 *
	 * Call this when a user-initiated bulk generation job is successfully started.
	 *
	 * @return void
	 */
	public static function record_generation_session(): void {
		$count = (int) get_option( self::OPT_SESSION_COUNT, 0 ) + 1;
		update_option( self::OPT_SESSION_COUNT, $count, false );
		self::record_active_day();
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Localized data for JS
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * Build the array to be localized as `window.BBAI_REVIEW_PROMPT`.
	 *
	 * @param array $ctx Live coverage counts (total, optimised).
	 * @return array<string,mixed>
	 */
	public static function get_localized_data( array $ctx = [] ): array {
		return [
			'shouldShow'      => self::should_show( $ctx ),
			'isEligible'      => self::is_eligible( $ctx ),
			'alreadyReviewed' => (bool) get_option( self::OPT_ALREADY_REVIEWED, false ),
			'dismissedUntil'  => (int) get_option( self::OPT_DISMISSED_UNTIL, 0 ),
			'reviewUrl'       => self::REVIEW_URL,
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'nonce'           => wp_create_nonce( 'beepbeepai_nonce' ),
		];
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Private helpers
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * @return string[]
	 */
	private static function get_active_days(): array {
		$days = get_option( self::OPT_ACTIVE_DAYS, [] );
		return is_array( $days ) ? $days : [];
	}

	private static function record_active_day(): void {
		$today = gmdate( 'Y-m-d' );
		$days  = self::get_active_days();
		if ( in_array( $today, $days, true ) ) {
			return;
		}
		$days[] = $today;
		// Keep only the most recent N days to prevent unbounded growth.
		if ( count( $days ) > self::ACTIVE_DAYS_MAX ) {
			$days = array_slice( $days, -self::ACTIVE_DAYS_MAX );
		}
		update_option( self::OPT_ACTIVE_DAYS, $days, false );
	}
}
