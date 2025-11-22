-- PostgreSQL/Supabase SQL Queries to Reset Credits
-- Run these in your Supabase SQL Editor
-- Replace {prefix} with your WordPress table prefix (usually 'wp_')

-- ============================================
-- QUICK RESET (Just run these queries):
-- ============================================

-- 1. Clear usage cache
DELETE FROM {prefix}options 
WHERE option_name = '_transient_bbai_usage_cache' 
   OR option_name = '_transient_timeout_bbai_usage_cache'
   OR option_name LIKE 'bbai_token_quota_%';

-- 2. Set usage to 0/50 (expires in 5 minutes = 300 seconds)
-- Note: Adjust resetDate to first day of next month (currently set to 2025-12-01)
INSERT INTO {prefix}options (option_name, option_value, autoload) 
VALUES (
    '_transient_bbai_usage_cache',
    'a:7:{s:4:"used";i:0;s:5:"limit";i:50;s:9:"remaining";i:50;s:4:"plan";s:4:"free";s:9:"resetDate";s:10:"2025-12-01";s:15:"reset_timestamp";i:1730419200;s:19:"seconds_until_reset";i:2592000;}',
    'no'
) ON CONFLICT (option_name) DO UPDATE SET option_value = EXCLUDED.option_value;

INSERT INTO {prefix}options (option_name, option_value, autoload) 
VALUES (
    '_transient_timeout_bbai_usage_cache',
    EXTRACT(EPOCH FROM NOW())::bigint + 300,
    'no'
) ON CONFLICT (option_name) DO UPDATE SET option_value = EXCLUDED.option_value;

-- 3. Clear credit usage logs (optional - comment out if you want to keep history)
-- TRUNCATE TABLE {prefix}bbai_credit_usage;

-- 4. Clear usage event logs (optional - comment out if you want to keep history)
-- TRUNCATE TABLE {prefix}bbai_usage_logs;

-- ============================================
-- EVEN SIMPLER VERSION (Just clear cache):
-- ============================================
-- DELETE FROM {prefix}options 
-- WHERE option_name LIKE '_transient%bbai_usage_cache%' 
--    OR option_name LIKE 'bbai_token_quota_%';

