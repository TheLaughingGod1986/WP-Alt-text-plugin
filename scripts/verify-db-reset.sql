-- Verify everything is reset
SELECT 'Total usage_logs for user 2' as check_type, COUNT(*) as count FROM usage_logs WHERE "userId" = 2
UNION ALL
SELECT 'Usage this month (Dec 2025)', COUNT(*) FROM usage_logs WHERE "userId" = 2 AND "createdAt" >= DATE_TRUNC('month', CURRENT_DATE)
UNION ALL
SELECT 'Usage in Oct 2025', COUNT(*) FROM usage_logs WHERE "userId" = 2 AND "createdAt" >= '2025-10-01' AND "createdAt" < '2025-11-01';


