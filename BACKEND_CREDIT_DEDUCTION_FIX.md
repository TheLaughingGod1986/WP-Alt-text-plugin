# Backend Credit Deduction Fix - Implementation Plan

## Problem Summary

Credits are not being decremented when alt text is generated. The frontend dashboard shows usage, but the backend consistently reports `quotaRemaining: 50` even after successful generations.

## Current Behavior (From Logs)

```
[DualAuth] Site not found for site hash: f7d4e04305d8b16f7c673ec7dff713ce
[AuthenticateBySiteHashForQuota] Site authentication
  siteHash: f7d4e04305d8b16f7c673ec7dff713ce
  hasLicense: true
  quotaRemaining: 50  ← Should decrease after generation
  quotaLimit: 50
  plan: "free"
```

**Issue**: After successful generation, `quotaRemaining` stays at 50 instead of decreasing to 49, 48, etc.

## Root Cause Analysis

1. **Site Registration Issue**: The warning `[DualAuth] Site not found for site hash` suggests the site hash is not properly registered/attached to the license in the database.

2. **Credit Deduction Not Happening**: Even though authentication succeeds, credits are not being decremented after successful generation.

3. **Usage Tracking**: The `/usage` endpoint returns cached/stale data instead of real-time usage.

## Required Fixes

### 1. Fix Site Registration

**Problem**: Site hash `f7d4e04305d8b16f7c673ec7dff713ce` is not found in the database.

**Solution**:
- When a site authenticates via `X-Site-Hash` header, ensure the site is automatically registered/attached to the license
- If site doesn't exist, create it and attach to the license
- Store the site hash → license relationship in the database

**Implementation**:
```javascript
// Pseudo-code for site registration
async function ensureSiteRegistered(siteHash, licenseKey) {
  let site = await db.sites.findOne({ siteHash, licenseKey });
  
  if (!site) {
    // Auto-register site on first request
    site = await db.sites.create({
      siteHash,
      licenseKey,
      createdAt: new Date(),
      quotaLimit: 50, // Free plan default
      quotaUsed: 0,
      plan: 'free'
    });
  }
  
  return site;
}
```

### 2. Implement Credit Deduction

**Problem**: Credits are not decremented after successful generation.

**Solution**: Decrement credits immediately after successful alt text generation.

**Implementation**:
```javascript
// In the generation endpoint (POST /api/generate)
async function generateAltText(imageData, siteHash, licenseKey) {
  // 1. Authenticate and get quota
  const site = await ensureSiteRegistered(siteHash, licenseKey);
  
  // 2. Check if credits available
  if (site.quotaUsed >= site.quotaLimit) {
    return { error: 'out_of_credits', message: 'Monthly quota exceeded' };
  }
  
  // 3. Generate alt text
  const result = await openai.generateAltText(imageData);
  
  // 4. ONLY decrement credits if generation succeeded
  if (result.success && result.alt_text) {
    await db.sites.updateOne(
      { siteHash, licenseKey },
      { 
        $inc: { quotaUsed: 1 },
        $set: { lastUsedAt: new Date() }
      }
    );
    
    // Update quota remaining in response
    site.quotaUsed += 1;
    result.usage = {
      used: site.quotaUsed,
      limit: site.quotaLimit,
      remaining: site.quotaLimit - site.quotaUsed
    };
  }
  
  return result;
}
```

### 3. Update Usage Endpoint

**Problem**: `/usage` endpoint returns stale data.

**Solution**: Always return real-time usage from database.

**Implementation**:
```javascript
// GET /api/usage
async function getUsage(siteHash, licenseKey) {
  const site = await db.sites.findOne({ siteHash, licenseKey });
  
  if (!site) {
    // If site not found, create it with default free credits
    return {
      used: 0,
      limit: 50,
      remaining: 50,
      plan: 'free',
      resetDate: getNextMonthFirstDay()
    };
  }
  
  // Calculate remaining
  const remaining = Math.max(0, site.quotaLimit - site.quotaUsed);
  
  return {
    used: site.quotaUsed,
    limit: site.quotaLimit,
    remaining: remaining,
    plan: site.plan || 'free',
    resetDate: site.resetDate || getNextMonthFirstDay(),
    resetTimestamp: getTimestamp(site.resetDate)
  };
}
```

### 4. Handle Monthly Reset

**Problem**: Credits need to reset on the first of each month.

**Solution**: Implement monthly reset logic.

**Implementation**:
```javascript
// Run this as a scheduled job (cron) on the 1st of each month
async function resetMonthlyQuotas() {
  const now = new Date();
  const firstOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
  
  // Reset all sites that haven't been reset this month
  await db.sites.updateMany(
    { 
      lastResetAt: { $lt: firstOfMonth },
      plan: 'free' // Only reset free plan sites
    },
    {
      $set: {
        quotaUsed: 0,
        lastResetAt: firstOfMonth,
        resetDate: getNextMonthFirstDay()
      }
    }
  );
}
```

## API Contract Requirements

### Generation Endpoint: `POST /api/generate`

**Request Headers**:
- `X-Site-Hash: f7d4e04305d8b16f7c673ec7dff713ce` (required)
- `Authorization: Bearer <token>` or `X-License-Key: <key>` (required)

**Response** (on success):
```json
{
  "success": true,
  "alt_text": "A red bicycle parked against a brick wall",
  "usage": {
    "used": 1,
    "limit": 50,
    "remaining": 49,
    "plan": "free"
  },
  "resetDate": "2026-02-01",
  "resetTimestamp": 1704067200
}
```

**Response** (on quota exceeded):
```json
{
  "success": false,
  "error": "out_of_credits",
  "reason": "no_credits",
  "message": "Monthly quota exceeded. Upgrade to Pro for more credits.",
  "credits": 0,
  "usage": {
    "used": 50,
    "limit": 50,
    "remaining": 0
  }
}
```

### Usage Endpoint: `GET /api/usage`

**Request Headers**:
- `X-Site-Hash: f7d4e04305d8b16f7c673ec7dff713ce` (required)
- `Authorization: Bearer <token>` or `X-License-Key: <key>` (required)

**Response**:
```json
{
  "success": true,
  "usage": {
    "used": 1,
    "limit": 50,
    "remaining": 49,
    "plan": "free",
    "resetDate": "2026-02-01",
    "resetTimestamp": 1704067200
  }
}
```

## Database Schema

### Sites Table

```sql
CREATE TABLE sites (
  id UUID PRIMARY KEY,
  site_hash VARCHAR(64) UNIQUE NOT NULL,
  license_key VARCHAR(255) NOT NULL,
  quota_limit INT DEFAULT 50,
  quota_used INT DEFAULT 0,
  plan VARCHAR(50) DEFAULT 'free',
  reset_date DATE,
  last_reset_at TIMESTAMP,
  last_used_at TIMESTAMP,
  created_at TIMESTAMP DEFAULT NOW(),
  updated_at TIMESTAMP DEFAULT NOW(),
  
  INDEX idx_site_hash (site_hash),
  INDEX idx_license_key (license_key),
  INDEX idx_reset_date (reset_date)
);
```

## Testing Checklist

### Test 1: Site Auto-Registration
- [ ] Send generation request with new site hash
- [ ] Verify site is created in database
- [ ] Verify site is attached to license
- [ ] Verify initial quota is set to 50

### Test 2: Credit Deduction
- [ ] Generate alt text successfully
- [ ] Verify `quotaUsed` increments by 1 in database
- [ ] Verify `/usage` endpoint returns updated count
- [ ] Verify response includes updated `usage` object

### Test 3: Quota Enforcement
- [ ] Use all 50 credits
- [ ] Attempt to generate alt text (51st request)
- [ ] Verify error response: `out_of_credits`
- [ ] Verify credits don't go negative

### Test 4: Monthly Reset
- [ ] Set test site's `lastResetAt` to previous month
- [ ] Run reset job
- [ ] Verify `quotaUsed` resets to 0
- [ ] Verify `resetDate` updates to next month

### Test 5: Usage Endpoint Accuracy
- [ ] Generate 5 alt texts
- [ ] Call `/usage` endpoint
- [ ] Verify `used: 5`, `remaining: 45`
- [ ] Verify data matches database exactly

## Error Handling

### Site Not Found
- **Action**: Auto-create site with default free plan (50 credits)
- **Log**: `INFO: Auto-registering new site: {siteHash}`
- **Response**: Continue with generation as normal

### License Not Found
- **Action**: Return authentication error
- **Response**: `401 Unauthorized` with message "Invalid license key"

### Quota Exceeded
- **Action**: Return error before generation
- **Response**: `402 Payment Required` or `403 Forbidden` with `out_of_credits` error

## Frontend Integration Points

The frontend expects:

1. **After Generation**: Response includes updated `usage` object
2. **Usage Endpoint**: Returns real-time data (not cached)
3. **Error Handling**: Clear error codes (`out_of_credits`, `no_credits`)
4. **Reset Dates**: Accurate `resetDate` and `resetTimestamp` fields

## Priority

1. **HIGH**: Fix credit deduction (users can't track usage)
2. **HIGH**: Fix site registration (prevents proper tracking)
3. **MEDIUM**: Implement monthly reset (prevents stale quotas)
4. **LOW**: Add caching optimization (after core functionality works)

## Questions for Backend Team

1. Is there a scheduled job for monthly resets? If not, when should it run?
2. Should credit deduction happen synchronously or can it be async?
3. Do we need to handle partial failures (generation succeeds but deduction fails)?
4. Should we log all credit deductions for audit purposes?
5. Are there any rate limiting considerations for the `/usage` endpoint?

## Contact

If you need clarification on any of these requirements, please reach out. The frontend is ready to consume the updated API responses once the backend is fixed.

