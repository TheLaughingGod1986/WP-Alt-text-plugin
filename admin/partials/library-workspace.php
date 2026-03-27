<?php
/**
 * ALT Library premium workspace layout.
 *
 * Expects the data/query variables prepared in library-tab.php.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-upgrade-state.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/command-hero.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/banner-system.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/page-hero.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/ui-components.php';

// $bbai_default_review_filter, $bbai_review_filter_from_url: set in library-tab.php before this partial is included.

$bbai_build_action = static function (array $config) use ($bbai_limit_reached_state) {
    $config = wp_parse_args(
        $config,
        [
            'label'       => '',
            'action'      => '',
            'attrs'       => '',
            'locked'      => false,
            'lock_reason' => 'generate_missing',
            'lock_source' => 'library-workspace',
        ]
    );

    $attrs = trim((string) $config['attrs']);

    if (!empty($config['action'])) {
        $attrs .= ' data-action="' . esc_attr($config['action']) . '"';
    }

    $config['is_locked'] = false;
    if (!empty($config['locked']) && $bbai_limit_reached_state) {
        $attrs .= ' data-bbai-action="open-upgrade"';
        $attrs .= ' data-bbai-locked-cta="1"';
        $attrs .= ' data-bbai-lock-reason="' . esc_attr((string) $config['lock_reason']) . '"';
        $attrs .= ' data-bbai-locked-source="' . esc_attr((string) $config['lock_source']) . '"';
        $attrs .= ' aria-disabled="true"';
        $config['is_locked'] = true;
    }

    $config['attrs'] = trim($attrs);

    return $config;
};

$bbai_scan_action = $bbai_build_action(
    [
        'label' => bbai_copy_cta_scan_media_library(),
        'attrs' => 'data-bbai-action="scan-opportunity"',
    ]
);

$bbai_review_optimized_action = $bbai_build_action(
    [
        'label' => bbai_copy_cta_review_optimized_images(),
        'attrs' => 'data-bbai-filter-target="optimized"',
    ]
);

$bbai_scan_new_uploads_action = $bbai_build_action(
    [
        'label'  => bbai_copy_cta_scan_new_uploads(),
        'action' => 'rescan-media-library',
    ]
);

$bbai_scan_manual_action = $bbai_build_action(
    [
        'label'  => bbai_copy_cta_rescan_media_library(),
        'action' => 'rescan-media-library',
    ]
);

$bbai_settings_automation_url = admin_url('admin.php?page=bbai-settings#bbai-enable-on-upload');
$bbai_review_usage_url        = admin_url('admin.php?page=bbai-credit-usage');

$bbai_surface_automation_action = $bbai_is_pro
    ? $bbai_build_action(
        [
            'label' => bbai_copy_cta_automation_settings(),
            'attrs' => 'href="' . esc_url($bbai_settings_automation_url) . '"',
        ]
    )
    : $bbai_build_action(
        [
            'label' => bbai_copy_cta_enable_auto_optimization(),
            'attrs' => 'data-bbai-action="open-upgrade" data-bbai-locked-cta="1" data-bbai-lock-reason="automation" data-bbai-locked-source="library-summary-automation"',
    ]
);

if ($bbai_cov_missing > 0) {
    $bbai_header_primary_action = $bbai_build_action(
        [
            'label'       => bbai_copy_cta_generate_alt(),
            'action'      => 'generate-missing',
            'locked'      => true,
            'lock_reason' => 'generate_missing',
            'lock_source' => 'library-header-primary',
        ]
    );
    $bbai_summary_primary_action = $bbai_build_action(
        [
            'label'       => bbai_copy_cta_fix_missing_alt(),
            'action'      => 'generate-missing',
            'locked'      => true,
            'lock_reason' => 'generate_missing',
            'lock_source' => 'library-summary-primary',
        ]
    );
    $bbai_task_primary_action = $bbai_build_action(
        [
            'label'       => bbai_copy_cta_generate_alt(),
            'action'      => 'generate-missing',
            'locked'      => true,
            'lock_reason' => 'generate_missing',
            'lock_source' => 'library-task-banner-primary',
        ]
    );
    $bbai_queue_primary_action = $bbai_build_action(
        [
            'label'       => bbai_copy_cta_generate_alt(),
            'action'      => 'generate-missing',
            'locked'      => true,
            'lock_reason' => 'generate_missing',
            'lock_source' => 'library-sidebar-queue',
        ]
    );

    $bbai_task_eyebrow = bbai_copy_section_next_task();
    $bbai_task_title   = bbai_copy_library_missing_alt_headline($bbai_cov_missing);
    $bbai_task_copy    = bbai_copy_helper_generate_then_review();

    $bbai_queue_title = bbai_copy_library_missing_alt_headline($bbai_cov_missing);
    $bbai_queue_copy  = bbai_copy_helper_queue_missing_then_review();
} elseif ($bbai_cov_needs_review > 0) {
    $bbai_header_primary_action = $bbai_build_action(
        [
            'label'       => bbai_copy_cta_improve_alt(),
            'action'      => 'regenerate-all',
            'attrs'       => 'data-bbai-regenerate-scope="needs-review" data-bbai-generation-source="regenerate-weak"',
            'locked'      => true,
            'lock_reason' => 'reoptimize_all',
            'lock_source' => 'library-header-primary',
        ]
    );
    $bbai_summary_primary_action = $bbai_build_action(
        [
            'label'       => bbai_copy_cta_improve_alt(),
            'action'      => 'regenerate-all',
            'attrs'       => 'data-bbai-regenerate-scope="needs-review" data-bbai-generation-source="regenerate-weak"',
            'locked'      => true,
            'lock_reason' => 'reoptimize_all',
            'lock_source' => 'library-summary-primary',
        ]
    );
    $bbai_task_primary_action = $bbai_build_action(
        [
            'label'       => bbai_copy_cta_improve_alt(),
            'action'      => 'regenerate-all',
            'attrs'       => 'data-bbai-regenerate-scope="needs-review" data-bbai-generation-source="regenerate-weak"',
            'locked'      => true,
            'lock_reason' => 'reoptimize_all',
            'lock_source' => 'library-task-banner-primary',
        ]
    );
    $bbai_queue_primary_action = $bbai_build_action(
        [
            'label'       => bbai_copy_cta_improve_alt(),
            'action'      => 'regenerate-all',
            'attrs'       => 'data-bbai-regenerate-scope="needs-review" data-bbai-generation-source="regenerate-weak"',
            'locked'      => true,
            'lock_reason' => 'reoptimize_all',
            'lock_source' => 'library-sidebar-queue',
        ]
    );

    $bbai_task_eyebrow = bbai_copy_section_descriptions_can_improve();
    $bbai_task_title   = bbai_copy_library_needs_review_headline($bbai_cov_needs_review);
    $bbai_task_copy    = bbai_copy_helper_needs_review_actions();

    $bbai_queue_title = bbai_copy_queue_needs_review_title($bbai_cov_needs_review);
    $bbai_queue_copy  = bbai_copy_helper_finish_review_queue();
} else {
    $bbai_header_primary_action = $bbai_surface_automation_action;
    $bbai_summary_primary_action = $bbai_build_action(
        [
            'label' => bbai_copy_cta_review_optimized_images(),
            'attrs' => 'data-bbai-filter-target="optimized"',
        ]
    );
    $bbai_task_primary_action = $bbai_surface_automation_action;
    $bbai_queue_primary_action = $bbai_build_action(
        [
            'label' => bbai_copy_cta_review_optimized_images(),
            'attrs' => 'data-bbai-filter-target="optimized"',
        ]
    );

    $bbai_task_eyebrow = bbai_copy_section_all_images_current();
    $bbai_task_title   = bbai_copy_library_fully_optimized_title();
    $bbai_task_copy    = bbai_copy_helper_healthy_keep_coverage();

    $bbai_queue_title = bbai_copy_queue_clear_title();
    $bbai_queue_copy  = bbai_copy_helper_healthy_keep_coverage();
}

$bbai_total_automated_images = max(
    (int) ($bbai_usage_stats['used'] ?? 0),
    $bbai_cov_optimized,
    $bbai_cov_auto
);
$bbai_saved_minutes = max(1, (int) ceil($bbai_total_automated_images * 0.5));
$bbai_saved_time_label = sprintf(
    _n('~%d minute', '~%d minutes', $bbai_saved_minutes, 'beepbeep-ai-alt-text-generator'),
    $bbai_saved_minutes
);

$bbai_usage_used = max(0, (int) ($bbai_usage_stats['used'] ?? 0));
$bbai_usage_limit = max(1, (int) ($bbai_usage_stats['limit'] ?? 50));
$bbai_usage_remaining = max(0, (int) ($bbai_usage_stats['remaining'] ?? 0));
$bbai_usage_pct = $bbai_usage_limit > 0 ? min(100, (int) round(($bbai_usage_used / $bbai_usage_limit) * 100)) : 0;
$bbai_usage_line = sprintf(
    __('%1$s of %2$s AI generations used', 'beepbeep-ai-alt-text-generator'),
    number_format_i18n($bbai_usage_used),
    number_format_i18n($bbai_usage_limit)
);
$bbai_usage_copy = $bbai_usage_remaining > 0
    ? sprintf(
        _n('%s credit remaining this cycle', '%s credits remaining this cycle', $bbai_usage_remaining, 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($bbai_usage_remaining)
    )
    : __('No credits remaining this cycle', 'beepbeep-ai-alt-text-generator');
$bbai_optimized_count = $bbai_cov_optimized;
$bbai_missing_count = $bbai_cov_missing;
$bbai_weak_count = $bbai_cov_needs_review;
$bbai_total_count = $bbai_cov_total;
$bbai_library_all_count = $bbai_optimized_count + $bbai_weak_count + $bbai_missing_count;
$bbai_credits_used = $bbai_usage_used;
$bbai_credits_limit = $bbai_usage_limit;
$bbai_credits_remaining = $bbai_usage_remaining;
$bbai_is_healthy = 0 === $bbai_missing_count && 0 === $bbai_weak_count && $bbai_library_all_count > 0;
// Default workspace filter: all (single active tab). Deep links (?status=) still apply via $bbai_review_filter_from_url.
if (!$bbai_review_filter_from_url) {
    $bbai_default_review_filter = 'all';
}
if (!in_array($bbai_default_review_filter, ['all', 'missing', 'optimized', 'weak'], true)) {
    $bbai_default_review_filter = 'all';
}
$bbai_is_low_credits = $bbai_credits_remaining > 0 && $bbai_credits_remaining <= BBAI_BANNER_LOW_CREDITS_THRESHOLD;
$bbai_is_out_of_credits = 0 === $bbai_credits_remaining;
$bbai_has_search_query = false;

$bbai_command_hero = bbai_page_hero_library_command_hero(
    [
        'cov_total'                => $bbai_library_all_count,
        'cov_missing'              => $bbai_cov_missing,
        'cov_needs_review'         => $bbai_cov_needs_review,
        'is_pro'                   => $bbai_is_pro,
        'settings_automation_url'  => $bbai_settings_automation_url,
        'is_low_credits'           => $bbai_is_low_credits,
        'is_out_of_credits'        => $bbai_is_out_of_credits,
        'credits_remaining'        => $bbai_credits_remaining,
        'credits_used'             => $bbai_credits_used,
        'credits_limit'            => $bbai_credits_limit,
        'usage_percent'            => $bbai_usage_pct,
        'library_url'              => add_query_arg(['page' => 'bbai-library'], admin_url('admin.php')),
        'missing_library_url'      => add_query_arg(['page' => 'bbai-library', 'status' => 'missing'], admin_url('admin.php')),
        'needs_review_library_url' => bbai_alt_library_needs_review_url(),
        'usage_url'                => $bbai_review_usage_url,
        'plan_label'               => isset($bbai_usage_stats['plan_label']) ? (string) $bbai_usage_stats['plan_label'] : '',
        'remaining_line'           => $bbai_usage_copy,
        'reset_timing'             => isset($bbai_usage_stats['days_until_reset'])
            ? sprintf(
                /* translators: %d: days until credit reset */
                _n('%d day until reset', '%d days until reset', (int) $bbai_usage_stats['days_until_reset'], 'beepbeep-ai-alt-text-generator'),
                (int) $bbai_usage_stats['days_until_reset']
            )
            : '',
    ]
);

$bbai_workspace_banner_slot = bbai_get_active_banner_slot_from_state((string) ($bbai_command_hero['banner_logical_state'] ?? ''));
if (!isset($bbai_command_hero['section_data_attrs']) || !is_array($bbai_command_hero['section_data_attrs'])) {
    $bbai_command_hero['section_data_attrs'] = [];
}
$bbai_command_hero['wrapper_extra_class'] = trim(((string) ($bbai_command_hero['wrapper_extra_class'] ?? '')) . ' bbai-library-top-hero-host');
$bbai_command_hero['section_extra_class'] = trim(((string) ($bbai_command_hero['section_extra_class'] ?? '')) . ' bbai-library-top-hero');
$bbai_command_hero['section_data_attrs']['data-bbai-active-banner-slot'] = (string) ($bbai_workspace_banner_slot ?? '');

$bbai_surface_semantic = (string) ($bbai_command_hero['semantic_state'] ?? 'healthy');
$bbai_surface_state    = 'healthy';
if ('empty' === $bbai_surface_semantic) {
    $bbai_surface_state = 'empty';
} elseif ('attention_missing' === $bbai_surface_semantic) {
    $bbai_surface_state = 'missing';
} elseif ('attention_weak' === $bbai_surface_semantic) {
    $bbai_surface_state = 'weak';
} elseif ('low_credits' === $bbai_surface_semantic) {
    $bbai_surface_state = 'low_credits';
} elseif ('out_of_credits' === $bbai_surface_semantic) {
    $bbai_surface_state = 'out_of_credits';
}

$bbai_state = [
    'total_images'        => $bbai_library_all_count,
    'optimized_count'     => $bbai_cov_optimized,
    'missing_alts'        => $bbai_cov_missing,
    'needs_review_count'  => $bbai_cov_needs_review,
    'has_scan_results'    => $bbai_library_all_count > 0,
    'last_scan_timestamp' => time(),
];

// Row states: precomputed in library-tab.php for the current page when the workspace dataset pipeline runs.
if (!isset($bbai_library_row_states) || !is_array($bbai_library_row_states)) {
    $bbai_library_row_states = [];
    $bbai_library_images_for_table = isset($bbai_all_images) && is_array($bbai_all_images) ? $bbai_all_images : [];
    if (!empty($bbai_library_images_for_table)) {
        foreach ($bbai_library_images_for_table as $bbai_row_idx => $bbai_row_image) {
            $bbai_library_row_states[ $bbai_row_idx ] = $this->get_library_workspace_row_state($bbai_row_image);
        }
    }
}

// Filter chip counts: from full normalized dataset (library-tab.php); fallback to coverage fields.
if (!isset($bbai_table_filter_counts) || !is_array($bbai_table_filter_counts)) {
    $bbai_table_filter_counts = [
        'all'       => max(0, (int) $bbai_cov_total),
        'missing'   => max(0, (int) $bbai_cov_missing),
        'weak'      => max(0, (int) $bbai_cov_needs_review),
        'optimized' => max(0, (int) $bbai_cov_optimized),
    ];
}
$bbai_has_filters = $bbai_table_filter_counts['all'] > 0;

$bbai_results_total = isset($bbai_library_filtered_total) ? (int) $bbai_library_filtered_total : (int) $bbai_total_count;
$bbai_row_count       = isset($bbai_all_images) && is_array($bbai_all_images) ? count($bbai_all_images) : 0;
$bbai_show_start      = $bbai_row_count > 0 ? $bbai_offset + 1 : 0;
$bbai_show_end        = $bbai_row_count > 0 ? min($bbai_offset + $bbai_row_count, $bbai_results_total) : 0;

// Hint under results heading: whether the current page has any missing/weak rows (not global counts).
$bbai_page_missing_or_weak_count = 0;
foreach ($bbai_library_row_states as $bbai_rs_hint) {
    if (!empty($bbai_rs_hint['status']) && in_array($bbai_rs_hint['status'], ['missing', 'weak'], true)) {
        ++$bbai_page_missing_or_weak_count;
    }
}

// Status filters: All → Missing → Needs review → Optimized (data-filter weak = needs review queue).
$bbai_library_workspace_filter_items = [
    [
        'key'               => 'all',
        'label'             => __('All', 'beepbeep-ai-alt-text-generator'),
        'count'             => $bbai_table_filter_counts['all'],
        'active'            => $bbai_default_review_filter === 'all',
        'filter_label_attr' => __('All', 'beepbeep-ai-alt-text-generator'),
        'problem'           => false,
    ],
    [
        'key'               => 'missing',
        'label'             => __('Missing', 'beepbeep-ai-alt-text-generator'),
        'count'             => $bbai_table_filter_counts['missing'],
        'active'            => $bbai_default_review_filter === 'missing',
        'filter_label_attr' => __('Missing', 'beepbeep-ai-alt-text-generator'),
        'problem'           => $bbai_table_filter_counts['missing'] > 0,
    ],
    [
        'key'               => 'weak',
        'label'             => __('Needs review', 'beepbeep-ai-alt-text-generator'),
        'count'             => $bbai_table_filter_counts['weak'],
        'active'            => $bbai_default_review_filter === 'weak',
        'filter_label_attr' => __('Needs review', 'beepbeep-ai-alt-text-generator'),
        'problem'           => $bbai_table_filter_counts['weak'] > 0,
    ],
    [
        'key'               => 'optimized',
        'label'             => __('Optimized', 'beepbeep-ai-alt-text-generator'),
        'count'             => $bbai_table_filter_counts['optimized'],
        'active'            => $bbai_default_review_filter === 'optimized',
        'filter_label_attr' => __('Optimized', 'beepbeep-ai-alt-text-generator'),
        'problem'           => false,
    ],
];
?>

<div
    class="bbai-library-container bbai-library-workspace bbai-container"
    data-bbai-library-workspace-root="1"
    data-bbai-current-page="alt-library"
    data-bbai-library-url="<?php echo esc_url(add_query_arg(['page' => 'bbai-library'], admin_url('admin.php'))); ?>"
    data-bbai-missing-library-url="<?php echo esc_url(add_query_arg(['page' => 'bbai-library', 'status' => 'missing'], admin_url('admin.php'))); ?>"
    data-bbai-usage-url="<?php echo esc_url($bbai_review_usage_url); ?>"
    data-bbai-guide-url="<?php echo esc_url(admin_url('admin.php?page=bbai-guide')); ?>"
    data-bbai-automation-settings-url="<?php echo esc_url($bbai_settings_automation_url); ?>"
    data-bbai-empty-filter="<?php echo esc_attr__('No images match this filter.', 'beepbeep-ai-alt-text-generator'); ?>"
    data-bbai-empty-filter-hint="<?php echo esc_attr__('Try another filter or show all images.', 'beepbeep-ai-alt-text-generator'); ?>"
    data-bbai-is-healthy="<?php echo $bbai_is_healthy ? 'true' : 'false'; ?>"
    data-bbai-is-low-credits="<?php echo $bbai_is_low_credits ? 'true' : 'false'; ?>"
    data-bbai-is-out-of-credits="<?php echo $bbai_is_out_of_credits ? 'true' : 'false'; ?>"
    data-bbai-is-pro-plan="<?php echo $bbai_is_pro ? 'true' : 'false'; ?>"
    data-bbai-has-filters="<?php echo $bbai_has_filters ? 'true' : 'false'; ?>"
    data-bbai-has-search-query="<?php echo $bbai_has_search_query ? 'true' : 'false'; ?>"
    data-bbai-total-count="<?php echo esc_attr((string) $bbai_cov_total); ?>"
    data-bbai-missing-count="<?php echo esc_attr((string) $bbai_cov_missing); ?>"
    data-bbai-weak-count="<?php echo esc_attr((string) $bbai_cov_needs_review); ?>"
    data-bbai-optimized-count="<?php echo esc_attr((string) $bbai_cov_optimized); ?>"
    data-bbai-library-server-filter="1"
>
    <?php
    require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/retention-lifecycle.php';
    $bbai_rn_uid            = get_current_user_id();
    $bbai_retention_nudge   = null;
    $bbai_lib_missing_url   = add_query_arg(['page' => 'bbai-library', 'status' => 'missing'], admin_url('admin.php'));
    $bbai_lib_review_url    = bbai_alt_library_needs_review_url();
    if ($bbai_rn_uid) {
        bbai_retention_schedule_snapshot_update($bbai_rn_uid, (int) $bbai_cov_total);
        $bbai_retention_nudge = bbai_retention_build_library_nudge(
            [
                'user_id'                  => $bbai_rn_uid,
                'missing'                  => (int) $bbai_cov_missing,
                'weak'                     => (int) $bbai_cov_needs_review,
                'total'                    => (int) $bbai_cov_total,
                'coverage_pct'             => (int) $bbai_cov_opt_pct,
                'missing_library_url'      => $bbai_lib_missing_url,
                'needs_review_library_url' => $bbai_lib_review_url,
                'is_out_of_credits'        => $bbai_is_out_of_credits,
            ]
        );
    }
    $bbai_library_retention_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/library-retention-nudge.php';
    if (
        $bbai_retention_nudge
        && !bbai_banner_is_priority_slot_active($bbai_workspace_banner_slot)
        && is_readable($bbai_library_retention_partial)
    ) {
        include $bbai_library_retention_partial;
    }
    ?>
    <style id="bbai-library-workspace-styles">
    .bbai-library-workspace {
        /* Map to unified + foundation tokens — keeps Library on the same colour rail as Dashboard/filters */
        --bbai-library-border: var(--bbai-border, #dfe8f6);
        --bbai-library-border-strong: var(--bbai-border-secondary, #cfd9ea);
        --bbai-library-surface: linear-gradient(135deg, var(--bbai-bg-secondary, #f7fbff) 0%, var(--bbai-info-bg, #eef4ff) 58%, var(--bbai-bg-primary, #ffffff) 100%);
        --bbai-library-card-shadow: var(--bbai-surface-card-shadow, 0 10px 24px rgba(15, 23, 42, 0.06));
        --bbai-library-soft-shadow: 0 16px 36px rgba(15, 23, 42, 0.05);
        --bbai-library-text: var(--bbai-text-primary, #111827);
        --bbai-library-muted: var(--bbai-text-secondary, #6b7280);
        --bbai-library-subtle: var(--bbai-status-neutral-indicator, #94a3b8);
        --bbai-library-track: var(--bbai-bg-tertiary, #e6edf8);
        --bbai-library-green: var(--bbai-status-optimized-accent, #16a34a);
        --bbai-library-green-bg: var(--bbai-status-optimized-bg, #effbf3);
        --bbai-library-amber: var(--bbai-status-needs-review-accent, #d97706);
        --bbai-library-amber-bg: var(--bbai-status-needs-review-bg, #fffbeb);
        --bbai-library-red: var(--bbai-status-missing-accent, #dc2626);
        --bbai-library-red-bg: var(--bbai-status-missing-bg, #fff1f2);
        /* Legacy name — maps to brand primary (green CTAs), not UI info blue */
        --bbai-library-blue: var(--bbai-primary, #10b981);
        --bbai-library-blue-bg: var(--bbai-info-bg, #eff6ff);
        /* Row rail + status wash — Phase 4 tokens (bbai-admin-foundation-tokens.css) */
        --bbai-row-rail-w: var(--bbai-status-row-rail-width);
        --bbai-row-rail-radius: var(--bbai-status-row-rail-radius);
        --bbai-row-rail-missing: var(--bbai-status-missing-rail);
        --bbai-row-rail-missing-strong: var(--bbai-status-missing-rail-strong);
        --bbai-row-rail-weak: var(--bbai-status-needs-review-rail);
        --bbai-row-rail-weak-strong: var(--bbai-status-needs-review-rail-strong);
        --bbai-row-rail-opt: var(--bbai-status-optimized-rail);
        --bbai-row-rail-opt-strong: var(--bbai-status-optimized-rail-strong);
        --bbai-row-content-inset: var(--bbai-status-row-content-inset);
        --bbai-link-row-strong: #047857;
        --bbai-link-row-secondary: #1d4ed8;
        --bbai-link-row-secondary-border: rgba(59, 130, 246, 0.42);
        --bbai-link-row-tertiary: #2563eb;
        /* Row action stack — one system for primary / secondary / menu trigger */
        --bbai-lib-act-h: 36px;
        --bbai-lib-act-radius: 9px;
        --bbai-lib-act-fs: 12px;
        --bbai-lib-act-fw: 600;
        --bbai-lib-act-gap: 8px;
        --bbai-lib-act-pad-x: 12px;
        --bbai-lib-act-border: #cbd5e1;
        --bbai-lib-act-border-strong: #94a3b8;
        --bbai-lib-act-surface: #ffffff;
        --bbai-lib-act-surface-hover: #f8fafc;
        --bbai-lib-act-text: #334155;
        --bbai-lib-act-text-muted: #64748b;
        color: var(--bbai-library-text);
    }

    /* Full-width tool surface — horizontal inset lives on body.bbai-dashboard .bbai-content-shell (saas-consistency.css) */
    .bbai-library-container.bbai-library-workspace.bbai-container {
        max-width: none;
        width: 100%;
        box-sizing: border-box;
        padding-left: 0;
        padding-right: 0;
    }

    .bbai-library-page-actions .bbai-btn,
    .bbai-library-summary-actions .bbai-btn,
    .bbai-library-task-banner__actions .bbai-btn,
    .bbai-library-sidebar-card .bbai-btn,
    .bbai-library-selection-bar .bbai-btn {
        min-height: 40px;
        border-radius: 12px;
        box-shadow: none;
        font-weight: 700;
    }

    .bbai-library-page-actions .bbai-btn-secondary,
    .bbai-library-summary-actions .bbai-btn-secondary,
    .bbai-library-task-banner__actions .bbai-btn-secondary,
    .bbai-library-sidebar-card .bbai-btn-secondary,
    .bbai-library-selection-bar .bbai-btn-secondary {
        border-color: #d7e0ee;
        background: #ffffff;
        color: #1f2937;
    }

    .bbai-library-scan-feedback {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: var(--card-gap, 16px);
        margin: 0 0 20px;
        padding: 18px 20px;
        border: 1px solid #dbe7f5;
        border-radius: 16px;
        background: linear-gradient(135deg, #f8fbff 0%, #eef6ff 100%);
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
    }

    .bbai-library-scan-feedback[hidden] {
        display: none !important;
    }

    .bbai-library-scan-feedback[data-state="attention"] {
        border-color: #bfdbfe;
        background: linear-gradient(135deg, #f7fbff 0%, #eef5ff 100%);
    }

    .bbai-library-scan-feedback[data-state="clear"] {
        border-color: #bbf7d0;
        background: linear-gradient(135deg, #f7fff9 0%, #effbf3 100%);
    }

    .bbai-library-scan-feedback__copy {
        min-width: 0;
        flex: 1;
    }

    .bbai-library-scan-feedback__eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin: 0 0 6px;
        font-size: 11px;
        line-height: 1;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        font-weight: 700;
        color: #0f766e;
    }

    .bbai-library-scan-feedback__summary,
    .bbai-library-scan-feedback__detail {
        margin: 0;
        color: var(--bbai-library-text);
        line-height: 1.55;
    }

    .bbai-library-scan-feedback__summary {
        font-size: 15px;
        font-weight: 600;
    }

    .bbai-library-scan-feedback__detail {
        margin-top: 4px;
        font-size: 13px;
        color: var(--bbai-library-muted);
    }

    .bbai-library-scan-feedback__actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        flex-shrink: 0;
    }

    .bbai-library-results--highlight {
        outline: 2px solid rgba(59, 130, 246, 0.45);
        outline-offset: 4px;
        animation: bbaiLibraryResultsPulse 1.6s ease;
    }

    @keyframes bbaiLibraryResultsPulse {
        0% {
            outline-color: rgba(59, 130, 246, 0.55);
        }

        100% {
            outline-color: transparent;
        }
    }

    .bbai-library-summary-card,
    .bbai-library-task-banner,
    .bbai-library-sidebar-card,
    .bbai-library-selection-bar {
        border: 1px solid var(--bbai-library-border);
        border-radius: 16px;
        background: #ffffff;
        box-shadow: var(--bbai-library-card-shadow);
    }

    .bbai-library-summary-card {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(250px, 320px);
        gap: var(--section-spacing, 24px);
        padding: 22px 24px;
        margin: 0 0 18px;
        background: var(--bbai-library-surface);
    }

    .bbai-library-summary-main {
        min-width: 0;
    }

    .bbai-library-summary-layout {
        display: flex;
        align-items: flex-start;
        gap: var(--card-gap, 16px);
    }

    .bbai-library-summary-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 48px;
        border-radius: 14px;
        flex: 0 0 48px;
        background: rgba(255, 255, 255, 0.88);
        border: 1px solid rgba(15, 23, 42, 0.08);
        color: #166534;
    }

    .bbai-library-summary-card[data-state="missing"] .bbai-library-summary-icon {
        color: #b91c1c;
    }

    .bbai-library-summary-card[data-state="weak"] .bbai-library-summary-icon {
        color: #b45309;
    }

    .bbai-library-summary-eyebrow,
    .bbai-library-task-banner__eyebrow,
    .bbai-library-sidebar-card__eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin: 0 0 8px;
        font-size: 11px;
        line-height: 1;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        font-weight: 700;
        color: #0f766e;
    }

    .bbai-library-summary-title,
    .bbai-library-task-banner__title,
    .bbai-library-sidebar-card__title {
        margin: 0;
        font-size: 24px;
        line-height: 1.15;
        letter-spacing: -0.03em;
        color: var(--bbai-library-text);
    }

    .bbai-library-summary-copy,
    .bbai-library-task-banner__copy,
    .bbai-library-sidebar-card__copy {
        margin: 10px 0 0;
        font-size: 14px;
        line-height: 1.6;
        color: var(--bbai-library-muted);
    }

    .bbai-library-summary-stats {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin-top: var(--card-gap, 16px);
    }

    .bbai-library-summary-stat {
        padding: 11px 13px;
        border-radius: 12px;
        border: 1px solid #e4ebf4;
        background: rgba(255, 255, 255, 0.88);
    }

    .bbai-library-summary-stat--optimized {
        background: var(--bbai-library-green-bg);
        border-color: #bbf7d0;
    }

    .bbai-library-summary-stat--missing {
        background: rgba(255, 255, 255, 0.88);
        border-color: #e4ebf4;
        box-shadow: inset 3px 0 0 rgba(225, 29, 72, 0.38);
    }

    .bbai-library-summary-stat-label {
        display: block;
        font-size: 12px;
        line-height: 1.4;
        color: var(--bbai-library-muted);
    }

    .bbai-library-summary-stat-value {
        display: block;
        margin-top: 4px;
        font-size: 22px;
        line-height: 1;
        font-weight: 700;
        letter-spacing: -0.02em;
        color: var(--bbai-library-text);
    }

    .bbai-library-summary-progress-wrap {
        margin-top: var(--card-gap, 16px);
    }

    .bbai-library-summary-progress {
        display: flex;
        overflow: hidden;
        width: 100%;
        height: 12px;
        border-radius: 999px;
        background: var(--bbai-library-track);
    }

    .bbai-library-summary-progress > span {
        display: block;
        height: 100%;
    }

    .bbai-library-summary-progress > .bbai-library-summary-progress__optimized {
        background: linear-gradient(90deg, #16a34a 0%, #22c55e 100%);
    }

    .bbai-library-summary-progress > .bbai-library-summary-progress__missing {
        background: linear-gradient(90deg, #ef4444 0%, #fb7185 100%);
    }

    .bbai-library-summary-foot {
        margin-top: var(--card-gap, 16px);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        font-size: 12px;
        color: var(--bbai-library-muted);
    }

    .bbai-library-summary-foot strong {
        color: var(--bbai-library-text);
    }

    .bbai-library-summary-side {
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        gap: 14px;
        padding: 18px;
        border-radius: 14px;
        border: 1px solid rgba(37, 99, 235, 0.08);
        background: rgba(255, 255, 255, 0.84);
    }

    .bbai-library-summary-next {
        display: flex;
        flex-direction: column;
        gap: 7px;
    }

    .bbai-library-summary-next-label {
        margin: 0;
        font-size: 12px;
        line-height: 1.4;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--bbai-library-subtle);
    }

    .bbai-library-summary-next-copy {
        margin: 0;
        font-size: 14px;
        line-height: 1.6;
        color: var(--bbai-library-text);
    }

    .bbai-library-summary-usage {
        display: flex;
        flex-direction: column;
        gap: 7px;
    }

    .bbai-library-summary-usage-line,
    .bbai-library-summary-usage-copy {
        margin: 0;
        font-size: 13px;
        line-height: 1.6;
        color: var(--bbai-library-muted);
    }

    .bbai-library-summary-usage-line strong {
        color: var(--bbai-library-text);
    }

    .bbai-library-summary-usage-bar {
        width: 100%;
        height: 10px;
        border-radius: 999px;
        overflow: hidden;
        background: #e5edf6;
    }

    .bbai-library-summary-usage-fill {
        display: block;
        height: 100%;
        width: 0;
        border-radius: inherit;
        background: linear-gradient(90deg, #2563eb 0%, #60a5fa 100%);
        transition: width 0.3s ease;
    }

    .bbai-library-summary-actions {
        display: flex;
        flex-direction: column;
        gap: 8px;
        align-items: stretch;
        flex-wrap: wrap;
    }

    .bbai-library-summary-actions .bbai-btn {
        justify-content: center;
        text-align: center;
    }

    /* No card chrome — avoids a “divider” seam between coverage grid and review workspace */
    .bbai-library-toolbar-card {
        position: sticky;
        top: 24px;
        z-index: 9;
        margin: 8px 0 0;
        padding: 4px 0 10px;
        border: none;
        border-radius: 0;
        background: transparent;
        box-shadow: none;
        backdrop-filter: none;
    }

    /*
     * Workspace card — lightweight dashboard-style sectioning (spacing + dividers, no nested boxes).
     */
    .bbai-library-workspace-card {
        --bbai-ws-space-xs: 8px;
        --bbai-ws-space-sm: 12px;
        --bbai-ws-space-md: 16px;
        --bbai-ws-space-lg: 24px;
        --bbai-ws-radius: var(--bbai-surface-card-radius, 14px);
        --bbai-ws-divider: #eef2f7;

        background: var(--bbai-surface-card-bg, #ffffff);
        border: var(--bbai-surface-card-border, 1px solid #e5e7eb);
        border-radius: var(--bbai-ws-radius);
        box-shadow: var(--bbai-surface-card-shadow, 0 1px 3px rgba(15, 23, 42, 0.04));
        overflow: hidden;
        margin-top: var(--bbai-ws-space-sm);
    }

    /* Card header: padding + type from bbai-section-header.css + saas-consistency (Dashboard command-card parity) */
    .bbai-library-workspace-card__head.bbai-section-header--split-end {
        background: #ffffff;
        border-bottom: none;
    }

    /* Inline filter toolbar — flows from same section as header */
    .bbai-library-workspace-card__filter-toolbar {
        padding: 16px var(--bbai-ws-space-lg) var(--bbai-ws-space-sm);
        margin: 0;
        background: transparent;
        border: none;
        border-bottom: 1px solid var(--bbai-ws-divider);
        border-radius: 0;
    }

    /*
     * Workspace filter toolbar — layout + status dots only; colors from bbai-admin-semantic-status.css
     */
    .bbai-library-workspace-card__filters.bbai-alt-review-filters {
        margin: 0;
    }

    .bbai-library-workspace-card__filters .bbai-alt-review-filters__list {
        margin: 0;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--bbai-ws-space-xs);
    }

    .bbai-library-workspace-card__filters .bbai-alt-review-filters__btn {
        position: relative;
        z-index: 0;
    }

    .bbai-library-workspace-card__filters .bbai-alt-review-filters__btn::before {
        content: '';
        flex: 0 0 8px;
        width: 8px;
        height: 8px;
        border-radius: 999px;
        background: var(--bbai-status-neutral-indicator);
    }

    .bbai-library-workspace-card__filters .bbai-alt-review-filters__label {
        white-space: nowrap;
    }

    .bbai-library-workspace-card__filters .bbai-alt-review-filters__btn[data-filter='missing']::before {
        background: var(--bbai-status-missing-dot);
    }

    .bbai-library-workspace-card__filters .bbai-alt-review-filters__btn[data-filter='weak']::before {
        background: var(--bbai-status-needs-review-dot);
    }

    .bbai-library-workspace-card__filters .bbai-alt-review-filters__btn[data-filter='optimized']::before {
        background: var(--bbai-status-optimized-dot);
    }

    .bbai-library-workspace-card__filters .bbai-alt-review-filters__btn[data-filter='all'].bbai-alt-review-filters__btn--active::before {
        background: rgba(255, 255, 255, 0.95);
        box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.35);
    }

    .bbai-library-workspace-card__filters .bbai-alt-review-filters__btn[data-filter='missing'].bbai-alt-review-filters__btn--active::before {
        background: rgba(255, 255, 255, 0.95);
        box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.35);
    }

    .bbai-library-workspace-card__filters .bbai-alt-review-filters__btn[data-filter='weak'].bbai-alt-review-filters__btn--active::before {
        background: rgba(255, 251, 235, 0.98);
        box-shadow: 0 0 0 1px rgba(255, 251, 235, 0.4);
    }

    .bbai-library-workspace-card__filters .bbai-alt-review-filters__btn[data-filter='optimized'].bbai-alt-review-filters__btn--active::before {
        background: rgba(240, 253, 244, 0.98);
        box-shadow: 0 0 0 1px rgba(240, 253, 244, 0.4);
    }

    /* Table region — same surface as card; padding only */
    .bbai-library-workspace-card__table-surface {
        padding: var(--bbai-ws-space-sm) var(--bbai-ws-space-lg) var(--bbai-ws-space-md);
        background: #ffffff;
        border: none;
    }

    .bbai-library-workspace-card__controls-row {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: var(--bbai-ws-space-xs);
        padding: 0 0 var(--bbai-ws-space-sm);
        margin: 0;
        background: transparent;
        border-bottom: none;
    }

    .bbai-library-workspace-card__controls-row .bbai-library-search {
        flex: 1 1 220px;
        min-width: 0;
        max-width: 420px;
        margin-right: auto;
    }

    .bbai-library-workspace-card__table-surface .bbai-library-main {
        margin: 0;
        padding: 0;
        background: transparent;
        border: none;
        border-radius: 0;
        box-shadow: none;
        overflow: visible;
    }

    .bbai-library-workspace-card__table-surface .bbai-library-table-shell {
        margin: 0;
        padding: 0;
        background: transparent;
    }


    @media (max-width: 960px) {
        .bbai-library-workspace-card__controls-row {
            justify-content: stretch;
        }
        .bbai-library-workspace-card__controls-row .bbai-library-search {
            flex: 1 1 100%;
            max-width: none;
            margin-right: 0;
        }
    }

    @media (max-width: 782px) {
        .bbai-library-workspace-card__filter-toolbar {
            padding: 16px var(--bbai-ws-space-md) var(--bbai-ws-space-sm);
        }
        .bbai-library-workspace-card__table-surface {
            padding: var(--bbai-ws-space-sm) var(--bbai-ws-space-md) var(--bbai-ws-space-md);
        }
    }

    /* Old filter-strip now lives inside the card — hide the standalone version */
    .bbai-library-workspace-filter-strip {
        display: none;
    }

    .bbai-library-workspace-intro {
        margin: 0;
        padding: 14px 0 8px;
        border-bottom: 1px solid #eef2f7;
    }

    .bbai-library-workspace-intro__title {
        margin: 0 0 4px;
        font-size: 19px;
        line-height: 1.22;
        font-weight: 700;
        letter-spacing: -0.03em;
        color: #0f172a;
    }

    .bbai-library-workspace-intro__tagline {
        margin: 0 0 6px;
        font-size: 13px;
        line-height: 1.45;
        color: var(--bbai-library-text);
        max-width: min(56ch, 100%);
    }

    .bbai-library-workspace-intro__meta {
        margin: 0;
        font-size: 12px;
        line-height: 1.4;
        font-weight: 500;
        color: var(--bbai-library-muted);
        font-variant-numeric: tabular-nums;
    }

    .bbai-library-workspace-intro__hint {
        margin: 6px 0 0;
        font-size: 12px;
        line-height: 1.42;
        color: var(--bbai-library-subtle);
        max-width: min(68ch, 100%);
    }

    .bbai-library-section-head {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: var(--card-gap, 16px);
        margin-bottom: var(--card-gap, 16px);
    }

    .bbai-library-section-title {
        margin: 0;
        font-size: 16px;
        line-height: 1.3;
        color: var(--bbai-library-text);
    }

    .bbai-library-section-copy {
        margin: 4px 0 0;
        font-size: 13px;
        line-height: 1.6;
        color: var(--bbai-library-muted);
    }

    .bbai-library-toolbar {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: var(--card-gap, 16px);
        flex-wrap: wrap;
    }

    .bbai-library-toolbar__left {
        min-width: 0;
    }

    .bbai-library-toolbar__right {
        flex: 1 1 260px;
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        flex-wrap: wrap;
        min-width: 0;
    }

    .bbai-library-toolbar__right--full {
        flex: 1 1 100%;
        max-width: 100%;
        justify-content: flex-end;
    }

    .bbai-library-toolbar__controls {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .bbai-library-search {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 260px;
        padding: 0 14px;
        border-radius: 12px;
        border: 1px solid #dce4f0;
        background: #ffffff;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.65);
    }

    .bbai-library-search svg {
        flex: 0 0 auto;
        color: var(--bbai-library-subtle);
    }

    .bbai-library-search input {
        width: 100%;
        min-height: 42px;
        border: 0;
        padding: 0;
        background: transparent;
        color: var(--bbai-library-text);
        font-size: 14px;
        box-shadow: none;
    }

    .bbai-library-search input:focus {
        outline: none;
        box-shadow: none;
    }

    .bbai-library-select {
        min-width: 150px;
        min-height: 42px;
        padding: 0 38px 0 14px;
        border-radius: 12px;
        border: 1px solid #dce4f0;
        background-color: #ffffff;
        color: var(--bbai-library-text);
        font-size: 14px;
        line-height: 1.4;
    }

    .bbai-library-select:focus,
    .bbai-library-search input:focus-visible,
    .bbai-filter-group__item:focus-visible,
    .bbai-library-row-action:focus-visible,
    .bbai-row-action-btn:focus-visible,
    .bbai-library-alt-trigger:focus-visible,
    .bbai-library-thumbnail-button:focus-visible {
        outline: 3px solid rgba(37, 99, 235, 0.28);
        outline-offset: 2px;
    }

    .bbai-library-work-grid {
        display: block;
    }

    .bbai-library-main {
        min-width: 0;
    }

    .bbai-library-sidebar {
        min-width: 0;
    }

    .bbai-library-sidebar__inner {
        position: sticky;
        top: 104px;
        display: flex;
        flex-direction: column;
        gap: var(--card-gap, 16px);
    }

    .bbai-library-sidebar-card {
        padding: 20px;
    }

    .bbai-library-sidebar-card__meta {
        margin: 10px 0 0;
        font-size: 13px;
        line-height: 1.6;
        color: var(--bbai-library-muted);
    }

    .bbai-library-sidebar-card__actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: var(--card-gap, 16px);
    }

    .bbai-library-sidebar-card--queue {
        background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
    }

    .bbai-library-sidebar-card--upgrade {
        background: linear-gradient(180deg, #f9fbff 0%, #ffffff 100%);
    }

    .bbai-library-sidebar-card--selection[hidden] {
        display: none !important;
    }

    .bbai-library-upgrade-price {
        display: flex;
        align-items: baseline;
        gap: 8px;
        margin-top: var(--card-gap, 16px);
        color: var(--bbai-library-text);
    }

    .bbai-library-upgrade-price strong {
        font-size: 28px;
        line-height: 1;
        letter-spacing: -0.03em;
    }

    .bbai-library-upgrade-price span {
        font-size: 14px;
        color: var(--bbai-library-muted);
    }

    .bbai-library-upgrade-list {
        display: grid;
        gap: 10px;
        padding: 0;
        margin: 16px 0 0;
        list-style: none;
    }

    .bbai-library-upgrade-list li {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        font-size: 14px;
        line-height: 1.5;
        color: var(--bbai-library-text);
    }

    .bbai-library-upgrade-list svg {
        flex: 0 0 auto;
        margin-top: 2px;
        color: var(--bbai-library-green);
    }

    .bbai-library-selection-bar {
        position: sticky;
        top: 76px;
        z-index: 8;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 11px 14px;
        margin: 0 0 12px;
        background: rgba(255, 255, 255, 0.97);
        backdrop-filter: blur(12px);
    }

    .bbai-library-selection-bar[hidden] {
        display: none !important;
    }

    .bbai-library-selection-bar__summary {
        display: flex;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
        min-width: 0;
    }

    .bbai-library-selection-bar__lead {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-weight: 700;
        color: var(--bbai-library-text);
    }

    .bbai-library-selection-bar__count,
    .bbai-library-selection-bar__credits {
        font-size: 13px;
        line-height: 1.5;
        color: var(--bbai-library-muted);
    }

    .bbai-library-selection-bar__actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .bbai-library-table-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        padding: 4px 0 10px;
        margin: 0 0 2px;
        border-bottom: 1px solid #e2e8f0;
    }

    .bbai-library-table-head--bulk-only {
        padding: 10px 0 12px;
        margin: 0;
        border-bottom: 1px solid #eef2f7;
        min-height: 0;
    }

    .bbai-library-main:not([data-bbai-bulk-mode="true"]) .bbai-library-table-head--bulk-only {
        display: none;
    }

    .bbai-library-main[data-bbai-bulk-mode="true"] .bbai-library-table-head--bulk-only {
        display: flex;
        align-items: center;
    }

    .bbai-library-table-title {
        margin: 0;
        font-size: 18px;
        line-height: 1.25;
        font-weight: 700;
        letter-spacing: -0.025em;
        color: #0f172a;
    }

    .bbai-library-table-meta {
        margin: 4px 0 0;
        font-size: 13px;
        line-height: 1.5;
        color: var(--bbai-library-muted);
    }

    .bbai-library-table {
        width: 100%;
        min-width: 880px;
        border-collapse: separate;
        border-spacing: 0 8px;
        margin: -8px 0 0;
    }

    .bbai-library-table thead th {
        padding: 0 16px 8px;
        font-size: 11px;
        line-height: 1.2;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        color: var(--bbai-library-subtle);
    }

    .bbai-library-table tbody td {
        vertical-align: top;
        padding: 12px 13px;
        background: #ffffff;
        border-top: 1px solid #e7edf6;
        border-bottom: 1px solid #e7edf6;
        transition: background-color 0.16s ease, border-color 0.16s ease, box-shadow 0.16s ease;
    }

    .bbai-library-table tbody td:first-child {
        border-left: 1px solid #e7edf6;
        border-top-left-radius: 18px;
        border-bottom-left-radius: 18px;
    }

    .bbai-library-table tbody td:last-child {
        border-right: 1px solid #e7edf6;
        border-top-right-radius: 18px;
        border-bottom-right-radius: 18px;
    }

    .bbai-library-table tbody tr:hover td,
    .bbai-library-table tbody tr:focus-within td {
        background: #fbfdff;
        border-color: #d7e2f0;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.72), 0 16px 30px rgba(15, 23, 42, 0.04);
    }

    .bbai-library-row--hidden {
        display: none;
    }

    .bbai-library-table .bbai-library-cell--select {
        width: 52px;
        padding-top: var(--card-gap, 16px) !important;
    }

    .bbai-library-table .bbai-library-cell--asset {
        width: 280px;
    }

    .bbai-library-table .bbai-library-cell--alt-text {
        width: 42%;
        min-width: 300px;
    }

    .bbai-library-table .bbai-library-cell--status {
        width: 280px;
    }

    .bbai-library-asset {
        display: flex;
        gap: 14px;
    }

    .bbai-library-thumbnail-button {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        padding: 0;
        border: 0;
        background: transparent;
        cursor: pointer;
    }

    .bbai-library-thumbnail,
    .bbai-library-thumbnail-placeholder {
        width: 58px;
        height: 58px;
        border-radius: 10px;
        object-fit: cover;
        background: #e5edf7;
        border: 1px solid #dbe5f2;
    }

    .bbai-library-thumbnail-placeholder {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--bbai-library-subtle);
    }

    .bbai-library-hover-preview {
        position: absolute;
        left: calc(100% + 14px);
        top: 50%;
        transform: translateY(-50%);
        width: 180px;
        padding: 10px;
        border-radius: 18px;
        background: rgba(15, 23, 42, 0.96);
        box-shadow: 0 28px 56px rgba(15, 23, 42, 0.24);
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.16s ease, transform 0.16s ease;
        z-index: 6;
    }

    .bbai-library-hover-preview img {
        display: block;
        width: 100%;
        border-radius: 12px;
    }

    .bbai-library-thumbnail-button:hover .bbai-library-hover-preview,
    .bbai-library-thumbnail-button:focus-visible .bbai-library-hover-preview {
        opacity: 1;
        transform: translateY(-50%) scale(1.01);
    }

    .bbai-library-info-cell {
        display: flex;
        flex-direction: column;
        gap: 5px;
        min-width: 0;
    }

    .bbai-library-info-name {
        margin: 0;
        font-size: 13px;
        line-height: 1.4;
        font-weight: 700;
        color: var(--bbai-library-text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 210px;
    }

    .bbai-library-info-meta,
    .bbai-library-info-updated {
        display: block;
        font-size: 11px;
        line-height: 1.5;
        color: var(--bbai-library-muted);
    }

    .bbai-library-review-block {
        display: flex;
        flex-direction: column;
        gap: 8px;
        min-width: 0;
    }

    .bbai-library-review-label {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        line-height: 1.2;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--bbai-library-subtle);
    }

    .bbai-library-review-tip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 18px;
        height: 18px;
        border-radius: 999px;
        border: 1px solid #dbe4ee;
        background: #ffffff;
        color: var(--bbai-library-muted);
        font-size: 11px;
        line-height: 1;
    }

    .bbai-library-alt-trigger {
        width: 100%;
        min-height: 62px;
        padding: 12px 14px;
        border-radius: 12px;
        border: 1px solid #dbe5f2;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        text-align: left;
        cursor: text;
        transition: border-color 0.16s ease, box-shadow 0.16s ease, transform 0.16s ease;
    }

    .bbai-library-alt-trigger:hover {
        border-color: #bfd1ea;
        transform: translateY(-1px);
    }

    .bbai-alt-text-preview {
        display: block;
        font-size: 14px;
        line-height: 1.65;
        color: var(--bbai-library-text);
        white-space: normal;
        overflow: hidden;
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 3;
    }

    .bbai-alt-text-missing {
        display: block;
        font-size: 14px;
        line-height: 1.5;
        font-style: italic;
        color: var(--bbai-library-red);
    }

    .bbai-library-status-stack {
        display: flex;
        flex-direction: column;
        gap: 9px;
        min-height: 100%;
    }

    .bbai-library-status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: fit-content;
        padding: 3px 8px;
        border-radius: 999px;
        font-size: 11px;
        line-height: 1.15;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    /* Status + score chips — colors from bbai-admin-semantic-status.css (--bbai-status-*) */

    .bbai-library-score-badge {
        display: inline-flex;
        align-items: baseline;
        gap: 3px;
        padding: 3px 8px;
        border-radius: 999px;
        font-size: 11px;
        line-height: 1.2;
        font-weight: 700;
        letter-spacing: 0.01em;
        border: 1px solid transparent;
        white-space: nowrap;
    }

    .bbai-library-score-badge__value {
        font-variant-numeric: tabular-nums;
    }

    .bbai-library-score-badge__label {
        font-weight: 600;
        opacity: 0.92;
    }

    .bbai-library-card__score-hint {
        margin: 4px 0 0;
        font-size: 11px;
        line-height: 1.4;
        color: var(--bbai-library-muted);
    }

    .bbai-library-actions {
        display: flex;
        align-items: stretch;
        gap: var(--bbai-lib-act-gap);
        flex-wrap: wrap;
        margin-top: auto;
    }

    .bbai-row-action-btn {
        box-sizing: border-box;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: var(--bbai-lib-act-h);
        height: var(--bbai-lib-act-h);
        padding: 0 var(--bbai-lib-act-pad-x);
        border-radius: var(--bbai-lib-act-radius);
        border: 1px solid var(--bbai-lib-act-border);
        background: var(--bbai-lib-act-surface);
        color: var(--bbai-lib-act-text);
        font-size: var(--bbai-lib-act-fs);
        line-height: 1.2;
        font-weight: var(--bbai-lib-act-fw);
        cursor: pointer;
        transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
    }

    .bbai-row-action-btn:hover:not(:disabled) {
        background: var(--bbai-lib-act-surface-hover);
        border-color: var(--bbai-lib-act-border-strong);
        color: #0f172a;
    }

    .bbai-row-action-btn:focus-visible {
        outline: none;
        box-shadow: 0 0 0 2px #ffffff, 0 0 0 4px rgba(100, 116, 139, 0.35);
    }

    .bbai-row-action-btn--primary {
        background: var(--bbai-cta-primary-bg, #10b981);
        border-color: var(--bbai-cta-primary-bg-active, #047857);
        color: #ffffff;
    }

    .bbai-row-action-btn--primary:hover:not(:disabled) {
        background: var(--bbai-cta-primary-bg-hover, #059669);
        border-color: var(--bbai-cta-primary-bg-active, #047857);
        color: #ffffff;
    }

    .bbai-row-action-btn--primary:focus-visible {
        box-shadow: 0 0 0 2px #ffffff, 0 0 0 4px rgba(16, 185, 129, 0.4);
    }

    .bbai-row-action-btn--ghost {
        background: var(--bbai-lib-act-surface);
        color: var(--bbai-lib-act-text-muted);
        border-color: #e2e8f0;
    }

    .bbai-row-action-btn.is-loading {
        pointer-events: none;
        opacity: 0.78;
    }

    .bbai-row-action-spinner {
        width: 12px;
        height: 12px;
        border-radius: 999px;
        border: 2px solid currentColor;
        border-right-color: transparent;
        animation: bbaiLibrarySpin 0.8s linear infinite;
    }

    .bbai-library-inline-edit {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .bbai-library-inline-edit__textarea {
        width: 100%;
        min-height: 64px;
        max-height: 200px;
        padding: 10px 12px;
        border-radius: 12px;
        border: 1px solid #60a5fa;
        box-shadow: 0 0 0 4px rgba(96, 165, 250, 0.12);
        resize: vertical;
        line-height: 1.45;
        box-sizing: border-box;
    }

    .bbai-library-inline-edit__actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .bbai-library-inline-edit__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 999px;
        border: 1px solid #dbe4ee;
        background: #ffffff;
        color: #334155;
        cursor: pointer;
    }

    .bbai-library-inline-edit__icon--save {
        background: var(--bbai-library-green-bg);
        border-color: #86efac;
        color: #166534;
    }

    .bbai-library-inline-edit__error {
        margin: 0;
        min-height: 18px;
        font-size: 12px;
        color: var(--bbai-library-red);
    }

    .bbai-library-filter-empty__cell {
        padding: 0 !important;
        background: transparent !important;
        border: 0 !important;
    }

    @keyframes bbaiLibrarySpin {
        to {
            transform: rotate(360deg);
        }
    }

    @media (max-width: 1180px) {
        .bbai-library-summary-card {
            grid-template-columns: 1fr;
        }

        .bbai-library-work-grid {
            grid-template-columns: 1fr;
        }

        .bbai-library-sidebar__inner {
            position: static;
        }
    }

    @media (max-width: 960px) {
        .bbai-library-hover-preview {
            display: none;
        }
    }

    @media (max-width: 782px) {
        .bbai-library-summary-card,
        .bbai-library-selection-bar,
        .bbai-library-workspace .bbai-pagination.bbai-table-meta {
            flex-direction: column;
            align-items: stretch;
        }

        .bbai-library-summary-actions,
        .bbai-library-selection-bar__actions,
        .bbai-library-workspace .bbai-pagination.bbai-table-meta .bbai-pagination-controls {
            justify-content: flex-start;
        }

        .bbai-library-summary-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .bbai-library-toolbar-card {
            position: static;
        }

        .bbai-library-section-head,
        .bbai-library-summary-layout,
        .bbai-library-summary-side {
            align-items: flex-start;
        }
    }

    @media (max-width: 640px) {
        .bbai-library-container {
            padding-bottom: 24px;
        }

        .bbai-library-surface-root .bbai-page-hero .bbai-banner__headline.bbai-dashboard-hero__headline {
            font-size: 20px;
        }

        .bbai-library-summary-title,
        .bbai-library-sidebar-card__title {
            font-size: 22px;
        }

        .bbai-library-search {
            min-width: 100%;
        }

        .bbai-library-select,
        #bbai-library-apply-bulk,
        .bbai-library-toolbar__controls > .bbai-btn {
            width: 100%;
        }

        .bbai-library-toolbar__right,
        .bbai-library-toolbar__controls {
            width: 100%;
            justify-content: stretch;
        }

        .bbai-library-summary-stats {
            grid-template-columns: 1fr;
        }
    }

    /* ALT Library refactor overrides */

    .bbai-library-workspace .bbai-queue-workflow[data-bbai-wf-mode="compact"] .bbai-queue-workflow__steps {
        display: none;
    }

    .bbai-library-workspace .bbai-queue-workflow[data-bbai-wf-mode="steps"] .bbai-queue-workflow__healthy {
        display: none;
    }

    .bbai-library-workspace .bbai-queue-workflow__healthy {
        display: grid;
        grid-template-columns: minmax(0, 1.2fr) minmax(240px, .8fr) auto;
        gap: var(--card-gap, 16px);
        align-items: center;
    }

    .bbai-library-workspace .bbai-queue-workflow__healthy-main {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        min-width: 0;
    }

    .bbai-library-workspace .bbai-queue-workflow__healthy-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border-radius: 14px;
        color: #166534;
        background: #ecfdf3;
        border: 1px solid #bbf7d0;
        flex: 0 0 44px;
    }

    .bbai-library-workspace .bbai-queue-workflow__healthy-eyebrow {
        margin: 0 0 6px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .12em;
        text-transform: uppercase;
        color: #15803d;
    }

    .bbai-library-workspace .bbai-queue-workflow__healthy-title {
        margin: 0;
        font-size: 22px;
        line-height: 1.15;
        letter-spacing: -.02em;
        color: #0f172a;
    }

    .bbai-library-workspace .bbai-queue-workflow__healthy-copy {
        margin: 8px 0 0;
        font-size: 14px;
        line-height: 1.6;
        color: #475569;
    }

    .bbai-library-workspace .bbai-queue-workflow__healthy-metrics {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 10px;
    }

    .bbai-library-workspace .bbai-queue-workflow__healthy-metric {
        padding: 14px;
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid #dbe7f4;
    }

    .bbai-library-workspace .bbai-queue-workflow__healthy-metric-label {
        display: block;
        font-size: 11px;
        line-height: 1.4;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #64748b;
    }

    .bbai-library-workspace .bbai-queue-workflow__healthy-metric-value {
        display: block;
        margin-top: 6px;
        font-size: 22px;
        line-height: 1;
        letter-spacing: -.03em;
        color: #0f172a;
    }

    .bbai-library-workspace .bbai-queue-workflow__healthy-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .bbai-library-workspace .bbai-queue-workflow__healthy-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 42px;
        padding: 0 16px;
        border-radius: 12px;
        border: 1px solid #dce6f2;
        background: #ffffff;
        color: #1e293b;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
    }

    .bbai-library-workspace .bbai-queue-workflow__healthy-btn--primary {
        background: #0f172a;
        border-color: #0f172a;
        color: #ffffff;
    }

    .bbai-library-workspace .bbai-queue-workflow__healthy-btn--upgrade {
        background: #f8fbff;
    }

    .bbai-library-summary-stat--weak {
        background: var(--bbai-library-amber-bg);
        border-color: #fcd34d;
    }

    .bbai-library-summary-stat--credits {
        background: var(--bbai-library-blue-bg);
        border-color: #bfdbfe;
    }

    .bbai-library-summary-progress > .bbai-library-summary-progress__weak {
        background: linear-gradient(90deg, #f59e0b 0%, #fbbf24 100%);
    }

    .bbai-library-summary-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
        margin-top: var(--card-gap, 16px);
    }

    .bbai-library-summary-next-title {
        margin: 0;
        font-size: 17px;
        line-height: 1.25;
        letter-spacing: -.02em;
        color: var(--bbai-library-text);
    }

    .bbai-library-summary-automation {
        display: flex;
        flex-direction: column;
        gap: 6px;
        padding: 11px 13px;
        border-radius: 12px;
        border: 1px solid #dbe7f4;
        background: #f8fbff;
    }

    .bbai-library-summary-tension {
        padding: 9px 12px;
        border-radius: 10px;
        border: 1px solid #fde68a;
        background: #fffbeb;
        font-size: 12px;
        line-height: 1.55;
        color: #92400e;
        font-weight: 600;
        margin: 0;
    }

    .bbai-library-summary-reassurance {
        margin: 0;
        font-size: 11px;
        line-height: 1.5;
        color: var(--bbai-library-subtle);
        text-align: center;
    }

    .bbai-library-usage-alert {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        padding: 11px 13px;
        border-radius: 12px;
        border: 1px solid rgba(252, 211, 77, 0.78);
        background: #fffbeb;
    }

    .bbai-library-usage-alert--out {
        border-color: rgba(254, 202, 202, 0.82);
        background: #fff1f2;
    }

    .bbai-library-usage-alert__copy {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
        font-size: 13px;
        line-height: 1.5;
        color: #475569;
    }

    .bbai-library-usage-alert__copy strong {
        color: #0f172a;
    }

    .bbai-library-toolbar__upgrade {
        border-style: dashed;
    }

    .bbai-library-selection-bar {
        top: 72px;
    }

    .bbai-library-selection-bar__meta {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .bbai-library-selection-bar__plan {
        font-size: 12px;
        line-height: 1.5;
        color: var(--bbai-library-subtle);
    }

    .bbai-library-info-meta-grid {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-wrap: wrap;
    }

    .bbai-library-info-meta {
        display: inline-flex;
        align-items: center;
        padding: 2px 6px;
        border-radius: 999px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        font-size: 10px;
        line-height: 1;
        color: #64748b;
    }

    .bbai-library-alt-preview-card {
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding: 11px 13px;
        border-radius: 12px;
        border: 1px solid #dbe5f2;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }

    .bbai-library-alt-preview-card--collapsible .bbai-alt-text-preview {
        -webkit-line-clamp: 2;
    }

    .bbai-library-alt-preview-card.is-expanded .bbai-alt-text-preview {
        display: block;
        overflow: visible;
    }

    .bbai-library-alt-expand {
        align-self: flex-start;
        padding: 0;
        border: 0;
        background: transparent;
        color: var(--bbai-link-row-tertiary);
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: underline;
        text-decoration-color: rgba(37, 99, 235, 0.35);
        text-underline-offset: 3px;
        transition: color 0.14s ease, text-decoration-color 0.14s ease;
    }

    .bbai-library-alt-expand:hover,
    .bbai-library-alt-expand:focus-visible {
        color: #1d4ed8;
        text-decoration-color: rgba(29, 78, 216, 0.75);
        outline: none;
    }

    .bbai-library-alt-helper,
    .bbai-library-status-copy {
        margin: 0;
        font-size: 12px;
        line-height: 1.6;
        color: var(--bbai-library-muted);
    }

    .bbai-library-status-tags {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
        padding: 5px 8px;
        border-radius: 10px;
        background: rgba(248, 250, 252, 0.95);
        border: 1px solid #e8edf4;
        max-width: 100%;
    }

    .bbai-library-status-badge--secondary {
        background: #f8fafc;
        color: #475569;
        border: 1px solid #dbe5f2;
    }

    .bbai-library-status-badge--ai {
        background: #eff6ff;
        color: #1d4ed8;
        border-color: #bfdbfe;
    }

    .bbai-library-status-badge--reviewed {
        background: #f0fdf4;
        color: #166534;
        border-color: #bbf7d0;
    }

    .bbai-library-actions {
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
    }

    .bbai-library-action-group {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .bbai-row-action-btn--review {
        background: #ecfdf3;
        border-color: #bbf7d0;
        color: #166534;
    }

    .bbai-row-action-btn--quiet {
        background: #f8fafc;
    }

    .bbai-library-filter-empty__cell .bbai-library-empty-card {
        margin-top: var(--card-gap, 16px);
    }

    @media (max-width: 1120px) {
        .bbai-library-workspace .bbai-queue-workflow__healthy {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 960px) {
        .bbai-library-summary-side,
        .bbai-library-usage-alert {
            gap: 12px;
        }

        .bbai-library-selection-bar {
            position: static;
        }
    }

    @media (max-width: 782px) {
        .bbai-library-scan-feedback {
            flex-direction: column;
        }

        .bbai-library-scan-feedback__actions {
            width: 100%;
        }

        .bbai-library-usage-alert,
        .bbai-library-workspace .bbai-queue-workflow__healthy-actions {
            flex-direction: column;
            align-items: stretch;
        }

        .bbai-library-workspace .bbai-queue-workflow__healthy-metrics {
            grid-template-columns: 1fr;
        }
    }

    /* Library command hero: use shared product-banner.css surfaces + CTA rail (parity with Analytics). */

    .bbai-library-credits-banner-host--strip {
        display: none;
    }

    /* =====================================================
       REVIEW WORKSPACE — Table v2
       ===================================================== */

    /* Single-line metadata (PNG • 1200×630 • 37KB • Updated 22 Mar) */
    .bbai-library-info-meta-line {
        display: block;
        font-size: 11px;
        line-height: 1.4;
        color: var(--bbai-library-subtle);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
        margin-top: 2px;
    }

    /* ALT preview card — v2: no label, cleaner bg */
    .bbai-library-alt-preview-card--v2 {
        min-height: 40px;
        background: #f6f9fd;
        border-color: #dde8f4;
        cursor: pointer;
    }

    .bbai-library-alt-preview-card--v2:hover {
        border-color: #b8cfe8;
        background: #f0f6fc;
    }

    .bbai-library-alt-preview-card--v2 .bbai-alt-text-preview {
        font-size: 14px;
        line-height: 1.45;
        color: var(--bbai-library-text);
        -webkit-line-clamp: 2;
    }

    .bbai-library-alt-preview-card--v2 .bbai-alt-text-missing {
        font-size: 13px;
        color: var(--bbai-library-subtle);
        font-style: normal;
    }

    /* Actions v2 — Generate/Regenerate is primary when AI action leads; Edit manually is secondary */
    .bbai-library-actions--v2 {
        flex-direction: row;
        flex-wrap: wrap;
        gap: 6px;
    }

    /* Row inline text links (View image • Copy ALT) */
    .bbai-library-row-links {
        display: flex;
        align-items: center;
        gap: 7px;
        margin-top: 4px;
    }

    .bbai-row-link {
        padding: 0;
        border: 0;
        background: transparent;
        color: var(--bbai-link-row-secondary);
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
        text-underline-offset: 2px;
        text-decoration-color: rgba(37, 99, 235, 0.35);
        transition: color 0.14s ease, text-decoration-color 0.14s ease;
        line-height: 1;
    }

    .bbai-row-link:hover,
    .bbai-row-link:focus-visible {
        color: #1e3a8a;
        text-decoration: underline;
        text-decoration-color: rgba(30, 58, 138, 0.65);
        outline: none;
    }

    .bbai-row-link:disabled,
    .bbai-row-link[aria-disabled='true'] {
        color: #94a3b8 !important;
        text-decoration: none !important;
        cursor: not-allowed !important;
        opacity: 1;
    }

    .bbai-row-link:disabled:hover,
    .bbai-row-link[aria-disabled='true']:hover {
        color: #94a3b8 !important;
        text-decoration: none !important;
    }

    .bbai-row-link-sep {
        color: #d1dae8;
        font-size: 11px;
        user-select: none;
    }

    /* Compact selection / bulk bar */
    .bbai-selection-bar-left {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        min-width: 0;
    }

    .bbai-selection-all-label {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        font-size: 13px;
        font-weight: 600;
        color: var(--bbai-library-text);
        cursor: pointer;
        user-select: none;
    }

    .bbai-selection-bar-count {
        font-size: 13px;
        font-weight: 700;
        color: var(--bbai-library-text);
    }

    .bbai-selection-bar-sep {
        color: #d1dae8;
        font-size: 14px;
        user-select: none;
    }

    .bbai-selection-clear {
        color: var(--bbai-library-muted) !important;
        background: transparent !important;
        border-color: transparent !important;
        font-weight: 600 !important;
        text-decoration: underline;
        text-underline-offset: 2px;
        text-decoration-color: transparent;
        padding-left: 4px !important;
        padding-right: 4px !important;
    }

    .bbai-selection-clear:hover {
        color: var(--bbai-library-text) !important;
        text-decoration-color: currentColor;
        background: transparent !important;
    }

    /* Table cell column widths — v2 (legacy <table> layout only; not review cards) */
    .bbai-library-table .bbai-library-cell--asset { width: 240px; }
    .bbai-library-table .bbai-library-cell--alt-text { width: auto; min-width: 280px; }
    .bbai-library-table .bbai-library-cell--status { width: 200px; }

    /* =====================================================
       REVIEW QUEUE — Card workflow
       ===================================================== */
    .bbai-library-toolbar {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 12px 20px;
        align-items: center;
    }

    @media (min-width: 720px) {
        .bbai-library-toolbar {
            grid-template-columns: minmax(0, 1fr) auto;
        }

        .bbai-library-toolbar__right {
            justify-self: end;
        }
    }

    .bbai-library-toolbar__left,
    .bbai-library-toolbar__right,
    .bbai-library-toolbar__controls {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .bbai-library-toolbar__right {
        justify-content: flex-end;
    }

    .bbai-library-toolbar__controls {
        justify-content: flex-end;
    }

    .bbai-library-filter-reset {
        display: inline-flex;
        align-items: center;
        min-height: 34px;
        padding: 0 4px;
        border: 0;
        background: transparent;
        color: var(--bbai-link-row-secondary);
        font-size: 12px;
        font-weight: 700;
        text-decoration: underline;
        text-underline-offset: 2px;
        text-decoration-color: rgba(37, 99, 235, 0.35);
        cursor: pointer;
        transition: color 0.14s ease, text-decoration-color 0.14s ease;
    }

    .bbai-library-filter-reset:hover,
    .bbai-library-filter-reset:focus-visible {
        color: #1e3a8a;
        text-decoration-color: rgba(30, 58, 138, 0.65);
        outline: none;
    }

    .bbai-library-container[data-bbai-has-filters="false"] .bbai-library-filter-reset {
        display: none;
    }

    .bbai-library-bulk-toggle[aria-pressed="true"] {
        background: #eef4ff;
        border-color: #c6d7f1;
        color: var(--bbai-library-text);
    }

    .bbai-library-selection-bar {
        top: 76px;
        z-index: 8;
        padding: 12px 14px;
        border: 1px solid #dbe6f2;
        border-radius: 18px;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06);
    }

    .bbai-library-selection-bar__credits {
        display: none;
    }

    .bbai-library-table-shell {
        padding: 0;
        margin: 2px 0 0;
        background: transparent;
        border: none;
        border-radius: 0;
        box-shadow: none;
    }

    .bbai-library-head-bulk {
        display: none;
        align-items: center;
        gap: 8px;
        margin-top: 2px;
        flex: 0 0 auto;
        font-size: 13px;
        font-weight: 600;
        color: var(--bbai-library-text);
        cursor: pointer;
        user-select: none;
    }

    .bbai-library-main[data-bbai-bulk-mode="true"] .bbai-library-head-bulk {
        display: inline-flex;
    }

    .bbai-library-head-bulk__label {
        line-height: 1.2;
    }

    .bbai-library-table-perfect-hint {
        margin: 6px 0 0;
        font-size: 13px;
        line-height: 1.45;
        color: var(--bbai-library-subtle);
        max-width: min(72ch, 100%);
    }

    .bbai-library-review-queue {
        display: flex;
        flex-direction: column;
        gap: 0;
        margin: 0;
        padding: 0;
        background: transparent;
        border: none;
        border-top: 1px solid #f1f5f9;
    }

    .bbai-library-review-card {
        --bbai-row-rail-current: transparent;
        --bbai-row-rail-hover: transparent;
        position: relative;
        display: grid;
        grid-template-columns: 60px minmax(0, 1fr);
        align-items: center;
        gap: var(--bbai-table-row-gap-y, var(--bbai-admin-table-row-gap-y)) var(--bbai-table-row-gap-x, var(--bbai-admin-table-row-gap-x));
        padding: var(--bbai-table-row-pad-y, var(--bbai-admin-table-row-pad-y)) var(--bbai-table-row-pad-x, var(--bbai-admin-table-row-pad-x)) var(--bbai-table-row-pad-y, var(--bbai-admin-table-row-pad-y)) calc(var(--bbai-row-rail-w) + var(--bbai-row-content-inset));
        margin: 0;
        background: #ffffff;
        border: none;
        border-bottom: 1px solid #f1f5f9;
        border-radius: 0;
        box-shadow: none;
        min-height: 0;
        box-sizing: border-box;
        isolation: isolate;
        transition:
            background-color 0.16s ease,
            border-color 0.16s ease;
    }

    /* Structural left rail — flush to row edge, full height */
    .bbai-library-review-card[data-status]::after {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: var(--bbai-row-rail-w);
        border-radius: 0 var(--bbai-row-rail-radius) var(--bbai-row-rail-radius) 0;
        background: var(--bbai-row-rail-current);
        pointer-events: none;
        z-index: 0;
        transition: background-color 0.16s ease, opacity 0.16s ease;
    }

    .bbai-library-review-card[data-status] > * {
        position: relative;
        z-index: 1;
    }

    .bbai-library-review-card[data-status='missing'] {
        --bbai-row-rail-current: var(--bbai-row-rail-missing);
        --bbai-row-rail-hover: var(--bbai-row-rail-missing-strong);
        background: var(--bbai-status-missing-row-bg);
    }

    .bbai-library-review-card[data-status='weak'] {
        --bbai-row-rail-current: var(--bbai-row-rail-weak);
        --bbai-row-rail-hover: var(--bbai-row-rail-weak-strong);
        background: var(--bbai-status-needs-review-row-bg);
    }

    .bbai-library-review-card[data-status='optimized'] {
        --bbai-row-rail-current: var(--bbai-row-rail-opt);
        --bbai-row-rail-hover: var(--bbai-row-rail-opt-strong);
        background: var(--bbai-status-optimized-row-bg);
    }

    .bbai-library-review-card:last-child {
        border-bottom: none;
    }

    .bbai-library-review-card:hover,
    .bbai-library-review-card:focus-within {
        background: #fafbfc;
    }

    .bbai-library-review-card[data-status='missing']:hover,
    .bbai-library-review-card[data-status='missing']:focus-within {
        background: #f8fafc;
    }

    .bbai-library-review-card[data-status='missing']:hover::after,
    .bbai-library-review-card[data-status='missing']:focus-within::after {
        background: var(--bbai-row-rail-hover);
    }

    .bbai-library-review-card[data-status='weak']:hover,
    .bbai-library-review-card[data-status='weak']:focus-within {
        background: rgba(254, 243, 199, 0.52);
    }

    .bbai-library-review-card[data-status='weak']:hover::after,
    .bbai-library-review-card[data-status='weak']:focus-within::after {
        background: var(--bbai-row-rail-hover);
    }

    .bbai-library-review-card[data-status='optimized']:hover,
    .bbai-library-review-card[data-status='optimized']:focus-within {
        background: rgba(220, 252, 231, 0.52);
    }

    .bbai-library-review-card[data-status='optimized']:hover::after,
    .bbai-library-review-card[data-status='optimized']:focus-within::after {
        background: var(--bbai-row-rail-hover);
    }

    .bbai-library-review-card.bbai-library-row--editing {
        background: rgba(239, 246, 255, 0.72);
        border-bottom-color: #dbeafe;
    }

    .bbai-library-review-card.bbai-library-row--editing::after {
        background: rgba(59, 130, 246, 0.55);
        border-radius: 0 var(--bbai-row-rail-radius) var(--bbai-row-rail-radius) 0;
    }

    .bbai-library-review-card.bbai-library-row--editing:hover::after,
    .bbai-library-review-card.bbai-library-row--editing:focus-within::after {
        background: rgba(37, 99, 235, 0.72);
    }

    .bbai-library-review-card.bbai-library-row--saved-flash {
        animation: bbai-library-row-saved 0.85s ease;
    }

    @keyframes bbai-library-row-saved {
        0% {
            background-color: rgba(220, 252, 231, 0.78);
        }
        100% {
            background-color: transparent;
        }
    }

    .bbai-library-inline-alt {
        display: flex;
        flex-direction: column;
        gap: 8px;
        width: 100%;
        min-width: 0;
    }

    .bbai-library-inline-alt__textarea {
        width: 100%;
        min-height: 48px;
        max-height: 120px;
        margin: 0;
        padding: 8px 11px;
        border: 1px solid #c7d5e8;
        border-radius: 10px;
        background: #ffffff;
        font-size: 14px;
        line-height: 1.45;
        color: var(--bbai-library-text);
        resize: vertical;
        transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
        box-sizing: border-box;
    }

    .bbai-library-inline-alt__textarea:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2), 0 4px 12px rgba(15, 23, 42, 0.06);
        background: #ffffff;
    }

    .bbai-library-inline-alt__toolbar {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    .bbai-library-inline-alt__toolbar .bbai-btn {
        min-height: 34px;
    }

    .bbai-library-inline-alt__saved {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 12px;
        font-weight: 600;
        color: #15803d;
        opacity: 0;
        transition: opacity 0.2s ease;
    }

    .bbai-library-inline-alt__saved.is-visible {
        opacity: 1;
    }

    .bbai-library-inline-alt__error {
        margin: 0;
        font-size: 12px;
        color: #b91c1c;
    }

    .bbai-library-main[data-bbai-bulk-mode="true"] .bbai-library-review-card {
        grid-template-columns: auto 60px minmax(0, 1fr);
    }

    .bbai-library-card__select {
        display: none;
        align-items: center;
        padding-top: 0;
    }

    .bbai-library-main[data-bbai-bulk-mode="true"] .bbai-library-card__select {
        display: flex;
    }

    .bbai-library-main[data-bbai-bulk-mode="true"] .bbai-library-review-card.is-selected {
        background: rgba(239, 246, 255, 0.72);
        border-bottom-color: #dbeafe;
    }

    .bbai-library-main[data-bbai-bulk-mode="true"] .bbai-library-review-card.is-selected::after {
        background: rgba(37, 99, 235, 0.58);
    }

    .bbai-library-main[data-bbai-bulk-mode="true"] .bbai-library-review-card.is-selected:hover::after,
    .bbai-library-main[data-bbai-bulk-mode="true"] .bbai-library-review-card.is-selected:focus-within::after {
        background: rgba(29, 78, 216, 0.72);
    }

    .bbai-library-review-card .bbai-library-thumbnail,
    .bbai-library-review-card .bbai-library-thumbnail-placeholder {
        width: 52px;
        height: 52px;
        border-radius: 8px;
    }

    .bbai-library-card__thumb {
        display: flex;
        align-items: center;
        justify-content: center;
        padding-top: 0;
    }

    /* Main cell: full width of row track (do not inherit legacy table .bbai-library-cell--alt-text 42% width). */
    .bbai-library-review-card .bbai-library-cell--alt-text.bbai-library-card__main {
        width: 100%;
        max-width: none;
        min-width: 0;
    }

    .bbai-library-card__main {
        min-width: 0;
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .bbai-library-card__col--meta {
        min-width: 0;
    }

    .bbai-library-card__col--status {
        min-width: 0;
    }

    .bbai-library-status-tags {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
    }

    .bbai-library-card__review-body {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 6px;
        min-width: 0;
        width: 100%;
    }

    .bbai-library-card__review-body .bbai-library-alt-slot {
        flex: 0 1 auto;
        min-width: 0;
        width: 100%;
    }

    .bbai-library-card__review-body .bbai-library-card__action-cluster {
        flex: 0 0 auto;
        align-self: stretch;
    }

    .bbai-library-card__tool-row,
    .bbai-library-card__action-cluster {
        display: flex;
        align-items: stretch;
        justify-content: flex-start;
        gap: var(--bbai-lib-act-gap);
        flex-wrap: wrap;
    }

    .bbai-library-card__tool-row.bbai-row-actions {
        flex-direction: column;
        flex-wrap: nowrap;
        align-items: stretch;
        gap: 6px;
    }

    .bbai-library-card__tool-row.bbai-row-actions .bbai-library-card__quick-actions {
        flex-direction: column;
        width: 100%;
        gap: 6px;
    }

    .bbai-library-card__extra-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
        width: 100%;
    }

    .bbai-library-card__action-cluster {
        margin-top: 0;
        width: 100%;
        min-width: 0;
    }

    .bbai-library-card__quick-actions {
        display: flex;
        align-items: stretch;
        flex-wrap: wrap;
        gap: var(--bbai-lib-act-gap);
        opacity: 0.92;
        transition: opacity 0.18s ease;
    }

    .bbai-library-review-card:hover .bbai-library-card__quick-actions,
    .bbai-library-review-card:focus-within .bbai-library-card__quick-actions {
        opacity: 1;
    }

    @media (hover: none) {
        .bbai-library-card__quick-actions {
            opacity: 1;
        }
    }

    /* Shared metrics: every row action control uses the same box model */
    .bbai-library-card__quick-action {
        box-sizing: border-box;
        margin: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: var(--bbai-lib-act-h);
        height: var(--bbai-lib-act-h);
        padding: 0 var(--bbai-lib-act-pad-x);
        border-radius: var(--bbai-lib-act-radius);
        font-size: var(--bbai-lib-act-fs);
        font-weight: var(--bbai-lib-act-fw);
        line-height: 1.2;
        cursor: pointer;
        border: 1px solid transparent;
        text-decoration: none;
        -webkit-appearance: none;
        appearance: none;
        transition: box-shadow 0.15s ease, background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease, filter 0.15s ease;
    }

    /* Primary — Generate / Regenerate on missing & needs-review rows */
    .bbai-library-workspace button.bbai-library-card__quick-action.bbai-library-card__quick-action--primary:not(:disabled):not(.bbai-is-locked) {
        background: var(--bbai-cta-primary-bg, #10b981) !important;
        background-color: var(--bbai-cta-primary-bg, #10b981) !important;
        border-color: var(--bbai-cta-primary-bg-active, #047857) !important;
        color: #ffffff !important;
        font-weight: var(--bbai-lib-act-fw);
        box-shadow: 0 1px 4px rgba(16, 185, 129, 0.28) !important;
        opacity: 1 !important;
        cursor: pointer !important;
        pointer-events: auto !important;
        filter: none;
    }

    .bbai-library-workspace button.bbai-library-card__quick-action.bbai-library-card__quick-action--primary:hover:not(:disabled):not(.bbai-is-locked),
    .bbai-library-workspace button.bbai-library-card__quick-action.bbai-library-card__quick-action--primary:focus-visible:not(.bbai-is-locked) {
        background: var(--bbai-cta-primary-bg-hover, #059669) !important;
        background-color: var(--bbai-cta-primary-bg-hover, #059669) !important;
        border-color: var(--bbai-cta-primary-bg-active, #047857) !important;
        color: #ffffff !important;
        box-shadow: 0 2px 10px rgba(16, 185, 129, 0.32) !important;
        filter: brightness(0.98);
        outline: none;
    }

    .bbai-library-workspace button.bbai-library-card__quick-action.bbai-library-card__quick-action--primary:focus-visible:not(.bbai-is-locked) {
        box-shadow: 0 0 0 2px #ffffff, 0 0 0 4px rgba(16, 185, 129, 0.4) !important;
    }

    /* Secondary — Edit manually (outlined) */
    .bbai-library-workspace button.bbai-library-card__quick-action.bbai-library-card__quick-action--secondary:not(:disabled):not(.bbai-is-locked) {
        background: var(--bbai-lib-act-surface) !important;
        border: 1px solid var(--bbai-lib-act-border) !important;
        color: var(--bbai-lib-act-text) !important;
        font-weight: var(--bbai-lib-act-fw);
        box-shadow: none !important;
        opacity: 1 !important;
    }

    .bbai-library-workspace button.bbai-library-card__quick-action.bbai-library-card__quick-action--secondary:hover:not(:disabled):not(.bbai-is-locked),
    .bbai-library-workspace button.bbai-library-card__quick-action.bbai-library-card__quick-action--secondary:focus-visible:not(.bbai-is-locked) {
        background: var(--bbai-lib-act-surface-hover) !important;
        border-color: var(--bbai-lib-act-border-strong) !important;
        color: #0f172a !important;
        outline: none;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06) !important;
    }

    .bbai-library-workspace button.bbai-library-card__quick-action.bbai-library-card__quick-action--secondary:focus-visible:not(.bbai-is-locked) {
        box-shadow: 0 0 0 2px #ffffff, 0 0 0 4px rgba(100, 116, 139, 0.35) !important;
    }

    /* Quiet secondary — Regenerate on optimized rows (same shape, lower urgency than primary pipeline) */
    .bbai-library-workspace button.bbai-library-card__quick-action.bbai-library-card__quick-action--quiet:not(:disabled):not(.bbai-is-locked) {
        background: var(--bbai-lib-act-surface) !important;
        border: 1px solid #e2e8f0 !important;
        color: var(--bbai-lib-act-text-muted) !important;
        font-weight: var(--bbai-lib-act-fw);
        box-shadow: none !important;
        opacity: 1 !important;
    }

    .bbai-library-workspace button.bbai-library-card__quick-action.bbai-library-card__quick-action--quiet:hover:not(:disabled):not(.bbai-is-locked),
    .bbai-library-workspace button.bbai-library-card__quick-action.bbai-library-card__quick-action--quiet:focus-visible:not(.bbai-is-locked) {
        background: var(--bbai-lib-act-surface-hover) !important;
        border-color: var(--bbai-lib-act-border) !important;
        color: #475569 !important;
        outline: none;
    }

    .bbai-library-workspace button.bbai-library-card__quick-action.bbai-library-card__quick-action--quiet:focus-visible:not(.bbai-is-locked) {
        box-shadow: 0 0 0 2px #ffffff, 0 0 0 4px rgba(148, 163, 184, 0.35) !important;
    }

    /* Native disabled — not confused with primary */
    .bbai-library-workspace button.bbai-library-card__quick-action:disabled {
        background: #e5e7eb !important;
        border-color: #d1d5db !important;
        color: #6b7280 !important;
        box-shadow: none !important;
        opacity: 0.85 !important;
        cursor: not-allowed !important;
        pointer-events: none !important;
        filter: none !important;
    }

    .bbai-library-workspace button.bbai-library-card__quick-action.bbai-library-card__quick-action--primary:disabled {
        background: #e5e7eb !important;
        color: #6b7280 !important;
        border-color: #d1d5db !important;
    }

    /* Legacy class aliases (older rows / cached markup) */
    .bbai-library-workspace button.bbai-library-card__quick-action.bbai-library-card__quick-action--lead:not(:disabled):not(.bbai-is-locked) {
        background: var(--bbai-lib-act-surface) !important;
        border: 1px solid var(--bbai-lib-act-border) !important;
        color: var(--bbai-lib-act-text) !important;
        font-weight: var(--bbai-lib-act-fw);
        box-shadow: none !important;
    }

    .bbai-library-workspace button.bbai-library-card__quick-action.bbai-library-card__quick-action--lead:hover:not(:disabled):not(.bbai-is-locked),
    .bbai-library-workspace button.bbai-library-card__quick-action.bbai-library-card__quick-action--lead:focus-visible:not(.bbai-is-locked) {
        background: var(--bbai-lib-act-surface-hover) !important;
        border-color: var(--bbai-lib-act-border-strong) !important;
        color: #0f172a !important;
        outline: none;
    }

    .bbai-library-workspace button.bbai-library-card__quick-action.bbai-library-card__quick-action--ghost:not(:disabled):not(.bbai-is-locked) {
        background: var(--bbai-lib-act-surface) !important;
        border: 1px solid #e2e8f0 !important;
        color: var(--bbai-lib-act-text-muted) !important;
        font-weight: var(--bbai-lib-act-fw);
        box-shadow: none !important;
    }

    .bbai-library-workspace button.bbai-library-card__quick-action.bbai-library-card__quick-action--ghost:hover:not(:disabled):not(.bbai-is-locked),
    .bbai-library-workspace button.bbai-library-card__quick-action.bbai-library-card__quick-action--ghost:focus-visible:not(.bbai-is-locked) {
        background: var(--bbai-lib-act-surface-hover) !important;
        border-color: var(--bbai-lib-act-border) !important;
        color: #475569 !important;
        outline: none;
    }

    /* Locked / credits — muted non-primary; still clickable for upgrade (delegated click) */
    .bbai-library-workspace button.bbai-library-card__quick-action.bbai-is-locked {
        opacity: 1 !important;
        cursor: pointer !important;
        pointer-events: auto !important;
        transform: none;
        background: #e8edf3 !important;
        border: 1px solid #cbd5e1 !important;
        color: #64748b !important;
        box-shadow: none !important;
        filter: none !important;
    }

    .bbai-library-workspace button.bbai-library-card__quick-action.bbai-is-locked:hover,
    .bbai-library-workspace button.bbai-library-card__quick-action.bbai-is-locked:focus-visible {
        background: #dce3ec !important;
        border-color: #94a3b8 !important;
        color: #475569 !important;
        outline: none;
        box-shadow: none !important;
    }

    .bbai-library-card__meta-wrap {
        min-width: 0;
        display: grid;
        gap: 2px;
        flex: 1;
    }

    .bbai-library-card__filename {
        margin: 0;
        font-size: 12px;
        line-height: 1.35;
        font-weight: 700;
        color: var(--bbai-library-text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .bbai-library-card__meta {
        margin: 0;
        font-size: 10px;
        line-height: 1.4;
        letter-spacing: 0.02em;
        color: rgba(100, 116, 139, 0.88);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .bbai-library-workspace .bbai-library-card__extra-actions .bbai-library-card__quick-action--ghost.bbai-is-locked {
        opacity: 1 !important;
        cursor: pointer !important;
        pointer-events: auto !important;
        background: #fffbeb !important;
        border-color: #fde68a !important;
        color: #b45309 !important;
        box-shadow: none !important;
    }

    .bbai-library-alt-preview-card--queue {
        box-sizing: border-box;
        width: 100%;
        min-width: 0;
        max-width: 100%;
        min-height: 48px;
        max-height: 5.5rem;
        padding: 8px 10px;
        border-radius: 10px;
        background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        border: 1px solid #c7d2e4;
        cursor: pointer;
        transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
        overflow: hidden;
    }

    .bbai-library-alt-preview-card--queue.is-expanded,
    .bbai-library-review-card.bbai-library-row--editing .bbai-library-alt-preview-card--queue {
        max-height: none;
        overflow: visible;
    }

    .bbai-library-alt-preview-card--queue:hover {
        background: linear-gradient(180deg, #f1f5f9 0%, #e8edf4 100%);
        border-color: #94a3b8;
        box-shadow: 0 1px 4px rgba(15, 23, 42, 0.06);
    }

    .bbai-library-alt-preview-card--queue:focus-within {
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2), 0 2px 10px rgba(15, 23, 42, 0.06);
        background: #f8fafc;
    }

    .bbai-library-alt-preview-card--queue .bbai-alt-text-preview {
        font-size: 14px;
        line-height: 1.45;
        -webkit-line-clamp: 2;
    }

    .bbai-library-alt-preview-card--queue.is-expanded .bbai-alt-text-preview {
        -webkit-line-clamp: unset;
        max-height: none;
    }

    .bbai-library-alt-preview-card--queue .bbai-alt-text-missing {
        font-size: 13px;
        line-height: 1.5;
        font-style: italic;
        color: #475569;
    }

    .bbai-library-filter-empty {
        display: block;
    }

    .bbai-library-filter-empty[hidden] {
        display: none !important;
    }

    .bbai-library-filter-empty__cell {
        display: block;
        padding: 0;
        background: transparent;
        border: 0;
    }

    @media (max-width: 1100px) {
        .bbai-library-toolbar__controls {
            justify-content: flex-start;
        }

        .bbai-library-review-card {
            grid-template-columns: 60px minmax(0, 1fr);
        }

        .bbai-library-main[data-bbai-bulk-mode="true"] .bbai-library-review-card {
            grid-template-columns: auto 60px minmax(0, 1fr);
        }

        .bbai-library-workspace-filter-strip {
            padding-top: 12px;
            padding-bottom: 14px;
        }

        .bbai-library-workspace-intro {
            padding-top: 16px;
        }
    }

    @media (max-width: 720px) {
        .bbai-library-selection-bar {
            top: 64px;
        }

        .bbai-library-review-card,
        .bbai-library-main[data-bbai-bulk-mode="true"] .bbai-library-review-card {
            grid-template-columns: 1fr;
        }

        .bbai-library-card__select {
            order: -1;
            padding-top: 0;
        }

        .bbai-library-card__thumb {
            justify-content: flex-start;
        }

    }

    /* =====================================================
       ALT EDIT MODAL
       ===================================================== */
    .bbai-library-edit-modal {
        position: fixed;
        inset: 0;
        z-index: 100000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: opacity 0.22s ease, visibility 0.22s ease;
    }

    .bbai-library-edit-modal.is-visible {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
    }

    .bbai-library-edit-modal__backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.44);
        backdrop-filter: blur(10px);
    }

    .bbai-library-edit-modal__dialog {
        position: relative;
        width: min(1040px, calc(100vw - 32px));
        max-height: min(820px, calc(100vh - 32px));
        overflow: auto;
        background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
        border: 1px solid rgba(219, 230, 242, 0.92);
        border-radius: 28px;
        box-shadow: 0 36px 90px rgba(15, 23, 42, 0.22);
        padding: 28px;
    }

    .bbai-library-edit-modal__close {
        position: absolute;
        top: 18px;
        right: 18px;
        width: 38px;
        height: 38px;
        border-radius: 999px;
        border: 1px solid #dbe6f2;
        background: rgba(248, 251, 255, 0.92);
        color: var(--bbai-library-text);
        font-size: 22px;
        line-height: 1;
        cursor: pointer;
        transition: background-color 0.16s ease, border-color 0.16s ease, transform 0.16s ease;
    }

    .bbai-library-edit-modal__close:hover,
    .bbai-library-edit-modal__close:focus-visible {
        background: #ffffff;
        border-color: #c7d7ea;
        transform: translateY(-1px);
    }

    .bbai-library-edit-modal__layout {
        display: grid;
        grid-template-columns: minmax(260px, 340px) minmax(0, 1fr);
        gap: 28px;
        align-items: start;
    }

    .bbai-library-edit-modal__media {
        display: grid;
        gap: 14px;
        padding: 14px;
        border-radius: 22px;
        background: #f7fafe;
        border: 1px solid #e3ebf5;
    }

    .bbai-library-edit-modal__preview-wrap {
        aspect-ratio: 1 / 1;
        border-radius: 18px;
        overflow: hidden;
        background: #eef4fb;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .bbai-library-edit-modal__preview {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .bbai-library-edit-modal__preview-fallback {
        display: grid;
        place-items: center;
        color: #8ca0b8;
        width: 100%;
        height: 100%;
    }

    .bbai-library-edit-modal__meta {
        display: grid;
        gap: 10px;
    }

    .bbai-library-edit-modal__meta-row {
        display: grid;
        gap: 4px;
        padding: 10px 12px;
        border-radius: 14px;
        background: #ffffff;
        border: 1px solid #e7eef7;
    }

    .bbai-library-edit-modal__meta-label {
        font-size: 11px;
        line-height: 1.2;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--bbai-library-subtle);
    }

    .bbai-library-edit-modal__meta-value {
        font-size: 13px;
        line-height: 1.5;
        color: var(--bbai-library-text);
        word-break: break-word;
    }

    .bbai-library-edit-modal__panel {
        display: grid;
        gap: var(--section-spacing, 24px);
        min-width: 0;
    }

    .bbai-library-edit-modal__header {
        display: grid;
        gap: 6px;
        padding-right: 42px;
    }

    .bbai-library-edit-modal__title {
        margin: 0;
        font-size: 28px;
        line-height: 1.05;
        letter-spacing: -0.04em;
        color: var(--bbai-library-text);
    }

    .bbai-library-edit-modal__subtitle {
        margin: 0;
        font-size: 14px;
        line-height: 1.6;
        color: var(--bbai-library-muted);
    }

    .bbai-library-edit-modal__stack {
        display: grid;
        gap: 10px;
    }

    .bbai-library-edit-modal__label {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        line-height: 1.2;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--bbai-library-subtle);
    }

    .bbai-library-edit-modal__label-badge {
        display: inline-flex;
        align-items: center;
        min-height: 20px;
        padding: 0 8px;
        border-radius: 999px;
        background: #eef4ff;
        color: #425c8c;
        font-size: 10px;
        line-height: 1;
        font-weight: 700;
        letter-spacing: 0.08em;
    }

    .bbai-library-edit-modal__suggestion {
        padding: 16px 18px;
        border-radius: 18px;
        background: #f7fafe;
        border: 1px solid #dfe8f4;
        color: var(--bbai-library-text);
        font-size: 14px;
        line-height: 1.7;
        min-height: 78px;
        white-space: pre-wrap;
    }

    .bbai-library-edit-modal__suggestion--empty {
        color: var(--bbai-library-muted);
    }

    .bbai-library-edit-modal__textarea {
        width: 100%;
        min-height: 150px;
        padding: 16px 18px;
        border-radius: 18px;
        border: 1px solid #c7d8ec;
        background: #ffffff;
        box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.03), 0 0 0 0 rgba(96, 165, 250, 0);
        font-size: 15px;
        line-height: 1.7;
        color: var(--bbai-library-text);
        resize: vertical;
        transition: border-color 0.16s ease, box-shadow 0.16s ease, background 0.16s ease;
    }

    .bbai-library-edit-modal__textarea:focus {
        outline: none;
        border-color: #60a5fa;
        box-shadow: 0 0 0 4px rgba(96, 165, 250, 0.12);
        background: #ffffff;
    }

    .bbai-library-edit-modal__toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--card-gap, 16px);
        flex-wrap: wrap;
    }

    .bbai-library-edit-modal__ai-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .bbai-library-edit-modal__ai-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 34px;
        padding: 0 12px;
        border-radius: 999px;
        border: 1px solid #d6e1ee;
        background: #f9fbfe;
        color: var(--bbai-library-text);
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        transition: background-color 0.16s ease, border-color 0.16s ease, transform 0.16s ease, color 0.16s ease;
    }

    .bbai-library-edit-modal__ai-btn:hover,
    .bbai-library-edit-modal__ai-btn:focus-visible {
        background: #eef5fc;
        border-color: #c3d5e9;
        transform: translateY(-1px);
    }

    .bbai-library-edit-modal__ai-btn.is-loading {
        pointer-events: none;
        color: #547293;
    }

    .bbai-library-edit-modal__quality {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        border-radius: 14px;
        background: #f7fafc;
        border: 1px solid #e2e8f0;
    }

    .bbai-library-edit-modal__quality-label {
        font-size: 12px;
        line-height: 1.3;
        color: var(--bbai-library-muted);
    }

    .bbai-library-edit-modal__quality-value {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
        padding: 0 10px;
        border-radius: 999px;
        font-size: 12px;
        line-height: 1;
        font-weight: 700;
    }

    .bbai-library-edit-modal__quality-value--high {
        background: rgba(22, 163, 74, 0.10);
        color: #166534;
    }

    .bbai-library-edit-modal__quality-value--good {
        background: rgba(59, 130, 246, 0.10);
        color: #1d4ed8;
    }

    .bbai-library-edit-modal__quality-value--needs-work {
        background: rgba(245, 158, 11, 0.14);
        color: #92400e;
    }

    .bbai-library-edit-modal__quality-copy {
        font-size: 13px;
        line-height: 1.5;
        color: var(--bbai-library-muted);
    }

    .bbai-library-edit-modal__automation {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        flex-wrap: wrap;
        padding: 14px 16px;
        border-radius: 18px;
        background: linear-gradient(180deg, #faf7ff 0%, #f6faff 100%);
        border: 1px solid #e6def7;
    }

    .bbai-library-edit-modal__automation-copy {
        display: grid;
        gap: 4px;
        min-width: 0;
    }

    .bbai-library-edit-modal__automation-title {
        margin: 0;
        font-size: 14px;
        line-height: 1.4;
        font-weight: 700;
        color: var(--bbai-library-text);
    }

    .bbai-library-edit-modal__automation-text {
        margin: 0;
        font-size: 13px;
        line-height: 1.5;
        color: var(--bbai-library-muted);
    }

    .bbai-library-edit-modal__footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        flex-wrap: wrap;
    }

    .bbai-library-edit-modal__hint,
    .bbai-library-edit-modal__error {
        margin: 0;
        font-size: 12px;
        line-height: 1.5;
    }

    .bbai-library-edit-modal__hint {
        color: var(--bbai-library-subtle);
    }

    .bbai-library-edit-modal__error {
        color: var(--bbai-library-red);
        min-height: 18px;
    }

    .bbai-library-edit-modal__actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    @media (max-width: 900px) {
        .bbai-library-edit-modal__layout {
            grid-template-columns: 1fr;
        }

        .bbai-library-edit-modal__media {
            grid-template-columns: 120px minmax(0, 1fr);
            align-items: start;
        }

        .bbai-library-edit-modal__preview-wrap {
            aspect-ratio: 1 / 1;
        }
    }

    @media (max-width: 640px) {
        .bbai-library-edit-modal {
            padding: 12px;
        }

        .bbai-library-edit-modal__dialog {
            width: min(100vw - 8px, 100%);
            max-height: calc(100vh - 16px);
            padding: 20px 16px 18px;
            border-radius: 22px;
        }

        .bbai-library-edit-modal__media {
            grid-template-columns: 1fr;
        }

        .bbai-library-edit-modal__footer {
            align-items: stretch;
        }

        .bbai-library-edit-modal__actions {
            width: 100%;
        }

        .bbai-library-edit-modal__actions .bbai-btn {
            flex: 1 1 auto;
            justify-content: center;
        }
    }
    </style>

    <?php
    /* Queue workflow suppressed — replaced by new premium top-card layout below. */
    $bbai_queue_workflow_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/queue-workflow.php';
    ?>
    <style>.bbai-library-workspace .bbai-queue-workflow { display: none !important; }</style>

    <!-- ── Library surface: shared banner + compact summary (scan JS: data-bbai-coverage-card) ── -->
    <div
        id="bbai-alt-coverage-card"
        class="bbai-library-surface-root bbai-top-section-stack"
        data-bbai-coverage-card="1"
        data-bbai-library-surface="1"
        data-state="<?php echo esc_attr($bbai_surface_state); ?>"
        data-bbai-free-plan-limit="<?php echo esc_attr((int) (($bbai_coverage ?? [])['free_plan_limit'] ?? 50)); ?>"
    >
        <?php bbai_ui_render('bbai-banner', [ 'command_hero' => $bbai_command_hero ]); ?>

        <div class="bbai-sr-only" aria-hidden="true">
            <span data-bbai-library-optimized><?php echo esc_html(number_format_i18n($bbai_optimized_count)); ?></span>
            <span data-bbai-library-weak><?php echo esc_html(number_format_i18n($bbai_weak_count)); ?></span>
            <span data-bbai-library-missing><?php echo esc_html(number_format_i18n($bbai_missing_count)); ?></span>
            <span data-bbai-coverage-total><?php echo esc_html(number_format_i18n($bbai_library_all_count)); ?></span>
            <p data-bbai-library-progress-label>
                <?php echo esc_html($bbai_cov_opt_pct >= 100 ? __('Fully optimized', 'beepbeep-ai-alt-text-generator') : sprintf(__('%s%% optimized', 'beepbeep-ai-alt-text-generator'), number_format_i18n($bbai_cov_opt_pct))); ?>
            </p>
            <span data-bbai-coverage-score-inline><?php echo esc_html(sprintf(__('%s%%', 'beepbeep-ai-alt-text-generator'), number_format_i18n($bbai_cov_opt_pct))); ?></span>
            <div>
                <span data-bbai-library-progress-optimized style="flex-basis: <?php echo esc_attr($bbai_cov_opt_pct); ?>%;"></span>
                <span data-bbai-library-progress-weak style="flex-basis: <?php echo esc_attr($bbai_cov_review_pct); ?>%;"></span>
                <span data-bbai-library-progress-missing style="flex-basis: <?php echo esc_attr($bbai_cov_miss_pct); ?>%;"></span>
                </div>
            <div data-bbai-library-core-usage>
                <p data-bbai-library-usage-line><strong><?php echo esc_html($bbai_usage_line); ?></strong></p>
                <div role="progressbar" data-bbai-library-usage-progressbar aria-valuenow="<?php echo esc_attr($bbai_usage_pct); ?>" aria-valuemin="0" aria-valuemax="100">
                    <span data-bbai-library-usage-progress style="width: <?php echo esc_attr($bbai_usage_pct); ?>%;"></span>
            </div>
                <p data-bbai-library-usage-copy><?php echo esc_html($bbai_usage_copy); ?></p>
                <span class="bbai-sr-only" data-bbai-library-credits-remaining><?php echo esc_html(number_format_i18n($bbai_credits_remaining)); ?></span>
                    </div>
                </div>
        <div class="bbai-library-credits-banner-host bbai-library-credits-banner-host--strip" data-bbai-library-credits-banner-host="1" hidden aria-hidden="true"></div>
    </div>

    <section class="bbai-library-scan-feedback" data-bbai-scan-feedback-banner data-state="attention" hidden>
        <div class="bbai-library-scan-feedback__copy" role="status" aria-live="polite" aria-atomic="true">
            <p class="bbai-library-scan-feedback__eyebrow"><?php esc_html_e('Scan complete', 'beepbeep-ai-alt-text-generator'); ?></p>
            <p class="bbai-library-scan-feedback__summary" data-bbai-scan-feedback-summary></p>
            <p class="bbai-library-scan-feedback__detail" data-bbai-scan-feedback-detail hidden></p>
        </div>
        <div class="bbai-library-scan-feedback__actions">
            <button type="button" class="bbai-btn bbai-btn-primary bbai-btn-sm" data-action="jump-scan-results" hidden><?php esc_html_e('View results', 'beepbeep-ai-alt-text-generator'); ?></button>
            <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" data-action="dismiss-scan-feedback"><?php esc_html_e('Close', 'beepbeep-ai-alt-text-generator'); ?></button>
        </div>
    </section>

    <?php
    $bbai_ws_pages      = isset($bbai_total_pages) ? max(1, (int) $bbai_total_pages) : 1;
    $bbai_ws_multi_page = $bbai_ws_pages > 1;
    $bbai_ws_meta_main  = '';
    if ($bbai_results_total < 1) {
        $bbai_ws_meta_main = __('No images', 'beepbeep-ai-alt-text-generator');
    } elseif ($bbai_row_count < 1) {
        $bbai_ws_meta_main = sprintf(
            /* translators: %s: total matching images */
            __('0 of %s', 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($bbai_results_total)
        );
    } elseif ($bbai_ws_multi_page) {
        $bbai_ws_meta_main = sprintf(
            /* translators: 1: start rank, 2: end rank, 3: total in current result set */
            __('Showing %1$s–%2$s of %3$s', 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($bbai_show_start),
            number_format_i18n($bbai_show_end),
            number_format_i18n($bbai_results_total)
        );
    } else {
        $bbai_ws_meta_main = sprintf(
            /* translators: %s: formatted image count */
            _n('%s image', '%s images', $bbai_results_total, 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($bbai_results_total)
        );
    }
    ?>
    <div class="bbai-library-workspace-card bbai-card bbai-dashboard-card bbai-command-card bbai-command-card--workspace">
        <header class="bbai-library-workspace-card__head bbai-ui-section-header bbai-section-header bbai-section-header--split-end" aria-labelledby="bbai-library-results-title">
            <div class="bbai-ui-section-header__text">
                <p class="bbai-ui-section-header__eyebrow bbai-command-card__eyebrow bbai-section-label bbai-card-label"><?php esc_html_e('ALT Library', 'beepbeep-ai-alt-text-generator'); ?></p>
                <h2 id="bbai-library-results-title" class="bbai-ui-section-header__title bbai-command-card__title bbai-section-title bbai-card-title" data-bbai-results-heading tabindex="-1"><?php esc_html_e('Review workspace', 'beepbeep-ai-alt-text-generator'); ?></h2>
            </div>
            <p class="bbai-command-card__meta bbai-section-meta" aria-live="polite"><?php echo esc_html($bbai_ws_meta_main); ?></p>
        </header>

        <div class="bbai-library-workspace-card__filter-toolbar">
            <div
                id="bbai-review-filter-tabs"
                class="bbai-alt-review-filters bbai-library-workspace-card__filters"
                role="group"
                aria-label="<?php esc_attr_e('Filter images by status', 'beepbeep-ai-alt-text-generator'); ?>"
                data-bbai-default-filter="<?php echo esc_attr($bbai_default_review_filter); ?>"
            >
                <div class="bbai-alt-review-filters__list">
                    <?php foreach ($bbai_library_workspace_filter_items as $bbai_filt_item) : ?>
                        <?php
                        if (!is_array($bbai_filt_item)) {
                            continue;
                        }
                        $bbai_fk          = isset($bbai_filt_item['key']) ? (string) $bbai_filt_item['key'] : '';
                        $bbai_data_filter = ('weak' === $bbai_fk) ? 'weak' : $bbai_fk;
                        if ('' === $bbai_data_filter) {
                            continue;
                        }
                        $bbai_f_active  = !empty($bbai_filt_item['active']);
                        $bbai_f_problem = !empty($bbai_filt_item['problem']);
                        $bbai_f_count   = isset($bbai_filt_item['count']) ? (int) $bbai_filt_item['count'] : 0;
                        $bbai_f_label   = isset($bbai_filt_item['label']) ? (string) $bbai_filt_item['label'] : '';
                        $bbai_fl_attr   = isset($bbai_filt_item['filter_label_attr']) ? (string) $bbai_filt_item['filter_label_attr'] : $bbai_f_label;
                        $bbai_btn_class = 'bbai-filter-pill bbai-alt-review-filters__btn';
                        if ('all' === $bbai_data_filter) {
                            $bbai_btn_class .= ' bbai-filter-pill--all';
                        } elseif ('missing' === $bbai_data_filter) {
                            $bbai_btn_class .= ' bbai-filter-pill--missing';
                        } elseif ('weak' === $bbai_data_filter) {
                            $bbai_btn_class .= ' bbai-filter-pill--needs-review';
                        } elseif ('optimized' === $bbai_data_filter) {
                            $bbai_btn_class .= ' bbai-filter-pill--optimized';
                        }
                        if ($bbai_f_active) {
                            $bbai_btn_class .= ' bbai-alt-review-filters__btn--active';
                        }
                        if ($bbai_f_problem) {
                            $bbai_btn_class .= ' bbai-alt-review-filters__btn--problem';
                        }
                        ?>
                        <button
                            type="button"
                            class="<?php echo esc_attr($bbai_btn_class); ?>"
                            data-filter="<?php echo esc_attr($bbai_data_filter); ?>"
                            data-bbai-filter-label="<?php echo esc_attr($bbai_fl_attr); ?>"
                            aria-pressed="<?php echo $bbai_f_active ? 'true' : 'false'; ?>"
                            <?php echo $bbai_f_active ? ' aria-current="true"' : ''; ?>
                        >
                            <span class="bbai-alt-review-filters__label"><?php echo esc_html($bbai_f_label); ?></span>
                            <span class="bbai-alt-review-filters__count"><?php echo esc_html(number_format_i18n($bbai_f_count)); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="bbai-library-workspace-card__table-surface">
        <div class="bbai-library-workspace-card__controls-row" id="bbai-library-toolbar" aria-live="polite">
            <label class="bbai-library-search" for="bbai-library-search">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.5"></circle>
                    <path d="M11 11L14 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                </svg>
                <input type="text" id="bbai-library-search" class="bbai-input" placeholder="<?php esc_attr_e('Search ALT text or filenames', 'beepbeep-ai-alt-text-generator'); ?>" />
            </label>
            <label class="bbai-sr-only" for="bbai-library-sort"><?php esc_html_e('Sort images', 'beepbeep-ai-alt-text-generator'); ?></label>
            <select id="bbai-library-sort" class="bbai-library-select bbai-select bbai-input">
                <option value="recently-updated" selected><?php esc_html_e('Recently updated', 'beepbeep-ai-alt-text-generator'); ?></option>
                <option value="score-asc"><?php esc_html_e('Lowest score first', 'beepbeep-ai-alt-text-generator'); ?></option>
                <option value="score-desc"><?php esc_html_e('Highest score first', 'beepbeep-ai-alt-text-generator'); ?></option>
            </select>
            <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm bbai-library-bulk-toggle" id="bbai-library-bulk-toggle" data-action="toggle-library-bulk-mode" aria-pressed="false"><?php esc_html_e('Select multiple', 'beepbeep-ai-alt-text-generator'); ?></button>
        </div>

    <main class="bbai-workspace bbai-library-main" data-bbai-bulk-mode="false">
            <?php if (!empty($bbai_all_images)) : ?>
                <div class="bbai-library-selection-bar" id="bbai-library-selection-bar" aria-live="polite" hidden>
                    <div class="bbai-selection-bar-left">
                        <label class="bbai-selection-all-label" for="bbai-select-all">
                            <input type="checkbox" id="bbai-select-all" class="bbai-checkbox" aria-label="<?php esc_attr_e('Select all images on this page', 'beepbeep-ai-alt-text-generator'); ?>" />
                            <span><?php esc_html_e('Select page', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </label>
                        <span class="bbai-selection-bar-sep" aria-hidden="true">|</span>
                        <span class="bbai-selection-bar-count" data-bbai-selected-count>0 <?php esc_html_e('selected', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <span class="bbai-library-selection-bar__credits" data-bbai-selected-credits></span>
                    </div>
                    <div class="bbai-library-selection-bar__actions">
                        <button type="button" class="bbai-btn bbai-btn-primary bbai-btn-sm<?php echo $bbai_limit_reached_state ? ' bbai-is-locked' : ''; ?>" id="bbai-batch-generate" data-action="generate-selected" data-bbai-lock-preserve-label="1"<?php if ($bbai_limit_reached_state) : ?> data-bbai-action="open-upgrade" data-bbai-locked-cta="1" data-bbai-lock-reason="generate_missing" data-bbai-locked-source="library-selection-bar-generate" aria-disabled="true"<?php endif; ?>><?php echo esc_html(bbai_copy_cta_generate_alt()); ?></button>
                        <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm<?php echo $bbai_limit_reached_state ? ' bbai-is-locked' : ''; ?>" id="bbai-batch-regenerate" data-action="regenerate-selected" data-bbai-lock-preserve-label="1"<?php if ($bbai_limit_reached_state) : ?> data-bbai-action="open-upgrade" data-bbai-locked-cta="1" data-bbai-lock-reason="reoptimize_all" data-bbai-locked-source="library-selection-bar-regenerate" aria-disabled="true"<?php endif; ?>><?php esc_html_e('Regenerate ALT', 'beepbeep-ai-alt-text-generator'); ?></button>
                        <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" id="bbai-batch-reviewed" data-action="mark-reviewed"><?php esc_html_e('Mark as reviewed', 'beepbeep-ai-alt-text-generator'); ?></button>
                        <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" id="bbai-batch-clear-alt" data-action="clear-alt-selected"><?php esc_html_e('Delete ALT', 'beepbeep-ai-alt-text-generator'); ?></button>
                        <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" id="bbai-batch-export" data-action="export-alt-text"><?php esc_html_e('Export', 'beepbeep-ai-alt-text-generator'); ?></button>
                        <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm bbai-selection-clear" id="bbai-batch-clear" data-action="clear-selection"><?php esc_html_e('Clear', 'beepbeep-ai-alt-text-generator'); ?></button>
                    </div>
                </div>

                <section id="bbai-alt-table" class="bbai-library-table-shell bbai-table bbai-table--workspace" data-bbai-scan-results-section aria-labelledby="bbai-library-results-title">
                    <div class="bbai-library-table-head bbai-library-table-head--bulk-only">
                        <label class="bbai-library-head-bulk bbai-library-head-bulk--selectall">
                            <input type="checkbox" class="bbai-checkbox bbai-select-all-table" aria-label="<?php esc_attr_e('Select all images on this page', 'beepbeep-ai-alt-text-generator'); ?>" title="<?php esc_attr_e('Select all on this page', 'beepbeep-ai-alt-text-generator'); ?>" />
                            <span class="bbai-library-head-bulk__label"><?php esc_html_e('Select all on this page', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </label>
                    </div>

                    <div class="bbai-table-wrap bbai-table__scroll">
                        <div id="bbai-library-table-body" class="bbai-library-review-queue bbai-table__body" role="list">
                                <?php foreach ($bbai_all_images as $bbai_row_idx => $bbai_image) : ?>
                                    <?php
                                    $bbai_attachment_id = $bbai_image->ID;
                                    $bbai_rs = $bbai_library_row_states[ $bbai_row_idx ] ?? $this->get_library_workspace_row_state($bbai_image);
                                    $bbai_current_alt = $bbai_image->alt_text ?? '';
                                    $bbai_clean_alt = $bbai_rs['clean_alt'];
                                    $bbai_has_alt = !empty($bbai_rs['has_alt']);

                                    $bbai_thumb_url = wp_get_attachment_image_src($bbai_attachment_id, 'thumbnail');
                                    $bbai_attached_file = get_attached_file($bbai_attachment_id);
                                    $bbai_filename = $bbai_attached_file ? basename($bbai_attached_file) : '';
                                    $bbai_modified_date = date_i18n('j M Y', strtotime($bbai_image->post_modified));
                                    $bbai_created_timestamp = strtotime((string) $bbai_image->post_date);
                                    $bbai_updated_timestamp = strtotime((string) $bbai_image->post_modified);

                                    $bbai_alt_preview = $bbai_clean_alt ?: '';
                                    $bbai_alt_preview_id = 'bbai-alt-preview-' . $bbai_attachment_id;
                                    $bbai_alt_char_count = function_exists('mb_strlen') ? mb_strlen($bbai_alt_preview) : strlen($bbai_alt_preview);
                                    $bbai_has_long_alt_preview = $bbai_alt_char_count > 160;

                                    $bbai_display_filename = !empty($bbai_filename) && is_string($bbai_filename) ? $bbai_filename : __('Unknown file', 'beepbeep-ai-alt-text-generator');
                                    if (is_string($bbai_display_filename) && strlen($bbai_display_filename) > 88) {
                                        $bbai_display_filename = wp_html_excerpt($bbai_display_filename, 85, '...');
                                    }
                                    $bbai_filename_sort = strtolower(!empty($bbai_filename) ? $bbai_filename : $bbai_display_filename);

                                    $bbai_file_extension = '';
                                    $bbai_file_dimensions = '';
                                    $bbai_file_size = '';
                                    $bbai_file_size_bytes = 0;
                                    if ($bbai_attached_file && is_string($bbai_attached_file) && file_exists($bbai_attached_file)) {
                                        if (!empty($bbai_filename) && is_string($bbai_filename)) {
                                            $bbai_path_info = pathinfo($bbai_filename);
                                            $bbai_file_extension = !empty($bbai_path_info['extension']) && is_string($bbai_path_info['extension']) ? strtoupper($bbai_path_info['extension']) : '';
                                        }

                                        $bbai_attachment_meta = wp_get_attachment_metadata($bbai_attachment_id);
                                        if (is_array($bbai_attachment_meta)) {
                                            $bbai_width = isset($bbai_attachment_meta['width']) ? absint($bbai_attachment_meta['width']) : 0;
                                            $bbai_height = isset($bbai_attachment_meta['height']) ? absint($bbai_attachment_meta['height']) : 0;
                                            if ($bbai_width > 0 && $bbai_height > 0) {
                                                $bbai_file_dimensions = $bbai_width . 'x' . $bbai_height;
                                            }
                                        }

                                        $bbai_file_size_bytes = filesize($bbai_attached_file);
                                        if (false !== $bbai_file_size_bytes && is_numeric($bbai_file_size_bytes)) {
                                            $bbai_file_size_bytes = (int) $bbai_file_size_bytes;
                                            $bbai_file_size = size_format($bbai_file_size_bytes);
                                        }
                                    }

                                    $bbai_file_meta_parts = [];
                                    if ($bbai_file_extension) {
                                        $bbai_file_meta_parts[] = $bbai_file_extension;
                                    }
                                    if ($bbai_file_dimensions) {
                                        $bbai_file_meta_parts[] = $bbai_file_dimensions;
                                    }
                                    if ($bbai_file_size) {
                                        $bbai_file_meta_parts[] = $bbai_file_size;
                                    }
                                    $bbai_file_meta = implode(' • ', $bbai_file_meta_parts);

                                    $status = $bbai_rs['status'];
                                    $bbai_status_label = $bbai_rs['status_label'];
                                    $bbai_quality_class = $bbai_rs['quality_class'];
                                    $bbai_quality_label = $bbai_rs['quality_label'];
                                    $bbai_quality_score = (int) $bbai_rs['quality_score'];
                                    $bbai_score_tier = $bbai_rs['score_tier'];
                                    $bbai_score_tier_label = $bbai_rs['score_tier_label'];
                                    $bbai_analysis = $bbai_rs['analysis'];
                                    $bbai_is_user_approved = !empty($bbai_rs['user_approved']);

                                    $bbai_score_hint = '';
                                    if (!$bbai_has_alt) {
                                        $bbai_score_hint = __('Add or generate ALT to score this image.', 'beepbeep-ai-alt-text-generator');
                                    } elseif ('weak' === $bbai_score_tier) {
                                        $bbai_score_hint = __('Low score — regenerating often helps.', 'beepbeep-ai-alt-text-generator');
                                    } elseif ('review' === $bbai_score_tier) {
                                        $bbai_score_hint = __('Consider a quick manual edit for stronger context.', 'beepbeep-ai-alt-text-generator');
                                    }

                                    $bbai_preview_image_url = $bbai_thumb_url && isset($bbai_thumb_url[0]) ? $bbai_thumb_url[0] : '';
                                    $bbai_modal_image_url = wp_get_attachment_image_url($bbai_attachment_id, 'large');
                                    if (empty($bbai_modal_image_url)) {
                                        $bbai_modal_image_url = wp_get_attachment_image_url($bbai_attachment_id, 'full');
                                    }
                                    if (empty($bbai_modal_image_url)) {
                                        $bbai_modal_image_url = $bbai_preview_image_url;
                                    }

                                    $bbai_ai_source_raw = isset($bbai_image->ai_source) ? strtolower(trim((string) $bbai_image->ai_source)) : '';
                                    $bbai_ai_source = in_array($bbai_ai_source_raw, ['ai', 'openai'], true) ? 'ai' : '';
                                    $bbai_quality_tooltip = __('No ALT text detected', 'beepbeep-ai-alt-text-generator');
                                    if ($bbai_has_alt) {
                                        if ($bbai_quality_score >= 85) {
                                            $bbai_quality_tooltip = __('Strong quality — safe to keep or fine-tune.', 'beepbeep-ai-alt-text-generator');
                                        } elseif ($bbai_quality_score >= 70) {
                                            $bbai_quality_tooltip = __('Adequate but could use a sharper edit.', 'beepbeep-ai-alt-text-generator');
                                        } else {
                                            $bbai_quality_tooltip = __('Low confidence — consider regenerating or rewriting.', 'beepbeep-ai-alt-text-generator');
                                        }
                                    }

                                    if (!$bbai_has_alt) {
                                        $bbai_row_summary = __('No ALT yet — generate or add manually.', 'beepbeep-ai-alt-text-generator');
                                    } elseif ('weak' === $status) {
                                        $bbai_row_summary = !empty($bbai_analysis['issues'][0])
                                            ? (string) $bbai_analysis['issues'][0]
                                            : __('This description may be too generic or low quality.', 'beepbeep-ai-alt-text-generator');
                                    } else {
                                        $bbai_row_summary = $bbai_is_user_approved
                                            ? __('Reviewed and approved by you.', 'beepbeep-ai-alt-text-generator')
                                            : __('Looks good, but you can still review or edit it.', 'beepbeep-ai-alt-text-generator');
                                    }

                                    $bbai_row_edit_label = __('Edit manually', 'beepbeep-ai-alt-text-generator');
                                    $bbai_show_review_action = 'weak' === $status && !$bbai_is_user_approved;
                                    $bbai_meta_parts_row = [];
                                    if (!empty($bbai_file_meta)) {
                                        $bbai_meta_parts_row[] = $bbai_file_meta;
                                    }
                                    if ($bbai_modified_date) {
                                        $bbai_meta_parts_row[] = sprintf(__('Updated %s', 'beepbeep-ai-alt-text-generator'), $bbai_modified_date);
                                    }
                                    $bbai_card_meta_line = implode(' • ', $bbai_meta_parts_row);

                                    $bbai_row_state_rank = 'missing' === $status ? 0 : ('weak' === $status ? 1 : 2);

                                    $bbai_regen_label = $bbai_has_alt ? __('Regenerate', 'beepbeep-ai-alt-text-generator') : bbai_copy_cta_generate_missing_images();
                                    $bbai_regen_title = $bbai_limit_reached_state
                                        ? __('Upgrade to unlock AI regenerations', 'beepbeep-ai-alt-text-generator')
                                        : (!$bbai_has_alt
                                            ? bbai_copy_cta_generate_missing_images()
                                            : __('Regenerate ALT text with AI', 'beepbeep-ai-alt-text-generator'));
                                    $bbai_edit_title = __('Edit ALT text manually', 'beepbeep-ai-alt-text-generator');

                                    /* Primary = Generate/Regenerate; secondary = Edit (consistent hierarchy). */
                                    $bbai_q1_class = 'bbai-library-card__quick-action--primary';
                                    $bbai_q1_action = 'regenerate-single';
                                    $bbai_q1_label = $bbai_regen_label;
                                    $bbai_q1_title = $bbai_regen_title;
                                    $bbai_q2_class = 'bbai-library-card__quick-action--secondary';
                                    $bbai_q2_action = 'edit-alt-inline';
                                    $bbai_q2_label = $bbai_row_edit_label;
                                    $bbai_q2_title = $bbai_edit_title;

                                    $bbai_row_extra_copy    = $bbai_has_alt;
                                    $bbai_row_extra_improve = ('weak' === $status && $bbai_has_alt);
                                    $bbai_row_has_extras    = $bbai_row_extra_copy || $bbai_row_extra_improve || $bbai_show_review_action;
                                    ?>
                                    <article class="bbai-library-row bbai-library-review-card bbai-row"
                                        data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"
                                        data-id="<?php echo esc_attr($bbai_attachment_id); ?>"
                                        data-status="<?php echo esc_attr($status); ?>"
                                        data-review-state="<?php echo esc_attr($status); ?>"
                                        data-quality="<?php echo esc_attr($bbai_quality_class); ?>"
                                        data-ai-source="<?php echo esc_attr($bbai_ai_source); ?>"
                                        data-alt-missing="<?php echo $bbai_has_alt ? 'false' : 'true'; ?>"
                                        data-approved="<?php echo $bbai_is_user_approved ? 'true' : 'false'; ?>"
                                        data-alt-full="<?php echo esc_attr($bbai_clean_alt); ?>"
                                        data-image-title="<?php echo esc_attr($bbai_image->post_title); ?>"
                                        data-image-url="<?php echo esc_url($bbai_modal_image_url); ?>"
                                        data-file-name="<?php echo esc_attr(!empty($bbai_filename) ? $bbai_filename : $bbai_display_filename); ?>"
                                        data-file-name-sort="<?php echo esc_attr($bbai_filename_sort); ?>"
                                        data-file-meta="<?php echo esc_attr($bbai_file_meta); ?>"
                                        data-file-size-bytes="<?php echo esc_attr((string) $bbai_file_size_bytes); ?>"
                                        data-last-updated="<?php echo esc_attr($bbai_modified_date); ?>"
                                        data-created-ts="<?php echo esc_attr($bbai_created_timestamp ? (string) $bbai_created_timestamp : '0'); ?>"
                                        data-updated-ts="<?php echo esc_attr($bbai_updated_timestamp ? (string) $bbai_updated_timestamp : '0'); ?>"
                                        data-state-rank="<?php echo esc_attr((string) $bbai_row_state_rank); ?>"
                                        data-status-label="<?php echo esc_attr($bbai_status_label); ?>"
                                        data-review-summary="<?php echo esc_attr($bbai_row_summary); ?>"
                                        data-quality-label="<?php echo esc_attr($bbai_quality_label); ?>"
                                        data-quality-class="<?php echo esc_attr($bbai_quality_class); ?>"
                                        data-quality-score="<?php echo esc_attr((string) (int) $bbai_quality_score); ?>"
                                        data-score-tier="<?php echo esc_attr($bbai_score_tier); ?>"
                                        data-quality-tooltip="<?php echo esc_attr($bbai_quality_tooltip); ?>"
                                        role="listitem">
                                        <div class="bbai-library-card__select">
                                            <input type="checkbox" class="bbai-checkbox bbai-library-row-check bbai-image-checkbox" value="<?php echo esc_attr($bbai_attachment_id); ?>" data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>" aria-label="<?php printf(esc_attr__('Select image %s', 'beepbeep-ai-alt-text-generator'), esc_attr($bbai_image->post_title)); ?>" />
                                        </div>
                                        <div class="bbai-library-card__thumb bbai-row__media">
                                            <?php if ($bbai_thumb_url) : ?>
                                                <button type="button" class="bbai-library-thumbnail-button" data-action="preview-image" data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>" aria-label="<?php esc_attr_e('Preview image', 'beepbeep-ai-alt-text-generator'); ?>">
                                                    <img src="<?php echo esc_url($bbai_thumb_url[0]); ?>" alt="" class="bbai-library-thumbnail" loading="lazy" decoding="async" />
                                                    <?php if (!empty($bbai_modal_image_url)) : ?>
                                                        <span class="bbai-library-hover-preview" aria-hidden="true">
                                                            <img src="<?php echo esc_url($bbai_modal_image_url); ?>" alt="" loading="lazy" decoding="async" />
                                                        </span>
                                                    <?php endif; ?>
                                                </button>
                                            <?php else : ?>
                                                <div class="bbai-library-thumbnail-placeholder">
                                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                                        <path d="M2 6L10 1L18 6V16C18 16.5304 17.7893 17.0391 17.4142 17.4142C17.0391 17.7893 16.5304 18 16 18H4C3.46957 18 2.96086 17.7893 2.58579 17.4142C2.21071 17.0391 2 16.5304 2 16V6Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                        <path d="M7 18V10H13V18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                    </svg>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="bbai-library-cell--alt-text bbai-library-card__main bbai-row__main">
                                            <div class="bbai-library-card__col bbai-library-card__col--meta bbai-row__meta">
                                                <div class="bbai-library-card__meta-wrap">
                                                    <p class="bbai-library-card__filename" title="<?php echo esc_attr($bbai_display_filename); ?>"><?php echo esc_html($bbai_display_filename); ?></p>
                                                    <p class="bbai-library-card__meta"><?php echo esc_html($bbai_card_meta_line); ?></p>
                                                </div>
                                                <?php if ('' !== $bbai_score_hint) : ?>
                                                    <p class="bbai-library-card__score-hint"><?php echo esc_html($bbai_score_hint); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="bbai-library-card__col bbai-library-card__col--status bbai-row__status">
                                                <div class="bbai-library-status-tags" role="group" aria-label="<?php esc_attr_e('Status and quality score', 'beepbeep-ai-alt-text-generator'); ?>">
                                                    <span class="bbai-library-status-badge bbai-library-status-badge--<?php echo esc_attr($status); ?>"><?php echo esc_html($bbai_status_label); ?></span>
                                                    <?php if ($bbai_has_alt) : ?>
                                                        <span class="bbai-library-score-badge bbai-library-score-badge--<?php echo esc_attr($bbai_score_tier); ?>" title="<?php echo esc_attr($bbai_quality_tooltip); ?>" aria-label="<?php echo esc_attr(sprintf(/* translators: 1: numeric score, 2: tier label */ __('%1$s, %2$s', 'beepbeep-ai-alt-text-generator'), (string) (int) $bbai_quality_score, $bbai_score_tier_label)); ?>">
                                                            <span class="bbai-library-score-badge__value" aria-hidden="true"><?php echo esc_html((string) (int) $bbai_quality_score); ?></span>
                                                            <span class="bbai-library-score-badge__label" aria-hidden="true"><?php echo esc_html($bbai_score_tier_label); ?></span>
                                                        </span>
                                                    <?php else : ?>
                                                        <span class="bbai-library-score-badge bbai-library-score-badge--missing" title="<?php echo esc_attr($bbai_quality_tooltip); ?>">
                                                            <span class="bbai-library-score-badge__value" aria-hidden="true">—</span>
                                                            <span class="bbai-library-score-badge__label"><?php esc_html_e('No ALT', 'beepbeep-ai-alt-text-generator'); ?></span>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="bbai-library-card__review-body">
                                                <div class="bbai-library-card__col bbai-library-card__col--alt bbai-row__content">
                                                    <div class="bbai-library-alt-slot" data-bbai-alt-slot="1">
                                                        <div class="bbai-library-alt-preview-card bbai-library-alt-preview-card--v2 bbai-library-alt-preview-card--queue<?php echo $bbai_has_long_alt_preview ? ' bbai-library-alt-preview-card--collapsible' : ''; ?>" data-bbai-alt-preview-card data-action="edit-alt-inline" data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>">
                                                            <?php if ($bbai_has_alt) : ?>
                                                                <p id="<?php echo esc_attr($bbai_alt_preview_id); ?>" class="bbai-alt-text-preview" title="<?php echo esc_attr($bbai_clean_alt); ?>"><?php echo esc_html($bbai_alt_preview); ?></p>
                                                                <?php if ($bbai_has_long_alt_preview) : ?>
                                                                    <button type="button" class="bbai-library-alt-expand" data-action="toggle-alt-preview" aria-expanded="false" aria-controls="<?php echo esc_attr($bbai_alt_preview_id); ?>"><?php esc_html_e('Show more', 'beepbeep-ai-alt-text-generator'); ?></button>
                                                                <?php endif; ?>
                                                            <?php else : ?>
                                                                <span class="bbai-alt-text-missing"><?php esc_html_e('No ALT yet — click to add or generate.', 'beepbeep-ai-alt-text-generator'); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="bbai-library-card__col bbai-library-card__col--actions bbai-row__actions">
                                                    <div class="bbai-library-card__tool-row bbai-library-card__action-cluster bbai-row-actions">
                                                        <div class="bbai-library-card__quick-actions" aria-label="<?php esc_attr_e('Row actions', 'beepbeep-ai-alt-text-generator'); ?>">
                                                            <button type="button"
                                                                class="bbai-library-card__quick-action bbai-row-actions__primary <?php echo esc_attr($bbai_q1_class); ?><?php echo ($bbai_limit_reached_state && 'regenerate-single' === $bbai_q1_action) ? ' bbai-is-locked' : ''; ?>"
                                                                data-action="<?php echo esc_attr($bbai_q1_action); ?>"
                                                                data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"
                                                                title="<?php echo esc_attr($bbai_q1_title); ?>"
                                                                <?php if ('regenerate-single' === $bbai_q1_action) : ?>data-bbai-lock-preserve-label="1"<?php endif; ?>
                                                                <?php if ($bbai_limit_reached_state && 'regenerate-single' === $bbai_q1_action) : ?>data-bbai-action="open-upgrade" data-bbai-locked-cta="1" data-bbai-lock-reason="regenerate_single" data-bbai-locked-source="library-row-regenerate-quick" aria-disabled="true"<?php endif; ?>>
                                                                <?php echo esc_html($bbai_q1_label); ?>
                                                            </button>
                                                            <button type="button"
                                                                class="bbai-library-card__quick-action bbai-row-actions__secondary <?php echo esc_attr($bbai_q2_class); ?><?php echo ($bbai_limit_reached_state && 'regenerate-single' === $bbai_q2_action) ? ' bbai-is-locked' : ''; ?>"
                                                                data-action="<?php echo esc_attr($bbai_q2_action); ?>"
                                                                data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"
                                                                title="<?php echo esc_attr($bbai_q2_title); ?>"
                                                                <?php if ('regenerate-single' === $bbai_q2_action) : ?>data-bbai-lock-preserve-label="1"<?php endif; ?>
                                                                <?php if ($bbai_limit_reached_state && 'regenerate-single' === $bbai_q2_action) : ?>data-bbai-action="open-upgrade" data-bbai-locked-cta="1" data-bbai-lock-reason="regenerate_single" data-bbai-locked-source="library-row-regenerate-quick" aria-disabled="true"<?php endif; ?>>
                                                                <?php echo esc_html($bbai_q2_label); ?>
                                                            </button>
                                                        </div>
                                                        <?php if ($bbai_row_has_extras) : ?>
                                                            <div class="bbai-library-card__extra-actions" role="group" aria-label="<?php esc_attr_e('Additional actions', 'beepbeep-ai-alt-text-generator'); ?>">
                                                                <?php if ($bbai_row_extra_copy) : ?>
                                                                    <button type="button" class="bbai-library-card__quick-action bbai-library-card__quick-action--ghost bbai-row-actions__extra" data-action="copy-alt-text" data-alt-text="<?php echo esc_attr($bbai_clean_alt); ?>" data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"><?php esc_html_e('Copy ALT', 'beepbeep-ai-alt-text-generator'); ?></button>
                                                                <?php endif; ?>
                                                                <?php if ($bbai_row_extra_improve) : ?>
                                                                    <button type="button"
                                                                        class="bbai-library-card__quick-action bbai-library-card__quick-action--ghost bbai-row-actions__extra<?php echo $bbai_limit_reached_state ? ' bbai-is-locked' : ''; ?>"
                                                                        data-action="phase17-improve-alt"
                                                                        data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"
                                                                        title="<?php echo esc_attr($bbai_limit_reached_state ? __('Upgrade to unlock AI improvements', 'beepbeep-ai-alt-text-generator') : __('Improve ALT with AI (uses one credit)', 'beepbeep-ai-alt-text-generator')); ?>"
                                                                        <?php if ($bbai_limit_reached_state) : ?>data-bbai-action="open-upgrade" data-bbai-locked-cta="1" data-bbai-lock-reason="regenerate_single" data-bbai-locked-source="library-row-phase17-improve" aria-disabled="true"<?php endif; ?>>
                                                                        <?php esc_html_e('Improve ALT', 'beepbeep-ai-alt-text-generator'); ?>
                                                                    </button>
                                                                <?php endif; ?>
                                                                <?php if ($bbai_show_review_action) : ?>
                                                                    <button type="button" class="bbai-library-card__quick-action bbai-library-card__quick-action--ghost bbai-row-actions__extra" data-action="approve-alt-inline" data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"><?php esc_html_e('Mark reviewed', 'beepbeep-ai-alt-text-generator'); ?></button>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <?php if ($bbai_total_pages > 1) : ?>
                    <nav class="bbai-pagination bbai-table-meta" aria-label="<?php esc_attr_e('ALT Library pagination', 'beepbeep-ai-alt-text-generator'); ?>">
                        <span class="bbai-pagination-info">
                            <?php
                            if ('missing' === $bbai_default_review_filter) {
                                printf(
                                    /* translators: 1: start number, 2: end number, 3: total missing images */
                                    esc_html__('Showing %1$s-%2$s of %3$s missing images', 'beepbeep-ai-alt-text-generator'),
                                    esc_html(number_format_i18n($bbai_show_start)),
                                    esc_html(number_format_i18n($bbai_show_end)),
                                    esc_html(number_format_i18n($bbai_results_total))
                                );
                            } elseif ('weak' === $bbai_default_review_filter) {
                                printf(
                                    /* translators: 1: start number, 2: end number, 3: total images needing review */
                                    esc_html__('Showing %1$s-%2$s of %3$s images needing review', 'beepbeep-ai-alt-text-generator'),
                                    esc_html(number_format_i18n($bbai_show_start)),
                                    esc_html(number_format_i18n($bbai_show_end)),
                                    esc_html(number_format_i18n($bbai_results_total))
                                );
                            } elseif ('optimized' === $bbai_default_review_filter) {
                                printf(
                                    /* translators: 1: start number, 2: end number, 3: total optimized images */
                                    esc_html__('Showing %1$s-%2$s of %3$s optimized images', 'beepbeep-ai-alt-text-generator'),
                                    esc_html(number_format_i18n($bbai_show_start)),
                                    esc_html(number_format_i18n($bbai_show_end)),
                                    esc_html(number_format_i18n($bbai_results_total))
                                );
                            } else {
                                printf(
                                    /* translators: 1: start number, 2: end number, 3: total count in result set */
                                    esc_html__('Showing %1$s-%2$s of %3$s images', 'beepbeep-ai-alt-text-generator'),
                                    esc_html(number_format_i18n($bbai_show_start)),
                                    esc_html(number_format_i18n($bbai_show_end)),
                                    esc_html(number_format_i18n($bbai_results_total))
                                );
                            }
                            ?>
                        </span>

                        <div class="bbai-pagination-controls">
                            <?php if ($bbai_current_page > 1) : ?>
                                <a href="<?php echo esc_url(add_query_arg('alt_page', $bbai_current_page - 1)); ?>" class="bbai-pagination-btn"><?php esc_html_e('Previous', 'beepbeep-ai-alt-text-generator'); ?></a>
                            <?php endif; ?>

                            <div class="bbai-pagination-pages">
                                <?php
                                $bbai_page_range = 2;
                                $bbai_start_page = max(1, $bbai_current_page - $bbai_page_range);
                                $bbai_end_page = min($bbai_total_pages, $bbai_current_page + $bbai_page_range);

                                if ($bbai_start_page > 1) {
                                    echo '<a href="' . esc_url(add_query_arg('alt_page', 1)) . '" class="bbai-pagination-btn">1</a>';
                                    if ($bbai_start_page > 2) {
                                        echo '<span class="bbai-pagination-ellipsis">...</span>';
                                    }
                                }

                                for ($bbai_i = $bbai_start_page; $bbai_i <= $bbai_end_page; $bbai_i++) {
                                    $bbai_class = ($bbai_i === $bbai_current_page) ? 'bbai-pagination-btn bbai-pagination-btn--current' : 'bbai-pagination-btn';
                                    echo '<a href="' . esc_url(add_query_arg('alt_page', $bbai_i)) . '" class="' . esc_attr($bbai_class) . '">' . esc_html($bbai_i) . '</a>';
                                }

                                if ($bbai_end_page < $bbai_total_pages) {
                                    if ($bbai_end_page < $bbai_total_pages - 1) {
                                        echo '<span class="bbai-pagination-ellipsis">...</span>';
                                    }
                                    echo '<a href="' . esc_url(add_query_arg('alt_page', $bbai_total_pages)) . '" class="bbai-pagination-btn">' . esc_html($bbai_total_pages) . '</a>';
                                }
                                ?>
                            </div>

                            <?php if ($bbai_current_page < $bbai_total_pages) : ?>
                                <a href="<?php echo esc_url(add_query_arg('alt_page', $bbai_current_page + 1)); ?>" class="bbai-pagination-btn"><?php esc_html_e('Next', 'beepbeep-ai-alt-text-generator'); ?></a>
                            <?php endif; ?>
                        </div>
                    </nav>
                <?php endif; ?>
            <?php else : ?>
                <section class="bbai-library-empty-state bbai-table-empty">
                    <div class="bbai-library-empty-state__icon bbai-table-empty__icon" aria-hidden="true">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                            <path d="M4 5.5A1.5 1.5 0 015.5 4h13A1.5 1.5 0 0120 5.5v13a1.5 1.5 0 01-1.5 1.5h-13A1.5 1.5 0 014 18.5v-13z" stroke="currentColor" stroke-width="1.5"></path>
                            <path d="M8 15l2.5-2.5L13 15l3-3 2 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                            <circle cx="9" cy="9" r="1.2" fill="currentColor"></circle>
                        </svg>
                    </div>
                    <h2 class="bbai-library-empty-state__title bbai-table-empty__title"><?php esc_html_e('No images found', 'beepbeep-ai-alt-text-generator'); ?></h2>
                    <p class="bbai-library-empty-state__copy bbai-table-empty__copy"><?php esc_html_e('Scan your media library to get started, then review generated ALT text here as your library grows.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    <div class="bbai-library-empty-state__actions bbai-table-empty__actions">
                        <button type="button" class="bbai-btn bbai-btn-primary bbai-btn-sm" <?php echo $bbai_scan_action['attrs']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes assembled from escaped values. ?>><?php echo esc_html($bbai_scan_action['label']); ?></button>
                        <a href="<?php echo esc_url(admin_url('upload.php')); ?>" class="bbai-btn bbai-btn-secondary bbai-btn-sm"><?php esc_html_e('Open Media Library', 'beepbeep-ai-alt-text-generator'); ?></a>
                    </div>
                </section>
            <?php endif; ?>
        </main>
        </div><!-- /.bbai-library-workspace-card__table-surface -->
    </div><!-- /.bbai-library-workspace-card -->
</div>
