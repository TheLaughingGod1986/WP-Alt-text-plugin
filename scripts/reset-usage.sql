-- Reset Usage for benoats@gmail.com
-- Run this with: render psql alttext-ai-db < scripts/reset-usage.sql

-- Step 1: Find user ID
SELECT id, email, plan FROM users WHERE email = 'benoats@gmail.com';

-- Step 2: Delete usage records for current month
-- Note: Replace [USER_ID] with the actual ID from Step 1
-- DELETE FROM usage WHERE user_id = [USER_ID] AND created_at >= DATE_TRUNC('month', CURRENT_DATE);

-- Step 3: Verify reset (should return 0)
-- SELECT COUNT(*) as remaining_usage FROM usage WHERE user_id = [USER_ID] AND created_at >= DATE_TRUNC('month', CURRENT_DATE);

