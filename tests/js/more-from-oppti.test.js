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

function makeEnv({ consent, await: awaitSuccess, rows }) {
    const beacons = [];
    const fetches = [];
    const docListeners = {};
    const links = (rows || ['titles', 'auditor']).map((to) => el({
        'data-bbai-mfo-link': '1',
        'data-to': to,
        'data-link-target': to === 'titles' ? 'installer' : 'wporg',
    }));
    // Older Titles promos printed by the Dashboard (line and "next audit" card).
    const legacy = [
        el({ 'data-bbai-titles-cross-sell': '1' }, { style: {} }),
        el({ 'data-bbai-next-audit': 'opptiai-titles' }, { style: {} }),
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
        querySelectorAll(sel) {
            return sel === '[data-bbai-titles-cross-sell],[data-bbai-next-audit="opptiai-titles"]' ? legacy : [];
        },
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
    return { panel, links, legacy, events, fetches, fire, click, submit };
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

    test('before success the old Titles promo stays; a same-page success leaves only the panel', () => {
        const env = makeEnv({ consent: false, await: true });
        const visibleTitles = () => (env.panel.hidden ? 0 : env.links.filter((l) => l.getAttribute('data-to') === 'titles').length)
            + env.legacy.filter((n) => !n.hidden).length;
        expect(env.legacy.map((n) => n.hidden)).toEqual([false, false]);
        env.fire('bbai:generation:finished', { successes: 0, failures: 2 });
        expect(env.legacy.map((n) => n.hidden)).toEqual([false, false]);
        env.fire('bbai:generation:finished', { successes: 1 });
        expect(env.panel.hidden).toBe(false);
        expect(env.legacy.map((n) => n.hidden)).toEqual([true, true]);
        expect(env.legacy.map((n) => n.style.display)).toEqual(['none', 'none']);
        // The old card is a single block; the line and the card never both print, so one Titles promo remains.
        expect(visibleTitles()).toBe(1);
    });

    test('reveal leaves the old Titles promo alone when the panel has no Titles row', () => {
        const env = makeEnv({ consent: false, await: true, rows: ['linking', 'auditor'] });
        env.fire('bbai:generation:finished', { successes: 1 });
        expect(env.panel.hidden).toBe(false);
        expect(env.legacy.map((n) => n.hidden)).toEqual([false, false]);
        expect(env.legacy.map((n) => n.style.display)).toEqual([undefined, undefined]);
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
        expect(evs[0].properties.suggested).toBe('titles-auditor');
        expect(evs[1].properties).toMatchObject({ from_plugin: 'alt', to_plugin: 'auditor', link_target: 'wporg', account_state: 'guest' });
        evs.forEach((e) => {
            expect(JSON.stringify(e.properties)).not.toMatch(/https?:|wordpress\.org|example/);
        });
    });
});
