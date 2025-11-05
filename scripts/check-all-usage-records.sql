-- Check ALL usage records (all time, not just this month)
SELECT 
    COUNT(*) as total_records,
    MIN("createdAt") as first_record,
    MAX("createdAt") as last_record
FROM usage_logs ul
JOIN users u ON u.id = ul."userId"
WHERE u.email = 'benoats@gmail.com';

-- Count by month
SELECT 
    DATE_TRUNC('month', ul."createdAt")::date as month,
    COUNT(*) as usage_count
FROM usage_logs ul
JOIN users u ON u.id = ul."userId"
WHERE u.email = 'benoats@gmail.com'
GROUP BY DATE_TRUNC('month', ul."createdAt")
ORDER BY month DESC
LIMIT 12;

