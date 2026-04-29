/**
 * BeepBeep AI Tooltip System
 * Handles tooltips with data-bbai-tooltip attribute
 */

(function() {
    'use strict';

    const i18n = window.wp && window.wp.i18n ? window.wp.i18n : null;
    const __ = i18n && typeof i18n.__ === 'function' ? i18n.__ : (text) => text;

    const bbaiTooltips = {
        tooltips: new Map(),
        shortcutsModal: null,
        mutationObserver: null,

        init: function() {
            this.initTooltips();
            this.initShortcutsModal();
            this.initKeyboardShortcuts();
            this.initMutationObserver();
        },

        /**
         * Initialize tooltip system
         */
        initTooltips: function() {
            // Handle tooltips on elements with data-bbai-tooltip
            document.addEventListener('mouseenter', this.handleTooltipEnter.bind(this), true);
            document.addEventListener('mouseleave', this.handleTooltipLeave.bind(this), true);
            document.addEventListener('focus', this.handleTooltipFocus.bind(this), true);
            document.addEventListener('blur', this.handleTooltipBlur.bind(this), true);
            
            // Hide tooltips on mousedown for interactive elements (immediate feedback)
            document.addEventListener('mousedown', this.handleMouseDown.bind(this), true);
            
            // Hide tooltips on click anywhere (except on tooltip triggers)
            document.addEventListener('click', this.handleDocumentClick.bind(this), true);
            
            // Hide tooltips on scroll
            document.addEventListener('scroll', this.handleScroll.bind(this), true);
            
            // Hide tooltips when mouse leaves the window
            document.addEventListener('mouseout', this.handleMouseOut.bind(this), true);
        },

        /**
         * Handle tooltip on mouse enter
         */
        handleTooltipEnter: function(e) {
            // Check if e.target exists and is a valid DOM element
            if (!e || !e.target || typeof e.target.closest !== 'function') {
                return;
            }

            const element = e.target.closest('[data-bbai-tooltip]');
            if (!element) return;

            // Cancel any pending hide for this element (prevents flicker
            // when moving between child elements of the same trigger)
            if (element._bbaiHideTimer) {
                clearTimeout(element._bbaiHideTimer);
                element._bbaiHideTimer = null;
                // Tooltip is already showing for this element, no action needed
                if (this.tooltips.has(element)) {
                    return;
                }
            }

            const text = element.getAttribute('data-bbai-tooltip');
            if (!text) return;

            const position = element.getAttribute('data-bbai-tooltip-position') || 'top';
            this.showTooltip(element, text, position);
        },

        /**
         * Handle tooltip on mouse leave
         */
        handleTooltipLeave: function(e) {
            // Check if e.target exists and is a valid DOM element
            if (!e || !e.target || typeof e.target.closest !== 'function') {
                return;
            }

            // Check if mouse left the tooltip itself
            const tooltip = e.target.closest('.bbai-tooltip');
            if (tooltip) {
                // Find the element that triggered this tooltip
                for (const [element, tooltipEl] of this.tooltips.entries()) {
                    if (tooltipEl === tooltip) {
                        this.scheduleHide(element);
                        return;
                    }
                }
            }

            // Check if mouse left the element with tooltip
            const element = e.target.closest('[data-bbai-tooltip]');
            if (element) {
                const relatedTarget = e.relatedTarget;
                
                // If relatedTarget is null, mouse left the window - hide tooltip immediately
                if (!relatedTarget) {
                    this.hideTooltip(element);
                    return;
                }
                
                // Only hide if we're not moving to the tooltip
                if (relatedTarget.closest('.bbai-tooltip')) {
                    return; // Don't hide if moving to tooltip
                }
                // Only hide if we're not moving to another child of the same trigger
                if (relatedTarget.closest('[data-bbai-tooltip]') === element) {
                    return;
                }
                this.scheduleHide(element);
            }
        },

        /**
         * Handle tooltip on focus (keyboard navigation)
         */
        handleTooltipFocus: function(e) {
            // Check if e.target exists and is a valid DOM element
            if (!e || !e.target || typeof e.target.closest !== 'function') {
                return;
            }

            const element = e.target.closest('[data-bbai-tooltip]');
            if (!element) return;

            const text = element.getAttribute('data-bbai-tooltip');
            if (!text) return;

            const position = element.getAttribute('data-bbai-tooltip-position') || 'top';
            this.showTooltip(element, text, position);
        },

        /**
         * Handle tooltip on blur
         */
        handleTooltipBlur: function(e) {
            // Check if e.target exists and is a valid DOM element
            if (!e || !e.target || typeof e.target.closest !== 'function') {
                return;
            }

            const element = e.target.closest('[data-bbai-tooltip]');
            if (!element) return;

            this.scheduleHide(element);
        },

        /**
         * Debounced hide — prevents rapid show/hide when moving between
         * child elements of the same tooltip trigger (e.g. button → icon SVG).
         */
        scheduleHide: function(element) {
            if (element._bbaiHideTimer) {
                clearTimeout(element._bbaiHideTimer);
            }
            element._bbaiHideTimer = setTimeout(() => {
                element._bbaiHideTimer = null;
                this.hideTooltip(element);
            }, 80);
        },

        /**
         * Show tooltip
         */
        showTooltip: function(element, text, position) {
            // Remove existing tooltip if any
            this.hideTooltip(element);

            // Create tooltip element
            const tooltip = document.createElement('div');
            tooltip.className = `bbai-tooltip bbai-tooltip--${position}`;
            tooltip.textContent = text;
            tooltip.setAttribute('role', 'tooltip');
            tooltip.setAttribute('aria-hidden', 'false');

            // Append to body for proper positioning (hidden initially to prevent jump)
            tooltip.style.visibility = 'hidden';
            document.body.appendChild(tooltip);

            // Calculate position after tooltip is in DOM and can be measured
            this.positionTooltip(element, tooltip, position);

            // Show with animation after positioning is complete
            requestAnimationFrame(() => {
                tooltip.style.visibility = 'visible';
                tooltip.classList.add('show');
            });

            // Store reference
            this.tooltips.set(element, tooltip);

            // Note: tooltip has pointer-events: none via CSS, so direct
            // mouseleave on tooltip won't fire. The capture-phase
            // handleTooltipLeave on document handles hiding instead.

            // Handle window resize
            const resizeHandler = () => this.positionTooltip(element, tooltip, position);
            window.addEventListener('resize', resizeHandler);
            tooltip._resizeHandler = resizeHandler;
        },

        /**
         * Hide tooltip
         */
        hideTooltip: function(element) {
            const tooltip = this.tooltips.get(element);
            if (!tooltip) return;

            // Clear any pending hide timer
            if (element._bbaiHideTimer) {
                clearTimeout(element._bbaiHideTimer);
                element._bbaiHideTimer = null;
            }

            // Remove from map IMMEDIATELY to prevent race condition where
            // a delayed delete from an old tooltip removes a new tooltip's reference.
            this.tooltips.delete(element);

            // Remove resize handler
            if (tooltip._resizeHandler) {
                window.removeEventListener('resize', tooltip._resizeHandler);
                tooltip._resizeHandler = null;
            }

            // Hide with animation
            tooltip.classList.remove('show');
            tooltip.setAttribute('aria-hidden', 'true');

            // Remove from DOM after animation
            setTimeout(() => {
                if (tooltip.parentNode) {
                    tooltip.parentNode.removeChild(tooltip);
                }
            }, 200);
        },

        /**
         * Hide all tooltips
         */
        hideAllTooltips: function() {
            // Create a copy of the entries array since we'll be modifying the map
            const entries = Array.from(this.tooltips.entries());
            entries.forEach(([element]) => {
                this.hideTooltip(element);
            });
        },

        /**
         * Handle mousedown - hide tooltips immediately for interactive elements
         */
        handleMouseDown: function(e) {
            // Hide tooltip if clicking on an interactive element with a tooltip
            const element = e.target.closest('[data-bbai-tooltip]');
            if (element) {
                // Check if it's an interactive element (button, link, etc.)
                const isInteractive = element.tagName === 'BUTTON' || 
                                     element.tagName === 'A' || 
                                     element.classList.contains('bbai-btn') ||
                                     element.getAttribute('role') === 'button' ||
                                     element.onclick !== null ||
                                     element.getAttribute('tabindex') !== null;
                
                if (isInteractive && this.tooltips.has(element)) {
                    // Hide immediately without delay for interactive elements
                    this.hideTooltip(element);
                }
            }
        },

        /**
         * Handle document click - hide tooltips when clicking outside
         */
        handleDocumentClick: function(e) {
            // Hide tooltip if clicking on a tooltip trigger (in case mousedown didn't catch it)
            const element = e.target.closest('[data-bbai-tooltip]');
            if (element && this.tooltips.has(element)) {
                this.hideTooltip(element);
            }
            
            // Hide all other tooltips when clicking elsewhere
            // (hideAllTooltips is safe to call even if we just hid one - it checks if tooltip exists)
            this.hideAllTooltips();
        },

        /**
         * Handle scroll - hide tooltips when scrolling
         */
        handleScroll: function(e) {
            // Hide all tooltips on scroll
            this.hideAllTooltips();
        },

        /**
         * Handle mouse leaving the window
         */
        handleMouseOut: function(e) {
            // If mouse leaves the document, hide all tooltips
            if (!e.relatedTarget || !document.contains(e.relatedTarget)) {
                this.hideAllTooltips();
            }
        },

        /**
         * Initialize MutationObserver to clean up tooltips when elements are removed
         */
        initMutationObserver: function() {
            if (typeof MutationObserver === 'undefined') {
                return;
            }

            this.mutationObserver = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    mutation.removedNodes.forEach((node) => {
                        // Check if removed node had a tooltip
                        if (node.nodeType === 1) { // Element node
                            const tooltipElement = node.closest ? node.closest('[data-bbai-tooltip]') : null;
                            if (tooltipElement && this.tooltips.has(tooltipElement)) {
                                this.hideTooltip(tooltipElement);
                            }
                            
                            // Also check if any removed node contains tooltip triggers
                            const tooltipTriggers = node.querySelectorAll ? node.querySelectorAll('[data-bbai-tooltip]') : [];
                            tooltipTriggers.forEach((trigger) => {
                                if (this.tooltips.has(trigger)) {
                                    this.hideTooltip(trigger);
                                }
                            });
                        }
                    });
                });
            });

            // Observe the document body for removed nodes
            this.mutationObserver.observe(document.body, {
                childList: true,
                subtree: true
            });
        },

        /**
         * Position tooltip relative to element
         */
        positionTooltip: function(element, tooltip, position) {
            const rect = element.getBoundingClientRect();
            const tooltipRect = tooltip.getBoundingClientRect();
            const scrollX = window.pageXOffset || document.documentElement.scrollLeft;
            const scrollY = window.pageYOffset || document.documentElement.scrollTop;

            let top, left;

            switch (position) {
                case 'top':
                    top = rect.top + scrollY - tooltipRect.height - 8;
                    left = rect.left + scrollX + (rect.width / 2) - (tooltipRect.width / 2);
                    break;
                case 'bottom':
                    top = rect.bottom + scrollY + 8;
                    left = rect.left + scrollX + (rect.width / 2) - (tooltipRect.width / 2);
                    break;
                case 'left':
                    top = rect.top + scrollY + (rect.height / 2) - (tooltipRect.height / 2);
                    left = rect.left + scrollX - tooltipRect.width - 8;
                    break;
                case 'right':
                    top = rect.top + scrollY + (rect.height / 2) - (tooltipRect.height / 2);
                    left = rect.right + scrollX + 8;
                    break;
                default:
                    top = rect.top + scrollY - tooltipRect.height - 8;
                    left = rect.left + scrollX + (rect.width / 2) - (tooltipRect.width / 2);
            }

            // Keep tooltip within viewport
            const padding = 8;
            if (left < padding) left = padding;
            if (left + tooltipRect.width > window.innerWidth - padding) {
                left = window.innerWidth - tooltipRect.width - padding;
            }
            if (top < scrollY + padding) {
                top = scrollY + padding;
                // Switch to bottom if doesn't fit on top
                if (position === 'top') {
                    top = rect.bottom + scrollY + 8;
                }
            }
            if (top + tooltipRect.height > scrollY + window.innerHeight - padding) {
                top = scrollY + window.innerHeight - tooltipRect.height - padding;
            }

            tooltip.style.top = top + 'px';
            tooltip.style.left = left + 'px';
        },

        /**
         * Initialize keyboard shortcuts modal
         */
        initShortcutsModal: function() {
            const shortcuts = [
                { key: 'G', description: __('Generate missing alt text', 'beepbeep-ai-alt-text-generator') },
                { key: 'R', description: __('Regenerate all alt text', 'beepbeep-ai-alt-text-generator') },
                { key: 'U', description: __('Open upgrade modal', 'beepbeep-ai-alt-text-generator') },
                { key: '?', description: __('Show keyboard shortcuts', 'beepbeep-ai-alt-text-generator') },
                { key: 'Esc', description: __('Close modals', 'beepbeep-ai-alt-text-generator') },
                { key: 'Ctrl', key2: 'K', description: __('Quick actions menu', 'beepbeep-ai-alt-text-generator') }
            ];

            // Create modal HTML
            const modal = document.createElement('div');
            modal.className = 'bbai-shortcuts-modal';
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-labelledby', 'bbai-shortcuts-title');
            modal.setAttribute('aria-modal', 'true');
            modal.innerHTML = `
                <div class="bbai-shortcuts-modal-content">
                    <div class="bbai-shortcuts-header">
                        <h2 class="bbai-shortcuts-title" id="bbai-shortcuts-title">${__('Keyboard Shortcuts', 'beepbeep-ai-alt-text-generator')}</h2>
                        <button type="button" class="bbai-shortcuts-close" aria-label="${__('Close', 'beepbeep-ai-alt-text-generator')}">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 6L6 18M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <div class="bbai-shortcuts-list">
                        ${shortcuts.map(s => `
                            <div class="bbai-shortcut-item">
                                <span class="bbai-shortcut-description">${s.description}</span>
                                <div class="bbai-shortcut-keys">
                                    <kbd class="bbai-shortcut-key">${s.key}</kbd>
                                    ${s.key2 ? `<span class="bbai-shortcut-key--plus">+</span><kbd class="bbai-shortcut-key">${s.key2}</kbd>` : ''}
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;

            document.body.appendChild(modal);
            this.shortcutsModal = modal;

            // Close button
            modal.querySelector('.bbai-shortcuts-close').addEventListener('click', () => {
                this.hideShortcutsModal();
            });

            // Close on backdrop click
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.hideShortcutsModal();
                }
            });

            // Close on Escape
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && modal.classList.contains('show')) {
                    this.hideShortcutsModal();
                }
            });
        },

        /**
         * Show shortcuts modal
         */
        showShortcutsModal: function() {
            if (!this.shortcutsModal) return;
            this.shortcutsModal.classList.add('show');
            // Focus trap
            const closeBtn = this.shortcutsModal.querySelector('.bbai-shortcuts-close');
            if (closeBtn) closeBtn.focus();
        },

        /**
         * Hide shortcuts modal
         */
        hideShortcutsModal: function() {
            if (!this.shortcutsModal) return;
            this.shortcutsModal.classList.remove('show');
        },

        /**
         * Initialize keyboard shortcuts
         */
        initKeyboardShortcuts: function() {
            document.addEventListener('keydown', (e) => {
                // Don't trigger if typing in input/textarea
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) {
                    return;
                }

                // Show shortcuts modal
                if (e.key === '?' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                    e.preventDefault();
                    this.showShortcutsModal();
                    return;
                }

                // Quick actions (Ctrl/Cmd + K)
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    // Trigger quick actions menu (can be extended)
                    this.showShortcutsModal();
                    return;
                }

                // Generate missing (G)
                if (e.key === 'g' || e.key === 'G') {
                    if (!e.ctrlKey && !e.metaKey && !e.altKey) {
                        const generateBtn = document.querySelector('[data-action="generate-missing"]');
                        if (generateBtn && !generateBtn.disabled) {
                            e.preventDefault();
                            generateBtn.click();
                        }
                    }
                }

                // Regenerate all (R)
                if (e.key === 'r' || e.key === 'R') {
                    if (!e.ctrlKey && !e.metaKey && !e.altKey) {
                        const regenerateBtn = document.querySelector('[data-action="regenerate-all"]');
                        if (regenerateBtn && !regenerateBtn.disabled) {
                            e.preventDefault();
                            regenerateBtn.click();
                        }
                    }
                }

                // Upgrade modal (U)
                if (e.key === 'u' || e.key === 'U') {
                    if (!e.ctrlKey && !e.metaKey && !e.altKey) {
                        const upgradeBtn = document.querySelector('[data-action="show-upgrade-modal"]');
                        if (upgradeBtn) {
                            e.preventDefault();
                            upgradeBtn.click();
                        }
                    }
                }
            });
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => bbaiTooltips.init());
    } else {
        bbaiTooltips.init();
    }

    // Expose globally for external use
    window.bbaiTooltips = bbaiTooltips;
})();
