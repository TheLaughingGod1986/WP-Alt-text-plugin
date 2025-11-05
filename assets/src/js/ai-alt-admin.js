/**
 * AI Alt Text Admin JavaScript
 * Handles bulk generate, regenerate all, and individual regenerate buttons
 */

(function($) {
    'use strict';

    // Ensure AI_ALT_GPT_DASH exists (from dashboard) or use AI_ALT_GPT
    var config = window.AI_ALT_GPT_DASH || window.AI_ALT_GPT || {};
    
    if (!config.rest || !config.nonce) {
        console.error('[AI Alt Text] JavaScript configuration missing. REST URL or nonce not found.');
        return;
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

        $btn.prop('disabled', true);
        $btn.text('Loading...');

        // Get list of images missing alt text
        $.ajax({
            url: config.restMissing || (config.restRoot + 'ai-alt/v1/list?scope=missing'),
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
            
            // Show progress bar
            showBulkProgress('Preparing bulk run...', count, 0);

            // Queue all images
            queueImages(ids, 'bulk', function(success, queued, error) {
                $btn.prop('disabled', false);
                $btn.text(originalText);
                
                if (success && queued > 0) {
                    hideBulkProgress();
                    alert('Successfully queued ' + queued + ' image' + (queued !== 1 ? 's' : '') + ' for alt text generation. Processing will start shortly.');
                    
                    // Refresh page after a delay to show updated stats
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else if (success && queued === 0) {
                    hideBulkProgress();
                    alert('All images are already in queue or processing. Please check the queue status.');
                    // Refresh to show current queue state
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    hideBulkProgress();
                    
                    // Error logging (keep minimal for production)
                    if (error && error.code) {
                        console.error('[AI Alt Text] Error:', error.code, error.message || 'Unknown error');
                    }
                    
                    // Check for insufficient credits FIRST, before any other error handling
                    if (error && error.code === 'insufficient_credits' && error.remaining !== null && error.remaining > 0) {
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
                            
                            queueImages(limitedIds, 'bulk', function(success, queued, queueError) {
                                $btn.prop('disabled', false);
                                $btn.text(originalText);
                                
                                if (success && queued > 0) {
                                    hideBulkProgress();
                                    alert('Successfully queued ' + queued + ' image' + (queued !== 1 ? 's' : '') + ' for alt text generation. Processing will start shortly.');
                                    setTimeout(function() {
                                        window.location.reload();
                                    }, 2000);
                                } else {
                                    hideBulkProgress();
                                    var errorMsg = 'Failed to queue images.';
                                    if (queueError && queueError.message) {
                                        errorMsg = queueError.message;
                                    }
                                    alert(errorMsg);
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
                                } else if (typeof window.alttextai_ajax !== 'undefined' && window.alttextai_ajax.is_authenticated) {
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
                    alert(errorMsg);
                }
            });
        })
        .fail(function(xhr, status, error) {
            console.error('[AI Alt Text] Failed to get missing images:', error, xhr);
            $btn.prop('disabled', false);
            $btn.text(originalText);
            alert('Failed to load images. Please try again.');
            hideBulkProgress();
        });
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

        $btn.prop('disabled', true);
        $btn.text('Loading...');

        // Get list of all images
        $.ajax({
            url: config.restAll || (config.restRoot + 'ai-alt/v1/list?scope=all'),
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
                alert('No images found.');
                $btn.prop('disabled', false);
                $btn.text(originalText);
                return;
            }

            var ids = response.ids || [];
            var count = ids.length;
            
            // Show progress bar
            showBulkProgress('Preparing bulk regeneration...', count, 0);

            // Queue all images
            queueImages(ids, 'bulk-regenerate', function(success, queued, error) {
                $btn.prop('disabled', false);
                $btn.text(originalText);
                
                if (success && queued > 0) {
                    hideBulkProgress();
                    alert('Successfully queued ' + queued + ' image' + (queued !== 1 ? 's' : '') + ' for alt text regeneration. Processing will start shortly.');
                    
                    // Refresh page after a delay to show updated stats
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else if (success && queued === 0) {
                    hideBulkProgress();
                    alert('All images are already in queue or processing. Please check the queue status.');
                    // Refresh to show current queue state
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    hideBulkProgress();
                    
                    // Error logging (keep minimal for production)
                    if (error && error.code) {
                        console.error('[AI Alt Text] Error:', error.code, error.message || 'Unknown error');
                    }
                    
                    // Check for insufficient credits FIRST, before any other error handling
                    if (error && error.code === 'insufficient_credits' && error.remaining !== null && error.remaining > 0) {
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
                            
                            queueImages(limitedIds, 'bulk-regenerate', function(success, queued, queueError) {
                                $btn.prop('disabled', false);
                                $btn.text(originalText);
                                
                                if (success && queued > 0) {
                                    hideBulkProgress();
                                    alert('Successfully queued ' + queued + ' image' + (queued !== 1 ? 's' : '') + ' for alt text regeneration. Processing will start shortly.');
                                    setTimeout(function() {
                                        window.location.reload();
                                    }, 2000);
                                } else {
                                    hideBulkProgress();
                                    var errorMsg = 'Failed to queue images.';
                                    if (queueError && queueError.message) {
                                        errorMsg = queueError.message;
                                    }
                                    alert(errorMsg);
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
                                } else if (typeof window.alttextai_ajax !== 'undefined' && window.alttextai_ajax.is_authenticated) {
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
                    alert(errorMsg);
                }
            });
        })
        .fail(function(xhr, status, error) {
            console.error('[AI Alt Text] Failed to get all images:', error, xhr);
            $btn.prop('disabled', false);
            $btn.text(originalText);
            alert('Failed to load images. Please try again.');
            hideBulkProgress();
        });
    }

    /**
     * Regenerate alt text for a single image
     */
    function handleRegenerateSingle(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var attachmentId = $btn.data('attachment-id');
        
        if (!attachmentId || $btn.prop('disabled')) {
            return false;
        }

        var originalText = $btn.text();
        $btn.prop('disabled', true);
        $btn.text('Generating...');

        // Use AJAX endpoint for single regeneration
        var ajaxUrl = (window.alttextai_ajax && window.alttextai_ajax.ajaxurl) || 
                     (window.AI_ALT_GPT && window.AI_ALT_GPT.restRoot ? window.AI_ALT_GPT.restRoot.replace(/\/$/, '') + '/admin-ajax.php' : null) ||
                     (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
        var nonceValue = (window.alttextai_ajax && window.alttextai_ajax.nonce) || 
                       (window.AI_ALT_GPT && window.AI_ALT_GPT.nonce) ||
                       '';
        
        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'alttextai_regenerate_single',
                attachment_id: attachmentId,
                nonce: nonceValue
            }
        })
        .done(function(response) {
            console.log('[AI Alt Text] Regenerate response:', response);
            $btn.prop('disabled', false);
            $btn.text(originalText);
            
            if (response && response.success) {
                // Update the alt text in the table if it exists
                var $row = $btn.closest('tr');
                var attachmentId = $btn.data('attachment-id');
                var newAltText = response.data && response.data.alt_text ? response.data.alt_text : '';
                
                if (newAltText) {
                    // Find the new alt text cell (has class alttextai-table__cell--new)
                    var $newAltCell = $row.find('.alttextai-table__cell--new');
                    
                    if ($newAltCell.length) {
                        // Check if there's a copy button (existing alt text) or just a span (no alt text)
                        var $copyButton = $newAltCell.find('.alttextai-copy-trigger');
                        var $copyText = $copyButton.find('.alttextai-copy-text');
                        
                        if ($copyButton.length && $copyText.length) {
                            // Update existing alt text in copy button
                            $copyText.text(newAltText);
                            $copyButton.attr('data-copy-alt', newAltText);
                        } else {
                            // No existing alt text - create the copy button structure
                            $newAltCell.html(
                                '<button type="button" class="alttextai-copy-trigger" data-copy-alt="' + 
                                $('<div>').text(newAltText).html() + 
                                '" aria-label="Copy alt text to clipboard">' +
                                '<span class="alttextai-copy-text">' + $('<div>').text(newAltText).html() + '</span>' +
                                '<span class="alttextai-copy-icon" aria-hidden="true">📋</span>' +
                                '</button>'
                            );
                        }
                        
                        // Update status badge to "Optimized"
                        var $statusCell = $row.find('.alttextai-table__cell--status');
                        if ($statusCell.length) {
                            $statusCell.html(
                                '<span class="alttextai-status-badge alttextai-status-badge--optimized">✅ Optimized</span>'
                            );
                        }
                        
                        // Update row data-status attribute
                        $row.attr('data-status', 'optimized');
                    }
                }
                
                // Show success message
                showNotification('Alt text regenerated successfully!', 'success');
                
                // Refresh usage stats if available
                if (typeof refreshUsageStats === 'function') {
                    refreshUsageStats();
                }
            } else {
                // Check for limit_reached error
                var errorData = response && response.data ? response.data : {};
                if (errorData.code === 'limit_reached') {
                    // Show upgrade modal instead of error notification
                    if (typeof showUpgradeModal === 'function') {
                        showUpgradeModal(errorData.usage);
                    } else if (typeof window.alttextai_show_upgrade_modal === 'function') {
                        window.alttextai_show_upgrade_modal(errorData.usage);
                    } else {
                        // Fallback: try to trigger via event
                        $(document).trigger('alttextai:show-upgrade-modal', [errorData.usage]);
                        showNotification(errorData.message || 'Monthly limit reached. Please upgrade to continue.', 'error');
                    }
                } else {
                    var message = errorData.message || 'Failed to regenerate alt text';
                    showNotification(message, 'error');
                }
            }
        })
        .fail(function(xhr, status, error) {
            console.error('[AI Alt Text] Failed to regenerate:', error, xhr);
            $btn.prop('disabled', false);
            $btn.text(originalText);
            
            // Check for limit_reached error in response
            var errorData = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
            if (errorData.code === 'limit_reached') {
                // Show upgrade modal instead of error notification
                if (typeof showUpgradeModal === 'function') {
                    showUpgradeModal(errorData.usage);
                } else if (typeof window.alttextai_show_upgrade_modal === 'function') {
                    window.alttextai_show_upgrade_modal(errorData.usage);
                } else {
                    // Fallback: try to trigger via event
                    $(document).trigger('alttextai:show-upgrade-modal', [errorData.usage]);
                    showNotification(errorData.message || 'Monthly limit reached. Please upgrade to continue.', 'error');
                }
            } else {
                var message = errorData.message || 'Failed to regenerate alt text';
                showNotification(message, 'error');
            }
        });
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
        var ajaxUrl = (window.alttextai_ajax && window.alttextai_ajax.ajax_url) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
        var nonceValue = (window.alttextai_ajax && window.alttextai_ajax.nonce) || config.nonce;
        
        // Queueing images (debug info removed for production)
        
        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'alttextai_bulk_queue',
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
                    callback(true, queued, null);
                } else {
                    console.warn('[AI Alt Text] No images were queued. Response:', response);
                    // Still might be success if 0 queued but they were already in queue
                    callback(true, queued, null);
                }
            } else {
                // Error response from server
                var errorMessage = 'Failed to queue images';
                var errorCode = null;
                var errorRemaining = null;
                
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
                    }
                }
                
                console.error('[AI Alt Text] Queue failed:', errorMessage, errorCode ? '(Code: ' + errorCode + ')' : '');
                
                // Pass error message to callback
                callback(false, 0, {
                    message: errorMessage,
                    code: errorCode,
                    remaining: errorRemaining
                });
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
                callback(success, queued, null);
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
                    url: config.rest || (config.restRoot + 'ai-alt/v1/generate/' + id),
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
     * Show bulk progress bar
     */
    function showBulkProgress(label, total, current) {
        var $progress = $('[data-bulk-progress]');
        if ($progress.length) {
            $progress.removeAttr('hidden');
            $('[data-bulk-progress-label]').text(label || 'Processing...');
        }
        updateBulkProgress(current, total);
    }

    /**
     * Update bulk progress bar
     */
    function updateBulkProgress(current, total) {
        var $progress = $('[data-bulk-progress]');
        var $bar = $('[data-bulk-progress-bar]');
        var $counts = $('[data-bulk-progress-counts]');
        
        if ($progress.length && $bar.length) {
            var percentage = total > 0 ? Math.round((current / total) * 100) : 0;
            $bar.css('width', percentage + '%');
        }
        
        if ($counts.length) {
            $counts.text(current + ' / ' + total);
        }
    }

    /**
     * Hide bulk progress bar
     */
    function hideBulkProgress() {
        var $progress = $('[data-bulk-progress]');
        if ($progress.length) {
            $progress.attr('hidden', 'hidden');
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

    // Initialize on document ready
    $(document).ready(function() {
        // AI Alt Text Admin JavaScript loaded
        
        // Handle generate missing button
        $(document).on('click', '[data-action="generate-missing"]', handleGenerateMissing);
        
        // Handle regenerate all button
        $(document).on('click', '[data-action="regenerate-all"]', handleRegenerateAll);
        
        // Handle individual regenerate buttons
        $(document).on('click', '[data-action="regenerate-single"]', handleRegenerateSingle);
    });

})(jQuery);

