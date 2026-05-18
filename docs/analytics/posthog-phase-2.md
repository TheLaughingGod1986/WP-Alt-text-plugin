# PostHog Phase 2 Analytics Setup

This document turns the BeepBeep AI / nAi WordPress plugin event stream into operational PostHog dashboards, cohorts, alerts, and replay workflows.

Scope:
- Use canonical events only in dashboards and alerts.
- Keep historical events for auditability. Hide legacy events from new dashboards instead of deleting them.
- Do not send or display raw email, license keys, API keys, JWTs, nonces, tokens, site URLs, ALT text, filenames, or other secrets.

## Access Prerequisites

Automatic setup requires:
- PostHog project URL, normally `https://us.posthog.com/project/<project_id>` or `https://app.posthog.com/project/<project_id>`.
- A PostHog personal API key with permission to read events and create dashboards, insights, cohorts, and subscriptions/alerts.
- The project ID for the plugin PostHog project.

If using the API, store credentials outside the repo:

```bash
export POSTHOG_HOST=https://us.posthog.com
export POSTHOG_PROJECT_ID=replace_me
export POSTHOG_PERSONAL_API_KEY=replace_me
```

## Phase 1: Audit Existing PostHog

### Live Event Audit Query

Use PostHog SQL or the query API to list events seen in the last 14 days:

```sql
SELECT
  event,
  count() AS event_count,
  uniqExact(distinct_id) AS unique_people,
  min(timestamp) AS first_seen,
  max(timestamp) AS last_seen
FROM events
WHERE timestamp >= now() - INTERVAL 14 DAY
GROUP BY event
ORDER BY event_count DESC
```

### Canonical Presence Check

Run:

```sql
SELECT
  event,
  count() AS event_count,
  uniqExact(distinct_id) AS unique_people
FROM events
WHERE timestamp >= now() - INTERVAL 14 DAY
  AND event IN (
    'plugin_opened',
    'dashboard_viewed',
    'generation_cta_clicked',
    'generation_request_started',
    'generation_job_created',
    'generation_progress_updated',
    'generation_completed',
    'generation_failed',
    'generation_stuck',
    'generation_recovered',
    'generation_backgrounded',
    'generation_resumed',
    'alt_library_viewed',
    'review_started',
    'upgrade_clicked',
    'upgrade_modal_opened',
    'checkout_started',
    'checkout_failed',
    'payment_succeeded'
  )
GROUP BY event
ORDER BY event
```

Expected result: all canonical events should appear after enough production traffic has passed through the updated plugin. `generation_recovered`, `generation_backgrounded`, `generation_resumed`, `checkout_failed`, and `payment_succeeded` may be sparse.

### Legacy Event Mapping

| Legacy event | Canonical event | Action |
| --- | --- | --- |
| `generate_cta_clicked` | `generation_cta_clicked` | Hide from new dashboards. Keep historical data. |
| `generation_start_failed` | `generation_failed` | Hide from new dashboards. Keep historical data. |
| `generation_batch_progress` | `generation_progress_updated` | Hide from new dashboards. Keep historical data. |
| `bulk_generation_started` | `batch_generation_started` | Use canonical generation funnel events for dashboards; keep as diagnostic event if already present. |
| `bulk_generation_completed` | `batch_generation_completed` | Prefer `generation_completed` in dashboards. |
| `bulk_generation_stalled_fallback_shown` | `generation_stuck` | Hide from new dashboards. |
| `bulk_generation_minimized` | `generation_backgrounded` | Hide from new dashboards. |
| `alt_generation` | `feature_used` or `generation_completed` depending on old payload | Hide from new dashboards; inspect before using in migration reports. |
| `alt_generated` | `generation_completed` | Hide from new dashboards. |
| `review_alt_text_clicked` | `review_started` | Hide from new dashboards. |
| `alt_library_regenerate_clicked` | `alt_regenerated` | Hide from new dashboards. |
| `alt_library_copy_clicked` | `alt_copied` | Hide from new dashboards. |
| `alt_library_edit_started` | `alt_edited_manually` | Hide from new dashboards. |
| `alt_library_edit_saved` | `review_saved` | Hide from new dashboards. |
| `upgrade_completed` | `payment_succeeded` | Hide from new dashboards. |
| `feature_used` | No direct replacement | Keep only for exploratory usage analysis; do not use for core funnels. |

Recommended cleanup:
- Mark legacy events as hidden in PostHog data management where supported.
- Do not delete any event definitions.
- Keep one temporary "Legacy Event Migration" insight for 30 days to confirm legacy event volume decays after plugin updates.

## Dashboard 1: Activation Funnel

Name: `BBAI - Activation Funnel`

Purpose: Show where new users drop before first successful generation.

Default filters:
- Date range: last 30 days.
- Exclude internal/dev traffic if an `environment` property exists: `environment = production`.
- Breakdown variants: `plugin_version`, `user_state` or `logged_in_state`, `plan` or `plan_type`, `source` or `source_page`, `browser`.

Insights:

1. `Activation funnel: plugin opened to completed generation`
   - Type: Funnel.
   - Steps:
     1. `plugin_opened`
     2. `dashboard_viewed`
     3. `generation_cta_clicked`
     4. `generation_request_started`
     5. `generation_completed`
   - Conversion window: 24 hours.
   - Breakdown: `plan_type`, then duplicate with `plugin_version` if needed.

2. `New signups per day`
   - Type: Trends.
   - Event: `signup_succeeded`.
   - Interval: day.
   - Display: line or bar.

3. `First generation completions per day`
   - Type: Trends or SQL.
   - Event: `generation_completed`.
   - Filter: first time for user/person where available. If first-time filter is unavailable, use SQL:

```sql
SELECT toDate(first_completed_at) AS day, count() AS first_generation_completions
FROM (
  SELECT distinct_id, min(timestamp) AS first_completed_at
  FROM events
  WHERE event = 'generation_completed'
  GROUP BY distinct_id
)
GROUP BY day
ORDER BY day
```

4. `Signed-up users with no generation within 24h`
   - Type: SQL table.

```sql
WITH signups AS (
  SELECT distinct_id, min(timestamp) AS signup_at
  FROM events
  WHERE event = 'signup_succeeded'
  GROUP BY distinct_id
),
gens AS (
  SELECT distinct_id, min(timestamp) AS generated_at
  FROM events
  WHERE event = 'generation_completed'
  GROUP BY distinct_id
)
SELECT
  signups.distinct_id,
  signups.signup_at,
  gens.generated_at,
  dateDiff('hour', signups.signup_at, now()) AS hours_since_signup
FROM signups
LEFT JOIN gens ON signups.distinct_id = gens.distinct_id
WHERE (gens.generated_at IS NULL OR gens.generated_at > signups.signup_at + INTERVAL 24 HOUR)
  AND signups.signup_at < now() - INTERVAL 24 HOUR
ORDER BY signups.signup_at DESC
LIMIT 200
```

5. `Replay: dashboard viewed, no generation CTA`
   - Type: Session replay saved filter.
   - Include event: `dashboard_viewed`.
   - Exclude event: `generation_cta_clicked`.
   - Date range: last 14 days.

## Dashboard 2: Generation Reliability

Name: `BBAI - Generation Reliability`

Purpose: Expose bugs in generation lifecycle.

Insights:

1. `Generation completed trend`
   - Type: Trends.
   - Event: `generation_completed`.
   - Interval: day.

2. `Generation failed trend`
   - Type: Trends.
   - Event: `generation_failed`.
   - Interval: day.
   - Breakdown: `error_code`.

3. `Generation stuck trend`
   - Type: Trends.
   - Event: `generation_stuck`.
   - Interval: day.
   - Breakdown: `source` or `current_screen`.

4. `Polling failed trend`
   - Type: Trends.
   - Event: `polling_failed`.
   - Interval: day.
   - Breakdown: `endpoint`.

5. `UI loading timeout trend`
   - Type: Trends.
   - Event: `ui_loading_timeout`.
   - Interval: day.
   - Breakdown: `loading_target`.

6. `Generation reliability funnel`
   - Type: Funnel.
   - Steps:
     1. `generation_cta_clicked`
     2. `generation_request_started`
     3. `generation_job_created`
     4. `generation_completed`
   - Conversion window: 2 hours.
   - Breakdown: `plugin_version`.

7. `Top generation error codes`
   - Type: SQL table.

```sql
SELECT
  properties.error_code AS error_code,
  properties.endpoint AS endpoint,
  count() AS events,
  uniqExact(distinct_id) AS users
FROM events
WHERE timestamp >= now() - INTERVAL 30 DAY
  AND event IN ('generation_failed', 'generation_stuck', 'polling_failed', 'ajax_request_failed')
GROUP BY error_code, endpoint
ORDER BY events DESC
LIMIT 50
```

8. `Replay: generation stuck or failed`
   - Type: Session replay saved filter.
   - Include any event: `generation_stuck`, `generation_failed`, `polling_failed`.

## Dashboard 3: Upgrade Intent

Name: `BBAI - Upgrade Intent`

Purpose: Understand monetisation signals and conversion friction.

Insights:

1. `Quota to payment funnel`
   - Type: Funnel.
   - Steps:
     1. `out_of_credits_banner_shown`
     2. `upgrade_modal_opened`
     3. `upgrade_clicked`
     4. `checkout_started`
     5. `payment_succeeded`
   - Conversion window: 7 days.
   - Breakdown: `current_plan`, `target_plan`, `trigger_location`.

2. `Upgrade clicked trend`
   - Type: Trends.
   - Event: `upgrade_clicked`.
   - Breakdown: `trigger_location`.

3. `Checkout started trend`
   - Type: Trends.
   - Event: `checkout_started`.
   - Breakdown: `target_plan`.

4. `Checkout failed trend`
   - Type: Trends.
   - Event: `checkout_failed`.
   - Breakdown: `error_code`.

5. `Users who hit quota and clicked upgrade`
   - Type: SQL table.

```sql
WITH quota AS (
  SELECT distinct_id, min(timestamp) AS quota_at
  FROM events
  WHERE event IN ('out_of_credits_banner_shown', 'quota_blocked', 'batch_generation_quota_limit_hit')
  GROUP BY distinct_id
),
upgrade AS (
  SELECT distinct_id, min(timestamp) AS upgrade_at, count() AS upgrade_clicks
  FROM events
  WHERE event = 'upgrade_clicked'
  GROUP BY distinct_id
)
SELECT
  quota.distinct_id,
  quota.quota_at,
  upgrade.upgrade_at,
  upgrade.upgrade_clicks
FROM quota
INNER JOIN upgrade ON quota.distinct_id = upgrade.distinct_id
WHERE upgrade.upgrade_at >= quota.quota_at
ORDER BY upgrade.upgrade_at DESC
LIMIT 200
```

6. `Replay: repeated upgrade clicks`
   - Type: Session replay saved filter or SQL-backed person list.
   - Include event: `upgrade_clicked`.
   - Filter users with `upgrade_clicked` count >= 2 in 24 hours where supported.

## Dashboard 4: UX Friction

Name: `BBAI - UX Friction`

Purpose: Find confusing or broken UI paths.

Insights:

1. `Dead clicks trend`
   - Type: Trends.
   - Event: `dead_click_detected`.
   - Breakdown: `current_screen`.

2. `Rage clicks trend`
   - Type: Trends.
   - Event: `rage_click_detected`.
   - Breakdown: `current_screen`.

3. `Generation click no-op trend`
   - Type: Trends.
   - Event: `generation_click_noop`.
   - Breakdown: `reason`.

4. `AJAX request failed trend`
   - Type: Trends.
   - Event: `ajax_request_failed`.
   - Breakdown: `endpoint`, `http_status`.

5. `Frontend errors trend`
   - Type: Trends.
   - Event: `frontend_error_captured`.
   - Breakdown: `error_message` or `error_code`.

6. `Events by screen`
   - Type: SQL table.

```sql
SELECT
  coalesce(nullIf(properties.current_screen, ''), nullIf(properties.page, ''), 'unknown') AS screen,
  event,
  count() AS events,
  uniqExact(distinct_id) AS users
FROM events
WHERE timestamp >= now() - INTERVAL 14 DAY
  AND event IN (
    'dead_click_detected',
    'rage_click_detected',
    'generation_click_noop',
    'ajax_request_failed',
    'frontend_error_captured',
    'ui_loading_timeout'
  )
GROUP BY screen, event
ORDER BY events DESC
LIMIT 100
```

7. `Replay: rage or dead clicks`
   - Type: Session replay saved filter.
   - Include any event: `rage_click_detected`, `dead_click_detected`.

## Dashboard 5: Retention

Name: `BBAI - Retention`

Purpose: Track whether users return after activation.

Insights:

1. `Retention: completed generation to plugin opened`
   - Type: Retention.
   - First event: `generation_completed`.
   - Returning event: `plugin_opened`.
   - Period: weekly.
   - Range: 8 weeks.

2. `Retention: completed generation to completed generation`
   - Type: Retention.
   - First event: `generation_completed`.
   - Returning event: `generation_completed`.
   - Period: weekly.
   - Range: 8 weeks.

3. `Cohort: generated once but did not return within 7 days`
   - See cohort definitions below.

4. `Cohort: generated 50+ credits`
   - See cohort definitions below.

5. `Cohort: returned after quota exhaustion`
   - See cohort definitions below.

6. `Active generating users by day/week`
   - Type: Trends.
   - Event: `generation_completed`.
   - Aggregation: unique users.
   - Duplicate insight: one daily, one weekly.

## Cohorts

Create these cohorts.

1. `BBAI - Signed up but no generation`
   - Criteria: `signup_succeeded` exists.
   - Exclusion: `generation_completed` within 24 hours after signup.
   - If UI cannot express the 24h exclusion, use the SQL from the Activation dashboard and save the resulting person list.

2. `BBAI - Generated once`
   - Criteria: `generation_completed` count equals 1.

3. `BBAI - Power free users`
   - Criteria:
     - `generation_completed` count >= 10, OR
     - event property `credits_used >= 50`.
   - Filter: `plan_type = free` or `current_plan = free`.

4. `BBAI - Upgrade intent users`
   - Criteria:
     - `upgrade_clicked` count >= 2, OR
     - `checkout_started` exists.

5. `BBAI - Stuck generation users`
   - Criteria:
     - `generation_stuck` exists, OR
     - `generation_click_noop` exists, OR
     - `ui_loading_timeout` exists.

6. `BBAI - Returned users`
   - Criteria:
     - `plugin_opened` or `generation_completed` on 2+ distinct days.
   - SQL helper:

```sql
SELECT distinct_id
FROM events
WHERE event IN ('plugin_opened', 'generation_completed')
GROUP BY distinct_id
HAVING uniqExact(toDate(timestamp)) >= 2
```

## Alerts

Create alerts where the PostHog project supports insight subscriptions or monitor-style alerts. If alerting is not enabled, create the insight and subscribe the product/engineering channel to daily or hourly email/Slack summaries.

1. `BBAI Alert - generation_failed up 50% DoD`
   - Insight: `generation_failed` trend.
   - Compare previous day.
   - Trigger: current 24h count >= 1.5x previous 24h count and current count >= 5.

2. `BBAI Alert - generation_stuck > 3 in 24h`
   - Insight: `generation_stuck` trend.
   - Trigger: count greater than 3 in last 24 hours.

3. `BBAI Alert - checkout_started zero while upgrade_clicked exists`
   - Insight: SQL.

```sql
SELECT
  countIf(event = 'upgrade_clicked') AS upgrade_clicks,
  countIf(event = 'checkout_started') AS checkout_starts
FROM events
WHERE timestamp >= now() - INTERVAL 7 DAY
```

   - Trigger: `upgrade_clicks > 0 AND checkout_starts = 0`.

4. `BBAI Alert - generation_completed down 50% WoW`
   - Insight: `generation_completed` trend.
   - Compare previous week.
   - Trigger: current 7d count <= 0.5x previous 7d count.

5. `BBAI Alert - frontend_error_captured > 5 in 24h`
   - Insight: `frontend_error_captured` trend.
   - Trigger: count greater than 5 in 24 hours.

6. `BBAI Alert - rage_click_detected > 5 in 24h`
   - Insight: `rage_click_detected` trend.
   - Trigger: count greater than 5 in 24 hours.

## Replay Saved Filters

Create saved replay filters.

1. `BBAI Replay - Generation failed or stuck`
   - Include any event: `generation_failed`, `generation_stuck`, `polling_failed`.
   - Sort: newest first.

2. `BBAI Replay - Signed up but did not generate`
   - Include event: `signup_succeeded`.
   - Exclude event: `generation_completed`.
   - Date range: last 14 days.

3. `BBAI Replay - Upgrade intent`
   - Include any event: `upgrade_clicked`, `checkout_started`.

4. `BBAI Replay - Rage or dead clicks`
   - Include any event: `rage_click_detected`, `dead_click_detected`.

5. `BBAI Replay - Background/resume generation`
   - Include any event: `generation_backgrounded`, `generation_resumed`.

## Event Naming Guide

### Canonical Events

Core lifecycle:
- `plugin_opened`
- `dashboard_viewed`
- `alt_library_viewed`
- `analytics_viewed`
- `usage_viewed`
- `settings_viewed`

Generation:
- `generation_cta_clicked`
- `generation_request_started`
- `generation_job_created`
- `generation_progress_updated`
- `generation_backgrounded`
- `generation_resumed`
- `generation_completed`
- `generation_failed`
- `generation_stuck`
- `generation_recovered`
- `generation_click_noop`

Review:
- `review_started`
- `review_saved`
- `alt_regenerated`
- `alt_copied`
- `alt_edited_manually`

Upgrade:
- `upgrade_banner_shown`
- `upgrade_modal_opened`
- `upgrade_clicked`
- `checkout_started`
- `checkout_failed`
- `payment_succeeded`

Friction:
- `dead_click_detected`
- `rage_click_detected`
- `ui_loading_timeout`
- `ajax_request_failed`
- `polling_failed`
- `quota_blocked`
- `auth_expired`
- `stale_generation_state`
- `frontend_error_captured`

### Deprecated Events

Do not use these in new dashboards:
- `generate_cta_clicked`
- `generation_start_failed`
- `generation_batch_progress`
- `bulk_generation_stalled_fallback_shown`
- `bulk_generation_minimized`
- `alt_generation`
- `alt_generated`
- `upgrade_completed`

### Diagnostic Events

These may remain visible for engineering drilldowns but should not anchor product funnels:
- `feature_used`
- `batch_generation_started`
- `batch_generation_completed`
- `bulk_generation_poll_started`
- `bulk_generation_first_item_completed`
- `batch_generation_quota_limit_hit`
- `batch_generation_partial_quota_stop`

## Manual PostHog Setup Steps

1. Open PostHog project.
2. Go to Data Management -> Events.
3. Search for deprecated events and mark them hidden if supported.
4. Create dashboards using the exact names above.
5. Add insights in each dashboard using the event names and SQL above.
6. Save cohorts using the names and criteria above.
7. Create replay saved filters using the names and event filters above.
8. Create alerts or subscriptions for each alert definition above.
9. Pin the five dashboards in this order:
   1. `BBAI - Activation Funnel`
   2. `BBAI - Generation Reliability`
   3. `BBAI - Upgrade Intent`
   4. `BBAI - UX Friction`
   5. `BBAI - Retention`

## How To Inspect A Failed Generation Recording

1. Open `BBAI - Generation Reliability`.
2. Open `Replay: generation stuck or failed`.
3. Filter by:
   - `generation_failed` or `generation_stuck`.
   - `plugin_version` equal to the affected release.
   - `error_code` if known.
4. Open a recording.
5. Confirm these moments:
   - `generation_cta_clicked`.
   - Loading state appears.
   - Modal opens or should have opened.
   - `generation_request_started`.
   - `generation_job_created` or fallback path.
   - `generation_progress_updated` or absence of progress.
   - `generation_failed` or `generation_stuck`.
6. Check console/network capture in replay. Do not copy secrets into tickets.
7. File the bug with:
   - PostHog recording link.
   - Event timeline.
   - `plugin_version`.
   - `error_code`.
   - `endpoint`.
   - `current_screen`.
   - Browser and viewport.

## How To Inspect Signed-Up-But-No-Generation Users

1. Open `BBAI - Activation Funnel`.
2. Open `Signed-up users with no generation within 24h`.
3. Pick users with recent signup timestamps and no `generation_completed`.
4. Open their person timeline.
5. Check whether they:
   - Saw `dashboard_viewed`.
   - Clicked `generation_cta_clicked`.
   - Hit `generation_click_noop`, `auth_expired`, `quota_blocked`, or `ui_loading_timeout`.
6. If a replay exists, inspect the saved filter `BBAI Replay - Signed up but did not generate`.
7. Segment by `plugin_version`, `logged_in_state`, `plan_type`, and `browser`.

## Taxonomy Watchlist

Watch for these issues after rollout:
- Both `generate_cta_clicked` and `generation_cta_clicked` appearing in new traffic. New dashboards should use only `generation_cta_clicked`.
- Missing `generation_job_created` in licensed bulk runs. If the backend cannot return a job ID, use `generation_stuck` with `error_code = missing_job_id`.
- `payment_succeeded` volume may lag if checkout return URLs are not reliably visited.
- `generation_recovered` requires explicit recovery paths; absence is acceptable until recovery UX emits it.
- `generation_backgrounded` and `generation_resumed` are only meaningful for active licensed bulk jobs with persisted job state.

## Recommended Next Improvements

1. Add a server-side `payment_succeeded` confirmation from Stripe webhook if available, so successful payments do not depend only on checkout return.
2. Add explicit `generation_recovered` when polling resumes after a stuck state and later completes.
3. Add `first_generation_completed` server-side or client-side once-per-person event to simplify activation dashboards.
4. Add `quota_remaining_bucket` property for easier upgrade segmentation.
5. Add an internal traffic flag or cohort to exclude dev/test usage from production dashboards.
