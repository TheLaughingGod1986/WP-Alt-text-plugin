-- SQL Script to Reset Usage via Render CLI
-- Run with: render psql <database-service-name> < reset-usage-render-direct.sql

-- Step 1: Find user ID
SELECT id, email, plan FROM users WHERE email = 'benoats@gmail.com';

-- Step 2: After noting the user_id from above, uncomment and run this:
-- DELETE FROM usage WHERE user_id = [USER_ID] AND created_at >= DATE_TRUNC('month', CURRENT_DATE);

-- Step 3: Verify reset
-- SELECT COUNT(*) as remaining_usage FROM usage WHERE user_id = [USER_ID] AND created_at >= DATE_TRUNC('month', CURRENT_DATE);

