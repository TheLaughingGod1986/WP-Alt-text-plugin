/**
 * Upcoming Plugins Subscribe Handler
 * Handles subscription to upcoming OpttiAI plugins from the banner
 */

class BbAIUpcomingPluginsSubscribe {
    constructor() {
        this.modal = null;
        this.form = null;
        this.messageElement = null;
        this.closeButton = null;
        this.overlay = null;
        this.subscribeButton = null;
        this.init();
    }

    init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setup());
        } else {
            this.setup();
        }
    }

    setup() {
        this.modal = document.getElementById('bbai-upcoming-plugins-subscribe-modal');
        this.subscribeButton = document.getElementById('bbai-upcoming-plugins-subscribe-btn');
        
        // Debug: Log if elements are found
        if (!this.subscribeButton) {
            console.warn('[OpttiAI] Subscribe button not found in DOM');
        }
        if (!this.modal) {
            console.warn('[OpttiAI] Subscribe modal not found in DOM');
        }
        
        if (this.modal) {
            this.form = this.modal.querySelector('#bbai-upcoming-plugins-subscribe-form');
            this.messageElement = this.modal.querySelector('#bbai-upcoming-plugins-subscribe-message');
            this.closeButton = this.modal.querySelector('.bbai-upcoming-plugins-subscribe-modal-close');
            this.overlay = this.modal.querySelector('.bbai-upcoming-plugins-subscribe-modal-overlay');
            this.attachEventListeners();
        }
        
        if (this.subscribeButton) {
            this.subscribeButton.addEventListener('click', () => this.showModal());
            // Ensure button is visible
            this.subscribeButton.style.display = 'block';
            this.subscribeButton.style.visibility = 'visible';
        } else {
            // Fallback: try to find button by class if ID doesn't work
            const fallbackButton = document.querySelector('.bbai-upcoming-plugins-subscribe-btn');
            if (fallbackButton) {
                this.subscribeButton = fallbackButton;
                this.subscribeButton.addEventListener('click', () => this.showModal());
                console.log('[OpttiAI] Found subscribe button via class selector');
            }
        }
    }

    attachEventListeners() {
        if (this.form) {
            this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        }
        
        if (this.closeButton) {
            this.closeButton.addEventListener('click', () => this.hideModal());
        }
        
        if (this.overlay) {
            this.overlay.addEventListener('click', (e) => {
                if (e.target === this.overlay) {
                    this.hideModal();
                }
            });
        }
        
        // Close on ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal && this.modal.classList.contains('active')) {
                this.hideModal();
            }
        });
    }

    showModal() {
        if (this.modal) {
            this.modal.classList.add('active');
            this.prefillEmail();
            // Focus on email input
            const emailInput = this.form?.querySelector('#bbai-upcoming-plugins-email');
            if (emailInput) {
                setTimeout(() => emailInput.focus(), 100);
            }
        }
    }

    hideModal() {
        if (this.modal) {
            this.modal.classList.remove('active');
            // Reset form after animation
            setTimeout(() => this.resetForm(), 200);
        }
    }

    prefillEmail() {
        const emailInput = this.form?.querySelector('#bbai-upcoming-plugins-email');
        if (emailInput && window.bbaiUpcomingPluginsSubscribeData?.userEmail && !emailInput.value) {
            emailInput.value = window.bbaiUpcomingPluginsSubscribeData.userEmail;
        }
    }

    resetForm() {
        if (this.form) {
            this.hideMessage();
            const submitBtn = this.form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = false;
                const spinner = submitBtn.querySelector('.bbai-btn-spinner');
                const btnText = submitBtn.querySelector('.bbai-btn-text');
                if (spinner) spinner.style.display = 'none';
                if (btnText) btnText.style.display = 'inline-block';
            }
        }
    }

    async handleSubmit(e) {
        e.preventDefault();
        this.setLoading(true);
        this.hideMessage();

        const formData = new FormData(this.form);
        const email = formData.get('email');

        if (!email || !email.includes('@')) {
            this.showMessage('Please enter a valid email address.', 'error');
            this.setLoading(false);
            return;
        }

        const siteUrl = window.bbaiUpcomingPluginsSubscribeData?.siteUrl || window.location.origin;

        try {
            // Submit to both waitlist and email endpoints
            const promises = [];

            // Submit to waitlist
            if (typeof window.submitToWaitlist === 'function') {
                promises.push(
                    window.submitToWaitlist(email, 'alt-text', 'upcoming-plugins-banner')
                        .catch(err => {
                            console.warn('Waitlist submission failed:', err);
                            // Don't throw - continue with email submission
                        })
                );
            }

            // Send email notification
            if (typeof window.sendPluginSignupEmail === 'function') {
                promises.push(
                    window.sendPluginSignupEmail(email, siteUrl, 'upcoming-plugins')
                        .catch(err => {
                            console.warn('Email notification failed:', err);
                            // Don't throw - continue
                        })
                );
            } else if (typeof window.postJson === 'function') {
                // Fallback: use postJson directly
                promises.push(
                    window.postJson('/email/dashboard', {
                        email: email,
                        plugin: 'upcoming-plugins',
                        source: 'banner',
                        siteUrl: siteUrl
                    }).catch(err => {
                        console.warn('Email notification failed:', err);
                    })
                );
            }

            // Wait for all promises (even if some fail)
            await Promise.allSettled(promises);

            // Show success message
            this.showMessage('You\'re subscribed! We\'ll notify you when new plugins launch.', 'success');
            
            // Hide modal after 2 seconds
            setTimeout(() => {
                this.hideModal();
            }, 2000);

        } catch (error) {
            console.error('Subscription error:', error);
            this.showMessage('Something went wrong. Please try again later.', 'error');
        } finally {
            this.setLoading(false);
        }
    }

    setLoading(isLoading) {
        const submitBtn = this.form?.querySelector('button[type="submit"]');
        const spinner = submitBtn?.querySelector('.bbai-btn-spinner');
        const btnText = submitBtn?.querySelector('.bbai-btn-text');
        const emailInput = this.form?.querySelector('#bbai-upcoming-plugins-email');

        if (submitBtn) {
            submitBtn.disabled = isLoading;
        }
        if (spinner) {
            spinner.style.display = isLoading ? 'inline-block' : 'none';
        }
        if (btnText) {
            btnText.style.display = isLoading ? 'none' : 'inline-block';
        }
        if (emailInput) {
            emailInput.disabled = isLoading;
        }
    }

    showMessage(message, type = 'info') {
        if (this.messageElement) {
            this.messageElement.textContent = message;
            this.messageElement.className = `bbai-message bbai-message--${type}`;
            this.messageElement.style.display = 'block';
        }
    }

    hideMessage() {
        if (this.messageElement) {
            this.messageElement.style.display = 'none';
        }
    }
}

// Initialize when DOM is ready
if (typeof window !== 'undefined') {
    window.bbaiUpcomingPluginsSubscribe = new BbAIUpcomingPluginsSubscribe();
}

