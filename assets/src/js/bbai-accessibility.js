/**
 * BeepBeep AI Accessibility Enhancements
 * ARIA labels, focus management, keyboard navigation, high contrast mode
 */

(function() {
    'use strict';

    const bbaiAccessibility = {
        focusHistory: [],
        skipLinks: [],
        highContrastMode: false,

        init: function() {
            this.addARIALabels();
            this.setupFocusManagement();
            this.setupKeyboardNavigation();
            this.setupSkipLinks();
            this.detectHighContrast();
            this.setupReducedMotion();
            this.announceDynamicContent();
        },

        /**
         * Add ARIA labels to elements missing them
         */
        addARIALabels: function() {
            // Buttons without labels
            const buttons = document.querySelectorAll('button:not([aria-label]):not([aria-labelledby])');
            buttons.forEach(btn => {
                const text = btn.textContent.trim();
                if (text) {
                    btn.setAttribute('aria-label', text);
                } else {
                    // Try to infer from icon or data attributes
                    const icon = btn.querySelector('svg');
                    const action = btn.getAttribute('data-action');
                    if (action) {
                        btn.setAttribute('aria-label', this.getActionLabel(action));
                    } else if (icon) {
                        btn.setAttribute('aria-label', 'Button');
                    }
                }
            });

            // Images without alt text
            const images = document.querySelectorAll('img:not([alt])');
            images.forEach(img => {
                img.setAttribute('alt', '');
                img.setAttribute('role', 'presentation');
            });

            // Form inputs
            const inputs = document.querySelectorAll('input:not([aria-label]):not([aria-labelledby])');
            inputs.forEach(input => {
                const label = input.closest('label') || document.querySelector(`label[for="${input.id}"]`);
                if (!label && input.placeholder) {
                    input.setAttribute('aria-label', input.placeholder);
                }
            });
        },

        /**
         * Get action label from data-action attribute
         */
        getActionLabel: function(action) {
            const labels = {
                'show-upgrade-modal': 'Open upgrade modal',
                'generate-missing': 'Generate Missing',
                'regenerate-all': 'Re-optimise All',
                'regenerate-single': 'Regenerate alt text for this image',
                'open-billing-portal': 'Open billing portal',
                'logout': 'Log out',
                'show-auth-modal': 'Show authentication modal'
            };
            return labels[action] || action.replace(/-/g, ' ');
        },

        /**
         * Setup focus management
         */
        setupFocusManagement: function() {
            // Track focus history
            document.addEventListener('focusin', (e) => {
                this.focusHistory.push(e.target);
                if (this.focusHistory.length > 10) {
                    this.focusHistory.shift();
                }
            });

            // Trap focus in modals
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Tab') {
                    const modal = document.querySelector('.bbai-modal-backdrop:not([style*="display: none"])');
                    if (modal) {
                        this.trapFocusInModal(modal, e);
                    }
                }
            });

            // Return focus when modal closes
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('bbai-modal-backdrop') || 
                    e.target.closest('.bbai-modal-close')) {
                    setTimeout(() => {
                        this.returnFocus();
                    }, 100);
                }
            });
        },

        /**
         * Trap focus within modal
         */
        trapFocusInModal: function(modal, e) {
            const focusableElements = modal.querySelectorAll(
                'a[href], button:not([disabled]), textarea:not([disabled]), ' +
                'input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
            );
            
            if (focusableElements.length === 0) return;

            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];

            if (e.shiftKey && document.activeElement === firstElement) {
                e.preventDefault();
                lastElement.focus();
            } else if (!e.shiftKey && document.activeElement === lastElement) {
                e.preventDefault();
                firstElement.focus();
            }
        },

        /**
         * Return focus to previous element
         */
        returnFocus: function() {
            if (this.focusHistory.length > 0) {
                const previousElement = this.focusHistory[this.focusHistory.length - 1];
                if (previousElement && document.body.contains(previousElement)) {
                    previousElement.focus();
                }
            }
        },

        /**
         * Setup keyboard navigation
         */
        setupKeyboardNavigation: function() {
            // Keyboard shortcuts
            document.addEventListener('keydown', (e) => {
                // Escape to close modals
                if (e.key === 'Escape') {
                    const modal = document.querySelector('.bbai-modal-backdrop:not([style*="display: none"])');
                    if (modal) {
                        const closeBtn = modal.querySelector('.bbai-modal-close, [data-action="close-modal"]');
                        if (closeBtn) {
                            closeBtn.click();
                        }
                    }
                }

                // Enter/Space on buttons
                if ((e.key === 'Enter' || e.key === ' ') && e.target.tagName === 'BUTTON') {
                    e.preventDefault();
                    e.target.click();
                }
            });

            // Arrow key navigation for lists
            const lists = document.querySelectorAll('.bbai-list, .bbai-table tbody');
            lists.forEach(list => {
                list.addEventListener('keydown', (e) => {
                    const items = Array.from(list.querySelectorAll('[tabindex="0"], tr, li'));
                    const currentIndex = items.indexOf(e.target);

                    if (e.key === 'ArrowDown' && currentIndex < items.length - 1) {
                        e.preventDefault();
                        items[currentIndex + 1].focus();
                    } else if (e.key === 'ArrowUp' && currentIndex > 0) {
                        e.preventDefault();
                        items[currentIndex - 1].focus();
                    }
                });
            });
        },

        /**
         * Setup skip links
         */
        setupSkipLinks: function() {
            const skipLink = document.createElement('a');
            skipLink.href = '#bbai-main-content';
            skipLink.textContent = 'Skip to main content';
            skipLink.className = 'bbai-skip-link';
            skipLink.setAttribute('aria-label', 'Skip to main content');
            document.body.insertBefore(skipLink, document.body.firstChild);

            // Ensure main content has ID
            const mainContent = document.querySelector('.bbai-container, main, #bbai-main-content');
            if (mainContent && !mainContent.id) {
                mainContent.id = 'bbai-main-content';
            }
        },

        /**
         * Detect high contrast mode
         */
        detectHighContrast: function() {
            // Check for Windows High Contrast Mode
            if (window.matchMedia) {
                const highContrastQuery = window.matchMedia('(prefers-contrast: high)');
                this.highContrastMode = highContrastQuery.matches;
                
                highContrastQuery.addEventListener('change', (e) => {
                    this.highContrastMode = e.matches;
                    document.body.classList.toggle('bbai-high-contrast', e.matches);
                });
            }

            // Check for forced colors mode
            if (window.matchMedia) {
                const forcedColorsQuery = window.matchMedia('(forced-colors: active)');
                if (forcedColorsQuery.matches) {
                    document.body.classList.add('bbai-forced-colors');
                }
            }
        },

        /**
         * Setup reduced motion support
         */
        setupReducedMotion: function() {
            if (window.matchMedia) {
                const reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
                if (reducedMotionQuery.matches) {
                    document.body.classList.add('bbai-reduced-motion');
                    
                    // Disable animations
                    const style = document.createElement('style');
                    style.textContent = `
                        .bbai-reduced-motion *,
                        .bbai-reduced-motion *::before,
                        .bbai-reduced-motion *::after {
                            animation-duration: 0.01ms !important;
                            animation-iteration-count: 1 !important;
                            transition-duration: 0.01ms !important;
                        }
                    `;
                    document.head.appendChild(style);
                }
            }
        },

        /**
         * Announce dynamic content changes
         */
        announceDynamicContent: function() {
            // Create live region for announcements
            const liveRegion = document.createElement('div');
            liveRegion.id = 'bbai-live-region';
            liveRegion.className = 'bbai-sr-only';
            liveRegion.setAttribute('aria-live', 'polite');
            liveRegion.setAttribute('aria-atomic', 'true');
            document.body.appendChild(liveRegion);

            // Listen for custom events
            document.addEventListener('bbai:announce', (e) => {
                const message = e.detail?.message || '';
                if (message) {
                    liveRegion.textContent = message;
                    setTimeout(() => {
                        liveRegion.textContent = '';
                    }, 1000);
                }
            });
        },

        /**
         * Announce message to screen readers
         */
        announce: function(message) {
            const event = new CustomEvent('bbai:announce', {
                detail: { message: message }
            });
            document.dispatchEvent(event);
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => bbaiAccessibility.init());
    } else {
        bbaiAccessibility.init();
    }

    // Expose globally
    window.bbaiAccessibility = bbaiAccessibility;
})();
