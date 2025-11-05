# Quick Start - Token Usage Monitoring

## 5-Minute Deployment

### Step 1: Deploy Plugin (2 min)

```bash
# Upload and activate
wp plugin activate seo-ai-alt-text-generator-auto-image-seo-accessibility
```

**That's it!** Tables are created automatically.

### Step 2: Verify (1 min)

```bash
# Check installation
wp db query "SELECT COUNT(*) FROM wp_alttextai_usage_events;"
wp option get alttextai_install_id
```

### Step 3: Test (2 min)

1. Go to WordPress admin → Media Library
2. Select an image
3. Click "Generate Alt Text (AI)"
4. Check event logged:

```bash
wp db query "SELECT * FROM wp_alttextai_usage_events ORDER BY id DESC LIMIT 1\G"
```

Expected output:
```
install_id: 550e8400-e29b-41d4-a716-446655440000_a3b4c5d6e7f8
wp_user_id: 1
source: manual
total_tokens: 175
created_at: 2025-11-03 10:30:00
```

✅ **Working!**

---

## What Happens Automatically

| Event | Frequency | Action |
|-------|-----------|--------|
| **Alt text generation** | On demand | Event logged to database |
| **Batch sync** | Every 5 min | Events sent to backend API |
| **Daily rollup** | Daily at 2 AM | Events summarized by user/day |
| **Cleanup** | Daily at 3 AM | Events older than 90 days purged |

---

## Monitoring

### Check Sync Status

```bash
wp eval '$t = new AltText_AI_Usage_Event_Tracker(); print_r($t->get_sync_status());'
```

Healthy response:
```php
Array (
    [status] => healthy
    [pending_events] => 0
    [failed_events] => 0
    [last_sync] => 2025-11-03 10:30:00
)
```

### View Recent Events

```bash
wp db query "
  SELECT wp_user_id, source, total_tokens, created_at
  FROM wp_alttextai_usage_events
  ORDER BY id DESC LIMIT 10;"
```

### Check Daily Summaries

```bash
wp db query "
  SELECT usage_date, SUM(total_tokens) as tokens, COUNT(DISTINCT wp_user_id) as users
  FROM wp_alttextai_usage_daily
  WHERE usage_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
  GROUP BY usage_date;"
```

---

## Troubleshooting

### Events Not Logging?

```bash
# Check if tables exist
wp db query "SHOW TABLES LIKE '%alttextai_usage%';"

# If missing, recreate:
wp eval 'AltText_AI_Usage_Event_Tracker::create_tables();'
```

### Sync Failing?

```bash
# Check JWT token
wp option get alttextai_jwt_token

# Manual retry
wp eval '$t = new AltText_AI_Usage_Event_Tracker(); $t->retry_failed_syncs();'
```

### Cron Not Running?

```bash
# Check cron events
wp cron event list | grep ai_alt

# Manually trigger sync
wp cron event run ai_alt_sync_usage_events
```

---

## Emergency Disable

If needed, add to `wp-config.php`:

```php
define('ALTTEXTAI_DISABLE_USAGE_TRACKING', true);
```

Events will stop logging immediately.

---

## Full Documentation

- **[IMPLEMENTATION_SUMMARY.md](IMPLEMENTATION_SUMMARY.md)** - Complete overview
- **[USAGE_TRACKING_SPEC.md](USAGE_TRACKING_SPEC.md)** - Technical spec
- **[USAGE_TRACKING_DEPLOYMENT_GUIDE.md](USAGE_TRACKING_DEPLOYMENT_GUIDE.md)** - Detailed guide

---

## That's It!

The system is **fully automated**. No configuration needed.

✅ Install → ✅ Activate → ✅ Done

Events are logged automatically and synced to your backend every 5 minutes.

