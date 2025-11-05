# Backend Integration Guide for Usage Tracking

This document provides complete implementation details for the backend API endpoints required to support the WordPress plugin's usage tracking system.

## Overview

The WordPress plugin tracks token usage events locally and syncs them to the backend for centralized analytics. The backend implements authentication, signature validation, and data aggregation endpoints inside `routes/usage.js` and `services/providerUsageService.js`.

---

## Authentication

All endpoints require JWT authentication via `Authorization: Bearer {token}` header.

The plugin stores the JWT in WordPress options (`alttextai_jwt_token`) and includes it in all API requests.

---

## Endpoint: POST /usage/event

**Purpose**: Accept batched usage events from WordPress installations.

### Request Headers
```
Authorization: Bearer {jwt_token}
Content-Type: application/json
X-Install-Signature: {signature}:{timestamp}
```

### Request Body
```json
{
  "install_id": "550e8400-e29b-41d4-a716-446655440000_a3b4c5d6e7f8",
  "account_id": "acc_1234567890",
  "events": [
    {
      "event_id": "evt_550e8400e29b41d4a716446655440000",
      "wp_user_id_hash": "sha256_hash_of_user_id",
      "source": "bulk",
      "model": "gpt-4o-mini",
      "prompt_tokens": 150,
      "completion_tokens": 25,
      "total_tokens": 175,
      "context": {
        "attachment_id": 12345,
        "image_dimensions": "1920x1080",
        "source_detail": "media_library"
      },
      "created_at": "2025-11-03T10:30:00Z",
      "processed_at": "2025-11-03T10:30:02Z"
    }
  ],
  "batch_sent_at": "2025-11-03T10:31:00Z"
}
```

### Signature Validation

The `X-Install-Signature` header contains: `{signature}:{timestamp}`

1. Extract `install_id` from request body
2. Look up installation secret from `installation_metadata` table
3. Validate timestamp (must be within 5 minutes)
4. Generate expected signature: `HMAC-SHA256(install_id:timestamp, secret)`
5. Compare with provided signature using constant-time comparison

**Implementation Example (Node.js)**:
```javascript
function validateInstallSignature(header, installId, secret) {
  const [signature, timestamp] = header.split(':');
  const timestampNum = parseInt(timestamp, 10);
  const now = Math.floor(Date.now() / 1000);
  
  // Check timestamp freshness (within 5 minutes)
  if (Math.abs(now - timestampNum) > 300) {
    return false;
  }
  
  // Generate expected signature
  const crypto = require('crypto');
  const expected = crypto
    .createHmac('sha256', secret)
    .update(`${installId}:${timestamp}`)
    .digest('hex');
  
  // Constant-time comparison
  return crypto.timingSafeEqual(
    Buffer.from(signature),
    Buffer.from(expected)
  );
}
```

### Response (Success - HTTP 200)
```json
{
  "success": true,
  "received": 1,
  "event_ids": ["evt_550e8400e29b41d4a716446655440000"],
  "message": "Events recorded successfully"
}
```

### Response (Error)
```json
{
  "success": false,
  "error": "invalid_signature",
  "message": "Installation signature validation failed",
  "received": 0
}
```

### Error Codes
- `401 Unauthorized`: Invalid or expired JWT
- `400 Bad Request`: Malformed payload
- `403 Forbidden`: Invalid installation signature
- `422 Unprocessable Entity`: Validation errors
- `500 Internal Server Error`: Server-side failure

---

## Database Schema

### Table: `usage_events`
```sql
CREATE TABLE usage_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id VARCHAR(50) NOT NULL,
  install_id VARCHAR(100) NOT NULL,
  account_id VARCHAR(50) NOT NULL,
  user_hash VARCHAR(64) NOT NULL,
  source VARCHAR(20) NOT NULL,
  model VARCHAR(50) NOT NULL,
  prompt_tokens INT UNSIGNED NOT NULL,
  completion_tokens INT UNSIGNED NOT NULL,
  total_tokens INT UNSIGNED NOT NULL,
  context JSON NULL,
  created_at DATETIME NOT NULL,
  processed_at DATETIME NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY event_id (event_id),
  KEY install_date (install_id, created_at),
  KEY account_date (account_id, created_at),
  KEY user_date (user_hash, created_at),
  KEY received_at (received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `usage_daily_summary`
```sql
CREATE TABLE usage_daily_summary (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  install_id VARCHAR(100) NOT NULL,
  account_id VARCHAR(50) NOT NULL,
  user_hash VARCHAR(64) NOT NULL,
  source VARCHAR(20) NOT NULL,
  usage_date DATE NOT NULL,
  total_requests INT UNSIGNED NOT NULL DEFAULT 0,
  prompt_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  completion_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  total_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  estimated_cost_usd DECIMAL(10,6) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY install_user_source_date (install_id, user_hash, source, usage_date),
  KEY account_date (account_id, usage_date),
  KEY usage_date (usage_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `installation_metadata`
```sql
CREATE TABLE installation_metadata (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  install_id VARCHAR(100) NOT NULL,
  account_id VARCHAR(50) NOT NULL,
  site_hash VARCHAR(64) NOT NULL,
  install_secret VARCHAR(128) NOT NULL,
  first_seen DATETIME NOT NULL,
  last_seen DATETIME NOT NULL,
  plugin_version VARCHAR(20) NULL,
  wordpress_version VARCHAR(20) NULL,
  php_version VARCHAR(20) NULL,
  is_multisite BOOLEAN NOT NULL DEFAULT 0,
  metadata JSON NULL,
  PRIMARY KEY (id),
  UNIQUE KEY install_id (install_id),
  KEY account_id (account_id),
  KEY last_seen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Processing Workflow

### 1. Event Ingestion

When `/api/usage/event` is called:

1. **Validate JWT**: Extract account_id from JWT payload
2. **Validate Signature**: Use installation secret to verify signature
3. **Store Events**: Insert into `usage_events` table
4. **Update Metadata**: Update `last_seen` in `installation_metadata`
5. **Return Response**: Send success/error response

### 2. Daily Aggregation (Cron Job)

Run daily at 2 AM to aggregate events into summaries:

```sql
-- Aggregate previous day's events
INSERT INTO usage_daily_summary (
  install_id, account_id, user_hash, source, usage_date,
  total_requests, prompt_tokens, completion_tokens, total_tokens, estimated_cost_usd
)
SELECT 
  install_id,
  account_id,
  user_hash,
  source,
  DATE(created_at) as usage_date,
  COUNT(*) as total_requests,
  SUM(prompt_tokens) as prompt_tokens,
  SUM(completion_tokens) as completion_tokens,
  SUM(total_tokens) as total_tokens,
  SUM(estimated_cost_usd) as estimated_cost_usd
FROM usage_events
WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
  AND id NOT IN (
    SELECT event_id FROM usage_daily_summary WHERE usage_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
  )
GROUP BY install_id, account_id, user_hash, source, DATE(created_at)
ON DUPLICATE KEY UPDATE
  total_requests = VALUES(total_requests),
  prompt_tokens = VALUES(prompt_tokens),
  completion_tokens = VALUES(completion_tokens),
  total_tokens = VALUES(total_tokens),
  estimated_cost_usd = VALUES(estimated_cost_usd),
  updated_at = NOW();
```

### 3. Cost Calculation

Calculate cost based on model pricing:

```javascript
const PRICING = {
  'gpt-4o-mini': {
    prompt: 0.00015,    // per 1K tokens
    completion: 0.0006  // per 1K tokens
  },
  'gpt-4o': {
    prompt: 0.0025,
    completion: 0.01
  },
  'gpt-4-turbo': {
    prompt: 0.01,
    completion: 0.03
  }
};

function calculateCost(model, promptTokens, completionTokens) {
  const rates = PRICING[model] || PRICING['gpt-4o-mini'];
  const promptCost = (promptTokens / 1000) * rates.prompt;
  const completionCost = (completionTokens / 1000) * rates.completion;
  return promptCost + completionCost;
}
```

### 4. Retention Policy

Run daily at 3 AM to clean up old events:

```sql
-- Delete events older than 90 days
DELETE FROM usage_events 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

**Note**: Daily summaries are kept indefinitely for historical analytics.

---

## Additional Endpoints (Optional but Recommended)

### GET /provider/usage/summary

Return aggregated usage data with filtering.

**Query Parameters**:
- `install_id` (optional): Filter by installation
- `date_from` (optional): Start date (ISO 8601)
- `date_to` (optional): End date
- `group_by` (optional): `day`, `user`, or `source`
- `limit` (optional): Max records (default 100)
- `offset` (optional): Pagination offset

**Response**:
```json
{
  "success": true,
  "data": [
    {
      "install_id": "550e8400-e29b-41d4-a716-446655440000_a3b4c5d6e7f8",
      "date": "2025-11-03",
      "user_hash": "sha256_hash_here",
      "source": "bulk",
      "total_requests": 45,
      "prompt_tokens": 6750,
      "completion_tokens": 1125,
      "total_tokens": 7875,
      "estimated_cost_usd": 0.0236
    }
  ],
  "meta": {
    "total": 45,
    "limit": 100,
    "offset": 0
  }
}
```

### GET /provider/usage/events

Return raw events within 90-day window.

**Query Parameters**:
- `install_id` (required): Installation UUID
- `date_from` (optional): Start date
- `date_to` (optional): End date
- `user_hash` (optional): Filter by user
- `source` (optional): Filter by source
- `limit` (optional): Max records
- `offset` (optional): Pagination

### GET /provider/usage/installations

List all installations with usage summaries (admin only).

**Query Parameters**:
- `date_from` (optional): Start date for aggregation
- `date_to` (optional): End date
- `sort_by` (optional): `tokens`, `cost`, or `date`
- `order` (optional): `desc` or `asc`
- `limit` (optional): Max records
- `offset` (optional): Pagination

**Response**:
```json
{
  "success": true,
  "installations": [
    {
      "install_id": "550e8400-e29b-41d4-a716-446655440000_a3b4c5d6e7f8",
      "account_id": "acc_1234567890",
      "first_seen": "2025-09-15T08:00:00Z",
      "last_seen": "2025-11-03T10:30:00Z",
      "total_requests": 1250,
      "total_tokens": 218750,
      "estimated_cost_usd": 65.625,
      "unique_users": 3,
      "active_days": 45
    }
  ],
  "meta": {
    "total": 1,
    "limit": 50,
    "offset": 0
  }
}
```

---

## Security Considerations

1. **Installation Secret Storage**: Store securely, never log or expose
2. **Timestamp Validation**: Reject signatures older than 5 minutes or future-dated
3. **User ID Hashing**: Never store raw WordPress user IDs, only hashes
4. **Rate Limiting**: Implement rate limiting on API endpoints (100 requests/minute recommended)
5. **JWT Validation**: Validate JWT on every request, check expiration
6. **SQL Injection**: Use parameterized queries
7. **XSS Prevention**: Sanitize all output

---

## Testing Checklist

- [ ] Signature validation works correctly
- [ ] Invalid signatures are rejected
- [ ] Expired timestamps are rejected
- [ ] Events are stored correctly
- [ ] Daily aggregation runs successfully
- [ ] Cost calculation is accurate
- [ ] Retention cleanup works
- [ ] JWT authentication required
- [ ] Rate limiting prevents abuse
- [ ] Error handling is graceful

---

## Monitoring

Recommended metrics to track:

1. **API Performance**:
   - Request latency (p50, p95, p99)
   - Error rate (4xx, 5xx)
   - Throughput (requests/second)

2. **Data Quality**:
   - Events received vs. stored
   - Duplicate event detection
   - Missing installations

3. **Usage Patterns**:
   - Events per installation
   - Average tokens per event
   - Peak usage times

---

## Support & Troubleshooting

### Common Issues

**Events not being received**:
- Check JWT token validity
- Verify installation signature secret
- Review error logs for validation failures

**Signature validation failing**:
- Ensure timestamp is within 5-minute window
- Verify installation secret matches
- Check HMAC implementation

**Database performance**:
- Ensure proper indexes are created
- Monitor query performance
- Consider partitioning for large datasets

---

## Endpoint: POST /api/billing/checkout

**Purpose**: Create Stripe checkout session for subscription upgrades.

### Request Headers
```
Authorization: Bearer {jwt_token}
Content-Type: application/json
```

### Request Body
```json
{
  "priceId": "price_1SMrxaJl9Rm418cMM4iikjlJ",
  "successUrl": "https://example.com/wp-admin/upload.php?page=ai-alt-gpt&checkout=success",
  "cancelUrl": "https://example.com/wp-admin/upload.php?page=ai-alt-gpt&checkout=cancel",
  "companyName": "AltText AI",
  "branding": {
    "name": "AltText AI",
    "statementDescriptor": "ALTTEXT AI",
    "description": "AI Alt Text Generator Plugin"
  }
}
```

### Stripe Checkout Session Configuration

When creating the Stripe checkout session, use the provided branding parameters:

**Company Name**: Use `companyName` (default: "AltText AI") instead of any default business name.

**Stripe Session Parameters**:
```javascript
const session = await stripe.checkout.sessions.create({
  payment_method_types: ['card'],
  line_items: [{
    price: priceId,
    quantity: 1,
  }],
  mode: 'subscription', // or 'payment' for one-time purchases
  success_url: successUrl,
  cancel_url: cancelUrl,
  customer_email: customerEmail, // if available
  // Branding overrides
  payment_intent_data: {
    statement_descriptor: branding.statementDescriptor || 'ALTTEXT AI',
    description: branding.description || 'AI Alt Text Generator Plugin'
  },
  // Custom metadata
  metadata: {
    plugin_name: 'AltText AI',
    company_name: companyName || 'AltText AI'
  }
});
```

**Important**: 
- The `companyName` should be used to customize the checkout page branding
- Set Stripe account business name to "AltText AI" in Stripe Dashboard Settings > Business settings
- Use the statement descriptor to show "ALTTEXT AI" on customer statements
- Ensure the checkout page shows "AltText AI" instead of any default business name

### Response (Success - HTTP 200)
```json
{
  "success": true,
  "data": {
    "url": "https://checkout.stripe.com/pay/cs_...",
    "sessionId": "cs_test_..."
  }
}
```

---

*Document maintained by: AltText AI Development Team*  
*Last updated: 2025-11-03*

