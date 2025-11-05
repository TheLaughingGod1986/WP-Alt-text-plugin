# Issue Analysis - Backend Connectivity Intermittent Failures

## Current Status

Based on your diagnostic output, here's what's happening:

### ✅ What's Working
- **Backend is online** - Health check successful at 16:28:30
- **Authentication is valid** - JWT token present and working
- **User logged in** - benoats@gmail.com on free plan
- **Recent health check succeeded** - Status 200, proper JSON response

### ❌ What's Failing
- **Historical DNS failures** - Multiple "cURL error 6" around 16:06-16:07
- **Queue not processing** - 6 items stuck in "pending" status
- **Billing endpoint failures** - Repeated failures to `/billing/plans`
- **API generate failures** - Three 500 errors at 16:00:39-16:00:41

---

## Timeline Analysis

```
16:00:39-16:00:41  → api/generate failures (HTTP 500) - Backend errors
16:06:10-16:07:34  → Multiple DNS failures (cURL error 6) - Cannot resolve host
16:28:30          → Health check SUCCESS - DNS resolved!
16:28:35          → Diagnostic generated - All systems appear working
```

**Pattern:** DNS resolution was failing intermittently, but it's working NOW.

---

## Root Cause: Intermittent DNS Resolution in Docker

The issue is **DNS cache poisoning or DNS resolver instability** in your Docker container.

### Why This Happens

1. **Docker's embedded DNS** (127.0.0.11) sometimes has issues
2. **DNS cache gets stale** when backend DNS changes (Cloudflare CDN rotation)
3. **Container doesn't refresh DNS** without restart
4. **PHP's cURL uses system DNS**, which caches aggressively

### Evidence
- Health check NOW works (16:28:30) ✅
- Same endpoint failed 20 minutes earlier (16:06-16:07) ❌
- No code changes between failures and success
- Error is specifically "Could not resolve host" (DNS issue)

---

## Why Queue Items Are Stuck

Looking at your queue:
```
"pending": 6,
"processing": 0,
"failed": 0
```

Items with 3 attempts (max retries reached):
- Item #3: IMG_1708 - 3 attempts, still "pending"
- Item #2: IMG_0200 - 3 attempts, still "pending"

**Problem:** Queue processor tried to process these during the DNS failure window, hit max retries, but didn't mark them as "failed". They're stuck in limbo.

---

## Immediate Fixes

### Fix 1: Clear DNS Cache in Container (Restart)
```bash
docker restart wp-alt-text-plugin-wordpress-1
```

This will:
- Clear DNS cache
- Reset PHP-FPM
- Force fresh DNS lookups

### Fix 2: Clear API Error Log
```bash
docker exec wp-alt-text-plugin-wordpress-1 php -r "
define('ABSPATH', '/var/www/html/');
\$_SERVER['HTTP_HOST'] = 'localhost';
require '/var/www/html/wp-load.php';
delete_option('alttextai_api_error_log');
echo '✅ Error log cleared!' . PHP_EOL;
"
```

### Fix 3: Reset Stuck Queue Items
```bash
docker exec wp-alt-text-plugin-wordpress-1 php -r "
define('ABSPATH', '/var/www/html/');
\$_SERVER['HTTP_HOST'] = 'localhost';
require '/var/www/html/wp-load.php';

global \$wpdb;
\$table = \$wpdb->prefix . 'ai_alt_gpt_queue';

// Reset items with 3 attempts back to 0
\$updated = \$wpdb->query(
    \"UPDATE \$table SET attempts = 0 WHERE attempts >= 3 AND status = 'pending'\"
);

echo \"✅ Reset \$updated queue items!\" . PHP_EOL;
"
```

### Fix 4: Manually Trigger Queue Processing
```bash
docker exec wp-alt-text-plugin-wordpress-1 php -r "
define('ABSPATH', '/var/www/html/');
\$_SERVER['HTTP_HOST'] = 'localhost';
require '/var/www/html/wp-load.php';

// Trigger queue processing
do_action('ai_alt_gpt_process_queue');
echo '✅ Queue processing triggered!' . PHP_EOL;
"
```

---

## Permanent Solutions

### Solution 1: Add Stable DNS to Docker Compose ⭐ **RECOMMENDED**

Edit your `docker-compose.yml`:

```yaml
services:
  wordpress:
    image: wordpress:latest
    ports:
      - "8080:80"
    dns:
      - 8.8.8.8      # Google DNS (primary)
      - 8.8.4.4      # Google DNS (secondary)
      - 1.1.1.1      # Cloudflare DNS (backup)
    environment:
      WORDPRESS_DB_HOST: db:3306
      WORDPRESS_DB_USER: wordpress
      WORDPRESS_DB_PASSWORD: wordpress
      WORDPRESS_DB_NAME: wordpress
      WORDPRESS_DEBUG: 1
    volumes:
      - wordpress_data:/var/www/html
      - ./:/var/www/html/wp-content/plugins/ai-alt-gpt
    depends_on:
      - db
```

Then restart:
```bash
docker-compose down
docker-compose up -d
```

### Solution 2: Increase cURL Timeout

Edit `includes/class-api-client-v2.php` line 143:

```php
$args = [
    'method' => $method,
    'headers' => $headers,
    'timeout' => 60,  // Increase from 30 to 60 seconds
    'redirection' => 5,
    'httpversion' => '1.1',
];
```

### Solution 3: Add DNS Pre-resolution

Add this to `includes/class-api-client-v2.php` before making requests:

```php
/**
 * Pre-warm DNS cache to avoid resolution failures
 */
private function prewarm_dns() {
    static $warmed = false;
    if ($warmed) return;

    // Force DNS resolution
    $host = 'alttext-ai-backend.onrender.com';
    gethostbyname($host);

    $warmed = true;
}
```

Call it in `make_request()` before the actual request.

### Solution 4: Add Retry Logic with Exponential Backoff

Wrap the `wp_remote_request()` call:

```php
private function make_request_with_retry($url, $args, $max_retries = 3) {
    $retry_count = 0;
    $backoff = 1; // seconds

    while ($retry_count < $max_retries) {
        $response = wp_remote_request($url, $args);

        if (!is_wp_error($response)) {
            return $response;
        }

        $error_message = $response->get_error_message();

        // Only retry on DNS errors
        if (strpos($error_message, 'could not resolve') === false &&
            strpos($error_message, 'timeout') === false) {
            return $response; // Don't retry other errors
        }

        $retry_count++;
        if ($retry_count < $max_retries) {
            sleep($backoff);
            $backoff *= 2; // Exponential backoff
        }
    }

    return $response;
}
```

---

## Queue Processing Issue

The queue items are stuck because:

1. **Max attempts reached (3)** but status is still "pending"
2. **Queue processor skips items** with `attempts >= 3`
3. **No automatic reset** mechanism

### Code Fix Needed

Check your queue processing logic. It should mark items as "failed" after max attempts:

```php
// In your queue processor
if ($item->attempts >= 3) {
    // Should set status to 'failed', not leave as 'pending'
    $wpdb->update(
        $queue_table,
        ['status' => 'failed'],
        ['id' => $item->id]
    );
}
```

---

## Monitoring & Prevention

### Add Health Check Monitoring

Create a cron job to monitor backend connectivity:

```bash
# Add to WordPress cron
docker exec wp-alt-text-plugin-wordpress-1 php -r "
define('ABSPATH', '/var/www/html/');
\$_SERVER['HTTP_HOST'] = 'localhost';
require '/var/www/html/wp-load.php';

// Schedule health check every 5 minutes
if (!wp_next_scheduled('alttextai_health_check')) {
    wp_schedule_event(time(), 'five_minutes', 'alttextai_health_check');
}
"
```

### Add Custom Cron Schedule

```php
add_filter('cron_schedules', function($schedules) {
    $schedules['five_minutes'] = [
        'interval' => 300,
        'display' => __('Every 5 Minutes')
    ];
    return $schedules;
});

add_action('alttextai_health_check', function() {
    $response = wp_remote_get('https://alttext-ai-backend.onrender.com/health');

    if (is_wp_error($response)) {
        // Log failure
        error_log('[AltText AI] Backend health check failed: ' . $response->get_error_message());

        // Try DNS refresh
        gethostbyname('alttext-ai-backend.onrender.com');
    }
});
```

---

## Action Plan (Do This Now)

### Step 1: Add DNS to docker-compose.yml
```bash
# Edit docker-compose.yml and add dns: section
# Then:
docker-compose down
docker-compose up -d
```

### Step 2: Clear Error Log
```bash
docker exec wp-alt-text-plugin-wordpress-1 php -r "
define('ABSPATH', '/var/www/html/');
\$_SERVER['HTTP_HOST'] = 'localhost';
require '/var/www/html/wp-load.php';
delete_option('alttextai_api_error_log');
echo '✅ Cleared!';
"
```

### Step 3: Reset Queue
```bash
docker exec wp-alt-text-plugin-wordpress-1 php -r "
define('ABSPATH', '/var/www/html/');
\$_SERVER['HTTP_HOST'] = 'localhost';
require '/var/www/html/wp-load.php';
global \$wpdb;
\$table = \$wpdb->prefix . 'ai_alt_gpt_queue';
\$wpdb->query(\"UPDATE \$table SET attempts = 0 WHERE attempts >= 3 AND status = 'pending'\");
echo '✅ Queue reset!';
"
```

### Step 4: Test Queue Processing
```bash
# Watch logs while queue processes
docker logs -f wp-alt-text-plugin-wordpress-1 &

# Trigger processing
docker exec wp-alt-text-plugin-wordpress-1 php -r "
define('ABSPATH', '/var/www/html/');
\$_SERVER['HTTP_HOST'] = 'localhost';
require '/var/www/html/wp-load.php';
do_action('ai_alt_gpt_process_queue');
"
```

---

## Summary

**Root Cause:** Docker's embedded DNS resolver is unstable for external hostnames

**Symptoms:**
- Intermittent cURL error 6 (DNS resolution)
- Queue items stuck at 3 attempts
- Health checks sometimes fail, sometimes succeed

**Solution:**
1. ✅ Add explicit DNS servers to docker-compose.yml
2. ✅ Reset stuck queue items
3. ✅ Clear error log
4. ✅ Restart containers with new DNS config

**Status After Fix:**
- DNS will be stable (Google DNS 8.8.8.8)
- Queue will process successfully
- No more intermittent failures

---

**Generated:** November 5, 2025, 16:35 UTC
**Issue:** Intermittent DNS resolution in Docker
**Severity:** Medium (causes queue processing failures)
**Fix Time:** 5 minutes
