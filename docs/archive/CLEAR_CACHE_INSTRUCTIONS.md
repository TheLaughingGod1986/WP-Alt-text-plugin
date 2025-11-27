# Clear Usage Cache - Quick Instructions

## The Problem
WordPress is showing 0/50 credits, but the upgrade modal appears when you click "Regenerate". This is because WordPress has cached old usage data showing 0 remaining credits.

## The Solution

### Option 1: Run the Script (Recommended)

**If you have SSH access to your WordPress server:**

```bash
cd /path/to/wordpress
php wp-content/plugins/beepbeep-ai-alt-text-generator/clear-usage-cache.php
```

**If WordPress is in a Docker container:**

```bash
docker exec -it wordpress_container_name php wp-content/plugins/beepbeep-ai-alt-text-generator/clear-usage-cache.php
```

**If you don't know the path, the script will try to find it automatically.**

### Option 2: Clear via WordPress Admin (Quick Fix)

1. Go to **WordPress Admin → Media → AI ALT Text**
2. Open browser Developer Tools (F12)
3. Go to **Console** tab
4. Run this command:
```javascript
jQuery.post(ajaxurl, {
    action: 'bbai_refresh_usage',
    nonce: BBAI.nonce
}, function(response) {
    console.log('Usage refreshed:', response);
    location.reload();
});
```

### Option 3: Manual Database Clear (Advanced)

If you have database access:

```sql
DELETE FROM wp_options WHERE option_name LIKE '_transient_bbai_usage_cache%';
DELETE FROM wp_options WHERE option_name LIKE '_transient_timeout_bbai_usage_cache%';
DELETE FROM wp_options WHERE option_name LIKE '_transient_bbai_stats_cache%';
DELETE FROM wp_options WHERE option_name LIKE '_transient_timeout_bbai_stats_cache%';
```

(Replace `wp_options` with your actual table prefix if different)

### After Clearing Cache

1. Refresh your WordPress admin page
2. The plugin should now fetch fresh usage data from the API
3. Try generating alt text again - the modal should no longer appear

## What the Script Does

1. ✅ Clears all cached usage data
2. ✅ Fetches fresh data from the API (which shows 0 used, 50 remaining)
3. ✅ Updates the cache with correct values
4. ✅ Clears all related caches

## Current Database Status (Confirmed)

- ✅ Used: **0**
- ✅ Limit: **50**
- ✅ Remaining: **50**

The database is correct - we just need to refresh WordPress cache!

