<?php
/** Body content for dashboard (cards, metrics). */
if (!defined('ABSPATH')) { exit; }
use BeepBeepAI\AltTextGenerator\Usage_Tracker;
use BeepBeepAI\AltTextGenerator\Admin\Plan_Helpers;

// Load Plan_Helpers class
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/class-plan-helpers.php';

// Include onboarding modal
$onboarding_modal_path = dirname(__FILE__) . '/onboarding-modal.php';
if (file_exists($onboarding_modal_path)) {
    include $onboarding_modal_path;
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
                        $is_authenticated = $this->api_client->is_authenticated();
                        $has_license = $this->api_client->has_active_license();
                        // Treat stored registration/license as access for dashboard content
                        $has_registered_user = $has_registered_user ?? false;
                        if ($is_authenticated || $has_license || $has_registered_user) :
                            // Get plan data from centralized helper
                            $plan_data = Plan_Helpers::get_plan_data();
                            $plan_slug = $plan_data['plan_slug'];
                            $is_free = $plan_data['is_free'];
                            $is_growth = $plan_data['is_growth'];
                            $is_agency = $plan_data['is_agency'];
                            $is_pro = $plan_data['is_pro'];
                            $plan_badge_text = Plan_Helpers::get_plan_badge_text();
                            $plan_badge_variant = Plan_Helpers::get_plan_badge_variant();
                        ?>
                        
                        <!-- Multi-User Token Bar Container -->
                        <div id="bbai-multiuser-token-bar-root" class="bbai-multiuser-token-bar-container bbai-mb-6"></div>
                        
                        <!-- Premium Stats Grid -->
                        <?php
                        // Check if user has quota remaining (for free users) or is on pro/agency plan
                        $plan = $usage_stats['plan'] ?? 'free';
                        $has_quota = ($usage_stats['remaining'] ?? 0) > 0;
                        $is_premium = in_array($plan, ['pro', 'agency'], true);
                        $can_generate = $has_quota || $is_premium;
                        ?>
                        <div class="bbai-dashboard-overview">
                            <!-- Usage Card with Circular Progress -->
                            <div class="bbai-usage-card-redesign">
                                <!-- Badge -->
                                <div class="flex">
                                    <?php
                                    $badge_text = $plan_badge_text;
                                    $badge_variant = $plan_badge_variant;
                                    $badge_class = '';
                                    $badge_partial = dirname(__FILE__) . '/badge.php';
                                    if (file_exists($badge_partial)) {
                                        include $badge_partial;
                                    }
                                    ?>
                                </div>
                                <?php
                                $percentage = min(100, max(0, $usage_stats['percentage'] ?? 0));
                                $percentage_display = $usage_stats['percentage_display'] ?? Usage_Tracker::format_percentage_label($percentage);
                                $radius = 48;
                                $circumference = 2 * M_PI * $radius;
                                // Calculate offset: at 0% = full circumference (hidden), at 100% = 0 (fully visible)
                                $stroke_dashoffset = $circumference * (1 - ($percentage / 100));
                                $gradient_id = 'grad-' . wp_generate_password(8, false);
                                ?>
                                <!-- Title -->
                                <h3 class="bbai-card-title"><?php esc_html_e('Alt Text Progress This Month', 'beepbeep-ai-alt-text-generator'); ?></h3>

                                <!-- Circular Progress -->
                                <div class="bbai-circular-progress-wrapper">
                                    <div class="bbai-circular-progress-container">
                                        <svg class="bbai-circular-progress-svg" viewBox="0 0 120 120" aria-hidden="true">
                                            <defs>
                                                <linearGradient id="<?php echo esc_attr($gradient_id); ?>" x1="0%" y1="0%" x2="100%" y2="100%">
                                                    <stop offset="0%" style="stop-color:#3B82F6;stop-opacity:1" />
                                                    <stop offset="100%" style="stop-color:#60A5FA;stop-opacity:1" />
                                                </linearGradient>
                                            </defs>
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
                                                cx="60"
                                                cy="60"
                                                r="48"
                                                fill="none"
                                                stroke="url(#<?php echo esc_attr($gradient_id); ?>)"
                                                stroke-width="12"
                                                stroke-linecap="round"
                                                stroke-dasharray="<?php echo esc_attr($circumference); ?>"
                                                stroke-dashoffset="<?php echo esc_attr($stroke_dashoffset); ?>"
                                                class="transition-all duration-500 ease-out" />
                                        </svg>
                                        <div class="bbai-circular-progress-text">
                                            <div class="bbai-circular-progress-percent"><?php echo esc_html($percentage_display); ?>%</div>
                                            <div class="bbai-circular-progress-label"><?php esc_html_e('IMAGES USED', 'beepbeep-ai-alt-text-generator'); ?></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Usage Count -->
                                <div class="text-center">
                                    <p class="bbai-usage-count">
                                        <strong><?php echo esc_html($usage_stats['used']); ?></strong> of <strong><?php echo esc_html($usage_stats['limit']); ?></strong> images used this month
                                    </p>
                                </div>

                                <!-- Linear Progress Bar -->
                                <div class="bbai-linear-progress">
                                    <div class="bbai-linear-progress-fill" style="width: <?php echo esc_attr($percentage); ?>%;"></div>
                                </div>

                                <!-- Reset Date -->
                                <p class="bbai-reset-date">
                                    <?php 
                                    $reset_date = $usage_stats['reset_date'] ?? '';
                                    if (!empty($reset_date)) {
                                        $reset_timestamp = strtotime($reset_date);
                                        if ($reset_timestamp !== false) {
                                            $formatted_date = date_i18n('F j, Y', $reset_timestamp);
                                            printf(esc_html__('Resets %s', 'beepbeep-ai-alt-text-generator'), esc_html($formatted_date));
                                        } else {
                                            printf(esc_html__('Resets %s', 'beepbeep-ai-alt-text-generator'), esc_html($reset_date));
                                        }
                                    } else {
                                        esc_html_e('Resets monthly', 'beepbeep-ai-alt-text-generator');
                                    }
                                    ?>
                                </p>

                                <!-- Plan-Aware CTA -->
                                <?php if ($is_free) : ?>
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
                                <?php elseif ($is_growth) : ?>
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
                                <?php elseif ($is_agency) : ?>
                                    <?php
                                    $billing_portal_url = '';
                                    if (class_exists('BeepBeepAI\AltTextGenerator\Usage_Tracker')) {
                                        $billing_portal_url = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_billing_portal_url();
                                    }
                                    if (!empty($billing_portal_url)) : ?>
                                        <a
                                            href="<?php echo esc_url($billing_portal_url); ?>"
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
                                    class="bbai-btn bbai-action-btn bbai-action-btn-primary <?php echo esc_attr((!$can_generate) ? 'disabled' : ''); ?>"
                                    data-action="generate-missing"
                                    <?php echo (!$can_generate) ? 'disabled' : ''; ?>
                                    aria-label="<?php esc_attr_e('Generate Missing', 'beepbeep-ai-alt-text-generator'); ?>"
                                    <?php if ($can_generate) : ?>
                                    data-bbai-tooltip="<?php esc_attr_e('Generate alt text for images that don\'t have any', 'beepbeep-ai-alt-text-generator'); ?>"
                                    data-bbai-tooltip-position="bottom"
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
                                        class="bbai-btn bbai-action-btn bbai-action-btn-secondary <?php echo esc_attr((!$can_generate) ? 'disabled' : ''); ?>"
                                        data-action="regenerate-all"
                                        <?php echo (!$can_generate) ? 'disabled' : ''; ?>
                                        aria-label="<?php esc_attr_e('Re-optimise All', 'beepbeep-ai-alt-text-generator'); ?>"
                                        <?php if ($can_generate) : ?>
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
                            <?php if (!$is_agency) : ?>
                            <div class="bbai-upgrade-growth-card">
                                <!-- Badge -->
                                <div class="flex">
                                    <?php
                                    $badge_text = esc_html__('GROWTH', 'beepbeep-ai-alt-text-generator');
                                    $badge_variant = 'growth';
                                    $badge_class = '';
                                    $badge_partial = dirname(__FILE__) . '/badge.php';
                                    if (file_exists($badge_partial)) {
                                        include $badge_partial;
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
                        $alt_texts_generated = $usage_stats['used'] ?? 0;
                        $description_mode = ($alt_texts_generated > 0 || ($stats['with_alt'] ?? 0) > 0) ? 'active' : 'empty';
                        $metric_cards_partial = dirname(__FILE__) . '/metric-cards.php';
                        if (file_exists($metric_cards_partial)) {
                            include $metric_cards_partial;
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
                        $total_images = $stats['total'] ?? 0;
                        $optimized = $stats['with_alt'] ?? 0;
                        $remaining_imgs = $stats['missing'] ?? 0;
                        $coverage_pct = $total_images > 0 ? round(($optimized / $total_images) * 100) : 0;

                        // Check if user has quota remaining (for free users) or is on pro/agency plan
                        $plan = $usage_stats['plan'] ?? 'free';
                        $has_quota = ($usage_stats['remaining'] ?? 0) > 0;
                        $is_premium = in_array($plan, ['pro', 'agency'], true);
                        $can_generate = $has_quota || $is_premium;
                        ?>
                        <div class="bbai-premium-card bbai-optimization-card <?php echo esc_attr(($total_images > 0 && $remaining_imgs === 0) ? 'bbai-optimization-card--complete' : ''); ?>">
                            <?php if ($total_images > 0 && $remaining_imgs === 0) : ?>
                                <div class="bbai-optimization-accent-bar"></div>
                            <?php endif; ?>
                            <div class="bbai-optimization-header">
                                <?php if ($total_images > 0 && $remaining_imgs === 0) : ?>
                                    <div class="bbai-optimization-success-chip">
                                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                                            <path d="M13 4L6 11L3 8" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                                <h2 class="bbai-optimization-title">
                                    <?php 
                                    if ($total_images > 0) {
                                        if ($remaining_imgs > 0) {
                                            printf(
                                                esc_html__('%1$d of %2$d images optimized', 'beepbeep-ai-alt-text-generator'),
                                                $optimized,
                                                $total_images
                                            );
                                        } else {
                                            // Success chip with checkmark icon is already shown above
                                            printf(
                                                esc_html__('All %1$d images optimized!', 'beepbeep-ai-alt-text-generator'),
                                                $total_images
                                            );
                                        }
                                    } else {
                                        esc_html_e('Ready to optimize images', 'beepbeep-ai-alt-text-generator');
                                    }
                                    ?>
                                </h2>
                            </div>
                            
                            <?php if ($total_images > 0) : ?>
                                <div class="bbai-optimization-progress">
                                    <div class="bbai-optimization-progress-bar">
                                        <div class="bbai-optimization-progress-fill" style="width: <?php echo esc_attr($coverage_pct); ?>%; background: <?php echo esc_attr(($remaining_imgs === 0) ? '#10b981' : '#9b5cff'); ?>;"></div>
                                    </div>
                                    <div class="bbai-optimization-stats">
                                        <div class="bbai-optimization-stat" data-bbai-tooltip="<?php esc_attr_e('Number of images that already have alt text. These images are SEO-ready and accessible.', 'beepbeep-ai-alt-text-generator'); ?>" data-bbai-tooltip-position="top">
                                            <span class="bbai-optimization-stat-label"><?php esc_html_e('Optimized', 'beepbeep-ai-alt-text-generator'); ?></span>
                                            <span class="bbai-optimization-stat-value"><?php echo esc_html($optimized); ?></span>
                                        </div>
                                        <div class="bbai-optimization-stat" data-bbai-tooltip="<?php esc_attr_e('Images without alt text. These need optimization for better SEO and accessibility compliance.', 'beepbeep-ai-alt-text-generator'); ?>" data-bbai-tooltip-position="top">
                                            <span class="bbai-optimization-stat-label"><?php esc_html_e('Remaining', 'beepbeep-ai-alt-text-generator'); ?></span>
                                            <span class="bbai-optimization-stat-value"><?php echo esc_html($remaining_imgs); ?></span>
                                        </div>
                                        <div class="bbai-optimization-stat" data-bbai-tooltip="<?php esc_attr_e('Total number of images in your media library. This includes all image types and sizes.', 'beepbeep-ai-alt-text-generator'); ?>" data-bbai-tooltip-position="top">
                                            <span class="bbai-optimization-stat-label"><?php esc_html_e('Total', 'beepbeep-ai-alt-text-generator'); ?></span>
                                            <span class="bbai-optimization-stat-value"><?php echo esc_html($total_images); ?></span>
                                        </div>
                                    </div>
                                    <div class="bbai-optimization-actions">
                                        <button type="button" class="bbai-optimization-cta bbai-optimization-cta--primary <?php echo esc_attr((!$can_generate) ? 'bbai-optimization-cta--locked' : ''); ?>" data-action="generate-missing" <?php echo (!$can_generate) ? 'disabled title="' . esc_attr__('Unlock 1,000 alt text generations with Growth →', 'beepbeep-ai-alt-text-generator') . '"' : 'data-bbai-tooltip="' . esc_attr__('Automatically generate alt text for all images that don\'t have any. Processes in the background without slowing down your site.', 'beepbeep-ai-alt-text-generator') . '" data-bbai-tooltip-position="bottom"'; ?>>
                                            <?php if (!$can_generate) : ?>
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
                                        <button type="button" class="bbai-optimization-cta bbai-optimization-cta--secondary bbai-cta-glow-blue <?php echo esc_attr((!$can_generate) ? 'bbai-optimization-cta--locked' : ''); ?>" data-action="regenerate-all" <?php echo (!$can_generate) ? 'disabled title="' . esc_attr__('Unlock 1,000 alt text generations with Growth →', 'beepbeep-ai-alt-text-generator') . '"' : 'data-bbai-tooltip="' . esc_attr__('Regenerate alt text for ALL images, even those that already have it. Useful after changing your tone/style settings or brand guidelines.', 'beepbeep-ai-alt-text-generator') . '" data-bbai-tooltip-position="bottom"'; ?>>
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
                                    $badge_text = esc_html__('GETTING STARTED', 'beepbeep-ai-alt-text-generator');
                                    $badge_variant = 'getting-started';
                                    $badge_class = 'bbai-ready-optimise__badge';
                                    $badge_partial = dirname(__FILE__) . '/badge.php';
                                    if (file_exists($badge_partial)) {
                                        include $badge_partial;
                                    }
                                    ?>
                                    <h3 class="bbai-ready-optimise__title"><?php esc_html_e("You're ready to optimise your images", 'beepbeep-ai-alt-text-generator'); ?></h3>
                                </div>
                            <?php endif; ?>
                        </div>
<?php endif; ?>

    <!-- Social Proof Widget -->
    <?php
    $social_proof_partial = dirname(__FILE__) . '/social-proof-widget.php';
    if (file_exists($social_proof_partial)) {
        include $social_proof_partial;
    }
    ?>

    <!-- Bottom Upsell CTA (reusable component) -->
    <?php
    // Plan variables are already set from Plan_Helpers above
    $bottom_upsell_partial = dirname(__FILE__) . '/bottom-upsell-cta.php';
    if (file_exists($bottom_upsell_partial)) {
        include $bottom_upsell_partial;
    }
    ?>
