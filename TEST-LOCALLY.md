# 🧪 Test Your Plugin Locally

Your backend is live! Now let's test the full integration.

## Quick Test Setup

### 1. Install Plugin on WordPress

```bash
# If using Local by Flywheel, XAMPP, MAMP, etc:
# Copy the plugin folder to your WordPress plugins directory
cp -r /path/to/WP-Alt-text-plugin /path/to/wordpress/wp-content/plugins/ai-alt-gpt
```

Or ZIP and install through WordPress admin:

```bash
cd /Users/benjaminoats/Library/CloudStorage/SynologyDrive-File-sync/Coding/wp-alt-text-ai
zip -r ai-alt-gpt.zip WP-Alt-text-plugin/ -x "*.git*" "*/node_modules/*" "*/backend/*"
```

Then upload via **Plugins → Add New → Upload Plugin**

### 2. Activate & Configure

1. Go to **Plugins → Installed Plugins**
2. Click **Activate** on "AI Alt Text Generator"
3. Go to **Media → AI Alt Text**
4. The API URL should already be set to: `https://alttext-ai-backend.onrender.com`
5. Save settings

### 3. Test Generation

#### Option A: From Dashboard

1. Go to **Media → AI Alt Text**
2. Look for the usage widget showing "0 of 50 used"
3. Click **"Process Existing Images"**
4. Select an image
5. Watch it generate!

#### Option B: From Media Library

1. Go to **Media → Library**
2. Click on any image
3. Look for the "Generate Alt Text" button
4. Click it and watch the magic! ✨

### 4. Verify Everything Works

Check these things:

- [ ] Usage counter updates (e.g., "1 of 50 used")
- [ ] Alt text appears in the image attachment
- [ ] Gamification XP/achievements show (if you kept those features)
- [ ] API calls complete within ~5 seconds
- [ ] No error messages

### 5. Test the Upgrade Modal

To see the upgrade prompt:

1. Make a direct API call to simulate hitting the limit:

```bash
# Test 49 times to get close to limit (or edit db.json on Render)
for i in {1..49}; do
  curl -s -X POST https://alttext-ai-backend.onrender.com/api/generate \
    -H "Content-Type: application/json" \
    -H "X-API-Secret: alttext-secret-key-2024" \
    -d "{\"imageUrl\":\"test$i.jpg\",\"domain\":\"yoursite.local\"}" > /dev/null
  echo "Generated $i"
done
```

2. Then try generating in WordPress - you should see the upgrade modal!

## 🐛 Troubleshooting

### "API Error" or "Connection Failed"

**Check:**

1. Is Render still running? Visit: https://alttext-ai-backend.onrender.com/health
2. Check WordPress error logs
3. Check Render logs: https://dashboard.render.com

### "Unauthorized" or 403 Error

**Fix:** The API secret might be wrong

1. Go to Render dashboard
2. Check `API_SECRET` environment variable
3. Make sure it matches in the plugin code

### Usage counter not updating

**Check:**

1. Open browser console (F12)
2. Look for JavaScript errors
3. Check if AJAX calls are succeeding

## ✅ Success Checklist

Before submitting to WordPress.org:

- [ ] Plugin activates without errors
- [ ] Settings page loads correctly
- [ ] Alt text generation works
- [ ] Usage counter displays and updates
- [ ] Upgrade modal appears at limit
- [ ] No PHP errors in debug.log
- [ ] No JavaScript console errors
- [ ] Works on latest WordPress version
- [ ] Works with default WordPress theme (Twenty Twenty-Four)

## 🎯 Next: WordPress.org Submission

Once everything tests perfectly, you're ready for:

1. Create `readme.txt`
2. Take screenshots
3. Create graphics (banner, icon)
4. Submit to WordPress.org

---

**Need help?** Check `DEPLOYMENT-SUCCESS.md` for the full setup details!


