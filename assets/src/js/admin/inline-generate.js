/**
 * Inline Generation
 * Handles inline alt text generation for queued images
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

(function($) {
    'use strict';

    /**
     * Begin inline generation after queue completes
     */
    window.startInlineGeneration = function(idList, source) {
        if (!idList || !idList.length || !window.bbaiHasBulkConfig) {
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
    };

    /**
     * Process images sequentially in batches and update modal progress
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

    /**
     * Finalize inline generation and show success modal
     */
    function finalizeInlineGeneration(successes, failures) {
        var $modal = $('#bbai-bulk-progress-modal');
        var total = successes + failures;
        var startTime = $modal.length ? $modal.data('startTime') : Date.now();
        var elapsed = (Date.now() - startTime) / 1000;

        var timeSavedMinutes = successes * 2;
        var timeSavedHours = Math.round(timeSavedMinutes / 60);
        var timeSavedText = timeSavedHours > 0 ? timeSavedHours + ' hour' + (timeSavedHours !== 1 ? 's' : '') : '< 1 hour';

        var confidence = total > 0 ? Math.round((successes / total) * 100) : 100;

        hideBulkProgress();

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

    /**
     * Generate alt text for a single image ID
     */
    function generateAltTextForId(id) {
        return new Promise(function(resolve, reject) {
            var ajaxUrl = (window.bbai_ajax && (window.bbai_ajax.ajax_url || window.bbai_ajax.ajaxurl)) || '';
            var nonceValue = (window.bbai_ajax && window.bbai_ajax.nonce) || '';
            if (!ajaxUrl) {
                reject({ message: 'AJAX endpoint unavailable.', code: 'ajax_unavailable' });
                return;
            }

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
                try {
                    if (response && response.success) {
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
                        else if (response.data && response.data.alt_text) {
                            resolve({
                                id: id,
                                alt: response.data.alt_text || '',
                                title: 'Image #' + id
                            });
                            return;
                        }
                        else if (response.data && response.data.message) {
                            reject({ message: response.data.message });
                            return;
                        }
                    }

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

                    console.error('[AI Alt Text] Unexpected response structure:', response);
                    reject({ message: 'Unexpected response from server.' });
                } catch (e) {
                    console.error('[AI Alt Text] Error parsing response:', e, response);
                    reject({ message: 'Error parsing server response: ' + (e.message || 'Unknown error') });
                }
            })
            .fail(function(xhr) {
                var message = 'Request failed';
                var errorCode = null;

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
                    message = 'Network error: Unable to connect to server.';
                } else if (xhr && xhr.status === 404) {
                    message = 'AJAX endpoint not found.';
                } else if (xhr && xhr.status === 500) {
                    message = 'Server error occurred.';
                } else if (xhr && xhr.status) {
                    message = 'Request failed with status ' + xhr.status;
                }

                console.error('[AI Alt Text] Inline generate request failed:', {
                    status: xhr ? xhr.status : 'unknown',
                    message: message
                });

                reject({ message: message, code: errorCode });
            });
        });
    }

})(jQuery);
