/**
 * Bulk Operations
 * Handles generate missing and regenerate all operations
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

(function($) {
    'use strict';

    var i18n = window.wp && window.wp.i18n ? window.wp.i18n : null;
    var __ = i18n && typeof i18n.__ === 'function' ? i18n.__ : function(text) { return text; };

    var config = window.bbaiAdminConfig || {};

    /**
     * Check usage stats and determine if user can proceed
     */
    function checkUsageBeforeOperation() {
        var usageStats = (window.BBAI_DASH && window.BBAI_DASH.initialUsage) ||
                         (window.BBAI_DASH && window.BBAI_DASH.usage) ||
                         (window.BBAI && window.BBAI.usage) || null;

        var remaining = usageStats && (usageStats.remaining !== undefined) ? parseInt(usageStats.remaining, 10) : null;
        var plan = usageStats && usageStats.plan ? usageStats.plan.toLowerCase() : 'free';
        var isPremium = plan === 'pro' || plan === 'agency';
        var isOutOfCredits = remaining !== null && remaining !== undefined && remaining === 0;

        return {
            usageStats: usageStats,
            remaining: remaining,
            isPremium: isPremium,
            isOutOfCredits: isOutOfCredits,
            hasCredits: remaining !== null && remaining !== undefined && remaining > 0
        };
    }

    /**
     * Generate alt text for missing images
     */
    window.handleGenerateMissing = function(e) {
        e.preventDefault();
        var $btn = $(this);
        var originalText = $btn.text();

        if ($btn.prop('disabled')) {
            return false;
        }

        if (!window.bbaiHasBulkConfig) {
            window.bbaiModal.error('Configuration error. Please refresh the page and try again.');
            return false;
        }

        var usage = checkUsageBeforeOperation();

        // If user has credits, proceed immediately
        if (usage.hasCredits) {
            continueWithGeneration();
            return false;
        }

        // If no usage stats available, let the API handle it
        if (!usage.usageStats) {
            continueWithGeneration();
            return false;
        }

        // If out of credits and not premium, show upgrade modal
        if (!usage.isPremium && usage.isOutOfCredits) {
            window.bbaiHandleLimitReached({
                message: 'Monthly limit reached. Upgrade to continue generating alt text.',
                code: 'limit_reached',
                usage: usage.usageStats
            });
            return false;
        }

        continueWithGeneration();
        return false;

        function continueWithGeneration() {
            $btn.prop('disabled', true);
            $btn.text('Loading...');

            $.ajax({
                url: config.restMissing || (config.restRoot + 'bbai/v1/list?scope=missing'),
                method: 'GET',
                headers: {
                    'X-WP-Nonce': config.nonce
                },
                data: {
                    limit: 500
                }
            })
            .done(function(response) {
                if (!response || !response.ids || response.ids.length === 0) {
                    // Show custom modal with options to go to library or regenerate all
                    window.bbaiModal.show({
                        type: 'info',
                        title: __('No Missing Alt Text', 'beepbeep-ai-alt-text-generator'),
                        message: __('All images in your library already have alt text. You can generate alt text for individual images in the ALT Library, or regenerate all alt text to update existing ones.', 'beepbeep-ai-alt-text-generator'),
                        buttons: [
                            {
                                text: __('Go to ALT Library', 'beepbeep-ai-alt-text-generator'),
                                primary: true,
                                action: function() {
                                    window.bbaiModal.close();
                                    // Navigate to library tab
                                    var libraryUrl = window.location.href.split('?')[0] + '?page=bbai-library';
                                    window.location.href = libraryUrl;
                                }
                            },
                            {
                                text: __('Regenerate All Alt Text', 'beepbeep-ai-alt-text-generator'),
                                primary: false,
                                action: function() {
                                    window.bbaiModal.close();
                                    // Trigger regenerate all button
                                    var regenerateBtn = document.querySelector('[data-action="regenerate-all"]');
                                    if (regenerateBtn && !regenerateBtn.disabled) {
                                        regenerateBtn.click();
                                    } else if (typeof window.handleRegenerateAll === 'function') {
                                        // Fallback: call the handler directly if button not found
                                        var fakeEvent = { preventDefault: function() {} };
                                        window.handleRegenerateAll.call(regenerateBtn || document.body, fakeEvent);
                                    }
                                }
                            },
                            {
                                text: __('Close', 'beepbeep-ai-alt-text-generator'),
                                primary: false,
                                action: function() {
                                    window.bbaiModal.close();
                                }
                            }
                        ]
                    });
                    $btn.prop('disabled', false);
                    $btn.text(originalText);
                    return;
                }

                var ids = response.ids || [];
                var count = ids.length;

                showBulkProgress('Preparing bulk run...', count, 0);

                bbaiQueueImages(ids, 'bulk', function(success, queued, error, processedIds) {
                    $btn.prop('disabled', false);
                    $btn.text(originalText);

                    if (success && queued > 0) {
                        updateBulkProgressTitle('Successfully Queued!');
                        logBulkProgressSuccess('Successfully queued ' + queued + ' image' + (queued !== 1 ? 's' : '') + ' for processing');
                        startInlineGeneration(processedIds || ids, 'bulk');
                    } else if (success && queued === 0) {
                        updateBulkProgressTitle('Already Queued');
                        logBulkProgressSuccess('All images are already in queue or processing');
                        startInlineGeneration(processedIds || ids, 'bulk');
                    } else {
                        handleBulkError(error, ids, count, $btn, originalText, 'bulk');
                    }
                });
            })
            .fail(function(xhr, status, error) {
                console.error('[AI Alt Text] Failed to get missing images:', error, xhr);
                $btn.prop('disabled', false);
                $btn.text(originalText);
                logBulkProgressError('Failed to load images. Please try again.');
            });
        }
    };

    /**
     * Regenerate alt text for all images
     */
    window.handleRegenerateAll = function(e) {
        e.preventDefault();

        if (!confirm('This will regenerate alt text for ALL images, replacing existing alt text. Are you sure?')) {
            return false;
        }

        var $btn = $(this);
        var originalText = $btn.text();

        if ($btn.prop('disabled')) {
            return false;
        }

        if (!window.bbaiHasBulkConfig) {
            window.bbaiModal.error('Configuration error. Please refresh the page and try again.');
            return false;
        }

        var usage = checkUsageBeforeOperation();

        if (!usage.isPremium && usage.isOutOfCredits) {
            window.bbaiHandleLimitReached({
                message: 'Monthly limit reached. Upgrade to continue regenerating alt text.',
                code: 'limit_reached',
                usage: usage.usageStats
            });
            return false;
        }

        $btn.prop('disabled', true);
        $btn.text('Loading...');

        $.ajax({
            url: config.restAll || (config.restRoot + 'bbai/v1/list?scope=all'),
            method: 'GET',
            headers: {
                'X-WP-Nonce': config.nonce
            },
            data: {
                limit: 500
            }
        })
        .done(function(response) {
            if (!response || !response.ids || response.ids.length === 0) {
                window.bbaiModal.info('No images found.');
                $btn.prop('disabled', false);
                $btn.text(originalText);
                return;
            }

            var ids = response.ids || [];
            var count = ids.length;

            showBulkProgress('Preparing bulk regeneration...', count, 0);

            bbaiQueueImages(ids, 'bulk-regenerate', function(success, queued, error, processedIds) {
                $btn.prop('disabled', false);
                $btn.text(originalText);

                if (success && queued > 0) {
                    updateBulkProgressTitle('Successfully Queued!');
                    logBulkProgressSuccess('Successfully queued ' + queued + ' image' + (queued !== 1 ? 's' : '') + ' for regeneration');
                    startInlineGeneration(processedIds || ids, 'bulk-regenerate');
                } else if (success && queued === 0) {
                    updateBulkProgressTitle('Already Queued');
                    logBulkProgressSuccess('All images are already in queue or processing');
                    startInlineGeneration(processedIds || ids, 'bulk-regenerate');
                } else {
                    handleBulkError(error, ids, count, $btn, originalText, 'bulk-regenerate');
                }
            });
        })
        .fail(function(xhr, status, error) {
            console.error('[AI Alt Text] Failed to get all images:', error, xhr);
            $btn.prop('disabled', false);
            $btn.text(originalText);
            logBulkProgressError('Failed to load images. Please try again.');
        });
    };

    /**
     * Handle bulk operation errors
     */
    function handleBulkError(error, ids, count, $btn, originalText, source) {
        if (error && error.code === 'limit_reached') {
            hideBulkProgress();
            window.bbaiHandleLimitReached(error);
            return;
        }

        if (error && error.code === 'insufficient_credits' && error.remaining !== null && error.remaining === 0) {
            hideBulkProgress();
            window.bbaiHandleLimitReached(error);
            return;
        }

        if (error && error.message) {
            logBulkProgressError(error.message);
        } else {
            logBulkProgressError('Failed to queue images. Please try again.');
        }

        if (error && error.code) {
            console.error('[AI Alt Text] Error:', error.code, error.message || 'Unknown error');
        }

        // Handle partial credits
        if (error && error.code === 'insufficient_credits' && error.remaining !== null && error.remaining > 0) {
            hideBulkProgress();
            var remainingCount = error.remaining;
            var errorMsg = error.message || 'You only have ' + remainingCount + ' generations remaining.';

            var generateWithRemaining = confirm(
                errorMsg + '\n\n' +
                'Would you like to generate ' + remainingCount + ' image' + (remainingCount !== 1 ? 's' : '') +
                ' now using your remaining ' + remainingCount + ' credit' + (remainingCount !== 1 ? 's' : '') + '?\n\n' +
                '(Click "OK" to generate now, or "Cancel" to upgrade)'
            );

            if (generateWithRemaining) {
                var limitedIds = ids.slice(0, remainingCount);
                $btn.prop('disabled', true);
                $btn.text('Loading...');
                showBulkProgress('Queueing ' + remainingCount + ' image' + (remainingCount !== 1 ? 's' : '') + '...', remainingCount, 0);

                bbaiQueueImages(limitedIds, source, function(success, queued, queueError, processedLimited) {
                    $btn.prop('disabled', false);
                    $btn.text(originalText);

                    if (success && queued > 0) {
                        updateBulkProgressTitle('Successfully Queued!');
                        logBulkProgressSuccess('Queued ' + queued + ' image' + (queued !== 1 ? 's' : '') + ' using remaining credits');
                        startInlineGeneration(processedLimited || limitedIds, source);
                    } else {
                        var msg = 'Failed to queue images.';
                        if (queueError && queueError.message) {
                            msg = queueError.message;
                        }
                        logBulkProgressError(msg);
                    }
                });
            } else {
                var confirmUpgrade = confirm(
                    'Would you like to upgrade to get more credits and generate all ' + count +
                    ' image' + (count !== 1 ? 's' : '') + '?'
                );

                if (confirmUpgrade) {
                    if (typeof alttextaiShowModal === 'function') {
                        alttextaiShowModal();
                    } else {
                        var upgradeBtn = document.querySelector('[data-action="show-upgrade-modal"]');
                        if (upgradeBtn) {
                            upgradeBtn.click();
                        }
                    }
                }
            }
        }
    }

})(jQuery);
