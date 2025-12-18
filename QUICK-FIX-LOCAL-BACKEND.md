# Quick Fix: Use Local Backend

## The Problem
Your plugin is using the production backend (`https://alttext-ai-backend.onrender.com`), so requests aren't reaching your local backend on `localhost:10000`.

## Quick Solution

### Step 1: Verify Docker Environment Variable
The `docker-compose.yml` has been updated. Restart WordPress:
```bash
docker-compose restart wordpress
```

### Step 2: Check API URL
Visit this URL in your browser (while logged into WordPress):
```
http://localhost:8080/wp-content/plugins/beepbeep-ai-alt-text-generator/check-api-url.php
```

This will show which API URL the plugin is actually using.

### Step 3: If Still Using Production Backend

**Option A: Set WordPress Option (Easiest)**
Add this to your WordPress theme's `functions.php` or create a simple plugin:

```php
add_action('init', function() {
    $options = get_option('bbai_options', array());
    $options['bbai_alt_api_host'] = 'http://host.docker.internal:10000';
    update_option('bbai_options', $options);
});
```

**Option B: Add to wp-config.php**
If you can access `wp-config.php` in your Docker container:
```php
define('ALT_API_HOST', 'http://host.docker.internal:10000');
```

### Step 4: Test
1. Submit forgot password form
2. Check your local backend logs - you should see the request
3. Check WordPress debug log for `[BBAI DEBUG]` entries

## Verify It's Working

After configuration, you should see in WordPress debug log:
```
[BBAI DEBUG] API Client initialized with URL: http://host.docker.internal:10000
```

And in your local backend logs, you should see:
```
POST /auth/forgot-password
```

## Troubleshooting

**If `host.docker.internal` doesn't work:**
- Try `172.17.0.1:10000` (Docker bridge IP)
- Or use your Mac's IP address: `192.168.x.x:10000`

**To find your Mac's IP:**
```bash
ifconfig | grep "inet " | grep -v 127.0.0.1
```

