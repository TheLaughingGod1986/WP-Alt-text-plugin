-- Add a test record, then immediately delete it
-- This helps us verify the backend can handle queries

-- First, let's see what columns usage_logs needs
\d usage_logs;

-- Then we can insert a test record
-- INSERT INTO usage_logs ("userId", "createdAt") 
-- VALUES (2, CURRENT_TIMESTAMP);

