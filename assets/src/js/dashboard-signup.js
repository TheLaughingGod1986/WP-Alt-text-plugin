/**
 * Dashboard Signup Component for AltText AI
 * Handles email collection for dashboard signup
 */

class BbAIDashboardSignup {
    constructor() {
        this.apiUrl = this.getApiUrl();
        this.container = null;
        this.init();
    }

    getApiUrl() {
        // First check for opttiApi baseUrl
        // Use opttiApi baseUrl (required)
        if (opttiApi?.baseUrl) {
            return opttiApi.baseUrl;
        }

        // Fail explicitly if not configured
        console.error('[AltText AI] API URL not configured. opttiApi.baseUrl is required.');
        return null;
    }

    init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.createSignupForm());
        } else {
            this.createSignupForm();
        }
    }

    createSignupForm() {
        // Look for a container where we can inject the signup form
        // This could be in the dashboard or settings page
        const targetContainer = document.querySelector('[data-bbai-signup-container]') || 
                                document.querySelector('.bbai-dashboard-signup') ||
                                document.getElementById('bbai-dashboard-signup');

        if (!targetContainer) {
            // If no container found, create a global function that can be called to show signup
            this.setupGlobalSignup();
            return;
        }

        this.container = targetContainer;
        this.renderForm();
    }

    renderForm() {
        if (!this.container) return;

        const formHTML = `
            <div class="bbai-dashboard-signup-form">
                <h3 class="bbai-dashboard-signup-title">Stay Updated</h3>
                <p class="bbai-dashboard-signup-description">Get notified about new features and updates.</p>
                <form id="dashboard-signup-form" class="bbai-dashboard-signup-form-inner" autocomplete="off" aria-label="Dashboard signup">
                    <div class="bbai-form-group">
                        <label for="dashboard-signup-email" class="bbai-sr-only">Email</label>
                        <input 
                            type="email" 
                            id="dashboard-signup-email" 
                            name="email" 
                            placeholder="Enter your email" 
                            autocomplete="email" 
                            required 
                            aria-required="true"
                            class="bbai-input"
                        >
                    </div>
                    <button type="submit" class="bbai-btn bbai-btn-primary" aria-label="Subscribe">
                        <span class="bbai-btn-text">Subscribe</span>
                        <span class="bbai-btn-spinner" style="display: none;" aria-hidden="true">⏳</span>
                    </button>
                </form>
                <div id="dashboard-signup-message" class="bbai-dashboard-signup-message" style="display: none;" role="status" aria-live="polite"></div>
            </div>
        `;

        this.container.innerHTML = formHTML;
        this.attachEventListeners();
    }

    setupGlobalSignup() {
        // Create a global function to show signup form in a modal or inline
        window.showBbAIDashboardSignup = (container) => {
            if (container) {
                this.container = container;
                this.renderForm();
            } else {
                // Create a modal-like overlay
                this.showModal();
            }
        };
    }

    showModal() {
        const modalHTML = `
            <div id="bbai-dashboard-signup-modal" class="bbai-dashboard-signup-modal" style="display: block;" role="dialog" aria-modal="true">
                <div class="bbai-dashboard-signup-modal-overlay">
                    <div class="bbai-dashboard-signup-modal-content">
                        <button class="bbai-dashboard-signup-modal-close" type="button" aria-label="Close">&times;</button>
                        <div id="bbai-dashboard-signup-modal-body"></div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.container = document.getElementById('bbai-dashboard-signup-modal-body');
        this.renderForm();

        // Attach close handler
        const closeBtn = document.querySelector('.bbai-dashboard-signup-modal-close');
        const overlay = document.querySelector('.bbai-dashboard-signup-modal-overlay');
        
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
    }

    hideModal() {
        const modal = document.getElementById('bbai-dashboard-signup-modal');
        if (modal) {
            modal.remove();
        }
    }

    attachEventListeners() {
        const form = document.getElementById('dashboard-signup-form');
        if (form) {
            form.addEventListener('submit', (e) => this.handleSubmit(e));
        }
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
            
            this.showMessage('Thanks for subscribing! We\'ll keep you updated.', 'success');
            form.reset();
        } catch (error) {
            console.error('Dashboard signup error:', error);
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
        const spinner = submitBtn?.querySelector('.bbai-btn-spinner');
        const btnText = submitBtn?.querySelector('.bbai-btn-text');
        const emailInput = form?.querySelector('#dashboard-signup-email');

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
        const messageEl = document.getElementById('dashboard-signup-message');
        if (messageEl) {
            messageEl.textContent = message;
            messageEl.className = `bbai-dashboard-signup-message bbai-dashboard-signup-message--${type}`;
            messageEl.style.display = 'block';
        }
    }

    hideMessage() {
        const messageEl = document.getElementById('dashboard-signup-message');
        if (messageEl) {
            messageEl.style.display = 'none';
        }
    }
}

// Initialize dashboard signup when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.bbaiDashboardSignup = new BbAIDashboardSignup();
    });
} else {
    window.bbaiDashboardSignup = new BbAIDashboardSignup();
}

