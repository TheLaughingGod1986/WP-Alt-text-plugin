<?php
/** Body content for dashboard (cards, metrics). */
if (!defined('ABSPATH')) { exit; }
use BeepBeepAI\AltTextGenerator\Usage_Tracker;
use BeepBeepAI\AltTextGenerator\Admin\Plan_Helpers;

// Load Plan_Helpers class
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/class-plan-helpers.php';

// Include onboarding modal
$bbai_onboarding_modal_path = plugin_dir_path( BBAI_PLUGIN_FILE ) . 'admin/partials/onboarding-modal.php';
if (file_exists($bbai_onboarding_modal_path)) {
    include $bbai_onboarding_modal_path;
}
?>
<!-- Tab Content: Dashboard -->
                    <div class="bbai-tab-content active" id="tab-dashboard">
                    <!-- Premium Dashboard Container -->
                    <div class="bbai-premium-dashboard">
                        <!-- Subtle Header Section -->
                        <div class="bbai-dashboard-header-section">
                            <div>
                                <h1 class="bbai-heading-1"><?php esc_html_e('Dashboard', 'beepbeep-ai-alt-text-generator'); ?></h1>
                                <p class="bbai-subtitle"><?php esc_html_e('Automated, accessible alt text generation for your WordPress media library.', 'beepbeep-ai-alt-text-generator'); ?></p>
                            </div>
                        </div>

                        <?php
                        $bbai_is_authenticated = $this->api_client->is_authenticated();
                        $bbai_has_license = $this->api_client->has_active_license();
                        // Treat stored registration/license as access for dashboard content
                        $bbai_has_registered_user = $bbai_has_registered_user ?? false;
                        if ($bbai_is_authenticated || $bbai_has_license || $bbai_has_registered_user) :
                            // Get plan data from centralized helper
                            $bbai_plan_data = Plan_Helpers::get_plan_data();
                            $bbai_plan_slug = $bbai_plan_data['plan_slug'];
                            $bbai_is_free = $bbai_plan_data['is_free'];
                            $bbai_is_growth = $bbai_plan_data['is_growth'];
                            $bbai_is_agency = $bbai_plan_data['is_agency'];
                            $bbai_is_pro = $bbai_plan_data['is_pro'];
                            $bbai_plan_badge_text = Plan_Helpers::get_plan_badge_text();
                            $bbai_plan_badge_variant = Plan_Helpers::get_plan_badge_variant();
                        ?>
                        
                        <!-- Multi-User Token Bar Container -->
                        <div id="bbai-multiuser-token-bar-root" class="bbai-multiuser-token-bar-container bbai-mb-6"></div>
                        
                        <!-- Premium Stats Grid -->
                        <?php
                        // Check if user has quota remaining (for free users) or is on pro/agency plan
                        $bbai_plan = $bbai_usage_stats['plan'] ?? 'free';
                        $bbai_has_quota = ($bbai_usage_stats['remaining'] ?? 0) > 0;
                        $bbai_is_premium = in_array($bbai_plan, ['pro', 'agency'], true);
                        $bbai_can_generate = $bbai_has_quota || $bbai_is_premium;
                        ?>
                        <div class="bbai-dashboard-overview">
                            <!-- Usage Card with Circular Progress -->
                            <div class="bbai-usage-card-redesign">
                                <!-- Badge -->
                                <div class="flex">
                                    <?php
                                    $bbai_badge_text = $bbai_plan_badge_text;
                                    $bbai_badge_variant = $bbai_plan_badge_variant;
                                    $bbai_badge_class = '';
                                    $bbai_badge_partial = plugin_dir_path( BBAI_PLUGIN_FILE ) . 'admin/partials/badge.php';
                                    if (file_exists($bbai_badge_partial)) {
                                        include $bbai_badge_partial;
                                    }
                                    ?>
                                </div>
                                <?php
                                $bbai_percentage = min(100, max(0, $bbai_usage_stats['percentage'] ?? 0));
                                $bbai_percentage_display = $bbai_usage_stats['percentage_display'] ?? Usage_Tracker::format_percentage_label($bbai_percentage);
                                $bbai_radius = 48;
                                $bbai_circumference = 2 * M_PI * $bbai_radius;
                                $bbai_stroke_dashoffset = $bbai_circumference * (1 - ($bbai_percentage / 100));

                                // Adaptive circle color based on usage percentage
                                if ($bbai_percentage >= 100) {
                                    $bbai_circle_stroke = '#EF4444';
                                    $bbai_ring_color_class = 'bbai-usage-ring--critical';
                                } elseif ($bbai_percentage >= 86) {
                                    $bbai_circle_stroke = '#EF4444';
                                    $bbai_ring_color_class = 'bbai-usage-ring--danger';
                                } elseif ($bbai_percentage >= 61) {
                                    $bbai_circle_stroke = '#F59E0B';
                                    $bbai_ring_color_class = 'bbai-usage-ring--warning';
                                } else {
                                    $bbai_circle_stroke = '#10B981';
                                    $bbai_ring_color_class = 'bbai-usage-ring--healthy';
                                }
                                ?>
                                <!-- Title -->
                                <h3 class="bbai-card-title"><?php esc_html_e('Alt Text Progress This Month', 'beepbeep-ai-alt-text-generator'); ?></h3>

                                <!-- Circular Progress -->
                                <div class="bbai-circular-progress-wrapper">
                                    <div class="bbai-circular-progress-container">
                                        <svg id="bbai-usage-circle-svg" class="bbai-circular-progress-svg" viewBox="0 0 120 120" aria-hidden="true">
                                            <!-- Background circle -->
                                            <circle
                                                cx="60"
                                                cy="60"
                                                r="48"
                                                fill="none"
                                                stroke="#f3f4f6"
                                                stroke-width="12" />
                                            <!-- Progress circle -->
                                            <circle
                                                id="bbai-usage-progress-ring"
                                                class="bbai-circular-progress-bar bbai-usage-ring <?php echo esc_attr($bbai_ring_color_class); ?>"
                                                cx="60"
                                                cy="60"
                                                r="48"
                                                fill="none"
                                                stroke="<?php echo esc_attr($bbai_circle_stroke); ?>"
                                                stroke-width="12"
                                                stroke-linecap="round"
                                                stroke-dasharray="<?php echo esc_attr($bbai_circumference); ?>"
                                                stroke-dashoffset="<?php echo esc_attr($bbai_stroke_dashoffset); ?>"
                                                data-circumference="<?php echo esc_attr($bbai_circumference); ?>"
                                                data-percentage="<?php echo esc_attr($bbai_percentage); ?>" />
                                        </svg>
                                        <div class="bbai-circular-progress-text">
                                            <div class="bbai-circular-progress-percent"><?php echo esc_html($bbai_percentage_display); ?>%</div>
                                            <div class="bbai-circular-progress-label"><?php esc_html_e('IMAGES USED', 'beepbeep-ai-alt-text-generator'); ?></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Usage Count -->
                                <div class="text-center">
                                    <p class="bbai-usage-count">
                                        <strong><?php echo esc_html(number_format_i18n($bbai_usage_stats['used'] ?? 0)); ?></strong> of <strong><?php echo esc_html(number_format_i18n($bbai_usage_stats['limit'] ?? 0)); ?></strong> images used this month
                                    </p>
                                </div>

                                <!-- Linear Progress Bar -->
                                <div class="bbai-linear-progress">
                                    <div class="bbai-linear-progress-fill" style="width: <?php echo esc_attr($bbai_percentage); ?>%; background: <?php echo esc_attr($bbai_circle_stroke); ?>;"></div>
                                </div>

                                <!-- Reset Date -->
                                <p class="bbai-reset-date">
                                    <?php 
                                    $bbai_reset_date = $bbai_usage_stats['reset_date'] ?? '';
                                    if (!empty($bbai_reset_date)) {
                                        $bbai_reset_timestamp = strtotime($bbai_reset_date);
                                        if ($bbai_reset_timestamp !== false) {
                                            $bbai_formatted_date = date_i18n('F j, Y', $bbai_reset_timestamp);
                                            /* translators: 1: reset date */
                                            printf(esc_html__('Resets %s', 'beepbeep-ai-alt-text-generator'), esc_html($bbai_formatted_date));
                                        } else {
                                            /* translators: 1: reset date */
                                            printf(esc_html__('Resets %s', 'beepbeep-ai-alt-text-generator'), esc_html($bbai_reset_date));
                                        }
                                    } else {
                                        esc_html_e('Resets monthly', 'beepbeep-ai-alt-text-generator');
                                    }
                                    ?>
                                </p>

                                <!-- Plan-Aware CTA -->
                                <?php if ($bbai_is_free) : ?>
                                    <button
                                        type="button"
                                        class="bbai-btn bbai-btn-primary bbai-upgrade-cta-green"
                                        data-action="show-upgrade-modal"
                                        aria-label="<?php esc_attr_e('Upgrade to 1,000 images/month', 'beepbeep-ai-alt-text-generator'); ?>"
                                    >
                                        <span><?php esc_html_e('Upgrade to 1,000 images/month', 'beepbeep-ai-alt-text-generator'); ?></span>
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5">
                                            <path d="M6 12L10 8L6 4" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </button>
                                <?php elseif ($bbai_is_growth) : ?>
                                    <button
                                        type="button"
                                        class="bbai-btn bbai-btn-primary bbai-upgrade-cta-green"
                                        data-action="show-upgrade-modal"
                                        aria-label="<?php esc_attr_e('Upgrade to Agency plan for 5,000 monthly credits', 'beepbeep-ai-alt-text-generator'); ?>"
                                    >
                                        <span><?php esc_html_e('Upgrade to Agency', 'beepbeep-ai-alt-text-generator'); ?></span>
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5">
                                            <path d="M6 12L10 8L6 4" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </button>
                                <?php elseif ($bbai_is_agency) : ?>
                                    <?php
                                    $bbai_billing_portal_url = '';
                                    if (class_exists('BeepBeepAI\AltTextGenerator\Usage_Tracker')) {
                                        $bbai_billing_portal_url = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_billing_portal_url();
                                    }
                                    if (!empty($bbai_billing_portal_url)) : ?>
                                        <a
                                            href="<?php echo esc_url($bbai_billing_portal_url); ?>"
                                            class="bbai-btn bbai-btn-secondary"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            <span><?php esc_html_e('Manage Subscription', 'beepbeep-ai-alt-text-generator'); ?></span>
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M4 12L12 4M12 4H6M12 4V10" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <!-- Action Buttons Grid -->
                                <div class="bbai-action-buttons-grid">
                                <button
                                    type="button"
                                    class="bbai-btn bbai-action-btn bbai-action-btn-primary <?php echo esc_attr((!$bbai_can_generate || $bbai_remaining_imgs === 0) ? 'disabled' : ''); ?>"
                                    data-action="generate-missing"
                                    <?php if (!$bbai_can_generate || $bbai_remaining_imgs === 0) : ?>
                                        disabled
                                    <?php endif; ?>
                                    aria-label="<?php esc_attr_e('Generate Missing', 'beepbeep-ai-alt-text-generator'); ?>"
                                    <?php if ($bbai_can_generate && $bbai_remaining_imgs > 0) : ?>
                                    data-bbai-tooltip="<?php esc_attr_e('Generate alt text for images that don\'t have any', 'beepbeep-ai-alt-text-generator'); ?>"
                                    data-bbai-tooltip-position="bottom"
                                    <?php elseif ($bbai_remaining_imgs === 0) : ?>
                                    title="<?php esc_attr_e('All images already have alt text', 'beepbeep-ai-alt-text-generator'); ?>"
                                    <?php else : ?>
                                    title="<?php esc_attr_e('Upgrade to unlock more generations', 'beepbeep-ai-alt-text-generator'); ?>"
                                    <?php endif; ?>
                                >
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="2" y="2" width="12" height="12" rx="2" />
                                            <path d="M6 6H10M6 10H10" stroke-linecap="round" />
                                        </svg>
                                        <span><?php esc_html_e('Generate Missing', 'beepbeep-ai-alt-text-generator'); ?></span>
                                    </button>
                                    <button
                                        type="button"
                                        class="bbai-btn bbai-action-btn bbai-action-btn-secondary <?php echo esc_attr((!$bbai_can_generate) ? 'disabled' : ''); ?>"
                                        data-action="regenerate-all"
                                        <?php if (!$bbai_can_generate) : ?>
                                            disabled
                                        <?php endif; ?>
                                        aria-label="<?php esc_attr_e('Re-optimise All', 'beepbeep-ai-alt-text-generator'); ?>"
                                        <?php if ($bbai_can_generate) : ?>
                                        data-bbai-tooltip="<?php esc_attr_e('Regenerate alt text for ALL images, replacing existing ones', 'beepbeep-ai-alt-text-generator'); ?>"
                                        data-bbai-tooltip-position="bottom"
                                        <?php else : ?>
                                        title="<?php esc_attr_e('Upgrade to unlock more generations', 'beepbeep-ai-alt-text-generator'); ?>"
                                        <?php endif; ?>
                                    >
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M8 2L10 6L14 8L10 10L8 14L6 10L2 8L6 6L8 2Z" />
                                            <circle cx="8" cy="8" r="2" fill="currentColor" />
                                        </svg>
                                        <span><?php esc_html_e('Re-optimise All', 'beepbeep-ai-alt-text-generator'); ?></span>
                                    </button>
                                </div>

                                <!-- Footer Text -->
                                <p class="bbai-footer-text">
                                    <?php esc_html_e('Free plan includes 50 images per month. Growth includes 1,000 images per month.', 'beepbeep-ai-alt-text-generator'); ?>
                                </p>
                            </div>
                            
                            <!-- Upgrade Growth Card -->
                            <?php if (!$bbai_is_agency) : ?>
                            <div class="bbai-upgrade-growth-card">
                                <!-- Badge -->
                                <div class="flex">
                                    <?php
                                    $bbai_badge_text = esc_html__('GROWTH', 'beepbeep-ai-alt-text-generator');
                                    $bbai_badge_variant = 'growth';
                                    $bbai_badge_class = '';
                                    $bbai_badge_partial = plugin_dir_path( BBAI_PLUGIN_FILE ) . 'admin/partials/badge.php';
                                    if (file_exists($bbai_badge_partial)) {
                                        include $bbai_badge_partial;
                                    }
                                    ?>
                                </div>

                                <!-- Title -->
                                <h3 class="bbai-card-title"><?php esc_html_e('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'); ?></h3>

                                <!-- Subtitle -->
                                <p class="bbai-card-subtitle"><?php esc_html_e('Automate alt text generation and scale image optimisation each month.', 'beepbeep-ai-alt-text-generator'); ?></p>

                                <!-- Benefit List -->
                                <ul class="bbai-benefits-list">
                                    <li class="bbai-benefit-item">
                                        <div class="bbai-benefit-icon">
                                            <svg fill="none" viewBox="0 0 16 16" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M13 4L6 11L3 8" />
                                            </svg>
                                        </div>
                                        <span class="bbai-benefit-text"><?php esc_html_e('1,000 AI alt texts per month', 'beepbeep-ai-alt-text-generator'); ?></span>
                                    </li>
                                    <li class="bbai-benefit-item">
                                        <div class="bbai-benefit-icon">
                                            <svg fill="none" viewBox="0 0 16 16" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M13 4L6 11L3 8" />
                                            </svg>
                                        </div>
                                        <span class="bbai-benefit-text"><?php esc_html_e('Bulk processing for the media library', 'beepbeep-ai-alt-text-generator'); ?></span>
                                    </li>
                                    <li class="bbai-benefit-item">
                                        <div class="bbai-benefit-icon">
                                            <svg fill="none" viewBox="0 0 16 16" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M13 4L6 11L3 8" />
                                            </svg>
                                        </div>
                                        <span class="bbai-benefit-text"><?php esc_html_e('Priority queue for faster results', 'beepbeep-ai-alt-text-generator'); ?></span>
                                    </li>
                                    <li class="bbai-benefit-item">
                                        <div class="bbai-benefit-icon">
                                            <svg fill="none" viewBox="0 0 16 16" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M13 4L6 11L3 8" />
                                            </svg>
                                        </div>
                                        <span class="bbai-benefit-text"><?php esc_html_e('Multilingual support for global SEO', 'beepbeep-ai-alt-text-generator'); ?></span>
                                    </li>
                                </ul>

                                <!-- CTA Section -->
                                <div class="bbai-cta-section">
                                    <!-- Primary CTA Button -->
                                    <button
                                        type="button"
                                        class="bbai-cta-primary"
                                        data-action="show-upgrade-modal"
                                        aria-label="<?php esc_attr_e('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'); ?>"
                                    >
                                        <?php esc_html_e('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'); ?>
                                    </button>

                                    <!-- Microcopy -->
                                    <p class="bbai-cta-microcopy"><?php esc_html_e('Includes 1,000 AI alt texts per month. Cancel anytime.', 'beepbeep-ai-alt-text-generator'); ?></p>

                                    <!-- Compare Plans Link -->
                                    <a
                                        href="#"
                                        class="bbai-compare-link"
                                        data-action="show-upgrade-modal"
                                        onclick="if(typeof alttextaiShowModal==='function'){alttextaiShowModal();}else if(typeof window.alttextaiShowModal==='function'){window.alttextaiShowModal();}return false;"
                                    >
                                        <?php esc_html_e('Compare plans', 'beepbeep-ai-alt-text-generator'); ?>
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Stats Cards Row -->
                        <?php
                        // Prepare data for reusable metric cards component
                        $bbai_alt_texts_generated = $bbai_usage_stats['used'] ?? 0;
                        $bbai_description_mode = ($bbai_alt_texts_generated > 0 || ($bbai_stats['with_alt'] ?? 0) > 0) ? 'active' : 'empty';
                        $bbai_metric_cards_partial = plugin_dir_path( BBAI_PLUGIN_FILE ) . 'admin/partials/metric-cards.php';
                        if (file_exists($bbai_metric_cards_partial)) {
                            include $bbai_metric_cards_partial;
                        }
                        ?>
                        
                        <!-- Site-Wide Licensing Notice -->
                        <div class="bbai-premium-card bbai-info-notice">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                <circle cx="10" cy="10" r="9" stroke="#0ea5e9" stroke-width="1.5" fill="none"/>
                                <path d="M10 6V10M10 14H10.01" stroke="#0ea5e9" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <span>
                                <?php 
                                esc_html_e('Monthly quota is shared across all site users. Upgrade to Growth for 1,000 generations per month.', 'beepbeep-ai-alt-text-generator');
                                ?>
                            </span>
                        </div>

                        <!-- Image Optimization Card (Full Width Pill) -->
                        <?php
                        $bbai_total_images = $bbai_stats['total'] ?? 0;
                        $bbai_optimized = $bbai_stats['with_alt'] ?? 0;
                        $bbai_remaining_imgs = $bbai_stats['missing'] ?? 0;
                        $bbai_coverage_pct = $bbai_total_images > 0 ? round(($bbai_optimized / $bbai_total_images) * 100) : 0;

                        // Check if user has quota remaining (for free users) or is on pro/agency plan
                        $bbai_plan = $bbai_usage_stats['plan'] ?? 'free';
                        $bbai_has_quota = ($bbai_usage_stats['remaining'] ?? 0) > 0;
                        $bbai_is_premium = in_array($bbai_plan, ['pro', 'agency'], true);
                        $bbai_can_generate = $bbai_has_quota || $bbai_is_premium;
                        ?>
                        <div class="bbai-premium-card bbai-optimization-card <?php echo esc_attr(($bbai_total_images > 0 && $bbai_remaining_imgs === 0) ? 'bbai-optimization-card--complete' : ''); ?>">
                            <?php if ($bbai_total_images > 0 && $bbai_remaining_imgs === 0) : ?>
                                <div class="bbai-optimization-accent-bar"></div>
                            <?php endif; ?>
                            <div class="bbai-optimization-header">
                                <?php if ($bbai_total_images > 0 && $bbai_remaining_imgs === 0) : ?>
                                    <div class="bbai-optimization-success-chip">
                                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                                            <path d="M13 4L6 11L3 8" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                                <h2 class="bbai-optimization-title">
                                    <?php 
                                    if ($bbai_total_images > 0) {
                                        if ($bbai_remaining_imgs > 0) {
                                            printf(
                                                /* translators: 1: optimized images count, 2: total images count */
                                                esc_html__('%1$s of %2$s images optimized', 'beepbeep-ai-alt-text-generator'),
                                                esc_html(number_format_i18n($bbai_optimized)),
                                                esc_html(number_format_i18n($bbai_total_images))
                                            );
                                        } else {
                                            // Success chip with checkmark icon is already shown above
                                            printf(
                                                /* translators: 1: total images count */
                                                esc_html__('All %1$s images optimized!', 'beepbeep-ai-alt-text-generator'),
                                                esc_html(number_format_i18n($bbai_total_images))
                                            );
                                        }
                                    } else {
                                        esc_html_e('Ready to optimize images', 'beepbeep-ai-alt-text-generator');
                                    }
                                    ?>
                                </h2>
                            </div>
                            
                            <?php if ($bbai_total_images > 0) : ?>
                                <div class="bbai-optimization-progress">
                                    <div class="bbai-optimization-progress-bar">
                                        <div class="bbai-optimization-progress-fill" style="width: <?php echo esc_attr($bbai_coverage_pct); ?>%; background: <?php echo esc_attr(($bbai_remaining_imgs === 0) ? '#10b981' : '#9b5cff'); ?>;"></div>
                                    </div>
                                    <div class="bbai-optimization-stats">
                                        <div class="bbai-optimization-stat" data-bbai-tooltip="<?php esc_attr_e('Number of images that already have alt text. These images are SEO-ready and accessible.', 'beepbeep-ai-alt-text-generator'); ?>" data-bbai-tooltip-position="top">
                                            <span class="bbai-optimization-stat-label"><?php esc_html_e('Optimized', 'beepbeep-ai-alt-text-generator'); ?></span>
                                            <span class="bbai-optimization-stat-value"><?php echo esc_html(number_format_i18n($bbai_optimized)); ?></span>
                                        </div>
                                        <div class="bbai-optimization-stat" data-bbai-tooltip="<?php esc_attr_e('Images without alt text. These need optimization for better SEO and accessibility compliance.', 'beepbeep-ai-alt-text-generator'); ?>" data-bbai-tooltip-position="top">
                                            <span class="bbai-optimization-stat-label"><?php esc_html_e('Remaining', 'beepbeep-ai-alt-text-generator'); ?></span>
                                            <span class="bbai-optimization-stat-value"><?php echo esc_html(number_format_i18n($bbai_remaining_imgs)); ?></span>
                                        </div>
                                        <div class="bbai-optimization-stat" data-bbai-tooltip="<?php esc_attr_e('Total number of images in your media library. This includes all image types and sizes.', 'beepbeep-ai-alt-text-generator'); ?>" data-bbai-tooltip-position="top">
                                            <span class="bbai-optimization-stat-label"><?php esc_html_e('Total', 'beepbeep-ai-alt-text-generator'); ?></span>
                                            <span class="bbai-optimization-stat-value"><?php echo esc_html(number_format_i18n($bbai_total_images)); ?></span>
                                        </div>
                                    </div>
                                    <div class="bbai-optimization-actions">
                                        <button type="button" class="bbai-optimization-cta bbai-optimization-cta--primary <?php echo esc_attr((!$bbai_can_generate) ? 'bbai-optimization-cta--locked' : ''); ?> <?php echo esc_attr(($bbai_remaining_imgs === 0) ? 'bbai-optimization-cta--disabled' : ''); ?>" data-action="generate-missing"
                                            <?php if (!$bbai_can_generate || $bbai_remaining_imgs === 0) : ?>
                                                disabled
                                                <?php if ($bbai_remaining_imgs === 0) : ?>
                                                    title="<?php esc_attr_e('All images already have alt text', 'beepbeep-ai-alt-text-generator'); ?>"
                                                <?php else : ?>
                                                    title="<?php esc_attr_e('Unlock 1,000 alt text generations with Growth →', 'beepbeep-ai-alt-text-generator'); ?>"
                                                <?php endif; ?>
                                            <?php else : ?>
                                                data-bbai-tooltip="<?php esc_attr_e('Automatically generate alt text for all images that don\'t have any. Processes in the background without slowing down your site.', 'beepbeep-ai-alt-text-generator'); ?>"
                                                data-bbai-tooltip-position="bottom"
                                            <?php endif; ?>
                                        >
                                            <?php if (!$bbai_can_generate) : ?>
                                                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" class="bbai-btn-icon">
                                                    <path d="M12 6V4a4 4 0 00-8 0v2M4 6h8l1 8H3L4 6z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                                <span><?php esc_html_e('Generate Missing', 'beepbeep-ai-alt-text-generator'); ?></span>
                                            <?php else : ?>
                                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="bbai-btn-icon">
                                                    <rect x="2" y="2" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                                    <path d="M6 6H10M6 10H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                </svg>
                                                <span><?php esc_html_e('Generate Missing', 'beepbeep-ai-alt-text-generator'); ?></span>
                                            <?php endif; ?>
                                        </button>
                                        <button type="button" class="bbai-optimization-cta bbai-optimization-cta--secondary bbai-cta-glow-blue <?php echo esc_attr((!$bbai_can_generate) ? 'bbai-optimization-cta--locked' : ''); ?>" data-action="regenerate-all"
                                            <?php if (!$bbai_can_generate) : ?>
                                                disabled
                                                title="<?php esc_attr_e('Unlock 1,000 alt text generations with Growth →', 'beepbeep-ai-alt-text-generator'); ?>"
                                            <?php else : ?>
                                                data-bbai-tooltip="<?php esc_attr_e('Regenerate alt text for ALL images, even those that already have it. Useful after changing your tone/style settings or brand guidelines.', 'beepbeep-ai-alt-text-generator'); ?>"
                                                data-bbai-tooltip-position="bottom"
                                            <?php endif; ?>
                                        >
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="bbai-btn-icon">
                                                <path d="M8 2L10 6L14 8L10 10L8 14L6 10L2 8L6 6L8 2Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                                <circle cx="8" cy="8" r="2" fill="currentColor"/>
                                            </svg>
                                            <span><?php esc_html_e('Re-optimise All', 'beepbeep-ai-alt-text-generator'); ?></span>
                                        </button>
                                    </div>
                                </div>
                            <?php else : ?>
                                <div class="bbai-optimization-empty bbai-ready-optimise">
                                    <?php
                                    $bbai_badge_text = esc_html__('GETTING STARTED', 'beepbeep-ai-alt-text-generator');
                                    $bbai_badge_variant = 'getting-started';
                                    $bbai_badge_class = 'bbai-ready-optimise__badge';
                                    $bbai_badge_partial = plugin_dir_path( BBAI_PLUGIN_FILE ) . 'admin/partials/badge.php';
                                    if (file_exists($bbai_badge_partial)) {
                                        include $bbai_badge_partial;
                                    }
                                    ?>
                                    <h3 class="bbai-ready-optimise__title"><?php esc_html_e("You're ready to optimise your images", 'beepbeep-ai-alt-text-generator'); ?></h3>
                                </div>
                            <?php endif; ?>
                        </div>
<?php endif; ?>

    <!-- Social Proof Widget -->
    <?php
    $bbai_social_proof_partial = plugin_dir_path( BBAI_PLUGIN_FILE ) . 'admin/partials/social-proof-widget.php';
    if (file_exists($bbai_social_proof_partial)) {
        include $bbai_social_proof_partial;
    }
    ?>

    <!-- Bottom Upsell CTA (reusable component) -->
    <?php
    // Plan variables are already set from Plan_Helpers above
    $bbai_bottom_upsell_partial = plugin_dir_path( BBAI_PLUGIN_FILE ) . 'admin/partials/bottom-upsell-cta.php';
    if (file_exists($bbai_bottom_upsell_partial)) {
        include $bbai_bottom_upsell_partial;
    }
    ?>
