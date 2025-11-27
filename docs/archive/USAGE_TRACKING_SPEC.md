# Token Usage Monitoring System Specification

## Overview
Multi-site token usage monitoring system that tracks usage per WordPress installation while providing centralized analytics for the service owner. Implementation lives in `alttext-ai-backend-clone/routes/usage.js`, `services/providerUsageService.js`, and the Prisma models in `prisma/schema.prisma`.

## Architecture

### Installation UUID Format
- **Format**: `{uuid-v4}_{sha256(site_url)}`
- **Example**: `550e8400-e29b-41d4-a716-446655440000_a3b4c5d6e7f8`
- **Storage**: WordPress option `alttextai_install_id`
- **Generation**: On plugin activation (first activation only)
- **Multisite**: One UUID per sub-site in multisite networks

### Privacy Rules

#### Plugin-Side Storage (90 days)
**Stored Locally:**
- WordPress user ID (integer)
- Token counts (prompt, completion, total)
- Model name (string)
- Source type (string: inline, bulk, queue, manual)
- Timestamps
- Request context (JSON: attachment_id, filename, dimensions)

**NOT Stored Locally:**
- Generated alt text content
- API keys or secrets
- Cross-site data

#### Backend Storage (90 days for events, indefinite for summaries)
**Stored Centrally:**
- Installation UUID
- Account ID (from JWT)
- Hashed user ID: `sha256(install_id + wp_user_id)`
- Token counts
- Model name
- Source type
- Timestamps
- Aggregated context (no PII)

**NOT Stored Centrally:**
- WordPress user names or emails
- Site URLs (only hashed reference)
- Alt text content
- Raw user IDs (only hashed)

**Data Retention:**
- Raw events: 90 days, then purged
- Daily summaries: 2 years
- Monthly aggregates: Indefinite

---

## API Contract

### Endpoint: `POST /usage/event`

**Purpose:** Accept signed usage events from WordPress installations.

#### Request Headers
```
Authorization: Bearer {jwt_token}
Content-Type: application/json
X-Install-Signature: sha256({install_id}:{timestamp}:{secret})
```

#### Request Body
```json
{
  "install_id": "550e8400-e29b-41d4-a716-446655440000_a3b4c5d6e7f8",
  "account_id": "acc_1234567890",
  "events": [
    {
      "event_id": "evt_550e8400e29b41d4a716446655440000",
      "wp_user_id_hash": "sha256_hash_here",
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

#### Response (Success)
```json
{
  "success": true,
  "received": 1,
  "event_ids": ["evt_550e8400e29b41d4a716446655440000"],
  "message": "Events recorded successfully"
}
```

#### Response (Error)
```json
{
  "success": false,
  "error": "invalid_signature",
  "message": "Installation signature validation failed",
  "received": 0
}
```

#### Error Codes
- `401 Unauthorized`: Invalid or expired JWT
- `400 Bad Request`: Malformed payload
- `403 Forbidden`: Invalid installation signature
- `422 Unprocessable Entity`: Validation errors
- `500 Internal Server Error`: Server-side failure

---

### Endpoint: `GET /provider/usage/summary`

**Purpose:** Retrieve aggregated usage data for dashboards.

#### Request Headers
```
Authorization: Bearer {jwt_token}
```

#### Query Parameters
```
?install_id={uuid}          # Optional: filter by installation
&date_from=2025-10-01       # Optional: start date (ISO 8601)
&date_to=2025-11-03         # Optional: end date
&group_by=day|user|source   # Optional: grouping dimension
&limit=100                  # Optional: max records (default 100)
&offset=0                   # Optional: pagination
```

#### Response
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

---

### Endpoint: `GET /provider/usage/events`

**Purpose:** Retrieve raw usage events (within 90-day window).

#### Request Headers
```
Authorization: Bearer {jwt_token}
```

#### Query Parameters
```
?install_id={uuid}          # Required: installation UUID
&date_from=2025-10-01       # Optional: start date
&date_to=2025-11-03         # Optional: end date
&user_hash={hash}           # Optional: filter by user
&source={source}            # Optional: filter by source
&limit=100                  # Optional: max records
&offset=0                   # Optional: pagination
```

#### Response
```json
{
  "success": true,
  "events": [
    {
      "event_id": "evt_550e8400e29b41d4a716446655440000",
      "install_id": "550e8400-e29b-41d4-a716-446655440000_a3b4c5d6e7f8",
      "user_hash": "sha256_hash_here",
      "source": "bulk",
      "model": "gpt-4o-mini",
      "prompt_tokens": 150,
      "completion_tokens": 25,
      "total_tokens": 175,
      "created_at": "2025-11-03T10:30:00Z",
      "processed_at": "2025-11-03T10:30:02Z"
    }
  ],
  "meta": {
    "total": 1,
    "limit": 100,
    "offset": 0
  }
}
```

---

### Endpoint: `GET /api/usage/installations`

**Purpose:** List all installations with usage summaries (admin only).

#### Request Headers
```
Authorization: Bearer {admin_jwt_token}
```

#### Query Parameters
```
?date_from=2025-10-01       # Optional: start date for aggregation
&date_to=2025-11-03         # Optional: end date
&sort_by=tokens|cost|date   # Optional: sorting
&order=desc|asc             # Optional: order
&limit=50                   # Optional: max records
&offset=0                   # Optional: pagination
```

#### Response
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

### Endpoint: `POST /api/usage/export`

**Purpose:** Generate CSV export of usage data.

#### Request Headers
```
Authorization: Bearer {jwt_token}
```

#### Request Body
```json
{
  "install_id": "550e8400-e29b-41d4-a716-446655440000_a3b4c5d6e7f8",
  "date_from": "2025-10-01",
  "date_to": "2025-11-03",
  "group_by": "day",
  "format": "csv"
}
```

#### Response
```
Content-Type: text/csv
Content-Disposition: attachment; filename="usage-export-2025-11-03.csv"

Date,User Hash,Source,Requests,Prompt Tokens,Completion Tokens,Total Tokens,Cost USD
2025-11-03,sha256_abc123,bulk,45,6750,1125,7875,0.0236
```

---

## Database Schemas

### WordPress Plugin Tables

#### `wp_alttextai_usage_events`
```sql
CREATE TABLE wp_alttextai_usage_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  install_id VARCHAR(100) NOT NULL,
  event_id VARCHAR(50) NOT NULL,
  wp_site_id BIGINT UNSIGNED NULL,
  wp_user_id BIGINT UNSIGNED NOT NULL,
  source VARCHAR(20) NOT NULL,
  request_id VARCHAR(50) NULL,
  model VARCHAR(50) NOT NULL,
  prompt_tokens INT UNSIGNED NOT NULL,
  completion_tokens INT UNSIGNED NOT NULL,
  total_tokens INT UNSIGNED NOT NULL,
  context JSON NULL,
  created_at DATETIME NOT NULL,
  processed_at DATETIME NULL,
  synced_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY event_id (event_id),
  KEY install_user_date (install_id, wp_user_id, created_at),
  KEY source_date (source, created_at),
  KEY synced (synced_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `wp_alttextai_usage_daily`
```sql
CREATE TABLE wp_alttextai_usage_daily (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  install_id VARCHAR(100) NOT NULL,
  wp_user_id BIGINT UNSIGNED NOT NULL,
  source VARCHAR(20) NOT NULL,
  usage_date DATE NOT NULL,
  total_requests INT UNSIGNED NOT NULL DEFAULT 0,
  prompt_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  completion_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  total_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY install_user_source_date (install_id, wp_user_id, source, usage_date),
  KEY usage_date (usage_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Queue Table Extension (add columns to existing table)
```sql
ALTER TABLE wp_alttextai_queue
  ADD COLUMN enqueued_by BIGINT UNSIGNED NULL AFTER source,
  ADD COLUMN processed_by BIGINT UNSIGNED NULL AFTER enqueued_by,
  ADD INDEX enqueued_by (enqueued_by),
  ADD INDEX processed_by (processed_by);
```

---

### Backend Database Tables

#### `usage_events`
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

#### `usage_daily_summary`
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

#### `installation_metadata`
```sql
CREATE TABLE installation_metadata (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  install_id VARCHAR(100) NOT NULL,
  account_id VARCHAR(50) NOT NULL,
  site_hash VARCHAR(64) NOT NULL,
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

## Security & Signatures

### Installation Signature Generation
```php
$timestamp = time();
$secret = get_option('alttextai_install_secret'); // Generated on activation
$signature = hash_hmac('sha256', $install_id . ':' . $timestamp, $secret);
$header = 'X-Install-Signature: ' . $signature . ':' . $timestamp;
```

### Backend Signature Validation
```php
list($signature, $timestamp) = explode(':', $header_value);

// Check timestamp freshness (within 5 minutes)
if (abs(time() - $timestamp) > 300) {
    return false; // Too old or future-dated
}

// Retrieve secret for this installation from metadata
$expected = hash_hmac('sha256', $install_id . ':' . $timestamp, $secret);
return hash_equals($expected, $signature);
```

### User ID Hashing
```php
// Plugin-side before sending
$user_hash = hash('sha256', $install_id . ':' . $wp_user_id . ':user');

// Backend storage
// Store only the hash, never the raw wp_user_id
```

---

## Batch Submission Strategy

### Plugin Behavior
1. **Real-time submission**: Single events sent immediately during user-initiated actions (inline generation)
2. **Batch submission**: Queue/bulk events batched every 5 minutes or 50 events (whichever first)
3. **Retry logic**: Failed submissions stored locally, retried up to 3 times with exponential backoff
4. **Offline resilience**: Events stored locally even if backend is unreachable; synced when connection restored

### Cron Schedule
- **Batch sync**: Every 5 minutes (`ai_alt_sync_usage_events`)
- **Daily rollup**: Daily at 2 AM (`ai_alt_rollup_usage_daily`)
- **Cleanup**: Daily at 3 AM (`ai_alt_cleanup_usage_events`)

---

## Cost Estimation

### Model Pricing (as of 2025-11)
```json
{
  "gpt-4o-mini": {
    "prompt": 0.00015,      // per 1K tokens
    "completion": 0.0006    // per 1K tokens
  },
  "gpt-4o": {
    "prompt": 0.0025,
    "completion": 0.01
  },
  "gpt-4-turbo": {
    "prompt": 0.01,
    "completion": 0.03
  }
}
```

### Calculation
```php
function estimate_cost($model, $prompt_tokens, $completion_tokens) {
    $pricing = [
        'gpt-4o-mini' => ['prompt' => 0.00015, 'completion' => 0.0006],
        'gpt-4o' => ['prompt' => 0.0025, 'completion' => 0.01],
        'gpt-4-turbo' => ['prompt' => 0.01, 'completion' => 0.03],
    ];

    $rates = $pricing[$model] ?? $pricing['gpt-4o-mini'];
    $prompt_cost = ($prompt_tokens / 1000) * $rates['prompt'];
    $completion_cost = ($completion_tokens / 1000) * $rates['completion'];

    return $prompt_cost + $completion_cost;
}
```

---

## Multisite Considerations

### Site ID Tracking
- **Single site**: `wp_site_id` is NULL
- **Multisite**: `wp_site_id` contains the blog_id (1, 2, 3, etc.)
- **Install UUID**: One per sub-site (different UUID for blog 1, blog 2, etc.)

### Capability Checks
- Network admins can view all sub-site usage
- Sub-site admins see only their site
- Non-admins see only their own usage

---

## Dashboard Capabilities

### WordPress Site Dashboard

**Admin View** (requires `manage_options`)
- Site-wide usage charts (daily, weekly, monthly)
- Top users by token consumption
- Source breakdown (inline, bulk, queue)
- Model distribution
- CSV export of all site data

**User View** (all authenticated users)
- Personal usage statistics
- Token consumption over time
- Source breakdown (my inline, my bulk, my queue)
- Estimated cost contribution

### Provider Central Dashboard

**Overview Page**
- Total installations count
- Total tokens consumed (all time, MTD, last 30 days)
- Total estimated cost
- Top 10 installations by usage
- Growth trends chart

**Installation Detail Page**
- Installation metadata (first seen, last seen, plugin version)
- Usage timeline chart
- Per-user breakdown within installation
- Source and model distribution
- Alert threshold status

**Analytics Page**
- Cross-installation trends
- Model adoption rates
- Source popularity
- Cost projections
- Anomaly detection (unusual spikes)

---

## Compliance & Privacy

### GDPR Compliance
- **Data minimization**: Only essential usage metrics stored
- **Pseudonymization**: User IDs hashed before transmission
- **Right to erasure**: API endpoint to purge all data for an installation
- **Data portability**: CSV export functionality
- **Consent**: Usage tracking disclosed in plugin documentation

### Data Processing Agreement
- Service owner is data processor
- WordPress site owner is data controller
- Usage data used solely for service delivery and analytics
- No third-party data sharing
- Data retained per documented retention policy

---

## Testing Strategy

### Unit Tests
- Installation UUID generation
- User hash computation
- Event logging
- Daily rollup calculations
- Cost estimation accuracy

### Integration Tests
- API event submission with signature validation
- Batch submission and retry logic
- Cron job execution
- Dashboard data retrieval
- Export functionality

### Load Tests
- 1000 events/minute submission rate
- 100 concurrent installations
- 90-day event retention at scale
- Dashboard query performance

---

## Deployment Checklist

### Plugin Deployment
- [ ] Database migrations tested on single-site
- [ ] Database migrations tested on multisite
- [ ] Activation hook generates UUID and secret
- [ ] Deactivation preserves data (no cleanup)
- [ ] Uninstall hook optionally purges data
- [ ] Backward compatibility verified (no breaking changes)
- [ ] Performance impact measured (< 50ms overhead per request)

### Backend Deployment
- [ ] Database migrations applied
- [ ] API endpoints deployed and tested
- [ ] JWT authentication verified
- [ ] Signature validation working
- [ ] Retention cron jobs scheduled
- [ ] Monitoring and alerting configured
- [ ] Rate limiting implemented
- [ ] Backup strategy in place

### Post-Launch
- [ ] Monitor error logs for failed submissions
- [ ] Track API response times
- [ ] Verify data accuracy (spot checks)
- [ ] User feedback collection
- [ ] Documentation published
- [ ] Support team trained

---

## Future Enhancements

### Phase 5 (Optional)
- Real-time usage alerts via WebSocket
- Predictive cost forecasting using ML
- Automated abuse detection
- Usage-based pricing tiers integration
- Mobile app for provider dashboard
- Slack/Discord bot for notifications
- Public API for third-party integrations
- Advanced analytics (cohort analysis, retention curves)

---

## Support & Troubleshooting

### Common Issues

**Events not syncing to backend**
- Check JWT token validity
- Verify installation signature
- Review error logs in `wp_alttextai_usage_events.context.sync_error`
- Test API connectivity

**Dashboard showing incorrect data**
- Verify daily rollup cron is running
- Check for timezone mismatches
- Recalculate summaries manually via admin action

**High database size**
- Confirm cleanup cron is running
- Check event retention window
- Manually purge old events if needed

### Debug Mode
```php
define('ALTTEXTAI_USAGE_DEBUG', true);
```
Enables verbose logging to `wp-content/debug.log` for all usage tracking operations.

---

## Version History

- **v1.0.0** (2025-11-03): Initial specification
- **v1.1.0** (TBD): Add real-time streaming updates
- **v1.2.0** (TBD): Predictive analytics and forecasting

---

*Document maintained by: AltText AI Development Team*
*Last updated: 2025-11-03*
