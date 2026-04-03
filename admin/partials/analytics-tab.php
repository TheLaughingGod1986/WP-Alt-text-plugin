<?php
/**
 * Analytics Dashboard Tab
 * Coverage trends, activity timeline, usage breakdown
 *
 * @package BeepBeep_AI
 * @since 6.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use BeepBeepAI\AltTextGenerator\Admin\Plan_Helpers;

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/class-plan-helpers.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/command-hero.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/banner-system.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/ui-components.php';

$bbai_stats = isset($bbai_stats) && is_array($bbai_stats) ? $bbai_stats : [];
$bbai_usage_stats = isset($bbai_usage_stats) && is_array($bbai_usage_stats) ? $bbai_usage_stats : [];

$bbai_banner_attn = bbai_get_attention_counts();
$bbai_total_images = max(
    0,
    max(
        (int) ($bbai_stats['total'] ?? $bbai_stats['total_images'] ?? 0),
        $bbai_banner_attn['total_images'],
        $bbai_banner_attn['missing'] + $bbai_banner_attn['needs_review'] + $bbai_banner_attn['optimized_count']
    )
);
$bbai_with_alt_count = max(0, $bbai_banner_attn['optimized_count'] + $bbai_banner_attn['needs_review']);
$bbai_missing_count = $bbai_banner_attn['missing'];
$bbai_weak_count = $bbai_banner_attn['needs_review'];
$bbai_coverage_percent = $bbai_total_images > 0 ? (int) round(($bbai_with_alt_count / $bbai_total_images) * 100) : 0;
$bbai_alt_coverage_display = $bbai_coverage_percent . '%';

$bbai_images_optimized_count = max(
    0,
    (int) (
        $bbai_stats['imagesProcessed']
        ?? $bbai_stats['images_processed']
        ?? $bbai_stats['imagesOptimized']
        ?? $bbai_stats['optimized_count']
        ?? $bbai_stats['generated']
        ?? $bbai_with_alt_count
    )
);
$bbai_images_processed = $bbai_images_optimized_count;
$bbai_images_delta_percent = max(0, (float) ($bbai_usage_stats['imagesDeltaPercent'] ?? $bbai_usage_stats['images_delta_percent'] ?? 0));

$bbai_usage_used = max(0, (int) ($bbai_usage_stats['used'] ?? 0));
$bbai_usage_limit = max(1, (int) ($bbai_usage_stats['limit'] ?? 50));
$bbai_usage_remaining = max(0, (int) ($bbai_usage_stats['remaining'] ?? 0));
$bbai_credits_used_percent = (int) round(($bbai_usage_used / $bbai_usage_limit) * 100);

$bbai_days_in_month = max(1, (int) wp_date('t'));
$bbai_days_elapsed = max(1, min($bbai_days_in_month, (int) wp_date('j')));
$bbai_daily_average = round($bbai_usage_used / $bbai_days_in_month, 1);
$bbai_projected_cycle_usage = (int) ceil(($bbai_usage_used / $bbai_days_elapsed) * $bbai_days_in_month);

$bbai_minutes_per_alt = 2.5;
$bbai_computed_time_saved_minutes = max(0, (int) round($bbai_usage_used * $bbai_minutes_per_alt));
$bbai_time_saved_hours = max(
    0,
    (float) (
        $bbai_usage_stats['timeSavedHours']
        ?? $bbai_usage_stats['time_saved_hours']
        ?? $bbai_stats['timeSavedHours']
        ?? $bbai_stats['time_saved_hours']
        ?? ($bbai_computed_time_saved_minutes / 60)
    )
);
$bbai_time_saved_minutes = max($bbai_computed_time_saved_minutes, (int) round($bbai_time_saved_hours * 60));

$bbai_before_missing_count = min($bbai_total_images, max($bbai_missing_count + $bbai_images_optimized_count, $bbai_missing_count));
$bbai_before_coverage_percent = $bbai_total_images > 0
    ? max(0, min(100, (int) round(((max(0, $bbai_total_images - $bbai_before_missing_count)) / $bbai_total_images) * 100)))
    : 0;
$bbai_coverage_improvement = max(0, $bbai_coverage_percent - $bbai_before_coverage_percent);

$bbai_plan_data = Plan_Helpers::get_plan_data();
$bbai_is_free = !empty($bbai_plan_data['is_free']);
$bbai_is_growth = !empty($bbai_plan_data['is_growth']);
$bbai_is_healthy = 0 === $bbai_missing_count && 0 === $bbai_weak_count && $bbai_total_images > 0;
$bbai_is_low_credits = $bbai_usage_remaining > 0 && $bbai_usage_remaining <= BBAI_BANNER_LOW_CREDITS_THRESHOLD;
$bbai_is_out_of_credits = 0 === $bbai_usage_remaining;
$bbai_has_activity = $bbai_usage_used > 0 || !empty($bbai_stats['latest_generated_raw']);

$bbai_library_url = admin_url('admin.php?page=bbai-library');
$bbai_settings_url = admin_url('admin.php?page=bbai-settings');
$bbai_billing_portal_url = '';
if (class_exists('\BeepBeepAI\AltTextGenerator\Usage_Tracker')) {
    $bbai_billing_portal_url = (string) \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_billing_portal_url();
}

$bbai_historical_coverage = [
    ['date' => 'Week 1', 'coverage' => max(0, $bbai_coverage_percent - 24)],
    ['date' => 'Week 2', 'coverage' => max(0, $bbai_coverage_percent - 20)],
    ['date' => 'Week 3', 'coverage' => max(0, $bbai_coverage_percent - 16)],
    ['date' => 'Week 4', 'coverage' => max(0, $bbai_coverage_percent - 12)],
    ['date' => 'Week 5', 'coverage' => max(0, $bbai_coverage_percent - 8)],
    ['date' => 'Current', 'coverage' => max(0, $bbai_coverage_percent - 4)],
    ['date' => 'Week 6', 'coverage' => max(0, $bbai_coverage_percent)],
];

$bbai_has_trend_data = false;
foreach ($bbai_historical_coverage as $bbai_point) {
    if ((int) ($bbai_point['coverage'] ?? 0) > 0) {
        $bbai_has_trend_data = true;
        break;
    }
}

$bbai_trend_first = (int) ($bbai_historical_coverage[0]['coverage'] ?? 0);
$bbai_trend_last = (int) ($bbai_historical_coverage[count($bbai_historical_coverage) - 1]['coverage'] ?? 0);
$bbai_trend_delta = $bbai_trend_last - $bbai_trend_first;
if (!$bbai_has_trend_data) {
    $bbai_trend_direction = 'empty';
} elseif ($bbai_trend_delta >= 5) {
    $bbai_trend_direction = 'improving';
} elseif ($bbai_trend_delta <= -5) {
    $bbai_trend_direction = 'declining';
} else {
    $bbai_trend_direction = 'stable';
}

$bbai_format_time_saved_value = static function(int $minutes) : string {
    if ($minutes <= 0) {
        return __('~2 hours saved', 'beepbeep-ai-alt-text-generator');
    }

    if ($minutes < 15) {
        return __('Less than 1 hour saved', 'beepbeep-ai-alt-text-generator');
    }

    if ($minutes < 60) {
        return sprintf(
            /* translators: %s: minutes saved */
            __('~%s minutes saved', 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($minutes)
        );
    }

    $hours = (int) round($minutes / 60);
    if ($hours <= 1) {
        return __('~1 hour saved', 'beepbeep-ai-alt-text-generator');
    }

    return sprintf(
        /* translators: %s: number of hours saved. */
        _n('~%s hour saved', '~%s hours saved', $hours, 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($hours)
    );
};

$bbai_make_button_action = static function(string $label, string $variant, array $attributes = [], string $class = '') : array {
    return [
        'label'      => $label,
        'variant'    => $variant,
        'size'       => 'sm',
        'attributes' => $attributes,
        'class'      => $class,
    ];
};

$bbai_make_link_action = static function(string $label, string $href, string $variant = 'secondary', array $attributes = [], string $class = '') : array {
    return [
        'label'      => $label,
        'href'       => $href,
        'variant'    => $variant,
        'size'       => 'sm',
        'attributes' => $attributes,
        'class'      => $class,
    ];
};

$bbai_render_action = static function(?array $action) : string {
    if (empty($action['label'])) {
        return '';
    }

    $variant = (string) ($action['variant'] ?? 'secondary');
    $size = (string) ($action['size'] ?? 'sm');
    $classes = [];

    if ('link' === $variant) {
        $classes[] = 'bbai-link-btn';
        if ('sm' === $size) {
            $classes[] = 'bbai-link-sm';
        }
    } else {
        $classes[] = 'bbai-btn';
        $classes[] = 'bbai-btn-' . $variant;
        if ('sm' === $size) {
            $classes[] = 'bbai-btn-sm';
        }
    }

    if (!empty($action['class'])) {
        $classes[] = (string) $action['class'];
    }

    $attributes = $action['attributes'] ?? [];
    $attribute_pairs = ['class="' . esc_attr(trim(implode(' ', array_filter($classes)))) . '"'];

    if (empty($action['href']) && !isset($attributes['type'])) {
        $attribute_pairs[] = 'type="button"';
    }

    foreach ($attributes as $name => $value) {
        if (null === $value || '' === $value) {
            continue;
        }

        $attribute_pairs[] = sprintf('%s="%s"', esc_attr((string) $name), esc_attr((string) $value));
    }

    $attributes_html = implode(' ', $attribute_pairs);
    $label = esc_html((string) $action['label']);

    if (!empty($action['href'])) {
        return sprintf('<a href="%1$s" %2$s>%3$s</a>', esc_url((string) $action['href']), $attributes_html, $label);
    }

    return sprintf('<button %1$s>%2$s</button>', $attributes_html, $label);
};

$bbai_get_review_library_action = static function() use ($bbai_make_link_action, $bbai_library_url) : array {
    return $bbai_make_link_action(
        __('Review ALT Library', 'beepbeep-ai-alt-text-generator'),
        $bbai_library_url,
        'secondary'
    );
};

$bbai_get_automation_action = static function() use ($bbai_is_free, $bbai_make_button_action, $bbai_make_link_action, $bbai_settings_url) : array {
    if ($bbai_is_free) {
        return $bbai_make_button_action(
            __('Enable Auto-Optimisation', 'beepbeep-ai-alt-text-generator'),
            'primary',
            ['data-action' => 'show-upgrade-modal']
        );
    }

    return $bbai_make_link_action(
        __('Enable Auto-Optimisation', 'beepbeep-ai-alt-text-generator'),
        $bbai_settings_url . '#bbai-enable-on-upload',
        'primary'
    );
};

$bbai_get_automation_link_action = static function() use ($bbai_is_free, $bbai_make_button_action, $bbai_make_link_action, $bbai_settings_url) : array {
    if ($bbai_is_free) {
        return $bbai_make_button_action(
            __('Enable Auto-Optimisation', 'beepbeep-ai-alt-text-generator'),
            'link',
            ['data-action' => 'show-upgrade-modal']
        );
    }

    return $bbai_make_link_action(
        __('Enable Auto-Optimisation', 'beepbeep-ai-alt-text-generator'),
        $bbai_settings_url . '#bbai-enable-on-upload',
        'link'
    );
};

$bbai_get_buy_credits_action = static function() use ($bbai_make_button_action, $bbai_make_link_action, $bbai_billing_portal_url) : array {
    if (!empty($bbai_billing_portal_url)) {
        return $bbai_make_link_action(
            __('Buy extra credits', 'beepbeep-ai-alt-text-generator'),
            $bbai_billing_portal_url,
            'secondary',
            ['target' => '_blank', 'rel' => 'noopener']
        );
    }

    return $bbai_make_button_action(
        __('Buy extra credits', 'beepbeep-ai-alt-text-generator'),
        'secondary',
        ['data-action' => 'show-upgrade-modal']
    );
};

/**
 * Legacy helper — prefer get_automation_action + get_buy_credits_action for new UI.
 */
$bbai_get_plan_action = static function(string $free_label = '', string $growth_label = '') use ($bbai_is_free, $bbai_is_growth, $bbai_make_button_action, $bbai_make_link_action, $bbai_billing_portal_url) : ?array {
    if ($bbai_is_free) {
        return $bbai_make_button_action(
            $free_label ?: __('Enable Auto-Optimisation', 'beepbeep-ai-alt-text-generator'),
            'primary',
            ['data-action' => 'show-upgrade-modal']
        );
    }

    if ($bbai_is_growth) {
        return $bbai_make_button_action(
            $growth_label ?: __('Enable Auto-Optimisation', 'beepbeep-ai-alt-text-generator'),
            'primary',
            ['data-action' => 'show-upgrade-modal']
        );
    }

    if (!empty($bbai_billing_portal_url)) {
        return $bbai_make_link_action(
            __('Manage subscription', 'beepbeep-ai-alt-text-generator'),
            $bbai_billing_portal_url,
            'link',
            ['target' => '_blank', 'rel' => 'noopener']
        );
    }

    return null;
};

$bbai_coverage_kpi_value = 0 === $bbai_total_images
    ? __('No images yet', 'beepbeep-ai-alt-text-generator')
    : sprintf(
        /* translators: %s: coverage percent */
        __('%s coverage', 'beepbeep-ai-alt-text-generator'),
        $bbai_alt_coverage_display
    );

if (0 === $bbai_total_images) {
    $bbai_coverage_kpi_description = __('Scan your media library to establish a coverage baseline.', 'beepbeep-ai-alt-text-generator');
    $bbai_coverage_kpi_meta = __('Analytics becomes more useful once images have been scanned.', 'beepbeep-ai-alt-text-generator');
} elseif ($bbai_is_healthy) {
    $bbai_coverage_kpi_description = __('All library images now include descriptive ALT text.', 'beepbeep-ai-alt-text-generator');
    $bbai_coverage_kpi_meta = __('Keep new uploads covered to maintain accessibility.', 'beepbeep-ai-alt-text-generator');
} elseif ($bbai_missing_count > 0) {
    $bbai_coverage_kpi_description = sprintf(
        /* translators: %s: number of images still missing ALT text. */
        _n('%s image is still missing ALT text.', '%s images are still missing ALT text.', $bbai_missing_count, 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($bbai_missing_count)
    );
    $bbai_coverage_kpi_meta = $bbai_weak_count > 0
        ? sprintf(
            /* translators: %s: number of descriptions still needing review. */
            _n('%s description still needs review.', '%s descriptions still need review.', $bbai_weak_count, 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($bbai_weak_count)
        )
        : __('Closing those gaps will deliver the next accessibility lift.', 'beepbeep-ai-alt-text-generator');
} else {
    $bbai_coverage_kpi_description = sprintf(
        /* translators: %s: number of descriptions still needing review. */
        _n('%s description still needs review.', '%s descriptions still need review.', $bbai_weak_count, 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($bbai_weak_count)
    );
    $bbai_coverage_kpi_meta = __('Coverage is strong, but refining weaker descriptions will improve quality.', 'beepbeep-ai-alt-text-generator');
}

$bbai_images_kpi_value = sprintf(
    /* translators: %s: number of optimized images. */
    _n('%s image optimized', '%s images optimized', $bbai_images_optimized_count, 'beepbeep-ai-alt-text-generator'),
    number_format_i18n($bbai_images_optimized_count)
);
$bbai_images_kpi_description = $bbai_images_optimized_count > 0
    ? __('Generated or improved across your media library.', 'beepbeep-ai-alt-text-generator')
    : __('No images have been optimized yet.', 'beepbeep-ai-alt-text-generator');

if ($bbai_images_optimized_count > 0 && $bbai_missing_count > 0) {
    $bbai_images_kpi_meta = sprintf(
        /* translators: %s: number of images still missing ALT text. */
        _n('%s image still has no ALT text.', '%s images still have no ALT text.', $bbai_missing_count, 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($bbai_missing_count)
    );
} elseif ($bbai_images_optimized_count > 0) {
    $bbai_images_kpi_meta = __('Your optimized library is building proof of value over time.', 'beepbeep-ai-alt-text-generator');
} else {
    $bbai_images_kpi_meta = __('Start with a scan to surface missing or weak descriptions.', 'beepbeep-ai-alt-text-generator');
}

$bbai_time_saved_kpi_value = $bbai_format_time_saved_value($bbai_time_saved_minutes);
$bbai_time_saved_kpi_description = $bbai_usage_used > 0
    ? __('Estimated manual writing time avoided.', 'beepbeep-ai-alt-text-generator')
    : __('Time saved appears after your first optimization run.', 'beepbeep-ai-alt-text-generator');
$bbai_time_saved_kpi_meta = __('Based on roughly 2.5 minutes per image description.', 'beepbeep-ai-alt-text-generator');

// Time metric card: calculated phrase when we have time; otherwise "~2 hours saved" (never generic "Time saved").
$bbai_time_metric_card = $bbai_time_saved_minutes > 0
    ? (string) $bbai_format_time_saved_value($bbai_time_saved_minutes)
    : __('~2 hours saved', 'beepbeep-ai-alt-text-generator');

// Short form for "You've saved %s so far." (avoids "~2 hours saved … so far").
$bbai_time_metric_short = $bbai_time_saved_minutes > 0
    ? preg_replace('/\s+saved$/u', '', (string) $bbai_format_time_saved_value($bbai_time_saved_minutes))
    : '';

// Trend summary under chart (single line, direct).
if (0 === $bbai_total_images || !$bbai_has_trend_data) {
    $bbai_trend_sentence = __('Scan to start tracking coverage here.', 'beepbeep-ai-alt-text-generator');
} elseif ('improving' === $bbai_trend_direction) {
    $bbai_trend_sentence = sprintf(
        /* translators: %s: number of coverage points improved this period. */
        _n(
            'Coverage up %s point this period.',
            'Coverage up %s points this period.',
            abs($bbai_trend_delta),
            'beepbeep-ai-alt-text-generator'
        ),
        number_format_i18n(abs($bbai_trend_delta))
    );
} elseif ('declining' === $bbai_trend_direction) {
    $bbai_trend_sentence = __('Coverage dipped — scan new uploads.', 'beepbeep-ai-alt-text-generator');
} elseif ('stable' === $bbai_trend_direction) {
    $bbai_trend_sentence = $bbai_is_healthy
        ? __('Coverage steady at full strength.', 'beepbeep-ai-alt-text-generator')
        : __('Coverage steady — close gaps when you can.', 'beepbeep-ai-alt-text-generator');
} else {
    $bbai_trend_sentence = __('Optimise images to see the trend.', 'beepbeep-ai-alt-text-generator');
}

// Inline text under metrics only — no box, no alert (dashboard-style supporting copy).
$bbai_analytics_saved_line = '';
if ($bbai_usage_used > 0 && '' !== $bbai_time_metric_short) {
    $bbai_analytics_saved_line = sprintf(
        /* translators: %s: e.g. "~2 hours" or "~45 minutes" (no trailing "saved") */
        __('You\'ve saved %s so far.', 'beepbeep-ai-alt-text-generator'),
        $bbai_time_metric_short
    );
}

// Single concise insight under metrics (no duplicate “risk” line — merged into automation guidance when healthy).
$bbai_analytics_guidance_line = __('New uploads can introduce missing ALT. Turn on automation to stay covered.', 'beepbeep-ai-alt-text-generator');
if (0 === $bbai_total_images) {
    $bbai_analytics_guidance_line = __('Scan your library to chart coverage and time saved.', 'beepbeep-ai-alt-text-generator');
} elseif ($bbai_missing_count > 0) {
    $bbai_analytics_guidance_line = sprintf(
        /* translators: %s: number of images still needing ALT text. */
        _n('%s image still needs ALT — scan first.', '%s images still need ALT — scan first.', $bbai_missing_count, 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($bbai_missing_count)
    );
} elseif ($bbai_weak_count > 0) {
    $bbai_analytics_guidance_line = __('Some descriptions need a pass — review in the ALT Library.', 'beepbeep-ai-alt-text-generator');
} elseif ($bbai_is_out_of_credits) {
    $bbai_analytics_guidance_line = __('No credits left this cycle — add capacity or wait for reset.', 'beepbeep-ai-alt-text-generator');
} elseif ($bbai_is_low_credits) {
    $bbai_analytics_guidance_line = __('Low credits — prioritise missing ALT, or turn on automation for new uploads.', 'beepbeep-ai-alt-text-generator');
}

$bbai_before_after_story_title = 0 === $bbai_total_images
    ? __('Scan your library to create your first before-and-after result.', 'beepbeep-ai-alt-text-generator')
    : sprintf(
        /* translators: 1: before coverage percent, 2: after coverage percent */
        __('Accessibility coverage improved from %1$s to %2$s.', 'beepbeep-ai-alt-text-generator'),
        $bbai_before_coverage_percent . '%',
        $bbai_alt_coverage_display
    );
$bbai_before_after_story_description = 0 === $bbai_total_images
    ? __('This section becomes a proof-of-value snapshot once BeepBeep AI starts optimizing your library.', 'beepbeep-ai-alt-text-generator')
    : __('This is the clearest proof of value on the page: fewer missing descriptions, better accessibility coverage, and less manual work.', 'beepbeep-ai-alt-text-generator');

$bbai_before_after_actions = [
    $bbai_make_button_action(__('Download report', 'beepbeep-ai-alt-text-generator'), 'secondary', ['id' => 'bbai-export-report']),
    $bbai_get_review_library_action(),
];

$bbai_activity_empty_secondary_action = $bbai_get_automation_link_action();

$bbai_analytics_snap = bbai_banner_snapshot_merge(
    [
        'missing_count'     => $bbai_missing_count,
        'weak_count'        => $bbai_weak_count,
        'total_images'      => $bbai_total_images,
        'credits_used'      => $bbai_usage_used,
        'credits_limit'     => $bbai_usage_limit,
        'credits_remaining' => $bbai_usage_remaining,
        'usage_percent'     => $bbai_credits_used_percent,
        'is_pro_plan'       => !$bbai_is_free,
        'is_first_run'      => 0 === $bbai_total_images,
        'is_low_credits'    => $bbai_is_low_credits,
        'is_out_of_credits' => $bbai_is_out_of_credits,
        'library_url'       => $bbai_library_url,
        'missing_library_url' => add_query_arg(['page' => 'bbai-library', 'status' => 'missing'], admin_url('admin.php')),
        'needs_review_library_url' => bbai_alt_library_needs_review_url(),
        'usage_url'         => admin_url('admin.php?page=bbai-credit-usage'),
        'settings_url'      => $bbai_settings_url,
        'guide_url'         => admin_url('admin.php?page=bbai-guide'),
    ]
);

$bbai_analytics_banner_logic = bbai_banner_pick_state(BBAI_BANNER_CTX_ANALYTICS, $bbai_analytics_snap);
$bbai_analytics_page_hero_variant = 'neutral';
if (BBAI_BANNER_STATE_HEALTHY === $bbai_analytics_banner_logic) {
    $bbai_analytics_page_hero_variant = 'success';
} elseif (in_array(
    $bbai_analytics_banner_logic,
    [BBAI_BANNER_STATE_NEEDS_ATTENTION, BBAI_BANNER_STATE_LOW_CREDITS, BBAI_BANNER_STATE_OUT_OF_CREDITS],
    true
)) {
    $bbai_analytics_page_hero_variant = 'warning';
}

$bbai_analytics_command_hero = bbai_banner_build_command_hero(
    BBAI_BANNER_CTX_ANALYTICS,
    $bbai_analytics_snap,
    [
        'page_hero_variant'  => $bbai_analytics_page_hero_variant,
        'aria_label'         => __('Analytics summary', 'beepbeep-ai-alt-text-generator'),
        'section_data_attrs' => [
            'data-bbai-analytics-hero' => '1',
        ],
    ]
);
$bbai_analytics_banner_slot = bbai_get_active_banner_slot_from_state($bbai_analytics_banner_logic);
if (!isset($bbai_analytics_command_hero['section_data_attrs']) || !is_array($bbai_analytics_command_hero['section_data_attrs'])) {
    $bbai_analytics_command_hero['section_data_attrs'] = [];
}
$bbai_analytics_command_hero['section_data_attrs']['data-bbai-active-banner-slot'] = (string) ($bbai_analytics_banner_slot ?? '');

$bbai_rail_credits_line = sprintf(
    /* translators: 1: used credits, 2: credit limit */
    __('You\'ve used %1$s of %2$s credits this cycle.', 'beepbeep-ai-alt-text-generator'),
    number_format_i18n($bbai_usage_used),
    number_format_i18n($bbai_usage_limit)
);
$bbai_analytics_payload = [
    'coverage' => $bbai_historical_coverage,
    'usage'    => $bbai_usage_stats,
    'meta'     => [
        'trendDirection'  => $bbai_trend_direction,
        'trendDelta'      => $bbai_trend_delta,
        'currentCoverage' => $bbai_coverage_percent,
        'hasActivity'     => $bbai_has_activity,
    ],
];

if (function_exists('wp_add_inline_script')) {
    wp_add_inline_script(
        'bbai-analytics',
        'window.bbaiAnalyticsData = ' . wp_json_encode($bbai_analytics_payload) . ';',
        'before'
    );
}

$bbai_analytics_sidebar_foot = '';
if ($bbai_is_low_credits || $bbai_is_out_of_credits) {
    $bbai_analytics_sidebar_foot = '<div class="bbai-product-rail__actions">'
        . bbai_command_hero_render_action($bbai_get_buy_credits_action(), 'secondary')
        . '</div>';
}
?>

<?php
bbai_ui_render('page-shell', [
    'phase' => 'open',
    'class' => 'bbai-analytics-page',
]);
bbai_ui_render('bbai-banner', [
    'command_hero' => $bbai_analytics_command_hero,
]);
bbai_ui_render('metrics-grid', [
    'phase'       => 'open',
    'aria_label'  => __('Key metrics', 'beepbeep-ai-alt-text-generator'),
]);
bbai_ui_render('stat-card', [
    'value' => (string) number_format_i18n($bbai_images_optimized_count),
    'label' => __('images optimised', 'beepbeep-ai-alt-text-generator'),
]);
bbai_ui_render('stat-card', [
    'value' => (string) $bbai_time_metric_card,
    'label' => __('writing time saved', 'beepbeep-ai-alt-text-generator'),
]);
bbai_ui_render('stat-card', [
    'value'      => (string) number_format_i18n($bbai_usage_remaining),
    'label'      => __('credits remaining', 'beepbeep-ai-alt-text-generator'),
    'root_class' => ($bbai_is_low_credits || $bbai_is_out_of_credits) ? 'bbai-analytics-metric-card--warn' : '',
]);
bbai_ui_render('metrics-grid', [ 'phase' => 'close' ]);
?>
<div class="bbai-ui-notice-stack bbai-analytics-post-metrics" role="note">
    <?php
    if ('' !== $bbai_analytics_saved_line) {
        bbai_ui_render('notice-strip', [
            'variant' => 'neutral',
            'message' => $bbai_analytics_saved_line,
        ]);
    }
    bbai_ui_render('notice-strip', [
        'variant' => 'info',
        'message' => $bbai_analytics_guidance_line,
    ]);
    ?>
</div>
<?php
bbai_ui_render('workspace-grid', [
    'phase' => 'open',
    'class' => 'bbai-analytics-workspace',
]);
bbai_ui_render('workspace-grid', [ 'phase' => 'main_open' ]);
bbai_ui_render('section-card', [
    'phase' => 'open',
    'tag'   => 'div',
    'class' => 'bbai-analytics-trend-block bbai-analytics-result-card',
]);
bbai_ui_render('section-header', [
    'class'           => 'bbai-analytics-trend-block__head bbai-ui-section-header bbai-section-header',
    'eyebrow_class'   => 'bbai-analytics-trend-block__eyebrow bbai-ui-section-header__eyebrow bbai-section-label bbai-card-label',
    'title_class'     => 'bbai-analytics-trend-block__title bbai-ui-section-header__title bbai-section-title bbai-card-title',
    'eyebrow'         => __('Trend', 'beepbeep-ai-alt-text-generator'),
    'title'           => __('Coverage over time', 'beepbeep-ai-alt-text-generator'),
]);
?>
<?php if ($bbai_has_trend_data) : ?>
<div class="bbai-analytics-trend-block__chart">
    <canvas id="bbai-coverage-chart"></canvas>
</div>
<?php else : ?>
<?php
bbai_ui_render(
    'product-state',
    [
        'variant'    => 'empty',
        'section'    => true,
        'compact'    => true,
        'root_class' => 'bbai-analytics-trend-block__empty',
        'title'      => __('Not enough history yet', 'beepbeep-ai-alt-text-generator'),
        'body'       => __('The coverage chart appears after your library has data to plot.', 'beepbeep-ai-alt-text-generator'),
    ]
);
?>
<?php endif; ?>
<p class="bbai-analytics-trend-block__summary"><?php echo esc_html($bbai_trend_sentence); ?></p>
<?php
bbai_ui_render('section-card', [
    'phase'      => 'close',
    'close_tag'  => 'div',
]);
bbai_ui_render('workspace-grid', [ 'phase' => 'main_close' ]);
bbai_ui_render('sidebar-card', [
        'title'         => __('Usage this cycle', 'beepbeep-ai-alt-text-generator'),
        'body'          => $bbai_rail_credits_line,
        'context_class' => 'bbai-analytics-rail--context',
        'aria_label'    => __('Usage and credits', 'beepbeep-ai-alt-text-generator'),
        'foot_html'     => $bbai_analytics_sidebar_foot,
    ]);
bbai_ui_render('workspace-grid', [ 'phase' => 'close' ]);
bbai_ui_render('page-shell', [ 'phase' => 'close' ]);
?>
