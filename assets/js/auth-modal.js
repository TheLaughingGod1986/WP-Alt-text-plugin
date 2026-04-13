/**
 * Authentication Modal for AltText AI
 * Handles user registration and login
 */

class BbAIAuthModal {
    constructor() {
        this.apiUrl = this.getApiUrl();
        this.token = this.getStoredToken();
        this.modalContext = 'fix';
        this.signupContext = 'fix';
        this.authStep = 'intro';
        this.modalContent = {
            fix: {
                title: 'Continue fixing your images',
                subtitle: 'Create a free account to continue fixing your {remainingImages} and unlock full access.',
                progress: 'You\'re {imageCount} away from full optimisation',
                cta: 'Create free account'
            },
            tabs: {
                title: 'Fix your missing ALT text',
                subtitle: 'Create a free account to review and fix your images.',
                progress: 'You\'re {imageCount} away from full optimisation',
                cta: 'Create free account'
            },
            library: {
                title: 'Unlock your full ALT library',
                subtitle: 'Create a free account to access your full image library and bulk optimise.',
                progress: 'You\'re {imageCount} away from full optimisation',
                cta: 'Create free account'
            },
            login: {
                title: 'Welcome back',
                subtitle: 'Log in to continue where you left off.',
                cta: 'Login'
            }
        };
        // Cache DOM elements for better performance
        this.modalElement = null;
        this.formElements = {};
        this.init();
    }

    getApiUrl() {
        // First check for configured API URL
        if (window.bbai_ajax?.api_url) {
            return window.bbai_ajax.api_url;
        }

        // No fallback for production - fail explicitly
        window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] API URL not configured');
        return null;
    }

    init() {
        this.createModalHTML();
        // Cache modal element after creation
        this.modalElement = document.getElementById('alttext-auth-modal');
        // Cache form elements
        this.formElements = {
            login: document.getElementById('alttext-login-form'),
            register: document.getElementById('alttext-register-form'),
            forgotPassword: document.getElementById('alttext-forgot-password-form'),
            resetPassword: document.getElementById('alttext-reset-password-form')
        };
        this.bindEvents();
        this.checkAuthStatus();
        this.checkResetPasswordParams();
    }

    emitAnalyticsEvent(eventName, properties) {
        try {
            document.dispatchEvent(new CustomEvent('bbai:analytics', {
                detail: Object.assign({ event: eventName }, properties || {})
            }));
        } catch (error) {
            // Ignore analytics dispatch failures.
        }
    }

    resolveSource(trigger, fallback) {
        if (window.bbaiAnalytics && typeof window.bbaiAnalytics.resolveSource === 'function') {
            return window.bbaiAnalytics.resolveSource(trigger || this.modalElement);
        }

        return fallback || 'modal';
    }

    setHeaderCopy(title, subtitle) {
        if (!this.modalElement) {
            return;
        }

        const titleNode = this.modalElement.querySelector('#alttext-auth-modal-title');
        const subtitleNode = this.modalElement.querySelector('#alttext-auth-modal-desc');

        if (titleNode && title) {
            titleNode.textContent = title;
        }

        if (subtitleNode && subtitle) {
            subtitleNode.textContent = subtitle;
        }
    }

    normalizeModalContext(context) {
        const value = String(context || '').toLowerCase();

        if (
            value === 'filter'
            || value === 'tabs'
            || value === 'tab'
            || value === 'all'
            || value === 'missing'
            || value === 'review'
            || value === 'weak'
            || value === 'needs_review'
            || value === 'needs-review'
            || value === 'optimized'
            || value === 'optimised'
        ) {
            return 'tabs';
        }

        if (
            value === 'library'
            || value === 'locked-library'
            || value === 'locked_library'
            || value === 'full-library'
            || value === 'full_library'
        ) {
            return 'library';
        }

        return this.modalContent[value] ? value : 'fix';
    }

    getRemainingImageCount() {
        const root = document.querySelector('[data-bbai-dashboard-root="1"], [data-bbai-library-workspace-root="1"], [data-bbai-dashboard-state-root="1"]');
        const missing = root ? parseInt(root.getAttribute('data-bbai-missing-count') || '0', 10) || 0 : 0;
        const weak = root ? parseInt(root.getAttribute('data-bbai-weak-count') || '0', 10) || 0 : 0;
        const actionable = root ? parseInt(root.getAttribute('data-bbai-actionable-count') || '0', 10) || 0 : 0;

        return Math.max(0, missing || actionable || (missing + weak));
    }

    interpolateModalCopy(copy) {
        const count = this.getRemainingImageCount();
        const imageCountText = count > 0
            ? count.toLocaleString() + (count === 1 ? ' image' : ' images')
            : 'images';
        const remainingImageText = count > 0
            ? 'remaining ' + imageCountText
            : 'remaining images';

        return String(copy || '')
            .replace('{remainingImages}', remainingImageText)
            .replace('{imageCount}', imageCountText)
            .replace('{count}', count > 0 ? count.toLocaleString() : '')
            .replace(/\s{2,}/g, ' ')
            .trim();
    }

    setModalContext(context) {
        this.modalContext = this.normalizeModalContext(context);

        if (this.modalContext !== 'login') {
            this.signupContext = this.modalContext;
        }

        if (this.modalElement) {
            this.modalElement.setAttribute('data-bbai-modal-context', this.modalContext);
        }

        return this.modalContext;
    }

    getModalContextFromTrigger(trigger, requestedTab) {
        const explicit = trigger && trigger.getAttribute
            ? trigger.getAttribute('data-bbai-modal-context')
            : '';
        const source = trigger && trigger.getAttribute
            ? String(trigger.getAttribute('data-bbai-locked-source') || '')
            : '';
        const segment = trigger && trigger.getAttribute
            ? String(trigger.getAttribute('data-bbai-status-segment') || trigger.getAttribute('data-bbai-review-segment') || '')
            : '';

        if (explicit) {
            return explicit;
        }

        if (requestedTab === 'login') {
            return 'login';
        }

        if (source.toLowerCase().indexOf('library') !== -1 || (trigger && trigger.closest && trigger.closest('[data-bbai-library-workspace-root="1"], .bbai-library-container'))) {
            return 'library';
        }

        if (segment) {
            return 'tabs';
        }

        return 'fix';
    }

    getModalContent(context) {
        const normalizedContext = this.normalizeModalContext(context || this.modalContext);
        const content = this.modalContent[normalizedContext] || this.modalContent.fix;

        return {
            context: normalizedContext,
            title: content.title,
            subtitle: this.interpolateModalCopy(content.subtitle),
            progress: this.getRemainingImageCount() > 0 ? this.interpolateModalCopy(content.progress) : '',
            cta: content.cta
        };
    }

    setSubmitButtonText(formId, label) {
        const form = document.getElementById(formId);
        const button = form ? form.querySelector('button[type="submit"]') : null;
        const text = button ? button.querySelector('.alttext-btn__text') : null;

        if (!button || !text || !label) {
            return;
        }

        text.textContent = label;
        button.setAttribute('aria-label', label);
    }

    setIntroButtonText(label) {
        const button = this.modalElement ? this.modalElement.querySelector('#alttext-auth-start-register') : null;

        if (!button || !label) {
            return;
        }

        button.textContent = label;
        button.setAttribute('aria-label', label);
    }

    setConversionHeaderCopy() {
        const content = this.getModalContent();
        const registerContent = this.modalContext === 'login' ? this.getModalContent('fix') : content;
        this.setHeaderCopy(content.title, content.subtitle);
        this.setIntroButtonText(registerContent.cta);
        this.setSubmitButtonText('register-form', registerContent.cta);
        this.setSubmitButtonText('login-form', this.modalContent.login.cta);

        const contextNode = this.modalElement ? this.modalElement.querySelector('#alttext-auth-modal-context') : null;
        const context = this.modalContext === 'login' ? '' : content.progress;

        if (!contextNode) {
            return;
        }

        if (context) {
            contextNode.textContent = context;
            contextNode.hidden = false;
        } else {
            contextNode.textContent = '';
            contextNode.hidden = true;
        }
    }

    focusField(selector) {
        if (!this.modalElement || !selector) {
            return;
        }

        const field = this.modalElement.querySelector(selector);

        if (field && typeof field.focus === 'function') {
            window.setTimeout(() => field.focus(), 0);
        }
    }

    getConversionContext() {
        const root = document.querySelector('[data-bbai-dashboard-root="1"]');
        const missing = root ? parseInt(root.getAttribute('data-bbai-missing-count') || '0', 10) || 0 : 0;

        if (missing === 1) {
            return 'You\'re 1 image away from full optimisation';
        }

        if (missing > 1) {
            return 'You\'re ' + missing.toLocaleString() + ' images away from full optimisation';
        }

        return '';
    }

    setAuthStep(step) {
        this.authStep = step === 'form' ? 'form' : 'intro';

        if (!this.modalElement) {
            return;
        }

        const introStep = this.modalElement.querySelector('#alttext-auth-value-step');
        const formStep = this.modalElement.querySelector('#alttext-auth-form-step');

        this.modalElement.setAttribute('data-bbai-auth-step', this.authStep);

        if (introStep) {
            introStep.hidden = this.authStep !== 'intro';
        }

        if (formStep) {
            formStep.hidden = this.authStep !== 'form';
        }
    }

    hideAllForms() {
        if (this.formElements.login) this.formElements.login.style.display = 'none';
        if (this.formElements.register) this.formElements.register.style.display = 'none';
        if (this.formElements.forgotPassword) this.formElements.forgotPassword.style.display = 'none';
        if (this.formElements.resetPassword) this.formElements.resetPassword.style.display = 'none';
    }

    getPostAuthRedirectUrl() {
        try {
            const currentUrl = new URL(window.location.href);
            const currentPage = String(currentUrl.searchParams.get('page') || '').toLowerCase();

            if (currentPage && currentPage.indexOf('bbai') === 0) {
                currentUrl.searchParams.delete('bbai_open_auth');
                currentUrl.searchParams.delete('bbai_auth_tab');
                currentUrl.searchParams.delete('bbai_auth_context');
                currentUrl.searchParams.delete('checkout');
                currentUrl.searchParams.delete('checkout_error');
                return currentUrl.toString();
            }
        } catch (error) {
            // Fall through to the static fallback below.
        }

        const adminUrl = window.bbai_ajax?.admin_url || 'admin.php';
        return `${adminUrl}?page=bbai`;
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

    checkPasswordStrength(fieldId, password) {
        // Cache these lookups (called frequently during typing)
        const strengthId = fieldId.replace('-password', '-password-strength');
        const fillId = fieldId.replace('-password', '-password-strength-fill');
        const labelId = fieldId.replace('-password', '-password-strength-label');
        const hintId = fieldId.replace('-password', '-password-hint');
        
        const strengthContainer = document.getElementById(strengthId);
        const strengthFill = document.getElementById(fillId);
        const strengthLabel = document.getElementById(labelId);
        const hint = document.getElementById(hintId);

        if (!strengthContainer || !strengthFill || !strengthLabel) {
            return;
        }

        if (!password || password.length === 0) {
            strengthContainer.style.display = 'none';
            if (hint) hint.style.display = 'block';
            return;
        }

        strengthContainer.style.display = 'block';
        if (hint) hint.style.display = 'none';

        let strength = 0;
        let label = '';
        let color = '';

        // Length check
        if (password.length >= 8) strength++;
        if (password.length >= 12) strength++;

        // Complexity checks
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
        if (/\d/.test(password)) strength++;
        if (/[^a-zA-Z\d]/.test(password)) strength++;

        // Determine strength level
        if (strength <= 1) {
            label = 'Weak';
            color = '#ef4444'; // red
            strengthFill.style.width = '25%';
        } else if (strength === 2) {
            label = 'Fair';
            color = '#f59e0b'; // orange
            strengthFill.style.width = '50%';
        } else if (strength === 3) {
            label = 'Good';
            color = '#3b82f6'; // blue
            strengthFill.style.width = '75%';
        } else {
            label = 'Strong';
            color = '#10b981'; // green
            strengthFill.style.width = '100%';
        }

        strengthFill.style.backgroundColor = color;
        strengthLabel.textContent = label;
        strengthLabel.style.color = color;
    }

    createModalHTML() {
        if (document.getElementById('alttext-auth-modal')) {
            return;
        }
        const modalHTML = `
            <div id="alttext-auth-modal" class="alttext-auth-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="alttext-auth-modal-title" aria-describedby="alttext-auth-modal-desc">
                <div class="alttext-auth-modal__overlay">
                    <div class="alttext-auth-modal__content">
                        <!-- Header with Icon + Close Button -->
                            <button class="alttext-auth-modal__close" type="button" aria-label="Close dialog">&times;</button>
                        
                        <div class="alttext-auth-modal__header">
                            <h2 class="alttext-auth-modal__title" id="alttext-auth-modal-title">Continue fixing your images</h2>
                            <p class="alttext-auth-modal__subtitle" id="alttext-auth-modal-desc">Create a free account to continue fixing your remaining images and unlock full access.</p>
                            <p class="alttext-auth-modal__context" id="alttext-auth-modal-context" hidden></p>
                        </div>
                        
                        <div class="alttext-auth-modal__body">
                            <div id="alttext-auth-value-step" class="alttext-auth-modal__value-step">
                                <ul class="alttext-auth-modal__value-list" aria-label="Account benefits">
                                    <li>Review and edit ALT text</li>
                                    <li>Bulk optimise your media library</li>
                                    <li>50 generations per month</li>
                                </ul>
                                <div class="alttext-auth-modal__intro-actions">
                                    <button type="button" id="alttext-auth-start-register" class="alttext-btn alttext-btn--primary alttext-auth-modal__intro-cta" aria-label="Create free account">Create free account</button>
                                    <button type="button" id="alttext-auth-intro-login" class="alttext-auth-modal__secondary-cta">Already have an account? Sign in</button>
                                </div>
                            </div>

                            <div id="alttext-auth-form-step" class="alttext-auth-modal__form-step" hidden>
                            <!-- Login Form -->
                            <div id="alttext-login-form" class="alttext-auth-form">
                                <form id="login-form" autocomplete="off" aria-label="Sign in to your BeepBeep AI account">
                                    <div class="alttext-form-group">
                                        <label for="login-email">Email</label>
                                        <input type="email" id="login-email" name="email" placeholder="Email" autocomplete="off" required aria-required="true">
                                    </div>
                                    <div class="alttext-form-group">
                                        <div class="alttext-form-group__header">
                                        <label for="login-password">Password</label>
                                        <a href="#" id="show-forgot-password" class="alttext-forgot-password-link" aria-label="Reset your password">Forgot password?</a>
                                        </div>
                                        <input type="text" id="login-password" name="password" data-password-field="true" data-password-autocomplete="current-password" autocomplete="off" inputmode="text" required aria-required="true">
                                    </div>
                                    <button type="submit" class="alttext-btn alttext-btn--primary" aria-label="Sign in">
                                        <span class="alttext-btn__text">Sign In</span>
                                        <span class="alttext-btn__spinner" style="display: none;" aria-hidden="true">⏳</span>
                                    </button>
                                </form>
                                <p class="alttext-auth-switch">
                                    Don't have an account? <a href="#" id="show-register" aria-label="Switch to registration form">Create one</a>
                                </p>
                            </div>

                            <!-- Register Form -->
                            <div id="alttext-register-form" class="alttext-auth-form" style="display: none;">
                                <form id="register-form" autocomplete="off" aria-label="Create a new BeepBeep AI account">
                                    <div class="alttext-form-group">
                                        <label for="register-email">Email</label>
                                        <input type="email" id="register-email" name="email" placeholder="Email" autocomplete="off" required aria-required="true">
                                    </div>
                                    <div class="alttext-form-group">
                                        <label for="register-password">Password</label>
                                        <input type="text" id="register-password" name="password" data-password-field="true" data-password-autocomplete="new-password" autocomplete="off" inputmode="text" minlength="8" required aria-required="true" aria-describedby="register-password-strength-label register-password-hint">
                                        <div class="alttext-password-strength" id="register-password-strength" style="display: none;" role="status" aria-live="polite" aria-atomic="true">
                                            <div class="alttext-password-strength-bar" aria-hidden="true">
                                                <div class="alttext-password-strength-fill" id="register-password-strength-fill" aria-hidden="true"></div>
                                            </div>
                                            <span class="alttext-password-strength-label" id="register-password-strength-label"></span>
                                        </div>
                                        <small id="register-password-hint">Minimum 8 characters</small>
                                    </div>
                                    <div class="alttext-form-group">
                                        <label for="register-confirm">Confirm Password</label>
                                        <input type="text" id="register-confirm" name="confirmPassword" data-password-field="true" data-password-autocomplete="new-password" autocomplete="off" inputmode="text" required aria-required="true">
                                    </div>
                                    <button type="submit" class="alttext-btn alttext-btn--primary" aria-label="Create account">
                                        <span class="alttext-btn__text">Create free account</span>
                                        <span class="alttext-btn__spinner" style="display: none;" aria-hidden="true">⏳</span>
                                    </button>
                                </form>
                                <p class="alttext-auth-switch">
                                    Already have an account? <a href="#" id="show-login" aria-label="Switch to sign in form">Sign in</a>
                                </p>
                            </div>

                            <!-- Forgot Password Form -->
                            <div id="alttext-forgot-password-form" class="alttext-auth-form" style="display: none;">
                                <h3>Reset Password</h3>
                                <p class="alttext-forgot-password-info">Enter your email address and we'll send you a link to reset your password.</p>
                                <form id="forgot-password-form" autocomplete="on" aria-label="Request password reset">
                                    <div class="alttext-form-group">
                                        <label for="forgot-email">Email</label>
                                        <input type="email" id="forgot-email" name="email" autocomplete="username" required aria-required="true">
                                    </div>
                                    <button type="submit" class="alttext-btn alttext-btn--primary" aria-label="Send password reset link">
                                        <span class="alttext-btn__text">Send Reset Link</span>
                                        <span class="alttext-btn__spinner" style="display: none;" aria-hidden="true">⏳</span>
                                    </button>
                                </form>
                                <p class="alttext-auth-switch">
                                    Remember your password?
                                    <a href="#" id="show-login-from-forgot" aria-label="Switch to sign in form">Sign in here</a>
                                </p>
                            </div>

                            <!-- Reset Password Form -->
                            <div id="alttext-reset-password-form" class="alttext-auth-form" style="display: none;">
                                <h3>Set New Password</h3>
                                <p class="alttext-reset-password-info">Enter your new password below.</p>
                                <form id="reset-password-form" autocomplete="off" aria-label="Reset your password">
                                    <div class="alttext-form-group">
                                        <label for="reset-email">Email</label>
                                        <input type="email" id="reset-email" name="email" autocomplete="off" required readonly aria-readonly="true">
                                    </div>
                                    <div class="alttext-form-group">
                                        <label for="reset-token">Reset Token</label>
                                        <input type="text" id="reset-token" name="token" autocomplete="off" required readonly aria-readonly="true">
                                    </div>
                                    <div class="alttext-form-group">
                                        <label for="reset-password">New Password</label>
                                        <input type="text" id="reset-password" name="password" data-password-field="true" data-password-autocomplete="new-password" autocomplete="off" inputmode="text" minlength="8" required aria-required="true" aria-describedby="reset-password-strength-label reset-password-hint">
                                        <div class="alttext-password-strength" id="reset-password-strength" style="display: none;" role="status" aria-live="polite" aria-atomic="true">
                                            <div class="alttext-password-strength-bar" aria-hidden="true">
                                                <div class="alttext-password-strength-fill" id="reset-password-strength-fill" aria-hidden="true"></div>
                                            </div>
                                            <span class="alttext-password-strength-label" id="reset-password-strength-label"></span>
                                        </div>
                                        <small id="reset-password-hint">Minimum 8 characters</small>
                                    </div>
                                    <div class="alttext-form-group">
                                        <label for="reset-confirm">Confirm New Password</label>
                                        <input type="text" id="reset-confirm" name="confirmPassword" data-password-field="true" data-password-autocomplete="new-password" autocomplete="off" inputmode="text" required aria-required="true">
                                    </div>
                                    <button type="submit" class="alttext-btn alttext-btn--primary" aria-label="Reset password">
                                        <span class="alttext-btn__text">Reset Password</span>
                                        <span class="alttext-btn__spinner" style="display: none;" aria-hidden="true">⏳</span>
                                    </button>
                                </form>
                                <p class="alttext-auth-switch">
                                    <a href="#" id="show-login-from-reset" aria-label="Switch to sign in form">Back to sign in</a>
                                </p>
                            </div>
                            </div>
                        </div>
                        
                        <!-- Growth Upsell Strip -->
                        <div class="alttext-auth-modal__footer">
                            <p class="alttext-auth-modal__upsell">Growth users get 1,000 AI alt texts per month + bulk processing + priority queue.</p>
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
            // Global CTA triggers (works even if dashboard script fails)
            const target = e.target;
            let authTrigger = target.closest('[data-action="show-auth-modal"], [data-action="show-dashboard-auth"]');
            
            // Also check for ID-based triggers (for hero section buttons)
            if (!authTrigger) {
                const clickedElement = target.closest('button, a') || target;
                if (clickedElement.id === 'bbai-show-auth-banner-btn' || clickedElement.id === 'bbai-show-auth-login-btn') {
                    authTrigger = clickedElement;
                }
            }
            
            if (authTrigger) {
                e.preventDefault();
                e.stopPropagation();

                const requestedTab = authTrigger.getAttribute('data-auth-tab') || authTrigger.dataset?.authTab || 
                                   (authTrigger.id === 'bbai-show-auth-login-btn' ? 'login' : 'register');
                const source = self.resolveSource(authTrigger, 'dashboard');
                const modalContext = self.getModalContextFromTrigger(authTrigger, requestedTab);

                if (requestedTab === 'register') {
                    self.emitAnalyticsEvent('signup_cta_clicked', { source: source });
                } else {
                    self.emitAnalyticsEvent('login_cta_clicked', { source: source });
                    self.emitAnalyticsEvent('login_modal_opened', { source: source });
                }

                self.show({
                    context: modalContext
                });
                if (requestedTab === 'register') {
                    self.showRegisterForm(modalContext);
                } else {
                    self.showLoginForm('login');
                }
                return;
            }

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
            if (e.target.id === 'alttext-auth-start-register' || e.target.closest('#alttext-auth-start-register')) {
                e.preventDefault();
                e.stopPropagation();
                self.emitAnalyticsEvent('signup_intro_cta_clicked', { source: 'modal' });
                self.showRegisterForm(self.signupContext || self.modalContext || 'fix', { forceForm: true });
                return;
            }

            if (e.target.id === 'alttext-auth-intro-login' || e.target.closest('#alttext-auth-intro-login')) {
                e.preventDefault();
                e.stopPropagation();
                self.showLoginForm('login');
                return;
            }

            if (e.target.id === 'show-register' || e.target.closest('#show-register')) {
                e.preventDefault();
                e.stopPropagation();
                self.emitAnalyticsEvent('signup_cta_clicked', { source: 'modal' });
                self.showRegisterForm(self.signupContext || 'fix', { forceForm: true });
                return;
            }

            if (e.target.id === 'show-login' || e.target.closest('#show-login')) {
                e.preventDefault();
                e.stopPropagation();
                self.showLoginForm('login');
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
                self.showLoginForm('login');
                return;
            }

            if (e.target.id === 'show-login-from-reset' || e.target.closest('#show-login-from-reset')) {
                e.preventDefault();
                e.stopPropagation();
                self.showLoginForm('login');
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
            if (e.key === 'Escape' && self.modalElement && self.modalElement.style.display === 'block') {
                self.hide();
            }
            
            // Focus trapping: keep focus within modal when open
            if (self.modalElement && self.modalElement.style.display === 'block') {
                const focusableElements = Array.prototype.slice.call(self.modalElement.querySelectorAll(
                    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
                )).filter(function(element) {
                    return !element.disabled
                        && !element.closest('[hidden]')
                        && element.offsetParent !== null;
                });
                const firstElement = focusableElements[0];
                const lastElement = focusableElements[focusableElements.length - 1];
                
                if (e.key === 'Tab' && firstElement && lastElement) {
                    if (e.shiftKey && document.activeElement === firstElement) {
                        e.preventDefault();
                        lastElement.focus();
                    } else if (!e.shiftKey && document.activeElement === lastElement) {
                        e.preventDefault();
                        firstElement.focus();
                    }
                }
            }
        });

        // Password strength indicator
        document.addEventListener('input', function(e) {
            if (e.target.id === 'reset-password' || e.target.id === 'register-password') {
                self.checkPasswordStrength(e.target.id, e.target.value);
            }
        });
    }

    enablePasswordFields() {
        if (!this.modalElement) return;
        const fields = this.modalElement.querySelectorAll('[data-password-field]');
        fields.forEach((input) => {
            input.type = 'password';
            const mode = input.dataset.passwordAutocomplete || 'off';
            input.setAttribute('autocomplete', mode);
        });
    }

    resetPasswordFields() {
        if (!this.modalElement) return;
        const fields = this.modalElement.querySelectorAll('[data-password-field]');
        fields.forEach((input) => {
            input.type = 'text';
            input.setAttribute('autocomplete', 'off');
            if (input.value) {
                input.value = '';
            }
        });
    }

    show(options) {
        const requestedContext = typeof options === 'string'
            ? options
            : (options && options.context ? options.context : '');

        if (this.modalElement) {
            if (requestedContext) {
                this.setModalContext(requestedContext);
            } else if (this.modalContext === 'login') {
                this.setModalContext(this.signupContext || 'fix');
            }

            this.setConversionHeaderCopy();

            if (!requestedContext && this.authStep === 'form') {
                this.setAuthStep('intro');
                this.hideAllForms();
            }

            if (this.modalElement.parentElement !== document.body) {
                document.body.appendChild(this.modalElement);
            }

            this.modalElement.style.display = 'block';
            this.modalElement.removeAttribute('aria-hidden');
            this.modalElement.setAttribute('data-bbai-auth-modal-visible', '1');
            document.body.classList.add('bbai-auth-modal-open');
            document.body.style.overflow = 'hidden';
            this.enablePasswordFields();
            
            // Focus trap: focus on first input or close button
            const firstInput = this.modalElement.querySelector('input[type="email"], input[type="password"], button');
            if (firstInput) {
                firstInput.focus();
            }
        }
    }

    hide() {
        if (this.modalElement) {
            this.modalElement.style.display = 'none';
            this.modalElement.setAttribute('aria-hidden', 'true');
            this.modalElement.removeAttribute('data-bbai-auth-modal-visible');
            document.body.classList.remove('bbai-auth-modal-open');
            document.body.style.overflow = '';
            this.resetPasswordFields();
        }
    }

    showLoginForm(context) {
        this.setModalContext(context || 'login');
        this.setConversionHeaderCopy();
        this.setAuthStep('form');
        // Use cached form elements
        if (this.formElements.login) this.formElements.login.style.display = 'block';
        if (this.formElements.register) this.formElements.register.style.display = 'none';
        if (this.formElements.forgotPassword) this.formElements.forgotPassword.style.display = 'none';
        if (this.formElements.resetPassword) this.formElements.resetPassword.style.display = 'none';
        this.focusField('#login-email');
    }

    showRegisterForm(context, options) {
        if (context) {
            this.setModalContext(context);
        } else if (this.modalContext === 'login') {
            this.setModalContext(this.signupContext || 'fix');
        } else {
            this.setModalContext(this.modalContext || 'fix');
        }
        this.setConversionHeaderCopy();
        if (!options || options.forceForm !== true) {
            this.setAuthStep('intro');
            this.hideAllForms();
            this.focusField('#alttext-auth-start-register');
            return;
        }
        this.setAuthStep('form');
        // Use cached form elements
        if (this.formElements.login) this.formElements.login.style.display = 'none';
        if (this.formElements.register) this.formElements.register.style.display = 'block';
        if (this.formElements.forgotPassword) this.formElements.forgotPassword.style.display = 'none';
        if (this.formElements.resetPassword) this.formElements.resetPassword.style.display = 'none';
        this.focusField('#register-email');
    }

    showForgotPasswordForm() {
        this.setAuthStep('form');
        // Use cached form elements
        if (this.formElements.login) this.formElements.login.style.display = 'none';
        if (this.formElements.register) this.formElements.register.style.display = 'none';
        if (this.formElements.forgotPassword) this.formElements.forgotPassword.style.display = 'block';
        if (this.formElements.resetPassword) this.formElements.resetPassword.style.display = 'none';
    }

    showResetPasswordForm(email, token) {
        this.setAuthStep('form');
        // Use cached form elements
        if (this.formElements.login) this.formElements.login.style.display = 'none';
        if (this.formElements.register) this.formElements.register.style.display = 'none';
        if (this.formElements.forgotPassword) this.formElements.forgotPassword.style.display = 'none';
        if (this.formElements.resetPassword) this.formElements.resetPassword.style.display = 'block';
        
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
        const source = this.resolveSource(this.modalElement, 'modal');

        this.setLoading(form, true);
        this.emitAnalyticsEvent('login_submitted', {
            source: source
        });

        // Validate AJAX config exists
        if (!window.bbai_ajax?.ajaxurl) {
            window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] AJAX configuration not loaded');
            this.showError('Configuration error. Please refresh the page and try again.');
            this.setLoading(form, false);
            return;
        }

        try {
            const response = await fetch(window.bbai_ajax.ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'beepbeepai_login',
                    email: email,
                    password: password,
                    nonce: window.bbai_ajax.nonce
                })
            });

            const data = await response.json();

            if (data.success) {
                // WordPress AJAX success response
                const userData = data.data?.user || {};
                this.emitAnalyticsEvent('login_succeeded', {
                    source: source
                });
                this.onAuthSuccess(userData);
                this.hide();
                this.showSuccess('Welcome back! You are now signed in to SEO AI Alt Text.');

                // Reload page to refresh authentication state and show dashboard
                // Clear any cached auth state first
                if (window.BBAI_DASH) {
                    delete window.BBAI_DASH.isAuthenticated;
                }
                if (window.bbai_ajax) {
                    // Force update authentication state
                    window.bbai_ajax.is_authenticated = true;
                }

                const redirectUrl = this.getPostAuthRedirectUrl();
                setTimeout(() => {
                    window.location.href = redirectUrl;
                }, 800);
            } else {
                // WordPress AJAX error response - message is in data.data.message
                const errorMessage = data.data?.message || data.message || 'Login failed';
                const rawErrorCode = data.data?.code || data.code || '';
                const errorCode = String(rawErrorCode || '').toLowerCase();
                const existingEmail = data.data?.existing_email || '';
                const inviteUrl = data.data?.invite_url || data.invite_url || '';

                // Site is locked to one connected account: prefill expected email.
                if (errorCode === 'site_has_license' && existingEmail) {
                    const loginEmailInput = document.getElementById('login-email');
                    if (loginEmailInput) {
                        loginEmailInput.value = existingEmail;
                    }
                }

                if (errorCode === 'invite_required' && inviteUrl) {
                    this.showError(`${errorMessage} ${inviteUrl}`);
                } else {
                    this.showError(errorMessage);
                }
                this.emitAnalyticsEvent('login_failed', {
                    source: source,
                    error_code: errorCode || 'login_failed'
                });
                // Clear portal flag on login failure
                localStorage.removeItem('alttextai_open_portal_after_login');
            }
        } catch (error) {
            window.BBAI_LOG && window.BBAI_LOG.error('Login error:', error);
            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                this.showError('Unable to connect to authentication server. The service may be temporarily unavailable. Please try again in a few minutes.');
            } else {
                this.showError('Network error. Please try again.');
            }
            this.emitAnalyticsEvent('login_failed', {
                source: source,
                error_code: 'network_error'
            });
            // Clear portal flag on network error
            localStorage.removeItem('alttextai_open_portal_after_login');
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
        const source = this.resolveSource(this.modalElement, 'modal');

        if (password !== confirmPassword) {
            this.showError('Passwords do not match');
            return;
        }

        this.setLoading(form, true);
        this.emitAnalyticsEvent('signup_started', {
            source: source
        });

        // Validate AJAX config exists
        if (!window.bbai_ajax?.ajaxurl) {
            window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] AJAX configuration not loaded');
            this.showError('Configuration error. Please refresh the page and try again.');
            this.setLoading(form, false);
            return;
        }

        try {
            const response = await fetch(window.bbai_ajax.ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'beepbeepai_register',
                    email: email,
                    password: password,
                    nonce: window.bbai_ajax.nonce
                })
            });

            const data = await response.json();

            if (data.success) {
                // WordPress AJAX success response
                const userData = data.data?.user || {};
                this.emitAnalyticsEvent('signup_succeeded', {
                    source: source
                });
                this.onAuthSuccess(userData);
                this.hide();
                this.showSuccess('Account created successfully! Welcome to SEO AI Alt Text.');

                const redirectUrl = this.getPostAuthRedirectUrl();
                setTimeout(() => {
                    window.location.href = redirectUrl;
                }, 1500);
            } else {
                // WordPress AJAX error response - message is in data.data.message
                const errorMessage = data.data?.message || data.message || 'Registration failed';
                const rawErrorCode = data.data?.code || data.code || '';
                const errorCode = String(rawErrorCode || '').toLowerCase();
                const existingEmail = data.data?.existing_email || data.data?.existingEmail || '';
                const inviteUrl = data.data?.invite_url || data.invite_url || '';

                // Existing account paths should guide user to login.
                if (errorCode === 'site_has_license' || errorCode === 'free_plan_exists' || errorCode === 'user_exists') {
                    this.showLoginForm();
                    const loginEmailInput = document.getElementById('login-email');
                    const loginPasswordInput = document.getElementById('login-password');

                    if (loginEmailInput) {
                        loginEmailInput.value = existingEmail || String(email || '');
                    }
                    if (loginPasswordInput) {
                        loginPasswordInput.focus();
                    }
                }

                if (errorCode === 'invite_required' && inviteUrl) {
                    this.showError(`${errorMessage} ${inviteUrl}`);
                } else {
                    this.showError(errorMessage);
                }
                // Clear portal flag on registration failure
                localStorage.removeItem('alttextai_open_portal_after_login');
            }
        } catch (error) {
            window.BBAI_LOG && window.BBAI_LOG.error('Registration error:', error);
            // Provide more specific error messages
            let errorMessage = 'Network error. Please try again.';
            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                errorMessage = 'Unable to connect to the server. Please check your internet connection and try again.';
            } else if (error.name === 'SyntaxError') {
                errorMessage = 'Server returned an invalid response. Please try again or contact support if the issue persists.';
            } else if (error.message && error.message.includes('timeout')) {
                errorMessage = 'Request timed out. The server may be busy. Please try again in a moment.';
            }
            this.showError(errorMessage);
            // Clear portal flag on network error
            localStorage.removeItem('alttextai_open_portal_after_login');
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
        if (!window.bbai_ajax?.ajaxurl) {
            window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] AJAX configuration not loaded');
            this.showError('Configuration error. Please refresh the page and try again.');
            this.setLoading(form, false);
            return;
        }

        try {
            const response = await fetch(window.bbai_ajax.ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'beepbeepai_forgot_password',
                    email: email,
                    nonce: window.bbai_ajax.nonce
                })
            });

            // Check if response is OK before parsing JSON
            if (!response.ok) {
                // Try to parse error response
                let errorData;
                try {
                    errorData = await response.json();
                } catch (e) {
                    errorData = { message: `Server error (${response.status})` };
                }
                this.setLoading(form, false);
                const errorMessage = errorData.data?.message || errorData.message || `Request failed with status ${response.status}`;
                this.showError(errorMessage);
                return;
            }

            const data = await response.json();

            if (data.success) {
                this.setLoading(form, false);
                
                // Build success message
                let successMessage = 'Reset link sent! ';
                
                // If reset link is provided in response (for testing/development), show it prominently
                if (data.data?.resetLink) {
                    successMessage = 'Password reset link generated! ';
                    successMessage += data.data.note || 'Email service is in development mode. ';
                    successMessage += '\n\nClick this link to reset your password:\n';
                    // Create a clickable link element
                    const resetLink = data.data.resetLink;
                    successMessage += resetLink;
                } else {
                    successMessage += 'Please check your email (including spam folder) for instructions. The link will expire in 1 hour.';
                }
                
                this.showSuccess(successMessage);
                // Clear form but keep modal open so user can see the message
                form.reset();
                // Don't auto-close modal - let user close it manually
            } else {
                this.setLoading(form, false);
                // Parse error message for better UX
                const rawMessage = data.data?.message || data.message || 'Failed to send reset link';
                let userMessage = rawMessage;
                
                // Provide actionable error messages
                if (rawMessage.toLowerCase().includes('not yet available') || rawMessage.toLowerCase().includes('being set up') || rawMessage.toLowerCase().includes('endpoint_not_found')) {
                    userMessage = 'Password reset is currently being set up. This feature is not yet available on our backend. Please contact support for assistance or try again later.';
                } else if (rawMessage.toLowerCase().includes('not found') && !rawMessage.toLowerCase().includes('account')) {
                    userMessage = 'Password reset endpoint is not available. Please contact support for assistance.';
                } else if (rawMessage.toLowerCase().includes('not found') || rawMessage.toLowerCase().includes('does not exist')) {
                    userMessage = 'No account found with this email address. Please check the spelling or sign up for a new account.';
                } else if (rawMessage.toLowerCase().includes('too many') || rawMessage.toLowerCase().includes('rate limit')) {
                    userMessage = 'Too many reset requests. Please wait 15 minutes before requesting another password reset.';
                } else if (rawMessage.toLowerCase().includes('temporarily unavailable') || rawMessage.toLowerCase().includes('unable to connect')) {
                    userMessage = 'The service is temporarily unavailable. Please try again in a few minutes.';
                } else if (rawMessage.toLowerCase().includes('not implemented') || rawMessage.toLowerCase().includes('404') || rawMessage.toLowerCase().includes('endpoint')) {
                    userMessage = 'Password reset is currently being set up. Please contact support or try again later.';
                }
                
                this.showError(userMessage);
            }
        } catch (error) {
            window.BBAI_LOG && window.BBAI_LOG.error('Forgot password error:', error);
            let errorMessage = 'Network error. Please try again.';
            
            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                errorMessage = 'Unable to connect to the server. Please check your internet connection and try again. If the problem persists, the service may be temporarily unavailable.';
            } else if (error.message && error.message.includes('timeout')) {
                errorMessage = 'Request timed out. Please check your internet connection and try again.';
            }
            
            this.showError(errorMessage);
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
        if (!window.bbai_ajax?.ajaxurl) {
            window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] AJAX configuration not loaded');
            this.showError('Configuration error. Please refresh the page and try again.');
            this.setLoading(form, false);
            return;
        }

        try {
            const response = await fetch(window.bbai_ajax.ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'beepbeepai_reset_password',
                    email: email,
                    token: token,
                    password: password,
                    nonce: window.bbai_ajax.nonce
                })
            });

            // Check if response is OK before parsing JSON
            if (!response.ok) {
                // Try to parse error response
                let errorData;
                try {
                    errorData = await response.json();
                } catch (e) {
                    errorData = { message: `Server error (${response.status})` };
                }
                this.setLoading(form, false);
                const errorMessage = errorData.data?.message || errorData.message || `Request failed with status ${response.status}`;
                this.showError(errorMessage);
                return;
            }

            const data = await response.json();

            if (data.success) {
                this.setLoading(form, false);
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
                this.setLoading(form, false);
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
                } else if (rawMessage.toLowerCase().includes('not implemented') || rawMessage.toLowerCase().includes('404') || rawMessage.toLowerCase().includes('endpoint')) {
                    userMessage = 'Password reset is currently being set up. Please contact support or try again later.';
                }
                
                this.showError(userMessage);
            }
        } catch (error) {
            window.BBAI_LOG && window.BBAI_LOG.error('Reset password error:', error);
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

        const usageSnapshot = typeof window.bbaiGetUsageSnapshot === 'function'
            ? window.bbaiGetUsageSnapshot(null)
            : null;

        const planElements = document.querySelectorAll('[data-user-plan]');
        const planText = (usageSnapshot && (usageSnapshot.plan_type || usageSnapshot.plan)) || user.plan || user.plan_type || user.planSlug || '';
        planElements.forEach(el => el.textContent = planText);

        const tokenElements = document.querySelectorAll('[data-user-tokens]');
        const remainingText = usageSnapshot && usageSnapshot.remaining != null
            ? usageSnapshot.remaining
            : (user.remaining ?? 0);
        tokenElements.forEach(el => el.textContent = remainingText);
    }

    storeToken(token) {
        localStorage.setItem('alttextai_token', token);
        // Also store in WordPress for server-side access
        const ajaxUrl = window.bbai_ajax?.ajax_url || window.bbai_ajax?.ajaxurl;
        if (!ajaxUrl) {
            return;
        }
        fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                action: 'bbai_store_token',
                token: token,
                nonce: window.bbai_ajax?.nonce || ''
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
function initAuthModal() {
    var existingAuthModal = window.authModal || window.AltTextAuthModal || (typeof bbaiApp !== 'undefined' && bbaiApp.authModal ? bbaiApp.authModal : null);

    if (existingAuthModal && typeof existingAuthModal.show === 'function') {
        window.AltTextAuthModal = existingAuthModal;
        if (typeof bbaiApp !== 'undefined') {
            bbaiApp.authModal = existingAuthModal;
        }
        window.authModal = existingAuthModal;
        return;
    }

    // Create instance and add to bbaiApp namespace
    var authModalInstance = new BbAIAuthModal();
    window.AltTextAuthModal = authModalInstance; // Legacy support
    if (typeof bbaiApp !== 'undefined') {
        bbaiApp.authModal = authModalInstance;
    }
    window.authModal = authModalInstance; // Alias for compatibility
}

// Initialize immediately if DOM is already loaded, otherwise wait for DOMContentLoaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAuthModal);
} else {
    // DOM is already loaded, initialize immediately
    initAuthModal();
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = BbAIAuthModal;
}
