<?php
/**
 * Clean dashboard command center for authenticated users.
 */

if (!defined('ABSPATH')) {
    exit;
}

use BeepBeepAI\AltTextGenerator\Usage_Tracker;
use BeepBeepAI\AltTextGenerator\Admin\Plan_Helpers;

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/class-plan-helpers.php';

$bbai_build_action = static function (string $label = '', array $overrides = []): array {
    return array_merge(
        [
            'label' => $label,
            'href' => '#',
            'action' => '',
            'bbai_action' => '',
            'aria_label' => '',
        ],
        $overrides
    );
};

$bbai_build_action_attrs = static function (array $action): string {
    $href = isset($action['href']) && '' !== (string) $action['href'] ? (string) $action['href'] : '#';
    $attributes = [
        'href="' . esc_url($href) . '"',
    ];

    if (!empty($action['action'])) {
        $attributes[] = 'data-action="' . esc_attr((string) $action['action']) . '"';
    }

    if (!empty($action['bbai_action'])) {
        $attributes[] = 'data-bbai-action="' . esc_attr((string) $action['bbai_action']) . '"';
    }

    if (!empty($action['aria_label'])) {
        $attributes[] = 'aria-label="' . esc_attr((string) $action['aria_label']) . '"';
    }

    return implode(' ', $attributes);
};

$bbai_render_action_link = static function (array $action, string $class_name, string $data_attribute) use ($bbai_build_action_attrs): void {
    $label = isset($action['label']) ? (string) $action['label'] : '';
    $attrs = '' !== $label ? $bbai_build_action_attrs($action) : 'href="#"';
    ?>
    <a
        <?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes are assembled from escaped values. ?>
        class="<?php echo esc_attr($class_name); ?>"
        <?php echo esc_attr($data_attribute); ?>
        <?php echo '' !== $label ? '' : 'hidden'; ?>
    ><?php echo esc_html($label); ?></a>
    <?php
};

$bbai_format_reset_timing = static function (?int $days_until_reset, string $fallback = ''): string {
    if (null !== $days_until_reset) {
        return sprintf(
            _n('Resets in %s day', 'Resets in %s days', $days_until_reset, 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($days_until_reset)
        );
    }

    return '' !== $fallback ? $fallback : __('Resets monthly', 'beepbeep-ai-alt-text-generator');
};

$bbai_format_reset_value = static function (?int $days_until_reset, string $fallback = ''): string {
    if (null !== $days_until_reset) {
        return sprintf(
            _n('%s day', '%s days', $days_until_reset, 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($days_until_reset)
        );
    }

    if ('' !== $fallback) {
        return preg_replace('/^Resets\s+/i', '', $fallback) ?: $fallback;
    }

    return __('Monthly', 'beepbeep-ai-alt-text-generator');
};

$bbai_format_percentage_label = static function (float $value): string {
    $numeric_value = is_finite($value) ? max(0.0, $value) : 0.0;

    if ($numeric_value >= 100) {
        return '100';
    }

    if ($numeric_value < 0.01 && $numeric_value > 0) {
        return '<0.01';
    }

    if ($numeric_value < 0.1) {
        return number_format_i18n($numeric_value, 2);
    }

    if ($numeric_value < 10) {
        return number_format_i18n($numeric_value, 1);
    }

    return number_format_i18n($numeric_value, 0);
};

$bbai_build_donut_background = static function (int $optimized, int $weak, int $missing, int $total): string {
    if ($total <= 0) {
        return 'conic-gradient(#e2e8f0 0deg 360deg)';
    }

    $optimized_angle = (360 * $optimized) / $total;
    $weak_angle = (360 * $weak) / $total;
    $optimized_end = max(0, min(360, $optimized_angle));
    $weak_end = max($optimized_end, min(360, $optimized_end + $weak_angle));

    return sprintf(
        'conic-gradient(#22c55e 0deg %.3Fdeg, #f59e0b %.3Fdeg %.3Fdeg, #ef4444 %.3Fdeg 360deg)',
        $optimized_end,
        $optimized_end,
        $weak_end,
        $weak_end
    );
};

$bbai_format_last_scan = static function (int $timestamp): string {
    $now = (int) current_time('timestamp');
    if ($timestamp <= 0 || $timestamp > $now) {
        return '';
    }

    $diff = $now - $timestamp;
    if ($diff < DAY_IN_SECONDS * 7) {
        return sprintf(
            __('Last scan %s ago', 'beepbeep-ai-alt-text-generator'),
            human_time_diff($timestamp, $now)
        );
    }

    return sprintf(
        __('Last scan %s', 'beepbeep-ai-alt-text-generator'),
        date_i18n(get_option('date_format'), $timestamp)
    );
};

$bbai_render_dashboard_alert = static function (array $alert) use ($bbai_render_action_link): void {
    ?>
    <section
        class="bbai-dashboard-alert-card bbai-dashboard-alert-card--<?php echo esc_attr($alert['tone'] ?? 'warning'); ?>"
        data-bbai-dashboard-alert="1"
        aria-labelledby="bbai-dashboard-alert-title"
        <?php echo !empty($alert['show']) ? '' : 'hidden'; ?>
    >
        <div class="bbai-dashboard-alert-card__copy">
            <h2 id="bbai-dashboard-alert-title" class="bbai-dashboard-alert-card__title" data-bbai-dashboard-alert-title><?php echo esc_html($alert['title'] ?? ''); ?></h2>
            <p class="bbai-dashboard-alert-card__message" data-bbai-dashboard-alert-message><?php echo esc_html($alert['message'] ?? ''); ?></p>
        </div>
        <div class="bbai-dashboard-alert-card__actions">
            <?php $bbai_render_action_link($alert['primary_action'] ?? [], 'bbai-command-action bbai-command-action--primary', 'data-bbai-dashboard-alert-primary'); ?>
            <?php $bbai_render_action_link($alert['secondary_action'] ?? [], 'bbai-command-action bbai-command-action--secondary', 'data-bbai-dashboard-alert-secondary'); ?>
        </div>
    </section>
    <?php
};

$bbai_is_authenticated = $this->api_client->is_authenticated();
$bbai_has_license = $this->api_client->has_active_license();
$bbai_has_registered_user = $bbai_has_registered_user ?? false;

if ($bbai_is_authenticated || $bbai_has_license || $bbai_has_registered_user) :
    $bbai_plan_data = Plan_Helpers::get_plan_data();
    $bbai_is_agency = !empty($bbai_plan_data['is_agency']);
    $bbai_is_premium = !empty($bbai_plan_data['is_pro']) || $bbai_is_agency;

    $bbai_credits_used = max(0, (int) ($bbai_usage_stats['used'] ?? 0));
    $bbai_credits_total = max(1, (int) ($bbai_usage_stats['limit'] ?? 50));
    $bbai_credits_remaining = isset($bbai_usage_stats['remaining'])
        ? max(0, (int) $bbai_usage_stats['remaining'])
        : max(0, $bbai_credits_total - $bbai_credits_used);

    if ($bbai_credits_used > $bbai_credits_total) {
        $bbai_credits_used = $bbai_credits_total;
        $bbai_credits_remaining = 0;
    }

    $bbai_coverage = (isset($this) && method_exists($this, 'get_alt_text_coverage_scan')) ? $this->get_alt_text_coverage_scan(false) : [];
    $bbai_missing_count = max(0, (int) ($bbai_stats['missing'] ?? 0));
    $bbai_weak_count = max(0, (int) ($bbai_coverage['needs_review_count'] ?? 0));
    $bbai_with_alt_count = max(0, (int) ($bbai_stats['with_alt'] ?? 0));
    $bbai_optimized_count = isset($bbai_stats['optimized_count'])
        ? max(0, (int) $bbai_stats['optimized_count'])
        : max(0, $bbai_with_alt_count - $bbai_weak_count);
    $bbai_total_images = max(
        0,
        max(
            (int) ($bbai_stats['total'] ?? 0),
            $bbai_optimized_count + $bbai_missing_count + $bbai_weak_count
        )
    );

    $bbai_reset_raw = (string) ($bbai_usage_stats['reset_date'] ?? '');
    $bbai_reset_timestamp_raw = isset($bbai_usage_stats['reset_timestamp']) ? (int) $bbai_usage_stats['reset_timestamp'] : 0;
    $bbai_reset_ts = $bbai_reset_timestamp_raw > 0 ? $bbai_reset_timestamp_raw : ($bbai_reset_raw !== '' ? strtotime($bbai_reset_raw) : false);
    $bbai_has_reset_timestamp = is_numeric($bbai_reset_ts) && (int) $bbai_reset_ts > 0;

    $bbai_days_until_reset = null;
    if ($bbai_has_reset_timestamp) {
        $bbai_days_until_reset = Usage_Tracker::calculate_days_until_reset((int) $bbai_reset_ts, (int) current_time('timestamp'));
    } elseif (isset($bbai_usage_stats['days_until_reset']) && is_numeric($bbai_usage_stats['days_until_reset'])) {
        $bbai_days_until_reset = max(0, (int) $bbai_usage_stats['days_until_reset']);
    }

    $bbai_reset_timing = $bbai_format_reset_timing(
        $bbai_days_until_reset,
        ($bbai_reset_raw !== '' || $bbai_has_reset_timestamp)
            ? sprintf(__('Resets %s', 'beepbeep-ai-alt-text-generator'), date_i18n(get_option('date_format'), $bbai_has_reset_timestamp ? (int) $bbai_reset_ts : current_time('timestamp')))
            : __('Resets monthly', 'beepbeep-ai-alt-text-generator')
    );

    $bbai_credits_reset_line = ($bbai_reset_raw !== '' || $bbai_has_reset_timestamp)
        ? sprintf(__('Credits reset %s', 'beepbeep-ai-alt-text-generator'), date_i18n('F j, Y', $bbai_has_reset_timestamp ? (int) $bbai_reset_ts : current_time('timestamp')))
        : __('Credits reset monthly', 'beepbeep-ai-alt-text-generator');

    $bbai_last_scan_timestamp = isset($bbai_coverage['scanned_at']) ? max(0, (int) $bbai_coverage['scanned_at']) : 0;
    $bbai_has_scan_history = $bbai_last_scan_timestamp > 0;
    $bbai_has_scan_results = $bbai_total_images > 0 || $bbai_missing_count > 0 || $bbai_weak_count > 0 || $bbai_optimized_count > 0;
    $bbai_coverage_percent = $bbai_total_images > 0 ? (int) round(($bbai_optimized_count / $bbai_total_images) * 100) : 0;
    $bbai_usage_percent = $bbai_credits_total > 0 ? (int) round(($bbai_credits_used / $bbai_credits_total) * 100) : 0;
    $bbai_growth_capacity = $bbai_is_premium ? max(1000, $bbai_credits_total) : 1000;
    $bbai_growth_usage_percent = max(0, min(100, ($bbai_credits_used / max(1, $bbai_growth_capacity)) * 100));
    $bbai_growth_usage_display = $bbai_format_percentage_label($bbai_growth_usage_percent);
    $bbai_growth_usage_line = $bbai_is_premium
        ? sprintf(
            __('You are using %s%% of Growth capacity this month.', 'beepbeep-ai-alt-text-generator'),
            $bbai_growth_usage_display
        )
        : sprintf(
            __('On Growth, this usage would be %s%% of monthly capacity.', 'beepbeep-ai-alt-text-generator'),
            $bbai_growth_usage_display
        );
    $bbai_donut_background = $bbai_build_donut_background($bbai_optimized_count, $bbai_weak_count, $bbai_missing_count, $bbai_total_images);

    $isHealthy = $bbai_missing_count === 0 && $bbai_weak_count === 0 && $bbai_total_images > 0;
    $isProPlan = $bbai_is_premium;
    $missingCount = $bbai_missing_count;
    $weakCount = $bbai_weak_count;
    $optimizedCount = $bbai_optimized_count;
    $totalImages = $bbai_total_images;
    $coveragePercent = $bbai_coverage_percent;
    $creditsUsed = $bbai_credits_used;
    $creditsLimit = $bbai_credits_total;
    $creditsRemaining = $bbai_credits_remaining;
    $daysUntilReset = null !== $bbai_days_until_reset ? (int) $bbai_days_until_reset : 0;
    $usagePercent = $bbai_usage_percent;
    $isLowCredits = $creditsRemaining < 10 && $creditsRemaining > 0;
    $isOutOfCredits = $creditsRemaining === 0;
    $hasScanHistory = $bbai_has_scan_history;
    $isFirstRun = !$hasScanHistory || $totalImages === 0;

    $bbai_library_url = add_query_arg(['page' => 'bbai-library'], admin_url('admin.php'));
    $bbai_optimized_library_url = add_query_arg(['page' => 'bbai-library', 'status' => 'optimized'], admin_url('admin.php'));
    $bbai_needs_review_library_url = add_query_arg(['page' => 'bbai-library', 'status' => 'needs_review'], admin_url('admin.php'));
    $bbai_missing_library_url = add_query_arg(['page' => 'bbai-library', 'status' => 'missing'], admin_url('admin.php'));
    $bbai_usage_url = admin_url('admin.php?page=bbai-credit-usage');
    $bbai_settings_url = admin_url('admin.php?page=bbai-settings');
    $bbai_guide_url = admin_url('admin.php?page=bbai-guide');

    $bbai_status_detail = '';
    if ($isFirstRun) {
        $bbai_status_detail = __('Run your first scan to see coverage and missing ALT text.', 'beepbeep-ai-alt-text-generator');
    } elseif ($missingCount > 0) {
        $bbai_status_detail = sprintf(
            _n('%s image is missing ALT text.', '%s images are missing ALT text.', $missingCount, 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($missingCount)
        );
    } elseif ($weakCount > 0) {
        $bbai_status_detail = sprintf(
            _n('%s description needs review.', '%s descriptions need review.', $weakCount, 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($weakCount)
        );
    } elseif ($totalImages > 0) {
        $bbai_status_detail = __('All current images include ALT text.', 'beepbeep-ai-alt-text-generator');
    }

    $bbai_plan_primary_action = $bbai_build_action(
        $isProPlan ? __('Review usage', 'beepbeep-ai-alt-text-generator') : __('Upgrade', 'beepbeep-ai-alt-text-generator'),
        $isProPlan
            ? [
                'href' => $bbai_usage_url,
                'aria_label' => __('Review usage', 'beepbeep-ai-alt-text-generator'),
            ]
            : [
                'action' => 'show-upgrade-modal',
                'aria_label' => __('Upgrade plan', 'beepbeep-ai-alt-text-generator'),
            ]
    );

    $bbai_plan_secondary_action = !$isProPlan
        ? $bbai_build_action(
            __('Review usage', 'beepbeep-ai-alt-text-generator'),
            [
                'href' => $bbai_usage_url,
                'aria_label' => __('Review usage', 'beepbeep-ai-alt-text-generator'),
            ]
        )
        : $bbai_build_action();

    $bbai_get_alert_state = static function (array $state) use ($bbai_build_action): array {
        $missing = max(0, (int) ($state['missingCount'] ?? 0));
        $weak = max(0, (int) ($state['weakCount'] ?? 0));
        $remaining = max(0, (int) ($state['creditsRemaining'] ?? 0));
        $is_pro = !empty($state['isProPlan']);
        $library_url = (string) ($state['libraryUrl'] ?? '#');
        $missing_library_url = (string) ($state['missingLibraryUrl'] ?? $library_url);
        $needs_review_library_url = (string) ($state['needsReviewLibraryUrl'] ?? $library_url);
        $usage_url = (string) ($state['usageUrl'] ?? '#');

        if (!empty($state['isOutOfCredits'])) {
            return [
                'show' => true,
                'tone' => 'danger',
                'title' => __('You have no optimizations left this cycle', 'beepbeep-ai-alt-text-generator'),
                'message' => __('Upgrade or wait for your allowance to reset to continue ALT generation.', 'beepbeep-ai-alt-text-generator'),
                'primary_action' => $bbai_build_action(
                    __('Upgrade', 'beepbeep-ai-alt-text-generator'),
                    [
                        'action' => 'show-upgrade-modal',
                        'aria_label' => __('Upgrade to continue ALT generation', 'beepbeep-ai-alt-text-generator'),
                    ]
                ),
                'secondary_action' => $bbai_build_action(
                    __('Review usage', 'beepbeep-ai-alt-text-generator'),
                    [
                        'href' => $usage_url,
                        'aria_label' => __('Review usage', 'beepbeep-ai-alt-text-generator'),
                    ]
                ),
            ];
        }

        if ($missing > 0) {
            return [
                'show' => true,
                'tone' => 'warning',
                'title' => __('Fix missing ALT text', 'beepbeep-ai-alt-text-generator'),
                'message' => _n('Fix the remaining image to reach full coverage.', 'Fix the remaining images to reach full coverage.', $missing, 'beepbeep-ai-alt-text-generator'),
                'primary_action' => $bbai_build_action(
                    __('Fix missing ALT text', 'beepbeep-ai-alt-text-generator'),
                    [
                        'action' => 'generate-missing',
                        'bbai_action' => 'generate_missing',
                        'aria_label' => __('Fix missing ALT text', 'beepbeep-ai-alt-text-generator'),
                    ]
                ),
                'secondary_action' => $bbai_build_action(
                    __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
                    [
                        'href' => $missing_library_url,
                        'aria_label' => __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
                    ]
                ),
            ];
        }

        if ($weak > 0) {
            return [
                'show' => true,
                'tone' => 'warning',
                'title' => __('Review ALT text', 'beepbeep-ai-alt-text-generator'),
                'message' => _n('Review the remaining description to reach full coverage.', 'Review the remaining descriptions to reach full coverage.', $weak, 'beepbeep-ai-alt-text-generator'),
                'primary_action' => $bbai_build_action(
                    __('Review ALT text', 'beepbeep-ai-alt-text-generator'),
                    [
                        'action' => 'show-generate-alt-modal',
                        'aria_label' => __('Review ALT text', 'beepbeep-ai-alt-text-generator'),
                    ]
                ),
                'secondary_action' => $bbai_build_action(
                    __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
                    [
                        'href' => $needs_review_library_url,
                        'aria_label' => __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
                    ]
                ),
            ];
        }

        if (!empty($state['isLowCredits'])) {
            return [
                'show' => true,
                'tone' => 'warning',
                'title' => sprintf(
                    _n('%s optimization left this cycle', '%s optimizations left this cycle', $remaining, 'beepbeep-ai-alt-text-generator'),
                    number_format_i18n($remaining)
                ),
                'message' => $is_pro
                    ? __('Review your usage before the cycle resets.', 'beepbeep-ai-alt-text-generator')
                    : __('Upgrade to keep optimizing without interruption.', 'beepbeep-ai-alt-text-generator'),
                'primary_action' => $is_pro
                    ? $bbai_build_action(
                        __('Review usage', 'beepbeep-ai-alt-text-generator'),
                        [
                            'href' => $usage_url,
                            'aria_label' => __('Review usage', 'beepbeep-ai-alt-text-generator'),
                        ]
                    )
                    : $bbai_build_action(
                        __('Upgrade', 'beepbeep-ai-alt-text-generator'),
                        [
                            'action' => 'show-upgrade-modal',
                            'aria_label' => __('Upgrade to keep optimizing without interruption', 'beepbeep-ai-alt-text-generator'),
                        ]
                    ),
                'secondary_action' => $is_pro
                    ? $bbai_build_action(
                        __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
                        [
                            'href' => $library_url,
                            'aria_label' => __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
                        ]
                    )
                    : $bbai_build_action(
                        __('Review usage', 'beepbeep-ai-alt-text-generator'),
                        [
                            'href' => $usage_url,
                            'aria_label' => __('Review usage', 'beepbeep-ai-alt-text-generator'),
                        ]
                    ),
            ];
        }

        return [
            'show' => false,
            'tone' => 'warning',
            'title' => '',
            'message' => '',
            'primary_action' => $bbai_build_action(),
            'secondary_action' => $bbai_build_action(),
        ];
    };

    $bbai_dashboard_state = [
        'isHealthy' => $isHealthy,
        'isProPlan' => $isProPlan,
        'missingCount' => $missingCount,
        'weakCount' => $weakCount,
        'optimizedCount' => $optimizedCount,
        'totalImages' => $totalImages,
        'coveragePercent' => $coveragePercent,
        'creditsUsed' => $creditsUsed,
        'creditsLimit' => $creditsLimit,
        'creditsRemaining' => $creditsRemaining,
        'daysUntilReset' => $daysUntilReset,
        'usagePercent' => $usagePercent,
        'isLowCredits' => $isLowCredits,
        'isOutOfCredits' => $isOutOfCredits,
        'hasScanHistory' => $hasScanHistory,
        'hasScanResults' => $bbai_has_scan_results,
        'isFirstRun' => $isFirstRun,
        'lastScanTimestamp' => $bbai_last_scan_timestamp,
        'lastScanLine' => $bbai_format_last_scan($bbai_last_scan_timestamp),
        'planLabel' => $isProPlan ? __('Growth plan', 'beepbeep-ai-alt-text-generator') : __('Free plan', 'beepbeep-ai-alt-text-generator'),
        'resetTiming' => $bbai_reset_timing,
        'compactResetTiming' => $bbai_format_reset_value($bbai_days_until_reset, $bbai_reset_timing),
        'creditsResetLine' => $bbai_credits_reset_line,
        'usageLine' => sprintf(
            __('%1$s / %2$s used', 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($creditsUsed),
            number_format_i18n($creditsLimit)
        ),
        'remainingLine' => sprintf(
            _n('%s remaining', '%s remaining', $creditsRemaining, 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($creditsRemaining)
        ),
        'statusSummary' => $totalImages > 0
            ? sprintf(
                _n('%1$s / %2$s image optimized', '%1$s / %2$s images optimized', $totalImages, 'beepbeep-ai-alt-text-generator'),
                number_format_i18n($optimizedCount),
                number_format_i18n($totalImages)
            )
            : __('No images found in your media library', 'beepbeep-ai-alt-text-generator'),
        'statusDetail' => $bbai_status_detail,
        'libraryUrl' => $bbai_library_url,
        'missingLibraryUrl' => $bbai_missing_library_url,
        'needsReviewLibraryUrl' => $bbai_needs_review_library_url,
        'usageUrl' => $bbai_usage_url,
        'settingsUrl' => $bbai_settings_url,
        'guideUrl' => $bbai_guide_url,
        'planPrimaryAction' => $bbai_plan_primary_action,
        'planSecondaryAction' => $bbai_plan_secondary_action,
    ];

    $bbai_primary_action = 'scan';
    if ($isOutOfCredits) {
        $bbai_primary_action = 'upgrade';
    } elseif ($missingCount > 0) {
        $bbai_primary_action = 'generate_missing';
    } elseif ($weakCount > 0) {
        $bbai_primary_action = 'review_weak';
    } elseif ($bbai_has_scan_results) {
        $bbai_primary_action = 'review_library';
    }

    $bbai_alert_state = $bbai_get_alert_state($bbai_dashboard_state);
    $bbai_review_prompt_url = 'https://wordpress.org/support/plugin/beepbeep-ai-alt-text-generator/reviews/#new-post';
    $bbai_show_review_prompt = $coveragePercent >= 95
        && $optimizedCount >= 20
        && $missingCount === 0
        && $weakCount === 0
        && !$isLowCredits
        && !$isOutOfCredits;
    $bbai_plan_primary_class = $isProPlan
        ? 'bbai-command-action bbai-command-action--secondary'
        : 'bbai-command-action bbai-command-action--primary';
    $bbai_plan_secondary_class = $isProPlan
        ? 'bbai-command-action bbai-command-action--tertiary'
        : 'bbai-command-action bbai-command-action--secondary';
    ?>

    <div
        id="bbai-dashboard-main"
        class="bbai-dashboard"
        data-bbai-dashboard-container
        data-bbai-dashboard-root="1"
        data-bbai-missing-count="<?php echo esc_attr($missingCount); ?>"
        data-bbai-weak-count="<?php echo esc_attr($weakCount); ?>"
        data-bbai-optimized-count="<?php echo esc_attr($optimizedCount); ?>"
        data-bbai-total-count="<?php echo esc_attr($totalImages); ?>"
        data-bbai-generated-count="<?php echo esc_attr(max(0, (int) ($bbai_stats['generated'] ?? $optimizedCount))); ?>"
        data-bbai-credits-used="<?php echo esc_attr($creditsUsed); ?>"
        data-bbai-credits-total="<?php echo esc_attr($creditsLimit); ?>"
        data-bbai-credits-remaining="<?php echo esc_attr($creditsRemaining); ?>"
        data-bbai-credits-reset-line="<?php echo esc_attr($bbai_credits_reset_line); ?>"
        data-bbai-is-premium="<?php echo esc_attr($isProPlan ? '1' : '0'); ?>"
        data-bbai-has-scan-results="<?php echo esc_attr($bbai_has_scan_results ? '1' : '0'); ?>"
        data-bbai-last-scan-ts="<?php echo esc_attr($bbai_last_scan_timestamp); ?>"
        data-bbai-primary-action="<?php echo esc_attr($bbai_primary_action); ?>"
        data-bbai-library-url="<?php echo esc_url($bbai_library_url); ?>"
        data-bbai-missing-library-url="<?php echo esc_url($bbai_missing_library_url); ?>"
        data-bbai-needs-review-library-url="<?php echo esc_url($bbai_needs_review_library_url); ?>"
        data-bbai-settings-url="<?php echo esc_url($bbai_settings_url); ?>"
        data-bbai-usage-url="<?php echo esc_url($bbai_usage_url); ?>"
        data-bbai-guide-url="<?php echo esc_url($bbai_guide_url); ?>"
    >
        <div id="bbai-limit-state-root" class="bbai-limit-state-root" hidden></div>

        <?php
        $bbai_banner_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-success-banner.php';
        if (file_exists($bbai_banner_partial)) {
            include $bbai_banner_partial;
        }
        ?>

        <div class="bbai-dashboard-command">
            <div class="bbai-dashboard-command__grid">
                <article class="bbai-dashboard-card bbai-command-card bbai-command-card--status" data-bbai-dashboard-status-card="1" aria-labelledby="bbai-status-title">
                    <header class="bbai-command-card__header">
                        <div>
                            <p class="bbai-command-card__eyebrow"><?php esc_html_e('Library status', 'beepbeep-ai-alt-text-generator'); ?></p>
                            <h2 id="bbai-status-title" class="bbai-command-card__title"><?php esc_html_e('Coverage at a glance', 'beepbeep-ai-alt-text-generator'); ?></h2>
                        </div>
                        <div class="bbai-command-card__meta-group">
                            <p class="bbai-command-card__meta" data-bbai-status-last-scan<?php echo '' !== ($bbai_dashboard_state['lastScanLine'] ?? '') ? '' : ' hidden'; ?>>
                                <?php echo esc_html($bbai_dashboard_state['lastScanLine'] ?? ''); ?>
                            </p>
                            <button
                                type="button"
                                class="bbai-command-card__refresh"
                                data-bbai-status-refresh
                                aria-label="<?php esc_attr_e('Refresh library scan', 'beepbeep-ai-alt-text-generator'); ?>"
                            >
                                <span class="bbai-command-card__refresh-icon" aria-hidden="true">
                                    <svg viewBox="0 0 20 20" fill="none" focusable="false">
                                        <path d="M16.667 10a6.667 6.667 0 1 1-1.953-4.714" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M13.333 3.333h3.334v3.334" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                                <span class="bbai-command-card__refresh-spinner" aria-hidden="true"></span>
                            </button>
                        </div>
                    </header>

                    <div class="bbai-command-status">
                        <div class="bbai-command-status__overview">
                            <div
                                class="bbai-command-donut"
                                data-bbai-status-donut
                                aria-hidden="true"
                                style="background: <?php echo esc_attr($bbai_donut_background); ?>;"
                            >
                                <span class="bbai-command-donut__inner"></span>
                            </div>

                            <div class="bbai-command-status__content">
                                <p class="bbai-command-status__label"><?php esc_html_e('Coverage', 'beepbeep-ai-alt-text-generator'); ?></p>
                                <div class="bbai-command-status__value-group">
                                    <p class="bbai-command-status__value">
                                        <span data-bbai-status-coverage-value><?php echo esc_html(number_format_i18n($coveragePercent)); ?></span><span>%</span>
                                    </p>
                                    <p class="bbai-command-status__summary-line" data-bbai-status-summary-ratio><?php echo esc_html($bbai_dashboard_state['statusSummary']); ?></p>
                                </div>
                            </div>
                        </div>

                        <nav class="bbai-command-status__summary" aria-label="<?php esc_attr_e('Open filtered views in ALT Library', 'beepbeep-ai-alt-text-generator'); ?>">
                            <a
                                class="bbai-command-breakdown bbai-command-breakdown--optimized"
                                href="<?php echo esc_url($bbai_optimized_library_url); ?>"
                                data-bbai-status-row
                                data-bbai-status-segment="optimized"
                                data-bbai-status-filter="optimized"
                                data-bbai-status-url="<?php echo esc_url($bbai_optimized_library_url); ?>"
                                aria-label="<?php esc_attr_e('View optimized images in ALT Library', 'beepbeep-ai-alt-text-generator'); ?>"
                            >
                                <span class="bbai-command-breakdown__label"><?php esc_html_e('Optimized', 'beepbeep-ai-alt-text-generator'); ?></span>
                                <span class="bbai-command-breakdown__meta">
                                    <span class="bbai-command-breakdown__value" data-bbai-status-metric="optimized"><?php echo esc_html(number_format_i18n($optimizedCount)); ?></span>
                                    <span class="bbai-command-breakdown__arrow" aria-hidden="true">→</span>
                                </span>
                            </a>
                            <a
                                class="bbai-command-breakdown bbai-command-breakdown--weak"
                                href="<?php echo esc_url($bbai_needs_review_library_url); ?>"
                                data-bbai-status-row
                                data-bbai-status-segment="weak"
                                data-bbai-status-filter="needs_review"
                                data-bbai-status-url="<?php echo esc_url($bbai_needs_review_library_url); ?>"
                                aria-label="<?php esc_attr_e('View images needing review in ALT Library', 'beepbeep-ai-alt-text-generator'); ?>"
                            >
                                <span class="bbai-command-breakdown__label"><?php esc_html_e('Needs review', 'beepbeep-ai-alt-text-generator'); ?></span>
                                <span class="bbai-command-breakdown__meta">
                                    <span class="bbai-command-breakdown__value" data-bbai-status-metric="weak"><?php echo esc_html(number_format_i18n($weakCount)); ?></span>
                                    <span class="bbai-command-breakdown__arrow" aria-hidden="true">→</span>
                                </span>
                            </a>
                            <a
                                class="bbai-command-breakdown bbai-command-breakdown--missing"
                                href="<?php echo esc_url($bbai_missing_library_url); ?>"
                                data-bbai-status-row
                                data-bbai-status-segment="missing"
                                data-bbai-status-filter="missing"
                                data-bbai-status-url="<?php echo esc_url($bbai_missing_library_url); ?>"
                                aria-label="<?php esc_attr_e('View images missing ALT text in ALT Library', 'beepbeep-ai-alt-text-generator'); ?>"
                            >
                                <span class="bbai-command-breakdown__label"><?php esc_html_e('Missing', 'beepbeep-ai-alt-text-generator'); ?></span>
                                <span class="bbai-command-breakdown__meta">
                                    <span class="bbai-command-breakdown__value" data-bbai-status-metric="missing"><?php echo esc_html(number_format_i18n($missingCount)); ?></span>
                                    <span class="bbai-command-breakdown__arrow" aria-hidden="true">→</span>
                                </span>
                            </a>
                        </nav>
                        <p class="bbai-command-status__detail" data-bbai-status-summary-detail<?php echo '' !== $bbai_status_detail ? '' : ' hidden'; ?>><?php echo esc_html($bbai_status_detail); ?></p>
                    </div>
                </article>

                <article class="bbai-dashboard-card bbai-command-card bbai-command-card--plan" aria-labelledby="bbai-plan-title">
                    <header class="bbai-command-card__header">
                        <div>
                            <p class="bbai-command-card__eyebrow"><?php esc_html_e('Plan & usage', 'beepbeep-ai-alt-text-generator'); ?></p>
                            <h2 id="bbai-plan-title" class="bbai-command-card__title"><?php esc_html_e('Current allowance', 'beepbeep-ai-alt-text-generator'); ?></h2>
                        </div>
                    </header>

                    <div class="bbai-command-plan">
                        <div class="bbai-command-plan__rows">
                            <div class="bbai-command-plan__row">
                                <span class="bbai-command-plan__label"><?php esc_html_e('Current plan', 'beepbeep-ai-alt-text-generator'); ?></span>
                                <strong class="bbai-command-plan__value" data-bbai-plan-label><?php echo esc_html($bbai_dashboard_state['planLabel']); ?></strong>
                            </div>
                            <div class="bbai-command-plan__row">
                                <span class="bbai-command-plan__label"><?php esc_html_e('This month', 'beepbeep-ai-alt-text-generator'); ?></span>
                                <strong class="bbai-command-plan__value" data-bbai-plan-usage-line><?php echo esc_html($bbai_dashboard_state['usageLine']); ?></strong>
                            </div>
                            <div class="bbai-command-plan__row">
                                <span class="bbai-command-plan__label"><?php esc_html_e('Remaining', 'beepbeep-ai-alt-text-generator'); ?></span>
                                <strong class="bbai-command-plan__value" data-bbai-plan-usage-remaining><?php echo esc_html($bbai_dashboard_state['remainingLine']); ?></strong>
                            </div>
                            <div class="bbai-command-plan__row">
                                <span class="bbai-command-plan__label"><?php esc_html_e('Reset', 'beepbeep-ai-alt-text-generator'); ?></span>
                                <strong class="bbai-command-plan__value" data-bbai-plan-usage-reset><?php echo esc_html($bbai_dashboard_state['compactResetTiming']); ?></strong>
                            </div>
                        </div>
                        <div class="bbai-command-plan__usage">
                            <div class="bbai-command-meter bbai-command-meter--plan" aria-hidden="true">
                                <span class="bbai-command-meter__fill bbai-command-meter__fill--plan" data-bbai-plan-usage-progress style="width: <?php echo esc_attr($usagePercent); ?>%;" data-bbai-plan-usage-progress-target="<?php echo esc_attr($usagePercent); ?>"></span>
                            </div>
                            <div class="bbai-command-plan__comparison-block">
                                <p class="bbai-command-plan__comparison" data-bbai-plan-growth-line><?php echo esc_html($bbai_growth_usage_line); ?></p>
                                <div class="bbai-command-plan__comparison-visual" aria-hidden="true">
                                    <div class="bbai-command-plan__comparison-header">
                                        <span class="bbai-command-plan__comparison-label"><?php esc_html_e('With Growth', 'beepbeep-ai-alt-text-generator'); ?></span>
                                        <span class="bbai-command-plan__comparison-value" data-bbai-plan-growth-percent-label><?php echo esc_html($bbai_growth_usage_display); ?>%</span>
                                    </div>
                                    <div class="bbai-command-meter bbai-command-meter--growth" aria-hidden="true">
                                        <span class="bbai-command-meter__fill bbai-command-meter__fill--growth" data-bbai-plan-growth-progress style="width: <?php echo esc_attr($bbai_growth_usage_percent); ?>%;" data-bbai-plan-growth-progress-target="<?php echo esc_attr($bbai_growth_usage_percent); ?>"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p class="bbai-command-plan__upgrade-note" data-bbai-plan-upgrade-note<?php echo $isProPlan ? ' hidden' : ''; ?>><?php esc_html_e('Avoid interruptions by upgrading to Growth.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <div class="bbai-command-plan__actions">
                            <?php $bbai_render_action_link($bbai_plan_primary_action, $bbai_plan_primary_class, 'data-bbai-plan-action-primary'); ?>
                            <?php $bbai_render_action_link($bbai_plan_secondary_action, $bbai_plan_secondary_class, 'data-bbai-plan-action-secondary'); ?>
                        </div>
                    </div>
                </article>
            </div>

            <?php $bbai_render_dashboard_alert($bbai_alert_state); ?>

            <aside class="bbai-dashboard-review-prompt" data-bbai-dashboard-review-prompt<?php echo $bbai_show_review_prompt ? '' : ' hidden'; ?>>
                <p class="bbai-dashboard-review-prompt__copy"><?php esc_html_e('Enjoying BeepBeep AI? A quick review helps others discover it.', 'beepbeep-ai-alt-text-generator'); ?></p>
                <a
                    href="<?php echo esc_url($bbai_review_prompt_url); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="bbai-dashboard-review-prompt__link"
                    data-bbai-dashboard-review-link
                ><?php esc_html_e('Leave a review', 'beepbeep-ai-alt-text-generator'); ?></a>
            </aside>

            <div class="bbai-dashboard-feedback" data-bbai-dashboard-feedback hidden aria-live="polite" role="status"></div>
        </div>
    </div>
<?php endif; ?>
