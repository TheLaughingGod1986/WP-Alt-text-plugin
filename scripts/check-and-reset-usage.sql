-- Check user and current usage, then optionally reset
-- This script will:
-- 1. Find the user
-- 2. Show current month usage count
-- 3. Reset usage (uncomment the DELETE line to actually reset)

-- Step 1: Find user
SELECT id, email, plan, created_at 
FROM users 
WHERE email = 'benoats@gmail.com';

-- Step 2: Show current month usage (replace [USER_ID] with the ID from above)
-- For now, let's use a subquery to do it all at once:
SELECT 
    u.id as user_id,
    u.email,
    u.plan,
    COUNT(us.id) as usage_count,
    DATE_TRUNC('month', CURRENT_DATE) as month_start
FROM users u
LEFT JOIN usage us ON us.user_id = u.id 
    AND us.created_at >= DATE_TRUNC('month', CURRENT_DATE)
WHERE u.email = 'benoats@gmail.com'
GROUP BY u.id, u.email, u.plan;

-- Step 3: Reset usage (uncomment to execute)
-- DELETE FROM usage 
-- WHERE user_id = (SELECT id FROM users WHERE email = 'benoats@gmail.com')
-- AND created_at >= DATE_TRUNC('month', CURRENT_DATE);

-- Step 4: Verify after reset
-- SELECT COUNT(*) as remaining_usage 
-- FROM usage 
-- WHERE user_id = (SELECT id FROM users WHERE email = 'benoats@gmail.com')
-- AND created_at >= DATE_TRUNC('month', CURRENT_DATE);

