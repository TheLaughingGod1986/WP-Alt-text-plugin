/**
 * Background Job Persistence Layer
 *
 * Polls /bbai/v1/queue on every BeepBeep AI admin page so generation progress
 * survives navigation, page refresh, and multiple browser tabs.
 *
 * State machine:
 *   idle → queued / processing → complete | failed
 *
 * Terminal states (complete / failed) persist in localStorage until:
 *   - the user explicitly dismisses the widget, or
 *   - a new job starts (overwrite), or
 *   - the 24-hour TTL expires.
 *
 * Dismiss persists across page loads: STORAGE_DISMISSED_KEY is written and
 * read on every init().  It is cleared only when a new job starts or the job
 * reaches a terminal state (so the user always sees the final outcome).
 *
 * Multi-tab:
 *   Primary tab holds a localStorage heartbeat and polls every 10 s.
 *   Secondary tabs listen via BroadcastChannel (fallback: 30 s slow poll).
 *   If the primary heartbeat goes stale (>18 s), any secondary takes over.
 *
 * Broadcast message types:
 *   primary_claim – tab elected as primary poller
 *   job_started   – new job began (clears dismissed flag on all tabs)
 *   job_update    – progress from primary → secondaries
 *   job_complete  – terminal state reached (clears dismissed, all tabs show)
 *   job_dismissed – user dismissed widget (all tabs hide)
 *
 * @package BeepBeep_AI
 * @since 5.2.1
 */
(function (global) {
    'use strict';

    // ─── Constants ────────────────────────────────────────────────────────────────
    var STORAGE_JOB_KEY       = 'bbai_background_job';
    var STORAGE_DISMISSED_KEY = 'bbai_job_dismissed';
    var PRIMARY_TAB_KEY       = 'bbai_primary_tab';
    var PRIMARY_HEARTBEAT_KEY = 'bbai_primary_heartbeat';
    var PRIMARY_TTL_MS        = 18000;        // tab considered dead after 18 s
    var POLL_INTERVAL_MS      = 10000;        // primary poll every 10 s
    var SECONDARY_POLL_MS     = 30000;        // secondary fallback poll
    var HEARTBEAT_INTERVAL_MS = 8000;         // primary heartbeat refresh
    var COMPLETE_TTL_MS       = 86400000;     // 24 h – discard stale complete state
    var CHANNEL_NAME          = 'bbai_job_sync';
    var REOPEN_PARAM          = 'bbai_reopen_progress';

    // ─── Module state ─────────────────────────────────────────────────────────────
    var pollTimer      = null;
    var heartbeatTimer = null;
    var isPrimary      = false;
    var bc             = null;
    var tabId          = 'tab_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);

    // ─── Storage helpers ──────────────────────────────────────────────────────────
    function storageRead(key) {
        try { return JSON.parse(global.localStorage.getItem(key) || 'null'); }
        catch (e) { return null; }
    }

    function storageWrite(key, val) {
        try { global.localStorage.setItem(key, JSON.stringify(val)); }
        catch (e) { /* private browsing / quota */ }
    }

    function storageDel(key) {
        try { global.localStorage.removeItem(key); }
        catch (e) {}
    }

    // ─── BroadcastChannel ─────────────────────────────────────────────────────────
    function setupBroadcastChannel() {
        if (!global.BroadcastChannel) { return; }
        try {
            bc = new global.BroadcastChannel(CHANNEL_NAME);
            bc.onmessage = function (evt) {
                if (!evt || !evt.data || !evt.data.type) { return; }
                var msg = evt.data;

                if (msg.type === 'primary_claim' && msg.tabId !== tabId) {
                    // Another tab took over — yield if we were primary.
                    if (isPrimary) {
                        isPrimary = false;
                        stopPolling();
                        stopHeartbeat();
                        scheduleSecondaryFallback();
                    }
                }

                if (msg.type === 'job_started' && msg.payload) {
                    // New job started on another tab — clear dismissed, restore widget.
                    storageDel(STORAGE_DISMISSED_KEY);
                    mergeAndApply(msg.payload, false);
                }

                if (msg.type === 'job_update' && msg.payload) {
                    // Progress update from primary.
                    mergeAndApply(msg.payload, false);
                }

                if (msg.type === 'job_complete' && msg.payload) {
                    // Job reached a terminal state — always re-show (clears dismissed).
                    storageDel(STORAGE_DISMISSED_KEY);
                    mergeAndApply(msg.payload, false);
                }

                if (msg.type === 'job_dismissed') {
                    // User dismissed on another tab — mirror here.
                    storageWrite(STORAGE_DISMISSED_KEY, { dismissed_at: Date.now() });
                    forceHideWidget();
                }
            };
        } catch (e) {
            bc = null;
        }
    }

    function broadcast(type, payload) {
        if (!bc) { return; }
        try { bc.postMessage({ type: type, payload: payload || null, tabId: tabId }); }
        catch (e) {}
    }

    // ─── Primary-tab election ──────────────────────────────────────────────────────
    function claimPrimary() {
        isPrimary = true;
        storageWrite(PRIMARY_TAB_KEY, tabId);
        storageWrite(PRIMARY_HEARTBEAT_KEY, Date.now());
        broadcast('primary_claim', { tabId: tabId });
        startHeartbeat();
        startPolling();
    }

    function tryClaimPrimary() {
        var storedId = storageRead(PRIMARY_TAB_KEY);
        var lastBeat = storageRead(PRIMARY_HEARTBEAT_KEY) || 0;
        var isStale  = (Date.now() - lastBeat) > PRIMARY_TTL_MS;

        if (!storedId || storedId === tabId || isStale) {
            claimPrimary();
        } else {
            isPrimary = false;
            scheduleSecondaryFallback();
        }
    }

    function startHeartbeat() {
        stopHeartbeat();
        heartbeatTimer = setInterval(function () {
            storageWrite(PRIMARY_HEARTBEAT_KEY, Date.now());
        }, HEARTBEAT_INTERVAL_MS);
    }

    function stopHeartbeat() {
        if (heartbeatTimer) {
            clearInterval(heartbeatTimer);
            heartbeatTimer = null;
        }
    }

    // ─── REST config ──────────────────────────────────────────────────────────────
    function getRestUrl() {
        var cfg = global.BBAI || global.BBAI_DASH || {};
        if (cfg.restQueue) { return cfg.restQueue; }
        if (cfg.restRoot)  { return cfg.restRoot + 'bbai/v1/queue'; }
        if (cfg.rest)      { return cfg.rest + 'queue'; }
        return null;
    }

    function getRestNonce() {
        var cfg = global.BBAI || global.BBAI_DASH || {};
        return cfg.nonce ||
            (global.alttextai_ajax && global.alttextai_ajax.nonce) ||
            (global.bbai_ajax      && global.bbai_ajax.nonce)      || null;
    }

    function getAdminUrl() {
        return (global.bbai_ajax && global.bbai_ajax.admin_url) ||
               (global.bbai_env  && global.bbai_env.admin_url)  || '';
    }

    // ─── Polling ──────────────────────────────────────────────────────────────────
    function startPolling() {
        stopPolling();
        doPoll();
    }

    function stopPolling() {
        if (pollTimer) {
            clearTimeout(pollTimer);
            pollTimer = null;
        }
    }

    function scheduleNextPoll() {
        if (!isPrimary) { return; }
        var saved  = storageRead(STORAGE_JOB_KEY);
        var active = saved && (saved.status === 'processing' || saved.status === 'queued');
        // Only reschedule while actively processing; terminal states stop polling.
        if (active) {
            pollTimer = setTimeout(doPoll, POLL_INTERVAL_MS);
        }
    }

    function scheduleSecondaryFallback() {
        // Secondary tab fallback: only needed when BroadcastChannel is absent.
        // Check whether the primary is still alive; if not, take over.
        if (pollTimer) { return; }
        var saved = storageRead(STORAGE_JOB_KEY);
        if (!saved || (saved.status !== 'processing' && saved.status !== 'queued')) {
            return; // No active job – no polling needed.
        }
        pollTimer = setTimeout(function () {
            pollTimer = null;
            var lastBeat = storageRead(PRIMARY_HEARTBEAT_KEY) || 0;
            if ((Date.now() - lastBeat) > PRIMARY_TTL_MS) {
                claimPrimary(); // Primary died – take over.
            } else {
                scheduleSecondaryFallback(); // Primary alive – re-arm.
            }
        }, SECONDARY_POLL_MS);
    }

    function doPoll() {
        var url   = getRestUrl();
        var nonce = getRestNonce();
        if (!url || !nonce) { return; }

        var xhr = new XMLHttpRequest();
        xhr.open('GET', url + (url.indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now());
        xhr.setRequestHeader('X-WP-Nonce', nonce);
        xhr.timeout = 15000;

        xhr.onload = function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                try { handlePollResponse(JSON.parse(xhr.responseText)); }
                catch (e) { scheduleNextPoll(); }
            } else if (xhr.status === 401 || xhr.status === 403) {
                stopPolling();
                stopHeartbeat(); // Auth lost — stop silently.
            } else {
                scheduleNextPoll(); // Transient error — retry.
            }
        };

        xhr.onerror   = scheduleNextPoll;
        xhr.ontimeout = scheduleNextPoll;
        xhr.send();
    }

    // ─── Poll response parsing ─────────────────────────────────────────────────────
    function handlePollResponse(data) {
        var jobState  = (data.job_state  || 'IDLE').toUpperCase();
        var activeJob = data.active_job  || null;
        var stats     = data.stats       || {};
        var payload   = null;

        if (jobState !== 'IDLE' && activeJob) {
            // Licensed bulk job API or formally tracked WP queue job.
            payload = buildActivePayload(activeJob, jobState, stats);

        } else if ((stats.pending || 0) > 0 || (stats.processing || 0) > 0) {
            // Queue has rows but no formal active_job record.
            var qTotal = (stats.pending || 0) + (stats.processing || 0);
            var qDone  = stats.completed_recent || 0;
            payload = {
                status:          (stats.processing || 0) > 0 ? 'processing' : 'queued',
                done:            qDone,
                total:           qTotal + qDone,
                completed_count: qDone,
                failed_count:    stats.failed || 0,
                state:           (stats.processing || 0) > 0 ? 'PROCESSING' : 'QUEUED',
                last_checked_at: Date.now()
            };

        } else {
            // Queue is idle — determine what happened to a saved job.
            var saved = storageRead(STORAGE_JOB_KEY);

            if (!saved) {
                // Nothing was running. Nothing to do.
                if (isPrimary) { stopPolling(); }
                return;
            }

            if (saved.status === 'complete' || saved.status === 'failed') {
                // Already in a terminal state — do NOT wipe it; just stop polling.
                if (isPrimary) { stopPolling(); }
                return;
            }

            if (saved.status === 'processing' || saved.status === 'queued') {
                var completedCount = stats.completed_recent || 0;
                var failedCount    = stats.failed           || 0;
                var requestedCount = saved.requested_count  || saved.total || 0;

                if (completedCount === 0 && failedCount === 0) {
                    // Queue is IDLE with no evidence of completed work.  Before
                    // declaring the job vanished, check two conditions:
                    //
                    // 1. In-browser sequential generation is running on THIS tab —
                    //    it never touches the WP queue, so IDLE is expected.
                    //    The inlineSyncUnsub subscriber keeps localStorage current.
                    //
                    // 2. Grace period: the job started less than 60 s ago — the
                    //    queue row may not exist yet (server-side jobs can take a
                    //    few seconds to appear) or the user clicked "Run in
                    //    background" before any image finished.
                    if (global.bbaiJobState && global.bbaiJobState.getState().running) {
                        // In-browser generation active — keep polling, don't wipe.
                        scheduleNextPoll();
                        return;
                    }
                    var jobAge = Date.now() - (saved.started_at || saved.last_checked_at || 0);
                    if (jobAge < 60000) {
                        // Too early to declare failure — give the server time.
                        scheduleNextPoll();
                        return;
                    }

                    // Queue emptied with no evidence of work — unexpected disappearance.
                    telemetry('generation_recovery_failed', {
                        reason:      'job_vanished',
                        job_id:      saved.job_id,
                        source_page: saved.source_page
                    });
                    storageDel(STORAGE_JOB_KEY);
                    syncJobStateToIdle();
                    if (isPrimary) {
                        stopPolling();
                        broadcast('job_update', null);
                    }
                    return;
                }

                // Normal completion (possibly with some failures).
                var isAllFailed = (failedCount > 0 && completedCount === 0);
                payload = {
                    status:          isAllFailed ? 'failed' : 'complete',
                    done:            completedCount,
                    total:           requestedCount || (completedCount + failedCount),
                    requested_count: requestedCount,
                    completed_count: completedCount,
                    failed_count:    failedCount,
                    state:           'COMPLETED',
                    last_checked_at: Date.now()
                };

                if (isPrimary) {
                    stopPolling();
                    broadcast('job_complete', payload);
                    // Clear dismissed so completion is always visible.
                    storageDel(STORAGE_DISMISSED_KEY);
                }
            }
        }

        if (payload) {
            mergeAndApply(payload, true);
            if (isPrimary &&
                payload.status !== 'complete' &&
                payload.status !== 'failed') {
                broadcast('job_update', payload);
                scheduleNextPoll();
            }
        }
    }

    function buildActivePayload(job, jobState, stats) {
        var total = Math.max(1,
            job.total ||
            (stats.pending || 0) + (stats.processing || 0) ||
            1
        );
        var done = Math.max(0, Math.min(job.done || 0, total));
        return {
            status:          jobState === 'QUEUED' ? 'queued' : 'processing',
            done:            done,
            total:           total,
            requested_count: total,
            completed_count: done,
            failed_count:    stats.failed || 0,
            state:           jobState,
            eta_seconds:     job.eta_seconds || null,
            last_checked_at: Date.now()
        };
    }

    // ─── Apply a job update ───────────────────────────────────────────────────────
    function mergeAndApply(payload, fromPoll) {
        // fromPoll is used for recovery_failed logic (no longer triggers on mere null).
        void fromPoll;

        var existing = storageRead(STORAGE_JOB_KEY) || {};

        // Discard stale terminal states older than COMPLETE_TTL_MS.
        if (existing.status === 'complete' || existing.status === 'failed') {
            var age = Date.now() - (existing.last_checked_at || existing.started_at || 0);
            if (age > COMPLETE_TTL_MS) {
                storageDel(STORAGE_JOB_KEY);
                storageDel(STORAGE_DISMISSED_KEY);
                existing = {};
            }
        }

        // Build merged record — preserve job identity fields from localStorage.
        var merged = {
            job_id:          existing.job_id          || payload.job_id          || ('job_' + Date.now()),
            status:          payload.status,
            done:            nvl(payload.done,            existing.done            || 0),
            total:           nvl(payload.total,           existing.total           || 0),
            requested_count: nvl(payload.requested_count, existing.requested_count || payload.total || 0),
            completed_count: nvl(payload.completed_count, existing.completed_count || payload.done  || 0),
            failed_count:    nvl(payload.failed_count,    existing.failed_count    || 0),
            source_page:     existing.source_page || getPageSlug(),
            state:           payload.state,
            started_at:      existing.started_at || Date.now(),
            last_checked_at: payload.last_checked_at || Date.now()
        };

        storageWrite(STORAGE_JOB_KEY, merged);

        // Reaching a terminal state always clears the dismissed flag so the
        // user sees the final outcome (success or failure).
        if (merged.status === 'complete' || merged.status === 'failed') {
            storageDel(STORAGE_DISMISSED_KEY);
        }

        var dismissed = storageRead(STORAGE_DISMISSED_KEY);
        if (!dismissed) {
            syncJobState(merged);
            telemetry('generation_global_indicator_shown', {
                status:          merged.status,
                done:            merged.done,
                total:           merged.total,
                completed_count: merged.completed_count,
                failed_count:    merged.failed_count,
                source_page:     merged.source_page
            });
        }
    }

    // null-tolerant value selector (picks first non-null value)
    function nvl(a, b) {
        return (a !== null && a !== undefined) ? a : b;
    }

    // ─── Sync window.bbaiJobState ─────────────────────────────────────────────────
    // Uses .update() — NOT .complete() — to avoid triggering job-state.js's
    // 8-second auto-dismiss timer for backend-recovered states.
    function syncJobState(data) {
        if (!global.bbaiJobState) { return; }

        var cur = global.bbaiJobState.getState();

        // Don't clobber a live in-browser generation that has the modal open.
        if (cur.modalVisible && cur.running) { return; }

        var total      = data.total || 0;
        var done       = data.done  || 0;
        var percentage = total > 0 ? Math.round((done / total) * 100) : 0;

        if (data.status === 'complete') {
            if (!cur.running) {
                global.bbaiJobState.update({
                    running:      false,
                    status:       'complete',
                    progress:     done,
                    total:        total,
                    percentage:   100,
                    successes:    data.completed_count || done,
                    failures:     data.failed_count    || 0,
                    modalVisible: false
                });
            }
        } else if (data.status === 'failed') {
            if (!cur.running) {
                global.bbaiJobState.update({
                    running:      false,
                    status:       'error',
                    progress:     done,
                    total:        total,
                    percentage:   percentage,
                    successes:    data.completed_count || done,
                    failures:     data.failed_count    || 0,
                    modalVisible: false
                });
            }
        } else if (data.status === 'processing' || data.status === 'queued') {
            global.bbaiJobState.update({
                running:      true,
                status:       'processing',
                progress:     done,
                total:        total,
                percentage:   percentage,
                label:        'Generating ALT text…',
                successes:    data.completed_count || 0,
                failures:     data.failed_count    || 0,
                modalVisible: cur.modalVisible
            });
        }
    }

    function syncJobStateToIdle() {
        if (!global.bbaiJobState) { return; }
        var cur = global.bbaiJobState.getState();
        // Don't reset if the progress modal is currently open.
        if (!cur.modalVisible) {
            global.bbaiJobState.reset();
        }
    }

    // Hard-hide the widget without relying on bbaiJobState update chain.
    function forceHideWidget() {
        if (global.bbaiJobState) { global.bbaiJobState.reset(); }
        var w = document.getElementById('bbai-job-widget');
        if (w) { w.hidden = true; }
    }

    // ─── Intercept "Run in background" button ──────────────────────────────────────
    // inlineSyncUnsub holds the active bbaiJobState subscriber that relays
    // in-browser progress to localStorage + other tabs while the modal is hidden.
    var inlineSyncUnsub = null;

    function bindRunInBackground() {
        document.addEventListener('click', function (evt) {
            var target = evt.target;
            if (!target || !target.closest) { return; }
            if (!target.closest('[data-bbai-bulk-progress-background]')) { return; }

            var cur      = global.bbaiJobState ? global.bbaiJobState.getState() : null;
            var existing = storageRead(STORAGE_JOB_KEY) || {};

            // Snapshot current in-memory state into localStorage immediately.
            var jobData = {
                job_id:          existing.job_id || ('job_' + Date.now()),
                status:          'processing',
                done:            cur ? (cur.progress  || 0) : 0,
                total:           cur ? (cur.total      || 0) : 0,
                requested_count: cur ? (cur.total      || 0) : 0,
                completed_count: cur ? (cur.successes  || cur.progress || 0) : 0,
                failed_count:    cur ? (cur.failures   || 0) : 0,
                source_page:     getPageSlug(),
                state:           'PROCESSING',
                started_at:      existing.started_at || Date.now(),
                last_checked_at: Date.now()
            };

            storageWrite(STORAGE_JOB_KEY, jobData);
            storageDel(STORAGE_DISMISSED_KEY); // New background event — clear old dismiss.

            telemetry('generation_backgrounded', {
                done:        jobData.done,
                total:       jobData.total,
                source_page: jobData.source_page,
                job_id:      jobData.job_id
            });

            broadcast('job_started', jobData);

            // Subscribe to bbaiJobState and relay every tick to localStorage + other
            // tabs.  This is the authoritative source of truth for in-browser (non-
            // queue) sequential generation which is invisible to the REST queue endpoint.
            if (global.bbaiJobState) {
                if (inlineSyncUnsub) { inlineSyncUnsub(); }
                inlineSyncUnsub = global.bbaiJobState.subscribe(function (s) {
                    // Stop listening once we reach idle (modal reset or new job).
                    if (s.status === 'idle') {
                        if (inlineSyncUnsub) { inlineSyncUnsub(); inlineSyncUnsub = null; }
                        return;
                    }

                    var saved = storageRead(STORAGE_JOB_KEY) || {};
                    var updated = {
                        job_id:          saved.job_id          || jobData.job_id,
                        source_page:     saved.source_page     || jobData.source_page,
                        started_at:      saved.started_at      || jobData.started_at,
                        requested_count: saved.requested_count || s.total || 0,
                        done:            s.progress   || 0,
                        total:           s.total      || saved.total || 0,
                        completed_count: s.successes  || 0,
                        failed_count:    s.failures   || 0,
                        last_checked_at: Date.now()
                    };

                    if (s.status === 'complete' || (s.progress >= s.total && s.total > 0 && !s.running)) {
                        updated.status = 'complete';
                        updated.state  = 'COMPLETED';
                        storageWrite(STORAGE_JOB_KEY, updated);
                        storageDel(STORAGE_DISMISSED_KEY);
                        broadcast('job_complete', updated);
                        if (inlineSyncUnsub) { inlineSyncUnsub(); inlineSyncUnsub = null; }
                    } else if (s.status === 'error' || s.status === 'quota') {
                        updated.status = 'failed';
                        updated.state  = 'FAILED';
                        storageWrite(STORAGE_JOB_KEY, updated);
                        storageDel(STORAGE_DISMISSED_KEY);
                        broadcast('job_complete', updated);
                        if (inlineSyncUnsub) { inlineSyncUnsub(); inlineSyncUnsub = null; }
                    } else {
                        updated.status = 'processing';
                        updated.state  = 'PROCESSING';
                        storageWrite(STORAGE_JOB_KEY, updated);
                        broadcast('job_update', updated);
                    }
                });
            }

            // Start polling so server-side (licensed bulk) jobs are also tracked.
            // For pure in-browser jobs the subscriber above keeps state current.
            if (!isPrimary) {
                claimPrimary();
            } else if (!pollTimer) {
                startPolling();
            }
        }, true);
    }

    // ─── "View progress" cross-page handling ──────────────────────────────────────
    // Intercepts clicks in capture phase before job-widget.js's own handler.
    function bindViewProgressButton() {
        document.addEventListener('click', function (evt) {
            var target = evt.target;
            if (!target || !target.closest) { return; }
            if (!target.closest('.bbai-job-widget__view')) { return; }

            var modal    = document.getElementById('bbai-bulk-progress-modal');
            var saved    = storageRead(STORAGE_JOB_KEY);
            var adminUrl = getAdminUrl();

            if (modal) {
                // Modal exists in current page — let job-widget.js handle it.
                telemetry('generation_resumed',       { via: 'view_progress', same_page: true });
                telemetry('generation_modal_reopened', { from_page: getPageSlug() });
                return;
            }

            // No modal — navigate.
            evt.preventDefault();
            evt.stopPropagation();
            if (evt.stopImmediatePropagation) { evt.stopImmediatePropagation(); }

            telemetry('generation_resumed',       { via: 'view_progress', same_page: false });
            telemetry('generation_modal_reopened', { from_page: getPageSlug() });

            if (saved && (saved.status === 'complete' || saved.status === 'failed')) {
                global.location.assign(adminUrl + '?page=bbai&tab=library');
            } else {
                global.location.assign(adminUrl + '?page=bbai&' + REOPEN_PARAM + '=1');
            }
        }, true);
    }

    // ─── Dismiss handling ──────────────────────────────────────────────────────────
    // Catches bubbled click from the widget's × button (job-widget.js hides the DOM
    // element first, then this fires at the document level).
    function bindDismissButton() {
        document.addEventListener('click', function (evt) {
            var target = evt.target;
            if (!target || !target.closest) { return; }
            if (!target.closest('.bbai-job-widget__close')) { return; }

            storageWrite(STORAGE_DISMISSED_KEY, { dismissed_at: Date.now() });
            broadcast('job_dismissed', null);
            // Reset bbaiJobState so the widget stays hidden if something re-renders.
            if (global.bbaiJobState) { global.bbaiJobState.reset(); }
        });
    }

    // ─── Handle ?bbai_reopen_progress=1 ──────────────────────────────────────────
    function maybeReopenOnDashboard() {
        if (global.location.search.indexOf(REOPEN_PARAM + '=1') === -1) { return; }

        var saved = storageRead(STORAGE_JOB_KEY);
        if (!saved) {
            telemetry('generation_recovery_failed', { reason: 'no_saved_state' });
            return;
        }

        // Verify with backend before reopening.
        var url   = getRestUrl();
        var nonce = getRestNonce();

        function proceed(jobState) {
            if (jobState && (jobState.status === 'processing' || jobState.status === 'queued')) {
                waitForModalAndOpen(jobState);
            } else if (jobState && jobState.status === 'complete') {
                telemetry('generation_resume_success', { via: 'url_reopen_complete' });
                // Widget already visible via syncJobState — nothing more needed.
            } else {
                telemetry('generation_recovery_failed', { reason: 'job_gone_on_reopen' });
            }
        }

        if (!url || !nonce) {
            proceed(saved); // Can't verify — proceed optimistically from saved state.
            return;
        }

        var xhr = new XMLHttpRequest();
        xhr.open('GET', url + '?_=' + Date.now());
        xhr.setRequestHeader('X-WP-Nonce', nonce);
        xhr.timeout = 10000;
        xhr.onload = function () {
            var data;
            try { data = JSON.parse(xhr.responseText); }
            catch (e) { proceed(saved); return; }
            handlePollResponse(data);
            proceed(storageRead(STORAGE_JOB_KEY));
        };
        xhr.onerror = xhr.ontimeout = function () { proceed(saved); };
        xhr.send();
    }

    function waitForModalAndOpen(jobState) {
        var attempts    = 0;
        var maxAttempts = 20; // 5 s at 250 ms intervals
        var interval    = setInterval(function () {
            attempts++;
            var modal = document.getElementById('bbai-bulk-progress-modal');

            if (modal) {
                clearInterval(interval);
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
                if (global.bbaiJobState) {
                    global.bbaiJobState.update({ modalVisible: true });
                }
                telemetry('generation_resume_success', {
                    via:    'url_reopen',
                    status: jobState.status
                });
                return;
            }

            if (attempts >= maxAttempts) {
                clearInterval(interval);
                // Modal never appeared — widget is still visible, which is acceptable.
                telemetry('generation_recovery_failed', { reason: 'modal_not_created_on_reopen' });
            }
        }, 250);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────────
    function getPageSlug() {
        try {
            var p = new URLSearchParams(global.location.search);
            return p.get('page') || 'unknown';
        } catch (e) {
            var m = global.location.search.match(/[?&]page=([^&]*)/);
            return m ? decodeURIComponent(m[1]) : 'unknown';
        }
    }

    function telemetry(event, props) {
        if (global.bbaiTrack && typeof global.bbaiTrack === 'function') {
            try { global.bbaiTrack(event, props || {}); } catch (e) {}
        }
    }

    // ─── Stale-TTL cleanup ────────────────────────────────────────────────────────
    function cleanupStaleTTL() {
        var saved = storageRead(STORAGE_JOB_KEY);
        if (!saved) { return; }
        if (saved.status !== 'complete' && saved.status !== 'failed') { return; }
        var ts  = saved.last_checked_at || saved.started_at || 0;
        var age = Date.now() - ts;
        if (age > COMPLETE_TTL_MS) {
            storageDel(STORAGE_JOB_KEY);
            storageDel(STORAGE_DISMISSED_KEY);
        }
    }

    // ─── Init ─────────────────────────────────────────────────────────────────────
    function init() {
        cleanupStaleTTL();
        setupBroadcastChannel();
        bindRunInBackground();
        bindViewProgressButton();
        bindDismissButton();

        var saved     = storageRead(STORAGE_JOB_KEY);
        var dismissed = storageRead(STORAGE_DISMISSED_KEY);

        // Restore widget state from localStorage immediately (before first poll).
        // Skip if the user had explicitly dismissed the widget.
        if (saved && !dismissed) {
            syncJobState(saved);
        }

        // Handle ?bbai_reopen_progress=1 URL parameter.
        maybeReopenOnDashboard();

        // Start polling only if there is an active job to track.
        if (saved && (saved.status === 'processing' || saved.status === 'queued')) {
            tryClaimPrimary();
        }
        // Terminal or absent states don't need polling; polling restarts when
        // the user clicks "Run in background" (bindRunInBackground → claimPrimary).
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // ─── Public API (debugging / integration) ────────────────────────────────────
    global.bbaiBackgroundJob = {
        poll:      doPoll,
        getState:  function () { return storageRead(STORAGE_JOB_KEY); },
        dismiss:   function () {
            storageWrite(STORAGE_DISMISSED_KEY, { dismissed_at: Date.now() });
            broadcast('job_dismissed', null);
            forceHideWidget();
        },
        clear:     function () {
            storageDel(STORAGE_JOB_KEY);
            storageDel(STORAGE_DISMISSED_KEY);
            stopPolling();
            syncJobStateToIdle();
        },
        isPrimary: function () { return isPrimary; },
        tabId:     tabId
    };

})(window);
