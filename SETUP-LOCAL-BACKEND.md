# Setup Local Backend for Testing

## Problem
The plugin is currently using the production backend (`https://alttext-ai-backend.onrender.com`), so requests aren't reaching your local backend.

## Solution: Configure Plugin to Use Local Backend

### Option 1: Docker Environment Variable (Recommended for Docker)
The `docker-compose.yml` has been updated to set `ALT_API_HOST=http://host.docker.internal:10000`.

**Restart Docker:**
```bash
docker-compose down
docker-compose up -d
```

### Option 2: WordPress Constant
Add to your WordPress `wp-config.php`:
```php
define( 'ALT_API_HOST', 'http://host.docker.internal:10000' );
```

**Note:** If WordPress is running in Docker, use `host.docker.internal` to access the host machine's localhost.

### Option 3: Environment Variable
Set before starting WordPress:
```bash
export ALT_API_HOST=http://localhost:10000
```

### Option 4: WordPress Option (Temporary)
You can also set it programmatically via WordPress:
```php
$options = get_option('bbai_options', array());
$options['bbai_alt_api_host'] = 'http://localhost:10000';
update_option('bbai_options', $options);
```

## Verify Configuration

After setting the override, check the logs:
1. Submit forgot password form
2. Check your local backend logs - you should see the request
3. Check WordPress debug log for `[BBAI DEBUG]` entries showing the API URL

## Testing

Once configured, test with:
```bash
php test-forgot-password.php
```

Or submit the form in WordPress and check:
- Local backend logs (should show the request)
- WordPress debug log (should show `[BBAI DEBUG]` entries)
- Browser console (should show `[AltText AI]` messages)

