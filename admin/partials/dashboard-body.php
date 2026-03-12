<?php
/** Body content for dashboard (cards, metrics). */
if (!defined('ABSPATH')) {
    exit;
}

use BeepBeepAI\AltTextGenerator\Usage_Tracker;
use BeepBeepAI\AltTextGenerator\Admin\Plan_Helpers;

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/class-plan-helpers.php';

/**
 * Row 1: Library Status card (donut + metrics + next action).
 */
$bbai_render_status_card = static function (array $state): void {
    $optimized = max(0, (int) ($state['optimized_count'] ?? 0));
    $missing = max(0, (int) ($state['missing_alts'] ?? 0));
    $weak = max(0, (int) ($state['needs_review_count'] ?? 0));
    $total_images = max(0, $optimized + $missing + $weak);
    $coverage_percentage = $total_images > 0 ? (int) round(($optimized / $total_images) * 100) : 0;
    $percentage_display = number_format_i18n($coverage_percentage);
    $radius = 48;
    $circumference = 2 * M_PI * $radius;
    $progress_label_text = __('ALT coverage', 'beepbeep-ai-alt-text-generator');
    $bbai_status_ring_segments = [];
    $bbai_status_ring_gap = 0.0;
    $bbai_status_ring_offset = 0.0;
    $bbai_status_ring_non_zero = count(
        array_filter(
            [
                $optimized,
                $weak,
                $missing,
            ],
            static function ($count): bool {
                return $count > 0;
            }
        )
    );
    $bbai_status_ring_linecap = $bbai_status_ring_non_zero > 1 ? 'butt' : 'round';

    foreach (
        [
            'optimized' => [
                'count' => $optimized,
                'stroke' => '#16A34A',
            ],
            'weak' => [
                'count' => $weak,
                'stroke' => '#F97316',
            ],
            'missing' => [
                'count' => $missing,
                'stroke' => '#EF4444',
            ],
        ] as $bbai_segment_key => $bbai_segment
    ) {
        $bbai_segment_count = max(0, (int) $bbai_segment['count']);
        $bbai_segment_length = $total_images > 0 ? ($circumference * ($bbai_segment_count / $total_images)) : 0.0;
        $bbai_segment_gap = $bbai_status_ring_non_zero > 1 ? min($bbai_status_ring_gap, $bbai_segment_length * 0.35) : 0.0;
        $bbai_segment_visible_length = max(0.0, $bbai_segment_length - $bbai_segment_gap);

        $bbai_status_ring_segments[$bbai_segment_key] = [
            'stroke' => $bbai_segment['stroke'],
            'dasharray' => sprintf('%.3F %.3F', $bbai_segment_visible_length, max(0.0, $circumference - $bbai_segment_visible_length)),
            'dashoffset' => sprintf('%.3F', -$bbai_status_ring_offset),
            'hidden' => $bbai_segment_visible_length <= 0.01,
        ];

        $bbai_status_ring_offset += $bbai_segment_length;
    }

    $bbai_library_url = add_query_arg(['page' => 'bbai-library'], admin_url('admin.php'));
    $bbai_missing_library_url = add_query_arg(['page' => 'bbai-library', 'status' => 'missing'], admin_url('admin.php'));
    $optimized_ratio_text = sprintf(
        _n('%1$s / %2$s image optimized', '%1$s / %2$s images optimized', max(1, $total_images), 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($optimized),
        number_format_i18n($total_images)
    );

    $summary_primary_line = $optimized_ratio_text;
    if ($missing > 0) {
        $summary_secondary_line = sprintf(
            _n('%s image missing ALT text', '%s images missing ALT text', $missing, 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($missing)
        );
    } elseif ($weak > 0) {
        $summary_secondary_line = sprintf(
            _n('%s description could be improved', '%s descriptions could be improved', $weak, 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($weak)
        );
    } elseif ($optimized > 0) {
        $summary_secondary_line = __('All descriptions look good', 'beepbeep-ai-alt-text-generator');
    } else {
        $summary_primary_line = __('No images found in your media library', 'beepbeep-ai-alt-text-generator');
        $summary_secondary_line = '';
    }
    $status_metrics = [
        [
            'modifier' => 'optimized',
            'label' => __('Optimized', 'beepbeep-ai-alt-text-generator'),
            'value' => $optimized,
            'icon' => '✔',
        ],
        [
            'modifier' => 'weak',
            'label' => __('Weak ALT', 'beepbeep-ai-alt-text-generator'),
            'value' => $weak,
            'icon' => '⚠',
        ],
        [
            'modifier' => 'missing',
            'label' => __('Missing ALT', 'beepbeep-ai-alt-text-generator'),
            'value' => $missing,
            'icon' => '✖',
        ],
    ];
    $insight_class = 'bbai-status-insight';
    if ($missing > 0) {
        $insight_class .= ' bbai-status-insight--danger';
    } elseif ($weak > 0) {
        $insight_class .= ' bbai-status-insight--warning';
    } else {
        $insight_class .= ' bbai-status-insight--success';
    }
    $last_scan_timestamp = max(0, (int) ($state['last_scan_timestamp'] ?? 0));
    $last_scan_line = '';
    if ($last_scan_timestamp > 0 && $last_scan_timestamp <= (int) current_time('timestamp')) {
        $last_scan_line = sprintf(
            __('Last scan: %s ago', 'beepbeep-ai-alt-text-generator'),
            human_time_diff($last_scan_timestamp, (int) current_time('timestamp'))
        );
    }
    $summary_meta_line = $last_scan_line;

    $insight_title = '';
    $insight_message = '';
    $insight_guidance = '';
    $insight_last_scan = '';
    $insight_primary_label = '';
    $insight_primary_data_action = '';
    $insight_primary_bbai_action = '';
    $insight_secondary_label = '';
    $insight_secondary_href = '';
    if ($missing > 0) {
        $insight_title = __('ALT text needed', 'beepbeep-ai-alt-text-generator');
        $insight_message = sprintf(
            _n('%s image is missing ALT text.', '%s images are missing ALT text.', $missing, 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($missing)
        );
        $insight_guidance = __('Click "Generate ALT Text" to automatically create the description.', 'beepbeep-ai-alt-text-generator');
        $insight_primary_label = __('Generate ALT Text', 'beepbeep-ai-alt-text-generator');
        $insight_primary_data_action = 'generate-missing';
        $insight_primary_bbai_action = 'generate_missing';
        $insight_secondary_label = __('Review in ALT Library', 'beepbeep-ai-alt-text-generator');
        $insight_secondary_href = $bbai_missing_library_url;
    } elseif ($weak > 0) {
        $insight_title = __('Accessibility improvements available', 'beepbeep-ai-alt-text-generator');
        $insight_message = sprintf(
            _n('%s image still has a weak ALT description.', '%s images still have weak ALT descriptions.', $weak, 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($weak)
        );
        $insight_guidance = __('Improve weaker descriptions automatically or review them manually in the ALT Library.', 'beepbeep-ai-alt-text-generator');
        $insight_last_scan = $last_scan_line;
        $insight_primary_label = __('Improve Weak ALT Text', 'beepbeep-ai-alt-text-generator');
        $insight_primary_data_action = 'show-generate-alt-modal';
        $insight_primary_bbai_action = '';
        $insight_secondary_label = __('Review in ALT Library', 'beepbeep-ai-alt-text-generator');
        $insight_secondary_href = $bbai_library_url;
    } else {
        $insight_title = __('Library fully optimized', 'beepbeep-ai-alt-text-generator');
        $insight_message = __('All images currently include accessible ALT text.', 'beepbeep-ai-alt-text-generator');
        $insight_guidance = __('Future uploads can be scanned and optimized automatically.', 'beepbeep-ai-alt-text-generator');
        $insight_last_scan = $last_scan_line;
        $insight_primary_label = __('Scan Media Library', 'beepbeep-ai-alt-text-generator');
        $insight_primary_data_action = 'scan-library';
        $insight_primary_bbai_action = 'scan-opportunity';
        $insight_secondary_label = __('Open ALT Library', 'beepbeep-ai-alt-text-generator');
        $insight_secondary_href = $bbai_library_url;
    }
    ?>
    <article class="bbai-dashboard-card bbai-status-card" data-bbai-dashboard-status-card="1" aria-labelledby="bbai-status-title">
        <header class="bbai-card__header">
            <h3 id="bbai-status-title" class="bbai-card__title"><?php esc_html_e('Library Status', 'beepbeep-ai-alt-text-generator'); ?></h3>
            <p class="bbai-status-card__scan-meta" data-bbai-status-last-scan<?php echo '' !== $summary_meta_line ? '' : ' hidden'; ?>><?php echo esc_html($summary_meta_line); ?></p>
        </header>
        <div class="bbai-card__body bbai-status-card__body">
            <div class="bbai-progress-donut-wrap">
                <div class="bbai-progress-donut bbai-circular-progress-container" data-bbai-donut-animate data-bbai-status-donut>
                    <svg class="bbai-circular-progress-svg" viewBox="0 0 120 120" aria-hidden="true">
                        <circle
                            class="bbai-status-ring-track"
                            cx="60"
                            cy="60"
                            r="48"
                            fill="none"
                            stroke="#E5E7EB"
                            stroke-width="10"
                        />
                        <?php foreach ($bbai_status_ring_segments as $bbai_segment_key => $bbai_segment) : ?>
                        <circle
                            class="bbai-status-ring-segment bbai-status-ring-segment--<?php echo esc_attr($bbai_segment_key); ?>"
                            cx="60"
                            cy="60"
                            r="48"
                            fill="none"
                            stroke="<?php echo esc_attr($bbai_segment['stroke']); ?>"
                            stroke-width="10"
                            stroke-linecap="<?php echo esc_attr($bbai_status_ring_linecap); ?>"
                            stroke-dasharray="<?php echo esc_attr($bbai_segment['dasharray']); ?>"
                            stroke-dashoffset="<?php echo esc_attr($bbai_segment['dashoffset']); ?>"
                            data-circumference="<?php echo esc_attr($circumference); ?>"
                            data-bbai-status-ring-segment="<?php echo esc_attr($bbai_segment_key); ?>"
                            <?php if ($bbai_segment['hidden']) : ?>style="opacity:0"<?php endif; ?>
                        />
                        <?php endforeach; ?>
                    </svg>
                    <div class="bbai-progress-donut-center bbai-donut-center">
                        <div class="bbai-progress-donut-value"><span class="bbai-donut-value bbai-number-animate" data-bbai-status-coverage-value><?php echo esc_html($percentage_display); ?></span><span class="bbai-donut-percent bbai-progress-donut-percent">%</span></div>
                        <div class="bbai-progress-donut-label bbai-donut-label"><?php echo esc_html($progress_label_text); ?></div>
                    </div>
                </div>
            </div>

            <div class="bbai-status-summary">
                <p class="bbai-status-summary__line bbai-status-summary__line--primary" data-bbai-status-summary-ratio><?php echo esc_html($summary_primary_line); ?></p>
                <p class="bbai-status-summary__line bbai-status-summary__line--secondary" data-bbai-status-summary-detail<?php echo '' !== $summary_secondary_line ? '' : ' hidden'; ?>><?php echo esc_html($summary_secondary_line); ?></p>
            </div>

            <div class="bbai-status-metrics">
                <?php foreach ($status_metrics as $metric) : ?>
                <div class="bbai-status-metric bbai-status-metric--<?php echo esc_attr($metric['modifier']); ?>">
                    <span class="bbai-status-metric__value bbai-number-animate" data-bbai-status-metric="<?php echo esc_attr($metric['modifier']); ?>"><?php echo esc_html(number_format_i18n($metric['value'])); ?></span>
                    <span class="bbai-status-metric__label">
                        <span class="bbai-status-metric__label-icon" aria-hidden="true"><?php echo esc_html($metric['icon']); ?></span>
                        <span class="bbai-status-metric__label-text"><?php echo esc_html($metric['label']); ?></span>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($missing > 0 || $optimized > 0 || $weak > 0 || empty($state['has_scan_results'])) : ?>
            <div class="<?php echo esc_attr($insight_class); ?>" data-bbai-status-insight>
                <div class="bbai-status-insight__header">
                    <?php if ($missing > 0) : ?>
                    <span class="bbai-status-insight__icon" aria-hidden="true">⚠</span>
                    <?php endif; ?>
                    <p class="bbai-status-insight__title" data-bbai-status-insight-title><?php echo esc_html($insight_title); ?></p>
                </div>
                <p class="bbai-status-insight__message">
                    <span class="bbai-status-insight__text" data-bbai-status-insight-text><?php echo esc_html($insight_message); ?></span>
                </p>
                <p class="bbai-status-insight__guidance" data-bbai-status-insight-guidance<?php echo '' !== $insight_guidance ? '' : ' hidden'; ?>><?php echo esc_html($insight_guidance); ?></p>
                <p class="bbai-status-insight__meta" data-bbai-status-insight-last-scan<?php echo '' !== $insight_last_scan ? '' : ' hidden'; ?>><?php echo esc_html($insight_last_scan); ?></p>
                <div class="bbai-status-insight__actions" data-bbai-status-insight-actions>
                    <button
                        type="button"
                        class="bbai-status-insight__action bbai-status-insight__action--button bbai-btn-primary"
                        data-bbai-status-insight-primary
                        data-action="<?php echo esc_attr($insight_primary_data_action); ?>"
                        data-bbai-action="<?php echo esc_attr($insight_primary_bbai_action); ?>"
                    ><?php echo esc_html($insight_primary_label); ?></button>
                    <a
                        href="<?php echo esc_url($insight_secondary_href); ?>"
                        class="bbai-status-insight__action bbai-status-insight__action--link"
                        data-bbai-status-insight-review
                    ><?php echo esc_html($insight_secondary_label); ?></a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </article>
    <?php
};

/**
 * Shared usage and upgrade state for row 2 cards.
 */
$bbai_get_plan_usage_card_state = static function (array $state): array {
    $remaining_credits = max(0, (int) ($state['credits_remaining'] ?? 0));
    $is_out_of_credits = !empty($state['is_out_of_credits']);
    $is_low_credits = !$is_out_of_credits && empty($state['is_premium']) && $remaining_credits <= 5;
    $free_usage_percentage = max(0, min(100, (float) ($state['usage_percentage'] ?? 0)));
    $credits_used = max(0, (int) ($state['credits_used'] ?? 0));
    $plan_label = !empty($state['is_premium'])
        ? __('Growth plan', 'beepbeep-ai-alt-text-generator')
        : __('Free plan', 'beepbeep-ai-alt-text-generator');
    $growth_capacity = !empty($state['is_premium'])
        ? max(1000, (int) ($state['credits_total'] ?? 1000))
        : 1000;
    $growth_usage_percentage = max(0, min(100, ($credits_used / max(1, $growth_capacity)) * 100));
    $growth_indicator_percentage = $growth_usage_percentage > 0 ? min(99.5, max(0.5, $growth_usage_percentage)) : 0;
    $minutes_saved = max(0, (int) round($credits_used * 2.5));
    $bbai_format_percentage = static function (float $value): string {
        if (!is_finite($value) || $value <= 0) {
            return '0';
        }
        if ($value >= 100) {
            return '100';
        }
        if ($value < 0.01) {
            return '<0.01';
        }
        if ($value < 0.1) {
            return number_format_i18n($value, 2);
        }
        if ($value < 10) {
            return number_format_i18n($value, 1);
        }

        return number_format_i18n($value, 0);
    };

    $usage_line = sprintf(
        __('%1$s / %2$s AI generations used this month', 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($credits_used),
        number_format_i18n($state['credits_total'] ?? 50)
    );
    $remaining_line = sprintf(
        __('%1$s remaining on the %2$s', 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($remaining_credits),
        $plan_label
    );
    $growth_line = __('1,000 AI ALT texts per month', 'beepbeep-ai-alt-text-generator');
    $growth_usage_line = !empty($state['is_premium'])
        ? sprintf(
            __('Currently using %s%% of Growth', 'beepbeep-ai-alt-text-generator'),
            $bbai_format_percentage($growth_usage_percentage)
        )
        : sprintf(
            __('At your current usage, you would use %s%% of Growth', 'beepbeep-ai-alt-text-generator'),
            $bbai_format_percentage($growth_usage_percentage)
        );
    $card_class = 'bbai-dashboard-card bbai-upgrade-card bbai-upgrade-card--subtle';
    $upgrade_context = '';

    if ($is_out_of_credits) {
        $upgrade_context = __('You\'ve reached your AI generation limit.', 'beepbeep-ai-alt-text-generator');
        $card_class = 'bbai-dashboard-card bbai-upgrade-card bbai-upgrade-card--urgent';
    } elseif ($is_low_credits) {
        $upgrade_context = __('You\'re running low on AI generations.', 'beepbeep-ai-alt-text-generator');
        $card_class = 'bbai-dashboard-card bbai-upgrade-card bbai-upgrade-card--urgent';
    }

    return [
        'card_class' => $card_class,
        'free_usage_percentage' => $free_usage_percentage,
        'growth_indicator_percentage' => $growth_indicator_percentage,
        'growth_line' => $growth_line,
        'growth_usage_line' => $growth_usage_line,
        'growth_usage_percentage' => $growth_usage_percentage,
        'minutes_saved' => $minutes_saved,
        'minutes_saved_display' => sprintf(
            _n('~%s minute', '~%s minutes', $minutes_saved, 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($minutes_saved)
        ),
        'plan_label' => $plan_label,
        'remaining_line' => $remaining_line,
        'upgrade_context' => $upgrade_context,
        'usage_line' => $usage_line,
    ];
};

/**
 * Row 2: Plan usage card.
 */
$bbai_render_plan_usage_card = static function (array $state) use ($bbai_get_plan_usage_card_state): void {
    $card_state = $bbai_get_plan_usage_card_state($state);
    $bbai_workflow_steps = [
        [
            'icon' => '🔎',
            'title' => __('Scan your media library', 'beepbeep-ai-alt-text-generator'),
            'description' => __('Click "Scan Library" to detect images missing ALT text.', 'beepbeep-ai-alt-text-generator'),
        ],
        [
            'icon' => '⚡',
            'title' => __('Generate ALT text automatically', 'beepbeep-ai-alt-text-generator'),
            'description' => __('Click "Generate ALT Text" to create optimized descriptions.', 'beepbeep-ai-alt-text-generator'),
        ],
        [
            'icon' => '✏',
            'title' => __('Review and improve results', 'beepbeep-ai-alt-text-generator'),
            'description' => __('Open the ALT Library to review or edit generated descriptions.', 'beepbeep-ai-alt-text-generator'),
        ],
    ];
    ?>
    <article class="bbai-dashboard-card bbai-plan-usage-card" aria-labelledby="bbai-plan-usage-title">
        <header class="bbai-card__header">
            <h3 id="bbai-plan-usage-title" class="bbai-card__title"><?php esc_html_e('Plan Usage', 'beepbeep-ai-alt-text-generator'); ?></h3>
        </header>
        <div class="bbai-card__body">
            <div class="bbai-plan-usage" data-bbai-plan-usage-card>
                <div class="bbai-plan-usage__tier bbai-plan-usage__tier--free">
                    <p class="bbai-plan-usage__label"><?php echo esc_html($card_state['plan_label']); ?></p>
                    <p class="bbai-plan-usage__line" data-bbai-plan-usage-line><?php echo esc_html($card_state['usage_line']); ?></p>
                    <p class="bbai-plan-usage__remaining" data-bbai-plan-usage-remaining><?php echo esc_html($card_state['remaining_line']); ?></p>
                    <p class="bbai-plan-usage__reset" data-bbai-plan-usage-reset><?php echo esc_html($state['credits_reset_line'] ?? $state['reset_label']); ?></p>
                    <div class="bbai-plan-usage__progress" aria-hidden="true">
                        <span class="bbai-plan-usage__progress-fill" data-bbai-plan-usage-progress data-bbai-plan-usage-progress-target="<?php echo esc_attr(round($card_state['free_usage_percentage'])); ?>"></span>
                    </div>
                </div>
                <div class="bbai-plan-usage__divider" aria-hidden="true"></div>
                <div class="bbai-plan-usage__guide" aria-labelledby="bbai-plan-usage-guide-title">
                    <p id="bbai-plan-usage-guide-title" class="bbai-plan-usage__section-title"><?php esc_html_e('How it works', 'beepbeep-ai-alt-text-generator'); ?></p>
                    <ol class="bbai-plan-usage__steps">
                        <?php foreach ($bbai_workflow_steps as $bbai_workflow_step) : ?>
                        <li class="bbai-plan-usage__step">
                            <span class="bbai-plan-usage__step-icon" aria-hidden="true"><?php echo esc_html($bbai_workflow_step['icon']); ?></span>
                            <span class="bbai-plan-usage__step-copy">
                                <span class="bbai-plan-usage__step-title"><?php echo esc_html($bbai_workflow_step['title']); ?></span>
                                <span class="bbai-plan-usage__step-description"><?php echo esc_html($bbai_workflow_step['description']); ?></span>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
                <?php if (!empty($state['show_upgrade_card'])) : ?>
                <div class="bbai-plan-usage__divider" aria-hidden="true"></div>
                <div class="bbai-plan-usage__upgrade">
                    <p class="bbai-plan-usage__section-title"><?php esc_html_e('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'); ?></p>
                    <p class="bbai-plan-usage__price"><?php esc_html_e('£12.99 / month', 'beepbeep-ai-alt-text-generator'); ?></p>
                    <p class="bbai-plan-usage__growth-copy" data-bbai-upgrade-growth-line><?php echo esc_html($card_state['growth_line']); ?></p>
                    <p class="bbai-plan-usage__comparison" data-bbai-upgrade-growth-usage><?php echo esc_html($card_state['growth_usage_line']); ?></p>
                    <div class="bbai-plan-usage__progress bbai-plan-usage__progress--growth" aria-hidden="true" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr(round($card_state['growth_usage_percentage'])); ?>">
                        <span class="bbai-plan-usage__progress-fill bbai-plan-usage__progress-fill--growth" data-bbai-upgrade-growth-progress data-bbai-upgrade-growth-progress-target="<?php echo esc_attr($card_state['growth_usage_percentage']); ?>"></span>
                        <span class="bbai-plan-usage__progress-indicator" data-bbai-upgrade-growth-indicator style="left: <?php echo esc_attr($card_state['growth_indicator_percentage']); ?>%"<?php echo $card_state['growth_usage_percentage'] > 0 ? '' : ' hidden'; ?>></span>
                    </div>
                    <div class="bbai-plan-usage__actions">
                        <button type="button" class="bbai-btn bbai-btn-primary bbai-plan-usage__cta" data-action="show-upgrade-modal"><?php esc_html_e('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'); ?></button>
                        <a href="#" class="bbai-link-secondary bbai-plan-usage__compare-link" data-action="show-upgrade-modal" onclick="if(typeof alttextaiShowModal==='function'){alttextaiShowModal();}else if(typeof window.alttextaiShowModal==='function'){window.alttextaiShowModal();}return false;"><?php esc_html_e('Compare plans', 'beepbeep-ai-alt-text-generator'); ?></a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </article>
    <?php
};

/**
 * Row 2: Upgrade plan card.
 */
$bbai_render_upgrade_card = static function (array $state) use ($bbai_get_plan_usage_card_state): void {
    if (empty($state['show_upgrade_card'])) {
        return;
    }

    $card_state = $bbai_get_plan_usage_card_state($state);
    ?>
    <article class="<?php echo esc_attr($card_state['card_class']); ?>" aria-labelledby="bbai-upgrade-title">
        <div class="bbai-upgrade-top">
            <header class="bbai-card__header">
                <h3 id="bbai-upgrade-title" class="bbai-card__title"><?php esc_html_e('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'); ?></h3>
                <p class="bbai-upgrade-price"><?php esc_html_e('£12.99 / month', 'beepbeep-ai-alt-text-generator'); ?></p>
            </header>
            <div class="bbai-card__body">
                <div class="bbai-upgrade-card__section">
                    <p class="bbai-meter-caption" data-bbai-upgrade-context<?php echo '' !== $card_state['upgrade_context'] ? '' : ' hidden'; ?>><?php echo esc_html($card_state['upgrade_context']); ?></p>
                    <ul class="bbai-feature-list bbai-upgrade-features">
                        <li><span class="bbai-feature-check" aria-hidden="true">✓</span><span><?php esc_html_e('1,000 AI ALT texts per month', 'beepbeep-ai-alt-text-generator'); ?></span></li>
                        <li><span class="bbai-feature-check" aria-hidden="true">✓</span><span><?php esc_html_e('Bulk media library optimisation', 'beepbeep-ai-alt-text-generator'); ?></span></li>
                        <li><span class="bbai-feature-check" aria-hidden="true">✓</span><span><?php esc_html_e('Priority queue processing', 'beepbeep-ai-alt-text-generator'); ?></span></li>
                        <li><span class="bbai-feature-check" aria-hidden="true">✓</span><span><?php esc_html_e('Multilingual SEO support', 'beepbeep-ai-alt-text-generator'); ?></span></li>
                    </ul>
                    <div class="bbai-upgrade-value" data-bbai-upgrade-value>
                        <p class="bbai-upgrade-value__line">
                            <span><?php esc_html_e('You\'ve already saved', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <strong data-bbai-upgrade-value-minutes><?php echo esc_html($card_state['minutes_saved_display']); ?></strong>
                            <span><?php esc_html_e('writing ALT text.', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </p>
                        <p class="bbai-upgrade-value__support">
                            <span><?php esc_html_e('Upgrade to Growth to optimize your', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <strong><?php esc_html_e('entire media library automatically.', 'beepbeep-ai-alt-text-generator'); ?></strong>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <footer class="bbai-card__footer bbai-upgrade-bottom">
            <button type="button" class="bbai-btn bbai-btn-primary bbai-upgrade-cta" data-action="show-upgrade-modal" data-bbai-upgrade-cta><?php esc_html_e('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'); ?></button>
            <a href="#" class="bbai-link-secondary bbai-upgrade-compare-link bbai-compare-plans" data-action="show-upgrade-modal" onclick="if(typeof alttextaiShowModal==='function'){alttextaiShowModal();}else if(typeof window.alttextaiShowModal==='function'){window.alttextaiShowModal();}return false;"><?php esc_html_e('Compare plans', 'beepbeep-ai-alt-text-generator'); ?></a>
        </footer>
    </article>
    <?php
};

/**
 * Row 2: Image Tools card.
 */
$bbai_render_actions_card = static function (array $state): void {
    $bbai_library_url = add_query_arg(['page' => 'bbai-library'], admin_url('admin.php'));
    ?>
    <section class="bbai-dashboard-card bbai-workflow-card bbai-actions-card" aria-labelledby="bbai-workflow-title">
        <header class="bbai-card__header">
            <h3 id="bbai-workflow-title" class="bbai-card__title"><?php esc_html_e('Manual Tools', 'beepbeep-ai-alt-text-generator'); ?></h3>
            <p class="bbai-card__copy bbai-workflow-card__desc"><?php esc_html_e('Use these tools to scan your library or review ALT text manually.', 'beepbeep-ai-alt-text-generator'); ?></p>
        </header>
        <div class="bbai-workflow-card__body">
            <div class="bbai-workflow-steps">
                <div class="bbai-workflow-step">
                    <span class="bbai-workflow-step__icon dashicons dashicons-search" aria-hidden="true"></span>
                    <div class="bbai-workflow-step__content">
                        <h4 class="bbai-workflow-step__title"><?php esc_html_e('Scan Media Library', 'beepbeep-ai-alt-text-generator'); ?></h4>
                        <p class="bbai-workflow-step__desc"><?php esc_html_e('Find images missing ALT text or weak descriptions.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <button type="button" class="bbai-workflow-step__btn bbai-workflow-step__btn--primary" data-bbai-action="scan-opportunity"><?php esc_html_e('Scan Library', 'beepbeep-ai-alt-text-generator'); ?></button>
                    </div>
                </div>
                <div class="bbai-workflow-step bbai-workflow-step--last">
                    <span class="bbai-workflow-step__icon dashicons dashicons-edit" aria-hidden="true"></span>
                    <div class="bbai-workflow-step__content">
                        <h4 class="bbai-workflow-step__title"><?php esc_html_e('Open ALT Library', 'beepbeep-ai-alt-text-generator'); ?></h4>
                        <p class="bbai-workflow-step__desc" data-bbai-workflow-review-desc><?php esc_html_e('Review and edit ALT descriptions manually.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <a href="<?php echo esc_url($bbai_library_url); ?>" class="bbai-workflow-step__btn bbai-workflow-step__btn--secondary" data-bbai-workflow-review-cta><?php esc_html_e('Open ALT Library', 'beepbeep-ai-alt-text-generator'); ?></a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php
};

/**
 * Row 4: Accessibility impact card.
 */
$bbai_render_accessibility_impact_card = static function (array $metrics): void {
    $accessibility_improved = max(0, min(100, (int) ($metrics['seo_impact'] ?? 0)));
    $images_optimized = max(0, (int) ($metrics['images_optimized'] ?? 0));
    $generated = max(0, (int) ($metrics['lifetime_generated'] ?? 0));
    $share_url = add_query_arg(
        [
            'text' => sprintf(
                __('My WordPress site improved accessibility by %s%% using BeepBeep AI.', 'beepbeep-ai-alt-text-generator'),
                number_format_i18n($accessibility_improved)
            ),
            'url'  => 'https://beepbeep.ai',
        ],
        'https://twitter.com/intent/tweet'
    );
    ?>
    <section class="bbai-dashboard-card bbai-accessibility-impact-card" data-bbai-accessibility-card="1" aria-labelledby="bbai-accessibility-impact-title">
        <header class="bbai-card__header">
            <h3 id="bbai-accessibility-impact-title" class="bbai-card__title"><?php esc_html_e('Accessibility Impact', 'beepbeep-ai-alt-text-generator'); ?></h3>
            <p class="bbai-card__copy"><?php esc_html_e('Show visitors your commitment to accessibility.', 'beepbeep-ai-alt-text-generator'); ?></p>
        </header>
        <div class="bbai-card__body">
            <div class="bbai-accessibility-impact__metrics" aria-label="<?php esc_attr_e('Accessibility impact metrics', 'beepbeep-ai-alt-text-generator'); ?>">
                <div class="bbai-accessibility-impact__metric">
                    <span class="bbai-accessibility-impact__metric-label"><?php esc_html_e('Accessibility improved', 'beepbeep-ai-alt-text-generator'); ?></span>
                    <p class="bbai-accessibility-impact__metric-value"><strong data-bbai-accessibility-coverage><?php echo esc_html(number_format_i18n($accessibility_improved)); ?></strong><span>%</span></p>
                </div>
                <div class="bbai-accessibility-impact__metric">
                    <span class="bbai-accessibility-impact__metric-label"><?php esc_html_e('Images optimized', 'beepbeep-ai-alt-text-generator'); ?></span>
                    <p class="bbai-accessibility-impact__metric-value"><strong data-bbai-accessibility-optimized><?php echo esc_html(number_format_i18n($images_optimized)); ?></strong></p>
                </div>
            </div>
            <p class="bbai-accessibility-impact__generated" data-bbai-accessibility-generated-line>
                <?php
                printf(
                    esc_html__('AI ALT descriptions generated: %s', 'beepbeep-ai-alt-text-generator'),
                    esc_html(number_format_i18n($generated))
                );
                ?>
            </p>
            <div
                id="bbai-accessibility-impact-preview"
                class="bbai-accessibility-impact__preview"
                data-bbai-accessibility-preview
                hidden
            >
                <img
                    class="bbai-accessibility-impact__badge"
                    data-bbai-accessibility-badge-preview
                    alt="<?php echo esc_attr(sprintf(__('Accessibility badge preview showing %1$s%% accessibility improvement and %2$s images optimized', 'beepbeep-ai-alt-text-generator'), number_format_i18n($accessibility_improved), number_format_i18n($images_optimized))); ?>"
                />
            </div>
            <div class="bbai-accessibility-impact__actions">
                <button
                    type="button"
                    class="bbai-workflow-step__btn bbai-workflow-step__btn--secondary bbai-accessibility-impact__action"
                    data-bbai-accessibility-action="preview"
                    aria-controls="bbai-accessibility-impact-preview"
                    aria-expanded="false"
                ><?php esc_html_e('Preview Badge', 'beepbeep-ai-alt-text-generator'); ?></button>
                <button
                    type="button"
                    class="bbai-workflow-step__btn bbai-workflow-step__btn--secondary bbai-accessibility-impact__action"
                    data-bbai-accessibility-action="download"
                ><?php esc_html_e('Download Badge', 'beepbeep-ai-alt-text-generator'); ?></button>
                <button
                    type="button"
                    class="bbai-workflow-step__btn bbai-workflow-step__btn--secondary bbai-accessibility-impact__action"
                    data-bbai-accessibility-action="copy-embed"
                ><?php esc_html_e('Copy HTML Embed', 'beepbeep-ai-alt-text-generator'); ?></button>
                <a
                    href="<?php echo esc_url($share_url); ?>"
                    class="bbai-workflow-step__btn bbai-workflow-step__btn--secondary bbai-accessibility-impact__action"
                    data-bbai-accessibility-action="share"
                    target="_blank"
                    rel="noreferrer noopener"
                ><?php esc_html_e('Share on Twitter/X', 'beepbeep-ai-alt-text-generator'); ?></a>
            </div>
        </div>
    </section>
    <?php
};

/**
 * Compact quick actions row under the banner.
 */
$bbai_render_quick_actions_row = static function (array $state): void {
    $bbai_library_url = add_query_arg(['page' => 'bbai-library'], admin_url('admin.php'));
    ?>
    <section class="bbai-dashboard-quick-actions bbai-dashboard-section" aria-label="<?php esc_attr_e('Quick Actions', 'beepbeep-ai-alt-text-generator'); ?>">
        <div class="bbai-dashboard-quick-actions__row">
            <button
                type="button"
                class="bbai-dashboard-quick-actions__button bbai-dashboard-quick-actions__button--secondary"
                data-bbai-action="scan-opportunity"
            ><?php esc_html_e('Scan Library', 'beepbeep-ai-alt-text-generator'); ?></button>
            <button
                type="button"
                class="bbai-dashboard-quick-actions__button bbai-dashboard-quick-actions__button--primary"
                data-action="show-generate-alt-modal"
            ><?php esc_html_e('Generate ALT Text', 'beepbeep-ai-alt-text-generator'); ?></button>
            <a
                href="<?php echo esc_url($bbai_library_url); ?>"
                class="bbai-dashboard-quick-actions__button bbai-dashboard-quick-actions__button--secondary"
            ><?php esc_html_e('ALT Library', 'beepbeep-ai-alt-text-generator'); ?></a>
        </div>
    </section>
    <?php
};

/**
 * Row 5: Review prompt.
 */
$bbai_render_review_prompt = static function (array $state): void {
    if ((int) ($state['credits_used'] ?? 0) <= 0) {
        return;
    }
    $review_url = 'https://wordpress.org/support/plugin/beepbeep-ai-alt-text-generator/reviews/#new-post';
    ?>
    <section class="bbai-review-prompt-card bbai-review-strip" aria-labelledby="bbai-review-prompt-title">
        <div class="bbai-review-prompt-card__body">
            <div class="bbai-review-prompt-card__summary">
                <div class="bbai-review-prompt-card__content">
                    <p id="bbai-review-prompt-title" class="bbai-review-prompt-card__title"><?php esc_html_e('Loving BeepBeep AI?', 'beepbeep-ai-alt-text-generator'); ?></p>
                    <p class="bbai-review-prompt-card__copy"><?php esc_html_e('Your review helps more WordPress sites improve accessibility.', 'beepbeep-ai-alt-text-generator'); ?></p>
                </div>
            </div>
            <div class="bbai-review-prompt-card__actions">
                <p class="bbai-review-prompt-card__stars" aria-hidden="true">★★★★★</p>
                <a href="<?php echo esc_url($review_url); ?>" class="bbai-review-prompt-card__cta" target="_blank" rel="noreferrer noopener"><?php esc_html_e('Leave a review', 'beepbeep-ai-alt-text-generator'); ?></a>
            </div>
        </div>
    </section>
    <?php
};

/**
 * Row 3: Stats row (4 equal cards).
 */
$bbai_render_stats_row = static function (array $metrics): void {
    $lifetime = max(0, (int) ($metrics['lifetime_generated'] ?? $metrics['images_optimized'] ?? 0));
    $minutes_saved = max(0, (int) round((float) ($metrics['hours_saved'] ?? 0) * 60));
    $time_saved_tooltip = __('Based on an average of ~8 seconds per ALT description.', 'beepbeep-ai-alt-text-generator');
    ?>
    <section class="bbai-dashboard-stats-wrap" aria-labelledby="bbai-performance-overview-title">
        <p id="bbai-performance-overview-title" class="bbai-dashboard-section-kicker bbai-performance-title"><span class="bbai-dashboard-section-kicker__icon dashicons dashicons-chart-bar" aria-hidden="true"></span><?php esc_html_e('Performance Overview', 'beepbeep-ai-alt-text-generator'); ?></p>
        <div class="bbai-dashboard-stats" aria-label="<?php esc_attr_e('Performance metrics', 'beepbeep-ai-alt-text-generator'); ?>">
            <article class="bbai-stat-card bbai-metric-card" data-bbai-performance-card="minutes">
                <div class="bbai-stat-card__icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div>
                <p class="bbai-stat-card__value bbai-metric-value"><span class="bbai-number-animate" data-bbai-performance-minutes><?php echo esc_html(number_format_i18n($minutes_saved)); ?></span> <span><?php esc_html_e('minutes saved', 'beepbeep-ai-alt-text-generator'); ?></span></p>
                <p class="bbai-stat-card__label bbai-metric-label bbai-stat-card__label--with-tooltip">
                    <span><?php esc_html_e('Time saved writing ALT text', 'beepbeep-ai-alt-text-generator'); ?></span>
                    <button
                        type="button"
                        class="bbai-stat-card__tooltip-trigger"
                        aria-label="<?php esc_attr_e('How time saved is calculated', 'beepbeep-ai-alt-text-generator'); ?>"
                        data-bbai-tooltip="<?php echo esc_attr($time_saved_tooltip); ?>"
                        data-bbai-tooltip-position="top"
                    >i</button>
                </p>
            </article>
            <article class="bbai-stat-card bbai-metric-card" data-bbai-performance-card="generated">
                <div class="bbai-stat-card__icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg></div>
                <p class="bbai-stat-card__value bbai-metric-value"><span class="bbai-number-animate" data-bbai-performance-generated><?php echo esc_html(number_format_i18n($lifetime)); ?></span></p>
                <p class="bbai-stat-card__label bbai-metric-label"><?php esc_html_e('AI ALT descriptions generated', 'beepbeep-ai-alt-text-generator'); ?></p>
            </article>
            <article class="bbai-stat-card bbai-metric-card" data-bbai-performance-card="coverage">
                <div class="bbai-stat-card__icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
                <p class="bbai-stat-card__value bbai-metric-value"><span class="bbai-number-animate" data-bbai-performance-coverage><?php echo esc_html((int) $metrics['seo_impact']); ?></span><span class="bbai-percent">%</span></p>
                <p class="bbai-stat-card__label bbai-metric-label"><?php esc_html_e('Accessibility improvement', 'beepbeep-ai-alt-text-generator'); ?></p>
            </article>
            <article class="bbai-stat-card bbai-metric-card" data-bbai-performance-card="optimized">
                <div class="bbai-stat-card__icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg></div>
                <p class="bbai-stat-card__value bbai-metric-value"><span class="bbai-number-animate" data-bbai-performance-optimized><?php echo esc_html(number_format_i18n((int) $metrics['images_optimized'])); ?></span></p>
                <p class="bbai-stat-card__label bbai-metric-label"><?php esc_html_e('Images optimized', 'beepbeep-ai-alt-text-generator'); ?></p>
            </article>
        </div>
    </section>
    <?php
};
?>

<?php
$bbai_is_authenticated = $this->api_client->is_authenticated();
$bbai_has_license = $this->api_client->has_active_license();
$bbai_has_registered_user = $bbai_has_registered_user ?? false;

if ($bbai_is_authenticated || $bbai_has_license || $bbai_has_registered_user) :
    $bbai_plan_data = Plan_Helpers::get_plan_data();
    $bbai_is_agency = !empty($bbai_plan_data['is_agency']);
    $bbai_is_premium = !empty($bbai_plan_data['is_pro']) || $bbai_is_agency;

    $bbai_credits_used = max(0, (int) ($bbai_usage_stats['used'] ?? 0));
    $bbai_credits_total = max(1, (int) ($bbai_usage_stats['limit'] ?? 50));
    $bbai_credits_remaining = isset($bbai_usage_stats['remaining']) ? max(0, (int) $bbai_usage_stats['remaining']) : max(0, $bbai_credits_total - $bbai_credits_used);
    if ($bbai_credits_used > $bbai_credits_total) {
        $bbai_credits_used = $bbai_credits_total;
        $bbai_credits_remaining = 0;
    }

    $bbai_missing_alts = max(0, (int) ($bbai_stats['missing'] ?? 0));
    $bbai_optimized_count = max(0, (int) ($bbai_stats['optimized_count'] ?? ($bbai_stats['with_alt'] ?? 0)));
    $bbai_total_images = max(0, (int) ($bbai_stats['total'] ?? 0));
    $bbai_usage_percentage = $bbai_credits_total > 0 ? min(100, max(0, ($bbai_credits_used / $bbai_credits_total) * 100)) : 0;

    $bbai_reset_raw = (string) ($bbai_usage_stats['reset_date'] ?? '');
    $bbai_reset_timestamp_raw = isset($bbai_usage_stats['reset_timestamp']) ? (int) $bbai_usage_stats['reset_timestamp'] : 0;
    $bbai_reset_ts = $bbai_reset_timestamp_raw > 0 ? $bbai_reset_timestamp_raw : ($bbai_reset_raw !== '' ? strtotime($bbai_reset_raw) : false);
    $bbai_has_reset_timestamp = is_numeric($bbai_reset_ts) && (int) $bbai_reset_ts > 0;

    $bbai_days_to_reset = null;
    if (is_numeric($bbai_reset_ts) && (int) $bbai_reset_ts > 0) {
        $bbai_days_to_reset = Usage_Tracker::calculate_days_until_reset((int) $bbai_reset_ts, (int) current_time('timestamp'));
    } elseif (isset($bbai_usage_stats['days_until_reset']) && is_numeric($bbai_usage_stats['days_until_reset'])) {
        $bbai_days_to_reset = max(0, (int) $bbai_usage_stats['days_until_reset']);
    }

    $bbai_reset_label = ($bbai_reset_raw !== '' || $bbai_has_reset_timestamp)
        ? sprintf(__('Resets %s', 'beepbeep-ai-alt-text-generator'), date_i18n('F j, Y', $bbai_has_reset_timestamp ? (int) $bbai_reset_ts : current_time('timestamp')))
        : __('Resets monthly', 'beepbeep-ai-alt-text-generator');

    $bbai_credits_reset_line = ($bbai_reset_raw !== '' || $bbai_has_reset_timestamp)
        ? sprintf(__('Credits reset %s', 'beepbeep-ai-alt-text-generator'), date_i18n('F j, Y', $bbai_has_reset_timestamp ? (int) $bbai_reset_ts : current_time('timestamp')))
        : __('Credits reset monthly', 'beepbeep-ai-alt-text-generator');

    $bbai_is_out_of_credits = ($bbai_credits_remaining <= 0 && !$bbai_is_premium);
    $bbai_coverage = (isset($this) && method_exists($this, 'get_alt_text_coverage_scan')) ? $this->get_alt_text_coverage_scan(false) : [];
    $bbai_needs_review_count = isset($bbai_coverage['needs_review_count']) ? (int) $bbai_coverage['needs_review_count'] : 0;
    $bbai_last_scan_timestamp = isset($bbai_coverage['scanned_at']) ? max(0, (int) $bbai_coverage['scanned_at']) : 0;
    $bbai_has_available_credits = $bbai_is_premium || $bbai_credits_remaining > 0;
    $bbai_has_scan_results = $bbai_total_images > 0 || $bbai_missing_alts > 0 || $bbai_needs_review_count > 0 || $bbai_optimized_count > 0;
    $bbai_primary_action = 'scan';
    if (!$bbai_has_available_credits) {
        $bbai_primary_action = 'upgrade';
    } elseif ($bbai_missing_alts > 0) {
        $bbai_primary_action = 'generate_missing';
    } elseif ($bbai_needs_review_count > 0) {
        $bbai_primary_action = 'review_weak';
    } elseif ($bbai_has_scan_results) {
        $bbai_primary_action = 'review_library';
    }

    $bbai_state = [
        'credits_used' => $bbai_credits_used,
        'credits_total' => $bbai_credits_total,
        'credits_remaining' => $bbai_credits_remaining,
        'missing_alts' => $bbai_missing_alts,
        'needs_review_count' => $bbai_needs_review_count,
        'optimized_count' => $bbai_optimized_count,
        'total_images' => $bbai_total_images,
        'usage_percentage' => $bbai_usage_percentage,
        'reset_label' => $bbai_reset_label,
        'credits_reset_line' => $bbai_credits_reset_line,
        'is_out_of_credits' => $bbai_is_out_of_credits,
        'is_premium' => $bbai_is_premium,
        'show_upgrade_card' => !$bbai_is_agency,
        'has_available_credits' => $bbai_has_available_credits,
        'has_scan_results' => $bbai_has_scan_results,
        'primary_action' => $bbai_primary_action,
        'last_scan_timestamp' => $bbai_last_scan_timestamp,
    ];

    $bbai_lifetime_generated = max(0, (int) ($bbai_stats['generated'] ?? $bbai_optimized_count));
    $bbai_metrics = [
        'hours_saved' => round(($bbai_credits_used * 2.5) / 60, 1),
        'images_optimized' => $bbai_optimized_count,
        'seo_impact' => $bbai_total_images > 0 ? round(($bbai_optimized_count / $bbai_total_images) * 100) : 0,
        'lifetime_generated' => $bbai_lifetime_generated,
    ];

    $bbai_credits_remaining = isset($bbai_credits_remaining) ? $bbai_credits_remaining : max(0, $bbai_credits_total - $bbai_credits_used);
    $bbai_is_free = !$bbai_is_premium;
    ?>

    <div
        id="bbai-dashboard-main"
        class="bbai-dashboard"
        data-bbai-dashboard-container
        data-bbai-dashboard-root="1"
        data-bbai-missing-count="<?php echo esc_attr($bbai_missing_alts); ?>"
        data-bbai-weak-count="<?php echo esc_attr($bbai_needs_review_count); ?>"
        data-bbai-optimized-count="<?php echo esc_attr($bbai_optimized_count); ?>"
        data-bbai-total-count="<?php echo esc_attr($bbai_total_images); ?>"
        data-bbai-generated-count="<?php echo esc_attr($bbai_lifetime_generated); ?>"
        data-bbai-credits-used="<?php echo esc_attr($bbai_credits_used); ?>"
        data-bbai-credits-total="<?php echo esc_attr($bbai_credits_total); ?>"
        data-bbai-credits-remaining="<?php echo esc_attr($bbai_credits_remaining); ?>"
        data-bbai-credits-reset-line="<?php echo esc_attr($bbai_credits_reset_line); ?>"
        data-bbai-is-premium="<?php echo esc_attr($bbai_is_premium ? '1' : '0'); ?>"
        data-bbai-last-scan-ts="<?php echo esc_attr($bbai_last_scan_timestamp); ?>"
        data-bbai-primary-action="<?php echo esc_attr($bbai_primary_action); ?>"
        data-bbai-library-url="<?php echo esc_url(add_query_arg(['page' => 'bbai-library'], admin_url('admin.php'))); ?>"
        data-bbai-missing-library-url="<?php echo esc_url(add_query_arg(['page' => 'bbai-library', 'status' => 'missing'], admin_url('admin.php'))); ?>"
    >
        <div id="bbai-limit-state-root" class="bbai-limit-state-root" hidden></div>

        <?php
        // Hero/success banner - always shown (same structure for both credit states)
        $bbai_banner_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-success-banner.php';
        if ( file_exists( $bbai_banner_partial ) ) {
            include $bbai_banner_partial;
        }
        ?>

        <?php $bbai_render_quick_actions_row($bbai_state); ?>

        <div class="bbai-dashboard-container">
            <div class="bbai-dashboard-row bbai-dashboard-row--1 bbai-dashboard-section">
                <?php $bbai_render_status_card($bbai_state); ?>
                <?php $bbai_render_plan_usage_card($bbai_state); ?>
            </div>

            <div class="bbai-dashboard-row bbai-dashboard-row--2 bbai-dashboard-section bbai-dashboard-section--lazy-render">
                <?php $bbai_render_actions_card($bbai_state); ?>
                <?php $bbai_render_upgrade_card($bbai_state); ?>
            </div>

            <div class="bbai-dashboard-row bbai-dashboard-row--3 bbai-dashboard-section bbai-dashboard-section--lazy-render">
                <?php $bbai_render_stats_row($bbai_metrics); ?>
            </div>

            <?php if ((int) ($bbai_state['credits_used'] ?? 0) > 0) : ?>
            <div class="bbai-dashboard-row bbai-dashboard-row--4 bbai-dashboard-section bbai-dashboard-section--lazy-render">
                <?php $bbai_render_accessibility_impact_card($bbai_metrics); ?>
            </div>

            <div class="bbai-dashboard-row bbai-dashboard-row--5 bbai-dashboard-section bbai-dashboard-section--last bbai-dashboard-section--lazy-render">
                <?php $bbai_render_review_prompt($bbai_state); ?>
            </div>
            <?php else : ?>
            <div class="bbai-dashboard-row bbai-dashboard-row--4 bbai-dashboard-section bbai-dashboard-section--last bbai-dashboard-section--lazy-render">
                <?php $bbai_render_accessibility_impact_card($bbai_metrics); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
