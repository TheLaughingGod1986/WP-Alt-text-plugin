/**
 * A sign-in, sign-up or /auth/me response can carry site_id / site_hash (or a
 * site object). None of those values may reach PostHog: the browser layer
 * must use only this site's own install id and site hash.
 *
 * Runs bbai-posthog.js and bbai-telemetry.js in one vm sandbox with a fake
 * PostHog client and a minimal jQuery/DOM stub. No network.
 * Run offline: unshare -rn sh -c 'ip link set lo up && npx jest --runInBand tests/js'
 */
'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const JS_DIR = path.join(__dirname, '..', '..', 'assets', 'js');
const POSTHOG_SRC = fs.readFileSync(path.join(JS_DIR, 'bbai-posthog.js'), 'utf8');
const TELEMETRY_SRC = fs.readFileSync(path.join(JS_DIR, 'bbai-telemetry.js'), 'utf8');

// Fake credential-shaped value a backend response might carry. Not a real key.
const FAKE_KEY = 'fakesitekey_0009_d41d8cd98f00b204e9800998ecf8427e';
// Local values the PHP side prints into the page.
const LOCAL_INSTALL_ID = '61cf62dcdec8b141d4415c712d64be5953f8f35b0ec8ed67137508be91c9592e';
const LOCAL_SITE_HASH = 'aaaabbbbccccddddeeeeffff0000111122223333444455556666777788889999';

function makeEnv() {
    const docHandlers = {};
    const winHandlers = {};
    const readyFns = [];
    const posts = [];
    const posthog = [];
    const timers = [];

    const fakeEl = () => ({
        on() { return this; },
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
        referrer: '',
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
    };

    const window = {
        BBAI_POSTHOG: {
            enabled: true,
            apiKey: 'phc_test',
            apiHost: 'http://127.0.0.1:9',
            instanceName: 'bbaiPosthog',
            context: {
                page: 'dashboard',
                site_install_id: LOCAL_INSTALL_ID,
                site_hash: LOCAL_SITE_HASH,
            },
            identify: { id: '', person_properties: {} },
        },
        BBAI_TELEMETRY: {
            ajaxUrl: '/wp-admin/admin-ajax.php',
            nonce: 'test-nonce',
            action: 'beepbeepai_telemetry',
            context: {
                page: 'dashboard',
                site_install_id: LOCAL_INSTALL_ID,
                site_id: LOCAL_INSTALL_ID,
                site_hash: LOCAL_SITE_HASH,
            },
        },
        bbaiPosthog: client,
        posthog: client,
        location: { href: 'http://localhost/wp-admin/admin.php?page=bbai', search: '?page=bbai', pathname: '/wp-admin/admin.php' },
        sessionStorage: {
            getItem: (k) => (k in storage ? storage[k] : null),
            setItem: (k, v) => { storage[k] = String(v); },
            removeItem: (k) => { delete storage[k]; },
        },
        localStorage: {
            getItem: () => null,
            setItem: () => {},
            removeItem: () => {},
        },
        setTimeout: (fn) => { timers.push(fn); return timers.length; },
        clearTimeout: () => {},
        setInterval: () => 0,
        clearInterval: () => {},
        addEventListener: (type, fn) => { (winHandlers[type] = winHandlers[type] || []).push(fn); },
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
        URLSearchParams,
        Date, JSON, Math, Object, Array, String, Number, Boolean, RegExp, Set, Map, Error,
        encodeURIComponent, decodeURIComponent, parseInt, parseFloat, isNaN,
    };
    vm.createContext(sandbox);
    vm.runInContext(POSTHOG_SRC, sandbox, { filename: 'bbai-posthog.js' });
    vm.runInContext(TELEMETRY_SRC, sandbox, { filename: 'bbai-telemetry.js' });
    readyFns.forEach((fn) => fn.call(document, $));

    return {
        window,
        posts,
        posthog,
        signIn(user) {
            (docHandlers['alttext:auth-success'] || []).forEach((fn) => fn({ detail: { user, token: 't' } }));
        },
        runTimers() {
            while (timers.length) { timers.shift()(); }
        },
        everything() {
            return JSON.stringify({ posthog, posts });
        },
    };
}

function poisonedUser(withAccount) {
    const user = {
        email: 'user@example.test',
        plan: 'free',
        site_id: FAKE_KEY,
        site_hash: FAKE_KEY,
        siteHash: FAKE_KEY,
        site_install_id: FAKE_KEY,
        install_id: FAKE_KEY,
        site: { id: FAKE_KEY, _id: FAKE_KEY, site_hash: FAKE_KEY },
    };
    if (withAccount) {
        user.id = 'acct_0009';
    }
    return user;
}

describe('backend-supplied site ids never reach PostHog', () => {
    test('sign-in with an account id: identify, alias, person and event props use local ids only', () => {
        const env = makeEnv();
        env.signIn(poisonedUser(true));
        env.window.bbaiTrack('dashboard_viewed', { site_id: FAKE_KEY, site_hash: FAKE_KEY });
        env.window.bbaiTelemetry.track('feature_used', { feature: 'x', site_id: FAKE_KEY, site_hash: FAKE_KEY, site: { id: FAKE_KEY } });
        env.window.bbaiTelemetry.flush();
        env.runTimers();

        expect(env.posthog.length).toBeGreaterThan(0);
        expect(env.everything()).not.toContain(FAKE_KEY);

        const ctx = env.window.bbaiAnalytics.getContext();
        expect(ctx.site_id).toBe(LOCAL_INSTALL_ID);
        expect(ctx.site_install_id).toBe(LOCAL_INSTALL_ID);
        expect(ctx.site_hash).toBe(LOCAL_SITE_HASH);

        const person = env.posthog.filter((c) => c.op === 'setPersonProperties');
        expect(person.length).toBeGreaterThan(0);
        person.forEach((c) => {
            expect(c.props.site_id).toBe(LOCAL_INSTALL_ID);
            expect(c.props.site_hash).toBe(LOCAL_SITE_HASH);
        });
        env.posthog.filter((c) => c.op === 'capture').forEach((c) => {
            expect(c.props.site_id).toBe(LOCAL_INSTALL_ID);
            expect(c.props.site_install_id).toBe(LOCAL_INSTALL_ID);
            expect(c.props.site_hash).toBe(LOCAL_SITE_HASH);
        });
    });

    test('sign-in without an account id: the identify id falls back to the local install id, not the response', () => {
        const env = makeEnv();
        env.signIn(poisonedUser(false));
        env.runTimers();

        const ids = env.posthog.filter((c) => c.op === 'identify').map((c) => c.id);
        ids.forEach((id) => expect(id).toBe(LOCAL_INSTALL_ID));
        expect(env.everything()).not.toContain(FAKE_KEY);
    });

    test('client events queued for the server bridge carry local site ids only', () => {
        const env = makeEnv();
        env.signIn(poisonedUser(true));
        env.window.bbaiAnalytics.updateContext({ site_id: FAKE_KEY, site_hash: FAKE_KEY });
        env.window.bbaiTelemetry.track('settings_saved', { site_id: FAKE_KEY, site_hash: FAKE_KEY, siteId: FAKE_KEY });
        env.window.bbaiTelemetry.flush();
        env.runTimers();

        const bridged = env.posts.filter((p) => p.data && p.data.action === 'beepbeepai_telemetry');
        expect(bridged.length).toBeGreaterThan(0);
        bridged.forEach((p) => {
            expect(p.data.events).not.toContain(FAKE_KEY);
            JSON.parse(p.data.events).forEach((e) => {
                expect(e.properties.site_id).toBe(LOCAL_INSTALL_ID);
                expect(e.properties.site_hash).toBe(LOCAL_SITE_HASH);
            });
        });
        expect(env.everything()).not.toContain(FAKE_KEY);
    });
});
