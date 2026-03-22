<?php
/**
 * ALT Library premium workspace layout.
 *
 * Expects the data/query variables prepared in library-tab.php.
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_requested_status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
$bbai_requested_filter_map = [
    'all'          => 'all',
    'missing'      => 'missing',
    'optimized'    => 'optimized',
    'weak'         => 'weak',
    'needs_review' => 'weak',
    'needs-review' => 'weak',
];

if (isset($bbai_requested_filter_map[$bbai_requested_status])) {
    $bbai_default_review_filter = $bbai_requested_filter_map[$bbai_requested_status];
}

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
    $bbai_header_primary_action = $bbai_scan_new_uploads_action;
    $bbai_summary_primary_action = $bbai_build_action(
        [
            'label' => __('Review optimized images', 'beepbeep-ai-alt-text-generator'),
            'attrs' => 'data-bbai-filter-target="optimized"',
        ]
    );
    $bbai_task_primary_action = $bbai_scan_new_uploads_action;
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
$bbai_credits_used = $bbai_usage_used;
$bbai_credits_limit = $bbai_usage_limit;
$bbai_credits_remaining = $bbai_usage_remaining;
$bbai_is_healthy = 0 === $bbai_missing_count && 0 === $bbai_weak_count && $bbai_total_count > 0;
$bbai_is_low_credits = $bbai_credits_remaining < 10 && $bbai_credits_remaining > 0;
$bbai_is_out_of_credits = 0 === $bbai_credits_remaining;
$bbai_has_filters = $bbai_total_count > 0;
$bbai_has_search_query = false;
$bbai_settings_automation_url = admin_url('admin.php?page=bbai-settings#bbai-enable-on-upload');

$bbai_surface_automation_action = $bbai_is_pro
    ? $bbai_build_action(
        [
            'label' => __('Manage auto-optimization', 'beepbeep-ai-alt-text-generator'),
            'attrs' => 'href="' . esc_url($bbai_settings_automation_url) . '"',
        ]
    )
    : $bbai_build_action(
        [
            'label' => __('Enable automatic ALT with Pro', 'beepbeep-ai-alt-text-generator'),
            'attrs' => 'data-bbai-action="open-upgrade" data-bbai-locked-cta="1" data-bbai-lock-reason="automation" data-bbai-locked-source="library-summary-automation"',
        ]
    );

$bbai_usage_alert_action = [];
$bbai_usage_alert_state = '';
$bbai_usage_alert_title = '';
$bbai_usage_alert_copy = '';

if (!$bbai_is_pro && $bbai_is_out_of_credits) {
    $bbai_usage_alert_state = 'out';
    $bbai_usage_alert_title = __('You’ve used all monthly credits', 'beepbeep-ai-alt-text-generator');
    $bbai_usage_alert_copy = __('Upgrade to continue generating ALT text and automate future uploads.', 'beepbeep-ai-alt-text-generator');
    $bbai_usage_alert_action = $bbai_build_action(
        [
            'label' => __('Upgrade to continue', 'beepbeep-ai-alt-text-generator'),
            'attrs' => 'data-bbai-action="open-upgrade" data-bbai-locked-cta="1" data-bbai-lock-reason="generate_missing" data-bbai-locked-source="library-usage-alert-out"',
        ]
    );
} elseif (!$bbai_is_pro && $bbai_is_low_credits) {
    $bbai_usage_alert_state = 'low';
    $bbai_usage_alert_title = __('You’re running low on credits', 'beepbeep-ai-alt-text-generator');
    $bbai_usage_alert_copy = sprintf(
        _n('You have %s optimization left this month.', 'You have %s optimizations left this month.', $bbai_credits_remaining, 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($bbai_credits_remaining)
    );
    $bbai_usage_alert_action = $bbai_build_action(
        [
            'label' => __('Upgrade before uploads build up', 'beepbeep-ai-alt-text-generator'),
            'attrs' => 'data-bbai-action="open-upgrade" data-bbai-locked-cta="1" data-bbai-lock-reason="generate_missing" data-bbai-locked-source="library-usage-alert-low"',
        ]
    );
}

$bbai_surface_state = 'healthy';
$bbai_surface_icon = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path><path d="M22 4L12 14.01l-3-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
$bbai_surface_title = __('All images are optimized', 'beepbeep-ai-alt-text-generator');
$bbai_surface_copy = __('Your media library is fully covered right now. Future uploads can still introduce new ALT gaps if you do not rescan or automate them.', 'beepbeep-ai-alt-text-generator');
$bbai_surface_next_title = $bbai_is_pro
    ? __('Keep future uploads covered automatically', 'beepbeep-ai-alt-text-generator')
    : __('Stop new uploads from becoming manual cleanup', 'beepbeep-ai-alt-text-generator');
$bbai_surface_next_copy = $bbai_is_pro
    ? __('On-upload automation is available in Settings, and you can scan new uploads any time you want a fresh check.', 'beepbeep-ai-alt-text-generator')
    : __('Upgrade to Pro to generate ALT text automatically for future uploads and reduce repeat review work.', 'beepbeep-ai-alt-text-generator');
$bbai_surface_automation_copy = $bbai_is_pro
    ? __('Automation can handle fresh uploads while you review optimized text or export progress when needed.', 'beepbeep-ai-alt-text-generator')
    : __('Pro automates future uploads, scales better for larger libraries, and cuts repeat maintenance work.', 'beepbeep-ai-alt-text-generator');
$bbai_surface_primary_action = $bbai_scan_new_uploads_action;
$bbai_surface_secondary_action = $bbai_surface_automation_action;

if ($bbai_missing_count > 0) {
    $bbai_surface_state = 'missing';
    $bbai_surface_icon = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.8"></circle><path d="M12 7V12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path><circle cx="12" cy="16" r="1" fill="currentColor"></circle></svg>';
    $bbai_surface_title = sprintf(
        _n('%s image still needs ALT text', '%s images still need ALT text', $bbai_missing_count, 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($bbai_missing_count)
    );
    $bbai_surface_copy = __('Clear the missing descriptions first, then use automation to keep future uploads from rebuilding the backlog.', 'beepbeep-ai-alt-text-generator');
    $bbai_surface_next_title = __('Generate the missing ALT backlog', 'beepbeep-ai-alt-text-generator');
    $bbai_surface_next_copy = __('This is the fastest way to improve accessibility coverage across the library before you fine-tune weaker copy.', 'beepbeep-ai-alt-text-generator');
    $bbai_surface_automation_copy = $bbai_is_pro
        ? __('Once the backlog is clear, on-upload automation can keep new media covered without another manual sweep.', 'beepbeep-ai-alt-text-generator')
        : __('Pro can auto-generate ALT text on upload so future media does not come back as repeat manual work.', 'beepbeep-ai-alt-text-generator');
    $bbai_surface_primary_action = $bbai_build_action(
        [
            'label'       => __('Generate missing ALT', 'beepbeep-ai-alt-text-generator'),
            'action'      => 'generate-missing',
            'locked'      => true,
            'lock_reason' => 'generate_missing',
            'lock_source' => 'library-progress-primary',
        ]
    );
} elseif ($bbai_weak_count > 0) {
    $bbai_surface_state = 'weak';
    $bbai_surface_icon = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3L21 20H3L12 3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"></path><path d="M12 9V13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path><circle cx="12" cy="17" r="1" fill="currentColor"></circle></svg>';
    $bbai_surface_title = sprintf(
        _n('%s ALT description needs review', '%s ALT descriptions need review', $bbai_weak_count, 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($bbai_weak_count)
    );
    $bbai_surface_copy = __('Tighten the remaining descriptions now, then use automation and bulk tools to keep future uploads easier to manage.', 'beepbeep-ai-alt-text-generator');
    $bbai_surface_next_title = __('Review the remaining weak descriptions', 'beepbeep-ai-alt-text-generator');
    $bbai_surface_next_copy = __('Approve strong copy, edit the edge cases, or regenerate the weakest suggestions in bulk.', 'beepbeep-ai-alt-text-generator');
    $bbai_surface_automation_copy = $bbai_is_pro
        ? __('After this review pass, on-upload automation can keep new images from creating another queue.', 'beepbeep-ai-alt-text-generator')
        : __('Pro keeps future uploads from adding more manual cleanup while giving you faster bulk tools for larger libraries.', 'beepbeep-ai-alt-text-generator');
    $bbai_surface_primary_action = $bbai_build_action(
        [
            'label' => __('Review weak ALT', 'beepbeep-ai-alt-text-generator'),
            'attrs' => 'data-bbai-filter-target="weak"',
        ]
    );
}

$bbai_state = [
    'total_images'        => $bbai_cov_total,
    'optimized_count'     => $bbai_cov_optimized,
    'missing_alts'        => $bbai_cov_missing,
    'needs_review_count'  => $bbai_cov_needs_review,
    'has_scan_results'    => $bbai_cov_total > 0,
    'last_scan_timestamp' => time(),
];
?>

<div
    class="bbai-library-container bbai-library-workspace"
    data-bbai-library-workspace-root="1"
    data-bbai-current-page="alt-library"
    data-bbai-library-url="<?php echo esc_url(add_query_arg(['page' => 'bbai-library'], admin_url('admin.php'))); ?>"
    data-bbai-missing-library-url="<?php echo esc_url(add_query_arg(['page' => 'bbai-library', 'status' => 'missing'], admin_url('admin.php'))); ?>"
    data-bbai-empty-filter="<?php echo esc_attr__('No images match your current controls.', 'beepbeep-ai-alt-text-generator'); ?>"
    data-bbai-is-healthy="<?php echo $bbai_is_healthy ? 'true' : 'false'; ?>"
    data-bbai-is-low-credits="<?php echo $bbai_is_low_credits ? 'true' : 'false'; ?>"
    data-bbai-is-out-of-credits="<?php echo $bbai_is_out_of_credits ? 'true' : 'false'; ?>"
    data-bbai-is-pro-plan="<?php echo $bbai_is_pro ? 'true' : 'false'; ?>"
    data-bbai-has-filters="<?php echo $bbai_has_filters ? 'true' : 'false'; ?>"
    data-bbai-has-search-query="<?php echo $bbai_has_search_query ? 'true' : 'false'; ?>"
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
        max-width: 1180px;
        margin: 0 auto;
        color: var(--bbai-library-text);
    }

    .bbai-library-page-header {
        margin: 0 0 24px;
        align-items: center;
    }

    .bbai-library-page-header .bbai-page-header-content {
        gap: 8px;
    }

    .bbai-library-page-title {
        margin: 0;
        font-size: 30px;
        line-height: 1.05;
        letter-spacing: -0.03em;
        color: var(--bbai-library-text);
    }

    .bbai-library-page-copy {
        margin: 0;
        max-width: 620px;
        font-size: 14px;
        line-height: 1.65;
        color: var(--bbai-library-muted);
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
        gap: 18px;
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
        border-color: #93c5fd;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12), var(--bbai-library-card-shadow);
        animation: bbaiLibraryResultsPulse 1.6s ease;
    }

    @keyframes bbaiLibraryResultsPulse {
        0% {
            box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.24), var(--bbai-library-card-shadow);
        }

        100% {
            box-shadow: 0 0 0 10px rgba(59, 130, 246, 0), var(--bbai-library-card-shadow);
        }
    }

    .bbai-library-summary-card,
    .bbai-library-task-banner,
    .bbai-library-toolbar-card,
    .bbai-library-table-shell,
    .bbai-library-sidebar-card,
    .bbai-library-selection-bar {
        border: 1px solid var(--bbai-library-border);
        border-radius: 16px;
        background: #ffffff;
        box-shadow: var(--bbai-library-card-shadow);
    }

    .bbai-library-summary-card {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(280px, 420px);
        gap: 32px;
        padding: 28px;
        margin: 0 0 28px;
        background: var(--bbai-library-surface);
    }

    .bbai-library-summary-main {
        min-width: 0;
    }

    .bbai-library-summary-layout {
        display: flex;
        align-items: flex-start;
        gap: 18px;
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
        gap: 12px;
        margin-top: 18px;
    }

    .bbai-library-summary-stat {
        padding: 14px 16px;
        border-radius: 14px;
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
        margin-top: 6px;
        font-size: 28px;
        line-height: 1;
        font-weight: 700;
        letter-spacing: -0.03em;
        color: var(--bbai-library-text);
    }

    .bbai-library-summary-progress-wrap {
        margin-top: 18px;
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
        margin-top: 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        font-size: 13px;
        color: var(--bbai-library-muted);
    }

    .bbai-library-summary-foot strong {
        color: var(--bbai-library-text);
    }

    .bbai-library-summary-side {
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 18px;
        padding: 22px;
        border-radius: 16px;
        border: 1px solid rgba(37, 99, 235, 0.08);
        background: rgba(255, 255, 255, 0.78);
    }

    .bbai-library-summary-next {
        display: flex;
        flex-direction: column;
        gap: 10px;
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
        gap: 10px;
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
        flex-direction: row;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }

    .bbai-library-toolbar-card {
        position: sticky;
        top: 24px;
        z-index: 9;
        padding: 16px 18px;
        margin: 0 0 28px;
        background: rgba(255, 255, 255, 0.94);
        backdrop-filter: blur(12px);
    }

    .bbai-library-section-head {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 16px;
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
        gap: 16px;
        flex-wrap: wrap;
    }

    .bbai-library-toolbar__left {
        flex: 1 1 420px;
        min-width: 0;
    }

    .bbai-library-toolbar__right {
        flex: 1 1 420px;
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        flex-wrap: wrap;
    }

    .bbai-alt-review-filters {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        padding: 4px;
        border-radius: 999px;
        background: #f6f9fc;
        border: 1px solid #e7edf6;
    }

    .bbai-alt-review-filters__btn {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        border-radius: 999px;
        border: 1px solid transparent;
        background: transparent;
        color: #334155;
        font-size: 13px;
        line-height: 1;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.16s ease;
    }

    .bbai-alt-review-filters__btn:hover {
        background: #ffffff;
        border-color: #dbe4f0;
    }

    .bbai-alt-review-filters__btn--active {
        background: #0f172a;
        border-color: #0f172a;
        color: #ffffff;
        box-shadow: 0 10px 18px rgba(15, 23, 42, 0.14);
    }

    .bbai-alt-review-filters__btn--problem .bbai-alt-review-filters__count {
        color: var(--bbai-library-amber);
    }

    .bbai-alt-review-filters__btn--active .bbai-alt-review-filters__count {
        color: rgba(255, 255, 255, 0.78);
    }

    .bbai-alt-review-filters__count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 26px;
        padding: 4px 8px;
        border-radius: 999px;
        background: rgba(15, 23, 42, 0.06);
        font-size: 12px;
        line-height: 1;
        color: var(--bbai-library-muted);
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
    .bbai-alt-review-filters__btn:focus-visible,
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
        gap: 16px;
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
        margin-top: 16px;
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
        margin-top: 12px;
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
        top: 96px;
        z-index: 8;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        padding: 16px 18px;
        margin: 0 0 16px;
        background: rgba(255, 255, 255, 0.96);
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

    .bbai-library-table-shell {
        padding: 16px;
    }

    .bbai-table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .bbai-library-table-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 0 2px 14px;
    }

    .bbai-library-table-title {
        margin: 0;
        font-size: 18px;
        line-height: 1.2;
        letter-spacing: -0.02em;
        color: var(--bbai-library-text);
    }

    .bbai-library-table-meta {
        margin: 4px 0 0;
        font-size: 13px;
        line-height: 1.5;
        color: var(--bbai-library-muted);
    }

    .bbai-library-table {
        width: 100%;
        min-width: 980px;
        border-collapse: separate;
        border-spacing: 0 14px;
        margin: -14px 0 0;
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
        padding: 20px 16px;
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

    .bbai-library-cell--select {
        width: 52px;
        padding-top: 24px !important;
    }

    .bbai-library-cell--asset {
        width: 320px;
    }

    .bbai-library-cell--alt-text {
        width: 42%;
        min-width: 300px;
    }

    .bbai-library-cell--status {
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
        width: 76px;
        height: 76px;
        border-radius: 18px;
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
        gap: 8px;
        min-width: 0;
    }

    .bbai-library-info-name {
        margin: 0;
        font-size: 15px;
        line-height: 1.45;
        font-weight: 700;
        color: var(--bbai-library-text);
        word-break: break-word;
    }

    .bbai-library-info-meta,
    .bbai-library-info-updated {
        display: block;
        font-size: 12px;
        line-height: 1.55;
        color: var(--bbai-library-muted);
    }

    .bbai-library-review-block {
        display: flex;
        flex-direction: column;
        gap: 12px;
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
        min-height: 84px;
        padding: 16px;
        border-radius: 16px;
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
        gap: 14px;
        min-height: 100%;
    }

    .bbai-library-status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: fit-content;
        padding: 7px 12px;
        border-radius: 999px;
        font-size: 12px;
        line-height: 1;
        font-weight: 800;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .bbai-library-status-badge--optimized {
        background: var(--bbai-library-green-bg);
        color: #166534;
    }

    .bbai-library-status-badge--weak {
        background: var(--bbai-library-amber-bg);
        color: #b45309;
    }

    .bbai-library-status-badge--missing {
        background: var(--bbai-library-red-bg);
        color: #b91c1c;
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
        gap: 8px;
        min-height: 38px;
        padding: 8px 12px;
        border-radius: 12px;
        border: 1px solid #dce4f0;
        background: #ffffff;
        color: #334155;
        font-size: 12px;
        line-height: 1;
        font-weight: 700;
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
        margin-top: 18px;
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
        margin-bottom: 16px;
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
        margin-top: 22px;
    }

    .bbai-pagination {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        margin-top: 18px;
        padding: 16px 2px 0;
    }

    .bbai-pagination-info {
        font-size: 13px;
        line-height: 1.5;
        color: var(--bbai-library-muted);
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
        min-width: 40px;
        min-height: 40px;
        padding: 0 14px;
        border-radius: 12px;
        border: 1px solid #dce4f0;
        background: #ffffff;
        color: #334155;
        text-decoration: none;
        font-size: 13px;
        line-height: 1;
        font-weight: 700;
        transition: all 0.16s ease;
    }

    .bbai-pagination-btn:hover:not(.bbai-pagination-btn--current) {
        border-color: #c4d4e8;
        background: #f8fbff;
        transform: translateY(-1px);
    }

    .bbai-pagination-btn--current {
        background: #0f172a;
        border-color: #0f172a;
        color: #ffffff;
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
        .bbai-library-page-header,
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

        .bbai-library-page-title {
            font-size: 26px;
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
        gap: 18px;
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
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .bbai-library-summary-next-title {
        margin: 0;
        font-size: 20px;
        line-height: 1.25;
        letter-spacing: -.02em;
        color: var(--bbai-library-text);
    }

    .bbai-library-summary-automation {
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding: 14px 16px;
        border-radius: 14px;
        border: 1px solid #dbe7f4;
        background: #f8fbff;
    }

    .bbai-library-usage-alert {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
        padding: 14px 16px;
        border-radius: 14px;
        border: 1px solid #fcd34d;
        background: #fffbeb;
    }

    .bbai-library-usage-alert--out {
        border-color: #fecaca;
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
        top: 88px;
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
        gap: 8px;
        flex-wrap: wrap;
    }

    .bbai-library-info-meta {
        display: inline-flex;
        align-items: center;
        padding: 4px 8px;
        border-radius: 999px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        font-size: 11px;
        line-height: 1;
        color: #475569;
    }

    .bbai-library-alt-preview-card {
        display: flex;
        flex-direction: column;
        gap: 10px;
        padding: 15px 16px;
        border-radius: 16px;
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
        color: #2563eb;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
    }

    .bbai-library-alt-helper,
    .bbai-library-status-copy {
        margin: 0;
        font-size: 12px;
        line-height: 1.6;
        color: var(--bbai-library-muted);
    }

    .bbai-library-status-tags {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
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
        gap: 10px;
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
        margin-top: 14px;
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
    </style>

    <header class="bbai-page-header bbai-library-page-header">
        <div class="bbai-page-header-content">
            <h1 class="bbai-library-page-title"><?php esc_html_e('ALT Library', 'beepbeep-ai-alt-text-generator'); ?></h1>
            <p class="bbai-library-page-copy"><?php esc_html_e('Review, generate, and improve ALT text across your media library.', 'beepbeep-ai-alt-text-generator'); ?></p>
        </div>
    </header>

    <?php
    $bbai_queue_workflow_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/queue-workflow.php';
    if (file_exists($bbai_queue_workflow_partial)) {
        include $bbai_queue_workflow_partial;
    }
    ?>

    <section id="bbai-alt-coverage-card" class="bbai-library-summary-card" data-bbai-coverage-card data-bbai-library-surface data-state="<?php echo esc_attr($bbai_surface_state); ?>" data-bbai-free-plan-limit="<?php echo esc_attr((int) ($bbai_coverage['free_plan_limit'] ?? 50)); ?>">
        <div class="bbai-library-summary-main">
            <div class="bbai-library-summary-layout">
                <div class="bbai-library-summary-icon" data-bbai-library-surface-icon>
                    <?php echo $bbai_surface_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG markup defined in template. ?>
                </div>
                <div>
                    <p class="bbai-library-summary-eyebrow"><?php esc_html_e('Optimization Summary', 'beepbeep-ai-alt-text-generator'); ?></p>
                    <h2 class="bbai-library-summary-title" data-bbai-library-surface-title><?php echo esc_html($bbai_surface_title); ?></h2>
                    <p class="bbai-library-summary-copy" data-bbai-library-surface-copy><?php echo esc_html($bbai_surface_copy); ?></p>
                </div>
            </div>

            <div class="bbai-library-summary-stats">
                <div class="bbai-library-summary-stat bbai-library-summary-stat--optimized">
                    <span class="bbai-library-summary-stat-label"><?php esc_html_e('Optimized', 'beepbeep-ai-alt-text-generator'); ?></span>
                    <strong class="bbai-library-summary-stat-value" data-bbai-library-optimized><?php echo esc_html(number_format_i18n($bbai_optimized_count)); ?></strong>
                </div>
                <div class="bbai-library-summary-stat bbai-library-summary-stat--weak">
                    <span class="bbai-library-summary-stat-label"><?php esc_html_e('Weak ALT', 'beepbeep-ai-alt-text-generator'); ?></span>
                    <strong class="bbai-library-summary-stat-value" data-bbai-library-weak><?php echo esc_html(number_format_i18n($bbai_weak_count)); ?></strong>
                </div>
                <div class="bbai-library-summary-stat bbai-library-summary-stat--missing">
                    <span class="bbai-library-summary-stat-label"><?php esc_html_e('Missing ALT', 'beepbeep-ai-alt-text-generator'); ?></span>
                    <strong class="bbai-library-summary-stat-value" data-bbai-library-missing><?php echo esc_html(number_format_i18n($bbai_missing_count)); ?></strong>
                </div>
                <div class="bbai-library-summary-stat bbai-library-summary-stat--credits">
                    <span class="bbai-library-summary-stat-label"><?php esc_html_e('Credits left', 'beepbeep-ai-alt-text-generator'); ?></span>
                    <strong class="bbai-library-summary-stat-value" data-bbai-library-credits-remaining><?php echo esc_html(number_format_i18n($bbai_credits_remaining)); ?></strong>
                </div>
            </div>

            <div class="bbai-library-summary-progress-wrap">
                <div class="bbai-library-summary-progress" role="progressbar" data-bbai-library-progressbar aria-valuenow="<?php echo esc_attr($bbai_cov_opt_pct); ?>" aria-valuemin="0" aria-valuemax="100">
                    <span class="bbai-library-summary-progress__optimized" data-bbai-library-progress-optimized style="flex-basis: <?php echo esc_attr($bbai_cov_opt_pct); ?>%;"></span>
                    <span class="bbai-library-summary-progress__weak" data-bbai-library-progress-weak style="flex-basis: <?php echo esc_attr($bbai_cov_review_pct); ?>%;"></span>
                    <span class="bbai-library-summary-progress__missing" data-bbai-library-progress-missing style="flex-basis: <?php echo esc_attr($bbai_cov_miss_pct); ?>%;"></span>
                </div>
            </div>

            <div class="bbai-library-summary-foot">
                <span data-bbai-library-progress-foot><?php esc_html_e('Use scan, filters, and bulk actions to keep current and future uploads covered.', 'beepbeep-ai-alt-text-generator'); ?></span>
                <strong data-bbai-coverage-score-inline><?php echo esc_html(sprintf(__('%s%% optimized', 'beepbeep-ai-alt-text-generator'), number_format_i18n($bbai_cov_opt_pct))); ?></strong>
            </div>
        </div>

        <aside class="bbai-library-summary-side" data-bbai-library-usage>
            <div class="bbai-library-summary-next">
                <p class="bbai-library-summary-next-label"><?php esc_html_e('Next best step', 'beepbeep-ai-alt-text-generator'); ?></p>
                <h3 class="bbai-library-summary-next-title" data-bbai-library-surface-next-title><?php echo esc_html($bbai_surface_next_title); ?></h3>
                <p class="bbai-library-summary-next-copy" data-bbai-library-surface-status><?php echo esc_html($bbai_surface_next_copy); ?></p>
            </div>

            <div class="bbai-library-summary-automation">
                <p class="bbai-library-summary-next-label"><?php esc_html_e('Future coverage', 'beepbeep-ai-alt-text-generator'); ?></p>
                <p class="bbai-library-summary-next-copy" data-bbai-library-automation-copy><?php echo esc_html($bbai_surface_automation_copy); ?></p>
            </div>

            <div class="bbai-library-summary-usage">
                <p class="bbai-library-summary-usage-line" data-bbai-library-usage-line><strong><?php echo esc_html($bbai_usage_line); ?></strong></p>
                <div class="bbai-library-summary-usage-bar" role="progressbar" data-bbai-library-usage-progressbar aria-valuenow="<?php echo esc_attr($bbai_usage_pct); ?>" aria-valuemin="0" aria-valuemax="100">
                    <span class="bbai-library-summary-usage-fill" data-bbai-library-usage-progress style="width: <?php echo esc_attr($bbai_usage_pct); ?>%;"></span>
                </div>
                <p class="bbai-library-summary-usage-copy" data-bbai-library-usage-copy><?php echo esc_html($bbai_usage_copy); ?></p>
            </div>

            <?php if ('' !== $bbai_usage_alert_state) : ?>
                <div class="bbai-library-usage-alert bbai-library-usage-alert--<?php echo esc_attr($bbai_usage_alert_state); ?>" data-bbai-library-usage-alert data-state="<?php echo esc_attr($bbai_usage_alert_state); ?>">
                    <div class="bbai-library-usage-alert__copy">
                        <strong data-bbai-library-usage-alert-title><?php echo esc_html($bbai_usage_alert_title); ?></strong>
                        <span data-bbai-library-usage-alert-copy><?php echo esc_html($bbai_usage_alert_copy); ?></span>
                    </div>
                    <?php if (!empty($bbai_usage_alert_action)) : ?>
                        <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" data-bbai-library-usage-alert-action <?php echo $bbai_usage_alert_action['attrs']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes assembled from escaped values. ?>><?php echo esc_html($bbai_usage_alert_action['label']); ?></button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="bbai-library-summary-actions">
                <button type="button" class="bbai-btn bbai-btn-primary bbai-btn-sm<?php echo $bbai_surface_primary_action['is_locked'] ? ' bbai-is-locked' : ''; ?>" data-bbai-library-surface-action <?php echo $bbai_surface_primary_action['attrs']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes assembled from escaped values. ?>><?php echo esc_html($bbai_surface_primary_action['label']); ?></button>
                <?php if (!empty($bbai_surface_secondary_action)) : ?>
                    <?php $bbai_surface_secondary_tag = false !== strpos($bbai_surface_secondary_action['attrs'], 'href=') ? 'a' : 'button'; ?>
                    <<?php echo esc_html($bbai_surface_secondary_tag); ?>
                        class="bbai-btn bbai-btn-secondary bbai-btn-sm<?php echo !empty($bbai_surface_secondary_action['is_locked']) ? ' bbai-is-locked' : ''; ?>"
                        data-bbai-library-secondary-action
                        <?php echo 'button' === $bbai_surface_secondary_tag ? 'type="button"' : ''; ?>
                        <?php echo $bbai_surface_secondary_action['attrs']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes assembled from escaped values. ?>
                    ><?php echo esc_html($bbai_surface_secondary_action['label']); ?></<?php echo esc_html($bbai_surface_secondary_tag); ?>>
                <?php endif; ?>
            </div>
        </aside>
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

    <section class="bbai-library-toolbar-card" aria-labelledby="bbai-library-filters-title">
        <div class="bbai-library-section-head">
            <div>
                <h2 id="bbai-library-filters-title" class="bbai-library-section-title"><?php esc_html_e('Library controls', 'beepbeep-ai-alt-text-generator'); ?></h2>
                <p class="bbai-library-section-copy"><?php esc_html_e('Filter the review queue, search filenames or ALT text, sort the page, and run bulk actions from one control bar.', 'beepbeep-ai-alt-text-generator'); ?></p>
            </div>
        </div>

        <div class="bbai-library-toolbar" id="bbai-library-toolbar" aria-live="polite">
            <div class="bbai-library-toolbar__left">
                <div id="bbai-review-filter-tabs" class="bbai-alt-review-filters" data-bbai-default-filter="<?php echo esc_attr($bbai_default_review_filter); ?>">
                    <button type="button" class="bbai-alt-review-filters__btn<?php echo $bbai_default_review_filter === 'all' ? ' bbai-alt-review-filters__btn--active' : ''; ?>" data-filter="all" data-bbai-filter-label="<?php esc_attr_e('All', 'beepbeep-ai-alt-text-generator'); ?>" aria-pressed="<?php echo $bbai_default_review_filter === 'all' ? 'true' : 'false'; ?>">
                        <span class="bbai-alt-review-filters__label"><?php esc_html_e('All', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <span class="bbai-alt-review-filters__count"><?php echo esc_html(number_format_i18n($bbai_total_count)); ?></span>
                    </button>
                    <button type="button" class="bbai-alt-review-filters__btn<?php echo $bbai_default_review_filter === 'missing' ? ' bbai-alt-review-filters__btn--active' : ''; ?><?php echo $bbai_missing_count > 0 ? ' bbai-alt-review-filters__btn--problem' : ''; ?>" data-filter="missing" data-bbai-filter-label="<?php esc_attr_e('Missing ALT', 'beepbeep-ai-alt-text-generator'); ?>" aria-pressed="<?php echo $bbai_default_review_filter === 'missing' ? 'true' : 'false'; ?>">
                        <span class="bbai-alt-review-filters__label"><?php esc_html_e('Missing ALT', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <span class="bbai-alt-review-filters__count"><?php echo esc_html(number_format_i18n($bbai_missing_count)); ?></span>
                    </button>
                    <button type="button" class="bbai-alt-review-filters__btn<?php echo $bbai_default_review_filter === 'weak' ? ' bbai-alt-review-filters__btn--active' : ''; ?><?php echo $bbai_weak_count > 0 ? ' bbai-alt-review-filters__btn--problem' : ''; ?>" data-filter="weak" data-bbai-filter-label="<?php esc_attr_e('Weak ALT', 'beepbeep-ai-alt-text-generator'); ?>" aria-pressed="<?php echo $bbai_default_review_filter === 'weak' ? 'true' : 'false'; ?>">
                        <span class="bbai-alt-review-filters__label"><?php esc_html_e('Weak ALT', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <span class="bbai-alt-review-filters__count"><?php echo esc_html(number_format_i18n($bbai_weak_count)); ?></span>
                    </button>
                    <button type="button" class="bbai-alt-review-filters__btn<?php echo $bbai_default_review_filter === 'optimized' ? ' bbai-alt-review-filters__btn--active' : ''; ?>" data-filter="optimized" data-bbai-filter-label="<?php esc_attr_e('Optimized', 'beepbeep-ai-alt-text-generator'); ?>" aria-pressed="<?php echo $bbai_default_review_filter === 'optimized' ? 'true' : 'false'; ?>">
                        <span class="bbai-alt-review-filters__label"><?php esc_html_e('Optimized', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <span class="bbai-alt-review-filters__count"><?php echo esc_html(number_format_i18n($bbai_optimized_count)); ?></span>
                    </button>
                </div>
            </div>

            <div class="bbai-library-toolbar__right">
                <div class="bbai-library-toolbar__controls">
                    <label class="bbai-library-search" for="bbai-library-search">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                            <circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.5"></circle>
                            <path d="M11 11L14 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                        </svg>
                        <input type="text" id="bbai-library-search" placeholder="<?php esc_attr_e('Search by filename or ALT text', 'beepbeep-ai-alt-text-generator'); ?>" />
                    </label>

                    <label class="bbai-sr-only" for="bbai-library-sort"><?php esc_html_e('Sort images', 'beepbeep-ai-alt-text-generator'); ?></label>
                    <select id="bbai-library-sort" class="bbai-library-select">
                        <option value="recently-added"><?php esc_html_e('Recently added', 'beepbeep-ai-alt-text-generator'); ?></option>
                        <option value="needs-attention"><?php esc_html_e('Needs attention', 'beepbeep-ai-alt-text-generator'); ?></option>
                        <option value="filename"><?php esc_html_e('Filename', 'beepbeep-ai-alt-text-generator'); ?></option>
                        <option value="file-size"><?php esc_html_e('File size', 'beepbeep-ai-alt-text-generator'); ?></option>
                    </select>

                    <label class="bbai-sr-only" for="bbai-library-bulk-action"><?php esc_html_e('Bulk action', 'beepbeep-ai-alt-text-generator'); ?></label>
                    <select id="bbai-library-bulk-action" class="bbai-library-select">
                        <option value=""><?php esc_html_e('Bulk actions', 'beepbeep-ai-alt-text-generator'); ?></option>
                        <option value="generate-selected"><?php esc_html_e('Generate ALT for selected', 'beepbeep-ai-alt-text-generator'); ?></option>
                        <option value="regenerate-selected"><?php esc_html_e('Regenerate ALT for selected', 'beepbeep-ai-alt-text-generator'); ?></option>
                        <option value="mark-reviewed"><?php esc_html_e('Mark selected reviewed', 'beepbeep-ai-alt-text-generator'); ?></option>
                        <option value="export-alt-text"><?php esc_html_e('Export selection', 'beepbeep-ai-alt-text-generator'); ?></option>
                    </select>

                    <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" id="bbai-library-apply-bulk" data-action="apply-bulk-selection" disabled aria-disabled="true"><?php esc_html_e('Apply bulk action', 'beepbeep-ai-alt-text-generator'); ?></button>
                    <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" data-action="select-all-visible"><?php esc_html_e('Select visible', 'beepbeep-ai-alt-text-generator'); ?></button>
                    <?php if ($bbai_is_pro) : ?>
                        <a href="<?php echo esc_url($bbai_settings_automation_url); ?>" class="bbai-btn bbai-btn-secondary bbai-btn-sm bbai-library-toolbar__upgrade"><?php esc_html_e('Automation settings', 'beepbeep-ai-alt-text-generator'); ?></a>
                    <?php else : ?>
                        <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm bbai-library-toolbar__upgrade" data-bbai-action="open-upgrade" data-bbai-locked-cta="1" data-bbai-lock-reason="automation" data-bbai-locked-source="library-controls-automation"><?php esc_html_e('Automate future uploads', 'beepbeep-ai-alt-text-generator'); ?></button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <main class="bbai-library-main">
            <?php if (!empty($bbai_all_images)) : ?>
                <div class="bbai-library-selection-bar" id="bbai-library-selection-bar" aria-live="polite" aria-hidden="false">
                    <div class="bbai-library-selection-bar__summary">
                        <label class="bbai-library-selection-bar__lead">
                            <input type="checkbox" id="bbai-select-all" class="bbai-checkbox" aria-label="<?php esc_attr_e('Select all images on this page', 'beepbeep-ai-alt-text-generator'); ?>" />
                            <?php esc_html_e('Select page', 'beepbeep-ai-alt-text-generator'); ?>
                        </label>
                        <div class="bbai-library-selection-bar__meta">
                            <span class="bbai-library-selection-bar__count" data-bbai-selected-count>0 <?php esc_html_e('images selected', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <span class="bbai-library-selection-bar__credits" data-bbai-selected-credits><?php esc_html_e('Up to 0 credits for AI actions', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <?php if ($bbai_is_pro) : ?>
                                <span class="bbai-library-selection-bar__plan"><?php esc_html_e('Auto-optimization is available in Settings for future uploads.', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <?php else : ?>
                                <span class="bbai-library-selection-bar__plan"><?php esc_html_e('Pro automates future uploads and reduces repeat cleanup work.', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="bbai-library-selection-bar__actions">
                        <button type="button" class="bbai-btn bbai-btn-primary bbai-btn-sm<?php echo $bbai_limit_reached_state ? ' bbai-is-locked' : ''; ?>" id="bbai-batch-generate" data-action="generate-selected" data-bbai-lock-preserve-label="1"<?php if ($bbai_limit_reached_state) : ?> data-bbai-action="open-upgrade" data-bbai-locked-cta="1" data-bbai-lock-reason="generate_missing" data-bbai-locked-source="library-selection-bar-generate" aria-disabled="true"<?php endif; ?>><?php esc_html_e('Generate ALT', 'beepbeep-ai-alt-text-generator'); ?></button>
                        <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm<?php echo $bbai_limit_reached_state ? ' bbai-is-locked' : ''; ?>" id="bbai-batch-regenerate" data-action="regenerate-selected" data-bbai-lock-preserve-label="1"<?php if ($bbai_limit_reached_state) : ?> data-bbai-action="open-upgrade" data-bbai-locked-cta="1" data-bbai-lock-reason="reoptimize_all" data-bbai-locked-source="library-selection-bar-regenerate" aria-disabled="true"<?php endif; ?>><?php esc_html_e('Bulk regenerate', 'beepbeep-ai-alt-text-generator'); ?></button>
                        <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" id="bbai-batch-reviewed" data-action="mark-reviewed"><?php esc_html_e('Mark reviewed', 'beepbeep-ai-alt-text-generator'); ?></button>
                        <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" id="bbai-batch-export" data-action="export-alt-text"><?php esc_html_e('Export report', 'beepbeep-ai-alt-text-generator'); ?></button>
                        <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" id="bbai-batch-clear" data-action="clear-selection"><?php esc_html_e('Clear', 'beepbeep-ai-alt-text-generator'); ?></button>
                    </div>
                </div>

                <section id="bbai-alt-table" class="bbai-library-table-shell" data-bbai-scan-results-section aria-labelledby="bbai-library-results-title">
                    <div class="bbai-library-table-head">
                        <div>
                            <h2 id="bbai-library-results-title" class="bbai-library-table-title" data-bbai-results-heading tabindex="-1"><?php esc_html_e('ALT review workspace', 'beepbeep-ai-alt-text-generator'); ?></h2>
                            <p class="bbai-library-table-meta">
                                <?php
                                $bbai_start = $bbai_offset + 1;
                                $bbai_end = min($bbai_offset + $bbai_per_page, $bbai_total_count);
                                printf(
                                    /* translators: 1: start, 2: end, 3: total images */
                                    esc_html__('Showing %1$s-%2$s of %3$s images on this page', 'beepbeep-ai-alt-text-generator'),
                                    esc_html(number_format_i18n($bbai_start)),
                                    esc_html(number_format_i18n($bbai_end)),
                                    esc_html(number_format_i18n($bbai_total_count))
                                );
                                ?>
                            </p>
                        </div>
                    </div>

                    <div class="bbai-table-wrap">
                        <table class="bbai-table bbai-library-table bbai-saas-table">
                            <thead>
                                <tr>
                                    <th class="bbai-library-col-select">
                                        <input type="checkbox" class="bbai-checkbox bbai-select-all-table" aria-label="<?php esc_attr_e('Select all images on this page', 'beepbeep-ai-alt-text-generator'); ?>" />
                                    </th>
                                    <th class="bbai-library-col-image"><?php esc_html_e('Image', 'beepbeep-ai-alt-text-generator'); ?></th>
                                    <th class="bbai-library-col-alt"><?php esc_html_e('ALT Preview', 'beepbeep-ai-alt-text-generator'); ?></th>
                                    <th class="bbai-library-col-status"><?php esc_html_e('Status & Actions', 'beepbeep-ai-alt-text-generator'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="bbai-library-table-body">
                                <?php foreach ($bbai_all_images as $bbai_image) : ?>
                                    <?php
                                    $bbai_attachment_id = $bbai_image->ID;
                                    $bbai_current_alt = $bbai_image->alt_text ?? '';
                                    $bbai_clean_alt = is_string($bbai_current_alt) ? trim($bbai_current_alt) : '';
                                    $bbai_has_alt = '' !== $bbai_clean_alt;

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
                                    if (is_string($bbai_display_filename) && strlen($bbai_display_filename) > 40) {
                                        $bbai_display_filename = wp_html_excerpt($bbai_display_filename, 37, '...');
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

                                    $status = 'missing';
                                    $bbai_status_label = __('Missing', 'beepbeep-ai-alt-text-generator');
                                    $bbai_quality_class = 'poor';
                                    $bbai_quality_label = __('Poor', 'beepbeep-ai-alt-text-generator');
                                    $bbai_quality_score = 0;
                                    $bbai_analysis = null;
                                    $bbai_is_user_approved = false;

                                    if ($bbai_has_alt) {
                                        $bbai_analysis = method_exists($this, 'evaluate_alt_health')
                                            ? $this->evaluate_alt_health($bbai_attachment_id, $bbai_clean_alt)
                                            : null;

                                        $bbai_is_user_approved = !empty($bbai_analysis['user_approved']);
                                        $bbai_quality_score = function_exists('bbai_calculate_alt_quality_score')
                                            ? bbai_calculate_alt_quality_score($bbai_clean_alt)
                                            : ((is_array($bbai_analysis) && isset($bbai_analysis['score'])) ? (int) $bbai_analysis['score'] : 50);

                                        if ($bbai_quality_score >= 90) {
                                            $bbai_quality_class = 'excellent';
                                            $bbai_quality_label = __('Excellent', 'beepbeep-ai-alt-text-generator');
                                        } elseif ($bbai_quality_score >= 80) {
                                            $bbai_quality_class = 'good';
                                            $bbai_quality_label = __('Good', 'beepbeep-ai-alt-text-generator');
                                        } elseif ($bbai_quality_score >= 60) {
                                            $bbai_quality_class = 'needs-review';
                                            $bbai_quality_label = __('Needs review', 'beepbeep-ai-alt-text-generator');
                                        }

                                        if ($bbai_is_user_approved || in_array($bbai_quality_class, ['excellent', 'good'], true)) {
                                            $status = 'optimized';
                                            $bbai_status_label = __('Optimized', 'beepbeep-ai-alt-text-generator');
                                        } else {
                                            $status = 'weak';
                                            $bbai_status_label = __('Needs review', 'beepbeep-ai-alt-text-generator');
                                        }
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
                                        if ('excellent' === $bbai_quality_class) {
                                            $bbai_quality_tooltip = __('ALT text is descriptive and SEO-friendly', 'beepbeep-ai-alt-text-generator');
                                        } elseif ('good' === $bbai_quality_class) {
                                            $bbai_quality_tooltip = __('ALT text is clear and descriptive', 'beepbeep-ai-alt-text-generator');
                                        } elseif ('needs-review' === $bbai_quality_class) {
                                            $bbai_quality_tooltip = __('ALT text could use more descriptive detail', 'beepbeep-ai-alt-text-generator');
                                        } else {
                                            $bbai_quality_tooltip = __('ALT text is too short or lacks descriptive detail', 'beepbeep-ai-alt-text-generator');
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
                                    $bbai_row_edit_label = __('Edit ALT', 'beepbeep-ai-alt-text-generator');
                                    $bbai_show_review_action = 'weak' === $status && !$bbai_is_user_approved;

                                    $bbai_row_state_rank = 'missing' === $status ? 0 : ('weak' === $status ? 1 : 2);
                                    ?>
                                    <tr class="bbai-library-row"
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
                                        data-quality-tooltip="<?php echo esc_attr($bbai_quality_tooltip); ?>">
                                        <td class="bbai-library-cell--select">
                                            <input type="checkbox" class="bbai-checkbox bbai-library-row-check bbai-image-checkbox" value="<?php echo esc_attr($bbai_attachment_id); ?>" data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>" aria-label="<?php printf(esc_attr__('Select image %s', 'beepbeep-ai-alt-text-generator'), esc_attr($bbai_image->post_title)); ?>" />
                                        </td>
                                        <td class="bbai-library-cell--asset">
                                            <div class="bbai-library-asset">
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

                                                <div class="bbai-library-info-cell">
                                                    <p class="bbai-library-info-name" title="<?php echo esc_attr($bbai_filename); ?>"><?php echo esc_html($bbai_display_filename); ?></p>
                                                    <div class="bbai-library-info-meta-grid">
                                                        <?php if (!empty($bbai_file_extension)) : ?>
                                                            <span class="bbai-library-info-meta"><?php echo esc_html($bbai_file_extension); ?></span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($bbai_file_dimensions)) : ?>
                                                            <span class="bbai-library-info-meta"><?php echo esc_html($bbai_file_dimensions); ?></span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($bbai_file_size)) : ?>
                                                            <span class="bbai-library-info-meta"><?php echo esc_html($bbai_file_size); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <span class="bbai-library-info-updated"><?php echo esc_html(sprintf(__('Updated %s', 'beepbeep-ai-alt-text-generator'), $bbai_modified_date)); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="bbai-library-cell--alt-text">
                                            <div class="bbai-library-review-block">
                                                <div class="bbai-library-review-label">
                                                    <?php esc_html_e('ALT preview', 'beepbeep-ai-alt-text-generator'); ?>
                                                    <span class="bbai-library-review-tip" data-bbai-tooltip="<?php esc_attr_e('ALT text should describe the image clearly for accessibility.', 'beepbeep-ai-alt-text-generator'); ?>" data-bbai-tooltip-position="top" tabindex="0">i</span>
                                                </div>
                                                <div class="bbai-library-alt-preview-card<?php echo $bbai_has_long_alt_preview ? ' bbai-library-alt-preview-card--collapsible' : ''; ?>" data-bbai-alt-preview-card>
                                                    <?php if ($bbai_has_alt) : ?>
                                                        <p id="<?php echo esc_attr($bbai_alt_preview_id); ?>" class="bbai-alt-text-preview" title="<?php echo esc_attr($bbai_clean_alt); ?>"><?php echo esc_html($bbai_alt_preview); ?></p>
                                                        <?php if ($bbai_has_long_alt_preview) : ?>
                                                            <button type="button" class="bbai-library-alt-expand" data-action="toggle-alt-preview" aria-expanded="false" aria-controls="<?php echo esc_attr($bbai_alt_preview_id); ?>"><?php esc_html_e('Show more', 'beepbeep-ai-alt-text-generator'); ?></button>
                                                        <?php endif; ?>
                                                    <?php else : ?>
                                                        <span class="bbai-alt-text-missing"><?php esc_html_e('No ALT text', 'beepbeep-ai-alt-text-generator'); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="bbai-library-alt-helper"><?php echo esc_html($bbai_row_summary); ?></p>
                                            </div>
                                        </td>
                                        <td class="bbai-library-cell--status">
                                            <div class="bbai-library-status-stack">
                                                <div class="bbai-library-status-tags">
                                                    <span class="bbai-library-status-badge bbai-library-status-badge--<?php echo esc_attr($status); ?>"><?php echo esc_html($bbai_status_label); ?></span>
                                                    <?php if ('ai' === $bbai_ai_source) : ?>
                                                        <span class="bbai-library-status-badge bbai-library-status-badge--secondary bbai-library-status-badge--ai"><?php esc_html_e('AI generated', 'beepbeep-ai-alt-text-generator'); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($bbai_is_user_approved) : ?>
                                                        <span class="bbai-library-status-badge bbai-library-status-badge--secondary bbai-library-status-badge--reviewed"><?php esc_html_e('Reviewed', 'beepbeep-ai-alt-text-generator'); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="bbai-library-status-copy"><?php echo esc_html($bbai_row_summary); ?></p>
                                                <div class="bbai-library-actions">
                                                    <div class="bbai-library-action-group">
                                                        <button type="button" class="bbai-row-action-btn bbai-row-action-btn--primary<?php echo $bbai_limit_reached_state ? ' bbai-is-locked' : ''; ?>" data-action="regenerate-single" data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>" data-bbai-lock-preserve-label="1" title="<?php echo esc_attr(!$bbai_has_alt ? __('Generate ALT text with AI', 'beepbeep-ai-alt-text-generator') : __('Regenerate ALT text with AI', 'beepbeep-ai-alt-text-generator')); ?>"<?php if ($bbai_limit_reached_state) : ?> data-bbai-action="open-upgrade" data-bbai-locked-cta="1" data-bbai-lock-reason="regenerate_single" data-bbai-locked-source="library-row-regenerate" aria-disabled="true"<?php endif; ?>><?php echo esc_html($bbai_row_action_primary_label); ?></button>
                                                        <button type="button" class="bbai-row-action-btn" data-action="edit-alt-inline" data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"><?php echo esc_html($bbai_row_edit_label); ?></button>
                                                        <?php if ($bbai_show_review_action) : ?>
                                                            <button type="button" class="bbai-row-action-btn bbai-row-action-btn--review" data-action="approve-alt-inline" data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"><?php esc_html_e('Mark reviewed', 'beepbeep-ai-alt-text-generator'); ?></button>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="bbai-library-action-group bbai-library-action-group--secondary">
                                                        <button type="button" class="bbai-row-action-btn bbai-row-action-btn--quiet" data-action="preview-image" data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"><?php esc_html_e('View image', 'beepbeep-ai-alt-text-generator'); ?></button>
                                                        <button type="button" class="bbai-row-action-btn bbai-row-action-btn--quiet" data-action="copy-alt-text" data-alt-text="<?php echo esc_attr($bbai_clean_alt); ?>" data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"<?php echo $bbai_has_alt ? '' : ' disabled aria-disabled="true"'; ?>><?php esc_html_e('Copy ALT text', 'beepbeep-ai-alt-text-generator'); ?></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <?php if ($bbai_total_pages > 1) : ?>
                    <nav class="bbai-pagination" aria-label="<?php esc_attr_e('ALT Library pagination', 'beepbeep-ai-alt-text-generator'); ?>">
                        <span class="bbai-pagination-info">
                            <?php
                            printf(
                                /* translators: 1: start number, 2: end number, 3: total count */
                                esc_html__('Showing %1$s-%2$s of %3$s images', 'beepbeep-ai-alt-text-generator'),
                                esc_html(number_format_i18n($bbai_start)),
                                esc_html(number_format_i18n($bbai_end)),
                                esc_html(number_format_i18n($bbai_total_count))
                            );
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
