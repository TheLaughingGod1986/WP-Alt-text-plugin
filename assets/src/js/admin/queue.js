/**
 * Queue Management
 * Handles image queue operations for bulk processing
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

(function($) {
    'use strict';

    var config = window.bbaiAdminConfig || {};

    /**
     * Queue multiple images for processing
     * Uses AJAX endpoint to queue images without generating immediately
     */
    window.bbaiQueueImages = function(ids, source, callback) {
        if (!ids || ids.length === 0) {
            callback(false, 0);
            return;
        }

        var ajaxUrl = '/wp-admin/admin-ajax.php';
        if (window.bbai_ajax) {
            ajaxUrl = window.bbai_ajax.ajax_url || window.bbai_ajax.ajaxurl || ajaxUrl;
        } else if (typeof ajaxurl !== 'undefined') {
            ajaxUrl = ajaxurl;
        }
        var nonceValue = (window.bbai_ajax && window.bbai_ajax.nonce) || config.nonce;

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
                var responseData = response.data || {};
                var queued = responseData.queued || 0;

                if (queued > 0) {
                    callback(true, queued, null, ids.slice(0));
                } else {
                    console.warn('[AI Alt Text] No images were queued. Response:', response);
                    callback(true, queued, null, ids.slice(0));
                }
            } else {
                var errorMessage = 'Failed to queue images';
                var errorCode = null;
                var errorRemaining = null;

                if (response && response.data) {
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

            if (xhr.status === 403) {
                console.error('[AI Alt Text] Authentication error - check nonce');
                callback(false, 0, errorData || { message: 'Authentication error. Please refresh the page and try again.' });
                return;
            }

            if (errorData && errorData.message) {
                callback(false, 0, errorData);
                return;
            }

            // Fallback: queue images individually via REST API
            bbaiQueueImagesFallback(ids, source, function(success, queued) {
                callback(success, queued, null, ids.slice(0));
            });
        });
    };

    /**
     * Fallback: Queue images one by one via REST API
     */
    function bbaiQueueImagesFallback(ids, source, callback) {
        var total = ids.length;
        var queued = 0;
        var failed = 0;
        var batchSize = 5;
        var processed = 0;

        function processBatch(startIndex) {
            var endIndex = Math.min(startIndex + batchSize, total);
            var batch = ids.slice(startIndex, endIndex);

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
                    if (typeof updateBulkProgress === 'function') {
                        updateBulkProgress(processed, total);
                    }
                })
                .fail(function() {
                    failed++;
                    processed++;
                    if (typeof updateBulkProgress === 'function') {
                        updateBulkProgress(processed, total);
                    }
                });
            });

            $.when.apply($, promises)
            .then(function() {
                if (endIndex < total) {
                    setTimeout(function() {
                        processBatch(endIndex);
                    }, 500);
                } else {
                    var success = queued > 0;
                    callback(success, queued, null);
                }
            })
            .fail(function() {
                console.error('[AI Alt Text] Fallback batch failed');
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

        processBatch(0);
    }

    // Alias for backward compatibility
    window.queueImages = window.bbaiQueueImages;

})(jQuery);
