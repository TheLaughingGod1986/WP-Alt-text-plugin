# Reset Site Usage via Supabase

Use this guide to reset a site's usage to 0 directly in the backend Supabase database.

## 1. Get the site ID from WordPress

The site identifier is stored in WordPress options. Run one of these:

**WP-CLI (recommended):**
```bash
wp option get beepbeepai_site_id
```

**Multisite (per-blog):**
```bash
wp option get beepbeepai_site_id_1  # Replace 1 with blog_id
```

**PHP eval:**
```bash
wp eval "echo \BeepBeepAI\AltTextGenerator\get_site_identifier();"
```

The `site_id` is a 32-character MD5 hash (e.g. `a1b2c3d4e5f6...`). Use this value in the SQL below.

---

## 2. Supabase SQL to reset usage

The backend API (`/api/usage`) returns `credits_used`, `used`, `limit`, `remaining`, `resetDate`. Usage is typically stored per site. Adapt the table/column names to match your backend schema.

### Option A: Single `site_usage` or `usage` table

If usage is stored in a table with `site_id` and `credits_used` / `used`:

```sql
-- Reset credits_used to 0 for a specific site
UPDATE site_usage
SET credits_used = 0, used = 0
WHERE site_id = 'YOUR_SITE_ID_HERE';

-- Or if the column is named differently:
UPDATE usage
SET credits_used = 0
WHERE site_id = 'YOUR_SITE_ID_HERE';
```

### Option B: `sites` table with usage columns

```sql
UPDATE sites
SET credits_used = 0, used = 0
WHERE id = 'YOUR_SITE_ID_HERE'
   OR site_hash = 'YOUR_SITE_ID_HERE'
   OR site_id = 'YOUR_SITE_ID_HERE';
```

### Option C: Monthly usage / period-based table

If usage is tracked per period (e.g. `monthly_usage`, `usage_periods`):

```sql
-- Reset current month's usage
UPDATE monthly_usage
SET credits_used = 0
WHERE site_id = 'YOUR_SITE_ID_HERE'
  AND period_start <= NOW()
  AND period_end >= NOW();
```

### Option D: `credits_used` in a `site_quota` or similar table

```sql
UPDATE site_quota
SET credits_used = 0, used = 0
WHERE site_id = 'YOUR_SITE_ID_HERE';
```

---

## 3. Find the correct table (if schema is unknown)

In Supabase SQL Editor, list tables and inspect usage-related ones:

```sql
-- List all tables
SELECT table_name
FROM information_schema.tables
WHERE table_schema = 'public'
ORDER BY table_name;

-- Search for usage-related columns
SELECT table_name, column_name
FROM information_schema.columns
WHERE table_schema = 'public'
  AND (column_name ILIKE '%usage%' OR column_name ILIKE '%credit%' OR column_name ILIKE '%quota%' OR column_name ILIKE '%site%')
ORDER BY table_name, column_name;
```

---

## 4. After resetting in Supabase

1. **Clear local caches** on the WordPress site:
   ```bash
   wp beepbeepai reset-usage
   ```
   Or visit: `?page=bbai-media&clear_cache=1&_bbai_nonce=<nonce>`

2. **Reload the dashboard** – it will refetch usage from the API.

---

## Example (replace with your schema)

```sql
-- Replace YOUR_SITE_ID_HERE with the value from step 1
UPDATE site_usage
SET credits_used = 0,
    used = 0,
    remaining = 50,
    updated_at = NOW()
WHERE site_id = 'YOUR_SITE_ID_HERE';
```
