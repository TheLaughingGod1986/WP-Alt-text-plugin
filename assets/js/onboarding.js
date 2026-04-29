/* global bbaiOnboarding, jQuery */

(function ($) {
    'use strict';

    var i18n = window.wp && window.wp.i18n ? window.wp.i18n : null;
    var __ = i18n && typeof i18n.__ === 'function' ? i18n.__ : function (text) { return text; };
    var _n = i18n && typeof i18n._n === 'function' ? i18n._n : function (single, plural, number) { return number === 1 ? single : plural; };
    var sprintf = i18n && typeof i18n.sprintf === 'function' ? i18n.sprintf : function (format) { return format; };

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

    function emitAnalyticsEvent(eventName, properties) {
        try {
            document.dispatchEvent(new CustomEvent('bbai:analytics', {
                detail: $.extend({ event: eventName }, properties || {})
            }));
        } catch (error) {
            // Ignore analytics dispatch failures.
        }
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

        var step = parseInt(
            root.getAttribute('data-bbai-onboarding-step') ||
                (bbaiOnboarding.currentStep ? String(bbaiOnboarding.currentStep) : '1'),
            10
        );
        if (!step || step < 1) {
            step = 1;
        }

        var isGenerateStep = step === 2;

        var scanButton = root.querySelector('[data-bbai-onboarding-action="start-scan"]');
        var skipButton = root.querySelector('[data-bbai-onboarding-action="skip"]');
        var statusEl = root.querySelector('.bbai-onboarding-status');
        var scanMetaEl = root.querySelector('[data-bbai-scan-meta]');
        var scanCountEl = root.querySelector('[data-bbai-scan-count]');
        var scanTimeEl = root.querySelector('[data-bbai-scan-time]');
        var scanProgressEl = root.querySelector('[data-bbai-scan-progress]');
        var scanProgressBarEl = root.querySelector('.bbai-onboarding-scan-progress-bar');
        var scanProgressFillEl = root.querySelector('[data-bbai-scan-progress-fill]');
        var scanProgressTextEl = root.querySelector('[data-bbai-scan-progress-text]');
        var scanProgressTimer = null;
        var scanRedirectTimer = null;
        var hasRedirected = false;

        function setHidden(el, hidden) {
            if (!el) {
                return;
            }

            if (hidden) {
                el.setAttribute('hidden', 'hidden');
            } else {
                el.removeAttribute('hidden');
            }
        }

        function clearScanTimers() {
            if (scanProgressTimer) {
                window.clearInterval(scanProgressTimer);
                scanProgressTimer = null;
            }

            if (scanRedirectTimer) {
                window.clearTimeout(scanRedirectTimer);
                scanRedirectTimer = null;
            }
        }

        function setScanProgress(percent, message) {
            var normalizedPercent = Math.max(0, Math.min(100, parseInt(percent, 10) || 0));
            if (scanProgressFillEl) {
                scanProgressFillEl.style.width = normalizedPercent + '%';
            }
            if (scanProgressBarEl) {
                scanProgressBarEl.setAttribute('aria-valuenow', String(normalizedPercent));
            }
            if (scanProgressTextEl && message) {
                scanProgressTextEl.textContent = message;
            }
        }

        function setScanEstimate(imageCount) {
            var count = Math.max(0, parseInt(imageCount, 10) || 0);
            var estimateSeconds = Math.max(5, Math.ceil(Math.max(count, 1) / 6));

            if (scanCountEl) {
                if (isGenerateStep) {
                    scanCountEl.textContent = sprintf(
                        _n(
                            'Queuing %d image for ALT text',
                            'Queuing %d images for ALT text',
                            count,
                            'beepbeep-ai-alt-text-generator'
                        ),
                        count
                    );
                } else {
                    scanCountEl.textContent = sprintf(
                        _n(
                            'Scanning %d image',
                            'Scanning %d images',
                            count,
                            'beepbeep-ai-alt-text-generator'
                        ),
                        count
                    );
                }
            }

            if (scanTimeEl) {
                scanTimeEl.textContent = sprintf(
                    __('Estimated time: ~%d seconds', 'beepbeep-ai-alt-text-generator'),
                    estimateSeconds
                );
            }

            return estimateSeconds;
        }

        function renderSignInCta() {
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
            ctaText.textContent = __('Sign in to start generating alt text.', 'beepbeep-ai-alt-text-generator');

            var ctaButton = document.createElement('button');
            ctaButton.type = 'button';
            ctaButton.className = 'button button-primary';
            ctaButton.textContent = __('Sign in', 'beepbeep-ai-alt-text-generator');
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

        if (scanButton) {
            // Capture original label for reliable restoration
            var scanButtonOriginalLabel = (scanButton.textContent || '').trim();
            var startBusyLabel = isGenerateStep
                ? (strings.generateStart || __('Starting generation...', 'beepbeep-ai-alt-text-generator'))
                : (strings.scanStart || __('Starting scan...', 'beepbeep-ai-alt-text-generator'));
            var prepareLabel = isGenerateStep
                ? __('Preparing generation...', 'beepbeep-ai-alt-text-generator')
                : __('Preparing scan...', 'beepbeep-ai-alt-text-generator');
            var failMessage = isGenerateStep
                ? (strings.generateFailed || __("Couldn't start generation. Please try again.", 'beepbeep-ai-alt-text-generator'))
                : (strings.scanFailed || __("Couldn't start the scan. Please try again.", 'beepbeep-ai-alt-text-generator'));

            scanButton.addEventListener('click', function () {
                clearScanTimers();
                hasRedirected = false;

                // Immediately disable and update label
                setLoading(scanButton, true, startBusyLabel);
                setStatus(statusEl, '', '');
                setHidden(scanMetaEl, true);
                setHidden(scanProgressEl, true);
                setScanProgress(0, prepareLabel);

                if (!isGenerateStep) {
                    emitAnalyticsEvent('scan_started', {
                        source: 'onboarding'
                    });
                }

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
                            var totalImages = parseInt((data.total != null ? data.total : queued), 10) || queued;
                            var estimateSeconds = setScanEstimate(totalImages);

                            if (isGenerateStep) {
                                emitAnalyticsEvent('generation_started', {
                                    source: 'onboarding',
                                    requested_count: queued
                                });
                            } else {
                                emitAnalyticsEvent('scan_completed', {
                                    source: 'onboarding',
                                    total_images: totalImages,
                                    queued_count: queued
                                });
                            }

                            // Build and show success message
                            if (queued > 0) {
                                var successMsg = isGenerateStep
                                    ? sprintf(
                                        _n(
                                            "We're generating ALT for %d image. You can leave this page—work continues in the background.",
                                            "We're generating ALT for %d images. You can leave this page—work continues in the background.",
                                            queued,
                                            'beepbeep-ai-alt-text-generator'
                                        ),
                                        queued
                                    )
                                    : sprintf(
                                        _n(
                                            "Scan started. We've queued %d image for alt text generation. You can safely leave this page while we process your library in the background.",
                                            "Scan started. We've queued %d images for alt text generation. You can safely leave this page while we process your library in the background.",
                                            queued,
                                            'beepbeep-ai-alt-text-generator'
                                        ),
                                        queued
                                    );
                                setStatus(statusEl, successMsg, 'success');
                                setHidden(scanMetaEl, false);
                                setHidden(scanProgressEl, false);
                                setScanProgress(5, __('Preparing generation...', 'beepbeep-ai-alt-text-generator'));
                            } else {
                                setStatus(statusEl, __("No images without alt text were found. You're all set.", 'beepbeep-ai-alt-text-generator'), 'info');
                            }

                            // Conditional redirect based on authentication
                            if (bbaiOnboarding.isAuthenticated) {
                                // Authenticated: show progress while generation starts, then redirect to review.
                                if (queued > 0) {
                                    var startedAt = Date.now();
                                    var minVisibleMs = 1200;
                                    var maxWaitMs = Math.min(Math.max((estimateSeconds * 1000) + 1200, 4000), 12000);
                                    var pollIntervalMs = 600;
                                    var queueRequestInFlight = false;
                                    var highestPercent = 12;
                                    var progressTick = null;

                                    var formatProgressMessage = function (percent, processedCount) {
                                        var normalizedProcessed = Math.max(0, parseInt(processedCount, 10) || 0);
                                        if (normalizedProcessed > 0 && totalImages > 0) {
                                            return sprintf(
                                                __('Generating alt text... %1$d/%2$d complete (%3$d%%)', 'beepbeep-ai-alt-text-generator'),
                                                normalizedProcessed,
                                                totalImages,
                                                percent
                                            );
                                        }

                                        return sprintf(
                                            __('Generating alt text... %d%%', 'beepbeep-ai-alt-text-generator'),
                                            percent
                                        );
                                    };

                                    var finishAndRedirect = function () {
                                        if (hasRedirected) {
                                            return;
                                        }

                                        hasRedirected = true;
                                        clearScanTimers();
                                        setScanProgress(100, __('Generation started. Opening review...', 'beepbeep-ai-alt-text-generator'));

                                        var elapsedMs = Date.now() - startedAt;
                                        var remainingVisibleMs = Math.max(0, minVisibleMs - elapsedMs);
                                        window.setTimeout(function () {
                                            window.location = redirectUrl;
                                        }, remainingVisibleMs + 150);
                                    };

                                    progressTick = function () {
                                        if (hasRedirected) {
                                            return;
                                        }

                                        var elapsedSeconds = Math.max(0, (Date.now() - startedAt) / 1000);
                                        var elapsedRatio = Math.min(1, elapsedSeconds / Math.max(estimateSeconds, 1));
                                        var easedRatio = Math.pow(elapsedRatio, 0.62);
                                        var simulatedPercent = Math.min(96, Math.max(12, Math.round(12 + (easedRatio * 84))));

                                        if (simulatedPercent > highestPercent) {
                                            highestPercent = simulatedPercent;
                                        }

                                        setScanProgress(highestPercent, formatProgressMessage(highestPercent, 0));

                                        if (queueRequestInFlight) {
                                            return;
                                        }

                                        queueRequestInFlight = true;
                                        $.post(bbaiOnboarding.ajaxUrl, {
                                            action: 'beepbeepai_queue_stats',
                                            nonce: bbaiOnboarding.queueStatsNonce
                                        })
                                            .done(function (queueResponse) {
                                                if (!(queueResponse && queueResponse.success && queueResponse.data && queueResponse.data.stats)) {
                                                    return;
                                                }

                                                var stats = queueResponse.data.stats || {};
                                                var pending = parseInt(stats.pending, 10) || 0;
                                                var processing = parseInt(stats.processing, 10) || 0;
                                                var remaining = pending + processing;
                                                var processed = Math.max(0, totalImages - remaining);
                                                var actualPercent = Math.min(99, Math.round((processed / Math.max(totalImages, 1)) * 100));

                                                if (actualPercent > highestPercent) {
                                                    highestPercent = actualPercent;
                                                }
                                                setScanProgress(highestPercent, formatProgressMessage(highestPercent, processed));

                                                if (remaining === 0) {
                                                    finishAndRedirect();
                                                }
                                            })
                                            .always(function () {
                                                queueRequestInFlight = false;
                                            });
                                    };

                                    // Run one tick immediately for quicker feedback, then continue on interval.
                                    progressTick();
                                    scanProgressTimer = window.setInterval(progressTick, pollIntervalMs);

                                    scanRedirectTimer = window.setTimeout(function () {
                                        finishAndRedirect();
                                    }, maxWaitMs);
                                } else {
                                    // No work queued - continue to review quickly.
                                    window.setTimeout(function () {
                                        window.location = redirectUrl;
                                    }, 1200);
                                }
                            } else {
                                // Not authenticated: re-enable button, show sign-in CTA
                                setLoading(scanButton, false, scanButtonOriginalLabel);
                                renderSignInCta();
                            }

                            return;
                        }

                        // Server error response - restore via helper to avoid inconsistent state
                        clearScanTimers();
                        setLoading(scanButton, false, scanButtonOriginalLabel);
                        setStatus(statusEl, failMessage, 'error');
                        emitAnalyticsEvent(isGenerateStep ? 'generation_failed' : 'scan_failed', {
                            source: 'onboarding'
                        });
                    })
                    .fail(function () {
                        // Network or request error - restore via helper
                        clearScanTimers();
                        setLoading(scanButton, false, scanButtonOriginalLabel);
                        setStatus(statusEl, failMessage, 'error');
                        emitAnalyticsEvent(isGenerateStep ? 'generation_failed' : 'scan_failed', {
                            source: 'onboarding'
                        });
                    });
            });
        }

        if (skipButton) {
            skipButton.addEventListener('click', function () {
                hasRedirected = true;
                clearScanTimers();
                setLoading(skipButton, true, strings.skipLabel || __('Skipping...', 'beepbeep-ai-alt-text-generator'));

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
                                : (strings.skipFailed || __('Unable to skip onboarding.', 'beepbeep-ai-alt-text-generator')),
                            'error'
                        );
                    })
                    .fail(function () {
                        setStatus(statusEl, strings.skipFailed || __('Unable to skip onboarding. Please try again.', 'beepbeep-ai-alt-text-generator'), 'error');
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
