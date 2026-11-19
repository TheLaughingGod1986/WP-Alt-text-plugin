/**
 * Saving Settings with usage analytics turned off must not send queued
 * client events (beepbeepai_telemetry POSTs or browser PostHog calls).
 *
 * Runs bbai-telemetry.js in a vm sandbox with a minimal jQuery/DOM stub.
 * Run offline: unshare -rn sh -c 'ip link set lo up && npx jest --runInBand tests/js'
 */
'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const SRC = fs.readFileSync(
    path.join(__dirname, '..', '..', 'assets', 'js', 'bbai-telemetry.js'),
    'utf8'
);

function makeEnv() {
    const handlers = {};
    const readyFns = [];
    const posts = [];
    const posthogCalls = [];
    const timers = [];

    const fakeEl = () => ({
        on(events, selector, handler) {
            const fn = typeof selector === 'function' ? selector : handler;
            String(events).split(/\s+/).forEach((ev) => {
                (handlers[ev] = handlers[ev] || []).push(fn);
            });
            return this;
        },
        text: () => '',
        attr: () => '',
    });

    const $ = function (arg) {
        if (typeof arg === 'function') {
            readyFns.push(arg);
            return undefined;
        }
        return fakeEl();
    };
    $.post = function (url, data) {
        posts.push({ url, data });
        return { fail() { return this; } };
    };
    $.extend = function () {
        const args = Array.prototype.slice.call(arguments);
        if (args[0] === true) { args.shift(); }
        return Object.assign.apply(Object, args);
    };

    const storage = {};
    const document = {
        cookie: '',
        querySelector: () => null,
        querySelectorAll: () => [],
        addEventListener: () => {},
        body: null,
    };
    const window = {
        BBAI_TELEMETRY: {
            ajaxUrl: '/wp-admin/admin-ajax.php',
            nonce: 'test-nonce',
            action: 'beepbeepai_telemetry',
            context: { page: 'settings' },
        },
        bbaiTrack: (event) => { posthogCalls.push(event); },
        location: { href: 'http://localhost/wp-admin/admin.php?page=bbai-settings', search: '?page=bbai-settings', pathname: '/wp-admin/admin.php' },
        sessionStorage: {
            getItem: (k) => (k in storage ? storage[k] : null),
            setItem: (k, v) => { storage[k] = String(v); },
            removeItem: (k) => { delete storage[k]; },
        },
        setTimeout: (fn) => { timers.push(fn); return timers.length; },
        clearTimeout: () => {},
        addEventListener: () => {},
        console: { debug() {}, log() {}, warn() {}, error() {} },
        document,
    };
    window.window = window;

    const sandbox = {
        window,
        document,
        jQuery: $,
        navigator: { userAgent: 'jest' },
        location: window.location,
        setTimeout: window.setTimeout,
        clearTimeout: window.clearTimeout,
        Date, JSON, Math, Object, Array, String, Number, Boolean, RegExp, Set, Map, Error,
        encodeURIComponent, decodeURIComponent, parseInt, parseFloat, isNaN,
    };
    vm.createContext(sandbox);
    vm.runInContext(SRC, sandbox, { filename: 'bbai-telemetry.js' });
    readyFns.forEach((fn) => fn.call(document, $));

    function settingsForm(checked) {
        const cb = { type: 'checkbox', name: 'bbai_usage_analytics', checked };
        return {
            tagName: 'FORM',
            closest: () => ({}),
            querySelector: (sel) => (
                sel.indexOf('bbai_usage_analytics') !== -1 && sel.indexOf('checkbox') !== -1 ? cb : null
            ),
        };
    }

    return {
        window,
        posts,
        posthogCalls,
        submitSettings(checked) {
            let prevented = false;
            const evt = { type: 'submit', preventDefault() { prevented = true; } };
            (handlers.submit || []).forEach((fn) => fn.call(settingsForm(checked), evt));
            return { prevented };
        },
        unload() {
            (handlers.beforeunload || []).forEach((fn) => fn.call(window, { type: 'beforeunload' }));
        },
        telemetryPosts() {
            return posts.filter((p) => p.data && p.data.action === 'beepbeepai_telemetry');
        },
        postedEvents() {
            return this.telemetryPosts().reduce((all, p) => all.concat(JSON.parse(p.data.events).map((e) => e.event)), []);
        },
    };
}

describe('bbai-telemetry settings submit and analytics consent', () => {
    test('submitting Settings with analytics unchecked sends no client events', () => {
        const env = makeEnv();
        const posthogBefore = env.posthogCalls.length;
        const res = env.submitSettings(false);
        env.unload();
        env.window.bbaiTelemetry.track('feature_used', { feature: 'after_optout' });
        env.window.bbaiTelemetry.flush();

        expect(res.prevented).toBe(false);
        expect(env.telemetryPosts()).toHaveLength(0);
        expect(env.posthogCalls.length).toBe(posthogBefore);
    });

    test('submitting Settings with analytics checked still tracks settings_saved', () => {
        const env = makeEnv();
        const res = env.submitSettings(true);
        env.unload();

        expect(res.prevented).toBe(false);
        expect(env.telemetryPosts().length).toBeGreaterThan(0);
        expect(env.postedEvents()).toContain('settings_saved');
    });
});
