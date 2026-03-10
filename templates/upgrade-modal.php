<?php
/**
 * Upgrade Modal Template - Premium SaaS Design
 * Matches new Dashboard, Analytics, Library, Settings design system
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Get current plan from API client
$bbai_current_plan = 'free';
$bbai_usage_data = [];
try {
    if (isset($this->api_client) && is_object($this->api_client) && method_exists($this->api_client, 'get_usage')) {
        $bbai_usage_data = $this->api_client->get_usage();
        if (!is_wp_error($bbai_usage_data) && is_array($bbai_usage_data) && isset($bbai_usage_data['plan'])) {
            $bbai_current_plan = strtolower($bbai_usage_data['plan']);
        }
    }
} catch (Exception $e) {
    // Silently fail, use default free plan
}
// Map 'pro' to 'growth' for consistency
if ($bbai_current_plan === 'pro') {
    $bbai_current_plan = 'growth';
}

// Get price IDs from backend API
$bbai_pro_price_id = $checkout_prices['pro'] ?? '';
$bbai_agency_price_id = $checkout_prices['agency'] ?? '';
$bbai_credits_price_id = $checkout_prices['credits'] ?? '';

// Fallback to hardcoded Stripe links if API price IDs not available
$bbai_stripe_links = [
    'pro' => 'https://buy.stripe.com/dRm28s4rc5Raf0GbY77ss02',
    'agency' => 'https://buy.stripe.com/28E14og9U0wQ19Q4vF7ss01',
    'credits' => 'https://buy.stripe.com/6oU9AUf5Q2EYaKq0fp7ss00'
];

// Currency - Default to GBP, but support detection
$bbai_currency = $bbai_currency ?? ['symbol' => '£', 'code' => 'GBP', 'free' => 0, 'growth' => 12.99, 'pro' => 12.99, 'agency' => 49.99, 'credits' => 19.99];

// Calculate annual prices (2 months free = 10 months of monthly price)
$bbai_growth_monthly = $bbai_currency['growth'] ?? 12.99;
$bbai_growth_annual = round($bbai_growth_monthly * 10, 2);
$bbai_agency_monthly = $bbai_currency['agency'] ?? 49.99;
$bbai_agency_annual = round($bbai_agency_monthly * 10, 2);

// Calculate annual savings (20% = 2 months free)
$bbai_growth_annual_savings = round(($bbai_growth_monthly * 12) - $bbai_growth_annual, 2);
$bbai_agency_annual_savings = round(($bbai_agency_monthly * 12) - $bbai_agency_annual, 2);

// Billing portal URL for current plan management
$bbai_billing_url = admin_url('admin.php?page=bbai-billing');
$bbai_docs_url = 'https://beepbeepai.com/docs';

$bbai_usage_limit = isset($bbai_usage_data['limit']) && is_numeric($bbai_usage_data['limit']) ? max(1, (int) $bbai_usage_data['limit']) : 50;
$bbai_usage_used = isset($bbai_usage_data['used']) && is_numeric($bbai_usage_data['used']) ? max(0, (int) $bbai_usage_data['used']) : 0;
$bbai_usage_remaining = isset($bbai_usage_data['remaining']) && is_numeric($bbai_usage_data['remaining']) ? max(0, (int) $bbai_usage_data['remaining']) : max(0, $bbai_usage_limit - $bbai_usage_used);

if (isset($bbai_usage_stats) && is_array($bbai_usage_stats)) {
    if (isset($bbai_usage_stats['limit']) && is_numeric($bbai_usage_stats['limit'])) {
        $bbai_usage_limit = max(1, (int) $bbai_usage_stats['limit']);
    }
    if (isset($bbai_usage_stats['used']) && is_numeric($bbai_usage_stats['used'])) {
        $bbai_usage_used = max(0, (int) $bbai_usage_stats['used']);
    }
    if (isset($bbai_usage_stats['remaining']) && is_numeric($bbai_usage_stats['remaining'])) {
        $bbai_usage_remaining = max(0, (int) $bbai_usage_stats['remaining']);
    }
}

$bbai_usage_used = min($bbai_usage_used, $bbai_usage_limit);
$bbai_is_usage_triggered = ('free' === $bbai_current_plan) && $bbai_usage_remaining <= 0;
$bbai_problem_images = 0;

if (isset($bbai_state) && is_array($bbai_state)) {
    $bbai_problem_images = max(
        0,
        (int) ($bbai_state['missing_alts'] ?? 0) + (int) ($bbai_state['needs_review_count'] ?? 0)
    );
} elseif (isset($bbai_stats) && is_array($bbai_stats)) {
    $bbai_problem_images = max(
        0,
        (int) ($bbai_stats['missing_alts'] ?? $bbai_stats['images_without_alt'] ?? 0) + (int) ($bbai_stats['needs_review_count'] ?? 0)
    );
}

$bbai_modal_title = $bbai_is_usage_triggered
    ? sprintf(
        /* translators: %s: number of free AI generations */
        __('You\'ve used all %s free AI generations', 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($bbai_usage_limit)
    )
    : __('Choose the right BeepBeep AI plan', 'beepbeep-ai-alt-text-generator');

$bbai_modal_subtitle = $bbai_is_usage_triggered
    ? __('Upgrade to continue optimizing images', 'beepbeep-ai-alt-text-generator')
    : __('Pick the plan that matches your media library size and monthly AI usage.', 'beepbeep-ai-alt-text-generator');
?>

<div id="bbai-upgrade-modal" class="bbai-modal-backdrop" data-bbai-upgrade-modal="1" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="bbai-upgrade-modal-title">
    <div class="bbai-upgrade-modal__content">
        <button type="button" class="bbai-btn bbai-btn-icon-only bbai-upgrade-modal__close" onclick="if(typeof alttextaiCloseModal==='function'){alttextaiCloseModal();}else if(typeof bbaiCloseModal==='function'){bbaiCloseModal();}" aria-label="<?php esc_attr_e('Close', 'beepbeep-ai-alt-text-generator'); ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
        </button>

        <div class="bbai-upgrade-modal__body">
            <div class="bbai-upgrade-modal__header">
                <h2 id="bbai-upgrade-modal-title"><?php echo esc_html($bbai_modal_title); ?></h2>
                <p class="bbai-upgrade-modal__subtitle"><?php echo esc_html($bbai_modal_subtitle); ?></p>
                <p class="bbai-upgrade-modal__trust-line" aria-label="<?php esc_attr_e('Cancel anytime, no lock-in, secure checkout', 'beepbeep-ai-alt-text-generator'); ?>">
                    <span><?php esc_html_e('Cancel anytime', 'beepbeep-ai-alt-text-generator'); ?></span>
                    <span><?php esc_html_e('No lock-in', 'beepbeep-ai-alt-text-generator'); ?></span>
                    <span><?php esc_html_e('Secure checkout', 'beepbeep-ai-alt-text-generator'); ?></span>
                </p>
                <?php if ($bbai_problem_images > 0) : ?>
                    <div class="bbai-upgrade-modal__problem">
                        <p class="bbai-upgrade-modal__problem-title">
                            <?php
                            printf(
                                /* translators: %s: number of images still needing ALT text improvements */
                                esc_html__('Your site still has %s images needing ALT text improvements.', 'beepbeep-ai-alt-text-generator'),
                                esc_html(number_format_i18n($bbai_problem_images))
                            );
                            ?>
                        </p>
                        <p class="bbai-upgrade-modal__problem-copy"><?php esc_html_e('Growth can help you fix them in minutes.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bbai-pricing-grid">
                <div class="bbai-pricing-card bbai-pricing-card--free">
                    <div class="bbai-pricing-card__badges">
                        <span class="bbai-pricing-card__badge bbai-pricing-card__badge--free"><?php esc_html_e('Free', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <?php if ($bbai_current_plan === 'free') : ?>
                            <span class="bbai-pricing-card__status"><?php esc_html_e('Current plan', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="bbai-pricing-card__intro">
                        <h3 class="bbai-pricing-card__title"><?php esc_html_e('Free', 'beepbeep-ai-alt-text-generator'); ?></h3>
                        <p class="bbai-pricing-card__descriptor"><?php esc_html_e('Best for trying BeepBeep AI', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                    <div class="bbai-pricing-card__price">
                        <span class="bbai-pricing-card__currency"><?php echo esc_html($bbai_currency['symbol']); ?></span>
                        <span class="bbai-pricing-card__amount">0</span>
                    </div>

                    <div class="bbai-pricing-card__limit"><?php esc_html_e('50 AI alt texts per month', 'beepbeep-ai-alt-text-generator'); ?></div>

                    <ul class="bbai-pricing-card__features">
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php esc_html_e('50 AI alt texts per month', 'beepbeep-ai-alt-text-generator'); ?>
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php esc_html_e('Basic bulk generation', 'beepbeep-ai-alt-text-generator'); ?>
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php esc_html_e('Great for small sites or testing', 'beepbeep-ai-alt-text-generator'); ?>
                        </li>
                    </ul>

                    <?php if ($bbai_current_plan === 'free') : ?>
                        <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-lg bbai-btn-block bbai-pricing-card__btn bbai-pricing-card__btn--free" disabled>
                            <?php esc_html_e('Current plan', 'beepbeep-ai-alt-text-generator'); ?>
                        </button>
                    <?php else : ?>
                        <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-lg bbai-btn-block bbai-pricing-card__btn bbai-pricing-card__btn--free">
                            <?php esc_html_e('Continue with Free', 'beepbeep-ai-alt-text-generator'); ?>
                        </button>
                    <?php endif; ?>
                </div>

                <div class="bbai-pricing-card bbai-pricing-card--growth">
                    <div class="bbai-pricing-card__badges">
                        <span class="bbai-pricing-card__badge bbai-pricing-card__badge--growth"><?php esc_html_e('Most popular', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <?php if ($bbai_current_plan === 'growth') : ?>
                            <span class="bbai-pricing-card__status"><?php esc_html_e('Current plan', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="bbai-pricing-card__intro">
                        <h3 class="bbai-pricing-card__title"><?php esc_html_e('Growth', 'beepbeep-ai-alt-text-generator'); ?></h3>
                        <p class="bbai-pricing-card__descriptor"><?php esc_html_e('Perfect for most WordPress sites', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <p class="bbai-pricing-card__proof"><?php esc_html_e('Most WordPress sites choose this plan', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                    <div class="bbai-pricing-card__price">
                        <span class="bbai-pricing-card__currency"><?php echo esc_html($bbai_currency['symbol']); ?></span>
                        <span class="bbai-pricing-card__amount"><?php echo esc_html(number_format($bbai_growth_monthly, 2)); ?></span>
                        <span class="bbai-pricing-card__period"><?php esc_html_e('/month', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </div>

                    <div class="bbai-pricing-card__limit"><?php esc_html_e('1,000 AI alt texts per month', 'beepbeep-ai-alt-text-generator'); ?></div>

                    <p class="bbai-pricing-card__billing">
                        <?php
                        /* translators: 1: annual price */
                        echo esc_html(sprintf(__('or £%s billed annually (2 months free)', 'beepbeep-ai-alt-text-generator'), number_format($bbai_growth_annual, 2)));
                        ?>
                    </p>

                    <div class="bbai-pricing-card__outcomes">
                        <p class="bbai-pricing-card__outcomes-title"><?php esc_html_e('What this unlocks', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <ul class="bbai-pricing-card__outcomes-list">
                            <li><?php esc_html_e('Optimize entire media libraries', 'beepbeep-ai-alt-text-generator'); ?></li>
                            <li><?php esc_html_e('Fix accessibility issues faster', 'beepbeep-ai-alt-text-generator'); ?></li>
                            <li><?php esc_html_e('Improve image SEO at scale', 'beepbeep-ai-alt-text-generator'); ?></li>
                        </ul>
                    </div>

                    <ul class="bbai-pricing-card__features">
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php esc_html_e('1,000 AI alt texts per month', 'beepbeep-ai-alt-text-generator'); ?>
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php esc_html_e('Bulk media library optimization', 'beepbeep-ai-alt-text-generator'); ?>
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php esc_html_e('Priority queue processing', 'beepbeep-ai-alt-text-generator'); ?>
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php esc_html_e('Multilingual SEO support', 'beepbeep-ai-alt-text-generator'); ?>
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php esc_html_e('Cancel or downgrade anytime', 'beepbeep-ai-alt-text-generator'); ?>
                        </li>
                    </ul>

                    <?php if ($bbai_current_plan === 'growth') : ?>
                        <a href="<?php echo esc_url($bbai_billing_url); ?>" class="bbai-btn bbai-btn-primary bbai-btn-lg bbai-btn-block bbai-pricing-card__btn bbai-pricing-card__btn--growth">
                            <?php esc_html_e('Manage billing', 'beepbeep-ai-alt-text-generator'); ?>
                        </a>
                    <?php else : ?>
                        <button type="button"
                                class="bbai-btn bbai-btn-primary bbai-btn-lg bbai-btn-block bbai-pricing-card__btn bbai-pricing-card__btn--growth"
                                data-action="checkout-plan"
                                data-plan="pro"
                                data-price-id="<?php echo esc_attr($bbai_pro_price_id); ?>"
                                data-fallback-url="<?php echo esc_url($bbai_stripe_links['pro']); ?>">
                            <?php esc_html_e('Start Growth Plan', 'beepbeep-ai-alt-text-generator'); ?>
                        </button>
                    <?php endif; ?>
                </div>

                <div class="bbai-pricing-card bbai-pricing-card--agency">
                    <div class="bbai-pricing-card__badges">
                        <span class="bbai-pricing-card__badge bbai-pricing-card__badge--agency"><?php esc_html_e('Agency', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <?php if ($bbai_current_plan === 'agency') : ?>
                            <span class="bbai-pricing-card__status"><?php esc_html_e('Current plan', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="bbai-pricing-card__intro">
                        <h3 class="bbai-pricing-card__title"><?php esc_html_e('Agency', 'beepbeep-ai-alt-text-generator'); ?></h3>
                        <p class="bbai-pricing-card__descriptor"><?php esc_html_e('Built for agencies and multi-site owners', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                    <div class="bbai-pricing-card__price">
                        <span class="bbai-pricing-card__currency"><?php echo esc_html($bbai_currency['symbol']); ?></span>
                        <span class="bbai-pricing-card__amount"><?php echo esc_html(number_format($bbai_agency_monthly, 2)); ?></span>
                        <span class="bbai-pricing-card__period"><?php esc_html_e('/month', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </div>
                    
                    <p class="bbai-pricing-card__billing">
                        <?php
                        /* translators: 1: annual price */
                        echo esc_html(sprintf(__('or £%s billed annually (2 months free)', 'beepbeep-ai-alt-text-generator'), number_format($bbai_agency_annual, 2)));
                        ?>
                    </p>

                    <div class="bbai-pricing-card__limit"><?php esc_html_e('10,000+ AI alt texts per month', 'beepbeep-ai-alt-text-generator'); ?></div>

                    <ul class="bbai-pricing-card__features">
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php esc_html_e('10,000+ AI alt texts per month', 'beepbeep-ai-alt-text-generator'); ?>
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php esc_html_e('Multi-site bulk optimization', 'beepbeep-ai-alt-text-generator'); ?>
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php esc_html_e('Client usage reporting', 'beepbeep-ai-alt-text-generator'); ?>
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php esc_html_e('Cancel or downgrade anytime', 'beepbeep-ai-alt-text-generator'); ?>
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="bbai-feature-coming-soon"><?php esc_html_e('White-label support (coming soon)', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </li>
                    </ul>

                    <?php if ($bbai_current_plan === 'agency') : ?>
                        <a href="<?php echo esc_url($bbai_billing_url); ?>" class="bbai-btn bbai-btn-lg bbai-btn-block bbai-pricing-card__btn bbai-pricing-card__btn--agency">
                            <?php esc_html_e('Manage billing', 'beepbeep-ai-alt-text-generator'); ?>
                        </a>
                    <?php else : ?>
                        <button type="button"
                                class="bbai-btn bbai-btn-lg bbai-btn-block bbai-pricing-card__btn bbai-pricing-card__btn--agency"
                                data-action="checkout-plan"
                                data-plan="agency"
                                data-price-id="<?php echo esc_attr($bbai_agency_price_id); ?>"
                                data-fallback-url="<?php echo esc_url($bbai_stripe_links['agency']); ?>">
                            <?php esc_html_e('Upgrade to Agency', 'beepbeep-ai-alt-text-generator'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bbai-topup-section">
                <div class="bbai-topup-section__content">
                    <div class="bbai-topup-section__text">
                        <p class="bbai-topup-section__eyebrow"><?php esc_html_e('Need a few more credits?', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <p class="bbai-topup-section__title">
                            <?php
                            printf(
                                /* translators: 1: currency symbol, 2: credit pack price */
                                esc_html__('100 ALT texts - %1$s%2$s', 'beepbeep-ai-alt-text-generator'),
                                esc_html($bbai_currency['symbol']),
                                esc_html(number_format((float) ($bbai_currency['credits'] ?? 9.99), 2))
                            );
                            ?>
                        </p>
                        <p class="bbai-topup-section__desc"><?php esc_html_e('One-time credits for smaller batches. They do not expire.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                    <button type="button"
                            class="bbai-btn bbai-btn-dark bbai-btn-lg bbai-topup-section__btn"
                            data-action="checkout-plan"
                            data-plan="credits"
                            data-price-id="<?php echo esc_attr($bbai_credits_price_id); ?>"
                            data-fallback-url="<?php echo esc_url($bbai_stripe_links['credits']); ?>">
                        <?php esc_html_e('Buy credits', 'beepbeep-ai-alt-text-generator'); ?>
                    </button>
                </div>
            </div>

            <div class="bbai-upgrade-modal__footer-links">
                <?php if ($bbai_current_plan !== 'free') : ?>
                    <a href="<?php echo esc_url($bbai_billing_url); ?>" class="bbai-upgrade-modal__footer-link"><?php esc_html_e('Manage billing', 'beepbeep-ai-alt-text-generator'); ?></a>
                <?php endif; ?>
                <a href="<?php echo esc_url($bbai_docs_url); ?>" class="bbai-upgrade-modal__footer-link" target="_blank" rel="noopener noreferrer"><?php esc_html_e('See docs', 'beepbeep-ai-alt-text-generator'); ?></a>
            </div>
        </div>
    </div>
</div>
