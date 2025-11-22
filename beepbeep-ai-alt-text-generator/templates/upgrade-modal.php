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

<div id="bbai-upgrade-modal" class="bbai-modal-backdrop" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="bbai-upgrade-modal-title" aria-describedby="bbai-upgrade-modal-desc">
    <div class="bbai-upgrade-modal__content">
        <div class="bbai-upgrade-modal__header">
            <div class="bbai-upgrade-modal__header-content">
                <h2 id="bbai-upgrade-modal-title"><?php esc_html_e('Unlock Unlimited SEO AI Alt Text Generation', 'beepbeep-ai-alt-text-generator'); ?></h2>
                <p class="bbai-upgrade-modal__subtitle" id="bbai-upgrade-modal-desc"><?php esc_html_e('Boost Google image rankings, improve SEO, and save hours of manual work', 'beepbeep-ai-alt-text-generator'); ?></p>
            </div>
            <button type="button" class="bbai-modal-close" onclick="if(typeof alttextaiCloseModal==='function'){alttextaiCloseModal();}else if(typeof bbaiCloseModal==='function'){bbaiCloseModal();}" aria-label="<?php esc_attr_e('Close upgrade modal', 'beepbeep-ai-alt-text-generator'); ?>">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M15 5L5 15M5 5l10 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
        </div>
        
        <div class="bbai-upgrade-modal__body">
            <?php if (!$is_authenticated) : ?>
                <!-- Not Authenticated - Show Pricing with Sign Up Note -->
                <div class="bbai-auth-notice">
                    <div class="bbai-auth-notice__icon">💡</div>
                    <p><strong><?php esc_html_e('New to SEO AI Alt Text?', 'beepbeep-ai-alt-text-generator'); ?></strong> <?php esc_html_e('You\'ll create your account during checkout. Existing users can', 'beepbeep-ai-alt-text-generator'); ?> <a href="#" onclick="triggerSignIn(); return false;"><?php esc_html_e('sign in here', 'beepbeep-ai-alt-text-generator'); ?></a>.</p>
                </div>
            <?php endif; ?>
            
            <!-- Pricing Plans -->
            <div class="bbai-pricing-container">
                <!-- Top Row: Pro & Agency Plans -->
                <div class="bbai-pricing-grid-top">
                    <!-- Pro Plan -->
                    <div class="bbai-plan-card bbai-plan-card--pro">
                        <div class="bbai-plan-header">
                            <h3><?php esc_html_e('Pro Plan', 'beepbeep-ai-alt-text-generator'); ?></h3>
                            <div class="bbai-plan-price">
                                <span class="bbai-price-amount">£12.99</span>
                                <span class="bbai-price-period"><?php esc_html_e('/month', 'beepbeep-ai-alt-text-generator'); ?></span>
                            </div>
                            <p class="bbai-plan-value"><?php esc_html_e('Perfect for growing websites', 'beepbeep-ai-alt-text-generator'); ?></p>
                        </div>
                        
                        <div class="bbai-plan-features">
                            <ul>
                                <li>
                                    <span class="bbai-feature-highlight"><?php esc_html_e('1,000', 'beepbeep-ai-alt-text-generator'); ?></span>
                                    <?php esc_html_e('AI-generated alt texts per month', 'beepbeep-ai-alt-text-generator'); ?>
                                </li>
                                <li><?php esc_html_e('Advanced quality scoring for SEO', 'beepbeep-ai-alt-text-generator'); ?></li>
                                <li><?php esc_html_e('Bulk process unlimited images', 'beepbeep-ai-alt-text-generator'); ?></li>
                                <li><?php esc_html_e('Priority support & assistance', 'beepbeep-ai-alt-text-generator'); ?></li>
                                <li><?php esc_html_e('Full API access included', 'beepbeep-ai-alt-text-generator'); ?></li>
                            </ul>
                        </div>
                        
                        <button type="button" 
                                class="bbai-btn-primary bbai-btn-icon bbai-plan-cta"
                                data-action="checkout-plan"
                                data-plan="pro"
                                data-price-id="<?php echo esc_attr($pro_price_id); ?>"
                                data-fallback-url="<?php echo esc_url($stripe_links['pro']); ?>"
                                aria-label="<?php esc_attr_e('Purchase Pro plan - Unlimited monthly generations', 'beepbeep-ai-alt-text-generator'); ?>">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                <path d="M8 2L6 6H2L6 9L4 14L8 11L12 14L10 9L14 6H10L8 2Z" fill="currentColor"/>
                            </svg>
                            <span><?php esc_html_e('Get Started with Pro', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </button>
                    </div>
                    
                    <!-- Agency Plan -->
                    <div class="bbai-plan-card bbai-plan-card--featured bbai-plan-card--agency">
                        <div class="bbai-plan-badge"><?php esc_html_e('MOST POPULAR', 'beepbeep-ai-alt-text-generator'); ?></div>
                        <div class="bbai-plan-header">
                            <h3><?php esc_html_e('Agency Plan', 'beepbeep-ai-alt-text-generator'); ?></h3>
                            <div class="bbai-plan-price">
                                <span class="bbai-price-amount">£49.99</span>
                                <span class="bbai-price-period"><?php esc_html_e('/month', 'beepbeep-ai-alt-text-generator'); ?></span>
                            </div>
                            <p class="bbai-plan-value"><?php esc_html_e('Best value for agencies & professionals', 'beepbeep-ai-alt-text-generator'); ?></p>
                        </div>
                        
                        <div class="bbai-plan-features">
                            <ul>
                                <li>
                                    <span class="bbai-feature-highlight"><?php esc_html_e('10,000', 'beepbeep-ai-alt-text-generator'); ?></span>
                                    <?php esc_html_e('AI-generated alt texts per month', 'beepbeep-ai-alt-text-generator'); ?>
                                </li>
                                <li><?php esc_html_e('Advanced quality scoring for SEO', 'beepbeep-ai-alt-text-generator'); ?></li>
                                <li><?php esc_html_e('Bulk process unlimited images', 'beepbeep-ai-alt-text-generator'); ?></li>
                                <li><?php esc_html_e('Priority support & assistance', 'beepbeep-ai-alt-text-generator'); ?> <span class="bbai-coming-soon"><?php esc_html_e('(coming soon)', 'beepbeep-ai-alt-text-generator'); ?></span></li>
                                <li><?php esc_html_e('Full API access included', 'beepbeep-ai-alt-text-generator'); ?> <span class="bbai-coming-soon"><?php esc_html_e('(coming soon)', 'beepbeep-ai-alt-text-generator'); ?></span></li>
                                <li><?php esc_html_e('White-label options for clients', 'beepbeep-ai-alt-text-generator'); ?> <span class="bbai-coming-soon"><?php esc_html_e('(coming soon)', 'beepbeep-ai-alt-text-generator'); ?></span></li>
                            </ul>
                        </div>
                        
                        <div class="bbai-plan-savings">
                            <?php esc_html_e('Save 15+ hours/month with automation', 'beepbeep-ai-alt-text-generator'); ?>
                        </div>
                        
                        <button type="button" 
                                class="bbai-btn-success bbai-btn-icon bbai-plan-cta"
                                data-action="checkout-plan"
                                data-plan="agency"
                                data-price-id="<?php echo esc_attr($agency_price_id); ?>"
                                data-fallback-url="<?php echo esc_url($stripe_links['agency']); ?>"
                                aria-label="<?php esc_attr_e('Purchase Agency plan - Best value for agencies and professionals', 'beepbeep-ai-alt-text-generator'); ?>">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                <path d="M8 2L6 6H2L6 9L4 14L8 11L12 14L10 9L14 6H10L8 2Z" fill="currentColor"/>
                            </svg>
                            <span><?php esc_html_e('Upgrade to Agency', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </button>
                    </div>
                </div>
                
                <!-- Bottom Row: Credits Pack (Full Width) -->
                <div class="bbai-pricing-grid-bottom">
                    <div class="bbai-plan-card bbai-plan-card--credits">
                        <div class="bbai-plan-card-content">
                            <div class="bbai-plan-header">
                                <div class="bbai-credits-header-left">
                                    <h3><?php esc_html_e('Credits Pack', 'beepbeep-ai-alt-text-generator'); ?></h3>
                                    <p class="bbai-plan-value"><?php esc_html_e('Top up when you need more', 'beepbeep-ai-alt-text-generator'); ?></p>
                                </div>
                                <div class="bbai-plan-price">
                                    <span class="bbai-price-amount">£9.99</span>
                                    <span class="bbai-price-period"><?php esc_html_e('one-time', 'beepbeep-ai-alt-text-generator'); ?></span>
                                </div>
                            </div>
                            
                            <div class="bbai-plan-features bbai-plan-features--horizontal">
                                <ul>
                                    <li>
                                        <span class="bbai-feature-highlight"><?php esc_html_e('100', 'beepbeep-ai-alt-text-generator'); ?></span>
                                        <?php esc_html_e('AI-generated alt texts', 'beepbeep-ai-alt-text-generator'); ?>
                                    </li>
                                    <li><?php esc_html_e('Never expires - use anytime', 'beepbeep-ai-alt-text-generator'); ?></li>
                                    <li><?php esc_html_e('Works with any plan', 'beepbeep-ai-alt-text-generator'); ?></li>
                                    <li><?php esc_html_e('Perfect for occasional use', 'beepbeep-ai-alt-text-generator'); ?></li>
                                </ul>
                            </div>
                        </div>
                        
                        <button type="button" 
                                class="bbai-btn-secondary bbai-btn-icon bbai-plan-cta"
                                data-action="checkout-plan"
                                data-plan="credits"
                                data-price-id="<?php echo esc_attr($credits_price_id); ?>"
                                data-fallback-url="<?php echo esc_url($stripe_links['credits']); ?>"
                                aria-label="<?php esc_attr_e('Purchase credit pack - 100 AI-generated alt texts', 'beepbeep-ai-alt-text-generator'); ?>">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                <path d="M8 2L6 6H2L6 9L4 14L8 11L12 14L10 9L14 6H10L8 2Z" fill="currentColor"/>
                            </svg>
                            <span><?php esc_html_e('Buy Credits', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </button>
                    </div>
                </div>
            </div>
                
                <!-- Trust Elements -->
                <div class="bbai-upgrade-modal__footer">
                    <div class="bbai-trust-elements">
                        <div class="bbai-trust-item">
                            <span class="bbai-trust-icon">🔒</span>
                            <span class="bbai-trust-text"><?php esc_html_e('Secure checkout via Stripe', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                        <div class="bbai-trust-item">
                            <span class="bbai-trust-icon">✅</span>
                            <span class="bbai-trust-text"><?php esc_html_e('Cancel anytime', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                        <div class="bbai-trust-item">
                            <span class="bbai-trust-icon">⚡</span>
                            <span class="bbai-trust-text"><?php esc_html_e('Instant activation', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                    </div>
                </div>
        </div>
    </div>
</div>
