# Backend Connectivity Troubleshooting Guide

## Current Status

**Backend Service:** ✅ **ONLINE AND OPERATIONAL**

- **URL:** https://alttext-ai-backend.onrender.com
- **Status:** Active and responding
- **DNS:** Resolving correctly to Cloudflare CDN
- **Health Check:** `/health` endpoint returns HTTP 200

**Test Results (from external network):**
```bash
$ nslookup alttext-ai-backend.onrender.com
# Returns: 216.24.57.251, 216.24.57.7

$ curl https://alttext-ai-backend.onrender.com/health
# Returns: {"status":"ok","timestamp":"2025-11-05T16:17:31.048Z","version":"2.0.0","phase":"monetization"}
```

## Problem Analysis

If WordPress shows "cURL error 6: Could not resolve host", this is **NOT** a backend issue—it's a **WordPress server configuration problem**.

### Common Causes

1. **DNS Cache** - Stale DNS cache on WordPress server
2. **Firewall/Network** - Outbound HTTPS blocked by hosting
3. **SSL Certificates** - Outdated CA certificate bundle
4. **WordPress Configuration** - Proxy or network constants in wp-config.php
5. **Hosting Restrictions** - Some hosts block external API calls

## Diagnostic Steps

### Step 1: Run Diagnostic Script

1. Upload `test-backend-connectivity.php` to your WordPress root directory
2. Access it via browser: `https://yoursite.com/test-backend-connectivity.php`
3. Review all test results

### Step 2: Check WordPress Settings

```php
// In wp-config.php, ensure these are NOT set or are correct:
define('WP_PROXY_HOST', ''); // Should be empty or not defined
define('WP_PROXY_PORT', ''); // Should be empty or not defined
define('WP_ACCESSIBLE_HOSTS', '*'); // Or include alttext-ai-backend.onrender.com
```

### Step 3: Test from Command Line

SSH into your WordPress server and run:

```bash
# Test DNS resolution
nslookup alttext-ai-backend.onrender.com

# Test with curl
curl -v https://alttext-ai-backend.onrender.com/health

# Test with wget
wget --spider https://alttext-ai-backend.onrender.com/health
```

### Step 4: Check PHP cURL

```bash
php -r "var_dump(function_exists('curl_init'));"
php -i | grep -i curl
```

## Solutions by Error Type

### cURL Error 6: "Could not resolve host"

**Cause:** DNS resolution failing on WordPress server

**Solutions:**
1. Restart web server and PHP-FPM
   ```bash
   sudo systemctl restart apache2  # or nginx
   sudo systemctl restart php7.4-fpm  # adjust version
   ```

2. Check DNS configuration
   ```bash
   cat /etc/resolv.conf
   # Should have valid nameservers like 8.8.8.8 or 1.1.1.1
   ```

3. Add to wp-config.php temporarily to bypass DNS cache:
   ```php
   define('WP_HTTP_BLOCK_EXTERNAL', false);
   ```

### cURL Error 60: "SSL certificate problem"

**Cause:** Outdated CA certificates

**Solutions:**
1. Update CA certificates
   ```bash
   # Ubuntu/Debian
   sudo apt-get update
   sudo apt-get install --reinstall ca-certificates

   # CentOS/RHEL
   sudo yum update ca-certificates
   ```

2. Temporary workaround (NOT RECOMMENDED for production):
   ```php
   // In API client, only for testing:
   curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
   ```

### cURL Error 7: "Connection refused"

**Cause:** Firewall blocking outbound connections

**Solutions:**
1. Check firewall rules
   ```bash
   sudo iptables -L -n | grep 443
   ```

2. Allow outbound HTTPS
   ```bash
   sudo iptables -A OUTPUT -p tcp --dport 443 -j ACCEPT
   ```

3. Contact hosting provider to whitelist:
   - `alttext-ai-backend.onrender.com`
   - Or Cloudflare IP ranges

### cURL Error 28: "Operation timed out"

**Cause:** Network latency or rate limiting

**Solutions:**
1. Increase timeout in wp-config.php:
   ```php
   define('WP_HTTP_TIMEOUT', 60);
   ```

2. Check server load and network speed

## WordPress-Specific Fixes

### Clear WordPress Transients

```php
// In WordPress admin or via WP-CLI
delete_transient('alttextai_token_last_check');
wp_cache_flush();
```

### Clear Plugin Cache

```php
// In plugin, clear cached API responses
delete_option('alttextai_api_error_log');
delete_transient('alttextai_backend_status');
```

### Force Reconnection

1. Go to WordPress admin → Media → AltText AI
2. Click "Logout" (if logged in)
3. Click "Login" and re-authenticate
4. This will force a fresh API connection

## Render Service Management

### Check Service Status (via Render Dashboard)

1. Log in to https://dashboard.render.com
2. Navigate to `alttext-ai-backend` service
3. Check:
   - **Status:** Should be "Live"
   - **Last Deploy:** Should be recent
   - **Logs:** Check for errors

### Restart Service

If service is in "Suspended" or "Failed" state:

1. Click "Manual Deploy" → "Deploy latest commit"
2. Or use Render API:
   ```bash
   curl -X POST https://api.render.com/v1/services/YOUR_SERVICE_ID/resume \
     -H "Authorization: Bearer YOUR_API_KEY"
   ```

### View Logs

```bash
# Via Render CLI
render logs -s alttext-ai-backend -n 100
```

## Hosting Provider Specific

### WP Engine
- May need to whitelist external domains via support ticket

### SiteGround
- Check "Site Tools" → "Security" → "Firewall"
- Whitelist alttext-ai-backend.onrender.com

### Kinsta
- Usually works out of the box
- If issues, contact support

### Cloudways
- Check "Settings & Packages" → "Packages"
- Ensure PHP cURL is enabled

### GoDaddy/Bluehost
- Often have aggressive firewalls
- Contact support to whitelist external APIs

## Contact Support

If all else fails, provide this information to support:

```
Backend URL: https://alttext-ai-backend.onrender.com
Error Message: [paste exact error from WordPress]
Test Results: [paste output from test-backend-connectivity.php]
WordPress Version: [version]
PHP Version: [version]
Hosting Provider: [provider name]
Server Location: [datacenter/country]
```

## Quick Verification Commands

```bash
# 1. Verify backend is up
curl https://alttext-ai-backend.onrender.com/health

# 2. Check DNS
dig alttext-ai-backend.onrender.com +short

# 3. Test HTTPS
openssl s_client -connect alttext-ai-backend.onrender.com:443 -servername alttext-ai-backend.onrender.com

# 4. Check from WordPress server
wp eval 'var_dump(wp_remote_get("https://alttext-ai-backend.onrender.com/health"));'
```

## Monitoring

### Set Up Uptime Monitoring

Use free services to monitor backend availability:
- UptimeRobot: https://uptimerobot.com
- Pingdom: https://www.pingdom.com
- StatusCake: https://www.statuscake.com

Monitor: `https://alttext-ai-backend.onrender.com/health`

Expected response: HTTP 200 with JSON body

---

**Last Updated:** November 5, 2025
**Backend Version:** 2.0.0 (Monetization Phase)
