# Testing Site-Based Licensing

## Quick Verification Checklist

### ✅ Code Verification (Already Done)

The code structure confirms site-based licensing:

1. **Token Storage**: `get_option('opptiai_alt_jwt_token')` - Site-wide, not per-user
2. **Site ID**: `get_option('opptiai_alt_site_id')` - Generated once per WordPress installation
3. **Usage Cache**: `get_transient('opptiai_alt_usage_cache')` - Site-wide cache
4. **API Headers**: `X-Site-ID` sent with every request

---

## Manual Testing Steps

### Test 1: Verify Site-Wide Authentication

1. **Login as Admin A**:
   - Go to WordPress Admin → Media → AltText AI
   - Click "Login" button
   - Log in with your OpptiAI account
   - ✅ Verify you see "Logged in" status

2. **Check as Different User (Admin B)**:
   - Log out of WordPress
   - Log in as a different admin user (Admin B)
   - Go to WordPress Admin → Media → AltText AI
   - ✅ Verify Admin B also sees "Logged in" status (without logging in themselves)
   - ✅ Verify Admin B sees the same account email/plan as Admin A

3. **Check as Editor/Author**:
   - Log out of WordPress
   - Log in as an Editor or Author (non-admin)
   - Go to WordPress Admin → Media → AltText AI
   - ✅ Verify Editor/Author sees "Logged in" status
   - ✅ Verify they can see usage stats

### Test 2: Verify Shared Usage Quota

1. **Check Initial Usage**:
   - As Admin A, note the current usage (e.g., "5 of 50 images")
   - ✅ Record: Used: ___, Limit: ___, Remaining: ___

2. **Generate Alt Text as Admin A**:
   - Generate alt text for 1 image
   - ✅ Verify usage updates (e.g., "6 of 50 images")

3. **Check Usage as Admin B**:
   - Log in as Admin B (different user)
   - Go to AltText AI dashboard
   - ✅ Verify Admin B sees the same updated usage (e.g., "6 of 50 images")
   - ✅ Verify the usage matches what Admin A saw

4. **Generate Alt Text as Admin B**:
   - Generate alt text for 1 image as Admin B
   - ✅ Verify usage updates (e.g., "7 of 50 images")

5. **Check Usage as Editor/Author**:
   - Log in as Editor/Author
   - Go to AltText AI dashboard
   - ✅ Verify Editor/Author sees the same usage (e.g., "7 of 50 images")
   - ✅ Verify they can generate alt text (uses shared quota)

### Test 3: Verify Site ID Generation

**Via WordPress Database**:
```sql
SELECT option_name, option_value 
FROM wp_options 
WHERE option_name = 'opptiai_alt_site_id';
```

**Expected Result**:
- ✅ `opptiai_alt_site_id` exists
- ✅ Value is a 32-character MD5 hash
- ✅ Same value for all users on the site

**Via PHP (in WordPress)**:
```php
$site_id = get_option('opptiai_alt_site_id');
echo "Site ID: " . $site_id;
```

### Test 4: Verify API Requests Include Site ID

**Check Network Tab in Browser**:
1. Open browser DevTools (F12)
2. Go to Network tab
3. Generate alt text for an image
4. Find the API request to `/api/generate`
5. Check Request Headers
6. ✅ Verify `X-Site-ID` header is present
7. ✅ Verify `X-Site-URL` header is present
8. ✅ Verify `Authorization: Bearer ...` header is present

### Test 5: Verify Disconnect Affects All Users

1. **As Admin A**:
   - Go to Settings tab
   - Click "Disconnect Account"
   - Confirm disconnection

2. **As Admin B**:
   - Log in as Admin B
   - Go to AltText AI dashboard
   - ✅ Verify Admin B sees "Not logged in" status
   - ✅ Verify Admin B cannot generate alt text

3. **As Editor/Author**:
   - Log in as Editor/Author
   - Go to AltText AI dashboard
   - ✅ Verify Editor/Author sees "Not logged in" status

---

## Expected Results

### ✅ All Tests Should Pass If:

1. **Authentication is Site-Wide**:
   - One user logs in → All users see "Logged in"
   - Token stored in `wp_options`, not `wp_usermeta`

2. **Usage is Shared**:
   - All users see the same usage quota
   - Generating alt text as any user updates the same quota
   - Usage cache is site-wide

3. **Site ID is Consistent**:
   - Same Site ID for all users
   - Site ID sent with every API request
   - Site ID persists across user sessions

4. **Disconnect Affects All**:
   - Disconnecting clears token for all users
   - All users see "Not logged in" after disconnect

---

## Troubleshooting

### Issue: Different users see different usage

**Possible Causes**:
- Browser cache (clear cache and hard refresh)
- Transient cache expired (wait 5 minutes or force refresh)
- Backend not tracking by Site ID (backend issue)

**Fix**:
- Clear browser cache
- Check `wp_options` table for `opptiai_alt_usage_cache` transient
- Verify backend API is using `X-Site-ID` header

### Issue: User B doesn't see "Logged in" after User A logs in

**Possible Causes**:
- Token not stored in `wp_options` (check database)
- User B's browser cache (clear cache)
- Plugin not loading API client correctly

**Fix**:
- Check `wp_options` for `opptiai_alt_jwt_token`
- Clear browser cache
- Verify plugin is active and loaded

### Issue: Site ID not generated

**Possible Causes**:
- No API requests made yet (Site ID generated on first request)
- Database write permissions issue

**Fix**:
- Make one API request (login or generate alt text)
- Check database permissions
- Verify `wp_options` table is writable

---

## Database Verification

### Check Site-Wide Storage

```sql
-- Check token (should exist if logged in)
SELECT option_name, LEFT(option_value, 20) as token_preview
FROM wp_options 
WHERE option_name = 'opptiai_alt_jwt_token';

-- Check site ID (should exist after first API call)
SELECT option_name, option_value as site_id
FROM wp_options 
WHERE option_name = 'opptiai_alt_site_id';

-- Check user data (should exist if logged in)
SELECT option_name, option_value
FROM wp_options 
WHERE option_name = 'opptiai_alt_user_data';

-- Check usage cache (transient, may not exist)
SELECT option_name, option_value
FROM wp_options 
WHERE option_name LIKE '_transient_opptiai_alt_usage_cache%';
```

### Verify NOT in User Meta

```sql
-- This should return NO results (token not per-user)
SELECT user_id, meta_key, LEFT(meta_value, 20) as token_preview
FROM wp_usermeta 
WHERE meta_key LIKE '%jwt_token%' OR meta_key LIKE '%opptiai%';
```

---

## Automated Test Script

Run via WP-CLI:
```bash
wp eval-file test-site-licensing.php
```

Or create a test page in WordPress admin (temporary):
1. Add this to `functions.php` temporarily:
```php
add_action('admin_menu', function() {
    add_submenu_page(
        'tools.php',
        'Test Site Licensing',
        'Test Site Licensing',
        'manage_options',
        'test-site-licensing',
        function() {
            include plugin_dir_path(__FILE__) . 'test-site-licensing.php';
        }
    );
});
```

---

## Success Criteria

✅ **Site-based licensing is working correctly if:**

1. ✅ One user logs in → All users see authenticated
2. ✅ All users see the same usage quota
3. ✅ Any user can generate alt text (uses shared quota)
4. ✅ Site ID is generated and consistent
5. ✅ Disconnect affects all users
6. ✅ Token stored in `wp_options` (not `wp_usermeta`)
7. ✅ Usage cache is site-wide (transients)

If all criteria pass, site-based licensing is working correctly! 🎉

