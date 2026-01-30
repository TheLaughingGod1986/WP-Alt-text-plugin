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
 * - $is_free (bool): Whether user is on free plan
 * - $is_growth (bool): Whether user is on growth/pro plan
 * - $is_agency (bool): Whether user is on agency plan
 *
 * Optional variables:
 * - $usage_stats (array): Array with 'used' and 'limit' keys for urgency messaging
 */
if (!defined('ABSPATH')) {
    exit;
}

// Default values if not set
$is_free = isset($is_free) ? $is_free : true;
$is_growth = isset($is_growth) ? $is_growth : false;
$is_agency = isset($is_agency) ? $is_agency : false;

// Get usage stats for urgency messaging
$usage_stats = isset($usage_stats) && is_array($usage_stats) ? $usage_stats : [];
$usage_percent = isset($usage_stats['limit']) && $usage_stats['limit'] > 0
    ? round(($usage_stats['used'] / $usage_stats['limit']) * 100)
    : 0;
$is_urgent = $usage_percent >= 80;
$is_critical = $usage_percent >= 95;
$remaining = isset($usage_stats['limit'], $usage_stats['used'])
    ? max(0, $usage_stats['limit'] - $usage_stats['used'])
    : 0;

// Determine what to show
$show_upsell = !$is_agency;
?>

<article class="bbai-bottom-upsell-cta bbai-mt-8" role="region" aria-labelledby="upgrade-headline">
    <?php if ($is_free) : ?>
        <!-- FREE USERS: Upgrade to Growth -->
        <div class="bbai-upgrade-growth-card" style="width: 100%; max-width: 100%;">
            <!-- Badge -->
            <div class="flex">
                <?php
                $badge_text = esc_html__('GROWTH', 'opptiai-alt');
                $badge_variant = 'growth';
                $badge_class = '';
                $badge_partial = plugin_dir_path( BBAI_PLUGIN_FILE ) . 'admin/partials/badge.php';
                if (file_exists($badge_partial)) {
                    include $badge_partial;
                }
                ?>
            </div>

            <!-- Title -->
            <h3 id="upgrade-headline" class="bbai-card-title"><?php esc_html_e('Upgrade to Growth', 'opptiai-alt'); ?></h3>

            <!-- Subtitle -->
            <p class="bbai-card-subtitle"><?php esc_html_e('Automate alt text generation and scale image optimisation each month.', 'opptiai-alt'); ?></p>

            <!-- Benefit List -->
            <ul class="bbai-benefits-list">
                <li class="bbai-benefit-item">
                    <div class="bbai-benefit-icon">
                        <svg fill="none" viewBox="0 0 16 16" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M13 4L6 11L3 8" />
                        </svg>
                    </div>
                    <span class="bbai-benefit-text"><?php esc_html_e('1,000 AI alt texts per month', 'opptiai-alt'); ?></span>
                </li>
                <li class="bbai-benefit-item">
                    <div class="bbai-benefit-icon">
                        <svg fill="none" viewBox="0 0 16 16" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M13 4L6 11L3 8" />
                        </svg>
                    </div>
                    <span class="bbai-benefit-text"><?php esc_html_e('Bulk processing for the media library', 'opptiai-alt'); ?></span>
                </li>
                <li class="bbai-benefit-item">
                    <div class="bbai-benefit-icon">
                        <svg fill="none" viewBox="0 0 16 16" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M13 4L6 11L3 8" />
                        </svg>
                    </div>
                    <span class="bbai-benefit-text"><?php esc_html_e('Priority queue for faster results', 'opptiai-alt'); ?></span>
                </li>
                <li class="bbai-benefit-item">
                    <div class="bbai-benefit-icon">
                        <svg fill="none" viewBox="0 0 16 16" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M13 4L6 11L3 8" />
                        </svg>
                    </div>
                    <span class="bbai-benefit-text"><?php esc_html_e('Multilingual support for global SEO', 'opptiai-alt'); ?></span>
                </li>
            </ul>

            <!-- CTA Section -->
            <div class="bbai-cta-section">
                <button type="button" class="bbai-cta-primary" data-action="show-upgrade-modal" aria-label="<?php esc_attr_e('Upgrade to Growth', 'opptiai-alt'); ?>">
                    <?php esc_html_e('Upgrade to Growth', 'opptiai-alt'); ?>
                </button>
                <p class="bbai-cta-microcopy"><?php esc_html_e('Includes 1,000 AI alt texts per month. Cancel anytime.', 'opptiai-alt'); ?></p>
                <a href="#" class="bbai-compare-link" data-action="show-upgrade-modal">
                    <?php esc_html_e('Compare plans', 'opptiai-alt'); ?>
                </a>
            </div>
        </div>

    <?php elseif ($is_growth) : ?>
        <!-- GROWTH/PRO USERS: Upgrade to Agency -->
        <div class="bbai-upgrade-growth-card bbai-upgrade-agency-card" style="width: 100%; max-width: 100%;">
            <!-- Badge -->
            <div class="flex">
                <?php
                $badge_text = esc_html__('AGENCY', 'opptiai-alt');
                $badge_variant = 'agency';
                $badge_class = '';
                $badge_partial = plugin_dir_path( BBAI_PLUGIN_FILE ) . 'admin/partials/badge.php';
                if (file_exists($badge_partial)) {
                    include $badge_partial;
                }
                ?>
            </div>

            <!-- Title -->
            <h3 id="upgrade-headline" class="bbai-card-title"><?php esc_html_e('Scale with Agency', 'opptiai-alt'); ?></h3>

            <!-- Subtitle -->
            <p class="bbai-card-subtitle"><?php esc_html_e('Unlimited potential for agencies and high-volume sites.', 'opptiai-alt'); ?></p>

            <!-- Benefit List -->
            <ul class="bbai-benefits-list">
                <li class="bbai-benefit-item">
                    <div class="bbai-benefit-icon">
                        <svg fill="none" viewBox="0 0 16 16" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M13 4L6 11L3 8" />
                        </svg>
                    </div>
                    <span class="bbai-benefit-text"><?php esc_html_e('5,000 alt texts per month', 'opptiai-alt'); ?></span>
                </li>
                <li class="bbai-benefit-item">
                    <div class="bbai-benefit-icon">
                        <svg fill="none" viewBox="0 0 16 16" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M13 4L6 11L3 8" />
                        </svg>
                    </div>
                    <span class="bbai-benefit-text"><?php esc_html_e('Multi-site license for unlimited WordPress sites', 'opptiai-alt'); ?></span>
                </li>
                <li class="bbai-benefit-item">
                    <div class="bbai-benefit-icon">
                        <svg fill="none" viewBox="0 0 16 16" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M13 4L6 11L3 8" />
                        </svg>
                    </div>
                    <span class="bbai-benefit-text"><?php esc_html_e('Priority support with dedicated account manager', 'opptiai-alt'); ?></span>
                </li>
                <li class="bbai-benefit-item">
                    <div class="bbai-benefit-icon">
                        <svg fill="none" viewBox="0 0 16 16" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M13 4L6 11L3 8" />
                        </svg>
                    </div>
                    <span class="bbai-benefit-text"><?php esc_html_e('Custom brand voice & style guidelines', 'opptiai-alt'); ?></span>
                </li>
            </ul>

            <!-- CTA Section -->
            <div class="bbai-cta-section">
                <button type="button" class="bbai-cta-primary" data-action="show-upgrade-modal">
                    <?php esc_html_e('Upgrade to Agency', 'opptiai-alt'); ?>
                </button>
                <p class="bbai-cta-microcopy"><?php esc_html_e('Perfect for agencies managing multiple client sites.', 'opptiai-alt'); ?></p>
                <a href="#" class="bbai-compare-link" data-action="show-upgrade-modal">
                    <?php esc_html_e('Compare plans', 'opptiai-alt'); ?>
                </a>
            </div>
        </div>

    <?php elseif ($is_agency) : ?>
        <!-- AGENCY USERS: Thank you + support links -->
        <div class="bbai-agency-thank-you-card" style="width: 100%; max-width: 100%;">
            <div class="bbai-thank-you-content">
                <div class="bbai-thank-you-icon">
                    <svg width="48" height="48" viewBox="0 0 48 48" fill="none">
                        <circle cx="24" cy="24" r="22" stroke="currentColor" stroke-width="2" fill="none" opacity="0.2"/>
                        <path d="M16 24L22 30L32 18" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="bbai-thank-you-text">
                    <h3 class="bbai-card-title"><?php esc_html_e('Thank you for being an Agency member!', 'opptiai-alt'); ?></h3>
                    <p class="bbai-card-subtitle"><?php esc_html_e('You have access to all features. We appreciate your support.', 'opptiai-alt'); ?></p>
                </div>
            </div>
            <div class="bbai-agency-links">
                <?php
                $billing_portal_url = '';
                if (class_exists('BeepBeepAI\AltTextGenerator\Usage_Tracker')) {
                    $billing_portal_url = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_billing_portal_url();
                }
                ?>
                <?php if (!empty($billing_portal_url)) : ?>
                <a href="<?php echo esc_url($billing_portal_url); ?>" class="bbai-agency-link" target="_blank" rel="noopener">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="2" y="3" width="12" height="10" rx="2"/>
                        <path d="M2 6h12"/>
                    </svg>
                    <?php esc_html_e('Manage Subscription', 'opptiai-alt'); ?>
                </a>
                <?php endif; ?>
                <a href="#" class="bbai-agency-link" data-action="open-contact-modal">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M14 10.5V12a1.5 1.5 0 01-1.5 1.5h-9A1.5 1.5 0 012 12V4a1.5 1.5 0 011.5-1.5h9A1.5 1.5 0 0114 4v1.5"/>
                        <path d="M8 9.5l6-4.5M8 9.5l-6-4.5"/>
                    </svg>
                    <?php esc_html_e('Priority Support', 'opptiai-alt'); ?>
                </a>
                <a href="https://beepbeepai.com/docs" class="bbai-agency-link" target="_blank" rel="noopener">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M4 2h8a2 2 0 012 2v10l-3-2H4a2 2 0 01-2-2V4a2 2 0 012-2z"/>
                        <path d="M5 6h6M5 9h4"/>
                    </svg>
                    <?php esc_html_e('Documentation', 'opptiai-alt'); ?>
                </a>
            </div>
        </div>
    <?php endif; ?>
</article>

<!-- Contact Us Link (shown for non-agency users) -->
<?php if (!$is_agency) : ?>
<div class="bbai-footer-contact" style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #e5e7eb; text-align: center;">
    <a href="#" class="bbai-contact-link" data-action="open-contact-modal" style="color: #6b7280; text-decoration: none; font-size: 14px; transition: color 0.2s;">
        <?php esc_html_e('Contact Us', 'opptiai-alt'); ?>
    </a>
</div>
<?php endif; ?>
