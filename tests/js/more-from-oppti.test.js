/**
 * "More from Oppti" panel script: reveals only after a successful generation,
 * dismisses for good, and sends crosssell events only with consent.
 *
 * Runs bbai-more-from-oppti.js in a vm sandbox with a minimal DOM stub.
 * Run offline: unshare -rn sh -c 'ip link set lo up && npx jest --runInBand tests/js'
 */
'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const SRC = fs.readFileSync(
    path.join(__dirname, '..', '..', 'assets', 'js', 'bbai-more-from-oppti.js'),
    'utf8'
);

function el(attrs, extra) {
    const node = Object.assign({
        attrs: Object.assign({}, attrs),
        listeners: {},
        hidden: false,
        getAttribute(k) { return Object.prototype.hasOwnProperty.call(this.attrs, k) ? this.attrs[k] : null; },
        hasAttribute(k) { return Object.prototype.hasOwnProperty.call(this.attrs, k); },
        removeAttribute(k) { delete this.attrs[k]; },
        addEventListener(type, fn) { (this.listeners[type] = this.listeners[type] || []).push(fn); },
        closest() { return this; },
    }, extra || {});
    return node;
}

function makeEnv({ consent, await: awaitSuccess }) {
    const beacons = [];
    const fetches = [];
    const docListeners = {};
    const links = [
        el({ 'data-bbai-mfo-link': '1', 'data-to': 'titles', 'data-link-target': 'installer' }),
        el({ 'data-bbai-mfo-link': '1', 'data-to': 'auditor', 'data-link-target': 'wporg' }),
    ];
    const form = el({ 'data-bbai-mfo-dismiss-form': '1' });
    const panelAttrs = { 'data-bbai-mfo': '1', 'data-from': 'alt', 'data-account-state': 'guest' };
    if (awaitSuccess) {
        panelAttrs['data-await-success'] = '1';
    }
    const panel = el(panelAttrs, {
        hidden: !!awaitSuccess,
        querySelector(sel) { return sel === '[data-bbai-mfo-dismiss-form]' ? form : null; },
        querySelectorAll(sel) { return sel === '[data-bbai-mfo-link]' ? links : []; },
        contains() { return true; },
    });
    class FormData {
        constructor() { this.data = {}; }
        append(k, v) { this.data[k] = v; }
    }
    const window = {
        BBAI_MORE_FROM_OPPTI: {
            ajaxUrl: '/wp-admin/admin-ajax.php',
            dismissAction: 'bbai_more_from_oppti_dismiss',
            dismissNonce: 'n1',
            telemetry: { enabled: consent, action: 'beepbeepai_telemetry', nonce: 'n2' },
        },
        FormData,
        navigator: { sendBeacon(url, data) { beacons.push(data.data); return true; } },
        fetch(url, opts) { fetches.push(opts.body.data); return Promise.resolve(); },
    };
    const document = {
        readyState: 'complete',
        querySelector(sel) { return sel === '[data-bbai-mfo]' ? panel : null; },
        addEventListener(type, fn) { (docListeners[type] = docListeners[type] || []).push(fn); },
    };
    vm.runInNewContext(SRC, { window, document, Number, Object, JSON });
    const events = () => beacons.map((b) => JSON.parse(b.events)[0]);
    const fire = (type, detail) => (docListeners[type] || []).forEach((fn) => fn({ detail }));
    const click = (link) => panel.listeners.click.forEach((fn) => fn({ target: link, preventDefault() {} }));
    const submit = () => {
        let prevented = false;
        form.listeners.submit.forEach((fn) => fn({ preventDefault() { prevented = true; } }));
        return prevented;
    };
    return { panel, links, events, fetches, fire, click, submit };
}

describe('More from Oppti panel script', () => {
    test('awaiting panel stays hidden on a failed or empty run', () => {
        const env = makeEnv({ consent: true, await: true });
        expect(env.panel.hidden).toBe(true);
        env.fire('bbai:generation:finished', { successes: 0, failures: 3 });
        expect(env.panel.hidden).toBe(true);
        env.fire('bbai:generation:finished', {});
        expect(env.panel.hidden).toBe(true);
        expect(env.events()).toEqual([]);
    });

    test('awaiting panel is revealed by a successful generation', () => {
        const env = makeEnv({ consent: true, await: true });
        env.fire('bbai:generation:finished', { successes: 2, failures: 1 });
        expect(env.panel.hidden).toBe(false);
        expect(env.events().map((e) => e.event)).toEqual(['crosssell_panel_shown']);
    });

    test('no events at all without consent', () => {
        const env = makeEnv({ consent: false, await: false });
        env.click(env.links[0]);
        env.submit();
        expect(env.events()).toEqual([]);
        // Dismissal itself is still saved.
        expect(env.fetches).toEqual([{ action: 'bbai_more_from_oppti_dismiss', nonce: 'n1' }]);
    });

    test('with consent: shown, clicked (from/to), dismissed', () => {
        const env = makeEnv({ consent: true, await: false });
        env.click(env.links[1]);
        expect(env.submit()).toBe(true);
        expect(env.panel.hidden).toBe(true);
        const evs = env.events();
        expect(evs.map((e) => e.event)).toEqual(['crosssell_panel_shown', 'crosssell_clicked', 'crosssell_panel_dismissed']);
        expect(evs[0].properties.suggested).toBe('titles,auditor');
        expect(evs[1].properties).toMatchObject({ from_plugin: 'alt', to_plugin: 'auditor', link_target: 'wporg', account_state: 'guest' });
        evs.forEach((e) => {
            expect(JSON.stringify(e.properties)).not.toMatch(/https?:|wordpress\.org|example/);
        });
    });
});
