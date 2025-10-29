<?php
/**
 * Upgrade Modal Template
 * Uses JavaScript to bypass WordPress CSP restrictions
 */

// Get the real Stripe payment links
$stripe_links = [
    'pro' => 'https://buy.stripe.com/dRm28s4rc5Raf0GbY77ss02',
    'agency' => 'https://buy.stripe.com/28E14og9U0wQ19Q4vF7ss01',
    'credits' => 'https://buy.stripe.com/6oU9AUf5Q2EYaKq0fp7ss00'
];

$pro_url = $stripe_links['pro'];
$agency_url = $stripe_links['agency'];
$credits_url = $stripe_links['credits'];

// Check if user is authenticated
$is_authenticated = $this->api_client->is_authenticated();
?>

<div id="alttextai-upgrade-modal" class="alttextai-modal-backdrop" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="alttextai-upgrade-modal-title" aria-describedby="alttextai-upgrade-modal-desc">
    <div class="alttextai-upgrade-modal__content">
        <div class="alttextai-upgrade-modal__header">
            <div class="alttextai-upgrade-modal__header-content">
                <h2 id="alttextai-upgrade-modal-title"><?php esc_html_e('Unlock Unlimited SEO AI Alt Text Generation', 'ai-alt-gpt'); ?></h2>
                <p class="alttextai-upgrade-modal__subtitle" id="alttextai-upgrade-modal-desc"><?php esc_html_e('Boost Google image rankings, improve SEO, and save hours of manual work', 'ai-alt-gpt'); ?></p>
            </div>
            <button type="button" class="alttextai-modal-close" onclick="alttextaiCloseModal();" aria-label="<?php esc_attr_e('Close upgrade modal', 'ai-alt-gpt'); ?>">
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
                    <p><strong><?php esc_html_e('New to SEO AI Alt Text?', 'ai-alt-gpt'); ?></strong> <?php esc_html_e('You\'ll create your account during checkout. Existing users can', 'ai-alt-gpt'); ?> <a href="#" onclick="triggerSignIn(); return false;"><?php esc_html_e('sign in here', 'ai-alt-gpt'); ?></a>.</p>
                </div>
            <?php endif; ?>
            
            <!-- Pricing Plans -->
            <div class="alttextai-pricing-container">
                <!-- Top Row: Pro & Agency Plans -->
                <div class="alttextai-pricing-grid-top">
                    <!-- Pro Plan -->
                    <div class="alttextai-plan-card alttextai-plan-card--pro">
                        <div class="alttextai-plan-header">
                            <h3><?php esc_html_e('Pro Plan', 'ai-alt-gpt'); ?></h3>
                            <div class="alttextai-plan-price">
                                <span class="alttextai-price-amount">£12.99</span>
                                <span class="alttextai-price-period"><?php esc_html_e('/month', 'ai-alt-gpt'); ?></span>
                            </div>
                            <p class="alttextai-plan-value"><?php esc_html_e('Perfect for growing websites', 'ai-alt-gpt'); ?></p>
                        </div>
                        
                        <div class="alttextai-plan-features">
                            <ul>
                                <li>
                                    <span class="alttextai-feature-highlight"><?php esc_html_e('1,000', 'ai-alt-gpt'); ?></span>
                                    <?php esc_html_e('AI-generated alt texts per month', 'ai-alt-gpt'); ?>
                                </li>
                                <li><?php esc_html_e('✅ Advanced quality scoring for SEO', 'ai-alt-gpt'); ?></li>
                                <li><?php esc_html_e('⚡ Bulk process unlimited images', 'ai-alt-gpt'); ?></li>
                                <li><?php esc_html_e('🎯 Priority support & assistance', 'ai-alt-gpt'); ?></li>
                                <li><?php esc_html_e('🔌 Full API access included', 'ai-alt-gpt'); ?></li>
                            </ul>
                        </div>
                        
                        <button type="button" 
                                class="alttextai-btn-primary alttextai-btn-icon alttextai-plan-cta"
                                onclick="openStripeLink('<?php echo esc_js($pro_url); ?>'); return false;"
                                aria-label="<?php esc_attr_e('Purchase Pro plan - Unlimited monthly generations', 'ai-alt-gpt'); ?>">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                <path d="M8 2L6 6H2L6 9L4 14L8 11L12 14L10 9L14 6H10L8 2Z" fill="currentColor"/>
                            </svg>
                            <span><?php esc_html_e('Get Started with Pro', 'ai-alt-gpt'); ?></span>
                        </button>
                    </div>
                    
                    <!-- Agency Plan -->
                    <div class="alttextai-plan-card alttextai-plan-card--featured alttextai-plan-card--agency">
                        <div class="alttextai-plan-badge"><?php esc_html_e('MOST POPULAR', 'ai-alt-gpt'); ?></div>
                        <div class="alttextai-plan-header">
                            <h3><?php esc_html_e('Agency Plan', 'ai-alt-gpt'); ?></h3>
                            <div class="alttextai-plan-price">
                                <span class="alttextai-price-amount">£49.99</span>
                                <span class="alttextai-price-period"><?php esc_html_e('/month', 'ai-alt-gpt'); ?></span>
                            </div>
                            <p class="alttextai-plan-value"><?php esc_html_e('Best value for agencies & professionals', 'ai-alt-gpt'); ?></p>
                        </div>
                        
                        <div class="alttextai-plan-features">
                            <ul>
                                <li>
                                    <span class="alttextai-feature-highlight"><?php esc_html_e('10,000', 'ai-alt-gpt'); ?></span>
                                    <?php esc_html_e('AI-generated alt texts per month', 'ai-alt-gpt'); ?>
                                </li>
                                <li><?php esc_html_e('✅ Advanced quality scoring for SEO', 'ai-alt-gpt'); ?></li>
                                <li><?php esc_html_e('⚡ Bulk process unlimited images', 'ai-alt-gpt'); ?></li>
                                <li><?php esc_html_e('🎯 Priority support & assistance', 'ai-alt-gpt'); ?></li>
                                <li><?php esc_html_e('🔌 Full API access included', 'ai-alt-gpt'); ?></li>
                                <li><?php esc_html_e('🎨 White-label options for clients', 'ai-alt-gpt'); ?></li>
                            </ul>
                        </div>
                        
                        <div class="alttextai-plan-savings">
                            <?php esc_html_e('💡 Save 15+ hours/month with automation', 'ai-alt-gpt'); ?>
                        </div>
                        
                        <button type="button" 
                                class="alttextai-btn-success alttextai-btn-icon alttextai-plan-cta"
                                onclick="openStripeLink('<?php echo esc_js($agency_url); ?>'); return false;"
                                aria-label="<?php esc_attr_e('Purchase Agency plan - Best value for agencies and professionals', 'ai-alt-gpt'); ?>">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                <path d="M8 2L6 6H2L6 9L4 14L8 11L12 14L10 9L14 6H10L8 2Z" fill="currentColor"/>
                            </svg>
                            <span><?php esc_html_e('Upgrade to Agency', 'ai-alt-gpt'); ?></span>
                        </button>
                    </div>
                </div>
                
                <!-- Bottom Row: Credits Pack (Full Width) -->
                <div class="alttextai-pricing-grid-bottom">
                    <div class="alttextai-plan-card alttextai-plan-card--credits">
                        <div class="alttextai-plan-card-content">
                            <div class="alttextai-plan-header">
                                <div class="alttextai-credits-header-left">
                                    <h3><?php esc_html_e('Credits Pack', 'ai-alt-gpt'); ?></h3>
                                    <p class="alttextai-plan-value"><?php esc_html_e('Top up when you need more', 'ai-alt-gpt'); ?></p>
                                </div>
                                <div class="alttextai-plan-price">
                                    <span class="alttextai-price-amount">£9.99</span>
                                    <span class="alttextai-price-period"><?php esc_html_e('one-time', 'ai-alt-gpt'); ?></span>
                                </div>
                            </div>
                            
                            <div class="alttextai-plan-features alttextai-plan-features--horizontal">
                                <ul>
                                    <li>
                                        <span class="alttextai-feature-highlight"><?php esc_html_e('100', 'ai-alt-gpt'); ?></span>
                                        <?php esc_html_e('AI-generated alt texts', 'ai-alt-gpt'); ?>
                                    </li>
                                    <li><?php esc_html_e('⏰ Never expires - use anytime', 'ai-alt-gpt'); ?></li>
                                    <li><?php esc_html_e('➕ Works with any plan', 'ai-alt-gpt'); ?></li>
                                    <li><?php esc_html_e('💰 Perfect for occasional use', 'ai-alt-gpt'); ?></li>
                                </ul>
                            </div>
                            
                            <button type="button" 
                                    class="alttextai-btn-secondary alttextai-btn-icon alttextai-plan-cta"
                                    onclick="openStripeLink('<?php echo esc_js($credits_url); ?>'); return false;"
                                    aria-label="<?php esc_attr_e('Purchase credit pack - 100 AI-generated alt texts', 'ai-alt-gpt'); ?>">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="M8 2L6 6H2L6 9L4 14L8 11L12 14L10 9L14 6H10L8 2Z" fill="currentColor"/>
                                </svg>
                                <span><?php esc_html_e('Buy Credits', 'ai-alt-gpt'); ?></span>
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
                            <span class="alttextai-trust-text"><?php esc_html_e('Secure checkout via Stripe', 'ai-alt-gpt'); ?></span>
                        </div>
                        <div class="alttextai-trust-item">
                            <span class="alttextai-trust-icon">✅</span>
                            <span class="alttextai-trust-text"><?php esc_html_e('Cancel anytime', 'ai-alt-gpt'); ?></span>
                        </div>
                        <div class="alttextai-trust-item">
                            <span class="alttextai-trust-icon">⚡</span>
                            <span class="alttextai-trust-text"><?php esc_html_e('Instant activation', 'ai-alt-gpt'); ?></span>
                        </div>
                    </div>
                </div>
        </div>
    </div>
</div>

<script>
function openStripeLink(url) {
    // Debug: console.log('[AltText AI] Opening Stripe link:', url);
    window.open(url, '_blank');
}

function alttextaiCloseModal() {
    const modal = document.getElementById('alttextai-upgrade-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function alttextaiShowModal() {
    const modal = document.getElementById('alttextai-upgrade-modal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

function triggerSignIn() {
    // Debug: console.log('[AltText AI] Triggering sign in...');
    
    // Close upgrade modal first
    alttextaiCloseModal();
    
    // Try multiple methods to trigger auth modal
    setTimeout(function() {
        // Debug: console.log('[AltText AI] Attempting to open auth modal...');
        
        // Method 1: Try to find and click the existing auth button
        const authBtn = document.getElementById('alttextai-show-auth-login-btn');
        if (authBtn) {
            // Debug: console.log('[AltText AI] Found auth button, clicking it');
            authBtn.click();
            return;
        }
        
        // Method 2: Try to find and click the auth banner button
        const bannerBtn = document.getElementById('alttextai-show-auth-banner-btn');
        if (bannerBtn) {
            // Debug: console.log('[AltText AI] Found banner button, clicking it');
            bannerBtn.click();
            return;
        }
        
        // Method 3: Try to trigger auth modal directly
        if (typeof window.authModal !== 'undefined' && window.authModal && typeof window.authModal.show === 'function') {
            // Debug: console.log('[AltText AI] Using authModal.show()');
            window.authModal.show();
            return;
        }
        
        // Method 4: Try to find auth modal and show it
        const authModal = document.getElementById('alttext-auth-modal');
        if (authModal) {
            // Debug: console.log('[AltText AI] Found auth modal, showing it');
            authModal.style.display = 'block';
            return;
        }
        
        // Method 5: Create a simple auth modal if none exists
        // Debug: console.log('[AltText AI] Creating simple auth modal');
        createSimpleAuthModal();
        
    }, 100);
}

function createSimpleAuthModal() {
    // Create a simple auth modal
    const modalHTML = `
        <div id="simple-auth-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999999; display: flex; align-items: center; justify-content: center;">
            <div style="background: white; padding: 30px; border-radius: 8px; max-width: 400px; width: 90%;">
                <h3 style="margin-top: 0;">Sign In Required</h3>
                <p>Please use the sign-in button in the dashboard to authenticate.</p>
                <div style="text-align: center; margin-top: 20px;">
                    <button onclick="document.getElementById('simple-auth-modal').remove();" style="padding: 10px 20px; background: #0073aa; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        Close
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

// Close modal when clicking backdrop (but not content)
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('alttextai-upgrade-modal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                alttextaiCloseModal();
            }
        });
        
        // Prevent clicks on content from closing modal
        const content = modal.querySelector('.alttextai-upgrade-modal__content');
        if (content) {
            content.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
    }
});
</script>

<style>
.alttextai-auth-required {
    text-align: center;
    padding: 40px 20px;
    background: #f9f9f9;
    border-radius: 8px;
    border: 1px solid #e1e5e9;
}

.alttextai-auth-required__icon {
    font-size: 48px;
    margin-bottom: 16px;
}

.alttextai-auth-required h3 {
    margin: 0 0 12px 0;
    font-size: 24px;
    color: #1a1a1a;
}

.alttextai-auth-required p {
    margin: 0 0 24px 0;
    color: #666;
    font-size: 16px;
    line-height: 1.5;
}

.alttextai-auth-required__actions {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
}

.alttextai-auth-required__actions .alttextai-upgrade-button {
    min-width: 120px;
}
</style>
