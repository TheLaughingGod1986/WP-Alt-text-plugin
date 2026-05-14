/**
 * Background Job Persistence Layer
 *
 * Polls /bbai/v1/queue on every plugin admin page so generation progress
 * survives navigation, page refreshes, and multiple browser tabs.
 *
 * Responsibilities:
 *  - Poll the REST queue endpoint (source of truth)
 *  - Persist recovery state to localStorage
 *  - Coordinate multi-tab polling via BroadcastChannel / storage events
 *  - Sync window.bbaiJobState so the floating widget stays up-to-date
 *  - Intercept "Run in background" clicks for telemetry
 *  - Handle ?bbai_reopen_progress=1 to reopen the modal on return
 *  - Emit telemetry events throughout
 *
 * @package BeepBeep_AI
 * @since 5.2.0
 */
(function (global) {
    'use strict';

    // ─── Constants ──────────────────────────────────────────────────────────────
    var STORAGE_JOB_KEY       = 'bbai_background_job';
    var STORAGE_DISMISSED_KEY = 'bbai_job_dismissed';
    var PRIMARY_TAB_KEY       = 'bbai_primary_tab';
    var PRIMARY_HEARTBEAT_KEY = 'bbai_primary_heartbeat';
    var PRIMARY_TTL_MS        = 18000;   // 18 s – tab considered dead after this
    var POLL_INTERVAL_MS      = 10000;   // 10 s primary poll
    var SECONDARY_POLL_MS     = 30000;   // 30 s secondary (fallback when no BC)
    var HEARTBEAT_INTERVAL_MS = 8000;    // 8 s heartbeat refresh
    var CHANNEL_NAME          = 'bbai_job_sync';
    var REOPEN_PARAM          = 'bbai_reopen_progress';

    // ─── Module state ────────────────────────────────────────────────────────────
    var pollTimer      = null;
    var heartbeatTimer = null;
    var isPrimary      = false;
    var bc             = null;
    var tabId          = 'tab_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);

    // ─── Storage helpers ─────────────────────────────────────────────────────────
    function storageRead(key) {
        try { return JSON.parse(global.localStorage.getItem(key) || 'null'); }
        catch (e) { return null; }
    }

    function storageWrite(key, val) {
        try { global.localStorage.setItem(key, JSON.stringify(val)); }
        catch (e) { /* quota or private browsing */ }
    }

    function storageDel(key) {
        try { global.localStorage.removeItem(key); }
        catch (e) {}
    }

    // ─── BroadcastChannel ────────────────────────────────────────────────────────
    function setupBroadcastChannel() {
        if (!global.BroadcastChannel) { return; }
        try {
            bc = new global.BroadcastChannel(CHANNEL_NAME);
            bc.onmessage = function (evt) {
                if (!evt || !evt.data || !evt.data.type) { return; }
                var msg = evt.data;

                if (msg.type === 'primary_claim' && msg.tabId !== tabId) {
                    // Another tab became primary — yield if we were primary.
                    if (isPrimary) {
                        isPrimary = false;
                        stopPolling();
                        stopHeartbeat();
                        scheduleSecondaryFallback();
                    }
                }

                if (msg.type === 'job_update') {
                    applyJobUpdate(msg.payload, false);
                }

                if (msg.type === 'job_cleared') {
                    storageDel(STORAGE_JOB_KEY);
                    storageDel(STORAGE_DISMISSED_KEY);
                    syncJobState(null);
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

    // ─── Primary tab election ────────────────────────────────────────────────────
    function claimPrimary() {
        isPrimary = true;
        storageWrite(PRIMARY_TAB_KEY, tabId);
        storageWrite(PRIMARY_HEARTBEAT_KEY, Date.now());
        broadcast('primary_claim', { tabId: tabId });
        startHeartbeat();
        startPolling();
    }

    function tryClaimPrimary() {
        var storedTabId  = storageRead(PRIMARY_TAB_KEY);
        var lastBeat     = storageRead(PRIMARY_HEARTBEAT_KEY) || 0;
        var isStale      = (Date.now() - lastBeat) > PRIMARY_TTL_MS;

        if (!storedTabId || storedTabId === tabId || isStale) {
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

    // ─── REST config resolution ──────────────────────────────────────────────────
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
            (global.bbai_ajax && global.bbai_ajax.nonce) || null;
    }

    function getAdminUrl() {
        if (global.bbai_ajax && global.bbai_ajax.admin_url) {
            return global.bbai_ajax.admin_url;
        }
        if (global.bbai_env && global.bbai_env.admin_url) {
            return global.bbai_env.admin_url;
        }
        return '';
    }

    // ─── Polling ─────────────────────────────────────────────────────────────────
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

    function scheduleSecondaryFallback() {
        // Secondary tabs poll at a reduced rate in case BroadcastChannel is absent.
        if (pollTimer) { return; }
        pollTimer = setTimeout(function () {
            pollTimer = null;
            // Re-check primary before polling.
            var lastBeat = storageRead(PRIMARY_HEARTBEAT_KEY) || 0;
            if ((Date.now() - lastBeat) > PRIMARY_TTL_MS) {
                claimPrimary(); // Primary died — take over.
            } else {
                doPoll();
                scheduleSecondaryFallback();
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
                try {
                    handlePollResponse(JSON.parse(xhr.responseText));
                } catch (e) { scheduleNextPoll(); }
            } else if (xhr.status === 401 || xhr.status === 403) {
                // Session expired — stop silently.
                stopPolling();
                stopHeartbeat();
            } else {
                scheduleNextPoll();
            }
        };

        xhr.onerror   = scheduleNextPoll;
        xhr.ontimeout = scheduleNextPoll;
        xhr.send();
    }

    function scheduleNextPoll() {
        if (!isPrimary) { return; }
        var saved = storageRead(STORAGE_JOB_KEY);
        var active = saved && (saved.status === 'processing' || saved.status === 'queued');
        pollTimer = setTimeout(doPoll, active ? POLL_INTERVAL_MS : POLL_INTERVAL_MS * 3);
    }

    // ─── Poll response parsing ────────────────────────────────────────────────────
    function handlePollResponse(data) {
        var jobState  = (data.job_state  || 'IDLE').toUpperCase();
        var activeJob = data.active_job  || null;
        var stats     = data.stats       || {};

        var payload = null;

        if (jobState !== 'IDLE' && activeJob) {
            payload = buildPayloadFromActiveJob(activeJob, jobState, stats);
        } else if (stats.pending > 0 || stats.processing > 0) {
            // Active queue rows without a formal active_job record.
            var total = (stats.pending || 0) + (stats.processing || 0);
            var done  = stats.completed_recent || 0;
            payload = {
                status: stats.processing > 0 ? 'processing' : 'queued',
                done:   done,
                total:  Math.max(total, done),
                state:  stats.processing > 0 ? 'PROCESSING' : 'QUEUED',
                last_checked_at: Date.now()
            };
        } else {
            // Queue is idle.  If we had an active job, figure out what happened.
            var saved = storageRead(STORAGE_JOB_KEY);
            if (saved && (saved.status === 'processing' || saved.status === 'queued')) {
                // It just finished — mark complete.
                var completedCount = stats.completed_recent || saved.done || saved.total || 0;
                payload = {
                    status: 'complete',
                    done:   completedCount,
                    total:  saved.total || completedCount,
                    state:  'COMPLETED',
                    last_checked_at: Date.now()
                };
            }
        }

        applyJobUpdate(payload, true);

        if (isPrimary) {
            broadcast('job_update', payload);
            scheduleNextPoll();
        }
    }

    function buildPayloadFromActiveJob(job, jobState, stats) {
        var total = Math.max(1, job.total || (stats.pending || 0) + (stats.processing || 0) || 1);
        var done  = Math.max(0, Math.min(job.done || 0, total));
        return {
            status:          jobState === 'QUEUED' ? 'queued' : 'processing',
            done:            done,
            total:           total,
            state:           jobState,
            eta_seconds:     job.eta_seconds || null,
            last_checked_at: Date.now()
        };
    }

    // ─── Apply job update ─────────────────────────────────────────────────────────
    function applyJobUpdate(payload, fromPoll) {
        if (!payload) {
            // Job ended.
            var saved = storageRead(STORAGE_JOB_KEY);
            if (saved && fromPoll && (saved.status === 'processing' || saved.status === 'queued')) {
                telemetry('generation_recovery_failed', { reason: 'job_gone_on_poll' });
            }
            storageDel(STORAGE_JOB_KEY);
            syncJobState(null);
            return;
        }

        // Merge with stored state (preserve started_at).
        var existing = storageRead(STORAGE_JOB_KEY) || {};
        var merged = {
            status:          payload.status,
            done:            payload.done,
            total:           payload.total,
            state:           payload.state,
            started_at:      existing.started_at || Date.now(),
            last_checked_at: payload.last_checked_at || Date.now()
        };
        storageWrite(STORAGE_JOB_KEY, merged);

        syncJobState(merged);
        maybeShowIndicator(merged);
    }

    // ─── Sync window.bbaiJobState ─────────────────────────────────────────────────
    function syncJobState(data) {
        if (!global.bbaiJobState) { return; }

        var currentState = global.bbaiJobState.getState();

        if (!data) {
            // Only reset if modal is not actively showing.
            if (!currentState.modalVisible && currentState.status !== 'idle') {
                global.bbaiJobState.reset();
            }
            return;
        }

        // Don't overwrite state while a live in-browser generation is ticking.
        if (currentState.modalVisible && currentState.running) { return; }

        var total      = data.total || 0;
        var done       = data.done  || 0;
        var percentage = total > 0 ? Math.round((done / total) * 100) : 0;

        if (data.status === 'complete') {
            if (!currentState.running) {
                global.bbaiJobState.update({
                    running:      false,
                    status:       'complete',
                    progress:     done,
                    total:        total,
                    percentage:   100,
                    successes:    done,
                    modalVisible: false
                });
            }
        } else if (data.status === 'processing' || data.status === 'queued') {
            // Only update if not already actively running (browser-side loop).
            global.bbaiJobState.update({
                running:      true,
                status:       'processing',
                progress:     done,
                total:        total,
                percentage:   percentage,
                label:        'Generating ALT text…',
                modalVisible: currentState.modalVisible
            });
        }
    }

    // ─── Global indicator management ─────────────────────────────────────────────
    // The primary indicator is the floating job-widget already created by job-widget.js.
    // We emit the standard bbaiJobState updates so it renders correctly.
    // Additionally we track when to show/hide based on dismiss state.

    function maybeShowIndicator(data) {
        var dismissed = storageRead(STORAGE_DISMISSED_KEY);

        // After completion the indicator should reappear even if previously dismissed.
        if (data.status === 'complete') {
            storageDel(STORAGE_DISMISSED_KEY);
            telemetry('generation_global_indicator_shown', {
                status: 'complete',
                done:   data.done,
                total:  data.total
            });
            return;
        }

        // Don't re-show while actively dismissed for a running job.
        if (dismissed && dismissed.status !== 'complete') { return; }

        telemetry('generation_global_indicator_shown', {
            status: data.status,
            done:   data.done,
            total:  data.total
        });
    }

    // ─── Intercept "Run in background" ───────────────────────────────────────────
    function bindRunInBackground() {
        document.addEventListener('click', function (evt) {
            var target = evt.target;
            if (!target || !target.closest) { return; }
            var btn = target.closest('[data-bbai-bulk-progress-background]');
            if (!btn) { return; }

            var current = global.bbaiJobState ? global.bbaiJobState.getState() : null;
            telemetry('generation_backgrounded', {
                done:        current ? current.progress : 0,
                total:       current ? current.total    : 0,
                source_page: getPageSlug()
            });

            // Clear any previous dismiss so the widget becomes visible.
            storageDel(STORAGE_DISMISSED_KEY);

            // Persist current in-memory state immediately so recovery works.
            if (current && current.running) {
                var existing = storageRead(STORAGE_JOB_KEY) || {};
                storageWrite(STORAGE_JOB_KEY, {
                    status:          'processing',
                    done:            current.progress || 0,
                    total:           current.total    || 0,
                    state:           'PROCESSING',
                    started_at:      existing.started_at || Date.now(),
                    last_checked_at: Date.now()
                });
            }
        }, true);
    }

    // ─── "View progress" cross-page handling ─────────────────────────────────────
    // job-widget.js has its own View handler, but it only opens the modal if it
    // already exists in the DOM.  We intercept via capture phase so we can redirect
    // to the dashboard when necessary.
    function bindViewProgressButton() {
        document.addEventListener('click', function (evt) {
            var target = evt.target;
            if (!target || !target.closest) { return; }
            var btn = target.closest('.bbai-job-widget__view');
            if (!btn) { return; }

            var modal = document.getElementById('bbai-bulk-progress-modal');

            if (modal) {
                // Modal exists (live generation in progress on this page).
                telemetry('generation_resumed', { via: 'view_progress', same_page: true });
                telemetry('generation_modal_reopened', { from_page: getPageSlug() });
                // Let job-widget.js own handler proceed.
                return;
            }

            // Navigate to dashboard to reopen modal.
            evt.preventDefault();
            evt.stopPropagation();
            if (typeof evt.stopImmediatePropagation === 'function') {
                evt.stopImmediatePropagation();
            }

            telemetry('generation_resumed', { via: 'view_progress', same_page: false });
            telemetry('generation_modal_reopened', { from_page: getPageSlug() });

            var saved    = storageRead(STORAGE_JOB_KEY);
            var adminUrl = getAdminUrl();

            if (saved && saved.status === 'complete') {
                // Jump to library for review.
                global.location.assign(adminUrl + '?page=bbai&tab=library');
            } else {
                global.location.assign(adminUrl + '?page=bbai&' + REOPEN_PARAM + '=1');
            }
        }, true);
    }

    // ─── Intercept dismiss on job-widget ─────────────────────────────────────────
    function bindDismissButton() {
        document.addEventListener('click', function (evt) {
            var target = evt.target;
            if (!target || !target.closest) { return; }
            if (!target.closest('.bbai-job-widget__close')) { return; }

            var saved = storageRead(STORAGE_JOB_KEY);
            if (saved) {
                storageWrite(STORAGE_DISMISSED_KEY, {
                    status:       saved.status,
                    dismissed_at: Date.now()
                });
            }
            broadcast('job_cleared', null);
        });
    }

    // ─── Handle ?bbai_reopen_progress=1 on dashboard ─────────────────────────────
    function maybeReopenOnDashboard() {
        if (global.location.search.indexOf(REOPEN_PARAM + '=1') === -1) { return; }

        var saved = storageRead(STORAGE_JOB_KEY);
        if (!saved) {
            telemetry('generation_recovery_failed', { reason: 'no_saved_state' });
            return;
        }

        // Immediately poll backend to get fresh status.
        var url   = getRestUrl();
        var nonce = getRestNonce();
        if (!url || !nonce) { return; }

        var xhr = new XMLHttpRequest();
        xhr.open('GET', url + '?_=' + Date.now());
        xhr.setRequestHeader('X-WP-Nonce', nonce);
        xhr.timeout = 10000;
        xhr.onload  = function () {
            var data;
            try { data = JSON.parse(xhr.responseText); } catch (e) { return; }
            handlePollResponse(data);

            // Wait for the generation modal to be injected by bbai-admin.js (up to 5 s).
            var attempts    = 0;
            var maxAttempts = 20;
            var interval    = setInterval(function () {
                attempts++;
                var modal = document.getElementById('bbai-bulk-progress-modal');
                var fresh = storageRead(STORAGE_JOB_KEY);

                if (modal && fresh) {
                    clearInterval(interval);

                    if (fresh.status === 'processing' || fresh.status === 'queued') {
                        modal.classList.add('active');
                        document.body.style.overflow = 'hidden';
                        if (global.bbaiJobState) {
                            global.bbaiJobState.update({ modalVisible: true });
                        }
                        telemetry('generation_resume_success', { via: 'url_reopen' });
                    } else if (fresh.status === 'complete') {
                        // Nothing more to show in the progress modal.
                        telemetry('generation_resume_success', { via: 'url_reopen_complete' });
                    }
                    return;
                }

                if (!fresh) {
                    // Backend confirmed idle while we were waiting.
                    clearInterval(interval);
                    telemetry('generation_recovery_failed', { reason: 'job_gone_during_reopen' });
                    return;
                }

                if (attempts >= maxAttempts) {
                    clearInterval(interval);
                    telemetry('generation_recovery_failed', { reason: 'modal_not_created' });
                }
            }, 250);
        };
        xhr.onerror = xhr.ontimeout = function () {
            telemetry('generation_recovery_failed', { reason: 'poll_failed_on_reopen' });
        };
        xhr.send();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────────
    function getPageSlug() {
        try {
            var params = new URLSearchParams(global.location.search);
            return params.get('page') || 'unknown';
        } catch (e) {
            var match = global.location.search.match(/[?&]page=([^&]*)/);
            return match ? decodeURIComponent(match[1]) : 'unknown';
        }
    }

    function telemetry(event, props) {
        if (global.bbaiTrack && typeof global.bbaiTrack === 'function') {
            try { global.bbaiTrack(event, props || {}); } catch (e) {}
        }
    }

    // ─── Initialise ───────────────────────────────────────────────────────────────
    function init() {
        setupBroadcastChannel();
        bindRunInBackground();
        bindViewProgressButton();
        bindDismissButton();

        // Recover and show saved state immediately (before first poll).
        var saved = storageRead(STORAGE_JOB_KEY);
        if (saved && (saved.status === 'processing' || saved.status === 'queued' || saved.status === 'complete')) {
            var dismissed = storageRead(STORAGE_DISMISSED_KEY);
            if (!dismissed || dismissed.status !== saved.status) {
                syncJobState(saved);
            }
        }

        // Handle ?bbai_reopen_progress=1.
        maybeReopenOnDashboard();

        // Elect primary polling tab.
        tryClaimPrimary();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // ─── Public API (for debugging) ───────────────────────────────────────────────
    global.bbaiBackgroundJob = {
        poll:       doPoll,
        getState:   function () { return storageRead(STORAGE_JOB_KEY); },
        clear:      function () {
            storageDel(STORAGE_JOB_KEY);
            storageDel(STORAGE_DISMISSED_KEY);
            stopPolling();
            broadcast('job_cleared', null);
        },
        isPrimary:  function () { return isPrimary; },
        tabId:      tabId
    };

})(window);
