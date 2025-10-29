/**
 * Authentication Modal for AltText AI
 * Handles user registration and login
 */

class AltTextAuthModal {
    constructor() {
        this.apiUrl = this.getApiUrl();
        this.token = this.getStoredToken();
        this.init();
    }

    getApiUrl() {
        // First check for configured API URL
        if (window.alttextai_ajax?.api_url) {
            return window.alttextai_ajax.api_url;
        }

        // Environment-based fallback for development
        const hostname = window.location.hostname;
        if (hostname === 'localhost' || hostname === '127.0.0.1') {
            return 'http://localhost:3001';
        }

        // No fallback for production - fail explicitly
        console.error('[AltText AI] API URL not configured');
        return null;
    }

    init() {
        this.createModalHTML();
        this.bindEvents();
        this.checkAuthStatus();
        this.checkResetPasswordParams();
    }

    checkResetPasswordParams() {
        // Check if URL contains reset token and email params
        const urlParams = new URLSearchParams(window.location.search);
        const resetToken = urlParams.get('reset-token');
        const resetEmail = urlParams.get('email');
        
        if (resetToken && resetEmail) {
            // Show reset password form
            this.show();
            this.showResetPasswordForm(resetEmail, resetToken);
        }
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
                                <form id="login-form" autocomplete="on">
                                    <div class="alttext-form-group">
                                        <label for="login-email">Email</label>
                                        <input type="email" id="login-email" name="email" autocomplete="username" required>
                                    </div>
                                    <div class="alttext-form-group">
                                        <label for="login-password">Password</label>
                                        <input type="password" id="login-password" name="password" autocomplete="current-password" required>
                                        <a href="#" id="show-forgot-password" class="alttext-forgot-password-link">Forgot password?</a>
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
                                <form id="register-form" autocomplete="off">
                                    <div class="alttext-form-group">
                                        <label for="register-email">Email</label>
                                        <input type="email" id="register-email" name="email" autocomplete="off" required>
                                    </div>
                                    <div class="alttext-form-group">
                                        <label for="register-password">Password</label>
                                        <input type="password" id="register-password" name="password" autocomplete="new-password" minlength="8" required>
                                        <small>Minimum 8 characters</small>
                                    </div>
                                    <div class="alttext-form-group">
                                        <label for="register-confirm">Confirm Password</label>
                                        <input type="password" id="register-confirm" name="confirmPassword" autocomplete="new-password" required>
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

                            <!-- Forgot Password Form -->
                            <div id="alttext-forgot-password-form" class="alttext-auth-form" style="display: none;">
                                <h3>Reset Password</h3>
                                <p class="alttext-forgot-password-info">Enter your email address and we'll send you a link to reset your password.</p>
                                <form id="forgot-password-form" autocomplete="on">
                                    <div class="alttext-form-group">
                                        <label for="forgot-email">Email</label>
                                        <input type="email" id="forgot-email" name="email" autocomplete="username" required>
                                    </div>
                                    <button type="submit" class="alttext-btn alttext-btn--primary">
                                        <span class="alttext-btn__text">Send Reset Link</span>
                                        <span class="alttext-btn__spinner" style="display: none;">⏳</span>
                                    </button>
                                </form>
                                <p class="alttext-auth-switch">
                                    Remember your password?
                                    <a href="#" id="show-login-from-forgot">Sign in here</a>
                                </p>
                            </div>

                            <!-- Reset Password Form -->
                            <div id="alttext-reset-password-form" class="alttext-auth-form" style="display: none;">
                                <h3>Set New Password</h3>
                                <p class="alttext-reset-password-info">Enter your new password below.</p>
                                <form id="reset-password-form" autocomplete="off">
                                    <div class="alttext-form-group">
                                        <label for="reset-email">Email</label>
                                        <input type="email" id="reset-email" name="email" autocomplete="username" required readonly>
                                    </div>
                                    <div class="alttext-form-group">
                                        <label for="reset-token">Reset Token</label>
                                        <input type="text" id="reset-token" name="token" required readonly>
                                    </div>
                                    <div class="alttext-form-group">
                                        <label for="reset-password">New Password</label>
                                        <input type="password" id="reset-password" name="password" autocomplete="new-password" minlength="8" required>
                                        <small>Minimum 8 characters</small>
                                    </div>
                                    <div class="alttext-form-group">
                                        <label for="reset-confirm">Confirm New Password</label>
                                        <input type="password" id="reset-confirm" name="confirmPassword" autocomplete="new-password" required>
                                    </div>
                                    <button type="submit" class="alttext-btn alttext-btn--primary">
                                        <span class="alttext-btn__text">Reset Password</span>
                                        <span class="alttext-btn__spinner" style="display: none;">⏳</span>
                                    </button>
                                </form>
                                <p class="alttext-auth-switch">
                                    <a href="#" id="show-login-from-reset">Back to sign in</a>
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
        const self = this;

        // Use event delegation from document for all modal events
        document.addEventListener('click', function(e) {
            // Close button
            if (e.target.closest('.alttext-auth-modal__close')) {
                e.preventDefault();
                e.stopPropagation();
                self.hide();
                return;
            }

            // Click on overlay background (not the content)
            if (e.target.classList.contains('alttext-auth-modal__overlay')) {
                self.hide();
                return;
            }

            // Form switching links
            if (e.target.id === 'show-register' || e.target.closest('#show-register')) {
                e.preventDefault();
                e.stopPropagation();
                self.showRegisterForm();
                return;
            }

            if (e.target.id === 'show-login' || e.target.closest('#show-login')) {
                e.preventDefault();
                e.stopPropagation();
                self.showLoginForm();
                return;
            }

            // Forgot password links
            if (e.target.id === 'show-forgot-password' || e.target.closest('#show-forgot-password')) {
                e.preventDefault();
                e.stopPropagation();
                self.showForgotPasswordForm();
                return;
            }

            if (e.target.id === 'show-login-from-forgot' || e.target.closest('#show-login-from-forgot')) {
                e.preventDefault();
                e.stopPropagation();
                self.showLoginForm();
                return;
            }

            if (e.target.id === 'show-login-from-reset' || e.target.closest('#show-login-from-reset')) {
                e.preventDefault();
                e.stopPropagation();
                self.showLoginForm();
                return;
            }
        }, true); // Use capture phase

        // Form submissions
        document.addEventListener('submit', function(e) {
            if (e.target.id === 'login-form') {
                e.preventDefault();
                e.stopPropagation();
                self.handleLogin();
                return;
            }

            if (e.target.id === 'register-form') {
                e.preventDefault();
                e.stopPropagation();
                self.handleRegister();
                return;
            }

            if (e.target.id === 'forgot-password-form') {
                e.preventDefault();
                e.stopPropagation();
                self.handleForgotPassword();
                return;
            }

            if (e.target.id === 'reset-password-form') {
                e.preventDefault();
                e.stopPropagation();
                self.handleResetPassword();
                return;
            }
        }, true); // Use capture phase

        // ESC key to close
        document.addEventListener('keydown', function(e) {
            const modal = document.getElementById('alttext-auth-modal');
            if (e.key === 'Escape' && modal && modal.style.display === 'block') {
                self.hide();
            }
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
        document.getElementById('alttext-forgot-password-form').style.display = 'none';
        document.getElementById('alttext-reset-password-form').style.display = 'none';
    }

    showRegisterForm() {
        document.getElementById('alttext-login-form').style.display = 'none';
        document.getElementById('alttext-register-form').style.display = 'block';
        document.getElementById('alttext-forgot-password-form').style.display = 'none';
        document.getElementById('alttext-reset-password-form').style.display = 'none';
    }

    showForgotPasswordForm() {
        document.getElementById('alttext-login-form').style.display = 'none';
        document.getElementById('alttext-register-form').style.display = 'none';
        document.getElementById('alttext-forgot-password-form').style.display = 'block';
        document.getElementById('alttext-reset-password-form').style.display = 'none';
    }

    showResetPasswordForm(email, token) {
        document.getElementById('alttext-login-form').style.display = 'none';
        document.getElementById('alttext-register-form').style.display = 'none';
        document.getElementById('alttext-forgot-password-form').style.display = 'none';
        document.getElementById('alttext-reset-password-form').style.display = 'block';
        
        // Pre-fill email and token from URL params
        const resetEmail = document.getElementById('reset-email');
        const resetToken = document.getElementById('reset-token');
        if (resetEmail && email) {
            resetEmail.value = email;
        }
        if (resetToken && token) {
            resetToken.value = token;
        }
    }

    async handleLogin() {
        const form = document.getElementById('login-form');
        const formData = new FormData(form);
        const email = formData.get('email');
        const password = formData.get('password');

        this.setLoading(form, true);

        // Validate AJAX config exists
        if (!window.alttextai_ajax?.ajaxurl) {
            console.error('[AltText AI] AJAX configuration not loaded');
            this.showError('Configuration error. Please refresh the page and try again.');
            this.setLoading(form, false);
            return;
        }

        try {
            const response = await fetch(window.alttextai_ajax.ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'alttextai_login',
                    email: email,
                    password: password,
                    nonce: window.alttextai_ajax.nonce
                })
            });

            const data = await response.json();

            if (data.success) {
                // WordPress AJAX success response
                const userData = data.data?.user || {};
                this.hide();
                this.showSuccess('Welcome back! You are now signed in.');

                // Reload page to refresh authentication state
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                // WordPress AJAX error response - message is in data.data.message
                const errorMessage = data.data?.message || data.message || 'Login failed';
                this.showError(errorMessage);
            }
        } catch (error) {
            console.error('Login error:', error);
            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                this.showError('Unable to connect to authentication server. The service may be temporarily unavailable. Please try again in a few minutes.');
            } else {
                this.showError('Network error. Please try again.');
            }
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

        // Validate AJAX config exists
        if (!window.alttextai_ajax?.ajaxurl) {
            console.error('[AltText AI] AJAX configuration not loaded');
            this.showError('Configuration error. Please refresh the page and try again.');
            this.setLoading(form, false);
            return;
        }

        try {
            const response = await fetch(window.alttextai_ajax.ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'alttextai_register',
                    email: email,
                    password: password,
                    nonce: window.alttextai_ajax.nonce
                })
            });

            const data = await response.json();

            if (data.success) {
                // WordPress AJAX success response
                const userData = data.data?.user || {};
                this.hide();
                this.showSuccess('Account created successfully! Welcome to AltText AI.');

                // Reload page to refresh authentication state
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                // WordPress AJAX error response - message is in data.data.message
                const errorMessage = data.data?.message || data.message || 'Registration failed';
                this.showError(errorMessage);
            }
        } catch (error) {
            console.error('Registration error:', error);
            this.showError('Network error. Please try again.');
        } finally {
            this.setLoading(form, false);
        }
    }

    async handleForgotPassword() {
        const form = document.getElementById('forgot-password-form');
        const formData = new FormData(form);
        const email = formData.get('email');

        this.setLoading(form, true);

        // Validate AJAX config exists
        if (!window.alttextai_ajax?.ajaxurl) {
            console.error('[AltText AI] AJAX configuration not loaded');
            this.showError('Configuration error. Please refresh the page and try again.');
            this.setLoading(form, false);
            return;
        }

        try {
            const response = await fetch(window.alttextai_ajax.ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'alttextai_forgot_password',
                    email: email,
                    nonce: window.alttextai_ajax.nonce
                })
            });

            const data = await response.json();

            if (data.success) {
                this.showSuccess('Reset link sent! Please check your email (including spam folder) for instructions. The link will expire in 1 hour.');
                // Clear form
                form.reset();
                // Redirect or close modal
                if (data.data?.redirect) {
                    setTimeout(() => {
                        window.location.href = data.data.redirect;
                    }, 2000);
                } else {
                    // Return to login after 3 seconds
                    setTimeout(() => {
                        this.showLoginForm();
                    }, 3000);
                }
            } else {
                // Parse error message for better UX
                const rawMessage = data.data?.message || data.message || 'Failed to send reset link';
                let userMessage = rawMessage;
                
                // Provide actionable error messages
                if (rawMessage.toLowerCase().includes('not found') || rawMessage.toLowerCase().includes('does not exist')) {
                    userMessage = 'No account found with this email address. Please check the spelling or sign up for a new account.';
                } else if (rawMessage.toLowerCase().includes('too many') || rawMessage.toLowerCase().includes('rate limit')) {
                    userMessage = 'Too many reset requests. Please wait 15 minutes before requesting another password reset.';
                } else if (rawMessage.toLowerCase().includes('temporarily unavailable') || rawMessage.toLowerCase().includes('unable to connect')) {
                    userMessage = 'The service is temporarily unavailable. Please try again in a few minutes.';
                }
                
                this.showError(userMessage);
            }
        } catch (error) {
            console.error('Forgot password error:', error);
            let errorMessage = 'Network error. Please try again.';
            
            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                errorMessage = 'Unable to connect to the server. Please check your internet connection and try again. If the problem persists, the service may be temporarily unavailable.';
            } else if (error.message && error.message.includes('timeout')) {
                errorMessage = 'Request timed out. Please check your internet connection and try again.';
            }
            
            this.showError(errorMessage);
        } finally {
            this.setLoading(form, false);
        }
    }

    async handleResetPassword() {
        const form = document.getElementById('reset-password-form');
        const formData = new FormData(form);
        const email = formData.get('email');
        const token = formData.get('token');
        const password = formData.get('password');
        const confirmPassword = formData.get('confirmPassword');

        if (password !== confirmPassword) {
            this.showError('Passwords do not match');
            return;
        }

        if (password.length < 8) {
            this.showError('Password must be at least 8 characters long');
            return;
        }

        this.setLoading(form, true);

        // Validate AJAX config exists
        if (!window.alttextai_ajax?.ajaxurl) {
            console.error('[AltText AI] AJAX configuration not loaded');
            this.showError('Configuration error. Please refresh the page and try again.');
            this.setLoading(form, false);
            return;
        }

        try {
            const response = await fetch(window.alttextai_ajax.ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'alttextai_reset_password',
                    email: email,
                    token: token,
                    password: password,
                    nonce: window.alttextai_ajax.nonce
                })
            });

            const data = await response.json();

            if (data.success) {
                this.showSuccess('Password reset successfully! Redirecting to sign in...');
                // Clear form
                form.reset();
                // Redirect if provided, otherwise reload
                if (data.data?.redirect) {
                    setTimeout(() => {
                        window.location.href = data.data.redirect;
                    }, 1500);
                } else {
                    // Show login form and reload page after 2 seconds
                    setTimeout(() => {
                        this.showLoginForm();
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }, 2000);
                }
            } else {
                // Parse error message for better UX
                const rawMessage = data.data?.message || data.message || 'Failed to reset password';
                let userMessage = rawMessage;
                
                // Provide actionable error messages
                if (rawMessage.toLowerCase().includes('expired') || rawMessage.toLowerCase().includes('invalid') && rawMessage.toLowerCase().includes('token')) {
                    userMessage = 'This password reset link has expired or is invalid. Please request a new password reset link.';
                } else if (rawMessage.toLowerCase().includes('token')) {
                    userMessage = 'Invalid reset token. Please check the link from your email or request a new one.';
                } else if (rawMessage.toLowerCase().includes('password') && rawMessage.toLowerCase().includes('weak') || rawMessage.toLowerCase().includes('strength')) {
                    userMessage = 'Password is too weak. Please choose a stronger password with at least 8 characters, including letters and numbers.';
                } else if (rawMessage.toLowerCase().includes('network') || rawMessage.toLowerCase().includes('unable to connect')) {
                    userMessage = 'Unable to connect to the server. Please check your internet connection and try again.';
                }
                
                this.showError(userMessage);
            }
        } catch (error) {
            console.error('Reset password error:', error);
            let errorMessage = 'Network error. Please try again.';
            
            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                errorMessage = 'Unable to connect to the server. Please check your internet connection and try again. If the problem persists, the service may be temporarily unavailable.';
            } else if (error.message && error.message.includes('timeout')) {
                errorMessage = 'Request timed out. Please check your internet connection and try again.';
            }
            
            this.showError(errorMessage);
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
        // Also store in WordPress for server-side access
        fetch(window.alttextai_ajax?.ajax_url || '/wp-admin/admin-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                action: 'alttextai_store_token',
                token: token,
                nonce: window.alttextai_ajax?.nonce || ''
            })
        });
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
    window.authModal = window.AltTextAuthModal; // Alias for compatibility
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AltTextAuthModal;
}
