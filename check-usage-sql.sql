-- SQL Queries to Check AltText AI Usage
-- Run these in your WordPress database (phpMyAdmin, MySQL client, etc.)

-- 1. Check cached usage data from WordPress transients
-- Usage data is stored in wp_options table with option_name like '_transient_beepbeepai_usage_cache'
SELECT 
    option_name,
    option_value,
    FROM_UNIXTIME(SUBSTRING_INDEX(option_name, '_transient_timeout_', -1)) as expiry_time
FROM wp_options
WHERE option_name LIKE '%beepbeepai_usage_cache%'
ORDER BY option_name;

-- 2. If the transient exists, decode the JSON to see usage details
-- The option_value contains serialized PHP data, but you can see the structure
SELECT 
    option_name,
    option_value
FROM wp_options
WHERE option_name = '_transient_beepbeepai_usage_cache';

-- 3. Check if usage events table exists and count events
SELECT 
    COUNT(*) as total_events,
    COUNT(DISTINCT DATE(created_at)) as days_with_events
FROM wp_alttextai_usage_events
WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01');

-- 4. Count events by month
SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(*) as event_count
FROM wp_alttextai_usage_events
GROUP BY DATE_FORMAT(created_at, '%Y-%m')
ORDER BY month DESC
LIMIT 6;

-- 5. Get current month usage count
SELECT 
    COUNT(*) as current_month_usage
FROM wp_alttextai_usage_events
WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m');

