# Development & Testing Tools

This document describes the development and testing utilities available for the usage tracking system.

## Test Scripts

### 1. Usage Tracking Test Helper

**Location**: `scripts/test-usage-tracking.php`

**Usage**:
```bash
wp eval-file scripts/test-usage-tracking.php
```

**What it does**:
- Tests installation ID generation
- Tests event logging
- Displays dashboard statistics
- Shows source breakdown
- Tests governance checks
- Checks rate limiting
- Tests abuse detection
- Shows sync status
- Verifies database tables

**Output**: Comprehensive test results for all usage tracking components.

### 2. Migration Script

**Location**: `scripts/migrate-existing-usage.php`

**Usage**:
```bash
wp eval-file scripts/migrate-existing-usage.php
```

**What it does**:
- Migrates existing usage data from old system
- Creates synthetic events for historical totals
- Preserves usage statistics in new event-based system
- Marks migration as complete (prevents re-running)

**Note**: Only runs once. Delete option `alttextai_usage_migration_done` to re-run.

## Health Check System

### Health Check Class

**Location**: `includes/class-usage-health-check.php`

**Methods**:
- `AltText_AI_Usage_Health_Check::check()` - Run full health check
- `AltText_AI_Usage_Health_Check::get_report()` - Get formatted report

**Usage (WP-CLI)**:
```php
require_once 'includes/class-usage-health-check.php';
echo AltText_AI_Usage_Health_Check::get_report();
```

**Checks Performed**:
1. Database tables exist
2. Installation ID configured
3. Cron jobs scheduled
4. Recent sync activity
5. Database performance

### AJAX Health Check

**Endpoint**: `wp_ajax_alttextai_usage_health_check`

**Usage** (from JavaScript):
```javascript
fetch(ajaxurl, {
    method: 'POST',
    body: new FormData({
        action: 'alttextai_usage_health_check',
        nonce: alttextai_nonce
    })
})
.then(r => r.json())
.then(data => {
    if (data.success) {
        console.log('Health:', data.data);
    }
});
```

## Debug Mode

Enable verbose logging by adding to `wp-config.php`:

```php
define('ALTTEXTAI_USAGE_DEBUG', true);
```

This logs all usage tracking operations to `wp-content/debug.log`:
- Event logging
- Sync operations
- Error details
- Performance metrics

## Common Development Tasks

### View Current Usage Stats
```php
$stats = AltText_AI_Usage_Tracker::get_stats_display(true);
print_r($stats);
```

### Manually Trigger Sync
```php
$api_client = new AltText_AI_API_Client_V2();
$tracker = new AltText_AI_Usage_Event_Tracker($api_client);
$result = $tracker->sync_events(50);
print_r($result);
```

### Check Rate Limits
```php
$governance = new AltText_AI_Usage_Governance($tracker);
$rate_limit = $governance->check_rate_limit(1);
print_r($rate_limit);
```

### Clear Old Events Manually
```php
AltText_AI_Usage_Event_Tracker::cleanup_old_events();
AltText_AI_Usage_Event_Tracker::cleanup_old_summaries();
```

### Reset Installation (for testing)
```php
delete_option('alttextai_install_id');
delete_option('alttextai_install_secret');
// Next activation will generate new IDs
```

### View Unsynced Events
```sql
SELECT COUNT(*) 
FROM wp_alttextai_usage_events 
WHERE synced_at IS NULL;
```

## Testing Checklist

### Basic Functionality
- [ ] Run test script - all checks pass
- [ ] Generate alt text - event logged
- [ ] View analytics dashboard - data appears
- [ ] Export CSV - file downloads correctly

### Error Handling
- [ ] Backend unreachable - events stored locally
- [ ] Invalid signature - syncing fails gracefully
- [ ] Missing tables - auto-created on error
- [ ] Rate limit exceeded - proper error message

### Governance
- [ ] 75% usage - warning alert appears
- [ ] 90% usage - critical alert appears
- [ ] 100% usage - block and error message
- [ ] Rate limit - generation blocked correctly
- [ ] Abuse detection - unusual patterns logged

### Cron Jobs
- [ ] Sync cron runs every 5 minutes
- [ ] Cleanup cron removes old events
- [ ] Jobs reschedule after deactivation/reactivation

## Troubleshooting

### Events Not Logging
1. Check database tables exist
2. Check for PHP errors in debug.log
3. Verify user has permissions
4. Check ALTTEXTAI_USAGE_DEBUG logs

### Events Not Syncing
1. Check JWT token validity
2. Verify installation secret matches backend
3. Check backend endpoint is available
4. Review sync_error column in events table

### Dashboard Shows No Data
1. Verify events are being logged
2. Check daily summaries exist
3. Clear cache and refresh
4. Check date range filters

### Cron Jobs Not Running
1. Verify WP_CRON is enabled
2. Check cron jobs are scheduled
3. Manually trigger: `wp cron event run ai_alt_sync_usage_events`
4. Check for PHP errors in cron execution

---

*For more information, see the main documentation files.*

