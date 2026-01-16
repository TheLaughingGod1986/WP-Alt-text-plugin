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
if ($is_free || $is_growth) :
?>
    <!-- Bottom Upsell CTA -->
    <article class="bbai-bottom-upsell-cta bbai-mt-8" role="region" aria-labelledby="upgrade-headline">
        <?php if ($is_free) : ?>
            <div class="bbai-upsell-card">
                <div class="bbai-upsell-card-content">
                    <div class="bbai-upsell-card-left">
                        <!-- Badge - Tier Identity -->
                        <span class="bbai-upsell-badge <?php echo esc_attr( $is_critical ? 'bbai-upsell-badge--critical' : ($is_urgent ? 'bbai-upsell-badge--urgent' : '') ); ?>">
                            <?php
                            if ($is_critical) {
                                esc_html_e('Last Chance', 'beepbeep-ai-alt-text-generator');
                            } elseif ($is_urgent) {
                                esc_html_e('Running Low', 'beepbeep-ai-alt-text-generator');
                            } else {
                                esc_html_e('Growth', 'beepbeep-ai-alt-text-generator');
                            }
                            ?>
                        </span>

                        <!-- Headline - Problem-Aware -->
                        <h3 id="upgrade-headline" class="bbai-upsell-title">
                            <?php
                            if ($is_critical) {
                                printf(
                                    /* translators: %d: number of credits remaining */
                                    esc_html__('Only %d Credits Left — Upgrade Now', 'beepbeep-ai-alt-text-generator'),
                                    $remaining
                                );
                            } elseif ($is_urgent) {
                                esc_html_e('Stop Losing SEO Traffic to Missing Alt Text', 'beepbeep-ai-alt-text-generator');
                            } else {
                                esc_html_e('Stop Losing SEO Traffic to Missing Alt Text', 'beepbeep-ai-alt-text-generator');
                            }
                            ?>
                        </h3>

                        <!-- Subheadline - Value Bridge -->
                        <p class="bbai-upsell-subtitle">
                            <?php
                            if ($is_critical) {
                                esc_html_e('Don\'t let your optimization stop. Growth gives you 20x more credits to keep your images SEO-ready.', 'beepbeep-ai-alt-text-generator');
                            } elseif ($is_urgent) {
                                printf(
                                    /* translators: %d: number of credits remaining */
                                    esc_html__('You have %d credits left. Growth gives you 20x more credits and bulk processing to optimize your entire library in minutes.', 'beepbeep-ai-alt-text-generator'),
                                    $remaining
                                );
                            } else {
                                esc_html_e('Growth gives you 20x more credits and bulk processing to optimize your entire media library in minutes—not months.', 'beepbeep-ai-alt-text-generator');
                            }
                            ?>
                        </p>

                        <!-- Features - Outcome + Benefit -->
                        <ul class="bbai-upsell-features" aria-label="<?php esc_attr_e('Growth plan features', 'beepbeep-ai-alt-text-generator'); ?>">
                            <li>
                                <svg width="20" height="20" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('1,000 credits/month — Optimize hundreds of images automatically', 'beepbeep-ai-alt-text-generator'); ?></span>
                            </li>
                            <li>
                                <svg width="20" height="20" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('Bulk processing — Fix your entire library in one click', 'beepbeep-ai-alt-text-generator'); ?></span>
                            </li>
                            <li>
                                <svg width="20" height="20" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('Priority queue — Skip the line, generate 3x faster', 'beepbeep-ai-alt-text-generator'); ?></span>
                            </li>
                        </ul>
                    </div>

                    <div class="bbai-upsell-card-right">
                        <div class="bbai-upsell-cta-wrapper">
                            <!-- Primary CTA -->
                            <button type="button" class="bbai-upsell-cta bbai-upsell-cta--prominent" data-action="show-upgrade-modal">
                                <span><?php esc_html_e('Start Free Trial', 'beepbeep-ai-alt-text-generator'); ?></span>
                                <svg width="20" height="20" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="M6 12L10 8L6 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>

                            <!-- Risk Reduction Microcopy -->
                            <p class="bbai-upsell-cta-hint">
                                <?php esc_html_e('14 days free · No credit card · Cancel anytime', 'beepbeep-ai-alt-text-generator'); ?>
                            </p>

                            <!-- Secondary Link -->
                            <a href="#" class="bbai-upsell-secondary-link" data-action="show-upgrade-modal" onclick="if(typeof alttextaiShowModal==='function'){alttextaiShowModal();}else if(typeof window.alttextaiShowModal==='function'){window.alttextaiShowModal();}return false;">
                                <?php esc_html_e('Compare Plans', 'beepbeep-ai-alt-text-generator'); ?>
                                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="M6 12L10 8L6 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif ($is_growth) : ?>
            <!-- Agency Upsell for Growth Users -->
            <div class="bbai-upsell-card bbai-upsell-card--agency">
                <div class="bbai-upsell-card-content">
                    <div class="bbai-upsell-card-left">
                        <span class="bbai-upsell-badge"><?php esc_html_e('Agency', 'beepbeep-ai-alt-text-generator'); ?></span>

                        <h3 id="upgrade-headline" class="bbai-upsell-title">
                            <?php esc_html_e('Scale Your SEO Services with Agency', 'beepbeep-ai-alt-text-generator'); ?>
                        </h3>

                        <p class="bbai-upsell-subtitle">
                            <?php esc_html_e('Manage multiple client sites from one dashboard. Track usage per site for easy invoicing and deliver professional SEO reports.', 'beepbeep-ai-alt-text-generator'); ?>
                        </p>

                        <ul class="bbai-upsell-features" aria-label="<?php esc_attr_e('Agency plan features', 'beepbeep-ai-alt-text-generator'); ?>">
                            <li>
                                <svg width="20" height="20" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('Multi-site dashboard — See all clients in one view', 'beepbeep-ai-alt-text-generator'); ?></span>
                            </li>
                            <li>
                                <svg width="20" height="20" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('Per-site usage tracking — Invoice clients accurately', 'beepbeep-ai-alt-text-generator'); ?></span>
                            </li>
                            <li>
                                <svg width="20" height="20" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('Branded SEO reports — Deliver professional results', 'beepbeep-ai-alt-text-generator'); ?></span>
                            </li>
                        </ul>
                    </div>

                    <div class="bbai-upsell-card-right">
                        <div class="bbai-upsell-cta-wrapper">
                            <button type="button" class="bbai-upsell-cta bbai-upsell-cta--prominent" data-action="show-upgrade-modal">
                                <span><?php esc_html_e('Explore Agency', 'beepbeep-ai-alt-text-generator'); ?></span>
                                <svg width="20" height="20" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="M6 12L10 8L6 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>

                            <p class="bbai-upsell-cta-hint">
                                <?php esc_html_e('Talk to sales for volume pricing', 'beepbeep-ai-alt-text-generator'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </article>
<?php endif; ?>
