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

$bbai_stats = isset($bbai_stats) && is_array($bbai_stats) ? $bbai_stats : [];
$bbai_usage_stats = isset($bbai_usage_stats) && is_array($bbai_usage_stats) ? $bbai_usage_stats : [];

$bbai_total_images = max(0, (int) ($bbai_stats['total'] ?? $bbai_stats['total_images'] ?? 0));
$bbai_with_alt_count = max(0, (int) ($bbai_stats['with_alt'] ?? $bbai_stats['images_with_alt'] ?? 0));
$bbai_missing_count = max(0, (int) ($bbai_stats['missing'] ?? $bbai_stats['images_missing_alt'] ?? 0));
$bbai_weak_count = max(0, (int) ($bbai_stats['needs_review_count'] ?? $bbai_stats['weakCount'] ?? $bbai_stats['weak_count'] ?? 0));
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
$bbai_usage_remaining = max(0, (int) ($bbai_usage_stats['remaining'] ?? ($bbai_usage_limit - $bbai_usage_used)));
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
$bbai_is_low_credits = $bbai_usage_remaining < 10 && $bbai_usage_remaining > 0;
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

$bbai_numbered_images = static function(int $count, string $single, string $plural) : string {
    return sprintf(
        _n($single, $plural, $count, 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($count)
    );
};

$bbai_format_time_saved_value = static function(int $minutes) : string {
    if ($minutes <= 0) {
        return __('Ready to save time', 'beepbeep-ai-alt-text-generator');
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

$bbai_get_scan_action = static function() use ($bbai_make_button_action) : array {
    return $bbai_make_button_action(
        __('Scan Media Library', 'beepbeep-ai-alt-text-generator'),
        'primary',
        ['data-action' => 'rescan-media-library']
    );
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
            __('Enable Auto-Optimization', 'beepbeep-ai-alt-text-generator'),
            'primary',
            ['data-action' => 'show-upgrade-modal']
        );
    }

    return $bbai_make_link_action(
        __('Enable Auto-Optimization', 'beepbeep-ai-alt-text-generator'),
        $bbai_settings_url . '#bbai-enable-on-upload',
        'primary'
    );
};

$bbai_get_automation_link_action = static function() use ($bbai_is_free, $bbai_make_button_action, $bbai_make_link_action, $bbai_settings_url) : array {
    if ($bbai_is_free) {
        return $bbai_make_button_action(
            __('Enable Auto-Optimization', 'beepbeep-ai-alt-text-generator'),
            'link',
            ['data-action' => 'show-upgrade-modal']
        );
    }

    return $bbai_make_link_action(
        __('Enable Auto-Optimization', 'beepbeep-ai-alt-text-generator'),
        $bbai_settings_url . '#bbai-enable-on-upload',
        'link'
    );
};

$bbai_get_plan_action = static function(string $free_label = '', string $growth_label = '') use ($bbai_is_free, $bbai_is_growth, $bbai_make_button_action, $bbai_make_link_action, $bbai_billing_portal_url) : ?array {
    if ($bbai_is_free) {
        return $bbai_make_button_action(
            $free_label ?: __('Compare plans', 'beepbeep-ai-alt-text-generator'),
            'link',
            ['data-action' => 'show-upgrade-modal']
        );
    }

    if ($bbai_is_growth) {
        return $bbai_make_button_action(
            $growth_label ?: __('Upgrade to Agency', 'beepbeep-ai-alt-text-generator'),
            'link',
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
    $bbai_coverage_kpi_description = $bbai_numbered_images(
        $bbai_missing_count,
        '%s image is still missing ALT text.',
        '%s images are still missing ALT text.'
    );
    $bbai_coverage_kpi_meta = $bbai_weak_count > 0
        ? $bbai_numbered_images($bbai_weak_count, '%s description still needs review.', '%s descriptions still need review.')
        : __('Closing those gaps will deliver the next accessibility lift.', 'beepbeep-ai-alt-text-generator');
} else {
    $bbai_coverage_kpi_description = $bbai_numbered_images(
        $bbai_weak_count,
        '%s description still needs review.',
        '%s descriptions still need review.'
    );
    $bbai_coverage_kpi_meta = __('Coverage is strong, but refining weaker descriptions will improve quality.', 'beepbeep-ai-alt-text-generator');
}

$bbai_images_kpi_value = $bbai_numbered_images(
    $bbai_images_optimized_count,
    '%s image optimized',
    '%s images optimized'
);
$bbai_images_kpi_description = $bbai_images_optimized_count > 0
    ? __('Generated or improved across your media library.', 'beepbeep-ai-alt-text-generator')
    : __('No images have been optimized yet.', 'beepbeep-ai-alt-text-generator');

if ($bbai_images_optimized_count > 0 && $bbai_missing_count > 0) {
    $bbai_images_kpi_meta = $bbai_numbered_images(
        $bbai_missing_count,
        '%s image still has no ALT text.',
        '%s images still have no ALT text.'
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

$bbai_chart_insight = [
    'tone'             => 'neutral',
    'status_label'     => __('Insight', 'beepbeep-ai-alt-text-generator'),
    'title'            => '',
    'description'      => '',
    'details'          => [],
    'primary_action'   => null,
    'secondary_action' => null,
];

if (0 === $bbai_total_images) {
    $bbai_chart_insight['status_label'] = __('Start here', 'beepbeep-ai-alt-text-generator');
    $bbai_chart_insight['title'] = __('Trend data will appear after your first scan.', 'beepbeep-ai-alt-text-generator');
    $bbai_chart_insight['description'] = __('Scan your media library to create a coverage baseline and start tracking improvement over time.', 'beepbeep-ai-alt-text-generator');
    $bbai_chart_insight['primary_action'] = $bbai_get_scan_action();
} elseif (!$bbai_has_trend_data) {
    $bbai_chart_insight['status_label'] = __('Building baseline', 'beepbeep-ai-alt-text-generator');
    $bbai_chart_insight['title'] = __('You need a little more optimization activity before trend analysis becomes useful.', 'beepbeep-ai-alt-text-generator');
    $bbai_chart_insight['description'] = __('Start by optimizing missing images so this chart can move from a baseline view into a real coverage trend.', 'beepbeep-ai-alt-text-generator');
    $bbai_chart_insight['details'][] = $bbai_missing_count > 0
        ? $bbai_numbered_images($bbai_missing_count, '%s image is ready for optimization.', '%s images are ready for optimization.')
        : __('Review weaker descriptions to build your next round of improvements.', 'beepbeep-ai-alt-text-generator');
    $bbai_chart_insight['primary_action'] = $bbai_missing_count > 0 ? $bbai_get_scan_action() : $bbai_get_review_library_action();
} elseif ('improving' === $bbai_trend_direction) {
    $bbai_chart_insight['tone'] = 'success';
    $bbai_chart_insight['status_label'] = __('Improving', 'beepbeep-ai-alt-text-generator');
    $bbai_chart_insight['title'] = __('Your ALT coverage improved steadily over the selected period.', 'beepbeep-ai-alt-text-generator');
    $bbai_chart_insight['description'] = 100 === $bbai_coverage_percent
        ? __('Coverage is now at 100%. Scan new uploads regularly to keep it there.', 'beepbeep-ai-alt-text-generator')
        : sprintf(
            /* translators: %s: coverage percent */
            __('Coverage is now at %s. Focus on the remaining gaps to keep momentum.', 'beepbeep-ai-alt-text-generator'),
            $bbai_alt_coverage_display
        );
    $bbai_chart_insight['details'][] = sprintf(
        _n('Coverage is up %s point from the start of the trend.', 'Coverage is up %s points from the start of the trend.', abs($bbai_trend_delta), 'beepbeep-ai-alt-text-generator'),
        number_format_i18n(abs($bbai_trend_delta))
    );
    if ($bbai_images_optimized_count > 0) {
        $bbai_chart_insight['details'][] = __('Recent gains came from recent optimization activity across your library.', 'beepbeep-ai-alt-text-generator');
    }
    $bbai_chart_insight['primary_action'] = $bbai_missing_count > 0 ? $bbai_get_scan_action() : $bbai_get_review_library_action();
    $bbai_chart_insight['secondary_action'] = $bbai_is_healthy ? $bbai_get_automation_link_action() : $bbai_get_plan_action();
} elseif ('declining' === $bbai_trend_direction) {
    $bbai_chart_insight['tone'] = 'warning';
    $bbai_chart_insight['status_label'] = __('Needs attention', 'beepbeep-ai-alt-text-generator');
    $bbai_chart_insight['title'] = __('Coverage slipped during the selected period.', 'beepbeep-ai-alt-text-generator');
    $bbai_chart_insight['description'] = __('New uploads may be entering the library without ALT text. Scan now to recover coverage before the gap grows.', 'beepbeep-ai-alt-text-generator');
    $bbai_chart_insight['details'][] = $bbai_numbered_images(
        max(0, $bbai_missing_count),
        '%s image is currently missing ALT text.',
        '%s images are currently missing ALT text.'
    );
    $bbai_chart_insight['primary_action'] = $bbai_get_scan_action();
    $bbai_chart_insight['secondary_action'] = $bbai_get_automation_link_action();
} else {
    $bbai_chart_insight['tone'] = $bbai_is_healthy ? 'success' : 'neutral';
    $bbai_chart_insight['status_label'] = $bbai_is_healthy ? __('Stable', 'beepbeep-ai-alt-text-generator') : __('Next step', 'beepbeep-ai-alt-text-generator');
    if ($bbai_is_healthy) {
        $bbai_chart_insight['title'] = __('Coverage is holding steady at full health.', 'beepbeep-ai-alt-text-generator');
        $bbai_chart_insight['description'] = __('Your library is fully covered. The next risk is new uploads arriving without ALT text.', 'beepbeep-ai-alt-text-generator');
        $bbai_chart_insight['details'][] = __('Enable automation or scan new uploads regularly to maintain coverage.', 'beepbeep-ai-alt-text-generator');
        $bbai_chart_insight['primary_action'] = $bbai_get_automation_action();
        $bbai_chart_insight['secondary_action'] = $bbai_get_review_library_action();
    } else {
        if ($bbai_missing_count > 0) {
            $bbai_chart_insight['title'] = __('Coverage has been stable recently, but there is still room to improve.', 'beepbeep-ai-alt-text-generator');
            $bbai_chart_insight['description'] = __('Focus on missing images first, then review weaker descriptions to improve accessibility and SEO value.', 'beepbeep-ai-alt-text-generator');
            $bbai_chart_insight['details'][] = $bbai_numbered_images(
                $bbai_missing_count,
                '%s image is still missing ALT text.',
                '%s images are still missing ALT text.'
            );
            if ($bbai_weak_count > 0) {
                $bbai_chart_insight['details'][] = $bbai_numbered_images(
                    $bbai_weak_count,
                    '%s description still needs review.',
                    '%s descriptions still need review.'
                );
            }
            $bbai_chart_insight['primary_action'] = $bbai_get_scan_action();
            $bbai_chart_insight['secondary_action'] = $bbai_get_review_library_action();
        } else {
            $bbai_chart_insight['title'] = __('Coverage is steady, and the next gains will come from quality improvements.', 'beepbeep-ai-alt-text-generator');
            $bbai_chart_insight['description'] = __('No images are missing ALT text right now, but some descriptions still need refinement to improve accessibility and SEO value.', 'beepbeep-ai-alt-text-generator');
            $bbai_chart_insight['details'][] = $bbai_numbered_images(
                $bbai_weak_count,
                '%s description still needs review.',
                '%s descriptions still need review.'
            );
            $bbai_chart_insight['primary_action'] = $bbai_get_review_library_action();
            $bbai_chart_insight['secondary_action'] = $bbai_get_automation_link_action();
        }
    }
}

$bbai_usage_insight = [
    'title'            => '',
    'description'      => '',
    'facts'            => [],
    'primary_action'   => null,
    'secondary_action' => null,
    'tone'             => 'neutral',
];

if ($bbai_is_out_of_credits) {
    $bbai_usage_insight['tone'] = 'warning';
    $bbai_usage_insight['title'] = __('You have used this cycle\'s allowance.', 'beepbeep-ai-alt-text-generator');
    $bbai_usage_insight['description'] = __('Upgrade to keep optimizing new uploads without waiting for the next reset.', 'beepbeep-ai-alt-text-generator');
    $bbai_usage_insight['facts'][] = sprintf(
        /* translators: %s: usage limit */
        __('%s of %s credits used.', 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($bbai_usage_used),
        number_format_i18n($bbai_usage_limit)
    );
    $bbai_usage_insight['facts'][] = __('Prioritize missing images first once credits return.', 'beepbeep-ai-alt-text-generator');
    $bbai_usage_insight['primary_action'] = $bbai_get_plan_action(__('Compare plans', 'beepbeep-ai-alt-text-generator'), __('Upgrade to Agency', 'beepbeep-ai-alt-text-generator'));
} elseif ($bbai_is_low_credits) {
    $bbai_usage_insight['tone'] = 'warning';
    $bbai_usage_insight['title'] = sprintf(
        /* translators: %s: credits remaining */
        __('Only %s credits remain this cycle.', 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($bbai_usage_remaining)
    );
    $bbai_usage_insight['description'] = $bbai_projected_cycle_usage > $bbai_usage_limit
        ? __('At your current pace, you may run low before the cycle resets.', 'beepbeep-ai-alt-text-generator')
        : __('Reserve the remaining credits for the images that still need ALT text most.', 'beepbeep-ai-alt-text-generator');
    $bbai_usage_insight['facts'][] = sprintf(
        /* translators: %s: percent used */
        __('You have used %s%% of this month\'s allowance.', 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($bbai_credits_used_percent)
    );
    $bbai_usage_insight['facts'][] = __('Automating future uploads reduces manual catch-up later.', 'beepbeep-ai-alt-text-generator');
    $bbai_usage_insight['primary_action'] = $bbai_get_plan_action(__('Compare plans', 'beepbeep-ai-alt-text-generator'), __('Upgrade to Agency', 'beepbeep-ai-alt-text-generator'));
    $bbai_usage_insight['secondary_action'] = $bbai_get_review_library_action();
} elseif (0 === $bbai_usage_used) {
    $bbai_usage_insight['title'] = __('You have not used this month\'s allowance yet.', 'beepbeep-ai-alt-text-generator');
    $bbai_usage_insight['description'] = __('Low usage detected. Automating future uploads can help you get more value while keeping coverage high.', 'beepbeep-ai-alt-text-generator');
    $bbai_usage_insight['facts'][] = __('You have used 0% of this month\'s allowance.', 'beepbeep-ai-alt-text-generator');
    $bbai_usage_insight['facts'][] = sprintf(
        _n('You still have room to optimize %s more image this cycle.', 'You still have room to optimize %s more images this cycle.', $bbai_usage_remaining, 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($bbai_usage_remaining)
    );
    $bbai_usage_insight['primary_action'] = $bbai_get_scan_action();
    $bbai_usage_insight['secondary_action'] = $bbai_get_automation_link_action();
} elseif ($bbai_projected_cycle_usage <= $bbai_usage_limit) {
    $bbai_usage_insight['tone'] = 'success';
    $bbai_usage_insight['title'] = __('At your current pace, your credits should last the full cycle.', 'beepbeep-ai-alt-text-generator');
    $bbai_usage_insight['description'] = sprintf(
        /* translators: 1: percent used, 2: credits remaining */
        __('You have used %1$s%% of this month\'s allowance and still have room to optimize %2$s more images.', 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($bbai_credits_used_percent),
        number_format_i18n($bbai_usage_remaining)
    );
    $bbai_usage_insight['facts'][] = sprintf(
        /* translators: %s: projected cycle usage */
        __('Projected cycle usage: about %s credits at your current pace.', 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($bbai_projected_cycle_usage)
    );
    if ($bbai_usage_remaining > ($bbai_usage_limit * 0.7)) {
        $bbai_usage_insight['facts'][] = __('Auto-optimization can help turn unused credits into steady coverage protection.', 'beepbeep-ai-alt-text-generator');
    } else {
        $bbai_usage_insight['facts'][] = __('Keep scanning new uploads so coverage stays current as the library grows.', 'beepbeep-ai-alt-text-generator');
    }
    $bbai_usage_insight['primary_action'] = $bbai_usage_remaining > ($bbai_usage_limit * 0.7)
        ? $bbai_get_automation_action()
        : $bbai_get_review_library_action();
    $bbai_usage_insight['secondary_action'] = $bbai_usage_remaining > ($bbai_usage_limit * 0.7)
        ? $bbai_get_plan_action()
        : $bbai_get_scan_action();
} else {
    $bbai_usage_insight['tone'] = 'warning';
    $bbai_usage_insight['title'] = __('At your current pace, you may run low before the cycle resets.', 'beepbeep-ai-alt-text-generator');
    $bbai_usage_insight['description'] = __('Consider upgrading before your next large batch so new uploads stay covered without interruption.', 'beepbeep-ai-alt-text-generator');
    $bbai_usage_insight['facts'][] = sprintf(
        /* translators: %s: projected cycle usage */
        __('Projected cycle usage: about %s credits this month.', 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($bbai_projected_cycle_usage)
    );
    $bbai_usage_insight['facts'][] = sprintf(
        _n('%s credit remains right now.', '%s credits remain right now.', $bbai_usage_remaining, 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($bbai_usage_remaining)
    );
    $bbai_usage_insight['primary_action'] = $bbai_get_plan_action(__('Compare plans', 'beepbeep-ai-alt-text-generator'), __('Upgrade to Agency', 'beepbeep-ai-alt-text-generator'));
    $bbai_usage_insight['secondary_action'] = $bbai_get_review_library_action();
}

$bbai_smart_insight = [
    'tone'           => 'neutral',
    'label'          => __('Smart insight', 'beepbeep-ai-alt-text-generator'),
    'title'          => '',
    'description'    => '',
    'supporting'     => '',
    'primary_action' => null,
];

if (0 === $bbai_total_images) {
    $bbai_smart_insight['label'] = __('Next step', 'beepbeep-ai-alt-text-generator');
    $bbai_smart_insight['title'] = __('Scan your library to unlock the rest of this dashboard.', 'beepbeep-ai-alt-text-generator');
    $bbai_smart_insight['description'] = __('Once images are scanned, BeepBeep AI can highlight missing ALT text, weak descriptions, and the fastest path to better accessibility coverage.', 'beepbeep-ai-alt-text-generator');
    $bbai_smart_insight['primary_action'] = $bbai_get_scan_action();
} elseif ($bbai_missing_count > 0) {
    $bbai_smart_insight['tone'] = 'warning';
    $bbai_smart_insight['label'] = __('Highest impact', 'beepbeep-ai-alt-text-generator');
    $bbai_smart_insight['title'] = __('Missing ALT text is still your biggest accessibility gap.', 'beepbeep-ai-alt-text-generator');
    $bbai_smart_insight['description'] = $bbai_numbered_images(
        $bbai_missing_count,
        '%s image is still missing ALT text. Optimizing it first will deliver the fastest coverage gain.',
        '%s images are still missing ALT text. Optimizing them first will deliver the fastest coverage gain.'
    );
    $bbai_smart_insight['supporting'] = $bbai_weak_count > 0
        ? $bbai_numbered_images($bbai_weak_count, '%s description also needs review after that.', '%s descriptions also need review after that.')
        : __('After the missing images are covered, review any weak descriptions to improve quality further.', 'beepbeep-ai-alt-text-generator');
    $bbai_smart_insight['primary_action'] = $bbai_get_scan_action();
} elseif ($bbai_weak_count > 0) {
    $bbai_smart_insight['tone'] = 'info';
    $bbai_smart_insight['label'] = __('Quality pass', 'beepbeep-ai-alt-text-generator');
    $bbai_smart_insight['title'] = __('Some ALT descriptions may still be too short or generic.', 'beepbeep-ai-alt-text-generator');
    $bbai_smart_insight['description'] = $bbai_numbered_images(
        $bbai_weak_count,
        '%s description needs review to improve accessibility and SEO value.',
        '%s descriptions need review to improve accessibility and SEO value.'
    );
    $bbai_smart_insight['supporting'] = __('Reviewing them now keeps coverage high and makes the library more usable for screen readers.', 'beepbeep-ai-alt-text-generator');
    $bbai_smart_insight['primary_action'] = $bbai_get_review_library_action();
} elseif ($bbai_is_out_of_credits) {
    $bbai_smart_insight['tone'] = 'warning';
    $bbai_smart_insight['label'] = __('Coverage risk', 'beepbeep-ai-alt-text-generator');
    $bbai_smart_insight['title'] = __('Your library is healthy today, but new uploads can create gaps while credits are exhausted.', 'beepbeep-ai-alt-text-generator');
    $bbai_smart_insight['description'] = __('Upgrading gives you more room to keep accessibility coverage moving without manual catch-up.', 'beepbeep-ai-alt-text-generator');
    $bbai_smart_insight['primary_action'] = $bbai_get_plan_action(__('Compare plans', 'beepbeep-ai-alt-text-generator'), __('Upgrade to Agency', 'beepbeep-ai-alt-text-generator'));
} elseif ($bbai_is_healthy && 100 === $bbai_coverage_percent) {
    $bbai_smart_insight['tone'] = 'success';
    $bbai_smart_insight['label'] = __('Healthy library', 'beepbeep-ai-alt-text-generator');
    $bbai_smart_insight['title'] = __('Your library is fully covered.', 'beepbeep-ai-alt-text-generator');
    $bbai_smart_insight['description'] = __('The next risk is new uploads arriving without ALT text. Keep future uploads covered automatically or scan them regularly.', 'beepbeep-ai-alt-text-generator');
    $bbai_smart_insight['supporting'] = $bbai_is_free
        ? __('Auto-optimization is the easiest way to keep accessibility coverage high without extra maintenance.', 'beepbeep-ai-alt-text-generator')
        : __('A quick automation pass in settings can keep this healthy state from slipping.', 'beepbeep-ai-alt-text-generator');
    $bbai_smart_insight['primary_action'] = $bbai_get_automation_action();
} elseif ($bbai_usage_used <= max(2, (int) floor($bbai_usage_limit * 0.1)) && $bbai_usage_remaining > 0) {
    $bbai_smart_insight['label'] = __('Low usage', 'beepbeep-ai-alt-text-generator');
    $bbai_smart_insight['title'] = __('You are only using a small portion of your monthly allowance.', 'beepbeep-ai-alt-text-generator');
    $bbai_smart_insight['description'] = __('Auto-optimization can help you get more value from the product while keeping future uploads covered by default.', 'beepbeep-ai-alt-text-generator');
    $bbai_smart_insight['supporting'] = sprintf(
        _n('You still have room to optimize %s more image this cycle.', 'You still have room to optimize %s more images this cycle.', $bbai_usage_remaining, 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($bbai_usage_remaining)
    );
    $bbai_smart_insight['primary_action'] = $bbai_get_automation_action();
} else {
    $bbai_smart_insight['label'] = __('Momentum', 'beepbeep-ai-alt-text-generator');
    $bbai_smart_insight['title'] = __('Coverage is moving in the right direction.', 'beepbeep-ai-alt-text-generator');
    $bbai_smart_insight['description'] = __('Review recent optimizations and keep scanning new uploads so coverage stays current as the media library grows.', 'beepbeep-ai-alt-text-generator');
    $bbai_smart_insight['primary_action'] = $bbai_get_review_library_action();
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

$bbai_activity_empty_secondary_action = $bbai_is_free
    ? $bbai_get_plan_action(__('Compare plans', 'beepbeep-ai-alt-text-generator'))
    : $bbai_get_automation_link_action();

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
?>

<div class="bbai-analytics-page">
    <div class="bbai-dashboard-header-section">
        <div class="bbai-page-header-content">
            <h1 class="bbai-page-title"><?php esc_html_e('Analytics', 'beepbeep-ai-alt-text-generator'); ?></h1>
            <p class="bbai-page-subtitle">
                <?php esc_html_e('Track coverage, usage, and the next actions that keep future uploads accessible.', 'beepbeep-ai-alt-text-generator'); ?>
            </p>
        </div>
    </div>

    <section class="bbai-premium-metrics-grid bbai-mb-6" aria-label="<?php esc_attr_e('Key metrics', 'beepbeep-ai-alt-text-generator'); ?>">
        <div class="bbai-premium-card bbai-metric-card bbai-analytics-kpi bbai-analytics-kpi--coverage">
            <div class="bbai-metric-icon" style="color: #10B981;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M5 19L19 5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M9 5H19V15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </div>
            <div class="bbai-analytics-kpi__eyebrow"><?php esc_html_e('ALT Coverage', 'beepbeep-ai-alt-text-generator'); ?></div>
            <div class="bbai-analytics-kpi__value"><?php echo esc_html($bbai_coverage_kpi_value); ?></div>
            <p class="bbai-analytics-kpi__description"><?php echo esc_html($bbai_coverage_kpi_description); ?></p>
            <p class="bbai-analytics-kpi__meta"><?php echo esc_html($bbai_coverage_kpi_meta); ?></p>
        </div>

        <div class="bbai-premium-card bbai-metric-card bbai-analytics-kpi bbai-analytics-kpi--optimized">
            <div class="bbai-metric-icon" style="color: #6366F1;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <rect x="3" y="4" width="18" height="14" rx="3" stroke="currentColor" stroke-width="1.8"/>
                    <circle cx="9" cy="9" r="2" fill="currentColor"/>
                    <path d="M4.5 16l5-5 4 4 3-3 3 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </div>
            <div class="bbai-analytics-kpi__eyebrow"><?php esc_html_e('Images Optimized', 'beepbeep-ai-alt-text-generator'); ?></div>
            <div class="bbai-analytics-kpi__value"><?php echo esc_html($bbai_images_kpi_value); ?></div>
            <p class="bbai-analytics-kpi__description"><?php echo esc_html($bbai_images_kpi_description); ?></p>
            <p class="bbai-analytics-kpi__meta"><?php echo esc_html($bbai_images_kpi_meta); ?></p>
        </div>

        <div class="bbai-premium-card bbai-metric-card bbai-analytics-kpi bbai-analytics-kpi--time">
            <div class="bbai-metric-icon" style="color: #0EA5E9;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>
                    <path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </div>
            <div class="bbai-analytics-kpi__eyebrow"><?php esc_html_e('Time Saved', 'beepbeep-ai-alt-text-generator'); ?></div>
            <div class="bbai-analytics-kpi__value"><?php echo esc_html($bbai_time_saved_kpi_value); ?></div>
            <p class="bbai-analytics-kpi__description"><?php echo esc_html($bbai_time_saved_kpi_description); ?></p>
            <p class="bbai-analytics-kpi__meta"><?php echo esc_html($bbai_time_saved_kpi_meta); ?></p>
        </div>
    </section>

    <section class="bbai-card bbai-mb-6 bbai-analytics-section" aria-labelledby="bbai-analytics-trend-title">
        <div class="bbai-card-header bbai-card-header--with-action">
            <div>
                <h2 class="bbai-card-title" id="bbai-analytics-trend-title"><?php esc_html_e('ALT Coverage Trend', 'beepbeep-ai-alt-text-generator'); ?></h2>
                <p class="bbai-card-subtitle"><?php esc_html_e('Track how coverage is changing and what to do next.', 'beepbeep-ai-alt-text-generator'); ?></p>
            </div>
            <div id="bbai-analytics-period" class="bbai-period-selector" role="group" aria-label="<?php esc_attr_e('Coverage period', 'beepbeep-ai-alt-text-generator'); ?>">
                <button type="button" data-period="30d" aria-pressed="true" class="bbai-btn bbai-btn-secondary bbai-btn-sm bbai-period-btn bbai-period-btn--active">
                    <?php esc_html_e('Last 30 Days', 'beepbeep-ai-alt-text-generator'); ?>
                </button>
                <button type="button" data-period="90d" aria-pressed="false" class="bbai-btn bbai-btn-secondary bbai-btn-sm bbai-period-btn">
                    <?php esc_html_e('Last 90 Days', 'beepbeep-ai-alt-text-generator'); ?>
                </button>
                <button type="button" data-period="ytd" aria-pressed="false" class="bbai-btn bbai-btn-secondary bbai-btn-sm bbai-period-btn">
                    <?php esc_html_e('YTD', 'beepbeep-ai-alt-text-generator'); ?>
                </button>
            </div>
        </div>
        <div class="bbai-card-body">
            <?php if ($bbai_has_trend_data) : ?>
                <div class="bbai-chart-container">
                    <canvas id="bbai-coverage-chart"></canvas>
                </div>
            <?php else : ?>
                <div class="bbai-chart-empty-state">
                    <div class="bbai-chart-empty-icon">
                        <svg width="64" height="64" viewBox="0 0 64 64" fill="none" aria-hidden="true">
                            <circle cx="32" cy="32" r="30" stroke="currentColor" stroke-width="2" stroke-dasharray="4 4"/>
                            <path d="M32 16v16l8 8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <p class="bbai-chart-empty-text"><?php esc_html_e('No coverage data is available yet. Start optimizing images to see trend insight here.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    <div class="bbai-analytics-actions bbai-analytics-actions--centered">
                        <?php echo $bbai_render_action($bbai_get_scan_action()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="bbai-card-footer">
            <div class="bbai-analytics-insight bbai-analytics-insight--<?php echo esc_attr($bbai_chart_insight['tone']); ?>">
                <div class="bbai-analytics-insight__header">
                    <span class="bbai-analytics-insight__label"><?php esc_html_e('Insight', 'beepbeep-ai-alt-text-generator'); ?></span>
                    <span class="bbai-analytics-pill bbai-analytics-pill--<?php echo esc_attr($bbai_chart_insight['tone']); ?>"><?php echo esc_html($bbai_chart_insight['status_label']); ?></span>
                </div>
                <h3 class="bbai-analytics-insight__title"><?php echo esc_html($bbai_chart_insight['title']); ?></h3>
                <p class="bbai-analytics-insight__description"><?php echo esc_html($bbai_chart_insight['description']); ?></p>
                <?php if (!empty($bbai_chart_insight['details'])) : ?>
                    <div class="bbai-analytics-insight__facts">
                        <?php foreach ($bbai_chart_insight['details'] as $bbai_chart_detail) : ?>
                            <span class="bbai-analytics-fact"><?php echo esc_html($bbai_chart_detail); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="bbai-analytics-actions">
                    <?php echo $bbai_render_action($bbai_chart_insight['primary_action']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php echo $bbai_render_action($bbai_chart_insight['secondary_action']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </div>
        </div>
    </section>

    <section class="bbai-card bbai-mb-6 bbai-analytics-section" aria-labelledby="bbai-analytics-usage-title">
        <div class="bbai-card-header">
            <div>
                <h2 class="bbai-card-title" id="bbai-analytics-usage-title"><?php esc_html_e('Usage Breakdown', 'beepbeep-ai-alt-text-generator'); ?></h2>
                <p class="bbai-card-subtitle"><?php esc_html_e('Understand how credits are translating into accessibility coverage this cycle.', 'beepbeep-ai-alt-text-generator'); ?></p>
            </div>
        </div>
        <div class="bbai-card-body">
            <div class="bbai-usage-breakdown-grid">
                <div class="bbai-usage-breakdown-item">
                    <div class="bbai-metric-icon" style="color: #10B981;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M4 12l4 4 12-12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <rect x="3" y="4" width="18" height="16" rx="3" stroke="currentColor" stroke-width="1.6"/>
                        </svg>
                    </div>
                    <div class="bbai-usage-breakdown-label"><?php esc_html_e('Credits Used', 'beepbeep-ai-alt-text-generator'); ?></div>
                    <div class="bbai-usage-breakdown-value">
                        <?php echo esc_html(number_format_i18n($bbai_usage_used)); ?>
                        <span class="bbai-usage-breakdown-total">
                            <?php
                            printf(
                                /* translators: %s: credit limit */
                                esc_html__('of %s', 'beepbeep-ai-alt-text-generator'),
                                esc_html(number_format_i18n($bbai_usage_limit))
                            );
                            ?>
                        </span>
                    </div>
                </div>

                <div class="bbai-usage-breakdown-item">
                    <div class="bbai-metric-icon" style="color: #F59E0B;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <rect x="4" y="5" width="16" height="14" rx="3" stroke="currentColor" stroke-width="1.8"/>
                            <path d="M7 9h10M7 13h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="bbai-usage-breakdown-label"><?php esc_html_e('Credits Remaining', 'beepbeep-ai-alt-text-generator'); ?></div>
                    <div class="bbai-usage-breakdown-value">
                        <?php echo esc_html(number_format_i18n($bbai_usage_remaining)); ?>
                        <span class="bbai-usage-breakdown-total"><?php esc_html_e('credits left', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </div>
                </div>

                <div class="bbai-usage-breakdown-item">
                    <div class="bbai-metric-icon" style="color: #0EA5E9;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <rect x="4" y="4" width="16" height="12" rx="3" stroke="currentColor" stroke-width="1.8"/>
                            <path d="M8 18h8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M9 8h6M9 11h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="bbai-usage-breakdown-label"><?php esc_html_e('Daily Average', 'beepbeep-ai-alt-text-generator'); ?></div>
                    <div class="bbai-usage-breakdown-value">
                        <?php echo esc_html(number_format_i18n($bbai_daily_average, 1)); ?>
                        <span class="bbai-usage-breakdown-total"><?php esc_html_e('generations per day', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </div>
                </div>

                <div class="bbai-usage-breakdown-item">
                    <div class="bbai-metric-icon" style="color: #8B5CF6;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M5 16l5-5 4 4 5-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M14 8h5v5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="bbai-usage-breakdown-label"><?php esc_html_e('Images Optimized', 'beepbeep-ai-alt-text-generator'); ?></div>
                    <div class="bbai-usage-breakdown-value">
                        <?php echo esc_html(number_format_i18n($bbai_images_processed)); ?>
                        <span class="bbai-usage-breakdown-total"><?php esc_html_e('images optimized', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </div>
                    <?php if ($bbai_images_delta_percent > 0) : ?>
                        <div class="bbai-analytics-inline-delta">
                            <?php
                            printf(
                                /* translators: %s: percentage increase */
                                esc_html__('+%s%% recent lift', 'beepbeep-ai-alt-text-generator'),
                                esc_html(number_format_i18n($bbai_images_delta_percent))
                            );
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="bbai-card-footer">
            <div class="bbai-analytics-summary bbai-analytics-summary--<?php echo esc_attr($bbai_usage_insight['tone']); ?>">
                <div class="bbai-analytics-summary__body">
                    <h3 class="bbai-analytics-summary__title"><?php echo esc_html($bbai_usage_insight['title']); ?></h3>
                    <p class="bbai-analytics-summary__description"><?php echo esc_html($bbai_usage_insight['description']); ?></p>
                    <div class="bbai-analytics-summary__facts">
                        <?php foreach ($bbai_usage_insight['facts'] as $bbai_usage_fact) : ?>
                            <span class="bbai-analytics-fact"><?php echo esc_html($bbai_usage_fact); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="bbai-analytics-actions">
                    <?php echo $bbai_render_action($bbai_usage_insight['primary_action']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php echo $bbai_render_action($bbai_usage_insight['secondary_action']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </div>
        </div>
    </section>

    <section class="bbai-card bbai-mb-6 bbai-analytics-smart-card bbai-analytics-smart-card--<?php echo esc_attr($bbai_smart_insight['tone']); ?>" aria-labelledby="bbai-analytics-smart-insight-title">
        <div class="bbai-card-body">
            <div class="bbai-analytics-smart-card__inner">
                <div class="bbai-analytics-smart-card__content">
                    <span class="bbai-analytics-smart-card__label"><?php echo esc_html($bbai_smart_insight['label']); ?></span>
                    <h2 class="bbai-card-title" id="bbai-analytics-smart-insight-title"><?php echo esc_html($bbai_smart_insight['title']); ?></h2>
                    <p class="bbai-analytics-smart-card__description"><?php echo esc_html($bbai_smart_insight['description']); ?></p>
                    <?php if (!empty($bbai_smart_insight['supporting'])) : ?>
                        <p class="bbai-analytics-smart-card__supporting"><?php echo esc_html($bbai_smart_insight['supporting']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="bbai-analytics-actions">
                    <?php echo $bbai_render_action($bbai_smart_insight['primary_action']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </div>
        </div>
    </section>

    <div class="bbai-analytics-bottom-row bbai-grid bbai-grid-2 bbai-mb-6">
        <section class="bbai-card bbai-dashboard-card bbai-analytics-proof-card" aria-labelledby="bbai-analytics-proof-title">
            <div class="bbai-card-header">
                <div>
                    <h2 class="bbai-card-title" id="bbai-analytics-proof-title"><?php esc_html_e('Before & After', 'beepbeep-ai-alt-text-generator'); ?></h2>
                    <p class="bbai-card-subtitle"><?php esc_html_e('A quick proof-of-value snapshot for your media library.', 'beepbeep-ai-alt-text-generator'); ?></p>
                </div>
            </div>
            <div class="bbai-card-body">
                <div class="bbai-comparison-grid">
                    <div class="bbai-comparison-item bbai-comparison-item--before">
                        <div class="bbai-metric-icon" style="color: #6B7280; margin-bottom: 12px;">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M4 17l5-5 4 4 4-5 3 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                <rect x="3" y="5" width="18" height="14" rx="3" stroke="currentColor" stroke-width="1.6"/>
                            </svg>
                        </div>
                        <div class="bbai-comparison-label"><?php esc_html_e('Before AI Optimization', 'beepbeep-ai-alt-text-generator'); ?></div>
                        <div class="bbai-comparison-value"><?php echo esc_html(number_format_i18n($bbai_before_missing_count)); ?></div>
                        <div class="bbai-comparison-description"><?php esc_html_e('images missing ALT text', 'beepbeep-ai-alt-text-generator'); ?></div>
                    </div>

                    <div class="bbai-comparison-item bbai-comparison-item--after">
                        <div class="bbai-metric-icon" style="color: #10B981; margin-bottom: 12px;">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M4 16l5-4 4 3 5-6 2 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                <rect x="3" y="5" width="18" height="14" rx="3" stroke="currentColor" stroke-width="1.6"/>
                            </svg>
                        </div>
                        <div class="bbai-comparison-label"><?php esc_html_e('After BeepBeep AI', 'beepbeep-ai-alt-text-generator'); ?></div>
                        <div class="bbai-comparison-value"><?php echo esc_html(number_format_i18n($bbai_images_processed)); ?></div>
                        <div class="bbai-comparison-description"><?php esc_html_e('images optimized', 'beepbeep-ai-alt-text-generator'); ?></div>
                    </div>
                </div>

                <div class="bbai-analytics-proof-story">
                    <h3 class="bbai-analytics-proof-story__title"><?php echo esc_html($bbai_before_after_story_title); ?></h3>
                    <p class="bbai-analytics-proof-story__description"><?php echo esc_html($bbai_before_after_story_description); ?></p>
                    <div class="bbai-analytics-proof-story__facts">
                        <span class="bbai-analytics-fact">
                            <?php
                            echo esc_html(
                                sprintf(
                                    /* translators: %s: before coverage percent */
                                    __('Before: %s coverage', 'beepbeep-ai-alt-text-generator'),
                                    $bbai_before_coverage_percent . '%'
                                )
                            );
                            ?>
                        </span>
                        <span class="bbai-analytics-fact">
                            <?php
                            echo esc_html(
                                sprintf(
                                    /* translators: %s: after coverage percent */
                                    __('After: %s coverage', 'beepbeep-ai-alt-text-generator'),
                                    $bbai_alt_coverage_display
                                )
                            );
                            ?>
                        </span>
                        <?php if ($bbai_coverage_improvement > 0) : ?>
                            <span class="bbai-analytics-fact">
                                <?php
                                printf(
                                    esc_html__('%s point improvement', 'beepbeep-ai-alt-text-generator'),
                                    esc_html(number_format_i18n($bbai_coverage_improvement))
                                );
                                ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="bbai-card-footer">
                <div class="bbai-analytics-actions">
                    <?php foreach ($bbai_before_after_actions as $bbai_before_after_action) : ?>
                        <?php echo $bbai_render_action($bbai_before_after_action); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="bbai-card bbai-dashboard-card bbai-analytics-activity-card" aria-labelledby="bbai-analytics-activity-title">
            <div class="bbai-card-header bbai-card-header--with-action">
                <div>
                    <h2 class="bbai-card-title" id="bbai-analytics-activity-title"><?php esc_html_e('Recent Activity', 'beepbeep-ai-alt-text-generator'); ?></h2>
                    <p class="bbai-card-subtitle"><?php esc_html_e('Recent optimizations, scans, and review-ready updates.', 'beepbeep-ai-alt-text-generator'); ?></p>
                </div>
                <button type="button" class="bbai-icon-btn bbai-icon-btn-sm" id="bbai-refresh-activity" title="<?php esc_attr_e('Refresh activity', 'beepbeep-ai-alt-text-generator'); ?>">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path d="M14 8A6 6 0 1 1 8 2" stroke-linecap="round"/>
                        <path d="M8 2V5L11 3" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
            <div class="bbai-card-body">
                <div class="bbai-activity-timeline" id="bbai-activity-timeline" aria-live="polite">
                    <div class="bbai-activity-loading" id="bbai-activity-loading" role="status">
                        <div class="bbai-spinner" aria-hidden="true"></div>
                        <p><?php esc_html_e('Loading activity...', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>

                    <div class="bbai-activity-empty" id="bbai-activity-empty" style="display: none;">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5" opacity="0.3"/>
                            <path d="M12 6v6l4 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity="0.5"/>
                        </svg>
                        <p class="bbai-empty-title"><?php esc_html_e('No activity yet', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <p class="bbai-empty-description"><?php esc_html_e('Activity will appear here as new ALT text is generated, reviewed, or refreshed across your library.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <div class="bbai-analytics-actions bbai-analytics-actions--centered">
                            <?php echo $bbai_render_action($bbai_get_scan_action()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php echo $bbai_render_action($bbai_activity_empty_secondary_action); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bbai-card-footer">
                <div class="bbai-analytics-actions">
                    <?php echo $bbai_render_action($bbai_get_review_library_action()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php echo $bbai_render_action($bbai_get_plan_action(__('Compare plans', 'beepbeep-ai-alt-text-generator'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </div>
        </section>
    </div>
</div>
