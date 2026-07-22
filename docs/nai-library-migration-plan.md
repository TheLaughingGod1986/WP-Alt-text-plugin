# NAI ALT Library Migration Plan

This plan now includes the first compatibility bridge between the NAI Library markup and the legacy Library JavaScript contracts. Further visual refactoring should still wait until these hooks are verified in a real WordPress admin context.

## Current Rendering Flow

- `admin/partials/library-tab.php` owns the legacy Library data preparation, filters, counts, and pagination.
- When the NAI design flag/path is active, `admin/partials/library-tab.php` includes `admin/partials/nai-library.php`.
- `admin/partials/nai-library.php` currently renders NAI visual markup while preserving many legacy behavior hooks such as `data-action`, row IDs, attachment IDs, and batch control IDs.
- The page still depends on shared legacy scripts, especially `assets/js/bbai-admin.js` and `assets/js/alt-library-filters.js`.

## Critical Hooks To Preserve

Do not rename these without updating every consumer and testing all affected flows:

| Hook | Current purpose | Known consumers |
|---|---|---|
| `data-action="rescan-media-library"` | Rescan Library CTA | `assets/js/bbai-admin.js` delegated click handlers |
| `data-action="generate-selected"` | Batch generate selected rows | `assets/js/bbai-admin.js`, busy-state helpers, batch bridge |
| `data-action="regenerate-selected"` | Batch regenerate selected rows | `assets/js/bbai-admin.js`, busy-state helpers, batch bridge |
| `data-action="clear-selection"` | Clear current selection | Library delegated handlers |
| `data-action="preview-image"` | Open image preview modal | `assets/js/bbai-admin.js` |
| `data-action="edit-alt-inline"` | Open inline/modal ALT editor | `assets/js/bbai-admin.js` |
| `data-action="regenerate-single"` | Generate/regenerate a single image | `assets/js/bbai-admin.js`, locked CTA handling |
| `data-action="show-upgrade-modal"` | Open upgrade modal | `assets/js/bbai-admin.js`, pricing/checkout flow |
| `data-bbai-locked-cta` | Guard paid/out-of-credit actions | `assets/js/bbai-admin.js` locked-action flow |
| `data-bbai-action="open-upgrade"` | Upgrade CTA routing | Upgrade/checkout bridge |
| `data-bbai-lock-reason` | Locked action reason | Locked CTA and analytics flow |
| `data-attachment-id` | Attachment lookup for row actions | Preview, edit, regenerate, approve, bulk flows |
| `data-status` | Row status/filtering | Filter and row-state scripts |
| `data-review-state` | Review filter state | Library filter scripts |
| `#bbai-library-search` | Search input | `assets/js/bbai-admin.js`, `assets/js/alt-library-filters.js` |
| `#bbai-select-all` | Select all rows | `assets/js/bbai-admin.js` |
| `#bbai-library-table-body` | Row container | Filter, bulk, progress, and empty-state scripts |
| `#bbai-library-selection-bar` | Batch action bar | Bulk selection scripts |

## Compatibility Hooks Added

`admin/partials/nai-library.php` now preserves the NAI visual classes while exposing the legacy behavior API:

- Root screen keeps `.nai-screen--library` and also exposes `.bbai-library-container` and `.bbai-library-main`.
- Filter row keeps `.nai-filter-row` and now uses `#bbai-review-filter-tabs`.
- Filter pills keep `.nai-filter-pill` and now render as `button[data-filter]` with `data-bbai-library-filter`, `data-bbai-filter-target`, `data-bbai-filter-label`, and `data-bbai-filter-href`.
- Active filter buttons include `.nai-filter-pill--active`, `.bbai-filter-group__item--active`, and `.bbai-alt-review-filters__btn--active`.
- Filter labels/counts expose `.bbai-filter-group__label` and `.bbai-filter-group__count`.
- Search remains visually `.nai-search__input` and keeps `#bbai-library-search`.
- Row container keeps `#bbai-library-table-body` and now includes `.bbai-library-review-queue`.
- Rows keep `.nai-lib-row` and now also expose `.bbai-library-row`.
- Row checkboxes keep `.bbai-image-checkbox` and now also expose `.bbai-library-row-check` and `.bbai-checkbox`.
- Rows now expose legacy data required by filters, selection, preview, edit, regenerate, and row-state updates: `data-attachment-id`, `data-id`, `data-status`, `data-review-state`, `data-alt-missing`, `data-alt-full`, `data-ai-source`, `data-quality`, `data-quality-class`, `data-quality-label`, `data-image-title`, `data-image-url`, `data-file-name`, `data-file-meta`, and `data-last-updated`.
- Thumbnail images now expose `.bbai-library-thumbnail` for preview modal fallback lookups.
- ALT preview text now exposes `.bbai-library-cell--alt-text` and `[data-bbai-alt-slot]` so legacy inline/modal edit paths can locate the editable text slot.

## Hooks Intentionally Left Unchanged

- `data-action` values remain unchanged: `rescan-media-library`, `generate-selected`, `regenerate-selected`, `clear-selection`, `preview-image`, `edit-alt-inline`, `regenerate-single`, and `show-upgrade-modal`.
- Locked CTA attributes remain unchanged: `data-bbai-locked-cta`, `data-bbai-action`, `data-bbai-lock-reason`, and `data-bbai-lock-preserve-label`.
- Batch IDs remain unchanged: `#bbai-batch-generate`, `#bbai-batch-regenerate`, `#bbai-batch-clear`, `#bbai-select-all`, and `#bbai-library-selection-bar`.
- AJAX action names, nonce keys, REST paths, and localized config objects are unchanged.

## AJAX, REST, And Nonce Dependencies

Library-related flows currently rely on localized config and WordPress nonces rather than hardcoded AJAX URLs:

- `window.bbai_ajax.ajax_url`
- `window.bbai_ajax.nonce` using the `beepbeepai_nonce` action
- `window.BBAI_DASH.nonce` / `window.BBAI.nonce` using `wp_rest` for REST calls
- `window.BBAI_DASH.restStats`
- `window.BBAI_DASH.restUsage`
- `window.BBAI.restMissing`
- `window.BBAI.restAll`
- `window.BBAI.restQueue`
- `X-WP-Nonce` headers for REST requests

Registered AJAX actions relevant to Library and adjacent dashboard/library actions include:

- `beepbeepai_regenerate_single`
- `beepbeepai_bulk_queue`
- `beepbeepai_inline_generate`
- `beepbeepai_bulk_job_start`
- `beepbeepai_bulk_job_poll`
- `beepbeepai_get_attachment_ids`
- `bbai_scan_missing_alt`
- `bbai_start_alt_coverage_scan`
- `bbai_poll_alt_coverage_scan`
- `bbai_rescan_alt_coverage`
- `bbai_generate_preview_alt`
- `bbai_apply_alt_batch`

## Active JavaScript Dependencies

`assets/js/bbai-admin.js` still expects legacy Library structure in several places:

- Rows queryable by `.bbai-library-row[data-attachment-id="..."]`.
- Row checkboxes queryable by `.bbai-library-row-check`.
- Filter tabs under `#bbai-review-filter-tabs button[data-filter]`.
- The table body at `#bbai-library-table-body`.
- Search input at `#bbai-library-search`.
- Selection bar at `#bbai-library-selection-bar`.
- Bulk controls such as `#bbai-batch-generate`, `#bbai-batch-regenerate`, and `#bbai-batch-clear`.
- Row state classes for processing, done, hidden, approve-pending, approve-out, and bulk queued/failed states.

`assets/js/alt-library-filters.js` also expects:

- `.bbai-library-row`
- `#bbai-library-table-body`
- `#bbai-library-filter-empty`
- `#bbai-review-filter-tabs button[data-filter]`
- Active filter classes such as `.bbai-alt-review-filters__btn--active` and `.bbai-filter-group__item--active`

## Live Admin Verification Added

`tests/e2e/nai-library-live.spec.ts` now exercises the NAI Library bridge inside real `wp-admin` against the plugin PHP, enqueued production CSS, and enqueued production JavaScript.

Coverage includes:

- WordPress login and Library page load through `admin.php?page=bbai-library`.
- Fixture seeding in wp-env only, using `wp eval`, when live media data is unavailable.
- Production script presence for `bbai-admin.js`, `alt-library-filters.js`, and `nai-dashboard.js`.
- Compatibility selectors: `#bbai-library-search`, `#bbai-review-filter-tabs`, `#bbai-library-table-body`, `.bbai-library-row`, and `.bbai-library-row-check`.
- Filter clicks, active state, visible row/empty-state stability, and no critical JS console errors.
- Search filtering, empty-state rendering, and clearing search.
- Single and multi-row selection with the bulk selection bar/count.
- Preview modal open/close through the existing `data-action="preview-image"` / `data-action="close-library-preview"` path.
- Edit path through the current production inline editor created by `data-action="edit-alt-inline"` and cancelled with `data-action="cancel-alt-inline"`.
- Regenerate path in unlocked state and locked/out-of-credit upgrade path without firing generation requests.
- Mobile viewport reachability for filters, search, rows, and row actions.

Live verification found one real compatibility gap: `assets/js/alt-library-filters.js` was present in the repo but not enqueued on the live Library page. `admin/traits/trait-core-assets.php` now enqueues it only on `bbai-library`, dependent on `bbai-admin`, preserving existing action names, nonce usage, REST paths, and delegated handlers.

The quota synchronisation bridge now also loads `assets/js/bbai-entitlements.js` before `bbai-admin.js`. When canonical backend `entitlement_state` reports `can_generate: false`, existing Library generation actions retain their `data-action` contracts but receive the established locked/upgrade attributes immediately. Preview, inline edit, filters, selection and pagination remain available.

Static entitlement-state coverage exposed one additional UI-state incompatibility: the NAI pagination wrapper uses `display:flex`, which can override the native `hidden` presentation when a loading or empty state is applied. The bridge now uses the additive `.nai-is-precedence-hidden` component state class only for loading/empty precedence. It reports `library_state_conflict_detected` through the existing analytics channel when empty and pagination arrive visibly together, without blocking filters, review, or editing.

Selectors confirmed safe in live admin:

- `#bbai-library-search`
- `#bbai-review-filter-tabs button[data-filter]`
- `#bbai-library-table-body`
- `.bbai-library-row`
- `.bbai-library-row-check`
- `data-action="preview-image"`
- `data-action="edit-alt-inline"`
- `data-action="regenerate-single"`
- `data-bbai-action="open-upgrade"` / `data-bbai-locked-cta`

## Connected Entitlement Coverage

`tests/e2e/nai-entitlement-connected.spec.ts` extends the live-admin bridge with an opt-in connected backend suite. The real tests require a dedicated linked account/site prepared with exactly one remaining generation credit:

```bash
BBAI_E2E_BASE_URL=http://localhost:8888 \
BBAI_E2E_CONNECTED_ENTITLEMENT=1 \
BBAI_E2E_RUN_CONNECTED_GENERATION=1 \
npx playwright test tests/e2e/nai-entitlement-connected.spec.ts
```

Set `BBAI_E2E_ADMIN_USER` and `BBAI_E2E_ADMIN_PASS` where local wp-env defaults do not apply. The suite also accepts `WP_BASE_URL`, `WP_ADMIN_USER`, and `WP_ADMIN_PASS`. `SUPABASE_URL` and `SUPABASE_SERVICE_ROLE_KEY` are required only for the separate `scripts/verify-live-truth-safe.mjs` inspection tool and are not consumed by Playwright.

Coverage includes:

- Real final-credit Library generation through the existing action path, followed by canonical exhausted state confirmation in both Library and Dashboard.
- Real exhausted-backend rejection after a test-only stale Library allowance replay; canonical state must replace the stale capability and the locked UI emits `generation_blocked_no_credits` once.
- Static regression coverage for the reported replay combination of stale generation controls, an exhausted canonical response, and contradictory empty/loading/pagination markers.
- A live-admin terminal bulk polling contract test using production scripts and a mocked terminal `beepbeepai_bulk_job_poll` response containing nested `entitlement_state`.

The real successful-generation path currently ingests canonical exhaustion via the plugin's existing post-generation usage refresh. Direct propagation of a successful generation response's `entitlement_state` through the WordPress AJAX wrapper is not assumed by this test.

Full real bulk execution is intentionally deferred: it consumes multiple backend credits and requires a stable asynchronous job fixture. The terminal response contract test proves that the frontend consumes the expected nested state without changing production actions, nonces, or polling logic.

### Connected Fixture Attempt - 2026-05-26

The read-only preflight against local wp-env confirmed agreeing usage/dashboard canonical state for a connected free-plan site, but reported `token_limit: 50`, `tokens_used_this_month: 1`, and `tokens_remaining: 49`. Because that is not an isolated one-credit fixture, no real final-generation or quota-denial request was issued and no UI/runtime fix was justified by this attempt.

The original zero-credit Dashboard/Library inconsistency remains protected by the canonical store regressions and live admin compatibility coverage, but final closure requires rerunning the connected suite after an approved test-only backend reset to exactly one remaining credit.

Verification for this attempt: static NAI coverage `25 passed`; live wp-env Library bridge `7 passed`; non-destructive connected entitlement execution `1 passed` terminal poll contract with `2 skipped` real generation/denial tests.

## Current NAI Markup Risks

- `.nai-lib-row` now carries `.bbai-library-row`, and live admin smoke coverage verifies row selection, preview, inline edit, regenerate, and locked CTA paths. Deeper save/regenerate success-state DOM mutation still needs coverage before row structure changes.
- NAI row checkboxes now include `.bbai-library-row-check`; live wp-env selection coverage confirms single and multi-select behavior with production `bbai-admin.js`.
- NAI filters now satisfy `#bbai-review-filter-tabs button[data-filter]`; live wp-env coverage confirms active state changes and row/empty-state stability. Server-side hrefs are retained as `data-bbai-filter-href` for future progressive enhancement, not as clickable anchors.
- Several Library elements still use inline styles. Move these into `nai-library.css` only after behavior compatibility is locked.
- `.nai-coverage*` is used by Library but styled in Dashboard CSS after the split. Move it to shared components in a focused visual regression pass.
- The current production edit path is inline editing, not a drawer/modal. Treat `.bbai-library-inline-alt` as the active edit surface until a deliberate Library UI migration replaces it.

## Safe Migration Sequence

1. Keep the live-admin smoke suite green while making any further Library changes.
2. Add save/cancel and successful regeneration result coverage before changing row mutation markup.
3. Move remaining Library inline styles into `assets/css/nai/nai-library.css`.
4. Refactor Library rows, filters, empty states, pagination, and bulk action bar into reusable NAI components while keeping behavior hooks.
5. Only after verified usage scans and passing tests, prune duplicated legacy Library CSS.

## Suggested Test Matrix

- Library page loads with rows and counts.
- Filter controls render and preserve target filter state.
- Search input can be typed into and does not break row state.
- Pagination preserves `alt_page` state and filter query arguments.
- Review/preview control exposes `data-action="preview-image"` and attachment IDs.
- Inline edit control exposes `data-action="edit-alt-inline"` and attachment IDs.
- Generate/regenerate CTAs expose expected `data-action` and locked attributes.
- Logged-out state shows the shell overlay and keeps upgrade/sign-in flows available.
- Out-of-credit state locks generation controls and exposes upgrade CTA.
- Mobile viewport keeps navigation, filters, rows, batch controls, and upgrade CTA reachable.

## Next Safe Step

Execute the opt-in final-credit connected suite against a prepared backend fixture. Once the exhaustion transition is proven with live backend data, add a controlled real bulk-job fixture or narrowly forward successful generation entitlement payloads through WordPress AJAX before resuming Library CSS extraction.
