# Reset Usage in Render Database

Since Render CLI's `psql` is interactive, the easiest way is to use the **Render Dashboard**.

## Quick Steps:

1. **Go to Render Dashboard**: https://dashboard.render.com
2. **Navigate to**: `alttext-ai-db` service
3. **Click**: "Connect" button (or "Info" tab → "Connect")
4. **Open**: The database console/query editor
5. **Paste and run** the SQL below:

## SQL to Check Current Status:

```sql
-- Check user and current usage
SELECT 
    u.id as user_id,
    u.email,
    u.plan,
    COUNT(us.id) as usage_count_this_month,
    DATE_TRUNC('month', CURRENT_DATE) as month_start
FROM users u
LEFT JOIN usage us ON us.user_id = u.id 
    AND us.created_at >= DATE_TRUNC('month', CURRENT_DATE)
WHERE u.email = 'benoats@gmail.com'
GROUP BY u.id, u.email, u.plan;
```

## SQL to Reset Usage:

```sql
-- Reset usage for current month
DELETE FROM usage 
WHERE user_id = (SELECT id FROM users WHERE email = 'benoats@gmail.com')
AND created_at >= DATE_TRUNC('month', CURRENT_DATE);
```

## SQL to Verify After Reset:

```sql
-- Verify usage is now 0
SELECT COUNT(*) as remaining_usage 
FROM usage 
WHERE user_id = (SELECT id FROM users WHERE email = 'benoats@gmail.com')
AND created_at >= DATE_TRUNC('month', CURRENT_DATE);
```

---

## Alternative: Using Render CLI (Interactive)

If you prefer CLI, you can try:

1. Run: `render psql alttext-ai-db`
2. When the interactive prompt opens, paste the SQL above
3. Press Enter to execute
4. Type `\q` to quit


