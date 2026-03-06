<?php
/** Body content for dashboard (cards, metrics). */
if (!defined('ABSPATH')) {
    exit;
}

use BeepBeepAI\AltTextGenerator\Usage_Tracker;
use BeepBeepAI\AltTextGenerator\Admin\Plan_Helpers;

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/class-plan-helpers.php';

$bbai_onboarding_modal_path = plugin_dir_path(BBAI_PLUGIN_FILE) . 'admin/partials/onboarding-modal.php';
if (file_exists($bbai_onboarding_modal_path)) {
    include $bbai_onboarding_modal_path;
}

$renderButtonAttrs = static function (array $attrs): string {
    $parts = [];
    foreach ($attrs as $name => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $parts[] = sprintf('%s="%s"', esc_attr((string) $name), esc_attr((string) $value));
    }
    return implode(' ', $parts);
};

$renderMainContentGrid = static function (array $state) use ($renderButtonAttrs): void {
    $percentage = (float) $state['usage_percentage'];
    $percentage_display = Usage_Tracker::format_percentage_label($percentage);
    $radius = 48;
    $circumference = 2 * M_PI * $radius;
    $stroke_dashoffset = $circumference * (1 - ($percentage / 100));
    $is_out_of_credits = !empty($state['is_out_of_credits']);
    $has_missing_alts = ((int) $state['missing_alts']) > 0;

    if ($percentage >= 86) {
        $ring_stroke = '#EF4444';
        $ring_class = 'bbai-usage-ring--danger';
    } elseif ($percentage >= 61) {
        $ring_stroke = '#F59E0B';
        $ring_class = 'bbai-usage-ring--warning';
    } else {
        $ring_stroke = '#10B981';
        $ring_class = 'bbai-usage-ring--healthy';
    }

    $upgrade_growth_capacity = 1000;
    $upgrade_unlock_remaining = max(0, $upgrade_growth_capacity - (int) $state['credits_used']);
    $upgrade_button_text = __('Upgrade to Growth', 'beepbeep-ai-alt-text-generator');
    $progress_label_text = $is_out_of_credits
        ? __('Free limit reached', 'beepbeep-ai-alt-text-generator')
        : __('Images used', 'beepbeep-ai-alt-text-generator');
    $show_library_action = !$has_missing_alts;
    $library_tab_url = add_query_arg(
        [
            'page' => 'bbai',
            'tab' => 'library',
        ],
        admin_url('admin.php')
    );

    $generate_button_classes = ['bbai-action-btn', 'bbai-action-btn-primary'];
    $generate_button_attrs = [];
    $generate_locked = false;

    if ($is_out_of_credits) {
        $generate_locked = true;
        $generate_button_classes[] = 'is-locked';
        $generate_button_classes[] = 'is-locked--hard';
        $generate_button_classes[] = 'bbai-is-locked';
        $generate_button_classes[] = 'disabled';
        $generate_button_attrs = [
            'aria-disabled' => 'true',
            'disabled' => 'disabled',
            'title' => __('You\'ve reached your monthly limit. Upgrade to generate more ALT text.', 'beepbeep-ai-alt-text-generator'),
        ];
    } elseif (!$has_missing_alts) {
        $generate_button_classes[] = 'disabled';
        $generate_button_attrs = [
            'aria-disabled' => 'true',
            'disabled' => 'disabled',
            'title' => __('No missing images found.', 'beepbeep-ai-alt-text-generator'),
        ];
    } else {
        $generate_button_attrs = [
            'data-action' => 'generate-missing',
            'data-bbai-action' => 'generate_missing',
        ];
    }

    $reopt_button_classes = ['bbai-action-btn', 'bbai-action-btn-secondary'];
    $reopt_button_attrs = [
        'data-action' => 'regenerate-all',
        'data-bbai-action' => 'reoptimize_all',
    ];
    $reopt_locked = false;

    if ($is_out_of_credits) {
        $reopt_locked = true;
        $reopt_button_classes[] = 'is-locked';
        $reopt_button_classes[] = 'is-locked--soft';
        $reopt_button_classes[] = 'bbai-is-locked';
        $reopt_button_classes[] = 'disabled';
        $reopt_button_attrs = [
            'aria-disabled' => 'true',
            'disabled' => 'disabled',
            'title' => __('You\'ve reached your monthly limit. Upgrade to generate more ALT text.', 'beepbeep-ai-alt-text-generator'),
        ];
    }
    ?>
    <section class="bbai-main-content-grid" aria-label="<?php esc_attr_e('Work area and upgrade', 'beepbeep-ai-alt-text-generator'); ?>">
        <div class="bbai-main-content-grid__work">
            <article class="bbai-usage-card-redesign bbai-status-card">
                <h3 class="bbai-card-title"><?php esc_html_e('Alt Text status', 'beepbeep-ai-alt-text-generator'); ?></h3>

                <div class="bbai-circular-progress-wrapper">
                    <div class="bbai-circular-progress-container">
                        <svg class="bbai-circular-progress-svg" viewBox="0 0 120 120" aria-hidden="true">
                            <circle cx="60" cy="60" r="48" fill="none" stroke="#e5e7eb" stroke-width="8" />
                            <circle
                                class="bbai-circular-progress-bar bbai-usage-ring <?php echo esc_attr($ring_class); ?>"
                                cx="60"
                                cy="60"
                                r="48"
                                fill="none"
                                stroke="<?php echo esc_attr($ring_stroke); ?>"
                                stroke-width="8"
                                stroke-linecap="round"
                                stroke-dasharray="<?php echo esc_attr($circumference); ?>"
                                stroke-dashoffset="<?php echo esc_attr($stroke_dashoffset); ?>"
                            />
                        </svg>
                        <div class="bbai-circular-progress-text">
                            <div class="bbai-circular-progress-percent"><?php echo esc_html($percentage_display); ?>%</div>
                            <div class="bbai-circular-progress-label"><?php echo esc_html($progress_label_text); ?></div>
                        </div>
                    </div>
                </div>

                <p class="bbai-usage-count">
                    <span class="bbai-usage-count-main">
                        <?php
                        printf(
                            /* translators: 1: used credits, 2: total credits */
                            esc_html__('%1$s / %2$s images used', 'beepbeep-ai-alt-text-generator'),
                            esc_html(number_format_i18n($state['credits_used'])),
                            esc_html(number_format_i18n($state['credits_total']))
                        );
                        ?>
                    </span>
                </p>

                <?php if ($is_out_of_credits) : ?>
                    <p class="bbai-usage-social-proof"><?php esc_html_e('Sites like yours typically process 300+ images per month', 'beepbeep-ai-alt-text-generator'); ?></p>
                <?php endif; ?>

                <p class="bbai-reset-date"><?php echo esc_html($state['reset_label']); ?></p>
            </article>

            <article class="bbai-usage-card-redesign bbai-actions-card">
                <h3 class="bbai-card-title"><?php esc_html_e('Actions', 'beepbeep-ai-alt-text-generator'); ?></h3>
                <?php if ($is_out_of_credits) : ?>
                    <p class="bbai-actions-card-helper"><?php esc_html_e('Upgrade required. You can still review existing alt text in ALT Library.', 'beepbeep-ai-alt-text-generator'); ?></p>
                <?php endif; ?>
                <div class="bbai-action-buttons-grid">
                    <div class="bbai-action-item">
                        <?php if ($show_library_action) : ?>
                            <a
                                class="bbai-action-btn bbai-action-btn-tertiary"
                                href="<?php echo esc_url($library_tab_url); ?>"
                            >
                                <?php esc_html_e('View ALT Library', 'beepbeep-ai-alt-text-generator'); ?>
                            </a>
                        <?php else : ?>
                            <button
                                type="button"
                                class="<?php echo esc_attr(implode(' ', $generate_button_classes)); ?>"
                                <?php echo $renderButtonAttrs($generate_button_attrs); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            >
                                <span><?php esc_html_e('Generate Missing Alt Text', 'beepbeep-ai-alt-text-generator'); ?></span>
                                <?php if ($generate_locked) : ?>
                                    <span class="bbai-btn-icon" aria-hidden="true">&#x1F512;</span>
                                <?php endif; ?>
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="bbai-action-item">
                        <button
                            type="button"
                            class="<?php echo esc_attr(implode(' ', $reopt_button_classes)); ?>"
                            <?php echo $renderButtonAttrs($reopt_button_attrs); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        >
                            <span><?php esc_html_e('Re-optimise All', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <?php if ($reopt_locked) : ?>
                                <span class="bbai-btn-icon" aria-hidden="true">&#x1F512;</span>
                            <?php endif; ?>
                        </button>
                    </div>
                </div>
            </article>
        </div>

        <?php if (!empty($state['show_upgrade_card'])) : ?>
        <article class="bbai-upgrade-growth-card">
            <h3 class="bbai-card-title"><?php esc_html_e('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'); ?></h3>
            <p class="bbai-card-subtitle"><?php esc_html_e('Automate alt text generation and scale image optimisation each month.', 'beepbeep-ai-alt-text-generator'); ?></p>

            <p class="bbai-upgrade-usage-context">
                <?php
                printf(
                    /* translators: 1: used credits, 2: total credits */
                    esc_html__('You’ve used %1$s / %2$s free images this month', 'beepbeep-ai-alt-text-generator'),
                    esc_html(number_format_i18n($state['credits_used'])),
                    esc_html(number_format_i18n($state['credits_total']))
                );
                ?>
            </p>
            <p class="bbai-upgrade-unlock-context">
                <?php
                printf(
                    /* translators: %s: unlockable images */
                    esc_html__('Unlock %s more images this month', 'beepbeep-ai-alt-text-generator'),
                    esc_html(number_format_i18n($upgrade_unlock_remaining))
                );
                ?>
            </p>

            <ul class="bbai-benefits-list">
                <li class="bbai-benefit-item"><span class="bbai-benefit-icon" aria-hidden="true">✓</span><span class="bbai-benefit-text"><?php esc_html_e('1,000 AI alt texts per month', 'beepbeep-ai-alt-text-generator'); ?></span></li>
                <li class="bbai-benefit-item"><span class="bbai-benefit-icon" aria-hidden="true">✓</span><span class="bbai-benefit-text"><?php esc_html_e('Bulk processing for media library', 'beepbeep-ai-alt-text-generator'); ?></span></li>
                <li class="bbai-benefit-item"><span class="bbai-benefit-icon" aria-hidden="true">✓</span><span class="bbai-benefit-text"><?php esc_html_e('Priority queue for faster results', 'beepbeep-ai-alt-text-generator'); ?></span></li>
                <li class="bbai-benefit-item"><span class="bbai-benefit-icon" aria-hidden="true">✓</span><span class="bbai-benefit-text"><?php esc_html_e('Multilingual SEO support', 'beepbeep-ai-alt-text-generator'); ?></span></li>
            </ul>

            <div class="bbai-upgrade-compare-row">
                <div class="bbai-upgrade-compare-item">
                    <span><?php esc_html_e('Current', 'beepbeep-ai-alt-text-generator'); ?></span>
                    <strong><?php echo esc_html(number_format_i18n($state['credits_used']) . ' / ' . number_format_i18n($state['credits_total'])); ?></strong>
                </div>
                <div class="bbai-upgrade-compare-item">
                    <span><?php esc_html_e('With Growth', 'beepbeep-ai-alt-text-generator'); ?></span>
                    <strong><?php echo esc_html(number_format_i18n($upgrade_growth_capacity) . ' / month'); ?></strong>
                </div>
                <div class="bbai-upgrade-compare-item">
                    <span><?php esc_html_e('Reset', 'beepbeep-ai-alt-text-generator'); ?></span>
                    <strong><?php echo esc_html($state['days_to_reset_label']); ?></strong>
                </div>
            </div>

            <div class="bbai-cta-section">
                <button type="button" class="bbai-cta-primary" data-action="show-upgrade-modal">
                    <?php echo esc_html($upgrade_button_text); ?>
                </button>
                <p class="bbai-cta-subtext"><?php esc_html_e('£12.99/month • Cancel anytime.', 'beepbeep-ai-alt-text-generator'); ?></p>
                <a href="#" class="bbai-compare-link" data-action="show-upgrade-modal" onclick="if(typeof alttextaiShowModal==='function'){alttextaiShowModal();}else if(typeof window.alttextaiShowModal==='function'){window.alttextaiShowModal();}return false;">
                    <?php esc_html_e('Compare plans', 'beepbeep-ai-alt-text-generator'); ?>
                </a>
            </div>
        </article>
        <?php endif; ?>
    </section>
    <?php
};

$renderStatsRow = static function (array $metrics): void {
    ?>
    <section class="bbai-dashboard-stats-row" aria-label="<?php esc_attr_e('Performance metrics', 'beepbeep-ai-alt-text-generator'); ?>">
        <article class="bbai-dashboard-stat-card">
            <p class="bbai-dashboard-stat-card__value"><?php echo esc_html(number_format((float) $metrics['hours_saved'], 1)); ?> <span>hrs</span></p>
            <p class="bbai-dashboard-stat-card__title"><?php esc_html_e('Time saved', 'beepbeep-ai-alt-text-generator'); ?></p>
        </article>
        <article class="bbai-dashboard-stat-card">
            <p class="bbai-dashboard-stat-card__value"><?php echo esc_html(number_format_i18n((int) $metrics['images_optimized'])); ?></p>
            <p class="bbai-dashboard-stat-card__title"><?php esc_html_e('Images optimized', 'beepbeep-ai-alt-text-generator'); ?></p>
        </article>
        <article class="bbai-dashboard-stat-card">
            <p class="bbai-dashboard-stat-card__value"><?php echo esc_html((int) $metrics['seo_impact']); ?>%</p>
            <p class="bbai-dashboard-stat-card__title"><?php esc_html_e('SEO impact', 'beepbeep-ai-alt-text-generator'); ?></p>
        </article>
    </section>
    <?php
};
?>

<div class="bbai-tab-content active" id="tab-dashboard">
    <div class="bbai-premium-dashboard bbai-dashboard-shell">
        <div class="bbai-dashboard-header-section">
            <div>
                <h1 class="bbai-heading-1"><?php esc_html_e('Dashboard', 'beepbeep-ai-alt-text-generator'); ?></h1>
                <p class="bbai-subtitle"><?php esc_html_e('Automated, accessible alt text generation for your WordPress media library.', 'beepbeep-ai-alt-text-generator'); ?></p>
            </div>
        </div>

        <?php
        $bbai_is_authenticated = $this->api_client->is_authenticated();
        $bbai_has_license = $this->api_client->has_active_license();
        $bbai_has_registered_user = $bbai_has_registered_user ?? false;

        if ($bbai_is_authenticated || $bbai_has_license || $bbai_has_registered_user) :
            $bbai_plan_data = Plan_Helpers::get_plan_data();
            $bbai_is_agency = !empty($bbai_plan_data['is_agency']);
            $bbai_is_premium = !empty($bbai_plan_data['is_pro']) || $bbai_is_agency;

            $credits_used = max(0, (int) ($bbai_usage_stats['used'] ?? 0));
            $credits_total = max(1, (int) ($bbai_usage_stats['limit'] ?? 50));
            $credits_remaining = isset($bbai_usage_stats['remaining'])
                ? max(0, (int) $bbai_usage_stats['remaining'])
                : max(0, $credits_total - $credits_used);
            if ($credits_used > $credits_total) {
                $credits_used = $credits_total;
                $credits_remaining = 0;
            }

            $missing_alts = max(0, (int) ($bbai_stats['missing'] ?? 0));
            $optimized_count = max(0, (int) ($bbai_stats['with_alt'] ?? 0));
            $total_images = max(0, (int) ($bbai_stats['total'] ?? 0));
            $usage_percentage = $credits_total > 0
                ? min(100, max(0, ($credits_used / $credits_total) * 100))
                : 0;

            $reset_raw = (string) ($bbai_usage_stats['reset_date'] ?? '');
            $reset_timestamp_raw = isset($bbai_usage_stats['reset_timestamp']) ? (int) $bbai_usage_stats['reset_timestamp'] : 0;
            $reset_ts = $reset_timestamp_raw > 0
                ? $reset_timestamp_raw
                : ($reset_raw !== '' ? strtotime($reset_raw) : false);
            $has_reset_timestamp = is_numeric($reset_ts) && (int) $reset_ts > 0;

            $days_to_reset = null;
            if (is_numeric($reset_ts) && (int) $reset_ts > 0) {
                $days_to_reset = Usage_Tracker::calculate_days_until_reset((int) $reset_ts, (int) current_time('timestamp'));
            } elseif (isset($bbai_usage_stats['days_until_reset']) && is_numeric($bbai_usage_stats['days_until_reset'])) {
                $days_to_reset = max(0, (int) $bbai_usage_stats['days_until_reset']);
            }

            $reset_label = ($reset_raw !== '' || $has_reset_timestamp)
                ? sprintf(
                    /* translators: %s: reset date */
                    __('Resets %s', 'beepbeep-ai-alt-text-generator'),
                    date_i18n('F j, Y', $has_reset_timestamp ? (int) $reset_ts : current_time('timestamp'))
                )
                : __('Resets monthly', 'beepbeep-ai-alt-text-generator');

            if ($days_to_reset === 0) {
                $days_to_reset_label = __('Today', 'beepbeep-ai-alt-text-generator');
                $reset_copy = __('Resets today', 'beepbeep-ai-alt-text-generator');
            } elseif ($days_to_reset !== null) {
                $days_to_reset_label = sprintf(
                    /* translators: %s: days until reset */
                    _n('%s day', '%s days', $days_to_reset, 'beepbeep-ai-alt-text-generator'),
                    number_format_i18n($days_to_reset)
                );
                $reset_copy = sprintf(
                    /* translators: %s: days until reset */
                    _n('Resets in %s day', 'Resets in %s days', $days_to_reset, 'beepbeep-ai-alt-text-generator'),
                    number_format_i18n($days_to_reset)
                );
            } else {
                $days_to_reset_label = __('Monthly', 'beepbeep-ai-alt-text-generator');
                $reset_copy = $reset_label;
            }

            $is_out_of_credits = ($credits_remaining <= 0 && !$bbai_is_premium);

            $bbai_state = [
                'credits_used' => $credits_used,
                'credits_total' => $credits_total,
                'credits_remaining' => $credits_remaining,
                'missing_alts' => $missing_alts,
                'optimized_count' => $optimized_count,
                'total_images' => $total_images,
                'usage_percentage' => $usage_percentage,
                'reset_label' => $reset_label,
                'reset_copy' => $reset_copy,
                'days_to_reset' => $days_to_reset,
                'days_to_reset_label' => $days_to_reset_label,
                'is_out_of_credits' => $is_out_of_credits,
                'show_upgrade_card' => !$bbai_is_agency,
            ];

            $bbai_metrics = [
                'hours_saved' => round(($credits_used * 2.5) / 60, 1),
                'images_optimized' => $optimized_count,
                'seo_impact' => $total_images > 0 ? round(($optimized_count / $total_images) * 100) : 0,
            ];
            ?>

            <div id="bbai-dashboard-main" data-bbai-dashboard-container>
                <div id="bbai-limit-state-root" class="bbai-limit-state-root" hidden></div>

                <?php $renderMainContentGrid($bbai_state); ?>
                <?php $renderStatsRow($bbai_metrics); ?>
            </div>
        <?php endif; ?>
    </div>
</div>
