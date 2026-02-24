<?php
/**
 * Upgrade Modal Template - Premium SaaS Design
 * Matches new Dashboard, Analytics, Library, Settings design system
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Get current plan from API client
$bbai_current_plan = 'free';
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
?>

<div id="bbai-upgrade-modal" class="bbai-modal-backdrop" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="bbai-upgrade-modal-title">
    <div class="bbai-upgrade-modal__content">
        <button type="button" class="bbai-btn bbai-btn-icon-only bbai-upgrade-modal__close" onclick="if(typeof alttextaiCloseModal==='function'){alttextaiCloseModal();}else if(typeof bbaiCloseModal==='function'){bbaiCloseModal();}" aria-label="<?php esc_attr_e('Close', 'beepbeep-ai-alt-text-generator'); ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
        </button>

        <div class="bbai-upgrade-modal__body">
            <!-- Header -->
            <div class="bbai-upgrade-modal__header">
                <h2 class="bbai-heading-2 bbai-text-center" id="bbai-upgrade-modal-title"><?php esc_html_e('Choose your BeepBeep AI plan', 'beepbeep-ai-alt-text-generator'); ?></h2>
                <p class="bbai-subtitle bbai-text-center"><?php esc_html_e('Choose a plan that matches your monthly usage. Cancel anytime.', 'beepbeep-ai-alt-text-generator'); ?></p>
            </div>


            <!-- Trust Badges -->
            <div class="bbai-trust-badges bbai-trust-badges--modal">
                <p class="bbai-trust-badges__text"><?php esc_html_e('Cancel anytime', 'beepbeep-ai-alt-text-generator'); ?></p>
            </div>

            <!-- Pricing Grid -->
            <div class="bbai-pricing-grid">
                <!-- FREE Plan -->
                <div class="bbai-pricing-card">
                    <?php if ($bbai_current_plan === 'free') : ?>
                        <span class="bbai-pricing-card__badge bbai-pricing-card__badge--current"><?php esc_html_e('Current plan', 'beepbeep-ai-alt-text-generator'); ?></span>
                    <?php else : ?>
                        <span class="bbai-pricing-card__badge bbai-pricing-card__badge--free"><?php esc_html_e('Free', 'beepbeep-ai-alt-text-generator'); ?></span>
                    <?php endif; ?>
                    
                    <div class="bbai-pricing-card__price">
                        <span class="bbai-pricing-card__currency"><?php echo esc_html($bbai_currency['symbol']); ?></span>
                        <span class="bbai-pricing-card__amount">0</span>
                    </div>
                    
                    <div class="bbai-pricing-card__limit"><?php esc_html_e('50 AI alt text per month', 'beepbeep-ai-alt-text-generator'); ?></div>
                    
                    <ul class="bbai-pricing-card__features">
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php esc_html_e('Great for trying BeepBeep AI', 'beepbeep-ai-alt-text-generator'); ?>
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

                <!-- GROWTH Plan (Most Popular) -->
                <div class="bbai-pricing-card bbai-pricing-card--growth">
                    <span class="bbai-pricing-card__badge bbai-pricing-card__badge--growth"><?php esc_html_e('Most Popular', 'beepbeep-ai-alt-text-generator'); ?></span>
                    
                    <div class="bbai-pricing-card__price">
                        <span class="bbai-pricing-card__currency"><?php echo esc_html($bbai_currency['symbol']); ?></span>
                        <span class="bbai-pricing-card__amount"><?php echo esc_html(number_format($bbai_growth_monthly, 2)); ?></span>
                        <span class="bbai-pricing-card__period"><?php esc_html_e('/month', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </div>
                    
                    <p class="bbai-pricing-card__billing">
                        <?php
                        /* translators: 1: annual price */
                        echo esc_html(sprintf(__('or £%s billed annually (2 months free)', 'beepbeep-ai-alt-text-generator'), number_format($bbai_growth_annual, 2)));
                        ?>
                    </p>
                    
                    <div class="bbai-pricing-card__limit"><?php esc_html_e('1,000 AI alt text per month', 'beepbeep-ai-alt-text-generator'); ?></div>
                    
                    <ul class="bbai-pricing-card__features">
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <strong><?php esc_html_e('1,000', 'beepbeep-ai-alt-text-generator'); ?></strong> <?php esc_html_e('AI alt text per month', 'beepbeep-ai-alt-text-generator'); ?>
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php esc_html_e('Bulk AI alt text for your full media library', 'beepbeep-ai-alt-text-generator'); ?>
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php esc_html_e('Priority queue, set tone and style', 'beepbeep-ai-alt-text-generator'); ?>
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
                            <?php esc_html_e('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'); ?>
                        </button>
                    <?php endif; ?>
                </div>

                <!-- AGENCY Plan -->
                <div class="bbai-pricing-card bbai-pricing-card--agency">
                    <span class="bbai-pricing-card__badge bbai-pricing-card__badge--agency"><?php esc_html_e('Agency', 'beepbeep-ai-alt-text-generator'); ?></span>
                    
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
                    
                    <div class="bbai-pricing-card__limit"><?php esc_html_e('10,000+ AI alt text per month', 'beepbeep-ai-alt-text-generator'); ?></div>
                    
                    <ul class="bbai-pricing-card__features">
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <strong><?php esc_html_e('10,000+', 'beepbeep-ai-alt-text-generator'); ?></strong> <?php esc_html_e('AI alt text for multiple client', 'beepbeep-ai-alt-text-generator'); ?>
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php esc_html_e('Bulk AI alt text for mobile sites from one dashboard', 'beepbeep-ai-alt-text-generator'); ?>
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php esc_html_e('Usage reporting for detailed client billing', 'beepbeep-ai-alt-text-generator'); ?>
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
                            <span class="bbai-feature-coming-soon"><?php esc_html_e('White-label coming soon!', 'beepbeep-ai-alt-text-generator'); ?></span>
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

            <!-- One-time Credits Section -->
            <div class="bbai-topup-section">
                <div class="bbai-topup-section__content">
                    <div class="bbai-topup-section__text">
                        <p class="bbai-topup-section__title"><?php esc_html_e('One-time credits', 'beepbeep-ai-alt-text-generator'); ?> <span class="bbai-topup-section__subtitle-inline"><?php esc_html_e('Pay-as-you-go', 'beepbeep-ai-alt-text-generator'); ?></span></p>
                        <p class="bbai-topup-section__desc"><?php esc_html_e('Pay-as-you-go credits (never expire). Ideal for photographers and seasonal use.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                    <div class="bbai-topup-section__price-action">
                        <div class="bbai-topup-section__price">
                            <span class="bbai-pricing-card__currency"><?php echo esc_html($bbai_currency['symbol']); ?></span>
                            <span class="bbai-pricing-card__amount"><?php echo esc_html(number_format($bbai_currency['credits'] ?? 19.99, 2)); ?></span>
                        </div>
                        <button type="button"
                                class="bbai-btn bbai-btn-dark bbai-btn-lg bbai-topup-section__btn"
                                data-action="checkout-plan"
                                data-plan="credits"
                                data-price-id="<?php echo esc_attr($bbai_credits_price_id); ?>"
                                data-fallback-url="<?php echo esc_url($bbai_stripe_links['credits']); ?>">
                            <?php esc_html_e('Buy 100 credits', 'beepbeep-ai-alt-text-generator'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- FAQ Section -->
            <div class="bbai-pricing-faq">
                <h3 class="bbai-pricing-faq__title"><?php esc_html_e('Frequently Asked Questions', 'beepbeep-ai-alt-text-generator'); ?></h3>
                <div class="bbai-pricing-faq__list">
                    <div class="bbai-pricing-faq__item">
                        <div class="bbai-pricing-faq__question">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span><?php esc_html_e('Can I downgrade anytime?', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                        <div class="bbai-pricing-faq__answer">
                            <?php esc_html_e('Yes. You can downgrade or cancel anytime.', 'beepbeep-ai-alt-text-generator'); ?>
                        </div>
                    </div>
                    <div class="bbai-pricing-faq__item">
                        <div class="bbai-pricing-faq__question">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span><?php esc_html_e('Do credits roll over?', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                        <div class="bbai-pricing-faq__answer">
                            <?php esc_html_e('Monthly credits reset each month. One-time credits do not expire.', 'beepbeep-ai-alt-text-generator'); ?>
                        </div>
                    </div>
                    <div class="bbai-pricing-faq__item">
                        <div class="bbai-pricing-faq__question">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span><?php esc_html_e('Will it work with WooCommerce and all themes?', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                        <div class="bbai-pricing-faq__answer">
                            <?php esc_html_e('Yes. It works with WooCommerce, Gutenberg, and most themes.', 'beepbeep-ai-alt-text-generator'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
