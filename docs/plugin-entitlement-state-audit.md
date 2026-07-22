# Plugin Frontend Entitlement State Audit

## Scope

This audit covers the active WordPress admin frontend surfaces and quota-sensitive JavaScript found while introducing `window.BBAIEntitlements`. Legacy CSS and non-quota action contracts remain unchanged.

Canonical client state is the additive backend `entitlement_state` object. The compatibility module is `assets/js/bbai-entitlements.js`; it accepts canonical responses and maps older localized usage snapshots only for bootstrap compatibility.

## State Ownership

| File / surface | Current quota source before bridge | UI impact | Desync risk | Replacement / bridge |
|---|---|---|---|---|
| `admin/traits/trait-core-assets.php` | `Usage_Helper::get_usage()`, `Usage_Tracker::get_stats_display()`, trial status and auth state localized into several objects | Seeds all admin scripts, upgrade context and account state | High: `BBAI`, `BBAI_DASH`, `BBAI_UPGRADE`, and `bbai_ajax` could begin with differing interpretations | Localize one sanitized `bbaiInitialEntitlementState`; expose compatible `entitlementState` in existing config objects |
| `assets/js/bbai-entitlements.js` | New canonical store; canonical payloads first, legacy usage only as fallback | Drives remaining values, exhausted notices, Library generation locks, Autopilot gate and paywall copy | Low when backend provides canonical state | Sole frontend capability API: `canGenerate()`, `canAutopilot()`, `remaining()`, `isExhausted()` |
| `admin/partials/nai-dashboard.php` | `$bbai_dashboard_state['creditsUsed'|'creditsLimit'|'creditsRemaining']` | Monthly usage metric, exhausted prompt, generation journey | High: PHP snapshot could disagree with Library/API after generation | Keep SSR values for first paint; annotate update targets and exhausted notice for `BBAIEntitlements` |
| `admin/partials/nai-library.php` | `$bbai_limit_reached_state` plus legacy JS usage calculations | Row and bulk Generate/Regenerate availability | Critical: this is the contradictory 0-credit/generation-available path | Keep existing data actions; `BBAIEntitlements` additively applies locked upgrade attributes whenever `can_generate` is false |
| `admin/partials/nai-autopilot.php` | Plan-only `$nai_ap_is_pro` | Autopilot toggle and upsell state | Medium: plan could allow UI while canonical feature gate is false | Add capability hook; store disables toggle and exposes one blocked copy when `can_autopilot` is false |
| `admin/partials/nai-settings.php` | `Usage_Helper::get_usage()` converted locally to used/limit/remain | Plan usage display | Medium: stale after auth or generation without reload | Add value targets updated from `BBAIEntitlements` |
| `admin/partials/nai-shell-open.php` | PHP user/account locals | Visible account identity after auth | Medium: modal auth formerly depended on redirect for shell refresh | Add existing auth display hook to the NAI email label; auth success updates available client targets immediately |

## JavaScript Consumers

| File / consumer | Current source / dependency | UI or action impact | Desync risk | Canonical handling |
|---|---|---|---|---|
| `assets/js/bbai-admin.js` `normalizeUsagePayload()` / `mirrorUsagePayload()` | Legacy `usage`, `quota`, DOM fallbacks and localized objects | Most admin generation, banners and Library mutations | Critical for quota-denied and final-credit flows | Quota decision path reads `BBAIEntitlements` first; mirrored payloads ingest canonical entitlement when included |
| `assets/js/bbai-admin.js` `isOutOfCreditsFromUsage()` / locked CTA handlers | Dashboard state contract, usage snapshot, guest-trial checks | Enables or routes generation buttons | Critical | Store exhaustion is authoritative when loaded; existing CTA/action handling remains intact |
| `assets/js/bbai-admin.js` usage refresh / state-truth requests | `restUsage`, `dashboard/state-truth`, stats and generation payloads | Counter refresh and post-generation state | High | Store captures canonical `entitlement_state` from fetch/AJAX responses and state-truth extraction |
| `assets/js/alt-library-filters.js` | Row attributes and filter IDs, no quota accounting | Search/filter empty-state rendering | Low for quota; medium for empty/pagination conflict | Keep filter contract; store observes empty/pagination precedence without blocking review/filtering |
| `assets/js/auth-modal.js` | WordPress AJAX login/register response plus redirect refresh | Logged-in shell and quota after authentication | High: free monthly allowance was only reliable after navigation | Consume auth response entitlement immediately; account creation success copy reports activated credits |
| `assets/js/upgrade-modal.js` | `BBAI_DASHBOARD_STATE_STORE`, `BBAI_DASH.initialUsage`, `BBAI_UPGRADE.usage` | Paywall usage text and eligibility context | High: modal may display stale remaining credits | Prefer `BBAIEntitlements.get()` mapped to its existing usage shape |
| `assets/js/admin/bbai-licensed-bulk-job-client.js` | Job create/poll responses and callbacks | Bulk completion/quota stops | High at terminal job boundaries | No rewrite: store fetch/AJAX response ingestion captures nested completed-job entitlement payloads |
| `assets/js/admin/bbai-background-job.js` / job widget | Persisted job state and polling | Cross-page progress recovery | Medium | No contract change; terminal backend responses are consumed globally when canonical payload is present |
| `assets/js/bbai-dashboard-state.js` | Dashboard response snapshots | Legacy dashboard state updates | Medium | Retained; canonical response ingestion supplies shared entitlement state independently |
| `assets/js/nai-dashboard.js` | NAI prototype interactions and modal copy | Paywall prototype and shell interactions | Medium for paywall text | Enqueued after entitlement module; canonical paywall text updates are applied by store |
| `assets/js/bbai-telemetry.js` / `assets/js/bbai-posthog.js` | Existing `bbai:analytics` event bus | Production-safe behavioral analytics | Low if properties remain capability-only | Store dispatches only non-PII state/action events through existing event bus |

## Hooks Preserved

- AJAX actions, nonce keys, REST route strings, and `data-action` values are unchanged.
- Library hooks remain active: `#bbai-library-search`, `#bbai-review-filter-tabs`, `#bbai-library-table-body`, `.bbai-library-row`, and `.bbai-library-row-check`.
- Review/edit/filter/search/pagination behavior is not gated by quota.
- Generation controls keep their original `data-action`; when exhausted, additive `data-bbai-action="open-upgrade"` and existing locked CTA attributes route the click safely.

## Canonical Response Ingestion

The store consumes `entitlement_state` when it is found in:

- Initial localized PHP bootstrap.
- Fetch JSON responses, including usage, auth, dashboard truth/stats and direct generation.
- jQuery AJAX responses, including existing WordPress generation and quota-denial flows.
- Nested `data`, `result`, `job`, `payload`, `response`, or `usage` objects used by job completion flows.

## Analytics

Events dispatched through the existing `bbai:analytics` bus contain no account identifiers:

- `entitlement_state_loaded`
- `paywall_shown`
- `upgrade_clicked` remains owned by existing upgrade CTA/modal telemetry handlers to avoid duplicate click capture.
- `generation_blocked_no_credits`
- `review_completed`
- `library_state_conflict_detected`

State/load and repeated blocked/paywall events are deduplicated where repeated rendering or clicking would otherwise add noise.

## Connected Backend Coverage

`tests/e2e/nai-entitlement-connected.spec.ts` adds an opt-in connected-backend check for the P0 exhaustion transition. It is intentionally disabled unless a dedicated linked test account/site has exactly one remaining credit and the destructive generation opt-in is present.

Required Playwright environment for the real single-credit and quota-denial tests:

| Variable | Purpose |
|---|---|
| `BBAI_E2E_BASE_URL` or `WP_BASE_URL` | WordPress admin base URL for the connected plugin site |
| `BBAI_E2E_ADMIN_USER` / `BBAI_E2E_ADMIN_PASS` or `WP_ADMIN_USER` / `WP_ADMIN_PASS` | WordPress admin login; defaults are retained only for local wp-env |
| `BBAI_E2E_CONNECTED_ENTITLEMENT=1` | Acknowledges that the site is linked to a controlled backend entitlement fixture |
| `BBAI_E2E_RUN_CONNECTED_GENERATION=1` | Acknowledges that the test consumes the final prepared credit |

`SUPABASE_URL` and `SUPABASE_SERVICE_ROLE_KEY` are used only by `scripts/verify-live-truth-safe.mjs` for optional backend truth inspection. The Playwright connected entitlement suite does not read or store those values.

Coverage added:

- A real Library generation click starts from `BBAIEntitlements.remaining() === 1`, waits for canonical exhausted `entitlement_state`, then verifies the Dashboard and Library both report zero and all generation actions are locked while preview/edit remains usable.
- A real quota-denial path replays a stale allowed Library capability in the browser, submits the existing action path to an already exhausted backend, and verifies the returned canonical state wins. A subsequent locked click confirms `generation_blocked_no_credits` is emitted once.
- The PostHog replay regression is covered statically by injecting stale Library availability plus conflicting empty/loading/pagination UI before a canonical exhausted response; the canonical store locks generation and resolves the contradictory UI state.

The successful WordPress UI generation response currently relies on the existing post-generation `/wp-json/bbai/v1/usage` refresh to ingest the backend canonical state. `beepbeepai_inline_generate` does not yet guarantee direct forwarding of the successful `/api/alt-text` `entitlement_state` envelope. The connected test therefore proves the production UI transition, while direct success-response forwarding remains a narrow follow-up hardening opportunity.

Real bulk execution is not run in this pass because it can consume multiple backend credits and depends on asynchronous job fixtures. Instead, the connected suite loads real `wp-admin` assets and supplies only the terminal `beepbeepai_bulk_job_poll` response with nested exhausted `entitlement_state`, verifying that the production AJAX ingestion path updates counters and locks generation controls.

## Fixture Validation Result - 2026-05-26

The local wp-env connected fixture check confirmed that the usage and dashboard state endpoints agree on a canonical free-plan state with `token_limit: 50`, `tokens_used_this_month: 1`, `tokens_remaining: 49`, `can_generate: true`, and `quota_state: "active"`. It is not the required isolated final-credit fixture.

No destructive generation was run and no backend state was changed. A dedicated test record must be manually reset through an approved backend process to `tokens_used_this_month: 49` and `tokens_remaining: 1` before executing the live final-credit and quota-denial cases. Until that execution passes, the original Dashboard-versus-Library zero-credit contradiction is covered by static/live bridge tests but is not closed by a real connected final-credit proof.

Safe regression results for this blocked attempt: static NAI suite `25 passed`; live wp-env Library suite `7 passed`; connected suite without destructive opt-in `1 passed` terminal contract and `2 skipped` real backend cases.

## Remaining Risks

- Legacy non-NAI pages still have DOM-derived quota fallbacks. The store now wins quota decisions in `bbai-admin.js`, but those surfaces need regression tests before old fallbacks can be removed.
- A real unmocked terminal bulk-job payload still needs verification against a controlled authenticated account with a disposable quota fixture.
- Direct forwarding of successful single-generation `entitlement_state` through the WordPress AJAX envelope is not yet asserted; the current tested UI path ingests the canonical follow-up usage response.
- Account identity elements outside the NAI shell may still rely on navigation after authentication.
- Pagination/empty precedence is guarded for the NAI/legacy Library containers; future server-rendered list variants should use the same state markers.

## Next Safe Step

Run the opt-in connected single-credit suite against a prepared backend fixture. After it is green, either add an additive WordPress AJAX pass-through for the successful generation `entitlement_state` envelope or establish a disposable real bulk fixture before extending quota handling to remaining legacy views.
