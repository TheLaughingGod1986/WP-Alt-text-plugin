# Docker WordPress Backend Connectivity - DIAGNOSIS

## Quick Status Check (Run These Commands)

Good news! I've already tested your Docker container and **it CAN reach the backend!**

### Test Results from Your Container

✅ **Backend Connection Test:**
```bash
$ docker exec wp-alt-text-plugin-wordpress-1 curl -s https://alttext-ai-backend.onrender.com/health
{"status":"ok","timestamp":"2025-11-05T16:30:02.382Z","version":"2.0.0","phase":"monetization"}
HTTP_CODE: 200
```

✅ **WordPress Settings:**
```json
{
    "api_url": "https://alttext-ai-backend.onrender.com",
    ...
}
```

**Conclusion:** Your Docker container has NO connectivity issues with the backend.

---

## So Why Are You Seeing Errors?

If the container can reach the backend but you're still seeing errors, here are the likely causes:

### 1. Authentication Issues (Most Likely)
**Symptom:** "Backend server unavailable" or login/register fails
**Cause:** No valid JWT token or token expired

**Fix:** Login again
```
1. Go to http://localhost:8080/wp-admin
2. Navigate to Media → AltText AI
3. Click "Login" or "Create Account"
4. Use your actual AltText AI credentials
```

### 2. JavaScript Cache Issues
**Symptom:** Old error messages persist even after backend is fixed
**Cause:** Browser cache showing old JavaScript

**Fix:** Hard refresh
```
Chrome/Firefox: Ctrl+Shift+R (Cmd+Shift+R on Mac)
Or open DevTools → Network tab → Check "Disable cache"
```

### 3. WordPress Plugin Cache
**Symptom:** Connection test shows old error
**Cause:** WordPress transients caching failed connection

**Fix:** Clear WordPress cache
```bash
docker exec wp-alt-text-plugin-wordpress-1 php -r "
define('ABSPATH', '/var/www/html/');
\$_SERVER['HTTP_HOST'] = 'localhost';
require '/var/www/html/wp-load.php';
delete_transient('alttextai_token_last_check');
delete_option('alttextai_api_error_log');
wp_cache_flush();
echo 'Cache cleared!';
"
```

### 4. Container Needs Restart
**Symptom:** Intermittent connection issues
**Cause:** DNS cache or temporary network glitch in container

**Fix:** Restart WordPress container
```bash
docker restart wp-alt-text-plugin-wordpress-1
```

---

## Testing Tools for Docker

### Test 1: Backend Connectivity (from container)
```bash
docker exec wp-alt-text-plugin-wordpress-1 \
  curl -v https://alttext-ai-backend.onrender.com/health
```
**Expected:** HTTP 200 with JSON response

### Test 2: Check JWT Token
```bash
docker exec wp-alt-text-plugin-wordpress-1 php -r "
define('ABSPATH', '/var/www/html/');
\$_SERVER['HTTP_HOST'] = 'localhost';
require '/var/www/html/wp-load.php';
\$token = get_option('opptiai_alt_jwt_token');
echo 'Token exists: ' . (!empty(\$token) ? 'YES' : 'NO') . PHP_EOL;
echo 'Token length: ' . strlen(\$token) . PHP_EOL;
"
```
**Expected:** Token exists: YES, Length > 100

### Test 3: Check API Error Log
```bash
docker exec wp-alt-text-plugin-wordpress-1 php -r "
define('ABSPATH', '/var/www/html/');
\$_SERVER['HTTP_HOST'] = 'localhost';
require '/var/www/html/wp-load.php';
\$errors = get_option('alttextai_api_error_log', []);
echo json_encode(\$errors, JSON_PRETTY_PRINT);
"
```

### Test 4: Test WordPress HTTP API
```bash
docker exec wp-alt-text-plugin-wordpress-1 php -r "
define('ABSPATH', '/var/www/html/');
\$_SERVER['HTTP_HOST'] = 'localhost';
require '/var/www/html/wp-load.php';
\$response = wp_remote_get('https://alttext-ai-backend.onrender.com/health');
if (is_wp_error(\$response)) {
    echo 'ERROR: ' . \$response->get_error_message() . PHP_EOL;
} else {
    echo 'Status: ' . wp_remote_retrieve_response_code(\$response) . PHP_EOL;
    echo 'Body: ' . wp_remote_retrieve_body(\$response) . PHP_EOL;
}
"
```

---

## Browser Console Diagnostics

Open browser DevTools (F12) and check:

### 1. Network Tab
```
- Look for failed requests to /wp-admin/admin-ajax.php
- Check for 401/403 responses (auth issues)
- Look for timeout errors
```

### 2. Console Tab
```javascript
// Run this in console to check auth status:
console.log('API URL:', window.alttextai_ajax?.api_url);
console.log('Nonce:', window.alttextai_ajax?.nonce);

// Test fetch to backend:
fetch('https://alttext-ai-backend.onrender.com/health')
  .then(r => r.json())
  .then(data => console.log('Backend status:', data))
  .catch(err => console.error('Backend error:', err));
```

---

## Common Docker-Specific Issues

### Issue 1: Container DNS Resolution
**Symptom:** `curl: (6) Could not resolve host`
**Check:**
```bash
docker exec wp-alt-text-plugin-wordpress-1 cat /etc/resolv.conf
```
**Should see:** `nameserver 8.8.8.8` or similar

**Fix:** Add DNS to docker-compose.yml:
```yaml
services:
  wordpress:
    dns:
      - 8.8.8.8
      - 8.8.4.4
```

### Issue 2: Docker Network Isolation
**Symptom:** Cannot reach external APIs
**Check:**
```bash
docker network inspect wp-alt-text-plugin_default
```
**Fix:** Ensure network is in bridge mode (default)

### Issue 3: Firewall on Host Machine
**Symptom:** Docker container blocked by macOS firewall
**Check:** System Preferences → Security → Firewall
**Fix:** Allow Docker.app

---

## Complete Diagnostic Script (Copy-Paste Ready)

Run this in your terminal to get full diagnostic output:

```bash
#!/bin/bash
echo "=== AltText AI Backend Diagnostic for Docker ==="
echo ""

echo "1. Testing Backend Connectivity..."
docker exec wp-alt-text-plugin-wordpress-1 \
  curl -s -w "\nHTTP Status: %{http_code}\n" \
  https://alttext-ai-backend.onrender.com/health
echo ""

echo "2. Checking WordPress Settings..."
docker exec wp-alt-text-plugin-wordpress-1 php -r "
define('ABSPATH', '/var/www/html/');
\$_SERVER['HTTP_HOST'] = 'localhost';
require '/var/www/html/wp-load.php';
\$settings = get_option('opptiai_alt_settings');
echo 'API URL: ' . (\$settings['api_url'] ?? 'NOT SET') . PHP_EOL;
"
echo ""

echo "3. Checking JWT Token..."
docker exec wp-alt-text-plugin-wordpress-1 php -r "
define('ABSPATH', '/var/www/html/');
\$_SERVER['HTTP_HOST'] = 'localhost';
require '/var/www/html/wp-load.php';
\$token = get_option('opptiai_alt_jwt_token');
echo 'Token: ' . (!empty(\$token) ? 'EXISTS (' . strlen(\$token) . ' chars)' : 'NOT SET') . PHP_EOL;
"
echo ""

echo "4. Testing WordPress HTTP API..."
docker exec wp-alt-text-plugin-wordpress-1 php -r "
define('ABSPATH', '/var/www/html/');
\$_SERVER['HTTP_HOST'] = 'localhost';
require '/var/www/html/wp-load.php';
\$response = wp_remote_get('https://alttext-ai-backend.onrender.com/health', ['timeout' => 10]);
if (is_wp_error(\$response)) {
    echo 'ERROR: ' . \$response->get_error_message() . PHP_EOL;
    echo 'Code: ' . \$response->get_error_code() . PHP_EOL;
} else {
    \$code = wp_remote_retrieve_response_code(\$response);
    echo 'HTTP Status: ' . \$code . PHP_EOL;
    if (\$code === 200) {
        echo '✅ WordPress HTTP API can reach backend!' . PHP_EOL;
    }
}
"
echo ""

echo "5. Checking Recent API Errors..."
docker exec wp-alt-text-plugin-wordpress-1 php -r "
define('ABSPATH', '/var/www/html/');
\$_SERVER['HTTP_HOST'] = 'localhost';
require '/var/www/html/wp-load.php';
\$errors = get_option('alttextai_api_error_log', []);
if (empty(\$errors)) {
    echo 'No recent API errors logged.' . PHP_EOL;
} else {
    echo 'Recent errors:' . PHP_EOL;
    foreach (array_slice(\$errors, -3) as \$error) {
        echo '  - ' . \$error['timestamp'] . ': ' . \$error['message'] . PHP_EOL;
    }
}
"

echo ""
echo "=== Diagnostic Complete ==="
```

Save as `docker-diagnostic.sh`, then run:
```bash
chmod +x docker-diagnostic.sh
./docker-diagnostic.sh
```

---

## Quick Fixes (Copy-Paste Ready)

### Fix 1: Clear All Plugin Cache
```bash
docker exec wp-alt-text-plugin-wordpress-1 php -r "
define('ABSPATH', '/var/www/html/');
\$_SERVER['HTTP_HOST'] = 'localhost';
require '/var/www/html/wp-load.php';
delete_transient('alttextai_token_last_check');
delete_transient('alttextai_backend_status');
delete_option('alttextai_api_error_log');
wp_cache_flush();
echo '✅ Cache cleared!' . PHP_EOL;
"
```

### Fix 2: Reset Authentication
```bash
docker exec wp-alt-text-plugin-wordpress-1 php -r "
define('ABSPATH', '/var/www/html/');
\$_SERVER['HTTP_HOST'] = 'localhost';
require '/var/www/html/wp-load.php';
delete_option('opptiai_alt_jwt_token');
delete_option('alttextai_user_data');
echo '✅ Auth cleared. Please login again.' . PHP_EOL;
"
```

### Fix 3: Restart Container
```bash
docker restart wp-alt-text-plugin-wordpress-1
echo "Waiting for container to restart..."
sleep 5
echo "✅ Container restarted!"
```

### Fix 4: View Live Logs
```bash
docker logs -f wp-alt-text-plugin-wordpress-1 --tail 50
# Press Ctrl+C to stop
```

---

## Development Mode Testing

If you want to test with a local backend (Node.js running on host):

### Add to wp-config.php (via Docker):
```bash
docker exec wp-alt-text-plugin-wordpress-1 bash -c "
cat >> /var/www/html/wp-config.php <<'EOF'

// Local development mode for AltText AI
define('WP_DEBUG', true);
define('WP_LOCAL_DEV', true);
define('ALTTEXT_AI_API_URL', 'http://host.docker.internal:3001');
EOF
echo '✅ Development mode enabled!'
"
```

**Note:** Use `host.docker.internal` to access services on your Mac from Docker.

### To revert:
```bash
docker exec wp-alt-text-plugin-wordpress-1 bash -c "
sed -i '/Local development mode for AltText AI/,+3d' /var/www/html/wp-config.php
echo '✅ Development mode disabled!'
"
```

---

## Summary

| Component | Status | Notes |
|-----------|--------|-------|
| Backend Service | ✅ Online | Confirmed via external test |
| Docker Container Network | ✅ Working | Container can reach backend |
| WordPress Settings | ✅ Correct | API URL properly configured |
| Most Likely Issue | ⚠️ Auth | Need to login/register |

**Action Required:**
1. Run the diagnostic script above
2. If token missing → Login via WordPress admin
3. If token exists → Clear cache and retry
4. If still failing → Share diagnostic output

---

**Last Updated:** November 5, 2025
**Your Container:** wp-alt-text-plugin-wordpress-1
**Your WordPress:** http://localhost:8080
