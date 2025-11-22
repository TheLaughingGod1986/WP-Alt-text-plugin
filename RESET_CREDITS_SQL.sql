-- Reset Credits on Render Database
-- Copy and paste this entire block into Render Dashboard → alttext-ai-db → Connect → Query Editor

-- Step 1: Check current usage (optional)
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

-- Step 2: Reset usage to 0 (RUN THIS)
DELETE FROM usage 
WHERE user_id = (SELECT id FROM users WHERE email = 'benoats@gmail.com')
AND created_at >= DATE_TRUNC('month', CURRENT_DATE);

-- Step 3: Verify reset worked (should show 0)
SELECT COUNT(*) as remaining_usage 
FROM usage 
WHERE user_id = (SELECT id FROM users WHERE email = 'benoats@gmail.com')
AND created_at >= DATE_TRUNC('month', CURRENT_DATE);

