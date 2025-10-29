# Account Management & Password Reset Implementation Plan

## Overview
This document outlines the implementation plan for adding two major features:
1. **Forgot Password/Password Reset** functionality
2. **Account Management** section in Settings (subscription management, billing, card updates)

---

## Phase 1: Forgot Password Feature

### 1.1 Frontend - Auth Modal Updates
**File**: `assets/auth-modal.js`
**Time**: ~15 minutes

**Tasks**:
- [ ] Add "Forgot password?" link below password field in login form (line 57)
- [ ] Create new password reset request form view (`alttext-forgot-password-form`)
  - Single email input field
  - Submit button
  - Back to login link
- [ ] Create password reset completion form view (`alttext-reset-password-form`)
  - Email field (pre-filled from query param)
  - Reset token field (from email link)
  - New password field
  - Confirm password field
  - Submit button
- [ ] Add `showForgotPasswordForm()` method to switch to forgot password view
- [ ] Add `handleForgotPassword()` method to submit email reset request
- [ ] Add `handleResetPassword()` method to complete password reset with token
- [ ] Update form switching logic to include forgot password flow

### 1.2 Backend API Client
**File**: `includes/class-api-client-v2.php`
**Time**: ~10 minutes

**Tasks**:
- [ ] Add `forgot_password($email)` method
  - Calls `/auth/forgot-password` endpoint
  - POST request with email
  - Returns success/error response
- [ ] Add `reset_password($email, $token, $new_password)` method
  - Calls `/auth/reset-password` endpoint
  - POST request with email, token, newPassword
  - Returns success/error response

### 1.3 WordPress AJAX Handlers
**File**: `ai-alt-gpt.php`
**Time**: ~15 minutes

**Tasks**:
- [ ] Add `ajax_forgot_password()` handler
  - Check nonce: `check_ajax_referer('alttextai_upgrade_nonce', 'nonce')`
  - Sanitize email: `sanitize_email($_POST['email'])`
  - Call `$this->api_client->forgot_password($email)`
  - Return success message or error
- [ ] Add `ajax_reset_password()` handler
  - Check nonce: `check_ajax_referer('alttextai_upgrade_nonce', 'nonce')`
  - Sanitize inputs: email, token, password
  - Validate password strength
  - Call `$this->api_client->reset_password($email, $token, $password)`
  - Return success and auto-login or redirect to login
- [ ] Register AJAX actions:
  - `add_action('wp_ajax_alttextai_forgot_password', [$this, 'ajax_forgot_password']);`
  - `add_action('wp_ajax_alttextai_reset_password', [$this, 'ajax_reset_password']);`

### 1.4 Styling
**File**: `assets/auth-modal.css`
**Time**: ~10 minutes

**Tasks**:
- [ ] Style forgot password form to match existing auth forms
- [ ] Style reset password form
- [ ] Ensure responsive design
- [ ] Add loading states for buttons

**Total Phase 1 Time**: ~50 minutes

---

## Phase 2: Account Management in Settings

### 2.1 Backend API Client - Subscription Info
**File**: `includes/class-api-client-v2.php`
**Time**: ~10 minutes

**Tasks**:
- [ ] Add `get_subscription_info()` method
  - Calls `/billing/subscription` endpoint
  - Returns subscription details:
    - Current plan (free/pro/agency/credits)
    - Subscription status (active/cancelled/trial)
    - Billing cycle (monthly/annual)
    - Next billing date
    - Next charge amount
    - Payment method last 4 digits
    - Payment method brand (visa/mastercard/etc)
    - Cancel at period end (true/false)
    - Plan price
    - Subscription ID

### 2.2 WordPress AJAX Handler
**File**: `ai-alt-gpt.php`
**Time**: ~10 minutes

**Tasks**:
- [ ] Add `ajax_get_subscription_info()` handler
  - Check nonce and authentication
  - Call `$this->api_client->get_subscription_info()`
  - Return subscription data or error
- [ ] Register AJAX action:
  - `add_action('wp_ajax_alttextai_get_subscription_info', [$this, 'ajax_get_subscription_info']);`

### 2.3 Settings Page UI - Account Management Section
**File**: `ai-alt-gpt.php` (Settings tab rendering)
**Time**: ~30 minutes

**Tasks**:
- [ ] Create new Account Management card section in Settings page
  - Place after Plan Status Card, before Generation Settings
  - Only visible when user is authenticated
- [ ] Display subscription information:
  - Current Plan badge
  - Subscription status badge (Active/Cancelled)
  - Billing cycle info
  - Next billing date
  - Next charge amount (with currency symbol)
  - Payment method info (last 4 digits, brand icon)
- [ ] Add action buttons:
  - "Update Payment Method" button → Opens Stripe Customer Portal
  - "Manage Subscription" button → Opens Stripe Customer Portal
  - "Cancel Subscription" link → Opens Stripe Customer Portal with cancellation flow
- [ ] Add warning message if subscription is set to cancel at period end
- [ ] Show "Reactivate Subscription" option if cancelled but still in billing period

### 2.4 JavaScript for Account Management
**File**: `assets/ai-alt-dashboard.js` or new `assets/ai-alt-settings.js`
**Time**: ~20 minutes

**Tasks**:
- [ ] Add function to fetch and display subscription info on Settings page load
- [ ] Add click handlers for:
  - "Update Payment Method" → Opens customer portal
  - "Manage Subscription" → Opens customer portal
  - "Cancel Subscription" → Opens customer portal with cancellation
- [ ] Handle portal return and refresh subscription info
- [ ] Show loading states while fetching subscription data
- [ ] Handle error states gracefully

### 2.5 Styling
**File**: `assets/guide-settings-pages.css` or `assets/ai-alt-dashboard.css`
**Time**: ~15 minutes

**Tasks**:
- [ ] Style Account Management card to match Settings design system
- [ ] Style subscription info display
- [ ] Style payment method display with card brand icons
- [ ] Style action buttons (consistency with existing button system)
- [ ] Style warning/notice messages
- [ ] Ensure responsive design
- [ ] Add icons for billing cycle, next charge, payment method

**Total Phase 2 Time**: ~85 minutes

---

## Phase 3: Backend Requirements (External)

### 3.1 Password Reset Endpoints
**Backend**: `backend/routes/auth.js` or similar
**Time**: ~45 minutes

**Required Endpoints**:
1. **POST `/auth/forgot-password`**
   - Accepts: `{ email: string }`
   - Generates reset token
   - Sends email with reset link (includes token)
   - Returns: `{ success: true, message: "Reset email sent" }`
   
2. **POST `/auth/reset-password`**
   - Accepts: `{ email: string, token: string, newPassword: string }`
   - Validates token (check expiry, validity)
   - Updates password in database
   - Invalidates token
   - Returns: `{ success: true, message: "Password reset successful" }`

### 3.2 Subscription Info Endpoint
**Backend**: `backend/routes/billing.js` or similar
**Time**: ~30 minutes

**Required Endpoint**:
1. **GET `/billing/subscription`**
   - Requires: JWT authentication
   - Returns subscription details:
     ```json
     {
       "plan": "pro",
       "status": "active",
       "billingCycle": "monthly",
       "nextBillingDate": "2025-11-01",
       "nextChargeAmount": 12.99,
       "currency": "GBP",
       "paymentMethod": {
         "last4": "4242",
         "brand": "visa",
         "expMonth": 12,
         "expYear": 2025
       },
       "cancelAtPeriodEnd": false,
       "subscriptionId": "sub_xxx",
       "currentPeriodEnd": "2025-11-01T00:00:00Z"
     }
     ```

### 3.3 Email Service Setup
**Backend**: Email sending service
**Time**: ~30 minutes

**Tasks**:
- [ ] Configure email service (SendGrid, AWS SES, etc.)
- [ ] Create password reset email template
- [ ] Include reset link: `https://your-site.com/wp-admin/upload.php?page=ai-alt-gpt&reset-token=TOKEN&email=EMAIL`
- [ ] Set token expiry (e.g., 1 hour)

**Total Phase 3 Time**: ~105 minutes (backend work)

---

## Implementation Summary

### Frontend Plugin Work (WordPress)
- **Phase 1**: ~50 minutes (Forgot Password)
- **Phase 2**: ~85 minutes (Account Management)
- **Total Frontend**: ~2 hours 15 minutes

### Backend Work (Separate Repository)
- **Phase 3**: ~105 minutes (~1 hour 45 minutes)

### Total Implementation Time
- **Full Stack**: ~4 hours
- **Frontend Only** (if backend exists): ~2 hours 15 minutes
- **Backend Only** (if frontend ready): ~1 hour 45 minutes

---

## Dependencies

### For Password Reset:
1. ✅ Frontend can be built independently
2. ⚠️ Requires backend `/auth/forgot-password` and `/auth/reset-password` endpoints
3. ⚠️ Requires email service configured in backend

### For Account Management:
1. ✅ Stripe Customer Portal functionality already exists (`create_customer_portal_session()`)
2. ⚠️ Requires backend `/billing/subscription` endpoint
3. ✅ Can use existing Stripe webhook for subscription updates

---

## Technical Notes

### Security Considerations:
- Password reset tokens should expire (1 hour recommended)
- Tokens should be single-use (invalidated after use)
- Email should be validated before sending reset link
- Rate limiting on password reset requests (prevent abuse)

### User Experience:
- Clear messaging about reset email being sent
- Instructions to check spam folder
- Token validation error messages
- Success confirmation after password reset
- Auto-login after successful reset (optional)

### Stripe Customer Portal:
- Already implemented via `ajax_create_portal()` handler
- Portal handles:
  - Update payment method
  - View invoice history
  - Cancel subscription
  - Update subscription plan
- Need to ensure portal is properly configured in Stripe dashboard

---

## Testing Checklist

### Password Reset:
- [ ] "Forgot password?" link appears on login form
- [ ] Reset form submission sends email
- [ ] Email contains valid reset link
- [ ] Reset link opens reset form
- [ ] Token validation works
- [ ] Password reset completes successfully
- [ ] User can login with new password
- [ ] Expired tokens are rejected
- [ ] Invalid tokens show error message

### Account Management:
- [ ] Subscription info displays correctly
- [ ] Plan details show accurate information
- [ ] Next billing date is correct
- [ ] Payment method displays correctly
- [ ] "Update Payment Method" opens Stripe portal
- [ ] "Manage Subscription" opens Stripe portal
- [ ] Portal updates reflect in Settings page
- [ ] Cancelled subscriptions show warning
- [ ] Free plan users see appropriate message

---

## Next Steps

1. **Start with Frontend** (can be done now):
   - Implement forgot password UI/UX
   - Implement account management UI/UX
   - Connect to existing Stripe portal functionality

2. **Backend Implementation** (separate task):
   - Add password reset endpoints
   - Add subscription info endpoint
   - Configure email service
   - Test end-to-end flow

3. **Integration Testing**:
   - Test complete password reset flow
   - Test account management features
   - Verify Stripe portal integration
   - Test error scenarios

