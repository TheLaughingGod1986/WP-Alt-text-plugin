# Password Reset Troubleshooting Guide

## Issue: Can't Reset Password

### Quick Checks

1. **Check Browser Console**
   - Open Developer Tools (F12)
   - Go to Console tab
   - Look for any red error messages when submitting the form
   - Check Network tab for the request to `admin-ajax.php`

2. **Check Backend Endpoints**
   The password reset requires these backend endpoints:
   - `POST /auth/forgot-password` - To request reset email
   - `POST /auth/reset-password` - To complete password reset

### Common Issues & Solutions

#### Issue 1: "Failed to send reset link" or Generic Error
**Possible Causes:**
- Backend endpoint `/auth/forgot-password` not implemented
- Backend endpoint returning error
- Network/CORS issues

**Solution:**
1. Check if backend has the endpoint: `curl -X POST https://alttext-ai-backend.onrender.com/auth/forgot-password -H "Content-Type: application/json" -d '{"email":"test@example.com"}'`
2. If you get 404, the endpoint isn't implemented yet
3. If you get 500, check backend logs
4. See BACKEND_INTEGRATION.md for exact API requirements

#### Issue 2: Loading Button Stuck
**Fix Applied:**
- Added `setLoading(form, false)` in all success/error paths
- Should be resolved in latest commit

#### Issue 3: "Configuration error"
**Possible Cause:**
- `window.alttextai_ajax` not loaded
- WordPress AJAX not configured

**Solution:**
- Refresh the page
- Check that `assets/auth-modal.js` is enqueued
- Check browser console for JS errors

#### Issue 4: No Email Received
**Possible Causes:**
1. Backend email service not configured
2. Email in spam folder
3. Email address doesn't exist in database
4. Backend endpoint returns success but doesn't send email

**Solution:**
1. Check spam folder
2. Verify email address is correct
3. Check backend logs for email sending errors
4. Test backend endpoint directly (see curl command above)

### Testing Password Reset

#### Test Forgot Password Request:
```bash
curl -X POST https://alttext-ai-backend.onrender.com/auth/forgot-password \
  -H "Content-Type: application/json" \
  -d '{"email":"your-email@example.com"}'
```

**Expected Response:**
```json
{
  "success": true,
  "data": {
    "message": "Password reset email sent successfully"
  }
}
```

#### Test Reset Password:
```bash
curl -X POST https://alttext-ai-backend.onrender.com/auth/reset-password \
  -H "Content-Type: application/json" \
  -d '{
    "email":"your-email@example.com",
    "token":"reset-token-from-email",
    "newPassword":"NewSecurePassword123!"
  }'
```

**Expected Response:**
```json
{
  "success": true,
  "data": {
    "message": "Password reset successfully"
  }
}
```

### Frontend Flow

1. User clicks "Forgot password?" link
2. `showForgotPasswordForm()` displays forgot password form
3. User enters email and submits
4. `handleForgotPassword()` sends AJAX request to WordPress:
   - Action: `alttextai_forgot_password`
   - WordPress calls `ajax_forgot_password()` in PHP
   - PHP calls `$api_client->forgot_password($email)`
   - API client makes request to backend `/auth/forgot-password`
5. Backend sends email with reset link
6. User clicks link in email (contains `reset-token` and `email` URL params)
7. Frontend detects URL params and shows reset password form
8. User enters new password and submits
9. `handleResetPassword()` sends AJAX request:
   - Action: `alttextai_reset_password`
   - WordPress calls `ajax_reset_password()` in PHP
   - PHP calls `$api_client->reset_password($email, $token, $password)`
   - API client makes request to backend `/auth/reset-password`
10. Backend validates token and updates password
11. User redirected to login

### Debug Steps

1. **Check Frontend Console:**
   - Open browser DevTools
   - Look for JavaScript errors
   - Check Network tab for failed requests

2. **Check Backend Logs:**
   - Check Render logs or your backend hosting logs
   - Look for errors on `/auth/forgot-password` or `/auth/reset-password`

3. **Test Backend Directly:**
   - Use curl commands above
   - Verify endpoints exist and return expected responses

4. **Check Email Service:**
   - Verify backend email service is configured (SendGrid, SMTP, etc.)
   - Check email service logs

### Next Steps if Still Not Working

1. **Backend Not Implemented:**
   - See `BACKEND_INTEGRATION.md` for exact API requirements
   - Backend team needs to implement the two endpoints

2. **Backend Returns Errors:**
   - Check backend error message in frontend
   - Fix backend issue based on error

3. **Network Issues:**
   - Check backend URL is correct in `class-api-client-v2.php`
   - Verify CORS is configured on backend
   - Check if backend is online

### Required Backend Implementation

The backend MUST implement:
1. `POST /auth/forgot-password` - Send reset email
2. `POST /auth/reset-password` - Complete password reset

See `BACKEND_INTEGRATION.md` for complete API specification.

