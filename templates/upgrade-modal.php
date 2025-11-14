<?php
/**
 * Upgrade Modal Template
 * Uses JavaScript to bypass WordPress CSP restrictions
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Check if user is authenticated
$is_authenticated = $this->api_client->is_authenticated();

// Get price IDs from backend API
// Note: $checkout_prices is passed in from the calling function
$pro_price_id = $checkout_prices['pro'] ?? '';
$agency_price_id = $checkout_prices['agency'] ?? '';
$credits_price_id = $checkout_prices['credits'] ?? '';

// Fallback to hardcoded Stripe links if API price IDs not available
$stripe_links = [
    'pro' => 'https://buy.stripe.com/dRm28s4rc5Raf0GbY77ss02',
    'agency' => 'https://buy.stripe.com/28E14og9U0wQ19Q4vF7ss01',
    'credits' => 'https://buy.stripe.com/6oU9AUf5Q2EYaKq0fp7ss00'
];
?>

<div id="alttextai-upgrade-modal" class="alttextai-modal-backdrop" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="alttextai-upgrade-modal-title" aria-describedby="alttextai-upgrade-modal-desc">
    <div class="alttextai-upgrade-modal__content">
        <div class="alttextai-upgrade-modal__header">
            <div class="alttextai-upgrade-modal__header-content">
                <h2 id="alttextai-upgrade-modal-title"><?php esc_html_e('Unlock Unlimited SEO AI Alt Text Generation', 'opptiai-alt-text-generator'); ?></h2>
                <p class="alttextai-upgrade-modal__subtitle" id="alttextai-upgrade-modal-desc"><?php esc_html_e('Boost Google image rankings, improve SEO, and save hours of manual work', 'opptiai-alt-text-generator'); ?></p>
            </div>
            <button type="button" class="alttextai-modal-close" onclick="alttextaiCloseModal();" aria-label="<?php esc_attr_e('Close upgrade modal', 'opptiai-alt-text-generator'); ?>">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M15 5L5 15M5 5l10 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
        </div>
        
        <div class="alttextai-upgrade-modal__body">
            <?php if (!$is_authenticated) : ?>
                <!-- Not Authenticated - Show Pricing with Sign Up Note -->
                <div class="alttextai-auth-notice">
                    <div class="alttextai-auth-notice__icon">💡</div>
                    <p><strong><?php esc_html_e('New to SEO AI Alt Text?', 'opptiai-alt-text-generator'); ?></strong> <?php esc_html_e('You\'ll create your account during checkout. Existing users can', 'opptiai-alt-text-generator'); ?> <a href="#" onclick="triggerSignIn(); return false;"><?php esc_html_e('sign in here', 'opptiai-alt-text-generator'); ?></a>.</p>
                </div>
            <?php endif; ?>
            
            <!-- Pricing Plans -->
            <div class="alttextai-pricing-container">
                <!-- Top Row: Pro & Agency Plans -->
                <div class="alttextai-pricing-grid-top">
                    <!-- Pro Plan -->
                    <div class="alttextai-plan-card alttextai-plan-card--pro">
                        <div class="alttextai-plan-header">
                            <h3><?php esc_html_e('Pro Plan', 'opptiai-alt-text-generator'); ?></h3>
                            <div class="alttextai-plan-price">
                                <span class="alttextai-price-amount">£12.99</span>
                                <span class="alttextai-price-period"><?php esc_html_e('/month', 'opptiai-alt-text-generator'); ?></span>
                            </div>
                            <p class="alttextai-plan-value"><?php esc_html_e('Perfect for growing websites', 'opptiai-alt-text-generator'); ?></p>
                        </div>
                        
                        <div class="alttextai-plan-features">
                            <ul>
                                <li>
                                    <span class="alttextai-feature-highlight"><?php esc_html_e('1,000', 'opptiai-alt-text-generator'); ?></span>
                                    <?php esc_html_e('AI-generated alt texts per month', 'opptiai-alt-text-generator'); ?>
                                </li>
                                <li><?php esc_html_e('Advanced quality scoring for SEO', 'opptiai-alt-text-generator'); ?></li>
                                <li><?php esc_html_e('Bulk process unlimited images', 'opptiai-alt-text-generator'); ?></li>
                                <li><?php esc_html_e('Priority support & assistance', 'opptiai-alt-text-generator'); ?></li>
                                <li><?php esc_html_e('Full API access included', 'opptiai-alt-text-generator'); ?></li>
                            </ul>
                        </div>
                        
                        <button type="button" 
                                class="alttextai-btn-primary alttextai-btn-icon alttextai-plan-cta"
                                data-action="checkout-plan"
                                data-plan="pro"
                                data-price-id="<?php echo esc_attr($pro_price_id); ?>"
                                data-fallback-url="<?php echo esc_url($stripe_links['pro']); ?>"
                                aria-label="<?php esc_attr_e('Purchase Pro plan - Unlimited monthly generations', 'opptiai-alt-text-generator'); ?>">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                <path d="M8 2L6 6H2L6 9L4 14L8 11L12 14L10 9L14 6H10L8 2Z" fill="currentColor"/>
                            </svg>
                            <span><?php esc_html_e('Get Started with Pro', 'opptiai-alt-text-generator'); ?></span>
                        </button>
                    </div>
                    
                    <!-- Agency Plan -->
                    <div class="alttextai-plan-card alttextai-plan-card--featured alttextai-plan-card--agency">
                        <div class="alttextai-plan-badge"><?php esc_html_e('MOST POPULAR', 'opptiai-alt-text-generator'); ?></div>
                        <div class="alttextai-plan-header">
                            <h3><?php esc_html_e('Agency Plan', 'opptiai-alt-text-generator'); ?></h3>
                            <div class="alttextai-plan-price">
                                <span class="alttextai-price-amount">£49.99</span>
                                <span class="alttextai-price-period"><?php esc_html_e('/month', 'opptiai-alt-text-generator'); ?></span>
                            </div>
                            <p class="alttextai-plan-value"><?php esc_html_e('Best value for agencies & professionals', 'opptiai-alt-text-generator'); ?></p>
                        </div>
                        
                        <div class="alttextai-plan-features">
                            <ul>
                                <li>
                                    <span class="alttextai-feature-highlight"><?php esc_html_e('10,000', 'opptiai-alt-text-generator'); ?></span>
                                    <?php esc_html_e('AI-generated alt texts per month', 'opptiai-alt-text-generator'); ?>
                                </li>
                                <li><?php esc_html_e('Advanced quality scoring for SEO', 'opptiai-alt-text-generator'); ?></li>
                                <li><?php esc_html_e('Bulk process unlimited images', 'opptiai-alt-text-generator'); ?></li>
                                <li><?php esc_html_e('Priority support & assistance', 'opptiai-alt-text-generator'); ?> <span class="alttextai-coming-soon"><?php esc_html_e('(coming soon)', 'opptiai-alt-text-generator'); ?></span></li>
                                <li><?php esc_html_e('Full API access included', 'opptiai-alt-text-generator'); ?> <span class="alttextai-coming-soon"><?php esc_html_e('(coming soon)', 'opptiai-alt-text-generator'); ?></span></li>
                                <li><?php esc_html_e('White-label options for clients', 'opptiai-alt-text-generator'); ?> <span class="alttextai-coming-soon"><?php esc_html_e('(coming soon)', 'opptiai-alt-text-generator'); ?></span></li>
                            </ul>
                        </div>
                        
                        <div class="alttextai-plan-savings">
                            <?php esc_html_e('Save 15+ hours/month with automation', 'opptiai-alt-text-generator'); ?>
                        </div>
                        
                        <button type="button" 
                                class="alttextai-btn-success alttextai-btn-icon alttextai-plan-cta"
                                data-action="checkout-plan"
                                data-plan="agency"
                                data-price-id="<?php echo esc_attr($agency_price_id); ?>"
                                data-fallback-url="<?php echo esc_url($stripe_links['agency']); ?>"
                                aria-label="<?php esc_attr_e('Purchase Agency plan - Best value for agencies and professionals', 'opptiai-alt-text-generator'); ?>">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                <path d="M8 2L6 6H2L6 9L4 14L8 11L12 14L10 9L14 6H10L8 2Z" fill="currentColor"/>
                            </svg>
                            <span><?php esc_html_e('Upgrade to Agency', 'opptiai-alt-text-generator'); ?></span>
                        </button>
                    </div>
                </div>
                
                <!-- Bottom Row: Credits Pack (Full Width) -->
                <div class="alttextai-pricing-grid-bottom">
                    <div class="alttextai-plan-card alttextai-plan-card--credits">
                        <div class="alttextai-plan-card-content">
                            <div class="alttextai-plan-header">
                                <div class="alttextai-credits-header-left">
                                    <h3><?php esc_html_e('Credits Pack', 'opptiai-alt-text-generator'); ?></h3>
                                    <p class="alttextai-plan-value"><?php esc_html_e('Top up when you need more', 'opptiai-alt-text-generator'); ?></p>
                                </div>
                                <div class="alttextai-plan-price">
                                    <span class="alttextai-price-amount">£9.99</span>
                                    <span class="alttextai-price-period"><?php esc_html_e('one-time', 'opptiai-alt-text-generator'); ?></span>
                                </div>
                            </div>
                            
                            <div class="alttextai-plan-features alttextai-plan-features--horizontal">
                                <ul>
                                    <li>
                                        <span class="alttextai-feature-highlight"><?php esc_html_e('100', 'opptiai-alt-text-generator'); ?></span>
                                        <?php esc_html_e('AI-generated alt texts', 'opptiai-alt-text-generator'); ?>
                                    </li>
                                    <li><?php esc_html_e('Never expires - use anytime', 'opptiai-alt-text-generator'); ?></li>
                                    <li><?php esc_html_e('Works with any plan', 'opptiai-alt-text-generator'); ?></li>
                                    <li><?php esc_html_e('Perfect for occasional use', 'opptiai-alt-text-generator'); ?></li>
                                </ul>
                            </div>
                            
                            <button type="button" 
                                    class="alttextai-btn-secondary alttextai-btn-icon alttextai-plan-cta"
                                    data-action="checkout-plan"
                                    data-plan="credits"
                                    data-price-id="<?php echo esc_attr($credits_price_id); ?>"
                                    data-fallback-url="<?php echo esc_url($stripe_links['credits']); ?>"
                                    aria-label="<?php esc_attr_e('Purchase credit pack - 100 AI-generated alt texts', 'opptiai-alt-text-generator'); ?>">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="M8 2L6 6H2L6 9L4 14L8 11L12 14L10 9L14 6H10L8 2Z" fill="currentColor"/>
                                </svg>
                                <span><?php esc_html_e('Buy Credits', 'opptiai-alt-text-generator'); ?></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
                
                <!-- Trust Elements -->
                <div class="alttextai-upgrade-modal__footer">
                    <div class="alttextai-trust-elements">
                        <div class="alttextai-trust-item">
                            <span class="alttextai-trust-icon">🔒</span>
                            <span class="alttextai-trust-text"><?php esc_html_e('Secure checkout via Stripe', 'opptiai-alt-text-generator'); ?></span>
                        </div>
                        <div class="alttextai-trust-item">
                            <span class="alttextai-trust-icon">✅</span>
                            <span class="alttextai-trust-text"><?php esc_html_e('Cancel anytime', 'opptiai-alt-text-generator'); ?></span>
                        </div>
                        <div class="alttextai-trust-item">
                            <span class="alttextai-trust-icon">⚡</span>
                            <span class="alttextai-trust-text"><?php esc_html_e('Instant activation', 'opptiai-alt-text-generator'); ?></span>
                        </div>
                    </div>
                </div>
        </div>
    </div>
</div>
