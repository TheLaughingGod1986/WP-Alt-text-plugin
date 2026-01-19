/**
 * BeepBeep AI Contact Modal
 * Contact form modal for user support requests via Resend API
 *
 * @package BeepBeep_AI_Alt_Text_Generator
 * @since 6.0.0
 */

(function($) {
    'use strict';

    /**
     * Contact Modal Class
     */
    class BBaiContactModal {
        constructor() {
            this.modal = null;
            this.form = null;
            this.isOpen = false;
            this.init();
        }

        /**
         * Initialize contact modal
         */
        init() {
            this.createModal();
            this.bindEvents();
        }

        /**
         * Create modal HTML structure
         */
        createModal() {
            if ($('#bbai-contact-modal-overlay').length > 0) {
                return; // Already exists
            }

            const modalHTML = `
                <div id="bbai-contact-modal-overlay" class="bbai-contact-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="bbai-contact-modal-title" style="display: none;">
                    <div class="bbai-contact-modal-backdrop"></div>
                    <div class="bbai-contact-modal-container">
                        <div class="bbai-contact-modal-content">
                            <div class="bbai-contact-modal-header">
                                <h2 id="bbai-contact-modal-title" class="bbai-contact-modal-title">Contact Us</h2>
                                <button type="button" class="bbai-contact-modal-close" aria-label="Close modal">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M18 6L6 18M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                            <form id="bbai-contact-form" class="bbai-contact-form">
                                <div class="bbai-contact-form-group">
                                    <label for="bbai-contact-name" class="bbai-contact-form-label">
                                        Name <span class="bbai-contact-form-required">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        id="bbai-contact-name" 
                                        name="name" 
                                        class="bbai-contact-form-input" 
                                        required 
                                        placeholder="Your name"
                                    />
                                </div>
                                <div class="bbai-contact-form-group">
                                    <label for="bbai-contact-email" class="bbai-contact-form-label">
                                        Email <span class="bbai-contact-form-required">*</span>
                                    </label>
                                    <input 
                                        type="email" 
                                        id="bbai-contact-email" 
                                        name="email" 
                                        class="bbai-contact-form-input" 
                                        required 
                                        placeholder="your@email.com"
                                    />
                                </div>
                                <div class="bbai-contact-form-group">
                                    <label for="bbai-contact-subject" class="bbai-contact-form-label">
                                        Subject <span class="bbai-contact-form-required">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        id="bbai-contact-subject" 
                                        name="subject" 
                                        class="bbai-contact-form-input" 
                                        required 
                                        placeholder="How can we help?"
                                    />
                                </div>
                                <div class="bbai-contact-form-group">
                                    <label for="bbai-contact-message" class="bbai-contact-form-label">
                                        Message <span class="bbai-contact-form-required">*</span>
                                    </label>
                                    <textarea 
                                        id="bbai-contact-message" 
                                        name="message" 
                                        class="bbai-contact-form-textarea" 
                                        required 
                                        rows="5"
                                        placeholder="Please describe your issue or question..."
                                    ></textarea>
                                </div>
                                <div class="bbai-contact-form-info">
                                    <p class="bbai-contact-form-info-text">
                                        Your system information (WordPress version, Plugin version) will be automatically included.
                                    </p>
                                </div>
                                <div id="bbai-contact-form-error" class="bbai-contact-form-error" style="display: none;"></div>
                                <div id="bbai-contact-form-success" class="bbai-contact-form-success" style="display: none;"></div>
                                <div class="bbai-contact-form-actions">
                                    <button type="button" class="bbai-btn bbai-btn-secondary" data-action="close-contact-modal">
                                        Cancel
                                    </button>
                                    <button type="submit" class="bbai-btn bbai-btn-primary" id="bbai-contact-submit">
                                        Send Message
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHTML);
            this.modal = $('#bbai-contact-modal-overlay');
            this.form = $('#bbai-contact-form');
        }

        /**
         * Bind events
         */
        bindEvents() {
            const self = this;

            // Open modal triggers
            $(document).on('click', '[data-action="open-contact-modal"], .bbai-contact-link', function(e) {
                e.preventDefault();
                self.open();
            });

            // Close modal
            $(document).on('click', '.bbai-contact-modal-backdrop, .bbai-contact-modal-close, [data-action="close-contact-modal"]', function(e) {
                e.preventDefault();
                self.close();
            });

            // ESC key to close
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && self.isOpen) {
                    self.close();
                }
            });

            // Form submission
            this.form.on('submit', function(e) {
                e.preventDefault();
                self.handleSubmit();
            });

            // Clear errors on input
            this.form.find('input, textarea').on('input', function() {
                self.hideError();
                self.hideSuccess();
            });
        }

        /**
         * Open modal
         */
        open() {
            if (this.isOpen) {
                return;
            }

            // Populate system info (hidden fields or add to form data)
            const wpVersion = typeof window.bbaiContactData !== 'undefined' && window.bbaiContactData.wp_version 
                ? window.bbaiContactData.wp_version 
                : '';
            const pluginVersion = typeof window.bbaiContactData !== 'undefined' && window.bbaiContactData.plugin_version 
                ? window.bbaiContactData.plugin_version 
                : '';

            // Store in form data (will be sent with submission)
            this.form.data('wp_version', wpVersion);
            this.form.data('plugin_version', pluginVersion);

            // Reset form
            this.form[0].reset();
            this.hideError();
            this.hideSuccess();

            // Show modal
            this.modal.fadeIn(200);
            this.isOpen = true;

            // Focus first input
            setTimeout(() => {
                $('#bbai-contact-name').focus();
            }, 200);

            // Prevent body scroll
            $('body').css('overflow', 'hidden');
        }

        /**
         * Close modal
         */
        close() {
            if (!this.isOpen) {
                return;
            }

            this.modal.fadeOut(200);
            this.isOpen = false;

            // Restore body scroll
            $('body').css('overflow', '');
        }

        /**
         * Handle form submission
         */
        handleSubmit() {
            const self = this;
            const submitButton = $('#bbai-contact-submit');
            const formData = {
                name: $('#bbai-contact-name').val().trim(),
                email: $('#bbai-contact-email').val().trim(),
                subject: $('#bbai-contact-subject').val().trim(),
                message: $('#bbai-contact-message').val().trim(),
                wp_version: this.form.data('wp_version') || '',
                plugin_version: this.form.data('plugin_version') || ''
            };

            // Client-side validation
            if (!formData.name || !formData.email || !formData.subject || !formData.message) {
                this.showError('Please fill in all required fields.');
                return;
            }

            if (!this.isValidEmail(formData.email)) {
                this.showError('Please enter a valid email address.');
                return;
            }

            // Disable submit button
            submitButton.prop('disabled', true).text('Sending...');
            this.hideError();
            this.hideSuccess();

            // Get AJAX config
            const ajaxConfig = typeof window.bbai_ajax !== 'undefined' ? window.bbai_ajax : 
                               (typeof bbai_ajax !== 'undefined' ? bbai_ajax : null);

            if (!ajaxConfig || !ajaxConfig.ajaxurl) {
                this.showError('Configuration error. Please refresh the page and try again.');
                submitButton.prop('disabled', false).text('Send Message');
                return;
            }

            // Prepare AJAX request
            const requestData = {
                action: 'bbai_send_contact_form',
                nonce: ajaxConfig.nonce || '',
                ...formData
            };

            // Send AJAX request
            $.ajax({
                url: ajaxConfig.ajaxurl,
                type: 'POST',
                data: requestData,
                success: function(response) {
                    if (response && response.success) {
                        // Hide form fields and show success message prominently
                        self.form.find('.bbai-contact-form-group').hide();
                        self.form.find('.bbai-contact-form-actions').hide();
                        self.form.find('.bbai-contact-form-info').hide();
                        
                        const successMessage = response.data && response.data.message 
                            ? response.data.message 
                            : 'Your message has been sent successfully. We\'ll get back to you soon!';
                        
                        self.showSuccess('<strong style="font-size: 16px; display: block; margin-bottom: 8px;">✓ Success!</strong>' + successMessage);
                        
                        // Scroll to top of modal to show success message
                        const modalContent = self.modal.find('.bbai-contact-modal-content');
                        const formContainer = self.modal.find('.bbai-contact-form');
                        if (formContainer.length) {
                            formContainer.scrollTop(0);
                        }
                        
                        // Reset form and close modal after 3 seconds
                        setTimeout(() => {
                            self.form[0].reset();
                            self.form.find('.bbai-contact-form-group').show();
                            self.form.find('.bbai-contact-form-actions').show();
                            self.form.find('.bbai-contact-form-info').show();
                            self.hideSuccess();
                            self.close();
                        }, 3000);
                    } else {
                        const errorMessage = response && response.data && response.data.message 
                            ? response.data.message 
                            : 'Failed to send message. Please try again.';
                        self.showError(errorMessage);
                    }
                    submitButton.prop('disabled', false).text('Send Message');
                },
                error: function(xhr, status, error) {
                    console.error('Contact form submission error:', error);
                    self.showError('Network error. Please check your connection and try again.');
                    submitButton.prop('disabled', false).text('Send Message');
                }
            });
        }

        /**
         * Validate email format
         */
        isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        /**
         * Show error message
         */
        showError(message) {
            const errorDiv = $('#bbai-contact-form-error');
            errorDiv.text(message).fadeIn(200);
            // Scroll to error
            $('html, body').animate({
                scrollTop: errorDiv.offset().top - 100
            }, 300);
        }

        /**
         * Hide error message
         */
        hideError() {
            $('#bbai-contact-form-error').fadeOut(200);
        }

        /**
         * Show success message
         */
        showSuccess(message) {
            const successDiv = $('#bbai-contact-form-success');
            successDiv.html(message).fadeIn(200);
            
            // Scroll success message into view within the modal
            const modalContent = this.modal.find('.bbai-contact-modal-content');
            const successOffset = successDiv.offset();
            const modalOffset = modalContent.offset();
            
            if (successOffset && modalOffset) {
                // Scroll within the modal's scrollable container
                const formContainer = this.modal.find('.bbai-contact-form');
                if (formContainer.length) {
                    const scrollTop = formContainer.scrollTop();
                    const elementTop = successDiv.position().top;
                    formContainer.scrollTop(scrollTop + elementTop - 20);
                }
            }
        }

        /**
         * Hide success message
         */
        hideSuccess() {
            $('#bbai-contact-form-success').fadeOut(200);
        }
    }

    // Initialize contact modal when DOM is ready
    $(document).ready(function() {
        if (typeof window.bbaiContactModal === 'undefined') {
            window.bbaiContactModal = new BBaiContactModal();
        }
    });

})(jQuery);
