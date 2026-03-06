/**
 * BeepBeep AI Backend API Contract Tests
 *
 * Verifies backend endpoints used by the plugin. Run against deployed backend.
 *
 * Run: BBAI_E2E_API_URL=https://alttext-ai-backend.onrender.com npx playwright test tests/e2e/api-contract.spec.js
 */

const { test, expect } = require('@playwright/test');

const API_URL = process.env.BBAI_E2E_API_URL || 'https://alttext-ai-backend.onrender.com';
const HAS_API_URL = Boolean(process.env.BBAI_E2E_API_URL);

test.describe('Backend API Contract', () => {
  test.beforeEach(() => {
    test.skip(!HAS_API_URL, 'Set BBAI_E2E_API_URL to run API contract tests (or uses default production URL).');
  });

  test('health endpoint returns 200', async ({ request }) => {
    const res = await request.get(`${API_URL}/health`);
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.ok).toBe(true);
  });

  test('license activate endpoint exists (no 404)', async ({ request }) => {
    const res = await request.post(`${API_URL}/api/license/activate`, {
      data: {
        licenseKey: 'test-invalid-key',
        siteHash: 'test-hash',
        siteUrl: 'https://example.com',
        installId: 'test-hash',
      },
      headers: { 'Content-Type': 'application/json' },
    });
    // Path must exist (not 404); may return 400/401 for invalid key or auth
    expect(res.status()).not.toBe(404);
    const body = await res.json();
    expect(body).toHaveProperty('error');
    // Either activate error (invalid key) or auth error (license required)
    const err = (body.error || '').toLowerCase();
    expect(err.includes('invalid') || err.includes('license') || err.includes('required')).toBeTruthy();
  });

  test('billing plans returns 200 (public)', async ({ request }) => {
    const res = await request.get(`${API_URL}/billing/plans`);
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.success).toBe(true);
    expect(Array.isArray(body.plans)).toBe(true);
  });

  test('usage requires auth (401 without headers)', async ({ request }) => {
    const res = await request.get(`${API_URL}/api/usage`);
    expect(res.status()).toBe(401);
  });

  test('billing info requires auth (401 without headers)', async ({ request }) => {
    const res = await request.get(`${API_URL}/billing/info`);
    expect(res.status()).toBe(401);
  });
});
