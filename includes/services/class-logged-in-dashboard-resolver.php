<?php
/**
 * Logged-in dashboard state resolver.
 *
 * Single source of truth for the authenticated-user dashboard.
 * Every hero, donut, pill, usage-bar, and surface component must render
 * exclusively from the object this class returns.
 *
 * DO NOT use this class for logged-out / guest-trial rendering.
 * The existing Dashboard_State class owns that path.
 *
 * @package BeepBeep_AI
 * @since   5.2.0
 */

namespace BeepBeepAI\AltTextGenerator\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Logged_In_Dashboard_Resolver {

	// ──────────────────────────────────────────────────────────────────────────
	// State ID constants
	// ──────────────────────────────────────────────────────────────────────────

	const STATE_NO_IMAGES       = 'NO_IMAGES';
	const STATE_QUEUED          = 'QUEUED';
	const STATE_PROCESSING      = 'PROCESSING';
	const STATE_ERROR           = 'ERROR';
	const STATE_QUOTA_EXHAUSTED = 'QUOTA_EXHAUSTED';
	const STATE_NEEDS_REVIEW    = 'NEEDS_REVIEW';
	const STATE_MISSING_ALT     = 'MISSING_ALT';
	const STATE_ALL_CLEAR       = 'ALL_CLEAR';

	const STATE_VERSION = 1;

	// ──────────────────────────────────────────────────────────────────────────
	// Main entry point
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * Resolve the full dashboard state object for a logged-in user.
	 *
	 * @param array<string,mixed> $ctx {
	 *   @type int         $mediaCount   Total images in media library.
	 *   @type array       $counts       Keys: missing, review, complete, failed.
	 *   @type array       $credits      Keys: used, total.
	 *   @type array|null  $job          Keys: status, done, total, eta_seconds, error.
	 *   @type array|null  $systemError  Keys: code, message.
	 *   @type string|null $last_run_at  ISO 8601 timestamp or null.
	 * }
	 * @return array<string,mixed> DashboardState contract v1.
	 */
	public static function resolve( array $ctx ): array {
		$ctx = self::normalize_ctx( $ctx );

		$state = self::compute_state_id( $ctx );

		return [
			'state'         => $state,
			'state_version' => self::STATE_VERSION,
			'hero'          => self::build_hero( $state, $ctx ),
			'banner'        => self::build_banner( $state, 'dashboard', $ctx ),
			'donut'         => self::build_donut( $state, $ctx ),
			'pills'         => self::build_pills( $state, $ctx ),
			'usage'         => self::build_usage( $ctx ),
			'surface'       => self::build_surface( $state, $ctx ),
			'meta'          => [
				'last_run_at'  => $ctx['last_run_at'],
				'total_images' => $ctx['mediaCount'],
				'job'          => $ctx['job'],
			],
		];
	}

	/**
	 * Resolve the dashboard UI from a normalized backend state-truth payload.
	 *
	 * Unlike resolve(), this path does not recompute the state from local
	 * counts/job heuristics. The backend `state` field remains authoritative.
	 *
	 * @param array<string,mixed> $truth Raw backend truth payload.
	 * @param array<string,mixed> $plan  Plan context: is_pro (bool), plan_slug (string), user_type (string).
	 * @return array<string,mixed>
	 */
	public static function resolve_from_truth( array $truth, array $plan = [] ): array {
		$truth = self::normalize_state_truth_payload( $truth );
		$ctx   = self::build_ctx(
			[
				'used'  => $truth['credits']['used'],
				'limit' => $truth['credits']['total'],
			],
			[
				'total_images' => $truth['counts']['total'],
				'missing'      => $truth['counts']['missing'],
				'weak'         => $truth['counts']['review'],
				'optimized'    => $truth['counts']['complete'],
				'failed'       => $truth['counts']['failed'],
			],
			is_array( $truth['job'] ) ? $truth['job'] : [],
			is_array( $truth['systemError'] ) ? $truth['systemError'] : [],
			$truth['last_run_at'],
			$plan
		);
		$state = self::normalize_state_truth_state( (string) ( $truth['state'] ?? '' ) );
		if ( '' === $state ) {
			$state = self::compute_state_id( $ctx );
		}

		return [
			'state'         => $state,
			'state_version' => self::STATE_VERSION,
			'hero'          => self::build_hero( $state, $ctx ),
			'banner'        => self::build_banner( $state, 'dashboard', $ctx ),
			'donut'         => self::build_donut( $state, $ctx ),
			'pills'         => self::build_pills( $state, $ctx ),
			'usage'         => self::build_usage( $ctx ),
			'surface'       => self::build_surface( $state, $ctx ),
			'meta'          => [
				'last_run_at'         => $ctx['last_run_at'],
				'total_images'        => $ctx['mediaCount'],
				'job'                 => $ctx['job'],
				'site'                => $truth['site'],
				'resolution_sources'  => $truth['resolution_sources'],
				'state_truth'         => $truth,
			],
		];
	}

	/**
	 * Normalize a backend truth payload into the contract expected by
	 * resolve_from_truth().
	 *
	 * @param array<string,mixed> $truth Raw backend truth payload.
	 * @return array<string,mixed>
	 */
	private static function normalize_state_truth_payload( array $truth ): array {
		$payload = isset( $truth['data'] ) && is_array( $truth['data'] ) ? $truth['data'] : $truth;
		$counts  = is_array( $payload['counts'] ?? null ) ? $payload['counts'] : [];
		$credits = is_array( $payload['credits'] ?? null ) ? $payload['credits'] : [];
		$job     = is_array( $payload['job'] ?? null ) ? $payload['job'] : null;
		$site    = is_array( $payload['site'] ?? null ) ? $payload['site'] : [];
		$sources = $payload['resolution_sources'] ?? $payload['resolutionSources'] ?? $payload['sources'] ?? [];
		$sources = is_array( $sources ) ? $sources : [];

		$missing  = self::read_truth_int( $counts, [ 'missing', 'missing_alt', 'missingAlt' ], 0 );
		$review   = self::read_truth_int( $counts, [ 'review', 'needs_review', 'needsReview', 'to_review', 'toReview', 'weak' ], 0 );
		$complete = self::read_truth_int( $counts, [ 'complete', 'optimized', 'optimised', 'generated' ], 0 );
		$failed   = self::read_truth_int( $counts, [ 'failed', 'errors' ], 0 );
		$total    = self::read_truth_int( $counts, [ 'total', 'total_images', 'totalImages', 'media_count', 'mediaCount' ], $missing + $review + $complete );
		$total    = max( 0, $total );

		$used      = self::read_truth_int( $credits, [ 'used', 'credits_used', 'creditsUsed' ], 0 );
		$limit     = self::read_truth_int( $credits, [ 'total', 'limit', 'credits_total', 'creditsTotal', 'monthly_limit' ], max( 1, $used ) );
		$remaining = self::read_truth_int( $credits, [ 'remaining', 'credits_remaining', 'creditsRemaining' ], max( 0, $limit - $used ) );

		$job_state = self::normalize_state_truth_state( (string) ( $payload['state'] ?? '' ) );
		$job_data  = self::normalize_truth_job_payload( $job, $job_state );

		$system_error = null;
		if ( isset( $payload['system_error'] ) && is_array( $payload['system_error'] ) ) {
			$system_error = $payload['system_error'];
		} elseif ( isset( $payload['systemError'] ) && is_array( $payload['systemError'] ) ) {
			$system_error = $payload['systemError'];
		}

		$last_run_at = self::read_truth_string(
			$payload,
			[ 'last_run_at', 'lastRunAt', 'last_completed_at', 'lastCompletedAt' ],
			''
		);
		if ( '' === $last_run_at && is_array( $site ) ) {
			$last_run_at = self::read_truth_string( $site, [ 'last_run_at', 'lastRunAt' ], '' );
		}
		if ( '' === $last_run_at && is_array( $job_data ) ) {
			$last_run_at = self::read_truth_string( $job_data, [ 'last_completed_at', 'lastCompletedAt' ], '' );
		}

		return [
			'state'              => $job_state,
			'counts'             => [
				'missing'  => max( 0, $missing ),
				'review'   => max( 0, $review ),
				'complete' => max( 0, $complete ),
				'failed'   => max( 0, $failed ),
				'total'    => max( 0, $total ),
			],
			'credits'            => [
				'used'      => max( 0, $used ),
				'total'     => max( 1, $limit ),
				'remaining' => max( 0, $remaining ),
			],
			'job'                => $job_data,
			'site'               => $site,
			'resolution_sources' => $sources,
			'systemError'        => is_array( $system_error ) ? $system_error : null,
			'last_run_at'        => '' !== $last_run_at ? $last_run_at : null,
		];
	}

	/**
	 * Normalize backend state strings to resolver constants.
	 *
	 * @param string $state Raw state.
	 * @return string
	 */
	private static function normalize_state_truth_state( string $state ): string {
		$state = strtoupper( trim( str_replace( [ '-', ' ' ], '_', $state ) ) );
		$allowed = [
			self::STATE_NO_IMAGES,
			self::STATE_QUEUED,
			self::STATE_PROCESSING,
			self::STATE_ERROR,
			self::STATE_QUOTA_EXHAUSTED,
			self::STATE_NEEDS_REVIEW,
			self::STATE_MISSING_ALT,
			self::STATE_ALL_CLEAR,
		];

		return in_array( $state, $allowed, true ) ? $state : '';
	}

	/**
	 * @param array<string,mixed>|null $job   Raw job payload.
	 * @param string                   $state Canonical state id.
	 * @return array<string,mixed>|null
	 */
	private static function normalize_truth_job_payload( ?array $job, string $state ): ?array {
		$job = is_array( $job ) ? $job : [];
		if ( [] === $job && ! in_array( $state, [ self::STATE_QUEUED, self::STATE_PROCESSING ], true ) ) {
			return null;
		}

		$status = self::normalize_job_status(
			self::read_truth_string( $job, [ 'status', 'job_status', 'jobStatus', 'state' ], self::STATE_QUEUED === $state ? 'queued' : 'processing' )
		);
		$active = self::read_truth_bool( $job, [ 'active', 'is_active', 'isActive' ], in_array( $state, [ self::STATE_QUEUED, self::STATE_PROCESSING ], true ) );

		return [
			'status'            => $status,
			'state'             => 'queued' === $status ? self::STATE_QUEUED : self::STATE_PROCESSING,
			'active'            => $active,
			'pausable'          => self::read_truth_bool( $job, [ 'pausable', 'can_pause', 'canPause' ], false ),
			'done'              => max( 0, self::read_truth_int( $job, [ 'done', 'processed', 'completed' ], 0 ) ),
			'total'             => max( 0, self::read_truth_int( $job, [ 'total', 'queue_count', 'queueCount', 'count' ], 0 ) ),
			'eta_seconds'       => self::read_truth_nullable_int( $job, [ 'eta_seconds', 'etaSeconds', 'eta' ] ),
			'error'             => self::read_truth_string( $job, [ 'error', 'message' ], '' ),
			'queue_count'       => max( 0, self::read_truth_int( $job, [ 'queue_count', 'queueCount', 'queued' ], 0 ) ),
			'last_checked_at'   => self::read_truth_string( $job, [ 'last_checked_at', 'lastCheckedAt', 'checked_at', 'checkedAt' ], '' ),
			'last_completed_at' => self::read_truth_string( $job, [ 'last_completed_at', 'lastCompletedAt', 'completed_at', 'completedAt' ], '' ),
		];
	}

	/**
	 * @param array<string,mixed> $source Source array.
	 * @param string[]            $keys   Candidate keys.
	 * @param int                 $default Default value.
	 * @return int
	 */
	private static function read_truth_int( array $source, array $keys, int $default ): int {
		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $source ) ) {
				continue;
			}
			if ( is_numeric( $source[ $key ] ) ) {
				return (int) $source[ $key ];
			}
		}

		return $default;
	}

	/**
	 * @param array<string,mixed> $source Source array.
	 * @param string[]            $keys   Candidate keys.
	 * @param string              $default Default value.
	 * @return string
	 */
	private static function read_truth_string( array $source, array $keys, string $default ): string {
		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $source ) ) {
				continue;
			}
			if ( is_scalar( $source[ $key ] ) ) {
				$value = trim( (string) $source[ $key ] );
				if ( '' !== $value ) {
					return $value;
				}
			}
		}

		return $default;
	}

	/**
	 * @param array<string,mixed> $source Source array.
	 * @param string[]            $keys   Candidate keys.
	 * @param bool                $default Default value.
	 * @return bool
	 */
	private static function read_truth_bool( array $source, array $keys, bool $default ): bool {
		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $source ) ) {
				continue;
			}
			return (bool) filter_var( $source[ $key ], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		}

		return $default;
	}

	/**
	 * @param array<string,mixed> $source Source array.
	 * @param string[]            $keys   Candidate keys.
	 * @return int|null
	 */
	private static function read_truth_nullable_int( array $source, array $keys ): ?int {
		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $source ) ) {
				continue;
			}
			if ( is_numeric( $source[ $key ] ) ) {
				return (int) $source[ $key ];
			}
		}

		return null;
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Priority resolver — single authoritative function
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * @param array<string,mixed> $ctx Normalised context.
	 * @return string State ID constant.
	 */
	public static function compute_state_id( array $ctx ): string {
		$counts      = $ctx['counts'];
		$credits     = $ctx['credits'];
		$job         = $ctx['job'];
		$system_err  = $ctx['systemError'];

		// 1. No images at all.
		if ( $ctx['mediaCount'] === 0 ) {
			return self::STATE_NO_IMAGES;
		}

		// 2. Active backend job wins over everything except NO_IMAGES.
		if ( self::is_active_generation_job( $job ) ) {
			$job_status = self::normalize_job_status( (string) ( $job['status'] ?? $job['state'] ?? '' ) );
			return 'queued' === $job_status ? self::STATE_QUEUED : self::STATE_PROCESSING;
		}

		// 3. System error when no active job (API key missing, auth failure, etc.).
		if ( $system_err !== null && $job === null ) {
			return self::STATE_ERROR;
		}

		// 4. Quota exhausted with work remaining.
		if ( $credits['used'] >= $credits['total'] && $counts['missing'] > 0 ) {
			return self::STATE_QUOTA_EXHAUSTED;
		}

		// 5. All generated but review items outstanding.
		if ( $counts['missing'] === 0 && $counts['review'] > 0 ) {
			return self::STATE_NEEDS_REVIEW;
		}

		// 6. Work to generate.
		if ( $counts['missing'] > 0 ) {
			return self::STATE_MISSING_ALT;
		}

		// 7. Failed items with nothing else to do — recovery state.
		if ( $counts['failed'] > 0 ) {
			return self::STATE_ERROR;
		}

		// 8. Everything done.
		return self::STATE_ALL_CLEAR;
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Hero builder
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * @param string              $state State ID.
	 * @param array<string,mixed> $ctx   Normalised context.
	 * @return array<string,mixed>
	 */
	private static function build_hero( string $state, array $ctx ): array {
		$counts     = $ctx['counts'];
		$job        = $ctx['job'];
		$last_run   = $ctx['last_run_at'];
		$total      = $ctx['mediaCount'];
		$system_err = $ctx['systemError'];
		$is_pro     = ! empty( $ctx['is_pro'] );
		$is_trial   = ( 'trial' === ( $ctx['user_type'] ?? 'free' ) );
		$signup_url = $ctx['signup_url'] ?? '';

		// Pre-computed values used in headlines and the summary block.
		$missing  = (int) ( $counts['missing']  ?? 0 );
		$review   = (int) ( $counts['review']   ?? 0 );
		$complete = (int) ( $counts['complete'] ?? 0 );
		$failed   = (int) ( $counts['failed']   ?? 0 );
		$credits  = $ctx['credits'];
		$cr_used  = (int) ( $credits['used']  ?? 0 );
		$cr_total = max( 1, (int) ( $credits['total'] ?? 1 ) );

		// Compact summary shown beneath the CTAs. Always present (suppressed per-state only).
		$summary = self::build_hero_summary( $missing, $review, $complete, $cr_used, $cr_total, $state, $is_pro );

		switch ( $state ) {

			case self::STATE_NO_IMAGES:
				return [
					'badge'         => [ 'text' => __( 'Getting started', 'beepbeep-ai-alt-text-generator' ), 'mod' => 'gray' ],
					'headline'      => __( 'Upload images to get started', 'beepbeep-ai-alt-text-generator' ),
					'support'       => __( 'Add images to your media library and BeepBeep will generate ALT text automatically.', 'beepbeep-ai-alt-text-generator' ),
					'variant'       => 'default',
					'primary_cta'   => [
						'label'  => __( 'Go to Media Library', 'beepbeep-ai-alt-text-generator' ),
						'action' => 'navigate',
						'href'   => admin_url( 'upload.php' ),
					],
					'secondary_cta' => [
						'label'  => __( 'Open settings', 'beepbeep-ai-alt-text-generator' ),
						'action' => 'navigate',
						'href'   => admin_url( 'admin.php?page=bbai-settings' ),
					],
					'summary'       => [],
				];

			case self::STATE_QUEUED:
				$job_total    = (int) ( $job['total'] ?? 0 );
				$queued_total = $job_total > 0 ? $job_total : $missing;
				$queued_href  = add_query_arg(
					[
						'page'   => 'bbai-library',
						'status' => 'missing',
						'filter' => 'missing',
					],
					admin_url( 'admin.php' )
				) . '#bbai-review-filter-tabs';

				return [
					'badge'         => [ 'text' => __( 'Queued', 'beepbeep-ai-alt-text-generator' ), 'mod' => 'gray' ],
					'headline'      => sprintf(
						/* translators: %s: queued image count */
						_n( '%s image is queued', '%s images are queued', $queued_total, 'beepbeep-ai-alt-text-generator' ),
						number_format_i18n( $queued_total )
					),
					'support'       => __( 'Your next batch is lined up. Start it now or leave it queued for automatic processing.', 'beepbeep-ai-alt-text-generator' ),
					'variant'       => 'queued',
					'primary_cta'   => [
						'label'      => __( 'Start generating now', 'beepbeep-ai-alt-text-generator' ),
						'busy_label' => __( 'Starting generation…', 'beepbeep-ai-alt-text-generator' ),
						'action'     => 'generate-missing',
					],
					'secondary_cta' => [
						'label'  => __( 'View queued images', 'beepbeep-ai-alt-text-generator' ),
						'action' => 'navigate',
						'href'   => $queued_href,
					],
					'summary'       => $summary,
					'live_signal'   => __( 'Last checked just now', 'beepbeep-ai-alt-text-generator' ),
				];

			case self::STATE_PROCESSING:
				$job_status = self::normalize_job_status( (string) ( $job['status'] ?? 'processing' ) );
				$done       = (int) ( $job['done'] ?? 0 );
				$job_total  = (int) ( $job['total'] ?? 0 );
				$remaining  = max( 0, $job_total - $done );
				$eta_secs   = $job['eta_seconds'] ?? null;
				$eta_str    = self::format_eta( $eta_secs );
				$pausable   = ! empty( $job['active'] ) && ! empty( $job['pausable'] ) && 'processing' === $job_status;

				// Pause only appears when the backend explicitly marks the job pausable.
				// Support copy: pro users see optimised-so-far as momentum proof.
				$running_support = $is_pro && $done > 0
					? sprintf(
						/* translators: 1: done count, 2: eta string */
						__( '%1$s optimised so far · ~%2$s to finish', 'beepbeep-ai-alt-text-generator' ),
						number_format_i18n( $done ),
						$eta_str ?: __( 'a moment', 'beepbeep-ai-alt-text-generator' )
					)
					: ( $eta_str
						? sprintf(
							/* translators: 1: remaining count, 2: ETA string */
							__( '%1$s remaining · ~%2$s to finish', 'beepbeep-ai-alt-text-generator' ),
							number_format_i18n( $remaining ),
							$eta_str
						)
						: sprintf(
							/* translators: %s: remaining count */
							_n( '%s image remaining.', '%s images remaining.', $remaining, 'beepbeep-ai-alt-text-generator' ),
							number_format_i18n( $remaining )
						)
					);

				$primary_cta = null;
				if ( $pausable ) {
					$primary_cta = [
						'label'      => __( 'Pause', 'beepbeep-ai-alt-text-generator' ),
						'busy_label' => __( 'Pausing…', 'beepbeep-ai-alt-text-generator' ),
						'action'     => 'pause-job',
					];
				}

				return [
					'badge'         => [ 'text' => __( 'Processing', 'beepbeep-ai-alt-text-generator' ), 'mod' => 'blue' ],
					'headline'      => sprintf(
						/* translators: 1: done count, 2: total count */
						__( 'Generating — %1$s of %2$s images done', 'beepbeep-ai-alt-text-generator' ),
						number_format_i18n( $done ),
						number_format_i18n( $job_total )
					),
					'support'       => $running_support,
					'variant'       => 'running',
					'primary_cta'   => $primary_cta,
					'secondary_cta' => [
						'label'  => __( 'Open ALT Library', 'beepbeep-ai-alt-text-generator' ),
						'action' => 'navigate',
						'href'   => admin_url( 'admin.php?page=bbai-library' ),
					],
					'summary'       => $summary,
				];

			case self::STATE_ERROR:
				$err_msg = $system_err
					? sanitize_text_field( (string) ( $system_err['message'] ?? '' ) )
					: __( 'Some images could not be processed. Retry to continue.', 'beepbeep-ai-alt-text-generator' );

				// API key missing — direct to settings.
				if ( $system_err && 'NO_API_KEY' === ( $system_err['code'] ?? '' ) ) {
					return [
						'badge'         => [ 'text' => __( 'Action needed', 'beepbeep-ai-alt-text-generator' ), 'mod' => 'amber' ],
						'headline'      => __( 'Connect your API key to start', 'beepbeep-ai-alt-text-generator' ),
						'support'       => __( 'Add your API key in settings to begin generating ALT text automatically.', 'beepbeep-ai-alt-text-generator' ),
						'variant'       => 'error',
						'primary_cta'   => [
							'label'  => __( 'Open settings', 'beepbeep-ai-alt-text-generator' ),
							'action' => 'navigate',
							'href'   => admin_url( 'admin.php?page=bbai-settings' ),
						],
						'secondary_cta' => null,
						'summary'       => [],
					];
				}

				// Failed items: pro users see progress preserved ("X already done"), free see the fix.
				$error_support = $is_pro && $complete > 0
					? sprintf(
						/* translators: %s: number of successfully optimised images */
						_n(
							'%s image is already optimised — retry to recover the rest.',
							'%s images are already optimised — retry to recover the rest.',
							$complete,
							'beepbeep-ai-alt-text-generator'
						),
						number_format_i18n( $complete )
					)
					: $err_msg;

				return [
					'badge'         => [ 'text' => __( 'Attention', 'beepbeep-ai-alt-text-generator' ), 'mod' => 'red' ],
					'headline'      => $failed > 0
						? sprintf(
							/* translators: %s: number of failed images */
							_n( '%s image failed to generate', '%s images failed to generate', $failed, 'beepbeep-ai-alt-text-generator' ),
							number_format_i18n( $failed )
						)
						: __( 'Generation stopped with an error', 'beepbeep-ai-alt-text-generator' ),
					'support'       => $error_support,
					'variant'       => 'error',
					'primary_cta'   => [
						'label'       => __( 'Retry failed', 'beepbeep-ai-alt-text-generator' ),
						'busy_label'  => __( 'Retrying…', 'beepbeep-ai-alt-text-generator' ),
						'action'      => 'retry-failed',
					],
					'secondary_cta' => [
						'label'  => __( 'Open ALT Library', 'beepbeep-ai-alt-text-generator' ),
						'action' => 'navigate',
						'href'   => admin_url( 'admin.php?page=bbai-library' ),
					],
					'summary'       => $summary,
				];

			case self::STATE_QUOTA_EXHAUSTED:
				// Trial users: free quota used up — prompt to create a free account.
				if ( $is_trial ) {
					$trial_limit = (int) ( $ctx['trial_limit'] ?? 0 );
					return [
						'badge'         => [ 'text' => __( 'Trial complete', 'beepbeep-ai-alt-text-generator' ), 'mod' => 'blue' ],
						'headline'      => __( 'Your free trial is complete', 'beepbeep-ai-alt-text-generator' ),
						'support'       => $trial_limit > 0
							? sprintf(
								/* translators: 1: trial limit, 2: missing count */
								__( 'You\'ve used your %1$s free generations. Create a free account to continue — %2$s images still need ALT text.', 'beepbeep-ai-alt-text-generator' ),
								number_format_i18n( $trial_limit ),
								number_format_i18n( $missing )
							)
							: sprintf(
								/* translators: %s: missing count */
								_n(
									'Create a free account to continue — %s image still needs ALT text.',
									'Create a free account to continue — %s images still need ALT text.',
									$missing,
									'beepbeep-ai-alt-text-generator'
								),
								number_format_i18n( $missing )
							),
						'variant'       => 'default',
						'primary_cta'   => [
							'label'  => __( 'Create free account to continue', 'beepbeep-ai-alt-text-generator' ),
							'action' => 'navigate',
							'href'   => '' !== $signup_url ? $signup_url : admin_url( 'admin.php?page=bbai-settings' ),
						],
						'secondary_cta' => [
							'label'  => __( 'Open ALT Library', 'beepbeep-ai-alt-text-generator' ),
							'action' => 'navigate',
							'href'   => admin_url( 'admin.php?page=bbai-library' ),
						],
						'summary'       => $summary,
					];
				}

				// Frame as interrupted progress, not failure. Pro users see what they've achieved.
				$quota_support = $is_pro && $complete > 0
					? sprintf(
						/* translators: 1: already optimised count, 2: remaining count */
						__( 'You\'ve already optimised %1$s images — add credits to finish the remaining %2$s.', 'beepbeep-ai-alt-text-generator' ),
						number_format_i18n( $complete ),
						number_format_i18n( $missing )
					)
					: sprintf(
						/* translators: %s: number of images still missing ALT text */
						_n(
							'Add credits to keep going — %s image still needs ALT text.',
							'Add credits to keep going — %s images still need ALT text.',
							$missing,
							'beepbeep-ai-alt-text-generator'
						),
						number_format_i18n( $missing )
					);

				return [
					'badge'         => [ 'text' => __( 'Credits needed', 'beepbeep-ai-alt-text-generator' ), 'mod' => 'amber' ],
					'headline'      => sprintf(
						/* translators: %s: number of images missing ALT text */
						_n( '%s image still needs ALT text', '%s images still need ALT text', $missing, 'beepbeep-ai-alt-text-generator' ),
						number_format_i18n( $missing )
					),
					'support'       => $quota_support,
					'variant'       => 'default',
					'primary_cta'   => [
						'label'  => __( 'Add credits', 'beepbeep-ai-alt-text-generator' ),
						'action' => 'add-credits',
						'href'   => admin_url( 'admin.php?page=bbai-credit-usage' ),
					],
					'secondary_cta' => [
						'label'  => __( 'Open ALT Library', 'beepbeep-ai-alt-text-generator' ),
						'action' => 'navigate',
						'href'   => admin_url( 'admin.php?page=bbai-library' ),
					],
					'summary'       => $summary,
				];

			case self::STATE_NEEDS_REVIEW:
				// Frame as a light approval task. Pro users see the AI-did-the-work angle.
				$review_library_href = function_exists( 'bbai_alt_library_needs_review_url' )
					? \bbai_alt_library_needs_review_url()
					: add_query_arg(
						[
							'page'   => 'bbai-library',
							'status' => 'needs_review',
						],
						admin_url( 'admin.php' )
					) . '#bbai-review-filter-tabs';
				$review_support = $is_pro
					? sprintf(
						/* translators: %s: number of images ready to approve */
						_n(
							'AI has prepared a suggestion for %s image — approve everything now or open the queue for a closer pass.',
							'AI has prepared suggestions for %s images — approve everything now or open the queue for a closer pass.',
							$review,
							'beepbeep-ai-alt-text-generator'
						),
						number_format_i18n( $review )
					)
					: __( 'ALT text is ready for a quick review before it goes live.', 'beepbeep-ai-alt-text-generator' );

				return [
					'badge'         => [ 'text' => __( 'Review ready', 'beepbeep-ai-alt-text-generator' ), 'mod' => 'blue' ],
					'headline'      => sprintf(
						/* translators: %s: number of images needing review */
						_n( '%s image is ready for review', '%s images are ready for review', $review, 'beepbeep-ai-alt-text-generator' ),
						number_format_i18n( $review )
					),
					'support'       => $review_support,
					'variant'       => 'default',
					'primary_cta'   => [
						'label'       => __( 'Approve all', 'beepbeep-ai-alt-text-generator' ),
						'busy_label'  => __( 'Approving…', 'beepbeep-ai-alt-text-generator' ),
						'action'      => 'approve-all',
					],
					'secondary_cta' => [
						'label'  => __( 'Open review queue', 'beepbeep-ai-alt-text-generator' ),
						'action' => 'navigate',
						'href'   => $review_library_href,
					],
					'summary'       => $summary,
				];

			case self::STATE_MISSING_ALT:
				// Trial users: encourage them to try the free generation (limited quota).
				// Pro users get progress proof ("X already done") then the call to action.
				// Free users get problem framing + action nudge.
				if ( $is_trial ) {
					$trial_limit = (int) ( $ctx['trial_limit'] ?? 0 );
					$trial_used  = (int) ( $ctx['trial_used'] ?? 0 );
					$trial_left  = max( 0, $trial_limit - $trial_used );
					$missing_support = $trial_left > 0
						? sprintf(
							/* translators: %s: free generations remaining */
							_n(
								'Use your remaining %s free generation to start improving accessibility now.',
								'Use your remaining %s free generations to start improving accessibility now.',
								$trial_left,
								'beepbeep-ai-alt-text-generator'
							),
							number_format_i18n( $trial_left )
						)
						: __( 'Generate ALT text now to improve accessibility and search visibility.', 'beepbeep-ai-alt-text-generator' );

					return [
						'badge'         => [ 'text' => __( 'Free trial', 'beepbeep-ai-alt-text-generator' ), 'mod' => 'blue' ],
						'headline'      => sprintf(
							/* translators: %s: number of images missing ALT text */
							_n( '%s image is missing ALT text', '%s images are missing ALT text', $missing, 'beepbeep-ai-alt-text-generator' ),
							number_format_i18n( $missing )
						),
						'support'       => $missing_support,
						'variant'       => 'default',
						'primary_cta'   => [
							'label'       => $trial_left > 0
								? sprintf(
									/* translators: %s: number of free generations remaining */
									_n( 'Generate free (%s left)', 'Generate free (%s left)', $trial_left, 'beepbeep-ai-alt-text-generator' ),
									number_format_i18n( $trial_left )
								)
								: __( 'Generate missing ALT text', 'beepbeep-ai-alt-text-generator' ),
							'busy_label'  => __( 'Starting…', 'beepbeep-ai-alt-text-generator' ),
							'action'      => 'generate-missing',
						],
						'secondary_cta' => [
							'label'  => __( 'Create free account', 'beepbeep-ai-alt-text-generator' ),
							'action' => 'navigate',
							'href'   => '' !== $signup_url ? $signup_url : admin_url( 'admin.php?page=bbai-settings' ),
						],
						'summary'       => $summary,
					];
				}

				$missing_support = $is_pro && $complete > 0
					? sprintf(
						/* translators: 1: already optimised count, 2: remaining count */
						__( '%1$s images already optimised — generate the remaining %2$s to complete your library.', 'beepbeep-ai-alt-text-generator' ),
						number_format_i18n( $complete ),
						number_format_i18n( $missing )
					)
					: __( 'Generate the missing ALT text now to keep your library accessible, searchable, and up to date.', 'beepbeep-ai-alt-text-generator' );

				return [
					'badge'         => [ 'text' => __( 'Action needed', 'beepbeep-ai-alt-text-generator' ), 'mod' => 'amber' ],
					'headline'      => sprintf(
						/* translators: %s: number of images missing ALT text */
						_n( '%s image is missing ALT text', '%s images are missing ALT text', $missing, 'beepbeep-ai-alt-text-generator' ),
						number_format_i18n( $missing )
					),
					'support'       => $missing_support,
					'variant'       => 'default',
					'primary_cta'   => [
						'label'       => __( 'Generate missing ALT text', 'beepbeep-ai-alt-text-generator' ),
						'busy_label'  => __( 'Starting…', 'beepbeep-ai-alt-text-generator' ),
						'action'      => 'generate-missing',
					],
					'secondary_cta' => [
						'label'  => __( 'Open ALT Library', 'beepbeep-ai-alt-text-generator' ),
						'action' => 'navigate',
						'href'   => admin_url( 'admin.php?page=bbai-library' ),
					],
					'summary'       => $summary,
				];

			case self::STATE_ALL_CLEAR:
			default:
				$last_run_formatted = $last_run
					? date_i18n( get_option( 'date_format' ), strtotime( $last_run ) )
					: null;

				// Pro users get proof of value (total optimised). Free get readiness messaging.
				$all_clear_support = $is_pro
					? ( $last_run_formatted
						? sprintf(
							/* translators: 1: total optimised count, 2: last run date */
							_n(
								'All %1$s image is optimised. Last updated %2$s — ready for new uploads.',
								'All %1$s images are optimised. Last updated %2$s — ready for new uploads.',
								$complete,
								'beepbeep-ai-alt-text-generator'
							),
							number_format_i18n( $complete ),
							$last_run_formatted
						)
						: sprintf(
							/* translators: %s: total optimised count */
							_n(
								'All %s image is optimised. New uploads are processed automatically.',
								'All %s images are optimised. New uploads are processed automatically.',
								$complete,
								'beepbeep-ai-alt-text-generator'
							),
							number_format_i18n( $complete )
						)
					)
					: ( $last_run_formatted
						? sprintf(
							/* translators: %s: last run date */
							__( 'Your library is fully optimised. Last updated %s.', 'beepbeep-ai-alt-text-generator' ),
							$last_run_formatted
						)
						: __( 'Your library is fully optimised. New uploads will be processed automatically.', 'beepbeep-ai-alt-text-generator' )
					);

				return [
					'badge'         => [ 'text' => __( 'All optimised', 'beepbeep-ai-alt-text-generator' ), 'mod' => 'green' ],
					'headline'      => sprintf(
						/* translators: %s: total image count */
						_n( 'All %s image is optimised', 'All %s images are optimised', $total, 'beepbeep-ai-alt-text-generator' ),
						number_format_i18n( $total )
					),
					'support'       => $all_clear_support,
					'variant'       => 'success',
					'primary_cta'   => [
						'label'       => __( 'Re-scan library', 'beepbeep-ai-alt-text-generator' ),
						'busy_label'  => __( 'Scanning…', 'beepbeep-ai-alt-text-generator' ),
						'action'      => 'rescan-media-library',
					],
					'secondary_cta' => [
						'label'  => __( 'Open ALT Library', 'beepbeep-ai-alt-text-generator' ),
						'action' => 'navigate',
						'href'   => admin_url( 'admin.php?page=bbai-library' ),
					],
					// Positive summary: pro users see optimised count prominently.
					'summary'       => [
						[
							'label' => __( 'Optimised', 'beepbeep-ai-alt-text-generator' ),
							'value' => number_format_i18n( $complete ),
							'mod'   => 'ok',
						],
						[
							'label' => __( 'Credits left', 'beepbeep-ai-alt-text-generator' ),
							'value' => number_format_i18n( max( 0, $cr_total - $cr_used ) ),
							'mod'   => 'muted',
						],
					],
				];
		}
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Banner builder — top status-banner component, state-aware
	// ──────────────────────────────────────────────────────────────────────────

	// ──────────────────────────────────────────────────────────────────────────
	// Page context constants — match BBAI_BANNER_CTX_* in banner-system.php
	// ──────────────────────────────────────────────────────────────────────────

	const CTX_DASHBOARD = 'dashboard';
	const CTX_LIBRARY   = 'library';
	const CTX_ANALYTICS = 'analytics';
	const CTX_CREDITS   = 'usage';

	// ──────────────────────────────────────────────────────────────────────────
	// Banner builder — public so library/analytics/credits can call directly
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * Build the resolver-canonical banner for any page context.
	 *
	 * Returns keys compatible with status-banner.php (title, body, tone,
	 * banner_variant, semantic_state) plus the clean contract keys (title,
	 * body, tone) for callers that inject over an existing command_hero.
	 *
	 * @param string              $state        One of the STATE_* constants.
	 * @param string              $page_context One of the CTX_* constants.
	 * @param array<string,mixed> $ctx          Normalised resolver context.
	 * @return array<string,mixed>
	 */
	public static function build_banner( string $state, string $page_context, array $ctx ): array {
		$counts     = $ctx['counts'] ?? [];
		$job        = $ctx['job']    ?? null;
		$system_err = $ctx['systemError'] ?? null;
		$missing    = (int) ( $counts['missing']  ?? 0 );
		$review     = (int) ( $counts['review']   ?? 0 );
		$complete   = (int) ( $counts['complete'] ?? 0 );
		$is_pro     = ! empty( $ctx['is_pro'] );

		// heading_tag h2 — the hero <section> already owns h1 on the dashboard.
		// suppress_host false — wrapper divs required by status-banner.php layout.
		$base = [
			'heading_tag'   => 'h2',
			'suppress_host' => false,
		];

		switch ( $state ) {

			// ── NO_IMAGES ────────────────────────────────────────────────────
			case self::STATE_NO_IMAGES:
				return array_merge( $base, [
					'title'          => __( 'Welcome — let\'s get your images optimised', 'beepbeep-ai-alt-text-generator' ),
					'body'           => __( 'Upload images to your media library and BeepBeep will generate SEO-ready ALT text automatically.', 'beepbeep-ai-alt-text-generator' ),
					'tone'           => 'setup',
					'banner_variant' => 'info',
					'semantic_state' => 'setup',
				] );

			// ── QUEUED ───────────────────────────────────────────────────────
			case self::STATE_QUEUED:
				$job_total = (int) ( $job['total'] ?? 0 );
				$queued_total = $job_total > 0 ? $job_total : $missing;

				// queued on dashboard — hero owns this state; suppress the banner
				// to avoid offering another "generate" action while work is waiting.
				if ( self::CTX_DASHBOARD === $page_context ) {
					return [];
				}

				return array_merge( $base, [
					'title'          => __( 'Generation queued', 'beepbeep-ai-alt-text-generator' ),
					'body'           => sprintf(
						/* translators: %s: total queued images */
						_n( '%s image is waiting to start.', '%s images are waiting to start.', $queued_total, 'beepbeep-ai-alt-text-generator' ),
						number_format_i18n( $queued_total )
					),
					'tone'           => 'setup',
					'banner_variant' => 'info',
					'semantic_state' => 'queued',
				] );

			// ── PROCESSING ───────────────────────────────────────────────────
			case self::STATE_PROCESSING:
				$done      = (int) ( $job['done']  ?? 0 );
				$job_total = (int) ( $job['total'] ?? 0 );

				// running on dashboard — hero already owns this state; suppress the banner
				// to avoid duplicating the same operational status in two places.
				if ( self::CTX_DASHBOARD === $page_context ) {
					return [];
				}

				// running on other pages — brief progress note is still useful.
				return array_merge( $base, [
					'title'          => __( 'Optimisation in progress', 'beepbeep-ai-alt-text-generator' ),
					'body'           => sprintf(
						/* translators: 1: done count, 2: total count */
						__( 'Generation in progress — %1$s of %2$s images done.', 'beepbeep-ai-alt-text-generator' ),
						number_format_i18n( $done ),
						number_format_i18n( $job_total )
					),
					'tone'           => 'healthy',
					'banner_variant' => 'info',
					'semantic_state' => 'processing',
				] );

			// ── ERROR ────────────────────────────────────────────────────────
			case self::STATE_ERROR:
				$is_no_key = $system_err && 'NO_API_KEY' === ( $system_err['code'] ?? '' );
				$error_body = $is_no_key
					? __( 'Your API key isn\'t connected yet. Add it in settings to start generating ALT text.', 'beepbeep-ai-alt-text-generator' )
					: __( 'One or more images couldn\'t be processed. Review the details and retry when ready.', 'beepbeep-ai-alt-text-generator' );

				return array_merge( $base, [
					'title'          => __( 'Something needs attention', 'beepbeep-ai-alt-text-generator' ),
					'body'           => $error_body,
					'tone'           => 'attention',
					'banner_variant' => 'warning',
					'semantic_state' => 'error',
				] );

			// ── QUOTA_EXHAUSTED ──────────────────────────────────────────────
			case self::STATE_QUOTA_EXHAUSTED:
				if ( self::CTX_DASHBOARD === $page_context ) {
					return [];
				}

				// Credits page: monetisation framing — emphasise the action.
				// All other pages: status framing — you've used your allocation.
				$quota_body = self::CTX_CREDITS === $page_context
					? sprintf(
						/* translators: %s: number of images still missing ALT text */
						_n(
							'%s image still needs ALT text. Add credits to continue generating.',
							'%s images still need ALT text. Add credits to continue generating.',
							$missing,
							'beepbeep-ai-alt-text-generator'
						),
						number_format_i18n( $missing )
					)
					: sprintf(
						/* translators: %s: number of images still missing ALT text */
						_n(
							'%s image remaining — add credits to continue.',
							'%s images remaining — add credits to continue.',
							$missing,
							'beepbeep-ai-alt-text-generator'
						),
						number_format_i18n( $missing )
					);

				return array_merge( $base, [
					'title'          => __( 'You\'ve used all your credits', 'beepbeep-ai-alt-text-generator' ),
					'body'           => $quota_body,
					'tone'           => 'attention',
					'banner_variant' => 'warning',
					'semantic_state' => 'quota_exhausted',
				] );

			// ── NEEDS_REVIEW ─────────────────────────────────────────────────
			case self::STATE_NEEDS_REVIEW:
				// Dashboard: hero owns the full action surface — suppress the banner
				// to avoid duplicating the same review status in two places.
				if ( self::CTX_DASHBOARD === $page_context ) {
					return [];
				}

				// Library: action-oriented — "use the filters".
				// All others: status — "images ready to approve".
				$review_body = self::CTX_LIBRARY === $page_context
					? sprintf(
						/* translators: %s: review count */
						_n(
							'%s image has generated ALT text waiting for approval. Use the filters below to review.',
							'%s images have generated ALT text waiting for approval. Use the filters below to review.',
							$review,
							'beepbeep-ai-alt-text-generator'
						),
						number_format_i18n( $review )
					)
					: sprintf(
						/* translators: %s: review count */
						_n(
							'%s image is ready to approve — AI has generated a suggestion for you to confirm.',
							'%s images are ready to approve — AI has generated suggestions for you to confirm.',
							$review,
							'beepbeep-ai-alt-text-generator'
						),
						number_format_i18n( $review )
					);

				return array_merge( $base, [
					'title'          => __( 'Images ready for review', 'beepbeep-ai-alt-text-generator' ),
					'body'           => $review_body,
					'tone'           => 'healthy',
					'banner_variant' => 'info',
					'semantic_state' => 'needs_review',
				] );

			// ── MISSING_ALT ──────────────────────────────────────────────────
			case self::STATE_MISSING_ALT:
				switch ( $page_context ) {
					case self::CTX_LIBRARY:
						// Action: user is already in the right place — orient to the queue.
						$missing_body = ( $missing > 0 && $review > 0 )
							? sprintf(
								/* translators: 1: missing count, 2: review count */
								__( '%1$s missing ALT text · %2$s awaiting review. Use the filters below to work through them.', 'beepbeep-ai-alt-text-generator' ),
								number_format_i18n( $missing ),
								number_format_i18n( $review )
							)
							: sprintf(
								/* translators: %s: missing count */
								_n(
									'%s image is missing ALT text. Generate it now to improve accessibility and SEO.',
									'%s images are missing ALT text. Generate them now to improve accessibility and SEO.',
									$missing,
									'beepbeep-ai-alt-text-generator'
								),
								number_format_i18n( $missing )
							);
						break;

					case self::CTX_ANALYTICS:
						// Insight → action: analytics is read-only, direct back to library.
						$missing_body = sprintf(
							/* translators: %s: missing count */
							_n(
								'%s image is missing ALT text. Head to your library to generate it.',
								'%s images are missing ALT text. Head to your library to generate them.',
								$missing,
								'beepbeep-ai-alt-text-generator'
							),
							number_format_i18n( $missing )
						);
						break;

					case self::CTX_CREDITS:
						// Monetisation: frame as blocked progress requiring credits.
						$missing_body = sprintf(
							/* translators: %s: missing count */
							_n(
								'%s image still needs ALT text. Add credits to continue generating.',
								'%s images still need ALT text. Add credits to continue generating.',
								$missing,
								'beepbeep-ai-alt-text-generator'
							),
							number_format_i18n( $missing )
						);
						break;

					default: // dashboard
						// Hero card already owns this state fully — suppress the banner
						// to avoid duplicating counts and action copy in two places.
						return [];
				}

				return array_merge( $base, [
					'title'          => __( 'Your library needs attention', 'beepbeep-ai-alt-text-generator' ),
					'body'           => $missing_body,
					'tone'           => 'attention',
					'banner_variant' => 'warning',
					'semantic_state' => 'missing_alt',
				] );

			// ── ALL_CLEAR ────────────────────────────────────────────────────
			case self::STATE_ALL_CLEAR:
			default:
				if ( self::CTX_DASHBOARD === $page_context ) {
					return [];
				}

				$all_clear_body = $complete > 0
					? sprintf(
						/* translators: %s: total optimised count */
						_n(
							'All %s image has ALT text. New uploads are processed automatically.',
							'All %s images have ALT text. New uploads are processed automatically.',
							$complete,
							'beepbeep-ai-alt-text-generator'
						),
						number_format_i18n( $complete )
					)
					: __( 'All images have ALT text. New uploads are processed automatically.', 'beepbeep-ai-alt-text-generator' );

				return array_merge( $base, [
					'title'          => __( 'Your library is fully optimised', 'beepbeep-ai-alt-text-generator' ),
					'body'           => $all_clear_body,
					'tone'           => 'healthy',
					'banner_variant' => 'success',
					'semantic_state' => 'all_clear',
				] );
		}
	}

	/**
	 * Build a resolver-canonical banner directly from raw counts.
	 *
	 * Convenience entry-point for pages (Library, Analytics, Credits) that
	 * don't run the full resolve() pipeline. Computes state from the counts
	 * alone then delegates to build_banner().
	 *
	 * @param string              $page_context One of the CTX_* constants.
	 * @param array<string,mixed> $data {
	 *   @type int         $missing    Images missing ALT text.
	 *   @type int         $review     Images awaiting review.
	 *   @type int         $complete   Images already optimised.
	 *   @type int         $total      Total images.
	 *   @type int         $cr_used    Credits used.
	 *   @type int         $cr_total   Credit limit.
	 *   @type array|null  $job        Active job (status, done, total) or null.
	 *   @type array|null  $systemError System error (code, message) or null.
	 * }
	 * @return array<string,mixed>
	 */
	public static function banner_for_page( string $page_context, array $data ): array {
		$missing  = max( 0, (int) ( $data['missing']  ?? 0 ) );
		$review   = max( 0, (int) ( $data['review']   ?? 0 ) );
		$complete = max( 0, (int) ( $data['complete'] ?? 0 ) );
		$total    = max( 0, (int) ( $data['total']    ?? ( $missing + $review + $complete ) ) );
		$cr_used  = max( 0, (int) ( $data['cr_used']  ?? 0 ) );
		$cr_total = max( 1, (int) ( $data['cr_total'] ?? 1 ) );
		$job      = is_array( $data['job'] ?? null ) && ! empty( $data['job'] ) ? $data['job'] : null;
		$sys_err  = is_array( $data['systemError'] ?? null ) && ! empty( $data['systemError'] ) ? $data['systemError'] : null;

		// Build a minimal normalised context and derive state.
		$ctx = [
			'mediaCount'  => $total,
			'counts'      => [
				'missing'  => $missing,
				'review'   => $review,
				'complete' => $complete,
				'failed'   => 0,
			],
			'credits'     => [ 'used' => $cr_used, 'total' => $cr_total ],
			'job'         => $job,
			'systemError' => $sys_err,
			'last_run_at' => null,
			'is_pro'      => false,
			'plan_slug'   => 'free',
		];

		$state = self::compute_state_id( $ctx );

		return self::build_banner( $state, $page_context, $ctx );
	}

	/**
	 * Build the compact summary stat row shown beneath hero CTAs.
	 *
	 * Returns an array of up to 3 items: [ label, value, mod ].
	 * mod: 'ok' | 'warn' | 'alert' | 'muted' — maps to color in the template.
	 *
	 * @return array<int, array{label: string, value: string, mod: string}>
	 */
	private static function build_hero_summary(
		int $missing, int $review, int $complete,
		int $cr_used, int $cr_total, string $state, bool $is_pro = false
	): array {
		// No meaningful summary for states without images or API key.
		if ( in_array( $state, [ self::STATE_NO_IMAGES ], true ) ) {
			return [];
		}

		$items    = [];
		$cr_pct   = $cr_total > 0 ? (int) round( ( $cr_used / $cr_total ) * 100 ) : 0;
		$cr_left  = max( 0, $cr_total - $cr_used );

		// In NEEDS_REVIEW, "To review" is the primary metric; credits stay muted.
		$is_review_state = ( self::STATE_NEEDS_REVIEW === $state );

		// Summary row: Missing · To review · Credits — shown in the right card.
		$items[] = [
			'label' => __( 'Missing', 'beepbeep-ai-alt-text-generator' ),
			'value' => number_format_i18n( $missing ),
			'mod'   => $missing > 0 ? 'alert' : 'ok',
		];
		$items[] = [
			'label' => __( 'To review', 'beepbeep-ai-alt-text-generator' ),
			'value' => number_format_i18n( $review ),
			'mod'   => $is_review_state ? 'primary' : ( $review > 0 ? 'warn' : 'ok' ),
		];
		$items[] = [
			'label' => __( 'Credits', 'beepbeep-ai-alt-text-generator' ),
			'value' => number_format_i18n( $cr_used ) . ' / ' . number_format_i18n( $cr_total ),
			'mod'   => $is_review_state ? 'muted' : ( $cr_pct >= 100 ? 'alert' : ( $cr_pct >= 80 ? 'warn' : 'muted' ) ),
		];

		return $items;
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Donut builder
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * @param string              $state State ID.
	 * @param array<string,mixed> $ctx   Normalised context.
	 * @return array<string,mixed>
	 */
	private static function build_donut( string $state, array $ctx ): array {
		$counts   = $ctx['counts'];
		$job      = $ctx['job'];
		$total    = max( 1, $ctx['mediaCount'] );
		$complete = (int) ( $counts['complete'] ?? 0 );
		$review   = (int) ( $counts['review'] ?? 0 );
		$missing  = (int) ( $counts['missing'] ?? 0 );
		$pct      = (int) round( ( $complete / $total ) * 100 );

		// Raw segment counts passed through so the hero can build the same
		// multi-color conic-gradient as the logged-out donut.
		$segments = [
			'optimized' => $complete,
			'weak'      => $review,
			'missing'   => $missing,
			'total'     => $total,
		];

		switch ( $state ) {

			case self::STATE_NO_IMAGES:
				return [
					'pct'              => 0,
					'color'            => 'gray',
					'animated'         => false,
					'center_label'     => '—',
					'center_sub_label' => __( 'no images', 'beepbeep-ai-alt-text-generator' ),
					'aria_label'       => __( 'No images in library', 'beepbeep-ai-alt-text-generator' ),
					'segments'         => $segments,
				];

			case self::STATE_QUEUED:
				$job_total = (int) ( $job['total'] ?? 0 );
				$queued_total = $job_total > 0 ? $job_total : $missing;
				return [
					'pct'              => 0,
					'color'            => 'gray',
					'animated'         => false,
					'center_label'     => number_format_i18n( $queued_total ),
					'center_sub_label' => _n( 'queued image', 'queued images', $queued_total, 'beepbeep-ai-alt-text-generator' ),
					'aria_label'       => sprintf(
						/* translators: %s: queued image count */
						_n( '%s image queued; waiting to start', '%s images queued; waiting to start', $queued_total, 'beepbeep-ai-alt-text-generator' ),
						number_format_i18n( $queued_total )
					),
					'segments'         => $segments,
					'job_done'         => 0,
					'job_total'        => $queued_total,
				];

			case self::STATE_PROCESSING:
				$done      = (int) ( $job['done'] ?? 0 );
				$job_total = max( 1, (int) ( $job['total'] ?? 1 ) );
				$job_pct   = (int) round( ( $done / $job_total ) * 100 );
				return [
					'pct'              => $job_pct,
					'color'            => 'blue',
					'animated'         => true,
					'center_label'     => number_format_i18n( $missing ),
					'center_sub_label' => _n( 'image left', 'images left', $missing, 'beepbeep-ai-alt-text-generator' ),
					'aria_label'       => sprintf(
						/* translators: %d: percentage complete */
						__( 'Processing: %d%% complete', 'beepbeep-ai-alt-text-generator' ),
						$job_pct
					),
					'segments'         => $segments,
					'job_pct'          => $job_pct,
					'job_done'         => $done,
					'job_total'        => $job_total,
				];

			case self::STATE_ERROR:
				return [
					'pct'              => $pct,
					'color'            => 'amber',
					'animated'         => false,
					'center_label'     => '!',
					'center_sub_label' => __( 'error', 'beepbeep-ai-alt-text-generator' ),
					'aria_label'       => __( 'Job stopped with errors', 'beepbeep-ai-alt-text-generator' ),
					'segments'         => $segments,
				];

			case self::STATE_QUOTA_EXHAUSTED:
				return [
					'pct'              => $pct,
					'color'            => 'amber',
					'animated'         => false,
					'center_label'     => number_format_i18n( $missing ),
					'center_sub_label' => __( 'credits needed', 'beepbeep-ai-alt-text-generator' ),
					'aria_label'       => sprintf(
						/* translators: %d: percentage of images with ALT text */
						__( '%d%% of images have ALT text — credits exhausted', 'beepbeep-ai-alt-text-generator' ),
						$pct
					),
					'segments'         => $segments,
				];

			case self::STATE_NEEDS_REVIEW:
				return [
					'pct'              => $pct,
					'color'            => 'blue',
					'animated'         => false,
					'center_label'     => (string) $review,
					'center_sub_label' => _n( 'to review', 'to review', $review, 'beepbeep-ai-alt-text-generator' ),
					'aria_label'       => sprintf(
						/* translators: %d: images needing review */
						_n( '%d image needs review', '%d images need review', $review, 'beepbeep-ai-alt-text-generator' ),
						$review
					),
					'segments'         => $segments,
				];

			case self::STATE_MISSING_ALT:
				return [
					'pct'              => $pct,
					'color'            => 'amber',
					'animated'         => false,
					'center_label'     => number_format_i18n( $missing ),
					'center_sub_label' => _n( 'missing ALT', 'missing ALT', $missing, 'beepbeep-ai-alt-text-generator' ),
					'aria_label'       => sprintf(
						/* translators: 1: complete count, 2: total count */
						__( '%1$s of %2$s images have ALT text', 'beepbeep-ai-alt-text-generator' ),
						number_format_i18n( $complete ),
						number_format_i18n( $total )
					),
					'segments'         => $segments,
				];

			case self::STATE_ALL_CLEAR:
			default:
				return [
					'pct'              => 100,
					'color'            => 'green',
					'animated'         => false,
					'center_label'     => '✓',
					'center_sub_label' => __( 'all clear', 'beepbeep-ai-alt-text-generator' ),
					'aria_label'       => __( 'All images have ALT text', 'beepbeep-ai-alt-text-generator' ),
					'segments'         => $segments,
				];
		}
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Pills builder — only non-zero pills included in array
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * @param string              $state State ID.
	 * @param array<string,mixed> $ctx   Normalised context.
	 * @return list<array<string,mixed>>
	 */
	private static function build_pills( string $state, array $ctx ): array {
		if ( self::STATE_NO_IMAGES === $state ) {
			return [];
		}

		$counts = $ctx['counts'];
		$job    = $ctx['job'];
		$pills  = [];

		$candidates = [
			[
				'id'    => 'missing',
				'count' => (int) ( $counts['missing'] ?? 0 ),
				'color' => 'red',
				'label' => static function ( int $n ): string {
					return sprintf(
						/* translators: %s: count */
						_n( '%s missing', '%s missing', $n, 'beepbeep-ai-alt-text-generator' ),
						number_format_i18n( $n )
					);
				},
			],
			[
				'id'    => 'review',
				'count' => (int) ( $counts['review'] ?? 0 ),
				'color' => 'amber',
				'label' => static function ( int $n ): string {
					return sprintf(
						/* translators: %s: count */
						_n( '%s to review', '%s to review', $n, 'beepbeep-ai-alt-text-generator' ),
						number_format_i18n( $n )
					);
				},
			],
			[
				'id'    => 'complete',
				'count' => (int) ( $counts['complete'] ?? 0 ),
				'color' => 'green',
				'label' => static function ( int $n ): string {
					return sprintf(
						/* translators: %s: count */
						_n( '%s complete', '%s complete', $n, 'beepbeep-ai-alt-text-generator' ),
						number_format_i18n( $n )
					);
				},
			],
			[
				'id'    => 'failed',
				'count' => (int) ( $counts['failed'] ?? 0 ),
				'color' => 'red',
				'label' => static function ( int $n ): string {
					return sprintf(
						/* translators: %s: count */
						_n( '%s failed', '%s failed', $n, 'beepbeep-ai-alt-text-generator' ),
						number_format_i18n( $n )
					);
				},
			],
		];

		foreach ( $candidates as $candidate ) {
			if ( $candidate['count'] > 0 ) {
				$pills[] = [
					'id'    => $candidate['id'],
					'label' => ( $candidate['label'] )( $candidate['count'] ),
					'count' => $candidate['count'],
					'color' => $candidate['color'],
				];
			}
		}

		// Add queued / processing pill only while a job is active.
		if ( self::STATE_QUEUED === $state && $job !== null ) {
			$queued_count = max( 0, (int) ( $job['total'] ?? 0 ) - (int) ( $job['done'] ?? 0 ) );
			if ( $queued_count > 0 ) {
				$pills[] = [
					'id'    => 'queued',
					'label' => sprintf(
						/* translators: %s: count */
						_n( '%s queued', '%s queued', $queued_count, 'beepbeep-ai-alt-text-generator' ),
						number_format_i18n( $queued_count )
					),
					'count' => $queued_count,
					'color' => 'gray',
				];
			}
		} elseif ( self::STATE_PROCESSING === $state && $job !== null ) {
			$processing_count = max( 0, (int) ( $job['total'] ?? 0 ) - (int) ( $job['done'] ?? 0 ) );
			if ( $processing_count > 0 ) {
				$pills[] = [
					'id'    => 'processing',
					'label' => sprintf(
						/* translators: %s: count */
						_n( '%s processing', '%s processing', $processing_count, 'beepbeep-ai-alt-text-generator' ),
						number_format_i18n( $processing_count )
					),
					'count' => $processing_count,
					'color' => 'blue',
				];
			}
		}

		return $pills;
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Usage bar builder
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * @param array<string,mixed> $ctx Normalised context.
	 * @return array<string,mixed>
	 */
	// Pro plan credit cap used for the "unlock" phantom bar in the hero.
	const PRO_CREDITS_CAP = 500;

	private static function build_usage( array $ctx ): array {
		$credits  = $ctx['credits'];
		$used     = (int) ( $credits['used'] ?? 0 );
		$total    = max( 1, (int) ( $credits['total'] ?? 1 ) );
		$pct      = min( 100, (int) round( ( $used / $total ) * 100 ) );

		// Color thresholds.
		if ( $total <= 0 || $pct > 90 ) {
			$color = 'red';
		} elseif ( $pct >= 50 ) {
			$color = 'amber';
		} else {
			$color = 'green';
		}

		// Hidden on NO_IMAGES to reduce noise (credits still exist, but not relevant here).
		$state  = $ctx['_resolved_state'] ?? '';
		$hidden = ( self::STATE_NO_IMAGES === $state );

		// Pro bar: show only when current plan is smaller than PRO_CREDITS_CAP.
		$pro_cap        = self::PRO_CREDITS_CAP;
		$show_pro_bar   = ( $total < $pro_cap );
		$pro_pct        = $show_pro_bar ? min( 100, (int) round( ( $used / $pro_cap ) * 100 ) ) : 0;

		return [
			'used'        => $used,
			'total'       => $total,
			'pct'         => $pct,
			'color'       => $color,
			'label'       => sprintf(
				/* translators: 1: used, 2: total */
				__( '%1$s / %2$s credits used', 'beepbeep-ai-alt-text-generator' ),
				number_format_i18n( $used ),
				number_format_i18n( $total )
			),
			'hidden'      => $hidden,
			'pro_cap'     => $pro_cap,
			'pro_pct'     => $pro_pct,
			'show_pro_bar'=> $show_pro_bar,
			'pro_label'   => sprintf(
				/* translators: %s: pro credit cap */
				__( '%s credits with Pro', 'beepbeep-ai-alt-text-generator' ),
				number_format_i18n( $pro_cap )
			),
		];
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Surface builder
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * @param string              $state State ID.
	 * @param array<string,mixed> $ctx   Normalised context.
	 * @return array<string,mixed>
	 */
	private static function build_surface( string $state, array $ctx ): array {
		switch ( $state ) {

			case self::STATE_NO_IMAGES:
				return [
					'component' => 'QuickStartChecklist',
					'props'     => [
						'steps'             => self::quick_start_steps(),
						'settings_url'      => admin_url( 'admin.php?page=bbai-settings' ),
						'media_library_url' => admin_url( 'upload.php' ),
					],
				];

			case self::STATE_PROCESSING:
				$job = $ctx['job'] ?? [];
				return [
					'component' => 'ActivityLog',
					'props'     => [
						'entries'    => [],        // populated by client polling
						'done'       => (int) ( $job['done'] ?? 0 ),
						'total'      => (int) ( $job['total'] ?? 0 ),
						'started_at' => null,
						'job_status' => self::normalize_job_status( (string) ( $job['status'] ?? 'processing' ) ),
					],
				];

			case self::STATE_QUEUED:
				$job = $ctx['job'] ?? [];
				return [
					'component' => 'ActivityLog',
					'props'     => [
						'entries'    => [],
						'done'       => 0,
						'total'      => (int) ( $job['total'] ?? 0 ),
						'started_at' => null,
						'job_status' => 'queued',
					],
				];

			case self::STATE_ERROR:
				$err = $ctx['systemError'] ?? [];
				return [
					'component' => 'ErrorDetailPanel',
					'props'     => [
						'failed_items'  => [],    // populated by client from /list?scope=failed
						'error_code'    => sanitize_key( (string) ( $err['code'] ?? 'UNKNOWN' ) ),
						'error_message' => sanitize_text_field( (string) ( $err['message'] ?? '' ) ),
						'retry_action'  => 'retry-failed',
					],
				];

			case self::STATE_QUOTA_EXHAUSTED:
				return [
					'component' => 'CreditTopUp',
					'props'     => [
						'current_credits' => $ctx['credits']['total'] - $ctx['credits']['used'],
						'plans'           => [],  // populated by client from /plans
						'billing_url'     => '',  // populated by client
						'queued_count'    => (int) ( $ctx['counts']['missing'] ?? 0 ),
					],
				];

			case self::STATE_NEEDS_REVIEW:
				return [
					'component' => 'ReviewQueue',
					'props'     => [
						'items'        => [],     // populated by client from /list?scope=needs_review
						'pagination'   => [ 'page' => 1, 'per_page' => 20 ],
						'bulk_actions' => [ 'approve_all' ],
					],
				];

			case self::STATE_MISSING_ALT:
				return [
					'component' => 'MissingAltTable',
					'props'     => [
						'items'        => [],     // populated by client from /list?scope=missing
						'pagination'   => [ 'page' => 1, 'per_page' => 20 ],
						'filters'      => [],
						'bulk_actions' => [ 'generate_selected', 'generate_all' ],
					],
				];

			case self::STATE_ALL_CLEAR:
			default:
				return [
					'component' => 'RecentActivity',
					'props'     => [
						'entries'     => [],      // populated by client
						'last_run_at' => $ctx['last_run_at'],
					],
				];
		}
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Context normalizer
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * Normalise and sanitise raw resolver input.
	 *
	 * @param array<string,mixed> $ctx Raw input.
	 * @return array<string,mixed>
	 */
	private static function normalize_ctx( array $ctx ): array {
		$counts = is_array( $ctx['counts'] ?? null ) ? $ctx['counts'] : [];
		$credits = is_array( $ctx['credits'] ?? null ) ? $ctx['credits'] : [];

		$total_credits = max( 1, (int) ( $credits['total'] ?? $credits['limit'] ?? 1 ) );
		$used_credits  = max( 0, (int) ( $credits['used'] ?? 0 ) );

		$job = null;
		if ( is_array( $ctx['job'] ?? null ) && ! empty( $ctx['job'] ) ) {
			$raw_job = $ctx['job'];
			$status  = self::normalize_job_status( (string) ( $raw_job['status'] ?? $raw_job['state'] ?? 'idle' ) );
			$active  = array_key_exists( 'active', $raw_job )
				? ! empty( $raw_job['active'] )
				: in_array( $status, [ 'queued', 'processing' ], true );
			$job     = [
				'status'      => $status,
				'state'       => isset( $raw_job['state'] ) ? sanitize_key( (string) $raw_job['state'] ) : strtoupper( $status ),
				'active'      => $active,
				'pausable'    => ! empty( $raw_job['pausable'] ),
				'done'        => max( 0, (int) ( $raw_job['done'] ?? 0 ) ),
				'total'       => max( 0, (int) ( $raw_job['total'] ?? 0 ) ),
				'eta_seconds' => isset( $raw_job['eta_seconds'] ) ? max( 0, (int) $raw_job['eta_seconds'] ) : null,
				'error'       => isset( $raw_job['error'] ) ? sanitize_text_field( (string) $raw_job['error'] ) : null,
			];
			if ( ! self::is_active_generation_job( $job ) ) {
				$job = null;
			}
		}

		$system_error = null;
		if ( is_array( $ctx['systemError'] ?? null ) && ! empty( $ctx['systemError'] ) ) {
			$raw_err      = $ctx['systemError'];
			$system_error = [
				'code'    => sanitize_key( (string) ( $raw_err['code'] ?? 'UNKNOWN' ) ),
				'message' => sanitize_text_field( (string) ( $raw_err['message'] ?? '' ) ),
			];
		}

		$user_type = sanitize_key( (string) ( $ctx['user_type'] ?? 'free' ) );
		if ( ! in_array( $user_type, [ 'trial', 'free', 'pro' ], true ) ) {
			$user_type = 'free';
		}

		return [
			'mediaCount'  => max( 0, (int) ( $ctx['mediaCount'] ?? $ctx['media_count'] ?? 0 ) ),
			'counts'      => [
				'missing'  => max( 0, (int) ( $counts['missing'] ?? 0 ) ),
				'review'   => max( 0, (int) ( $counts['review'] ?? $counts['weak'] ?? 0 ) ),
				'complete' => max( 0, (int) ( $counts['complete'] ?? $counts['optimized'] ?? 0 ) ),
				'failed'   => max( 0, (int) ( $counts['failed'] ?? 0 ) ),
			],
			'credits'     => [
				'used'  => $used_credits,
				'total' => $total_credits,
			],
			'job'         => $job,
			'systemError' => $system_error,
			'is_pro'      => ! empty( $ctx['is_pro'] ),
			'plan_slug'   => sanitize_key( (string) ( $ctx['plan_slug'] ?? 'free' ) ),
			'last_run_at' => isset( $ctx['last_run_at'] ) ? sanitize_text_field( (string) $ctx['last_run_at'] ) : null,
			'user_type'   => $user_type,
			'trial_limit' => max( 0, (int) ( $ctx['trial_limit'] ?? 0 ) ),
			'trial_used'  => max( 0, (int) ( $ctx['trial_used'] ?? 0 ) ),
			'signup_url'  => isset( $ctx['signup_url'] ) ? esc_url_raw( (string) $ctx['signup_url'] ) : '',
		];
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Helpers
	// ──────────────────────────────────────────────────────────────────────────

	private static function normalize_job_status( string $status ): string {
		$status = strtolower( trim( $status ) );

		if ( in_array( $status, [ 'queued', 'queue', 'pending', 'scheduled', 'waiting' ], true ) ) {
			return 'queued';
		}

		if ( in_array( $status, [ 'processing', 'running', 'in_progress', 'started', 'working' ], true ) ) {
			return 'processing';
		}

		if ( in_array( $status, [ 'complete', 'completed', 'success', 'succeeded' ], true ) ) {
			return 'completed';
		}

		if ( in_array( $status, [ 'failed', 'error', 'cancelled', 'canceled', 'stale', 'expired' ], true ) ) {
			return 'failed';
		}

		return 'idle';
	}

	private static function is_active_generation_job( $job ): bool {
		if ( ! is_array( $job ) || empty( $job ) ) {
			return false;
		}

		$status = self::normalize_job_status( (string) ( $job['status'] ?? $job['state'] ?? '' ) );
		if ( ! in_array( $status, [ 'queued', 'processing' ], true ) ) {
			return false;
		}

		return array_key_exists( 'active', $job ) ? ! empty( $job['active'] ) : true;
	}

	/**
	 * Format an ETA in seconds into a human-readable string.
	 *
	 * @param int|null $eta_seconds Seconds remaining.
	 * @return string
	 */
	private static function format_eta( ?int $eta_seconds ): string {
		if ( null === $eta_seconds || $eta_seconds <= 0 ) {
			return __( 'a moment', 'beepbeep-ai-alt-text-generator' );
		}
		if ( $eta_seconds < 60 ) {
			return sprintf(
				/* translators: %ds: seconds */
				__( '%ds', 'beepbeep-ai-alt-text-generator' ),
				$eta_seconds
			);
		}
		$mins = (int) ceil( $eta_seconds / 60 );
		return sprintf(
			/* translators: %dm: minutes */
			__( '%dm', 'beepbeep-ai-alt-text-generator' ),
			$mins
		);
	}

	/**
	 * Quick-start checklist steps for the NO_IMAGES surface.
	 *
	 * @return list<array<string,string>>
	 */
	private static function quick_start_steps(): array {
		return [
			[
				'id'    => 'api_key',
				'label' => __( 'Connect your API key in settings', 'beepbeep-ai-alt-text-generator' ),
				'href'  => admin_url( 'admin.php?page=bbai-settings' ),
			],
			[
				'id'    => 'language',
				'label' => __( 'Set your preferred output language', 'beepbeep-ai-alt-text-generator' ),
				'href'  => admin_url( 'admin.php?page=bbai-settings' ),
			],
			[
				'id'    => 'upload',
				'label' => __( 'Upload images to your media library', 'beepbeep-ai-alt-text-generator' ),
				'href'  => admin_url( 'upload.php' ),
			],
		];
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Context builder — assembles ctx from live WordPress data
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * Build a resolver context from live site data for the current request.
	 *
	 * Call this from the REST endpoint or the PHP dashboard partial.
	 *
	 * @param array<string,mixed> $usage_stats  Usage payload (used, total/limit, remaining).
	 * @param array<string,mixed> $coverage     Coverage scan payload (missing, weak/review, optimized, total).
	 * @param array<string,mixed> $job_data     Active job payload or empty array.
	 * @param array<string,mixed> $system_error System error payload or empty array.
	 * @param string|null         $last_run_at  ISO 8601 timestamp of last completed job.
	 * @return array<string,mixed>
	 */
	/**
	 * @param array<string,mixed> $usage_stats  Usage payload (used, total/limit, remaining).
	 * @param array<string,mixed> $coverage     Coverage scan payload (missing, weak/review, optimized, total).
	 * @param array<string,mixed> $job_data     Active job payload or empty array.
	 * @param array<string,mixed> $system_error System error payload or empty array.
	 * @param string|null         $last_run_at  ISO 8601 timestamp of last completed job.
	 * @param array<string,mixed> $plan         Plan context: is_pro (bool), plan_slug (string).
	 * @return array<string,mixed>
	 */
	public static function build_ctx(
		array $usage_stats,
		array $coverage,
		array $job_data = [],
		array $system_error = [],
		?string $last_run_at = null,
		array $plan = []
	): array {
		$missing  = max( 0, (int) ( $coverage['missing'] ?? $coverage['missing_count'] ?? 0 ) );
		$review   = max( 0, (int) ( $coverage['weak'] ?? $coverage['review'] ?? $coverage['weak_count'] ?? $coverage['needs_review'] ?? 0 ) );
		$complete = max( 0, (int) ( $coverage['optimized'] ?? $coverage['optimized_count'] ?? $coverage['complete'] ?? 0 ) );
		$failed   = max( 0, (int) ( $coverage['failed'] ?? 0 ) );
		$total    = max( 0, (int) ( $coverage['total_images'] ?? $coverage['total'] ?? ( $missing + $review + $complete ) ) );

		$used  = max( 0, (int) ( $usage_stats['credits_used'] ?? $usage_stats['used'] ?? 0 ) );
		$limit = max( 1, (int) ( $usage_stats['credits_total'] ?? $usage_stats['limit'] ?? 50 ) );

		// user_type: 'trial' | 'free' | 'pro'
		$user_type = sanitize_key( (string) ( $plan['user_type'] ?? 'free' ) );
		if ( ! in_array( $user_type, [ 'trial', 'free', 'pro' ], true ) ) {
			$user_type = 'free';
		}

		return [
			'mediaCount'    => $total,
			'counts'        => [
				'missing'  => $missing,
				'review'   => $review,
				'complete' => $complete,
				'failed'   => $failed,
			],
			'credits'       => [
				'used'  => $used,
				'total' => $limit,
			],
			'job'           => ! empty( $job_data ) ? $job_data : null,
			'systemError'   => ! empty( $system_error ) ? $system_error : null,
			'last_run_at'   => $last_run_at,
			'is_pro'        => ! empty( $plan['is_pro'] ),
			'plan_slug'     => sanitize_key( (string) ( $plan['plan_slug'] ?? 'free' ) ),
			'user_type'     => $user_type,
			'trial_limit'   => max( 0, (int) ( $plan['trial_limit'] ?? 0 ) ),
			'trial_used'    => max( 0, (int) ( $plan['trial_used'] ?? 0 ) ),
			'signup_url'    => isset( $plan['signup_url'] ) ? esc_url_raw( (string) $plan['signup_url'] ) : '',
		];
	}
}
