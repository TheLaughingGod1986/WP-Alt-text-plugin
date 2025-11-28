/**
 * AI Alt Text Admin JavaScript
 * Handles bulk generate, regenerate all, and individual regenerate buttons
 */

(function($) {
    'use strict';

    // Ensure BBAI_DASH exists (from dashboard) or use BBAI
    var config = window.BBAI_DASH || window.BBAI || {};
    
    // Check if we have the necessary configuration for bulk operations
    var hasBulkConfig = config.rest && config.nonce;
    
    if (!hasBulkConfig) {
        console.warn('[AI Alt Text] REST configuration missing. Bulk operations disabled, but single regenerate will still work.');
    }

    function canManageAccount() {
        return !!(window.BBAI && window.BBAI.canManage);
    }

    function handleLimitReached(errorData) {
        var message = (errorData && errorData.message) || 'Monthly limit reached. Please contact a site administrator.';
        if (!canManageAccount()) {
            showNotification(message, 'warning');
            return;
        }

        var usage = errorData && errorData.usage ? errorData.usage : null;

        // Try multiple methods to show the upgrade modal
        if (typeof alttextaiShowModal === 'function') {
            alttextaiShowModal();
        } else if (typeof window.alttextaiShowModal === 'function') {
            window.alttextaiShowModal();
        } else if (typeof showUpgradeModal === 'function') {
            showUpgradeModal(usage);
        } else if (typeof window.beepbeepai_show_upgrade_modal === 'function') {
            window.beepbeepai_show_upgrade_modal(usage);
        } else {
            // Fallback: trigger event or click upgrade button
            var upgradeBtn = document.querySelector('[data-action="show-upgrade-modal"]');
            if (upgradeBtn) {
                upgradeBtn.click();
            } else {
                $(document).trigger('alttextai:show-upgrade-modal', [usage]);
            }
        }

        // Show notification
        if (typeof showNotification === 'function') {
            showNotification(message, 'error');
        }
    }

    /**
     * Generate alt text for missing images
     */
    function handleGenerateMissing(e) {
        e.preventDefault();
        var $btn = $(this);
        var originalText = $btn.text();
        
        if ($btn.prop('disabled')) {
            return false;
        }
        
        // Check if we have necessary configuration
        if (!hasBulkConfig) {
            alert('Configuration error. Please refresh the page and try again.');
            return false;
        }

        // Check if user is out of credits BEFORE starting
        // First, try to get fresh usage stats from API if available
        var usageStats = (window.BBAI_DASH && window.BBAI_DASH.initialUsage) ||
                         (window.BBAI_DASH && window.BBAI_DASH.usage) ||
                         (window.BBAI && window.BBAI.usage) || null;

        // Check if remaining is 0 or less, or if used >= limit
        var remaining = usageStats && (usageStats.remaining !== undefined) ? parseInt(usageStats.remaining, 10) : null;
        var used = usageStats && (usageStats.used !== undefined) ? parseInt(usageStats.used, 10) : null;
        var limit = usageStats && (usageStats.limit !== undefined) ? parseInt(usageStats.limit, 10) : null;
        var plan = usageStats && usageStats.plan ? usageStats.plan.toLowerCase() : 'free';

        // Check if user has quota OR is on premium plan (pro/agency)
        // Only show modal when remaining is explicitly 0 (not null/undefined)
        var isPremium = plan === 'pro' || plan === 'agency';
        var hasQuota = remaining !== null && remaining !== undefined && remaining > 0;
        var isOutOfCredits = remaining !== null && remaining !== undefined && remaining === 0;

        // Safety check: If we have credits remaining (> 0), NEVER show modal
        if (remaining !== null && remaining !== undefined && remaining > 0) {
            // User has credits - continue without checking anything else
            continueWithGeneration();
            return false;
        }

        // If no usage stats available, don't block - let the API handle it
        if (!usageStats) {
            continueWithGeneration();
            return false;
        }

        // If usage stats show limit reached, try refreshing from API once before blocking
        // ONLY if credits are explicitly 0 (not null/undefined)
        if (!isPremium && isOutOfCredits && config.restUsage) {
            // Attempt to fetch fresh usage stats from API
            var refreshUsagePromise = $.ajax({
                url: config.restUsage,
                method: 'GET',
                headers: {
                    'X-WP-Nonce': config.nonce
                },
                cache: false
            }).then(function(response) {
                if (response && response.success && response.data) {
                    // Update cached usage stats
                    var freshUsage = response.data;
                    if (window.BBAI_DASH) {
                        window.BBAI_DASH.usage = freshUsage;
                    }
                    // Re-check with fresh data
                    var freshRemaining = freshUsage.remaining !== undefined ? parseInt(freshUsage.remaining, 10) : null;
                    var freshPlan = freshUsage.plan ? freshUsage.plan.toLowerCase() : 'free';
                    var freshIsPremium = freshPlan === 'pro' || freshPlan === 'agency';
                    var freshIsOutOfCredits = freshRemaining !== null && freshRemaining !== undefined && freshRemaining === 0;
                    
                    if (!freshIsPremium && freshIsOutOfCredits) {
                        // Still at limit after refresh - only show modal when credits are exactly 0
                        handleLimitReached({
                            message: 'Monthly limit reached. Upgrade to continue generating alt text.',
                            code: 'limit_reached',
                            usage: freshUsage
                        });
                        return false;
                    }
                    // Fresh data shows quota available, continue with generation
                    return true;
                }
                // API call failed, use cached data
                return null;
            }).catch(function() {
                // API call failed, use cached data
                return null;
            });
            
            // Wait for refresh attempt, then check again
            refreshUsagePromise.done(function(shouldContinue) {
                if (shouldContinue === false) {
                    // Still at limit after refresh
                    return false;
                }
                if (shouldContinue === null) {
                    // Refresh failed, check cached data - only show modal if credits are actually 0
                    var cachedRemaining = remaining;
                    var cachedIsOutOfCredits = cachedRemaining !== null && cachedRemaining !== undefined && cachedRemaining === 0;
                    if (cachedIsOutOfCredits && !isPremium) {
                        handleLimitReached({
                            message: 'Monthly limit reached. Upgrade to continue generating alt text.',
                            code: 'limit_reached',
                            usage: usageStats
                        });
                        return false;
                    }
                    // If cached data shows credits available or is unclear, continue anyway
                    continueWithGeneration();
                    return;
                }
                // Fresh data shows quota available, continue
                continueWithGeneration();
            });
            
            return false; // Prevent immediate continuation, wait for refresh
        } else if (usageStats && !isPremium && isOutOfCredits) {
            // User is out of credits (remaining === 0) and not on premium plan - show upgrade modal
            // Only show modal when credits are explicitly 0, not when null/undefined
            handleLimitReached({
                message: 'Monthly limit reached. Upgrade to continue generating alt text.',
                code: 'limit_reached',
                usage: usageStats
            });
            return false;
        }

        continueWithGeneration();
        return false;

        function continueWithGeneration() {
            $btn.prop('disabled', true);
            $btn.text('Loading...');

            // Get list of images missing alt text
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
                alert('No images found that need alt text.');
                $btn.prop('disabled', false);
                $btn.text(originalText);
                return;
            }

            var ids = response.ids || [];
            var count = ids.length;
            
            // Log analytics event
            if (typeof window.logEvent === 'function') {
                window.logEvent('alt_text_generate', {
                    count: count,
                    source: 'bulk-generate-missing'
                });
            }
            
            // Show progress bar
            showBulkProgress('Preparing bulk run...', count, 0);

            // Queue all images
            queueImages(ids, 'bulk', function(success, queued, error, processedIds) {
                $btn.prop('disabled', false);
                $btn.text(originalText);

                // Check for subscription error
                if (error && error.subscriptionError) {
                    hideBulkProgress();
                    // Upgrade modal should already be triggered by queueImages
                    return; // Exit early
                }

                if (success && queued > 0) {
                    // Update modal to show success and keep it open
                    updateBulkProgressTitle('Successfully Queued!');
                    logBulkProgressSuccess('Successfully queued ' + queued + ' image' + (queued !== 1 ? 's' : '') + ' for processing');
                    startInlineGeneration(processedIds || ids, 'bulk');

                    // Don't hide modal - let user close it manually or monitor progress
                } else if (success && queued === 0) {
                    updateBulkProgressTitle('Already Queued');
                    logBulkProgressSuccess('All images are already in queue or processing');
                    startInlineGeneration(processedIds || ids, 'bulk');

                    // Don't hide modal - let user close it manually
                } else {
                    // Check for limit_reached FIRST - show upgrade modal immediately
                    if (error && error.code === 'limit_reached') {
                        hideBulkProgress();
                        handleLimitReached(error);
                        return; // Exit early - don't show bulk progress modal
                    }

                    // Check for insufficient credits with 0 remaining - show upgrade modal
                    if (error && error.code === 'insufficient_credits' && error.remaining !== null && error.remaining === 0) {
                        hideBulkProgress();
                        handleLimitReached(error);
                        return; // Exit early - don't show bulk progress modal
                    }

                    // Show error in modal log
                    if (error && error.message) {
                        logBulkProgressError(error.message);
                    } else {
                        logBulkProgressError('Failed to queue images. Please try again.');
                    }

                    // Error logging (keep minimal for production)
                    if (error && error.code) {
                        console.error('[AI Alt Text] Error:', error.code, error.message || 'Unknown error');
                    }

                    // Check for insufficient credits with remaining > 0 - offer partial generation
                    if (error && error.code === 'insufficient_credits' && error.remaining !== null && error.remaining > 0) {
                        hideBulkProgress();
                        var remainingCount = error.remaining;
                        var totalRequested = count;
                        var errorMsg = error.message || 'You only have ' + remainingCount + ' generations remaining.';
                        
                        // Offer to generate with remaining credits or upgrade
                        var generateWithRemaining = confirm(
                            errorMsg + '\n\n' +
                            'Would you like to generate ' + remainingCount + ' image' + (remainingCount !== 1 ? 's' : '') + 
                            ' now using your remaining ' + remainingCount + ' credit' + (remainingCount !== 1 ? 's' : '') + '?\n\n' +
                            '(Click "OK" to generate now, or "Cancel" to upgrade)'
                        );
                        
                        if (generateWithRemaining) {
                            // Generate with remaining credits
                            var limitedIds = ids.slice(0, remainingCount);
                            $btn.prop('disabled', true);
                            $btn.text('Loading...');
                            showBulkProgress('Queueing ' + remainingCount + ' image' + (remainingCount !== 1 ? 's' : '') + '...', remainingCount, 0);
                            
                            queueImages(limitedIds, 'bulk', function(success, queued, queueError, processedLimited) {
                                $btn.prop('disabled', false);
                                $btn.text(originalText);
                                
                                if (success && queued > 0) {
                                    updateBulkProgressTitle('Successfully Queued!');
                                    logBulkProgressSuccess('Queued ' + queued + ' image' + (queued !== 1 ? 's' : '') + ' using remaining credits');
                                    startInlineGeneration(processedLimited || limitedIds, 'bulk');
                                } else {
                                    var errorMsg = 'Failed to queue images.';
                                    if (queueError && queueError.message) {
                                        errorMsg = queueError.message;
                                    }
                                    logBulkProgressError(errorMsg);
                                }
                            });
                            return; // Exit early
                        } else {
                            // User clicked Cancel - offer upgrade
                            var confirmUpgrade = confirm(
                                'Would you like to upgrade to get more credits and generate all ' + totalRequested + 
                                ' image' + (totalRequested !== 1 ? 's' : '') + '?'
                            );
                            
                            if (confirmUpgrade) {
                                // Upgrade
                                if (typeof alttextaiShowModal === 'function') {
                                    alttextaiShowModal();
                                } else if (typeof window.bbai_ajax !== 'undefined' && window.bbai_ajax.is_authenticated) {
                                    var upgradeBtn = document.querySelector('[data-action="show-upgrade-modal"]');
                                    if (upgradeBtn) {
                                        upgradeBtn.click();
                                    }
                                } else {
                                    if (typeof showAuthLogin === 'function') {
                                        showAuthLogin();
                                    }
                                }
                            }
                            return; // Exit early
                        }
                    }
                    
                    // Handle other errors
                    var errorMsg = 'Failed to queue images.';
                    if (error && error.message) {
                        errorMsg = error.message;
                    } else {
                        if (count > 0) {
                            errorMsg += ' Please check your browser console for details and try again.';
                        } else {
                            errorMsg += ' No images were found to queue.';
                        }
                    }

                    if (error && error.message) {
                        console.error('[AI Alt Text] Error details:', error);
                    } else {
                        console.error('[AI Alt Text] Queue failed for generate missing - no error details');
                    }

                    // Keep modal open to show error - user can close manually
                }
            });
            })
            .fail(function(xhr, status, error) {
                console.error('[AI Alt Text] Failed to get missing images:', error, xhr);
                $btn.prop('disabled', false);
                $btn.text(originalText);

                logBulkProgressError('Failed to load images. Please try again.');
                // Keep modal open to show error - user can close manually
            });
        }
    }

    /**
     * Regenerate alt text for all images
     */
    function handleRegenerateAll(e) {
        e.preventDefault();
        
        if (!confirm('This will regenerate alt text for ALL images, replacing existing alt text. Are you sure?')) {
            return false;
        }

        var $btn = $(this);
        var originalText = $btn.text();
        
        if ($btn.prop('disabled')) {
            return false;
        }

        // Check if we have necessary configuration (check dynamically in case BBAI_DASH loads after script)
        var currentConfig = window.BBAI_DASH || window.BBAI || config;
        var hasConfig = currentConfig.rest && currentConfig.nonce;
        if (!hasConfig) {
            console.error('[AI Alt Text] Missing REST config:', {
                BBAI_DASH: !!window.BBAI_DASH,
                BBAI: !!window.BBAI,
                config: config,
                currentConfig: currentConfig
            });
            alert('Configuration error. Please refresh the page and try again.');
            return false;
        }

        // Check if user is out of credits BEFORE starting
        var usageStats = (window.BBAI_DASH && window.BBAI_DASH.initialUsage) ||
                         (window.BBAI_DASH && window.BBAI_DASH.usage) ||
                         (window.BBAI && window.BBAI.usage) || null;

        // Check if remaining is 0 or less, or if used >= limit
        var remaining = usageStats && (usageStats.remaining !== undefined) ? parseInt(usageStats.remaining, 10) : null;
        var used = usageStats && (usageStats.used !== undefined) ? parseInt(usageStats.used, 10) : null;
        var limit = usageStats && (usageStats.limit !== undefined) ? parseInt(usageStats.limit, 10) : null;
        var plan = usageStats && usageStats.plan ? usageStats.plan.toLowerCase() : 'free';

        // Safety check: If we have credits remaining (> 0), NEVER show modal
        if (remaining !== null && remaining !== undefined && remaining > 0) {
            // User has credits - continue with regeneration
            $btn.prop('disabled', true);
            $btn.text('Loading...');
            // Continue with regeneration - user has credits
        } else {
            // Check if user has quota OR is on premium plan (pro/agency)
            // Only show modal when remaining is explicitly 0 (not null/undefined)
            var isPremium = plan === 'pro' || plan === 'agency';
            var isOutOfCredits = remaining !== null && remaining !== undefined && remaining === 0;

            // If no usage stats available, don't block - let the API handle it
            if (!usageStats) {
                $btn.prop('disabled', true);
                $btn.text('Loading...');
                // Continue with regeneration - API will handle credit checks
            } else if (!isPremium && isOutOfCredits) {
                // User is out of credits (remaining === 0) and not on premium plan - show upgrade modal
                // Only show modal when credits are explicitly 0, not when null/undefined
                handleLimitReached({
                    message: 'Monthly limit reached. Upgrade to continue regenerating alt text.',
                    code: 'limit_reached',
                    usage: usageStats
                });
                return false;
            }
        }

        $btn.prop('disabled', true);
        $btn.text('Loading...');

        // Get list of all images (use dynamically checked config)
        var currentConfig = window.BBAI_DASH || window.BBAI || config;
        $.ajax({
            url: currentConfig.restAll || (currentConfig.restRoot + 'bbai/v1/list?scope=all'),
            method: 'GET',
            headers: {
                'X-WP-Nonce': currentConfig.nonce
            },
            data: {
                limit: 500
            }
        })
        .done(function(response) {
            if (!response || !response.ids || response.ids.length === 0) {
                alert('No images found.');
                $btn.prop('disabled', false);
                $btn.text(originalText);
                return;
            }

            var ids = response.ids || [];
            var count = ids.length;
            
            // Log analytics event
            if (typeof window.logEvent === 'function') {
                window.logEvent('alt_text_generate', {
                    count: count,
                    source: 'bulk-regenerate-all'
                });
            }
            
            // Show progress bar
            showBulkProgress('Preparing bulk regeneration...', count, 0);

            // Queue all images
            queueImages(ids, 'bulk-regenerate', function(success, queued, error, processedIds) {
                $btn.prop('disabled', false);
                $btn.text(originalText);

                // Check for subscription error
                if (error && error.subscriptionError) {
                    hideBulkProgress();
                    // Upgrade modal should already be triggered by queueImages
                    return; // Exit early
                }

                if (success && queued > 0) {
                    // Update modal to show success and keep it open
                    updateBulkProgressTitle('Successfully Queued!');
                    logBulkProgressSuccess('Successfully queued ' + queued + ' image' + (queued !== 1 ? 's' : '') + ' for regeneration');
                    startInlineGeneration(processedIds || ids, 'bulk-regenerate');

                    // Don't hide modal - let user close it manually or monitor progress
                } else if (success && queued === 0) {
                    updateBulkProgressTitle('Already Queued');
                    logBulkProgressSuccess('All images are already in queue or processing');
                    startInlineGeneration(processedIds || ids, 'bulk-regenerate');

                    // Don't hide modal - let user close it manually
                } else {
                    // Check for limit_reached FIRST - show upgrade modal immediately
                    if (error && error.code === 'limit_reached') {
                        hideBulkProgress();
                        handleLimitReached(error);
                        return; // Exit early - don't show bulk progress modal
                    }

                    // Check for insufficient credits with 0 remaining - show upgrade modal
                    if (error && error.code === 'insufficient_credits' && error.remaining !== null && error.remaining === 0) {
                        hideBulkProgress();
                        handleLimitReached(error);
                        return; // Exit early - don't show bulk progress modal
                    }

                    // Show error in modal log
                    if (error && error.message) {
                        logBulkProgressError(error.message);
                    } else {
                        logBulkProgressError('Failed to queue images. Please try again.');
                    }

                    // Error logging (keep minimal for production)
                    if (error && error.code) {
                        console.error('[AI Alt Text] Error:', error.code, error.message || 'Unknown error');
                    }

                    // Check for insufficient credits with remaining > 0 - offer partial regeneration
                    if (error && error.code === 'insufficient_credits' && error.remaining !== null && error.remaining > 0) {
                        hideBulkProgress();
                        var remainingCount = error.remaining;
                        var totalRequested = count;
                        var errorMsg = error.message || 'You only have ' + remainingCount + ' generations remaining.';
                        
                        // Offer to regenerate with remaining credits or upgrade
                        var regenerateWithRemaining = confirm(
                            errorMsg + '\n\n' +
                            'Would you like to regenerate ' + remainingCount + ' image' + (remainingCount !== 1 ? 's' : '') + 
                            ' now using your remaining ' + remainingCount + ' credit' + (remainingCount !== 1 ? 's' : '') + '?\n\n' +
                            '(Click "OK" to regenerate now, or "Cancel" to upgrade)'
                        );
                        
                        if (regenerateWithRemaining) {
                            // Regenerate with remaining credits
                            var limitedIds = ids.slice(0, remainingCount);
                            $btn.prop('disabled', true);
                            $btn.text('Loading...');
                            showBulkProgress('Queueing ' + remainingCount + ' image' + (remainingCount !== 1 ? 's' : '') + ' for regeneration...', remainingCount, 0);
                            
                            queueImages(limitedIds, 'bulk-regenerate', function(success, queued, queueError, processedLimited) {
                                $btn.prop('disabled', false);
                                $btn.text(originalText);
                                
                                if (success && queued > 0) {
                                    updateBulkProgressTitle('Successfully Queued!');
                                    logBulkProgressSuccess('Queued ' + queued + ' image' + (queued !== 1 ? 's' : '') + ' using remaining credits');
                                    startInlineGeneration(processedLimited || limitedIds, 'bulk-regenerate');
                                } else {
                                    var errorMsg = 'Failed to queue images.';
                                    if (queueError && queueError.message) {
                                        errorMsg = queueError.message;
                                    }
                                    logBulkProgressError(errorMsg);
                                }
                            });
                            return; // Exit early
                        } else {
                            // User clicked Cancel - offer upgrade
                            var confirmUpgrade = confirm(
                                'Would you like to upgrade to get more credits and regenerate all ' + totalRequested + 
                                ' image' + (totalRequested !== 1 ? 's' : '') + '?'
                            );
                            
                            if (confirmUpgrade) {
                                // Upgrade
                                if (typeof alttextaiShowModal === 'function') {
                                    alttextaiShowModal();
                                } else if (typeof window.bbai_ajax !== 'undefined' && window.bbai_ajax.is_authenticated) {
                                    var upgradeBtn = document.querySelector('[data-action="show-upgrade-modal"]');
                                    if (upgradeBtn) {
                                        upgradeBtn.click();
                                    }
                                } else {
                                    if (typeof showAuthLogin === 'function') {
                                        showAuthLogin();
                                    }
                                }
                            }
                            return; // Exit early
                        }
                    }
                    
                    // Handle other errors
                    var errorMsg = 'Failed to queue images.';
                    if (error && error.message) {
                        errorMsg = error.message;
                    } else {
                        if (count > 0) {
                            errorMsg += ' Please check your browser console for details and try again.';
                        } else {
                            errorMsg += ' No images were found to queue.';
                        }
                    }

                    if (error && error.message) {
                        console.error('[AI Alt Text] Error details:', error);
                    } else {
                        console.error('[AI Alt Text] Queue failed for regenerate all - no error details');
                    }

                    // Keep modal open to show error - user can close manually
                }
            });
        })
        .fail(function(xhr, status, error) {
            console.error('[AI Alt Text] Failed to get all images:', error, xhr);
            $btn.prop('disabled', false);
            $btn.text(originalText);

            logBulkProgressError('Failed to load images. Please try again.');
            // Keep modal open to show error - user can close manually
        });
    }

    /**
     * Regenerate alt text for a single image - shows modal with preview
     */
    function handleRegenerateSingle(e) {
        e.preventDefault();

        console.log('[AI Alt Text] Regenerate button clicked');
        var $btn = $(this);
        var attachmentId = $btn.data('attachment-id');

        console.log('[AI Alt Text] Attachment ID:', attachmentId);
        console.log('[AI Alt Text] Button disabled?', $btn.prop('disabled'));

        if (!attachmentId || $btn.prop('disabled')) {
            console.warn('[AI Alt Text] Cannot regenerate - missing ID or button disabled');
            return false;
        }

        // Log analytics event
        if (typeof window.logEvent === 'function') {
            window.logEvent('alt_text_generate', {
                count: 1,
                source: 'regenerate-single'
            });
        }

        // Disable the button immediately to prevent multiple clicks
        var originalText = $btn.text();
        $btn.prop('disabled', true).addClass('regenerating');
        $btn.text('Processing...');

        // Get image info from the row
        var $row = $btn.closest('tr');
        var imageTitle = $row.find('.bbai-table__cell--title').text().trim() || 'Image';
        var imageSrc = $row.find('img').attr('src') || '';

        // Show modal
        showRegenerateModal(attachmentId, imageTitle, imageSrc, $btn, originalText);
    }

    /**
     * Show regenerate modal and start generation
     */
    function showRegenerateModal(attachmentId, imageTitle, imageSrc, $btn, originalBtnText) {
        // Check if modal exists, if not create it
        var $modal = $('#bbai-regenerate-modal');
        if (!$modal.length) {
            $modal = createRegenerateModal();
        }

        // Populate modal with image info
        $modal.find('.bbai-regenerate-modal__image-title').text(imageTitle);
        $modal.find('.bbai-regenerate-modal__thumbnail').attr('src', imageSrc);

        // Show loading state
        $modal.find('.bbai-regenerate-modal__loading').addClass('active');
        $modal.find('.bbai-regenerate-modal__result').removeClass('active');
        $modal.find('.bbai-regenerate-modal__error').removeClass('active');

        // Disable accept button during loading
        $modal.find('.bbai-regenerate-modal__btn--accept').prop('disabled', true);

        // Show modal
        $modal.addClass('active');
        $('body').css('overflow', 'hidden');

        console.log('[AI Alt Text] Starting AJAX request...');

        // Use AJAX endpoint for single regeneration
        var ajaxUrl = (window.bbai_ajax && window.bbai_ajax.ajaxurl) ||
                     (window.BBAI && window.BBAI.restRoot ? window.BBAI.restRoot.replace(/\/$/, '') + '/admin-ajax.php' : null) ||
                     (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
        var nonceValue = (window.bbai_ajax && window.bbai_ajax.nonce) ||
                       (window.BBAI && window.BBAI.nonce) ||
                       '';

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'beepbeepai_regenerate_single',
                attachment_id: attachmentId,
                nonce: nonceValue
            }
        })
        .done(function(response) {
            console.log('[AI Alt Text] Regenerate response:', response);

            // Hide loading state
            $modal.find('.bbai-regenerate-modal__loading').removeClass('active');

            // Check for subscription error or NO_ACCESS in response
            var subscriptionError = null;
            if (response && response.data) {
                if (response.data.subscription_error || 
                    response.data.no_access ||
                    (response.data.code && response.data.code === 'NO_ACCESS') ||
                    (response.data.error_code && (
                        response.data.error_code === 'subscription_required' ||
                        response.data.error_code === 'subscription_expired' ||
                        response.data.error_code === 'quota_exceeded' ||
                        response.data.error_code === 'no_access' ||
                        response.data.error_code === 'out_of_credits'
                    ))) {
                    // Determine error code from NO_ACCESS context
                    if (response.data.code === 'NO_ACCESS' || response.data.no_access) {
                        var credits = response.data.credits !== undefined ? parseInt(response.data.credits, 10) : null;
                        var subscriptionExpired = response.data.subscription_expired === true;
                        if (subscriptionExpired) {
                            subscriptionError = 'subscription_expired';
                        } else if (credits !== null && credits === 0) {
                            subscriptionError = 'out_of_credits';
                        } else {
                            subscriptionError = 'no_access';
                        }
                    } else {
                        subscriptionError = response.data.error_code || response.data.subscription_error || 'subscription_required';
                    }
                }
            }
            
            // Handle subscription error or NO_ACCESS
            if (subscriptionError) {
                closeRegenerateModal($modal);
                reenableButton($btn, originalBtnText);
                
                // Disable generate button and show No Access state
                $btn.prop('disabled', true).addClass('bbai-no-access');
                
                // Add No Access message/link if needed
                if (!$btn.data('no-access-message-added')) {
                    var noAccessMessage = $('<span class="bbai-no-access-message" style="display: block; margin-top: 8px; color: #dc3232; font-size: 12px;">' +
                        '<a href="#" style="text-decoration: underline;" onclick="if(typeof window.bbai_openUpgradeModal===\'function\'){window.bbai_openUpgradeModal(\'' + subscriptionError + '\');return false;}">Upgrade or purchase credits</a>' +
                        '</span>');
                    $btn.after(noAccessMessage);
                    $btn.data('no-access-message-added', true);
                }
                
                // Trigger upgrade modal
                if (typeof window.bbai_openUpgradeModal === 'function') {
                    window.bbai_openUpgradeModal(subscriptionError);
                }
                return;
            }

            if (response && response.success) {
                var newAltText = response.data && response.data.alt_text ? response.data.alt_text : '';
                console.log('[AI Alt Text] New alt text:', newAltText);

                if (newAltText) {
                    // Log analytics event for alt text generation
                    if (typeof window.logEvent === 'function') {
                        window.logEvent('alt_text_generated', {
                            attachment_id: attachmentId,
                            source: 'ajax'
                        });
                    }
                    
                    // Show result
                    $modal.find('.bbai-regenerate-modal__alt-text').text(newAltText);
                    $modal.find('.bbai-regenerate-modal__result').addClass('active');

                    // Enable accept button
                    $modal.find('.bbai-regenerate-modal__btn--accept')
                        .prop('disabled', false)
                        .off('click')
                        .on('click', function() {
                            acceptRegeneratedAltText(attachmentId, newAltText, $btn, originalBtnText, $modal);
                        });
                } else {
                    showModalError($modal, 'No alt text was generated. Please try again.');
                    reenableButton($btn, originalBtnText);
                }
            } else {
                // Check for limit_reached error
                var errorData = response && response.data ? response.data : {};
                if (errorData.code === 'limit_reached') {
                    closeRegenerateModal($modal);
                    reenableButton($btn, originalBtnText);
                    handleLimitReached(errorData);
                } else {
                    var message = errorData.message || 'Failed to regenerate alt text';
                    showModalError($modal, message);
                    reenableButton($btn, originalBtnText);
                }
            }
        })
        .fail(function(xhr, status, error) {
            console.error('[AI Alt Text] Failed to regenerate:', error, xhr);

            // Hide loading state
            $modal.find('.bbai-regenerate-modal__loading').removeClass('active');

            // Check for limit_reached error in response
            var errorData = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
            if (errorData.code === 'limit_reached') {
                closeRegenerateModal($modal);
                reenableButton($btn, originalBtnText);
                handleLimitReached(errorData);
            } else {
                var message = errorData.message || 'Failed to regenerate alt text. Please try again.';
                showModalError($modal, message);
                reenableButton($btn, originalBtnText);
            }
        });

        // Handle cancel button
        $modal.find('.bbai-regenerate-modal__btn--cancel')
            .off('click')
            .on('click', function() {
                closeRegenerateModal($modal);
                reenableButton($btn, originalBtnText);
            });
    }

    /**
     * Create the regenerate modal HTML
     */
    function createRegenerateModal() {
        var modalHtml =
            '<div id="bbai-regenerate-modal" class="bbai-regenerate-modal">' +
            '    <div class="bbai-regenerate-modal__content">' +
            '        <div class="bbai-regenerate-modal__header">' +
            '            <h2 class="bbai-regenerate-modal__title">Regenerate Alt Text</h2>' +
            '            <p class="bbai-regenerate-modal__subtitle">Review the new alt text before applying</p>' +
            '        </div>' +
            '        <div class="bbai-regenerate-modal__body">' +
            '            <div class="bbai-regenerate-modal__image-preview">' +
            '                <img src="" alt="" class="bbai-regenerate-modal__thumbnail">' +
            '                <div class="bbai-regenerate-modal__image-info">' +
            '                    <p class="bbai-regenerate-modal__image-title"></p>' +
            '                </div>' +
            '            </div>' +
            '            <div class="bbai-regenerate-modal__error"></div>' +
            '            <div class="bbai-regenerate-modal__loading">' +
            '                <div class="bbai-regenerate-modal__spinner"></div>' +
            '                <p class="bbai-regenerate-modal__loading-text">Generating new alt text...</p>' +
            '            </div>' +
            '            <div class="bbai-regenerate-modal__result">' +
            '                <p class="bbai-regenerate-modal__alt-text-label">New Alt Text:</p>' +
            '                <p class="bbai-regenerate-modal__alt-text"></p>' +
            '            </div>' +
            '        </div>' +
            '        <div class="bbai-regenerate-modal__footer">' +
            '            <button type="button" class="bbai-regenerate-modal__btn bbai-regenerate-modal__btn--cancel">Cancel</button>' +
            '            <button type="button" class="bbai-regenerate-modal__btn bbai-regenerate-modal__btn--accept" disabled>Accept & Apply</button>' +
            '        </div>' +
            '    </div>' +
            '</div>';

        $('body').append(modalHtml);
        return $('#bbai-regenerate-modal');
    }

    /**
     * Show error in modal
     */
    function showModalError($modal, message) {
        $modal.find('.bbai-regenerate-modal__error').text(message).addClass('active');
    }

    /**
     * Close regenerate modal
     */
    function closeRegenerateModal($modal) {
        $modal.removeClass('active');
        $('body').css('overflow', '');
    }

    /**
     * Re-enable the regenerate button
     */
    function reenableButton($btn, originalText) {
        $btn.prop('disabled', false).removeClass('regenerating');
        $btn.text(originalText);
    }

    /**
     * Accept regenerated alt text and update the UI
     */
    function acceptRegeneratedAltText(attachmentId, newAltText, $btn, originalBtnText, $modal) {
        console.log('[AI Alt Text] Accepting new alt text');

        // Update the alt text in the table
        var $row = $btn.closest('tr');

        if (newAltText) {
            var $altCell = $row.find('.bbai-library-cell--alt-text');

            if ($altCell.length) {
                var safeAlt = $('<div>').text(newAltText).html();
                var truncated = newAltText.length > 80 ? newAltText.substring(0, 77) + '…' : newAltText;
                var safeTruncated = $('<div>').text(truncated).html();

                var $existing = $altCell.find('.bbai-library-alt-text');
                if ($existing.length) {
                    $existing.text(truncated);
                    $existing.attr('title', newAltText);
                } else {
                    $altCell.html(
                        '<div class="bbai-library-alt-text" title="' + safeAlt + '">' + safeTruncated + '</div>'
                    );
                }

                // Update status badge to "Regenerated"
                var $statusCell = $row.find('.bbai-library-cell--status span');
                if ($statusCell.length) {
                    $statusCell
                        .removeClass()
                        .addClass('bbai-status-badge bbai-status-badge--regenerated')
                        .text('Regenerated');
                }

                // Update row data attribute
                $row.attr('data-status', 'regenerated');
            }
        }

        // Close modal
        closeRegenerateModal($modal);

        // Re-enable button
        reenableButton($btn, originalBtnText);

        // Show success message
        showNotification('Alt text updated successfully!', 'success');

        // Refresh usage stats if available
        if (typeof refreshUsageStats === 'function') {
            refreshUsageStats();
        }
    }

    /**
     * Queue multiple images for processing
     * Uses AJAX endpoint to queue images without generating immediately
     */
    function queueImages(ids, source, callback) {
        if (!ids || ids.length === 0) {
            callback(false, 0);
            return;
        }

        var total = ids.length;
        var queued = 0;
        
        // Use AJAX to queue images
        // We'll create a single AJAX call that queues all images
        var ajaxUrl = '/wp-admin/admin-ajax.php';
        if (window.bbai_ajax) {
            ajaxUrl = window.bbai_ajax.ajax_url || window.bbai_ajax.ajaxurl || ajaxUrl;
        } else if (typeof ajaxurl !== 'undefined') {
            ajaxUrl = ajaxurl;
        }
        var nonceValue = (window.bbai_ajax && window.bbai_ajax.nonce) || config.nonce;
        
        // Queueing images (debug info removed for production)
        
        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'beepbeepai_bulk_queue',
                attachment_ids: ids,
                source: source || 'bulk',
                nonce: nonceValue
            },
            dataType: 'json'
        })
        .done(function(response) {
            
            if (response && response.success) {
                // WordPress wp_send_json_success returns {success: true, data: {...}}
                var responseData = response.data || {};
                queued = responseData.queued || 0;
                
                if (queued > 0) {
                    callback(true, queued, null, ids.slice(0));
                } else {
                    console.warn('[AI Alt Text] No images were queued. Response:', response);
                    // Still might be success if 0 queued but they were already in queue
                    callback(true, queued, null, ids.slice(0));
                }
            } else {
                // Error response from server
                var errorMessage = 'Failed to queue images';
                var errorCode = null;
                var errorRemaining = null;
                var subscriptionError = null;
                
                // WordPress wp_send_json_error wraps data in response.data
                if (response && response.data) {
                    // Check if data is an object with properties
                    if (typeof response.data === 'object' && response.data !== null) {
                        if (response.data.message) {
                            errorMessage = response.data.message;
                        }
                        if (response.data.code) {
                            errorCode = response.data.code;
                        }
                        if (response.data.remaining !== undefined && response.data.remaining !== null) {
                            errorRemaining = parseInt(response.data.remaining, 10);
                        }
                        
                        // Check for subscription error or NO_ACCESS
                        if (response.data.subscription_error || 
                            response.data.no_access ||
                            (response.data.code && response.data.code === 'NO_ACCESS') ||
                            (response.data.error_code && (
                                response.data.error_code === 'subscription_required' ||
                                response.data.error_code === 'subscription_expired' ||
                                response.data.error_code === 'quota_exceeded' ||
                                response.data.error_code === 'no_access' ||
                                response.data.error_code === 'out_of_credits'
                            ))) {
                            // Determine error code from NO_ACCESS context
                            if (response.data.code === 'NO_ACCESS' || response.data.no_access) {
                                var credits = response.data.credits !== undefined ? parseInt(response.data.credits, 10) : null;
                                var subscriptionExpired = response.data.subscription_expired === true;
                                if (subscriptionExpired) {
                                    subscriptionError = 'subscription_expired';
                                } else if (credits !== null && credits === 0) {
                                    subscriptionError = 'out_of_credits';
                                } else {
                                    subscriptionError = 'no_access';
                                }
                            } else {
                                subscriptionError = response.data.error_code || response.data.subscription_error || 'subscription_required';
                            }
                        }
                    }
                }
                
                // Check for subscription error and trigger modal
                if (subscriptionError) {
                    if (typeof window.bbai_openUpgradeModal === 'function') {
                        window.bbai_openUpgradeModal(subscriptionError);
                    }
                    callback(false, 0, {
                        message: errorMessage,
                        code: errorCode,
                        remaining: errorRemaining,
                        subscriptionError: subscriptionError
                    }, ids.slice(0));
                    return;
                }
                
                console.error('[AI Alt Text] Queue failed:', errorMessage, errorCode ? '(Code: ' + errorCode + ')' : '');
                
                // Pass error message to callback
                callback(false, 0, {
                    message: errorMessage,
                    code: errorCode,
                    remaining: errorRemaining
                }, ids.slice(0));
            }
        })
        .fail(function(xhr, status, error) {
            console.error('[AI Alt Text] AJAX request failed:', {
                status: status,
                error: error,
                xhr: xhr,
                responseText: xhr.responseText,
                statusCode: xhr.status
            });
            
            // Try to parse error response
            var errorData = null;
            try {
                var parsed = JSON.parse(xhr.responseText);
                if (parsed && parsed.data) {
                    errorData = {
                        message: parsed.data.message || 'Failed to queue images',
                        code: parsed.data.code || null,
                        remaining: parsed.data.remaining || null
                    };
                }
            } catch (e) {
                // Not JSON, use default error
            }
            
            // Check if it's a nonce error
            if (xhr.status === 403) {
                console.error('[AI Alt Text] Authentication error - check nonce');
                callback(false, 0, errorData || { message: 'Authentication error. Please refresh the page and try again.' });
                return;
            }
            
            // If we have a specific error message, use it instead of falling back
            if (errorData && errorData.message) {
                callback(false, 0, errorData);
                return;
            }
            
            // Fallback: queue images individually via REST API
            // This is slower but more reliable
            // Falling back to REST API method
            queueImagesFallback(ids, source, function(success, queued) {
                callback(success, queued, null, ids.slice(0));
            });
        });
    }

    /**
     * Fallback: Queue images one by one via REST API
     */
    function queueImagesFallback(ids, source, callback) {
        var total = ids.length;
        var queued = 0;
        var failed = 0;
        var batchSize = 5; // Smaller batches for fallback
        var processed = 0;
        
        function processBatch(startIndex) {
            var endIndex = Math.min(startIndex + batchSize, total);
            var batch = ids.slice(startIndex, endIndex);
            
            // Queue this batch using REST generate endpoint (which will queue if busy)
            var promises = batch.map(function(id) {
                return $.ajax({
                    url: (config.restRoot || config.rest || '') + 'bbai/v1/generate/' + id,
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': config.nonce
                    }
                })
                .done(function() {
                    queued++;
                    processed++;
                    updateBulkProgress(processed, total);
                })
                .fail(function() {
                    failed++;
                    processed++;
                    updateBulkProgress(processed, total);
                });
            });
            
            // Wait for batch to complete, then process next batch
            $.when.apply($, promises)
            .then(function() {
                if (endIndex < total) {
                    // Small delay between batches to avoid overwhelming the server
                    setTimeout(function() {
                        processBatch(endIndex);
                    }, 500);
                } else {
                    // All batches processed
                    var success = queued > 0;
                    callback(success, queued, null);
                }
            })
            .fail(function() {
                console.error('[AI Alt Text] Fallback batch failed');
                // Continue processing even if batch fails
                if (endIndex < total) {
                    setTimeout(function() {
                        processBatch(endIndex);
                    }, 500);
                } else {
                    var success = queued > 0;
                    callback(success, queued, null);
                }
            });
        }

        // Start processing
        processBatch(0);
    }

    /**
     * Begin inline generation after queue completes.
     */
    function startInlineGeneration(idList, source) {
        if (!idList || !idList.length || !hasBulkConfig) {
            return;
        }

        var normalized = Array.from(new Set(idList.map(function(id) {
            return parseInt(id, 10);
        }).filter(function(id) {
            return !isNaN(id) && id > 0;
        })));

        if (!normalized.length) {
            return;
        }

        var $modal = $('#bbai-bulk-progress-modal');
        if (!$modal.length) {
            return;
        }

        $modal.data('startTime', Date.now());
        $modal.data('total', normalized.length);
        $modal.data('current', 0);
        $modal.data('successes', 0);
        $modal.data('failed', 0);
        $modal.find('.bbai-bulk-progress__total').text(normalized.length);
        $modal.find('.bbai-bulk-progress__current').text(0);
        $modal.find('.bbai-bulk-progress__percentage').text('0%');
        $modal.find('.bbai-bulk-progress__eta').text('Calculating...');
        $modal.find('.bbai-bulk-progress__bar-fill').css('width', '0%');

        var intro = 'Starting inline generation for ' + normalized.length + ' image' + (normalized.length !== 1 ? 's' : '') + '...';
        updateBulkProgressTitle('Generating Alt Text…');
        logBulkProgressSuccess(intro);

        $modal.data('batchQueue', normalized.slice(0));
        var inlineBatchSize = window.BBAI && window.BBAI.inlineBatchSize
            ? Math.max(1, parseInt(window.BBAI.inlineBatchSize, 10))
            : 1;
        processInlineGenerationQueue(normalized, inlineBatchSize);
    }

    /**
     * Process images sequentially in batches and update modal progress.
     */
    function processInlineGenerationQueue(queue, batchSize) {
        batchSize = batchSize || 1;
        var $modal = $('#bbai-bulk-progress-modal');
        if (!$modal.length) {
            return;
        }
        var total = queue.length;
        var processed = 0;
        var successes = 0;
        var failures = 0;
        var active = 0;

        function processNext() {
            if (!queue.length && active === 0) {
                finalizeInlineGeneration(successes, failures);
                return;
            }

            if (active >= batchSize || !queue.length) {
                return;
            }

            var id = queue.shift();
            active++;

            generateAltTextForId(id)
                .then(function(result) {
                    successes++;
                    processed++;
                    var title = result && result.title ? result.title : ('Generated alt text for image #' + id);
                    updateBulkProgress(processed, total, title);
                })
                .catch(function(error) {
                    failures++;
                    processed++;
                    var message = 'Image #' + id + ': ' + (error && error.message ? error.message : 'Failed to generate alt text.');
                    logBulkProgressError(message);
                    updateBulkProgress(processed, total);
                })
                .finally(function() {
                    active--;
                    setTimeout(processNext, 250);
                });

            if (active < batchSize && queue.length) {
                processNext();
            }
        }

        processNext();
    }

    function finalizeInlineGeneration(successes, failures) {
        var $modal = $('#bbai-bulk-progress-modal');
        var total = successes + failures;
        var startTime = $modal.length ? $modal.data('startTime') : Date.now();
        var elapsed = (Date.now() - startTime) / 1000; // seconds
        
        // Calculate time saved (estimate: 2 minutes per image)
        var timeSavedMinutes = successes * 2;
        var timeSavedHours = Math.round(timeSavedMinutes / 60);
        var timeSavedText = timeSavedHours > 0 ? timeSavedHours + ' hour' + (timeSavedHours !== 1 ? 's' : '') : '< 1 hour';
        
        // Calculate AI confidence (100% if no failures, otherwise percentage)
        var confidence = total > 0 ? Math.round((successes / total) * 100) : 100;
        
        // Hide progress modal
        hideBulkProgress();
        
        // Show success modal
        setTimeout(function() {
            showSuccessModal({
                processed: successes,
                total: total,
                failures: failures,
                timeSaved: timeSavedText,
                confidence: confidence
            });
        }, 300);

        if (typeof refreshUsageStats === 'function') {
            refreshUsageStats();
        }
    }

    function generateAltTextForId(id) {
        return new Promise(function(resolve, reject) {
            var ajaxUrl = (window.bbai_ajax && (window.bbai_ajax.ajax_url || window.bbai_ajax.ajaxurl)) ||
                (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
            var nonceValue = (window.bbai_ajax && window.bbai_ajax.nonce) || '';

            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'beepbeepai_inline_generate',
                    attachment_ids: [id],
                    nonce: nonceValue
                }
            })
            .done(function(response) {
                // Handle successful HTTP response (status 200)
                try {
                    // Handle successful response
                    if (response && response.success) {
                        // Check for results array (inline generate format)
                        if (response.data && response.data.results && Array.isArray(response.data.results)) {
                            var first = response.data.results[0];
                            if (first && first.success) {
                                resolve({
                                    id: id,
                                    alt: first.alt_text || '',
                                    title: first.title || ('Image #' + id)
                                });
                                return;
                            } else {
                                // Generation failed for this image - extract error message
                                var errorMsg = 'Failed to generate alt text.';
                                if (first && first.message) {
                                    errorMsg = first.message;
                                } else if (first && first.code) {
                                    errorMsg = 'Generation failed: ' + first.code;
                                }
                                reject({ message: errorMsg, code: (first && first.code) ? first.code : 'generation_failed' });
                                return;
                            }
                        }
                        // Check for direct alt_text (regenerate single format)
                        else if (response.data && response.data.alt_text) {
                            resolve({
                                id: id,
                                alt: response.data.alt_text || '',
                                title: 'Image #' + id
                            });
                            return;
                        }
                        // Check for error message in data
                        else if (response.data && response.data.message) {
                            reject({ message: response.data.message });
                            return;
                        }
                    }
                    
                    // Handle error response (success: false)
                    if (response && response.success === false) {
                        var errorMsg = 'Failed to generate alt text.';
                        if (response.data && response.data.message) {
                            errorMsg = response.data.message;
                        } else if (response.message) {
                            errorMsg = response.message;
                        }
                        reject({ message: errorMsg, code: (response.data && response.data.code) || response.code || 'api_error' });
                        return;
                    }
                    
                    // Unexpected response structure
                    console.error('[AI Alt Text] Unexpected response structure:', response);
                    reject({ message: 'Unexpected response from server. Response structure does not match expected format.' });
                } catch (e) {
                    console.error('[AI Alt Text] Error parsing response:', e, response);
                    reject({ message: 'Error parsing server response: ' + (e.message || 'Unknown error') });
                }
            })
            .fail(function(xhr) {
                var message = 'Request failed';
                var errorCode = null;
                
                // Try to extract detailed error information
                if (xhr && xhr.responseJSON) {
                    if (xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        message = xhr.responseJSON.data.message;
                    } else if (xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    if (xhr.responseJSON.data && xhr.responseJSON.data.code) {
                        errorCode = xhr.responseJSON.data.code;
                    } else if (xhr.responseJSON.code) {
                        errorCode = xhr.responseJSON.code;
                    }
                } else if (xhr && xhr.status === 0) {
                    message = 'Network error: Unable to connect to server. Please check your internet connection.';
                } else if (xhr && xhr.status === 404) {
                    message = 'AJAX endpoint not found. The plugin may need to be reactivated.';
                } else if (xhr && xhr.status === 500) {
                    message = 'Server error occurred. Please check your WordPress error logs.';
                } else if (xhr && xhr.status === 200) {
                    // Status 200 but response structure is invalid or parsing failed
                    message = 'Server returned an invalid response. Please check the browser console for details.';
                } else if (xhr && xhr.status) {
                    message = 'Request failed with status ' + xhr.status;
                }
                
                // Log detailed error for debugging
                console.error('[AI Alt Text] Inline generate request failed:', {
                    status: xhr ? xhr.status : 'unknown',
                    statusText: xhr ? xhr.statusText : 'unknown',
                    response: xhr ? xhr.responseJSON : 'no response',
                    errorCode: errorCode,
                    message: message
                });
                
                reject({ message: message, code: errorCode });
            });
        });
    }

    /**
     * Show bulk progress modal with detailed tracking
     */
    function showBulkProgress(label, total, current) {
        var $modal = $('#bbai-bulk-progress-modal');

        // Create modal if it doesn't exist
        if (!$modal.length) {
            $modal = createBulkProgressModal();
        }

        // Initialize progress tracking
        $modal.data('startTime', Date.now());
        $modal.data('total', total);
        $modal.data('current', current || 0);

        // Update initial state
        $modal.find('.bbai-bulk-progress__title').text(label || 'Processing Images...');
        $modal.find('.bbai-bulk-progress__total').text(total);
        $modal.find('.bbai-bulk-progress__log').empty();

        // Show modal
        $modal.addClass('active');
        $('body').css('overflow', 'hidden');

        updateBulkProgress(current || 0, total);
    }

    /**
     * Create bulk progress modal HTML
     */
    function createBulkProgressModal() {
        var modalHtml =
            '<div id="bbai-bulk-progress-modal" class="bbai-bulk-progress-modal">' +
            '    <div class="bbai-bulk-progress-modal__overlay"></div>' +
            '    <div class="bbai-bulk-progress-modal__content">' +
            '        <div class="bbai-bulk-progress__header">' +
            '            <h2 class="bbai-bulk-progress__title">Processing Images...</h2>' +
            '            <button type="button" class="bbai-bulk-progress__close" aria-label="Close">×</button>' +
            '        </div>' +
            '        <div class="bbai-bulk-progress__body">' +
            '            <div class="bbai-bulk-progress__stats">' +
            '                <div class="bbai-bulk-progress__stat">' +
            '                    <span class="bbai-bulk-progress__stat-label">Progress</span>' +
            '                    <span class="bbai-bulk-progress__stat-value">' +
            '                        <span class="bbai-bulk-progress__current">0</span> / ' +
            '                        <span class="bbai-bulk-progress__total">0</span>' +
            '                    </span>' +
            '                </div>' +
            '                <div class="bbai-bulk-progress__stat">' +
            '                    <span class="bbai-bulk-progress__stat-label">Percentage</span>' +
            '                    <span class="bbai-bulk-progress__stat-value bbai-bulk-progress__percentage">0%</span>' +
            '                </div>' +
            '                <div class="bbai-bulk-progress__stat">' +
            '                    <span class="bbai-bulk-progress__stat-label">Estimated Time</span>' +
            '                    <span class="bbai-bulk-progress__stat-value bbai-bulk-progress__eta">Calculating...</span>' +
            '                </div>' +
            '            </div>' +
            '            <div class="bbai-bulk-progress__bar-container">' +
            '                <div class="bbai-bulk-progress__bar">' +
            '                    <div class="bbai-bulk-progress__bar-fill" style="width: 0%"></div>' +
            '                </div>' +
            '            </div>' +
            '            <div class="bbai-bulk-progress__log-container">' +
            '                <h3 class="bbai-bulk-progress__log-title">Processing Log</h3>' +
            '                <div class="bbai-bulk-progress__log"></div>' +
            '            </div>' +
            '        </div>' +
            '    </div>' +
            '</div>';

        $('body').append(modalHtml);
        var $modal = $('#bbai-bulk-progress-modal');

        // Add close button handler
        $modal.find('.bbai-bulk-progress__close').on('click', function() {
            hideBulkProgress();
        });

        return $modal;
    }

    /**
     * Update bulk progress bar with detailed stats
     */
    function updateBulkProgress(current, total, imageTitle) {
        var $modal = $('#bbai-bulk-progress-modal');
        if (!$modal.length) return;

        var percentage = total > 0 ? Math.round((current / total) * 100) : 0;
        var startTime = $modal.data('startTime') || Date.now();
        var elapsed = (Date.now() - startTime) / 1000; // seconds

        // Calculate ETA
        var eta = 'Calculating...';
        if (current > 0 && elapsed > 0) {
            var avgTimePerImage = elapsed / current;
            var remaining = total - current;
            var etaSeconds = remaining * avgTimePerImage;

            if (etaSeconds < 60) {
                eta = Math.ceil(etaSeconds) + 's';
            } else if (etaSeconds < 3600) {
                eta = Math.ceil(etaSeconds / 60) + 'm';
            } else {
                var hours = Math.floor(etaSeconds / 3600);
                var mins = Math.ceil((etaSeconds % 3600) / 60);
                eta = hours + 'h ' + mins + 'm';
            }
        }

        // Update stats
        $modal.find('.bbai-bulk-progress__current').text(current);
        $modal.find('.bbai-bulk-progress__percentage').text(percentage + '%');
        $modal.find('.bbai-bulk-progress__eta').text(eta);
        $modal.find('.bbai-bulk-progress__bar-fill').css('width', percentage + '%');

        // Add log entry if image title provided
        if (imageTitle) {
            var timestamp = new Date().toLocaleTimeString();
            var logEntry =
                '<div class="bbai-bulk-progress__log-entry">' +
                '    <span class="bbai-bulk-progress__log-time">' + timestamp + '</span>' +
                '    <span class="bbai-bulk-progress__log-icon">✓</span>' +
                '    <span class="bbai-bulk-progress__log-text">' + escapeHtml(imageTitle) + '</span>' +
                '</div>';

            var $log = $modal.find('.bbai-bulk-progress__log');
            $log.append(logEntry);

            // Auto-scroll to bottom
            $log.scrollTop($log[0].scrollHeight);
        }
    }

    /**
     * Add error log entry
     */
    function logBulkProgressError(errorMessage) {
        var $modal = $('#bbai-bulk-progress-modal');
        if (!$modal.length) return;

        var timestamp = new Date().toLocaleTimeString();
        var logEntry =
            '<div class="bbai-bulk-progress__log-entry bbai-bulk-progress__log-entry--error">' +
            '    <span class="bbai-bulk-progress__log-time">' + timestamp + '</span>' +
            '    <span class="bbai-bulk-progress__log-icon">✗</span>' +
            '    <span class="bbai-bulk-progress__log-text">' + escapeHtml(errorMessage || 'An error occurred') + '</span>' +
            '</div>';

        var $log = $modal.find('.bbai-bulk-progress__log');
        $log.append(logEntry);
        $log.scrollTop($log[0].scrollHeight);
    }

    /**
     * Add success log entry
     */
    function logBulkProgressSuccess(successMessage) {
        var $modal = $('#bbai-bulk-progress-modal');
        if (!$modal.length) return;

        var timestamp = new Date().toLocaleTimeString();
        var logEntry =
            '<div class="bbai-bulk-progress__log-entry bbai-bulk-progress__log-entry--success">' +
            '    <span class="bbai-bulk-progress__log-time">' + timestamp + '</span>' +
            '    <span class="bbai-bulk-progress__log-icon">✓</span>' +
            '    <span class="bbai-bulk-progress__log-text">' + escapeHtml(successMessage || 'Success') + '</span>' +
            '</div>';

        var $log = $modal.find('.bbai-bulk-progress__log');
        $log.append(logEntry);
        $log.scrollTop($log[0].scrollHeight);
    }

    /**
     * Update bulk progress modal title
     */
    function updateBulkProgressTitle(title) {
        var $modal = $('#bbai-bulk-progress-modal');
        if (!$modal.length) return;

        $modal.find('.bbai-bulk-progress__title').text(title);
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Hide bulk progress bar
     */
    function hideBulkProgress() {
        var $modal = $('#bbai-bulk-progress-modal');
        if ($modal.length) {
            $modal.removeClass('active');
            $('body').css('overflow', '');
        }
    }

    /**
     * Create and show success modal
     */
    function createSuccessModal() {
        var modalHtml =
            '<div id="bbai-modal-success" class="bbai-modal-success" role="dialog" aria-modal="true" aria-labelledby="bbai-modal-success-title">' +
            '    <div class="bbai-modal-success__overlay"></div>' +
            '    <div class="bbai-modal-success__content">' +
            '        <button type="button" class="bbai-modal-success__close" aria-label="' + escapeHtml('Close') + '">×</button>' +
            '        <div class="bbai-modal-success__header">' +
            '            <div class="bbai-modal-success__badge">' +
            '                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' +
            '                    <path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' +
            '                </svg>' +
            '            </div>' +
            '            <h2 id="bbai-modal-success-title" class="bbai-modal-success__title">Alt Text Generated Successfully</h2>' +
            '            <p class="bbai-modal-success__subtitle">Your images have been processed and are ready to review.</p>' +
            '        </div>' +
            '        <div class="bbai-modal-success__stats">' +
            '            <div class="bbai-modal-success__stat-card">' +
            '                <div class="bbai-modal-success__stat-number" data-stat="processed">0</div>' +
            '                <div class="bbai-modal-success__stat-label">Images Processed</div>' +
            '            </div>' +
            '            <div class="bbai-modal-success__stat-card">' +
            '                <div class="bbai-modal-success__stat-number" data-stat="time">0</div>' +
            '                <div class="bbai-modal-success__stat-label">Time Saved</div>' +
            '            </div>' +
            '            <div class="bbai-modal-success__stat-card">' +
            '                <div class="bbai-modal-success__stat-number" data-stat="confidence">0%</div>' +
            '                <div class="bbai-modal-success__stat-label">AI Confidence</div>' +
            '            </div>' +
            '        </div>' +
            '        <div class="bbai-modal-success__summary" data-summary-type="success">' +
            '            <div class="bbai-modal-success__summary-icon">✓</div>' +
            '            <div class="bbai-modal-success__summary-text">All images were processed successfully.</div>' +
            '        </div>' +
            '        <div class="bbai-modal-success__actions">' +
            '            <a href="' + escapeHtml((typeof ajaxurl !== 'undefined' ? ajaxurl.replace('/admin-ajax.php', '/admin.php') : '/wp-admin/admin.php') + '?page=bbai&tab=library') + '" class="bbai-modal-success__btn bbai-modal-success__btn--primary">View ALT Library →</a>' +
            '            <button type="button" class="bbai-modal-success__btn bbai-modal-success__btn--secondary" data-action="view-warnings" style="display: none;">View Warnings</button>' +
            '        </div>' +
            '    </div>' +
            '</div>';

        var $existing = $('#bbai-modal-success');
        if ($existing.length) {
            $existing.remove();
        }

        $('body').append(modalHtml);
        var $modal = $('#bbai-modal-success');

        // Close handlers
        $modal.find('.bbai-modal-success__close, .bbai-modal-success__overlay').on('click', function() {
            hideSuccessModal();
        });

        // View Warnings button handler
        $modal.find('[data-action="view-warnings"]').on('click', function() {
            hideSuccessModal();
            // Show the ALT Library tab with filters applied
            var libraryUrl = (typeof ajaxurl !== 'undefined' ? ajaxurl.replace('/admin-ajax.php', '/admin.php') : '/wp-admin/admin.php') + '?page=bbai&tab=library';
            window.location.href = libraryUrl;
        });

        // ESC key handler
        $(document).on('keydown.opptiai-success-modal', function(e) {
            if (e.keyCode === 27 && $modal.hasClass('active')) {
                hideSuccessModal();
            }
        });

        return $modal;
    }

    /**
     * Show success modal with stats
     */
    function showSuccessModal(data) {
        var $modal = $('#bbai-modal-success');
        if (!$modal.length) {
            $modal = createSuccessModal();
        }

        // Update stats
        $modal.find('[data-stat="processed"]').text(data.processed || 0);
        $modal.find('[data-stat="time"]').text(data.timeSaved || '0 hours');
        $modal.find('[data-stat="confidence"]').text((data.confidence || 0) + '%');

        // Update summary based on failures
        var $summary = $modal.find('.bbai-modal-success__summary');
        var $warningsBtn = $modal.find('[data-action="view-warnings"]');
        
        if (data.failures > 0) {
            $summary.attr('data-summary-type', 'warning');
            $summary.find('.bbai-modal-success__summary-icon').text('⚠');
            $summary.find('.bbai-modal-success__summary-text').text('Some images generated with warnings — review details below.');
            $warningsBtn.show();
        } else {
            $summary.attr('data-summary-type', 'success');
            $summary.find('.bbai-modal-success__summary-icon').text('✓');
            $summary.find('.bbai-modal-success__summary-text').text('All images were processed successfully.');
            $warningsBtn.hide();
        }

        // Show modal
        $modal.addClass('active');
        $('body').css('overflow', 'hidden');

        // Focus management
        setTimeout(function() {
            $modal.find('.bbai-modal-success__close').focus();
        }, 100);
    }

    /**
     * Hide success modal
     */
    function hideSuccessModal() {
        var $modal = $('#bbai-modal-success');
        if ($modal.length) {
            $modal.removeClass('active');
            $('body').css('overflow', '');
            $(document).off('keydown.opptiai-success-modal');
        }
    }

    /**
     * Show notification message
     */
    function showNotification(message, type) {
        type = type || 'info';
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap').first().prepend($notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $notice.remove();
            });
        }, 5000);
    }


    /**
     * License Management Functions
     */

    // Handle license activation form submission
    function handleLicenseActivation(e) {
        e.preventDefault();

        var $form = $('#license-activation-form');
        var $input = $('#license-key-input');
        var $button = $('#activate-license-btn');
        var $status = $('#license-activation-status');
        var nonce = $('#license-nonce').val();

        var licenseKey = $input.val().trim();

        if (!licenseKey) {
            showLicenseStatus('error', 'Please enter a license key');
            return;
        }

        // Disable form
        $button.prop('disabled', true).text('Activating...');
        $input.prop('disabled', true);

        // Make AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'beepbeepai_activate_license',
                nonce: nonce,
                license_key: licenseKey
            },
            success: function(response) {
                if (response.success) {
                    showLicenseStatus('success', response.data.message || 'License activated successfully!');

                    // Reload page after 1 second to show activated state
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showLicenseStatus('error', response.data.message || 'Failed to activate license');
                    $button.prop('disabled', false).text('Activate License');
                    $input.prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                showLicenseStatus('error', 'Network error: ' + error);
                $button.prop('disabled', false).text('Activate License');
                $input.prop('disabled', false);
            }
        });
    }

    // Handle license deactivation
    function handleLicenseDeactivation(e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to deactivate this license? You will need to reactivate it to continue using the shared quota.')) {
            return;
        }

        var $button = $(this);
        var nonce = $('#license-nonce').val();

        // Disable button
        $button.prop('disabled', true).text('Deactivating...');

        // Make AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'beepbeepai_deactivate_license',
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    alert(response.data.message || 'License deactivated successfully');

                    // Reload page to show deactivated state
                    window.location.reload();
                } else {
                    alert('Error: ' + (response.data.message || 'Failed to deactivate license'));
                    $button.prop('disabled', false).text('Deactivate License');
                }
            },
            error: function(xhr, status, error) {
                alert('Network error: ' + error);
                $button.prop('disabled', false).text('Deactivate License');
            }
        });
    }

    // Force license sync via REST endpoint
    function handleManualLicenseSync(e) {
        e.preventDefault();

        if (typeof window.fetch !== 'function') {
            alert('Your browser cannot perform automatic license sync. Please update your browser or contact support.');
            return;
        }

        if (!window.BBAI || !BBAI.restLicenseAttach) {
            alert('License sync service is unavailable. Please refresh and try again.');
            return;
        }

        var $button = $(this);
        var originalText = $button.text();
        $button.prop('disabled', true).text('Syncing...');

        var defaults = BBAI.autoAttachDefaults || {};
        var payload = {
            siteUrl: $button.data('siteUrl') || defaults.siteUrl || window.location.origin,
            siteHash: $button.data('siteHash') || defaults.siteHash || '',
            installId: $button.data('installId') || defaults.installId || defaults.siteHash || ''
        };

        fetch(BBAI.restLicenseAttach, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': BBAI.nonce
            },
            body: JSON.stringify(payload)
        })
        .then(function(response) {
            // Check for quota exceeded error
            if (response.error === 'quota_exceeded') {
                if (typeof showUpgradeModal === 'function') {
                    showUpgradeModal();
                } else if (typeof window.showUpgradeModal === 'function') {
                    window.showUpgradeModal();
                } else if (typeof alttextaiShowModal === 'function') {
                    alttextaiShowModal();
                }
                return;
            }
            
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(function(data) {
            if (!data || data.success !== true) {
                throw new Error((data && data.message) ? data.message : 'License sync failed. Please try again.');
            }
            showNotification(data.message || 'License synced successfully.', 'success');
            setTimeout(function() {
                window.location.reload();
            }, 1200);
        })
        .catch(function(error) {
            console.error('[AI Alt Text] License sync failed:', error);
            alert(error && error.message ? error.message : 'Unable to sync license. Please try again or contact support.');
        })
        .finally(function() {
            $button.prop('disabled', false).text(originalText);
        });
    }

    // Show status message in license activation form
    function showLicenseStatus(type, message) {
        var $status = $('#license-activation-status');
        var iconHtml = '';
        var bgColor = '';
        var textColor = '';

        if (type === 'success') {
            iconHtml = '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" style="vertical-align: middle; margin-right: 8px;"><circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="2" fill="none"/><path d="M6 10L9 13L14 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            bgColor = '#d1fae5';
            textColor = '#065f46';
        } else {
            iconHtml = '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" style="vertical-align: middle; margin-right: 8px;"><circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="2" fill="none"/><path d="M10 6v4M10 14h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
            bgColor = '#fee2e2';
            textColor = '#991b1b';
        }

        $status.html(
            '<div style="padding: 12px; background: ' + bgColor + '; color: ' + textColor + '; border-radius: 4px; font-size: 14px;">' +
            iconHtml + message +
            '</div>'
        ).show();
    }

    // Initialize on document ready
    $(document).ready(function() {
        console.log('[AI Alt Text] Admin JavaScript loaded');
        console.log('[AI Alt Text] Config:', config);
        console.log('[AI Alt Text] Has bulk config:', hasBulkConfig);
        console.log('[AI Alt Text] bbai_ajax:', window.bbai_ajax);

        // Count regenerate buttons
        var regenButtons = $('[data-action="regenerate-single"]');
        console.log('[AI Alt Text] Found ' + regenButtons.length + ' regenerate buttons');

        // Handle generate missing button
        $(document).on('click', '[data-action="generate-missing"]', handleGenerateMissing);

        // Handle regenerate all button
        $(document).on('click', '[data-action="regenerate-all"]', handleRegenerateAll);

        // Handle individual regenerate buttons
        $(document).on('click', '[data-action="regenerate-single"]', function(e) {
            console.log('[AI Alt Text] Regenerate button click event fired!');
            handleRegenerateSingle.call(this, e);
        });

        // License management handlers
        $('#license-activation-form').on('submit', handleLicenseActivation);
        $(document).on('click', '[data-action="deactivate-license"]', handleLicenseDeactivation);
        $(document).on('click', '[data-action="force-license-sync"]', handleManualLicenseSync);

        console.log('[AI Alt Text] License management handlers registered');
    });

})(jQuery);
