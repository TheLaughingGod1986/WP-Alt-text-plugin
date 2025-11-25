/**
 * Dashboard Signup Handler
 * Handles form submission and API communication for dashboard signup modal
 */

(function() {
    'use strict';

    class DashboardSignupHandler {
        constructor() {
            this.apiUrl = null;
            this.siteDomain = '';
            this.nonce = '';
            this.ajaxUrl = '';
            this.init();
        }

        init() {
            // Wait for data from PHP
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.setup());
            } else {
                this.setup();
            }
        }

        setup() {
            // Get data from PHP
            if (window.bbaiDashboardSignupData) {
                this.apiUrl = window.bbaiDashboardSignupData.apiUrl || (window.bbai_ajax && window.bbai_ajax.api_url);
                this.siteDomain = window.bbaiDashboardSignupData.siteDomain || '';
                this.nonce = window.bbaiDashboardSignupData.nonce || '';
                this.ajaxUrl = window.bbaiDashboardSignupData.ajaxUrl || (window.bbai_ajax && window.bbai_ajax.ajaxurl);
            } else {
                // Fallback to window.bbai_ajax
                this.apiUrl = window.bbai_ajax && window.bbai_ajax.api_url;
                this.ajaxUrl = window.bbai_ajax && window.bbai_ajax.ajaxurl;
            }

            const form = document.getElementById('bbai-dashboard-signup-form');
            const closeBtn = document.getElementById('bbai-dashboard-signup-close');
            const modal = document.getElementById('bbai-dashboard-signup-modal');
            const overlay = modal && modal.querySelector('.alttext-auth-modal__overlay');

            if (form) {
                form.addEventListener('submit', (e) => this.handleSubmit(e));
            }

            if (closeBtn) {
                closeBtn.addEventListener('click', () => this.hideModal());
            }

            if (overlay) {
                overlay.addEventListener('click', (e) => {
                    if (e.target === overlay) {
                        this.hideModal();
                    }
                });
            }

            // Close on Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isModalVisible()) {
                    this.hideModal();
                }
            });

            // Auto-show modal if it should be visible
            if (modal && modal.style.display === 'block') {
                this.showModal();
            }
        }

        async handleSubmit(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            
            const name = formData.get('name') || '';
            const email = formData.get('email') || '';
            const acceptTerms = formData.get('accept_terms') === '1';

            // Validation
            if (!email || !email.includes('@')) {
                this.showMessage('Please enter a valid email address.', 'error');
                return;
            }

            if (!name || name.trim() === '') {
                this.showMessage('Please enter your name.', 'error');
                return;
            }

            if (!acceptTerms) {
                this.showMessage('Please accept the Terms of Service to continue.', 'error');
                return;
            }

            this.setLoading(form, true);
            this.hideMessage();

            try {
                await this.submitToBackend(email, name);
                
                // Success
                this.showMessage('Thank you! We\'ll be in touch soon.', 'success');
                form.style.display = 'none';
                
                // Clear transients via AJAX
                this.clearTransients();
                
                // Close modal after 3 seconds
                setTimeout(() => {
                    this.hideModal();
                }, 3000);
            } catch (error) {
                console.error('Dashboard signup error:', error);
                this.showMessage('Something went wrong. Please try again later.', 'error');
                
                // Log error to WordPress
                this.logError(error);
            } finally {
                this.setLoading(form, false);
            }
        }

    async submitToBackend(email, name) {
        // Use postJson from apiClient if available
        if (typeof window.postJson === 'function') {
            return await window.postJson('/email/dashboard', {
                email: email,
                name: name,
                plugin: 'alttext',
                source: 'dashboard',
                domain: this.siteDomain
            });
        }
        
        // Fallback if apiClient not loaded
        if (!this.apiUrl) {
            throw new Error('API URL not configured');
        }

        const payload = {
            email: email,
            name: name,
            plugin: 'alttext',
            source: 'dashboard',
            domain: this.siteDomain
        };

        const response = await fetch(`${this.apiUrl}/email/dashboard`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            const errorMessage = errorData.error || `HTTP error! status: ${response.status}`;
            throw new Error(errorMessage);
        }

        const data = await response.json();
        return data;
    }

        clearTransients() {
            if (!this.ajaxUrl) {
                return;
            }

            // Clear transients via AJAX (non-blocking)
            fetch(this.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'bbai_clear_signup_transients',
                    nonce: this.nonce
                })
            }).catch(err => {
                console.error('Failed to clear transients:', err);
            });
        }

        logError(error) {
            if (!this.ajaxUrl) {
                return;
            }

            const errorMessage = error.message || 'Unknown error';
            const responseCode = error.status || 'N/A';

            // Log to WordPress via AJAX (non-blocking)
            fetch(this.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'bbai_log_dashboard_signup_error',
                    nonce: this.nonce,
                    error_message: errorMessage,
                    response_code: responseCode
                })
            }).catch(err => {
                console.error('Failed to log error:', err);
            });
        }

        setLoading(form, isLoading) {
            const submitBtn = form?.querySelector('button[type="submit"]');
            const spinner = submitBtn?.querySelector('.alttext-btn__spinner');
            const btnText = submitBtn?.querySelector('.alttext-btn__text');
            const inputs = form?.querySelectorAll('input');

            if (submitBtn) {
                submitBtn.disabled = isLoading;
            }

            if (spinner) {
                spinner.style.display = isLoading ? 'inline-block' : 'none';
            }

            if (btnText) {
                btnText.style.display = isLoading ? 'none' : 'inline-block';
            }

            if (inputs) {
                inputs.forEach(input => {
                    input.disabled = isLoading;
                });
            }
        }

        showMessage(message, type = 'info') {
            const messageEl = document.getElementById('bbai-dashboard-signup-message');
            if (messageEl) {
                messageEl.textContent = message;
                messageEl.className = `alttext-alert alttext-alert--${type}`;
                messageEl.style.display = 'block';
            }
        }

        hideMessage() {
            const messageEl = document.getElementById('bbai-dashboard-signup-message');
            if (messageEl) {
                messageEl.style.display = 'none';
            }
        }

        showModal() {
            const modal = document.getElementById('bbai-dashboard-signup-modal');
            if (modal) {
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
                
                // Focus on name input
                const nameInput = modal.querySelector('#bbai-signup-name');
                if (nameInput) {
                    setTimeout(() => nameInput.focus(), 100);
                }
            }
        }

        hideModal() {
            const modal = document.getElementById('bbai-dashboard-signup-modal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = '';
                this.hideMessage();
            }
        }

        isModalVisible() {
            const modal = document.getElementById('bbai-dashboard-signup-modal');
            return modal && modal.style.display !== 'none';
        }
    }

    // Initialize
    window.bbaiDashboardSignupHandler = new DashboardSignupHandler();
})();

