# Backend Integration Documentation

This document provides the exact API contract that the backend must implement to support the frontend password reset and account management features.

---

## Overview

The WordPress plugin frontend requires two new backend endpoints:

1. **Password Reset Flow**: `/auth/forgot-password` and `/auth/reset-password`
2. **Subscription Information**: `/billing/subscription`

The Stripe Customer Portal endpoint (`/billing/portal`) already exists and is working.

---

## 1. Password Reset Endpoints

### 1.1 POST `/auth/forgot-password`

**Purpose**: Request a password reset email for a user.

**Authentication**: Not required (public endpoint)

**Request Body**:
```json
{
  "email": "user@example.com"
}
```

**Expected Response - Success** (HTTP 200):
```json
{
  "success": true,
  "data": {
    "message": "Password reset email sent successfully"
  }
}
```

**Expected Response - Error** (HTTP 400):
```json
{
  "success": false,
  "data": {
    "error": "User not found",
    "message": "No account found with this email address"
  }
}
```

**Error Scenarios**:
- `400 Bad Request`: Invalid email format, user not found
- `429 Too Many Requests`: Rate limit exceeded (too many reset requests)
- `500 Internal Server Error`: Server error, email service unavailable

**Requirements**:
1. Generate a secure, one-time-use reset token (recommended: 32+ characters)
2. Store token in database with:
   - Expiration time (recommended: 1 hour)
   - Email address (for verification)
   - Used flag (false initially)
3. Send email to user with reset link:
   - Link format: `https://wordpress-site.com/wp-admin/upload.php?page=opptiai-alt&reset-token=TOKEN&email=EMAIL`
   - Include expiration time in email
   - Include instructions to check spam folder
4. **Security**: Always return success message (even if email not found) to prevent email enumeration
5. **Rate Limiting**: Limit requests per email (e.g., 3 per hour) and per IP

**Email Template**:
```
Subject: Reset Your AltText AI Password

Hi there,

You requested to reset your password for AltText AI.

Click the link below to reset your password:
[RESET_LINK]

This link will expire in 1 hour.

If you didn't request this, please ignore this email.

---

AltText AI Team
```

---

### 1.2 POST `/auth/reset-password`

**Purpose**: Complete password reset using token from email.

**Authentication**: Not required (public endpoint)

**Request Body**:
```json
{
  "email": "user@example.com",
  "token": "abc123...xyz789",
  "newPassword": "NewSecurePassword123!"
}
```

**Expected Response - Success** (HTTP 200):
```json
{
  "success": true,
  "data": {
    "message": "Password reset successfully"
  }
}
```

**Expected Response - Error** (HTTP 400):
```json
{
  "success": false,
  "data": {
    "error": "Invalid or expired token",
    "message": "This password reset link has expired or is invalid. Please request a new one."
  }
}
```

**Error Scenarios**:
- `400 Bad Request`: 
  - Invalid token
  - Expired token
  - Token already used
  - Weak password (less than 8 characters, or doesn't meet policy)
  - Email doesn't match token
- `404 Not Found`: User not found
- `500 Internal Server Error`: Server error

**Requirements**:
1. Validate token:
   - Check token exists in database
   - Check token matches email
   - Check token not expired
   - Check token not already used
2. Validate password:
   - Minimum 8 characters
   - (Optional) Enforce complexity requirements
3. Update user password:
   - Hash password securely (bcrypt, argon2, etc.)
   - Update user record in database
4. Invalidate token:
   - Mark token as used
   - Or delete token from database
5. **Security**: After 5 failed attempts, invalidate token

**Password Validation**:
- Frontend validates: minimum 8 characters
- Backend should enforce same + any additional requirements
- Return clear error if password too weak

---

## 2. Subscription Information Endpoint

### 2.1 GET `/billing/subscription`

**Purpose**: Fetch current user's subscription and billing information.

**Authentication**: Required (JWT token in `Authorization: Bearer TOKEN` header)

**Request**: No body required

**Expected Response - Success** (HTTP 200):
```json
{
  "success": true,
  "data": {
    "plan": "pro",
    "status": "active",
    "billingCycle": "monthly",
    "nextBillingDate": "2025-11-01T00:00:00Z",
    "nextChargeAmount": 12.99,
    "currency": "GBP",
    "paymentMethod": {
      "last4": "4242",
      "brand": "visa",
      "expMonth": 12,
      "expYear": 2025
    },
    "cancelAtPeriodEnd": false,
    "subscriptionId": "sub_1234567890",
    "currentPeriodEnd": "2025-11-01T00:00:00Z"
  }
}
```

**Expected Response - Free Plan** (HTTP 200):
```json
{
  "success": true,
  "data": {
    "plan": "free",
    "status": "free",
    "billingCycle": null,
    "nextBillingDate": null,
    "nextChargeAmount": null,
    "currency": null,
    "paymentMethod": null,
    "cancelAtPeriodEnd": false,
    "subscriptionId": null,
    "currentPeriodEnd": null
  }
}
```

**Expected Response - Error** (HTTP 401):
```json
{
  "success": false,
  "data": {
    "error": "Unauthorized",
    "message": "Authentication required"
  }
}
```

**Error Scenarios**:
- `401 Unauthorized`: Invalid or missing JWT token
- `404 Not Found`: User has no subscription (should return free plan, not 404)
- `500 Internal Server Error`: Server error

**Field Descriptions**:

| Field | Type | Description | Required |
|-------|------|-------------|----------|
| `plan` | string | Plan name: `"free"`, `"pro"`, `"agency"`, or `"credits"` | Yes |
| `status` | string | Subscription status: `"free"`, `"active"`, `"cancelled"`, `"trial"`, `"past_due"` | Yes |
| `billingCycle` | string/null | `"monthly"` or `"annual"` | If subscribed |
| `nextBillingDate` | string/null | ISO 8601 datetime of next charge | If subscribed |
| `nextChargeAmount` | number/null | Amount in currency units (e.g., 12.99 for £12.99) | If subscribed |
| `currency` | string/null | ISO currency code: `"GBP"`, `"USD"`, `"EUR"` | If subscribed |
| `paymentMethod` | object/null | Payment method details | If subscribed |
| `paymentMethod.last4` | string | Last 4 digits of card | If payment method exists |
| `paymentMethod.brand` | string | Card brand: `"visa"`, `"mastercard"`, `"amex"`, etc. | If payment method exists |
| `paymentMethod.expMonth` | number | Expiration month (1-12) | If payment method exists |
| `paymentMethod.expYear` | number | Expiration year (YYYY) | If payment method exists |
| `cancelAtPeriodEnd` | boolean | `true` if subscription set to cancel | Yes |
| `subscriptionId` | string/null | Stripe subscription ID | If subscribed |
| `currentPeriodEnd` | string/null | ISO 8601 datetime when current period ends | If subscribed |

**Requirements**:
1. Authenticate user via JWT token
2. Fetch subscription from Stripe using customer ID
3. Return subscription details or free plan if no subscription
4. Handle cancelled subscriptions (show status: `"cancelled"`, set `cancelAtPeriodEnd: true` if applicable)
5. Format dates as ISO 8601 strings
6. Handle missing payment methods gracefully (return `null`)

**Stripe Integration Notes**:
- Use Stripe API to fetch customer's subscription
- Get payment method from customer's default payment method
- Handle edge cases:
  - Multiple subscriptions (use most recent/active)
  - No payment method (return `null`)
  - Cancelled but still in billing period (show `cancelAtPeriodEnd: true`)

---

## 3. Existing Endpoint (Already Working)

### 3.1 POST `/billing/portal`

**Status**: ✅ Already implemented and working

**Purpose**: Create Stripe Customer Portal session URL

**Authentication**: Required (JWT token)

**Request Body**:
```json
{
  "returnUrl": "https://wordpress-site.com/wp-admin/upload.php?page=opptiai-alt"
}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "url": "https://billing.stripe.com/p/session/..."
  }
}
```

No changes needed for this endpoint.

---

## 4. Error Response Format

All endpoints should follow this consistent error format:

```json
{
  "success": false,
  "data": {
    "error": "Error Code",
    "message": "User-friendly error message"
  }
}
```

**HTTP Status Codes**:
- `200 OK`: Success
- `400 Bad Request`: Invalid request data
- `401 Unauthorized`: Authentication required
- `404 Not Found`: Resource not found
- `429 Too Many Requests`: Rate limit exceeded
- `500 Internal Server Error`: Server error

---

## 5. Example cURL Commands

### Test Forgot Password (No Auth Required)
```bash
curl -X POST https://alttext-ai-backend.onrender.com/auth/forgot-password \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com"}'
```

### Test Reset Password (No Auth Required)
```bash
curl -X POST https://alttext-ai-backend.onrender.com/auth/reset-password \
  -H "Content-Type: application/json" \
  -d '{
    "email":"user@example.com",
    "token":"abc123...xyz789",
    "newPassword":"NewSecurePassword123!"
  }'
```

### Test Get Subscription (Auth Required)
```bash
curl -X GET https://alttext-ai-backend.onrender.com/billing/subscription \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json"
```

---

## 6. Mock Data for Testing

### Free Plan Response
```json
{
  "success": true,
  "data": {
    "plan": "free",
    "status": "free",
    "billingCycle": null,
    "nextBillingDate": null,
    "nextChargeAmount": null,
    "currency": null,
    "paymentMethod": null,
    "cancelAtPeriodEnd": false,
    "subscriptionId": null,
    "currentPeriodEnd": null
  }
}
```

### Pro Plan (Active) Response
```json
{
  "success": true,
  "data": {
    "plan": "pro",
    "status": "active",
    "billingCycle": "monthly",
    "nextBillingDate": "2025-11-01T00:00:00Z",
    "nextChargeAmount": 12.99,
    "currency": "GBP",
    "paymentMethod": {
      "last4": "4242",
      "brand": "visa",
      "expMonth": 12,
      "expYear": 2025
    },
    "cancelAtPeriodEnd": false,
    "subscriptionId": "sub_1234567890",
    "currentPeriodEnd": "2025-11-01T00:00:00Z"
  }
}
```

### Pro Plan (Cancelled - Still Active Until Period End)
```json
{
  "success": true,
  "data": {
    "plan": "pro",
    "status": "active",
    "billingCycle": "monthly",
    "nextBillingDate": "2025-11-01T00:00:00Z",
    "nextChargeAmount": 0.00,
    "currency": "GBP",
    "paymentMethod": {
      "last4": "4242",
      "brand": "visa",
      "expMonth": 12,
      "expYear": 2025
    },
    "cancelAtPeriodEnd": true,
    "subscriptionId": "sub_1234567890",
    "currentPeriodEnd": "2025-11-01T00:00:00Z"
  }
}
```

---

## 7. Security Considerations

### Password Reset:
1. **Token Security**:
   - Use cryptographically secure random token (32+ bytes)
   - Store hashed token in database (not plaintext)
   - Single-use tokens (invalidate after use)
   - 1-hour expiration minimum

2. **Rate Limiting**:
   - Limit per email: 3 requests/hour
   - Limit per IP: 5 requests/hour
   - Use exponential backoff for repeated requests

3. **Email Privacy**:
   - Don't reveal if email exists (always return success)
   - Log failed attempts for security review

4. **Password Validation**:
   - Minimum 8 characters
   - (Optional) Require complexity
   - Hash with bcrypt/argon2 (never store plaintext)

### Subscription Endpoint:
1. **Authentication**:
   - Validate JWT token on every request
   - Return 401 if token invalid/expired

2. **Data Privacy**:
   - Only return user's own subscription
   - Never expose other users' data
   - Sanitize payment method data (only last4, not full number)

---

## 8. Testing Checklist

### Password Reset Flow:
- [ ] Request reset with valid email → receives email
- [ ] Request reset with invalid email → still returns success (security)
- [ ] Use valid token → password resets successfully
- [ ] Use expired token → returns error
- [ ] Use invalid token → returns error
- [ ] Use already-used token → returns error
- [ ] Submit weak password → returns error
- [ ] Rate limit exceeded → returns 429 error

### Subscription Endpoint:
- [ ] Authenticated user with subscription → returns subscription data
- [ ] Authenticated user without subscription → returns free plan
- [ ] Unauthenticated request → returns 401
- [ ] Invalid token → returns 401
- [ ] Subscription with cancelled flag → shows `cancelAtPeriodEnd: true`
- [ ] Missing payment method → returns `paymentMethod: null`

---

## 9. Implementation Notes

### Email Service Setup
- Configure email service (SendGrid, AWS SES, Mailgun, etc.)
- Use HTML email template with plain text fallback
- Include branding and clear call-to-action
- Test email delivery to various providers (Gmail, Outlook, etc.)

### Database Schema
For password reset tokens, create a table or use existing structure:

```sql
CREATE TABLE password_reset_tokens (
  id SERIAL PRIMARY KEY,
  email VARCHAR(255) NOT NULL,
  token_hash VARCHAR(255) NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  used BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_token_hash (token_hash),
  INDEX idx_expires_at (expires_at)
);
```

### Stripe Integration
- Use Stripe Node.js SDK
- Fetch customer from JWT user ID
- Get active subscription from customer
- Retrieve payment method from customer's default payment method
- Handle edge cases gracefully

---

## 10. Frontend Expectations

The WordPress plugin frontend:

1. **Handles all error scenarios gracefully**
2. **Shows loading states during requests**
3. **Provides user-friendly error messages**
4. **Redirects to success pages after operations**
5. **Caches subscription info** (optional optimization)

The frontend is **ready and waiting** for these backend endpoints.

---

## Questions?

If backend implementation differs from this specification, please communicate changes before deployment so frontend can be updated accordingly.

**Contact**: Update this section with backend team contact info if needed.

