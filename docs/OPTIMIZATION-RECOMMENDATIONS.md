# Optimization Recommendations

**Date:** March 4, 2026  
**Scope:** Backend, Frontend, Database – safe changes that won't affect current users.

---

## 1. Database (Supabase)

### 1.1 Scheduled cleanup jobs (safe, recommended)

Add cron jobs to prune old data. **No schema changes, no user impact.**

```sql
-- Run daily: delete expired dashboard sessions (older than 7 days)
DELETE FROM dashboard_sessions WHERE expires_at < NOW() - INTERVAL '7 days';

-- Run weekly: delete old debug logs (older than 90 days)
DELETE FROM debug_logs WHERE created_at < NOW() - INTERVAL '90 days';
```

**How:** Supabase Dashboard → Database → Extensions → enable `pg_cron`, or use an external cron calling a small API endpoint.

### 1.2 Index verification (safe)

Confirm indexes exist for common query patterns. Run in SQL Editor:

```sql
SELECT indexname, tablename FROM pg_indexes WHERE schemaname = 'public' AND tablename IN ('usage_logs', 'sites', 'licenses');
```

Expected: `idx_usage_logs_license_key`, `idx_usage_logs_created_at`, `idx_sites_site_hash`, etc. Add any missing.

### 1.3 usage_logs partitioning (future, when volume grows)

If `usage_logs` exceeds ~100k rows, consider partitioning by `created_at` (monthly). **Defer until needed** – requires migration.

---

## 2. Backend (oppti-backend)

### 2.1 In-memory rate limit → Redis (low risk)

`altTextRateLimits` uses an in-memory `Map`. On Render, instances can scale, so limits won't be shared. Move to Redis (like the main rate limiter):

```js
// In checkRateLimit: use redis.incr + redis.expire instead of Map
```

**Impact:** More accurate rate limiting across instances. No user-facing change if limits are generous.

### 2.2 Response compression (safe)

Add compression middleware to reduce payload size:

```js
const compression = require('compression');
app.use(compression());
```

**Impact:** Smaller responses, faster loads. No behavior change.

### 2.3 Billing plans caching (safe)

`/billing/plans` returns static data. Cache for 5–10 minutes:

```js
let plansCache = null;
let plansCacheExpiry = 0;
router.get('/plans', (req, res) => {
  if (plansCache && Date.now() < plansCacheExpiry) {
    return res.json(plansCache);
  }
  plansCache = { success: true, plans };
  plansCacheExpiry = Date.now() + 5 * 60 * 1000;
  res.json(plansCache);
});
```

**Impact:** Fewer CPU cycles per request. Plans rarely change.

---

## 3. Frontend (WP Plugin)

### 3.1 Unify usage cache keys (low risk)

Multiple cache keys exist: `bbai_usage_cache`, `bbai_quota_cache`, `opptibbai_usage_cache`, `beepbeep_ai_usage_cache`. Consolidate to `bbai_usage_cache` and `bbai_quota_cache` only. Clear legacy keys on plugin load (one-time migration).

**Impact:** Simpler cache logic, less confusion. No user impact if migration is careful.

### 3.2 Reduce duplicate API client instantiation (safe)

`new API_Client_V2()` is created in several places. Use the singleton consistently:

```php
$api_client = \BeepBeepAI\AltTextGenerator\API_Client_V2::get_instance();
```

**Impact:** Slightly less memory and init work. No behavior change.

### 3.3 Lazy-load dashboard bridge (safe)

Load React/dashboard bridge only when the dashboard tab is active, not on every page load. Use `wp_enqueue_script` with a condition or dynamic `import()`.

**Impact:** Faster initial load for users who don’t open the dashboard tab immediately.

### 3.4 Debounce search/filter inputs (safe) ✓

Library search (`#bbai-library-search`) and debug logs search use 300ms debounce. `bbai-performance.js` also debounces `.bbai-library-search-input`.

**Impact:** Fewer DOM operations while typing. No functional change.

### 3.5 Asset bundling (safe)

Verify `bbai-admin.bundle.js` and `bbai-dashboard.bundle.js` are minified and that unused chunks are tree-shaken. Run `npm run build` and check bundle sizes.

**Impact:** Smaller JS payloads, faster page loads.

---

## 4. Cleanup (no user impact)

### 4.1 Legacy option migration (one-time)

Options like `beepbeepai_jwt_token`, `opptibbai_jwt_token`, `beepbeepai_settings` are migrated on read. Add a one-time admin notice or background task to delete migrated legacy options after confirming migration worked. **Only after validating** that all code paths use new keys.

### 4.2 Remove dead code (low risk)

- `purgeLegacyDashboardProgressBars()` – if no legacy progress bars exist in the DOM, simplify or remove.
- Deprecated REST methods – document and schedule removal for a future major version.

### 4.3 Consolidate duplicate modal logic (medium risk)

Upgrade modal show logic is duplicated (e.g. `tryShowUpgradeModal` in multiple files). Extract to a single module and reuse. **Test thoroughly** – modals are user-facing.

---

## 5. Priority summary

| Priority | Item | Effort | Risk | Status |
|----------|------|--------|------|--------|
| High | Database cleanup jobs | Low | None | **Done** – `POST /admin/cleanup`, see oppti-backend/docs/CRON-CLEANUP.md |
| High | Backend response compression | Low | None | **Done** |
| Medium | Billing plans caching | Low | None | **Done** – 5 min TTL |
| Medium | Rate limit → Redis | Medium | Low | **Done** – Redis with in-memory fallback |
| Medium | Unify usage cache keys | Medium | Low | **Done** – one-time legacy cleanup on init |
| Low | Lazy-load dashboard bridge | Medium | Low | **Done** – bbai-dashboard only on dashboard tab |
| Low | Singleton API client | Low | None | **Done** |
| Low | Legacy option cleanup | Low | Low (after validation) | **Done** – settings only (bbai_gpt_settings, etc.) |

---

## 6. What NOT to do (would affect users)

- **Don’t** change cache TTLs drastically without testing (could cause stale or missing data).
- **Don’t** remove legacy option reads until migration is verified site-wide.
- **Don’t** partition `usage_logs` without a proper migration and backfill.
- **Don’t** change API response shapes without a versioned endpoint or frontend update.
- **Don’t** drop indexes without confirming they’re unused.
