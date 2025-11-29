<?php
/**
 * Out of Credits Modal Template
 * Shows when user has run out of credits (credits = 0)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Get dashboard URL for Buy Credits link
$dashboard_url = '';
// Default dashboard URL
$dashboard_base_url = 'https://app.optti.ai';
$dashboard_url = $dashboard_base_url . '/credits?ref=plugin&plugin=beepbeep';

// If we have a configured dashboard URL, use it
if (defined('BBAI_DASHBOARD_URL') && BBAI_DASHBOARD_URL) {
    $dashboard_base_url = rtrim(BBAI_DASHBOARD_URL, '/');
    $dashboard_url = $dashboard_base_url . '/credits?ref=plugin&plugin=beepbeep';
}
?>

<div id="bbai-out-of-credits-modal" class="bbai-modal-backdrop" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="bbai-out-of-credits-modal-title" aria-describedby="bbai-out-of-credits-modal-desc">
    <div class="bbai-upgrade-modal__content" style="max-width: 500px;">
        <!-- Header -->
        <div class="bbai-upgrade-modal__header">
            <div class="bbai-upgrade-modal__header-content">
                <h2 id="bbai-out-of-credits-modal-title"><?php esc_html_e("You've run out of credits", 'beepbeep-ai-alt-text-generator'); ?></h2>
                <p class="bbai-upgrade-modal__subtitle" id="bbai-out-of-credits-modal-desc">
                    <?php esc_html_e('Your plan does not include unlimited usage. You can buy a credit pack to continue generating alt text.', 'beepbeep-ai-alt-text-generator'); ?>
                </p>
            </div>
            <button type="button" class="bbai-modal-close" onclick="if(typeof bbaiCloseOutOfCreditsModal==='function'){bbaiCloseOutOfCreditsModal();}else if(typeof alttextaiCloseModal==='function'){alttextaiCloseModal();}" aria-label="<?php esc_attr_e('Close modal', 'beepbeep-ai-alt-text-generator'); ?>">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M15 5L5 15M5 5l10 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
        </div>
        
        <div class="bbai-upgrade-modal__body" style="padding: 24px;">
            <div style="text-align: center;">
                <p style="margin-bottom: 24px; color: #6b7280;">
                    <?php esc_html_e('Purchase credit packs to continue generating alt text for your images.', 'beepbeep-ai-alt-text-generator'); ?>
                </p>
            </div>
        </div>

        <!-- Footer -->
        <div class="bbai-upgrade-modal__footer" style="padding: 20px 24px; border-top: 1px solid #E5E7EB; display: flex; gap: 12px; justify-content: flex-end; background: #FFFFFF;">
            <button type="button" 
                    class="button button-secondary" 
                    onclick="if(typeof bbaiCloseOutOfCreditsModal==='function'){bbaiCloseOutOfCreditsModal();}else if(typeof alttextaiCloseModal==='function'){alttextaiCloseModal();}"
                    aria-label="<?php esc_attr_e('Cancel', 'beepbeep-ai-alt-text-generator'); ?>">
                <?php esc_html_e('Cancel', 'beepbeep-ai-alt-text-generator'); ?>
            </button>
            <button type="button" 
                    class="button button-primary" 
                    id="bbai-buy-credits-website-btn"
                    data-dashboard-url="<?php echo esc_attr($dashboard_url); ?>"
                    aria-label="<?php esc_attr_e('Buy Credits (opens website)', 'beepbeep-ai-alt-text-generator'); ?>">
                <?php esc_html_e('Buy Credits (opens website)', 'beepbeep-ai-alt-text-generator'); ?>
            </button>
        </div>
    </div>
</div>

<script>
(function($) {
    'use strict';
    
    /**
     * Open out-of-credits modal
     */
    if (typeof window.bbai_openOutOfCreditsModal !== 'function') {
        window.bbai_openOutOfCreditsModal = function() {
            const modal = document.getElementById('bbai-out-of-credits-modal');
            if (modal) {
                modal.style.display = 'flex';
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
                
                // Log analytics event
                if (typeof window.logEvent === 'function') {
                    window.logEvent('out_of_credits_modal_open', {
                        source: 'no_access_error'
                    });
                }
            }
        };
    }
    
    /**
     * Close out-of-credits modal
     */
    if (typeof window.bbaiCloseOutOfCreditsModal !== 'function') {
        window.bbaiCloseOutOfCreditsModal = function() {
            const modal = document.getElementById('bbai-out-of-credits-modal');
            if (modal) {
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }
        };
    }
    
    // Handle Buy Credits button click
    $(document).ready(function() {
        $(document).on('click', '#bbai-buy-credits-website-btn', function(e) {
            e.preventDefault();
            
            // Get dashboard URL from button data attribute or fallback
            let dashboardUrl = $(this).attr('data-dashboard-url') || '';
            
            // Fallback: try to get from opttiApi or other sources
            if (!dashboardUrl || dashboardUrl === '') {
                if (typeof opttiApi !== 'undefined' && opttiApi.dashboardUrl) {
                    dashboardUrl = opttiApi.dashboardUrl;
                } else if (typeof window.bbai_ajax !== 'undefined' && window.bbai_ajax.dashboard_url) {
                    dashboardUrl = window.bbai_ajax.dashboard_url;
                } else {
                    dashboardUrl = 'https://app.optti.ai';
                }
            }
            
            // Ensure URL ends with /credits?ref=plugin&plugin=beepbeep
            const baseUrl = dashboardUrl.replace(/\/credits.*$/, '').replace(/\/$/, '');
            const buyCreditsUrl = baseUrl + '/credits?ref=plugin&plugin=beepbeep';
            
            // Log analytics event
            if (typeof window.logEvent === 'function') {
                window.logEvent('buy_credits_clicked', {
                    source: 'out_of_credits_modal',
                    url: buyCreditsUrl
                });
            }
            
            // Open Buy Credits page in new tab
            window.open(buyCreditsUrl, '_blank', 'noopener,noreferrer');
            
            // Close modal
            if (typeof window.bbaiCloseOutOfCreditsModal === 'function') {
                window.bbaiCloseOutOfCreditsModal();
            }
        });
        
        // Handle Cancel button
        $(document).on('click', '#bbai-out-of-credits-modal .bbai-modal-close, #bbai-out-of-credits-modal .button-secondary', function(e) {
            e.preventDefault();
            if (typeof window.bbaiCloseOutOfCreditsModal === 'function') {
                window.bbaiCloseOutOfCreditsModal();
            }
        });
        
        // Close modal on overlay click
        $(document).on('click', '#bbai-out-of-credits-modal.bbai-modal-backdrop', function(e) {
            if (e.target === this) {
                if (typeof window.bbaiCloseOutOfCreditsModal === 'function') {
                    window.bbaiCloseOutOfCreditsModal();
                }
            }
        });
    });
})(jQuery);
</script>

