/**
 * 4.6.147: the 0-credit completion modal's two events go through the normal
 * telemetry pipeline (bbai:analytics -> bbai-telemetry.js -> server bridge
 * queue) with source=completion_modal, plan and credits_remaining=0 like the
 * dashboard/banner sends. The browser never captures to PostHog itself
 * (BBAI_POSTHOG.enabled is false as printed by the plugin); the server bridge
 * forwards only with consent (see CompletionModalCapEventsBridgeTest.php).
 *
 * Runs the shipped admin functions plus bbai-posthog.js and bbai-telemetry.js
 * in a vm sandbox. No network. Run offline: unshare -rn node_modules/.bin/jest tests/js
 */
'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const JS_DIR = path.join(__dirname, '..', '..', 'assets', 'js');
const read = (n) => fs.readFileSync(path.join(JS_DIR, n), 'utf8');
const ADMIN_SRC = read('bbai-admin.js');
const POSTHOG_SRC = read('bbai-posthog.js');
const TELEMETRY_SRC = read('bbai-telemetry.js');

function sliceFn(src, header) {
    const start = src.indexOf(header);
    if (start === -1) { throw new Error('missing ' + header); }
    const end = src.indexOf('\n    }\n', start);
    return src.slice(start, end + '\n    }\n'.length);
}

function makeEnv(posthogEnabled) {
    const docHandlers = {};
    const readyFns = [];
    const posts = [];
    const posthog = [];
    const timers = [];
    const opened = [];
    const $ = function (arg) {
        if (typeof arg === 'function') { readyFns.push(arg); return undefined; }
        return { on() { return this; }, text: () => '', attr: () => '' };
    };
    $.post = function (url, data) { posts.push({ url, data }); return { fail() { return this; } }; };
    $.extend = function () {
        const args = Array.prototype.slice.call(arguments);
        if (args[0] === true) { args.shift(); }
        return Object.assign.apply(Object, args);
    };
    const document = {
        cookie: '',
        referrer: '',
        querySelector: () => null,
        querySelectorAll: () => [],
        getElementsByTagName: () => [],
        createElement: () => ({ setAttribute() {}, addEventListener() {} }),
        addEventListener: (type, fn) => { (docHandlers[type] = docHandlers[type] || []).push(fn); },
        dispatchEvent: (ev) => { (docHandlers[ev.type] || []).forEach((fn) => fn(ev)); return true; },
        body: null,
    };
    const client = {
        __loaded: true,
        config: {},
        capture: (event, props) => posthog.push({ op: 'capture', event, props }),
        identify() {}, alias() {}, register() {}, setPersonProperties() {}, startSessionRecording() {},
    };
    const context = {
        page: 'alt_library',
        is_logged_in: true,
        plan: 'free',
        plan_type: 'free',
        plugin_slug: 'beepbeep-ai-alt-text-generator',
        plugin_version: '4.6.147',
        is_test: false,
    };
    const window = {
        BBAI_POSTHOG: { enabled: posthogEnabled, apiKey: '', apiHost: '', instanceName: 'bbaiPosthog', context: Object.assign({}, context), identify: { id: '' } },
        BBAI_TELEMETRY: { ajaxUrl: '/wp-admin/admin-ajax.php', nonce: 'n', action: 'beepbeepai_telemetry', context: Object.assign({}, context) },
        posthog: client,
        bbaiPosthog: client,
        location: { href: 'https://example.com/wp-admin/admin.php?page=bbai-library', search: '?page=bbai-library', pathname: '/wp-admin/admin.php' },
        sessionStorage: { getItem: () => null, setItem() {}, removeItem() {} },
        localStorage: { getItem: () => null, setItem() {}, removeItem() {} },
        setTimeout: (fn) => { timers.push(fn); return timers.length; },
        clearTimeout() {}, setInterval: () => 0, clearInterval() {}, addEventListener() {},
        console: { debug() {}, log() {}, warn() {}, error() {} },
        bbaiOpenUpgradeModal: (reason, ctx) => { opened.push({ reason, ctx }); return true; },
        document,
    };
    window.window = window;
    function CustomEvent(type, init) { this.type = type; this.detail = init && init.detail; }
    const sandbox = {
        window, document, jQuery: $, $, CustomEvent,
        navigator: { userAgent: 'jest' }, location: window.location,
        setTimeout: window.setTimeout, clearTimeout: window.clearTimeout,
        fetch: () => ({ catch() {} }),
        URLSearchParams, URL, Date, JSON, Math, Object, Array, String, Number, Boolean, RegExp, Set, Map, Error,
        encodeURIComponent, decodeURIComponent, parseInt, parseFloat, isNaN,
    };
    vm.createContext(sandbox);
    vm.runInContext(POSTHOG_SRC, sandbox, { filename: 'bbai-posthog.js' });
    vm.runInContext(TELEMETRY_SRC, sandbox, { filename: 'bbai-telemetry.js' });
    readyFns.forEach((fn) => fn.call(document, $));
    vm.runInContext([
        sliceFn(ADMIN_SRC, '    function dispatchAnalyticsEvent(eventName, payload) {'),
        sliceFn(ADMIN_SRC, '    function openBulkProgressCapUpgradeModal() {'),
        sliceFn(ADMIN_SRC, '    function trackBulkProgressCapShown($modal, state) {'),
        'function saveBulkProgressState(m, s) { return s; }',
        'function openUpgradeModal() { return false; }',
        'this.__capShown = trackBulkProgressCapShown; this.__openCap = openBulkProgressCapUpgradeModal;',
    ].join('\n'), sandbox);
    return {
        sandbox, window, posts, posthog, opened,
        runTimers() { let n = 0; while (timers.length && n++ < 500) { timers.shift()(); } },
        bridged() {
            return posts.filter((p) => p.data && p.data.action === 'beepbeepai_telemetry')
                .flatMap((p) => JSON.parse(p.data.events));
        },
    };
}

describe('completion modal cap events through the telemetry pipeline', () => {
    test('both events reach the server bridge queue with source=completion_modal, plan free, credits_remaining 0', () => {
        const env = makeEnv(false);
        const state = {};
        env.sandbox.__capShown({}, state);
        env.sandbox.__capShown({}, state); // re-render of the same modal
        env.sandbox.__openCap();
        env.runTimers();
        env.window.bbaiTelemetry.flush();
        env.runTimers();

        const events = env.bridged();
        const shown = events.filter((e) => e.event === 'out_of_credits_banner_shown');
        const clicked = events.filter((e) => e.event === 'upgrade_cta_clicked');
        expect(shown).toHaveLength(1);
        expect(clicked).toHaveLength(1);
        for (const e of [shown[0], clicked[0]]) {
            expect(e.properties.source).toBe('completion_modal');
            expect(e.properties.plan).toBe('free');
            expect(e.properties.credits_remaining).toBe(0);
            expect(e.properties.auth_state).toBe('authenticated');
            expect(e.properties.is_signed_in).toBe(true);
            expect(e.properties.quota_type).toBe('monthly_account');
        }
        expect(clicked[0].properties.trigger).toBe('credits_exhausted');
        expect(clicked[0].properties.trigger_location).toBe('completion_modal');
        // upgrade_modal_opened is sent by upgrade-modal.js with source = context.source.
        expect(env.opened).toEqual([{ reason: 'limit_reached', ctx: { source: 'completion_modal', triggerKey: 'credits_exhausted', force: true } }]);
        // Browser capture is off as printed by the plugin: nothing goes to PostHog from the page.
        expect(env.posthog.filter((c) => c.op === 'capture')).toEqual([]);
    });

    test('upgrade-modal.js takes upgrade_modal_opened source from the open context', () => {
        const um = read('upgrade-modal.js');
        expect(um).toContain("trackUpgradeEvent('upgrade_modal_opened', {\n            source: request.context && request.context.source ? request.context.source : 'upgrade-modal',");
    });

});
