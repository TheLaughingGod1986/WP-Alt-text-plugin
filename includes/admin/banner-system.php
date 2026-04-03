<?php
/**
 * Shared product banner: single highest-priority system state, normalized command_hero payloads.
 *
 * @package BeepBeep_AI
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** Page contexts for priority rules. */
const BBAI_BANNER_CTX_DASHBOARD = 'dashboard';
const BBAI_BANNER_CTX_LIBRARY   = 'library';
const BBAI_BANNER_CTX_ANALYTICS = 'analytics';
const BBAI_BANNER_CTX_USAGE     = 'usage';

/** Resolved logical states (single active banner; strict priority). */
const BBAI_BANNER_STATE_HEALTHY          = 'healthy';
const BBAI_BANNER_STATE_NEEDS_ATTENTION  = 'needs_attention';
const BBAI_BANNER_STATE_LOW_CREDITS      = 'low_credits';
const BBAI_BANNER_STATE_OUT_OF_CREDITS   = 'out_of_credits';
/** Dashboard-only celebration slot (early activation); never competes with credit/attention banners. */
const BBAI_BANNER_STATE_FIRST_SUCCESS    = 'first_success';
/** Explicit no primary status-banner render (opt-in via filter or future callers). */
const BBAI_BANNER_STATE_NONE             = 'none';

/**
 * Credits remaining at or below this value (and > 0) => low_credits banner state.
 * Must match client-side dashboard hero logic.
 */
const BBAI_BANNER_LOW_CREDITS_THRESHOLD = 10;

/**
 * Single source of truth for "images need attention" in shared banners: missing ALT + needs review (weak),
 * using the same coverage scan and row rules as ALT Library (`Core::get_alt_text_coverage_scan`).
 *
 * @param array<string, mixed>|null $coverage_scan Optional pre-fetched scan from the same request (avoids a second Core bootstrap when already available).
 *
 * @return array{
 *   missing: int,
 *   needs_review: int,
 *   total: int,
 *   total_images: int,
 *   optimized_count: int
 * }
 */
function bbai_get_attention_counts(?array $coverage_scan = null): array
{
    $empty = [
        'missing'           => 0,
        'needs_review'      => 0,
        'total'             => 0,
        'total_images'      => 0,
        'optimized_count'   => 0,
    ];

    $c = null;
    if (
        is_array($coverage_scan)
        && isset($coverage_scan['images_missing_alt'])
        && array_key_exists('needs_review_count', $coverage_scan)
    ) {
        $c = $coverage_scan;
    }

    if (null === $c) {
        if (!defined('BEEPBEEP_AI_PLUGIN_DIR')) {
            return $empty;
        }
        try {
            if (!class_exists(\BeepBeepAI\AltTextGenerator\Core::class)) {
                $core_file = BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-core.php';
                if (is_readable($core_file)) {
                    require_once $core_file;
                }
            }
            if (!class_exists(\BeepBeepAI\AltTextGenerator\Core::class)) {
                return $empty;
            }
            $core = new \BeepBeepAI\AltTextGenerator\Core();
            $c    = $core->get_alt_text_coverage_scan(false);
        } catch (\Throwable $e) {
            return $empty;
        }
    }

    if (!is_array($c)) {
        return $empty;
    }

    $missing        = max(0, (int) ($c['images_missing_alt'] ?? 0));
    $needs_review   = max(0, (int) ($c['needs_review_count'] ?? 0));
    $optimized      = max(0, (int) ($c['optimized_count'] ?? 0));
    if ($optimized <= 0 && isset($c['images_with_alt'])) {
        $optimized = max(0, (int) $c['images_with_alt'] - $needs_review);
    }
    $total_images = max(0, (int) ($c['total_images'] ?? 0));
    $sum          = $missing + $needs_review + $optimized;
    if ($total_images <= 0 && $sum > 0) {
        $total_images = $sum;
    }

    return [
        'missing'         => $missing,
        'needs_review'    => $needs_review,
        'total'           => $missing + $needs_review,
        'total_images'    => $total_images,
        'optimized_count' => $optimized,
    ];
}

/**
 * Normalize dashboard $bbai_dashboard_state into a resolver snapshot.
 *
 * @param array<string, mixed> $d Dashboard state from dashboard-body.
 * @return array<string, mixed>
 */
function bbai_banner_snapshot_from_dashboard_state(array $d): array
{
    return [
        'missing_count'     => max(0, (int) ($d['missingCount'] ?? 0)),
        'weak_count'        => max(0, (int) ($d['weakCount'] ?? 0)),
        'total_images'      => max(0, (int) ($d['totalImages'] ?? 0)),
        'credits_used'      => max(0, (int) ($d['creditsUsed'] ?? 0)),
        'credits_limit'     => max(1, (int) ($d['creditsLimit'] ?? 50)),
        'credits_remaining' => max(0, (int) ($d['creditsRemaining'] ?? 0)),
        'usage_percent'     => min(100, max(0, (int) ($d['usagePercent'] ?? 0))),
        'is_pro_plan'       => !empty($d['isProPlan']),
        'is_first_run'      => !empty($d['isFirstRun']),
        'is_low_credits'    => !empty($d['isLowCredits']),
        'is_out_of_credits' => !empty($d['isOutOfCredits']),
        'has_ever_generated_alt' => !empty($d['hasEverGeneratedAlt']),
        'optimized_count'   => max(0, (int) ($d['optimizedCount'] ?? 0)),
        'auth_state'        => (string) ($d['authState'] ?? ''),
        'quota_type'        => (string) ($d['quotaType'] ?? ''),
        'quota_state'       => (string) ($d['quotaState'] ?? ''),
        'signup_required'   => !empty($d['signupRequired']),
        'upgrade_required'  => !empty($d['upgradeRequired']),
        'is_trial'          => !empty($d['isTrial']),
        'free_plan_offer'   => max(0, (int) ($d['freePlanOffer'] ?? 50)),
        'low_credit_threshold' => max(0, (int) ($d['lowCreditThreshold'] ?? 0)),
        'plan_label'        => (string) ($d['planLabel'] ?? ''),
        'remaining_line'    => (string) ($d['remainingLine'] ?? ''),
        'reset_timing'      => (string) ($d['resetTiming'] ?? ''),
        'library_url'       => (string) ($d['libraryUrl'] ?? admin_url('admin.php?page=bbai-library')),
        'missing_library_url' => (string) ($d['missingLibraryUrl'] ?? admin_url('admin.php?page=bbai-library')),
        'needs_review_library_url' => (string) ($d['needsReviewLibraryUrl'] ?? bbai_alt_library_needs_review_url()),
        'usage_url'         => (string) ($d['usageUrl'] ?? admin_url('admin.php?page=bbai-credit-usage')),
        'settings_url'      => (string) ($d['settingsUrl'] ?? admin_url('admin.php?page=bbai-settings')),
        'guide_url'         => (string) ($d['guideUrl'] ?? admin_url('admin.php?page=bbai-guide')),
        'billing_portal_url' => '',
        'upgrade_url'       => '',
        'has_connected_account' => !empty($d['hasConnectedAccount']),
        'plan_slug'         => strtolower((string) ($d['planSlug'] ?? 'free')),
    ];
}

/**
 * Snapshot for Analytics / Library-style callers (already computed globals).
 *
 * @param array<string, mixed> $overrides Keys aligned with snapshot + URLs.
 */
function bbai_banner_snapshot_merge(array $overrides): array
{
    $defaults = [
        'missing_count'       => 0,
        'weak_count'          => 0,
        'total_images'        => 0,
        'credits_used'        => 0,
        'credits_limit'       => 50,
        'credits_remaining'   => 0,
        'usage_percent'       => 0,
        'is_pro_plan'         => false,
        'is_first_run'        => false,
        'is_low_credits'      => false,
        'is_out_of_credits'   => false,
        'has_ever_generated_alt' => false,
        'optimized_count'     => 0,
        'auth_state'          => '',
        'quota_type'          => '',
        'quota_state'         => '',
        'signup_required'     => false,
        'upgrade_required'    => false,
        'is_trial'            => false,
        'free_plan_offer'     => 50,
        'low_credit_threshold' => 0,
        'plan_label'          => '',
        'remaining_line'      => '',
        'reset_timing'        => '',
        'library_url'         => admin_url('admin.php?page=bbai-library'),
        'missing_library_url' => add_query_arg(['page' => 'bbai-library', 'status' => 'missing'], admin_url('admin.php')),
        'needs_review_library_url' => bbai_alt_library_needs_review_url(),
        'usage_url'           => admin_url('admin.php?page=bbai-credit-usage'),
        'settings_url'        => admin_url('admin.php?page=bbai-settings'),
        'guide_url'           => admin_url('admin.php?page=bbai-guide'),
        'billing_portal_url'  => '',
        'upgrade_url'         => '',
        'has_connected_account' => true,
        'plan_slug'           => 'free',
    ];

    return array_merge($defaults, $overrides);
}

/**
 * Issue sentence for shared credit-cycle + library-attention banners (correct pluralisation).
 *
 * @param int $issue_count Missing ALT + needs-review combined.
 */
function bbai_banner_build_issue_attention_message(int $issue_count): string
{
    return bbai_copy_attention_issue_sentence($issue_count);
}

/**
 * Supporting line for low / out-of-credits banners (credits + optional issues, bullet-separated).
 *
 * Format: "{n} optimization(s) left this cycle" [ " • " "{issues} image(s) need attention" ]
 *
 * @param int $credits_remaining Credits left this cycle (0 allowed).
 * @param int $issue_count       Missing ALT + needs-review combined.
 */
function bbai_banner_build_credit_supporting_line(int $credits_remaining, int $issue_count): string
{
    $credits_remaining = max(0, $credits_remaining);
    $issue_count       = max(0, $issue_count);
    $credits_fmt      = number_format_i18n($credits_remaining);
    $credits_segment  = '';

    if ($credits_remaining <= 0) {
        $credits_segment = __('You can still review your existing ALT text. Upgrade to continue generating.', 'beepbeep-ai-alt-text-generator');
    } else {
        $credits_segment = sprintf(
            /* translators: %s: credits remaining (locale-formatted number) */
            _n(
                '%s credit left this month. Keep your library moving.',
                '%s credits left this month. Keep your library moving.',
                $credits_remaining,
                'beepbeep-ai-alt-text-generator'
            ),
            $credits_fmt
        );
    }

    if ($issue_count <= 0) {
        return $credits_segment;
    }

    $issues_segment = bbai_copy_attention_issue_sentence($issue_count);

    return $credits_segment . ' • ' . $issues_segment;
}

/**
 * Whether the snapshot represents an anonymous trial user.
 *
 * @param array<string, mixed> $input
 */
function bbai_banner_is_anonymous_trial_contract(array $input): bool
{
    $auth_state = strtolower(trim((string) ($input['auth_state'] ?? '')));
    $quota_type = strtolower(trim((string) ($input['quota_type'] ?? '')));

    return 'anonymous' === $auth_state || 'trial' === $quota_type || !empty($input['is_trial']);
}

/**
 * Normalize snapshot or inline counts for the global banner resolver.
 *
 * @param array<string, mixed> $input
 * @return array{
 *   missing_count:int,
 *   needs_review_count:int,
 *   total_images:int,
 *   credits_remaining:int,
 *   credits_limit:int,
 *   low_credit_threshold:int,
 *   total_issues:int,
 *   auth_state:string,
 *   quota_type:string,
 *   quota_state:string,
 *   signup_required:bool,
 *   upgrade_required:bool,
 *   free_plan_offer:int,
 *   is_anonymous_trial:bool
 * }
 */
function bbai_banner_normalize_banner_data(array $input): array
{
    $missing       = max(0, (int) ($input['missing_count'] ?? 0));
    $needs_review  = max(0, (int) ($input['needs_review_count'] ?? $input['weak_count'] ?? 0));
    $total         = max(0, (int) ($input['total_images'] ?? 0));
    $rem           = max(0, (int) ($input['credits_remaining'] ?? 0));
    $limit         = max(1, (int) ($input['credits_limit'] ?? $input['credit_limit'] ?? 50));
    $thresh_input  = isset($input['low_credit_threshold']) ? (int) $input['low_credit_threshold'] : null;
    $total_issues  = $missing + $needs_review;
    $auth_state    = trim((string) ($input['auth_state'] ?? ''));
    $quota_type    = trim((string) ($input['quota_type'] ?? ''));
    $quota_state   = trim((string) ($input['quota_state'] ?? ''));
    $signup_required = !empty($input['signup_required']);
    $upgrade_required = !empty($input['upgrade_required']);
    $free_plan_offer = max(0, (int) ($input['free_plan_offer'] ?? 50));
    $is_anonymous_trial = bbai_banner_is_anonymous_trial_contract($input);
    $thresh = null !== $thresh_input && $thresh_input > 0
        ? $thresh_input
        : ($is_anonymous_trial
            ? ( function_exists('\BeepBeepAI\AltTextGenerator\bbai_get_trial_near_limit_threshold')
                ? max(1, (int) \BeepBeepAI\AltTextGenerator\bbai_get_trial_near_limit_threshold($limit))
                : min(2, max(1, $limit - 1)) )
            : max(0, (int) apply_filters('bbai_banner_low_credits_threshold', BBAI_BANNER_LOW_CREDITS_THRESHOLD)));

    return [
        'missing_count'        => $missing,
        'needs_review_count'   => $needs_review,
        'total_images'         => $total,
        'credits_remaining'    => $rem,
        'credits_limit'        => $limit,
        'low_credit_threshold' => $thresh,
        'total_issues'         => $total_issues,
        'auth_state'           => $auth_state,
        'quota_type'           => $quota_type,
        'quota_state'          => $quota_state,
        'signup_required'      => $signup_required,
        'upgrade_required'     => $upgrade_required,
        'free_plan_offer'      => $free_plan_offer,
        'is_anonymous_trial'   => $is_anonymous_trial,
    ];
}

/**
 * Single primary top-of-page banner state (strict priority). Use this for gating which hero/banner renders.
 *
 * Priority:
 * 1. out_of_credits
 * 2. low_credits
 * 3. needs_attention
 * 4. first_success (dashboard context only; early activation, no open issues, credits above low threshold)
 * 5. none
 *
 * Product slot labels (lowCredits → missingAlt → milestone): see bbai_get_active_banner_slot_from_state().
 *
 * @param array<string, mixed> $snapshot Keys aligned with bbai_banner_snapshot_merge + optional flags.
 * @param string               $page_context BBAI_BANNER_CTX_*; first_success applies only to dashboard.
 */
function bbai_get_primary_banner_state(array $snapshot, string $page_context = ''): string
{
    $d = bbai_banner_normalize_banner_data($snapshot);

    if (0 === $d['credits_remaining']) {
        return BBAI_BANNER_STATE_OUT_OF_CREDITS;
    }

    if ($d['credits_remaining'] > 0 && $d['credits_remaining'] <= $d['low_credit_threshold']) {
        return BBAI_BANNER_STATE_LOW_CREDITS;
    }

    if ($d['total_issues'] > 0) {
        return BBAI_BANNER_STATE_NEEDS_ATTENTION;
    }

    if (
        BBAI_BANNER_CTX_DASHBOARD === $page_context
        && bbai_banner_snapshot_first_success_eligible($snapshot, $d)
    ) {
        return BBAI_BANNER_STATE_FIRST_SUCCESS;
    }

    return BBAI_BANNER_STATE_NONE;
}

/**
 * Canonical top-banner resolver alias (readability helper for page templates).
 *
 * @param array<string, mixed> $snapshot
 */
function bbai_resolve_top_banner(array $snapshot, string $page_context = ''): string
{
    return bbai_get_primary_banner_state($snapshot, $page_context);
}

/**
 * Map internal banner state to a single product slot key (mirrors client `bbaiGetActiveBanner`).
 *
 * @return 'lowCredits'|'missingAlt'|'milestone'|null
 */
function bbai_get_active_banner_slot_from_state(string $state): ?string
{
    if ('' !== $state && 0 === strpos($state, 'plan_')) {
        return null;
    }
    if (BBAI_BANNER_STATE_OUT_OF_CREDITS === $state || BBAI_BANNER_STATE_LOW_CREDITS === $state) {
        return 'lowCredits';
    }
    if (BBAI_BANNER_STATE_NEEDS_ATTENTION === $state) {
        return 'missingAlt';
    }
    if (BBAI_BANNER_STATE_FIRST_SUCCESS === $state) {
        return 'milestone';
    }

    return null;
}

/**
 * Active top-banner slot from snapshot (strict priority via bbai_get_primary_banner_state).
 *
 * @return 'lowCredits'|'missingAlt'|'milestone'|null
 */
function bbai_get_active_banner_slot(array $snapshot, string $page_context = ''): ?string
{
    return bbai_get_active_banner_slot_from_state(bbai_get_primary_banner_state($snapshot, $page_context));
}

/**
 * When non-null, only the primary command hero should show — hide milestone strips, retention nudges, etc.
 */
function bbai_banner_is_priority_slot_active(?string $active_slot): bool
{
    return null !== $active_slot && '' !== $active_slot;
}

/**
 * Early-activation “first success” slot: at least one optimized (saved) ALT visible in coverage, no library issues, modest credit usage.
 *
 * @param array<string, mixed>        $snapshot Raw snapshot.
 * @param array<string, mixed> $d Normalized banner data.
 */
function bbai_banner_snapshot_first_success_eligible(array $snapshot, array $d): bool
{
    $optimized = max(0, (int) ($snapshot['optimized_count'] ?? 0));
    if ($optimized <= 0) {
        return false;
    }
    if (empty($snapshot['has_ever_generated_alt'])) {
        return false;
    }
    if (!empty($snapshot['suppress_first_success_banner'])) {
        return false;
    }
    if ($d['total_issues'] > 0) {
        return false;
    }
    $used = max(0, (int) ($snapshot['credits_used'] ?? 0));
    $max  = (int) apply_filters('bbai_banner_first_success_max_credits_used', 15);
    if ($used > $max) {
        return false;
    }

    return (bool) apply_filters('bbai_banner_first_success_eligible', true, $snapshot, $d);
}

/**
 * When true, omit dashboard plan-card lines that repeat credit + attention messaging already in the primary banner.
 *
 * @param string $primary_state Result of bbai_get_primary_banner_state().
 */
function bbai_banner_suppress_dashboard_plan_attention_note(string $primary_state, int $missing_count, int $weak_count): bool
{
    if ($missing_count <= 0 && $weak_count <= 0) {
        return false;
    }

    return in_array(
        $primary_state,
        [
            BBAI_BANNER_STATE_OUT_OF_CREDITS,
            BBAI_BANNER_STATE_LOW_CREDITS,
            BBAI_BANNER_STATE_NEEDS_ATTENTION,
        ],
        true
    );
}

/**
 * Credit + attention subset only (no first_success / none). Prefer bbai_get_primary_banner_state().
 *
 * @param array<string, mixed> $data Raw or normalized counts (see bbai_banner_normalize_banner_data()).
 */
function bbai_banner_resolve_state(array $data): string
{
    $d = bbai_banner_normalize_banner_data($data);

    if (0 === $d['credits_remaining']) {
        return BBAI_BANNER_STATE_OUT_OF_CREDITS;
    }

    if ($d['credits_remaining'] > 0 && $d['credits_remaining'] <= $d['low_credit_threshold']) {
        return BBAI_BANNER_STATE_LOW_CREDITS;
    }

    if ($d['total_issues'] > 0 && $d['credits_remaining'] > $d['low_credit_threshold']) {
        return BBAI_BANNER_STATE_NEEDS_ATTENTION;
    }

    return BBAI_BANNER_STATE_HEALTHY;
}

/**
 * Headline + supporting copy for a resolved state (no CTAs).
 *
 * @param array<string, mixed> $data Raw or normalized counts.
 * @return array{
 *   state:string,
 *   needs_attention_variant:?string,
 *   title:string,
 *   body:string,
 *   status_line:string,
 *   show_progress:bool
 * }
 */
function bbai_banner_get_content(string $state, array $data): array
{
    $d            = bbai_banner_normalize_banner_data($data);
    $rem          = $d['credits_remaining'];
    $missing      = $d['missing_count'];
    $needs_review = $d['needs_review_count'];
    $total        = $d['total_images'];
    $issues       = $d['total_issues'];
    $is_anonymous_trial = !empty($d['is_anonymous_trial']);
    $free_plan_offer = max(0, (int) ($d['free_plan_offer'] ?? 50));

    switch ($state) {
        case BBAI_BANNER_STATE_OUT_OF_CREDITS:
            return [
                'state'                   => $state,
                'needs_attention_variant' => null,
                'title'                   => $is_anonymous_trial
                    ? __('Free trial complete', 'beepbeep-ai-alt-text-generator')
                    : bbai_copy_banner_out_of_credits_title(),
                'body'                    => $is_anonymous_trial
                    ? sprintf(
                        /* translators: %d: free signed-up plan offer. */
                        __('Create a free account to unlock %d generations per month and continue where you left off.', 'beepbeep-ai-alt-text-generator'),
                        $free_plan_offer
                    )
                    : bbai_banner_build_credit_supporting_line(0, $issues),
                'status_line'             => '',
                'show_progress'           => false,
            ];

        case BBAI_BANNER_STATE_LOW_CREDITS:
            return [
                'state'                   => $state,
                'needs_attention_variant' => null,
                'title'                   => $is_anonymous_trial
                    ? __('Your free trial is almost used', 'beepbeep-ai-alt-text-generator')
                    : bbai_copy_banner_low_credits_title(),
                'body'                    => $is_anonymous_trial
                    ? sprintf(
                        /* translators: 1: remaining trial generations, 2: free signed-up plan offer. */
                        _n(
                            '%1$s free trial generation left. Create a free account to unlock %2$d per month.',
                            '%1$s free trial generations left. Create a free account to unlock %2$d per month.',
                            $rem,
                            'beepbeep-ai-alt-text-generator'
                        ),
                        number_format_i18n($rem),
                        $free_plan_offer
                    )
                    : bbai_banner_build_credit_supporting_line($rem, $issues),
                'status_line'             => '',
                'show_progress'           => true,
            ];

        case BBAI_BANNER_STATE_NEEDS_ATTENTION:
            if ($missing > 0 && $needs_review > 0) {
                $variant = 'mixed';
            } elseif ($missing > 0) {
                $variant = 'missing';
            } else {
                $variant = 'weak';
            }

            $status = bbai_banner_build_issue_attention_message($issues);

            return [
                'state'                   => $state,
                'needs_attention_variant' => $variant,
                'title'                   => bbai_copy_banner_needs_attention_title(),
                'body'                    => bbai_copy_banner_needs_attention_body(),
                'status_line'             => $status,
                'show_progress'           => false,
            ];

        case BBAI_BANNER_STATE_FIRST_SUCCESS:
            return [
                'state'                   => $state,
                'needs_attention_variant' => null,
                'title'                   => __('Nice — your first ALT is live', 'beepbeep-ai-alt-text-generator'),
                'body'                    => __('Screen readers and search engines can better understand your images. Keep going to lift coverage across your library.', 'beepbeep-ai-alt-text-generator'),
                'status_line'             => '',
                'show_progress'           => false,
            ];

        case BBAI_BANNER_STATE_NONE:
            return [
                'state'                   => $state,
                'needs_attention_variant' => null,
                'title'                   => '',
                'body'                    => '',
                'status_line'             => '',
                'show_progress'           => false,
            ];

        case BBAI_BANNER_STATE_HEALTHY:
        default:
            if ($total > 0) {
                return [
                    'state'                   => BBAI_BANNER_STATE_HEALTHY,
                    'needs_attention_variant' => null,
                    'title'                   => bbai_copy_banner_healthy_title(),
                    'body'                    => bbai_copy_banner_healthy_body(),
                    'status_line'             => '',
                    'show_progress'           => false,
                ];
            }

            return [
                'state'                   => BBAI_BANNER_STATE_HEALTHY,
                'needs_attention_variant' => null,
                'title'                   => bbai_copy_banner_first_run_title(),
                'body'                    => bbai_copy_banner_first_run_body(),
                'status_line'             => bbai_copy_banner_first_run_status(),
                'show_progress'           => false,
            ];
    }
}

/**
 * @param int $needs_review_count Images flagged for review / weak ALT (same as snapshot weak_count).
 * @return array{
 *   state:string,
 *   needs_attention_variant:?string,
 *   title:string,
 *   body:string,
 *   status_line:string,
 *   show_progress:bool
 * }
 */
function bbai_banner_get_banner_state(int $remaining_credits, int $missing_count, int $needs_review_count, int $total_images): array
{
    $payload = [
        'credits_remaining'  => $remaining_credits,
        'missing_count'      => $missing_count,
        'needs_review_count' => $needs_review_count,
        'total_images'       => $total_images,
    ];
    $state = bbai_get_primary_banner_state($payload, '');

    return array_merge(bbai_banner_get_content($state, $payload), ['state' => $state]);
}

/**
 * @see bbai_banner_get_banner_state()
 *
 * @return array<string, mixed>
 */
function bbai_get_banner_state(int $remaining_credits, int $missing_count, int $_needs_review_count, int $total_images): array
{
    return bbai_banner_get_banner_state($remaining_credits, $missing_count, $_needs_review_count, $total_images);
}

/**
 * @param array<string, mixed> $s Snapshot (credits_remaining, missing_count, weak_count, total_images).
 * @param string               $page_context BBAI_BANNER_CTX_* for first_success gating.
 * @return array<string, mixed>
 */
function bbai_banner_get_banner_state_from_snapshot(array $s, string $page_context = ''): array
{
    $payload = [
        'credits_remaining'  => max(0, (int) ($s['credits_remaining'] ?? 0)),
        'missing_count'      => max(0, (int) ($s['missing_count'] ?? 0)),
        'needs_review_count' => max(0, (int) ($s['needs_review_count'] ?? $s['weak_count'] ?? 0)),
        'total_images'       => max(0, (int) ($s['total_images'] ?? 0)),
        'credits_limit'      => max(1, (int) ($s['credits_limit'] ?? 50)),
        'auth_state'         => (string) ($s['auth_state'] ?? ''),
        'quota_type'         => (string) ($s['quota_type'] ?? ''),
        'quota_state'        => (string) ($s['quota_state'] ?? ''),
        'signup_required'    => !empty($s['signup_required']),
        'upgrade_required'   => !empty($s['upgrade_required']),
        'free_plan_offer'    => max(0, (int) ($s['free_plan_offer'] ?? 50)),
        'low_credit_threshold' => max(0, (int) ($s['low_credit_threshold'] ?? 0)),
    ];
    $merged = array_merge($s, $payload);
    $state  = bbai_get_primary_banner_state($merged, $page_context);

    return array_merge(bbai_banner_get_content($state, $merged), ['state' => $state]);
}

/**
 * Resolve the single highest-priority banner system state (numeric credits; not snapshot flags).
 *
 * @param string               $context Surface key (BBAI_BANNER_CTX_*). Reserved.
 * @param array<string, mixed> $s       Snapshot.
 */
function bbai_banner_get_system_state(string $context, array $s): string
{
    return (string) bbai_banner_get_banner_state_from_snapshot($s, $context)['state'];
}

/**
 * Pick logical banner state (single priority chain).
 *
 * @param string               $page_context BBAI_BANNER_CTX_*.
 * @param array<string, mixed> $s            Snapshot.
 */
function bbai_banner_pick_state(string $page_context, array $s): string
{
    return bbai_get_primary_banner_state($s, $page_context);
}

/**
 * Merge attributes for library surface JS hooks (no locked-upgrade semantics on hero).
 *
 * @param array<string, scalar|null> $attrs
 * @return array<string, scalar|null>
 */
function bbai_banner_merge_library_attrs(array $attrs): array
{
    $attrs['data-bbai-library-surface-action'] = '1';

    return $attrs;
}

/**
 * Build scan primary action: dashboard historically uses data-bbai-action; others use data-action.
 *
 * @return array<string, mixed>
 */
function bbai_banner_action_scan(string $page_context): array
{
    if (BBAI_BANNER_CTX_DASHBOARD === $page_context) {
        return [
            'label'      => bbai_copy_cta_scan_media_library(),
            'href'       => '#',
            'attributes' => [
                'data-bbai-action' => 'scan-opportunity',
            ],
        ];
    }

    return [
        'label'      => bbai_copy_cta_scan_media_library(),
        'attributes' => [
            'data-action' => 'rescan-media-library',
        ],
    ];
}

/**
 * Free-tier hero upgrade: consistent modal trigger (standard primary appearance).
 *
 * @return array<string, mixed>
 */
function bbai_banner_action_upgrade_modal(string $label): array
{
    return [
        'label'      => $label,
        'attributes' => [
            'data-action' => 'show-upgrade-modal',
        ],
    ];
}

/**
 * Automation: Growth = settings deep link; Free = upgrade modal.
 *
 * @return array<string, mixed>
 */
function bbai_banner_action_automation(array $s, string $page_context): array
{
    $settings = (string) ($s['settings_url'] ?? admin_url('admin.php?page=bbai-settings')) . '#bbai-enable-on-upload';
    if (!empty($s['is_pro_plan'])) {
        $a = [
            'label' => bbai_copy_cta_enable_auto_optimization(),
            'href'  => $settings,
            'attributes' => [],
        ];
    } else {
        $a = bbai_banner_action_upgrade_modal(bbai_copy_cta_enable_auto_optimization());
    }

    if (BBAI_BANNER_CTX_LIBRARY === $page_context) {
        $a['attributes'] = bbai_banner_merge_library_attrs($a['attributes']);
    }

    return $a;
}

/**
 * Secondary: manual rescan (healthy states).
 *
 * @return array<string, mixed>
 */
function bbai_banner_action_scan_manual(string $page_context): array
{
    $attrs = ['data-action' => 'rescan-media-library'];
    if (BBAI_BANNER_CTX_LIBRARY === $page_context) {
        $attrs = bbai_banner_merge_library_attrs($attrs);
    }

    return [
        'label'      => bbai_copy_cta_rescan_media_library(),
        'attributes' => $attrs,
    ];
}

/**
 * Secondary CTA: deep-link to ALT Library (needs_attention state).
 *
 * @return array<string, mixed>
 */
function bbai_banner_action_open_library(string $page_context, string $library_url): array
{
    $attrs = [];
    if (BBAI_BANNER_CTX_LIBRARY === $page_context) {
        $attrs = bbai_banner_merge_library_attrs($attrs);
    }

    return [
        'label'      => bbai_copy_cta_open_alt_library(),
        'href'       => $library_url,
        'attributes' => $attrs,
    ];
}

/**
 * Credit-pressure hero CTAs (low / out): single ladder via Upgrade_Path_Resolver.
 *
 * @return array{0:?array<string,mixed>,1:?array<string,mixed>}
 */
function bbai_banner_upgrade_credit_actions(string $page_context, array $s): array
{
    if (!class_exists(\BeepBeepAI\AltTextGenerator\Services\Upgrade_Path_Resolver::class, false)) {
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-upgrade-path-resolver.php';
    }

    [$primary, $secondary] = \BeepBeepAI\AltTextGenerator\Services\Upgrade_Path_Resolver::credit_banner_actions($s, $page_context);

    if (BBAI_BANNER_CTX_LIBRARY === $page_context) {
        if (is_array($primary) && isset($primary['attributes']) && is_array($primary['attributes'])) {
            $primary['attributes'] = bbai_banner_merge_library_attrs($primary['attributes']);
        }
        if (is_array($secondary) && isset($secondary['attributes']) && is_array($secondary['attributes'])) {
            $secondary['attributes'] = bbai_banner_merge_library_attrs($secondary['attributes']);
        }
    }

    return [$primary, $secondary];
}

/**
 * Single source of truth for banner content, CTAs, progress visibility, and tone.
 * Copy comes only from bbai_banner_get_banner_state_from_snapshot() — no page overrides.
 *
 * @param string               $page_context BBAI_BANNER_CTX_*.
 * @param array<string, mixed> $s            Snapshot.
 * @param array<string, mixed> $opts         show_hero_loop request, etc.
 * @return array{
 *   logical_state:string,
 *   tone:string,
 *   banner_variant:string,
 *   semantic_state:string,
 *   title:string,
 *   body:string,
 *   status_line:string,
 *   note:string,
 *   show_progress:bool,
 *   progress_aria_valuetext:string,
 *   primary_action:?array,
 *   secondary_action:?array,
 *   show_hero_loop:bool
 * }
 */
function bbai_banner_get_config(string $page_context, array $s, array $opts = []): array
{
    if (!function_exists('bbai_copy_cta_upgrade_growth')) {
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/content/bbai-admin-copy.php';
    }

    $bs    = bbai_banner_get_banner_state_from_snapshot($s, $page_context);
    $state = (string) $bs['state'];
    $normalized = bbai_banner_normalize_banner_data($s);
    $is_anonymous_trial = !empty($normalized['is_anonymous_trial']);
    $free_plan_offer = max(0, (int) ($normalized['free_plan_offer'] ?? 50));

    $used    = max(0, (int) ($s['credits_used'] ?? 0));
    $limit   = max(1, (int) ($s['credits_limit'] ?? 50));
    $pct     = min(100, max(0, (int) ($s['usage_percent'] ?? 0)));

    $library_url  = (string) ($s['library_url'] ?? admin_url('admin.php?page=bbai-library'));
    $usage_url    = (string) ($s['usage_url'] ?? admin_url('admin.php?page=bbai-credit-usage'));
    $guide_url    = (string) ($s['guide_url'] ?? admin_url('admin.php?page=bbai-guide'));

    $progress_aria = $is_anonymous_trial
        ? sprintf(
            /* translators: 1: percent used, 2: used trial generations, 3: total trial generations. */
            __('%1$s%% of the free trial used — %2$s of %3$s generations.', 'beepbeep-ai-alt-text-generator'),
            (string) number_format_i18n($pct),
            (string) number_format_i18n($used),
            (string) number_format_i18n($limit)
        )
        : sprintf(
            /* translators: 1: percent used, 2: credits used, 3: credit limit */
            __('%1$s%% used — %2$s of %3$s credits this cycle.', 'beepbeep-ai-alt-text-generator'),
            (string) number_format_i18n($pct),
            (string) number_format_i18n($used),
            (string) number_format_i18n($limit)
        );

    $signup_action = [
        'label'      => function_exists('bbai_copy_cta_create_free_account') ? bbai_copy_cta_create_free_account() : __('Create free account', 'beepbeep-ai-alt-text-generator'),
        'attributes' => [
            'data-action'                 => 'show-auth-modal',
            'data-auth-tab'               => 'register',
            'data-bbai-analytics-upgrade' => 'trial_create_account_clicked',
        ],
    ];
    $needs_review_library_href = (string) ( $s['needs_review_library_url'] ?? '' );
    if ( '' === $needs_review_library_href ) {
        $needs_review_library_href = bbai_alt_library_needs_review_url();
    }
    $review_alt_in_library = [
        'label'        => bbai_copy_cta_review_usage(),
        'href'         => $needs_review_library_href,
        'attributes'   => [],
    ];

    $default_loop = BBAI_BANNER_CTX_DASHBOARD === $page_context
        && !empty($opts['show_hero_loop'])
        && BBAI_BANNER_STATE_HEALTHY === $state;

    $base = [
        'logical_state'           => $state,
        'tone'                    => 'healthy',
        'banner_variant'          => 'success',
        'semantic_state'          => 'healthy',
        'title'                   => '',
        'body'                    => '',
        'status_line'             => '',
        'note'                    => '',
        'show_progress'           => false,
        'progress_aria_valuetext' => $progress_aria,
        'primary_action'          => null,
        'secondary_action'        => null,
        'show_hero_loop'          => $default_loop,
    ];

    switch ($state) {
        case BBAI_BANNER_STATE_HEALTHY:
            $total_imgs = max(0, (int) ($s['total_images'] ?? 0));
            if (0 === $total_imgs) {
                return array_merge($base, [
                    'semantic_state'   => 'empty',
                    'tone'             => 'setup',
                    'banner_variant'   => 'success',
                    'title'            => (string) $bs['title'],
                    'body'             => (string) $bs['body'],
                    'status_line'      => (string) $bs['status_line'],
                    'show_progress'    => (bool) $bs['show_progress'],
                    'primary_action'   => bbai_banner_action_scan($page_context),
                    'secondary_action' => [
                        'label'      => bbai_copy_cta_learn_how(),
                        'href'       => $guide_url,
                        'attributes' => [],
                    ],
                    'show_hero_loop'   => $default_loop,
                ]);
            }

            if ($is_anonymous_trial) {
                return array_merge($base, [
                    'semantic_state'   => 'healthy',
                    'tone'             => 'healthy',
                    'banner_variant'   => 'success',
                    'title'            => (string) $bs['title'],
                    'body'             => (string) $bs['body'],
                    'status_line'      => (string) $bs['status_line'],
                    'show_progress'    => false,
                    'primary_action'   => BBAI_BANNER_CTX_LIBRARY === $page_context
                        ? bbai_banner_action_scan_manual($page_context)
                        : bbai_banner_action_open_library($page_context, $library_url),
                    'secondary_action' => $signup_action,
                ]);
            }

            return array_merge($base, [
                'semantic_state'   => 'healthy',
                'tone'             => 'healthy',
                'banner_variant'   => 'success',
                'title'            => (string) $bs['title'],
                'body'             => (string) $bs['body'],
                'status_line'      => (string) $bs['status_line'],
                'show_progress'    => false,
                'primary_action'   => bbai_banner_action_automation($s, $page_context),
                'secondary_action' => bbai_banner_action_scan_manual($page_context),
            ]);

        case BBAI_BANNER_STATE_NEEDS_ATTENTION:
            $variant = (string) ($bs['needs_attention_variant'] ?? 'missing');
            if ('weak' === $variant) {
                $weak_review_url = (string) ($s['needs_review_library_url'] ?? '');
                if ('' === $weak_review_url) {
                    $weak_review_url = bbai_alt_library_needs_review_url();
                }
                $primary = [
                    'label'      => bbai_copy_cta_review_needs_review_filter(),
                    'href'       => $weak_review_url,
                    'attributes' => [],
                ];
                $semantic = 'attention_weak';
            } else {
                $primary = [
                    'label'      => bbai_copy_cta_fix_missing_alt(),
                    'attributes' => [
                        'data-action'      => 'generate-missing',
                        'data-bbai-action' => 'generate_missing',
                    ],
                ];
                $semantic = 'attention_missing';
            }

            return array_merge($base, [
                'semantic_state'   => $semantic,
                'tone'             => 'attention',
                'banner_variant'   => 'warning',
                'title'            => (string) $bs['title'],
                'body'             => (string) $bs['body'],
                'status_line'      => (string) $bs['status_line'],
                'show_progress'    => false,
                'primary_action'   => $primary,
                'secondary_action' => bbai_banner_action_open_library($page_context, $library_url),
                'show_hero_loop'   => false,
            ]);

        case BBAI_BANNER_STATE_LOW_CREDITS:
            if ($is_anonymous_trial) {
                $primary_anon_low = BBAI_BANNER_CTX_LIBRARY === $page_context
                    ? bbai_banner_action_scan_manual($page_context)
                    : bbai_banner_action_open_library($page_context, $library_url);

                return array_merge($base, [
                    'semantic_state'          => 'low_credits',
                    'tone'                    => 'attention',
                    'banner_variant'          => 'warning',
                    'title'                   => (string) $bs['title'],
                    'body'                    => (string) $bs['body'],
                    'status_line'             => (string) $bs['status_line'],
                    'show_progress'           => true,
                    'progress_aria_valuetext' => $progress_aria,
                    'primary_action'          => $primary_anon_low,
                    'secondary_action'        => $signup_action,
                    'show_hero_loop'          => false,
                ]);
            }

            [$low_primary, $low_secondary] = bbai_banner_upgrade_credit_actions($page_context, $s);

            return array_merge($base, [
                'semantic_state'          => 'low_credits',
                'tone'                    => 'attention',
                'banner_variant'          => 'warning',
                'title'                   => (string) $bs['title'],
                'body'                    => (string) $bs['body'],
                'status_line'             => (string) $bs['status_line'],
                'show_progress'           => true,
                'progress_aria_valuetext' => $progress_aria,
                'primary_action'          => $low_primary,
                'secondary_action'        => $low_secondary,
                'show_hero_loop'          => false,
            ]);

        case BBAI_BANNER_STATE_OUT_OF_CREDITS:
            [$out_primary, $out_secondary] = bbai_banner_upgrade_credit_actions($page_context, $s);

            return array_merge($base, [
                'semantic_state'   => 'out_of_credits',
                'tone'             => 'paused',
                'banner_variant'   => 'warning',
                'title'            => (string) $bs['title'],
                'body'             => (string) $bs['body'],
                'status_line'      => (string) $bs['status_line'],
                'show_progress'    => false,
                'primary_action'   => $out_primary,
                'secondary_action' => $out_secondary,
                'show_hero_loop'   => false,
            ]);

        case BBAI_BANNER_STATE_FIRST_SUCCESS:
            return array_merge($base, [
                'semantic_state'   => 'first_success',
                'tone'             => 'healthy',
                'banner_variant'   => 'success',
                'title'            => (string) $bs['title'],
                'body'             => (string) $bs['body'],
                'status_line'      => (string) $bs['status_line'],
                'show_progress'    => false,
                'primary_action'   => [
                    'label'      => bbai_copy_cta_generate_missing_images(),
                    'attributes' => [
                        'data-action'           => 'generate-missing',
                        'data-bbai-starter-cap' => '10',
                    ],
                ],
                'secondary_action' => bbai_banner_action_open_library($page_context, $library_url),
                'show_hero_loop'   => false,
            ]);

        case BBAI_BANNER_STATE_NONE:
            return array_merge($base, [
                'suppress_banner_render' => true,
                'semantic_state'         => 'none',
                'tone'                   => 'healthy',
                'banner_variant'         => 'success',
                'title'                  => '',
                'body'                   => '',
                'status_line'            => '',
                'show_progress'          => false,
                'primary_action'         => null,
                'secondary_action'       => null,
                'show_hero_loop'         => false,
            ]);

        default:
            $open_library = [
                'label'      => bbai_copy_cta_open_alt_library(),
                'href'       => $library_url,
                'attributes' => [],
            ];

            return array_merge($base, [
                'title'            => __('BeepBeep AI', 'beepbeep-ai-alt-text-generator'),
                'body'             => '',
                'primary_action'   => bbai_banner_action_scan($page_context),
                'secondary_action' => $open_library,
            ]);
    }
}

/**
 * Map logical banner state to dashboard hero data-state slug (bbai-dashboard.js).
 *
 * @param array<string, mixed> $s Snapshot.
 */
function bbai_banner_dashboard_semantic_slug(string $state, array $s): string
{
    switch ($state) {
        case BBAI_BANNER_STATE_HEALTHY:
            if (0 === (int) ($s['total_images'] ?? 0)) {
                return 'first-run';
            }
            return !empty($s['is_pro_plan']) ? 'healthy-pro' : 'healthy-free';
        case BBAI_BANNER_STATE_LOW_CREDITS:
            return 'low-credits';
        case BBAI_BANNER_STATE_OUT_OF_CREDITS:
            return 'out-of-credits';
        case BBAI_BANNER_STATE_NEEDS_ATTENTION:
            return 'incomplete';
        case BBAI_BANNER_STATE_FIRST_SUCCESS:
            return 'first-success';
        case BBAI_BANNER_STATE_NONE:
            return 'none';
        default:
            return 'healthy-free';
    }
}

/**
 * Build full command_hero for status-banner from snapshot + page context.
 *
 * Options:
 * - show_hero_loop (bool) request — applied only for dashboard healthy/first-run via get_config
 * - aria_label (string)
 * - section_data_attrs (array)
 * - wrapper_extra_class, section_extra_class
 * - icon_wrapper_attrs, headline_attrs
 * - usage_anchor (usage page)
 *
 * Progress bar visibility is state-driven only (low_credits). Caller show_progress is ignored.
 *
 * @param array<string, mixed> $opts
 * @return array<string, mixed>
 */
function bbai_banner_build_command_hero(string $page_context, array $s, array $opts = []): array
{
    require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/command-hero.php';

    $state  = bbai_banner_pick_state($page_context, $s);
    $cfg    = bbai_banner_get_config($page_context, $s, $opts);
    $semantic = (string) $cfg['semantic_state'];

    if (BBAI_BANNER_CTX_DASHBOARD === $page_context) {
        $semantic = bbai_banner_dashboard_semantic_slug($state, $s);
    }

    $primary_a   = $cfg['primary_action'];
    $secondary_a = $cfg['secondary_action'];

    if (BBAI_BANNER_CTX_LIBRARY === $page_context) {
        if (null !== $primary_a) {
            $primary_a['attributes'] = bbai_banner_merge_library_attrs($primary_a['attributes'] ?? []);
        }
        if (null !== $secondary_a) {
            $secondary_a['attributes'] = bbai_banner_merge_library_attrs($secondary_a['attributes'] ?? []);
        }
    }

    $primary_html   = $primary_a ? bbai_command_hero_render_action($primary_a, 'primary') : '';
    $secondary_html = $secondary_a ? bbai_command_hero_render_action($secondary_a, 'secondary') : '';

    $show_progress = !empty($cfg['show_progress']);
    $show_loop     = !empty($cfg['show_hero_loop']);

    $settings_url = (string) ($s['settings_url'] ?? admin_url('admin.php?page=bbai-settings'));

    $section_data = isset($opts['section_data_attrs']) && is_array($opts['section_data_attrs']) ? $opts['section_data_attrs'] : [];
    $section_data['data-bbai-shared-banner']        = '1';
    $section_data['data-bbai-banner-logical-state'] = $state;

    $hero = [
        'semantic_state'           => $semantic,
        'tone'                     => (string) $cfg['tone'],
        'banner_variant'           => (string) $cfg['banner_variant'],
        'suppress_banner_render'   => !empty($cfg['suppress_banner_render']),
        'page_hero_variant'        => (string) ($opts['page_hero_variant'] ?? ''),
        'aria_label'               => (string) ($opts['aria_label'] ?? __('BeepBeep AI', 'beepbeep-ai-alt-text-generator')),
        'eyebrow'                  => (string) ($opts['eyebrow'] ?? ''),
        'title'                    => (string) $cfg['title'],
        'body'                     => (string) $cfg['body'],
        'status_line'              => (string) $cfg['status_line'],
        'note'                     => (string) $cfg['note'],
        'show_progress'            => $show_progress,
        'plan_label'               => (string) ($s['plan_label'] ?? ''),
        'remaining_line'           => (string) ($s['remaining_line'] ?? ''),
        'reset_timing'             => (string) ($s['reset_timing'] ?? ''),
        'progress_percent'         => (int) ($s['usage_percent'] ?? 0),
        'progress_aria_valuetext'  => (string) ($cfg['progress_aria_valuetext'] ?? ''),
        'show_hero_loop'           => $show_loop,
        'settings_url'             => $settings_url,
        'primary_html'             => $primary_html,
        'secondary_html'           => $secondary_html,
        'tertiary_html'            => '',
        'actions_append_html'      => (string) ($opts['actions_append_html'] ?? ''),
        'inline_stats'             => [],
        'section_extra_class'      => (string) ($opts['section_extra_class'] ?? ''),
        'wrapper_extra_class'      => trim('bbai-status-banner-host ' . (string) ($opts['wrapper_extra_class'] ?? '')),
        'section_data_attrs'       => $section_data,
        'icon_wrapper_attrs'       => isset($opts['icon_wrapper_attrs']) && is_array($opts['icon_wrapper_attrs']) ? $opts['icon_wrapper_attrs'] : [],
        'headline_attrs'           => isset($opts['headline_attrs']) && is_array($opts['headline_attrs']) ? $opts['headline_attrs'] : [],
        'subtext_attrs'            => isset($opts['subtext_attrs']) && is_array($opts['subtext_attrs']) ? $opts['subtext_attrs'] : [],
        'banner_logical_state'     => $state,
    ];

    if ('' === $hero['page_hero_variant']) {
        unset($hero['page_hero_variant']);
    }

    return $hero;
}

/**
 * Map legacy inline-banner / quota payloads into command_hero for the shared bbai-banner shell.
 *
 * @param array<string, mixed> $ib Keys: variant, meta, progress_aria, heading_tag, etc.
 *
 * @return array<string, mixed>
 */
function bbai_banner_inline_payload_to_command_hero(array $ib): array
{
    require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/command-hero.php';

    $ch = $ib;

    $variant = strtolower(trim((string) ($ib['variant'] ?? '')));
    if ('' !== $variant && in_array($variant, ['success', 'warning', 'info', 'neutral'], true)) {
        $ch['banner_variant'] = $variant;
    }
    unset($ch['variant']);

    if (isset($ib['meta'])) {
        if (!isset($ch['status_line']) || '' === trim((string) $ch['status_line'])) {
            $ch['status_line'] = (string) $ib['meta'];
        }
        unset($ch['meta']);
    }

    if (isset($ib['progress_aria'])) {
        if (!isset($ch['progress_aria_valuetext']) || '' === trim((string) $ch['progress_aria_valuetext'])) {
            $ch['progress_aria_valuetext'] = trim((string) $ib['progress_aria']);
        }
        unset($ch['progress_aria']);
    }

    $bv = strtolower(trim((string) ($ch['banner_variant'] ?? 'neutral')));
    if (!in_array($bv, ['success', 'warning', 'info', 'neutral'], true)) {
        $bv = 'neutral';
        $ch['banner_variant'] = $bv;
    }

    $tone = trim((string) ($ch['tone'] ?? ''));
    if ('' === $tone) {
        $ch['tone'] = 'warning' === $bv ? 'attention' : ('info' === $bv ? 'setup' : 'healthy');
    }

    if (empty($ch['icon_html']) || !is_string($ch['icon_html']) || '' === $ch['icon_html']) {
        $ch['icon_html'] = bbai_command_hero_icon_markup_for_banner_variant($bv, (string) $ch['tone']);
    }

    $ht = strtolower((string) ($ch['heading_tag'] ?? 'h1'));
    $ch['heading_tag'] = in_array($ht, ['h1', 'h2'], true) ? $ht : 'h1';

    return $ch;
}

/**
 * Default data-* hooks for ALT Library surface (JS + layout).
 *
 * @return array<string, array<string, string>>
 */
function bbai_banner_library_surface_attr_opts(): array
{
    return [
        'icon_wrapper_attrs' => [
            'data-bbai-library-surface-icon' => '1',
        ],
        'headline_attrs'     => [
            'data-bbai-library-surface-title' => '1',
        ],
        'subtext_attrs'      => [
            'data-bbai-library-surface-copy' => '1',
        ],
        'section_data_attrs' => [
            'data-bbai-library-surface' => '1',
        ],
    ];
}
