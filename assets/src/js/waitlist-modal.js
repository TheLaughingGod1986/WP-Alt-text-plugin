/**
 * Waitlist Modal for AltText AI
 * Handles waitlist email collection
 */

class BbAIWaitlistModal {
    constructor() {
        this.apiUrl = this.getApiUrl();
        this.modalElement = null;
        this.init();
    }

    getApiUrl() {
        // First check for opttiApi baseUrl
        if (opttiApi?.baseUrl) {
            return opttiApi.baseUrl;
        }
        
        // Fallback to legacy API URL
        if (window.bbai_ajax?.api_url) {
            return window.bbai_ajax.api_url;
        }

        // No fallback for production - fail explicitly
        console.error('[AltText AI] API URL not configured');
        return null;
    }

    init() {
        this.createModalHTML();
        this.modalElement = document.getElementById('alttext-waitlist-modal');
        this.attachEventListeners();
    }

    createModalHTML() {
        const modalHTML = `
            <div id="alttext-waitlist-modal" class="alttext-waitlist-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="alttext-waitlist-modal-title" aria-describedby="alttext-waitlist-modal-desc">
                <div class="alttext-waitlist-modal__overlay">
                    <div class="alttext-waitlist-modal__content">
                        <button class="alttext-waitlist-modal__close" type="button" aria-label="Close dialog">&times;</button>
                        
                        <div class="alttext-waitlist-modal__header">
                            <h2 class="alttext-waitlist-modal__title" id="alttext-waitlist-modal-title">Join the Waitlist</h2>
                            <p class="alttext-waitlist-modal__subtitle" id="alttext-waitlist-modal-desc">Be the first to know when new features are available.</p>
                        </div>
                        
                        <div class="alttext-waitlist-modal__body">
                            <form id="waitlist-form" autocomplete="off" aria-label="Join waitlist">
                                <div class="alttext-form-group">
                                    <label for="waitlist-email">Email</label>
                                    <input type="email" id="waitlist-email" name="email" placeholder="your@email.com" autocomplete="email" required aria-required="true">
                                </div>
                                <button type="submit" class="alttext-btn alttext-btn--primary" aria-label="Join waitlist">
                                    <span class="alttext-btn__text">Join Waitlist</span>
                                    <span class="alttext-btn__spinner" style="display: none;" aria-hidden="true">⏳</span>
                                </button>
                            </form>
                            <div id="waitlist-message" class="alttext-waitlist-message" style="display: none;" role="status" aria-live="polite"></div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    attachEventListeners() {
        const form = document.getElementById('waitlist-form');
        const closeBtn = this.modalElement?.querySelector('.alttext-waitlist-modal__close');
        const overlay = this.modalElement?.querySelector('.alttext-waitlist-modal__overlay');

        if (form) {
            form.addEventListener('submit', (e) => this.handleSubmit(e));
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.hide());
        }

        if (overlay) {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    this.hide();
                }
            });
        }

        // Close on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isVisible()) {
                this.hide();
            }
        });
    }

    async handleSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const email = formData.get('email');

        if (!email || !email.includes('@')) {
            this.showMessage('Please enter a valid email address.', 'error');
            return;
        }

        this.setLoading(form, true);
        this.hideMessage();

        try {
            // Call welcome email endpoint
            await this.sendWelcomeEmail(email);
            
            this.showMessage('Thanks! We\'ll be in touch soon.', 'success');
            form.reset();
            
            // Hide modal after 2 seconds
            setTimeout(() => {
                this.hide();
            }, 2000);
        } catch (error) {
            console.error('Waitlist submission error:', error);
            this.showMessage('Something went wrong. Please try again later.', 'error');
        } finally {
            this.setLoading(form, false);
        }
    }

    async sendWelcomeEmail(email) {
        // Use sendPluginSignup from optti-api.js if available
        if (typeof window.sendPluginSignup === 'function') {
            return await window.sendPluginSignup({
                email,
                plugin: opttiApi?.plugin || 'beepbeep-ai',
                site: opttiApi?.site || window.location.origin
            });
        }
        
        // Use postJson from apiClient if available
        if (typeof window.postJson === 'function') {
            return await window.postJson('/email/plugin-signup', {
                email: email,
                plugin: opttiApi?.plugin || 'beepbeep-ai',
                site: opttiApi?.site || window.location.origin
            });
        }
        
        // Use shared helper function as fallback
        if (typeof window.sendWelcomeEmail === 'function') {
            return await window.sendWelcomeEmail(email);
        }
        
        // Final fallback if nothing loaded
        const apiUrl = opttiApi?.baseUrl || this.apiUrl;
        if (!apiUrl) {
            throw new Error('API URL not configured');
        }

        const response = await fetch(`${apiUrl}/email/plugin-signup`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                email: email,
                plugin: opttiApi?.plugin || 'beepbeep-ai',
                site: opttiApi?.site || window.location.origin
            })
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.error || 'Failed to send welcome email');
        }

        const data = await response.json();
        return data;
    }

    setLoading(form, isLoading) {
        const submitBtn = form?.querySelector('button[type="submit"]');
        const spinner = submitBtn?.querySelector('.alttext-btn__spinner');
        const btnText = submitBtn?.querySelector('.alttext-btn__text');
        const emailInput = form?.querySelector('#waitlist-email');

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
        const messageEl = document.getElementById('waitlist-message');
        if (messageEl) {
            messageEl.textContent = message;
            messageEl.className = `alttext-waitlist-message alttext-waitlist-message--${type}`;
            messageEl.style.display = 'block';
        }
    }

    hideMessage() {
        const messageEl = document.getElementById('waitlist-message');
        if (messageEl) {
            messageEl.style.display = 'none';
        }
    }

    show() {
        if (this.modalElement) {
            this.modalElement.style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            // Focus on email input
            const emailInput = this.modalElement.querySelector('#waitlist-email');
            if (emailInput) {
                setTimeout(() => emailInput.focus(), 100);
            }
        }
    }

    hide() {
        if (this.modalElement) {
            this.modalElement.style.display = 'none';
            document.body.style.overflow = '';
            this.hideMessage();
        }
    }

    isVisible() {
        return this.modalElement && this.modalElement.style.display !== 'none';
    }
}

// Initialize waitlist modal when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.bbaiWaitlistModal = new BbAIWaitlistModal();
    });
} else {
    window.bbaiWaitlistModal = new BbAIWaitlistModal();
}

// Expose global function to open waitlist modal
window.openBbAIWaitlist = function() {
    if (window.bbaiWaitlistModal) {
        window.bbaiWaitlistModal.show();
    }
};

