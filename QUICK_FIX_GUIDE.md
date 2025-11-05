# Quick Fix Guide: Backend "Unavailable" Error

## TL;DR - Backend is FINE, WordPress Server is the Problem

✅ Backend: **ONLINE** (confirmed via external testing)
❌ WordPress Server: **Cannot reach backend** (DNS/network issue)

---

## For WordPress Site Admins

### Step 1: Run Diagnostic (2 minutes)

1. Download `test-backend-connectivity.php` from this repo
2. Upload to your WordPress root directory (same folder as wp-config.php)
3. Visit: `https://yoursite.com/test-backend-connectivity.php`
4. **Screenshot the results** and send to your hosting provider

### Step 2: Quick Fixes (try in order)

#### Fix 1: Clear Server DNS Cache (Most Common Solution)
```bash
# Via SSH to your server:
sudo systemctl restart php-fpm  # or php7.4-fpm, php8.0-fpm, etc.
sudo systemctl restart nginx    # or apache2
sudo systemctl restart systemd-resolved  # if using systemd
```

#### Fix 2: Check WordPress Configuration
Edit `wp-config.php` and ensure these lines are NOT blocking external APIs:
```php
// Add these if they don't exist, or verify they're set correctly:
define('WP_HTTP_BLOCK_EXTERNAL', false);
define('WP_ACCESSIBLE_HOSTS', '*');
```

#### Fix 3: Test from Command Line
```bash
# SSH into your WordPress server, then run:
curl https://alttext-ai-backend.onrender.com/health

# Should return:
# {"status":"ok","timestamp":"...","version":"2.0.0","phase":"monetization"}
```

If `curl` fails with the same error, it's definitely your server configuration.

#### Fix 4: Contact Hosting Provider
Send them this message:

```
Subject: Need to whitelist external API domain

Hi,

My WordPress site needs to connect to an external API for a plugin.
Please whitelist the following domain:

Domain: alttext-ai-backend.onrender.com
IPs: 216.24.57.251, 216.24.57.7 (Cloudflare CDN)
Port: 443 (HTTPS)
Purpose: AI image alt text generation API

The backend is confirmed operational from external networks, but
my WordPress server cannot resolve DNS or connect. This may be
due to firewall rules or DNS configuration.

Can you please verify:
1. Outbound HTTPS (port 443) is allowed
2. DNS resolution is working
3. No firewall blocking this domain

Thank you!
```

---

## For Plugin Developers

### What I've Provided

1. **test-backend-connectivity.php** - Complete diagnostic tool
   - Tests DNS resolution
   - Checks cURL availability
   - Tests WordPress HTTP API
   - Shows server environment

2. **BACKEND_TROUBLESHOOTING.md** - Complete troubleshooting guide
   - Error code explanations
   - Hosting-specific instructions
   - Render service management

3. **BACKEND_STATUS_REPORT.md** - Full diagnostic report
   - Test results from external network
   - Backend confirmed operational
   - Root cause analysis

### Backend Status
```
URL: https://alttext-ai-backend.onrender.com
Status: ✅ ONLINE
DNS: ✅ Resolving (216.24.57.251, 216.24.57.7)
SSL: ✅ Valid
Latency: ~5ms
Health: ✅ /health returns HTTP 200
```

### NO ACTION NEEDED on Backend
The Render service is:
- ✅ Running
- ✅ Responding
- ✅ DNS configured correctly
- ✅ SSL certificates valid

### Recommended Plugin Improvements

1. **Add "Test Connection" Button** in settings
   ```php
   // Test backend connectivity and show detailed error
   function test_backend_connection() {
       $api_client = new AltText_AI_API_Client_V2();
       $response = wp_remote_get('https://alttext-ai-backend.onrender.com/health');

       if (is_wp_error($response)) {
           echo "Error: " . $response->get_error_message();
           echo "Code: " . $response->get_error_code();
       } else {
           echo "✅ Backend is reachable!";
       }
   }
   ```

2. **Better Error Messages**
   Instead of: "Backend server is currently unavailable"
   Show: "Cannot connect to backend. This is likely a server configuration issue. [Click here for troubleshooting guide]"

3. **Log Connection Attempts**
   Track failed connections with timestamps to help diagnose intermittent issues.

---

## Common Hosting Providers

### WP Engine
- May need to whitelist via support ticket
- Support: https://wpengine.com/support/

### SiteGround
- Check "Site Tools" → "Security" → "Firewall"
- Add alttext-ai-backend.onrender.com to whitelist

### Kinsta
- Usually works out of the box
- If issues, contact: https://kinsta.com/kinsta-support/

### Cloudways
- Check PHP settings include cURL
- Firewall rules should allow outbound HTTPS

### GoDaddy / Bluehost
- Known to have aggressive firewalls
- Contact support to whitelist external APIs

---

## Verification

After applying fixes, verify connection:

### Via Browser
Visit WordPress admin → Media → AltText AI
Try to login/register - should work without errors

### Via Diagnostic Script
Re-run `test-backend-connectivity.php`
All tests should show ✅ green checkmarks

### Via WP-CLI
```bash
wp eval 'var_dump(wp_remote_get("https://alttext-ai-backend.onrender.com/health"));'
# Should return array with status 200 and body containing {"status":"ok",...}
```

---

## Still Not Working?

If none of the above fixes work:

1. **Run full diagnostic** with `test-backend-connectivity.php`
2. **Check WordPress debug log** (`wp-content/debug.log`)
3. **Check server error logs** (`/var/log/nginx/error.log` or `/var/log/apache2/error.log`)
4. **Contact hosting provider** with diagnostic results

---

## Summary

| Component | Status | Action Required |
|-----------|--------|-----------------|
| Backend Service | ✅ Online | None |
| DNS Resolution | ✅ Working | None |
| SSL/HTTPS | ✅ Valid | None |
| WordPress Server | ❌ Cannot connect | User/hosting fix needed |

**Bottom Line:** Backend is perfect. WordPress server needs configuration fix.

---

**Files to Share with Users:**
1. `test-backend-connectivity.php` - Main diagnostic tool
2. `BACKEND_TROUBLESHOOTING.md` - Complete guide
3. `QUICK_FIX_GUIDE.md` - This file (quick reference)

**Last Updated:** November 5, 2025
