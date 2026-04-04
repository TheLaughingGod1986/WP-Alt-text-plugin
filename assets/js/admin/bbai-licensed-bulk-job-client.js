/**
 * Licensed bulk ALT generation: one POST /api/jobs + poll GET /api/jobs/:id (via WordPress admin-ajax).
 * Trial/anonymous flows stay on sequential inline_generate in bbai-admin.js.
 */
(function (window, $) {
    'use strict';

    var STORAGE_KEY = 'bbai_active_bulk_job';

    function isEligible() {
        return !!(window.bbai_ajax && window.bbai_ajax.use_licensed_bulk_jobs);
    }

    function sourceUsesRegenerate(source) {
        var s = String(source || '');
        return s.indexOf('regenerate') !== -1 || s === 'bulk-regenerate';
    }

    function stopPolling($modal) {
        if (!$modal || !$modal.length) {
            return;
        }
        var t = $modal.data('bbaiBulkJobPollTimer');
        if (t) {
            clearTimeout(t);
        }
        $modal.removeData('bbaiBulkJobPollTimer');
    }

    function isTerminalStatus(status) {
        var s = String(status || '').toLowerCase();
        return (
            s === 'complete' ||
            s === 'completed' ||
            s === 'failed' ||
            s === 'error' ||
            s === 'cancelled' ||
            s === 'canceled'
        );
    }

    function getItemAttachmentId(item) {
        if (!item || typeof item !== 'object') {
            return 0;
        }
        var keys = ['attachment_id', 'attachmentId', 'image_id', 'imageId', 'id'];
        var i;
        for (i = 0; i < keys.length; i++) {
            var k = keys[i];
            if (item[k] === undefined || item[k] === null) {
                continue;
            }
            var n = parseInt(item[k], 10);
            if (!isNaN(n) && n > 0) {
                return n;
            }
        }
        return 0;
    }

    function getItemPhase(item) {
        if (!item || typeof item !== 'object') {
            return '';
        }
        var raw = item.stage || item.status || item.state || '';
        return String(raw || '').toLowerCase();
    }

    function getItemErrorMessage(item) {
        if (!item || typeof item !== 'object') {
            return '';
        }
        var e = item.errors;
        if (typeof e === 'string' && e) {
            return e;
        }
        if (Array.isArray(e) && e.length) {
            return e
                .map(function (x) {
                    return typeof x === 'string' ? x : (x && x.message) || '';
                })
                .filter(Boolean)
                .join('; ');
        }
        if (e && typeof e === 'object' && e.message) {
            return String(e.message);
        }
        if (item.errorMessage) {
            return String(item.errorMessage);
        }
        if (item.error) {
            return typeof item.error === 'string' ? item.error : String(item.error.message || '');
        }
        return '';
    }

    function extractAltFromItem(item) {
        if (!item || typeof item !== 'object') {
            return '';
        }
        var keys = ['altText', 'alt_text', 'alt'];
        var i;
        for (i = 0; i < keys.length; i++) {
            var v = item[keys[i]];
            if (typeof v === 'string' && v.trim() !== '') {
                return v.trim();
            }
        }
        return '';
    }

    function resolveBackendPercent(job) {
        if (!job || typeof job !== 'object') {
            return null;
        }
        var p = job.percentComplete;
        if (typeof p === 'number' && !isNaN(p)) {
            return p;
        }
        if (typeof p === 'string' && p !== '' && !isNaN(parseFloat(p))) {
            return parseFloat(p);
        }
        var pr = job.progress;
        if (typeof pr === 'number' && !isNaN(pr)) {
            if (pr >= 0 && pr <= 1) {
                return pr * 100;
            }
            if (pr > 1 && pr <= 100) {
                return pr;
            }
        }
        return null;
    }

    /**
     * Map items[] to library row classes (queued / preparing / generating / completed / failed).
     * @param {Array} items
     * @param {{ onRowMapped?: function(number, string, Object): void }} hooks
     * @return {{ completed: number, failed: number }}
     */
    function mapJobItemsToLibraryRows(items, hooks) {
        var completed = 0;
        var failed = 0;
        var i;
        var list = Array.isArray(items) ? items : [];
        for (i = 0; i < list.length; i++) {
            var it = list[i];
            var aid = getItemAttachmentId(it);
            if (!aid) {
                continue;
            }
            var ph = getItemPhase(it);
            applyRowPhase(aid, ph, it);
            if (hooks && typeof hooks.onRowMapped === 'function') {
                hooks.onRowMapped(aid, ph, it);
            }
            if (ph === 'completed' || ph === 'complete' || ph === 'success' || ph === 'succeeded') {
                completed++;
            } else if (ph === 'failed' || ph === 'error') {
                failed++;
            }
        }
        return { completed: completed, failed: failed };
    }

    function applyRowPhase(attachmentId, phase, item) {
        var rowEl = document.querySelector('.bbai-library-row[data-attachment-id="' + attachmentId + '"]');
        if (!rowEl) {
            return;
        }
        rowEl.removeAttribute('title');
        rowEl.classList.remove(
            'bbai-library-row--bulk-queued',
            'bbai-library-row--processing',
            'bbai-library-row--bulk-failed'
        );
        if (phase === 'failed' || phase === 'error') {
            var errMsg = item ? getItemErrorMessage(item) : '';
            if (errMsg) {
                rowEl.setAttribute('title', errMsg);
            }
            rowEl.classList.add('bbai-library-row--bulk-failed');
            window.setTimeout(function () {
                if (rowEl) {
                    rowEl.classList.remove('bbai-library-row--bulk-failed');
                }
            }, 8000);
            return;
        }
        if (phase === 'completed' || phase === 'complete' || phase === 'success' || phase === 'succeeded') {
            if (typeof window.bbaiSetRowDone === 'function') {
                window.bbaiSetRowDone(rowEl);
            }
            return;
        }
        if (phase === 'queued' || phase === 'pending' || phase === '') {
            rowEl.classList.add('bbai-library-row--bulk-queued');
            return;
        }
        if (phase === 'preparing' || phase === 'generating' || phase === 'processing' || phase === 'running') {
            if (typeof window.bbaiSetRowProcessing === 'function') {
                window.bbaiSetRowProcessing(rowEl);
            } else {
                rowEl.classList.add('bbai-library-row--processing');
            }
        }
    }

    function persistActiveJob(jobId, total) {
        try {
            window.sessionStorage.setItem(
                STORAGE_KEY,
                JSON.stringify({ jobId: jobId, startedAt: Date.now(), total: total })
            );
        } catch (e) {}
    }

    function clearActiveJob() {
        try {
            window.sessionStorage.removeItem(STORAGE_KEY);
        } catch (e2) {}
    }

    function getAjaxConfig() {
        var ajaxUrl = (window.bbai_ajax && (window.bbai_ajax.ajax_url || window.bbai_ajax.ajaxurl)) || '';
        var nonce = (window.bbai_ajax && window.bbai_ajax.nonce) || '';
        return { ajaxUrl: ajaxUrl, nonce: nonce };
    }

    /**
     * @param {Object} options
     * @param {jQuery} options.$modal
     * @param {number[]} options.attachmentIds
     * @param {string} options.source
     * @param {function(string): void} [options.onLogSuccess]
     * @param {function(string): void} [options.onLogError]
     * @param {function(string): void} [options.onTitle]
     * @param {function(): void} [options.armStallHint]
     * @param {function(): void} [options.clearStallHint]
     * @param {function(Object): void} [options.onPollStarted]
     * @param {function(Object): void} [options.onFirstItemCompleted]
     * @param {function(Object): void} [options.onProgressSeen]
     * @param {function(number[]): void} options.onNotEligibleFallback — sequential trial-safe path
     * @param {function(): void} options.onFatalNoAjax
     * @param {function(Object): void} options.syncModalProgress — receives tick payload
     * @param {function(Array): void} options.applyUpdatedImages
     * @param {function(number, string): void} [options.syncRowFromItemAlt] — first-result UX from items[].altText
     * @param {function(): void} [options.maybeMinimizeLongRun]
     * @param {function(number, number, number): void} options.finalize — successes, failures, skipped
     */
    function run(options) {
        var $modal = options.$modal;
        var normalizedIds = options.attachmentIds || [];
        var source = options.source || 'bulk';
        var total = normalizedIds.length;
        var regenerate = sourceUsesRegenerate(source);
        var cfg = getAjaxConfig();

        var onLogSuccess = options.onLogSuccess || function () {};
        var onLogError = options.onLogError || function () {};
        var onTitle = options.onTitle || function () {};
        var armStall = options.armStallHint || function () {};
        var clearStall = options.clearStallHint || function () {};
        var onPollStarted = options.onPollStarted || function () {};
        var onFirstItemCompleted = options.onFirstItemCompleted || function () {};
        var onProgressSeen = options.onProgressSeen || function () {};
        var onNotEligibleFallback = options.onNotEligibleFallback;
        var onFatalNoAjax = options.onFatalNoAjax;
        var syncModalProgress = options.syncModalProgress;
        var applyUpdatedImages = options.applyUpdatedImages || function () {};
        var syncRowFromItemAlt = options.syncRowFromItemAlt;
        var maybeMinimizeLongRun = options.maybeMinimizeLongRun || function () {};
        var finalize = options.finalize;

        if (!$modal || !$modal.length || !total) {
            return;
        }

        stopPolling($modal);
        $modal.removeData('bbaiBulkJobPollCount');

        if (!cfg.ajaxUrl) {
            if (typeof onFatalNoAjax === 'function') {
                onFatalNoAjax();
            }
            return;
        }

        var pollStartedTracked = false;
        var firstItemCompletedTracked = false;
        var progressSeenTracked = false;
        var longRunMinimizeTracked = false;
        var preFailed = 0;

        armStall();

        onTitle(options.strings && options.strings.startingGeneration ? options.strings.startingGeneration : 'Starting generation…');
        onLogSuccess(options.strings && options.strings.sendingBatch ? options.strings.sendingBatch : 'Sending batch to the server…');

        $.ajax({
            url: cfg.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            timeout: 180000,
            data: {
                action: 'beepbeepai_bulk_job_start',
                attachment_ids: JSON.stringify(normalizedIds),
                regenerate: regenerate ? 1 : 0,
                generation_source: String(source),
                nonce: cfg.nonce
            }
        })
            .done(function (response) {
                clearStall();

                if (!response || !response.success || !response.data) {
                    var errCode = response && response.data && response.data.code ? String(response.data.code) : '';
                    if (errCode === 'bbai_jobs_not_eligible' && typeof onNotEligibleFallback === 'function') {
                        onNotEligibleFallback(normalizedIds);
                        return;
                    }
                    var msg =
                        response && response.data && response.data.message
                            ? String(response.data.message)
                            : options.strings && options.strings.couldNotStart
                              ? options.strings.couldNotStart
                              : 'Could not start bulk generation.';
                    onLogError(msg);
                    finalize(0, 0, 0);
                    return;
                }

                var d = response.data;
                var pre = d.preflight_errors || [];
                preFailed = 0;
                if (pre.length && options.onPreflightError) {
                    pre.forEach(function (pe) {
                        var pid = pe && pe.attachment_id !== undefined ? parseInt(pe.attachment_id, 10) : 0;
                        if (pid > 0) {
                            preFailed++;
                            options.onPreflightError(pid, pe);
                        }
                    });
                } else if (pre.length) {
                    pre.forEach(function (pe) {
                        var pid = pe && pe.attachment_id !== undefined ? parseInt(pe.attachment_id, 10) : 0;
                        if (pid > 0) {
                            preFailed++;
                            applyRowPhase(pid, 'failed', { errorMessage: pe.message || pe.code || '' });
                            onLogError(
                                (options.formatImageError || function (id, m) {
                                    return 'Image #' + id + ': ' + m;
                                })(pid, pe.message || pe.code || '')
                            );
                        }
                    });
                }

                var jobId = d.job_id ? String(d.job_id) : '';
                if (!jobId) {
                    onLogError(options.strings && options.strings.noJobId ? options.strings.noJobId : 'No job id returned.');
                    finalize(0, 0, 0);
                    return;
                }

                persistActiveJob(jobId, total);

                if (!pollStartedTracked) {
                    pollStartedTracked = true;
                    onPollStarted({ jobId: jobId, requested_count: total, source: source });
                }

                var jobState = { successes: 0, failures: preFailed, lastItems: [] };

                function scheduleNextPoll(delayMs) {
                    var timerId = window.setTimeout(runPoll, delayMs);
                    $modal.data('bbaiBulkJobPollTimer', timerId);
                }

                function runPoll() {
                    var pollN = parseInt($modal.data('bbaiBulkJobPollCount') || '0', 10) || 0;
                    $modal.data('bbaiBulkJobPollCount', pollN + 1);
                    var delayMs = pollN < 5 ? 850 : 2000;

                    $.ajax({
                        url: cfg.ajaxUrl,
                        method: 'POST',
                        dataType: 'json',
                        timeout: 60000,
                        data: {
                            action: 'beepbeepai_bulk_job_poll',
                            job_id: jobId,
                            nonce: cfg.nonce
                        }
                    })
                        .done(function (pollRes) {
                            if (!pollRes || !pollRes.success || !pollRes.data) {
                                scheduleNextPoll(delayMs);
                                return;
                            }

                            var pdata = pollRes.data;
                            var job = pdata.job || {};
                            var items = job.items;
                            if (!items || !items.length) {
                                items = job.results || [];
                            }
                            if (!Array.isArray(items)) {
                                items = [];
                            }
                            jobState.lastItems = items;

                            applyUpdatedImages(pdata.updated_images || []);

                            if (typeof syncRowFromItemAlt === 'function') {
                                var ix;
                                for (ix = 0; ix < items.length; ix++) {
                                    var rowItem = items[ix];
                                    var ph0 = getItemPhase(rowItem);
                                    if (
                                        ph0 === 'completed' ||
                                        ph0 === 'complete' ||
                                        ph0 === 'success' ||
                                        ph0 === 'succeeded'
                                    ) {
                                        var alt0 = extractAltFromItem(rowItem);
                                        var id0 = getItemAttachmentId(rowItem);
                                        if (id0 && alt0) {
                                            syncRowFromItemAlt(id0, alt0);
                                        }
                                    }
                                }
                            }

                            var counts = mapJobItemsToLibraryRows(items, null);
                            jobState.successes = counts.completed;
                            jobState.failures = preFailed + counts.failed;

                            var terminal = isTerminalStatus(job.status);
                            var finishedSlots = Math.min(total, jobState.successes + jobState.failures);

                            if (!progressSeenTracked && finishedSlots > 0) {
                                progressSeenTracked = true;
                                onProgressSeen();
                            }

                            if (!firstItemCompletedTracked && pdata.updated_images && pdata.updated_images.length) {
                                firstItemCompletedTracked = true;
                                onFirstItemCompleted({ jobId: jobId });
                            } else if (!firstItemCompletedTracked && typeof syncRowFromItemAlt === 'function') {
                                var j;
                                for (j = 0; j < items.length; j++) {
                                    var itj = items[j];
                                    var phj = getItemPhase(itj);
                                    if (
                                        (phj === 'completed' || phj === 'complete' || phj === 'success') &&
                                        extractAltFromItem(itj)
                                    ) {
                                        firstItemCompletedTracked = true;
                                        onFirstItemCompleted({ jobId: jobId });
                                        break;
                                    }
                                }
                            }

                            var backendPct = resolveBackendPercent(job);

                            if (typeof syncModalProgress === 'function') {
                                syncModalProgress({
                                    $modal: $modal,
                                    job: job,
                                    items: items,
                                    jobState: jobState,
                                    total: total,
                                    preFailed: preFailed,
                                    finishedSlots: finishedSlots,
                                    terminal: terminal,
                                    backendPercent: backendPct,
                                    source: source
                                });
                            }

                            if (!longRunMinimizeTracked && $modal.hasClass('active')) {
                                var started = $modal.data('startTime') || Date.now();
                                if (Date.now() - started > 90000) {
                                    longRunMinimizeTracked = true;
                                    maybeMinimizeLongRun();
                                }
                            }

                            if (terminal) {
                                stopPolling($modal);
                                clearActiveJob();
                                finalize(jobState.successes, jobState.failures, 0);
                                return;
                            }

                            scheduleNextPoll(delayMs);
                        })
                        .fail(function () {
                            scheduleNextPoll(delayMs);
                        });
                }

                onTitle(
                    options.strings && options.strings.jobStartedProcessing
                        ? options.strings.jobStartedProcessing
                        : 'Generation job started — processing images…'
                );
                onLogSuccess(
                    options.strings && options.strings.batchAccepted ? options.strings.batchAccepted : 'Batch accepted. Processing on the server…'
                );
                runPoll();
            })
            .fail(function (xhr) {
                clearStall();
                var failMsg =
                    options.strings && options.strings.couldNotStart ? options.strings.couldNotStart : 'Could not start bulk generation.';
                if (xhr && xhr.statusText === 'timeout') {
                    failMsg =
                        options.strings && options.strings.startTimeout
                            ? options.strings.startTimeout
                            : 'Starting the batch timed out. Please try again with fewer images or check your connection.';
                }
                onLogError(failMsg);
                finalize(0, 0, 0);
            });
    }

    window.bbaiLicensedBulkJobClient = {
        STORAGE_KEY: STORAGE_KEY,
        isEligible: isEligible,
        sourceUsesRegenerate: sourceUsesRegenerate,
        stopPolling: stopPolling,
        isTerminalStatus: isTerminalStatus,
        getItemAttachmentId: getItemAttachmentId,
        getItemPhase: getItemPhase,
        getItemErrorMessage: getItemErrorMessage,
        extractAltFromItem: extractAltFromItem,
        resolveBackendPercent: resolveBackendPercent,
        applyRowPhase: applyRowPhase,
        mapJobItemsToLibraryRows: mapJobItemsToLibraryRows,
        persistActiveJob: persistActiveJob,
        clearActiveJob: clearActiveJob,
        getAjaxConfig: getAjaxConfig,
        run: run
    };
})(window, jQuery);
