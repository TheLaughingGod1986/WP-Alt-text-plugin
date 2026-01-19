# Backend Contact Endpoint Update - Allow Anonymous Submissions

## Overview

The `/api/contact` endpoint currently requires authentication (license key or JWT token), which prevents unauthenticated users from submitting the contact form. This document outlines the changes needed to allow anonymous contact form submissions while maintaining security through site-based headers.

## Current Status

- **Endpoint**: `POST /api/contact`
- **Current Auth**: Requires `X-License-Key` or `Authorization: Bearer <JWT>`
- **Error Response**: `401 Unauthorized` - "License key required"

## Required Changes

### 1. Update Authentication Middleware

The contact endpoint should accept requests with **only site headers** (not requiring license key/JWT), but still validate the site headers for abuse prevention:

**Accept:**
- `X-Site-Hash` (site identifier)
- `X-Site-URL` (WordPress site URL)
- `X-Site-Fingerprint` (site fingerprint for abuse prevention)

**Optional (include if available):**
- `X-License-Key` (if user has license)
- `Authorization: Bearer <JWT>` (if user is authenticated)
- `X-WP-User-ID` (if user is logged in)

### 2. Expected Request Format

```json
POST /api/contact
Content-Type: application/json

{
  "name": "User Name",
  "email": "user@example.com",
  "subject": "Subject line",
  "message": "Message content",
  "wp_version": "6.4",
  "plugin_version": "6.0.0"
}
```

**Headers:**
```
Content-Type: application/json
X-Site-Hash: <site_identifier>
X-Site-URL: <wordpress_site_url>
X-Site-Fingerprint: <site_fingerprint>
[Optional] X-License-Key: <license_key>
[Optional] Authorization: Bearer <jwt_token>
[Optional] X-WP-User-ID: <user_id>
```

### 3. Recommended Authentication Logic

```javascript
// Pseudo-code for Express.js route
router.post('/api/contact', async (req, res) => {
  // Require site headers for abuse prevention (not full auth)
  if (!req.headers['x-site-hash'] || !req.headers['x-site-url']) {
    return res.status(400).json({
      error: 'INVALID_REQUEST',
      message: 'Site headers (X-Site-Hash, X-Site-URL) are required'
    });
  }

  // Validate required fields
  const { name, email, subject, message } = req.body;
  if (!name || !email || !subject || !message) {
    return res.status(400).json({
      error: 'VALIDATION_ERROR',
      message: 'All fields (name, email, subject, message) are required'
    });
  }

  // Validate email format
  if (!isValidEmail(email)) {
    return res.status(400).json({
      error: 'VALIDATION_ERROR',
      message: 'Invalid email address format'
    });
  }

  // Optional: Rate limiting based on site hash (prevent spam)
  const rateLimitKey = `contact_${req.headers['x-site-hash']}`;
  // Check rate limit...

  // Send email via Resend
  try {
    const result = await sendContactEmail({
      name,
      email,
      subject,
      message,
      wpVersion: req.body.wp_version,
      pluginVersion: req.body.plugin_version,
      siteUrl: req.headers['x-site-url'],
      siteHash: req.headers['x-site-hash'],
      licenseKey: req.headers['x-license-key'] || null,
      userId: req.headers['x-wp-user-id'] || null
    });

    return res.status(200).json({
      success: true,
      message: 'Your message has been sent successfully. We\'ll get back to you soon!'
    });
  } catch (error) {
    console.error('Contact form error:', error);
    return res.status(500).json({
      error: 'SEND_FAILED',
      message: 'Failed to send message. Please try again later.'
    });
  }
});
```

### 4. Security Considerations

1. **Rate Limiting**: Implement rate limiting per `X-Site-Hash` to prevent spam
   - Example: 3 submissions per hour per site

2. **Email Validation**: Validate email format server-side

3. **Input Sanitization**: Sanitize all input fields before sending email

4. **Site Header Validation**: Validate `X-Site-Hash` and `X-Site-URL` format

5. **Optional: CAPTCHA**: Consider adding CAPTCHA for additional spam protection

### 5. Response Format

**Success (200):**
```json
{
  "success": true,
  "message": "Your message has been sent successfully. We'll get back to you soon!"
}
```

**Error (400/500):**
```json
{
  "error": "VALIDATION_ERROR",
  "message": "All fields are required"
}
```

## Testing

After implementing the changes, test with:

```bash
# Test without authentication (should work)
curl -X POST https://alttext-ai-backend.onrender.com/api/contact \
  -H "Content-Type: application/json" \
  -H "X-Site-Hash: test-site-hash" \
  -H "X-Site-URL: https://example.com" \
  -H "X-Site-Fingerprint: test-fingerprint" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "subject": "Test Subject",
    "message": "Test message",
    "wp_version": "6.4",
    "plugin_version": "6.0.0"
  }'

# Should return: {"success": true, "message": "..."}
```

## Plugin Compatibility

The WordPress plugin already sends all required headers via `get_auth_headers()`. No plugin changes are needed - the plugin will continue to work with authenticated users, and will now also work for unauthenticated users.

## Implementation Notes

- The endpoint should accept requests **without** `X-License-Key` or `Authorization` headers
- Site headers (`X-Site-Hash`, `X-Site-URL`, `X-Site-Fingerprint`) should still be validated for abuse prevention
- If license key or JWT is provided, include them in the email metadata for support context
- Maintain backward compatibility - authenticated requests should still work

## Related Files

- Backend route: `/api/contact` (Express.js/Node.js)
- Plugin client: `includes/class-api-client-v2.php` → `send_contact_email()`
- Plugin handler: `admin/class-bbai-core.php` → `ajax_send_contact_form()`
