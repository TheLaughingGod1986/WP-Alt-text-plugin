/**
 * BeepBeep AI Toast Notification System
 * Replaces alerts with accessible, branded toast notifications
 */

(function() {
    'use strict';

    const bbaiToast = {
        container: null,
        toasts: [],

        init: function() {
            this.createContainer();
            this.bindEvents();
        },

        /**
         * Create toast container
         */
        createContainer: function() {
            const container = document.createElement('div');
            container.className = 'bbai-toast-container';
            container.setAttribute('aria-live', 'polite');
            container.setAttribute('aria-atomic', 'true');
            document.body.appendChild(container);
            this.container = container;
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Close on Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.toasts.length > 0) {
                    // Close the most recent toast
                    const lastToast = this.toasts[this.toasts.length - 1];
                    if (lastToast) {
                        this.hide(lastToast.id);
                    }
                }
            });
        },

        /**
         * Show toast notification
         */
        show: function(message, options = {}) {
            const {
                type = 'info',
                duration = 5000,
                action = null,
                actionLabel = null,
                persistent = false
            } = options;

            const id = 'toast-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            const toast = document.createElement('div');
            toast.className = `bbai-toast bbai-toast--${type}`;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.id = id;

            // Icon based on type
            const icons = {
                success: '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="2"/><path d="M6 10l2.5 2.5L14 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
                error: '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="2"/><path d="M10 6V10M10 14H10.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
                warning: '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 2L18 16H2L10 2z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M10 8V12M10 16H10.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
                info: '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="2"/><path d="M10 8V12M10 16H10.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'
            };

            const icon = icons[type] || icons.info;

            // Build toast content
            let toastHTML = `
                <div class="bbai-toast-icon">${icon}</div>
                <div class="bbai-toast-content">
                    <div class="bbai-toast-message">${this.escapeHtml(message)}</div>
                </div>
                <button type="button" class="bbai-toast-close" aria-label="Close">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 4L4 12M4 4l8 8"/>
                    </svg>
                </button>
            `;

            // Add action button if provided
            if (action && actionLabel) {
                toastHTML = `
                    <div class="bbai-toast-icon">${icon}</div>
                    <div class="bbai-toast-content">
                        <div class="bbai-toast-message">${this.escapeHtml(message)}</div>
                        <button type="button" class="bbai-toast-action" data-action="${this.escapeHtml(action)}">
                            ${this.escapeHtml(actionLabel)}
                        </button>
                    </div>
                    <button type="button" class="bbai-toast-close" aria-label="Close">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 4L4 12M4 4l8 8"/>
                        </svg>
                    </button>
                `;
            }

            toast.innerHTML = toastHTML;

            // Close button handler
            const closeBtn = toast.querySelector('.bbai-toast-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    this.hide(id);
                });
            }

            // Action button handler
            const actionBtn = toast.querySelector('.bbai-toast-action');
            if (actionBtn) {
                actionBtn.addEventListener('click', () => {
                    if (typeof action === 'function') {
                        action();
                    }
                    this.hide(id);
                });
            }

            // Append to container
            this.container.appendChild(toast);
            this.toasts.push({ id, element: toast, persistent });

            // Show with animation
            requestAnimationFrame(() => {
                toast.classList.add('show');
            });

            // Auto-dismiss if not persistent
            if (!persistent && duration > 0) {
                setTimeout(() => {
                    this.hide(id);
                }, duration);
            }

            return id;
        },

        /**
         * Hide toast
         */
        hide: function(id) {
            const toastIndex = this.toasts.findIndex(t => t.id === id);
            if (toastIndex === -1) return;

            const toast = this.toasts[toastIndex].element;
            toast.classList.remove('show');
            toast.classList.add('hide');

            // Remove after animation
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
                this.toasts.splice(toastIndex, 1);
            }, 300);
        },

        /**
         * Hide all toasts
         */
        hideAll: function() {
            this.toasts.forEach(toast => {
                this.hide(toast.id);
            });
        },

        /**
         * Success toast
         */
        success: function(message, options = {}) {
            return this.show(message, { ...options, type: 'success' });
        },

        /**
         * Error toast
         */
        error: function(message, options = {}) {
            return this.show(message, { ...options, type: 'error', duration: 7000 });
        },

        /**
         * Warning toast
         */
        warning: function(message, options = {}) {
            return this.show(message, { ...options, type: 'warning', duration: 6000 });
        },

        /**
         * Info toast
         */
        info: function(message, options = {}) {
            return this.show(message, { ...options, type: 'info' });
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => bbaiToast.init());
    } else {
        bbaiToast.init();
    }

    // Expose globally
    window.bbaiToast = bbaiToast;
})();
