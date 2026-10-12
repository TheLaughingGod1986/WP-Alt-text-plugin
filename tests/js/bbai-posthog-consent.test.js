/**
 * The PostHog browser SDK must never load or send without an explicit
 * `enabled: true` from PHP (which 4.6.141 never sets). Runs in plain node.
 */
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const src = fs.readFileSync(path.join(__dirname, '../../assets/js/bbai-posthog.js'), 'utf8');

function run(cfg) {
    const created = [];
    const fetchCalls = [];
    const captures = [];
    const document = {
        cookie: '',
        referrer: 'https://google.com/?q=x',
        createElement: (tag) => { const el = { tag, setAttribute() {}, getAttribute() { return null; }, addEventListener() {} }; created.push(el); return el; },
        getElementsByTagName: () => [],
        addEventListener() {},
        head: { appendChild: (el) => created.push(el) },
    };
    const window = {
        BBAI_POSTHOG: cfg,
        location: { search: '?utm_source=x', pathname: '/wp-admin/admin.php' },
        sessionStorage: { getItem: () => null, setItem() {} },
        addEventListener() {},
        setTimeout: () => 0,
        // A pre-loaded posthog global must not be used either.
        posthog: { __loaded: true, config: {}, init() { captures.push('init'); }, capture: (n) => captures.push(n), identify: (i) => captures.push('identify:' + i), startSessionRecording: () => captures.push('replay'), register() {} },
    };
    window.window = window;
    vm.runInNewContext(src, { window, document, URLSearchParams, fetch: (...a) => { fetchCalls.push(a); return Promise.resolve(); }, console });
    window.bbaiTrack('generation_started', { url: 'https://customer.example/' });
    window.bbaiPageView('dashboard');
    window.bbaiIdentify('acct_1', {});
    window.bbaiAnalytics.alias('acct_1', 'site_1');
    return { created, captures };
}

describe('bbai-posthog consent gate', () => {
    test.each([
        ['no config (upgrade from 4.6.140)', undefined],
        ['enabled missing', { apiKey: 'phc_x', apiHost: 'https://us.i.posthog.com', identify: { id: 'acct_1' } }],
        ['enabled false', { enabled: false, apiKey: 'phc_x', apiHost: 'https://us.i.posthog.com', identify: { id: 'acct_1' } }],
        ['enabled truthy string', { enabled: '1', apiKey: 'phc_x', apiHost: 'https://us.i.posthog.com', identify: { id: 'acct_1' } }],
    ])('no SDK load, capture, identify or replay with %s', (_l, cfg) => {
        const { created, captures } = run(cfg);
        expect(created.filter((el) => el.tag === 'script')).toHaveLength(0);
        expect(captures).toEqual([]);
    });
});
