/**
 * Authentication Modal for AltText AI
 * Handles user registration and login
 */

class AltTextAuthModal {
    constructor() {
        this.apiUrl = window.alttextai_ajax?.api_url || 'http://localhost:3001';
        this.token = this.getStoredToken();
        this.init();
    }

    init() {
        this.createModalHTML();
        this.bindEvents();
        this.checkAuthStatus();
    }

    createModalHTML() {
        const modalHTML = `
            <div id="alttext-auth-modal" class="alttext-auth-modal" style="display: none;">
                <div class="alttext-auth-modal__overlay">
                    <div class="alttext-auth-modal__content">
                        <div class="alttext-auth-modal__header">
                            <h2 class="alttext-auth-modal__title">AltText AI Account</h2>
                            <button class="alttext-auth-modal__close" type="button">&times;</button>
                        </div>
                        
                        <div class="alttext-auth-modal__body">
                            <!-- Login Form -->
                            <div id="alttext-login-form" class="alttext-auth-form">
                                <h3>Sign In</h3>
                                <form id="login-form">
                                    <div class="alttext-form-group">
                                        <label for="login-email">Email</label>
                                        <input type="email" id="login-email" name="email" required>
                                    </div>
                                    <div class="alttext-form-group">
                                        <label for="login-password">Password</label>
                                        <input type="password" id="login-password" name="password" required>
                                    </div>
                                    <button type="submit" class="alttext-btn alttext-btn--primary">
                                        <span class="alttext-btn__text">Sign In</span>
                                        <span class="alttext-btn__spinner" style="display: none;">⏳</span>
                                    </button>
                                </form>
                                <p class="alttext-auth-switch">
                                    Don't have an account? 
                                    <a href="#" id="show-register">Create one here</a>
                                </p>
                            </div>

                            <!-- Register Form -->
                            <div id="alttext-register-form" class="alttext-auth-form" style="display: none;">
                                <h3>Create Account</h3>
                                <form id="register-form">
                                    <div class="alttext-form-group">
                                        <label for="register-email">Email</label>
                                        <input type="email" id="register-email" name="email" required>
                                    </div>
                                    <div class="alttext-form-group">
                                        <label for="register-password">Password</label>
                                        <input type="password" id="register-password" name="password" minlength="8" required>
                                        <small>Minimum 8 characters</small>
                                    </div>
                                    <div class="alttext-form-group">
                                        <label for="register-confirm">Confirm Password</label>
                                        <input type="password" id="register-confirm" name="confirmPassword" required>
                                    </div>
                                    <button type="submit" class="alttext-btn alttext-btn--primary">
                                        <span class="alttext-btn__text">Create Account</span>
                                        <span class="alttext-btn__spinner" style="display: none;">⏳</span>
                                    </button>
                                </form>
                                <p class="alttext-auth-switch">
                                    Already have an account? 
                                    <a href="#" id="show-login">Sign in here</a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    bindEvents() {
        // Close modal
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('alttext-auth-modal__close') || 
                e.target.classList.contains('alttext-auth-modal__overlay')) {
                this.hide();
            }
        });

        // Form switching
        document.getElementById('show-register')?.addEventListener('click', (e) => {
            e.preventDefault();
            this.showRegisterForm();
        });

        document.getElementById('show-login')?.addEventListener('click', (e) => {
            e.preventDefault();
            this.showLoginForm();
        });

        // Form submissions
        document.getElementById('login-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleLogin();
        });

        document.getElementById('register-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleRegister();
        });
    }

    show() {
        document.getElementById('alttext-auth-modal').style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    hide() {
        document.getElementById('alttext-auth-modal').style.display = 'none';
        document.body.style.overflow = '';
    }

    showLoginForm() {
        document.getElementById('alttext-login-form').style.display = 'block';
        document.getElementById('alttext-register-form').style.display = 'none';
    }

    showRegisterForm() {
        document.getElementById('alttext-login-form').style.display = 'none';
        document.getElementById('alttext-register-form').style.display = 'block';
    }

    async handleLogin() {
        const form = document.getElementById('login-form');
        const formData = new FormData(form);
        const email = formData.get('email');
        const password = formData.get('password');

        this.setLoading(form, true);

        try {
            const response = await fetch(window.alttextai_ajax?.ajax_url || '/wp-admin/admin-ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'alttextai_login',
                    email: email,
                    password: password,
                    nonce: window.alttextai_ajax?.nonce || ''
                })
            });

            const data = await response.json();

            if (data.success) {
                this.storeToken(data.token);
                this.token = data.token;
                this.hide();
                this.showSuccess('Welcome back! You are now signed in.');
                this.onAuthSuccess(data.user);
            } else {
                this.showError(data.error || 'Login failed');
            }
        } catch (error) {
            this.showError('Network error. Please try again.');
        } finally {
            this.setLoading(form, false);
        }
    }

    async handleRegister() {
        const form = document.getElementById('register-form');
        const formData = new FormData(form);
        const email = formData.get('email');
        const password = formData.get('password');
        const confirmPassword = formData.get('confirmPassword');

        if (password !== confirmPassword) {
            this.showError('Passwords do not match');
            return;
        }

        this.setLoading(form, true);

        try {
            const response = await fetch(window.alttextai_ajax?.ajax_url || '/wp-admin/admin-ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'alttextai_register',
                    email: email,
                    password: password,
                    nonce: window.alttextai_ajax?.nonce || ''
                })
            });

            const data = await response.json();

            if (data.success) {
                this.storeToken(data.token);
                this.token = data.token;
                this.hide();
                this.showSuccess('Account created successfully! Welcome to AltText AI.');
                this.onAuthSuccess(data.user);
            } else {
                this.showError(data.error || 'Registration failed');
            }
        } catch (error) {
            this.showError('Network error. Please try again.');
        } finally {
            this.setLoading(form, false);
        }
    }

    async checkAuthStatus() {
        if (!this.token) {
            this.showAuthRequired();
            return;
        }

        try {
            const response = await fetch(`${this.apiUrl}/auth/me`, {
                headers: {
                    'Authorization': `Bearer ${this.token}`
                }
            });

            if (response.ok) {
                const data = await response.json();
                this.onAuthSuccess(data.user);
            } else {
                this.clearToken();
                this.showAuthRequired();
            }
        } catch (error) {
            this.clearToken();
            this.showAuthRequired();
        }
    }

    showAuthRequired() {
        // Show auth modal or redirect to login
        const authButton = document.querySelector('[data-auth-required]');
        if (authButton) {
            authButton.addEventListener('click', (e) => {
                e.preventDefault();
                this.show();
            });
        }
    }

    onAuthSuccess(user) {
        // Update UI with user info
        this.updateUserDisplay(user);
        
        // Trigger custom event
        document.dispatchEvent(new CustomEvent('alttext:auth-success', {
            detail: { user, token: this.token }
        }));
    }

    updateUserDisplay(user) {
        // Update any user info displays
        const userElements = document.querySelectorAll('[data-user-email]');
        userElements.forEach(el => el.textContent = user.email);

        const planElements = document.querySelectorAll('[data-user-plan]');
        planElements.forEach(el => el.textContent = user.plan);

        const tokenElements = document.querySelectorAll('[data-user-tokens]');
        tokenElements.forEach(el => el.textContent = user.tokensRemaining);
    }

    storeToken(token) {
        localStorage.setItem('alttextai_token', token);
    }

    getStoredToken() {
        return localStorage.getItem('alttextai_token');
    }

    clearToken() {
        localStorage.removeItem('alttextai_token');
        this.token = null;
    }

    setLoading(form, loading) {
        const button = form.querySelector('button[type="submit"]');
        const text = button.querySelector('.alttext-btn__text');
        const spinner = button.querySelector('.alttext-btn__spinner');

        if (loading) {
            text.style.display = 'none';
            spinner.style.display = 'inline';
            button.disabled = true;
        } else {
            text.style.display = 'inline';
            spinner.style.display = 'none';
            button.disabled = false;
        }
    }

    showError(message) {
        // Remove existing alerts
        document.querySelectorAll('.alttext-alert').forEach(el => el.remove());

        const alert = document.createElement('div');
        alert.className = 'alttext-alert alttext-alert--error';
        alert.textContent = message;

        const modalBody = document.querySelector('.alttext-auth-modal__body');
        modalBody.insertBefore(alert, modalBody.firstChild);

        setTimeout(() => alert.remove(), 5000);
    }

    showSuccess(message) {
        // Remove existing alerts
        document.querySelectorAll('.alttext-alert').forEach(el => el.remove());

        const alert = document.createElement('div');
        alert.className = 'alttext-alert alttext-alert--success';
        alert.textContent = message;

        const modalBody = document.querySelector('.alttext-auth-modal__body');
        modalBody.insertBefore(alert, modalBody.firstChild);

        setTimeout(() => alert.remove(), 5000);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.AltTextAuthModal = new AltTextAuthModal();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AltTextAuthModal;
}
