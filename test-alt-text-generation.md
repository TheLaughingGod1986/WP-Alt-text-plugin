# Test Alt Text Generation - Quick Guide

## Issue Resolved ✅

**Problem**: Images were not generating alt text due to invalid OpenAI API key on backend
**Solution**: Updated OpenAI API key on Render deployment
**Status**: Backend is now healthy and operational

---

## Next Steps to Test

### 1. **Clear Failed Queue Jobs**

Go to your WordPress admin panel:
- Navigate to: **Media → Alt Text AI**
- Click on the **"Queue"** or **"Dashboard"** tab
- You should see buttons to:
  - **"Retry Failed Jobs"** - This will retry all failed jobs with the new valid API key
  - **"Process Queue Now"** - This will manually trigger queue processing
  - **"Clear Completed"** - This will remove old completed jobs

### 2. **Test Alt Text Generation**

Option A: **Upload a New Image**
1. Go to **Media → Add New**
2. Upload a test image
3. The plugin should automatically queue it for alt text generation
4. Wait 30-60 seconds
5. Check the image in the Media Library - it should now have alt text

Option B: **Regenerate Existing Image**
1. Go to **Media → Library**
2. Click on any image
3. Look for the **"Generate Alt Text"** button (might be labeled differently)
4. Click it
5. Wait for the alt text to be generated

Option C: **Bulk Generate**
1. Go to **Media → Alt Text AI**
2. Select multiple images
3. Click **"Bulk Generate Alt Text"** or similar button
4. Monitor the queue progress

### 3. **Monitor Queue Status**

Check the queue to see if jobs are processing:
- **Pending**: Waiting to be processed
- **Processing**: Currently generating alt text
- **Completed**: Successfully generated
- **Failed**: Had an error (should be resolved now)

---

## What Was Wrong

The backend API at `https://alttext-ai-backend.onrender.com` had an incorrect OpenAI API key configured. When the WordPress plugin tried to generate alt text, it would:

1. ✅ Successfully queue the image
2. ✅ Send request to backend API
3. ❌ Backend would fail calling OpenAI (invalid key)
4. ❌ Return 500 error to WordPress
5. ❌ Job would fail and retry multiple times

**The Exact Error:**
```
"Incorrect API key provided: sk-proj-...4BIA"
```

---

## Verification

To confirm everything is working:

1. **Check Backend Health**:
   ```bash
   curl https://alttext-ai-backend.onrender.com/health
   ```
   Should return: `{"status":"ok","timestamp":"...","version":"2.0.0","phase":"monetization"}`

2. **Check WordPress Logs**:
   Look for success messages instead of error messages in your WordPress debug log

3. **Check Queue Progress**:
   The queue should show jobs moving from "pending" → "processing" → "completed"

---

## Troubleshooting

If alt text still isn't generating:

1. **Make sure you're logged in** to the Alt Text AI plugin (check authentication status)
2. **Verify you have credits/quota** available (free tier might be limited)
3. **Check the queue** - are there jobs stuck in "processing" for too long? They might need to be reset
4. **Look at the debug tab** in the plugin for error messages
5. **Check browser console** for any JavaScript errors

---

## Quick Commands for Testing

### Test Backend Directly (Terminal):
```bash
# Health check
curl https://alttext-ai-backend.onrender.com/health

# Check if Render deployment is complete
# (If you see 502, wait a minute and try again)
```

### View WordPress Logs:
```bash
docker logs wp-alt-text-plugin-wordpress-1 2>&1 | grep -i "alt text\|queue" | tail -20
```

---

## Summary

✅ Backend OpenAI API key updated on Render
✅ Backend is healthy and responding
✅ Queue system is operational
⏳ **Next**: Test alt text generation in WordPress admin panel

The plugin should now be working! Try uploading a new image or regenerating alt text for an existing one.
