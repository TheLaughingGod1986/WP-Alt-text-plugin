# Usage Tracking Quick Reference

## Class Reference

### `AltText_AI_Usage_Event_Tracker`

Main class for event logging and syncing.

**Methods**:
- `get_install_id()` - Get or generate installation UUID
- `get_install_secret()` - Get or generate installation secret
- `log_event($args)` - Log a usage event
- `sync_events($limit)` - Sync pending events to backend
- `get_dashboard_stats($user_id)` - Get stats for dashboard
- `get_site_summary($args)` - Get aggregated usage data
- `export_to_csv($args)` - Generate CSV export
- `cleanup_old_events()` - Remove events older than 90 days

**Usage Example**:
```php
$tracker = new AltText_AI_Usage_Event_Tracker($api_client);
$tracker->log_event([
    'wp_user_id' => 1,
    'source' => 'inline',
    'model' => 'gpt-4o-mini',
    'prompt_tokens' => 150,
    'completion_tokens' => 25,
    'total_tokens' => 175,
    'context' => ['attachment_id' => 123]
]);
```

### `AltText_AI_Usage_Governance`

Handles alerts, rate limiting, and abuse detection.

**Methods**:
- `check_thresholds($current, $limit, $period)` - Check usage thresholds
- `check_rate_limit($user_id)` - Check rate limits
- `detect_abuse($user_id, $timeframe)` - Detect abuse patterns
- `should_block_generation($plan, $used)` - Check if should block
- `get_active_alerts()` - Get alerts for display
- `get_plan_limit($plan)` - Get usage limit for plan

**Usage Example**:
```php
$governance = new AltText_AI_Usage_Governance($tracker);
$rate_limit = $governance->check_rate_limit(1);
if (!$rate_limit['allowed']) {
    // Block request
}
```

## Database Tables

### `wp_alttextai_usage_events`
Raw event log. Columns:
- `install_id`, `event_id`, `wp_user_id`, `source`, `model`
- `prompt_tokens`, `completion_tokens`, `total_tokens`
- `context` (JSON), `created_at`, `synced_at`

### `wp_alttextai_usage_daily`
Daily summaries. Columns:
- `install_id`, `wp_user_id`, `source`, `usage_date`
- `total_requests`, `prompt_tokens`, `completion_tokens`, `total_tokens`

## Cron Hooks

- `ai_alt_sync_usage_events` - Every 5 minutes
- `ai_alt_rollup_usage_daily` - Daily at 2 AM
- `ai_alt_cleanup_usage_events` - Daily at 3 AM

## WordPress Options

- `alttextai_install_id` - Installation UUID
- `alttextai_install_secret` - Installation secret for signing
- `alttextai_usage_thresholds` - Custom thresholds (optional)
- `alttextai_alerts_sent` - Alert history

## Filters & Actions

### Filters
- `alttextai_usage_thresholds` - Customize threshold percentages

### Actions
- `admin_notices` - Displays usage alerts

## Constants

- `ALTTEXTAI_USAGE_DEBUG` - Enable debug logging (set in wp-config.php)

## API Endpoints (Backend)

- `POST /api/usage/event` - Submit events
- `GET /api/usage/summary` - Get aggregated data
- `GET /api/usage/events` - Get raw events
- `GET /api/usage/installations` - List installations

## Common Tasks

### Check Current Usage
```php
$stats = AltText_AI_Usage_Tracker::get_stats_display();
$used = $stats['used'];
$limit = $stats['limit'];
```

### Get Analytics Data
```php
$tracker = new AltText_AI_Usage_Event_Tracker($api_client);
$analytics = $tracker->get_dashboard_stats(null); // null = all users
```

### Manually Sync Events
```php
$tracker = new AltText_AI_Usage_Event_Tracker($api_client);
$result = $tracker->sync_events(50); // Sync up to 50 events
```

### Clear Alerts
```php
$governance = new AltText_AI_Usage_Governance($tracker);
$governance->clear_alert('warning');
```

---

*For full documentation, see `USAGE_TRACKING_SPEC.md` and `docs/backend-integration-guide.md`*

