/**
 * BeepBeep AI Tooltip System
 * Handles tooltips with data-bbai-tooltip attribute
 */

(function() {
    'use strict';

    const bbaiTooltips = {
        tooltips: new Map(),
        shortcutsModal: null,

        init: function() {
            this.initTooltips();
            this.initShortcutsModal();
            this.initKeyboardShortcuts();
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
                        this.hideTooltip(element);
                        return;
                    }
                }
            }

            // Check if mouse left the element with tooltip
            const element = e.target.closest('[data-bbai-tooltip]');
            if (element) {
                // Only hide if we're not moving to the tooltip
                const relatedTarget = e.relatedTarget;
                if (relatedTarget && relatedTarget.closest('.bbai-tooltip')) {
                    return; // Don't hide if moving to tooltip
                }
                this.hideTooltip(element);
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

            this.hideTooltip(element);
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

            // Append to body for proper positioning
            document.body.appendChild(tooltip);

            // Calculate position
            this.positionTooltip(element, tooltip, position);

            // Show with animation
            requestAnimationFrame(() => {
                tooltip.classList.add('show');
            });

            // Store reference
            this.tooltips.set(element, tooltip);

            // Hide tooltip when mouse leaves the tooltip itself
            tooltip.addEventListener('mouseleave', (e) => {
                // Only hide if not moving back to the element
                const relatedTarget = e.relatedTarget;
                if (relatedTarget && relatedTarget.closest('[data-bbai-tooltip]') === element) {
                    return; // Don't hide if moving back to element
                }
                this.hideTooltip(element);
            });

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

            // Remove resize handler
            if (tooltip._resizeHandler) {
                window.removeEventListener('resize', tooltip._resizeHandler);
            }

            // Hide with animation
            tooltip.classList.remove('show');
            tooltip.setAttribute('aria-hidden', 'true');

            // Remove after animation
            setTimeout(() => {
                if (tooltip.parentNode) {
                    tooltip.parentNode.removeChild(tooltip);
                }
                this.tooltips.delete(element);
            }, 200);
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
                { key: 'G', description: 'Generate missing alt text' },
                { key: 'R', description: 'Regenerate all alt text' },
                { key: 'U', description: 'Open upgrade modal' },
                { key: '?', description: 'Show keyboard shortcuts' },
                { key: 'Esc', description: 'Close modals' },
                { key: 'Ctrl', key2: 'K', description: 'Quick actions menu' }
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
                        <h2 class="bbai-shortcuts-title" id="bbai-shortcuts-title">Keyboard Shortcuts</h2>
                        <button type="button" class="bbai-shortcuts-close" aria-label="Close">
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
                if (e.key === '?' && !e.ctrlKey && !e.metaKey && !e.altKey && !e.shiftKey) {
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
