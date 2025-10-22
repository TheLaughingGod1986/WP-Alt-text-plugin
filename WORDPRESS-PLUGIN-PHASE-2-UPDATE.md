# WordPress Plugin Phase 2 Update - Complete

**Date:** October 21, 2025
**Version:** 4.0.0
**Status:** ✅ COMPLETE

---

## Summary

The WordPress plugin has been successfully updated to support Phase 2 JWT authentication, user accounts, and Stripe billing integration. The plugin is now ready for integration testing with the Phase 2 backend.

---

## What Was Changed

### 1. API Client Upgrade ✅

**Files Modified:**
- [ai-alt-gpt.php:17-18](ai-alt-gpt.php#L17-L18) - Added v2 API client
- [ai-alt-gpt.php:40](ai-alt-gpt.php#L40) - Switched to `AltText_AI_API_Client_V2`

**Changes:**
```php
// OLD (Phase 1)
$this->api_client = new AltText_AI_API_Client();

// NEW (Phase 2)
$this->api_client = new AltText_AI_API_Client_V2();
```

**Impact:**
- All API calls now use JWT authentication
- Authorization headers automatically added
- Token management handled automatically
- Backwards incompatible with Phase 1 backend

---

### 2. Authentication UI Assets ✅

**Files Modified:**
- [ai-alt-gpt.php:2634-2648](ai-alt-gpt.php#L2634-L2648) - Enqueued auth modal CSS/JS

**New Assets Loaded:**
- `assets/auth-modal.css` - Authentication modal styles
- `assets/auth-modal.js` - Authentication modal logic (login/register)

**When Loaded:**
- Only on `media_page_ai-alt-gpt` (main dashboard)
- Automatically included alongside dashboard assets

---

### 3. AJAX Handlers for Authentication ✅

**Files Modified:**
- [ai-alt-gpt.php:76-82](ai-alt-gpt.php#L76-L82) - Registered AJAX actions
- [ai-alt-gpt.php:3006-3146](ai-alt-gpt.php#L3006-L3146) - Implemented handlers

**New AJAX Endpoints:**

| Action | Handler | Purpose |
|--------|---------|---------|
| `alttextai_register` | `ajax_register()` | Create new user account |
| `alttextai_login` | `ajax_login()` | Login existing user |
| `alttextai_logout` | `ajax_logout()` | Logout and clear token |
| `alttextai_get_user_info` | `ajax_get_user_info()` | Get current user & usage |
| `alttextai_create_checkout` | `ajax_create_checkout()` | Create Stripe checkout |
| `alttextai_create_portal` | `ajax_create_portal()` | Access billing portal |

**Usage Example:**
```javascript
// Login via AJAX
jQuery.post(ajaxurl, {
    action: 'alttextai_login',
    nonce: alttextai_ajax.nonce,
    email: 'user@example.com',
    password: 'password123'
}, function(response) {
    if (response.success) {
        console.log('Logged in:', response.data.user);
    }
});
```

---

### 4. JavaScript Integration ✅

**Files Modified:**
- [ai-alt-gpt.php:2687-2697](ai-alt-gpt.php#L2687-L2697) - Updated AJAX variables

**New JavaScript Variables:**
```javascript
window.alttextai_ajax = {
    ajaxurl: '/wp-admin/admin-ajax.php',
    nonce: 'nonce-value',
    api_url: 'http://localhost:3001',        // NEW
    is_authenticated: false,                  // NEW
    user_data: { email, plan, ... }          // NEW
};
```

**Available to:**
- `assets/auth-modal.js` - Authentication modal
- `assets/ai-alt-dashboard.js` - Main dashboard
- `assets/upgrade-modal.js` - Upgrade prompts

---

### 5. Version Update ✅

**Files Modified:**
- [ai-alt-gpt.php:5](ai-alt-gpt.php#L5) - Version bumped to 4.0.0
- [ai-alt-gpt.php:4](ai-alt-gpt.php#L4) - Updated description

**Old:**
```
Version: 3.1.0
Description: ...Get 10 free generations per month...
```

**New:**
```
Version: 4.0.0
Description: ...Create your free account to get started...
```

---

## Files Involved

### Modified Files
1. **[ai-alt-gpt.php](ai-alt-gpt.php)** - Main plugin file
   - Loaded v2 API client
   - Switched to JWT authentication
   - Added auth modal assets
   - Registered AJAX handlers
   - Implemented auth/billing endpoints
   - Updated version to 4.0.0

### Existing Files (Already Present)
2. **[includes/class-api-client-v2.php](includes/class-api-client-v2.php)** - Phase 2 API client
3. **[assets/auth-modal.js](assets/auth-modal.js)** - Authentication UI logic
4. **[assets/auth-modal.css](assets/auth-modal.css)** - Authentication UI styles

### Unchanged Files
- `includes/class-api-client.php` - Phase 1 client (kept for reference)
- `includes/class-usage-tracker.php` - Still used for caching
- `includes/class-queue.php` - Queue system unchanged
- All other assets - Dashboard, admin, styles

---

## Breaking Changes ⚠️

### 1. **Authentication Required**
- Users MUST create an account to use the plugin
- Anonymous/domain-based usage no longer supported
- Existing users will see login prompt on next use

### 2. **API Incompatibility**
- Plugin now requires Phase 2 backend (JWT)
- Will NOT work with Phase 1 backend (domain-based)
- Backend must be running and accessible

### 3. **Configuration Changes**
- `api_url` setting must point to Phase 2 backend
- Default changed to `http://localhost:3001` for development
- Production deployment needs correct URL

---

## Configuration

### Required Settings

**WordPress Admin → Media → AI Alt Text Generation → Settings:**

```
API URL: http://localhost:3001              (Development)
API URL: https://your-backend.onrender.com  (Production)
```

### Environment Detection

The v2 API client automatically detects the environment:

```php
// Development (localhost/Docker)
$default_url = 'http://host.docker.internal:3001';

// Production
$default_url = $options['api_url'] ?? 'http://localhost:3001';
```

---

## Testing Checklist

### ✅ Backend Tests (Completed)
- [x] Backend API running on port 3001
- [x] Health endpoint responding
- [x] Registration endpoint working
- [x] Login endpoint working
- [x] Alt text generation with JWT working
- [x] Usage tracking working
- [x] Billing endpoints working

### 🔲 WordPress Plugin Tests (Pending)
- [ ] Plugin activates without errors
- [ ] Authentication modal appears
- [ ] User can register new account
- [ ] User can login with existing account
- [ ] Dashboard shows user info and usage
- [ ] Alt text generation works with JWT
- [ ] Usage limits are enforced
- [ ] Upgrade modal shows when limit reached
- [ ] Stripe checkout redirects work
- [ ] Billing portal access works
- [ ] Logout works correctly

---

## User Flow

### First-Time User
1. User installs plugin
2. User navigates to AI Alt Text dashboard
3. **Authentication modal appears automatically**
4. User creates account (email + password)
5. User is logged in automatically
6. Dashboard shows: 10/10 images remaining (Free plan)
7. User generates alt text for images
8. Usage decrements: 9/10, 8/10, etc.

### Existing User
1. User has account from previous session
2. JWT token stored in WordPress options
3. Dashboard loads immediately (no login required)
4. If token expired: login modal appears

### Upgrade Flow
1. User reaches limit (0/10 remaining)
2. Upgrade modal appears
3. User clicks "Upgrade to Pro"
4. AJAX call to `alttextai_create_checkout`
5. Redirect to Stripe checkout
6. After payment: redirect to success page
7. Webhook updates user plan
8. User sees: 1000/1000 images remaining (Pro plan)

---

## API Communication

### Authentication Flow
```
WordPress Plugin ─────→ Phase 2 Backend
                POST /auth/register
                { email, password }

Phase 2 Backend ──────→ WordPress Plugin
                200 OK
                { token, user }

WordPress Plugin
  └─ Stores JWT token in wp_options
  └─ Stores user data in wp_options
```

### Generation Flow
```
WordPress Plugin ─────→ Phase 2 Backend
                POST /api/generate
                Authorization: Bearer {token}
                { image_data, context }

Phase 2 Backend
  └─ Verifies JWT
  └─ Checks usage limits
  └─ Generates alt text
  └─ Decrements usage

Phase 2 Backend ──────→ WordPress Plugin
                200 OK
                { alt_text, usage }

WordPress Plugin
  └─ Updates image alt text
  └─ Updates cached usage
```

---

## Deployment Steps

### 1. Update Backend URL (Required)
```php
// In WordPress admin settings
API URL: https://your-backend.onrender.com
```

### 2. Deploy Backend (Required)
- Deploy Phase 2 backend to Render/Railway
- Update environment variables
- Configure Stripe live keys
- Set up webhooks

### 3. Test Integration
1. Clear WordPress options (fresh start)
2. Navigate to AI Alt Text dashboard
3. Create test account
4. Generate alt text
5. Verify usage tracking
6. Test upgrade flow

---

## Troubleshooting

### Issue: "Authentication Required" appears repeatedly
**Cause:** Backend not accessible or JWT token invalid
**Fix:** Check backend is running and API URL is correct

### Issue: "Failed to generate alt text"
**Cause:** Not authenticated or usage limit reached
**Fix:** Login or upgrade plan

### Issue: Auth modal not appearing
**Cause:** JavaScript not loaded
**Fix:** Check browser console for errors, verify assets enqueued

### Issue: AJAX requests fail with 400/403
**Cause:** Nonce validation failure
**Fix:** Refresh page to get new nonce

---

## Next Steps

### Immediate (Required)
1. ✅ Update plugin files (DONE)
2. 🔲 Deploy backend to production
3. 🔲 Update API URL in plugin settings
4. 🔲 Test authentication flow end-to-end
5. 🔲 Test alt text generation with JWT
6. 🔲 Test upgrade/billing flow

### Secondary (Recommended)
1. 🔲 Update plugin screenshots
2. 🔲 Update plugin documentation
3. 🔲 Create user onboarding guide
4. 🔲 Add error logging/debugging
5. 🔲 Monitor usage analytics

---

## Code Changes Summary

### Lines Added: ~180
### Lines Modified: ~10
### Files Changed: 1
### New AJAX Endpoints: 6

**Diff Summary:**
```diff
+ Load v2 API client
+ Switch to JWT authentication
+ Enqueue auth modal assets
+ Register auth AJAX actions
+ Implement registration handler
+ Implement login handler
+ Implement logout handler
+ Implement user info handler
+ Implement checkout handler
+ Implement portal handler
+ Update version to 4.0.0
+ Update plugin description
```

---

## Backwards Compatibility

### ❌ Not Backward Compatible
- Plugin will NOT work with Phase 1 backend
- Cannot revert to domain-based authentication
- Users must migrate to Phase 2

### Migration Path
1. Backend must be updated to Phase 2 first
2. Then update WordPress plugin to 4.0.0
3. Existing users must create accounts
4. Usage data does not migrate (starts fresh)

---

## Success Criteria

Phase 2 WordPress integration is considered complete when:

- [x] ✅ v2 API client loaded and used
- [x] ✅ Auth modal assets enqueued
- [x] ✅ AJAX handlers implemented
- [x] ✅ Version updated to 4.0.0
- [ ] 🔲 End-to-end authentication works
- [ ] 🔲 Alt text generation with JWT works
- [ ] 🔲 Usage tracking updates correctly
- [ ] 🔲 Upgrade flow redirects to Stripe
- [ ] 🔲 No JavaScript errors in console

---

## Conclusion

The WordPress plugin has been successfully updated to Phase 2! All code changes are complete, and the plugin is ready for integration testing with the Phase 2 backend.

**Status:** ✅ Code Complete - Pending Integration Testing

**Next Action:** Deploy Phase 2 backend and test end-to-end flow

---

**Plugin Version:** 4.0.0
**Backend Version Required:** 2.0.0 (Phase 2)
**Minimum WordPress:** 5.8
**Minimum PHP:** 7.4
