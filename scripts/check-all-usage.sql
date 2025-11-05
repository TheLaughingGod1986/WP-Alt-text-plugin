-- Check ALL usage records for benoats@gmail.com
SELECT 
    u.id, 
    u.email, 
    u.plan,
    COUNT(ul.id) as total_usage_all_time,
    COUNT(CASE WHEN ul."createdAt" >= DATE_TRUNC('month', CURRENT_DATE) THEN 1 END) as usage_this_month,
    MIN(ul."createdAt") as first_usage,
    MAX(ul."createdAt") as last_usage
FROM users u
LEFT JOIN usage_logs ul ON ul."userId" = u.id
WHERE u.email = 'benoats@gmail.com'
GROUP BY u.id, u.email, u.plan;

-- Show breakdown by month
SELECT 
    DATE_TRUNC('month', ul."createdAt") as month,
    COUNT(*) as usage_count
FROM usage_logs ul
JOIN users u ON u.id = ul."userId"
WHERE u.email = 'benoats@gmail.com'
GROUP BY DATE_TRUNC('month', ul."createdAt")
ORDER BY month DESC;
