<?php
/**
 * Bottom Upsell CTA - High Conversion Component
 *
 * Displays a consistent, conversion-optimized upgrade CTA across all tabs.
 * Redesigned for maximum clarity, trust, and conversion.
 *
 * Required variables (must be set before including):
 * - $is_free (bool): Whether user is on free plan
 * - $is_growth (bool): Whether user is on growth plan
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

// Only show for Free or Growth users
$show_upsell = ($is_free || $is_growth);
?>
    <!-- Bottom Upsell CTA - Full Width Upgrade Growth Card -->
    <article class="bbai-bottom-upsell-cta bbai-mt-8" role="region" aria-labelledby="upgrade-headline">
        <?php if ($is_free || $is_growth) : ?>
            <?php if (!$is_agency) : ?>
            <div class="bbai-upgrade-growth-card" style="width: 100%; max-width: 100%;">
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
                <h3 id="upgrade-headline" class="bbai-card-title"><?php esc_html_e('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'); ?></h3>

                <!-- Subtitle -->
                <p class="bbai-card-subtitle"><?php esc_html_e('Automate all alt text and scale hours away every month.', 'beepbeep-ai-alt-text-generator'); ?></p>

                <!-- Benefit List -->
                <ul class="bbai-benefits-list">
                    <li class="bbai-benefit-item">
                        <div class="bbai-benefit-icon">
                            <svg fill="none" viewBox="0 0 16 16" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M13 4L6 11L3 8" />
                            </svg>
                        </div>
                        <span class="bbai-benefit-text"><?php esc_html_e('1,000 alt texts per month', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </li>
                    <li class="bbai-benefit-item">
                        <div class="bbai-benefit-icon">
                            <svg fill="none" viewBox="0 0 16 16" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M13 4L6 11L3 8" />
                            </svg>
                        </div>
                        <span class="bbai-benefit-text"><?php esc_html_e('Bulk Processing for entire image libraries', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </li>
                    <li class="bbai-benefit-item">
                        <div class="bbai-benefit-icon">
                            <svg fill="none" viewBox="0 0 16 16" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M13 4L6 11L3 8" />
                            </svg>
                        </div>
                        <span class="bbai-benefit-text"><?php esc_html_e('Priority queue for 3x faster results', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </li>
                    <li class="bbai-benefit-item">
                        <div class="bbai-benefit-icon">
                            <svg fill="none" viewBox="0 0 16 16" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M13 4L6 11L3 8" />
                            </svg>
                        </div>
                        <span class="bbai-benefit-text"><?php esc_html_e('Multilingual support for global SEO reach', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </li>
                </ul>

                <!-- CTA Section -->
                <div class="bbai-cta-section">
                    <!-- Primary CTA Button -->
                    <button
                        type="button"
                        class="bbai-cta-primary"
                        data-action="show-upgrade-modal"
                    >
                        <?php esc_html_e('Start 14 day free trial', 'beepbeep-ai-alt-text-generator'); ?>
                    </button>

                    <!-- Microcopy -->
                    <p class="bbai-cta-microcopy"><?php esc_html_e('Upgrade to Growth, Cancel anytime +', 'beepbeep-ai-alt-text-generator'); ?></p>

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
        <?php endif; ?>
    </article>

<!-- Contact Us Link (shown on all pages) -->
<div class="bbai-footer-contact" style="margin-top: <?php echo $show_upsell ? '24px' : '0'; ?>; padding-top: 24px; border-top: 1px solid #e5e7eb; text-align: center;">
    <a href="#" class="bbai-contact-link" data-action="open-contact-modal" style="color: #6b7280; text-decoration: none; font-size: 14px; transition: color 0.2s;">
        <?php esc_html_e('Contact Us', 'beepbeep-ai-alt-text-generator'); ?>
    </a>
</div>
