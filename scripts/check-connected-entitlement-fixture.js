#!/usr/bin/env node

/**
 * Read-only preflight for the destructive connected entitlement E2E suite.
 *
 * This checks the canonical entitlement exposed by the installed plugin. It
 * does not modify WordPress or backend data and never prints credentials.
 */

const { chromium } = require('@playwright/test');

const REQUIRED_OPT_INS = [
  'BBAI_E2E_CONNECTED_ENTITLEMENT',
  'BBAI_E2E_RUN_CONNECTED_GENERATION',
];

function fail(message) {
  console.error(`FAIL: ${message}`);
  process.exitCode = 1;
}

function configured(name, fallback) {
  return process.env[name] || (fallback ? process.env[fallback] : '') || '';
}

function findEntitlement(payload, depth = 0) {
  if (!payload || typeof payload !== 'object' || depth > 6) {
    return null;
  }
  if (payload.entitlement_state && typeof payload.entitlement_state === 'object') {
    return payload.entitlement_state;
  }
  for (const key of ['data', 'result', 'job', 'payload', 'response', 'usage']) {
    const found = findEntitlement(payload[key], depth + 1);
    if (found) {
      return found;
    }
  }
  return null;
}

function safeStateSummary(state) {
  return {
    plan: String(state.plan_type || state.plan || ''),
    token_limit: Number(state.token_limit),
    tokens_used_this_month: Number(state.tokens_used_this_month),
    tokens_remaining: Number(state.tokens_remaining),
    can_generate: state.can_generate === true,
    quota_state: String(state.quota_state || ''),
    is_logged_in: state.is_logged_in === true,
  };
}

async function fetchPluginRest(page, path) {
  return page.evaluate(async (routePath) => {
    const nonce =
      window.BBAI?.nonce ||
      window.BBAI_DASH?.nonce ||
      window.BBAI_DASHBOARD?.nonce ||
      window.wpApiSettings?.nonce ||
      '';
    const restRoot =
      window.BBAI?.restRoot ||
      window.BBAI_DASH?.restRoot ||
      window.BBAI_DASHBOARD?.restRoot ||
      window.wpApiSettings?.root ||
      new URL('/wp-json/', window.location.origin).toString();
    const route = String(routePath).replace(/^\/wp-json\//, '');
    const controller = new AbortController();
    const timeoutId = window.setTimeout(() => controller.abort(), 30000);
    try {
      const response = await fetch(new URL(route, restRoot).toString(), {
        method: 'GET',
        credentials: 'same-origin',
        headers: nonce ? { 'X-WP-Nonce': nonce } : {},
        signal: controller.signal,
      });
      const text = await response.text();
      let body = null;
      try {
        body = text ? JSON.parse(text) : null;
      } catch (error) {
        body = null;
      }
      return { ok: response.ok, status: response.status, body };
    } finally {
      window.clearTimeout(timeoutId);
    }
  }, path);
}

async function main() {
  const baseUrl = configured('BBAI_E2E_BASE_URL', 'WP_BASE_URL').replace(/\/+$/, '');
  const adminUser = configured('BBAI_E2E_ADMIN_USER', 'WP_ADMIN_USER');
  const adminPass = configured('BBAI_E2E_ADMIN_PASS', 'WP_ADMIN_PASS');

  const missing = [];
  if (!baseUrl) missing.push('BBAI_E2E_BASE_URL (or WP_BASE_URL)');
  if (!adminUser) missing.push('BBAI_E2E_ADMIN_USER (or WP_ADMIN_USER)');
  if (!adminPass) missing.push('BBAI_E2E_ADMIN_PASS (or WP_ADMIN_PASS)');
  for (const name of REQUIRED_OPT_INS) {
    if (process.env[name] !== '1') {
      missing.push(`${name}=1`);
    }
  }
  if (missing.length > 0) {
    fail(`fixture check is not armed; missing ${missing.join(', ')}.`);
    return;
  }

  let parsedBase;
  try {
    parsedBase = new URL(baseUrl);
  } catch (error) {
    fail('BBAI_E2E_BASE_URL/WP_BASE_URL is not a valid URL.');
    return;
  }
  const localHosts = new Set(['localhost', '127.0.0.1', '::1', '[::1]']);
  if (!localHosts.has(parsedBase.hostname)) {
    fail('the fixture checker only runs against local wp-env URLs (localhost, 127.0.0.1, or ::1).');
    return;
  }

  let browser;
  try {
    browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    await page.goto(`${baseUrl}/wp-login.php`, { waitUntil: 'domcontentloaded', timeout: 30000 });
    if (await page.locator('#user_login').isVisible().catch(() => false)) {
      await page.fill('#user_login', adminUser);
      await page.fill('#user_pass', adminPass);
      await Promise.all([
        page.waitForURL(/\/wp-admin\//, { timeout: 30000 }).catch(() => null),
        page.click('#wp-submit'),
      ]);
    }
    if (!page.url().includes('/wp-admin')) {
      fail('WordPress admin authentication failed.');
      return;
    }

    await page.goto(`${baseUrl}/wp-admin/admin.php?page=bbai`, {
      waitUntil: 'domcontentloaded',
      timeout: 45000,
    });
    const usageResponse = await fetchPluginRest(page, '/wp-json/bbai/v1/usage');
    const truthResponse = await fetchPluginRest(page, '/wp-json/bbai/v1/dashboard/state-truth');
    if (!usageResponse.ok) {
      fail(`plugin usage endpoint returned HTTP ${usageResponse.status}.`);
      return;
    }
    if (!truthResponse.ok) {
      fail(`plugin dashboard state endpoint returned HTTP ${truthResponse.status}.`);
      return;
    }

    const usageState = findEntitlement(usageResponse.body);
    const truthState = findEntitlement(truthResponse.body);
    if (!usageState) {
      fail('canonical entitlement_state was not returned by the plugin usage endpoint.');
      return;
    }
    if (!truthState) {
      fail('canonical entitlement_state was not returned by the plugin dashboard state endpoint.');
      return;
    }
    const summary = safeStateSummary(usageState);
    const truthSummary = safeStateSummary(truthState);
    console.log('Connected fixture state:', JSON.stringify(summary));

    if (
      truthSummary.token_limit !== summary.token_limit ||
      truthSummary.tokens_used_this_month !== summary.tokens_used_this_month ||
      truthSummary.tokens_remaining !== summary.tokens_remaining ||
      truthSummary.can_generate !== summary.can_generate
    ) {
      fail('usage and dashboard state endpoints do not agree on canonical entitlement values.');
      return;
    }
    console.log('PASS: usage and dashboard state endpoints agree on canonical entitlement values.');

    if (!summary.is_logged_in) {
      fail('the configured wp-env site is not connected to a logged-in backend test account/site.');
      return;
    }
    if (!Number.isFinite(summary.token_limit) || summary.token_limit <= 0) {
      fail('token_limit is not a positive finite value.');
      return;
    }
    if (!Number.isFinite(summary.tokens_used_this_month) || summary.tokens_used_this_month < 0) {
      fail('tokens_used_this_month is not a non-negative finite value.');
      return;
    }
    if (summary.tokens_remaining !== 1) {
      fail(`tokens_remaining must be exactly 1 before destructive execution; received ${summary.tokens_remaining}.`);
      return;
    }
    if (summary.tokens_used_this_month !== summary.token_limit - 1) {
      fail('tokens_used_this_month does not match token_limit - 1 for a one-credit fixture.');
      return;
    }
    if (!summary.can_generate) {
      fail('can_generate must be true while the prepared final credit remains.');
      return;
    }

    console.log('PASS: connected entitlement fixture is prepared with exactly one remaining credit.');
  } catch (error) {
    fail(`fixture check could not complete: ${error instanceof Error ? error.message : String(error)}.`);
  } finally {
    if (browser) {
      await browser.close();
    }
  }
}

main();
