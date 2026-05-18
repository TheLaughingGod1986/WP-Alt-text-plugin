/**
 * Global Job State Manager
 *
 * Central state for background ALT text generation jobs.
 * Both the progress modal and floating widget read from this.
 *
 * @package BeepBeep_AI
 * @since 5.1.0
 */
(function (global) {
    'use strict';

    var STORAGE_KEY = 'bbai_active_generation_state_v1';
    var MAX_STATE_AGE_MS = 6 * 60 * 60 * 1000;
    var listeners = [];
    var autoDismissTimer = null;
    var suppressPersist = false;

    var state = {
        running: false,
        progress: 0,
        total: 0,
        percentage: 0,
        successes: 0,
        failures: 0,
        skipped: 0,
        status: 'idle',       // idle | processing | complete | error | quota
        label: '',
        mode: '',
        startTime: null,
        eta: '',
        modalVisible: false,
        lastImageTitle: ''
    };

    function isPersistable(next) {
        return !!next && next.status !== 'idle' && (
            next.running ||
            next.status === 'processing' ||
            next.status === 'complete' ||
            next.status === 'error' ||
            next.status === 'quota'
        );
    }

    function readStoredState() {
        var raw;
        var parsed;
        var updatedAt;

        try {
            raw = global.localStorage ? global.localStorage.getItem(STORAGE_KEY) : '';
            if (!raw) {
                return null;
            }
            parsed = JSON.parse(raw);
            updatedAt = parseInt(parsed.updatedAt || parsed.startTime || '0', 10) || 0;
            if (updatedAt && Date.now() - updatedAt > MAX_STATE_AGE_MS) {
                global.localStorage.removeItem(STORAGE_KEY);
                return null;
            }
            return parsed && typeof parsed === 'object' ? parsed : null;
        } catch (e) {
            return null;
        }
    }

    function persistState() {
        if (suppressPersist) {
            return;
        }
        try {
            if (!global.localStorage) {
                return;
            }
            if (!isPersistable(state)) {
                global.localStorage.removeItem(STORAGE_KEY);
                return;
            }
            global.localStorage.setItem(STORAGE_KEY, JSON.stringify({
                running: !!state.running,
                progress: Math.max(0, parseInt(state.progress, 10) || 0),
                total: Math.max(0, parseInt(state.total, 10) || 0),
                percentage: Math.max(0, parseInt(state.percentage, 10) || 0),
                successes: Math.max(0, parseInt(state.successes, 10) || 0),
                failures: Math.max(0, parseInt(state.failures, 10) || 0),
                skipped: Math.max(0, parseInt(state.skipped, 10) || 0),
                status: String(state.status || 'idle'),
                label: String(state.label || ''),
                mode: String(state.mode || ''),
                startTime: state.startTime || Date.now(),
                updatedAt: Date.now(),
                eta: String(state.eta || ''),
                modalVisible: !!state.modalVisible,
                lastImageTitle: String(state.lastImageTitle || '')
            }));
        } catch (e) {}
    }

    function applyStoredState(stored) {
        if (!stored || typeof stored !== 'object') {
            return;
        }
        suppressPersist = true;
        update({
            running: !!stored.running,
            progress: Math.max(0, parseInt(stored.progress, 10) || 0),
            total: Math.max(0, parseInt(stored.total, 10) || 0),
            percentage: Math.max(0, parseInt(stored.percentage, 10) || 0),
            successes: Math.max(0, parseInt(stored.successes, 10) || 0),
            failures: Math.max(0, parseInt(stored.failures, 10) || 0),
            skipped: Math.max(0, parseInt(stored.skipped, 10) || 0),
            status: String(stored.status || 'idle'),
            label: String(stored.label || ''),
            mode: String(stored.mode || ''),
            startTime: stored.startTime || null,
            eta: String(stored.eta || ''),
            modalVisible: !!stored.modalVisible,
            lastImageTitle: String(stored.lastImageTitle || '')
        });
        suppressPersist = false;
    }

    function notify() {
        for (var i = 0; i < listeners.length; i++) {
            try { listeners[i](state); } catch (e) { /* swallow */ }
        }
    }

    /**
     * Update one or more state keys and notify listeners.
     */
    function update(patch) {
        var changed = false;
        for (var key in patch) {
            if (Object.prototype.hasOwnProperty.call(patch, key) && state[key] !== patch[key]) {
                state[key] = patch[key];
                changed = true;
            }
        }
        if (changed) {
            if (state.total > 0) {
                state.percentage = Math.round((state.progress / state.total) * 100);
            }
            persistState();
            notify();
        }
    }

    /**
     * Start a new job.
     */
    function start(label, total, mode) {
        if (autoDismissTimer) {
            clearTimeout(autoDismissTimer);
            autoDismissTimer = null;
        }
        update({
            running: true,
            progress: 0,
            total: Math.max(0, total || 0),
            percentage: 0,
            successes: 0,
            failures: 0,
            skipped: 0,
            status: 'processing',
            label: label || 'Generation continues in background',
            mode: mode || '',
            startTime: Date.now(),
            eta: '',
            modalVisible: true,
            lastImageTitle: ''
        });
    }

    /**
     * Record a single image processed (success or failure).
     */
    function tick(opts) {
        var isSuccess = opts && opts.success !== false;
        var newProgress = state.progress + 1;
        var patch = {
            progress: newProgress,
            lastImageTitle: (opts && opts.title) || ''
        };
        if (isSuccess) {
            patch.successes = state.successes + 1;
        } else {
            patch.failures = state.failures + 1;
        }

        // ETA
        var elapsed = (Date.now() - (state.startTime || Date.now())) / 1000;
        if (newProgress > 0 && elapsed > 0) {
            var avg = elapsed / newProgress;
            var remaining = (state.total - newProgress) * avg;
            if (remaining < 60) {
                patch.eta = Math.ceil(remaining) + 's';
            } else if (remaining < 3600) {
                patch.eta = Math.ceil(remaining / 60) + 'm';
            } else {
                patch.eta = Math.floor(remaining / 3600) + 'h ' + Math.ceil((remaining % 3600) / 60) + 'm';
            }
        }

        update(patch);
    }

    /**
     * Mark job as complete.
     */
    function complete(opts) {
        opts = opts || {};
        update({
            running: false,
            successes: opts.successes !== undefined ? Math.max(0, parseInt(opts.successes, 10) || 0) : state.successes,
            failures: opts.failures !== undefined ? Math.max(0, parseInt(opts.failures, 10) || 0) : state.failures,
            skipped: opts.skipped !== undefined ? Math.max(0, parseInt(opts.skipped, 10) || 0) : state.skipped,
            status: opts.status || (state.failures > 0 ? 'error' : 'complete'),
            percentage: 100,
            eta: '',
            progress: state.total
        });

        // Keep terminal states visible until the user acknowledges them.
        // Users often background generation and navigate between wp-admin pages;
        // auto-clearing the state makes the finished job look like it vanished.
    }

    /**
     * Reset to idle.
     */
    function reset() {
        if (autoDismissTimer) {
            clearTimeout(autoDismissTimer);
            autoDismissTimer = null;
        }
        update({
            running: false,
            progress: 0,
            total: 0,
            percentage: 0,
            successes: 0,
            failures: 0,
            skipped: 0,
            status: 'idle',
            label: '',
            mode: '',
            startTime: null,
            eta: '',
            modalVisible: false,
            lastImageTitle: ''
        });
        try {
            if (global.localStorage) {
                global.localStorage.removeItem(STORAGE_KEY);
            }
        } catch (e) {}
    }

    /**
     * Subscribe to state changes.
     * @returns {Function} unsubscribe
     */
    function subscribe(fn) {
        listeners.push(fn);
        return function () {
            listeners = listeners.filter(function (f) { return f !== fn; });
        };
    }

    /**
     * Read current state (shallow copy).
     */
    function getState() {
        var copy = {};
        for (var key in state) {
            if (Object.prototype.hasOwnProperty.call(state, key)) {
                copy[key] = state[key];
            }
        }
        return copy;
    }

    global.bbaiJobState = {
        start: start,
        tick: tick,
        complete: complete,
        reset: reset,
        update: update,
        subscribe: subscribe,
        getState: getState
    };

    applyStoredState(readStoredState());

    if (global.addEventListener) {
        global.addEventListener('storage', function (event) {
            if (!event || event.key !== STORAGE_KEY) {
                return;
            }
            if (!event.newValue) {
                suppressPersist = true;
                reset();
                suppressPersist = false;
                return;
            }
            try {
                applyStoredState(JSON.parse(event.newValue));
            } catch (e) {}
        });
    }

})(window);
