/**
 * Checkout attribution sent with beepbeepai_create_checkout carries only the
 * checkout context and the UTM tags. The referrer and landing page are not
 * sent (4.6.145), and ids, keys and email in the page context or overrides
 * never reach it.
 *
 * Run offline: unshare -rn sh -c 'ip link set lo up && npx jest --runInBand tests/js'
 */
'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const JS_DIR = path.join(__dirname, '..', '..', 'assets', 'js');
const read = (name) => fs.readFileSync(path.join(JS_DIR, name), 'utf8');

const ALLOWED = [
    'trigger_feature', 'trigger_location', 'source_page', 'target_plan', 'current_plan', 'source',
    'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
];
const FORBIDDEN = ['referrer', 'landing_page', 'account_id', 'user_id', 'license_key', 'license_key_present', 'site_id', 'site_hash', 'site_install_id', 'email'];
const SECRETS = ['LK-CRAFTED-SECRET', 'crafted@example.test', 'acct-123', 'user-456', 'site-789', 'hash-abc'];

const craftedContext = () => ({
    page: 'dashboard',
    plan_type: 'free',
    account_id: 'acct-123',
    user_id: 'user-456',
    license_key: 'LK-CRAFTED-SECRET',
    license_key_present: true,
    site_install_id: 'site-789',
    site_id: 'site-789',
    site_hash: 'hash-abc',
    email: 'crafted@example.test',
});

function fakeJQuery() {
    const $ = function (arg) {
        if (typeof arg === 'function') { return undefined; }
        return { on() { return this; }, text: () => '', attr: () => '' };
    };
    $.post = () => ({ fail() { return this; } });
    $.extend = function () {
        const args = Array.prototype.slice.call(arguments);
        if (args[0] === true) { args.shift(); }
        return Object.assign.apply(Object, args);
    };
    return $;
}

function baseWindow(extra) {
    const document = { cookie: '', querySelector: () => null, querySelectorAll: () => [], addEventListener: () => {} };
    const window = Object.assign({
        location: { href: 'http://localhost/wp-admin/admin.php?page=bbai', search: '?page=bbai', pathname: '/wp-admin/admin.php' },
        sessionStorage: { getItem: () => null, setItem: () => {}, removeItem: () => {} },
        setTimeout: () => 1,
        clearTimeout: () => {},
        addEventListener: () => {},
        console: { debug() {}, log() {}, warn() {}, error() {} },
        document,
    }, extra || {});
    window.window = window;
    return window;
}

function sandboxFor(window) {
    const sandbox = {
        window, document: window.document, jQuery: fakeJQuery(), navigator: { userAgent: 'jest' }, location: window.location,
        setTimeout: window.setTimeout, clearTimeout: window.clearTimeout,
        Date, JSON, Math, Object, Array, String, Number, Boolean, RegExp, Set, Map, Error,
        encodeURIComponent, decodeURIComponent, parseInt, parseFloat, isNaN,
    };
    vm.createContext(sandbox);
    return sandbox;
}

function loadTelemetry() {
    const ctx = craftedContext();
    const window = baseWindow({
        BBAI_TELEMETRY: { ajaxUrl: '/wp-admin/admin-ajax.php', nonce: 'n', context: ctx },
        BBAI_POSTHOG: { context: ctx },
        bbaiAnalytics: { getContext: () => ctx },
    });
    const sandbox = sandboxFor(window);
    vm.runInContext(read('bbai-telemetry.js'), sandbox, { filename: 'bbai-telemetry.js' });
    return window;
}

function expectAllowed(payload) {
    expect(Object.keys(payload).filter((k) => ALLOWED.indexOf(k) === -1)).toEqual([]);
    FORBIDDEN.forEach((k) => expect(payload).not.toHaveProperty(k));
    const json = JSON.stringify(payload);
    SECRETS.forEach((s) => expect(json).not.toContain(s));
}

// Dashboard fallback (no bbaiTelemetry on the page), from the source and the shipped .min.js.
function dashboardFallbackFromSource() {
    const src = read('bbai-dashboard.js');
    const start = src.indexOf('    function getCheckoutAttributionPayload(planName) {');
    const end = src.indexOf('\n    }\n', start);
    expect(start).toBeGreaterThan(-1);
    return '(' + src.slice(start, end + 6).trim() + ')';
}

function dashboardFallbackFromMin() {
    const src = read('bbai-dashboard.min.js');
    const marker = 'function(t){if(window.bbaiTelemetry&&"function"==typeof window.bbaiTelemetry.buildCheckoutAttribution)';
    const start = src.indexOf(marker);
    expect(start).toBeGreaterThan(-1);
    const end = src.indexOf('}(n)', start);
    return '(' + src.slice(start, end + 1) + ')';
}

function runDashboardFallback(fnSource) {
    const ctx = craftedContext();
    const window = baseWindow({ bbaiAnalytics: { getContext: () => ctx }, BBAI_POSTHOG: { context: ctx } });
    const sandbox = sandboxFor(window);
    const fn = vm.runInContext(fnSource, sandbox);
    return fn('growth');
}

describe('checkout attribution allowlist', () => {
    test('telemetry buildCheckoutAttribution keeps only allowed keys and drops referrer and landing page', () => {
        const window = loadTelemetry();
        const payload = window.bbaiTelemetry.buildCheckoutAttribution(Object.assign(craftedContext(), {
            target_plan: 'growth',
            source: 'app',
            utm_source: 'newsletter',
            referrer: 'https://Someone@WWW.Google.com:443/search?q=alt&email=crafted@example.test',
            landing_page: 'https://example.com/wp-admin/admin.php?page=bbai&license_key=LK-CRAFTED-SECRET#top',
        }));

        expectAllowed(payload);
        expect(payload.target_plan).toBe('growth');
        expect(payload.source).toBe('app');
        expect(payload.utm_source).toBe('newsletter');
        expect(payload).not.toHaveProperty('referrer');
        expect(payload).not.toHaveProperty('landing_page');
        expect(JSON.stringify(payload)).not.toMatch(/google|wp-admin/i);
    });

    test('telemetry buildCheckoutAttribution with the dashboard overrides drops page-context identity', () => {
        const window = loadTelemetry();
        const payload = window.bbaiTelemetry.buildCheckoutAttribution({ target_plan: 'growth', source: 'app' });
        expectAllowed(payload);
        expect(payload.target_plan).toBe('growth');
    });

    test('dashboard fallback payload (bbai-dashboard.js) has no identity fields', () => {
        const payload = runDashboardFallback(dashboardFallbackFromSource());
        expectAllowed(payload);
        expect(payload.target_plan).toBe('growth');
        expect(payload.source_page).toBe('dashboard');
    });

    test('dashboard fallback payload (shipped bbai-dashboard.min.js) has no identity fields', () => {
        const payload = runDashboardFallback(dashboardFallbackFromMin());
        expectAllowed(payload);
        expect(payload.target_plan).toBe('growth');
        expect(payload.current_plan).toBe('free');
    });

    test.each([
        ['bbai-dashboard.js', dashboardFallbackFromSource],
        ['bbai-dashboard.min.js', dashboardFallbackFromMin],
    ])('dashboard checkout payload from %s, with telemetry loaded, has no referrer or landing page', (label, extract) => {
        const ctx = Object.assign(craftedContext(), {
            referrer: 'https://www.google.com/search?q=alt',
            landing_page: '/wp-admin/admin.php?page=bbai',
            utm_source: 'newsletter',
        });
        const window = baseWindow({
            BBAI_TELEMETRY: { ajaxUrl: '/wp-admin/admin-ajax.php', nonce: 'n', context: ctx },
            BBAI_POSTHOG: { context: ctx },
            bbaiAnalytics: { getContext: () => ctx },
        });
        const sandbox = sandboxFor(window);
        vm.runInContext(read('bbai-telemetry.js'), sandbox, { filename: 'bbai-telemetry.js' });
        expect(typeof window.bbaiTelemetry.buildCheckoutAttribution).toBe('function');
        const payload = vm.runInContext(extract(), sandbox)('growth');
        expectAllowed(payload);
        expect(payload).not.toHaveProperty('referrer');
        expect(payload).not.toHaveProperty('landing_page');
        expect(payload.target_plan).toBe('growth');
    });

    test.each(['bbai-dashboard.js', 'bbai-dashboard.min.js', 'bbai-telemetry.js', 'bbai-posthog.js'])('%s has no staff or test email domain', (name) => {
        expect(read(name)).not.toContain('qa.oppti.dev');
    });
});
