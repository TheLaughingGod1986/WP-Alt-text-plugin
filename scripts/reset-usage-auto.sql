-- Auto-reset usage (run this after getting user_id)
-- Usage: First run reset-usage.sql to get user_id, then replace [USER_ID] below and run this file

-- This will delete all usage records for the current month for benoats@gmail.com
-- Replace [USER_ID] with the actual ID from the first query

DO $$
DECLARE
    user_id_val INTEGER;
BEGIN
    -- Get user ID
    SELECT id INTO user_id_val FROM users WHERE email = 'benoats@gmail.com';
    
    IF user_id_val IS NULL THEN
        RAISE EXCEPTION 'User not found';
    END IF;
    
    -- Delete usage records
    DELETE FROM usage 
    WHERE user_id = user_id_val 
    AND created_at >= DATE_TRUNC('month', CURRENT_DATE);
    
    RAISE NOTICE 'Deleted usage records for user_id: %', user_id_val;
    
    -- Show remaining count
    RAISE NOTICE 'Remaining usage this month: %', (
        SELECT COUNT(*) FROM usage 
        WHERE user_id = user_id_val 
        AND created_at >= DATE_TRUNC('month', CURRENT_DATE)
    );
END $$;

