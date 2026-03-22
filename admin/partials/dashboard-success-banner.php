<?php
/**
 * Simplified dashboard hero banner.
 *
 * @package BeepBeep_AI_Alt_Text_Generator
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_state = $bbai_dashboard_state ?? [];
if (empty($bbai_state) || !is_array($bbai_state)) {
    return;
}

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

$bbai_get_hero_icon = static function (string $tone): string {
    if ('setup' === $tone) {
        return '<svg viewBox="0 0 24 24" fill="none" focusable="false"><circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.8"/><path d="M20 20L16.2 16.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
    }

    if ('attention' === $tone) {
        return '<svg viewBox="0 0 24 24" fill="none" focusable="false"><path d="M12 3L21 19H3L12 3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 9V13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="12" cy="17" r="1" fill="currentColor"/></svg>';
    }

    if ('paused' === $tone) {
        return '<svg viewBox="0 0 24 24" fill="none" focusable="false"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M10 9V15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M14 9V15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
    }

    return '<svg viewBox="0 0 24 24" fill="none" focusable="false"><path d="M22 11.08V12A10 10 0 1 1 12 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M22 4L12 14.01L9 11.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
};

$bbai_create_action = static function (string $label = '', array $overrides = []): array {
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

$bbai_missing_count = max(0, (int) ($bbai_state['missingCount'] ?? 0));
$bbai_weak_count = max(0, (int) ($bbai_state['weakCount'] ?? 0));
$bbai_total_images = max(0, (int) ($bbai_state['totalImages'] ?? 0));
$bbai_credits_used = max(0, (int) ($bbai_state['creditsUsed'] ?? 0));
$bbai_credits_limit = max(1, (int) ($bbai_state['creditsLimit'] ?? 50));
$bbai_credits_remaining = max(0, (int) ($bbai_state['creditsRemaining'] ?? 0));
$bbai_usage_percent = min(100, max(0, (int) ($bbai_state['usagePercent'] ?? 0)));
$bbai_is_pro_plan = !empty($bbai_state['isProPlan']);
$bbai_is_healthy = !empty($bbai_state['isHealthy']);
$bbai_is_low_credits = !empty($bbai_state['isLowCredits']);
$bbai_is_out_of_credits = !empty($bbai_state['isOutOfCredits']);
$bbai_is_first_run = !empty($bbai_state['isFirstRun']);
$bbai_days_until_reset = max(0, (int) ($bbai_state['daysUntilReset'] ?? 0));
$bbai_plan_label = (string) ($bbai_state['planLabel'] ?? ($bbai_is_pro_plan ? __('Growth plan', 'beepbeep-ai-alt-text-generator') : __('Free plan', 'beepbeep-ai-alt-text-generator')));
$bbai_remaining_line = (string) ($bbai_state['remainingLine'] ?? '');
$bbai_reset_timing = (string) ($bbai_state['resetTiming'] ?? '');
$bbai_library_url = (string) ($bbai_state['libraryUrl'] ?? admin_url('admin.php?page=bbai-library'));
$bbai_missing_library_url = (string) ($bbai_state['missingLibraryUrl'] ?? $bbai_library_url);
$bbai_needs_review_library_url = (string) ($bbai_state['needsReviewLibraryUrl'] ?? $bbai_library_url);
$bbai_usage_url = (string) ($bbai_state['usageUrl'] ?? admin_url('admin.php?page=bbai-credit-usage'));
$bbai_settings_url = (string) ($bbai_state['settingsUrl'] ?? admin_url('admin.php?page=bbai-settings'));
$bbai_guide_url = (string) ($bbai_state['guideUrl'] ?? admin_url('admin.php?page=bbai-guide'));

$bbai_hero_state = 'healthy-free';
$bbai_hero_tone = 'healthy';
$bbai_hero_headline = '';
$bbai_hero_subtext = '';
$bbai_hero_next_step = '';
$bbai_hero_note = '';
$bbai_primary_action = $bbai_create_action();
$bbai_secondary_action = $bbai_create_action();
$bbai_tertiary_action = $bbai_create_action();

if ($bbai_is_first_run) {
    $bbai_hero_state = 'first-run';
    $bbai_hero_tone = 'setup';
    $bbai_hero_headline = __('No images found yet.', 'beepbeep-ai-alt-text-generator');
    $bbai_hero_subtext = __('Upload images to start improving your SEO.', 'beepbeep-ai-alt-text-generator');
    $bbai_hero_next_step = __('Scan your site once images are uploaded.', 'beepbeep-ai-alt-text-generator');
    $bbai_primary_action = $bbai_create_action(
        __('Scan Media Library', 'beepbeep-ai-alt-text-generator'),
        [
            'bbai_action' => 'scan-opportunity',
            'aria_label' => __('Scan Media Library', 'beepbeep-ai-alt-text-generator'),
        ]
    );
    $bbai_secondary_action = $bbai_create_action(
        __('Learn how it works', 'beepbeep-ai-alt-text-generator'),
        [
            'href' => $bbai_guide_url,
            'aria_label' => __('Learn how it works', 'beepbeep-ai-alt-text-generator'),
        ]
    );
} elseif ($bbai_is_out_of_credits) {
    $bbai_hero_state = 'out-of-credits';
    $bbai_hero_tone = 'paused';
    $bbai_hero_headline = __('You’ve used all credits this cycle', 'beepbeep-ai-alt-text-generator');
    $bbai_hero_subtext = __('ALT generation is paused until your allowance resets or you upgrade.', 'beepbeep-ai-alt-text-generator');
    $bbai_hero_next_step = __('Upgrade to continue optimizing new images.', 'beepbeep-ai-alt-text-generator');
    $bbai_primary_action = $bbai_create_action(
        __('Upgrade', 'beepbeep-ai-alt-text-generator'),
        [
            'action' => 'show-upgrade-modal',
            'aria_label' => __('Upgrade to continue optimizing new images', 'beepbeep-ai-alt-text-generator'),
        ]
    );
    $bbai_secondary_action = $bbai_create_action(
        __('Review usage', 'beepbeep-ai-alt-text-generator'),
        [
            'href' => $bbai_usage_url,
            'aria_label' => __('Review usage', 'beepbeep-ai-alt-text-generator'),
        ]
    );
    $bbai_tertiary_action = $bbai_create_action(
        __('View details', 'beepbeep-ai-alt-text-generator'),
        [
            'href' => $bbai_library_url,
            'aria_label' => __('View details', 'beepbeep-ai-alt-text-generator'),
        ]
    );
} elseif ($bbai_is_low_credits) {
    $bbai_hero_state = 'low-credits';
    $bbai_hero_tone = 'attention';
    $bbai_hero_headline = __('You’re running low on credits', 'beepbeep-ai-alt-text-generator');
    $bbai_hero_subtext = sprintf(
        _n('You have %s optimization left this cycle.', 'You have %s optimizations left this cycle.', $bbai_credits_remaining, 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($bbai_credits_remaining)
    );

    if ($bbai_is_pro_plan) {
        $bbai_hero_next_step = __('Review your usage and plan ahead.', 'beepbeep-ai-alt-text-generator');
        $bbai_primary_action = $bbai_create_action(
            __('Review usage', 'beepbeep-ai-alt-text-generator'),
            [
                'href' => $bbai_usage_url,
                'aria_label' => __('Review usage', 'beepbeep-ai-alt-text-generator'),
            ]
        );
        $bbai_secondary_action = $bbai_create_action(
            __('View details', 'beepbeep-ai-alt-text-generator'),
            [
                'href' => $bbai_library_url,
                'aria_label' => __('View details', 'beepbeep-ai-alt-text-generator'),
            ]
        );
    } else {
        $bbai_hero_next_step = __('Upgrade to keep optimizing without interruption.', 'beepbeep-ai-alt-text-generator');
        $bbai_primary_action = $bbai_create_action(
            __('Upgrade', 'beepbeep-ai-alt-text-generator'),
            [
                'action' => 'show-upgrade-modal',
                'aria_label' => __('Upgrade to keep optimizing without interruption', 'beepbeep-ai-alt-text-generator'),
            ]
        );
        $bbai_secondary_action = $bbai_create_action(
            __('Review usage', 'beepbeep-ai-alt-text-generator'),
            [
                'href' => $bbai_usage_url,
                'aria_label' => __('Review usage', 'beepbeep-ai-alt-text-generator'),
            ]
        );
    }
} elseif (!$bbai_is_healthy) {
    $bbai_hero_state = 'incomplete';
    $bbai_hero_tone = 'attention';
    $bbai_hero_headline = $bbai_missing_count > 0
        ? (
            1 === $bbai_missing_count
                ? __('1 image is costing you traffic', 'beepbeep-ai-alt-text-generator')
                : sprintf(
                    /* translators: %s: number of images missing ALT text */
                    __('%s images are costing you traffic', 'beepbeep-ai-alt-text-generator'),
                    number_format_i18n($bbai_missing_count)
                )
        )
        : __('Your ALT text needs a final review', 'beepbeep-ai-alt-text-generator');
    $bbai_hero_subtext = $bbai_missing_count > 0
        ? (
            1 === $bbai_missing_count
                ? __('Fix it now and recover lost traffic. One click to reach 100% optimisation.', 'beepbeep-ai-alt-text-generator')
                : __('Fix them now and recover lost traffic.', 'beepbeep-ai-alt-text-generator')
        )
        : __('Strengthen weak ALT text to protect your rankings.', 'beepbeep-ai-alt-text-generator');
    $bbai_hero_next_step = $bbai_missing_count > 0
        ? (
            1 === $bbai_missing_count
                ? __('Fix this to reach 100% image SEO coverage.', 'beepbeep-ai-alt-text-generator')
                : __('Fix them to reach 100% image SEO coverage.', 'beepbeep-ai-alt-text-generator')
        )
        : __('Finish your review to reach full coverage.', 'beepbeep-ai-alt-text-generator');
    $bbai_primary_action = $bbai_missing_count > 0
        ? $bbai_create_action(
            sprintf(
                /* translators: %s: number of images */
                __('Fix missing ALT text (%s)', 'beepbeep-ai-alt-text-generator'),
                number_format_i18n($bbai_missing_count)
            ),
            [
                'action' => 'generate-missing',
                'bbai_action' => 'generate_missing',
                'aria_label' => sprintf(
                    /* translators: %s: number of images */
                    __('Fix missing ALT text (%s)', 'beepbeep-ai-alt-text-generator'),
                    number_format_i18n($bbai_missing_count)
                ),
            ]
        )
        : $bbai_create_action(
            __('Review ALT text', 'beepbeep-ai-alt-text-generator'),
            [
                'action' => 'show-generate-alt-modal',
                'aria_label' => __('Review ALT text', 'beepbeep-ai-alt-text-generator'),
            ]
        );
    $bbai_secondary_action = $bbai_create_action(
        __('See what\'s broken', 'beepbeep-ai-alt-text-generator'),
        [
            'href' => $bbai_missing_count > 0 ? $bbai_missing_library_url : $bbai_needs_review_library_url,
            'aria_label' => __('See what\'s broken', 'beepbeep-ai-alt-text-generator'),
        ]
    );
} elseif ($bbai_is_pro_plan) {
    $bbai_hero_state = 'healthy-pro';
    $bbai_hero_tone = 'healthy';
    $bbai_hero_headline = __('You’ve fully optimised your image SEO 🎉', 'beepbeep-ai-alt-text-generator');
    $bbai_hero_subtext = __('Your site is now fully discoverable in Google Images.', 'beepbeep-ai-alt-text-generator');
    $bbai_hero_next_step = '';
    $bbai_hero_note = __('⚡ Full scan completed in under 10 seconds', 'beepbeep-ai-alt-text-generator');
    $bbai_primary_action = $bbai_create_action(
        __('Check for new SEO issues', 'beepbeep-ai-alt-text-generator'),
        [
            'bbai_action' => 'scan-opportunity',
            'aria_label' => __('Check for new SEO issues', 'beepbeep-ai-alt-text-generator'),
        ]
    );
    $bbai_secondary_action = $bbai_create_action(
        __('View Library', 'beepbeep-ai-alt-text-generator'),
        [
            'href' => $bbai_library_url,
            'aria_label' => __('View library', 'beepbeep-ai-alt-text-generator'),
        ]
    );
    $bbai_tertiary_action = [];
} else {
    $bbai_hero_state = 'healthy-free';
    $bbai_hero_tone = 'healthy';
    $bbai_hero_headline = __('You’ve fully optimised your image SEO 🎉', 'beepbeep-ai-alt-text-generator');
    $bbai_hero_subtext = __('Your site is now fully discoverable in Google Images.', 'beepbeep-ai-alt-text-generator');
    $bbai_hero_next_step = '';
    $bbai_hero_note = __('⚡ Full scan completed in under 10 seconds', 'beepbeep-ai-alt-text-generator');
    $bbai_primary_action = $bbai_create_action(
        __('Check for new SEO issues', 'beepbeep-ai-alt-text-generator'),
        [
            'bbai_action' => 'scan-opportunity',
            'aria_label' => __('Check for new SEO issues', 'beepbeep-ai-alt-text-generator'),
        ]
    );
    $bbai_secondary_action = $bbai_create_action(
        __('View Library', 'beepbeep-ai-alt-text-generator'),
        [
            'href' => $bbai_library_url,
            'aria_label' => __('View library', 'beepbeep-ai-alt-text-generator'),
        ]
    );
    $bbai_tertiary_action = [];
}

$bbai_primary_label = (string) ($bbai_primary_action['label'] ?? '');
$bbai_secondary_label = (string) ($bbai_secondary_action['label'] ?? '');
$bbai_tertiary_label = (string) ($bbai_tertiary_action['label'] ?? '');
$bbai_primary_attrs = $bbai_build_action_attrs($bbai_primary_action);
$bbai_secondary_attrs = '' !== $bbai_secondary_label ? $bbai_build_action_attrs($bbai_secondary_action) : 'href="#"';
$bbai_tertiary_attrs = '' !== $bbai_tertiary_label ? $bbai_build_action_attrs($bbai_tertiary_action) : 'href="#"';
?>
<div class="bbai-hero-full">
    <div class="bbai-hero-inner">
        <section
            class="bbai-dashboard-hero bbai-dashboard-hero--command bbai-dashboard-section"
            data-bbai-dashboard-hero="1"
            data-bbai-shared-banner="1"
            data-state="<?php echo esc_attr($bbai_hero_state); ?>"
            data-tone="<?php echo esc_attr($bbai_hero_tone); ?>"
            data-bbai-banner-used="<?php echo esc_attr($bbai_credits_used); ?>"
            data-bbai-banner-limit="<?php echo esc_attr($bbai_credits_limit); ?>"
            data-bbai-banner-remaining="<?php echo esc_attr($bbai_credits_remaining); ?>"
            data-bbai-banner-library-url="<?php echo esc_url($bbai_library_url); ?>"
            data-bbai-banner-missing-count="<?php echo esc_attr($bbai_missing_count); ?>"
            data-bbai-banner-weak-count="<?php echo esc_attr($bbai_weak_count); ?>"
            data-bbai-banner-days-left="<?php echo esc_attr($bbai_days_until_reset); ?>"
            data-bbai-banner-settings-url="<?php echo esc_url($bbai_settings_url); ?>"
            aria-label="<?php esc_attr_e('Dashboard summary', 'beepbeep-ai-alt-text-generator'); ?>"
        >
            <div class="bbai-dashboard-hero-inner">
                <div class="bbai-dashboard-hero__layout">
                    <div class="bbai-dashboard-hero-copy">
                        <div class="bbai-dashboard-hero__heading-row">
                            <div class="bbai-dashboard-hero__icon" data-bbai-hero-icon aria-hidden="true">
                                <?php echo $bbai_get_hero_icon($bbai_hero_tone); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG helper output. ?>
                            </div>
                            <div class="bbai-dashboard-hero__heading-copy">
                                <h1 class="bbai-dashboard-hero__headline" data-bbai-hero-headline><?php echo esc_html($bbai_hero_headline); ?></h1>
                            </div>
                        </div>

                        <p class="bbai-dashboard-hero__subtext" data-bbai-hero-subtext><?php echo esc_html($bbai_hero_subtext); ?></p>
                        <p class="bbai-dashboard-hero__next-step" data-bbai-hero-next-step><?php echo esc_html($bbai_hero_next_step); ?></p>

                        <div class="bbai-dashboard-hero__usage-block">
                            <p class="bbai-dashboard-hero__usage" data-bbai-banner-usage-line data-bbai-reset-copy="<?php echo esc_attr($bbai_reset_timing); ?>">
                                <span class="bbai-dashboard-hero__usage-pill"><?php echo esc_html($bbai_plan_label); ?></span>
                                <span class="bbai-dashboard-hero__usage-primary"><?php echo esc_html($bbai_remaining_line); ?></span>
                                <span class="bbai-dashboard-hero__usage-secondary"><?php echo esc_html($bbai_reset_timing); ?></span>
                            </p>
                            <div class="bbai-dashboard-hero-progress">
                                <div class="bbai-dashboard-hero-progress-track" role="progressbar" aria-valuenow="<?php echo esc_attr($bbai_usage_percent); ?>" aria-valuemin="0" aria-valuemax="100">
                                    <span class="bbai-dashboard-hero-progress-fill" data-bbai-banner-progress data-bbai-banner-progress-target="<?php echo esc_attr($bbai_usage_percent); ?>" style="width: <?php echo esc_attr($bbai_usage_percent); ?>%;"></span>
                                </div>
                            </div>
                        </div>

                        <p class="bbai-dashboard-hero__note" data-bbai-hero-note<?php echo '' !== $bbai_hero_note ? '' : ' hidden'; ?>><?php echo esc_html($bbai_hero_note); ?></p>
                        <div class="bbai-dashboard-hero__loop" data-bbai-hero-loop hidden>
                            <p class="bbai-dashboard-hero__loop-label"><?php esc_html_e('Stay ahead in search', 'beepbeep-ai-alt-text-generator'); ?></p>
                            <p class="bbai-dashboard-hero__loop-support" data-bbai-hero-loop-support hidden></p>
                            <div class="bbai-dashboard-hero__loop-actions">
                                <a href="#" class="bbai-dashboard-hero__loop-link" data-bbai-hero-loop-scan><?php esc_html_e('Check for new SEO issues', 'beepbeep-ai-alt-text-generator'); ?></a>
                                <a href="<?php echo esc_url($bbai_settings_url); ?>" class="bbai-dashboard-hero__loop-link" data-bbai-hero-loop-settings><?php esc_html_e('Auto-optimise future uploads', 'beepbeep-ai-alt-text-generator'); ?></a>
                                <a href="#" class="bbai-dashboard-hero__loop-link" data-action="show-upgrade-modal" data-bbai-hero-loop-upgrade<?php echo $bbai_is_pro_plan ? ' hidden' : ''; ?>><?php esc_html_e('Upgrade for unlimited optimisation', 'beepbeep-ai-alt-text-generator'); ?></a>
                            </div>
                            <p class="bbai-dashboard-hero__loop-tension" data-bbai-hero-loop-tension hidden></p>
                        </div>
                    </div>

                    <div class="bbai-dashboard-hero-actions" aria-label="<?php esc_attr_e('Recommended actions', 'beepbeep-ai-alt-text-generator'); ?>">
                        <div class="bbai-dashboard-hero-actions__item bbai-dashboard-hero-actions__item--primary" data-bbai-hero-primary-item<?php echo '' !== $bbai_primary_label ? '' : ' hidden'; ?>>
                            <a <?php echo $bbai_primary_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes are assembled from escaped values above. ?> class="bbai-dashboard-hero__cta bbai-dashboard-hero__cta--primary" data-bbai-hero-generator-cta><?php echo esc_html($bbai_primary_label); ?></a>
                        </div>

                        <div class="bbai-dashboard-hero__secondary-actions">
                            <div class="bbai-dashboard-hero-actions__item" data-bbai-hero-secondary-item<?php echo '' !== $bbai_secondary_label ? '' : ' hidden'; ?>>
                                <a <?php echo $bbai_secondary_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes are assembled from escaped values above. ?> class="bbai-dashboard-hero__link bbai-dashboard-hero__link--secondary" data-bbai-hero-library-cta><?php echo esc_html($bbai_secondary_label); ?></a>
                            </div>
                            <div class="bbai-dashboard-hero-actions__item" data-bbai-hero-tertiary-item<?php echo '' !== $bbai_tertiary_label ? '' : ' hidden'; ?>>
                                <a <?php echo $bbai_tertiary_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes are assembled from escaped values above. ?> class="bbai-dashboard-hero__link bbai-dashboard-hero__link--tertiary" data-bbai-hero-secondary-link><?php echo esc_html($bbai_tertiary_label); ?></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
