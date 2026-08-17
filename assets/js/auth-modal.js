/**
 * Authentication Modal for AltText AI
 * Handles user registration and login
 */

class BbAIAuthModal {
    constructor() {
        this.apiUrl = this.getApiUrl();
        this.token = this.getStoredToken();
        this.modalContext = 'default';
        this.modalMetrics = {};
        this.postSignupGuideModal = null;
        this.isAuthRedirecting = false;
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
        this.installPostSignupGuideTrigger();
        this.maybeShowPostSignupGuide();
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

    setModalContext(context, metrics) {
        this.modalContext = String(context || 'default');
        this.modalMetrics = metrics && typeof metrics === 'object'
            ? metrics
            : (window.bbaiTrialCompletionImpact && typeof window.bbaiTrialCompletionImpact === 'object'
                ? window.bbaiTrialCompletionImpact
                : {});
        this.renderModalContext();
    }

    renderModalContext() {
        if (!this.modalElement) {
            return;
        }

        const title = this.modalElement.querySelector('.alttext-auth-modal__title');
        const subtitle = this.modalElement.querySelector('.alttext-auth-modal__subtitle');
        const impact = this.modalElement.querySelector('[data-bbai-trial-impact]');
        const impactImages = this.modalElement.querySelector('[data-bbai-trial-impact-images]');
        const impactCoverage = this.modalElement.querySelector('[data-bbai-trial-impact-coverage]');
        const impactLift = this.modalElement.querySelector('[data-bbai-trial-impact-lift]');
        const registerButton = this.modalElement.querySelector('#register-form .alttext-btn__text');
        const footer = this.modalElement.querySelector('.alttext-auth-modal__upsell');
        const exhausted = this.modalContext === 'register_exhausted';
        const metrics = this.modalMetrics || {};
        const imagesImproved = Math.max(0, parseInt(metrics.imagesImproved, 10) || 0);
        const trialLimit = Math.max(1, parseInt(metrics.trialLimit, 10) || 5);
        const coverageBefore = Math.max(0, Math.min(100, parseInt(metrics.coverageBefore, 10) || 0));
        const coverageAfter = Math.max(0, Math.min(100, parseInt(metrics.coverageAfter, 10) || 0));
        const coverageLift = Math.max(0, Math.min(100, parseInt(metrics.coverageLift, 10) || 0));

        this.modalElement.setAttribute('data-bbai-modal-context', this.modalContext);

        if (exhausted) {
            if (title) title.textContent = 'Your ' + trialLimit + ' free ALT texts are complete 🎉';
            if (subtitle) {
                subtitle.textContent = coverageLift > 0
                    ? 'You improved ALT text coverage from ' + coverageBefore + '% to ' + coverageAfter + '% (+' + coverageLift + ' points), helping accessibility and image SEO.'
                    : 'You have already improved your website’s accessibility and image SEO. Keep the momentum going for free.';
            }
            if (impact) impact.hidden = false;
            if (impactImages) impactImages.textContent = imagesImproved > 0 ? String(imagesImproved) : '5';
            if (impactCoverage) impactCoverage.textContent = coverageAfter > 0 ? coverageAfter + '%' : 'Improved';
            if (impactLift) {
                impactLift.textContent = coverageLift > 0
                    ? '+' + coverageLift + ' percentage points'
                    : 'More accessible';
            }
            if (registerButton) registerButton.textContent = 'Create Free Account — Get 15 Monthly';
            if (footer) footer.textContent = '15 free AI generations every month for life. No credit card needed.';
            return;
        }

        if (impact) impact.hidden = true;
        if (registerButton) registerButton.textContent = 'Create Account';
        if (footer) footer.textContent = 'Growth users get 1,000 AI alt texts per month + bulk processing + priority queue.';

        if (this.modalContext === 'login') {
            if (title) title.textContent = 'Welcome back';
            if (subtitle) subtitle.textContent = 'Sign in to sync your subscription, usage quota, and account preferences.';
        } else {
            if (title) title.textContent = 'Create your free BeepBeep AI account';
            if (subtitle) subtitle.textContent = 'Get 15 free AI generations every month. No credit card needed.';
        }
    }

    getPostAuthRedirectUrl() {
        try {
            const currentUrl = new URL(window.location.href);
            const currentPage = String(currentUrl.searchParams.get('page') || '').toLowerCase();

            if (currentPage && currentPage.indexOf('bbai') === 0) {
                currentUrl.searchParams.delete('bbai_open_auth');
                currentUrl.searchParams.delete('bbai_auth_tab');
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

    getAdminPageUrl(page) {
        const adminUrl = window.bbai_ajax?.admin_url || 'admin.php';
        const base = String(adminUrl).indexOf('admin.php') !== -1 ? String(adminUrl) : `${adminUrl}admin.php`;
        const separator = base.indexOf('?') === -1 ? '?' : '&';
        return `${base}${separator}page=${encodeURIComponent(page || 'bbai')}`;
    }

    queuePostSignupOnboarding() {
        try {
            sessionStorage.setItem('bbai_show_post_signup_onboarding', '1');
        } catch (error) {
            // Ignore storage failures; registration itself should not be blocked.
        }
    }

    redirectToDashboardAfterAuth(redirectUrl, form, message) {
        this.isAuthRedirecting = true;
        if (this.modalElement) {
            this.modalElement.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        if (form) {
            this.setLoading(form, true, 'Loading dashboard...');
        }
        this.showSuccess(message || 'Loading your dashboard...');

        const targetUrl = redirectUrl || this.getPostAuthRedirectUrl();
        window.setTimeout(() => {
            window.location.href = targetUrl;
        }, 50);
    }

    shouldShowPostSignupGuide() {
        const hasSignedInSurface = !!document.querySelector('[data-bbai-logged-in-dashboard], [data-bbai-has-connected-account="1"]');
        const isAuthenticated = !!(window.bbai_ajax && window.bbai_ajax.is_authenticated === true);

        try {
            return sessionStorage.getItem('bbai_show_post_signup_onboarding') === '1' && (isAuthenticated || hasSignedInSurface);
        } catch (error) {
            return false;
        }
    }

    installPostSignupGuideTrigger() {
        const install = () => {
            if (document.querySelector('[data-bbai-show-post-signup-guide]')) {
                return;
            }

            const hasSignedInSurface = !!document.querySelector('[data-bbai-logged-in-dashboard], [data-bbai-has-connected-account="1"]');
            if (!hasSignedInSurface && !(window.bbai_ajax && window.bbai_ajax.is_authenticated === true)) {
                return;
            }

            const headerRight = document.querySelector('.bbai-dashboard-header__right');
            if (!headerRight) {
                return;
            }

            const logout = headerRight.querySelector('[data-action="logout"]');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'bbai-dashboard-header__quickstart bbai-dashboard-header__logout';
            button.setAttribute('data-bbai-show-post-signup-guide', '1');
            button.textContent = 'Quick start';

            if (logout && logout.parentNode === headerRight) {
                headerRight.insertBefore(button, logout);
            } else {
                headerRight.appendChild(button);
            }
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', install, { once: true });
        } else {
            install();
        }
    }

    maybeShowPostSignupGuide() {
        const showIfQueued = () => {
            if (!this.shouldShowPostSignupGuide()) {
                return;
            }

            try {
                sessionStorage.removeItem('bbai_show_post_signup_onboarding');
            } catch (error) {
                // Ignore storage failures.
            }

            window.setTimeout(() => {
                this.showPostSignupGuide({ source: 'post_signup' });
            }, 250);
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', showIfQueued, { once: true });
        } else {
            showIfQueued();
        }
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
                            <h2 class="alttext-auth-modal__title" id="alttext-auth-modal-title">BeepBeep AI Account</h2>
                            <p class="alttext-auth-modal__subtitle" id="alttext-auth-modal-desc">Sign in to sync your subscription, usage quota, and account preferences.</p>
                        </div>
                        
                        <div class="alttext-auth-modal__body">
                            <section class="alttext-auth-modal__impact" data-bbai-trial-impact hidden>
                                <div class="alttext-auth-modal__impact-grid">
                                    <div><strong data-bbai-trial-impact-images>5</strong><span>images improved</span></div>
                                    <div><strong data-bbai-trial-impact-coverage>Improved</strong><span>ALT text coverage</span></div>
                                    <div><strong data-bbai-trial-impact-lift>More accessible</strong><span>accessibility progress</span></div>
                                </div>
                                <p>Your new ALT text helps screen readers understand your images and gives search engines more useful image context.</p>
                                <div class="alttext-auth-modal__offer">
                                    <strong>Keep going free</strong>
                                    <span>15 free generations every month for life · No credit card</span>
                                </div>
                            </section>

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
                                        <span class="alttext-btn__text">Create Account</span>
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
                                <p class="alttext-forgot-password-info" id="alttext-auth-modal-desc">Enter your email address and we'll send you a link to reset your password.</p>
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
            let authTrigger = target.closest('[data-action="show-auth-modal"]');
            
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
                const modalContext = authTrigger.getAttribute('data-bbai-modal-context') ||
                    (requestedTab === 'register' ? 'register' : 'login');

                self.setModalContext(modalContext);

                if (requestedTab === 'register') {
                    self.emitAnalyticsEvent('signup_cta_clicked', {
                        source: source,
                        modal_context: modalContext,
                        conversion_stage: modalContext === 'register_exhausted' ? 'guest_trial_complete' : 'signup_interest'
                    });
                    if (modalContext === 'register_exhausted') {
                        const trialImpact = window.bbaiTrialCompletionImpact || {};
                        self.emitAnalyticsEvent('trial_complete_cta_clicked', {
                            cta: 'create_account',
                            source: source,
                            modal_context: modalContext,
                            conversion_stage: 'guest_trial_complete',
                            auth_state: 'anonymous',
                            account_state: 'anonymous_trial',
                            is_signed_in: false,
                            images_improved: trialImpact.imagesImproved || 0,
                            coverage_after: trialImpact.coverageAfter || 0,
                            coverage_lift: trialImpact.coverageLift || 0,
                            trial_used: trialImpact.trialUsed || 0,
                            trial_limit: trialImpact.trialLimit || 0
                        });
                    }
                } else {
                    self.emitAnalyticsEvent('login_cta_clicked', { source: source });
                    self.emitAnalyticsEvent('login_modal_opened', { source: source });
                }

                self.show({
                    tab: requestedTab,
                    context: modalContext
                });
                return;
            }

            if (e.target.closest('[data-bbai-show-post-signup-guide]')) {
                e.preventDefault();
                e.stopPropagation();
                self.showPostSignupGuide({ source: 'manual' });
                return;
            }

            if (
                e.target.closest('[data-bbai-post-signup-close]') ||
                (
                    e.target.classList &&
                    e.target.classList.contains('alttext-auth-modal__overlay') &&
                    e.target.closest('#bbai-post-signup-guide-modal')
                )
            ) {
                e.preventDefault();
                e.stopPropagation();
                self.hidePostSignupGuide();
                return;
            }

            const postSignupGenerate = e.target.closest('[data-bbai-post-signup-generate]');
            if (postSignupGenerate) {
                e.preventDefault();
                e.stopPropagation();

                if (postSignupGenerate.getAttribute('data-bbai-post-signup-upgrade') === '1') {
                    self.hidePostSignupGuide();
                    self.openUpgradeFromPostSignupGuide(postSignupGenerate);
                    return;
                }

                self.hidePostSignupGuide();
                self.startFirstGenerationFromGuide();
                return;
            }

            if (e.target.closest('[data-bbai-post-signup-library]')) {
                e.preventDefault();
                e.stopPropagation();
                window.location.href = self.getAdminPageUrl('bbai-library');
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
            if (e.target.id === 'show-register' || e.target.closest('#show-register')) {
                e.preventDefault();
                e.stopPropagation();
                self.emitAnalyticsEvent('signup_cta_clicked', { source: 'modal' });
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
            if (e.key === 'Escape' && self.modalElement && self.modalElement.style.display === 'block') {
                self.hide();
            }
            
            // Focus trapping: keep focus within modal when open
            if (self.modalElement && self.modalElement.style.display === 'block') {
                const focusableElements = self.modalElement.querySelectorAll(
                    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
                );
                const firstElement = focusableElements[0];
                const lastElement = focusableElements[focusableElements.length - 1];
                
                if (e.key === 'Tab') {
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

    getRequestedTab(options) {
        if (typeof options === 'string') {
            return options === 'register' ? 'register' : 'login';
        }
        if (!options || typeof options !== 'object') {
            return '';
        }

        const tab = String(options.tab || options.authTab || '').toLowerCase();
        const context = String(options.context || '').toLowerCase();
        if (tab === 'register' || tab === 'signup' || context === 'register' || context === 'signup') {
            return 'register';
        }
        if (tab === 'login' || tab === 'sign_in' || context === 'login' || context === 'sign_in') {
            return 'login';
        }
        return '';
    }

    show(options) {
        const requestedTab = this.getRequestedTab(options);

        if (requestedTab === 'register') {
            this.showRegisterForm();
        } else if (requestedTab === 'login') {
            this.showLoginForm();
        }

        if (this.modalElement) {
            this.modalElement.style.display = 'block';
            document.body.style.overflow = 'hidden';
            this.enablePasswordFields();
            
            // Focus trap: focus on first input or close button
            const activeForm = this.modalElement.querySelector('.alttext-auth-form[style*="block"], .alttext-auth-form:not([style*="display: none"])');
            const firstInput = (activeForm || this.modalElement).querySelector('input[type="email"], input[type="password"], button');
            if (firstInput) {
                firstInput.focus();
            }
        }
    }

    hide() {
        if (this.modalElement) {
            this.modalElement.style.display = 'none';
            document.body.style.overflow = '';
            this.resetPasswordFields();
        }
    }

    showLoginForm() {
        this.modalContext = 'login';
        this.renderModalContext();
        // Use cached form elements
        if (this.formElements.login) this.formElements.login.style.display = 'block';
        if (this.formElements.register) this.formElements.register.style.display = 'none';
        if (this.formElements.forgotPassword) this.formElements.forgotPassword.style.display = 'none';
        if (this.formElements.resetPassword) this.formElements.resetPassword.style.display = 'none';
    }

    showRegisterForm() {
        if (this.modalContext === 'login' || this.modalContext === 'default') {
            this.modalContext = 'register';
        }
        this.renderModalContext();
        // Use cached form elements
        if (this.formElements.login) this.formElements.login.style.display = 'none';
        if (this.formElements.register) this.formElements.register.style.display = 'block';
        if (this.formElements.forgotPassword) this.formElements.forgotPassword.style.display = 'none';
        if (this.formElements.resetPassword) this.formElements.resetPassword.style.display = 'none';
    }

    showForgotPasswordForm() {
        // Use cached form elements
        if (this.formElements.login) this.formElements.login.style.display = 'none';
        if (this.formElements.register) this.formElements.register.style.display = 'none';
        if (this.formElements.forgotPassword) this.formElements.forgotPassword.style.display = 'block';
        if (this.formElements.resetPassword) this.formElements.resetPassword.style.display = 'none';
    }

    showResetPasswordForm(email, token) {
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

        this.isAuthRedirecting = false;
        this.setLoading(form, true, 'Signing in...');
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
                if (window.BBAIEntitlements && typeof window.BBAIEntitlements.consume === 'function') {
                    window.BBAIEntitlements.consume(data, 'login');
                }
                this.emitAnalyticsEvent('login_succeeded', {
                    source: source,
                    user_state: 'signed_in',
                    is_logged_in: true,
                    is_signed_in: true,
                    is_saas_authenticated: true,
                    auth_state: 'authenticated',
                    account_state: 'connected_account',
                    plan: userData.plan || userData.plan_type || userData.planSlug || 'free',
                    plan_type: userData.plan_type || userData.plan || userData.planSlug || 'free'
                });
                if (window.bbaiTelemetry && typeof window.bbaiTelemetry.flush === 'function') {
                    window.bbaiTelemetry.flush();
                }
                this.onAuthSuccess(userData);

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
                this.redirectToDashboardAfterAuth(redirectUrl, form, 'Welcome back. Loading your dashboard...');
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
            if (!this.isAuthRedirecting) {
                this.setLoading(form, false);
            }
        }
    }

    async handleRegister() {
        const form = document.getElementById('register-form');
        const formData = new FormData(form);
        const email = formData.get('email');
        const password = formData.get('password');
        const confirmPassword = formData.get('confirmPassword');
        const source = this.resolveSource(this.modalElement, 'modal');

        this.isAuthRedirecting = false;
        if (password !== confirmPassword) {
            this.showError('Passwords do not match');
            return;
        }

        this.setLoading(form, true, 'Creating account...');
        this.emitAnalyticsEvent('signup_started', {
            source: source,
            modal_context: this.modalContext,
            conversion_stage: this.modalContext === 'register_exhausted' ? 'guest_trial_complete' : 'signup_form',
            auth_state: 'anonymous',
            account_state: 'anonymous_trial',
            is_signed_in: false,
            is_saas_authenticated: false,
            images_improved: this.modalMetrics.imagesImproved || 0,
            coverage_after: this.modalMetrics.coverageAfter || 0,
            coverage_lift: this.modalMetrics.coverageLift || 0,
            trial_used: this.modalMetrics.trialUsed || 0,
            trial_limit: this.modalMetrics.trialLimit || 0
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
                if (window.BBAIEntitlements && typeof window.BBAIEntitlements.consume === 'function') {
                    window.BBAIEntitlements.consume(data, 'register');
                }
                this.emitAnalyticsEvent('signup_succeeded', {
                    source: source,
                    modal_context: this.modalContext,
                    conversion_stage: this.modalContext === 'register_exhausted' ? 'guest_trial_converted' : 'signup_complete',
                    user_state: 'signed_in',
                    is_logged_in: true,
                    is_signed_in: true,
                    is_saas_authenticated: true,
                    auth_state: 'authenticated',
                    account_state: 'connected_account',
                    guest_trial_converted: this.modalContext === 'register_exhausted',
                    images_improved: this.modalMetrics.imagesImproved || 0,
                    coverage_after: this.modalMetrics.coverageAfter || 0,
                    coverage_lift: this.modalMetrics.coverageLift || 0,
                    trial_used: this.modalMetrics.trialUsed || 0,
                    trial_limit: this.modalMetrics.trialLimit || 0,
                    plan: userData.plan || userData.plan_type || userData.planSlug || 'free',
                    plan_type: userData.plan_type || userData.plan || userData.planSlug || 'free'
                });
                if (window.bbaiTelemetry && typeof window.bbaiTelemetry.flush === 'function') {
                    window.bbaiTelemetry.flush();
                }
                this.onAuthSuccess(userData);
                this.queuePostSignupOnboarding();

                const redirectUrl = this.getPostAuthRedirectUrl();
                this.redirectToDashboardAfterAuth(redirectUrl, form, 'Account created. Loading your dashboard...');
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
            if (!this.isAuthRedirecting) {
                this.setLoading(form, false);
            }
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

    setLoading(form, loading, busyText) {
        const button = form.querySelector('button[type="submit"]');
        const text = button.querySelector('.alttext-btn__text');
        const spinner = button.querySelector('.alttext-btn__spinner');

        if (loading) {
            if (text && !text.getAttribute('data-bbai-original-text')) {
                text.setAttribute('data-bbai-original-text', text.textContent || '');
            }
            if (text) {
                text.textContent = busyText || 'Working...';
                text.style.display = 'inline';
            }
            if (spinner) {
                spinner.style.display = 'inline';
            }
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        } else {
            if (text) {
                text.textContent = text.getAttribute('data-bbai-original-text') || text.textContent || '';
                text.removeAttribute('data-bbai-original-text');
                text.style.display = 'inline';
            }
            if (spinner) {
                spinner.style.display = 'none';
            }
            button.disabled = false;
            button.removeAttribute('aria-busy');
        }
    }

    createPostSignupGuideModal() {
        if (this.postSignupGuideModal) {
            return this.postSignupGuideModal;
        }

        let modal = document.getElementById('bbai-post-signup-guide-modal');
        if (modal) {
            this.postSignupGuideModal = modal;
            return modal;
        }

        const modalHTML = `
            <div id="bbai-post-signup-guide-modal" class="alttext-auth-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="bbai-post-signup-guide-title" aria-describedby="bbai-post-signup-guide-desc">
                <div class="alttext-auth-modal__overlay">
                    <div class="alttext-auth-modal__content">
                        <button class="alttext-auth-modal__close" type="button" aria-label="Close quick start" data-bbai-post-signup-close="1">&times;</button>
                        <div class="alttext-auth-modal__header">
                            <h2 class="alttext-auth-modal__title" id="bbai-post-signup-guide-title">Quick start</h2>
                            <p class="alttext-auth-modal__subtitle" id="bbai-post-signup-guide-desc">Your free account includes 15 AI ALT text generations each month.</p>
                        </div>
                        <div class="alttext-auth-modal__body">
                            <section class="alttext-auth-modal__impact">
                                <p>Start by generating ALT text for images that are missing it, then review the results in your ALT Library.</p>
                                <div class="alttext-auth-modal__offer">
                                    <strong>Best first step</strong>
                                    <span>Generate missing ALT text, then approve or edit the suggestions in the library.</span>
                                </div>
                            </section>
                            <div class="alttext-auth-modal__intro-actions">
                                <button type="button" class="alttext-btn alttext-btn--primary alttext-auth-modal__intro-cta" data-bbai-post-signup-generate="1">Generate missing ALT text</button>
                                <button type="button" class="alttext-auth-modal__secondary-cta" data-bbai-post-signup-library="1">Open ALT Library</button>
                                <button type="button" class="alttext-auth-modal__secondary-cta" data-bbai-post-signup-close="1">I’ll do this later</button>
                            </div>
                        </div>
                        <div class="alttext-auth-modal__footer">
                            <p class="alttext-auth-modal__upsell">Use the Quick start button on this page anytime to see this again.</p>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.postSignupGuideModal = document.getElementById('bbai-post-signup-guide-modal');
        return this.postSignupGuideModal;
    }

    showPostSignupGuide(options) {
        const modal = this.createPostSignupGuideModal();
        if (!modal) {
            return;
        }

        this.syncPostSignupGuideGenerationState(modal);

        modal.style.display = 'block';
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        this.emitAnalyticsEvent('post_signup_onboarding_viewed', {
            source: options && options.source ? options.source : 'manual'
        });

        const primary = modal.querySelector('[data-bbai-post-signup-generate]');
        const focusTarget = primary && !primary.disabled
            ? primary
            : modal.querySelector('[data-bbai-post-signup-library]');
        if (focusTarget && typeof focusTarget.focus === 'function') {
            focusTarget.focus();
        }
    }

    syncPostSignupGuideGenerationState(modal) {
        const primary = modal.querySelector('[data-bbai-post-signup-generate]');
        const dashboardData = typeof window.bbaiGetDashboardData === 'function'
            ? window.bbaiGetDashboardData()
            : null;
        const usageSnapshot = typeof window.bbaiGetUsageSnapshot === 'function'
            ? window.bbaiGetUsageSnapshot(null)
            : null;
        const remainingValue = dashboardData && dashboardData.creditsRemaining != null
            ? dashboardData.creditsRemaining
            : (usageSnapshot && usageSnapshot.remaining != null ? usageSnapshot.remaining : null);
        const remaining = Number.parseInt(remainingValue, 10);
        const exhausted = Number.isFinite(remaining) && remaining <= 0;

        if (!primary) {
            return;
        }

        primary.disabled = false;
        primary.classList.toggle('is-disabled', false);
        primary.classList.toggle('alttext-auth-modal__intro-cta--upgrade', exhausted);
        primary.setAttribute('aria-disabled', 'false');
        primary.setAttribute('data-bbai-post-signup-upgrade', exhausted ? '1' : '0');
        primary.textContent = exhausted
            ? 'Upgrade to continue generating'
            : 'Generate missing ALT text';

        if (exhausted) {
            primary.setAttribute('title', 'Open upgrade plans');
        } else {
            primary.removeAttribute('title');
        }
    }

    hidePostSignupGuide() {
        const modal = this.postSignupGuideModal || document.getElementById('bbai-post-signup-guide-modal');
        if (!modal) {
            return;
        }

        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    startFirstGenerationFromGuide() {
        const trigger = document.querySelector('[data-action="generate-missing"]:not([aria-disabled="true"]), [data-bbai-action="generate_missing"]:not([aria-disabled="true"])');
        if (trigger && typeof trigger.click === 'function') {
            trigger.click();
            return;
        }

        window.location.href = this.getAdminPageUrl('bbai');
    }

    openUpgradeFromPostSignupGuide(trigger) {
        const context = {
            source: 'post-signup-guide',
            trigger: trigger,
            triggerKey: 'generate_missing'
        };

        if (typeof window.bbaiOpenUpgradeModal === 'function') {
            try {
                if (window.bbaiOpenUpgradeModal('generate_missing', context) !== false) {
                    return;
                }
            } catch (error) {
                window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Quick start upgrade modal failed', error);
            }
        }

        if (typeof window.alttextaiShowModal === 'function' && window.alttextaiShowModal() !== false) {
            return;
        }

        const upgradeTrigger = document.querySelector('[data-action="show-upgrade-modal"]');
        if (upgradeTrigger && upgradeTrigger !== trigger && typeof upgradeTrigger.click === 'function') {
            upgradeTrigger.click();
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
