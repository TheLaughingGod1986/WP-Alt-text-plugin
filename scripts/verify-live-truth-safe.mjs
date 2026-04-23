#!/usr/bin/env node

/**
 * SAFE live verification for BeepBeep AI backend/frontend truth alignment.
 *
 * This script intentionally avoids arbitrary SQL, Supabase RPC calls, and debug
 * endpoints. Supabase access is limited to direct PostgREST reads against the
 * known tables below through a small `supabase.from(table)` wrapper.
 *
 * Required env:
 * - WP_BASE_URL
 * - WP_ADMIN_USER
 * - WP_ADMIN_PASS
 * - SUPABASE_URL
 * - SUPABASE_SERVICE_ROLE_KEY
 *
 * Optional env:
 * - HEADLESS=true|false
 * - RUN_GENERATE=true|false
 * - EXPECT_BILLING=true|false
 */

import { createRequire } from 'node:module';

const REQUIRED_ENV = [
  'WP_BASE_URL',
  'WP_ADMIN_USER',
  'WP_ADMIN_PASS',
  'SUPABASE_URL',
  'SUPABASE_SERVICE_ROLE_KEY',
];

const KNOWN_TABLES = new Set([
  'sites',
  'site_quotas',
  'site_trials',
  'site_subscriptions',
  'generation_requests',
  'usage_events',
  'image_alt_states',
]);

const SITE_ID_COLUMNS = [
  'site_id',
  'site_hash',
  'site_key',
  'install_id',
  'installId',
  'site_identifier',
  'external_site_id',
  'wordpress_site_id',
  'id',
];

const SITE_TABLE_COLUMNS = [
  'id',
  'site_id',
  'site_hash',
  'site_key',
  'install_id',
  'installId',
  'site_identifier',
  'external_site_id',
  'wordpress_site_id',
  'url',
  'site_url',
  'home_url',
  'domain',
];

const TIMESTAMP_COLUMNS = [
  'created_at',
  'updated_at',
  'requested_at',
  'generated_at',
  'inserted_at',
  'last_seen_at',
];

const GENERATE_WAIT_MS = 90_000;
const POLL_INTERVAL_MS = 5_000;

function boolEnv(name, defaultValue = false) {
  const raw = process.env[name];
  if (raw === undefined || raw === '') {
    return defaultValue;
  }
  return ['1', 'true', 'yes', 'on'].includes(String(raw).toLowerCase());
}

function normalizeBaseUrl(raw) {
  return String(raw || '').replace(/\/+$/, '');
}

function wpUrl(baseUrl, path) {
  return `${normalizeBaseUrl(baseUrl)}${path.startsWith('/') ? path : `/${path}`}`;
}

function nowIso() {
  return new Date().toISOString();
}

function shortValue(value) {
  if (value === null || value === undefined) {
    return value;
  }
  const str = String(value);
  if (str.length <= 160) {
    return str;
  }
  return `${str.slice(0, 80)}...${str.slice(-24)}`;
}

function unique(values) {
  const seen = new Set();
  const out = [];
  for (const value of values) {
    if (value === null || value === undefined) {
      continue;
    }
    const str = String(value).trim();
    if (!str || seen.has(str)) {
      continue;
    }
    seen.add(str);
    out.push(str);
  }
  return out;
}

function addCheck(summary, name, ok, message, details = {}, required = true) {
  summary.checks.push({
    name,
    ok: !!ok,
    required: !!required,
    message,
    details,
  });
}

function finalize(summary) {
  summary.ok = summary.checks
    .filter((check) => check.required)
    .every((check) => check.ok);
  summary.finishedAt = nowIso();
  console.log(JSON.stringify(summary, null, 2));
  process.exit(summary.ok ? 0 : 1);
}

function missingEnvSummary(missing) {
  const summary = {
    ok: false,
    startedAt: nowIso(),
    finishedAt: nowIso(),
    config: {},
    checks: [
      {
        name: 'required_env',
        ok: false,
        required: true,
        message: `Missing required env vars: ${missing.join(', ')}`,
        details: { missing },
      },
    ],
  };
  console.log(JSON.stringify(summary, null, 2));
  process.exit(2);
}

function loadPlaywright() {
  const attempts = [
    '../tests/e2e/package.json',
    '../package.json',
  ];
  const errors = [];

  for (const attempt of attempts) {
    try {
      const requireFrom = createRequire(new URL(attempt, import.meta.url));
      return requireFrom('playwright');
    } catch (error) {
      errors.push(`${attempt}: ${error.message}`);
    }
  }

  throw new Error(
    'Playwright is not installed. Install the existing E2E dependencies first: cd tests/e2e && npm install && npx playwright install chromium. ' +
      errors.join(' | ')
  );
}

function assertSafeName(name, kind) {
  if (!/^[A-Za-z_][A-Za-z0-9_]*$/.test(name)) {
    throw new Error(`Unsafe ${kind} name: ${name}`);
  }
}

function createSafeSupabaseRestClient({ url, serviceRoleKey }) {
  const baseUrl = normalizeBaseUrl(url);
  const key = String(serviceRoleKey || '').trim();

  return {
    from(table) {
      assertSafeName(table, 'table');
      if (!KNOWN_TABLES.has(table)) {
        throw new Error(`Blocked Supabase table "${table}". Allowed tables: ${Array.from(KNOWN_TABLES).join(', ')}`);
      }
      return new SafeSupabaseQuery({ baseUrl, key, table });
    },
  };
}

class SafeSupabaseQuery {
  constructor({ baseUrl, key, table }) {
    this.baseUrl = baseUrl;
    this.key = key;
    this.table = table;
    this.params = new URLSearchParams();
    this.params.set('select', '*');
    this.headers = {
      apikey: key,
      Authorization: `Bearer ${key}`,
      Accept: 'application/json',
    };
  }

  select(columns = '*') {
    if (columns !== '*') {
      for (const column of String(columns).split(',')) {
        const trimmed = column.trim();
        if (trimmed && trimmed !== '*' && !/^[A-Za-z_][A-Za-z0-9_]*$/.test(trimmed)) {
          throw new Error(`Unsafe select column: ${trimmed}`);
        }
      }
    }
    this.params.set('select', columns);
    return this;
  }

  eq(column, value) {
    assertSafeName(column, 'column');
    this.params.set(column, `eq.${String(value)}`);
    return this;
  }

  gte(column, value) {
    assertSafeName(column, 'column');
    this.params.set(column, `gte.${String(value)}`);
    return this;
  }

  order(column, { ascending = false } = {}) {
    assertSafeName(column, 'column');
    this.params.set('order', `${column}.${ascending ? 'asc' : 'desc'}`);
    return this;
  }

  limit(count) {
    this.params.set('limit', String(Math.max(0, Math.min(100, Number(count) || 0))));
    return this;
  }

  async execute() {
    const endpoint = `${this.baseUrl}/rest/v1/${this.table}?${this.params.toString()}`;
    const response = await fetch(endpoint, {
      method: 'GET',
      headers: this.headers,
    });
    const text = await response.text();
    let body = null;
    try {
      body = text ? JSON.parse(text) : null;
    } catch {
      body = text;
    }

    if (!response.ok) {
      return {
        ok: false,
        status: response.status,
        table: this.table,
        error: body,
      };
    }

    return {
      ok: true,
      status: response.status,
      table: this.table,
      rows: Array.isArray(body) ? body : [],
    };
  }
}

function postgresErrorCode(result) {
  return result && result.error && typeof result.error === 'object'
    ? String(result.error.code || '')
    : '';
}

function postgresErrorMessage(result) {
  if (!result || !result.error) {
    return '';
  }
  if (typeof result.error === 'string') {
    return result.error;
  }
  return String(result.error.message || result.error.details || result.error.hint || '');
}

function isMissingTable(result) {
  const code = postgresErrorCode(result);
  const message = postgresErrorMessage(result).toLowerCase();
  return code === '42P01' ||
    result.status === 404 ||
    message.includes('relation') && message.includes('does not exist');
}

function isIgnorableColumnOrTypeError(result) {
  const code = postgresErrorCode(result);
  const message = postgresErrorMessage(result).toLowerCase();
  return code === '42703' ||
    code === '22P02' ||
    message.includes('column') && message.includes('does not exist') ||
    message.includes('invalid input syntax');
}

async function tableExists(supabase, table) {
  const result = await supabase.from(table).select('*').limit(1).execute();
  if (result.ok) {
    return { exists: true, status: result.status };
  }
  if (isMissingTable(result)) {
    return { exists: false, status: result.status, error: result.error };
  }
  return { exists: true, status: result.status, error: result.error };
}

function summarizeRow(row) {
  if (!row || typeof row !== 'object') {
    return null;
  }
  const keys = [
    'id',
    'site_id',
    'site_hash',
    'site_key',
    'install_id',
    'site_identifier',
    'status',
    'plan',
    'plan_slug',
    'created_at',
    'updated_at',
    'requested_at',
    'generated_at',
  ];
  const summary = {};
  for (const key of keys) {
    if (row[key] !== undefined && row[key] !== null && row[key] !== '') {
      summary[key] = shortValue(row[key]);
    }
  }
  return summary;
}

function rowKey(row) {
  if (!row || typeof row !== 'object') {
    return '';
  }
  for (const key of ['id', 'request_id', 'event_id', 'uuid']) {
    if (row[key]) {
      return `${key}:${row[key]}`;
    }
  }
  return JSON.stringify(summarizeRow(row) || row);
}

function rowTimestamp(row) {
  if (!row || typeof row !== 'object') {
    return 0;
  }
  for (const key of TIMESTAMP_COLUMNS) {
    if (!row[key]) {
      continue;
    }
    const time = Date.parse(String(row[key]));
    if (!Number.isNaN(time)) {
      return time;
    }
  }
  return 0;
}

async function queryRowsForSite(supabase, table, candidates, { limit = 20, useOrdering = true } = {}) {
  const attempts = [];
  const orderColumns = useOrdering ? [...TIMESTAMP_COLUMNS, 'id', null] : [null];

  for (const candidate of candidates) {
    if (!candidate || !candidate.column || candidate.value === undefined || candidate.value === null || candidate.value === '') {
      continue;
    }

    for (const orderColumn of orderColumns) {
      let query = supabase
        .from(table)
        .select('*')
        .eq(candidate.column, candidate.value)
        .limit(limit);

      if (orderColumn) {
        query = query.order(orderColumn, { ascending: false });
      }

      const result = await query.execute();
      attempts.push({
        column: candidate.column,
        value: shortValue(candidate.value),
        order: orderColumn || '',
        ok: result.ok,
        status: result.status,
        errorCode: postgresErrorCode(result) || undefined,
        error: result.ok ? undefined : shortValue(postgresErrorMessage(result)),
      });

      if (result.ok && result.rows.length > 0) {
        return {
          tableExists: true,
          rows: result.rows,
          matchedBy: {
            column: candidate.column,
            value: shortValue(candidate.value),
          },
          attempts,
        };
      }

      if (result.ok) {
        break;
      }

      if (isMissingTable(result)) {
        return {
          tableExists: false,
          rows: [],
          matchedBy: null,
          attempts,
          error: result.error,
        };
      }

      if (isIgnorableColumnOrTypeError(result) && !orderColumn) {
        break;
      }
    }
  }

  return {
    tableExists: true,
    rows: [],
    matchedBy: null,
    attempts,
  };
}

async function queryRowsForSiteWithRecentFallback(supabase, table, candidates, sinceMs) {
  const result = await queryRowsForSite(supabase, table, candidates, { limit: 50, useOrdering: true });
  if (!result.tableExists || result.rows.length === 0) {
    return result;
  }
  const recentRows = result.rows.filter((row) => {
    const timestamp = rowTimestamp(row);
    return timestamp > 0 && timestamp >= sinceMs;
  });
  return {
    ...result,
    recentRows,
  };
}

function buildSiteFindCandidates(siteIdentifiers, baseUrl) {
  const url = normalizeBaseUrl(baseUrl);
  let hostname = '';
  try {
    hostname = new URL(url).hostname;
  } catch {
    hostname = '';
  }

  const candidates = [];
  for (const value of siteIdentifiers) {
    for (const column of SITE_TABLE_COLUMNS) {
      if (['url', 'site_url', 'home_url', 'domain'].includes(column)) {
        continue;
      }
      candidates.push({ column, value });
    }
  }
  for (const column of ['url', 'site_url', 'home_url']) {
    candidates.push({ column, value: url });
    candidates.push({ column, value: `${url}/` });
  }
  if (hostname) {
    candidates.push({ column: 'domain', value: hostname });
  }
  return candidates;
}

function buildRelatedCandidates(siteIdentifiers, siteRow) {
  const rowValues = [];
  if (siteRow && typeof siteRow === 'object') {
    for (const key of SITE_ID_COLUMNS) {
      rowValues.push(siteRow[key]);
    }
  }
  const values = unique([...siteIdentifiers, ...rowValues]);
  const candidates = [];
  for (const value of values) {
    for (const column of SITE_ID_COLUMNS) {
      candidates.push({ column, value });
    }
  }
  return candidates;
}

function collectKeyValues(value, keys, out = [], depth = 0) {
  if (!value || depth > 6) {
    return out;
  }
  if (Array.isArray(value)) {
    for (const item of value.slice(0, 25)) {
      collectKeyValues(item, keys, out, depth + 1);
    }
    return out;
  }
  if (typeof value !== 'object') {
    return out;
  }
  for (const [key, child] of Object.entries(value)) {
    if (keys.has(key) && (typeof child === 'string' || typeof child === 'number' || typeof child === 'boolean')) {
      out.push(child);
    }
    if (child && typeof child === 'object') {
      collectKeyValues(child, keys, out, depth + 1);
    }
  }
  return out;
}

function readNumberFromKeys(object, keys) {
  if (!object || typeof object !== 'object') {
    return null;
  }
  for (const key of keys) {
    if (object[key] === undefined || object[key] === null || object[key] === '') {
      continue;
    }
    const number = Number(object[key]);
    if (Number.isFinite(number)) {
      return number;
    }
  }
  return null;
}

function normalizeCredits(payload) {
  const data = payload && typeof payload === 'object' ? payload.data : null;
  const candidates = [
    payload && payload.credits,
    payload && payload.usage,
    payload && payload.quota,
    data && data.credits,
    data && data.usage,
    data && data.quota,
    data,
    payload,
  ].filter((item) => item && typeof item === 'object');

  let used = null;
  let total = null;
  let remaining = null;
  for (const candidate of candidates) {
    used = used ?? readNumberFromKeys(candidate, ['used', 'credits_used', 'creditsUsed', 'total_used']);
    total = total ?? readNumberFromKeys(candidate, ['total', 'limit', 'credits_total', 'creditsTotal', 'monthly_limit', 'total_limit']);
    remaining = remaining ?? readNumberFromKeys(candidate, ['remaining', 'credits_remaining', 'creditsRemaining']);
  }
  return { used, total, remaining };
}

function normalizeCounts(payload) {
  const data = payload && typeof payload === 'object' ? payload.data : null;
  const root = payload && typeof payload === 'object'
    ? (payload.counts || payload.stats || data?.counts || data?.stats || data || payload)
    : {};
  return {
    missing: readNumberFromKeys(root, ['missing', 'missing_alt', 'missingAlt']),
    review: readNumberFromKeys(root, ['review', 'needs_review', 'needsReview', 'weak']),
    complete: readNumberFromKeys(root, ['complete', 'optimized', 'with_alt', 'withAlt']),
    total: readNumberFromKeys(root, ['total', 'total_images', 'totalImages']),
  };
}

function compareNumbers(label, left, right, details) {
  if (left === null || right === null) {
    return true;
  }
  const ok = Number(left) === Number(right);
  if (!ok) {
    details[label] = { left, right };
  }
  return ok;
}

function looksLikeTrial(stateTruth, usage, pageBootstrap) {
  const attrs = pageBootstrap?.dashboardRootAttrs || {};
  const data = usage && typeof usage === 'object' ? usage.data : null;
  const usageRoot = data && typeof data === 'object' ? { ...data, ...usage } : (usage || {});
  const truthCredits = stateTruth?.credits && typeof stateTruth.credits === 'object' ? stateTruth.credits : {};

  const values = [
    stateTruth?.auth_state,
    stateTruth?.quota_type,
    truthCredits.plan,
    truthCredits.plan_slug,
    truthCredits.plan_type,
    usageRoot.auth_state,
    usageRoot.quota_type,
    usageRoot.plan,
    usageRoot.plan_slug,
    usageRoot.plan_type,
    usageRoot.source,
    attrs['data-bbai-auth-state'],
    attrs['data-bbai-quota-type'],
  ].filter((value) => value !== undefined && value !== null).map((value) => String(value).toLowerCase());

  const flags = [
    stateTruth?.is_trial,
    stateTruth?.isTrial,
    truthCredits.is_trial,
    truthCredits.isTrial,
    usageRoot.is_trial,
    usageRoot.isTrial,
    attrs['data-bbai-is-guest-trial'],
  ];

  return values.some((value) => value === 'trial' || value === 'anonymous_trial' || value === 'anonymous') ||
    flags.some((value) => value === true || value === 1 || value === '1' || String(value).toLowerCase() === 'true');
}

async function fetchWpRest(page, path) {
  return page.evaluate(async ({ path: endpointPath }) => {
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
    const route = String(endpointPath).replace(/^\/wp-json\//, '');
    const url = new URL(route, restRoot).toString();
    const response = await fetch(url, {
      method: 'GET',
      credentials: 'same-origin',
      headers: nonce ? { 'X-WP-Nonce': nonce } : {},
    });
    const text = await response.text();
    let body = null;
    try {
      body = text ? JSON.parse(text) : null;
    } catch {
      body = text;
    }
    return {
      ok: response.ok,
      status: response.status,
      url,
      body,
    };
  }, { path });
}

async function fetchAjaxSiteListing(page) {
  return page.evaluate(async () => {
    const ajaxUrl = window.bbai_ajax?.ajaxurl || window.bbai_ajax?.ajax_url || '';
    const nonce = window.bbai_ajax?.nonce || '';
    if (!ajaxUrl || !nonce) {
      return {
        available: false,
        ok: false,
        status: 0,
        body: null,
        message: 'bbai_ajax ajax URL or nonce was not present',
      };
    }
    const body = new URLSearchParams();
    body.append('action', 'beepbeepai_get_license_sites');
    body.append('nonce', nonce);
    const response = await fetch(ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
    });
    const text = await response.text();
    let parsed = null;
    try {
      parsed = text ? JSON.parse(text) : null;
    } catch {
      parsed = text;
    }
    return {
      available: true,
      ok: response.ok && !!(parsed && parsed.success),
      status: response.status,
      body: parsed,
    };
  });
}

async function readPageBootstrap(page) {
  return page.evaluate(() => {
    const dashboardRoot = document.querySelector('[data-bbai-dashboard-root="1"]');
    const loggedInRoot = document.querySelector('[data-bbai-logged-in-dashboard]');
    const attrs = {};
    if (dashboardRoot) {
      for (const attr of dashboardRoot.attributes) {
        if (attr.name.startsWith('data-bbai-')) {
          attrs[attr.name] = attr.value;
        }
      }
    }
    return {
      title: document.title,
      href: window.location.href,
      hasDashboardRoot: !!dashboardRoot,
      hasLoggedInDashboardRoot: !!loggedInRoot,
      loggedInState: loggedInRoot?.getAttribute('data-state') || '',
      stateTruthUrl: loggedInRoot?.getAttribute('data-bbai-li-state-truth-url') || '',
      dashboardRootAttrs: attrs,
      globals: {
        BBAI: window.BBAI || null,
        BBAI_DASH: window.BBAI_DASH || null,
        BBAI_DASHBOARD: window.BBAI_DASHBOARD || null,
        bbai_ajax: window.bbai_ajax || null,
      },
    };
  });
}

async function clickGenerateIfRequested(page, summary) {
  const candidateSelectors = [
    '[data-bbai-li-primary-cta]',
    '[data-bbai-li-action="generate-missing"]',
    '[data-action="generate-missing"]',
    '[data-bbai-action="generate_missing"]',
  ];

  for (const selector of candidateSelectors) {
    const locator = page.locator(selector).first();
    if (await locator.count() === 0) {
      continue;
    }
    if (!(await locator.isVisible().catch(() => false))) {
      continue;
    }
    const disabled = await locator.evaluate((node) => {
      return !!node.disabled ||
        node.getAttribute('aria-disabled') === 'true' ||
        node.hasAttribute('data-bbai-lock-reason');
    }).catch(() => true);
    const label = await locator.textContent().catch(() => '');
    if (disabled) {
      addCheck(summary, 'generate_button_available', false, 'Generate control was present but disabled or locked', {
        selector,
        label: shortValue(label),
      });
      return false;
    }
    await locator.click();
    addCheck(summary, 'generate_click', true, 'Optional generate click was dispatched through the dashboard UI', {
      selector,
      label: shortValue(label),
    });
    await page.waitForTimeout(3_000);
    return true;
  }

  addCheck(summary, 'generate_button_available', false, 'RUN_GENERATE=true but no visible generate control was found', {
    selectors: candidateSelectors,
  });
  return false;
}

async function pollForNewRows({ supabase, table, candidates, beforeKeys, sinceMs, timeoutMs }) {
  const started = Date.now();
  let lastResult = null;

  while (Date.now() - started <= timeoutMs) {
    lastResult = await queryRowsForSiteWithRecentFallback(supabase, table, candidates, sinceMs);
    if (!lastResult.tableExists) {
      return lastResult;
    }
    const rows = lastResult.rows || [];
    const recentRows = lastResult.recentRows || [];
    const newRows = rows.filter((row) => !beforeKeys.has(rowKey(row)));
    if (recentRows.length > 0 || newRows.length > 0) {
      return {
        ...lastResult,
        rows,
        recentRows,
        newRows,
      };
    }
    await new Promise((resolve) => setTimeout(resolve, POLL_INTERVAL_MS));
  }

  return lastResult || {
    tableExists: true,
    rows: [],
    recentRows: [],
    newRows: [],
    matchedBy: null,
    attempts: [],
  };
}

async function main() {
  const missing = REQUIRED_ENV.filter((name) => !process.env[name]);
  if (missing.length > 0) {
    missingEnvSummary(missing);
  }

  const config = {
    wpBaseUrl: normalizeBaseUrl(process.env.WP_BASE_URL),
    headless: boolEnv('HEADLESS', true),
    runGenerate: boolEnv('RUN_GENERATE', false),
    expectBilling: boolEnv('EXPECT_BILLING', false),
  };

  const summary = {
    ok: false,
    startedAt: nowIso(),
    config,
    checks: [],
    wordpress: {
      dashboardUrl: wpUrl(config.wpBaseUrl, '/wp-admin/admin.php?page=bbai'),
      endpoints: {},
    },
    identity: {},
    supabase: {
      allowedTables: Array.from(KNOWN_TABLES),
      tables: {},
    },
    generation: {
      requested: config.runGenerate,
    },
  };

  let browser = null;

  try {
    const { chromium } = loadPlaywright();
    const supabase = createSafeSupabaseRestClient({
      url: process.env.SUPABASE_URL,
      serviceRoleKey: process.env.SUPABASE_SERVICE_ROLE_KEY,
    });

    browser = await chromium.launch({ headless: config.headless });
    const page = await browser.newPage();

    await page.goto(wpUrl(config.wpBaseUrl, '/wp-login.php'), { waitUntil: 'domcontentloaded' });
    const loginFormVisible = await page.locator('#user_login').isVisible().catch(() => false);
    if (loginFormVisible) {
      await page.fill('#user_login', process.env.WP_ADMIN_USER);
      await page.fill('#user_pass', process.env.WP_ADMIN_PASS);
      await Promise.all([
        page.waitForURL(/\/wp-admin\//, { timeout: 20_000 }).catch(() => null),
        page.click('#wp-submit'),
      ]);
    }

    await page.goto(summary.wordpress.dashboardUrl, { waitUntil: 'domcontentloaded', timeout: 45_000 });
    await page
      .waitForSelector('[data-bbai-dashboard-root="1"], [data-bbai-logged-in-dashboard]', { timeout: 20_000 })
      .catch(() => null);
    const pageBootstrap = await readPageBootstrap(page);
    summary.wordpress.page = {
      title: pageBootstrap.title,
      href: pageBootstrap.href,
      hasDashboardRoot: pageBootstrap.hasDashboardRoot,
      hasLoggedInDashboardRoot: pageBootstrap.hasLoggedInDashboardRoot,
      loggedInState: pageBootstrap.loggedInState,
    };
    addCheck(summary, 'wp_admin_dashboard_loaded', pageBootstrap.hasDashboardRoot || pageBootstrap.hasLoggedInDashboardRoot, 'WordPress admin plugin dashboard loaded', summary.wordpress.page);

    const stateTruthRes = await fetchWpRest(page, '/wp-json/bbai/v1/dashboard/state-truth');
    const usageRes = await fetchWpRest(page, '/wp-json/bbai/v1/usage');
    const usageSummaryRes = await fetchWpRest(page, '/wp-json/bbai/v1/usage/summary');
    const siteListingRes = await fetchAjaxSiteListing(page);

    summary.wordpress.endpoints.stateTruth = {
      ok: stateTruthRes.ok,
      status: stateTruthRes.status,
      url: stateTruthRes.url,
      state: stateTruthRes.body && typeof stateTruthRes.body === 'object' ? stateTruthRes.body.state : undefined,
    };
    summary.wordpress.endpoints.usage = {
      ok: usageRes.ok,
      status: usageRes.status,
      url: usageRes.url,
    };
    summary.wordpress.endpoints.usageSummary = {
      ok: usageSummaryRes.ok,
      status: usageSummaryRes.status,
      url: usageSummaryRes.url,
    };
    summary.wordpress.endpoints.siteListing = {
      available: siteListingRes.available,
      ok: siteListingRes.ok,
      status: siteListingRes.status,
      success: !!(siteListingRes.body && siteListingRes.body.success),
      message: siteListingRes.message || siteListingRes.body?.data?.message || siteListingRes.body?.message || '',
    };

    addCheck(summary, 'state_truth_endpoint', stateTruthRes.ok, '/wp-json/bbai/v1/dashboard/state-truth returned OK', summary.wordpress.endpoints.stateTruth);
    addCheck(summary, 'usage_endpoint', usageRes.ok, '/wp-json/bbai/v1/usage returned OK', summary.wordpress.endpoints.usage);
    addCheck(summary, 'site_listing_probe', siteListingRes.available, 'AJAX site listing was probed when available', summary.wordpress.endpoints.siteListing, false);

    const stateTruth = stateTruthRes.body && typeof stateTruthRes.body === 'object' ? stateTruthRes.body : {};
    const usage = usageRes.body && typeof usageRes.body === 'object' ? usageRes.body : {};
    const usageSummary = usageSummaryRes.body && typeof usageSummaryRes.body === 'object' ? usageSummaryRes.body : {};
    const stateTruthState = String(stateTruth.state || '').toUpperCase();
    addCheck(summary, 'state_truth_state_not_error', stateTruthRes.ok && !!stateTruthState && stateTruthState !== 'ERROR', 'Dashboard state-truth returned a usable non-ERROR state', {
      state: stateTruth.state || '',
      message: stateTruth.message || stateTruth.error || '',
      fallback: !!stateTruth.fallback,
    });

    const rootAttrs = pageBootstrap.dashboardRootAttrs || {};
    const rootCredits = {
      used: readNumberFromKeys(rootAttrs, ['data-bbai-credits-used']),
      total: readNumberFromKeys(rootAttrs, ['data-bbai-credits-total']),
      remaining: readNumberFromKeys(rootAttrs, ['data-bbai-credits-remaining']),
    };
    const rootCounts = {
      missing: readNumberFromKeys(rootAttrs, ['data-bbai-missing-count']),
      review: readNumberFromKeys(rootAttrs, ['data-bbai-weak-count']),
      complete: readNumberFromKeys(rootAttrs, ['data-bbai-optimized-count']),
      total: readNumberFromKeys(rootAttrs, ['data-bbai-total-count']),
    };
    const truthCredits = normalizeCredits(stateTruth);
    const usageCredits = normalizeCredits(usage);
    const truthCounts = normalizeCounts(stateTruth);

    const endpointMismatch = {};
    const endpointConsistent = ['used', 'total', 'remaining'].every((key) => (
      compareNumbers(key, truthCredits[key], usageCredits[key], endpointMismatch)
    ));
    addCheck(summary, 'state_truth_usage_credit_alignment', endpointConsistent, 'State-truth credits align with usage endpoint where both expose values', {
      stateTruth: truthCredits,
      usage: usageCredits,
      mismatches: endpointMismatch,
    });

    const pageMismatch = {};
    const pageCreditsConsistent = ['used', 'total', 'remaining'].every((key) => (
      compareNumbers(`credits.${key}`, truthCredits[key], rootCredits[key], pageMismatch)
    ));
    const pageCountsConsistent = ['missing', 'review', 'complete', 'total'].every((key) => (
      compareNumbers(`counts.${key}`, truthCounts[key], rootCounts[key], pageMismatch)
    ));
    addCheck(summary, 'page_root_truth_alignment', pageCreditsConsistent && pageCountsConsistent, 'Dashboard DOM root aligns with state-truth where both expose values', {
      stateTruth: { credits: truthCredits, counts: truthCounts },
      pageRoot: { credits: rootCredits, counts: rootCounts },
      mismatches: pageMismatch,
    });

    const identityKeys = new Set([
      'site_hash',
      'siteHash',
      'site_id',
      'siteId',
      'site_key',
      'siteKey',
      'install_id',
      'installId',
      'site_identifier',
      'siteIdentifier',
    ]);
    const siteIdentifiers = unique([
      ...collectKeyValues(stateTruth, identityKeys),
      ...collectKeyValues(usage, identityKeys),
      ...collectKeyValues(usageSummary, identityKeys),
      ...collectKeyValues(pageBootstrap.globals, identityKeys),
    ]);
    summary.identity.candidates = siteIdentifiers.map(shortValue);
    summary.identity.primary = siteIdentifiers[0] || '';
    addCheck(summary, 'site_identifier_detected', siteIdentifiers.length > 0, 'Detected site identifier from page or normal plugin endpoints when exposed', {
      candidates: summary.identity.candidates,
    }, false);

    const siteFindCandidates = buildSiteFindCandidates(siteIdentifiers, config.wpBaseUrl);
    const siteRows = await queryRowsForSite(supabase, 'sites', siteFindCandidates, { limit: 5, useOrdering: true });
    const siteRow = siteRows.rows[0] || null;
    summary.supabase.tables.sites = {
      tableExists: siteRows.tableExists,
      rowCount: siteRows.rows.length,
      matchedBy: siteRows.matchedBy,
      sample: summarizeRow(siteRow),
    };
    addCheck(summary, 'linked_site_exists', siteRows.tableExists && !!siteRow, 'Linked site exists in Supabase sites table', summary.supabase.tables.sites);

    const relatedCandidates = buildRelatedCandidates(siteIdentifiers, siteRow);

    const quotaRows = await queryRowsForSite(supabase, 'site_quotas', relatedCandidates, { limit: 5, useOrdering: true });
    summary.supabase.tables.site_quotas = {
      tableExists: quotaRows.tableExists,
      rowCount: quotaRows.rows.length,
      matchedBy: quotaRows.matchedBy,
      sample: summarizeRow(quotaRows.rows[0]),
    };
    addCheck(summary, 'site_quota_exists', quotaRows.tableExists && quotaRows.rows.length > 0, 'site_quotas row exists for linked site', summary.supabase.tables.site_quotas);

    const isTrialScenario = looksLikeTrial(stateTruth, usage, pageBootstrap);
    summary.identity.trialScenarioDetected = isTrialScenario;
    const trialRows = await queryRowsForSite(supabase, 'site_trials', relatedCandidates, { limit: 5, useOrdering: true });
    summary.supabase.tables.site_trials = {
      tableExists: trialRows.tableExists,
      rowCount: trialRows.rows.length,
      matchedBy: trialRows.matchedBy,
      sample: summarizeRow(trialRows.rows[0]),
      required: isTrialScenario,
    };
    addCheck(summary, 'site_trial_exists_when_trial', !isTrialScenario || (trialRows.tableExists && trialRows.rows.length > 0), 'site_trials row exists when the live state is a trial scenario', summary.supabase.tables.site_trials, isTrialScenario);

    const subscriptionRows = await queryRowsForSite(supabase, 'site_subscriptions', relatedCandidates, { limit: 5, useOrdering: true });
    summary.supabase.tables.site_subscriptions = {
      tableExists: subscriptionRows.tableExists,
      rowCount: subscriptionRows.rows.length,
      matchedBy: subscriptionRows.matchedBy,
      sample: summarizeRow(subscriptionRows.rows[0]),
      required: config.expectBilling,
    };
    addCheck(summary, 'site_subscription_exists_when_expected', !config.expectBilling || (subscriptionRows.tableExists && subscriptionRows.rows.length > 0), 'site_subscriptions row exists when EXPECT_BILLING=true', summary.supabase.tables.site_subscriptions, config.expectBilling);

    const beforeGenerationRequests = await queryRowsForSite(supabase, 'generation_requests', relatedCandidates, { limit: 50, useOrdering: true });
    const beforeUsageEvents = await queryRowsForSite(supabase, 'usage_events', relatedCandidates, { limit: 50, useOrdering: true });
    const imageAltStatesExists = await tableExists(supabase, 'image_alt_states');
    let beforeImageAltStates = { tableExists: imageAltStatesExists.exists, rows: [] };
    if (imageAltStatesExists.exists) {
      beforeImageAltStates = await queryRowsForSite(supabase, 'image_alt_states', relatedCandidates, { limit: 50, useOrdering: true });
    }

    if (config.runGenerate) {
      const generateStartedAt = Date.now();
      summary.generation.startedAt = new Date(generateStartedAt).toISOString();
      const clicked = await clickGenerateIfRequested(page, summary);

      if (clicked) {
        const beforeGenerationRequestKeys = new Set((beforeGenerationRequests.rows || []).map(rowKey));
        const beforeUsageEventKeys = new Set((beforeUsageEvents.rows || []).map(rowKey));
        const beforeImageAltStateKeys = new Set((beforeImageAltStates.rows || []).map(rowKey));

        const generationRows = await pollForNewRows({
          supabase,
          table: 'generation_requests',
          candidates: relatedCandidates,
          beforeKeys: beforeGenerationRequestKeys,
          sinceMs: generateStartedAt,
          timeoutMs: GENERATE_WAIT_MS,
        });
        summary.supabase.tables.generation_requests = {
          tableExists: generationRows.tableExists,
          rowCount: generationRows.rows?.length || 0,
          recentCount: generationRows.recentRows?.length || 0,
          newCount: generationRows.newRows?.length || 0,
          matchedBy: generationRows.matchedBy,
          sample: summarizeRow((generationRows.recentRows || generationRows.newRows || generationRows.rows || [])[0]),
          required: true,
        };
        addCheck(summary, 'generation_request_exists_after_generate', generationRows.tableExists && ((generationRows.recentRows?.length || 0) > 0 || (generationRows.newRows?.length || 0) > 0), 'generation_requests row exists after RUN_GENERATE click', summary.supabase.tables.generation_requests);

        const usageEventRows = await pollForNewRows({
          supabase,
          table: 'usage_events',
          candidates: relatedCandidates,
          beforeKeys: beforeUsageEventKeys,
          sinceMs: generateStartedAt,
          timeoutMs: GENERATE_WAIT_MS,
        });
        summary.supabase.tables.usage_events = {
          tableExists: usageEventRows.tableExists,
          rowCount: usageEventRows.rows?.length || 0,
          recentCount: usageEventRows.recentRows?.length || 0,
          newCount: usageEventRows.newRows?.length || 0,
          matchedBy: usageEventRows.matchedBy,
          sample: summarizeRow((usageEventRows.recentRows || usageEventRows.newRows || usageEventRows.rows || [])[0]),
          required: true,
        };
        addCheck(summary, 'usage_event_exists_after_generate', usageEventRows.tableExists && ((usageEventRows.recentRows?.length || 0) > 0 || (usageEventRows.newRows?.length || 0) > 0), 'usage_events row exists after RUN_GENERATE click', summary.supabase.tables.usage_events);

        if (imageAltStatesExists.exists) {
          const imageStateRows = await pollForNewRows({
            supabase,
            table: 'image_alt_states',
            candidates: relatedCandidates,
            beforeKeys: beforeImageAltStateKeys,
            sinceMs: generateStartedAt,
            timeoutMs: GENERATE_WAIT_MS,
          });
          summary.supabase.tables.image_alt_states = {
            tableExists: imageStateRows.tableExists,
            rowCount: imageStateRows.rows?.length || 0,
            recentCount: imageStateRows.recentRows?.length || 0,
            newCount: imageStateRows.newRows?.length || 0,
            matchedBy: imageStateRows.matchedBy,
            sample: summarizeRow((imageStateRows.recentRows || imageStateRows.newRows || imageStateRows.rows || [])[0]),
            required: true,
          };
          addCheck(summary, 'image_alt_state_exists_after_generate', imageStateRows.tableExists && ((imageStateRows.recentRows?.length || 0) > 0 || (imageStateRows.newRows?.length || 0) > 0), 'image_alt_states row exists after RUN_GENERATE click when table exists', summary.supabase.tables.image_alt_states);
        } else {
          summary.supabase.tables.image_alt_states = {
            tableExists: false,
            rowCount: 0,
            required: false,
          };
          addCheck(summary, 'image_alt_states_table_optional', true, 'image_alt_states table is absent, so image state verification is skipped', summary.supabase.tables.image_alt_states, false);
        }
      }
    } else {
      summary.supabase.tables.generation_requests = {
        tableExists: beforeGenerationRequests.tableExists,
        rowCount: beforeGenerationRequests.rows.length,
        matchedBy: beforeGenerationRequests.matchedBy,
        sample: summarizeRow(beforeGenerationRequests.rows[0]),
        required: false,
      };
      summary.supabase.tables.usage_events = {
        tableExists: beforeUsageEvents.tableExists,
        rowCount: beforeUsageEvents.rows.length,
        matchedBy: beforeUsageEvents.matchedBy,
        sample: summarizeRow(beforeUsageEvents.rows[0]),
        required: false,
      };
      summary.supabase.tables.image_alt_states = {
        tableExists: imageAltStatesExists.exists,
        status: imageAltStatesExists.status,
        rowCount: beforeImageAltStates.rows?.length || 0,
        matchedBy: beforeImageAltStates.matchedBy || null,
        sample: summarizeRow(beforeImageAltStates.rows?.[0]),
        required: false,
      };
      addCheck(summary, 'generation_tables_observed_without_generate', true, 'Generation-specific tables were inspected but not required because RUN_GENERATE=false', {
        generation_requests: summary.supabase.tables.generation_requests,
        usage_events: summary.supabase.tables.usage_events,
        image_alt_states: summary.supabase.tables.image_alt_states,
      }, false);
    }

    await browser.close();
    browser = null;
    finalize(summary);
  } catch (error) {
    addCheck(summary, 'fatal_error', false, error && error.stack ? error.stack : String(error), {}, true);
    if (browser) {
      await browser.close().catch(() => {});
    }
    finalize(summary);
  }
}

main();
