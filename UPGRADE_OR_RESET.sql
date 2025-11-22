-- Upgrade to Pro OR Reset Credits on Render Database
-- Run this in Render Dashboard → alttext-ai-db → Connect → Query Editor

-- OPTION 1: Reset Credits to 0 (Keep Free Plan)
DELETE FROM usage 
WHERE user_id = (SELECT id FROM users WHERE email = 'benoats@gmail.com')
AND created_at >= DATE_TRUNC('month', CURRENT_DATE);

-- OPTION 2: Upgrade to Pro Plan (1,000 credits/month)
UPDATE users 
SET plan = 'pro'
WHERE email = 'benoats@gmail.com';

-- OPTION 3: Do BOTH - Reset credits AND upgrade to Pro
-- First reset:
DELETE FROM usage 
WHERE user_id = (SELECT id FROM users WHERE email = 'benoats@gmail.com')
AND created_at >= DATE_TRUNC('month', CURRENT_DATE);

-- Then upgrade:
UPDATE users 
SET plan = 'pro'
WHERE email = 'benoats@gmail.com';

-- Verify changes
SELECT 
    u.id as user_id,
    u.email,
    u.plan,
    COUNT(us.id) as usage_count_this_month
FROM users u
LEFT JOIN usage us ON us.user_id = u.id 
    AND us.created_at >= DATE_TRUNC('month', CURRENT_DATE)
WHERE u.email = 'benoats@gmail.com'
GROUP BY u.id, u.email, u.plan;

