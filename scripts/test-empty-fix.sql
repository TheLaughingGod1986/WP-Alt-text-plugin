-- Check table structure first
\d usage_logs;

-- Add ONE test record for current month (December 2025)
-- This will make usage = 1/50, but let's see if API works
INSERT INTO usage_logs ("userId", "createdAt") 
VALUES (2, CURRENT_TIMESTAMP)
RETURNING id, "userId", "createdAt";

-- Verify it was added
SELECT COUNT(*) as current_usage FROM usage_logs WHERE "userId" = 2;


