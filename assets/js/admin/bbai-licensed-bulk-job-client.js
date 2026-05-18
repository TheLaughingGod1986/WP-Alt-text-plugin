/**
 * Licensed bulk ALT generation: one POST /api/jobs + poll GET /api/jobs/:id (via WordPress admin-ajax).
 * Trial/anonymous flows stay on sequential inline_generate in bbai-admin.js.
 */
(function (window, $) {
    'use strict';

    var STORAGE_KEY = 'bbai_active_bulk_job';
    var POLL_LOCK_KEY = 'bbai_active_bulk_job_poll_lock';
    var POLL_LOCK_TTL = 10000;
    var TAB_ID = 'tab_' + Date.now() + '_' + Math.random().toString(36).slice(2);
    var recoveryStarted = false;

    function isEligible() {
        return !!(window.bbai_ajax && window.bbai_ajax.use_licensed_bulk_jobs);
    }

    function sourceUsesRegenerate(source) {
        var s = String(source || '');
        return s.indexOf('regenerate') !== -1 || s === 'bulk-regenerate' || s === 'fix-all-issues';
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

    function persistActiveJob(jobId, total, extra) {
        var now = Date.now();
        var payload = Object.assign({
            jobId: jobId,
            job_id: jobId,
            startedAt: now,
            started_at: now,
            updatedAt: now,
            last_checked_at: now,
            total: total,
            requested_count: total,
            progress: 0,
            completed_count: 0,
            successes: 0,
            failures: 0,
            failed_count: 0,
            status: 'processing',
            source: 'licensed_bulk_job',
            source_page: (window.bbaiAnalytics && window.bbaiAnalytics.getCurrentScreen && window.bbaiAnalytics.getCurrentScreen()) || window.location.pathname,
            resumedTracked: false
        }, extra || {});
        try {
            window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
        } catch (e) {}
    }

    function readActiveJob() {
        var raw;
        try {
            raw = window.localStorage.getItem(STORAGE_KEY) || window.sessionStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (e) {
            return null;
        }
    }

    function writeActiveJob(job) {
        try {
            var now = Date.now();
            var payload = Object.assign({}, job || {}, {
                job_id: (job && (job.job_id || job.jobId)) || '',
                started_at: (job && (job.started_at || job.startedAt)) || now,
                updatedAt: now,
                last_checked_at: now,
                requested_count: job && (job.requested_count || job.total) || 0,
                completed_count: job && (job.completed_count || job.successes || job.progress) || 0,
                failed_count: job && (job.failed_count || job.failures) || 0,
                source_page: job && job.source_page || ((window.bbaiAnalytics && window.bbaiAnalytics.getCurrentScreen && window.bbaiAnalytics.getCurrentScreen()) || window.location.pathname)
            });
            window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
        } catch (e) {}
    }

    function clearActiveJob() {
        try {
            window.sessionStorage.removeItem(STORAGE_KEY);
            window.localStorage.removeItem(STORAGE_KEY);
            window.localStorage.removeItem(POLL_LOCK_KEY);
        } catch (e2) {}
    }

    function claimPollLeadership() {
        var now = Date.now();
        var lock;

        try {
            lock = JSON.parse(window.localStorage.getItem(POLL_LOCK_KEY) || 'null');
        } catch (e) {
            lock = null;
        }

        if (
            lock &&
            lock.owner &&
            lock.owner !== TAB_ID &&
            parseInt(lock.expiresAt || '0', 10) > now
        ) {
            return false;
        }

        try {
            window.localStorage.setItem(POLL_LOCK_KEY, JSON.stringify({
                owner: TAB_ID,
                expiresAt: now + POLL_LOCK_TTL,
                updatedAt: now
            }));
        } catch (e2) {}

        return true;
    }

    function getAjaxConfig() {
        var ajaxUrl = (window.bbai_ajax && (window.bbai_ajax.ajax_url || window.bbai_ajax.ajaxurl)) || '';
        var nonce = (window.bbai_ajax && window.bbai_ajax.nonce) || '';
        return { ajaxUrl: ajaxUrl, nonce: nonce };
    }

    function emitAnalytics(eventName, payload) {
        try {
            document.dispatchEvent(new CustomEvent('bbai:analytics', {
                detail: Object.assign({
                    event: eventName,
                    timestamp: Date.now()
                }, payload || {})
            }));
        } catch (e) {}
    }

    function shouldFallbackToInlineStart(errCode, message, responseStatus) {
        var code = String(errCode || '').toLowerCase();
        var msg = String(message || '').toLowerCase();
        var status = parseInt(responseStatus, 10) || 0;

        if (
            code.indexOf('quota') !== -1 ||
            code.indexOf('credit') !== -1 ||
            code.indexOf('limit') !== -1 ||
            code.indexOf('auth') !== -1 ||
            code.indexOf('forbidden') !== -1 ||
            status === 401 ||
            status === 403 ||
            status === 429
        ) {
            return false;
        }

        return code === 'bbai_jobs_not_eligible' ||
            code === 'bbai_job_create_failed' ||
            code === 'rest_no_route' ||
            code === 'http_request_failed' ||
            code === 'bulk_job_start_failed' ||
            code === 'start_timeout' ||
            code === 'start_request_failed' ||
            status === 0 ||
            status === 404 ||
            msg.indexOf('could not start bulk generation') !== -1 ||
            msg.indexOf('failed to start bulk generation job') !== -1 ||
            msg.indexOf('not found') !== -1;
    }

    function fallbackToInlineGeneration(onNotEligibleFallback, ids, onLogSuccess) {
        if (typeof onNotEligibleFallback !== 'function') {
            return false;
        }
        if (typeof onLogSuccess === 'function') {
            onLogSuccess('Server background job unavailable. Continuing generation in this page…');
        }
        onNotEligibleFallback(ids);
        return true;
    }

    function emitReplayMarker(marker, payload) {
        emitAnalytics('replay_marker', Object.assign({ marker: marker }, payload || {}));
        if (window.bbaiAnalytics && typeof window.bbaiAnalytics.trackGenerationLifecycle === 'function' && /^generation_/.test(marker)) {
            try {
                window.bbaiAnalytics.trackGenerationLifecycle(marker, payload || {});
            } catch (e) {}
        }
    }

    function isRecoverableActiveJob(active) {
        if (!active || !active.jobId) {
            return false;
        }
        if (isTerminalStatus(active.status)) {
            return false;
        }
        var started = parseInt(active.startedAt || active.updatedAt || '0', 10) || 0;
        return !started || Date.now() - started < 6 * 60 * 60 * 1000;
    }

    function updateJobWidgetFromActive(active, label) {
        if (!window.bbaiJobState || !active || !active.jobId) {
            return;
        }
        var total = Math.max(0, parseInt(active.total, 10) || 0);
        var progress = Math.max(0, parseInt(active.progress, 10) || 0);
        if (!window.bbaiJobState.getState().running) {
            window.bbaiJobState.start(label || 'Reconnecting to active generation', total, active.source || 'licensed_bulk_job');
        }
        window.bbaiJobState.update({
            running: true,
            modalVisible: false,
            progress: progress,
            total: total,
            successes: Math.max(0, parseInt(active.successes, 10) || 0),
            failures: Math.max(0, parseInt(active.failures, 10) || 0),
            status: 'processing',
            label: label || 'Generation continues in background'
        });
    }

    function recoverActiveJob(options) {
        var active = readActiveJob();
        var cfg = getAjaxConfig();
        var attempts = 0;
        var opts = options || {};

        if (!isRecoverableActiveJob(active) || !cfg.ajaxUrl) {
            return false;
        }

        if (recoveryStarted) {
            updateJobWidgetFromActive(active, 'Generation continues in background');
            return true;
        }

        if (!claimPollLeadership()) {
            updateJobWidgetFromActive(active, 'Generation continues in background');
            window.setTimeout(function () {
                recoveryStarted = false;
                recoverActiveJob({ surface: 'follower_retry' });
            }, POLL_LOCK_TTL + 1000);
            return true;
        }

        recoveryStarted = true;
        updateJobWidgetFromActive(active, 'Reconnecting to active generation');
        if (!active.resumedTracked) {
            emitReplayMarker('generation_resumed', {
                source: 'licensed_bulk_job',
                job_id: active.jobId,
                requested_count: active.total || 0,
                recovery_surface: opts.surface || 'bootstrap'
            });
            active.resumedTracked = true;
            writeActiveJob(active);
        }

        function pollRecovered() {
            if (!claimPollLeadership()) {
                recoveryStarted = false;
                return;
            }
            attempts++;
            $.ajax({
                url: cfg.ajaxUrl,
                method: 'POST',
                dataType: 'json',
                timeout: 45000,
                data: {
                    action: 'beepbeepai_bulk_job_poll',
                    job_id: active.jobId,
                    nonce: cfg.nonce
                }
            })
                .done(function (pollRes) {
                    if (!pollRes || !pollRes.success || !pollRes.data) {
                        if (attempts >= 3) {
                            emitReplayMarker('generation_recovery_failed', {
                                source: 'licensed_bulk_job',
                                job_id: active.jobId,
                                requested_count: active.total || 0,
                                error_code: 'poll_response_invalid'
                            });
                            clearActiveJob();
                            recoveryStarted = false;
                            if (window.bbaiJobState) {
                                window.bbaiJobState.complete({ status: 'error', successes: active.successes || 0, failures: active.failures || 1, skipped: 0 });
                            }
                            return;
                        }
                        window.setTimeout(pollRecovered, 2000);
                        return;
                    }

                    var pdata = pollRes.data;
                    var job = pdata.job || {};
                    var items = Array.isArray(job.items) ? job.items : (Array.isArray(job.results) ? job.results : []);
                    var counts = mapJobItemsToLibraryRows(items, null);
                    var total = Math.max(0, parseInt(active.total || job.total || job.count || '0', 10) || 0);
                    var recovered = Object.assign({}, active, {
                        status: job.status || active.status || 'processing',
                        progress: Math.min(total, counts.completed + counts.failed),
                        successes: counts.completed,
                        failures: counts.failed,
                        total: total
                    });
                    if (!active.resumeSuccessTracked) {
                        emitReplayMarker('generation_resume_success', {
                            source: 'licensed_bulk_job',
                            job_id: active.jobId,
                            requested_count: total,
                            processed_count: recovered.progress
                        });
                        recovered.resumeSuccessTracked = true;
                    }
                    writeActiveJob(recovered);

                    updateJobWidgetFromActive(recovered, 'Generation resumed');

                    if (isTerminalStatus(job.status)) {
                        clearActiveJob();
                        recoveryStarted = false;
                        if (window.bbaiJobState) {
                            window.bbaiJobState.complete({
                                status: counts.failed > 0 ? 'error' : 'complete',
                                successes: counts.completed,
                                failures: counts.failed,
                                skipped: 0
                            });
                        }
                        return;
                    }

                    active = recovered;
                    window.setTimeout(pollRecovered, 2500);
                })
                .fail(function () {
                    if (attempts >= 3) {
                        emitReplayMarker('generation_recovery_failed', {
                            source: 'licensed_bulk_job',
                            job_id: active.jobId,
                            requested_count: active.total || 0,
                            error_code: 'poll_request_failed'
                        });
                        clearActiveJob();
                        recoveryStarted = false;
                        if (window.bbaiJobState) {
                            window.bbaiJobState.complete({ status: 'error', successes: active.successes || 0, failures: active.failures || 1, skipped: 0 });
                        }
                        return;
                    }
                    window.setTimeout(pollRecovered, 2500);
                });
        }

        pollRecovered();
        return true;
    }

    function bindNavigationLifecycleAnalytics() {
        window.addEventListener('pagehide', function() {
            var job = readActiveJob();
            if (!job || !job.jobId) {
                return;
            }
            emitAnalytics('generation_backgrounded', {
                source: 'licensed_bulk_job',
                job_id: job.jobId,
                requested_count: job.total || 0,
                duration_ms: job.startedAt ? Math.max(0, Date.now() - job.startedAt) : 0
            });
        });

        if ($ && typeof $.fn !== 'undefined') {
            $(function () {
                recoverActiveJob({ surface: 'bootstrap' });
            });
        } else if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                recoverActiveJob({ surface: 'bootstrap' });
            });
        } else {
            recoverActiveJob({ surface: 'bootstrap' });
        }
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

        if (isRecoverableActiveJob(readActiveJob())) {
            emitAnalytics('generation_click_noop', {
                source: source,
                reason: 'active_job_recovered',
                requested_count: total
            });
            recoverActiveJob({ surface: 'cta_guard' });
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
            timeout: 12000,
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
                    var msg =
                        response && response.data && response.data.message
                            ? String(response.data.message)
                            : options.strings && options.strings.couldNotStart
                              ? options.strings.couldNotStart
                              : 'Could not start bulk generation.';
                    var responseStatus = response && response.data && response.data.data && response.data.data.status_code
                        ? parseInt(response.data.data.status_code, 10)
                        : 0;
                    if (shouldFallbackToInlineStart(errCode, msg, responseStatus) && fallbackToInlineGeneration(onNotEligibleFallback, normalizedIds, onLogSuccess)) {
                        return;
                    }
                    onLogError(msg);
                    emitAnalytics('generation_failed', {
                        source: source,
                        endpoint: 'beepbeepai_bulk_job_start',
                        ajax_action: 'beepbeepai_bulk_job_start',
                        requested_count: total,
                        error_code: errCode || 'bulk_job_start_failed',
                        error_message: msg
                    });
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
                    emitAnalytics('generation_stuck', {
                        source: source,
                        endpoint: 'beepbeepai_bulk_job_start',
                        ajax_action: 'beepbeepai_bulk_job_start',
                        requested_count: total,
                        error_code: 'missing_job_id',
                        error_message: 'No job id returned.'
                    });
                    finalize(0, 0, 0);
                    return;
                }

                persistActiveJob(jobId, total, { source: source, status: 'processing' });
                emitReplayMarker('generation_job_created', {
                    source: source,
                    job_id: jobId,
                    requested_count: total
                });

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
                            writeActiveJob({
                                jobId: jobId,
                                startedAt: (readActiveJob() || {}).startedAt || Date.now(),
                                total: total,
                                progress: finishedSlots,
                                successes: jobState.successes,
                                failures: jobState.failures,
                                status: job.status || 'processing',
                                source: source,
                                resumedTracked: true
                            });

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
                            emitAnalytics('polling_failed', {
                                source: source,
                                endpoint: 'beepbeepai_bulk_job_poll',
                                ajax_action: 'beepbeepai_bulk_job_poll',
                                job_id: jobId,
                                requested_count: total,
                                error_code: 'poll_request_failed'
                            });
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
                if (
                    xhr &&
                    shouldFallbackToInlineStart(
                        xhr.statusText === 'timeout' ? 'start_timeout' : 'start_request_failed',
                        failMsg,
                        xhr.status
                    ) &&
                    fallbackToInlineGeneration(onNotEligibleFallback, normalizedIds, onLogSuccess)
                ) {
                    emitAnalytics('generation_recovery_failed', {
                        source: source,
                        endpoint: 'beepbeepai_bulk_job_start',
                        ajax_action: 'beepbeepai_bulk_job_start',
                        requested_count: total,
                        error_code: xhr.statusText === 'timeout' ? 'start_timeout_fallback' : 'start_request_fallback',
                        error_message: failMsg,
                        response_status: xhr && xhr.status ? xhr.status : 0
                    });
                    return;
                }
                if (
                    xhr &&
                    shouldFallbackToInlineStart('start_request_failed', failMsg, xhr.status) &&
                    fallbackToInlineGeneration(onNotEligibleFallback, normalizedIds, onLogSuccess)
                ) {
                    return;
                }
                onLogError(failMsg);
                emitAnalytics('generation_failed', {
                    source: source,
                    endpoint: 'beepbeepai_bulk_job_start',
                    ajax_action: 'beepbeepai_bulk_job_start',
                    requested_count: total,
                    error_code: xhr && xhr.statusText === 'timeout' ? 'start_timeout' : 'start_request_failed',
                    error_message: failMsg,
                    response_status: xhr && xhr.status ? xhr.status : 0
                });
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
        readActiveJob: readActiveJob,
        writeActiveJob: writeActiveJob,
        clearActiveJob: clearActiveJob,
        recoverActiveJob: recoverActiveJob,
        hasActiveJob: function () {
            return isRecoverableActiveJob(readActiveJob());
        },
        getAjaxConfig: getAjaxConfig,
        emitAnalytics: emitAnalytics,
        run: run
    };

    bindNavigationLifecycleAnalytics();
})(window, jQuery);
