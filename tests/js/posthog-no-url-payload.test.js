/**
 * No browser path sends the site address, a host, a page path or any other
 * URL-shaped value to PostHog: super properties (register), event captures,
 * person properties, the server-bridge queue, and posthog-js's own automatic
 * properties ($current_url, $host, $pathname, $referrer, $referring_domain,
 * $initial_*) through sanitize_properties / before_send.
 *
 * Runs bbai-posthog.js and bbai-telemetry.js in a vm sandbox with a fake
 * PostHog client. No network.
 * Run offline: unshare -rn node_modules/.bin/jest tests/js
 */
'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const JS_DIR = path.join(__dirname, '..', '..', 'assets', 'js');
const POSTHOG_SRC = fs.readFileSync(path.join(JS_DIR, 'bbai-posthog.js'), 'utf8');
const TELEMETRY_SRC = fs.readFileSync(path.join(JS_DIR, 'bbai-telemetry.js'), 'utf8');

const HOST = 'leaky-shop.example.org';
const PAGE = 'https://' + HOST + '/wp-admin/admin.php?page=bbai';
const LOCAL_INSTALL_ID = '61cf62dcdec8b141d4415c712d64be5953f8f35b0ec8ed67137508be91c9592e';
const LOCAL_SITE_HASH = 'aaaabbbbccccddddeeeeffff0000111122223333444455556666777788889999';

const FORBIDDEN_KEYS = ['site_url', 'host', 'site_host', 'domain', 'home_url', '$current_url', '$host', '$pathname',
    '$referrer', '$referring_domain', 'current_url', 'pathname', 'referrer', 'referring_domain', 'url', 'landing_page',
    '$initial_current_url', '$initial_referrer', '$initial_referring_domain', '$initial_host', '$initial_pathname'];
const URL_VALUE = /([a-z][a-z0-9+.-]*:\/\/|^\/\/|^\/\S*$|\bwww\.|localhost|\b[a-z0-9-]+(\.[a-z0-9-]+)*\.[a-z]{2,}\b|::1|\b\d{1,3}(\.\d{1,3}){3}\b)/i;

function violations(obj, prefix) {
    const out = [];
    Object.keys(obj || {}).forEach((k) => {
        const v = obj[k];
        const lower = k.toLowerCase();
        if (FORBIDDEN_KEYS.includes(lower) || FORBIDDEN_KEYS.includes('$' + lower.replace(/^\$+/, ''))) {
            out.push('key ' + prefix + k);
        }
        if (v && typeof v === 'object') {
            out.push(...violations(v, prefix + k + '.'));
        } else if (typeof v === 'string' && URL_VALUE.test(v) && !/version$/i.test(k)) {
            out.push('value ' + prefix + k + '=' + v);
        }
    });
    return out;
}

function makeEnv(options) {
    const opts = options || {};
    const docHandlers = {};
    const readyFns = [];
    const posts = [];
    const posthog = [];
    const timers = [];
    const initCalls = [];

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

    const storage = {};
    const document = {
        cookie: '',
        referrer: 'https://search.example.com/?q=alt+text',
        querySelector: () => null,
        querySelectorAll: () => [],
        getElementsByTagName: () => [],
        createElement: () => ({ setAttribute() {}, addEventListener() {} }),
        addEventListener: (type, fn) => { (docHandlers[type] = docHandlers[type] || []).push(fn); },
        body: null,
    };

    const client = {
        __loaded: true,
        config: {},
        capture: (event, props) => posthog.push({ op: 'capture', event, props }),
        identify: (id) => posthog.push({ op: 'identify', id }),
        alias: (a, b) => posthog.push({ op: 'alias', a, b }),
        register: (props) => posthog.push({ op: 'register', props }),
        setPersonProperties: (props) => posthog.push({ op: 'setPersonProperties', props }),
        startSessionRecording: () => posthog.push({ op: 'startSessionRecording' }),
    };
    const library = Object.assign({}, client, {
        __loaded: false,
        config: null,
        init(key, cfg) { initCalls.push(cfg); this.__loaded = true; this.config = cfg; },
    });

    // A page printed by an older build (or a cached page) still carrying the site address.
    const staleContext = {
        page: 'dashboard',
        site_install_id: LOCAL_INSTALL_ID,
        site_id: LOCAL_INSTALL_ID,
        site_hash: LOCAL_SITE_HASH,
        site_url: 'https://' + HOST + '/',
        host: HOST,
        site_host: HOST,
        referrer: 'https://search.example.com/',
        landing_page: '/wp-admin/admin.php',
        plugin_slug: 'beepbeep-ai-alt-text-generator',
        plugin_version: '4.6.145',
        wp_version: '6.8.3',
        is_test: false,
    };

    const window = {
        BBAI_POSTHOG: {
            enabled: true,
            apiKey: 'phc_test',
            apiHost: 'http://127.0.0.1:9',
            instanceName: 'bbaiPosthog',
            sessionRecordingEnabled: true,
            context: Object.assign({}, staleContext),
            identify: { id: '', person_properties: { site_url: 'https://' + HOST + '/', plan: 'free' } },
        },
        BBAI_TELEMETRY: {
            ajaxUrl: '/wp-admin/admin-ajax.php',
            nonce: 'test-nonce',
            action: 'beepbeepai_telemetry',
            context: Object.assign({}, staleContext),
        },
        posthog: opts.uninitialised ? library : client,
        location: { href: PAGE, search: '?page=bbai&utm_source=newsletter', pathname: '/wp-admin/admin.php' },
        bbai_ajax: { ajaxurl: '/wp-admin/admin-ajax.php', nonce: 'n' },
        sessionStorage: {
            getItem: (k) => (k in storage ? storage[k] : null),
            setItem: (k, v) => { storage[k] = String(v); },
            removeItem: (k) => { delete storage[k]; },
        },
        localStorage: { getItem: () => null, setItem: () => {}, removeItem: () => {} },
        setTimeout: (fn) => { timers.push(fn); return timers.length; },
        clearTimeout: () => {},
        setInterval: () => 0,
        clearInterval: () => {},
        addEventListener: () => {},
        console: { debug() {}, log() {}, warn() {}, error() {} },
        document,
    };
    if (!opts.uninitialised) {
        window.bbaiPosthog = client;
    }
    window.window = window;
    const localFetches = [];

    const sandbox = {
        window, document, jQuery: $,
        navigator: { userAgent: 'jest' },
        location: window.location,
        setTimeout: window.setTimeout,
        clearTimeout: window.clearTimeout,
        fetch: (url, init) => { localFetches.push({ url, body: init && init.body }); return { catch() {} }; },
        URLSearchParams, URL,
        Date, JSON, Math, Object, Array, String, Number, Boolean, RegExp, Set, Map, Error,
        encodeURIComponent, decodeURIComponent, parseInt, parseFloat, isNaN,
    };
    vm.createContext(sandbox);
    vm.runInContext(POSTHOG_SRC, sandbox, { filename: 'bbai-posthog.js' });
    vm.runInContext(TELEMETRY_SRC, sandbox, { filename: 'bbai-telemetry.js' });
    readyFns.forEach((fn) => fn.call(document, $));

    return {
        window, posts, posthog, initCalls, localFetches,
        runTimers() { let n = 0; while (timers.length && n++ < 500) { timers.shift()(); } },
    };
}

describe('browser analytics never send URLs or hosts', () => {
    test('register, capture, person properties and bridge events are clean; site_hash and plugin_slug stay', () => {
        const env = makeEnv();
        env.window.bbaiAnalytics.updateContext({ current_url: PAGE, checkout_url: 'https://pay.example.net/x', tab: 'library' });
        env.window.bbaiTrack('plugin_opened', { $current_url: PAGE, $host: HOST, $pathname: '/wp-admin/admin.php', source: 'menu' });
        env.window.bbaiIdentify('acct_1', { website: PAGE, plan: 'free', $set_once: { $initial_referrer: PAGE } });
        env.window.bbaiTelemetry.track('dashboard_viewed', { page_url: PAGE, referrer: PAGE, note: 'from ' + HOST });
        env.window.bbaiTelemetry.track('feature_used', { feature: 'bulk', return_to: PAGE });
        env.window.bbaiTelemetry.flush();
        env.runTimers();

        const sent = env.posthog.filter((c) => c.props);
        expect(sent.some((c) => c.op === 'register')).toBe(true);
        expect(sent.some((c) => c.op === 'capture' && c.event === 'plugin_opened')).toBe(true);
        expect(sent.some((c) => c.op === 'setPersonProperties')).toBe(true);
        sent.forEach((c) => {
            expect(violations(c.props, c.op + ':')).toEqual([]);
        });
        sent.filter((c) => c.op === 'capture').forEach((c) => {
            expect(c.props.site_hash).toBe(LOCAL_SITE_HASH);
            expect(c.props.plugin_slug).toBe('beepbeep-ai-alt-text-generator');
            expect(typeof c.props.is_test).toBe('boolean');
        });
        sent.filter((c) => c.op === 'setPersonProperties').forEach((c) => {
            expect(c.props.plan).toBe('free');
            expect(typeof c.props.is_test).toBe('boolean');
        });

        const bridged = env.posts.filter((p) => p.data && p.data.action === 'beepbeepai_telemetry');
        expect(bridged.length).toBeGreaterThan(0);
        bridged.forEach((p) => {
            JSON.parse(p.data.events).forEach((e) => {
                expect(violations(e.properties, e.event + ':')).toEqual([]);
                expect(e.properties.site_hash).toBe(LOCAL_SITE_HASH);
                expect(e.properties.plugin_slug).toBe('beepbeep-ai-alt-text-generator');
            });
        });
        expect(JSON.stringify({ a: env.posthog, b: bridged })).not.toContain(HOST);
        expect(env.posthog.some((c) => c.op === 'startSessionRecording')).toBe(false);
    });

    test('posthog-js init disables URL capture and strips its automatic properties', () => {
        const env = makeEnv({ uninitialised: true });
        env.runTimers();
        expect(env.initCalls.length).toBe(1);
        const cfg = env.initCalls[0];
        expect(cfg.autocapture).toBe(false);
        expect(cfg.capture_pageview).toBe(false);
        expect(cfg.capture_pageleave).toBe(false);
        expect(cfg.disable_session_recording).toBe(true);
        expect(cfg.save_referrer).toBe(false);
        expect(cfg.capture_performance).toBe(false);
        expect(Array.isArray(cfg.property_denylist)).toBe(true);
        ['$current_url', '$host', '$pathname', '$referrer', '$referring_domain'].forEach((k) => expect(cfg.property_denylist).toContain(k));

        const auto = {
            $current_url: PAGE, $host: HOST, $pathname: '/wp-admin/admin.php', $referrer: PAGE,
            $referring_domain: HOST, $initial_current_url: PAGE, $initial_referring_domain: HOST,
            $lib: 'web', $browser: 'Chrome', site_hash: LOCAL_SITE_HASH, plugin_slug: 'beepbeep-ai-alt-text-generator',
            token: 'phc_test', distinct_id: LOCAL_INSTALL_ID,
        };
        const sanitized = cfg.sanitize_properties(Object.assign({}, auto), 'plugin_opened');
        expect(violations(sanitized, '')).toEqual([]);
        expect(sanitized.site_hash).toBe(LOCAL_SITE_HASH);
        expect(sanitized.plugin_slug).toBe('beepbeep-ai-alt-text-generator');
        expect(sanitized.$browser).toBe('Chrome');

        const event = cfg.before_send({
            event: 'plugin_opened',
            properties: Object.assign({}, auto, { $set: { $current_url: PAGE, plan: 'free' }, $set_once: { $initial_referrer: PAGE } }),
            $set: { $current_url: PAGE, $host: HOST, plan: 'free' },
            $set_once: { $initial_current_url: PAGE, $initial_referring_domain: HOST },
        });
        expect(violations(event, '')).toEqual([]);
        expect(event.$set.plan).toBe('free');
        expect(event.properties.site_hash).toBe(LOCAL_SITE_HASH);
        expect(JSON.stringify(event)).not.toContain(HOST);
    });

    test('first-touch referrer and landing page stay out of the analytics context', () => {
        const env = makeEnv();
        env.runTimers();
        const ctx = env.window.bbaiAnalytics.getContext();
        expect(ctx.referrer).toBeUndefined();
        expect(ctx.landing_page).toBeUndefined();
        expect(ctx.utm_source).toBe('newsletter');
        env.posthog.filter((c) => c.op === 'register').forEach((c) => {
            expect(violations(c.props, 'register:')).toEqual([]);
        });
    });
});
