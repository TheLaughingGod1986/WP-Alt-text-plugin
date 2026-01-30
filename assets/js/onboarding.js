/* global bbaiOnboarding, jQuery */

(function ($) {
    'use strict';

    function setStatus(el, message, type) {
        if (!el) {
            return;
        }
        el.textContent = message || '';
        el.classList.remove('is-success', 'is-error', 'is-info');
        if (type) {
            el.classList.add('is-' + type);
        }
    }

    function setLoading(button, isLoading, label) {
        if (!button) {
            return;
        }

        if (isLoading) {
            if (!button.dataset.originalText) {
                button.dataset.originalText = button.textContent;
            }
            button.textContent = label || button.textContent;
            button.disabled = true;
        } else {
            button.disabled = false;
            // Restore explicitly to avoid stuck labels/spinners/odd states
            button.textContent = label || button.dataset.originalText || button.textContent;
        }
    }

    function postAction(action, data) {
        var payload = $.extend(
            {
                action: action,
                nonce: bbaiOnboarding.nonce
            },
            data || {}
        );

        return $.post(bbaiOnboarding.ajaxUrl, payload);
    }

    function initOnboarding() {
        if (typeof bbaiOnboarding === 'undefined') {
            return;
        }

        var strings = bbaiOnboarding.strings || {};
        var root = document.querySelector('.bbai-onboarding');
        if (!root) {
            return;
        }

        var scanButton = root.querySelector('[data-bbai-onboarding-action="start-scan"]');
        var skipButton = root.querySelector('[data-bbai-onboarding-action="skip"]');
        var statusEl = root.querySelector('.bbai-onboarding-status');

        if (scanButton) {
            // Capture original label for reliable restoration
            var scanButtonOriginalLabel = (scanButton.textContent || '').trim();

            scanButton.addEventListener('click', function () {
                // Immediately disable and update label
                setLoading(scanButton, true, 'Starting scan...');
                setStatus(statusEl, '', '');

                postAction('bbai_start_scan')
                    .done(function (response) {
                        if (response && response.success) {
                            var data = response.data || {};

                            // Be defensive about the queued key name
                            var queued = parseInt((data.queued != null ? data.queued : data.queued_count), 10) || 0;

                            // Harden redirect URL lookup
                            var redirectUrl =
                                data.redirect ||
                                data.redirect_url ||
                                data.redirectUrl ||
                                bbaiOnboarding.dashboardUrl;

                            // Build and show success message
                            if (queued > 0) {
                                var successMsg =
                                    'Scan started. We\'ve queued ' +
                                    queued +
                                    ' image' +
                                    (queued === 1 ? '' : 's') +
                                    ' for alt text generation. You can safely leave this page while we process your library in the background.';
                                setStatus(statusEl, successMsg, 'success');
                            } else {
                                setStatus(statusEl, 'No images without alt text were found. You\'re all set.', 'info');
                            }

                            // Conditional redirect based on authentication
                            if (bbaiOnboarding.isAuthenticated) {
                                // Authenticated: delay redirect to show the message
                                setTimeout(function () {
                                    window.location = redirectUrl;
                                }, 1500);
                            } else {
                                // Not authenticated: re-enable button, show sign-in CTA
                                setLoading(scanButton, false, scanButtonOriginalLabel);

                                // Remove any existing sign-in CTA
                                var existingCta = root.querySelector('.bbai-onboarding-signin-cta');
                                if (existingCta) {
                                    existingCta.remove();
                                }

                                // Create sign-in CTA container
                                var ctaContainer = document.createElement('div');
                                ctaContainer.className = 'bbai-onboarding-signin-cta';
                                ctaContainer.style.cssText = 'margin-top: 16px; text-align: center;';

                                var ctaText = document.createElement('p');
                                ctaText.style.cssText = 'margin: 0 0 12px; color: #4b5563; font-size: 14px;';
                                ctaText.textContent = 'Sign in to start generating alt text.';

                                var ctaButton = document.createElement('button');
                                ctaButton.type = 'button';
                                ctaButton.className = 'button button-primary';
                                ctaButton.textContent = 'Sign in';
                                ctaButton.setAttribute('data-action', 'show-auth-modal');
                                ctaButton.setAttribute('data-auth-tab', 'login');

                                ctaContainer.appendChild(ctaText);
                                ctaContainer.appendChild(ctaButton);

                                // Insert after status element
                                if (statusEl && statusEl.parentNode) {
                                    statusEl.parentNode.insertBefore(ctaContainer, statusEl.nextSibling);
                                }

                                // Trigger auth modal click handler (uses existing modal system)
                                ctaButton.addEventListener('click', function () {
                                    var modalTrigger = document.querySelector('[data-action="show-auth-modal"]');
                                    if (modalTrigger && modalTrigger !== ctaButton) {
                                        modalTrigger.click();
                                    } else {
                                        // Fallback: dispatch custom event for modal system
                                        document.dispatchEvent(new CustomEvent('bbai:show-auth-modal', {
                                            detail: { tab: 'login' }
                                        }));
                                    }
                                });
                            }

                            return;
                        }

                        // Server error response - restore via helper to avoid inconsistent state
                        setLoading(scanButton, false, scanButtonOriginalLabel);
                        setStatus(statusEl, 'Couldn\'t start the scan. Please try again.', 'error');
                    })
                    .fail(function () {
                        // Network or request error - restore via helper
                        setLoading(scanButton, false, scanButtonOriginalLabel);
                        setStatus(statusEl, 'Couldn\'t start the scan. Please try again.', 'error');
                    });
            });
        }

        if (skipButton) {
            skipButton.addEventListener('click', function () {
                setLoading(skipButton, true, strings.skipLabel || 'Skipping...');

                postAction('bbai_onboarding_skip')
                    .done(function (response) {
                        if (response && response.success) {
                            var redirectUrl =
                                response.data && response.data.redirect
                                    ? response.data.redirect
                                    : bbaiOnboarding.dashboardUrl;
                            window.location = redirectUrl;
                            return;
                        }
                        setStatus(
                            statusEl,
                            response && response.data && response.data.message
                                ? response.data.message
                                : (strings.skipFailed || 'Unable to skip onboarding.'),
                            'error'
                        );
                    })
                    .fail(function () {
                        setStatus(statusEl, strings.skipFailed || 'Unable to skip onboarding. Please try again.', 'error');
                    })
                    .always(function () {
                        setLoading(skipButton, false);
                    });
            });
        }
    }

    /**
     * Initialize Step 3 - Fetch and display queue stats
     * Scoped to .bbai-onboarding-step3 root element
     *
     * When queue finishes (pending + processing === 0), marks onboarding as completed.
     */
    function initOnboardingStep3() {
        if (typeof bbaiOnboarding === 'undefined') {
            return;
        }

        var root = document.querySelector('.bbai-onboarding-step3');
        if (!root) {
            return;
        }

        var statsContainer = root.querySelector('[data-bbai-step3-stats]');
        if (!statsContainer || !bbaiOnboarding.isAuthenticated) {
            return;
        }

        var queuedEl = statsContainer.querySelector('[data-stat="queued"]');
        var processedEl = statsContainer.querySelector('[data-stat="processed"]');
        var errorsEl = statsContainer.querySelector('[data-stat="errors"]');
        var errorsWrapper = statsContainer.querySelector('[data-stat-errors-wrapper]');

        function updateStat(el, value) {
            if (!el) return;
            el.textContent = typeof value === 'number' ? value.toString() : '0';
        }

        function showError() {
            if (queuedEl) queuedEl.textContent = '-';
            if (processedEl) processedEl.textContent = '-';
            if (errorsWrapper) errorsWrapper.style.display = 'none';
        }

        /**
         * Mark onboarding as completed via AJAX.
         * Uses multiple guards to ensure idempotent behavior:
         * - bbaiOnboarding.isOnboardingCompleted (server-side state at page load)
         * - window.__bbaiOnboardingCompletedSent (in-memory flag for this session)
         * - root data attribute (DOM-level persistence for SPA-like refreshes)
         */
        function maybeMarkOnboardingCompleted() {
            // Guard 1: Already completed on server at page load
            if (bbaiOnboarding.isOnboardingCompleted) {
                return;
            }

            // Guard 2: Already sent completion request this session
            if (window.__bbaiOnboardingCompletedSent) {
                return;
            }

            // Guard 3: Already marked in DOM (handles edge cases)
            if (root.dataset.onboardingCompletionSent === 'true') {
                return;
            }

            // Set flags immediately to prevent race conditions
            window.__bbaiOnboardingCompletedSent = true;
            root.dataset.onboardingCompletionSent = 'true';

            // Fire-and-forget completion call (no UI changes needed)
            $.post(bbaiOnboarding.ajaxUrl, {
                action: 'bbai_complete_onboarding',
                nonce: bbaiOnboarding.queueStatsNonce // Same nonce as queue stats (beepbeepai_nonce)
            });
        }

        // Fetch queue stats via AJAX
        $.post(bbaiOnboarding.ajaxUrl, {
            action: 'beepbeepai_queue_stats',
            nonce: bbaiOnboarding.queueStatsNonce
        })
        .done(function (response) {
            if (response && response.success && response.data) {
                var data = response.data;
                var stats = data.stats || {};

                // "In queue" = all work not yet completed (pending + processing)
                var pending = parseInt(stats.pending, 10) || 0;
                var processing = parseInt(stats.processing, 10) || 0;
                var queued = pending + processing;

                // "Processed" = prefer completed_recent, fallback to completed
                var processed = (typeof stats.completed_recent !== 'undefined')
                    ? (parseInt(stats.completed_recent, 10) || 0)
                    : (parseInt(stats.completed, 10) || 0);

                // "Errors" = failed count
                var errors = parseInt(stats.failed, 10) || 0;

                updateStat(queuedEl, queued);
                updateStat(processedEl, processed);

                // Only show errors row if there are errors
                if (errors > 0 && errorsWrapper) {
                    errorsWrapper.style.display = '';
                    updateStat(errorsEl, errors);
                } else if (errorsWrapper) {
                    errorsWrapper.style.display = 'none';
                }

                // Mark onboarding completed when queue is empty
                if (queued === 0) {
                    maybeMarkOnboardingCompleted();
                }
            } else {
                showError();
            }
        })
        .fail(function () {
            showError();
        });
    }

    $(initOnboarding);
    $(initOnboardingStep3);
})(jQuery);