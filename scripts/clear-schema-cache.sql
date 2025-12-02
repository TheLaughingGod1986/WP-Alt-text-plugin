-- Clear Schema Cache (if using connection pooling)
-- After adding columns, you may need to refresh the schema cache
-- Run this if columns exist but backend still can't see them

-- ============================================
-- Option 1: If using PgBouncer or connection pooling
-- ============================================
-- You may need to reconnect or restart the connection pooler
-- This depends on your Supabase setup

-- ============================================
-- Option 2: Refresh statistics and analyze tables
-- ============================================
ANALYZE public.licenses;
ANALYZE public.sites;

-- ============================================
-- Option 3: Check if columns are actually accessible
-- ============================================
-- Verify columns exist and are accessible
SELECT 
    table_name,
    column_name,
    data_type,
    is_nullable,
    column_default
FROM information_schema.columns
WHERE table_schema = 'public'
  AND table_name IN ('licenses', 'sites')
  AND column_name IN ('auto_attach_status', 'created_at', 'plan')
ORDER BY table_name, column_name;

-- ============================================
-- Option 4: Test reading from the columns
-- ============================================
-- If this query works, columns are accessible
SELECT 
    COUNT(*) as license_count,
    COUNT(auto_attach_status) as has_auto_attach_status
FROM public.licenses;

SELECT 
    COUNT(*) as site_count,
    COUNT(created_at) as has_created_at,
    COUNT(plan) as has_plan
FROM public.sites;

