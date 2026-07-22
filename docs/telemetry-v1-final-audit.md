# Telemetry V1 Final Audit

## 2026-07-16 Release Freeze Addendum

This pass did not change the live plugin UI. Changes were limited to telemetry
normalization, PostHog bridge behavior, one canonical value-delivery event, and
dashboard validation metadata.

### Duplicate events removed

- `guest_dashboard_viewed` remains a legacy input only. New and normalized events
  use `dashboard_viewed` with `user_state = guest` or `user_state = signed_in`.
- Upgrade aliases still normalize centrally:
  `upgrade_clicked` / `upgrade_started` -> `upgrade_cta_clicked`.
- Checkout aliases still normalize centrally:
  `checkout_session_created` -> `checkout_started`,
  `upgrade_completed` -> `checkout_completed`.
- Signup aliases still normalize centrally:
  `account_created` -> `signup_succeeded`.
- Failure aliases still normalize centrally:
  `generation_failed` -> `generation_failed_*` variants and
  `alt_generated_failed` -> `generation_failed_unknown`.
- Legacy `alt_generated` is retained for continuity. New persisted per-image
  value delivery also emits canonical `alt_text_generated`.

### `plugin_opened` behavior

`plugin_opened` means once per browser session, not every AJAX render. The client
stores `bbai_plugin_opened_sent:<session_id>` in `sessionStorage`, emits
`plugin_opened_semantics = once_per_browser_session`, and attaches `page_view_id`
for page-level reconstruction. `dashboard_viewed` remains the page-entry event.

### Standardized properties

All plugin-owned events that pass through the central JS or PHP telemetry layer
now get these common fields where available:

- `site_install_id`, `site_url`, `host`
- `plugin_version`, `app_version`
- `plan`, `user_state`
- `is_first_generation`, `is_returning_user`
- `wp_version`, `php_version`
- `credits_remaining`
- `generation_mode`
- `feature_name` for `feature_used`
- `session_id` or `journey_id`
- `environment`, `is_internal`
- `event_schema_version` plus legacy `telemetry_version`
- `$insert_id` for PostHog de-duplication

### Feature events

`feature_used.feature_name` is restricted to:

- `dashboard`
- `library`
- `single_generation`
- `bulk_generation`
- `review`
- `settings`
- `statistics`
- `woocommerce`
- `billing`
- `account`

Legacy feature aliases are mapped into that set. Signup/login map to `account`,
quota maps to `billing`, and review queue maps to `review`. Unknown values are
rejected rather than added to the v1 schema.

### Generation mode

`generation_mode` is restricted to `single`, `bulk`, `regeneration`,
`automatic`, or `unknown`. The legacy `generate-missing` value is normalized from
actual item count: one item becomes `single`, multiple items become `bulk`.

### Privacy and recording

The telemetry scrubber drops `email`, `license_key`, passwords, tokens, API keys,
raw responses, prompts, alt text, filenames, and image URLs before local
persistence or PostHog capture. PostHog session recording now stays disabled
unless config explicitly sets `sessionRecordingEnabled = true`.

### Live PostHog validation

Updated insight `9888113` / `jgTGmBgR` on dashboard `1791626`:

- Removed the separate legacy `guest_dashboard_viewed` validation row.
- Added canonical `alt_text_generated`.
- Uses only requested statuses: `Not instrumented`, `Tracked but zero`,
  `Property missing`, `Tracked and active`.
- Shows `canonical_event`, `last_seen`, `status`, `properties_present`,
  `example_site`, `expected_frequency`, `events_30d`, and `sites_30d`.

### Release gaps

- `checkout_completed` and `subscription_activated` are only authoritative after
  backend or Stripe confirmation; the WordPress plugin should not fake them from
  checkout intent.
- Server lifecycle events may not have `session_id` before the browser creates a
  telemetry session, but they do include `journey_id`.
- Live PostHog will continue to show historical `Property missing` until new
  plugin events with `event_schema_version` and the cleaned common properties are
  ingested.

## Scope

This audit freezes the WordPress plugin telemetry schema for v1. It is limited to
telemetry code, schema normalization, and dashboard documentation. It does not
change the live plugin UI and does not deploy or push to WordPress.org SVN.

## Duplicate Events Removed

- `guest_dashboard_viewed` is no longer a canonical event. New dashboard views use
  `dashboard_viewed` with `user_state = "guest"` or `user_state = "signed_in"`.
- Legacy `guest_dashboard_viewed` callers are normalized to `dashboard_viewed`
  before local persistence and PostHog forwarding.
- Legacy conversion and upgrade aliases are normalized centrally:
  `account_created` -> `signup_succeeded`,
  `upgrade_clicked` / `upgrade_started` -> `upgrade_cta_clicked`,
  `checkout_session_created` -> `checkout_started`,
  `upgrade_completed` -> `checkout_completed`,
  `alt_generated_success` -> `generation_completed`,
  `alt_generated_failed` -> `generation_failed_unknown`.

## Property Names Standardized

Major events are enriched centrally with:

- `site_install_id`
- `site_url`
- `host`
- `plugin_version`
- `wp_version`
- `php_version`
- `plan`
- `plan_type`
- `user_state`
- `is_logged_in`
- `is_returning_user`
- `is_first_generation`
- `credits_remaining` when usage is available
- `session_id` when browser/admin session state is available
- `journey_id`, falling back to `site_install_id`
- `generation_mode` for generation events
- `feature_name` for `feature_used`

Canonical `user_state` values are `guest` and `signed_in`.

Canonical `plan` values are `free`, `trial`, `pro`, `agency`, and `unknown`.
`starter`, `growth`, and `enterprise` are mapped to `pro`.

## Feature Events

`feature_used` accepts only:

- `dashboard`
- `library`
- `single_generation`
- `bulk_generation`
- `review`
- `settings`
- `statistics`
- `woocommerce`
- `billing`
- `account`

Unknown or missing feature names are rejected by the telemetry layer rather than
being sent as a new long-term schema value.

## Checkout Funnel

Expected journey:

1. `upgrade_cta_clicked`
2. `upgrade_modal_opened`
3. `checkout_started`
4. `checkout_completed`
5. `subscription_started`

The WordPress plugin can reliably emit the first three steps. `checkout_completed`
requires a confirmed checkout return/backend signal. `subscription_started`
should be emitted by the SaaS backend or Stripe webhook after Stripe confirms the
new subscription; the WordPress plugin cannot prove it locally.

## Events Missing Required Properties

Known residual risks:

- Legacy browser bundles or cached admin pages may emit old event names until the
  latest plugin assets are loaded, but the PHP telemetry layer normalizes known
  aliases before forwarding.
- Server-side events fired before a browser session exists may not have
  `session_id`; they still include `journey_id`.
- Some low-priority diagnostic events such as debug, retention, banner, or
  navigation telemetry may not include all generation-specific properties because
  they are not generation events.

## Dashboard Cards Updated

No checked-in PostHog dashboard/card definitions or HogQL files were found in
this repository. The repo-side update documents the corrected dashboard patterns
in `docs/posthog-telemetry-plan.md`.

## Dashboard Cards Requiring Live Production Data

The screenshot shows the live PostHog "Signup & Conversion" cards failing with
raw HogQL type errors. These cards must be fixed inside PostHog because their
definitions are not checked into this repository.

Use these rules for the live cards:

- Use typed `timestamp` for event dates.
- Parse nullable property dates with `parseDateTimeBestEffortOrNull(nullIf(toString(...), ''))`.
- Never call `toDate()` directly on nullable JSON/property values.
- Return `0` or an empty state with `coalesce`/safe joins instead of surfacing SQL
  errors.
- Treat `account_created` as a legacy alias only; dashboards should query
  `signup_succeeded` and `login_succeeded`.

## Live PostHog Signup & Conversion Card Fixes

No checked-in PostHog dashboard/card definitions or HogQL files were found in
this repository. These fixes must be applied manually in the live PostHog
dashboard cards.

Use the canonical `signup_succeeded` and `login_succeeded` events for these
cards. Do not include `account_created` in the live replacement queries unless
you are intentionally repairing a legacy-only historical card; including both
`account_created` and `signup_succeeded` can double-count the same normalized
signup.

### Signup & Conversion — 1 Day

Card name: `Signup & Conversion — 1 Day`

Exact query:

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
                nullIf(nullIf(toString(properties['download_date']), ''), 'null')
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

What it measures: distinct IDs with a canonical signup or login success in the
last 1 day, plus the subset that can be safely attributed to a parsed WordPress
download date.

Expected empty-state behaviour: returns one row with `conversions = 0`,
`attributed_downloads = 0`, and `conversion_rate = 0` instead of a HogQL date
parsing/type error.

How to validate after pasting into PostHog: run the card for the 1 day period,
confirm no raw SQL error appears, confirm the result columns are present, then
click into matching events and verify any counted conversion uses
`signup_succeeded` or `login_succeeded`.

### Signup & Conversion — 1 Week

Card name: `Signup & Conversion — 1 Week`

Exact query:

```sql
WITH signup_events AS (
    SELECT
        distinct_id,
        min(timestamp) AS signup_at
    FROM events
    WHERE event IN ('signup_succeeded', 'login_succeeded')
      AND timestamp >= now() - INTERVAL 7 DAY
    GROUP BY distinct_id
),
download_events AS (
    SELECT
        distinct_id,
        min(
            parseDateTimeBestEffortOrNull(
                nullIf(nullIf(toString(properties['download_date']), ''), 'null')
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

What it measures: distinct IDs with a canonical signup or login success in the
last 7 days, plus the subset that can be safely attributed to a parsed WordPress
download date.

Expected empty-state behaviour: returns one row with `conversions = 0`,
`attributed_downloads = 0`, and `conversion_rate = 0` instead of a HogQL date
parsing/type error.

How to validate after pasting into PostHog: run the card for the 1 week period,
confirm no raw SQL error appears, compare the conversion count with a trends
query for `signup_succeeded` plus `login_succeeded`, and confirm no
`account_created` alias is included in the query.

### Signup & Conversion — 1 Month

Card name: `Signup & Conversion — 1 Month`

Exact query:

```sql
WITH signup_events AS (
    SELECT
        distinct_id,
        min(timestamp) AS signup_at
    FROM events
    WHERE event IN ('signup_succeeded', 'login_succeeded')
      AND timestamp >= now() - INTERVAL 30 DAY
    GROUP BY distinct_id
),
download_events AS (
    SELECT
        distinct_id,
        min(
            parseDateTimeBestEffortOrNull(
                nullIf(nullIf(toString(properties['download_date']), ''), 'null')
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

What it measures: distinct IDs with a canonical signup or login success in the
last 30 days, plus the subset that can be safely attributed to a parsed WordPress
download date.

Expected empty-state behaviour: returns one row with `conversions = 0`,
`attributed_downloads = 0`, and `conversion_rate = 0` instead of a HogQL date
parsing/type error.

How to validate after pasting into PostHog: run the card for the 1 month period,
confirm no raw SQL error appears, verify nullable or missing `download_date`
values do not break the card, and spot-check counted users against
`signup_succeeded` or `login_succeeded` event samples.

### Signup & Conversion — 1 Quarter

Card name: `Signup & Conversion — 1 Quarter`

Exact query:

```sql
WITH signup_events AS (
    SELECT
        distinct_id,
        min(timestamp) AS signup_at
    FROM events
    WHERE event IN ('signup_succeeded', 'login_succeeded')
      AND timestamp >= now() - INTERVAL 90 DAY
    GROUP BY distinct_id
),
download_events AS (
    SELECT
        distinct_id,
        min(
            parseDateTimeBestEffortOrNull(
                nullIf(nullIf(toString(properties['download_date']), ''), 'null')
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

What it measures: distinct IDs with a canonical signup or login success in the
last 90 days, plus the subset that can be safely attributed to a parsed WordPress
download date.

Expected empty-state behaviour: returns one row with `conversions = 0`,
`attributed_downloads = 0`, and `conversion_rate = 0` instead of a HogQL date
parsing/type error.

How to validate after pasting into PostHog: run the card for the 1 quarter
period, confirm no raw SQL error appears, verify the interval line uses
`INTERVAL 90 DAY`, and confirm the card does not include `account_created` unless
you are deliberately reviewing a legacy-only historical data range.

## Validation Dashboard

Create one "Telemetry Validation" section with:

- `Canonical Event`
- `Last Seen`
- `Status`
- `Properties Present`
- `Example Site`
- `Expected Frequency`

Statuses:

- Green: `Tracked and active`
- Amber: `Tracked but zero`
- Grey: `Not instrumented`
- Amber: `Property missing`

Never show red solely because an event is not instrumented.

## WordPress-Limited Gaps

- WordPress can identify checkout intent, but it cannot independently verify
  Stripe payment success without a backend-confirmed return or webhook.
- WordPress cannot authoritatively emit `subscription_started`; this belongs in
  the SaaS billing backend or Stripe webhook processor.
- Live PostHog dashboard cards cannot be fixed from this repo unless dashboard
  definitions are exported and checked in.
