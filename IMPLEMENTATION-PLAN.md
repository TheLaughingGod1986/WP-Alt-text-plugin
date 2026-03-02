# Implementation Plan – Test Report Recommendations

**Created:** February 11, 2026  
**Source:** PLUGIN-TEST-REPORT.md

---

## Overview

This plan organizes all recommendations into phases with effort estimates, dependencies, and concrete tasks. Work can be done incrementally.

| Phase | Focus | Est. effort | Priority |
|-------|-------|-------------|----------|
| 1 | Bug fixes & doc alignment | 2–4 hrs | High |
| 2 | UX improvements | 3–5 hrs | Medium |
| 3 | Technical debt & tests | 6–10 hrs | Medium |
| 4 | Performance & polish | 4–6 hrs | Low |

---

## Phase 1: Bug Fixes & Documentation Alignment

### 1.1 Inconsistent free plan messaging

**Goal:** Align free plan messaging across readme and UI.

**Tasks:**
1. Decide: Does free plan include bulk or not?
   - If yes: Update `ComparisonTable.jsx` so `Bulk optimisation` = true for free.
   - If no: Update readme.txt to remove "Bulk generation tools" from Free Plan Features.
2. Check `templates/upgrade-modal.php`, `admin/partials/dashboard-logged-out.php`, and any other copy that mentions free vs paid bulk.
3. Align all copy and UI.

**Files to modify:**
- `readme.txt`
- `admin/components/ComparisonTable.jsx`
- Possibly: `templates/upgrade-modal.php`, `admin/partials/dashboard-logged-out.php`, `admin/components/EnterprisePricingModal.jsx`

**Acceptance:** Free plan and bulk are described consistently in readme and UI.

---

### 1.2 Regenerate alt text mismatch

**Goal:** Ensure correct attachment is sent to the API and that the description matches the image.

**Tasks:**
1. Trace Regen flow:
   - `assets/src/js/admin/single-regenerate.js` or equivalent
   - REST/AJAX handler: `includes/controllers/class-generation-controller.php` or `admin/class-bbai-core.php`
   - API client: `includes/class-api-client-v2.php`
2. Check: Is the correct attachment ID passed from the frontend?
3. Check: Does the backend send the right image URL or base64 to the API?
4. Add logging or debug output to verify attachment ID and image URL at each step.
5. If the API returns a wrong description, consider:
   - Retry logic
   - Better error messages
   - Or flagging as API issue for backend team

**Files to inspect/modify:**
- `assets/src/js/admin/single-regenerate.js`
- `admin/partials/library-tab.php` (or Library.jsx)
- `includes/controllers/class-generation-controller.php`
- `includes/services/class-generation-service.php`
- `includes/class-api-client-v2.php`

**Acceptance:** Regen on a given image returns a description that matches that image.

---

### 1.3 Installation instructions mismatch

**Goal:** Update readme so users can find the plugin in the admin.

**Tasks:**
1. Update readme.txt Installation section:
   - Step 4: Change "Open Media -> AI Alt Text" to "Open BeepBeep AI -> Dashboard (or ALT Library)".
   - Optionally add a short note about the menu location.
2. Check for other references to "Media -> AI Alt Text" in readme, docs, or onboarding.

**Files to modify:**
- `readme.txt` (around line 99)

**Acceptance:** Installation steps match the current menu structure.

---

### 1.4 Session expired notice

**Goal:** Investigate and fix the "Session expired" notice if it appears when the user is logged in.

**Tasks:**
1. Decide: Is this WordPress core or plugin-specific?
2. If core: Check if it appears only when WP session expires; if so, document as expected and optionally add a short note in the plugin UI.
3. If plugin: Check if the plugin uses any custom session handling or heartbeat that could conflict.
4. Search for `heartbeat` or `session` in plugin code.
5. If the notice is incorrect or misleading, add a filter or adjustment to suppress or clarify it on plugin pages.

**Files to inspect:**
- `assets/src/js/bbai-error-handler.js` (heartbeat handling)
- Any custom auth or session logic in `includes/` or `admin/`

**Acceptance:** "Session expired" is either correct or no longer misleading when the user is logged in.

---

### 1.5 Resolve TODO comments

**Goal:** Resolve or document the two TODOs in `class-bbai-core.php`.

**Tasks:**
1. **TODO at line 1548** – "Update this if ALT Library slug changes":
   - Decide: Is the slug hardcoded or configurable?
   - Either add a constant or config for the slug, or add a comment with the current slug and a note to update if it changes.
2. **TODO at line 4711** – "Re-enable when React components are properly built":
   - Decide: Are React components ready now? If yes, re-enable.
   - If no: Add a brief comment or ticket reference explaining when it will be re-enabled.

**Files to modify:**
- `admin/class-bbai-core.php`

**Acceptance:** Both TODOs are either implemented or clearly documented.

---

## Phase 2: UX Improvements

### 2.1 First-time / logged-out state

**Goal:** Clarify that users can try 10 generations without an account, then sign up for 50/month.

**Tasks:**
1. Review `admin/partials/dashboard-logged-out.php` and related components.
2. Add a short, prominent line such as: "Try 10 generations now — no account needed. Sign up for 50/month."
3. Ensure the same message is visible in the logged-out onboarding flow.
4. Optionally add a small "How it works" or FAQ section.

**Files to modify:**
- `admin/partials/dashboard-logged-out.php`
- Possibly: `admin/components/` (logged-out components)

**Acceptance:** Logged-out users see a clear explanation of trial vs paid.

---

### 2.2 Disabled button tooltips

**Goal:** Add tooltips when "Generate Missing" and "Re-optimise All" are disabled.

**Tasks:**
1. Decide: When are these buttons disabled?
   - No images missing?
   - Upgrade required?
   - Other?
2. Add `title` or `aria-label` (or a tooltip component) with appropriate messages, e.g.:
   - "No images need generation"
   - "Upgrade to Growth for bulk processing"
   - "All images already optimized"
3. Ensure tooltips are accessible (keyboard, screen readers).

**Files to modify:**
- `admin/components/dashboard/Dashboard.jsx` (or equivalent)
- `admin/partials/dashboard-body.php`
- Possibly `assets/src/js/bbai-tooltips.js` if a shared tooltip system exists

**Acceptance:** Disabled buttons show clear, helpful tooltips.

---

### 2.3 ALT Library empty state

**Goal:** Show an empty state when there are no images, with a link to Media Library.

**Tasks:**
1. In `admin/components/library/Library.jsx` (or equivalent), add a check for empty results.
2. Add an empty state UI:
   - Short message: "No images in your library yet."
   - Link: "Add images in Media Library" → `upload.php`
   - Optional: "Add Media" button.
3. Optionally add a small illustration or icon.

**Files to modify:**
- `admin/components/library/Library.jsx`
- Possibly `admin/partials/library-tab.php`

**Acceptance:** Empty ALT Library shows a clear empty state and link to Media Library.

---

## Phase 3: Technical Debt & Tests

### 3.1 Automated tests

**Goal:** Add PHPUnit and Jest/Playwright tests for critical paths.

**Tasks:**

**PHPUnit setup:**
1. Add `phpunit.xml` (or `phpunit.xml.dist`).
2. Add `composer require --dev phpunit/phpunit` for local dev.
3. Create `tests/` directory:
   - `tests/bootstrap.php` – load WordPress test env.
   - `tests/unit/` – unit tests.
   - `tests/integration/` – integration tests (optional).
4. Add tests for:
   - `Generation_Service` – generation with mocked API.
   - `Usage_Service` – usage tracking.
   - `Auth_Controller` or auth logic.
   - Input validation and sanitization.

**JS/E2E tests:**
1. Add Jest or Playwright for JS:
   - `npm install --save-dev jest @babel/core @babel/preset-env` (or Playwright).
2. Add `scripts/test` in `package.json`:
   - `"test": "jest"` or `"test:e2e": "playwright test"`.
3. Add tests for:
   - Regen modal flow.
   - Bulk actions (if feasible).
   - Settings save.
4. Optionally extend `scripts/capture-screenshots.js` into a Playwright test suite.

**Files to create/modify:**
- `phpunit.xml` (or `phpunit.xml.dist`)
- `composer.json` (dev deps)
- `tests/bootstrap.php`
- `tests/unit/` (test files)
- `package.json` (test script)
- `jest.config.js` or `playwright.config.js`
- `tests/e2e/` or `tests/js/` (if applicable)

**Acceptance:** `composer test` or `npm test` runs relevant tests and passes.

---

### 3.2 Error handling

**Goal:** Improve API error and timeout handling with clear messages and retry options.

**Tasks:**
1. Audit API error handling in:
   - `includes/class-api-client-v2.php`
   - `includes/services/class-generation-service.php`
   - Frontend: `assets/src/js/bbai-error-handler.js`, `assets/src/js/bbai-admin.js`
2. Map error cases:
   - Network failure
   - Timeout
   - 4xx/5xx
   - Rate limit
   - Invalid response
3. Add user-facing messages:
   - "Connection failed. Please check your connection and try again."
   - "Request timed out. Retry?"
   - "Service temporarily unavailable. Retry in a few minutes."
4. Add retry UI where appropriate (e.g. "Retry" button in modals).
5. Optionally add retry logic for transient failures (e.g. 1–2 retries with backoff).

**Files to modify:**
- `includes/class-api-client-v2.php`
- `includes/services/class-generation-service.php`
- `assets/src/js/bbai-error-handler.js`
- `assets/src/js/bbai-admin.js`
- Modal components (e.g. Regen modal)

**Acceptance:** API failures and timeouts show clear messages and retry options.

---

### 3.3 Accessibility audit

**Goal:** Run an a11y audit and fix critical issues.

**Tasks:**
1. Add axe-core (or similar) to the project:
   - `npm install --save-dev @axe-core/playwright` or `axe-core` for Jest.
2. Add a11y tests (e.g. in Playwright):
   - Dashboard
   - ALT Library
   - Settings
   - Upgrade modal
   - Regen modal
3. Run the audit and fix:
   - Color contrast
   - Focus management
   - ARIA labels
   - Keyboard navigation
   - Form labels
4. Optionally add `npm run test:a11y` to run the audit.

**Files to create/modify:**
- `tests/a11y/` or `tests/e2e/a11y.spec.js`
- Components with a11y issues (modals, buttons, forms)

**Acceptance:** Critical a11y issues are resolved and audit can be run via `npm run test:a11y` (or equivalent).

---

### 3.4 Inline documentation (PHPDoc)

**Goal:** Add PHPDoc to main service classes.

**Tasks:**
1. Add PHPDoc to:
   - `includes/services/class-generation-service.php`
   - `includes/services/class-usage-service.php`
   - `includes/services/class-authentication-service.php`
   - `includes/services/class-license-service.php`
   - `includes/services/class-queue-service.php`
2. Include:
   - Class description
   - Method descriptions
   - `@param` and `@return` where applicable
   - `@throws` for exceptions

**Files to modify:**
- `includes/services/class-*.php`

**Acceptance:** Main service classes have PHPDoc blocks for public methods.

---

## Phase 4: Performance & Polish

### 4.1 ALT Library pagination / virtual scrolling

**Goal:** Improve performance for large libraries.

**Tasks:**
1. Decide: Pagination vs infinite scroll vs virtual scrolling.
2. Implement:
   - **Pagination:** Add page size (e.g. 24, 48) and prev/next buttons.
   - **Virtual scrolling:** Use a library like `react-window` or `react-virtualized` if the list is React-based.
3. Ensure REST API supports:
   - `per_page` and `page` (or `offset`/`limit`).
4. Update Library component to handle pagination.
5. Test with 100+ images.

**Files to modify:**
- `admin/components/library/Library.jsx`
- REST handler for library list (e.g. `handle_list` in REST controller)
- `admin/partials/library-tab.php` (if server-side)

**Acceptance:** ALT Library loads quickly for large libraries.

---

### 4.2 Asset loading scope

**Goal:** Ensure admin scripts/styles load only on plugin pages.

**Tasks:**
1. Audit `admin/traits/trait-core-assets.php` and `admin/class-bbai-core.php` for `wp_enqueue_script` / `wp_enqueue_style`.
2. Ensure each enqueue uses a condition that matches the plugin page:
   - `$hook === 'toplevel_page_bbai'` or similar
   - `strpos($hook, 'bbai') !== false` or similar
3. Avoid loading heavy bundles (e.g. dashboard, React) on non-plugin pages.
4. Test: Visit Settings, Plugins, Posts, etc. and confirm plugin assets are not loaded.

**Files to modify:**
- `admin/traits/trait-core-assets.php`
- `admin/class-bbai-core.php`

**Acceptance:** Plugin assets are only loaded on plugin admin pages.

---

## Execution Order

1. **Phase 1** – Fix bugs and docs first; low risk, high impact.
2. **Phase 2** – UX improvements; can be done in parallel with Phase 3.
3. **Phase 3** – Tests and technical debt; can be done incrementally.
4. **Phase 4** – Performance and polish; can be done last or when needed.

---

## Checklist Summary

| # | Task | Phase | Est. |
|---|------|-------|------|
| 1.1 | Align free plan messaging | 1 | 1–2 hrs |
| 1.2 | Fix Regen alt text mismatch | 1 | 2–3 hrs |
| 1.3 | Update installation instructions | 1 | 15 min |
| 1.4 | Investigate session expired | 1 | 1 hr |
| 1.5 | Resolve TODOs | 1 | 30 min |
| 2.1 | First-time / logged-out UX | 2 | 1–2 hrs |
| 2.2 | Disabled button tooltips | 2 | 1 hr |
| 2.3 | ALT Library empty state | 2 | 1 hr |
| 3.1 | Automated tests | 3 | 4–6 hrs |
| 3.2 | Error handling | 3 | 2–3 hrs |
| 3.3 | Accessibility audit | 3 | 2–3 hrs |
| 3.4 | PHPDoc | 3 | 1–2 hrs |
| 4.1 | ALT Library pagination | 4 | 3–4 hrs |
| 4.2 | Asset loading scope | 4 | 1–2 hrs |

**Total estimated:** ~22–35 hours
