# Forgot Password Debugging Summary

## Current Status
- ✅ Plugin code is working (AJAX handler receives request)
- ✅ Frontend JavaScript is configured
- ❓ Backend response needs investigation
- ❓ Email sending status unknown

## Debugging Added

### 1. PHP Logging (WordPress)
All logs will appear in `wp-content/debug.log` with prefix `[BBAI DEBUG]`:
- AJAX handler entry point
- Email validation
- API client calls
- Full backend request/response

### 2. JavaScript Console Logging
All logs appear in browser console with prefix `[AltText AI]`:
- Form submission
- AJAX request details
- Full response data
- Error details

### 3. Backend API Testing
Test script created: `test-forgot-password.php`

## How to Debug

### Step 1: Enable WordPress Debug
Add to `wp-config.php`:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

### Step 2: Check Logs After Submitting Form

**WordPress Debug Log:**
```bash
tail -f wp-content/debug.log | grep "BBAI DEBUG"
```

**Browser Console:**
- Open DevTools (F12)
- Filter console by: `AltText AI`
- Submit forgot password form
- Check all `[AltText AI]` messages

**Network Tab:**
- Filter by: `admin-ajax`
- Click the request
- Check Response tab

### Step 3: Test Backend Directly
```bash
php test-forgot-password.php
```

Or test production backend:
```bash
curl -X POST https://alttext-ai-backend.onrender.com/auth/forgot-password \
  -H "Content-Type: application/json" \
  -d '{"email":"benoats@gmail.com","siteUrl":"http://localhost"}'
```

## What to Look For

1. **Is the request reaching WordPress?**
   - Look for: `[BBAI DEBUG] ajax_forgot_password called`

2. **Is the backend being called?**
   - Look for: `[BBAI DEBUG] make_request called for forgot-password`
   - Check: `[BBAI DEBUG] Full URL: ...`

3. **What is the backend returning?**
   - Look for: `[BBAI DEBUG] Response body: ...`
   - Check browser console: `[AltText AI] Parsed response data:`

4. **Is email being sent?**
   - Check backend logs for Resend API calls
   - Look for `emailSent: false` in response
   - Check for `error` field in response

## Common Issues

1. **Backend returns success but no email:**
   - Resend API key not configured
   - Resend from email not set
   - Domain not verified in Resend
   - Backend not calling Resend API

2. **Backend returns 404:**
   - Endpoint not implemented
   - Wrong URL path

3. **Backend returns 500:**
   - Server error
   - Check backend logs

4. **No logs appearing:**
   - WP_DEBUG not enabled
   - Log file permissions issue
   - JavaScript not loading

## Next Steps

1. Submit the forgot password form
2. Check `wp-content/debug.log` for `[BBAI DEBUG]` entries
3. Check browser console for `[AltText AI]` messages
4. Check backend terminal logs
5. Share the findings

