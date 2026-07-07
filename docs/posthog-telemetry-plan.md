# PostHog Telemetry Plan

BeepBeep AI uses PostHog only for product analytics after the site owner opts in.
The default for a fresh WordPress.org install is no external PostHog capture.

## Consent Gate

- Option: `bbai_telemetry_consent`
- Enabled value: `yes`
- Default value: `no`
- Runtime helper: `BeepBeepAI\AltTextGenerator\BBAI_Telemetry::has_telemetry_consent()`
- Managed/test override constant: `BBAI_TELEMETRY_CONSENT`
- Override filter: `bbai_telemetry_consent_granted`

Local telemetry events may still be emitted to the in-site ring buffer for debugging.
External PostHog capture must check the consent gate.

## Capture Transport

Browser PostHog capture is disabled for WordPress admin pages. The browser still
emits allowlisted activity into the plugin's WordPress AJAX telemetry endpoint,
and PHP forwards those events to PostHog with `transport=wp_admin_server_bridge`.
This avoids customer-site browser ingestion failures while keeping the same
event names available in PostHog.

Every plugin-owned event must include `site_install_id`. This is the canonical
join key for download-to-activation-to-logged-out-to-account funnels because it
survives the transition from an anonymous WordPress admin visit to an
authenticated BeepBeep account. Use `site_hash` or legacy `site_id` only as
fallbacks when querying events captured before this contract existed.

For local or managed test installs, define this constant before loading the
plugin to force server-side capture on:

```php
define( 'BBAI_TELEMETRY_CONSENT', true );
```

Lifecycle events are server-side events. They are emitted from the WordPress
activation/deactivation path and queued in `bbai_telemetry_lifecycle_queue` so
they can be flushed on the next admin load when activation happens before admin
JavaScript is available. Do not rely on browser JavaScript for install,
activation, or update telemetry.

## Common Properties

Every plugin-owned event should include these properties when technically available:

- `site_install_id`, `site_id`, `site_hash`
- `site_url`, `site_host`, `host`
- `plugin_version`, `wp_version`, `php_version`
- `plan`, `plan_type`, `user_state`, `is_logged_in`
- `session_id` for browser/admin requests when available; `journey_id` always falls back to `site_install_id`
- `credits_remaining`
- `images_selected`, `images_generated`, `generation_mode`
- `is_first_open`, `is_first_generation`, `is_returning_user`
- `country`

Do not send ALT text, filenames, media URLs, page URLs, license keys, tokens, or full IP addresses.

`plan` and `plan_type` must use the normalized dashboard values:

- `free`
- `trial`
- `pro`
- `agency`
- `unknown`

Map `starter`, `growth`, and `enterprise` to `pro` for telemetry dashboards.
After `signup_succeeded` or `login_succeeded`, subsequent events for the same
site should resolve to `free`, `trial`, `pro`, or `agency` whenever account,
license, or usage state exists. Use `unknown` only when neither local account
state nor usage state is available.

## Canonical Event Contract

Legacy callers may still emit older names such as `guest_dashboard_viewed`, `upgrade_clicked`, `upgrade_completed`, `checkout_session_created`, `account_created`, `first_alt_generated`, `alt_generated_success`, `alt_generated_failed`, and `generation_failed`. The telemetry layer normalizes them before persistence and PostHog forwarding.

| Funnel | Canonical events | Purpose |
| --- | --- | --- |
| Discovery and lifecycle | `wporg_downloads_daily`, `wporg_stats_snapshot`, `plugin_activated`, `plugin_updated`, `plugin_deactivated` | Answer whether users find, activate, update, and keep the plugin. `plugin_installed` is not reliable from the WordPress plugin runtime. |
| Opening and navigation | `plugin_opened`, `dashboard_viewed`, `alt_library_viewed`, `analytics_viewed`, `usage_viewed`, `settings_viewed`, `first_run_completed` | Answer whether users open the product and reach the core surfaces. `dashboard_viewed` uses `user_state` to distinguish guest and signed-in views. |
| Activation and value | `generation_started`, `batch_generation_started`, `generation_completed`, `alt_generated`, `trial_generation_started`, `trial_generation_completed`, `image_regenerated`, `manual_alt_edit`, `review_queue_opened`, `review_completed` | Answer whether users reach value and continue improving images. |
| Structured failures | `generation_failed_timeout`, `generation_failed_api`, `generation_failed_auth`, `generation_failed_no_credits`, `generation_failed_invalid_image`, `generation_failed_rate_limit`, `generation_failed_network`, `generation_failed_unknown` | Explain why users fail. Include `error_code`, `response_time`, `provider`, and `retry_attempt`. |
| Quota and upgrade intent | `quota_exhausted_state_shown`, `generation_blocked_no_credits`, `batch_generation_started`, `batch_generation_completed`, `batch_generation_quota_limit_hit`, `batch_generation_partial_quota_stop`, `upgrade_cta_clicked`, `checkout_started`, `checkout_completed` | Answer where quota users stop and who starts checkout. |
| Signup and login | `signup_cta_clicked`, `signup_started`, `signup_succeeded`, `login_cta_clicked`, `login_modal_opened`, `login_submitted`, `login_succeeded`, `login_failed` | Answer who converts from anonymous/admin usage to an account. |
| Billing lifecycle | `trial_started`, `trial_expired`, `subscription_started`, `subscription_cancelled`, `credits_purchased` | Answer who pays, trials, renews, cancels, or buys credits. |
| Feature adoption | `feature_used` with `feature_name` | Answer which product areas are used. Preferred `feature_name` values: `dashboard`, `bulk_generation`, `single_generation`, `library`, `review`, `review_queue`, `settings`, `account`, `billing`, `statistics`, `woocommerce`, `login`, `signup`, `quota`. |
| Help and docs | `support_clicked`, `documentation_opened` | Answer where users seek help. |

## Lifecycle Semantics

- `plugin_installed`: not emitted by the WordPress plugin. WordPress.org install
  cannot be reliably distinguished from activation, upload, local install, or
  reactivation inside plugin runtime.
- `plugin_activated`: queued once per persisted WordPress installation using
  `bbai_telemetry_plugin_activated` as the duplicate marker.
- `plugin_updated`: queued only when a previous stored plugin version exists and
  differs from the current plugin version. Active-plugin updates are detected on
  `admin_init`; deactivated-plugin updates are detected if/when the plugin is
  activated again.

Version markers:

- Current version: `bbai_telemetry_current_plugin_version`
- Previous version: `bbai_telemetry_previous_plugin_version`
- Legacy/current compatibility marker: `bbai_telemetry_plugin_version`
- Activation marker: `bbai_telemetry_plugin_activated`
- Legacy install markers retained only for historical compatibility:
  `bbai_telemetry_plugin_installed`, `bbai_telemetry_plugin_installed_logged`

Lifecycle events must include `site_install_id`, `site_url`, `host`,
`plugin_version`, `plugin_plan`, `wp_version`, `wordpress_version`,
`php_version`, `environment`, `plan`, `user_state`, and `is_logged_in` when
WordPress has those values available.

## `plugin_opened` Semantics

`plugin_opened` means "this browser session opened the BeepBeep AI plugin admin".
It fires once per browser session, keyed by `session_id`, not once per page load.
Repeated refreshes, tab changes, AJAX reloads, or route changes in the same
browser session should not create another `plugin_opened`.

Page-level activity is measured by page view events such as `dashboard_viewed`,
`alt_library_viewed`, `settings_viewed`, and `usage_viewed`.

## Feature Name Rules

Every `feature_used` event must include `feature_name`. Allowed dashboard values:

- `dashboard`
- `library`
- `single_generation`
- `bulk_generation`
- `review`
- `review_queue`
- `settings`
- `statistics`
- `woocommerce`
- `billing`
- `account`
- `login`
- `signup`
- `quota`
Unknown or unapproved values must be rejected by the telemetry layer. Do not
send `feature_used` without a valid `feature_name`; the dashboard property health
card should treat omissions as instrumentation bugs.

## Current Health vs Historical Health

The Growth Command Center dashboard (1791626) includes paired cards:

| Card | Scope | Purpose |
| --- | --- | --- |
| Validation — Last Seen Canonical Events | 30-day historical | Long-term instrumentation gaps and alias normalization |
| Current Health — Validation (24h / v4.6.110+) | Last 24h OR `plugin_version >= 4.6.110` | Answers whether today's release is healthy |
| Telemetry Quality — Required Properties | 30-day historical | Property coverage across all recent traffic |
| Current Health — Required Properties (24h / v4.6.110+) | Last 24h OR `plugin_version >= 4.6.110` | Property coverage on current-release traffic only |

Use **Current Health** cards before release review. Use **Historical** cards for
taxonomy migration audits and long-window trend analysis.

## Telemetry State Labels

| State | Meaning | Color guidance |
| --- | --- | --- |
| Not instrumented | Canonical event never seen in lookback | Grey |
| No activity | Instrumented but zero for expected-quiet events (`checkout_completed`, `plugin_activated`, `plugin_updated`) | Grey |
| Tracked but zero | Instrumented but zero for active product metrics in window | Grey/amber |
| Property missing | Events present but required properties blank on current traffic | Amber/red |
| Tracked and active | Events and required properties healthy | Green |

## Checkout Funnel Coverage

Canonical upgrade journey:

1. `upgrade_cta_clicked`
2. `upgrade_modal_opened`
3. `checkout_started`
4. `checkout_completed`
5. `subscription_started`

The WordPress plugin can reliably emit `upgrade_cta_clicked`,
`upgrade_modal_opened`, and `checkout_started`. `checkout_completed` can only be
trusted when emitted after a backend-confirmed checkout success return or
backend/Stripe confirmation. `subscription_started` cannot be proven inside the
WordPress plugin alone; it should be emitted by the SaaS backend or webhook after
Stripe confirms a new subscription.

## Dashboard State Definitions

- `plugin_installed`: not reliable from the plugin runtime. Remove this as a
  success metric or show it as a non-instrumentable limitation.
- `plugin_activated`: instrumented, Tracked, and active after the first
  activation emits the event.
- `plugin_updated`: instrumented and Tracked, but can legitimately show zero
  when no update occurred during the selected range.
- `checkout_completed`: instrumented and Tracked, but can legitimately show zero
  when no successful payment occurred during the selected range.
- Missing properties such as `feature_name`, `site_install_id`, or normalized
  `plan` are property health failures, not "Not instrumented" states.

## Signup & Conversion HogQL Safety

PostHog dashboard cards must avoid raw date casts on nullable or mixed-type
properties. Prefer event timestamps (`timestamp`) for event windows and guard
property-derived dates before casting.

Safe patterns:

```sql
-- Event dates: timestamp is already typed.
toDate(timestamp) AS event_date
```

```sql
-- Nullable property date strings: cast through toString and nullIf first.
parseDateTimeBestEffortOrNull(nullIf(toString(properties.download_date), '')) AS download_at
```

```sql
-- Date difference: only compute when both sides parsed.
dateDiff('day', download_at, signup_at)
```

Cards should wrap nullable metrics with `coalesce(..., 0)` for counters and
return empty result sets or zero values instead of surfacing SQL/type errors.
Download-derived fields must be parsed with `parseDateTimeBestEffortOrNull`;
never call `toDate()` directly on a nullable JSON property.

For the Signup & Conversion cards currently failing in PostHog, replace the
direct `toDate(parseDateTime64BestEffortOrNull(if(has(events.properties_group_custom, ...`
pattern with a subquery that parses download dates once and filters nulls safely:

```sql
WITH signup_events AS (
    SELECT
        distinct_id,
        min(timestamp) AS signup_at
    FROM events
    WHERE event IN ('signup_succeeded', 'login_succeeded')
      AND timestamp >= now() - INTERVAL 1 DAY
    GROUP BY distinct_id
),
download_events AS (
    SELECT
        distinct_id,
        min(
            parseDateTimeBestEffortOrNull(
                nullIf(toString(properties['download_date']), '')
            )
        ) AS download_at
    FROM events
    WHERE event = 'wporg_downloads_daily'
    GROUP BY distinct_id
)
SELECT
    countIf(signup_at IS NOT NULL) AS conversions,
    countIf(download_at IS NOT NULL) AS attributed_downloads,
    round(
        conversions / greatest(attributed_downloads, 1),
        4
    ) AS conversion_rate
FROM signup_events
LEFT JOIN download_events USING distinct_id
WHERE download_at IS NULL OR signup_at >= download_at
```

For 7-day, 30-day, and 90-day cards, change only the interval. If a card needs a
daily trend, group by `toDate(signup_at)` after `signup_at` is already selected
from the typed `timestamp` column.

## Dashboard Metric Map

See also [`saas-analytics.md`](saas-analytics.md) for billing events, webhook architecture, identity flow, and alert HogQL.

- Executive Summary: downloads, real active sites, returning sites, generating sites, signed-up users, paid users, MRR/ARR, trial users, conversion rate, growth rate, health score, and one evidence-backed summary.
- Acquisition Funnel: `wporg_downloads_daily` -> `plugin_opened` -> `dashboard_viewed` -> `generation_started` -> `batch_generation_started` -> `generation_completed` -> `batch_generation_completed` -> `signup_cta_clicked` -> `signup_started` -> `signup_succeeded` -> `checkout_started` -> `checkout_completed` -> `subscription_started`.
- Time To Value: median time from download to open, open to first generation, generation to signup, and signup to paid.
- Generation Funnel: started, completed, structured failures, completion rate, average images per generation, and processing time.
- Failure Dashboard: top structured failure events, affected sites, affected plans, retry count, and trend.
- Quota Dashboard: quota reached, credits exhausted, generation blocked, upgrade CTA shown/clicked, checkout started, paid conversion.
- Retention: one-time, returning, regular, loyal sites, active days, weekly cohorts, monthly cohorts.
- Feature Adoption: top/least-used `feature_name`, growth, and stickiness.
- Telemetry Validation: one section with columns `Canonical Event`, `Last Seen`, `Status`, `Properties Present`, `Example Site`, and `Expected Frequency`.
- Telemetry Health: use grey for Not instrumented and No activity, amber for Tracked but zero or Property missing, and green for Healthy. Never show red solely because an event is not instrumented or because checkout_completed/plugin_activated are quiet. Lifecycle events are instrumented through the server bridge; treat `plugin_activated` and `plugin_updated` as tracked events, with `plugin_updated` allowed to be zero when no update occurred. Treat `plugin_installed` as not reliable from the plugin runtime.
- Current Health cards: filter to last 24 hours OR `plugin_version >= 4.6.110` to answer release health without historical alias gaps.

## 4.6.114 Trigger Map

| Event | File | Function / selector |
| --- | --- | --- |
| `batch_generation_started` | `assets/js/bbai-admin.js` | `startInlineGeneration()` after `generation_started` |
| `batch_generation_started` | `assets/js/nai-dashboard/generation.js` | `emitGenerationStarted()` when bulk count > 1 |
| `upgrade_cta_clicked` | `assets/js/nai-dashboard/events.js` | `[data-nai-open-paywall]` via `trackUpgradeCtaClicked()` |
| `upgrade_cta_clicked` | `assets/js/bbai-telemetry.js` | `bindUpgradeUi()` delegated fallback |
| `checkout_started` | `assets/js/bbai-dashboard.js` | `openCheckoutUrl()` immediately before Stripe redirect |
| `checkout_started` | `assets/js/upgrade-modal.js` | `[data-action="checkout-plan"]` capture listener |
| `signup_started` | `assets/js/auth-modal.js` | `handleRegister()` before AJAX |
| `login_succeeded` | `assets/js/auth-modal.js` | `handleLogin()` on success + flush |

## Data That Must Not Be Sent

- ALT text contents.
- Image contents.
- Filenames, media URLs, post titles, or page URLs.
- Email addresses unless the user is explicitly authenticated and the event is covered by account terms.
- Raw license keys, API tokens, JWTs, payment identifiers, or full IP addresses.

## WordPress.org Aggregate Import

Run the importer from an operator machine, CI job, or backend cron:

```bash
node scripts/import-wporg-stats-to-posthog.mjs --dry-run
```

To send events:

```bash
POSTHOG_PROJECT_API_KEY=phc_xxx \
node scripts/import-wporg-stats-to-posthog.mjs
```

Optional arguments:

```bash
node scripts/import-wporg-stats-to-posthog.mjs --slug=beepbeep-ai-alt-text-generator --limit=30
```

The importer must not be scheduled from customer WordPress sites.
