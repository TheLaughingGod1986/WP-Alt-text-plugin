/**
 * BeepBeep AI - Custom Modal System
 * Replaces native alert() with accessible, branded modals
 *
 * @package BeepBeep_AI_Alt_Text_Generator
 * @since 4.3.0
 */

(function($) {
    'use strict';

    const i18n = window.wp && window.wp.i18n ? window.wp.i18n : null;
    const __ = i18n && typeof i18n.__ === 'function' ? i18n.__ : (text) => text;

    /**
     * Modal types with corresponding icons and styles
     */
    const MODAL_TYPES = {
        error: {
            icon: '<svg width="48" height="48" viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="22" stroke="currentColor" stroke-width="2"/><path d="M24 14v14M24 32v2" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>',
            className: 'bbai-modal--error',
            defaultTitle: __('Error', 'beepbeep-ai-alt-text-generator')
        },
        warning: {
            icon: '<svg width="48" height="48" viewBox="0 0 48 48" fill="none"><path d="M24 4L44 40H4L24 4z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M24 18v12M24 34v2" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>',
            className: 'bbai-modal--warning',
            defaultTitle: __('Warning', 'beepbeep-ai-alt-text-generator')
        },
        info: {
            icon: '<svg width="48" height="48" viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="22" stroke="currentColor" stroke-width="2"/><path d="M24 22v12M24 16v2" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>',
            className: 'bbai-modal--info',
            defaultTitle: __('Information', 'beepbeep-ai-alt-text-generator')
        },
        success: {
            icon: '<svg width="48" height="48" viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="22" stroke="currentColor" stroke-width="2"/><path d="M14 24l8 8 16-16" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            className: 'bbai-modal--success',
            defaultTitle: __('Success', 'beepbeep-ai-alt-text-generator')
        }
    };

    /**
     * BeepBeep AI Modal Class
     */
    class BBaiModal {
        constructor() {
            this.modal = null;
            this.overlay = null;
            this.activeModal = null;
            this.previousFocus = null;
            this.init();
        }

        /**
         * Initialize modal system
         */
        init() {
            this.createModalContainer();
            this.bindEvents();
        }

        /**
         * Create modal container in DOM
         */
        createModalContainer() {
            if ($('#bbai-modal-overlay').length > 0) {
                return; // Already exists
            }

            const overlayHTML = `
                <div id="bbai-modal-overlay" class="bbai-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="bbai-modal-title" style="display: none;">
                    <div class="bbai-modal-backdrop"></div>
                    <div class="bbai-modal-container">
                        <div class="bbai-modal-content">
                            <div class="bbai-modal-icon"></div>
                            <h2 id="bbai-modal-title" class="bbai-modal-title"></h2>
                            <div id="bbai-modal-message" class="bbai-modal-message"></div>
                            <div class="bbai-modal-buttons"></div>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(overlayHTML);
            this.overlay = $('#bbai-modal-overlay');
            this.modal = this.overlay.find('.bbai-modal-container');
        }

        /**
         * Bind global events
         */
        bindEvents() {
            const self = this;

            // Close on backdrop click
            $(document).on('click', '.bbai-modal-backdrop', function() {
                self.close();
            });

            // Close on ESC key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && self.activeModal) {
                    self.close();
                }
            });

            // Trap focus inside modal
            $(document).on('keydown', '#bbai-modal-overlay', function(e) {
                if (e.key === 'Tab' && self.activeModal) {
                    self.trapFocus(e);
                }
            });
        }

        /**
         * Show modal
         *
         * @param {Object} options - Modal configuration
         * @param {string} options.message - Message to display (required)
         * @param {string} options.title - Modal title (optional)
         * @param {string} options.type - Modal type: error|warning|info|success (default: info)
         * @param {Array} options.buttons - Array of button configurations (optional)
         * @param {Function} options.onClose - Callback when modal closes (optional)
         */
        show(options) {
            if (!options || !options.message) {
                console.error('BBaiModal: message is required');
                return;
            }

            const type = options.type || 'info';
            const config = MODAL_TYPES[type] || MODAL_TYPES.info;
            const title = options.title || config.defaultTitle;
            const buttons = options.buttons || [{ text: __('OK', 'beepbeep-ai-alt-text-generator'), primary: true, action: () => this.close() }];

            // Store previous focus
            this.previousFocus = document.activeElement;

            // Build modal content
            this.overlay.removeClass('bbai-modal--error bbai-modal--warning bbai-modal--info bbai-modal--success');
            this.overlay.addClass(config.className);

            this.overlay.find('.bbai-modal-icon').html(config.icon);
            this.overlay.find('.bbai-modal-title').text(title);
            this.overlay.find('.bbai-modal-message').html(this.sanitizeMessage(options.message));

            // Build buttons
            const buttonsHTML = buttons.map((btn, index) => {
                const btnClass = btn.primary ? 'bbai-modal-button bbai-modal-button--primary' : 'bbai-modal-button bbai-modal-button--secondary';
                return `<button type="button" class="${btnClass}" data-action="${index}">${this.escapeHtml(btn.text)}</button>`;
            }).join('');
            this.overlay.find('.bbai-modal-buttons').html(buttonsHTML);

            // Bind button actions
            this.overlay.find('.bbai-modal-button').off('click').on('click', (e) => {
                const index = parseInt($(e.currentTarget).data('action'));
                const button = buttons[index];
                if (button && button.action) {
                    button.action();
                } else {
                    this.close();
                }
            });

            // Show modal
            this.overlay.fadeIn(200);
            this.activeModal = options;

            // Focus first button
            setTimeout(() => {
                this.overlay.find('.bbai-modal-button').first().focus();
            }, 250);

            // Store onClose callback
            this.onCloseCallback = options.onClose;
        }

        /**
         * Close modal
         */
        close() {
            if (!this.activeModal) return;

            this.overlay.fadeOut(200, () => {
                this.activeModal = null;

                // Restore focus
                if (this.previousFocus && this.previousFocus.focus) {
                    this.previousFocus.focus();
                }

                // Call onClose callback
                if (this.onCloseCallback && typeof this.onCloseCallback === 'function') {
                    this.onCloseCallback();
                    this.onCloseCallback = null;
                }
            });
        }

        /**
         * Trap focus inside modal
         */
        trapFocus(e) {
            const focusableElements = this.overlay.find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            const firstElement = focusableElements.first()[0];
            const lastElement = focusableElements.last()[0];

            if (e.shiftKey && document.activeElement === firstElement) {
                e.preventDefault();
                lastElement.focus();
            } else if (!e.shiftKey && document.activeElement === lastElement) {
                e.preventDefault();
                firstElement.focus();
            }
        }

        /**
         * Sanitize message (allow basic HTML but escape scripts)
         */
        sanitizeMessage(message) {
            // Convert newlines to <br>
            message = String(message).replace(/\n/g, '<br>');

            // Allow only safe HTML tags
            const div = document.createElement('div');
            div.textContent = message;
            let sanitized = div.innerHTML;

            // Re-enable line breaks
            sanitized = sanitized.replace(/&lt;br&gt;/g, '<br>');

            return sanitized;
        }

        /**
         * Escape HTML
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * Convenience methods for different modal types
         */
        error(message, title) {
            return this.show({ type: 'error', message, title });
        }

        warning(message, title) {
            return this.show({ type: 'warning', message, title });
        }

        info(message, title) {
            return this.show({ type: 'info', message, title });
        }

        success(message, title) {
            return this.show({ type: 'success', message, title });
        }

        /**
         * Confirmation dialog with Yes/No buttons
         */
        confirm(options) {
            const confirmOptions = {
                type: options.type || 'warning',
                title: options.title || __('Confirm', 'beepbeep-ai-alt-text-generator'),
                message: options.message,
                buttons: [
                    {
                        text: options.cancelText || __('Cancel', 'beepbeep-ai-alt-text-generator'),
                        primary: false,
                        action: () => {
                            this.close();
                            if (options.onCancel) options.onCancel();
                        }
                    },
                    {
                        text: options.confirmText || __('OK', 'beepbeep-ai-alt-text-generator'),
                        primary: true,
                        action: () => {
                            this.close();
                            if (options.onConfirm) options.onConfirm();
                        }
                    }
                ]
            };

            return this.show(confirmOptions);
        }
    }

    /**
     * Initialize global instance
     */
    window.bbaiModal = new BBaiModal();

    /**
     * jQuery plugin
     */
    $.fn.bbaiModal = function(options) {
        return window.bbaiModal.show(options);
    };

})(jQuery);
