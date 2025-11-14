-- Quick SQL Query to Check Your Usage
-- Run this in phpMyAdmin or your MySQL client
-- Replace 'wp_' with your actual table prefix if different

-- Check the cached usage data
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

-- To see if you've used all 50 credits, look for the option_value in the row
-- where option_name = '_transient_opptiai_alt_usage_cache'
-- The value will be serialized PHP data, but you can see numbers like:
-- "used";i:50 means you've used 50
-- "limit";i:50 means your limit is 50
-- "remaining";i:0 means you have 0 remaining

-- Alternative: Count usage events from the events table (if it exists)
SELECT 
    COUNT(*) as total_generations_this_month
FROM wp_alttextai_usage_events
WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m');

