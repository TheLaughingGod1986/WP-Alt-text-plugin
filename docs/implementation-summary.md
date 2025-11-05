# Usage Tracking Implementation Summary

## ✅ Completed Phases

### Phase 0: API Contracts and Privacy Documentation
- **Status**: ✅ Complete
- **Location**: `USAGE_TRACKING_SPEC.md`
- Comprehensive API contract documentation
- Privacy and GDPR compliance rules defined

### Phase 1: WordPress Plugin Implementation
- **Status**: ✅ Complete

#### Phase 1.1: Database Migrations ✅
- `wp_alttextai_usage_events` table created on activation
- `wp_alttextai_usage_daily` table created on activation
- Queue table extended with user tracking columns
- Installation UUID generation (`{uuid-v4}_{site-hash}`)
- Installation secret generation for HMAC signing

#### Phase 1.2: Usage Tracker Class ✅
- `AltText_AI_Usage_Event_Tracker` class implemented
- Event logging with user attribution
- User ID hashing (`sha256(install_id:user_id:user)`)
- Event ID generation (`evt_` prefixed UUIDs)
- Daily summary aggregation

#### Phase 1.3: Generation Pipeline Integration ✅
- Inline generation tracking (AJAX/REST)
- Bulk generation tracking
- Queue processing tracking
- Manual generation tracking
- All sources properly attributed

#### Phase 1.4: Cron Jobs and Syncing ✅
- Batch sync cron (every 5 minutes)
- Daily rollup cron (2 AM daily)
- Cleanup cron (3 AM daily, 90-day retention)
- Retry logic with exponential backoff
- Signature generation for backend sync

### Phase 3: WordPress Analytics Dashboard
- **Status**: ✅ Complete

#### Phase 3.1: WordPress Dashboard ✅
- Admin view: Site-wide statistics, top users, source breakdown
- User view: Personal usage statistics
- CSV export functionality
- Multisite support with capability checks
- Real-time data from usage tracker

### Phase 4: Alerts and Governance
- **Status**: ✅ Complete

#### Phase 4.1: Thresholds and Alerts ✅
- Warning threshold (75% usage)
- Critical threshold (90% usage)
- Block threshold (100% usage)
- Admin notice display system
- One alert per day per level

#### Phase 4.2: Governance Rules ✅
- Rate limiting: 100 requests / 5 minutes, 50k tokens / 5 minutes
- Plan-based usage caps
- Abuse detection (200+ requests/hour, 100k+ tokens/hour)
- Automatic blocking at limits

---

## ⏳ Remaining Phases (Backend Work)

### Phase 2: Backend API Implementation
- **Status**: ⏳ Pending
- **Required Endpoints**:
  - `POST /api/usage/event` - Accept batched events
  - `GET /api/usage/summary` - Return aggregated data
  - `GET /api/usage/events` - Return raw events
  - `GET /api/usage/installations` - List installations (admin)
  - `POST /api/usage/export` - CSV export
- **Database Schema**:
  - `usage_events` table
  - `usage_daily_summary` table
  - `installation_metadata` table
- **See**: `docs/backend-integration-guide.md` for full details

### Phase 3.2: Provider Central Dashboard
- **Status**: ⏳ Pending
- **Requirements**:
  - Overview page with total installations
  - Installation detail pages
  - Cross-installation analytics
  - Cost projections and anomaly detection

---

## File Structure

### Core Implementation Files
```
includes/
  ├── class-usage-event-tracker.php    # Event logging and syncing
  ├── class-usage-governance.php       # Alerts and rate limiting
  ├── class-usage-tracker.php           # Legacy usage caching
  └── class-api-client-v2.php           # API client with post() method

admin/
  └── class-ai-alt-gpt-core.php        # Main plugin class with:
                                        #   - Usage tracking hooks
                                        #   - Analytics dashboard
                                        #   - Governance integration
                                        #   - CSV export

ai-alt-gpt.php                         # Plugin bootstrap (loads all classes)
```

### Database Tables
- `wp_alttextai_usage_events` - Raw event log (90-day retention)
- `wp_alttextai_usage_daily` - Daily summaries (2-year retention)
- `wp_alttextai_queue` - Queue with user tracking columns

### Documentation
- `USAGE_TRACKING_SPEC.md` - Complete specification
- `docs/backend-integration-guide.md` - Backend implementation guide
- `docs/implementation-summary.md` - This file

---

## How It Works

### Event Flow

1. **Generation Triggered**
   - User generates alt text (inline, bulk, queue, or manual)
   - `generate_and_save()` method called

2. **Governance Checks**
   - Rate limiting check (100 requests/5min, 50k tokens/5min)
   - Usage threshold check (warn/critical/block)
   - Abuse detection
   - Plan limit enforcement

3. **API Call**
   - Alt text generated via backend API
   - Response includes token usage

4. **Event Logging**
   - Event logged to `wp_alttextai_usage_events`
   - User ID hashed for privacy
   - Daily summary updated immediately
   - Context stored (image dimensions, source, etc.)

5. **Batch Syncing**
   - Cron job runs every 5 minutes
   - Unsynced events batched (up to 50)
   - Signed request sent to backend `/api/usage/event`
   - Events marked as synced on success

6. **Aggregation**
   - Daily cron aggregates events into summaries
   - Used for analytics dashboard
   - Retained for 2 years

7. **Cleanup**
   - Daily cron deletes events older than 90 days
   - Keeps database size manageable

---

## Configuration

### Thresholds
Stored in WordPress option: `alttextai_usage_thresholds`
- Default: warning=75%, critical=90%, block=100%
- Can be customized via filter: `alttextai_usage_thresholds`

### Rate Limits
Defined in `AltText_AI_Usage_Governance`:
- Max requests: 100 per 5 minutes
- Max tokens: 50,000 per 5 minutes
- Adjustable via constants

### Plan Limits
Defined in `AltText_AI_Usage_Governance::get_plan_limit()`:
- Free: 50 per month
- Pro: 10,000 per month
- Agency: 100,000 per month
- Credits: Unlimited

---

## Testing

### Manual Testing Checklist

1. **Event Logging**:
   - [ ] Generate alt text inline → Check `wp_alttextai_usage_events`
   - [ ] Bulk generate → Events logged with source='bulk'
   - [ ] Queue processing → Events logged with source='queue'
   - [ ] Verify user_id is hashed correctly

2. **Analytics Dashboard**:
   - [ ] Visit Usage Analytics tab
   - [ ] Verify stats cards display correctly
   - [ ] Check source breakdown table
   - [ ] Verify top users table (admin only)
   - [ ] Test CSV export

3. **Governance**:
   - [ ] Trigger warning alert (75% usage)
   - [ ] Trigger critical alert (90% usage)
   - [ ] Verify block at 100% usage
   - [ ] Test rate limiting (100 requests/5min)
   - [ ] Verify abuse detection logs

4. **Cron Jobs**:
   - [ ] Manually trigger sync cron
   - [ ] Verify events sync to backend (when implemented)
   - [ ] Test cleanup cron removes old events

---

## Performance Considerations

1. **Database Indexes**: All queries use indexed columns
2. **Batch Processing**: Events synced in batches (50 max)
3. **Async Updates**: Daily summaries updated asynchronously
4. **Retention Policy**: Old events cleaned up automatically
5. **Caching**: Usage stats cached for 5 minutes

---

## Security Features

1. **User Privacy**: User IDs hashed before transmission
2. **Signature Validation**: HMAC-SHA256 signing for backend sync
3. **Rate Limiting**: Prevents API abuse
4. **Abuse Detection**: Identifies unusual patterns
5. **JWT Authentication**: Required for all API calls

---

## Next Steps

1. **Backend Implementation**: Follow `docs/backend-integration-guide.md`
2. **Testing**: Test end-to-end with real backend
3. **Monitoring**: Set up error logging and alerts
4. **Provider Dashboard**: Build centralized analytics (Phase 3.2)

---

*Last updated: 2025-11-03*

