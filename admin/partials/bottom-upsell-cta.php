<?php
/**
 * Bottom Upsell CTA - Plan-Aware Component
 *
 * Displays different CTAs based on user's current plan:
 * - Free users: Upgrade to Growth CTA
 * - Growth users: Upgrade to Agency CTA
 * - Agency users: Thank you message with support links
 *
 * Required variables (must be set before including):
 * - $bbai_is_free (bool): Whether user is on free plan
 * - $bbai_is_growth (bool): Whether user is on growth/pro plan
 * - $bbai_is_agency (bool): Whether user is on agency plan
 *
 * Optional variables:
 * - $bbai_usage_stats (array): Array with 'used' and 'limit' keys for urgency messaging
 */
if (!defined('ABSPATH')) {
    exit;
}

// Default values if not set
$bbai_is_free = isset($bbai_is_free) ? $bbai_is_free : true;
$bbai_is_growth = isset($bbai_is_growth) ? $bbai_is_growth : false;
$bbai_is_agency = isset($bbai_is_agency) ? $bbai_is_agency : false;

// Get usage stats for urgency messaging
$bbai_usage_stats = isset($bbai_usage_stats) && is_array($bbai_usage_stats) ? $bbai_usage_stats : [];
$bbai_usage_percent = isset($bbai_usage_stats['limit']) && $bbai_usage_stats['limit'] > 0
    ? round(($bbai_usage_stats['used'] / $bbai_usage_stats['limit']) * 100)
    : 0;
$bbai_is_urgent = $bbai_usage_percent >= 80;
$bbai_is_critical = $bbai_usage_percent >= 95;
$bbai_remaining = isset($bbai_usage_stats['limit'], $bbai_usage_stats['used'])
    ? max(0, $bbai_usage_stats['limit'] - $bbai_usage_stats['used'])
    : 0;

// Determine what to show
$bbai_show_upsell = !$bbai_is_agency;
$bbai_headline_override = isset($bbai_bottom_upsell_headline) && is_string($bbai_bottom_upsell_headline) && $bbai_bottom_upsell_headline !== ''
    ? $bbai_bottom_upsell_headline
    : '';
$bbai_bottom_upsell_class = !empty($bbai_bottom_upsell_compact) ? ' bbai-bottom-upsell-cta--compact' : '';
?>

<article class="bbai-bottom-upsell-cta bbai-mt-8<?php echo esc_attr($bbai_bottom_upsell_class); ?>" role="region" aria-labelledby="upgrade-headline">
    <?php if ($bbai_is_free) : ?>
        <!-- FREE USERS: Upgrade to Growth -->
        <?php
        $bbai_upsell_reset_date = $bbai_usage_stats['reset_date'] ?? $bbai_usage_stats['resetDate'] ?? '';
        $bbai_upsell_reset_ts = ! empty($bbai_upsell_reset_date) ? strtotime((string) $bbai_upsell_reset_date) : 0;
        $bbai_upsell_days_left = isset($bbai_usage_stats['days_until_reset']) && is_numeric($bbai_usage_stats['days_until_reset'])
            ? max(0, (int) $bbai_usage_stats['days_until_reset'])
            : ($bbai_upsell_reset_ts > 0 ? max(0, (int) floor(($bbai_upsell_reset_ts - time()) / DAY_IN_SECONDS)) : 0);
        $bbai_growth_capacity = 1000;
        $bbai_current_used = max(0, intval($bbai_usage_stats['used'] ?? 0));
        $bbai_current_limit = max(1, intval($bbai_usage_stats['limit'] ?? 50));
        $bbai_days_to_reset_label = $bbai_upsell_days_left <= 0
            ? __('Today', 'beepbeep-ai-alt-text-generator')
            : sprintf(
                /* translators: %s: days until reset */
                _n('%s day', '%s days', $bbai_upsell_days_left, 'beepbeep-ai-alt-text-generator'),
                number_format_i18n($bbai_upsell_days_left)
            );
        $bbai_current_bar_pct = min(100, max(0, round(($bbai_current_used / $bbai_current_limit) * 100)));
        $bbai_growth_bar_pct = min(100, max(0, round(($bbai_current_used / $bbai_growth_capacity) * 100)));
        ?>
        <div class="bbai-upgrade-card bbai-upgrade-growth-card">
            <div class="bbai-upgrade-card__grid">
                <div class="bbai-upgrade-card__value">
                    <h3 id="upgrade-headline" class="bbai-upgrade-card__title">
                        <?php echo esc_html($bbai_headline_override !== '' ? $bbai_headline_override : __('Upgrade to Growth', 'beepbeep-ai-alt-text-generator')); ?>
                    </h3>
                    <p class="bbai-upgrade-card__description"><?php esc_html_e('Automate AI text generation and scale image optimisation', 'beepbeep-ai-alt-text-generator'); ?></p>

                    <ul class="bbai-upgrade-features bbai-benefits-list" role="list">
                        <li class="bbai-upgrade-features__item bbai-benefit-item">
                            <span class="bbai-upgrade-features__icon bbai-benefit-icon" aria-hidden="true">
                                <svg fill="none" viewBox="0 0 16 16" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 4L6 11L3 8" /></svg>
                            </span>
                            <span class="bbai-benefit-text"><?php esc_html_e('1,000 AI alt texts per month', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </li>
                        <li class="bbai-upgrade-features__item bbai-benefit-item">
                            <span class="bbai-upgrade-features__icon bbai-benefit-icon" aria-hidden="true">
                                <svg fill="none" viewBox="0 0 16 16" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 4L6 11L3 8" /></svg>
                            </span>
                            <span class="bbai-benefit-text"><?php esc_html_e('Bulk processing', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </li>
                        <li class="bbai-upgrade-features__item bbai-benefit-item">
                            <span class="bbai-upgrade-features__icon bbai-benefit-icon" aria-hidden="true">
                                <svg fill="none" viewBox="0 0 16 16" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 4L6 11L3 8" /></svg>
                            </span>
                            <span class="bbai-benefit-text"><?php esc_html_e('Priority queue for faster results', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </li>
                        <li class="bbai-upgrade-features__item bbai-benefit-item">
                            <span class="bbai-upgrade-features__icon bbai-benefit-icon" aria-hidden="true">
                                <svg fill="none" viewBox="0 0 16 16" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 4L6 11L3 8" /></svg>
                            </span>
                            <span class="bbai-benefit-text"><?php esc_html_e('Multilingual support for global SEO', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </li>
                    </ul>

                    <hr class="bbai-upgrade-card__divider" aria-hidden="true">

                    <p class="bbai-upgrade-unlock-context">
                        <?php esc_html_e( 'Unlock 1,000 AI ALT texts per month', 'beepbeep-ai-alt-text-generator' ); ?>
                    </p>
                </div>

                <div class="bbai-upgrade-card__usage">
                    <div class="bbai-upgrade-usage-card bbai-upgrade-stats-box">
                        <div class="bbai-upgrade-usage-card__row">
                            <span class="bbai-upgrade-stat-label"><?php esc_html_e('Current', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <strong class="bbai-upgrade-stat-value"><?php echo esc_html(number_format_i18n($bbai_current_used) . ' / ' . number_format_i18n($bbai_current_limit)); ?></strong>
                        </div>
                        <div class="bbai-upgrade-progress bbai-upgrade-stat-bar" role="progressbar" aria-valuenow="<?php echo esc_attr($bbai_current_bar_pct); ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?php esc_attr_e('Current plan usage', 'beepbeep-ai-alt-text-generator'); ?>">
                            <span class="bbai-upgrade-progress__fill" style="width:<?php echo esc_attr($bbai_current_bar_pct); ?>%"></span>
                        </div>

                        <div class="bbai-upgrade-usage-card__row">
                            <span class="bbai-upgrade-stat-label"><?php esc_html_e('With Growth', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <strong class="bbai-upgrade-stat-value"><?php echo esc_html(number_format_i18n($bbai_growth_capacity) . ' / month'); ?></strong>
                        </div>
                        <div class="bbai-upgrade-progress bbai-upgrade-stat-bar" role="progressbar" aria-valuenow="<?php echo esc_attr($bbai_growth_bar_pct); ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?php esc_attr_e('Growth plan usage', 'beepbeep-ai-alt-text-generator'); ?>">
                            <span class="bbai-upgrade-progress__fill" style="width:<?php echo esc_attr($bbai_growth_bar_pct); ?>%"></span>
                        </div>

                        <div class="bbai-upgrade-usage-card__row bbai-upgrade-stat-row--reset">
                            <span class="bbai-upgrade-stat-label"><?php esc_html_e('Reset', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <strong class="bbai-upgrade-stat-value"><?php echo esc_html($bbai_days_to_reset_label); ?></strong>
                        </div>

                        <button type="button" class="bbai-upgrade-cta bbai-cta-primary" data-action="show-upgrade-modal" aria-label="<?php esc_attr_e('Upgrade to Growth – £12.99/month', 'beepbeep-ai-alt-text-generator'); ?>">
                            <?php esc_html_e('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'); ?> – <?php esc_html_e('£12.99/month', 'beepbeep-ai-alt-text-generator'); ?>
                        </button>
                        <p class="bbai-cta-microcopy"><?php esc_html_e('Cancel anytime', 'beepbeep-ai-alt-text-generator'); ?> · <a href="#" class="bbai-compare-link" data-action="show-upgrade-modal"><?php esc_html_e('Compare plans', 'beepbeep-ai-alt-text-generator'); ?></a></p>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($bbai_is_growth) : ?>
        <!-- GROWTH/PRO USERS: Upgrade to Agency -->
        <div class="bbai-upgrade-growth-card bbai-upgrade-agency-card">
            <!-- Badge -->
            <div class="flex">
                <?php
                $bbai_badge_text = esc_html__('AGENCY', 'beepbeep-ai-alt-text-generator');
                $bbai_badge_variant = 'agency';
                $bbai_badge_class = '';
                $bbai_badge_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/badge.php';
                if (file_exists($bbai_badge_partial)) {
                    include $bbai_badge_partial;
                }
                ?>
            </div>

            <!-- Title -->
            <h3 id="upgrade-headline" class="bbai-card-title">
                <?php echo esc_html($bbai_headline_override !== '' ? $bbai_headline_override : __('Scale with Agency', 'beepbeep-ai-alt-text-generator')); ?>
            </h3>

            <!-- Subtitle -->
            <p class="bbai-card-subtitle"><?php esc_html_e('Unlimited potential for agencies and high-volume sites.', 'beepbeep-ai-alt-text-generator'); ?></p>

            <!-- Benefit List -->
            <ul class="bbai-benefits-list">
                <li class="bbai-benefit-item">
                    <div class="bbai-benefit-icon">
                        <svg fill="none" viewBox="0 0 16 16" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M13 4L6 11L3 8" />
                        </svg>
                    </div>
                    <span class="bbai-benefit-text"><?php esc_html_e('5,000 alt texts per month', 'beepbeep-ai-alt-text-generator'); ?></span>
                </li>
                <li class="bbai-benefit-item">
                    <div class="bbai-benefit-icon">
                        <svg fill="none" viewBox="0 0 16 16" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M13 4L6 11L3 8" />
                        </svg>
                    </div>
                    <span class="bbai-benefit-text"><?php esc_html_e('Multi-site license for unlimited WordPress sites', 'beepbeep-ai-alt-text-generator'); ?></span>
                </li>
                <li class="bbai-benefit-item">
                    <div class="bbai-benefit-icon">
                        <svg fill="none" viewBox="0 0 16 16" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M13 4L6 11L3 8" />
                        </svg>
                    </div>
                    <span class="bbai-benefit-text"><?php esc_html_e('Priority support with dedicated account manager', 'beepbeep-ai-alt-text-generator'); ?></span>
                </li>
                <li class="bbai-benefit-item">
                    <div class="bbai-benefit-icon">
                        <svg fill="none" viewBox="0 0 16 16" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M13 4L6 11L3 8" />
                        </svg>
                    </div>
                    <span class="bbai-benefit-text"><?php esc_html_e('Custom brand voice & style guidelines', 'beepbeep-ai-alt-text-generator'); ?></span>
                </li>
            </ul>

            <!-- CTA Section -->
            <div class="bbai-cta-section">
                <button type="button" class="bbai-cta-primary" data-action="show-upgrade-modal">
                    <?php esc_html_e('Upgrade to Agency', 'beepbeep-ai-alt-text-generator'); ?>
                </button>
                <p class="bbai-cta-microcopy"><?php esc_html_e('Perfect for agencies managing multiple client sites.', 'beepbeep-ai-alt-text-generator'); ?></p>
                <a href="#" class="bbai-compare-link" data-action="show-upgrade-modal">
                    <?php esc_html_e('Compare plans', 'beepbeep-ai-alt-text-generator'); ?>
                </a>
            </div>
        </div>

    <?php elseif ($bbai_is_agency) : ?>
        <!-- AGENCY USERS: Thank you + support links -->
        <div class="bbai-agency-thank-you-card">
            <div class="bbai-thank-you-content">
                <div class="bbai-thank-you-icon">
                    <svg width="48" height="48" viewBox="0 0 48 48" fill="none">
                        <circle cx="24" cy="24" r="22" stroke="currentColor" stroke-width="2" fill="none" opacity="0.2"/>
                        <path d="M16 24L22 30L32 18" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="bbai-thank-you-text">
                    <h3 class="bbai-card-title"><?php esc_html_e('Thank you for being an Agency member!', 'beepbeep-ai-alt-text-generator'); ?></h3>
                    <p class="bbai-card-subtitle"><?php esc_html_e('You have access to all features. We appreciate your support.', 'beepbeep-ai-alt-text-generator'); ?></p>
                </div>
            </div>
            <div class="bbai-agency-links">
                <?php
                $bbai_billing_portal_url = '';
                if (class_exists('BeepBeepAI\AltTextGenerator\Usage_Tracker')) {
                    $bbai_billing_portal_url = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_billing_portal_url();
                }
                ?>
                <?php if (!empty($bbai_billing_portal_url)) : ?>
                <a href="<?php echo esc_url($bbai_billing_portal_url); ?>" class="bbai-agency-link" target="_blank" rel="noopener">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="2" y="3" width="12" height="10" rx="2"/>
                        <path d="M2 6h12"/>
                    </svg>
                    <?php esc_html_e('Manage Subscription', 'beepbeep-ai-alt-text-generator'); ?>
                </a>
                <?php endif; ?>
                <a href="#" class="bbai-agency-link" data-action="open-contact-modal">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M14 10.5V12a1.5 1.5 0 01-1.5 1.5h-9A1.5 1.5 0 012 12V4a1.5 1.5 0 011.5-1.5h9A1.5 1.5 0 0114 4v1.5"/>
                        <path d="M8 9.5l6-4.5M8 9.5l-6-4.5"/>
                    </svg>
                    <?php esc_html_e('Priority Support', 'beepbeep-ai-alt-text-generator'); ?>
                </a>
                <a href="https://beepbeepai.com/docs" class="bbai-agency-link" target="_blank" rel="noopener">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M4 2h8a2 2 0 012 2v10l-3-2H4a2 2 0 01-2-2V4a2 2 0 012-2z"/>
                        <path d="M5 6h6M5 9h4"/>
                    </svg>
                    <?php esc_html_e('Documentation', 'beepbeep-ai-alt-text-generator'); ?>
                </a>
            </div>
        </div>
    <?php endif; ?>
</article>
