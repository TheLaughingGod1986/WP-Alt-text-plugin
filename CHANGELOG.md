## Changelog

All notable changes to this plugin are documented in this file.

The format is loosely based on Keep a Changelog, but optimized for internal release notes.

### 4.6.134 — 2026-09-02

- **Changed**: Alt Text upgrade modal uses USD Stripe Price IDs and $ amounts for en_US site/user locales; non-US keeps existing GBP Price IDs and £ copy.

### 4.6.133 — 2026-08-21

- **Changed**: In-plugin By line is now OpptiAI linked to https://oppti.dev.

### 4.6.132 — 2026-08-18

- **Added**: Quiet OpptiAI Titles cross-sell at the bottom of the home Dashboard tab for signed-in Free, Starter, and Growth users (hidden from guests and Agency). Links to the Titles admin page when active, otherwise WordPress.org.
- **Changed**: Dashboard usage card / hero “Only X credit(s) left this month” only when remaining 1–5 (singular at 1); remaining 0 keeps exhausted copy (“No credits remaining/left this month”); above 5 shows used/limit numbers only.
- **Added**: Quiet usage clarification under the usage card: credits count generations (including retries and titles); images count what you saved.
- **Added**: Settings Account Credit Wallet matching Titles — Free/Starter/Growth service card, shared monthly pool from GET /api/usage, per-plugin `usage_by_feature` breakdown (Image ALT Text first as “This plugin”; Titles used/% when billed or Open/Not installed; Internal Linking / Schema Not installed without Get/Install). Shared-wallet note under rows. “View Growth plan” CTA (billing id `pro` unchanged). Guests never see the wallet. No invented Free-25 limit when usage is missing; unused `--growth` wallet CSS removed (keep `--paid`).

### 4.6.131 — 2026-08-18

- **Changed**: User-facing BeepBeep chrome in wp-admin (logos, toasts, modals, empty states, settings copy) → OpptiAI Alt Text.
- **Changed**: Upgrade to Pro CTAs → Upgrade to Growth (billing id `pro` unchanged).

### 4.6.130 — 2026-08-18

- **Fixed**: When OpptiAI Titles is installed first, Alt Text adopts that site's `beepti_site_id` so both plugins share one credit wallet. Adoption only runs when Alt Text has no site id yet; already-split sites stay split.

### 4.6.129 — 2026-08-17

- **Fixed**: Unified leftover Free/guest copy and shipped defaults to Free 25 / guest 10 (exhausted-guest post-generation copy, local free-credit allocation, and monetisation Free positioning). Paid plan entitlements and scan/coverage limits unchanged.

### 4.6.128 — 2026-08-17

- **Changed**: Rebranded visible wp-admin plugin name and sidebar menu label to OpptiAI Alt Text.

### 4.6.127 — 2026-08-17

- **Changed**: Free monthly AI allowance increased from 15 to 25 generations.
- **Changed**: Guest/anonymous trial increased from 5 to 10 generations.
- **Changed**: FAQ, dashboard upsell, trial meter defaults, and Free plan pricing copy updated to Free 25 / guest 10. Paid plan entitlements unchanged.

### 4.6.126 — 2026-07-24

- **Improved**: Removed the duplicate monthly progress bar and made limit notices text-only so the dashboard no longer shows stacked meters for the same state.

### 4.6.125 — 2026-07-24

- **Improved**: Free-plan usage copy is now context-aware — monthly exhaustion no longer shows the daily-limit explanation.

### 4.6.124 — 2026-07-24

- **Fixed**: Free-plan daily limit no longer looks like a monthly outage — CTA, progress, and usage copy now explain 5/day vs 15/month when today’s allowance is used.

### 4.6.123 — 2026-07-17

- **Fixed**: Guard `get_billing_info()` against missing nested `billing` keys so Settings no longer emits an undefined-array-key warning for free accounts.

### 4.6.122 — 2026-07-16

- **Fixed**: Mounted live generation progress beneath the current visible daily-pass dashboard actions instead of the hidden legacy runtime.
- **Preserved**: The panel remains hidden while idle, so the existing dashboard UI is unchanged outside active/completed generation.

### 4.6.121 — 2026-07-16

- **Fixed**: Restored the live generation progress panel beneath the existing logged-in dashboard actions.
- **Preserved**: The current dashboard UI remains unchanged outside the isolated progress panel.

### 4.6.120 — 2026-07-16

- **Improved**: The guest dashboard now clearly explains the five no-signup generations before the first click.
- **Improved**: Completing the guest trial opens an impact-led signup modal with images improved, ALT coverage gained, and the 15-free-monthly lifetime offer.
- **Analytics**: Guest generation events now carry explicit anonymous-account properties, and the complete guest offer-to-signup funnel is forwarded to PostHog.

### 4.6.119 — 2026-07-16

- **Improved**: The logged-in dashboard now shows live image-generation progress beneath the primary actions, including the active image, total count, and completion percentage.

### 4.6.118 — 2026-07-16

- **Fixed**: Guest trial usage, coverage, and remaining-image counters now update immediately after successful generation without a page refresh.
- **Improved**: Exhausted guests now see a stronger free-account prompt offering 15 free generations each month.

### 4.6.117 — 2026-07-16

- **Fixed**: Restored the required `BBAI_Attribution` class to the WordPress.org package so fresh installs and updates activate successfully.

### 4.6.116 — 2026-07-16

- **Fixed**: Generation retries now share a correlation ID, honour explicit non-retryable backend responses, and emit only one terminal telemetry outcome per run.

### 4.6.115 — 2026-07-08

- **Changed**: Telemetry-only release — marketing attribution passthrough to checkout metadata and PostHog identity enrichment for backend billing webhook join.

### 4.6.114 — 2026-07-07

- **Fixed**: Telemetry verification release — `batch_generation_started`, library-page `wp_localize_script` guards, NAI paywall `upgrade_cta_clicked`, checkout redirect `checkout_started`, auth funnel flush, and `$entitlement_state` initialization.

### 4.6.113 — 2026-07-07

- **Fixed**: Exposed `trackFeatureUsed` on `window.bbaiTelemetry` for shared client callers.
- **Fixed**: NAI dashboard `feature_used` helper normalizes feature names and prefers the telemetry wrapper.

### 4.6.112 — 2026-07-07

- **Fixed**: Rebuilt `bbai-admin.min.js` and `bbai-dashboard.min.js` so production (`SCRIPT_DEBUG` off) serves client telemetry fixes from 4.6.111.

### 4.6.111 — 2026-07-07

- **Fixed**: NAI dashboard generation path now emits client `generation_started` / `generation_completed` telemetry when the legacy drawer runs.
- **Fixed**: NAI shell navigation now emits `feature_used` for library, settings, dashboard, billing, and statistics destinations.
- **Fixed**: Client telemetry assigns a per-run `generation_run_id` so `generation_completed` is not dropped when the drawer path starts before `bbai-admin` dispatches.

### 4.6.110 — 2026-07-07

- **Changed**: Central enrichment fills `plugin_slug`, `telemetry_version`, normalized host, `generation_type`, `quota_state`, and `license_state` on all new telemetry events.
- **Changed**: `feature_used` now always requires `feature_name`.
- **Added**: PHPUnit regression tests for the canonical telemetry property contract.

### 4.6.109 — 2026-07-07

- **Fixed**: Routed plugin activation and install lifecycle events through the queued PostHog server bridge so `plugin_activated` reaches PostHog after admin bootstrap.
- **Fixed**: Added the missing `is_posthog_internal_environment()` helper so generation telemetry no longer fatals during `alt_generated` capture.
- **Fixed**: Defaulted telemetry consent to opt-in for fresh installs so WordPress.org sites emit product analytics without a settings toggle.

### 4.6.90 — 2026-06-23

- **Changed**: Unified logged-out and logged-in dashboard rendering around the shared ALT coverage dashboard structure.
- **Changed**: Made ALT Coverage, progress ring, scanned/optimised/missing/review counts, and Next Recommended Action the primary dashboard model.
- **Fixed**: Removed remaining Today’s Pass, ALT Pass, workflow stepper, and duplicate accessibility metric language from dashboard surfaces.

### 4.6.14 — 2026-05-29

- **Changed**: Refactored the nAi dashboard into smaller PHP components and focused JavaScript modules while preserving existing dashboard behaviour.
- **Changed**: Split ALT Library generation state helpers into focused legacy-compatible modules for locks, notices, bulk orchestration, API request construction, and row/count/filter state.
- **Fixed**: Added regression coverage and release compliance cleanup for dashboard and ALT Library generation flows.

### 4.6.4 — 2026-05-14

- **Fixed**: Background generation persistence now survives page navigation, refreshes, and multi-tab sessions across all plugin pages.
- **Fixed**: In-browser background jobs now sync progress via the shared bbaiJobState subscription, removing duplicate polling and stale state after navigation.
- **Fixed**: ALT Library pagination now automatically reloads to show remaining filtered items after approving all visible items on the current page.
- **Fixed**: Floating job widget "Review images" button is now visible after generation completes (WordPress admin CSS override was making text invisible).
- **Fixed**: Removed misleading "Review suggestions." suffix from the generation-complete widget status line.
- **Fixed**: Plugin Check `MissingTranslatorsComment` warnings resolved by adding translator comments to all i18n calls with printf placeholders.
- **Fixed**: Dashboard insight cards (Accessibility, Time Saved, SEO) now have horizontally aligned value headings and CTA buttons across all three cards.

### 4.6.3 — 2026-05-06

- **Changed**: Updated the WordPress.org large and small banners with correctly sized Better Alt Text, Better Accessibility artwork.
- **Changed**: Refreshed the WordPress.org thumbnail icon and improved readme SEO coverage for AI alt text, image SEO, accessibility, WooCommerce, and missing alt attributes.
- **Changed**: Polished the dashboard insight cards with clearer outcome copy, proof badges, stronger visual hierarchy, and aligned refreshed-state values.
- **Fixed**: Manual Re-scan library feedback now shows persistent inline completion or failure messaging after the scan finishes.
- **Fixed**: Scan completion messaging now reattaches if a dashboard render replaces the re-scan link.
- **Fixed**: All-optimised dashboard presentation, retention-strip copy, and state consistency after generation, review, approval, and polling.
- **Fixed**: Upgrade CTA and modal stacking edge cases around generation-complete and automation prompts.

### 4.6.2 — 2026-04-29

- **Changed**: Replaced the WordPress.org banner and thumbnail icon with refreshed Better Alt Text, Better Accessibility artwork.
- **Changed**: Standardised generation button loading states with one spinner, one clear label, hard disabled buttons, and duplicate-click protection.
- **Changed**: Improved the bulk generation modal with dynamic steps, real progress, live feed entries, compact layout, and clearer completion actions.
- **Changed**: Added contextual first-success, review, credit, and upgrade prompts that wait until meaningful moments instead of interrupting active work.
- **Changed**: Polished dashboard spacing, CTA alignment, retention-strip copy, and completion/review states.
- **Fixed**: Dashboard polling is now idempotent so unchanged state no longer flickers, replays completion animations, or replaces stable DOM.
- **Fixed**: Dashboard state stays in sync after generation and approval, including the donut, right card, credits card, and middle progress CTA.
- **Fixed**: Removed stale all-optimised, Done, and review-ready state leaks after approving or generating images.
- **Fixed**: Monthly credit usage display and progress bars now update from backend truth after generation.
- **Fixed**: Manual dashboard re-scans now show clear completion or error feedback instead of silently returning to the idle link.
- **Fixed**: Automatic optimisation CTAs are clickable, trackable, and connected to the existing upgrade modal.
- **Fixed**: Upgrade modal stacking from the generation-complete automation link now opens pricing above the dismissed progress modal.


### 4.6.1 — 2026-04-29

- **Changed**: Maintenance release.
