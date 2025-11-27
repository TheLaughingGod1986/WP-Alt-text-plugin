# Reset Credits on Render Database

## Quick Method: Use Render Dashboard

### Steps:

1. **Go to Render Dashboard**: https://dashboard.render.com

2. **Navigate to your database service**: Look for `alttext-ai-db` (or your database service name)

3. **Click "Connect"** button (or go to "Info" tab → "Connect")

4. **Open the database console/query editor**

5. **Paste and run this SQL to reset your credits**:

```sql
-- Reset usage for current month
DELETE FROM usage 
WHERE user_id = (SELECT id FROM users WHERE email = 'benoats@gmail.com')
AND created_at >= DATE_TRUNC('month', CURRENT_DATE);
```

6. **Verify the reset worked**:

```sql
-- Check remaining usage (should be 0)
SELECT COUNT(*) as remaining_usage 
FROM usage 
WHERE user_id = (SELECT id FROM users WHERE email = 'benoats@gmail.com')
AND created_at >= DATE_TRUNC('month', CURRENT_DATE);
```

7. **After resetting on Render, clear WordPress cache**:
   - Go to WordPress Admin
   - Navigate to the plugin dashboard
   - The credits should now show as 0/50

---

## Alternative: Using Render CLI

If you have Render CLI installed:

```bash
render psql alttext-ai-db
```

Then paste the SQL commands above and press Enter.

---

## What This Does:

- Deletes all usage records for your email (`benoats@gmail.com`) for the current month
- Your credits will be reset to 0 used / 50 remaining
- This only affects the backend database; WordPress cache will update on next refresh

---

**Note**: Replace `benoats@gmail.com` with your actual email if different.

