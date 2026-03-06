# BeepBeep AI Plugin – Testing Plan

**Created:** March 4, 2026  
**Purpose:** End-to-end testing plan for plugin + backend integration after API alignment fixes.

---

## 1. Prerequisites

- [ ] WordPress site running (local or staging)
- [ ] BeepBeep AI plugin installed and activated
- [ ] Backend deployed (alttext-ai-backend.onrender.com) with latest changes from `main`
- [ ] Admin credentials for WordPress

---

## 2. Browser E2E Test Scenarios (cursor-ide-browser / Playwright)

### 2.1 Authentication flow

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Navigate to Media → AI ALT Text | Dashboard loads |
| 2 | Click "Log in" or "Authenticate" | Auth modal opens |
| 3 | Enter email + password | Login succeeds |
| 4 | Verify dashboard shows logged-in state | Email, plan badge visible |
| 5 | Log out | Returns to logged-out state |

### 2.2 License activation

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Open license settings / activation UI | License key input visible |
| 2 | Enter valid license key | Activation succeeds |
| 3 | Verify "License activated" message | No errors |
| 4 | Check usage shows plan limit | Correct limit (e.g. 1000 for Pro) |

### 2.3 Site disconnect (Agency)

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Log in as Agency license holder | Dashboard loads |
| 2 | Open "License sites" or "Manage sites" | List of sites shown |
| 3 | Click "Disconnect" on a site | Confirmation modal |
| 4 | Confirm disconnect | Site removed from list |

### 2.4 Billing info

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Log in with paid plan | Dashboard loads |
| 2 | Open billing / subscription section | Billing info shown |
| 3 | Verify plan, next billing date | No 404 or API errors |

### 2.5 Alt text generation

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Ensure media library has images | Dashboard shows stats |
| 2 | Click "Generate Missing" | Queue starts |
| 3 | Wait for processing | Progress updates |
| 4 | Verify alt text applied | Images show generated alt |

### 2.6 Trial usage

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Use plugin without logging in | Trial mode |
| 2 | Generate alt text (up to trial limit) | Trial count increments |
| 3 | Exceed trial limit | Upgrade prompt shown |

---

## 3. API Contract Tests (Backend)

Run against deployed backend:

```bash
# Health
curl https://alttext-ai-backend.onrender.com/health

# License activate (public)
curl -X POST https://alttext-ai-backend.onrender.com/api/license/activate \
  -H "Content-Type: application/json" \
  -d '{"licenseKey":"test-key","siteHash":"test-hash","siteUrl":"https://example.com"}'

# License sites (requires X-License-Key)
curl -H "X-License-Key: YOUR_KEY" \
  https://alttext-ai-backend.onrender.com/api/licenses/sites

# Billing info (requires auth)
curl -H "X-License-Key: YOUR_KEY" \
  https://alttext-ai-backend.onrender.com/billing/info

# Disconnect site (requires auth)
curl -X DELETE -H "X-License-Key: YOUR_KEY" \
  https://alttext-ai-backend.onrender.com/api/licenses/sites/SITE_HASH
```

---

## 4. Known Issues to Verify

| Issue | Status | Fix |
|-------|--------|-----|
| License activate path | Fixed | Backend now serves `/api/license/activate` |
| License sites path | Fixed | Backend now serves `/api/licenses/sites` |
| Activate body format | Fixed | Backend accepts camelCase |
| Activate response | Fixed | Backend returns `organization` |
| Billing info 404 | Fixed | Backend now has `GET /billing/info` |
| Disconnect site 404 | Fixed | Backend now has `DELETE /license/sites/:id` |

---

## 5. Playwright / Browser MCP Script (Outline)

```javascript
// Example test flow (adapt for Playwright or cursor-ide-browser)
test('license activation flow', async ({ page }) => {
  await page.goto('/wp-admin/upload.php?page=bbai');
  await page.fill('[name="log"]', 'admin');
  await page.fill('[name="pwd"]', process.env.WP_PASSWORD);
  await page.click('#wp-submit');
  await page.waitForURL('**/upload.php?page=bbai**');
  // Open license modal, enter key, confirm activation
  // Assert success message
});
```

---

## 6. Fix Plan (If Issues Found)

| Problem | Action |
|---------|--------|
| 401 on license activate | Check auth middleware public paths |
| 404 on activate | Verify backend deployed, restart Render |
| Organization null in frontend | Check activate response shape |
| Billing info empty | Verify license has stripe_customer_id |
| Disconnect fails | Check site belongs to license |

---

## 7. Running Tests

```bash
# API contract tests (no WordPress needed)
npm run test:e2e:api

# Plugin + dashboard tests (requires WordPress at BBAI_E2E_BASE_URL)
BBAI_E2E_BASE_URL=http://localhost:8080 BBAI_E2E_WP_USER=admin BBAI_E2E_WP_PASS=xxx npm run test:e2e:plugin

# Or use env file: export BBAI_E2E_BASE_URL=... BBAI_E2E_WP_USER=... BBAI_E2E_WP_PASS=...
```

## 8. Next Steps

1. Run plugin tests with valid credentials (see `.env.e2e.example`).
2. Confirm backend deployment on Render (auto-deploy from `main`).
3. Fix any issues found and re-test.
4. Add Playwright to CI for regression.
