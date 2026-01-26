/**
 * Upgrade Modal
 * Show/hide upgrade modal and handle triggers
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

// Ensure global variables exist (may not be defined if this file loads before main bundle)
var alttextaiDebug = (typeof alttextaiDebug !== 'undefined') ? alttextaiDebug : ((typeof window !== 'undefined' && typeof window.bbai_ajax !== 'undefined' && window.bbai_ajax.debug) || false);
var bbaiApp = (typeof bbaiApp !== 'undefined') ? bbaiApp : {};

// Fallback upgrade selectors
var FALLBACK_UPGRADE_SELECTOR = [
    '.bbai-upgrade-link',
    '.bbai-upgrade-inline',
    '.bbai-upgrade-cta-card button',
    '.bbai-upgrade-cta-card a',
    '.bbai-pro-upsell-card button',
    '.bbai-upgrade-banner button',
    '.bbai-upgrade-banner a',
    '.bbai-hero-actions [data-cta="upgrade"]',
    '.bbai-upgrade-callout button',
    '.bbai-upgrade-callout a',
    '[data-upgrade-trigger="true"]'
].join(', ');

/**
 * Show upgrade modal
 */
function alttextaiShowModal() {
    if (alttextaiDebug) console.log('[AltText AI] alttextaiShowModal() called');
    var modal = document.getElementById('bbai-upgrade-modal');

    if (!modal) {
        console.error('[AltText AI] Upgrade modal element not found in DOM!');
        // Try to find it by class
        var byClass = document.querySelector('.bbai-modal-backdrop');
        if (byClass) {
            byClass.id = 'bbai-upgrade-modal';
            return alttextaiShowModal(); // Retry
        }
        if (window.bbaiModal) {
            window.bbaiModal.warning('Upgrade modal not found. Please refresh the page.');
        }
        return false;
    }

    // Force display with inline styles (most reliable)
    modal.style.cssText = 'display: flex !important; z-index: 999999 !important; position: fixed !important; inset: 0 !important; background-color: rgba(0,0,0,0.6) !important; align-items: center !important; justify-content: center !important; visibility: visible !important; opacity: 1 !important;';

    // Also force the modal content to be visible
    var modalContent = modal.querySelector('.bbai-upgrade-modal__content');
    if (modalContent) {
        modalContent.style.cssText = 'opacity: 1 !important; visibility: visible !important; transform: translateY(0) scale(1) !important;';
    }

    // Add active class for CSS consistency
    modal.classList.add('active');
    modal.classList.add('is-visible');
    modal.removeAttribute('aria-hidden');
    modal.setAttribute('aria-modal', 'true');
    document.body.style.overflow = 'hidden';
    if (document.documentElement) {
        document.documentElement.style.overflow = 'hidden';
    }

    // Focus the close button after animation starts
    setTimeout(function() {
        var closeBtn = modal.querySelector('.bbai-upgrade-modal__close, .bbai-modal-close, [data-action="close-modal"], button[aria-label*="Close"]');
        if (closeBtn && typeof closeBtn.focus === 'function') {
            closeBtn.focus();
        }
    }, 150);

    if (alttextaiDebug) console.log('[AltText AI] Modal should now be visible');
    return true;
}

/**
 * Close upgrade modal
 */
function alttextaiCloseModal() {
    var modal = document.getElementById('bbai-upgrade-modal');
    if (modal) {
        // Remove active class to trigger CSS transition
        modal.classList.remove('active');
        
        // Wait for animation to complete before hiding
        setTimeout(function() {
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        }, 300); // Match animation duration
        
        document.body.style.overflow = '';
    }
}

/**
 * Unified handler for upgrade triggers
 */
function handleUpgradeTrigger(event, triggerElement) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    try {
        if (typeof alttextaiShowModal === 'function') {
            alttextaiShowModal();
        } else {
            var modal = document.getElementById('bbai-upgrade-modal');
            if (modal) {
                modal.style.display = 'flex';
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }
        }
    } catch (err) {
        if (alttextaiDebug) console.error('[AltText AI] Error in handleUpgradeTrigger:', err);
    }
}

/**
 * Bind direct upgrade handlers
 */
function bindDirectUpgradeHandlers(targetSelector) {
    var selector = targetSelector || FALLBACK_UPGRADE_SELECTOR;
    if (!selector) return;

    var nodes = document.querySelectorAll(selector);
    if (!nodes || !nodes.length) return;

    nodes.forEach(function(el) {
        if (!el) return;
        if (el.dataset && el.dataset.upgradeBound === '1') return;

        el.addEventListener('click', function(event) {
            handleUpgradeTrigger(event, el);
        }, true);

        if (el.dataset) {
            el.dataset.upgradeBound = '1';
        }
    });
}

/**
 * Bind fallback listeners for upgrade CTAs
 */
function bindFallbackUpgradeTriggers() {
    if (!FALLBACK_UPGRADE_SELECTOR) return;

    document.addEventListener('click', function(event) {
        var fallbackTrigger = event.target && event.target.closest(FALLBACK_UPGRADE_SELECTOR);
        if (!fallbackTrigger) return;

        if (fallbackTrigger.closest('[data-action="show-upgrade-modal"]')) return;

        handleUpgradeTrigger(event, fallbackTrigger);
    }, true);
}

/**
 * Add data-action attributes to CTAs missing them
 */
function ensureUpgradeAttributes(targetSelector) {
    var selector = targetSelector || FALLBACK_UPGRADE_SELECTOR;
    if (!selector) return;

    var nodes = document.querySelectorAll(selector);
    if (!nodes || !nodes.length) return;

    nodes.forEach(function(el) {
        if (!el) return;
        if (!el.hasAttribute('data-action')) {
            el.setAttribute('data-action', 'show-upgrade-modal');
        }
        el.setAttribute('data-upgrade-trigger', 'true');
    });

    bindDirectUpgradeHandlers(selector);
}

/**
 * Observe DOM for future upgrade triggers
 */
function observeFutureUpgradeTriggers() {
    if (typeof MutationObserver === 'undefined' || !FALLBACK_UPGRADE_SELECTOR) return;

    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (!mutation.addedNodes || !mutation.addedNodes.length) return;

            mutation.addedNodes.forEach(function(node) {
                if (!node || node.nodeType !== 1) return;

                if (node.matches && node.matches(FALLBACK_UPGRADE_SELECTOR)) {
                    ensureUpgradeAttributes();
                } else if (node.querySelector) {
                    var matches = node.querySelector(FALLBACK_UPGRADE_SELECTOR);
                    if (matches) {
                        ensureUpgradeAttributes();
                    }
                }
            });
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });
}

/**
 * Check if modal exists
 */
function checkModalExists() {
    var modal = document.getElementById('bbai-upgrade-modal');
    if (!modal) {
        console.warn('[AltText AI] Upgrade modal not found in DOM. Make sure upgrade-modal.php is included.');
    } else {
        if (alttextaiDebug) console.log('[AltText AI] Upgrade modal found in DOM');
    }
}

// Make functions available globally
window.alttextaiShowModal = alttextaiShowModal;
window.alttextaiCloseModal = alttextaiCloseModal;
window.handleUpgradeTrigger = handleUpgradeTrigger;
window.bindFallbackUpgradeTriggers = bindFallbackUpgradeTriggers;
window.ensureUpgradeAttributes = ensureUpgradeAttributes;
window.observeFutureUpgradeTriggers = observeFutureUpgradeTriggers;

// Add to bbaiApp namespace
bbaiApp.showModal = alttextaiShowModal;
bbaiApp.closeModal = alttextaiCloseModal;

// Test function
window.testUpgradeModal = function() {
    console.log('=== Testing Upgrade Modal ===');
    var modal = document.getElementById('bbai-upgrade-modal');
    console.log('Modal element:', modal);
    if (modal) {
        console.log('Modal HTML:', modal.outerHTML.substring(0, 200));
        alttextaiShowModal();
    } else {
        console.error('Modal not found!');
    }
};
