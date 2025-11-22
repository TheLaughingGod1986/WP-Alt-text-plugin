# ✅ Clear Usage Cache - Quick Fix

## The Problem
WordPress shows "0/50" credits, but the upgrade modal appears when you click "Regenerate". This is because WordPress has cached old usage data.

## ✅ Solution: Run This in Browser Console

1. **Open your WordPress Admin** (go to Media → AI ALT Text)
2. **Open Browser Developer Tools** (Press `F12` or Right-click → Inspect)
3. **Click on the "Console" tab**
4. **Copy and paste this command, then press Enter:**

```javascript
jQuery.post(ajaxurl, {
    action: 'beepbeepai_refresh_usage',
    nonce: jQuery('#license-nonce').val() || BBAI?.nonce || ''
}, function(response) {
    if (response.success) {
        console.log('✅ Cache cleared! Usage refreshed:', response.data);
        location.reload();
    } else {
        console.error('❌ Error:', response.data);
        alert('Failed to refresh usage. Please try refreshing the page manually.');
    }
});
```

5. **The page will automatically reload** with fresh usage data.

## What This Does

- ✅ Clears all cached usage data
- ✅ Fetches fresh data from the API (which shows 0 used, 50 remaining)
- ✅ Updates the cache with correct values
- ✅ Refreshes the page to show updated credits

## Alternative: If Console Command Doesn't Work

Try this simpler version:

```javascript
fetch(ajaxurl, {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'action=beepbeepai_refresh_usage&nonce=' + (BBAI?.nonce || '')
}).then(r => r.json()).then(data => {
    console.log('Response:', data);
    location.reload();
});
```

## After Clearing

1. ✅ The page will reload automatically
2. ✅ Your credits should now show correctly (0/50)
3. ✅ Try generating alt text again - the modal should no longer appear!

## Database Status (Confirmed)

- ✅ Used: **0**
- ✅ Limit: **50**  
- ✅ Remaining: **50**

The database is correct - we just needed to refresh WordPress cache!

