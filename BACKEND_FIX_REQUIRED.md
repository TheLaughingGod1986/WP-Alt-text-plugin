# Backend Fix Required for Image ID Issue

## Problem
All regenerate requests are returning the same alt text because the backend is receiving `image_id: 5` for all requests, regardless of which image is being processed.

## Diagnostic Steps

### 1. Check Plugin Side (WordPress Debug Logs)
Look for these log entries when regenerating different images:
```
"Regenerate request received" - shows attachment_id being received
"generate_alt_text called" - shows image_id being used
"API Request Payload (BEFORE sending to backend)" - shows exact payload
```

**If plugin logs show:**
- Different `attachment_id` values → **Backend issue** ✅
- Same `attachment_id` value → **Plugin issue** ❌

### 2. Check Backend Logs
Look for this log entry:
```
[Generate] Request parsed, image_id: X
```

**If backend logs show:**
- Always `image_id: 5` → **Backend is caching or reading wrong field** ✅
- Different `image_id` values → **Backend working correctly** (issue elsewhere)

## Plugin Side Fix (if needed)

The plugin already sends `image_id` in TWO places:
1. Root level: `body.image_id = "7"`
2. Nested: `body.image_data.image_id = "7"`

### Backend Should Read From:
**Option 1 (Recommended):** Read from root level `image_id` first:
```javascript
const imageId = req.body.image_id || req.body.image_data?.image_id;
```

**Option 2:** Read from nested location:
```javascript
const imageId = req.body.image_data?.image_id || req.body.image_id;
```

## Backend Side Fix (if backend is the issue)

### Problem Location
The backend is likely:
1. Caching the first `image_id` received
2. Reading from the wrong field in the request body
3. Not properly extracting `image_id` from nested `image_data`

### Fix Required

**In the backend API handler (`routes/generate.js` or similar):**

```javascript
// BEFORE (possibly wrong):
const imageId = req.body.image_data?.image_id || 5; // ❌ Defaults to 5!

// AFTER (correct):
const imageId = req.body.image_id || req.body.image_data?.image_id;

// Or more defensive:
let imageId = null;
if (req.body.image_id) {
    imageId = String(req.body.image_id);
} else if (req.body.image_data?.image_id) {
    imageId = String(req.body.image_data.image_id);
} else {
    return res.status(400).json({
        success: false,
        error: 'Missing image_id in request body'
    });
}
```

### Additional Backend Checks

1. **Check for caching:**
   - Ensure `image_id` is extracted fresh from each request
   - Don't cache `image_id` between requests
   - Clear any request-level caches

2. **Check request parsing:**
   - Verify `req.body` contains the expected structure
   - Log `req.body` to see what's actually being received
   - Check if middleware is modifying the request body

3. **Check for hardcoded defaults:**
   - Search codebase for `image_id: 5` or `imageId = 5`
   - Remove any default values that might be overriding the actual `image_id`

## How to Verify the Fix

1. **Regenerate alt text for image ID 7**
   - Plugin logs should show: `image_id: 7`
   - Backend logs should show: `image_id: 7`
   - Response should describe image 7

2. **Regenerate alt text for image ID 9**
   - Plugin logs should show: `image_id: 9`
   - Backend logs should show: `image_id: 9`
   - Response should describe image 9 (different from image 7)

3. **If both still return same alt text:**
   - Check if backend is actually using `image_id` when fetching the image
   - Check if backend is caching image analysis results by URL instead of ID
   - Check if backend is using cached OpenAI responses

## Quick Test

Run this diagnostic script:
```bash
php scripts/diagnose-image-id-issue.php
```

This will show if the plugin is correctly preparing different `image_id` values.

