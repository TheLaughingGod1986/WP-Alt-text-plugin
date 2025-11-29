<?php
/**
 * Upgrade Modal Template
 * SEO-optimized pricing modal for maximum conversion
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Check if user is authenticated
$is_authenticated = $this->api_client->is_authenticated();

// Get price IDs from backend API
$pro_price_id = $checkout_prices['pro'] ?? '';
$agency_price_id = $checkout_prices['agency'] ?? '';
$credits_price_id = $checkout_prices['credits'] ?? '';

// Fallback to hardcoded Stripe links if API price IDs not available
$stripe_links = [
    'pro' => 'https://buy.stripe.com/dRm28s4rc5Raf0GbY77ss02',
    'agency' => 'https://buy.stripe.com/28E14og9U0wQ19Q4vF7ss01',
    'credits' => 'https://buy.stripe.com/6oU9AUf5Q2EYaKq0fp7ss00'
];

// Get currency - Default to GBP since Stripe is configured for GBP
$locale = get_locale();
$locale_lower = strtolower($locale);
$locale_prefix = substr($locale_lower, 0, 2);

// Check if explicitly set to force USD
$force_usd = get_option('bbai_force_usd_currency', false);

// Explicitly US/Canada locales - USD ($) - only use USD if explicitly US/Canada locale
$is_us_locale = in_array($locale_lower, ['en_us', 'en_ca']);

// Default to GBP since Stripe is configured for GBP
// Only use USD if explicitly US locale OR force USD option is set
if ($is_us_locale || $force_usd) {
    $currency = ['symbol' => '$', 'code' => 'USD', 'pro_monthly' => 14.99, 'pro_yearly' => 149, 'agency_monthly' => 59.99, 'agency_yearly' => 599, 'credits' => 11.99];
}
// European locales - EUR (€)
else if (in_array($locale_prefix, ['de', 'fr', 'it', 'es', 'pt', 'nl', 'pl', 'el', 'cs', 'sk', 'hu', 'ro', 'bg', 'hr', 'sl', 'lt', 'lv', 'et', 'fi', 'sv', 'da', 'be', 'at', 'ie', 'lu', 'mt', 'cy'])) {
    $currency = ['symbol' => '€', 'code' => 'EUR', 'pro_monthly' => 12.99, 'pro_yearly' => 129, 'agency_monthly' => 49.99, 'agency_yearly' => 499, 'credits' => 9.99];
}
// Default to GBP for all other cases (since Stripe is configured for GBP)
else {
    $currency = ['symbol' => '£', 'code' => 'GBP', 'pro_monthly' => 12.99, 'pro_yearly' => 129, 'agency_monthly' => 49.99, 'agency_yearly' => 499, 'credits' => 9.99];
}
?>

<div id="bbai-upgrade-modal" class="bbai-modal-backdrop" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="bbai-upgrade-modal-title" aria-describedby="bbai-upgrade-modal-desc">
    <div class="bbai-upgrade-modal__content">
        <!-- Header -->
        <div class="bbai-upgrade-modal__header">
            <div class="bbai-upgrade-modal__header-content">
                <h2 id="bbai-upgrade-modal-title"><?php esc_html_e('Fix Missing Alt Text Automatically — Boost Google Images Rankings in Seconds', 'beepbeep-ai-alt-text-generator'); ?></h2>
                <p class="bbai-upgrade-modal__subtitle" id="bbai-upgrade-modal-desc"><?php esc_html_e('Stop losing traffic from Google Images. Generate SEO-optimized, WCAG-compliant alt text for thousands of images automatically. No manual work required.', 'beepbeep-ai-alt-text-generator'); ?></p>
                
                <!-- Trust Badges -->
                <div class="bbai-trust-badges">
                    <div class="bbai-trust-badge">
                        <span class="bbai-trust-badge-icon">✓</span>
                        <span class="bbai-trust-badge-text"><?php esc_html_e('Trusted by 2,000+ WordPress sites', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </div>
                    <div class="bbai-trust-badge">
                        <span class="bbai-trust-badge-icon">✓</span>
                        <span class="bbai-trust-badge-text"><?php esc_html_e('WCAG-compliant alt text', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </div>
                </div>
            </div>
            <button type="button" class="bbai-modal-close" onclick="if(typeof alttextaiCloseModal==='function'){alttextaiCloseModal();}else if(typeof bbaiCloseModal==='function'){bbaiCloseModal();}" aria-label="<?php esc_attr_e('Close upgrade modal', 'beepbeep-ai-alt-text-generator'); ?>">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M15 5L5 15M5 5l10 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
        </div>
        
        <div class="bbai-upgrade-modal__body">
            <!-- NO_ACCESS / Out of Credits Notice (shown via JavaScript) -->
            <div id="bbai-no-access-notice" class="bbai-auth-notice bbai-no-access-notice" style="display: none;">
                <div class="bbai-auth-notice__icon">⚠️</div>
                <p id="bbai-no-access-message"></p>
                <div class="bbai-no-access-actions" style="margin-top: 12px; display: flex; gap: 12px; flex-wrap: wrap;">
                    <button type="button" class="button button-primary" id="bbai-buy-credits-btn" style="display: none;">
                        <?php esc_html_e('Buy Credits on Dashboard', 'beepbeep-ai-alt-text-generator'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="bbai-upgrade-subscription-btn" style="display: none;">
                        <?php esc_html_e('Upgrade Subscription', 'beepbeep-ai-alt-text-generator'); ?>
                    </button>
                </div>
            </div>
            
            <?php if (!$is_authenticated) : ?>
                <!-- Not Authenticated - Show Pricing with Sign Up Note -->
                <div class="bbai-auth-notice">
                    <div class="bbai-auth-notice__icon">💡</div>
                    <p><strong><?php esc_html_e('Get started in 30 seconds', 'beepbeep-ai-alt-text-generator'); ?></strong> <?php esc_html_e('Create your free account during checkout. Generate 50 AI alt texts immediately. Existing users can', 'beepbeep-ai-alt-text-generator'); ?> <a href="#" onclick="triggerSignIn(); return false;"><?php esc_html_e('sign in here', 'beepbeep-ai-alt-text-generator'); ?></a>.</p>
                </div>
            <?php endif; ?>
            
            <!-- Why Upgrade Bar -->
            <div class="bbai-why-upgrade">
                <div class="bbai-why-upgrade-item">
                    <span class="bbai-why-upgrade-icon">⚡</span>
                    <span class="bbai-why-upgrade-text"><?php esc_html_e('Generate alt text for 1,000+ images automatically', 'beepbeep-ai-alt-text-generator'); ?></span>
                </div>
                <div class="bbai-why-upgrade-item">
                    <span class="bbai-why-upgrade-icon">📈</span>
                    <span class="bbai-why-upgrade-text"><?php esc_html_e('Boost Google Images rankings with SEO-optimized descriptions', 'beepbeep-ai-alt-text-generator'); ?></span>
                </div>
                <div class="bbai-why-upgrade-item">
                    <span class="bbai-why-upgrade-icon">⏱️</span>
                    <span class="bbai-why-upgrade-text"><?php esc_html_e('Save 10+ hours monthly — no manual alt text writing', 'beepbeep-ai-alt-text-generator'); ?></span>
                </div>
            </div>
            
            <!-- Monthly/Yearly Toggle -->
            <div class="bbai-billing-toggle">
                <button type="button" class="bbai-billing-toggle__option bbai-billing-toggle__option--monthly bbai-billing-toggle__option--active" data-billing="monthly">
                    <?php esc_html_e('Monthly', 'beepbeep-ai-alt-text-generator'); ?>
                </button>
                <button type="button" class="bbai-billing-toggle__option bbai-billing-toggle__option--yearly bbai-billing-toggle__option--disabled" data-billing="yearly" disabled title="<?php esc_attr_e('Coming soon', 'beepbeep-ai-alt-text-generator'); ?>">
                    <?php esc_html_e('Yearly', 'beepbeep-ai-alt-text-generator'); ?>
                    <span class="bbai-billing-coming-soon"><?php esc_html_e('COMING SOON', 'beepbeep-ai-alt-text-generator'); ?></span>
                </button>
            </div>
            
            <!-- Pricing Plans -->
            <div class="bbai-pricing-container">
                <!-- Pro Plan Card -->
                <div class="bbai-plan-card bbai-plan-card--pro">
                    <div class="bbai-plan-badge bbai-plan-badge--popular"><?php esc_html_e('MOST POPULAR', 'beepbeep-ai-alt-text-generator'); ?></div>
                    <div class="bbai-plan-header">
                        <h3><?php esc_html_e('Pro Plan', 'beepbeep-ai-alt-text-generator'); ?></h3>
                        <div class="bbai-plan-price">
                            <span class="bbai-price-amount bbai-price-monthly"><?php echo esc_html($currency['symbol']); ?><span class="bbai-price-value"><?php echo esc_html(number_format($currency['pro_monthly'], 2)); ?></span></span>
                            <span class="bbai-price-amount bbai-price-yearly" style="display: none;"><?php echo esc_html($currency['symbol']); ?><span class="bbai-price-value"><?php echo esc_html($currency['pro_yearly']); ?></span></span>
                            <span class="bbai-price-period bbai-price-period-monthly"><?php esc_html_e('/month', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <span class="bbai-price-period bbai-price-period-yearly" style="display: none;"><?php esc_html_e('/year', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                    </div>
                    
                    <div class="bbai-plan-features">
                        <ul>
                            <li>
                                <span class="bbai-feature-highlight"><?php esc_html_e('1,000', 'beepbeep-ai-alt-text-generator'); ?></span>
                                <?php esc_html_e('AI-generated alt texts per month', 'beepbeep-ai-alt-text-generator'); ?>
                            </li>
                            <li><?php esc_html_e('Boost Google Images rankings automatically', 'beepbeep-ai-alt-text-generator'); ?></li>
                            <li><?php esc_html_e('Bulk generate alt text for entire media library', 'beepbeep-ai-alt-text-generator'); ?></li>
                            <li><?php esc_html_e('WCAG-compliant descriptions for accessibility', 'beepbeep-ai-alt-text-generator'); ?></li>
                            <li><?php esc_html_e('Priority support and email assistance', 'beepbeep-ai-alt-text-generator'); ?></li>
                            <li><?php esc_html_e('Use on one WordPress site', 'beepbeep-ai-alt-text-generator'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="bbai-plan-credits-info">
                        <small><?php esc_html_e('100 credits/month', 'beepbeep-ai-alt-text-generator'); ?></small>
                    </div>

                    <button type="button"
                            class="bbai-btn-primary bbai-plan-cta bbai-plan-cta--pro"
                            data-action="checkout-plan"
                            data-plan="pro"
                            data-price-id="<?php echo esc_attr($pro_price_id); ?>"
                            data-fallback-url="<?php echo esc_url($stripe_links['pro']); ?>"
                            aria-label="<?php esc_attr_e('Purchase Pro plan', 'beepbeep-ai-alt-text-generator'); ?>">
                        <?php esc_html_e('Faster SEO Cleanup', 'beepbeep-ai-alt-text-generator'); ?>
                    </button>
                    
                    <p class="bbai-plan-trust"><?php esc_html_e('Cancel anytime. Instant activation. No setup required.', 'beepbeep-ai-alt-text-generator'); ?></p>
                </div>
                
                <!-- Agency Plan Card -->
                <div class="bbai-plan-card bbai-plan-card--agency">
                    <div class="bbai-plan-badge bbai-plan-badge--professional"><?php esc_html_e('BEST FOR PROFESSIONALS', 'beepbeep-ai-alt-text-generator'); ?></div>
                    <div class="bbai-plan-header">
                        <h3><?php esc_html_e('Agency Plan', 'beepbeep-ai-alt-text-generator'); ?></h3>
                        <div class="bbai-plan-price">
                            <span class="bbai-price-amount bbai-price-monthly"><?php echo esc_html($currency['symbol']); ?><span class="bbai-price-value"><?php echo esc_html(number_format($currency['agency_monthly'], 2)); ?></span></span>
                            <span class="bbai-price-amount bbai-price-yearly" style="display: none;"><?php echo esc_html($currency['symbol']); ?><span class="bbai-price-value"><?php echo esc_html($currency['agency_yearly']); ?></span></span>
                            <span class="bbai-price-period bbai-price-period-monthly"><?php esc_html_e('/month', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <span class="bbai-price-period bbai-price-period-yearly" style="display: none;"><?php esc_html_e('/year', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                    </div>
                    
                    <div class="bbai-plan-features">
                        <ul>
                            <li>
                                <span class="bbai-feature-highlight"><?php esc_html_e('10,000', 'beepbeep-ai-alt-text-generator'); ?></span>
                                <?php esc_html_e('AI-generated alt texts per month', 'beepbeep-ai-alt-text-generator'); ?>
                            </li>
                            <li><?php esc_html_e('Maximize Google Images traffic with SEO-optimized alt text', 'beepbeep-ai-alt-text-generator'); ?></li>
                            <li><?php esc_html_e('Bulk generate for thousands of images across multiple sites', 'beepbeep-ai-alt-text-generator'); ?></li>
                            <li><?php esc_html_e('WCAG 2.1 AA compliance for all client sites', 'beepbeep-ai-alt-text-generator'); ?></li>
                            <li><?php esc_html_e('Dedicated account manager and priority support', 'beepbeep-ai-alt-text-generator'); ?></li>
                            <li><?php esc_html_e('Use on unlimited WordPress sites', 'beepbeep-ai-alt-text-generator'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="bbai-plan-credits-info">
                        <small><?php esc_html_e('100~time purchase', 'beepbeep-ai-alt-text-generator'); ?></small>
                    </div>

                    <button type="button"
                            class="bbai-btn-success bbai-plan-cta bbai-plan-cta--agency"
                            data-action="checkout-plan"
                            data-plan="agency"
                            data-price-id="<?php echo esc_attr($agency_price_id); ?>"
                            data-fallback-url="<?php echo esc_url($stripe_links['agency']); ?>"
                            aria-label="<?php esc_attr_e('Purchase Agency plan', 'beepbeep-ai-alt-text-generator'); ?>">
                        <?php esc_html_e('Scale Bulk SEO Automation Across Sites', 'beepbeep-ai-alt-text-generator'); ?>
                    </button>
                    
                    <p class="bbai-plan-trust"><?php esc_html_e('Perfect for agencies managing 10+ client sites.', 'beepbeep-ai-alt-text-generator'); ?></p>
                </div>

                <!-- Credits Pack Section - Dynamically loaded from API -->
                <div class="bbai-credits-section" id="bbai-credits-packs-container">
                    <div class="bbai-credits-loading" style="text-align: center; padding: 20px; display: none;">
                        <?php esc_html_e('Loading credit packs...', 'beepbeep-ai-alt-text-generator'); ?>
                    </div>
                    <div class="bbai-credits-packs-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
                        <!-- Credit packs will be dynamically inserted here via JavaScript -->
                    </div>
                </div>
            </div>

            <!-- Footer Trust Line -->
            <div class="bbai-upgrade-modal__footer">
                <p class="bbai-footer-trust">
                        <?php esc_html_e('Secure Stripe checkout • Cancel anytime • Instant access • Works with Elementor, Divi, Quberg, and all themes', 'beepbeep-ai-alt-text-generator'); ?>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
(function($) {
    'use strict';
    
    // Check for NO_ACCESS reasons and show appropriate notice
    function checkNoAccessReason() {
        if (typeof sessionStorage === 'undefined') return;
        
        var reason = sessionStorage.getItem('bbai_upgrade_reason');
        var message = sessionStorage.getItem('bbai_upgrade_message');
        var credits = sessionStorage.getItem('bbai_upgrade_credits');
        var subscriptionExpired = sessionStorage.getItem('bbai_upgrade_subscription_expired') === 'true';
        
        if (!reason || !message) return;
        
        var notice = $('#bbai-no-access-notice');
        var messageEl = $('#bbai-no-access-message');
        var buyCreditsBtn = $('#bbai-buy-credits-btn');
        var upgradeSubscriptionBtn = $('#bbai-upgrade-subscription-btn');
        
        // Show notice for NO_ACCESS related reasons
        if (reason === 'no_access' || reason === 'out_of_credits' || reason === 'subscription_expired') {
            notice.show();
            messageEl.html('<strong>' + message + '</strong>');
            
            // Show appropriate CTAs based on reason
            if (reason === 'out_of_credits') {
                // Show both buttons for out of credits
                buyCreditsBtn.show().on('click', function() {
                    // Link to dashboard credits purchase
                    var dashboardUrl = (typeof opttiApi !== 'undefined' && opttiApi.dashboardUrl) || (typeof window.bbai_ajax !== 'undefined' && window.bbai_ajax.dashboard_url) || '#';
                    if (dashboardUrl && dashboardUrl !== '#') {
                        window.open(dashboardUrl + '/dashboard/credits', '_blank');
                    }
                });
                upgradeSubscriptionBtn.show().on('click', function() {
                    // Scroll to pricing plans or highlight subscription plans
                    $('html, body').animate({
                        scrollTop: $('.bbai-pricing-container').offset().top - 20
                    }, 500);
                    $('.bbai-plan-card--pro, .bbai-plan-card--agency').addClass('bbai-plan-highlight').delay(3000).queue(function() {
                        $(this).removeClass('bbai-plan-highlight').dequeue();
                    });
                });
            } else if (reason === 'subscription_expired') {
                // Show upgrade subscription button
                upgradeSubscriptionBtn.show().on('click', function() {
                    $('html, body').animate({
                        scrollTop: $('.bbai-pricing-container').offset().top - 20
                    }, 500);
                    $('.bbai-plan-card--pro, .bbai-plan-card--agency').addClass('bbai-plan-highlight').delay(3000).queue(function() {
                        $(this).removeClass('bbai-plan-highlight').dequeue();
                    });
                });
            } else {
                // Generic NO_ACCESS - show both options
                buyCreditsBtn.show().on('click', function() {
                    var dashboardUrl = (typeof opttiApi !== 'undefined' && opttiApi.dashboardUrl) || (typeof window.bbai_ajax !== 'undefined' && window.bbai_ajax.dashboard_url) || '#';
                    if (dashboardUrl && dashboardUrl !== '#') {
                        window.open(dashboardUrl + '/dashboard/credits', '_blank');
                    }
                });
                upgradeSubscriptionBtn.show().on('click', function() {
                    $('html, body').animate({
                        scrollTop: $('.bbai-pricing-container').offset().top - 20
                    }, 500);
                });
            }
        }
    }
    
    /**
     * Load and render credit packs dynamically
     */
    async function loadCreditPacks() {
        const container = document.getElementById('bbai-credits-packs-container');
        const loadingEl = container?.querySelector('.bbai-credits-loading');
        const packsGrid = container?.querySelector('.bbai-credits-packs-grid');
        
        if (!container || !packsGrid) return;
        
        // Show loading state
        if (loadingEl) loadingEl.style.display = 'block';
        if (packsGrid) packsGrid.style.display = 'none';
        
        try {
            // Use getCreditPacks if available
            let packsResult;
            if (typeof window.getCreditPacks === 'function') {
                packsResult = await window.getCreditPacks();
            } else {
                // Fallback: fetch directly from REST API
                const token = (typeof opttiApi !== 'undefined' && opttiApi.token) || '';
                const headers = {
                    'Content-Type': 'application/json',
                };
                if (token) {
                    headers['Authorization'] = 'Bearer ' + token;
                }
                
                const restUrl = (typeof opttiApi !== 'undefined' && opttiApi.restUrl) || '/wp-json/bbai/v1/credits/packs';
                const res = await fetch(restUrl, {
                    method: 'GET',
                    headers: headers
                });
                
                if (res.ok) {
                    const data = await res.json();
                    packsResult = {
                        ok: true,
                        packs: Array.isArray(data) ? data : (data.packs || [])
                    };
                } else {
                    packsResult = {
                        ok: false,
                        packs: []
                    };
                }
            }
            
            if (!packsResult || !packsResult.ok || !packsResult.packs || packsResult.packs.length === 0) {
                // Hide credits section if no packs available
                if (container) container.style.display = 'none';
                return;
            }
            
            const packs = packsResult.packs;
            const currencySymbol = '<?php echo esc_js($currency['symbol']); ?>';
            
            // Clear existing packs
            packsGrid.innerHTML = '';
            
            // Render each pack
            packs.forEach(function(pack) {
                const credits = pack.credits || 0;
                const price = pack.price || 0;
                const packId = pack.id || '';
                const priceFormatted = (price / 100).toFixed(2); // Convert from cents to currency
                
                const packCard = document.createElement('div');
                packCard.className = 'bbai-plan-card bbai-plan-card--credits';
                packCard.innerHTML = `
                    <div class="bbai-credits-header">
                        <div class="bbai-credits-header-left">
                            <h3><?php esc_html_e('Credit Pack', 'beepbeep-ai-alt-text-generator'); ?></h3>
                            <div class="bbai-credits-features">
                                <span class="bbai-credits-feature">${credits} <?php esc_html_e('credits', 'beepbeep-ai-alt-text-generator'); ?></span>
                                <span class="bbai-credits-feature"><?php esc_html_e('Never expire', 'beepbeep-ai-alt-text-generator'); ?></span>
                                <span class="bbai-credits-feature"><?php esc_html_e('One-time purchase', 'beepbeep-ai-alt-text-generator'); ?></span>
                            </div>
                        </div>
                        <div class="bbai-plan-price">
                            <span class="bbai-price-amount">${currencySymbol}${priceFormatted}</span>
                            <span class="bbai-price-period"><?php esc_html_e('One-time', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                    </div>
                    
                    <div class="bbai-plan-credits-info">
                        <small><?php esc_html_e('Credits:', 'beepbeep-ai-alt-text-generator'); ?> ${credits} <?php esc_html_e('one-time purchase', 'beepbeep-ai-alt-text-generator'); ?></small>
                    </div>
                    
                    <button type="button" 
                            class="bbai-btn-secondary bbai-plan-cta bbai-plan-cta--credits"
                            data-action="checkout-plan"
                            data-plan="credits"
                            data-pack-id="${packId}"
                            data-price-id="${packId}"
                            aria-label="<?php esc_attr_e('Purchase credit pack', 'beepbeep-ai-alt-text-generator'); ?>">
                        <?php esc_html_e('Buy Credits', 'beepbeep-ai-alt-text-generator'); ?>
                    </button>
                `;
                
                packsGrid.appendChild(packCard);
            });
            
            // Show packs grid
            if (loadingEl) loadingEl.style.display = 'none';
            if (packsGrid) packsGrid.style.display = 'grid';
            
        } catch (error) {
            console.error('[AltText AI] Error loading credit packs:', error);
            // Hide credits section on error
            if (container) container.style.display = 'none';
            if (loadingEl) loadingEl.style.display = 'none';
        }
    }
    
    // Billing toggle functionality
    $(document).ready(function() {
        // Check for NO_ACCESS reason on modal open
        checkNoAccessReason();
        
        // Also check when modal is shown (in case it's opened programmatically)
        var modal = $('#bbai-upgrade-modal');
        if (modal.length) {
            // Use MutationObserver to detect when modal is shown
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                        var display = modal.css('display');
                        if (display === 'flex' || display === 'block') {
                            setTimeout(checkNoAccessReason, 100);
                            // Load credit packs when modal is shown
                            loadCreditPacks();
                        }
                    }
                });
            });
            observer.observe(modal[0], { attributes: true, attributeFilter: ['style'] });
        }
        
        // Load credit packs on initial page load if modal is already visible
        if (modal.length && (modal.css('display') === 'flex' || modal.css('display') === 'block')) {
            loadCreditPacks();
        }
        
        // Currency is already detected server-side via WordPress locale
        // This ensures prices display correctly based on user's WordPress language/region settings
        
        $('.bbai-billing-toggle__option').on('click', function() {
            // Prevent interaction with disabled yearly option
            if ($(this).hasClass('bbai-billing-toggle__option--disabled') || $(this).prop('disabled')) {
                return false;
            }
            
            var billing = $(this).data('billing');
            $('.bbai-billing-toggle__option').removeClass('bbai-billing-toggle__option--active');
            $(this).addClass('bbai-billing-toggle__option--active');
            
            // Always show monthly pricing (yearly is disabled)
            $('.bbai-price-monthly, .bbai-price-period-monthly').show();
            $('.bbai-price-yearly, .bbai-price-period-yearly').hide();
        });
    });
})(jQuery);
</script>
