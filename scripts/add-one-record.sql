-- Add ONE test record to see if API works with data
INSERT INTO usage_logs ("userId", "createdAt") 
VALUES (2, CURRENT_TIMESTAMP)
RETURNING id, "userId", "createdAt";

-- Verify
SELECT COUNT(*) as usage_count FROM usage_logs WHERE "userId" = 2;

