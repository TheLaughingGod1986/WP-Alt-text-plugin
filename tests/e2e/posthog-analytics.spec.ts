import { expect, test } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const POSTHOG_BRIDGE = path.resolve(process.cwd(), 'assets/js/bbai-posthog.js');
const TELEMETRY = path.resolve(process.cwd(), 'assets/js/bbai-telemetry.js');

test('PostHog bridge initializes once with privacy-safe replay and lifecycle APIs', async ({ page }) => {
  await page.addInitScript(() => {
    (window as any).__captures = [];
    (window as any).__replayStarts = [];
    (window as any).__initCalls = 0;
    (window as any).posthog = {
      __loaded: false,
      config: null,
      init(_key: string, config: Record<string, unknown>) {
        (window as any).__initCalls += 1;
        this.__loaded = true;
        this.config = config;
        if (typeof config.loaded === 'function') {
          config.loaded(this);
        }
      },
      capture(event: string, properties: Record<string, unknown>) {
        (window as any).__captures.push({ event, properties });
      },
      register(properties: Record<string, unknown>) {
        this.registered = { ...(this.registered || {}), ...properties };
      },
      identify(id: string) {
        this.identified = id;
      },
      setPersonProperties(properties: Record<string, unknown>) {
        this.personProperties = properties;
      },
      startSessionRecording(force?: boolean) {
        (window as any).__replayStarts.push(force === true);
      },
      get_session_id() {
        return 'session-fixture';
      },
    };
    (window as any).BBAI_POSTHOG = {
      enabled: true,
      apiKey: 'phc_test',
      apiHost: 'https://us.i.posthog.com',
      replayEnabled: true,
      replaySampleRate: 0,
      debug: true,
      context: {
        page: 'dashboard',
        site_hash: 'site_fixture',
        plugin_version: '1.0.0',
        plan_type: 'free',
      },
    };
  });

  await page.goto('about:blank');
  await page.addScriptTag({ path: POSTHOG_BRIDGE });
  await page.evaluate(() => {
    (window as any).bbaiTrackGenerationLifecycle('generation_request_started', {
      job_id: 'job_123',
      password: 'must-not-leak',
      email: 'person@example.com',
      apiKey: 'sk-secret',
      jwt: 'eyJabc.def.ghi',
      nonce: 'nonce-fixture',
      licenseKey: 'license-fixture',
      site_url: 'https://example.test/wp-admin/',
      nested: {
        siteUrl: 'https://nested.example.test',
        token: 'nested-token',
      },
      requested_count: 2,
    });
    (window as any).bbaiCaptureError(new Error('Fixture failure'), { endpoint: 'beepbeepai_bulk_queue' });
    (window as any).bbaiTrackGenerationLifecycle('generation_failed', { job_id: 'job_123', error_message: 'failed' });
    (window as any).bbaiTrackGenerationLifecycle('generation_stuck', { job_id: 'job_123', duration_ms: 20000 });
    (window as any).bbaiTrackGenerationLifecycle('upgrade_clicked', { trigger_location: 'lower_cta' });
    (window as any).bbaiTrackGenerationLifecycle('checkout_started', { target_plan: 'growth' });
  });
  await page.addScriptTag({ path: POSTHOG_BRIDGE });

  const result = await page.evaluate(() => ({
    initCalls: (window as any).__initCalls,
    initConfig: (window as any).posthog.config,
    captures: (window as any).__captures,
    replayStarts: (window as any).__replayStarts,
    api: {
      track: typeof (window as any).bbaiTrack,
      identify: typeof (window as any).bbaiIdentify,
      captureError: typeof (window as any).bbaiCaptureError,
      replayContext: typeof (window as any).bbaiStartReplayContext,
      lifecycle: typeof (window as any).bbaiTrackGenerationLifecycle,
    },
  }));

  expect(result.initCalls).toBe(1);
  expect(result.initConfig.autocapture).toBe(false);
  expect(result.initConfig.capture_pageview).toBe(true);
  expect(result.initConfig.capture_pageleave).toBe(true);
  expect(result.initConfig.persistence).toBe('localStorage+cookie');
  expect(result.initConfig.disable_session_recording).toBe(false);
  expect(result.initConfig.session_recording.maskAllInputs).toBe(true);
  expect(result.initConfig.session_recording.captureConsoleLog).toBe(true);
  expect(result.api).toEqual({
    track: 'function',
    identify: 'function',
    captureError: 'function',
    replayContext: 'function',
    lifecycle: 'function',
  });
  expect(result.replayStarts).toContain(true);
  expect(result.captures.some((event: any) => event.event === 'generation_request_started')).toBe(true);
  for (const eventName of ['generation_failed', 'generation_stuck', 'upgrade_clicked', 'checkout_started']) {
    expect(result.captures.some((event: any) => event.event === eventName)).toBe(true);
  }
  const serialized = JSON.stringify(result.captures);
  for (const secret of [
    'must-not-leak',
    'person@example.com',
    'sk-secret',
    'eyJabc.def.ghi',
    'nonce-fixture',
    'license-fixture',
    'https://example.test',
    'https://nested.example.test',
    'nested-token',
  ]) {
    expect(serialized).not.toContain(secret);
  }
});

test('PostHog bridge dedupes repeated events and does not let capture failures throw', async ({ page }) => {
  await page.addInitScript(() => {
    (window as any).__captures = [];
    (window as any).__thrown = false;
    (window as any).posthog = {
      __loaded: false,
      config: null,
      init(_key: string, config: Record<string, unknown>) {
        this.__loaded = true;
        this.config = config;
        if (typeof config.loaded === 'function') {
          config.loaded(this);
        }
      },
      capture(event: string, properties: Record<string, unknown>) {
        if (event === 'generation_failed') {
          throw new Error('PostHog unavailable');
        }
        (window as any).__captures.push({ event, properties });
      },
      register() {},
      startSessionRecording() {},
    };
    (window as any).BBAI_POSTHOG = {
      enabled: true,
      apiKey: 'phc_test',
      apiHost: 'https://us.i.posthog.com',
      replayEnabled: true,
      replaySampleRate: 0,
      context: { page: 'dashboard' },
    };
  });

  await page.goto('about:blank');
  await page.addScriptTag({ path: POSTHOG_BRIDGE });
  await page.evaluate(() => {
    try {
      (window as any).bbaiTrack('generation_cta_clicked', { source: 'dashboard', requested_count: 50 });
      (window as any).bbaiTrack('generation_cta_clicked', { source: 'dashboard', requested_count: 50 });
      (window as any).bbaiTrackGenerationLifecycle('generation_failed', { job_id: 'job_fail', error_code: 'fixture' });
    } catch (_error) {
      (window as any).__thrown = true;
    }
  });

  const result = await page.evaluate(() => ({
    thrown: (window as any).__thrown,
    ctaCaptures: (window as any).__captures.filter((event: any) => event.event === 'generation_cta_clicked').length,
  }));

  expect(result.thrown).toBe(false);
  expect(result.ctaCaptures).toBe(1);
});

test('PostHog bridge does not report failed PostHog ingestion as app AJAX failures', async ({ page }) => {
  await page.addInitScript(() => {
    (window as any).__captures = [];
    const originalFetch = window.fetch.bind(window);
    window.fetch = ((input: RequestInfo | URL, init?: RequestInit) => {
      const url = typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url;
      if (url.includes('posthog.com')) {
        return Promise.resolve(new Response('{}', { status: 401 }));
      }
      if (url.includes('/wp-admin/admin-ajax.php')) {
        return Promise.resolve(new Response('{}', { status: 500 }));
      }
      return originalFetch(input, init);
    }) as typeof window.fetch;
    (window as any).posthog = {
      __loaded: false,
      config: null,
      init(_key: string, config: Record<string, unknown>) {
        this.__loaded = true;
        this.config = config;
        if (typeof config.loaded === 'function') {
          config.loaded(this);
        }
      },
      capture(event: string, properties: Record<string, unknown>) {
        (window as any).__captures.push({ event, properties });
      },
      register() {},
      startSessionRecording() {},
    };
    (window as any).BBAI_POSTHOG = {
      enabled: true,
      apiKey: 'phc_test',
      apiHost: 'https://us.i.posthog.com',
      replayEnabled: false,
      replaySampleRate: 0,
      context: { page: 'dashboard' },
    };
  });

  await page.goto('about:blank');
  await page.addScriptTag({ path: POSTHOG_BRIDGE });
  await page.evaluate(async () => {
    await fetch('https://us.i.posthog.com/e/?ip=0');
    await fetch('/wp-admin/admin-ajax.php?action=beepbeepai_fixture');
  });

  const captures = await page.evaluate(() => (window as any).__captures);
  const ajaxFailures = captures.filter((event: any) => event.event === 'ajax_request_failed');

  expect(ajaxFailures).toHaveLength(1);
  expect(ajaxFailures[0].properties.endpoint).toContain('/wp-admin/admin-ajax.php');
  expect(JSON.stringify(captures)).not.toContain('posthog.com');
});

test('PostHog bridge ignores intentional fetch aborts as network failures', async ({ page }) => {
  await page.addInitScript(() => {
    (window as any).__captures = [];
    window.fetch = (() => Promise.reject(new DOMException('The operation was aborted.', 'AbortError'))) as typeof window.fetch;
    (window as any).posthog = {
      __loaded: false,
      config: null,
      init(_key: string, config: Record<string, unknown>) {
        this.__loaded = true;
        this.config = config;
        if (typeof config.loaded === 'function') {
          config.loaded(this);
        }
      },
      capture(event: string, properties: Record<string, unknown>) {
        (window as any).__captures.push({ event, properties });
      },
      register() {},
      startSessionRecording() {},
    };
    (window as any).BBAI_POSTHOG = {
      enabled: true,
      apiKey: 'phc_test',
      apiHost: 'https://us.i.posthog.com',
      replayEnabled: false,
      context: { page: 'dashboard' },
    };
  });

  await page.goto('about:blank');
  await page.addScriptTag({ path: POSTHOG_BRIDGE });
  await page.evaluate(async () => {
    try {
      await fetch('/wp-json/bbai/v1/dashboard/state-truth');
    } catch (_error) {
      // Expected fixture abort.
    }
  });

  const captures = await page.evaluate(() => (window as any).__captures);
  expect(captures.some((event: any) => event.event === 'ajax_request_failed')).toBe(false);
});

test('PostHog bridge captures dashboard custom analytics events directly', async ({ page }) => {
  await page.addInitScript(() => {
    (window as any).__captures = [];
    (window as any).__replayStarts = [];
    (window as any).posthog = {
      __loaded: false,
      config: null,
      init(_key: string, config: Record<string, unknown>) {
        this.__loaded = true;
        this.config = config;
        if (typeof config.loaded === 'function') {
          config.loaded(this);
        }
      },
      capture(event: string, properties: Record<string, unknown>) {
        (window as any).__captures.push({ event, properties });
      },
      register() {},
      identify() {},
      setPersonProperties() {},
      startSessionRecording(force?: boolean) {
        (window as any).__replayStarts.push(force === true);
      },
      get_session_id() {
        return 'session-fixture';
      },
    };
    (window as any).BBAI_POSTHOG = {
      enabled: true,
      apiKey: 'phc_test',
      apiHost: 'https://us.i.posthog.com',
      replayEnabled: true,
      replaySampleRate: 1,
      forceReplayEverySession: true,
      context: { page: 'alt_library', site_hash: 'site_fixture' },
    };
  });

  await page.goto('about:blank');
  await page.addScriptTag({ path: POSTHOG_BRIDGE });
  await page.evaluate(() => {
    document.dispatchEvent(new CustomEvent('bbai:analytics', {
      detail: { event: 'alt_library_regenerate_clicked', source: 'library', attachment_id: 149 },
    }));
    document.dispatchEvent(new CustomEvent('bbai:analytics', {
      detail: { event: 'alt_library_copy_clicked', source: 'library', attachment_id: 149 },
    }));
    document.dispatchEvent(new CustomEvent('bbai:analytics', {
      detail: { event: 'generation_request_started', source: 'dashboard', requested_count: 3 },
    }));
  });

  const result = await page.evaluate(() => ({
    captures: (window as any).__captures,
    replayStarts: (window as any).__replayStarts,
    disableSessionRecording: (window as any).posthog.config.disable_session_recording,
  }));

  expect(result.disableSessionRecording).toBe(false);
  expect(result.replayStarts).toContain(true);
  for (const eventName of ['alt_library_regenerate_clicked', 'alt_library_copy_clicked', 'generation_request_started']) {
    expect(result.captures.some((event: any) => event.event === eventName)).toBe(true);
  }
});

test('Canonical generation taxonomy uses generation_cta_clicked for dashboard generate CTAs', () => {
  const telemetrySource = fs.readFileSync(TELEMETRY, 'utf8');
  expect(telemetrySource).toContain("generation_cta_clicked', buildCtaProps");
  expect(telemetrySource).not.toContain("generate_cta_clicked', buildCtaProps");
});

test('50-image generation lifecycle sequence is capturable without raw secrets', async ({ page }) => {
  await page.addInitScript(() => {
    (window as any).__captures = [];
    (window as any).posthog = {
      __loaded: false,
      config: null,
      init(_key: string, config: Record<string, unknown>) {
        this.__loaded = true;
        this.config = config;
        if (typeof config.loaded === 'function') {
          config.loaded(this);
        }
      },
      capture(event: string, properties: Record<string, unknown>) {
        (window as any).__captures.push({ event, properties });
      },
      register() {},
      startSessionRecording() {},
    };
    (window as any).BBAI_POSTHOG = {
      enabled: true,
      apiKey: 'phc_test',
      apiHost: 'https://us.i.posthog.com',
      replayEnabled: true,
      replaySampleRate: 0,
      context: { page: 'dashboard', site_hash: 'site_fixture' },
    };
  });

  await page.goto('about:blank');
  await page.addScriptTag({ path: POSTHOG_BRIDGE });
  await page.evaluate(() => {
    const base = { requested_count: 50, source: 'dashboard', license_key: 'raw-license' };
    (window as any).bbaiTrackGenerationLifecycle('generation_cta_clicked', base);
    (window as any).bbaiTrackGenerationLifecycle('generation_request_started', { ...base, ajax_action: 'beepbeepai_bulk_queue' });
    (window as any).bbaiTrackGenerationLifecycle('generation_job_created', { ...base, job_id: 'job_50' });
    (window as any).bbaiTrackGenerationLifecycle('generation_progress_updated', { ...base, job_id: 'job_50', processed_count: 25 });
    (window as any).bbaiTrackGenerationLifecycle('generation_completed', { ...base, job_id: 'job_50', processed_count: 50 });
  });

  const captures = await page.evaluate(() => (window as any).__captures);
  for (const eventName of [
    'generation_cta_clicked',
    'generation_request_started',
    'generation_job_created',
    'generation_progress_updated',
    'generation_completed',
  ]) {
    expect(captures.some((event: any) => event.event === eventName)).toBe(true);
  }
  expect(JSON.stringify(captures)).not.toContain('raw-license');
});
