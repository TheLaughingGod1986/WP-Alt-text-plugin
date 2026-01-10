<?php
/** Body content for dashboard (cards, metrics). */
if (!defined('ABSPATH')) { exit; }
use BeepBeepAI\AltTextGenerator\Usage_Tracker;
?>
<!-- Tab Content: Dashboard -->
                    <div class="bbai-tab-content active" id="tab-dashboard">
                    <!-- Premium Dashboard Container -->
                    <div class="bbai-premium-dashboard">
                        <!-- Subtle Header Section -->
                        <div class="bbai-dashboard-header-section">
                            <h1 class="bbai-dashboard-title"><?php esc_html_e('Dashboard', 'beepbeep-ai-alt-text-generator'); ?></h1>
                            <p class="bbai-dashboard-subtitle"><?php esc_html_e('Automated, accessible alt text generation for your WordPress media library.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        </div>

                        <?php 
                        $is_authenticated = $this->api_client->is_authenticated();
                        $has_license = $this->api_client->has_active_license();
                        // Treat stored registration/license as access for dashboard content
                        $has_registered_user = $has_registered_user ?? false;
                        if ($is_authenticated || $has_license || $has_registered_user) : 
                            // Get plan from usage stats or license
                            $plan_slug = $usage_stats['plan'] ?? 'free';
                            
                            // If using license, check license plan
                            if ($has_license && $plan_slug === 'free') {
                                $license_data = $this->api_client->get_license_data();
                                if ($license_data && isset($license_data['organization'])) {
                                    $plan_slug = strtolower($license_data['organization']['plan'] ?? 'free');
                                }
                            }
                            
                            // Determine badge text and class
                            $plan_badge_class = 'bbai-usage-plan-badge';
                            $is_agency = ($plan_slug === 'agency');
                            $is_pro = ($plan_slug === 'pro' || $plan_slug === 'agency');
                            
                            if ($plan_slug === 'agency') {
                                $plan_badge_text = esc_html__('AGENCY', 'beepbeep-ai-alt-text-generator');
                                $plan_badge_class .= ' bbai-usage-plan-badge--agency';
                            } elseif ($plan_slug === 'pro') {
                                $plan_badge_text = esc_html__('PRO', 'beepbeep-ai-alt-text-generator');
                                $plan_badge_class .= ' bbai-usage-plan-badge--pro';
                            } else {
                                $plan_badge_text = esc_html__('FREE', 'beepbeep-ai-alt-text-generator');
                                $plan_badge_class .= ' bbai-usage-plan-badge--free';
                            }
                        ?>
                        
                        <!-- Multi-User Token Bar Container -->
                        <div id="bbai-multiuser-token-bar-root" class="bbai-multiuser-token-bar-container bbai-mb-6"></div>
                        
                        <!-- Premium Stats Grid -->
                        <div class="bbai-premium-stats-grid<?php echo esc_attr($is_agency ? ' bbai-premium-stats-grid--single' : ''); ?>">
                            <!-- Usage Card with Circular Progress -->
                            <div class="bbai-premium-card bbai-usage-card<?php echo esc_attr($is_agency ? ' bbai-usage-card--full-width' : ''); ?>">
                                <?php if ($is_agency) : ?>
                                <!-- Soft purple gradient badge for Agency -->
                                <span class="bbai-usage-plan-badge bbai-usage-plan-badge--agency-polished"><?php echo esc_html__('AGENCY', 'beepbeep-ai-alt-text-generator'); ?></span>
                                <?php else : ?>
                                <span class="<?php echo esc_attr($plan_badge_class); ?>"><?php echo esc_html($plan_badge_text); ?></span>
                                <?php endif; ?>
                                <?php
                                $percentage = min(100, max(0, $usage_stats['percentage'] ?? 0));
                                $percentage_display = $usage_stats['percentage_display'] ?? Usage_Tracker::format_percentage_label($percentage);
                                $radius = 54;
                                $circumference = 2 * M_PI * $radius;
                                // Calculate offset: at 0% = full circumference (hidden), at 100% = 0 (fully visible)
                                $stroke_dashoffset = $circumference * (1 - ($percentage / 100));
                                $gradient_id = 'grad-' . wp_generate_password(8, false);
                                ?>
                                <?php if ($is_agency) : ?>
                                <!-- Full-width agency layout - Polished Design -->
                                <div class="bbai-usage-card-layout-full">
                                    <div class="bbai-usage-card-left">
                                        <h3 class="bbai-usage-card-title"><?php esc_html_e('Alt Text Generated This Month', 'beepbeep-ai-alt-text-generator'); ?></h3>
                                        <div class="bbai-usage-card-stats">
                                            <div class="bbai-usage-stat-item">
                                                <div class="bbai-usage-stat-value bbai-number-counting"><?php echo esc_html(number_format_i18n($usage_stats['used'])); ?></div>
                                                <div class="bbai-usage-stat-label"><?php esc_html_e('Generated', 'beepbeep-ai-alt-text-generator'); ?></div>
                                            </div>
                                            <div class="bbai-usage-stat-divider"></div>
                                            <div class="bbai-usage-stat-item">
                                                <div class="bbai-usage-stat-value bbai-number-counting"><?php echo esc_html(number_format_i18n($usage_stats['limit'])); ?></div>
                                                <div class="bbai-usage-stat-label"><?php esc_html_e('Monthly Limit', 'beepbeep-ai-alt-text-generator'); ?></div>
                                            </div>
                                            <div class="bbai-usage-stat-divider"></div>
                                            <div class="bbai-usage-stat-item">
                                                <div class="bbai-usage-stat-value bbai-number-counting"><?php echo esc_html(number_format_i18n($usage_stats['remaining'] ?? 0)); ?></div>
                                                <div class="bbai-usage-stat-label"><?php esc_html_e('Remaining', 'beepbeep-ai-alt-text-generator'); ?></div>
                                            </div>
                                        </div>
                                        <div class="bbai-usage-card-reset">
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="bbai-mr-2" aria-hidden="true">
                                                <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                                <path d="M8 4V8L10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                            </svg>
                                            <span>
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
                                            </span>
                                        </div>
                                        <?php 
                                        $plan_slug = $usage_stats['plan'] ?? 'free';
                                        $billing_portal = Usage_Tracker::get_billing_portal_url();
                                        ?>
                                        <?php if (!empty($billing_portal)) : ?>
                                        <div class="bbai-usage-card-actions">
                                            <a href="#" class="bbai-usage-billing-link" data-action="open-billing-portal">
                                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="bbai-mr-2" aria-hidden="true">
                                                    <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                                    <circle cx="8" cy="8" r="2" fill="currentColor"/>
                                                </svg>
                                                <?php esc_html_e('Manage Billing', 'beepbeep-ai-alt-text-generator'); ?>
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="bbai-usage-card-divider" aria-hidden="true"></div>
                                    <div class="bbai-usage-card-right">
                                <div class="bbai-usage-ring-wrapper">
                                            <?php
                                            // Modern thin stroke ring gauge for agency
                                            $agency_radius = 60;
                                            $agency_circumference = 2 * M_PI * $agency_radius;
                                            $agency_stroke_dashoffset = $agency_circumference * (1 - ($percentage / 100));
                                            $agency_gradient_id = 'grad-agency-' . wp_generate_password(8, false);
                                            ?>
                                            <div class="bbai-circular-progress bbai-circular-progress--agency" 
                                                 data-percentage="<?php echo esc_attr($percentage); ?>"
                                                 aria-label="<?php printf(esc_attr__('Credits used: %s%%', 'beepbeep-ai-alt-text-generator'), esc_attr($percentage_display)); ?>"
                                                 role="progressbar"
                                                 aria-valuenow="<?php echo esc_attr($percentage); ?>"
                                                 aria-valuemin="0"
                                                 aria-valuemax="100">
                                                <svg class="bbai-circular-progress-svg" viewBox="0 0 140 140" aria-hidden="true">
                                                    <defs>
                                                        <linearGradient id="<?php echo esc_attr($agency_gradient_id); ?>" x1="0%" y1="0%" x2="100%" y2="100%">
                                                            <stop offset="0%" style="stop-color:#9b5cff;stop-opacity:1" />
                                                            <stop offset="100%" style="stop-color:#7c3aed;stop-opacity:1" />
                                                        </linearGradient>
                                                    </defs>
                                                    <!-- Background circle -->
                                                    <circle 
                                                    cx="70" 
                                                    cy="70" 
                                                        r="<?php echo esc_attr($agency_radius); ?>" 
                                                    fill="none"
                                                        stroke="#f3f4f6" 
                                                        stroke-width="8" 
                                                        class="bbai-circular-progress-bg" />
                                                    <!-- Progress circle -->
                                                    <circle 
                                                        cx="70" 
                                                        cy="70" 
                                                        r="<?php echo esc_attr($agency_radius); ?>" 
                                                        fill="none"
                                                        stroke="url(#<?php echo esc_attr($agency_gradient_id); ?>)"
                                                        stroke-width="8"
                                                    stroke-linecap="round"
                                                        stroke-dasharray="<?php echo esc_attr($agency_circumference); ?>"
                                                        stroke-dashoffset="<?php echo esc_attr($agency_stroke_dashoffset); ?>"
                                                        class="bbai-circular-progress-bar"
                                                        data-circumference="<?php echo esc_attr($agency_circumference); ?>"
                                                        data-offset="<?php echo esc_attr($agency_stroke_dashoffset); ?>"
                                                        transform="rotate(-90 70 70)" />
                                                </svg>
                                                <div class="bbai-circular-progress-text">
                                                    <div class="bbai-circular-progress-percent bbai-number-counting"><?php echo esc_html($percentage_display); ?>%</div>
                                                    <div class="bbai-circular-progress-label"><?php esc_html_e('Credits Used', 'beepbeep-ai-alt-text-generator'); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php else : ?>
                                <!-- Standard vertical layout -->
                                <h3 class="bbai-usage-card-title"><?php esc_html_e('Alt Text Generated This Month', 'beepbeep-ai-alt-text-generator'); ?></h3>
                                <div class="bbai-usage-ring-wrapper">
                                    <div class="bbai-circular-progress" data-percentage="<?php echo esc_attr($percentage); ?>">
                                        <svg class="bbai-circular-progress-svg" viewBox="0 0 120 120">
                                            <defs>
                                                <linearGradient id="<?php echo esc_attr($gradient_id); ?>" x1="0%" y1="0%" x2="100%" y2="100%">
                                                    <stop offset="0%" style="stop-color:#9b5cff;stop-opacity:1" />
                                                    <stop offset="100%" style="stop-color:#7c3aed;stop-opacity:1" />
                                                </linearGradient>
                                            </defs>
                                            <!-- Background circle -->
                                            <circle 
                                                cx="60" 
                                                cy="60" 
                                                r="<?php echo esc_attr($radius); ?>" 
                                                fill="none" 
                                                stroke="#f3f4f6" 
                                                stroke-width="12" 
                                                class="bbai-circular-progress-bg" />
                                            <!-- Progress circle -->
                                            <circle 
                                                cx="60" 
                                                cy="60" 
                                                r="<?php echo esc_attr($radius); ?>" 
                                                fill="none"
                                                stroke="url(#<?php echo esc_attr($gradient_id); ?>)"
                                                stroke-width="12"
                                                stroke-linecap="round"
                                                stroke-dasharray="<?php echo esc_attr($circumference); ?>"
                                                stroke-dashoffset="<?php echo esc_attr($stroke_dashoffset); ?>"
                                                class="bbai-circular-progress-bar"
                                                data-circumference="<?php echo esc_attr($circumference); ?>"
                                                data-offset="<?php echo esc_attr($stroke_dashoffset); ?>" />
                                        </svg>
                                        <div class="bbai-circular-progress-text">
                                            <div class="bbai-circular-progress-percent"><?php echo esc_html($percentage_display); ?>%</div>
                                            <div class="bbai-circular-progress-label"><?php esc_html_e('credits used', 'beepbeep-ai-alt-text-generator'); ?></div>
                                        </div>
                                    </div>
                                    <button type="button" class="bbai-usage-tooltip" aria-label="<?php esc_attr_e('How quotas work', 'beepbeep-ai-alt-text-generator'); ?>" title="<?php esc_attr_e('Your monthly quota resets on the first of each month. Upgrade to Pro for 1,000 generations per month.', 'beepbeep-ai-alt-text-generator'); ?>">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                            <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                            <path d="M8 5V5.01M8 11H8.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                        </svg>
                                    </button>
                                </div>
                                <div class="bbai-usage-details">
                                    <div class="bbai-usage-text">
                                        <strong class="bbai-number-counting"><?php echo esc_html($usage_stats['used']); ?></strong> / <strong class="bbai-number-counting"><?php echo esc_html($usage_stats['limit']); ?></strong>
                                    </div>
                                    <div class="bbai-usage-microcopy">
                                        <?php 
                                        $reset_date = $usage_stats['reset_date'] ?? '';
                                        if (!empty($reset_date)) {
                                            // Format as "resets MONTH DAY, YEAR"
                                            $reset_timestamp = strtotime($reset_date);
                                            if ($reset_timestamp !== false) {
                                                $formatted_date = date_i18n('F j, Y', $reset_timestamp);
                                                printf(
                                                    esc_html__('Resets %s', 'beepbeep-ai-alt-text-generator'),
                                                    esc_html($formatted_date)
                                                );
                                            } else {
                                                printf(
                                                    esc_html__('Resets %s', 'beepbeep-ai-alt-text-generator'),
                                                    esc_html($reset_date)
                                                );
                                            }
                                        } else {
                                            esc_html_e('Resets monthly', 'beepbeep-ai-alt-text-generator');
                                        }
                                        ?>
                                    </div>
                                    <?php 
                                    $plan_slug = $usage_stats['plan'] ?? 'free';
                                    $billing_portal = Usage_Tracker::get_billing_portal_url();
                                    $is_pro = ($plan_slug === 'pro' || $plan_slug === 'agency');
                                    ?>
                                    <?php if (!$is_pro) : ?>
                                    <a href="#" class="bbai-btn bbai-btn-primary bbai-usage-upgrade-link" data-action="show-upgrade-modal">
                                            <?php esc_html_e('Upgrade for 1,000 generations monthly', 'beepbeep-ai-alt-text-generator'); ?> →
                                    </a>
                                        <a href="#" class="bbai-usage-billing-link" data-action="open-billing-portal">
                                            <?php esc_html_e('Manage billing & invoices', 'beepbeep-ai-alt-text-generator'); ?> →
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Premium Upsell Card -->
                            <?php if (!$is_agency) : ?>
                            <div class="bbai-premium-card bbai-upsell-card">
                                <h3 class="bbai-upsell-title"><?php esc_html_e('Upgrade to Pro — Unlock 1,000 AI Generations Monthly', 'beepbeep-ai-alt-text-generator'); ?></h3>
                                <ul class="bbai-upsell-features">
                                    <li>
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                            <circle cx="10" cy="10" r="10" fill="#0EAD4B"/>
                                            <path d="M6 10l2.5 2.5L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <?php esc_html_e('1,000 image generations per month', 'beepbeep-ai-alt-text-generator'); ?>
                                    </li>
                                    <li>
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                            <circle cx="10" cy="10" r="10" fill="#0EAD4B"/>
                                            <path d="M6 10l2.5 2.5L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <?php esc_html_e('Priority queue processing', 'beepbeep-ai-alt-text-generator'); ?>
                                    </li>
                                    <li>
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                            <circle cx="10" cy="10" r="10" fill="#0EAD4B"/>
                                            <path d="M6 10l2.5 2.5L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <?php esc_html_e('Bulk optimisation for large libraries', 'beepbeep-ai-alt-text-generator'); ?>
                                    </li>
                                    <li>
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                            <circle cx="10" cy="10" r="10" fill="#0EAD4B"/>
                                            <path d="M6 10l2.5 2.5L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <?php esc_html_e('Multilingual AI alt text', 'beepbeep-ai-alt-text-generator'); ?>
                                    </li>
                                    <li>
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                            <circle cx="10" cy="10" r="10" fill="#0EAD4B"/>
                                            <path d="M6 10l2.5 2.5L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <?php esc_html_e('Faster & more descriptive alt text from improved Vision models', 'beepbeep-ai-alt-text-generator'); ?>
                                    </li>
                                </ul>
                                <button type="button" class="bbai-upsell-cta bbai-upsell-cta--large bbai-cta-glow-green" data-action="show-upgrade-modal">
                                    <?php esc_html_e('Pro or Agency', 'beepbeep-ai-alt-text-generator'); ?>
                                </button>
                                <p class="bbai-upsell-microcopy">
                                    <?php esc_html_e('Save 15+ hours/month with automated SEO alt generation.', 'beepbeep-ai-alt-text-generator'); ?>
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Stats Cards Row -->
                        <?php
                        $alt_texts_generated = $usage_stats['used'] ?? 0;
                        $minutes_per_alt_text = 2.5;
                        $hours_saved = round(($alt_texts_generated * $minutes_per_alt_text) / 60, 1);
                        $total_images = $stats['total'] ?? 0;
                        $optimized = $stats['with_alt'] ?? 0;
                        $remaining_images = $stats['missing'] ?? 0;
                        $coverage_percent = $total_images > 0 ? round(($optimized / $total_images) * 100) : 0;
                        ?>
                        <div class="bbai-premium-metrics-grid">
                            <!-- Time Saved Card -->
                            <div class="bbai-premium-card bbai-metric-card">
                                <div class="bbai-metric-icon">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none"/>
                                        <path d="M12 6V12L16 14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                </div>
                                <div class="bbai-metric-value bbai-number-counting"><?php echo esc_html($hours_saved); ?> hrs</div>
                                <div class="bbai-metric-label"><?php esc_html_e('TIME SAVED', 'beepbeep-ai-alt-text-generator'); ?></div>
                                <div class="bbai-metric-description"><?php esc_html_e('vs manual optimisation', 'beepbeep-ai-alt-text-generator'); ?></div>
                            </div>
                            
                            <!-- Images Optimized Card -->
                            <div class="bbai-premium-card bbai-metric-card">
                                <div class="bbai-metric-icon">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                                        <path d="M9 11l3 3L22 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                                <div class="bbai-metric-value bbai-number-counting"><?php echo esc_html($optimized); ?></div>
                                <div class="bbai-metric-label"><?php esc_html_e('IMAGES OPTIMIZED', 'beepbeep-ai-alt-text-generator'); ?></div>
                                <div class="bbai-metric-description"><?php esc_html_e('with generated alt text', 'beepbeep-ai-alt-text-generator'); ?></div>
                            </div>
                            
                            <!-- Estimated SEO Impact Card -->
                            <div class="bbai-premium-card bbai-metric-card">
                                <div class="bbai-metric-icon">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                                        <path d="M2 12L12 2L22 12M12 8V22" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                                <div class="bbai-metric-value bbai-number-counting"><?php echo esc_html($coverage_percent); ?>%</div>
                                <div class="bbai-metric-label"><?php esc_html_e('ESTIMATED SEO IMPACT', 'beepbeep-ai-alt-text-generator'); ?></div>
                            </div>
                        </div>
                        
                        <!-- Site-Wide Licensing Notice -->
                        <div class="bbai-premium-card bbai-info-notice">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                <circle cx="10" cy="10" r="9" stroke="#0ea5e9" stroke-width="1.5" fill="none"/>
                                <path d="M10 6V10M10 14H10.01" stroke="#0ea5e9" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <span>
                                <?php 
                                $site_name = trim(get_bloginfo('name'));
                                $site_label = $site_name !== '' ? $site_name : __('this WordPress site', 'beepbeep-ai-alt-text-generator');
                                printf(
                                    esc_html__('Monthly quota shared across all users on %s. Upgrade to Pro for 1,000 generations per month.', 'beepbeep-ai-alt-text-generator'),
                                    '<strong>' . esc_html($site_label) . '</strong>'
                                );
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
                                        <button type="button" class="bbai-optimization-cta bbai-optimization-cta--primary <?php echo esc_attr((!$can_generate) ? 'bbai-optimization-cta--locked' : ''); ?>" data-action="generate-missing" <?php echo (!$can_generate) ? 'disabled title="' . esc_attr__('Unlock 1,000 alt text generations with Pro →', 'beepbeep-ai-alt-text-generator') . '"' : 'data-bbai-tooltip="' . esc_attr__('Automatically generate alt text for all images that don\'t have any. Processes in the background without slowing down your site.', 'beepbeep-ai-alt-text-generator') . '" data-bbai-tooltip-position="bottom"'; ?>>
                                            <?php if (!$can_generate) : ?>
                                                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" class="bbai-btn-icon">
                                                    <path d="M12 6V4a4 4 0 00-8 0v2M4 6h8l1 8H3L4 6z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                                <span><?php esc_html_e('Generate Missing Alt Text', 'beepbeep-ai-alt-text-generator'); ?></span>
                                            <?php else : ?>
                                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="bbai-btn-icon">
                                                    <rect x="2" y="2" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                                    <path d="M6 6H10M6 10H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                </svg>
                                                <span><?php esc_html_e('Generate Missing Alt Text', 'beepbeep-ai-alt-text-generator'); ?></span>
                                            <?php endif; ?>
                                        </button>
                                        <button type="button" class="bbai-optimization-cta bbai-optimization-cta--secondary bbai-cta-glow-blue <?php echo esc_attr((!$can_generate) ? 'bbai-optimization-cta--locked' : ''); ?>" data-action="regenerate-all" <?php echo (!$can_generate) ? 'disabled title="' . esc_attr__('Unlock 1,000 alt text generations with Pro →', 'beepbeep-ai-alt-text-generator') . '"' : 'data-bbai-tooltip="' . esc_attr__('Regenerate alt text for ALL images, even those that already have it. Useful after changing your tone/style settings or brand guidelines.', 'beepbeep-ai-alt-text-generator') . '" data-bbai-tooltip-position="bottom"'; ?>>
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="bbai-btn-icon">
                                                <path d="M8 2L10 6L14 8L10 10L8 14L6 10L2 8L6 6L8 2Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                                <circle cx="8" cy="8" r="2" fill="currentColor"/>
                                            </svg>
                                            <span><?php esc_html_e('Re-optimize All Alt Text', 'beepbeep-ai-alt-text-generator'); ?></span>
                                        </button>
                                    </div>
                                </div>
                            <?php else : ?>
                                <div class="bbai-optimization-empty">
                                    <p><?php esc_html_e('Upload images to your Media Library and generate SEO-optimized alt text automatically. Every image gets WCAG-compliant descriptions that boost Google Images rankings.', 'beepbeep-ai-alt-text-generator'); ?></p>
                                    <a href="<?php echo esc_url(admin_url('upload.php')); ?>" class="bbai-btn bbai-btn-primary">
                                        <?php esc_html_e('Go to Media Library', 'beepbeep-ai-alt-text-generator'); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
<?php endif; ?>
