/**
 * 4.6.147: after a bulk run uses the last credits, the completion summary
 * shows this run's credit count (read after the run, not the pre-run page
 * snapshot) and the dashboard's cap CTA ("Upgrade to continue generating",
 * opening the in-plugin upgrade modal) instead of the Autopilot upsell.
 * Also: paywall_shown / quota_exhausted_state_shown are off the allowlists.
 *
 * Run offline: unshare -rn node_modules/.bin/jest tests/js
 */
'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const ROOT = path.join(__dirname, '..', '..');
const read = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8');
const ADMIN_SRC = read('assets/js/bbai-admin.js');
const ADMIN_MIN = read('assets/js/bbai-admin.min.js');

function sliceFn(src, header) {
    const start = src.indexOf(header);
    if (start === -1) { throw new Error('missing ' + header); }
    const end = src.indexOf('\n    }\n', start);
    if (end === -1) { throw new Error('missing end for ' + header); }
    return src.slice(start, end + '\n    }\n'.length);
}

const FNS = [
    '    function isAnonymousTrialUsage(usage) {',
    '    function getBulkProgressDismissSecondaryCta() {',
    '    function getBulkProgressCompletionPresentation(state) {',
    '    function getBulkProgressCompletionUsage(state) {',
    '    function getBulkProgressCapCtaConfig() {',
    '    function readPostGenerationUsageSnapshot(refreshed) {',
    '    function openBulkProgressCapUpgradeModal() {',
];

function makeRoot(attrs) {
    return { getAttribute: (k) => (Object.prototype.hasOwnProperty.call(attrs, k) ? String(attrs[k]) : null) };
}

function load(opts) {
    const ctx = {
        window: { setTimeout: (fn) => fn() },
        __: (s) => s,
        sprintf: (fmt, ...args) => {
            let i = 0;
            return fmt.replace(/%(\d+)\$s|%s/g, (m, n) => String(n ? args[Number(n) - 1] : args[i++]));
        },
        formatDashboardNumber: (n) => String(n),
        buildBulkProgressSuccessTitleHtml: () => 'title',
        getDashboardActionableStateSnapshot: () => ({ key: opts.actionableKey || 'none' }),
        getDashboardRootNode: () => opts.root,
        buildDashboardReviewLibraryUrl: (u) => u,
        getAltLibraryAdminUrl: () => 'admin.php?page=bbai-library',
        getFreeAccountMonthlyLimit: () => 25,
        // Pre-run page snapshot (stale on purpose).
        getUsageSnapshot: () => (opts.pageUsage ? Object.assign({}, opts.pageUsage) : null),
        normalizeUsagePayload: (u) => {
            if (!u || typeof u !== 'object') { return null; }
            const used = Number(u.used !== undefined ? u.used : u.credits_used);
            const limit = Number(u.limit !== undefined ? u.limit : u.credits_total);
            if (!isFinite(used) || !isFinite(limit)) { return null; }
            const remaining = u.remaining !== undefined ? Number(u.remaining) : Math.max(0, limit - used);
            return Object.assign({}, u, { used, limit, remaining });
        },
        openUpgradeModal: (...a) => { ctx.__fallback = a; return true; },
        getBulkProgressCompletionCtaConfig: () => ({
            primary: { label: 'Continue optimising', action: 'library', url: 'x' },
            secondary: { label: 'Close', action: 'dismiss-completion' }
        }),
    };
    vm.createContext(ctx);
    vm.runInContext(FNS.map((h) => sliceFn(ADMIN_SRC, h)).join('\n') +
        '\nthis.present = getBulkProgressCompletionPresentation; this.readUsage = readPostGenerationUsageSnapshot;' +
        ' this.cap = getBulkProgressCapCtaConfig; this.openCap = openBulkProgressCapUpgradeModal;', ctx);
    return ctx;
}

const SIGNED_IN_FREE = { 'data-bbai-has-connected-account': '1', 'data-bbai-is-premium': '0', 'data-bbai-credits-used': '22', 'data-bbai-credits-total': '25', 'data-bbai-credits-remaining': '3' };

describe('bulk completion at 0 credits', () => {
    test('QA repro: stale 22/25 snapshot, fresh 25/25 after the run -> cap CTA, no Autopilot upsell', () => {
        const ctx = load({ root: makeRoot(SIGNED_IN_FREE), pageUsage: { used: 22, limit: 25, remaining: 3 } });
        const before = ctx.present({ processed: 3, total: 3, failed: 0 });
        expect(before.supportingLine).toContain('You’ve used 22 of 25 credits this month.');
        expect(before.capCta).toBe(false);
        expect(before.automationCta).toBe(true);

        const fresh = ctx.readUsage({ success: true, used: 25, limit: 25, remaining: 0 });
        const after = ctx.present({ processed: 3, total: 3, failed: 0, postGen: { usage: fresh } });
        expect(after.supportingLine).toBe('You’ve used 25 of 25 credits this month.');
        expect(after.capCta).toBe(true);
        expect(after.automationCta).toBe(false);

        const cta = ctx.cap();
        expect(cta.primary).toEqual({ label: 'Upgrade to continue generating', action: 'upgrade-at-cap' });
        expect(cta.secondary).toEqual({ label: 'Review ALT text', action: 'review-result' });
    });

    test('credits left -> unchanged (fresh count, Autopilot link stays)', () => {
        const ctx = load({ root: makeRoot(SIGNED_IN_FREE), pageUsage: { used: 10, limit: 25, remaining: 15 } });
        const p = ctx.present({ processed: 2, total: 2, failed: 0, postGen: { usage: { used: 12, limit: 25, remaining: 13 } } });
        expect(p.capCta).toBe(false);
        expect(p.automationCta).toBe(true);
        expect(p.supportingLine).toContain('You’ve used 12 of 25 credits this month.');
    });

    test('guest trial at 0 and paid plans never get the cap CTA', () => {
        const guest = load({ root: makeRoot({ 'data-bbai-has-connected-account': '0', 'data-bbai-is-premium': '0' }) });
        expect(guest.present({ processed: 3, total: 3, failed: 0, postGen: { usage: { used: 10, limit: 10, remaining: 0, auth_state: 'anonymous', quota_type: 'trial' } } }).capCta).toBe(false);
        const paid = load({ root: makeRoot(Object.assign({}, SIGNED_IN_FREE, { 'data-bbai-is-premium': '1' })) });
        const p = paid.present({ processed: 3, total: 3, failed: 0, postGen: { usage: { used: 100, limit: 100, remaining: 0 } } });
        expect(p.capCta).toBe(false);
        expect(p.automationCta).toBe(false);
    });

    test('no fresh usage and no snapshot -> falls back to page attrs (remaining from data-bbai-credits-remaining)', () => {
        const ctx = load({ root: makeRoot(Object.assign({}, SIGNED_IN_FREE, { 'data-bbai-credits-used': '25', 'data-bbai-credits-remaining': '0' })), pageUsage: null });
        const p = ctx.present({ processed: 1, total: 1, failed: 0 });
        expect(p.capCta).toBe(true);
    });

    test('readPostGenerationUsageSnapshot: refreshed state usage first, then server payload, then page', () => {
        const ctx = load({ root: makeRoot(SIGNED_IN_FREE), pageUsage: { used: 22, limit: 25, remaining: 3 } });
        expect(ctx.readUsage({ usage: { used: 25, limit: 25, remaining: 0 } })).toMatchObject({ used: 25, limit: 25, remaining: 0 });
        expect(ctx.readUsage({ used: 24, limit: 25 })).toMatchObject({ used: 24, remaining: 1 });
        expect(ctx.readUsage(null)).toMatchObject({ used: 22, remaining: 3 });
    });

    test('cap CTA opens the in-plugin upgrade modal (limit_reached) and tracks upgrade_cta_clicked', () => {
        const ctx = load({ root: makeRoot(SIGNED_IN_FREE) });
        const calls = [];
        ctx.window.bbaiTrack = (e, p) => calls.push(['track', e, p]);
        ctx.window.bbaiOpenUpgradeModal = (r, c) => { calls.push(['open', r, c]); return true; };
        ctx.openCap();
        expect(calls[0]).toEqual(['track', 'upgrade_cta_clicked', { trigger: 'credits_exhausted', context: 'generation_complete' }]);
        expect(calls[1][0]).toBe('open');
        expect(calls[1][1]).toBe('limit_reached');
        expect(calls[1][2]).toMatchObject({ source: 'generation_complete', triggerKey: 'credits_exhausted', force: true });
        expect(ctx.__fallback).toBeUndefined();
    });

    test('render and click paths are wired in source and in the shipped .min.js', () => {
        expect(ADMIN_SRC).toContain("ctaConfig = presentation && presentation.capCta\n                ? getBulkProgressCapCtaConfig()");
        expect(ADMIN_SRC).toContain("if (action === 'upgrade-at-cap') {");
        expect(ADMIN_SRC).toContain('withFreshUsage(refreshedState || null);');
        for (const s of ['"upgrade-at-cap"', '"Upgrade to continue generating"', '"credits_exhausted"', 'capCta']) {
            expect(ADMIN_MIN).toContain(s);
        }
    });
});

describe('telemetry allowlist delist (4.6.147)', () => {
    test('paywall_shown and quota_exhausted_state_shown are gone; cap events stay', () => {
        const tel = read('assets/js/bbai-telemetry.js');
        const start = tel.indexOf('var posthogAllowlist = {');
        const block = tel.slice(start, tel.indexOf('};', start));
        expect(block).not.toMatch(/\bpaywall_shown\b/);
        expect(block).not.toMatch(/\bquota_exhausted_state_shown\b/);
        for (const e of ['out_of_credits_banner_shown', 'upgrade_modal_opened', 'upgrade_cta_clicked']) {
            expect(block).toContain(e + ': true');
        }
        const core = read('admin/class-bbai-core.php');
        const cs = core.indexOf('function is_posthog_forwarded_client_event');
        const cblock = core.slice(cs, core.indexOf('return ! empty', cs));
        expect(cblock).not.toMatch(/'paywall_shown'/);
        expect(cblock).not.toMatch(/'quota_exhausted_state_shown'/);
        for (const e of ['out_of_credits_banner_shown', 'upgrade_modal_opened', 'upgrade_cta_clicked']) {
            expect(cblock).toContain("'" + e + "'");
        }
    });
});
