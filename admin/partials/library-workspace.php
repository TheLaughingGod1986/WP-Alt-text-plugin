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
        'label' => __('Scan Library', 'beepbeep-ai-alt-text-generator'),
        'attrs' => 'data-bbai-action="scan-opportunity"',
    ]
);

$bbai_review_optimized_action = $bbai_build_action(
    [
        'label' => __('Review Optimized', 'beepbeep-ai-alt-text-generator'),
        'attrs' => 'data-bbai-filter-target="optimized"',
    ]
);

$bbai_scan_new_uploads_action = $bbai_build_action(
    [
        'label'  => __('Scan for new uploads', 'beepbeep-ai-alt-text-generator'),
        'action' => 'rescan-media-library',
    ]
);

$bbai_scan_manual_action = $bbai_build_action(
    [
        'label'  => __('Scan manually', 'beepbeep-ai-alt-text-generator'),
        'action' => 'rescan-media-library',
    ]
);

$bbai_settings_automation_url = admin_url('admin.php?page=bbai-settings#bbai-enable-on-upload');
$bbai_review_usage_url        = admin_url('admin.php?page=bbai-credit-usage');

$bbai_surface_automation_action = $bbai_is_pro
    ? $bbai_build_action(
        [
            'label' => __('Automation settings', 'beepbeep-ai-alt-text-generator'),
            'attrs' => 'href="' . esc_url($bbai_settings_automation_url) . '"',
        ]
    )
    : $bbai_build_action(
        [
            'label' => __('Enable Auto-Optimisation', 'beepbeep-ai-alt-text-generator'),
            'attrs' => 'data-bbai-action="open-upgrade" data-bbai-locked-cta="1" data-bbai-lock-reason="automation" data-bbai-locked-source="library-summary-automation"',
    ]
);

if ($bbai_cov_missing > 0) {
    $bbai_header_primary_action = $bbai_build_action(
        [
            'label'       => __('Generate ALT Text', 'beepbeep-ai-alt-text-generator'),
            'action'      => 'generate-missing',
            'locked'      => true,
            'lock_reason' => 'generate_missing',
            'lock_source' => 'library-header-primary',
        ]
    );
    $bbai_summary_primary_action = $bbai_build_action(
        [
            'label'       => __('Fix missing ALT text', 'beepbeep-ai-alt-text-generator'),
            'action'      => 'generate-missing',
            'locked'      => true,
            'lock_reason' => 'generate_missing',
            'lock_source' => 'library-summary-primary',
        ]
    );
    $bbai_task_primary_action = $bbai_build_action(
        [
            'label'       => __('Generate ALT Text', 'beepbeep-ai-alt-text-generator'),
            'action'      => 'generate-missing',
            'locked'      => true,
            'lock_reason' => 'generate_missing',
            'lock_source' => 'library-task-banner-primary',
        ]
    );
    $bbai_queue_primary_action = $bbai_build_action(
        [
            'label'       => __('Generate ALT Text', 'beepbeep-ai-alt-text-generator'),
            'action'      => 'generate-missing',
            'locked'      => true,
            'lock_reason' => 'generate_missing',
            'lock_source' => 'library-sidebar-queue',
        ]
    );

    $bbai_task_eyebrow = __('Accessibility improvements available', 'beepbeep-ai-alt-text-generator');
    $bbai_task_title = sprintf(
        _n('%s image is missing ALT text', '%s images are missing ALT text', $bbai_cov_missing, 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($bbai_cov_missing)
    );
    $bbai_task_copy = __('Generate ALT text first to improve accessibility and SEO.', 'beepbeep-ai-alt-text-generator');

    $bbai_queue_title = sprintf(
        _n('%s image needs ALT text', '%s images need ALT text', $bbai_cov_missing, 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($bbai_cov_missing)
    );
    $bbai_queue_copy = __('Generate missing ALT text first before reviewing weaker descriptions.', 'beepbeep-ai-alt-text-generator');
} elseif ($bbai_cov_needs_review > 0) {
    $bbai_header_primary_action = $bbai_build_action(
        [
            'label'       => __('Improve ALT Text', 'beepbeep-ai-alt-text-generator'),
            'action'      => 'regenerate-all',
            'attrs'       => 'data-bbai-regenerate-scope="needs-review" data-bbai-generation-source="regenerate-weak"',
            'locked'      => true,
            'lock_reason' => 'reoptimize_all',
            'lock_source' => 'library-header-primary',
        ]
    );
    $bbai_summary_primary_action = $bbai_build_action(
        [
            'label'       => __('Improve ALT Text', 'beepbeep-ai-alt-text-generator'),
            'action'      => 'regenerate-all',
            'attrs'       => 'data-bbai-regenerate-scope="needs-review" data-bbai-generation-source="regenerate-weak"',
            'locked'      => true,
            'lock_reason' => 'reoptimize_all',
            'lock_source' => 'library-summary-primary',
        ]
    );
    $bbai_task_primary_action = $bbai_build_action(
        [
            'label'       => __('Improve ALT Text', 'beepbeep-ai-alt-text-generator'),
            'action'      => 'regenerate-all',
            'attrs'       => 'data-bbai-regenerate-scope="needs-review" data-bbai-generation-source="regenerate-weak"',
            'locked'      => true,
            'lock_reason' => 'reoptimize_all',
            'lock_source' => 'library-task-banner-primary',
        ]
    );
    $bbai_queue_primary_action = $bbai_build_action(
        [
            'label'       => __('Improve ALT Text', 'beepbeep-ai-alt-text-generator'),
            'action'      => 'regenerate-all',
            'attrs'       => 'data-bbai-regenerate-scope="needs-review" data-bbai-generation-source="regenerate-weak"',
            'locked'      => true,
            'lock_reason' => 'reoptimize_all',
            'lock_source' => 'library-sidebar-queue',
        ]
    );

    $bbai_task_eyebrow = __('Descriptions can be improved', 'beepbeep-ai-alt-text-generator');
    $bbai_task_title = sprintf(
        _n('%s image has a weak ALT description', '%s images have weak ALT descriptions', $bbai_cov_needs_review, 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($bbai_cov_needs_review)
    );
    $bbai_task_copy = __('Review and improve these descriptions for stronger accessibility and search quality.', 'beepbeep-ai-alt-text-generator');

    $bbai_queue_title = sprintf(
        _n('%s description needs review', '%s descriptions need review', $bbai_cov_needs_review, 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($bbai_cov_needs_review)
    );
    $bbai_queue_copy = __('Work through weaker ALT descriptions to finish the review queue.', 'beepbeep-ai-alt-text-generator');
} else {
    $bbai_header_primary_action = $bbai_surface_automation_action;
    $bbai_summary_primary_action = $bbai_build_action(
        [
            'label' => __('Review optimized images', 'beepbeep-ai-alt-text-generator'),
            'attrs' => 'data-bbai-filter-target="optimized"',
        ]
    );
    $bbai_task_primary_action = $bbai_surface_automation_action;
    $bbai_queue_primary_action = $bbai_build_action(
        [
            'label' => __('Review optimized images', 'beepbeep-ai-alt-text-generator'),
            'attrs' => 'data-bbai-filter-target="optimized"',
        ]
    );

    $bbai_task_eyebrow = __('All images look good', 'beepbeep-ai-alt-text-generator');
    $bbai_task_title = __('Your ALT Library is fully optimized', 'beepbeep-ai-alt-text-generator');
    $bbai_task_copy = __('Scan for new uploads or review optimized images any time.', 'beepbeep-ai-alt-text-generator');

    $bbai_queue_title = __('Your queue is clear', 'beepbeep-ai-alt-text-generator');
    $bbai_queue_copy = __('Everything currently has ALT text. Scan for new uploads to keep coverage current.', 'beepbeep-ai-alt-text-generator');
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
$bbai_usage_remaining = max(0, (int) ($bbai_usage_stats['remaining'] ?? ($bbai_usage_limit - $bbai_usage_used)));
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
        'needs_review_library_url' => add_query_arg(['page' => 'bbai-library', 'status' => 'needs_review'], admin_url('admin.php')),
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
    ],
    [
        'key'               => 'missing',
        'label'             => __('Missing', 'beepbeep-ai-alt-text-generator'),
        'count'             => $bbai_table_filter_counts['missing'],
        'active'            => $bbai_default_review_filter === 'missing',
        'filter_label_attr' => __('Missing', 'beepbeep-ai-alt-text-generator'),
    ],
    [
        'key'               => 'weak',
        'label'             => __('Needs review', 'beepbeep-ai-alt-text-generator'),
        'count'             => $bbai_table_filter_counts['weak'],
        'active'            => $bbai_default_review_filter === 'weak',
        'filter_label_attr' => __('Needs review', 'beepbeep-ai-alt-text-generator'),
    ],
    [
        'key'               => 'optimized',
        'label'             => __('Optimized', 'beepbeep-ai-alt-text-generator'),
        'count'             => $bbai_table_filter_counts['optimized'],
        'active'            => $bbai_default_review_filter === 'optimized',
        'filter_label_attr' => __('Optimized', 'beepbeep-ai-alt-text-generator'),
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
    <style id="bbai-library-workspace-styles">
    .bbai-library-workspace {
        --bbai-library-border: #dfe8f6;
        --bbai-library-border-strong: #cfd9ea;
        --bbai-library-surface: linear-gradient(135deg, #f7fbff 0%, #eef4ff 58%, #ffffff 100%);
        --bbai-library-card-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
        --bbai-library-soft-shadow: 0 16px 36px rgba(15, 23, 42, 0.05);
        --bbai-library-text: #111827;
        --bbai-library-muted: #6b7280;
        --bbai-library-subtle: #94a3b8;
        --bbai-library-track: #e6edf8;
        --bbai-library-green: #16a34a;
        --bbai-library-green-bg: #effbf3;
        --bbai-library-amber: #d97706;
        --bbai-library-amber-bg: #fffbeb;
        --bbai-library-red: #dc2626;
        --bbai-library-red-bg: #fff1f2;
        --bbai-library-blue: #2563eb;
        --bbai-library-blue-bg: #eff6ff;
        /* Shared ALT status tokens — row left accents + filter pills (see filter-group.css) */
        --bbai-status-missing-accent: rgba(244, 63, 94, 0.52);
        --bbai-status-missing-bg: rgba(255, 241, 242, 0.58);
        --bbai-status-weak-accent: rgba(245, 158, 11, 0.5);
        --bbai-status-weak-bg: rgba(255, 251, 235, 0.68);
        --bbai-status-opt-accent: rgba(34, 197, 94, 0.52);
        --bbai-status-opt-bg: rgba(240, 253, 244, 0.58);
        --bbai-link-row-strong: #047857;
        --bbai-link-row-secondary: #1d4ed8;
        --bbai-link-row-secondary-border: rgba(59, 130, 246, 0.42);
        --bbai-link-row-tertiary: #2563eb;
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
        background: var(--bbai-library-red-bg);
        border-color: #fecdd3;
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

    /* Horizontal filter bar: banner → control → workspace */
    .bbai-library-workspace-filter-strip {
        margin: 0;
        padding: 14px 0 14px;
        border-bottom: 1px solid #e2e8f0;
        background: linear-gradient(180deg, #f8fafc 0%, #ffffff 55%);
    }

    .bbai-library-workspace-filter-strip__inner {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
        max-width: 100%;
        min-width: 0;
    }

    .bbai-library-workspace-filter-strip__label {
        margin: 0;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.07em;
        text-transform: uppercase;
        color: var(--bbai-library-subtle);
    }

    .bbai-library-workspace-filter-bar {
        width: 100%;
        min-width: 0;
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

    .bbai-library-status-badge--optimized {
        background: #dcfce7;
        color: #14532d;
        border: 1px solid rgba(22, 163, 74, 0.22);
    }

    .bbai-library-status-badge--weak {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid rgba(245, 158, 11, 0.28);
    }

    .bbai-library-status-badge--missing {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid rgba(239, 68, 68, 0.22);
    }

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

    .bbai-library-score-badge--good {
        background: #ecfdf5;
        color: #065f46;
        border-color: rgba(16, 185, 129, 0.35);
    }

    .bbai-library-score-badge--review {
        background: #fffbeb;
        color: #92400e;
        border-color: rgba(245, 158, 11, 0.4);
    }

    .bbai-library-score-badge--weak {
        background: #fef2f2;
        color: #991b1b;
        border-color: rgba(239, 68, 68, 0.35);
    }

    .bbai-library-score-badge--missing {
        background: #f8fafc;
        color: #64748b;
        border-color: #e2e8f0;
    }

    .bbai-library-card__score-hint {
        margin: 4px 0 0;
        font-size: 11px;
        line-height: 1.4;
        color: var(--bbai-library-muted);
    }

    .bbai-library-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: auto;
    }

    .bbai-row-action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 32px;
        padding: 6px 11px;
        border-radius: 10px;
        border: 1px solid #dce4f0;
        background: #ffffff;
        color: #334155;
        font-size: 12px;
        line-height: 1;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.16s ease;
    }

    .bbai-row-action-btn:hover:not(:disabled) {
        background: #f8fbff;
        border-color: #c4d4e8;
        transform: translateY(-1px);
    }

    .bbai-row-action-btn--primary {
        background: #0f172a;
        border-color: #0f172a;
        color: #ffffff;
    }

    .bbai-row-action-btn--primary:hover:not(:disabled) {
        background: #1e293b;
        border-color: #1e293b;
    }

    .bbai-row-action-btn--ghost {
        background: #ffffff;
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
        gap: 12px;
    }

    .bbai-library-inline-edit__textarea {
        width: 100%;
        min-height: 112px;
        padding: 14px 16px;
        border-radius: 16px;
        border: 1px solid #60a5fa;
        box-shadow: 0 0 0 4px rgba(96, 165, 250, 0.12);
        resize: vertical;
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

    .bbai-library-empty-card {
        padding: 28px 24px;
        margin-top: 2px;
        border-radius: 18px;
        border: 1px dashed #d8e4f2;
        background: linear-gradient(180deg, #fbfdff 0%, #ffffff 100%);
        text-align: center;
    }

    .bbai-library-empty-card__title {
        margin: 0;
        font-size: 20px;
        line-height: 1.2;
        letter-spacing: -0.02em;
        color: var(--bbai-library-text);
    }

    .bbai-library-empty-card__copy {
        margin: 10px auto 0;
        max-width: 480px;
        font-size: 14px;
        line-height: 1.6;
        color: var(--bbai-library-muted);
    }

    .bbai-library-empty-card__actions {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: var(--card-gap, 16px);
    }

    .bbai-library-empty-state {
        padding: 32px;
        border-radius: 18px;
        border: 1px solid var(--bbai-library-border);
        background: linear-gradient(180deg, #f9fbff 0%, #ffffff 100%);
        box-shadow: var(--bbai-library-card-shadow);
        text-align: center;
    }

    .bbai-library-empty-state__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 72px;
        height: 72px;
        margin-bottom: var(--card-gap, 16px);
        border-radius: 20px;
        background: #f3f7fc;
        color: var(--bbai-library-subtle);
    }

    .bbai-library-empty-state__title {
        margin: 0;
        font-size: 24px;
        line-height: 1.15;
        letter-spacing: -0.03em;
        color: var(--bbai-library-text);
    }

    .bbai-library-empty-state__copy {
        margin: 12px auto 0;
        max-width: 520px;
        font-size: 14px;
        line-height: 1.7;
        color: var(--bbai-library-muted);
    }

    .bbai-library-empty-state__actions {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: var(--section-spacing, 24px);
    }

    .bbai-pagination {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        margin-top: 18px;
        padding: 18px 2px 0;
    }

    .bbai-pagination-info {
        font-size: 12px;
        line-height: 1.45;
        color: rgba(100, 116, 139, 0.92);
        font-weight: 500;
    }

    .bbai-pagination-controls,
    .bbai-pagination-pages {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .bbai-pagination-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 38px;
        min-height: 38px;
        padding: 0 12px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        color: #475569;
        text-decoration: none;
        font-size: 13px;
        line-height: 1;
        font-weight: 600;
        transition: border-color 0.18s ease, background-color 0.18s ease, color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
    }

    .bbai-pagination-btn:hover:not(.bbai-pagination-btn--current) {
        border-color: #cbd5e1;
        background: #f8fafc;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
        transform: translateY(-1px);
    }

    .bbai-pagination-btn--current {
        background: #10b981;
        border-color: #10b981;
        color: #ffffff;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
        cursor: default;
        transform: none;
    }

    .bbai-pagination-ellipsis {
        color: var(--bbai-library-subtle);
        font-size: 13px;
        line-height: 1;
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
        .bbai-pagination {
            flex-direction: column;
            align-items: stretch;
        }

        .bbai-library-summary-actions,
        .bbai-library-selection-bar__actions,
        .bbai-pagination-controls {
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
        -webkit-line-clamp: 3;
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
        min-height: 54px;
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
        line-height: 1.65;
        color: var(--bbai-library-text);
        -webkit-line-clamp: 3;
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

    .bbai-table-wrap {
        overflow-x: auto;
        overflow-y: visible;
        -webkit-overflow-scrolling: touch;
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
        border-top: 1px solid #e2e8f0;
    }

    .bbai-library-review-card {
        display: grid;
        grid-template-columns: 60px minmax(0, 1fr);
        align-items: start;
        gap: 12px 16px;
        padding: 12px 6px 12px 2px;
        margin: 0;
        background: #ffffff;
        border: none;
        border-bottom: 1px solid #eef2f7;
        border-radius: 0;
        box-shadow: none;
        min-height: 0;
        box-sizing: border-box;
        transition: background-color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .bbai-library-review-card[data-status='missing'] {
        background: linear-gradient(90deg, var(--bbai-status-missing-bg) 0%, #ffffff 12px);
        box-shadow: inset 3px 0 0 var(--bbai-status-missing-accent);
    }

    .bbai-library-review-card[data-status='weak'] {
        background: linear-gradient(90deg, var(--bbai-status-weak-bg) 0%, #ffffff 12px);
        box-shadow: inset 3px 0 0 var(--bbai-status-weak-accent);
    }

    .bbai-library-review-card[data-status='optimized'] {
        background: linear-gradient(90deg, var(--bbai-status-opt-bg) 0%, #ffffff 12px);
        box-shadow: inset 3px 0 0 var(--bbai-status-opt-accent);
    }

    .bbai-library-review-card:last-child {
        border-bottom-color: #eef2f7;
    }

    .bbai-library-review-card:hover,
    .bbai-library-review-card:focus-within {
        background: #f8fafc;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85);
        transform: none;
    }

    .bbai-library-review-card[data-status='missing']:hover,
    .bbai-library-review-card[data-status='missing']:focus-within {
        background: linear-gradient(90deg, rgba(255, 228, 230, 0.75) 0%, #f8fafc 14px);
        box-shadow: inset 3px 0 0 var(--bbai-status-missing-accent), inset 0 1px 0 rgba(255, 255, 255, 0.85);
    }

    .bbai-library-review-card[data-status='weak']:hover,
    .bbai-library-review-card[data-status='weak']:focus-within {
        background: linear-gradient(90deg, rgba(254, 243, 199, 0.72) 0%, #f8fafc 14px);
        box-shadow: inset 3px 0 0 var(--bbai-status-weak-accent), inset 0 1px 0 rgba(255, 255, 255, 0.85);
    }

    .bbai-library-review-card[data-status='optimized']:hover,
    .bbai-library-review-card[data-status='optimized']:focus-within {
        background: linear-gradient(90deg, rgba(220, 252, 231, 0.72) 0%, #f8fafc 14px);
        box-shadow: inset 3px 0 0 var(--bbai-status-opt-accent), inset 0 1px 0 rgba(255, 255, 255, 0.85);
    }

    .bbai-library-review-card.bbai-library-row--editing {
        background: rgba(239, 246, 255, 0.65);
        box-shadow: inset 3px 0 0 #3b82f6;
        border-bottom-color: #dbeafe;
    }

    .bbai-library-review-card.bbai-library-row--saved-flash {
        animation: bbai-library-row-saved 0.85s ease;
    }

    @keyframes bbai-library-row-saved {
        0% {
            background: rgba(220, 252, 231, 0.85);
            box-shadow: inset 3px 0 0 #22c55e;
        }
        100% {
            background: #ffffff;
            box-shadow: none;
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
        background: rgba(239, 246, 255, 0.65);
        border-bottom-color: #dbeafe;
        box-shadow: inset 3px 0 0 #2563eb;
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
        gap: 8px;
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
        gap: 8px;
        min-width: 0;
        width: 100%;
    }

    .bbai-library-card__review-body .bbai-library-alt-slot {
        flex: 1 1 auto;
        min-width: 0;
        width: 100%;
    }

    .bbai-library-card__review-body .bbai-library-card__action-cluster {
        flex: 0 0 auto;
        align-self: stretch;
    }

    @media (min-width: 900px) {
        .bbai-library-card__main {
            display: grid;
            grid-template-columns: minmax(0, min(200px, 20vw)) minmax(108px, 128px) minmax(0, 1fr) minmax(112px, 152px);
            column-gap: 12px;
            row-gap: 4px;
            align-items: start;
        }

        .bbai-library-card__col--meta {
            grid-column: 1;
            grid-row: 1;
        }

        .bbai-library-card__col--status {
            grid-column: 2;
            grid-row: 1;
            justify-self: start;
            align-self: start;
            padding-top: 1px;
        }

        .bbai-library-card__review-body {
            display: contents;
        }

        .bbai-library-card__col--alt {
            grid-column: 3;
            grid-row: 1;
            min-width: 0;
        }

        .bbai-library-card__col--actions {
            grid-column: 4;
            grid-row: 1;
            min-width: 0;
            justify-self: end;
            align-self: start;
        }

        .bbai-library-card__review-body .bbai-library-alt-slot {
            width: 100%;
        }

        .bbai-library-card__review-body .bbai-library-card__action-cluster {
            width: 100%;
            max-width: 152px;
            justify-content: flex-end;
            margin-top: 0;
        }
    }

    @media (min-width: 1200px) {
        .bbai-library-card__main {
            grid-template-columns: minmax(0, min(220px, 18vw)) minmax(112px, 136px) minmax(0, 1fr) minmax(118px, 160px);
            column-gap: 14px;
        }
    }

    .bbai-library-card__tool-row,
    .bbai-library-card__action-cluster {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        gap: 6px;
        flex-wrap: wrap;
    }

    .bbai-library-card__action-cluster {
        margin-top: 0;
    }

    .bbai-library-card__action-cluster .bbai-library-row-menu--cluster {
        margin-left: auto;
        flex-shrink: 0;
    }

    @media (min-width: 900px) {
        .bbai-library-card__action-cluster {
            flex-direction: column;
            align-items: stretch;
            gap: 6px;
        }

        .bbai-library-card__action-cluster .bbai-library-card__quick-actions {
            width: 100%;
            justify-content: stretch;
        }

        .bbai-library-card__action-cluster .bbai-library-card__quick-actions .bbai-library-card__quick-action {
            flex: 1 1 auto;
            min-width: 0;
        }

        .bbai-library-card__action-cluster .bbai-library-row-menu--cluster {
            margin-left: 0;
            align-self: stretch;
        }

        .bbai-library-card__action-cluster .bbai-library-row-menu__toggle {
            width: 100%;
            justify-content: center;
        }
    }

    .bbai-library-card__quick-actions {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
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

    .bbai-library-card__quick-action {
        margin: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 32px;
        padding: 7px 14px;
        border-radius: 9px;
        font-size: 12px;
        font-weight: 600;
        line-height: 1.2;
        cursor: pointer;
        border: 1px solid transparent;
        text-decoration: none;
        -webkit-appearance: none;
        appearance: none;
        transition: box-shadow 0.15s ease, background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease, filter 0.15s ease;
    }

    /* Primary AI action — solid brand green; wins over WP admin button resets */
    .bbai-library-workspace button.bbai-library-card__quick-action.bbai-library-card__quick-action--primary:not(:disabled):not(.bbai-is-locked) {
        background: var(--bbai-cta-primary-bg, #10b981) !important;
        background-color: var(--bbai-cta-primary-bg, #10b981) !important;
        border-color: var(--bbai-cta-primary-bg-active, #047857) !important;
        color: #ffffff !important;
        font-weight: 700;
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

    /* Secondary — visible on white rows; clearly subordinate to primary */
    .bbai-library-workspace button.bbai-library-card__quick-action.bbai-library-card__quick-action--secondary:not(:disabled):not(.bbai-is-locked) {
        background: #eef2f7 !important;
        border: 1px solid #94a3b8 !important;
        color: #334155 !important;
        font-weight: 600;
        box-shadow: none !important;
        opacity: 1 !important;
    }

    .bbai-library-workspace button.bbai-library-card__quick-action.bbai-library-card__quick-action--secondary:hover:not(:disabled):not(.bbai-is-locked),
    .bbai-library-workspace button.bbai-library-card__quick-action.bbai-library-card__quick-action--secondary:focus-visible:not(.bbai-is-locked) {
        background: #e2e8f0 !important;
        border-color: #64748b !important;
        color: #0f172a !important;
        outline: none;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08) !important;
    }

    .bbai-library-workspace button.bbai-library-card__quick-action.bbai-library-card__quick-action--secondary:focus-visible:not(.bbai-is-locked) {
        box-shadow: 0 0 0 2px #ffffff, 0 0 0 4px rgba(100, 116, 139, 0.35) !important;
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

    /* Optimized rows: deliberate “Edit” without full primary weight */
    .bbai-library-card__quick-action--lead {
        background: #ffffff;
        border: 1px solid #c7d2e4;
        color: #0f172a;
        font-weight: 600;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }

    .bbai-library-card__quick-action--lead:hover:not(:disabled),
    .bbai-library-card__quick-action--lead:focus-visible {
        background: #f8fafc;
        border-color: #94a3b8;
        color: #0f172a;
        outline: none;
        box-shadow: 0 1px 4px rgba(15, 23, 42, 0.06);
    }

    .bbai-library-card__quick-action--lead:focus-visible {
        box-shadow: 0 0 0 2px #ffffff, 0 0 0 4px rgba(100, 116, 139, 0.28);
    }

    .bbai-library-card__quick-action--ghost {
        background: transparent;
        border: 1px solid #e2e8f0;
        color: #64748b;
        font-weight: 600;
        box-shadow: none;
    }

    .bbai-library-card__quick-action--ghost:hover:not(:disabled):not(.bbai-is-locked),
    .bbai-library-card__quick-action--ghost:focus-visible:not(.bbai-is-locked) {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: #475569;
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

    .bbai-library-row-menu {
        position: relative;
    }

    .bbai-library-row-menu[open] {
        z-index: 3;
    }

    .bbai-library-row-menu__toggle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 30px;
        height: 30px;
        padding: 0 8px;
        border: 1px solid rgba(59, 130, 246, 0.28);
        border-radius: 8px;
        background: #f8fafc;
        color: #2563eb;
        font-size: 14px;
        line-height: 1;
        letter-spacing: 0.06em;
        cursor: pointer;
        list-style: none;
        transition: background-color 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease, color 0.18s ease;
    }

    .bbai-library-row-menu--cluster .bbai-library-row-menu__toggle {
        font-weight: 700;
    }

    .bbai-library-row-menu__toggle::-webkit-details-marker {
        display: none;
    }

    .bbai-library-row-menu__toggle:hover,
    .bbai-library-row-menu__toggle:focus-visible {
        background: #eff6ff;
        border-color: rgba(37, 99, 235, 0.45);
        color: #1d4ed8;
        box-shadow: 0 2px 8px rgba(37, 99, 235, 0.12);
        outline: none;
    }

    .bbai-library-row-menu__toggle:focus-visible {
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
    }

    .bbai-library-row-menu__panel {
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        min-width: 180px;
        padding: 8px;
        background: #ffffff;
        border: 1px solid #dbe6f2;
        border-radius: 16px;
        box-shadow: 0 22px 46px rgba(15, 23, 42, 0.16);
        display: grid;
        gap: 4px;
    }

    .bbai-library-row-menu__item {
        display: flex;
        align-items: center;
        width: 100%;
        min-height: 36px;
        padding: 0 10px;
        border: 0;
        border-radius: 10px;
        background: transparent;
        color: var(--bbai-library-text);
        font-size: 13px;
        font-weight: 600;
        text-align: left;
        cursor: pointer;
        transition: background-color 0.14s ease, color 0.14s ease;
    }

    .bbai-library-row-menu__item:hover,
    .bbai-library-row-menu__item:focus-visible {
        background: #f3f7fc;
        color: var(--bbai-library-blue);
    }

    .bbai-library-row-menu__item--tertiary {
        color: #3b82f6;
        font-weight: 600;
    }

    .bbai-library-row-menu__item--tertiary:hover,
    .bbai-library-row-menu__item--tertiary:focus-visible {
        color: #1d4ed8;
        text-decoration: underline;
        text-underline-offset: 3px;
        text-decoration-color: rgba(29, 78, 216, 0.45);
    }

    .bbai-library-row-menu__item.bbai-is-locked {
        opacity: 1;
        cursor: pointer;
        background: #fffbeb;
        color: #b45309;
    }

    .bbai-library-row-menu__item.bbai-is-locked:hover,
    .bbai-library-row-menu__item.bbai-is-locked:focus-visible {
        background: #fef3c7;
        color: #92400e;
        text-decoration: none;
    }

    .bbai-library-row-menu__item:disabled {
        opacity: 1;
        color: #94a3b8;
        background: transparent;
        cursor: not-allowed;
    }

    .bbai-library-row-menu__item:disabled:hover {
        background: transparent;
        color: #94a3b8;
    }

    .bbai-library-alt-preview-card--queue {
        box-sizing: border-box;
        width: 100%;
        min-width: 0;
        max-width: 100%;
        min-height: 68px;
        padding: 10px 12px;
        border-radius: 10px;
        background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        border: 1px solid #c7d2e4;
        cursor: pointer;
        transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
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
        line-height: 1.55;
        -webkit-line-clamp: 4;
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

        .bbai-library-row-menu__panel {
            left: 0;
            right: auto;
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
        class="bbai-library-surface-root"
        data-bbai-coverage-card="1"
        data-bbai-library-surface="1"
        data-state="<?php echo esc_attr($bbai_surface_state); ?>"
        data-bbai-free-plan-limit="<?php echo esc_attr((int) (($bbai_coverage ?? [])['free_plan_limit'] ?? 50)); ?>"
    >
        <?php bbai_ui_render('status-banner', [ 'command_hero' => $bbai_command_hero ]); ?>

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

    <section class="bbai-library-workspace-filter-strip" aria-labelledby="bbai-library-filter-bar-label">
        <div class="bbai-library-workspace-filter-strip__inner">
            <p class="bbai-library-workspace-filter-strip__label" id="bbai-library-filter-bar-label"><?php esc_html_e('Show in table', 'beepbeep-ai-alt-text-generator'); ?></p>
            <div class="bbai-library-workspace-filter-bar">
                <div class="bbai-library-filter-toolbar">
                    <?php
                    bbai_ui_render(
                        'filter-group',
                        [
                            'variant'          => 'horizontal',
                            'interaction_mode' => 'filter',
                            'id'               => 'bbai-review-filter-tabs',
                            'default_filter'   => $bbai_default_review_filter,
                            'aria_label'       => __('Filter images by status', 'beepbeep-ai-alt-text-generator'),
                            'items'            => $bbai_library_workspace_filter_items,
                        ]
                    );
                    ?>
            </div>
            </div>
        </div>
    </section>

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

    <section class="bbai-library-toolbar-card bbai-library-toolbar-card--utilities" aria-label="<?php esc_attr_e('Search, sort, and bulk selection', 'beepbeep-ai-alt-text-generator'); ?>">
        <div class="bbai-library-toolbar" id="bbai-library-toolbar" aria-live="polite">
            <div class="bbai-library-toolbar__right bbai-library-toolbar__right--full">
                <div class="bbai-library-toolbar__controls">
                    <label class="bbai-library-search" for="bbai-library-search">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                            <circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.5"></circle>
                            <path d="M11 11L14 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                        </svg>
                        <input type="text" id="bbai-library-search" placeholder="<?php esc_attr_e('Search ALT text or filenames', 'beepbeep-ai-alt-text-generator'); ?>" />
                    </label>

                    <label class="bbai-sr-only" for="bbai-library-sort"><?php esc_html_e('Sort images', 'beepbeep-ai-alt-text-generator'); ?></label>
                    <select id="bbai-library-sort" class="bbai-library-select">
                        <option value="recently-updated" selected><?php esc_html_e('Recently updated', 'beepbeep-ai-alt-text-generator'); ?></option>
                        <option value="score-asc"><?php esc_html_e('Lowest score first', 'beepbeep-ai-alt-text-generator'); ?></option>
                        <option value="score-desc"><?php esc_html_e('Highest score first', 'beepbeep-ai-alt-text-generator'); ?></option>
                    </select>
                    <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm bbai-library-bulk-toggle" id="bbai-library-bulk-toggle" data-action="toggle-library-bulk-mode" aria-pressed="false"><?php esc_html_e('Select multiple', 'beepbeep-ai-alt-text-generator'); ?></button>
                </div>
            </div>
        </div>
    </section>

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
                        <button type="button" class="bbai-btn bbai-btn-primary bbai-btn-sm<?php echo $bbai_limit_reached_state ? ' bbai-is-locked' : ''; ?>" id="bbai-batch-generate" data-action="generate-selected" data-bbai-lock-preserve-label="1"<?php if ($bbai_limit_reached_state) : ?> data-bbai-action="open-upgrade" data-bbai-locked-cta="1" data-bbai-lock-reason="generate_missing" data-bbai-locked-source="library-selection-bar-generate" aria-disabled="true"<?php endif; ?>><?php esc_html_e('Generate ALT', 'beepbeep-ai-alt-text-generator'); ?></button>
                        <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm<?php echo $bbai_limit_reached_state ? ' bbai-is-locked' : ''; ?>" id="bbai-batch-regenerate" data-action="regenerate-selected" data-bbai-lock-preserve-label="1"<?php if ($bbai_limit_reached_state) : ?> data-bbai-action="open-upgrade" data-bbai-locked-cta="1" data-bbai-lock-reason="reoptimize_all" data-bbai-locked-source="library-selection-bar-regenerate" aria-disabled="true"<?php endif; ?>><?php esc_html_e('Regenerate ALT', 'beepbeep-ai-alt-text-generator'); ?></button>
                        <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" id="bbai-batch-reviewed" data-action="mark-reviewed"><?php esc_html_e('Mark as reviewed', 'beepbeep-ai-alt-text-generator'); ?></button>
                        <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" id="bbai-batch-clear-alt" data-action="clear-alt-selected"><?php esc_html_e('Delete ALT', 'beepbeep-ai-alt-text-generator'); ?></button>
                        <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" id="bbai-batch-export" data-action="export-alt-text"><?php esc_html_e('Export', 'beepbeep-ai-alt-text-generator'); ?></button>
                        <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm bbai-selection-clear" id="bbai-batch-clear" data-action="clear-selection"><?php esc_html_e('Clear', 'beepbeep-ai-alt-text-generator'); ?></button>
                    </div>
                </div>

                <header class="bbai-library-workspace-intro" aria-labelledby="bbai-library-results-title">
                    <div class="bbai-library-workspace-intro__text">
                        <h2 id="bbai-library-results-title" class="bbai-library-workspace-intro__title" data-bbai-results-heading tabindex="-1"><?php esc_html_e('ALT review workspace', 'beepbeep-ai-alt-text-generator'); ?></h2>
                        <p class="bbai-library-workspace-intro__tagline"><?php esc_html_e('Generate, edit, or regenerate ALT text per image.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <p class="bbai-library-workspace-intro__meta">
                                <?php
                                if ($bbai_row_count < 1) {
                                    if ($bbai_results_total < 1) {
                                        esc_html_e('No images to show for this filter.', 'beepbeep-ai-alt-text-generator');
                                    } else {
                                        printf(
                                            /* translators: %s: total matching images */
                                            esc_html__('No images on this page (0 of %s matching).', 'beepbeep-ai-alt-text-generator'),
                                            esc_html(number_format_i18n($bbai_results_total))
                                        );
                                    }
                                } elseif ('missing' === $bbai_default_review_filter) {
                                    printf(
                                        /* translators: 1: start rank, 2: end rank, 3: total missing images */
                                        esc_html__('Showing %1$s–%2$s of %3$s missing images', 'beepbeep-ai-alt-text-generator'),
                                        esc_html(number_format_i18n($bbai_show_start)),
                                        esc_html(number_format_i18n($bbai_show_end)),
                                        esc_html(number_format_i18n($bbai_results_total))
                                    );
                                } elseif ('weak' === $bbai_default_review_filter) {
                                    printf(
                                        /* translators: 1: start rank, 2: end rank, 3: total images needing review */
                                        esc_html__('Showing %1$s–%2$s of %3$s images needing review', 'beepbeep-ai-alt-text-generator'),
                                        esc_html(number_format_i18n($bbai_show_start)),
                                        esc_html(number_format_i18n($bbai_show_end)),
                                        esc_html(number_format_i18n($bbai_results_total))
                                    );
                                } elseif ('optimized' === $bbai_default_review_filter) {
                                    printf(
                                        /* translators: 1: start rank, 2: end rank, 3: total optimized images */
                                        esc_html__('Showing %1$s–%2$s of %3$s optimized images', 'beepbeep-ai-alt-text-generator'),
                                        esc_html(number_format_i18n($bbai_show_start)),
                                        esc_html(number_format_i18n($bbai_show_end)),
                                        esc_html(number_format_i18n($bbai_results_total))
                                    );
                                } else {
                                    printf(
                                        /* translators: 1: start rank, 2: end rank, 3: total images in the current result set */
                                        esc_html__('Showing %1$s–%2$s of %3$s images on this page', 'beepbeep-ai-alt-text-generator'),
                                        esc_html(number_format_i18n($bbai_show_start)),
                                        esc_html(number_format_i18n($bbai_show_end)),
                                        esc_html(number_format_i18n($bbai_results_total))
                                    );
                                }
                                ?>
                            </p>
                        <?php if (0 === $bbai_page_missing_or_weak_count) : ?>
                            <p class="bbai-library-workspace-intro__hint"><?php esc_html_e('All images on this page meet your quality bar. You can still edit any ALT below.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <?php endif; ?>
                        </div>
                </header>

                <section id="bbai-alt-table" class="bbai-library-table-shell" data-bbai-scan-results-section aria-labelledby="bbai-library-results-title">
                    <div class="bbai-library-table-head bbai-library-table-head--bulk-only">
                        <label class="bbai-library-head-bulk bbai-library-head-bulk--selectall">
                            <input type="checkbox" class="bbai-checkbox bbai-select-all-table" aria-label="<?php esc_attr_e('Select all images on this page', 'beepbeep-ai-alt-text-generator'); ?>" title="<?php esc_attr_e('Select all on this page', 'beepbeep-ai-alt-text-generator'); ?>" />
                            <span class="bbai-library-head-bulk__label"><?php esc_html_e('Select all on this page', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </label>
                    </div>

                    <div class="bbai-table-wrap">
                        <div id="bbai-library-table-body" class="bbai-library-review-queue" role="list">
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
                                        $bbai_row_summary = __('Generate ALT text with AI or add it manually.', 'beepbeep-ai-alt-text-generator');
                                    } elseif ('weak' === $status) {
                                        $bbai_row_summary = !empty($bbai_analysis['issues'][0])
                                            ? (string) $bbai_analysis['issues'][0]
                                            : __('This description may be too generic or low quality.', 'beepbeep-ai-alt-text-generator');
                                    } else {
                                        $bbai_row_summary = $bbai_is_user_approved
                                            ? __('Reviewed and approved by you.', 'beepbeep-ai-alt-text-generator')
                                            : __('Looks good, but you can still review or edit it.', 'beepbeep-ai-alt-text-generator');
                                    }

                                    $bbai_row_action_primary_label = $bbai_has_alt ? __('Regenerate ALT', 'beepbeep-ai-alt-text-generator') : __('Generate ALT', 'beepbeep-ai-alt-text-generator');
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

                                    $bbai_regen_label = $bbai_has_alt ? __('Regenerate', 'beepbeep-ai-alt-text-generator') : __('Generate', 'beepbeep-ai-alt-text-generator');
                                    $bbai_regen_title = $bbai_limit_reached_state
                                        ? __('Upgrade to unlock AI regenerations', 'beepbeep-ai-alt-text-generator')
                                        : (!$bbai_has_alt
                                            ? __('Generate ALT text with AI', 'beepbeep-ai-alt-text-generator')
                                            : __('Regenerate ALT text with AI', 'beepbeep-ai-alt-text-generator'));
                                    $bbai_edit_title = __('Edit ALT text manually', 'beepbeep-ai-alt-text-generator');

                                    if (!$bbai_has_alt || 'missing' === $status) {
                                        $bbai_q1_class = 'bbai-library-card__quick-action--primary';
                                        $bbai_q1_action = 'regenerate-single';
                                        $bbai_q1_label = $bbai_regen_label;
                                        $bbai_q1_title = $bbai_regen_title;
                                        $bbai_q2_class = 'bbai-library-card__quick-action--secondary';
                                        $bbai_q2_action = 'edit-alt-inline';
                                        $bbai_q2_label = $bbai_row_edit_label;
                                        $bbai_q2_title = $bbai_edit_title;
                                    } elseif ('weak' === $status) {
                                        $bbai_q1_class = 'bbai-library-card__quick-action--primary';
                                        $bbai_q1_action = 'regenerate-single';
                                        $bbai_q1_label = __('Regenerate', 'beepbeep-ai-alt-text-generator');
                                        $bbai_q1_title = $bbai_regen_title;
                                        $bbai_q2_class = 'bbai-library-card__quick-action--secondary';
                                        $bbai_q2_action = 'edit-alt-inline';
                                        $bbai_q2_label = $bbai_row_edit_label;
                                        $bbai_q2_title = $bbai_edit_title;
                                    } else {
                                        $bbai_q1_class = 'bbai-library-card__quick-action--lead';
                                        $bbai_q1_action = 'edit-alt-inline';
                                        $bbai_q1_label = $bbai_row_edit_label;
                                        $bbai_q1_title = $bbai_edit_title;
                                        $bbai_q2_class = 'bbai-library-card__quick-action--ghost';
                                        $bbai_q2_action = 'regenerate-single';
                                        $bbai_q2_label = __('Regenerate', 'beepbeep-ai-alt-text-generator');
                                        $bbai_q2_title = $bbai_regen_title;
                                    }
                                    ?>
                                    <article class="bbai-library-row bbai-library-review-card"
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
                                        <div class="bbai-library-card__thumb">
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
                                        <div class="bbai-library-cell--alt-text bbai-library-card__main">
                                            <div class="bbai-library-card__col bbai-library-card__col--meta">
                                                <div class="bbai-library-card__meta-wrap">
                                                    <p class="bbai-library-card__filename" title="<?php echo esc_attr($bbai_display_filename); ?>"><?php echo esc_html($bbai_display_filename); ?></p>
                                                    <p class="bbai-library-card__meta"><?php echo esc_html($bbai_card_meta_line); ?></p>
                                                </div>
                                                <?php if ('' !== $bbai_score_hint) : ?>
                                                    <p class="bbai-library-card__score-hint"><?php echo esc_html($bbai_score_hint); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="bbai-library-card__col bbai-library-card__col--status">
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
                                                <div class="bbai-library-card__col bbai-library-card__col--alt">
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
                                                <div class="bbai-library-card__col bbai-library-card__col--actions">
                                                    <div class="bbai-library-card__tool-row bbai-library-card__action-cluster">
                                                        <div class="bbai-library-card__quick-actions" aria-label="<?php esc_attr_e('Row actions', 'beepbeep-ai-alt-text-generator'); ?>">
                                                            <button type="button"
                                                                class="bbai-library-card__quick-action <?php echo esc_attr($bbai_q1_class); ?><?php echo ($bbai_limit_reached_state && 'regenerate-single' === $bbai_q1_action) ? ' bbai-is-locked' : ''; ?>"
                                                                data-action="<?php echo esc_attr($bbai_q1_action); ?>"
                                                                data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"
                                                                title="<?php echo esc_attr($bbai_q1_title); ?>"
                                                                <?php if ('regenerate-single' === $bbai_q1_action) : ?>data-bbai-lock-preserve-label="1"<?php endif; ?>
                                                                <?php if ($bbai_limit_reached_state && 'regenerate-single' === $bbai_q1_action) : ?>data-bbai-action="open-upgrade" data-bbai-locked-cta="1" data-bbai-lock-reason="regenerate_single" data-bbai-locked-source="library-row-regenerate-quick" aria-disabled="true"<?php endif; ?>>
                                                                <?php echo esc_html($bbai_q1_label); ?>
                                                            </button>
                                                            <button type="button"
                                                                class="bbai-library-card__quick-action <?php echo esc_attr($bbai_q2_class); ?><?php echo ($bbai_limit_reached_state && 'regenerate-single' === $bbai_q2_action) ? ' bbai-is-locked' : ''; ?>"
                                                                data-action="<?php echo esc_attr($bbai_q2_action); ?>"
                                                                data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"
                                                                title="<?php echo esc_attr($bbai_q2_title); ?>"
                                                                <?php if ('regenerate-single' === $bbai_q2_action) : ?>data-bbai-lock-preserve-label="1"<?php endif; ?>
                                                                <?php if ($bbai_limit_reached_state && 'regenerate-single' === $bbai_q2_action) : ?>data-bbai-action="open-upgrade" data-bbai-locked-cta="1" data-bbai-lock-reason="regenerate_single" data-bbai-locked-source="library-row-regenerate-quick" aria-disabled="true"<?php endif; ?>>
                                                                <?php echo esc_html($bbai_q2_label); ?>
                                                            </button>
                                                        </div>
                                                        <details class="bbai-library-row-menu bbai-library-row-menu--cluster">
                                                            <summary class="bbai-library-row-menu__toggle" aria-label="<?php esc_attr_e('More actions', 'beepbeep-ai-alt-text-generator'); ?>">•••</summary>
                                                            <div class="bbai-library-row-menu__panel">
                                                                <button type="button" class="bbai-library-row-menu__item bbai-library-row-menu__item--tertiary" data-action="preview-image" data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"><?php esc_html_e('View image', 'beepbeep-ai-alt-text-generator'); ?></button>
                                                                <?php if ($bbai_has_alt) : ?>
                                                                    <button type="button" class="bbai-library-row-menu__item" data-action="copy-alt-text" data-alt-text="<?php echo esc_attr($bbai_clean_alt); ?>" data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"><?php esc_html_e('Copy ALT', 'beepbeep-ai-alt-text-generator'); ?></button>
                                                                <?php endif; ?>
                                                                <button type="button"
                                                                        class="bbai-library-row-menu__item<?php echo $bbai_limit_reached_state ? ' bbai-is-locked' : ''; ?>"
                                                                        data-action="regenerate-single"
                                                                        data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"
                                                                        data-bbai-lock-preserve-label="1"
                                                                        title="<?php echo esc_attr($bbai_limit_reached_state ? __('Upgrade to unlock AI regenerations', 'beepbeep-ai-alt-text-generator') : (!$bbai_has_alt ? __('Generate ALT text with AI', 'beepbeep-ai-alt-text-generator') : __('Regenerate ALT text with AI', 'beepbeep-ai-alt-text-generator'))); ?>"
                                                                        <?php if ($bbai_limit_reached_state) : ?>data-bbai-action="open-upgrade" data-bbai-locked-cta="1" data-bbai-lock-reason="regenerate_single" data-bbai-locked-source="library-row-regenerate" aria-disabled="true"<?php endif; ?>>
                                                                    <?php echo esc_html($bbai_row_action_primary_label); ?>
                                                                </button>
                                                                <?php if ($bbai_show_review_action) : ?>
                                                                    <button type="button" class="bbai-library-row-menu__item" data-action="approve-alt-inline" data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>">
                                                                        <?php esc_html_e('Mark reviewed', 'beepbeep-ai-alt-text-generator'); ?>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </details>
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
                    <nav class="bbai-pagination" aria-label="<?php esc_attr_e('ALT Library pagination', 'beepbeep-ai-alt-text-generator'); ?>">
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
                <section class="bbai-library-empty-state">
                    <div class="bbai-library-empty-state__icon" aria-hidden="true">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                            <path d="M4 5.5A1.5 1.5 0 015.5 4h13A1.5 1.5 0 0120 5.5v13a1.5 1.5 0 01-1.5 1.5h-13A1.5 1.5 0 014 18.5v-13z" stroke="currentColor" stroke-width="1.5"></path>
                            <path d="M8 15l2.5-2.5L13 15l3-3 2 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                            <circle cx="9" cy="9" r="1.2" fill="currentColor"></circle>
                        </svg>
                    </div>
                    <h2 class="bbai-library-empty-state__title"><?php esc_html_e('No images found', 'beepbeep-ai-alt-text-generator'); ?></h2>
                    <p class="bbai-library-empty-state__copy"><?php esc_html_e('Scan your media library to get started, then review generated ALT text here as your library grows.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    <div class="bbai-library-empty-state__actions">
                        <button type="button" class="bbai-btn bbai-btn-primary bbai-btn-sm" <?php echo $bbai_scan_action['attrs']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes assembled from escaped values. ?>><?php echo esc_html($bbai_scan_action['label']); ?></button>
                        <a href="<?php echo esc_url(admin_url('upload.php')); ?>" class="bbai-btn bbai-btn-secondary bbai-btn-sm"><?php esc_html_e('Open Media Library', 'beepbeep-ai-alt-text-generator'); ?></a>
                    </div>
                </section>
            <?php endif; ?>
        </main>
</div>
