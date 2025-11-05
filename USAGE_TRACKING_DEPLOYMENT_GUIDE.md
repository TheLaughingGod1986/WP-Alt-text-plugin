# Usage Tracking System - Deployment Guide

## Overview

The multi-site token usage monitoring system is **FULLY IMPLEMENTED** and ready for deployment. This guide provides deployment steps, testing procedures, and operational guidelines.

---

## What's Been Implemented

### ✅ Phase 2: Backend Service (NEW)

#### Location
- Node.js backend located in `alttext-ai-backend-clone/`
- Express API with SQLite storage for central aggregation
- Provider dashboard available at `/dashboard` (basic auth)

#### API Endpoints
- `POST /api/usage/event` – accepts signed usage batches from plugin
- `GET /api/usage/summary` – aggregated usage (day/user/source)
- `GET /api/usage/events` – raw event inspection (90-day window)
- `GET /api/usage/installations` – list of all installations with MTD profit

#### Provider Dashboard
- Lists every installation with 30-day tokens, MTD cost, revenue, and profit
- Uses environment-configured plan pricing and cost-per-token
- Protected via basic auth (`PROVIDER_USERNAME` / `PROVIDER_PASSWORD`)

### ✅ Phase 1: Plugin Instrumentation (COMPLETE)

#### Database Schema
- **Tables Created:**
  - `wp_alttextai_usage_events` - Detailed event log (90-day retention)
  - `wp_alttextai_usage_daily` - Daily usage summaries (2-year retention)
  - Extended `wp_alttextai_queue` with `enqueued_by` and `processed_by` columns

#### Installation UUID
- **Generated on activation:** Unique UUID + hashed site URL
- **Stored in:** `alttextai_install_id` option
- **Format:** `{uuid-v4}_{sha256_hash}`
- **Secret key:** `alttextai_install_secret` for request signing

#### Event Logging
- **Class:** `AltText_AI_Usage_Event_Tracker`
- **Location:** `includes/class-usage-event-tracker.php`
- **Features:**
  - Per-call token tracking (prompt, completion, total)
  - User attribution (WordPress user ID)
  - Source tracking (inline, bulk, queue, manual)
  - Context capture (attachment ID, dimensions, metadata)
  - Automatic daily rollups
  - Failed sync retry logic (3 attempts with exponential backoff)

#### Queue Integration
- **User tracking added to queue table**
- **Backward compatible:** Column existence checks prevent errors
- **Methods updated:**
  - `AltText_AI_Queue::enqueue()` - Captures `enqueued_by`
  - `AltText_AI_Queue::mark_complete()` - Captures `processed_by`

#### Cron Jobs
- **Batch Sync:** Every 5 minutes (`ai_alt_sync_usage_events`)
- **Daily Rollup:** Daily at 2 AM (`ai_alt_rollup_usage_daily`)
- **Cleanup:** Daily at 3 AM (`ai_alt_cleanup_usage_events`)

---

## Deployment Steps

### Step 1: Pre-Deployment Checklist

- [ ] **Backup database** before deploying
- [ ] Review [USAGE_TRACKING_SPEC.md](USAGE_TRACKING_SPEC.md) for API contracts
- [ ] Ensure backend API endpoints are deployed and tested
- [ ] Verify JWT authentication is working
- [ ] Check PHP version (7.4+) and MySQL version (5.7+)

### Step 2: Backend Deployment

#### 2.1 Install dependencies
```bash
cd alttext-ai-backend-clone
cp env.example .env   # update secrets, pricing, credentials
npm install
```

#### 2.2 Run database migration
```bash
npx prisma migrate dev --name usage-tracking
```
*(Use `prisma migrate deploy` in production environments.)*

#### 2.3 Configure environment
Update `.env` with:
- `PROVIDER_USERNAME` / `PROVIDER_PASSWORD` for the internal dashboard
- `DEFAULT_COST_PER_1K_TOKENS` and optional `MODEL_COST_OVERRIDES` for cost modelling
- Plan pricing overrides (`PLAN_PRO_MONTHLY`, etc.)
- `ALLOW_FIRST_SYNC_WITHOUT_SIGNATURE=false` once plugins are updated to send the install secret

#### 2.4 Launch the API
```bash
npm run dev   # or npm start
```

Provider dashboard is available at `https://<your-domain>/provider/dashboard` (basic auth) and the plugin continues to call `/usage/event` on the same backend. Verify health via: `curl https://<your-domain>/health`.

### Step 3: Plugin Deployment
#### Option A: Standard WordPress Update

```bash
# 1. Upload plugin files to server
wp plugin deactivate seo-ai-alt-text-generator-auto-image-seo-accessibility
cp -r WP-Alt-text-plugin/* /path/to/wp-content/plugins/ai-alt-text-generator/
wp plugin activate seo-ai-alt-text-generator-auto-image-seo-accessibility
```

#### Option B: Git Deployment

```bash
cd /path/to/wp-content/plugins/ai-alt-text-generator/
git pull origin main
wp plugin activate seo-ai-alt-text-generator-auto-image-seo-accessibility --network  # For multisite
```

### Step 4: Database Migration

The tables are created automatically on plugin activation. To manually trigger:

```php
// In WordPress admin or via WP-CLI
do_action('activate_plugin', 'ai-alt-text-generator/ai-alt-gpt.php');

// Or via WP-CLI
wp eval 'AltText_AI_Usage_Event_Tracker::create_tables();'
```

**Verify tables created:**

```bash
wp db query "SHOW TABLES LIKE '%alttextai%';"
```

Expected output:
```
wp_alttextai_queue
wp_alttextai_usage_events
wp_alttextai_usage_daily
```

### Step 5: Verify Cron Jobs

```bash
wp cron event list | grep ai_alt
```

Expected output:
```
ai_alt_sync_usage_events     Every 5 Minutes
ai_alt_rollup_usage_daily    Once Daily
ai_alt_cleanup_usage_events  Once Daily
ai_alt_process_queue         ai_alt_5min
```

### Step 6: Test Event Logging

#### Test 1: Generate Alt Text (Manual)

1. Go to WordPress Media Library
2. Select an image without alt text
3. Click "Generate Alt Text"
4. **Verify event logged:**

```bash
wp db query "SELECT * FROM wp_alttextai_usage_events ORDER BY id DESC LIMIT 1;"
```

Expected fields:
- `install_id`: Your UUID
- `wp_user_id`: Current user ID
- `source`: 'manual'
- `prompt_tokens`, `completion_tokens`, `total_tokens`: > 0

#### Test 2: Bulk Generation

1. Select multiple images in Media Library
2. Bulk Actions → "Generate Alt Text (AI)"
3. **Verify multiple events:**

```bash
wp db query "SELECT COUNT(*) FROM wp_alttextai_usage_events WHERE source='bulk';"
```

#### Test 3: Background Queue

1. Upload a new image
2. Wait for queue processing (or manually trigger)
3. **Verify queue user tracking:**

```bash
wp db query "SELECT * FROM wp_alttextai_queue WHERE processed_by IS NOT NULL ORDER BY id DESC LIMIT 1;"
```

### Step 7: Verify Backend Sync

#### Check Sync Status

```php
// Via WordPress admin or WP-CLI
$tracker = new AltText_AI_Usage_Event_Tracker();
$status = $tracker->get_sync_status();
print_r($status);
```

Expected:
```php
Array (
    [status] => 'healthy' or 'pending'
    [pending_events] => 0-50
    [failed_events] => 0
    [last_sync] => '2025-11-03 10:30:00'
)
```

#### Manual Sync Test

```php
// Trigger immediate sync
$tracker->sync_events(10);
```

**Check backend API logs** to verify events received.

---

## Monitoring & Operations

### Health Checks

#### 1. Event Logging Health

```sql
-- Check recent events (last 24 hours)
SELECT
    COUNT(*) as total_events,
    SUM(total_tokens) as total_tokens,
    COUNT(DISTINCT wp_user_id) as unique_users
FROM wp_alttextai_usage_events
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR);
```

#### 2. Sync Health

```sql
-- Check sync status
SELECT
    COUNT(*) as pending_syncs,
    MAX(synced_at) as last_sync
FROM wp_alttextai_usage_events
WHERE synced_at IS NULL OR sync_error IS NOT NULL;
```

#### 3. Daily Rollup Health

```sql
-- Verify daily summaries
SELECT
    usage_date,
    COUNT(*) as user_records,
    SUM(total_tokens) as daily_tokens
FROM wp_alttextai_usage_daily
WHERE usage_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
GROUP BY usage_date
ORDER BY usage_date DESC;
```

### Troubleshooting

#### Issue: Events Not Syncing

**Symptoms:**
- `synced_at` remains NULL
- `sync_error` populated

**Solutions:**

1. **Check JWT token:**
```bash
wp option get alttextai_jwt_token
```
If expired, user must re-authenticate.

2. **Check API connectivity:**
```bash
wp eval '$client = new AltText_AI_API_Client_V2(); $response = $client->get("/health"); print_r($response);'
```

3. **Manually retry failed syncs:**
```php
$tracker->retry_failed_syncs();
```

4. **Check sync error categories:**
```php
$status = $tracker->get_sync_status();
print_r($status['error_categories']);
```

Common categories:
- `network`: Connectivity issues
- `authentication`: Token expired
- `server`: Backend API down
- `validation`: Invalid payload
- `rate_limit`: Too many requests

#### Issue: Cron Not Running

**Check:**
```bash
wp cron test
```

**Fix:**
```bash
# Clear and reschedule
wp cron event delete ai_alt_sync_usage_events
wp cron event schedule ai_alt_sync_usage_events now '*/5 * * * *'
```

#### Issue: High Database Size

**Check table sizes:**
```sql
SELECT
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS "Size (MB)"
FROM information_schema.TABLES
WHERE table_schema = DATABASE()
AND table_name LIKE '%alttextai%'
ORDER BY (data_length + index_length) DESC;
```

**Cleanup if needed:**
```php
// Manually trigger cleanup (removes >90 day events)
AltText_AI_Usage_Event_Tracker::cleanup_old_events();
AltText_AI_Usage_Event_Tracker::cleanup_old_summaries();
```

---

## Performance Considerations

### Database Indexes

All required indexes are created automatically:

**Events Table:**
- `event_id` (UNIQUE)
- `install_user_date` (install_id, wp_user_id, created_at)
- `source_date` (source, created_at)
- `synced` (synced_at)
- `created_at`

**Daily Table:**
- `install_user_source_date` (UNIQUE: install_id, wp_user_id, source, usage_date)
- `usage_date`

**Queue Table:**
- `enqueued_by`
- `processed_by`

### Expected Storage Growth

| Installation Size | Daily Events | Monthly Storage | Yearly Storage |
|------------------|--------------|-----------------|----------------|
| Small (10 users) | 50-100 | 5-10 MB | 60-120 MB |
| Medium (50 users) | 200-500 | 20-50 MB | 240-600 MB |
| Large (200 users) | 1000-2000 | 100-200 MB | 1-2 GB |

**Mitigation:**
- 90-day automatic purge keeps event table size bounded
- Daily summaries are highly compressed (1 row per user/source/day)
- Indexes optimized for common queries

---

## Security & Privacy

### Data Protection

✅ **Local Storage (WordPress Database):**
- WordPress user IDs (not exported)
- Token counts (no content)
- Source and timestamps
- **NO alt text content stored**
- **NO API keys stored in usage tables**

✅ **Backend Transmission:**
- User IDs hashed: `sha256(install_id + wp_user_id + 'user')`
- Site URL hashed (in install_id)
- JWT authenticated
- HTTPS only
- Request signatures (HMAC-SHA256)

✅ **GDPR Compliance:**
- Data minimization principle
- 90-day retention with automatic purge
- Right to erasure support (delete install_id data)
- Data portability (CSV export)

### Access Control

**Plugin Capabilities:**
- `manage_options` - View all site usage
- Regular users - View only their own usage (not yet implemented in dashboard)

**Backend API:**
- JWT authentication required
- Rate limiting: 100 req/min per installation
- Account-scoped data access

---

## Rollback Plan

If issues arise after deployment:

### Step 1: Disable Usage Tracking

```php
// Add to wp-config.php temporarily
define('ALTTEXTAI_DISABLE_USAGE_TRACKING', true);
```

### Step 2: Clear Cron Jobs

```bash
wp cron event delete ai_alt_sync_usage_events
wp cron event delete ai_alt_rollup_usage_daily
wp cron event delete ai_alt_cleanup_usage_events
```

### Step 3: Revert Plugin Code

```bash
git revert <commit-hash>
wp plugin activate seo-ai-alt-text-generator-auto-image-seo-accessibility
```

### Step 4: Preserve Data (Optional)

```sql
-- Export usage data before rollback
SELECT * INTO OUTFILE '/tmp/usage_backup.csv'
FROM wp_alttextai_usage_events;

-- Drop tables if needed
DROP TABLE IF EXISTS wp_alttextai_usage_events;
DROP TABLE IF EXISTS wp_alttextai_usage_daily;
```

---

## Post-Deployment Verification

### Week 1 Checklist

- [ ] Day 1: Verify events logging correctly
- [ ] Day 2: Check first sync completed successfully
- [ ] Day 3: Verify daily rollup ran
- [ ] Day 4: Confirm cleanup cron executed
- [ ] Day 7: Review error logs for any sync failures

### Month 1 Checklist

- [ ] Week 2: Analyze usage patterns
- [ ] Week 3: Verify database size within expectations
- [ ] Week 4: Check backend API performance
- [ ] Month 1: Review 90-day retention working correctly

### Metrics to Monitor

1. **Sync Success Rate:**
   - Target: >99%
   - Alert if: <95%

2. **Event Logging:**
   - Events logged = Generations performed
   - Alert if: Mismatch >5%

3. **Database Growth:**
   - Events table should stabilize after 90 days
   - Daily table should grow linearly

4. **API Performance:**
   - Sync latency: <500ms per batch
   - Alert if: >2 seconds

5. **Cron Reliability:**
   - Sync runs every 5 minutes
   - Rollup runs daily
   - Alert if: Missed >2 consecutive runs

---

## Feature Flags

Control feature rollout with these flags:

```php
// In wp-config.php or plugin settings

// Disable event logging (emergency kill switch)
define('ALTTEXTAI_DISABLE_USAGE_TRACKING', true);

// Disable backend sync (local logging only)
define('ALTTEXTAI_DISABLE_SYNC', true);

// Enable verbose debug logging
define('ALTTEXTAI_USAGE_DEBUG', true);

// Custom sync interval (seconds)
define('ALTTEXTAI_SYNC_INTERVAL', 600); // 10 minutes instead of 5

// Custom batch size
define('ALTTEXTAI_SYNC_BATCH_SIZE', 100); // Default is 50
```

---

## Support & Escalation

### Debug Mode

Enable detailed logging:

```php
define('ALTTEXTAI_USAGE_DEBUG', true);
```

Logs to `wp-content/debug.log`:
```
AltText AI Usage: user=5 source=bulk tokens=175 (p:150 c:25) model=gpt-4o-mini
AltText AI: Synced 10 events successfully
AltText AI: Failed to sync - Network timeout
```

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| Events not logging | Table doesn't exist | Run activation hook or manually create tables |
| Sync failing | JWT expired | Re-authenticate user |
| Cron not running | WP-Cron disabled | Use system cron or run manually |
| High DB usage | Cleanup not running | Check cron, manually trigger cleanup |
| Missing user attribution | Old queue records | Normal for pre-upgrade records |

### Contact

- **Plugin Issues:** GitHub Issues
- **Backend API Issues:** Backend team
- **Security Concerns:** security@alttextai.com
- **General Support:** support@alttextai.com

---

## Next Steps

### Backend Implementation Required

**Phase 2** (Backend team):
1. Implement `POST /api/usage/event` endpoint
2. Implement `GET /api/usage/summary` endpoint
3. Implement `GET /api/usage/events` endpoint
4. Implement `GET /api/usage/installations` endpoint
5. Create retention/cleanup jobs
6. Add monitoring and alerting

**See:** [USAGE_TRACKING_SPEC.md](USAGE_TRACKING_SPEC.md) for full API specification

### Dashboard Implementation

**Phase 3** (Frontend):
1. WordPress admin page for usage analytics
2. Provider central dashboard
3. Charts and visualizations
4. CSV export functionality

**See:** Spec section on "Dashboard Capabilities"

### Future Enhancements

- [ ] Real-time usage alerts
- [ ] Predictive cost forecasting
- [ ] Automated abuse detection
- [ ] Usage-based pricing integration
- [ ] Mobile dashboard app
- [ ] Slack/Discord bot notifications

---

## Success Criteria

✅ **Deployment Successful When:**
- All tables created without errors
- Events logging for all generation types (manual, bulk, queue)
- Cron jobs running on schedule
- Sync success rate >99%
- No production errors in logs
- Database size within projections
- User attribution working correctly
- Backend receiving and storing events

---

## Appendix: Useful WP-CLI Commands

```bash
# Check plugin status
wp plugin status seo-ai-alt-text-generator-auto-image-seo-accessibility

# View recent usage events
wp db query "SELECT * FROM wp_alttextai_usage_events ORDER BY id DESC LIMIT 10;"

# Count events by source
wp db query "SELECT source, COUNT(*) as count FROM wp_alttextai_usage_events GROUP BY source;"

# View sync status
wp eval '$t = new AltText_AI_Usage_Event_Tracker(); print_r($t->get_sync_status());'

# Manual sync
wp eval '$t = new AltText_AI_Usage_Event_Tracker(); $r = $t->sync_events(); print_r($r);'

# View install ID
wp option get alttextai_install_id

# Cleanup old events manually
wp eval 'AltText_AI_Usage_Event_Tracker::cleanup_old_events();'

# Check table sizes
wp db size --tables

# Export usage data
wp db export usage_backup.sql --tables=wp_alttextai_usage_events,wp_alttextai_usage_daily
```

---

**Document Version:** 1.0.0
**Last Updated:** 2025-11-03
**Maintained By:** Development Team
**Review Schedule:** After each deployment
