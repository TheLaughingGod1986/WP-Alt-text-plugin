# Backend Credit Deduction Fix - Quick Summary

## The Problem
Credits are not being decremented when alt text is generated. Backend logs show `quotaRemaining: 50` even after successful generations.

## Root Causes
1. **Site not registered**: Warning `[DualAuth] Site not found for site hash` - site hash needs to be auto-registered
2. **No credit deduction**: Credits aren't decremented after successful generation
3. **Stale usage data**: `/usage` endpoint may be returning cached data

## Required Fixes

### 1. Auto-Register Sites
When a site authenticates with `X-Site-Hash` header:
- If site doesn't exist in DB → create it
- Attach to license
- Initialize with 50 free credits

### 2. Decrement Credits After Generation
In `POST /api/generate` endpoint:
```javascript
// After successful generation:
if (result.success && result.alt_text) {
  await db.sites.updateOne(
    { siteHash, licenseKey },
    { $inc: { quotaUsed: 1 } }
  );
  
  // Include updated usage in response
  result.usage = {
    used: site.quotaUsed + 1,
    limit: site.quotaLimit,
    remaining: site.quotaLimit - (site.quotaUsed + 1)
  };
}
```

### 3. Return Real-Time Usage
`GET /api/usage` should:
- Query database directly (not cache)
- Return current `quotaUsed` value
- Calculate `remaining = limit - used`

## Expected API Response

**After generation** (`POST /api/generate`):
```json
{
  "success": true,
  "alt_text": "...",
  "usage": {
    "used": 1,      // ← Should increment
    "limit": 50,
    "remaining": 49  // ← Should decrease
  }
}
```

**Usage endpoint** (`GET /api/usage`):
```json
{
  "usage": {
    "used": 1,      // ← Real-time from DB
    "limit": 50,
    "remaining": 49
  }
}
```

## Testing
1. Generate alt text → check DB: `quotaUsed` should be 1
2. Call `/usage` → should return `used: 1, remaining: 49`
3. Generate again → `quotaUsed` should be 2, `remaining: 48`

## Full Details
See `BACKEND_CREDIT_DEDUCTION_FIX.md` for complete implementation plan.

