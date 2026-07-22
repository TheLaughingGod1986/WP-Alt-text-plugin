#!/usr/bin/env node
/**
 * Telemetry E2E verification for 4.6.114 from WP.org zip mapping.
 * Captures beepbeepai_telemetry AJAX payloads and page PHP warnings.
 */
import { chromium } from '@playwright/test';
import { spawnSync } from 'node:child_process';

const BASE = (process.env.BBAI_E2E_BASE_URL || 'http://localhost:8897').replace(/\/$/, '');
const WP_USER = process.env.BBAI_E2E_ADMIN_USER || 'admin';
const WP_PASS = process.env.BBAI_E2E_ADMIN_PASS || 'password';
const CONTAINER = process.env.BBAI_E2E_WP_CLI_CONTAINER || findWpCliContainer();

function findWpCliContainer() {
  if (process.env.BBAI_E2E_WP_CLI_CONTAINER) return process.env.BBAI_E2E_WP_CLI_CONTAINER;
  const ps = spawnSync('docker', ['ps', '--format', '{{.Names}}'], { encoding: 'utf8' });
  if (ps.status !== 0) return '';
  const names = ps.stdout.split(/\r?\n/).map((l) => l.trim()).filter(Boolean);
  const port = (process.env.BBAI_E2E_BASE_URL || '').includes('8897') ? '46114' : '';
  if (port) {
    const match = names.find((n) => n.includes('46114') && n.endsWith('-cli-1') && !n.includes('tests'));
    if (match) return match;
  }
  return names.find((n) => n.endsWith('-cli-1') && !n.includes('tests')) || '';
}

function runWpCli(args) {
  if (!CONTAINER) return { status: 1, stdout: '', stderr: 'no cli container' };
  const r = spawnSync('docker', ['exec', CONTAINER, 'wp', '--path=/var/www/html', ...args], { encoding: 'utf8' });
  return { status: r.status, stdout: r.stdout || '', stderr: r.stderr || '' };
}

function seedFixtures() {
  runWpCli(['plugin', 'activate', 'beepbeep-ai-alt-text-generator']);
  runWpCli(['option', 'update', 'bbai_telemetry_consent', 'yes']);
  const php = `
update_option('beepbeepai_jwt_token', 'e2e-telemetry-verify-token');
update_option('beepbeepai_license_key', 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
update_option('beepbeepai_license_data', array(
  'organization' => array('id' => 1, 'name' => 'E2E Org'),
  'site' => array('id' => 1, 'url' => home_url()),
));
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
$upload = wp_upload_dir();
wp_mkdir_p($upload['path']);
for ($i = 1; $i <= 3; $i++) {
  $title = 'bbai-tel-verify-' . $i;
  $existing = get_page_by_title($title, OBJECT, 'attachment');
  if ($existing) { delete_post_meta($existing->ID, '_wp_attachment_image_alt'); continue; }
  $file = trailingslashit($upload['path']) . $title . '.png';
  file_put_contents($file, $png);
  $id = wp_insert_attachment(['post_title'=>$title,'post_mime_type'=>'image/png','post_status'=>'inherit'], $file);
  require_once ABSPATH . 'wp-admin/includes/image.php';
  wp_generate_attachment_metadata($id, $file);
}
echo 'seeded';
`;
  runWpCli(['eval', php]);
}

function parseTelemetryEvents(postData) {
  if (!postData) return [];
  const params = new URLSearchParams(postData);
  const raw = params.get('events');
  if (!raw) return [];
  try {
    const batch = JSON.parse(raw);
    return Array.isArray(batch) ? batch.map((e) => e.event).filter(Boolean) : [];
  } catch {
    return [];
  }
}

async function login(page) {
  await page.goto(`${BASE}/wp-admin/`, { waitUntil: 'domcontentloaded' });
  if (page.url().includes('wp-login.php')) {
    await page.fill('#user_login', WP_USER);
    await page.fill('#user_pass', WP_PASS);
    await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.click('#wp-submit')]);
  }
}

const results = {};
const observed = new Set();

function record(name, pass, detail = '') {
  results[name] = { pass, detail };
  console.log(`${pass ? 'PASS' : 'FAIL'} ${name}${detail ? ` — ${detail}` : ''}`);
}

seedFixtures();

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext();
const page = await context.newPage();

const telemetryEvents = [];
const consoleLines = [];
page.on('console', (msg) => consoleLines.push(msg.text()));
page.on('request', (req) => {
  const pd = req.postData() || '';
  if (pd.includes('events') && (pd.includes('telemetry') || pd.includes('beepbeepai'))) {
    for (const ev of parseTelemetryEvents(pd)) {
      telemetryEvents.push(ev);
      observed.add(ev);
    }
  }
});

async function flushTelemetry() {
  await page.evaluate(async () => {
    if (window.bbaiTelemetry && typeof window.bbaiTelemetry.flush === 'function') {
      window.bbaiTelemetry.flush();
    }
    await new Promise((r) => setTimeout(r, 1800));
  });
}

await login(page);

// Dashboard first — plugin_opened + dashboard_viewed
await page.goto(`${BASE}/wp-admin/admin.php?page=bbai`, { waitUntil: 'networkidle', timeout: 60000 });
await page.waitForSelector('[data-bbai-dashboard-root], #bbai-dashboard-root, .bbai-dashboard, .nai-app', { timeout: 30000 }).catch(() => {});
const telReady = await page.evaluate(() => !!(window.bbaiTelemetry && window.BBAI_TELEMETRY && window.BBAI_TELEMETRY.ajaxUrl));
console.log('telemetry client ready:', telReady);
await flushTelemetry();

record('plugin_opened', observed.has('plugin_opened') || telemetryEvents.includes('plugin_opened'));
record('dashboard_viewed', observed.has('dashboard_viewed') || telemetryEvents.includes('dashboard_viewed'));

// Library page — PHP warnings
await page.goto(`${BASE}/wp-admin/admin.php?page=bbai-library`, { waitUntil: 'networkidle', timeout: 60000 });
await page.waitForTimeout(3000);
const html = await page.content();
const phpWarnings = [
  html.includes('$entitlement_state'),
  html.includes('Undefined variable'),
  html.includes('WP_Scripts::localize'),
  html.includes('was called incorrectly'),
].some(Boolean);
record('no_entitlement_state_php_warning', !phpWarnings && !html.includes('entitlement_state</strong>'), phpWarnings ? 'PHP warning in HTML' : 'clean');
record('no_wp_localize_notices', !consoleLines.some((l) => /localize.*incorrectly|WP_Scripts::localize/i.test(l)), consoleLines.filter((l) => /localize/i.test(l)).join('; ') || 'clean');

await flushTelemetry();

record('alt_library_viewed', observed.has('alt_library_viewed') || telemetryEvents.includes('alt_library_viewed'));

// Batch generation — select rows and trigger generate via DOM click (toolbar may be off-screen).
await page.goto(`${BASE}/wp-admin/admin.php?page=bbai-library&status=missing`, { waitUntil: 'networkidle', timeout: 60000 });
await page.waitForTimeout(2500);
await page.evaluate(() => {
  document.querySelectorAll('.bbai-library-row-check, .bbai-image-checkbox').forEach((el, i) => {
    if (i < 2) {
      el.checked = true;
      el.dispatchEvent(new Event('change', { bubbles: true }));
    }
  });
  document.querySelector('#bbai-batch-generate')?.click();
});
await page.waitForTimeout(15000);
await flushTelemetry();
record('batch_generation_started', observed.has('batch_generation_started') || telemetryEvents.includes('batch_generation_started'));
record('batch_generation_completed', observed.has('batch_generation_completed') || telemetryEvents.includes('batch_generation_completed'));

// upgrade_cta_clicked — workspace library uses telemetry delegated fallback on show-upgrade-modal.
await page.evaluate(() => {
  const btn = document.createElement('button');
  btn.type = 'button';
  btn.setAttribute('data-action', 'show-upgrade-modal');
  btn.setAttribute('data-bbai-pricing-variant', 'growth');
  document.body.appendChild(btn);
  btn.click();
  btn.remove();
});
await flushTelemetry();
record('upgrade_cta_clicked', observed.has('upgrade_cta_clicked') || telemetryEvents.includes('upgrade_cta_clicked'));

// checkout_started — open upgrade modal then select a plan if present.
await page.evaluate(() => {
  if (typeof window.openPricingModal === 'function') {
    window.openPricingModal('growth');
  } else if (typeof window.bbaiOpenUpgradeModal === 'function') {
    window.bbaiOpenUpgradeModal('default', { source: 'e2e_verify', force: true });
  }
});
await page.waitForTimeout(1500);
const checkout = page.locator('[data-bbai-checkout-plan], [data-action="checkout-plan"], [data-plan]').first();
if (await checkout.count()) {
  await checkout.click({ force: true }).catch(() => {});
  await flushTelemetry();
}
record('checkout_started', observed.has('checkout_started') || telemetryEvents.includes('checkout_started'), observed.has('checkout_started') ? '' : 'checkout plan not available');

// Auth modal — signup_started (may not complete without sandbox)
await page.goto(`${BASE}/wp-admin/admin.php?page=bbai`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(1000);
const signupBtn = page.locator('[data-bbai-auth="signup"], [data-action="open-signup"], .bbai-auth-signup, #bbai-open-signup').first();
if (await signupBtn.count()) {
  await signupBtn.click().catch(() => {});
  await page.waitForTimeout(1000);
}
record('signup_started', observed.has('signup_started') || telemetryEvents.includes('signup_started'), 'optional — requires auth UI');

await browser.close();

const versionCheck = runWpCli(['plugin', 'get', 'beepbeep-ai-alt-text-generator', '--field=version']);
const version = (versionCheck.stdout || '').trim().split(/\s/).pop();
record('plugin_version_46114', version === '4.6.114', version || 'missing');

console.log('\n--- Observed telemetry events ---');
console.log([...new Set(telemetryEvents)].sort().join(', ') || '(none)');

const allRequired = [
  'no_entitlement_state_php_warning', 'no_wp_localize_notices',
  'plugin_opened', 'alt_library_viewed', 'dashboard_viewed',
  'batch_generation_started', 'upgrade_cta_clicked', 'plugin_version_46114',
];
const passCount = allRequired.filter((k) => results[k]?.pass).length;
const optional = ['batch_generation_completed', 'checkout_started', 'signup_started'];
console.log(`\nRequired: ${passCount}/${allRequired.length}`);
console.log(`Optional: ${optional.filter((k) => results[k]?.pass).length}/${optional.length}`);
process.exit(passCount === allRequired.length ? 0 : 1);
