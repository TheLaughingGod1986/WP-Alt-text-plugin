<?php
/** Body content for dashboard (cards, metrics). */
if (!defined('ABSPATH')) {
    exit;
}

use BeepBeepAI\AltTextGenerator\Usage_Tracker;
use BeepBeepAI\AltTextGenerator\Admin\Plan_Helpers;

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/class-plan-helpers.php';

/**
 * Row 1: Alt Text Status card (donut + metrics + plan usage + upgrade signal).
 */
$bbai_render_status_card = static function (array $state): void {
    $percentage = (float) $state['usage_percentage'];
    $percentage_display = Usage_Tracker::format_percentage_label($percentage);
    $radius = 48;
    $circumference = 2 * M_PI * $radius;
    $stroke_dashoffset = $circumference * (1 - ($percentage / 100));
    $is_out_of_credits = !empty($state['is_out_of_credits']);
    $ring_stroke = 'url(#bbai-ring-gradient-purple)';
    $ring_class = $is_out_of_credits ? 'bbai-usage-ring--limit' : 'bbai-usage-ring--default';
    $progress_label_text = __('ALT coverage', 'beepbeep-ai-alt-text-generator');

    $optimized = max(0, (int) ($state['optimized_count'] ?? 0));
    $missing = max(0, (int) ($state['missing_alts'] ?? 0));
    $weak = max(0, (int) ($state['needs_review_count'] ?? 0));
    $images_remaining = $missing + $weak;

    $upgrade_unlock = max(0, 1000 - (int) $state['credits_used']);
    $bbai_library_url = add_query_arg(['page' => 'bbai-library'], admin_url('admin.php'));
    $bbai_missing_library_url = add_query_arg(['page' => 'bbai-library', 'status' => 'missing'], admin_url('admin.php'));
    $usage_progress_width = max(0, min(100, $percentage));
    $summary_line = $images_remaining > 0
        ? sprintf(
            /* translators: 1: optimized image count, 2: count of images needing improvement */
            __('%1$s images optimized · %2$s need improvement', 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($optimized),
            number_format_i18n($images_remaining)
        )
        : sprintf(
            /* translators: %s: optimized image count */
            __('%s images optimized · All descriptions look good', 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($optimized)
        );
    $status_metrics = [
        [
            'modifier' => 'optimized',
            'label' => __('Optimized', 'beepbeep-ai-alt-text-generator'),
            'value' => $optimized,
        ],
        [
            'modifier' => 'weak',
            'label' => __('Weak ALT', 'beepbeep-ai-alt-text-generator'),
            'value' => $weak,
        ],
        [
            'modifier' => 'missing',
            'label' => __('Missing ALT', 'beepbeep-ai-alt-text-generator'),
            'value' => $missing,
        ],
    ];
    $insight_class = 'bbai-status-insight';
    if ($weak > 0) {
        $insight_class .= ' bbai-status-insight--warning';
    } elseif ($missing > 0) {
        $insight_class .= ' bbai-status-insight--danger';
    } else {
        $insight_class .= ' bbai-status-insight--success';
    }
    $credits_remaining = max(0, (int) ($state['credits_remaining'] ?? 0));
    $insight_title = '';
    $insight_message = '';
    $insight_action_label = '';
    $insight_action_url = '';
    if ($weak > 0) {
        $insight_title = __('Accessibility improvements available', 'beepbeep-ai-alt-text-generator');
        $insight_message = sprintf(__('%s images still have weak ALT descriptions.', 'beepbeep-ai-alt-text-generator'), number_format_i18n($weak));
        $insight_action_label = __('Improve ALT Text Now', 'beepbeep-ai-alt-text-generator');
        $insight_action_url = $bbai_library_url;
    } elseif ($missing > 0) {
        $insight_title = __('ALT text still needed', 'beepbeep-ai-alt-text-generator');
        $insight_message = sprintf(__('%s images are still missing ALT text.', 'beepbeep-ai-alt-text-generator'), number_format_i18n($missing));
        $insight_action_label = __('Review Missing ALT Text', 'beepbeep-ai-alt-text-generator');
        $insight_action_url = $bbai_missing_library_url;
    } else {
        $insight_title = __('Library fully optimized', 'beepbeep-ai-alt-text-generator');
        $insight_message = __('Your media library is fully optimized.', 'beepbeep-ai-alt-text-generator');
    }
    ?>
    <article class="bbai-dashboard-card bbai-status-card" aria-labelledby="bbai-status-title">
        <header class="bbai-card__header">
            <h3 id="bbai-status-title" class="bbai-card__title"><?php esc_html_e('Alt Text Status', 'beepbeep-ai-alt-text-generator'); ?></h3>
        </header>
        <div class="bbai-card__body bbai-status-card__body">
            <div class="bbai-progress-donut-wrap">
                <div class="bbai-progress-donut bbai-circular-progress-container" data-bbai-donut-animate>
                    <svg class="bbai-circular-progress-svg" viewBox="0 0 120 120" aria-hidden="true">
                        <defs>
                            <linearGradient id="bbai-ring-gradient-purple" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:#b15cff"/>
                                <stop offset="100%" style="stop-color:#7c4dff"/>
                            </linearGradient>
                        </defs>
                        <circle cx="60" cy="60" r="48" fill="none" stroke="#ebeef5" stroke-width="12" />
                        <circle
                            class="bbai-circular-progress-bar bbai-usage-ring <?php echo esc_attr($ring_class); ?>"
                            cx="60" cy="60" r="48"
                            fill="none" stroke="<?php echo esc_attr($ring_stroke); ?>"
                            stroke-width="12" stroke-linecap="round"
                            stroke-dasharray="<?php echo esc_attr($circumference); ?>"
                            stroke-dashoffset="<?php echo esc_attr($circumference); ?>"
                            data-circumference="<?php echo esc_attr($circumference); ?>"
                            data-target-offset="<?php echo esc_attr($stroke_dashoffset); ?>"
                        />
                    </svg>
                    <div class="bbai-progress-donut-center bbai-donut-center">
                        <div class="bbai-progress-donut-value"><span class="bbai-donut-value bbai-number-animate"><?php echo esc_html($percentage_display); ?></span><span class="bbai-donut-percent bbai-progress-donut-percent">%</span></div>
                        <div class="bbai-progress-donut-label bbai-donut-label"><?php echo esc_html($progress_label_text); ?></div>
                    </div>
                </div>
            </div>

            <div class="bbai-status-summary">
                <p class="bbai-status-summary__line"><?php echo esc_html($summary_line); ?></p>
            </div>

            <div class="bbai-status-metrics">
                <?php foreach ($status_metrics as $metric) : ?>
                <div class="bbai-status-metric bbai-status-metric--<?php echo esc_attr($metric['modifier']); ?>">
                    <span class="bbai-status-metric__value bbai-number-animate"><?php echo esc_html(number_format_i18n($metric['value'])); ?></span>
                    <span class="bbai-status-metric__label"><?php echo esc_html($metric['label']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($weak > 0 || $missing > 0 || ($optimized > 0 && $missing === 0 && $weak === 0)) : ?>
            <div class="<?php echo esc_attr($insight_class); ?>">
                <div class="bbai-status-insight__header">
                    <?php if ($weak > 0 || $missing > 0) : ?>
                    <span class="bbai-status-insight__icon" aria-hidden="true">⚠</span>
                    <?php endif; ?>
                    <p class="bbai-status-insight__title"><?php echo esc_html($insight_title); ?></p>
                </div>
                <p class="bbai-status-insight__message">
                    <span class="bbai-status-insight__text"><?php echo esc_html($insight_message); ?></span>
                </p>
                <?php if ($insight_action_label !== '' && $insight_action_url !== '') : ?>
                <a href="<?php echo esc_url($insight_action_url); ?>" class="bbai-status-insight__action"><?php echo esc_html($insight_action_label); ?></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="bbai-plan-usage-divider"></div>

            <div class="bbai-plan-usage">
                <p class="bbai-plan-usage__title"><?php esc_html_e('Free plan usage', 'beepbeep-ai-alt-text-generator'); ?></p>
                <div class="bbai-plan-usage__progress" aria-hidden="true">
                    <span class="bbai-plan-usage__progress-fill" style="width:0%" data-bbai-banner-progress data-bbai-banner-progress-target="<?php echo esc_attr($usage_progress_width); ?>"></span>
                </div>
                <p class="bbai-plan-usage__line"><?php printf(esc_html__('%1$s / %2$s AI generations used', 'beepbeep-ai-alt-text-generator'), esc_html(number_format_i18n($state['credits_used'])), esc_html(number_format_i18n($state['credits_total']))); ?></p>
                <p class="bbai-plan-usage__reset"><?php echo esc_html($state['credits_reset_line'] ?? $state['reset_label']); ?></p>
                <?php if ($is_out_of_credits && $upgrade_unlock > 0) : ?>
                <p class="bbai-plan-usage__remaining bbai-plan-usage__remaining--exhausted"><?php printf(esc_html__('You\'ve used all %s free generations.', 'beepbeep-ai-alt-text-generator'), esc_html(number_format_i18n($state['credits_total']))); ?></p>
                <button type="button" class="bbai-plan-usage__upgrade-copy bbai-inline-upgrade-trigger" data-action="show-upgrade-modal"><?php printf(esc_html__('Upgrade to generate %s more ALT texts this month.', 'beepbeep-ai-alt-text-generator'), esc_html(number_format_i18n($upgrade_unlock))); ?></button>
                <?php else : ?>
                <p class="bbai-plan-usage__remaining"><?php printf(esc_html__('%s AI generations remaining', 'beepbeep-ai-alt-text-generator'), esc_html(number_format_i18n($credits_remaining))); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </article>
    <?php
};

/**
 * Row 1: Upgrade Plan card (features, CTA, no comparison table).
 */
$bbai_render_upgrade_card = static function (array $state): void {
    $is_out_of_credits = !empty($state['is_out_of_credits']);
    if (empty($state['show_upgrade_card'])) {
        return;
    }
    $growth_plan_limit = 1000;
    $current_usage = max(0, (int) ($state['credits_used'] ?? 0));
    $upgrade_unlock = max(0, $growth_plan_limit - $current_usage);
    $weak = max(0, (int) ($state['needs_review_count'] ?? 0));
    $missing = max(0, (int) ($state['missing_alts'] ?? 0));
    $bbai_free_pct = min(100, max(0, ((int) ($state['credits_total'] ?? 0) > 0 ? ($current_usage / (int) $state['credits_total']) * 100 : 0)));
    $bbai_growth_marker_pct = min(100, max(0, ($growth_plan_limit > 0 ? ($current_usage / $growth_plan_limit) * 100 : 0)));
    $bbai_growth_marker_visual_pct = min(100, max($bbai_growth_marker_pct, 10));
    if ($bbai_growth_marker_pct >= 1) {
        $bbai_growth_usage_label = number_format_i18n((int) round($bbai_growth_marker_pct));
    } else {
        $bbai_growth_usage_label = Usage_Tracker::format_percentage_label($bbai_growth_marker_pct);
    }

    if ($weak > 0) {
        $upgrade_context = sprintf(
            /* translators: %s: number of images with weak alt text */
            __('%s images still have weak ALT text and could be improved.', 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($weak)
        );
    } elseif ($missing > 0) {
        $upgrade_context = sprintf(
            /* translators: %s: number of images missing alt text */
            __('%s images are still missing ALT text and need attention.', 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($missing)
        );
    } else {
        $upgrade_context = __('Upgrade now to keep new uploads optimized automatically.', 'beepbeep-ai-alt-text-generator');
    }

    $card_class = 'bbai-dashboard-card bbai-upgrade-card';
    if ($is_out_of_credits) {
        $card_class .= ' bbai-upgrade-card--highlight';
    }
    ?>
    <article class="<?php echo esc_attr($card_class); ?>" aria-labelledby="bbai-growth-title">
        <div class="bbai-upgrade-top">
            <header class="bbai-card__header">
                <h3 id="bbai-growth-title" class="bbai-card__title"><?php esc_html_e('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'); ?></h3>
                <p class="bbai-card__copy bbai-upgrade-subtitle"><?php esc_html_e('Automate alt text generation and scale image optimisation across your media library.', 'beepbeep-ai-alt-text-generator'); ?></p>
                <span class="bbai-upgrade-card__badge bbai-plan-badge"><?php esc_html_e('Most popular plan', 'beepbeep-ai-alt-text-generator'); ?></span>
            </header>
            <div class="bbai-card__body">
                <ul class="bbai-feature-list bbai-upgrade-features">
                    <li><span class="bbai-feature-check" aria-hidden="true">✓</span><?php esc_html_e('1,000 AI alt texts per month', 'beepbeep-ai-alt-text-generator'); ?></li>
                    <li><span class="bbai-feature-check" aria-hidden="true">✓</span><?php esc_html_e('Bulk media library optimisation', 'beepbeep-ai-alt-text-generator'); ?></li>
                    <li><span class="bbai-feature-check" aria-hidden="true">✓</span><?php esc_html_e('Priority queue processing', 'beepbeep-ai-alt-text-generator'); ?></li>
                    <li><span class="bbai-feature-check" aria-hidden="true">✓</span><?php esc_html_e('Multilingual SEO support', 'beepbeep-ai-alt-text-generator'); ?></li>
                </ul>
                <div class="bbai-upgrade-context"><?php echo esc_html($upgrade_context); ?></div>

                <div class="bbai-upgrade-meters">
                    <div class="bbai-meter bbai-meter-free">
                        <div class="bbai-meter-header">
                            <span><?php esc_html_e('Free plan usage', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <span class="bbai-meter-value"><?php printf(esc_html__('%1$s / %2$s images', 'beepbeep-ai-alt-text-generator'), esc_html(number_format_i18n($current_usage)), esc_html(number_format_i18n((int) $state['credits_total']))); ?></span>
                        </div>
                        <div class="bbai-meter-bar">
                            <div class="bbai-meter-fill" style="width:0%" data-bbai-banner-progress data-bbai-banner-progress-target="<?php echo esc_attr($bbai_free_pct); ?>"></div>
                        </div>
                        <div class="bbai-meter-caption">
                            <?php esc_html_e('Resets tomorrow', 'beepbeep-ai-alt-text-generator'); ?>
                        </div>
                    </div>

                    <div class="bbai-meter bbai-meter-growth">
                        <div class="bbai-meter-header">
                            <span><?php esc_html_e('Growth plan limit', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <span class="bbai-meter-value"><?php esc_html_e('1,000 images / month', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                        <div class="bbai-meter-bar bbai-meter-bar-growth" aria-hidden="true">
                            <div class="bbai-meter-capacity-fill"></div>
                            <div class="bbai-meter-current-marker" style="--bbai-marker-left: 0%;" data-bbai-marker-progress data-bbai-marker-progress-target="<?php echo esc_attr($bbai_growth_marker_visual_pct); ?>"></div>
                        </div>
                        <div class="bbai-meter-caption">
                            <?php
                            printf(
                                /* translators: %s: percentage of the Growth plan used at current usage */
                                esc_html__("You'd only use ~%s%% of this plan", 'beepbeep-ai-alt-text-generator'),
                                esc_html($bbai_growth_usage_label)
                            );
                            ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <footer class="bbai-card__footer bbai-upgrade-bottom">
            <button type="button" class="bbai-btn bbai-btn-primary bbai-upgrade-cta" data-action="show-upgrade-modal"><?php esc_html_e('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'); ?></button>
            <div class="bbai-upgrade-trust"><?php esc_html_e('Cancel anytime • No lock-in', 'beepbeep-ai-alt-text-generator'); ?></div>
            <button type="button" class="bbai-upgrade-value bbai-inline-upgrade-trigger" data-action="show-upgrade-modal">
                <?php
                printf(
                    /* translators: %s: number of additional images unlocked this month */
                    esc_html__('Unlock %s more images this month', 'beepbeep-ai-alt-text-generator'),
                    esc_html(number_format_i18n($upgrade_unlock))
                );
                ?>
            </button>
            <a href="#" class="bbai-link-secondary bbai-upgrade-compare-link bbai-compare-plans" data-action="show-upgrade-modal" onclick="if(typeof alttextaiShowModal==='function'){alttextaiShowModal();}else if(typeof window.alttextaiShowModal==='function'){window.alttextaiShowModal();}return false;"><?php esc_html_e('Compare plans', 'beepbeep-ai-alt-text-generator'); ?></a>
        </footer>
    </article>
    <?php
};

/**
 * Row 2: Image Optimization Workflow card.
 * Three-step workflow: Scan, Generate, Review.
 */
$bbai_render_actions_card = static function (array $state): void {
    $is_out_of_credits = !empty($state['is_out_of_credits']);
    $generate_locked = $is_out_of_credits;
    $bbai_library_url = add_query_arg(['page' => 'bbai-library'], admin_url('admin.php'));
    $bbai_lock_svg = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';
    ?>
    <section class="bbai-dashboard-card bbai-workflow-card bbai-actions-card" aria-labelledby="bbai-workflow-title">
        <header class="bbai-card__header">
            <h3 id="bbai-workflow-title" class="bbai-card__title"><?php esc_html_e('Image Optimization Workflow', 'beepbeep-ai-alt-text-generator'); ?></h3>
            <p class="bbai-card__copy bbai-workflow-card__desc"><?php esc_html_e('Follow these steps to optimize your image ALT text and improve accessibility and SEO.', 'beepbeep-ai-alt-text-generator'); ?></p>
        </header>
        <div class="bbai-card__body">
            <div class="bbai-workflow-steps">
                <div class="bbai-workflow-step">
                    <span class="bbai-workflow-step__icon dashicons dashicons-search" aria-hidden="true"></span>
                    <div class="bbai-workflow-step__content">
                        <h4 class="bbai-workflow-step__title"><?php esc_html_e('Scan Media Library', 'beepbeep-ai-alt-text-generator'); ?></h4>
                        <p class="bbai-workflow-step__desc"><?php esc_html_e('Find images missing ALT text or with weak descriptions.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <button type="button" class="bbai-workflow-step__btn bbai-workflow-step__btn--primary" data-bbai-action="scan-opportunity"><?php esc_html_e('Scan Library', 'beepbeep-ai-alt-text-generator'); ?></button>
                    </div>
                </div>
                <div class="bbai-workflow-step">
                    <span class="bbai-workflow-step__icon dashicons dashicons-admin-tools" aria-hidden="true"></span>
                    <div class="bbai-workflow-step__content">
                        <h4 class="bbai-workflow-step__title"><?php esc_html_e('Generate Missing ALT Text', 'beepbeep-ai-alt-text-generator'); ?></h4>
                        <p class="bbai-workflow-step__desc"><?php esc_html_e('Automatically generate descriptive ALT text using AI.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <?php if ($generate_locked) : ?>
                        <button type="button" class="bbai-workflow-step__btn bbai-workflow-step__btn--locked" data-action="show-upgrade-modal" aria-label="<?php esc_attr_e('Upgrade to unlock this feature', 'beepbeep-ai-alt-text-generator'); ?>"><span class="bbai-workflow-step__lock-icon" aria-hidden="true"><?php echo $bbai_lock_svg; ?></span><?php esc_html_e('Upgrade to unlock', 'beepbeep-ai-alt-text-generator'); ?></button>
                        <?php else : ?>
                        <button type="button" class="bbai-workflow-step__btn bbai-workflow-step__btn--primary" data-action="generate-missing" data-bbai-action="generate_missing"><?php esc_html_e('Generate Missing', 'beepbeep-ai-alt-text-generator'); ?></button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="bbai-workflow-step bbai-workflow-step--last">
                    <span class="bbai-workflow-step__icon dashicons dashicons-edit" aria-hidden="true"></span>
                    <div class="bbai-workflow-step__content">
                        <h4 class="bbai-workflow-step__title"><?php esc_html_e('Review Optimized Images', 'beepbeep-ai-alt-text-generator'); ?></h4>
                        <p class="bbai-workflow-step__desc"><?php esc_html_e('Edit or approve generated ALT text before publishing.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <a href="<?php echo esc_url($bbai_library_url); ?>" class="bbai-workflow-step__btn bbai-workflow-step__btn--secondary"><?php esc_html_e('Open ALT Library', 'beepbeep-ai-alt-text-generator'); ?></a>
                    </div>
                </div>
            </div>
            <?php
            $bbai_guide_url = add_query_arg(['page' => 'bbai', 'tab' => 'guide'], admin_url('admin.php'));
            ?>
            <p class="bbai-workflow-card__help-cta"><a href="<?php echo esc_url($bbai_guide_url); ?>"><?php esc_html_e('Need help getting started? View the quick setup guide.', 'beepbeep-ai-alt-text-generator'); ?></a></p>
        </div>
    </section>
    <?php
};

/**
 * Row 4: Review prompt.
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
                    <p id="bbai-review-prompt-title" class="bbai-review-prompt-card__title"><?php esc_html_e('Enjoying BeepBeep AI?', 'beepbeep-ai-alt-text-generator'); ?></p>
                    <p class="bbai-review-prompt-card__copy"><?php esc_html_e('Your review helps more WordPress sites improve accessibility.', 'beepbeep-ai-alt-text-generator'); ?></p>
                </div>
                <div class="bbai-review-prompt-card__meta">
                    <p class="bbai-review-prompt-card__stars" aria-hidden="true">★★★★★</p>
                </div>
            </div>
            <a href="<?php echo esc_url($review_url); ?>" class="bbai-review-prompt-card__cta" target="_blank" rel="noreferrer noopener"><?php esc_html_e('Leave a review', 'beepbeep-ai-alt-text-generator'); ?></a>
        </div>
    </section>
    <?php
};

/**
 * Row 3: Stats row (4 equal cards).
 */
$bbai_render_stats_row = static function (array $metrics): void {
    $lifetime = max(0, (int) ($metrics['lifetime_generated'] ?? $metrics['images_optimized'] ?? 0));
    ?>
    <section class="bbai-dashboard-stats-wrap" aria-labelledby="bbai-performance-overview-title">
        <p id="bbai-performance-overview-title" class="bbai-dashboard-section-kicker bbai-performance-title"><?php esc_html_e('Performance Overview', 'beepbeep-ai-alt-text-generator'); ?></p>
        <div class="bbai-dashboard-stats" aria-label="<?php esc_attr_e('Performance metrics', 'beepbeep-ai-alt-text-generator'); ?>">
            <article class="bbai-stat-card bbai-metric-card">
                <div class="bbai-stat-card__icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div>
                <p class="bbai-stat-card__value bbai-metric-value"><span class="bbai-number-animate"><?php echo esc_html(number_format((float) $metrics['hours_saved'], 1)); ?></span> <span><?php esc_html_e('hrs', 'beepbeep-ai-alt-text-generator'); ?></span></p>
                <p class="bbai-stat-card__label bbai-metric-label"><?php esc_html_e('Time Saved', 'beepbeep-ai-alt-text-generator'); ?></p>
            </article>
            <article class="bbai-stat-card bbai-metric-card">
                <div class="bbai-stat-card__icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg></div>
                <p class="bbai-stat-card__value bbai-metric-value"><span class="bbai-number-animate"><?php echo esc_html(number_format_i18n((int) $metrics['images_optimized'])); ?></span></p>
                <p class="bbai-stat-card__label bbai-metric-label"><?php esc_html_e('Images Optimized', 'beepbeep-ai-alt-text-generator'); ?></p>
            </article>
            <article class="bbai-stat-card bbai-metric-card">
                <div class="bbai-stat-card__icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
                <p class="bbai-stat-card__value bbai-metric-value"><span class="bbai-number-animate"><?php echo esc_html((int) $metrics['seo_impact']); ?></span><span class="bbai-percent">%</span></p>
                <p class="bbai-stat-card__label bbai-metric-label"><?php esc_html_e('Accessibility Improvement', 'beepbeep-ai-alt-text-generator'); ?></p>
            </article>
            <article class="bbai-stat-card bbai-metric-card">
                <div class="bbai-stat-card__icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg></div>
                <p class="bbai-stat-card__value bbai-metric-value"><span class="bbai-number-animate"><?php echo esc_html(number_format_i18n($lifetime)); ?></span></p>
                <p class="bbai-stat-card__label bbai-metric-label"><?php esc_html_e('Total ALT Text Generated', 'beepbeep-ai-alt-text-generator'); ?></p>
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
    $bbai_optimized_count = max(0, (int) ($bbai_stats['with_alt'] ?? 0));
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
        'show_upgrade_card' => !$bbai_is_agency,
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

    <div id="bbai-dashboard-main" class="bbai-dashboard" data-bbai-dashboard-container>
        <div id="bbai-limit-state-root" class="bbai-limit-state-root" hidden></div>

        <?php
        // Hero/success banner - always shown (same structure for both credit states)
        $bbai_banner_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-success-banner.php';
        if ( file_exists( $bbai_banner_partial ) ) {
            include $bbai_banner_partial;
        }
        ?>

        <header class="bbai-dashboard-header bbai-dashboard-section">
            <h1 class="bbai-dashboard-header__title"><?php esc_html_e('Dashboard', 'beepbeep-ai-alt-text-generator'); ?></h1>
            <p class="bbai-dashboard-header__subtitle"><?php esc_html_e('Automated, accessible alt text generation for your WordPress media library.', 'beepbeep-ai-alt-text-generator'); ?></p>
        </header>

        <div class="bbai-dashboard-container">
            <div class="bbai-dashboard-row bbai-dashboard-row--1 bbai-dashboard-section">
                <?php $bbai_render_status_card($bbai_state); ?>
                <?php $bbai_render_upgrade_card($bbai_state); ?>
            </div>

            <div class="bbai-dashboard-row bbai-dashboard-row--2 bbai-dashboard-section bbai-dashboard-section--lazy-render">
                <?php $bbai_render_actions_card($bbai_state); ?>
                <?php
                $bbai_opportunity_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/opportunity-scanner.php';
                if (file_exists($bbai_opportunity_partial)) {
                    include $bbai_opportunity_partial;
                }
                ?>
            </div>

            <div class="bbai-dashboard-row bbai-dashboard-row--3 bbai-dashboard-section bbai-dashboard-section--last bbai-dashboard-section--lazy-render">
                <section class="bbai-dashboard-footer-section" aria-label="<?php esc_attr_e('Performance and feedback', 'beepbeep-ai-alt-text-generator'); ?>">
                    <?php $bbai_render_stats_row($bbai_metrics); ?>
                    <?php if ((int) ($bbai_state['credits_used'] ?? 0) > 0) : ?>
                    <?php $bbai_render_review_prompt($bbai_state); ?>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </div>
<?php endif; ?>
