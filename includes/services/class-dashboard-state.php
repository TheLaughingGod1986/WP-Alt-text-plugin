<?php
/**
 * Dashboard and ALT Library state resolver.
 *
 * Centralizes trial, quota, and runtime state used by the in-product
 * dashboard flow so guest users, logged-in users, and exhausted states render
 * from the same model.
 */

namespace BeepBeepAI\AltTextGenerator\Services;

use BeepBeepAI\AltTextGenerator\Trial_Quota;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( Upgrade_Path_Resolver::class, false ) ) {
	require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-upgrade-path-resolver.php';
}

class Dashboard_State {
	const STATE_LOGGED_OUT_TRIAL_AVAILABLE = 'logged_out_trial_available';
	const STATE_LOGGED_OUT_TRIAL_RUNNING   = 'logged_out_trial_running';
	const STATE_LOGGED_OUT_TRIAL_EXHAUSTED = 'logged_out_trial_exhausted';
	const STATE_LOGGED_IN_FREE_OR_PAID     = 'logged_in_free_or_paid';
	const STATE_GENERATION_RUNNING         = 'generation_running';
	const STATE_GENERATION_COMPLETE        = 'generation_complete';
	const STATE_GENERATION_FAILED          = 'generation_failed';
	const RUNTIME_IDLE                     = 'idle';

	// Funnel states — single linear journey from signup → conversion.
	const FUNNEL_NOT_SCANNED   = 'not_scanned';
	const FUNNEL_SCANNING      = 'scanning';
	const FUNNEL_HAS_ISSUES    = 'has_issues';
	const FUNNEL_FIXING        = 'fixing';
	const FUNNEL_COMPLETE      = 'complete';
	const FUNNEL_LIMIT_REACHED = 'limit_reached';

	const ACTIONABLE_STATE_MISSING  = 'missing';
	const ACTIONABLE_STATE_REVIEW   = 'review';
	const ACTIONABLE_STATE_COMPLETE = 'complete';

	/**
	 * Resolve the current product state model.
	 *
	 * @param array<string, mixed> $args Resolver arguments.
	 * @return array<string, mixed>
	 */
	public static function resolve( array $args ): array {
		$trial_status   = self::normalize_trial_status( $args['trial_status'] ?? [] );
		$is_guest_trial = ! empty( $args['is_guest_trial'] ) || ! empty( $trial_status['should_gate'] );
		$is_premium     = ! empty( $args['is_premium'] );
		$usage_stats    = self::normalize_usage_stats( $args['usage_stats'] ?? [], $trial_status, $is_guest_trial, $is_premium );
		$runtime_state  = self::normalize_runtime_state( $args['runtime_state'] ?? self::RUNTIME_IDLE );
		$missing_count  = max( 0, (int) ( $args['missing_count'] ?? 0 ) );
		$weak_count     = max( 0, (int) ( $args['weak_count'] ?? 0 ) );
		$total_count    = max( 0, (int) ( $args['total_count'] ?? 0 ) );
		$actionable_model = self::resolve_actionable_state( $missing_count, $weak_count, $total_count );
		$actionable       = $actionable_model['actionable_count'] > 0;
		$base_state     = self::resolve_base_state( $is_guest_trial, $trial_status );
		$resolved_state = self::resolve_display_state( $base_state, $runtime_state );

		$generate_available = $is_guest_trial
			? ! $trial_status['exhausted']
			: ( $is_premium || $usage_stats['remaining'] > 0 );

		$plan_slug_for_ladder = $is_guest_trial
			? 'free'
			: strtolower( (string) ( $args['plan_slug'] ?? $usage_stats['plan'] ?? 'free' ) );

		$upgrade_path = Upgrade_Path_Resolver::resolve(
			[
				'is_guest_trial' => $is_guest_trial,
				'plan_slug'      => $plan_slug_for_ladder,
			]
		);

		// One locked monetisation mode: matches upgrade ladder (no generic "upgrade").
		$locked_cta_mode = '';
		if ( $is_guest_trial && $trial_status['exhausted'] ) {
			$locked_cta_mode = 'create_account';
		} elseif ( ! $generate_available ) {
			if ( Upgrade_Path_Resolver::STEP_AGENCY === $upgrade_path['step'] ) {
				$locked_cta_mode = 'manage_plan';
			} elseif ( Upgrade_Path_Resolver::STEP_GROWTH === $upgrade_path['step'] ) {
				$locked_cta_mode = 'upgrade_agency';
			} elseif ( ! $is_premium ) {
				$locked_cta_mode = 'upgrade_growth';
			}
		}

		return [
			'state'            => $resolved_state,
			'base_state'       => $base_state,
			'runtime_state'    => $runtime_state,
			'supported_states' => [
				self::STATE_LOGGED_OUT_TRIAL_AVAILABLE,
				self::STATE_LOGGED_OUT_TRIAL_RUNNING,
				self::STATE_LOGGED_OUT_TRIAL_EXHAUSTED,
				self::STATE_LOGGED_IN_FREE_OR_PAID,
				self::STATE_GENERATION_RUNNING,
				self::STATE_GENERATION_COMPLETE,
				self::STATE_GENERATION_FAILED,
			],
			'trial'            => $trial_status,
			'usage'            => $usage_stats,
			'actionable'       => $actionable_model,
			'flags'            => [
				'is_guest_trial'             => $is_guest_trial,
				'is_premium'                 => $is_premium,
				'has_actionable_images'      => $actionable,
				'generate_enabled'           => $generate_available && $actionable,
				'generate_available'         => $generate_available,
				'lock_generation_actions'    => ! $generate_available && ( $is_guest_trial || ! $is_premium ),
				'show_trial_helper_copy'     => $is_guest_trial && ! $trial_status['exhausted'],
				'show_exhausted_upgrade_wall'=> $base_state === self::STATE_LOGGED_OUT_TRIAL_EXHAUSTED,
			],
			'cta'              => [
				'primary_mode' => self::resolve_primary_cta_mode(
					$base_state,
					(string) $actionable_model['state'],
					$actionable,
					$generate_available
				),
				'locked_mode'  => $locked_cta_mode,
			],
			'upgrade_path'     => $upgrade_path,
			'copy'             => self::build_copy( $trial_status ),
		];
	}

	/**
	 * Resolve the single actionable state for dashboard UI.
	 *
	 * @param int $missing_count Missing ALT count.
	 * @param int $weak_count Needs-review count.
	 * @param int $total_count Total image count.
	 * @return array<string, int|string>
	 */
	public static function resolve_actionable_state( int $missing_count, int $weak_count, int $total_count = 0 ): array {
		$missing_count    = max( 0, $missing_count );
		$weak_count       = max( 0, $weak_count );
		$total_count      = max( 0, $total_count );
		$actionable_count = $missing_count + $weak_count;
		$state            = self::ACTIONABLE_STATE_COMPLETE;

		if ( $missing_count > 0 ) {
			$state = self::ACTIONABLE_STATE_MISSING;
		} elseif ( $weak_count > 0 ) {
			$state = self::ACTIONABLE_STATE_REVIEW;
		}

		return [
			'state'            => $state,
			'actionable_count' => $actionable_count,
			'missing_count'    => $missing_count,
			'review_count'     => $weak_count,
			'total_count'      => $total_count,
		];
	}

	/**
	 * Normalize authoritative trial status.
	 *
	 * @param array<string, mixed> $trial_status Raw status.
	 * @return array<string, mixed>
	 */
	private static function normalize_trial_status( array $trial_status ): array {
		if ( ! class_exists( Trial_Quota::class ) ) {
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-trial-quota.php';
		}

		$limit     = max( 0, (int) ( $trial_status['limit'] ?? Trial_Quota::get_limit() ) );
		$used      = max( 0, min( $limit, (int) ( $trial_status['used'] ?? 0 ) ) );
		$remaining = isset( $trial_status['remaining'] )
			? max( 0, (int) $trial_status['remaining'] )
			: max( 0, $limit - $used );

		return [
			'is_trial'           => array_key_exists( 'is_trial', $trial_status ) ? ! empty( $trial_status['is_trial'] ) : true,
			'should_gate'        => ! empty( $trial_status['should_gate'] ),
			'limit'              => $limit,
			'used'               => $used,
			'remaining'          => $remaining,
			'exhausted'          => $remaining <= 0,
			'site_hash'          => sanitize_key( (string) ( $trial_status['site_hash'] ?? '' ) ),
			'anon_id'            => sanitize_text_field( (string) ( $trial_status['anon_id'] ?? '' ) ),
			'identity_key'       => sanitize_key( (string) ( $trial_status['identity_key'] ?? '' ) ),
			'monthly_free_limit' => Trial_Quota::get_free_account_monthly_limit(),
		];
	}

	/**
	 * Normalize usage so guest trial pages and paid pages share a shape.
	 *
	 * @param array<string, mixed> $usage_stats Usage snapshot.
	 * @param array<string, mixed> $trial_status Trial snapshot.
	 * @param bool                 $is_guest_trial Guest-trial flag.
	 * @param bool                 $is_premium Premium plan flag.
	 * @return array<string, mixed>
	 */
	private static function normalize_usage_stats( array $usage_stats, array $trial_status, bool $is_guest_trial, bool $is_premium ): array {
		if ( $is_guest_trial ) {
			$used      = max( 0, (int) $trial_status['used'] );
			$limit     = max( 1, (int) $trial_status['limit'] );
			$remaining = max( 0, (int) $trial_status['remaining'] );
			$plan      = 'anonymous_trial';
		} else {
			$used = max( 0, (int) ( $usage_stats['credits_used'] ?? $usage_stats['creditsUsed'] ?? $usage_stats['used'] ?? 0 ) );
			$limit = 50;
			foreach (
				[
					$usage_stats['credits_total'] ?? null,
					$usage_stats['creditsTotal'] ?? null,
					$usage_stats['creditsLimit'] ?? null,
					$usage_stats['limit'] ?? null,
				] as $candidate
			) {
				if ( null !== $candidate && '' !== $candidate && (int) $candidate > 0 ) {
					$limit = max( 1, (int) $candidate );
					break;
				}
			}
			$remaining_candidate = $usage_stats['credits_remaining'] ?? $usage_stats['creditsRemaining'] ?? $usage_stats['remaining'] ?? null;
			if ( null !== $remaining_candidate && '' !== $remaining_candidate ) {
				$remaining = max( 0, (int) $remaining_candidate );
			} else {
				$remaining = max( 0, $limit - $used );
			}
			$plan = sanitize_key( (string) ( $usage_stats['plan'] ?? $usage_stats['plan_type'] ?? 'free' ) );
		}

		return [
			'used'      => $used,
			'limit'     => $limit,
			'remaining' => $remaining,
			'plan'      => $plan,
			'is_premium'=> $is_premium,
		];
	}

	/**
	 * Resolve the durable, server-authored base state.
	 *
	 * @param bool                 $is_guest_trial Guest-trial flag.
	 * @param array<string, mixed> $trial_status Trial snapshot.
	 * @return string
	 */
	private static function resolve_base_state( bool $is_guest_trial, array $trial_status ): string {
		if ( $is_guest_trial ) {
			return ! empty( $trial_status['exhausted'] )
				? self::STATE_LOGGED_OUT_TRIAL_EXHAUSTED
				: self::STATE_LOGGED_OUT_TRIAL_AVAILABLE;
		}

		return self::STATE_LOGGED_IN_FREE_OR_PAID;
	}

	/**
	 * Normalize a runtime state name.
	 *
	 * @param string $runtime_state Raw runtime state.
	 * @return string
	 */
	private static function normalize_runtime_state( string $runtime_state ): string {
		$runtime_state = sanitize_key( $runtime_state );

		return in_array(
			$runtime_state,
			[
				self::RUNTIME_IDLE,
				self::STATE_GENERATION_RUNNING,
				self::STATE_GENERATION_COMPLETE,
				self::STATE_GENERATION_FAILED,
			],
			true
		)
			? $runtime_state
			: self::RUNTIME_IDLE;
	}

	/**
	 * Resolve the currently visible state from base + runtime state.
	 *
	 * @param string $base_state Base server-authored state.
	 * @param string $runtime_state Runtime state.
	 * @return string
	 */
	public static function resolve_display_state( string $base_state, string $runtime_state ): string {
		if ( self::STATE_GENERATION_COMPLETE === $runtime_state ) {
			return self::STATE_GENERATION_COMPLETE;
		}

		if ( self::STATE_GENERATION_FAILED === $runtime_state ) {
			return self::STATE_GENERATION_FAILED;
		}

		if ( self::STATE_GENERATION_RUNNING === $runtime_state ) {
			return self::STATE_LOGGED_OUT_TRIAL_AVAILABLE === $base_state
				? self::STATE_LOGGED_OUT_TRIAL_RUNNING
				: self::STATE_GENERATION_RUNNING;
		}

		return $base_state;
	}

	/**
	 * Resolve the current primary CTA mode.
	 *
	 * @param string $base_state Durable base state.
	 * @param string $actionable_state Actionable state key.
	 * @param bool   $actionable Whether the page has actionable images.
	 * @param bool   $generate_available Whether generation is currently available.
	 * @return string
	 */
	private static function resolve_primary_cta_mode( string $base_state, string $actionable_state, bool $actionable, bool $generate_available ): string {
		if ( self::STATE_LOGGED_OUT_TRIAL_EXHAUSTED === $base_state ) {
			return 'create_account';
		}

		if ( $generate_available && $actionable ) {
			if ( self::ACTIONABLE_STATE_MISSING === $actionable_state ) {
				return 'generate';
			}

			if ( self::ACTIONABLE_STATE_REVIEW === $actionable_state ) {
				return 'reoptimize';
			}
		}

		return 'review';
	}

	/**
	 * Resolve the funnel state from the full product state model.
	 *
	 * Maps the existing base_state + runtime_state + flags into one of six
	 * linear funnel states that drive hero, donut, and CTA rendering.
	 *
	 * @param array<string, mixed> $model Resolved product state model from resolve().
	 * @return string One of the FUNNEL_* constants.
	 */
	public static function resolve_funnel_state( array $model ): string {
		$runtime   = $model['runtime_state'] ?? self::RUNTIME_IDLE;
		$flags     = $model['flags'] ?? [];
		$usage     = $model['usage'] ?? [];
		$remaining = (int) ( $usage['remaining'] ?? 0 );

		// Credits exhausted — monetisation moment takes priority.
		if ( ! empty( $flags['lock_generation_actions'] ) || $remaining <= 0 ) {
			// Only show limit_reached if user has actually used the product.
			$has_scan = ! empty( $model['_has_scan_history'] );
			if ( $has_scan || (int) ( $usage['used'] ?? 0 ) > 0 ) {
				return self::FUNNEL_LIMIT_REACHED;
			}
		}

		// Generation running — fixing state.
		if ( self::STATE_GENERATION_RUNNING === $runtime
			|| self::STATE_LOGGED_OUT_TRIAL_RUNNING === ( $model['state'] ?? '' )
		) {
			return self::FUNNEL_FIXING;
		}

		// No scan history — first experience.
		if ( empty( $model['_has_scan_history'] ) ) {
			return self::FUNNEL_NOT_SCANNED;
		}

		$missing = (int) ( $model['_missing_count'] ?? 0 );
		$weak    = (int) ( $model['_weak_count'] ?? 0 );
		$total   = (int) ( $model['_total_images'] ?? 0 );

		// All images optimized.
		if ( $total > 0 && $missing === 0 && $weak === 0 ) {
			return self::FUNNEL_COMPLETE;
		}

		// Has issues to fix.
		if ( $missing > 0 || $weak > 0 ) {
			return self::FUNNEL_HAS_ISSUES;
		}

		// Fallback: no images at all — treat as not scanned.
		return self::FUNNEL_NOT_SCANNED;
	}

	/**
	 * Shared copy used by the exhausted-trial upgrade wall.
	 *
	 * @param array<string, mixed> $trial_status Trial snapshot.
	 * @return array<string, string>
	 */
	private static function build_copy( array $trial_status ): array {
		$trial_limit   = max( 1, (int) $trial_status['limit'] );
		$trial_used    = max( 0, min( $trial_limit, (int) $trial_status['used'] ) );
		$monthly_limit = max( 1, (int) $trial_status['monthly_free_limit'] );

		return [
			'exhausted_eyebrow' => __( 'Free trial complete', 'beepbeep-ai-alt-text-generator' ),
			'exhausted_title'   => sprintf(
				/* translators: 1: used count, 2: limit count */
				__( 'You used %1$s of %2$s free trial generations on real media', 'beepbeep-ai-alt-text-generator' ),
				number_format_i18n( $trial_used ),
				number_format_i18n( $trial_limit )
			),
			'exhausted_body'    => sprintf(
				/* translators: %d: monthly free account credits */
				__( 'Keep your results visible, create a free account to unlock %d free credits every month, or upgrade for more volume.', 'beepbeep-ai-alt-text-generator' ),
				$monthly_limit
			),
			'value_prop'        => __( 'Keep fixing your library without starting over.', 'beepbeep-ai-alt-text-generator' ),
			'trust_copy'        => __( 'No credit card required. Your trial results and library state stay visible.', 'beepbeep-ai-alt-text-generator' ),
			'helper_copy'       => sprintf(
				/* translators: %d: monthly free account credits */
				__( '%d free credits every month', 'beepbeep-ai-alt-text-generator' ),
				$monthly_limit
			),
			'primary_cta'       => __( 'Create free account', 'beepbeep-ai-alt-text-generator' ),
			'secondary_cta'     => __( 'View plans', 'beepbeep-ai-alt-text-generator' ),
			'usage_line'        => sprintf(
				/* translators: 1: used count, 2: limit count */
				__( '%1$s / %2$s free trial generations used', 'beepbeep-ai-alt-text-generator' ),
				number_format_i18n( $trial_used ),
				number_format_i18n( $trial_limit )
			),
		];
	}
}
