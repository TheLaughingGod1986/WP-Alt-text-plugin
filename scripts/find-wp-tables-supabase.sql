-- Check what tables exist in Supabase
-- Run this first to see what tables you have access to

-- List all tables in the current schema
SELECT table_name 
FROM information_schema.tables 
WHERE table_schema = 'public'
ORDER BY table_name;

-- Check if there's an 'options' table (without prefix)
SELECT table_name 
FROM information_schema.tables 
WHERE table_schema = 'public' 
  AND table_name LIKE '%options%';

-- Check for any WordPress-like tables
SELECT table_name 
FROM information_schema.tables 
WHERE table_schema = 'public' 
  AND (table_name LIKE '%option%' 
       OR table_name LIKE '%user%' 
       OR table_name LIKE '%post%');

