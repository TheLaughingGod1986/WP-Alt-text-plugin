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
        'plan_label'        => (string) ($d['planLabel'] ?? ''),
        'remaining_line'    => (string) ($d['remainingLine'] ?? ''),
        'reset_timing'      => (string) ($d['resetTiming'] ?? ''),
        'library_url'       => (string) ($d['libraryUrl'] ?? admin_url('admin.php?page=bbai-library')),
        'missing_library_url' => (string) ($d['missingLibraryUrl'] ?? admin_url('admin.php?page=bbai-library')),
        'needs_review_library_url' => (string) ($d['needsReviewLibraryUrl'] ?? admin_url('admin.php?page=bbai-library')),
        'usage_url'         => (string) ($d['usageUrl'] ?? admin_url('admin.php?page=bbai-credit-usage')),
        'settings_url'      => (string) ($d['settingsUrl'] ?? admin_url('admin.php?page=bbai-settings')),
        'guide_url'         => (string) ($d['guideUrl'] ?? admin_url('admin.php?page=bbai-guide')),
        'billing_portal_url' => '',
        'upgrade_url'       => '',
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
        'plan_label'          => '',
        'remaining_line'      => '',
        'reset_timing'        => '',
        'library_url'         => admin_url('admin.php?page=bbai-library'),
        'missing_library_url' => add_query_arg(['page' => 'bbai-library', 'status' => 'missing'], admin_url('admin.php')),
        'needs_review_library_url' => add_query_arg(['page' => 'bbai-library', 'status' => 'needs_review'], admin_url('admin.php')),
        'usage_url'           => admin_url('admin.php?page=bbai-credit-usage'),
        'settings_url'        => admin_url('admin.php?page=bbai-settings'),
        'guide_url'           => admin_url('admin.php?page=bbai-guide'),
        'billing_portal_url'  => '',
        'upgrade_url'         => '',
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
    if ($issue_count <= 0) {
        return __('All images are optimized.', 'beepbeep-ai-alt-text-generator');
    }

    if (1 === $issue_count) {
        return __('1 image still needs attention.', 'beepbeep-ai-alt-text-generator');
    }

    return sprintf(
        /* translators: %s: formatted issue count (locale-aware) */
        _n('%s image still needs attention.', '%s images still need attention.', $issue_count, 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($issue_count)
    );
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
    $credits_fmt         = number_format_i18n($credits_remaining);

    $credits_segment = sprintf(
        /* translators: %s: credits remaining (locale-formatted number) */
        _n(
            '%s optimization left this cycle',
            '%s optimizations left this cycle',
            $credits_remaining,
            'beepbeep-ai-alt-text-generator'
        ),
        $credits_fmt
    );

    if ($issue_count <= 0) {
        return $credits_segment;
    }

    if (1 === $issue_count) {
        $issues_segment = __('1 image needs attention', 'beepbeep-ai-alt-text-generator');
    } else {
        $issues_segment = sprintf(
            /* translators: %s: issue count (locale-formatted, 2+) */
            __('%s images need attention', 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($issue_count)
        );
    }

    return $credits_segment . ' • ' . $issues_segment;
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
 *   total_issues:int
 * }
 */
function bbai_banner_normalize_banner_data(array $input): array
{
    $missing       = max(0, (int) ($input['missing_count'] ?? 0));
    $needs_review  = max(0, (int) ($input['needs_review_count'] ?? $input['weak_count'] ?? 0));
    $total         = max(0, (int) ($input['total_images'] ?? 0));
    $rem           = max(0, (int) ($input['credits_remaining'] ?? 0));
    $limit         = max(1, (int) ($input['credits_limit'] ?? $input['credit_limit'] ?? 50));
    $thresh        = max(0, (int) apply_filters('bbai_banner_low_credits_threshold', BBAI_BANNER_LOW_CREDITS_THRESHOLD));
    $total_issues  = $missing + $needs_review;

    return [
        'missing_count'        => $missing,
        'needs_review_count'   => $needs_review,
        'total_images'         => $total,
        'credits_remaining'    => $rem,
        'credits_limit'        => $limit,
        'low_credit_threshold' => $thresh,
        'total_issues'         => $total_issues,
    ];
}

/**
 * Single highest-priority banner logical state (same on every admin surface).
 * Priority: out_of_credits → low_credits → needs_attention → healthy.
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

    switch ($state) {
        case BBAI_BANNER_STATE_OUT_OF_CREDITS:
            return [
                'state'                   => $state,
                'needs_attention_variant' => null,
                'title'                   => __('You’re out of credits', 'beepbeep-ai-alt-text-generator'),
                'body'                    => bbai_banner_build_credit_supporting_line(0, $issues),
                'status_line'             => '',
                'show_progress'           => false,
            ];

        case BBAI_BANNER_STATE_LOW_CREDITS:
            return [
                'state'                   => $state,
                'needs_attention_variant' => null,
                'title'                   => __('You’re running low on credits', 'beepbeep-ai-alt-text-generator'),
                'body'                    => bbai_banner_build_credit_supporting_line($rem, $issues),
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
                'title'                   => __('Your library needs attention', 'beepbeep-ai-alt-text-generator'),
                'body'                    => __('Some images are missing ALT text or need improvement.', 'beepbeep-ai-alt-text-generator'),
                'status_line'             => $status,
                'show_progress'           => false,
            ];

        case BBAI_BANNER_STATE_HEALTHY:
        default:
            if ($total > 0) {
                return [
                    'state'                   => BBAI_BANNER_STATE_HEALTHY,
                    'needs_attention_variant' => null,
                    'title'                   => __('Your library is in great shape', 'beepbeep-ai-alt-text-generator'),
                    'body'                    => __('All images are optimized and up to date.', 'beepbeep-ai-alt-text-generator'),
                    'status_line'             => '',
                    'show_progress'           => false,
                ];
            }

            return [
                'state'                   => BBAI_BANNER_STATE_HEALTHY,
                'needs_attention_variant' => null,
                'title'                   => __('Get started with your media library', 'beepbeep-ai-alt-text-generator'),
                'body'                    => __('Scan your library to find missing ALT text and improve accessibility faster.', 'beepbeep-ai-alt-text-generator'),
                'status_line'             => __('Start by scanning your media library.', 'beepbeep-ai-alt-text-generator'),
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
    $state = bbai_banner_resolve_state($payload);

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
 * @return array<string, mixed>
 */
function bbai_banner_get_banner_state_from_snapshot(array $s): array
{
    $payload = [
        'credits_remaining'  => max(0, (int) ($s['credits_remaining'] ?? 0)),
        'missing_count'      => max(0, (int) ($s['missing_count'] ?? 0)),
        'needs_review_count' => max(0, (int) ($s['needs_review_count'] ?? $s['weak_count'] ?? 0)),
        'total_images'       => max(0, (int) ($s['total_images'] ?? 0)),
        'credits_limit'      => max(1, (int) ($s['credits_limit'] ?? 50)),
    ];
    $state = bbai_banner_resolve_state($payload);

    return array_merge(bbai_banner_get_content($state, $payload), ['state' => $state]);
}

/**
 * Resolve the single highest-priority banner system state (numeric credits; not snapshot flags).
 *
 * @param string               $context Surface key (BBAI_BANNER_CTX_*). Reserved.
 * @param array<string, mixed> $s       Snapshot.
 */
function bbai_banner_get_system_state(string $context, array $s): string
{
    unset($context);

    return (string) bbai_banner_get_banner_state_from_snapshot($s)['state'];
}

/**
 * Pick logical banner state (alias of strict system resolver).
 *
 * @param string               $page_context BBAI_BANNER_CTX_*.
 * @param array<string, mixed> $s            Snapshot.
 */
function bbai_banner_pick_state(string $page_context, array $s): string
{
    return bbai_banner_get_system_state($page_context, $s);
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
            'label'      => __('Scan Media Library', 'beepbeep-ai-alt-text-generator'),
            'href'       => '#',
            'attributes' => [
                'data-bbai-action' => 'scan-opportunity',
            ],
        ];
    }

    return [
        'label'      => __('Scan Media Library', 'beepbeep-ai-alt-text-generator'),
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
            'label' => __('Enable Auto-Optimisation', 'beepbeep-ai-alt-text-generator'),
            'href'  => $settings,
            'attributes' => [],
        ];
    } else {
        $a = bbai_banner_action_upgrade_modal(__('Enable Auto-Optimisation', 'beepbeep-ai-alt-text-generator'));
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
        'label'      => __('Scan manually', 'beepbeep-ai-alt-text-generator'),
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
        'label'      => __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
        'href'       => $library_url,
        'attributes' => $attrs,
    ];
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
    $bs    = bbai_banner_get_banner_state_from_snapshot($s);
    $state = (string) $bs['state'];

    $used    = max(0, (int) ($s['credits_used'] ?? 0));
    $limit   = max(1, (int) ($s['credits_limit'] ?? 50));
    $pct     = min(100, max(0, (int) ($s['usage_percent'] ?? 0)));

    $library_url  = (string) ($s['library_url'] ?? admin_url('admin.php?page=bbai-library'));
    $usage_url    = (string) ($s['usage_url'] ?? admin_url('admin.php?page=bbai-credit-usage'));
    $guide_url    = (string) ($s['guide_url'] ?? admin_url('admin.php?page=bbai-guide'));

    $progress_aria = sprintf(
        /* translators: 1: percent used, 2: credits used, 3: credit limit */
        __('%1$s%% used — %2$s of %3$s credits this cycle.', 'beepbeep-ai-alt-text-generator'),
        (string) number_format_i18n($pct),
        (string) number_format_i18n($used),
        (string) number_format_i18n($limit)
    );

    $upgrade_growth = __('Upgrade to Growth', 'beepbeep-ai-alt-text-generator');
    $review_usage   = [
        'label'      => __('Review usage', 'beepbeep-ai-alt-text-generator'),
        'href'       => $usage_url,
        'attributes' => [],
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
                        'label'      => __('Learn how it works', 'beepbeep-ai-alt-text-generator'),
                        'href'       => $guide_url,
                        'attributes' => [],
                    ],
                    'show_hero_loop'   => $default_loop,
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
                $primary = [
                    'label'      => __('Improve ALT text', 'beepbeep-ai-alt-text-generator'),
                    'attributes' => [
                        'data-action'                  => 'regenerate-all',
                        'data-bbai-regenerate-scope'   => 'needs-review',
                        'data-bbai-generation-source'  => 'regenerate-weak',
                    ],
                ];
                $semantic = 'attention_weak';
            } else {
                $primary = [
                    'label'      => __('Fix missing ALT text', 'beepbeep-ai-alt-text-generator'),
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
            return array_merge($base, [
                'semantic_state'          => 'low_credits',
                'tone'                    => 'attention',
                'banner_variant'          => 'warning',
                'title'                   => (string) $bs['title'],
                'body'                    => (string) $bs['body'],
                'status_line'             => (string) $bs['status_line'],
                'show_progress'           => true,
                'progress_aria_valuetext' => $progress_aria,
                'primary_action'          => bbai_banner_action_upgrade_modal($upgrade_growth),
                'secondary_action'        => $review_usage,
                'show_hero_loop'          => false,
            ]);

        case BBAI_BANNER_STATE_OUT_OF_CREDITS:
            return array_merge($base, [
                'semantic_state'   => 'out_of_credits',
                'tone'             => 'paused',
                'banner_variant'   => 'warning',
                'title'            => (string) $bs['title'],
                'body'             => (string) $bs['body'],
                'status_line'      => (string) $bs['status_line'],
                'show_progress'    => false,
                'primary_action'   => bbai_banner_action_upgrade_modal($upgrade_growth),
                'secondary_action' => $review_usage,
                'show_hero_loop'   => false,
            ]);

        default:
            $open_library = [
                'label'      => __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
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
    ];

    if ('' === $hero['page_hero_variant']) {
        unset($hero['page_hero_variant']);
    }

    return $hero;
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
