# Image Accessibility Audit — Technical Implementation Plan

**Status:** Proposed (pre-implementation)
**Author:** Engineering
**Target plugin version:** 4.8.0
**Target DB schema version:** `BEEPBEEP_AI_DB_VERSION` 1.0.0 → 1.1.0
**Scope:** Plugin-side only. No backend, Supabase, Stripe, hosted-dashboard or production API changes.

---

## 1. Current architecture summary

### 1.1 Bootstrap and entry points

| Concern | Location |
| --- | --- |
| Plugin header, constants, activation/deactivation, multisite fan-out | [beepbeep-ai-alt-text-generator.php](beepbeep-ai-alt-text-generator.php) |
| Required-file manifest (no autoloader — explicit `require_once` list) | [beepbeep-ai-alt-text-generator.php:168-218](beepbeep-ai-alt-text-generator.php:168) |
| Plugin runner | `beepbeepai_run()` → `\BeepBeepAI\AltTextGenerator\Plugin` in [includes/class-bbai.php](includes/class-bbai.php) |
| Hook registration (admin menu, AJAX, REST, cron, media hooks) | [admin/class-bbai-admin-hooks.php](admin/class-bbai-admin-hooks.php) |
| DB schema install/upgrade | [includes/class-bbai-db.php](includes/class-bbai-db.php), hooked to `admin_init` via `DB_Schema::maybe_upgrade` |
| Uninstall/data purge | [uninstall.php](uninstall.php) |

Constants of note: `BEEPBEEP_AI_VERSION` (4.7.0), `BEEPBEEP_AI_DB_VERSION` (1.0.0), `BEEPBEEP_AI_PLUGIN_DIR/URL`, plus legacy `BBAI_*` aliases.

### 1.2 Namespaces and class organisation

Two coexisting architectures:

1. **Legacy / primary — `BeepBeepAI\AltTextGenerator`.** File convention `class-bbai-*.php` / `class-*.php`, hand-required. This is what actually renders the admin and serves the live AJAX/REST surface. Central object is `Core` ([admin/class-bbai-core.php](admin/class-bbai-core.php)), **12,583 lines**, assembled from traits in [admin/traits/](admin/traits/): `Core_Media`, `Core_Generation`, `Core_Review`, `Core_Assets`, `Core_Export`, `Core_Ajax_{Auth,License,Billing,Queue}`.
2. **v5 service layer — `BeepBeepAI\AltText\Core` + `…\Services` + `…\Controllers`.** DI container ([includes/core/class-container.php](includes/core/class-container.php)), `Service_Provider`, `Router`, `Event_Bus`, bootstrapped by [includes/bootstrap-v5.php](includes/bootstrap-v5.php). Registers a **parallel** set of AJAX actions under `bbai_*` while the legacy layer registers `beepbeepai_*` for the same operations.

Note the namespace collision hazard: `BeepBeepAI\AltTextGenerator\Services\*` (legacy service classes such as `Usage_Helper`, `Dashboard_State`) is a *different* namespace from `BeepBeepAI\AltText\Core` (v5). New code must pick one deliberately.

### 1.3 Admin page registration and dashboard

- Menu built in `Core::add_settings_page()` — **[admin/class-bbai-core.php:1912](admin/class-bbai-core.php:1912) is the live implementation.** A near-identical copy exists in [admin/traits/trait-core-admin-ui.php:19-166](admin/traits/trait-core-admin-ui.php:19), but that trait is **never `use`d by any class** (verified: `Core_Admin_UI` appears only in its own definition) — it is dead code. See §2.4.
- Top-level slug `bbai`; submenus `bbai-library`, `bbai-analytics`, `bbai-credit-usage`, `bbai-settings`, plus hidden routes (`bbai-guide`, `bbai-debug`, `bbai-checkout`, `bbai-ui-kit`).
- **All pages route through one callback**, `Core::render_settings_page()` ([admin/class-bbai-core.php:2655](admin/class-bbai-core.php:2655)), which resolves `?page=` + `?tab=` into a tab and includes a partial from [admin/partials/](admin/partials/).
- Design system: PHP component partials in [admin/partials/components/](admin/partials/components/) rendered via `bbai_ui_render( $component, $args )` ([includes/admin/ui-components.php](includes/admin/ui-components.php)). Existing components directly relevant to us: `stat-card`, `stat-group`, `metrics-grid`, `image-table`, `filter-group`, `progress-block`, `section-card`, `section-header`, `state-block`, `ui-button`, `page-shell`.
- CSS is plain hand-written CSS under [assets/css/](assets/css/), organised `base/ components/ features/<area>/ nai/ tokens/`. No preprocessor, no CSS-in-JS.

### 1.4 Existing image discovery

There is **no shared discovery service**. Attachment queries are open-coded in at least four places:

| Purpose | Location |
| --- | --- |
| Media stats (totals / with-alt / missing) | `Core::get_media_stats()` [admin/traits/trait-core-media.php:41](admin/traits/trait-core-media.php:41) |
| Coverage-scan totals + keyset-paginated batch rows | `Core::get_alt_coverage_image_total()` / `get_alt_coverage_batch_rows()` [admin/class-bbai-core.php:3697-3755](admin/class-bbai-core.php:3697) |
| Missing / needs-review ID lists | `Core::get_missing_attachment_ids()`, `get_needs_review_attachment_ids()` [admin/class-bbai-core.php:4543](admin/class-bbai-core.php:4543) |
| ALT Library table rows | inline `$wpdb` in [admin/partials/library-tab.php](admin/partials/library-tab.php) |

All use the same predicate: `post_type='attachment' AND post_status='inherit' AND post_mime_type LIKE 'image/%'`.

### 1.5 Existing audit-like functionality (important — do not duplicate)

**`Core::get_alt_text_coverage_scan()` / the "ALT coverage scan"** ([admin/class-bbai-core.php:3674-4308](admin/class-bbai-core.php:3674)) is already a batched, resumable-ish, AJAX-pumped site scan. It:

- counts totals with one `COUNT(*)`;
- walks attachments in keyset-paginated batches of 150 (`WHERE ID < :last ORDER BY ID DESC`), pulling alt / approved-hash / source in one grouped `LEFT JOIN`;
- classifies each row (`missing`, `weak`, `approved`, `filename_only`) and counts a site-wide duplicate total with a single `GROUP BY alt.meta_value HAVING cnt > 1`;
- persists job state in a transient `bbai_alt_coverage_job_{id}` (TTL 900s) and the final aggregate in `bbai_alt_coverage_scan_v4` (TTL 86400s);
- is driven by `bbai_start_alt_coverage_scan` / `bbai_poll_alt_coverage_scan` / `bbai_rescan_alt_coverage` AJAX actions.

**Its limitation is the reason this project exists: it stores aggregate counters only.** There is no per-image record, so no filterable issue list, no per-issue resolution, no history, and no way to answer "which images, and which issue".

Also present: `BBAI_Alt_Quality_Scorer` ([includes/class-alt-quality-scorer.php](includes/class-alt-quality-scorer.php), 915 lines) — a deterministic 3-layer alt-text quality engine (hard-fail gate → weighted breakdown → optional LLM layer) with placeholder/generic/redundant-prefix word lists; and `BBAI_SEO_Quality_Checker` ([includes/class-seo-quality-checker.php](includes/class-seo-quality-checker.php)) with `is_just_filename()`, `has_redundant_prefix()`, `has_descriptive_content()`.

### 1.6 Generation, bulk processing, review queue

- **Generation:** legacy path `Core::generate_and_save()` ([admin/class-bbai-core.php:6270](admin/class-bbai-core.php:6270)) → `Core::persist_generation_result()` ([admin/class-bbai-core.php:5794](admin/class-bbai-core.php:5794)) is the single safe write point for alt text (`_wp_attachment_image_alt` + `_bbai_generated_at`, `_bbai_source`, `_bbai_model`, token meta, and the `_bbai_review_*` snapshot). ⚠️ A **shadowed** `persist_generation_result()` with an incompatible signature also exists at [admin/traits/trait-core-generation.php:274](admin/traits/trait-core-generation.php:274) — see §2.4. v5 mirror: `Services\Generation_Service` ([includes/services/class-generation-service.php](includes/services/class-generation-service.php)).
- **Bulk:** `Queue` ([includes/class-queue.php](includes/class-queue.php)) — custom table `{prefix}bbai_queue`, statuses `pending|processing|complete|failed`, `claim_batch()` with locking, `reset_stale()`, retry/backoff, driven by a WP-Cron single event `bbai_process_queue`. Plus a synchronous AJAX bulk-job pump (`beepbeepai_bulk_job_start` / `_poll`).
- **Review queue:** post-meta based, not a table. Meta contract `_bbai_review_{score,status,grade,summary,issues,model,alt_hash,version}` + `_bbai_reviewed_at`; approval is `_bbai_user_approved_hash` / `_bbai_user_approved_at`. The **live writer** is `Core::persist_generation_result()` ([admin/class-bbai-core.php:5818-5825](admin/class-bbai-core.php:5818)); the trait's writer is shadowed (§2.4). Still-live trait members: `Core_Review::evaluate_alt_health()`, `get_review_snapshot()`, `purge_review_meta()` ([admin/traits/trait-core-review.php:68-283](admin/traits/trait-core-review.php:68)) — these are *not* shadowed and are called from `Core`, `Core_Media` and [admin/partials/library-tab.php:747](admin/partials/library-tab.php:747). `Core::get_library_workspace_row_state()` ([admin/class-bbai-core.php:4462](admin/class-bbai-core.php:4462)) is the canonical row classifier (`missing` / `weak` / `optimized` + `user_approved`). Surfaced by [admin/partials/dashboard-li-surface-review-queue.php](admin/partials/dashboard-li-surface-review-queue.php) and the `image-table` component with scope `needs_review`.

### 1.7 Data stores

**Custom tables** (all created in `DB_Schema::upgrade_to_1_0_0()`): `bbai_queue`, `bbai_logs`, `bbai_credit_usage`, `bbai_usage_logs`, `bbai_contact_submissions`.

**Post meta:** `_wp_attachment_image_alt` (core), `_bbai_generated_at`, `_bbai_source`, `_bbai_model`, `_bbai_tokens_*`, `_bbai_review_*`, `_bbai_reviewed_at`, `_bbai_user_approved_hash`, `_bbai_user_approved_at`.

**Options / transients:** `bbai_settings`, `bbai_db_version`, `beepbeepai_jwt_token`, `beepbeepai_license_{key,data}`, `bbai_telemetry_*`, `bbai_todays_pass_*`; transients `bbai_alt_coverage_scan_v4`, `bbai_alt_coverage_job_*`, `bbai_stats_v3`, `bbai_usage_cache`. Object-cache wrapper with generation-based invalidation: `BBAI_Cache` ([includes/class-bbai-cache.php](includes/class-bbai-cache.php)) — `BBAI_Cache::bump( $slug )`.

### 1.8 Background processing

- WP-Cron only. `Queue::CRON_HOOK = 'bbai_process_queue'` (single events), `beepbeepai_run_migration`.
- **Action Scheduler is not bundled.** `Event_Bus` opportunistically calls `as_enqueue_async_action()` if some other plugin provides it, else falls back to `wp_schedule_single_event` ([includes/core/class-event-bus.php:170-190](includes/core/class-event-bus.php:170)).
- The coverage scan does **not** use cron at all — the browser pumps batches via AJAX polling. This is the most reliable pattern in this codebase and the one we will follow.

### 1.9 HTTP surface

- **REST:** namespace `bbai/v1`, ~30 routes in [admin/class-bbai-rest-controller.php](admin/class-bbai-rest-controller.php) (2,915 lines). Permission callbacks: `can_edit_media` (license-aware: `upload_files` when licensed, else manage), `can_edit_attachment` (`current_user_can('edit_post',$id)`), `can_manage_admin` (`manage_options`).
- **AJAX:** ~60 actions mapped in `Admin_Hooks::register_ajax_hooks()`. House convention: `wp_verify_nonce( $_POST['nonce'], 'beepbeepai_nonce' )` then `$this->user_can_manage()`, then `wp_send_json_success/error` with an explicit HTTP status.

### 1.10 Auth, licensing, entitlements, credits

- `API_Client_V2` ([includes/class-api-client-v2.php](includes/class-api-client-v2.php), 3,940 lines) — singleton; JWT + licence key; traits for auth/billing/usage/token/license.
- `Auth_State::resolve()` ([includes/services/class-auth-state.php](includes/services/class-auth-state.php)) — canonical auth snapshot.
- `Admin\Plan_Helpers` ([includes/admin/class-plan-helpers.php](includes/admin/class-plan-helpers.php)) — canonical plan detection: `get_plan_slug()`, `get_plan_data()` → `plan_slug, is_free, is_growth, is_agency, is_pro, is_paid, can_autopilot`.
- Credits/quota: `Usage_Tracker`, `Token_Quota_Service`, `Trial_Quota`, `Credit_Usage_Logger`.
- Capability: `Core::CAPABILITY = 'manage_bbbbai_text'`, granted to admins by `Core::ensure_capability()`, with `manage_options` fallback.

### 1.11 Telemetry

`BBAI_Telemetry::emit( string $event, array $props )` ([includes/class-bbai-telemetry.php](includes/class-bbai-telemetry.php)) → sanitises, enriches with canonical properties (`site_install_id`, `plugin_version`, `plan`, `environment`, `telemetry_version`, …), fires the `bbai_telemetry_event` action and pushes to a 300-entry ring buffer. PostHog capture via `capture_posthog_event()`. Consent gate: `has_telemetry_consent()`. Canonical-property invariants are covered by [tests/phpunit/TelemetryCanonicalTest.php](tests/phpunit/TelemetryCanonicalTest.php).

### 1.12 Tooling, tests, requirements

| Item | Value |
| --- | --- |
| PHP | `Requires PHP` not declared in header; PHPCS `testVersion` = **7.4-** |
| WordPress | **6.2+** (`Requires at least`, and PHPCS `minimum_supported_wp_version`) |
| Multisite | Supported — activation/deactivation fan-out + `wp_initialize_site` |
| PHP unit tests | PHPUnit 10.5, `tests/phpunit/`, **stub bootstrap with no WordPress** ([tests/phpunit/bootstrap.php](tests/phpunit/bootstrap.php)). Baseline: **9 tests, 49 assertions, green.** |
| JS tests | Written for **`node --test`** (`node:test` + `node:assert`), in [tests/](tests/) — baseline **12 tests green**. ⚠️ `npx jest` reports "Test suite failed to run / 0 tests" for *every* file, new and pre-existing: the suites are node:test, not Jest. Run `node --test tests/*.test.js`. See §12. |
| E2E | Playwright in [tests/e2e/](tests/e2e/), skipped unless `BBAI_E2E_BASE_URL` is set |
| PHPCS | [phpcs.xml](phpcs.xml), `WordPress-Extra`. **Currently broken in this checkout** — see §11 |
| Static analysis | **None** (no PHPStan/Psalm) |
| Build | `npm run build:js` (terser minification, [scripts/build-min-js.mjs](scripts/build-min-js.mjs)); zip via `scripts/build-plugin-zip.bash` |
| JS framework | None. Hand-written ES5/ES6 in `assets/js/` + `assets/src/js/`, minified to `assets/dist/js/` |

---

## 2. Reuse / extend / risk assessment

### 2.1 Reusable unchanged

| Component | Use in audit |
| --- | --- |
| `DB_Schema` | Add `upgrade_to_1_1_0()`; the idempotency + `maybe_upgrade` machinery is already correct |
| `BBAI_Alt_Quality_Scorer` | Backing engine for `generic_alt`, `possible_keyword_stuffing`, `alt_too_short` heuristics; its `$placeholder_words` / `$generic_image_words` / `$redundant_prefixes` lists are the vocabulary for our rules |
| `BBAI_SEO_Quality_Checker::is_just_filename()` | `filename_as_alt` rule |
| `BBAI_Telemetry::emit()` | All audit events |
| `Admin\Plan_Helpers` | Backing store for the entitlement adapter |
| `BBAI_Cache` | Dashboard aggregate caching + invalidation |
| `bbai_ui_render()` + component partials | Entire audit UI |
| `Queue` | Remediation (generate) actions enqueue here — unchanged |
| `Core::persist_generation_result()` (class copy, not the trait) | The only alt-text write path we will trigger |
| `_bbai_review_*` meta contract | Review-queue integration — read/observe only |
| REST permission callbacks | Copy the `can_edit_media` / `can_edit_attachment` pattern |

### 2.2 To extend (additively)

| Component | Extension |
| --- | --- |
| `DB_Schema` | new table + version bump 1.0.0 → 1.1.0 |
| `trait-core-admin-ui.php` | one `add_submenu_page` for `bbai-audit` |
| `Core::render_settings_page()` + `get_resolved_tab()` | add `bbai-audit → audit` to both page→tab maps |
| `trait-core-assets.php` | enqueue audit CSS/JS **only** when the resolved tab is `audit` |
| `Admin_Hooks` | register audit REST controller + audit AJAX actions |
| `uninstall.php` | drop the audit table, delete audit options + decorative meta |
| `attachment_updated` / `wp_update_attachment_metadata` hooks | invalidate the affected audit row |

### 2.3 Risky / tightly coupled — handle with care

1. **`admin/class-bbai-core.php` is a 12,583-line god object.** Adding audit logic there compounds the problem and makes PHPCS/review impractical. **Decision: no new logic in `Core`.** Audit lives in `includes/audit/`; `Core` gains only route/asset wiring.
2. **Two parallel architectures with duplicate AJAX action names** (`beepbeepai_regenerate_single` in legacy, `bbai_regenerate_single` in v5 router) — same operation, two entry points. Audit will register **one** set of actions, in the legacy namespace, matching the surface that is actually live.
3. **Shadowed trait methods (the sharpest trap in this codebase).** `Core` declares methods that collide with methods in the traits it `use`s. In PHP the **class method silently wins** — no error, no warning. Verified shadowed (trait copy is dead): `Core_Media::{get_media_stats, invalidate_stats_cache, is_image, get_missing_attachment_ids, get_all_attachment_ids, prepare_attachment_snapshot, get_source_meta_map, format_source_label, format_source_description}`, `Core_Generation::{persist_generation_result, build_prompt, build_inline_image_payload, extract_json_object, redact_api_token, sanitize_error_message, should_retry_without_image}`, `Core_Review::{hash_alt_text, review_alt_text_with_model}`, and all eight `Core_Ajax_Queue::ajax_*` methods. **`persist_generation_result` is the dangerous one — the two copies have incompatible signatures.** Only `Core_Assets::enqueue_admin` is handled correctly (explicitly aliased to `trait_enqueue_admin` and called from `Core::enqueue_admin`). *Implication for this work: always confirm which copy is live before calling into `Core`; prefer not to call `Core` at all from audit code.*
4. **The existing coverage scan is a competing "scan" concept.** Two scan buttons producing different numbers is the single biggest UX risk. Mitigation in §7.
5. **Transient-only scan state.** A persistent object cache that evicts transients loses the job mid-scan. Audit run state goes in an **option** (autoload off), not a transient.
6. **No lock on the existing scan** — two admins can run concurrent coverage scans. Audit adds an explicit lock.
7. **`Plan_Helpers::get_plan_data()` caches in a static** for the request and calls the remote API — never call it inside a per-image loop.

### 2.4 Duplicated logic already in the tree

- **~26 methods exist in two copies** (class + trait), with the trait copy dead — see §2.3.3 for the full list. This is the largest single source of duplication in the plugin.
- **`admin/traits/trait-core-admin-ui.php` (1,042 lines) is entirely dead** — the `Core_Admin_UI` trait is never `use`d by any class. Its `add_settings_page()`, `handle_checkout_redirect()` and onboarding renderers are all duplicated live in `Core`. Recommend deletion in a separate cleanup change.
- Alt-text normalisation implemented at least twice: `Core::normalize_alt_coverage_compare_value()` and `Core::hash_alt_text()` / `Core_Review::hash_alt_text()`.
- Attachment-image predicate repeated in 4+ places (§1.4).
- `DB_Schema::create_performance_indexes()` creates `idx_bbai_generated_at` and `idx_bbai_source` with **identical column definitions** `(meta_key(50), meta_value(50))` — one is redundant.

### 2.5 Security / performance / data-integrity observations (pre-existing)

| Severity | Observation |
| --- | --- |
| Low | PostHog project API key is hardcoded ([includes/class-bbai-telemetry.php:44](includes/class-bbai-telemetry.php:44)). This is a *public* ingest key by PostHog's design — acceptable, noted for completeness. |
| Low | Coverage-scan job polling checks `user_id` ownership but the job transient key is guessable (UUID) — mitigated by the nonce + capability check. Audit will keep the ownership check. |
| Medium (perf) | `get_alt_coverage_duplicate_count()` runs an unbounded `GROUP BY meta_value` over all attachment alt meta at the end of every scan. On very large libraries this is a filesort over a `TEXT` column. Audit reuses the shape but bounds it (§6.3). |
| Medium (perf) | Full-repo PHPCS **OOMs at 128 MB** tokenising `class-bbai-core.php`. |
| Copy | [admin/partials/nai-guest-audit.php:35-41](admin/partials/nai-guest-audit.php:35) derives a **"WCAG risk: Low/Medium/High"** label from coverage percentage. That is a compliance-adjacent claim of exactly the kind this project's copy guidance forbids. Recommend rewording in a separate, small change — flagged, not in this plan's scope. |

---

## 3. Proposed architecture

New directory `includes/audit/`, namespace **`BeepBeepAI\AltTextGenerator\Audit`**, file naming `class-*.php` to match repo convention. Wired by explicit `require_once` in the main plugin file (no autoloader in this project).

```
includes/audit/
  class-audit-issue.php                 Audit_Issue                 pure  ✅ stage 1
  class-audit-vocabulary.php            Audit_Vocabulary            pure  ✅ stage 1
  class-image-audit-context.php         Image_Audit_Context         pure  ✅ stage 1
  class-audit-rule-interface.php        Audit_Rule_Interface        pure  ✅ stage 1
  class-abstract-audit-rule.php         Abstract_Audit_Rule         pure  ✅ stage 1
  class-audit-rule-registry.php         Audit_Rule_Registry         pure  ✅ stage 1
  rules/
    class-missing-alt-rule.php          Missing_Alt_Rule            pure  ✅ stage 1
    class-empty-alt-review-rule.php     Empty_Alt_Review_Rule       pure  ✅ stage 1
    class-filename-alt-rule.php         Filename_Alt_Rule           pure  ✅ stage 1
    class-generic-alt-rule.php          Generic_Alt_Rule            pure  ✅ stage 1
    class-duplicate-alt-rule.php        Duplicate_Alt_Rule          pure  ✅ stage 1
    class-alt-too-short-rule.php        Alt_Too_Short_Rule          pure  ✅ stage 1
    class-alt-too-long-rule.php         Alt_Too_Long_Rule           pure  ✅ stage 1
    class-keyword-stuffing-rule.php     Keyword_Stuffing_Rule       pure  ✅ stage 1
    class-orphaned-attachment-rule.php  Orphaned_Attachment_Rule    pure  ✅ stage 1
    class-no-usage-context-rule.php     No_Usage_Context_Rule       pure  ✅ stage 1
  class-image-accessibility-score-service.php  Image_Accessibility_Score_Service  pure  ✅ stage 1
  class-image-audit-repository.php      Image_Audit_Repository      $wpdb  ◑ schema only in stage 1
  class-image-discovery-service.php     Image_Discovery_Service     WP/$wpdb        stage 2
  class-image-audit-service.php         Image_Audit_Service         orchestrator    stage 2
  class-audit-batch-processor.php       Audit_Batch_Processor       WP              stage 2
  class-audit-progress-service.php      Audit_Progress_Service      options + lock  stage 2
  class-audit-ajax-controller.php       Audit_Ajax_Controller       scan pump       stage 2
  class-audit-rest-controller.php       Audit_REST_Controller       bbai/v1/audit/* stage 4
  class-decorative-image-service.php    Decorative_Image_Service    post meta       stage 5
  class-audit-entitlement-service.php   Audit_Entitlement_Service   adapter         stage 5
  class-audit-telemetry-service.php     Audit_Telemetry_Service     wrapper         stage 5
```

Two deviations from the original sketch, both settled during stage 1:

- **`Alt_Length_Rule` was split into `Alt_Too_Short_Rule` and `Alt_Too_Long_Rule`.** One rule reports one issue code, which keeps `Audit_Rule_Interface` to a single `get_code()` and lets the two checks carry different severities (warning vs information) and different decorative handling.
- **`Audit_Vocabulary` and `Abstract_Audit_Rule` were added.** The former is a memoised adapter over new public accessors on `BBAI_Alt_Quality_Scorer` so the audit and the generation-time scorer share one word list instead of duplicating it; the latter holds the threshold plumbing and catalogue lookups every rule needs.

**"pure" = no WordPress function calls in the hot path**, so it is directly unit-testable under the existing stub bootstrap. Translation is handled by passing already-translated label/explanation strings in from a WP-aware registry method, keeping the rule `evaluate()` methods free of `__()`.

Data flow:

```
Admin clicks "Run image audit"
  → AJAX bbai_audit_start  ──► Audit_Progress_Service::acquire_lock()
                               Image_Discovery_Service::count()
                               Image_Audit_Repository::begin_run()
  → AJAX bbai_audit_tick (polled) ──► Audit_Batch_Processor::process_batch()
         ├─ Image_Discovery_Service::get_batch( after_id, size )   [1 query]
         ├─ Duplicate map (built once per run, cached in run state)  [1 query]
         ├─ per image → Image_Audit_Context → Audit_Rule_Registry::evaluate()
         └─ Image_Audit_Repository::upsert_many()                  [1 query]
  → run completes ──► Image_Accessibility_Score_Service::calculate()
                      cached in bbai_audit_summary option + BBAI_Cache
```

---

## 4. Data model

### 4.1 New table `{prefix}bbai_image_audit`

```sql
CREATE TABLE {prefix}bbai_image_audit (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    attachment_id  BIGINT UNSIGNED NOT NULL,
    site_id        VARCHAR(64)     NOT NULL DEFAULT '',
    run_id         VARCHAR(36)     NOT NULL DEFAULT '',
    current_alt_hash CHAR(40)      NOT NULL DEFAULT '',
    alt_length     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    issue_codes    VARCHAR(255)    NOT NULL DEFAULT '',
    issue_count    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    severity       VARCHAR(20)     NOT NULL DEFAULT 'information',
    audit_status   VARCHAR(20)     NOT NULL DEFAULT 'open',
    score_contribution DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    duplicate_group_size SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    context_summary VARCHAR(191)   NOT NULL DEFAULT '',
    parent_post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    last_scanned_at DATETIME       NULL,
    resolved_at    DATETIME        NULL,
    created_at     DATETIME        NOT NULL,
    updated_at     DATETIME        NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY attachment_site (attachment_id, site_id),
    KEY severity_status (severity, audit_status),
    KEY audit_status (audit_status),
    KEY last_scanned_at (last_scanned_at),
    KEY run_id (run_id)
) {charset_collate};
```

Design notes:

- **`UNIQUE KEY (attachment_id, site_id)`** gives us idempotent `INSERT … ON DUPLICATE KEY UPDATE` re-scans — one row per image, no history bloat. `site_id` mirrors the `bbai_usage_logs` convention (from `get_site_identifier()`), so multisite/table-sharing behaves consistently; on single site it is a constant.
- **`issue_codes`** is a comma-delimited, alphabetically-sorted list of codes wrapped in delimiters — `,missing_alt,no_usage_context,` — so filtering is an index-assisted-ish `LIKE '%,missing_alt,%'` without needing JSON functions (MySQL 5.6/MariaDB 10.x compatible). `VARCHAR(255)` is sufficient for all 10 codes (~140 chars worst case).
- **`severity`** is the *worst* severity across the row's issues, precomputed so the issues table can sort/filter without re-evaluating rules.
- **`audit_status`** ∈ `open | resolved | dismissed | decorative`.
- `current_alt_hash` = `sha1( normalised alt )` — lets us detect "alt changed since last scan" and auto-resolve without a rescan.
- **No binary data, no duplicated attachment fields.** Filename, URL, thumbnail, title, caption, description are read live from WP at render time for the ≤50 rows on screen (single `_prime_post_caches` + `update_postmeta_cache`).
- `context_summary` is a short human string (`"Used by: About Us"` / `"Not attached to a post"`), truncated to 191 chars.

### 4.2 Options

| Option | Autoload | Contents |
| --- | --- | --- |
| `bbai_audit_run_state` | no | Active/last run: `run_id, status, total, processed, last_attachment_id, batch_number, started_at, updated_at, counters{}, started_by` |
| `bbai_audit_summary` | no | Last completed summary (score + counts + `completed_at`) — what the dashboard reads |
| `bbai_audit_duplicate_map` | no | Run-scoped `hash => count` map for alt values appearing >1 time (bounded, see §6.3) |
| `bbai_audit_last_completed_at` | yes (small) | Unix ts, used by the dashboard card without loading the full summary |

Transients: `bbai_audit_lock` (TTL 300s, holds `run_id` + `user_id` + heartbeat) — a transient is correct here because expiry *is* the stale-lock recovery mechanism.

### 4.3 Post meta (decorative)

| Key | Value |
| --- | --- |
| `_bbai_decorative` | `'1'` when marked decorative; deleted when reversed |
| `_bbai_decorative_set_at` | MySQL datetime |
| `_bbai_decorative_set_by` | user ID |

Stored as meta rather than a table column because it is an *administrator decision about the image*, must survive audit-table truncation/rebuild, and belongs with the attachment.

---

## 5. Audit rules

### 5.1 Issue catalogue

| Code | Severity | Auto-fixable | Review req. | Trigger (conservative) |
| --- | --- | --- | --- | --- |
| `missing_alt` | critical | yes | no | No `_wp_attachment_image_alt` meta row **at all**, and not marked decorative |
| `empty_alt_review_required` | review | no | yes | Meta row exists but value is empty/whitespace, and not marked decorative — communicates *uncertainty*, not failure |
| `filename_as_alt` | warning | yes | no | Normalised alt == normalised filename stem, **or** alt matches `^[\w-]+\.(jpe?g|png|gif|webp|avif|svg)$` |
| `generic_alt` | warning | yes | no | After stripping redundant prefixes, remaining meaningful tokens are all in `$generic_image_words`/`$placeholder_words` (delegated to `BBAI_Alt_Quality_Scorer` vocabulary) |
| `duplicate_alt` | warning | no | yes | Normalised non-empty alt shared by ≥2 attachments, alt length ≥ 15 chars and ≥ 3 tokens (short/generic values are excluded to avoid noise) |
| `alt_too_short` | warning | yes | no | Non-empty alt with < 3 meaningful tokens **or** < 12 characters |
| `alt_too_long` | information | no | no | > 250 characters (conservative; common screen-reader guidance is ~125, so 125–250 is *not* flagged) |
| `possible_keyword_stuffing` | warning | no | yes | ≥ 6 tokens **and** (any token repeats ≥ 3×, or comma/pipe-separated fragments ≥ 4 with mean fragment length ≤ 3 tokens). Named "possible" deliberately |
| `orphaned_attachment` | information | no | no | `post_parent = 0` and no usage signal found |
| `no_usage_context` | information | no | no | Usage context could not be determined cheaply (distinct from *confirmed* orphan) |

Precedence: `missing_alt` and `empty_alt_review_required` are mutually exclusive and suppress all content-quality rules. Marked-decorative images are excluded from `missing_alt`, `empty_alt_review_required`, `alt_too_short` and `duplicate_alt`.

Row severity = max(issue severities), ordered `critical > warning > review > information`.

### 5.2 Rule contract

```php
interface Audit_Rule_Interface {
    public function get_code(): string;
    public function get_severity(): string;                 // critical|warning|review|information
    public function is_auto_fixable(): bool;
    public function requires_human_review(): bool;
    public function applies_to( Image_Audit_Context $ctx ): bool;
    public function evaluate( Image_Audit_Context $ctx ): bool;   // true = issue present
}
```

Label / explanation / recommended action live in `Audit_Issue::definition( $code )` (WP-aware, translated), keeping `evaluate()` pure. Extension point:

```php
apply_filters( 'bbai_audit_rules', array $rules );     // add/remove/replace rules
apply_filters( 'bbai_audit_rule_thresholds', array $thresholds );
apply_filters( 'bbai_audit_issue_definition', array $definition, string $code );
```

### 5.3 Normalisation (single implementation)

`Image_Audit_Context::normalise( string $v ): string` — lowercase, strip accents where `remove_accents()` is available, replace non-alphanumerics with a single space, collapse whitespace, trim. This is the one normaliser; the duplicate map and `filename_as_alt` both use it. (Deliberately identical in behaviour to the existing `Core::normalize_alt_coverage_compare_value()` so numbers stay consistent with the legacy coverage card.)

---

## 6. Scanning strategy

### 6.1 Discovery

`Image_Discovery_Service`:

```php
count_images(): int
get_batch( int $after_attachment_id, int $size ): array   // keyset pagination, ORDER BY ID DESC
```

One query per batch, modelled on `get_alt_coverage_batch_rows()` but returning additionally `post_title`, `post_excerpt` (caption), `post_content` (description), `post_parent`, `guid`, `post_mime_type`, plus grouped meta (`_wp_attachment_image_alt`, `_bbai_decorative`, `_bbai_source`, `_bbai_user_approved_hash`). **No `get_post()` / `wp_get_attachment_metadata()` per row** — those are only called for the ≤50 rows rendered in the issues table.

Keyset (`ID < :last`) not `OFFSET` — constant cost regardless of depth, and stable under concurrent uploads.

Extensibility: `apply_filters( 'bbai_audit_discovery_where', $sql_fragment )` and a `$mime_types` constructor argument prepare for future WooCommerce/page-context sources without changing callers.

### 6.2 Usage context (cheap only)

Phase one uses **only** signals available without an HTML crawl:
1. `post_parent` → parent post title;
2. featured-image usage via a single batched `postmeta` lookup on `_thumbnail_id` for the batch's IDs.

**No full-site HTML crawl.** How the two usage rules divide, as built in stage 2:

- Both signals are cheap and always available, so usage normally counts as **resolved**. An image with no parent and no featured-image reference reports `orphaned_attachment`, whose copy states plainly that the finding is "based on the checks this plugin runs" and that the image "may still be used in a way the audit cannot see" — which is true: an image placed only in block or shortcode markup reads as unattached.
- `no_usage_context` is the **degraded-mode** signal. It fires when usage genuinely was not established: the `bbai_audit_resolve_usage_context` filter turned resolution off, or the lookup query failed. A failed lookup must never be reported as "definitely unused", and the code returns `known => false` on `null` results specifically to prevent that.

### 6.3 Duplicate detection

Built **once per run**, at run start, with a single bounded query:

```sql
SELECT LOWER(TRIM(alt.meta_value)) AS v, COUNT(*) AS cnt
  FROM {posts} p
  INNER JOIN {postmeta} alt ON p.ID = alt.post_id AND alt.meta_key = '_wp_attachment_image_alt'
 WHERE p.post_type='attachment' AND p.post_status='inherit'
   AND p.post_mime_type LIKE 'image/%'
   AND CHAR_LENGTH(TRIM(alt.meta_value)) >= 15
 GROUP BY v HAVING cnt > 1
 ORDER BY cnt DESC
 LIMIT 5000
```

Result is normalised in PHP into `sha1(normalised) => count`, stored in `bbai_audit_duplicate_map` for the run's lifetime, giving O(1) per-image lookup with **zero per-image queries**. The `LIMIT 5000` bound (the 5,000 most-repeated values) keeps the option under ~250 KB; if the cap is hit we record `duplicate_map_truncated: true` and surface "duplicate detection covered the 5,000 most repeated values" in the UI rather than silently under-reporting.

### 6.4 Batching, progress, resume, cancel, locking

| Concern | Approach |
| --- | --- |
| Driver | Browser-pumped AJAX (`bbai_audit_start` → repeated `bbai_audit_tick`), matching the proven coverage-scan pattern. A WP-Cron hook `bbai_audit_continue` is **registered but not scheduled** in phase one, so scheduled scanning is a one-line change later. |
| Batch size | Default 100, `apply_filters( 'bbai_audit_batch_size', 100 )`, clamped 10–250 |
| Progress | `bbai_audit_run_state` option updated after every batch: `processed`, `total`, `batch_number`, `percent`, `counters` |
| Resume | State is an option, not a transient → survives cache flush. `last_attachment_id` is the resume cursor. UI offers **Resume** when status is `running`/`failed` and `updated_at` is stale |
| Cancel | `bbai_audit_cancel` sets status `cancelled` and releases the lock. Rows already written stay (partial results are still useful and clearly labelled) |
| Lock | `bbai_audit_lock` transient, TTL 300s, refreshed each batch. `acquire_lock()` uses `add_option`-style atomic semantics (`wp_cache_add`-backed `set_transient` guarded by a `SELECT … FOR UPDATE`-free compare-and-set on an autoload-off option) so two admins cannot start concurrent runs |
| Stale lock | Lock TTL expiry **plus** an explicit heartbeat check: if `updated_at` is older than 5 min the UI offers "Resume" (which steals the lock) rather than silently restarting |
| Per-image errors | Each image is evaluated in a `try/catch`; a failure writes `audit_status = 'open'`, `issue_codes = ''`, increments `counters.errors`, and logs via `Debug_Log` — **the run continues** |
| No re-processing | The keyset cursor only moves forward within a run; an image cannot be visited twice |
| No silent restart | `start` on an existing incomplete run returns `409`-equivalent JSON with the existing run's state; the UI must explicitly choose Resume or Start over |

Statuses: `not_started | queued | running | completed | failed | cancelled`.

### 6.5 Performance expectations

These are **design targets derived from the query plan, to be measured during Stage 2 QA** — not measured results:

| Library size | Batches @100 | DB queries | Expectation |
| --- | --- | --- | --- |
| 100 images | 1 | ~5 | Sub-second, single tick |
| 1,000 images | 10 | ~25 | A few seconds of polling |
| 10,000 images | 100 | ~205 | Minutes; must stay responsive and resumable |

Per batch the cost is fixed: 1 discovery query + 1 featured-image lookup + 1 multi-row upsert, plus one duplicate-map query per *run*. There is deliberately no per-image query. Beyond 10,000 images we will **state that it is untested** rather than claim support.

---

## 7. Admin UI changes

### 7.1 New page

New submenu **"Image Audit"** → `admin.php?page=bbai-audit`, rendered through the existing `render_settings_page()` router (so header, nav, plan banners and the design system are inherited unchanged). New partial `admin/partials/audit-tab.php` composed of:

- `admin/partials/components/audit-score-card.php` — score ring + the required disclaimer
- `admin/partials/components/audit-stat-grid.php` — total / no issues detected / needs attention / missing / duplicate / weak
- `admin/partials/components/audit-progress.php` — progress bar, batch counter, Cancel
- `admin/partials/components/audit-issue-badge.php` — severity chip
- `admin/partials/components/audit-issues-table.php` — filterable, paginated table

States handled explicitly: **first-run** (never scanned → explainer + single "Run image audit" CTA), **loading**, **running** (progress + Cancel), **stale/interrupted** (Resume), **empty** (scan completed, no issues detected), **error**, **entitlement-limited** (free plan preview + upgrade CTA).

### 7.2 Dashboard integration

The existing dashboard coverage card gains an "Image accessibility health" figure sourced from `bbai_audit_summary` **when a completed audit exists**, and falls back to the current coverage transient otherwise. The legacy coverage scan keeps working untouched — we add a second, richer source, we do not remove the first. Resolving the two scans into one is explicitly deferred to phase two and recorded in §14.

### 7.3 Copy

Required disclaimer, rendered wherever the score appears:

> This score is an OpptiAI health indicator based on the checks available in this plugin. It is not a formal accessibility certification.

Plus an inline explainer next to `empty_alt_review_required`: *"Decorative images can intentionally use an empty alt attribute. Review these and mark them as decorative if they carry no meaning."*

Forbidden strings (`WCAG compliant`, `guaranteed accessible`, `legally compliant`, `perfect accessibility`, `AI-certified`) will be asserted absent by a test over the audit partials.

### 7.4 Issues table

Columns: thumbnail · title/filename · current alt · detected issues · severity · context · last scanned · status · actions.

Filters (`all`, `missing_alt`, `weak` [= filename/generic/short/stuffing], `duplicate_alt`, `review_required`, `critical`, `warning`, `resolved`) render via the existing `filter-group` component as **real links/buttons** (`<a>`/`<button>`), keyboard-operable, with `aria-pressed`, not JS-only divs.

Row actions — every one delegates to existing functionality:

| Action | Delegates to |
| --- | --- |
| Generate / Regenerate | existing `beepbeepai_regenerate_single` AJAX → `Core::generate_and_save()` |
| Edit manually | existing inline-edit path → `_wp_attachment_image_alt` |
| Send to review queue | existing `Core_Review::review_alt_text_with_model()` |
| Mark as decorative / Undo | new `Decorative_Image_Service` (post meta) |
| Dismiss issue | new — sets `audit_status = 'dismissed'` |
| View attachment | `get_edit_post_link()` |

### 7.5 Assets

`assets/css/features/audit/audit.css` and `assets/js/bbai-audit.js` (minified to `assets/dist/js/bbai-audit.min.js` by the existing `npm run build:js`), enqueued **only** when `get_resolved_tab() === 'audit'`. No audit asset loads on any other admin screen.

---

## 8. Integration points

### 8.1 With generation

The audit **never generates alt text itself**. "Generate" in the audit table posts to the existing generation AJAX action with `source = 'audit'`, so credits, quota, trial gating, telemetry and error handling all behave exactly as they do today. `source = 'audit'` is a new value for the existing `_bbai_source` meta and lets us attribute generations to the audit funnel.

### 8.2 With the review queue

No second review system. Flow:

1. Audit detects an issue → 2. user clicks Generate/Improve → 3. **existing** generation produces a suggestion → 4. it lands in the **existing** review queue (`_bbai_review_*` meta) → 5. user approves/edits/rejects there → 6. alt is saved by **existing** `persist_generation_result()` → 7. `attachment_updated` fires → 8. the audit row for that attachment is re-evaluated → 9. summary cache is bumped and the score updates.

Step 8 is the only new mechanism: a lightweight single-image re-evaluation (`Image_Audit_Service::rescan_attachment( $id )`) hooked to `attachment_updated` and to the alt-meta update path. If the new alt hash differs and no issues remain, the row is set to `resolved` with `resolved_at`.

### 8.3 With decorative decisions

Marking decorative sets the meta, sets `audit_status = 'decorative'`, removes the missing/empty issues from the row, and recomputes the summary. Reversing deletes the meta and re-evaluates. **Nothing auto-classifies as decorative** — not by filename, not by size, not by AI.

---

## 9. Entitlements

`Audit_Entitlement_Service` — a thin adapter so plan names appear in exactly one file:

```php
Audit_Entitlement_Service::can( 'run_full_audit' )
Audit_Entitlement_Service::can( 'view_all_audit_results' )
Audit_Entitlement_Service::can( 'use_bulk_remediation' )
Audit_Entitlement_Service::can( 'enable_continuous_monitoring' )
```

Backed by `Plan_Helpers::get_plan_data()`; every result passes through `apply_filters( 'bbai_audit_capability', bool $allowed, string $capability, array $plan )`.

Phase-one policy (no billing changes):

| Capability | Free | Paid |
| --- | --- | --- |
| `run_full_audit` | ✅ full site scan | ✅ |
| `view_all_audit_results` | ❌ — headline score + all counts + first 25 issue rows, then an upgrade CTA | ✅ |
| `use_bulk_remediation` | ❌ | ✅ |
| `enable_continuous_monitoring` | ❌ (not implemented in phase one; the capability exists so the UI can show a disabled placeholder) | ❌ |

Free users can still generate (existing free credits) and edit manually from the visible rows.

---

## 10. Telemetry

Via `Audit_Telemetry_Service` → `BBAI_Telemetry::emit()`. Every event carries the canonical enrichment already asserted by `TelemetryCanonicalTest` (`plugin_version`, `plan`, `site_install_id`, …).

| Event | Extra properties |
| --- | --- |
| `image_audit_started` | `total_images_bucket`, `scan_size_bucket`, `is_resume` |
| `image_audit_batch_completed` | `batch_number`, `batch_size`, `scan_size_bucket` |
| `image_audit_completed` | `total_images_bucket`, `issue_count_bucket`, `score_bucket`, `audit_duration_bucket` |
| `image_audit_failed` | `batch_number`, `error_type` (slug only) |
| `image_audit_cancelled` | `batch_number`, `percent_bucket` |
| `image_audit_results_viewed` | `score_bucket`, `issue_count_bucket` |
| `image_audit_filter_used` | `filter` (enum) |
| `image_issue_action_clicked` | `action_type`, `issue_type` |
| `image_marked_decorative` | `reversed` (bool) |
| `image_issue_resolved` | `issue_type`, `resolution` (`generated`/`manual`/`dismissed`/`decorative`) |
| `image_audit_upgrade_cta_clicked` | `placement` |

Buckets (never raw counts): `scan_size`/`total_images` → `0 | 1-50 | 51-500 | 501-5000 | 5000+`; `issue_count` → `0 | 1-10 | 11-100 | 101-1000 | 1000+`; `score` → `0-24 | 25-49 | 50-74 | 75-89 | 90-100`; `audit_duration` → `<5s | 5-30s | 30-120s | 2-10m | >10m`.

**Never sent:** image URLs, filenames, alt text, post content, PII, tokens, keys. Enforced by the service constructing property arrays from a whitelist, plus a unit test asserting no free-text field escapes.

**De-duplication:** `image_audit_started` / `completed` / `cancelled` / `failed` fire at most once per `run_id`, guarded by a flag in `bbai_audit_run_state`. `image_audit_results_viewed` fires at most once per page load.

---

## 11. Migration

1. Bump `BEEPBEEP_AI_DB_VERSION` → `1.1.0`.
2. Add `DB_Schema::upgrade_to_1_1_0()` → `Image_Audit_Repository::create_table()` via **`dbDelta`**, using `$wpdb->prefix` and `$wpdb->get_charset_collate()`.
3. Idempotent by construction: `install()` short-circuits on `version_compare`, `dbDelta` is re-runnable, and the version guard makes a repeat call a no-op. Runs on activation *and* on `admin_init` for update-without-reactivation.
4. **No existing data is touched.** No option renames, no meta migrations, no writes to `_wp_attachment_image_alt`. Existing licences, subscriptions, settings, generated alt text and review records are untouched.
5. Multisite: the new table is per-site (uses `$wpdb->prefix`), created by the existing per-blog activation fan-out and by `maybe_upgrade` on each site's `admin_init`.
6. `uninstall.php`: drop `{prefix}bbai_image_audit`, delete `bbai_audit_*` options, delete `_bbai_decorative*` meta.

Backwards compatibility: an install where the audit table failed to create must not fatal. `Image_Audit_Repository` checks `DB_Schema::table_exists()` once per request and, if missing, the audit page renders an actionable error state instead of throwing.

---

## 12. Testing strategy

**Unit (PHPUnit, existing stub bootstrap — no WordPress required):** all rules are pure classes, so `tests/phpunit/audit/` covers: missing vs empty-alt distinction; decorative exclusion; filename detection (incl. extension form and hyphen/underscore variants); generic/placeholder detection; duplicate normalisation (case, whitespace, punctuation, accents) and the short-value exclusion; short/long thresholds incl. exact boundaries; keyword-stuffing precision (must *not* fire on legitimate 8-word descriptions); severity precedence; score formula at boundaries (0, 100, all-decorative, zero images); bucket helpers; telemetry property whitelist.

**Integration-ish (stubbed `$wpdb`):** repository upsert/read-back, `issue_codes` filter matching, batch progress accumulation, resume from cursor, lock acquisition/refusal, stale-lock recovery, migration idempotency (calling `install()` twice), issue resolution when the alt hash changes.

**Permission/nonce:** REST permission callbacks and AJAX handlers asserted to reject missing nonce (403) and insufficient capability (403), and to reject attachment IDs the user cannot `edit_post`.

**JS:** `node --test` coverage for the polling controller's state machine (start → tick → complete / cancel / error / resume) mirroring the existing `tests/dashboard-*.test.js` style.

**E2E (Playwright, opt-in):** run audit on a seeded library, assert score renders, filter changes row set, decorative toggle round-trips.

**Copy guard:** a test asserting no forbidden compliance phrase appears in `admin/partials/audit-*`.

Every stage runs: `vendor/bin/phpunit`, `npx jest`, `php -l` over changed files, and scoped PHPCS.

### PHPCS status (pre-existing, environmental)

`vendor/bin/phpcs` **cannot run as configured in this checkout**: `installed_paths` is unset, so `WordPress-Extra` does not resolve, and once the paths are supplied manually the run OOMs at the default 128 MB while tokenising `admin/class-bbai-core.php`. Workaround used for this work (does not modify committed config):

```bash
vendor/bin/phpcs -d memory_limit=1024M --runtime-set installed_paths vendor/wp-coding-standards/wpcs,vendor/phpcsstandards/phpcsextra,vendor/phpcsstandards/phpcsutils,vendor/phpcompatibility/php-compatibility,vendor/phpcompatibility/phpcompatibility-wp,vendor/phpcompatibility/phpcompatibility-paragonie includes/audit
```

Recommend (separate change): add `"config": { "allow-plugins": { "dealerdirect/phpcodesniffer-composer-installer": true } }` to `composer.json` so paths register automatically.

### JS test runner (pre-existing, environmental)

The JS suites use `node:test`/`node:assert`, but [jest.config.js](jest.config.js) is set up as if they were Jest suites, and `package.json`'s `test` script is still the `npm init` placeholder. Running `npx jest` reports **"Test suite failed to run — your test suite must contain at least one test"** for all six files while the embedded node runner quietly executes and passes them. This predates this work and affects the three original suites identically. The working command is:

```bash
node --test tests/*.test.js
```

Recommend (separate change): set `"test": "node --test tests/*.test.js"` in `package.json` and drop `jest.config.js`, or convert the suites to Jest. Not changed here so this feature's diff stays feature-only.

---

## 13. Rollback strategy

| Level | Action |
| --- | --- |
| Feature | `add_filter( 'bbai_audit_enabled', '__return_false' )` — hides the menu, skips REST/AJAX registration, leaves data intact. A constant `BBAI_DISABLE_AUDIT` does the same for wp-config-level kill. |
| Code | Revert the feature commit(s). The audit table simply goes unused; nothing else reads it. |
| Data | `DROP TABLE {prefix}bbai_image_audit` + `delete_option( 'bbai_audit_*' )`. **No existing table, option or meta is modified by this work**, so a rollback cannot corrupt generation, licensing or review data. |
| Schema version | If the DB version is rolled back below 1.1.0, `maybe_upgrade` will simply re-run `dbDelta` on next admin load — safe. |
| Decorative meta | Survives rollback harmlessly (unknown meta keys are inert). |

---

## 14. Files expected to change

**New (25):**

```
includes/audit/…                                   (19 classes, see §3)
admin/partials/audit-tab.php
admin/partials/components/audit-score-card.php
admin/partials/components/audit-stat-grid.php
admin/partials/components/audit-progress.php
admin/partials/components/audit-issue-badge.php
admin/partials/components/audit-issues-table.php
assets/css/features/audit/audit.css
assets/js/bbai-audit.js
tests/phpunit/audit/*Test.php
tests/audit-progress-controller.test.js
docs/image-accessibility-audit-plan.md              (this file)
```

**Modified (7, all additive):**

| File | Change |
| --- | --- |
| `beepbeep-ai-alt-text-generator.php` | bump `BEEPBEEP_AI_DB_VERSION` to 1.1.0; `require_once` the audit classes |
| `includes/class-bbai-db.php` | `upgrade_to_1_1_0()` + register the audit table |
| `admin/class-bbai-admin-hooks.php` | register audit AJAX actions behind `bbai_audit_enabled` / `BBAI_DISABLE_AUDIT`, `delete_attachment` cleanup, and (stage 4) the audit REST controller + `attachment_updated` rescan hook |
| `phpcs.xml` | declare `manage_bbbbai_text` as a known custom capability |
| `admin/class-bbai-core.php` | add the `bbai-audit` submenu at `add_settings_page()` (line 1912 — the **live** copy, not the dead trait) + `bbai-audit → audit` in the page→tab map + include the partial. Routing only, ~15 lines |
| `admin/traits/trait-core-assets.php` | audit page→tab entry and conditional enqueue |
| `uninstall.php` | drop audit table, delete audit options and decorative meta |
| `readme.txt` / `CHANGELOG.md` | release notes at the end |

---

## 15. Implementation stages

Each stage ends with: run PHPUnit + node tests + `php -l` + scoped PHPCS, summarise changed files, restate remaining risks.

**Stage 1 — Data model + rules (pure, no UI). ✅ COMPLETE.**
Issue catalogue, `Image_Audit_Context`, rule interface + base + registry + 10 rules, score service, `Image_Audit_Repository::create_table()`, `DB_Schema` 1.1.0 migration, uninstall cleanup, plus shared vocabulary accessors on `BBAI_Alt_Quality_Scorer`. 81 new unit tests. No user-visible change: nothing is registered, rendered or scheduled, and the only runtime effect on an existing site is that `DB_Schema::maybe_upgrade()` creates one empty table.

**Stage 2 — Discovery, repository, batch processor, progress. ✅ COMPLETE.**
`Image_Discovery_Service`, run-scoped duplicate map, multi-row upserts, `Audit_Batch_Processor`, `Audit_Progress_Service` (option-backed state, `add_option` lock, resume, cancel, stale recovery), `Image_Audit_Service` orchestrator and `Audit_Ajax_Controller`. 104 further unit tests. Verified end to end against a synthetic 250-image library: 3 batches, descending cursor, lock held then released, exact expected counters, resume preserving run id and cursor. No UI yet — the AJAX actions exist but nothing calls them.

**Stage 3 — Dashboard summary + manual run + progress UI. ✅ COMPLETE.**
`bbai-audit` submenu and tab routing, `audit-tab.php` plus three components (score card with disclaimer, stat grid, run/progress controls), `audit.css`, `bbai-audit.js` polling controller. First-run, running, stale/resumable, empty, error and no-JS states all render server-side. Assets gated to `is_audit_tab()`. 20 new JS tests (8 markup contract, 12 controller behaviour). All five page states verified by rendering them.

**Stage 4 — Issues table. ✅ COMPLETE.**
`Audit_Filters` (8 filters), `query_issues()` and `count_by_filter()`, `audit-issues-table.php` + `audit-issue-badge.php`, `bbai-audit-issues.js`, `Image_Audit_Service::rescan_attachment()` and the `bbai_audit_rescan` action. 26 new PHPUnit tests, 12 new JS contract tests.

**Deviation: no REST controller.** The table is server-rendered with query-arg filters and pagination, matching how `library-tab.php` already works, so `class-audit-rest-controller.php` was not needed. The only new endpoint is `bbai_audit_rescan` on the existing AJAX controller; generation reuses `beepbeepai_regenerate_single` unchanged. Less new surface, one fewer permission model to get right.

**Deviation: `rescan_attachment()` landed here rather than in stage 5.** Without it the table would show stale findings the moment a user generated alt text from it, which would have been dishonest. Decorative marking, dismissal, telemetry and entitlements remain in stage 5 as planned.

**Stage 5 — Decorative, resolution, telemetry, entitlements. ✅ COMPLETE.**
`Decorative_Image_Service` (reversible, meta-backed, never automatic), dismissal via `Image_Audit_Repository::set_status()`, all 11 telemetry events with buckets and per-run de-duplication, `Audit_Entitlement_Service` with the four named capabilities and a query-enforced free-plan cap. Also replaced the Stage 2 upper-bound `count_weak_alt()` with a `COUNT(DISTINCT attachment_id)` query. 47 new PHPUnit tests, 12 new JS contract tests.

**Note on `site_url` in telemetry.** Audit events carry only buckets, enums and counts. The envelope they travel in also carries the plugin's pre-existing canonical identity properties (`site_url`, `host`, `site_install_id`, …) added by `BBAI_Telemetry::enrich_properties()` and asserted by `TelemetryCanonicalTest`. That is an existing plugin-wide decision, not something the audit introduced, and it was left alone rather than changed unilaterally. If the intent is that no audit event should carry the site URL, that is a change to the shared telemetry schema and should be decided separately.

---

## 16. Open questions / decisions needing sign-off

1. **Two scans.** Phase one ships the audit *alongside* the legacy coverage scan. Do we want a phase-two task to retire `get_alt_text_coverage_scan()` and have the dashboard read only from the audit table? (Recommended, but it touches the dashboard hero and several partials, so it is out of this phase.)
2. **Free-plan issue cap.** The plan assumes free sees the full score/counts but only the first 25 rows. Confirm this matches product strategy — it is a one-constant change.
3. **`nai-guest-audit.php` "WCAG risk" copy** contradicts the copy rules for this project (§2.5). Recommend a separate small change to reword; not touched here.
4. **PHPCS/composer config fix** (§12) — recommend landing separately so this feature's diff stays feature-only.
5. **Dead-code cleanup** — `trait-core-admin-ui.php` (1,042 lines, never used) and ~26 shadowed trait methods (§2.3.3, §2.4). Not touched by this work, but they are an active foot-gun for anyone editing `Core`. Recommend a separate cleanup change.
