/**
 * checkout_fallback_payment_link fires exactly once per Payment Link fallback,
 * from the dashboard's Continue to payment panel (source and shipped .min.js),
 * through bbai-telemetry.js to the server bridge (not posthog-js too), with
 * plugin, source, reason and plan, and no site address, URL or email.
 * The Payment Link itself gets client_reference_id plus the checkout utm_* tags.
 *
 * Run offline: unshare -rn node_modules/.bin/jest tests/js
 */
'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const JS_DIR = path.join(__dirname, '..', '..', 'assets', 'js');
const read = (name) => fs.readFileSync(path.join(JS_DIR, name), 'utf8');
const POSTHOG_SRC = read('bbai-posthog.js');
const TELEMETRY_SRC = read('bbai-telemetry.js');
const DASH_SRC = read('bbai-dashboard.js');
const DASH_MIN = read('bbai-dashboard.min.js');

const HOST = 'leaky-shop.example.org';
const PAGE = 'https://' + HOST + '/wp-admin/admin.php?page=bbai';
const INSTALL_ID = '61cf62dcdec8b141d4415c712d64be5953f8f35b0ec8ed67137508be91c9592e';
const LINK = 'https://buy.stripe.com/dRm28s4rc5Raf0GbY77ss02';
const SESSION_URL = 'https://checkout.stripe.com/c/pay/cs_test_a1b2c3';
const EVENT = 'checkout_fallback_payment_link';

const FORBIDDEN_KEYS = ['site_url', 'host', 'site_host', 'domain', 'home_url', 'url', 'fallback_url', 'payment_link',
    'email', 'admin_email', 'user_email', 'endpoint', 'error_code', 'timestamp', '$current_url', '$host', '$pathname',
    '$referrer', 'referrer', 'landing_page'];
const URL_VALUE = /([a-z][a-z0-9+.-]*:\/\/|^\/\/|^\/\S*$|\bwww\.|localhost|\b[a-z0-9-]+(\.[a-z0-9-]+)*\.[a-z]{2,}\b|::1|\b\d{1,3}(\.\d{1,3}){3}\b|@)/i;

// --- extract the dashboard functions under test -------------------------
function sliceFn(src, header, endMarker) {
    const start = src.indexOf(header);
    if (start === -1) { throw new Error('missing ' + header); }
    const end = src.indexOf(endMarker, start);
    if (end === -1) { throw new Error('missing end for ' + header); }
    return src.slice(start, end + endMarker.length);
}

function sourceFns() {
    return [
        sliceFn(DASH_SRC, '    function isAllowedCheckoutUrl(url) {', '\n    }\n'),
        sliceFn(DASH_SRC, '    function showCheckoutContinuePanel(opts) {', '\n        return panel;\n    }\n'),
        sliceFn(DASH_SRC, '    function dispatchCheckoutFallbackAnalytics(planName, reason) {', '\n    }\n'),
        sliceFn(DASH_SRC, '    function decoratePaymentLinkUrl(url) {', '\n        return parsed.toString();\n    }\n'),
        'globalThis.__panel = showCheckoutContinuePanel;',
    ].join('\n');
}

function minFns() {
    const panelAt = DASH_MIN.indexOf('Checkout could not start automatically');
    const kStart = DASH_MIN.lastIndexOf('function k(t){', panelAt);
    const kEnd = DASH_MIN.indexOf('document.body.appendChild(i),i}', kStart);
    if (kStart === -1 || kEnd === -1) { throw new Error('min panel not found'); }
    const k = DASH_MIN.slice(kStart, kEnd + 'document.body.appendChild(i),i}'.length);
    const oStart = DASH_MIN.indexOf('function O(t,e){try{document.dispatchEvent');
    const dStart = DASH_MIN.indexOf('function bbaiDecoratePaymentLink(t){');
    const dEnd = DASH_MIN.indexOf('e.toString()}', dStart) + 'e.toString()}'.length;
    const xStart = DASH_MIN.indexOf('function x(t){try{var e=new URL(');
    const xEnd = DASH_MIN.indexOf('catch(t){return!1}}', xStart) + 'catch(t){return!1}}'.length;
    return [
        k,
        DASH_MIN.slice(oStart, dStart),
        DASH_MIN.slice(dStart, dEnd),
        DASH_MIN.slice(xStart, xEnd),
        'function S(){} function a(s){return s} function _(){} function v(){return !1} var L=2000;',
        'globalThis.__panel = k;',
    ].join('\n');
}

// --- sandbox: bbai-posthog.js + bbai-telemetry.js with a fake DOM -----------
function makeEnv(dashboardCode) {
    const docHandlers = {};
    const readyFns = [];
    const posts = [];
    const posthog = [];
    const timers = [];
    const elements = [];

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

    function makeEl(tag) {
        const el = {
            tagName: String(tag).toUpperCase(), style: {}, children: [], attrs: {}, listeners: {},
            setAttribute(k, v) { this.attrs[k] = String(v); },
            getAttribute(k) { return this.attrs[k]; },
            addEventListener(t, fn) { (this.listeners[t] = this.listeners[t] || []).push(fn); },
            appendChild(c) { this.children.push(c); return c; },
            remove() {},
        };
        elements.push(el);
        return el;
    }
    const document = {
        cookie: '',
        referrer: '',
        visibilityState: 'visible',
        querySelector: () => null,
        querySelectorAll: () => [],
        getElementById: () => null,
        getElementsByTagName: () => [],
        createElement: makeEl,
        addEventListener: (type, fn) => { (docHandlers[type] = docHandlers[type] || []).push(fn); },
        dispatchEvent: (evt) => { (docHandlers[evt.type] || []).forEach((fn) => fn(evt)); return true; },
        body: null,
    };
    document.body = makeEl('body');

    class CustomEvent {
        constructor(type, init) { this.type = type; this.detail = init && init.detail; }
    }

    const ctx = {
        page: 'dashboard',
        site_install_id: INSTALL_ID,
        site_hash: 'aaaabbbbccccddddeeeeffff0000111122223333444455556666777788889999',
        site_url: 'https://' + HOST + '/',
        host: HOST,
        plugin_slug: 'beepbeep-ai-alt-text-generator',
        plugin_version: '4.6.145',
        is_test: false,
    };
    const client = {
        __loaded: true,
        config: {},
        capture: (event, props) => posthog.push({ op: 'capture', event, props }),
        identify() {}, alias() {}, register() {}, setPersonProperties() {}, startSessionRecording() {},
    };
    const storage = {};
    const window = {
        BBAI_POSTHOG: { enabled: true, apiKey: 'phc_test', apiHost: 'http://127.0.0.1:9', instanceName: 'bbaiPosthog', context: Object.assign({}, ctx), identify: { id: '' } },
        BBAI_TELEMETRY: { ajaxUrl: '/wp-admin/admin-ajax.php', nonce: 'n', action: 'beepbeepai_telemetry', context: Object.assign({}, ctx) },
        posthog: client,
        bbaiPosthog: client,
        location: { href: PAGE, search: '?page=bbai', pathname: '/wp-admin/admin.php' },
        bbai_ajax: {
            ajaxurl: '/wp-admin/admin-ajax.php',
            nonce: 'n',
            payment_link_params: { client_reference_id: 'alt_text_' + INSTALL_ID, utm_source: 'wporg', utm_campaign: 'first_touch' },
        },
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
        navigator: { clipboard: null },
        document,
    };
    window.window = window;

    const sandbox = {
        window, document, jQuery: $, CustomEvent,
        navigator: { userAgent: 'jest' },
        location: window.location,
        setTimeout: window.setTimeout,
        clearTimeout: window.clearTimeout,
        URLSearchParams, URL,
        Date, JSON, Math, Object, Array, String, Number, Boolean, RegExp, Set, Map, Error,
        encodeURIComponent, decodeURIComponent, parseInt, parseFloat, isNaN,
        __: (s) => s,
        removeCheckoutContinuePanel() {},
        trackCheckoutEvent() {},
    };
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    vm.runInContext(POSTHOG_SRC, sandbox, { filename: 'bbai-posthog.js' });
    vm.runInContext(TELEMETRY_SRC, sandbox, { filename: 'bbai-telemetry.js' });
    readyFns.forEach((fn) => fn.call(document, $));
    vm.runInContext(dashboardCode, sandbox, { filename: 'dashboard-panel.js' });

    function bridged() {
        window.bbaiTelemetry.flush();
        let n = 0;
        while (timers.length && n++ < 500) { timers.shift()(); }
        const out = [];
        posts.forEach((p) => {
            const rows = JSON.parse(p.data.events || '[]');
            rows.forEach((r) => out.push(r));
        });
        return out;
    }
    function links() {
        return elements.filter((el) => el.tagName === 'A').map((el) => el.href);
    }
    return { window, posthog, bridged, links, panel: sandbox.__panel, sandbox };
}

function violations(obj) {
    const out = [];
    Object.keys(obj || {}).forEach((k) => {
        const v = obj[k];
        if (FORBIDDEN_KEYS.includes(k.toLowerCase())) { out.push('key ' + k); }
        if (typeof v === 'string' && URL_VALUE.test(v) && !/version$/i.test(k)) { out.push('value ' + k + '=' + v); }
    });
    return out;
}

const VARIANTS = [
    ['bbai-dashboard.js', sourceFns],
    ['bbai-dashboard.min.js', minFns],
];

describe.each(VARIANTS)('Payment Link fallback from %s', (label, build) => {
    test('one event per fallback, with plugin, source, reason and plan only', () => {
        const env = makeEnv(build());
        env.panel({ message: 'Server error', url: LINK, plan: 'pro', retry() {}, reason: 'http_502' });
        const events = env.bridged().filter((r) => r.event === EVENT);
        expect(events).toHaveLength(1);
        const p = events[0].properties;
        expect(p.plugin).toBe('alt_text');
        expect(p.reason).toBe('http_502');
        expect(p.plan).toBe('pro');
        expect(typeof p.source).toBe('string');
        expect(p.source).not.toBe('');
        expect(p.source).not.toBe('checkout');
        expect(p.source).toBe('dashboard'); // no button known: the screen
        expect(violations(p)).toEqual([]);
        expect(JSON.stringify(p)).not.toContain(HOST);
        expect(JSON.stringify(p)).not.toContain('buy.stripe.com');
        // Server bridge only: posthog-js does not capture it as well.
        expect(env.posthog.filter((c) => c.event === EVENT)).toHaveLength(0);
    });

    test('source is the button the checkout came from when known', () => {
        const env = makeEnv(build());
        env.window.bbaiTelemetry.track('upgrade_cta_clicked', { trigger_location: 'usage_banner', source_page: 'dashboard', target_plan: 'starter' });
        env.panel({ message: 'x', url: LINK, plan: 'starter', retry() {}, reason: 'missing_checkout_url' });
        const events = env.bridged().filter((r) => r.event === EVENT);
        expect(events).toHaveLength(1);
        expect(events[0].properties.source).toBe('usage_banner');
        expect(events[0].properties.source_page).toBe('dashboard');
    });

    test('a repeat panel for the same fallback does not fire twice', () => {
        const env = makeEnv(build());
        env.panel({ message: 'x', url: LINK, plan: 'pro', retry() {}, reason: 'checkout_session_failed' });
        env.panel({ message: 'x', url: LINK, plan: 'pro', retry() {}, reason: 'checkout_session_failed' });
        expect(env.bridged().filter((r) => r.event === EVENT)).toHaveLength(1);
    });

    test('no event for a Checkout Session panel or without a Payment Link', () => {
        const env = makeEnv(build());
        env.panel({ url: SESSION_URL, sessionId: 'cs_test_a1b2c3', plan: 'pro' });
        env.panel({ message: 'Rate limited', url: '', plan: 'pro', retry() {}, reason: 'rate_limited' });
        expect(env.bridged().filter((r) => r.event === EVENT)).toHaveLength(0);
        expect(env.links()).toEqual([SESSION_URL]);
    });

    test('the Payment Link gets client_reference_id and utm_* without losing its own params', () => {
        const env = makeEnv(build());
        env.window.bbaiTelemetry.buildCheckoutAttribution = () => ({ utm_source: 'newsletter', utm_medium: 'email', source: 'app' });
        env.panel({ message: 'x', url: LINK + '?prefilled_promo_code=SAVE&utm_term=kept', plan: 'pro', retry() {}, reason: 'http_500' });
        const href = env.links()[0];
        const u = new URL(href);
        expect(u.hostname).toBe('buy.stripe.com');
        expect(u.searchParams.get('client_reference_id')).toBe('alt_text_' + INSTALL_ID);
        expect(u.searchParams.get('utm_source')).toBe('newsletter');
        expect(u.searchParams.get('utm_medium')).toBe('email');
        expect(u.searchParams.get('utm_campaign')).toBe('first_touch');
        expect(u.searchParams.get('utm_term')).toBe('kept');
        expect(u.searchParams.get('prefilled_promo_code')).toBe('SAVE');
        expect(href).not.toContain(HOST);
    });
});

describe('call sites', () => {
    test('the event is dispatched only from the panel, and every error panel passes a reason', () => {
        expect(DASH_SRC.split('dispatchCheckoutFallbackAnalytics(').length - 1).toBe(2); // definition + panel
        expect(DASH_MIN.split('checkout_fallback_payment_link').length - 1).toBe(1);
        expect(DASH_MIN.split('t.reason&&O(t.plan,t.reason)').length - 1).toBe(1);
        const srcPanels = DASH_SRC.match(/showCheckoutContinuePanel\(\{[\s\S]*?\}\);/g) || [];
        srcPanels.filter((c) => /message:/.test(c)).forEach((c) => expect(c).toMatch(/reason:/));
        const minPanels = DASH_MIN.match(/k\(\{message:[^}]*\}\)/g) || [];
        expect(minPanels.length).toBeGreaterThanOrEqual(6);
        minPanels.forEach((c) => expect(c).toMatch(/reason:/));
    });
});
