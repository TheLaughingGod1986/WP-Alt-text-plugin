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

    var listeners = [];
    var autoDismissTimer = null;

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
            label: label || 'Processing images\u2026',
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

        // Auto-dismiss widget after 8 seconds
        autoDismissTimer = setTimeout(function () {
            if (!state.running && (state.status === 'complete' || state.status === 'error' || state.status === 'quota')) {
                reset();
            }
        }, 8000);
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

})(window);
