# Quick Usage Check Instructions

## Option 1: Run from WordPress Root Directory

1. Navigate to your WordPress root directory (where `wp-config.php` is located)
2. Run:
   ```bash
   php wp-content/plugins/opptiai-alt-text-generator/check-db-usage.php
   ```

## Option 2: Direct SQL Query (phpMyAdmin or MySQL Client)

Run this SQL query in your database:

```sql
-- Check usage cache
SELECT 
    option_name,
    option_value,
    CASE 
        WHEN option_name LIKE '%_transient_timeout_%' THEN 
            FROM_UNIXTIME(CAST(option_value AS UNSIGNED))
        ELSE NULL
    END as expires_at
FROM wp_options
WHERE option_name LIKE '%opptiai_alt_usage_cache%'
ORDER BY option_name;
```

**To check if you've used all 50 credits:**

Look at the row where `option_name = '_transient_opptiai_alt_usage_cache'`. 

In the `option_value` field, look for:
- `"used";i:50` = You've used 50 credits ✅
- `"limit";i:50` = Your limit is 50
- `"remaining";i:0` = 0 remaining

## Option 3: Count Events from Events Table

```sql
SELECT COUNT(*) as total_generations_this_month
FROM wp_alttextai_usage_events
WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m');
```

This counts all generation events for the current month.

---

**Note:** The usage cache expires after 5 minutes. If you don't see data, visit the plugin dashboard to refresh it.

