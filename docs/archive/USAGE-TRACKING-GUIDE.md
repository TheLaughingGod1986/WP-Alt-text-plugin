# Usage Tracking Guide - BeepBeep AI Alt Text Generator

## Where to Check User Usage

### Location
**WordPress Admin → Media → Credit Usage**

Access URL: `/wp-admin/upload.php?page=bbai-credit-usage`

### What You'll See

#### 1. Summary Cards (Top of Page)
- **Total Credits Allocated**: Your plan limit (e.g., 50 for free, 1,000 for Pro)
- **Total Credits Used**: Total credits consumed across all users
- **Credits Remaining**: How many credits are left this month
- **Active Users**: Number of WordPress users who have generated alt text

#### 2. User Breakdown Table
Shows per-user statistics:
- **User**: Display name and email
- **Credits Used**: Total credits consumed by this user
- **Images Processed**: Number of images this user has generated alt text for
- **Total Cost**: Estimated API cost (if available)
- **Last Activity**: Most recent generation timestamp
- **Actions**: "View Details" button to see individual image breakdown

#### 3. Filters
- **Date Range**: Filter by date_from and date_to
- **User Filter**: Dropdown to filter by specific user
- **Source Filter**: Filter by source type (manual/auto/bulk/inline/queue)

#### 4. User Detail View
Click "View Details" on any user to see:
- Per-image breakdown with:
  - Image filename/title
  - Credits used per image
  - Cost per image
  - AI model used
  - Source type
  - Timestamp

---

## How Site-Wide Usage is Tracked

### Dual Tracking System

The plugin uses **two complementary systems** to track site-wide usage:

---

### Method 1: Backend API Tracking (Primary Source of Truth)

**File**: `includes/class-usage-tracker.php`

**How it works:**
1. Plugin calls `$api_client->get_usage()` to fetch current usage from backend API
2. Backend returns site-wide totals: `used`, `limit`, `remaining`, `plan`, `resetDate`
3. Plugin caches this in WordPress transient: `bbai_usage_cache` (5-minute expiry)
4. All users see the same shared balance (site-wide pool)

**Where it's used:**
- Dashboard tab (`Media → AI Alt Text`)
- Settings tab
- Throughout plugin UI
- Credit limit checks

**Code Flow:**
```php
// Fetch from backend API
$live_usage = $api_client->get_usage();

// Update local cache
Usage_Tracker::update_usage($live_usage);

// Display via cache
$usage_stats = Usage_Tracker::get_stats_display();
```

**Key Points:**
- ✅ **Single source of truth**: Backend API is authoritative
- ✅ **Shared pool**: All users see the same remaining balance
- ✅ **Real-time**: Fetches fresh data from backend (cached 5 minutes)
- ✅ **Per-site tracking**: Uses site fingerprint to prevent abuse

---

### Method 2: Local Database Tracking (Detailed Breakdown)

**File**: `includes/class-credit-usage-logger.php`

**How it works:**
1. Every generation logs to `wp_bbai_credit_usage` table:
   - `user_id` - Which WordPress user generated it
   - `attachment_id` - Which image was processed
   - `credits_used` - Tokens/credits consumed
   - `token_cost` - Actual API cost (if available)
   - `model` - AI model used (e.g., gpt-4o)
   - `source` - Generation source (manual/auto/bulk/inline/queue)
   - `generated_at` - Timestamp

2. Aggregates data for reporting:
   - `Credit_Usage_Logger::get_site_usage()` - Total site-wide usage
   - `Credit_Usage_Logger::get_usage_by_user()` - Per-user breakdown
   - `Credit_Usage_Logger::get_user_details()` - Detailed per-image view

**Where it's used:**
- Credit Usage admin page (`Media → Credit Usage`)
- User breakdown tables
- Detailed usage reports

**Code Flow:**
```php
// Log each generation
Credit_Usage_Logger::log_usage(
    $attachment_id,
    $user_id,
    $credits_used,
    $token_cost,
    $model,
    $source
);

// Aggregate for reports
$site_usage = Credit_Usage_Logger::get_site_usage($filters);
$user_breakdown = Credit_Usage_Logger::get_usage_by_user($filters);
```

**Key Points:**
- ✅ **Per-user tracking**: Shows which user generated each image
- ✅ **Detailed history**: Complete audit trail of all generations
- ✅ **Filterable**: Date ranges, users, sources
- ✅ **Local database**: Fast queries, no API calls needed

---

## How They Work Together

### Credit Consumption Flow

1. **User generates alt text** → `generate_and_save()` called
2. **Free credits allocated** (if first generation on site)
3. **API call made** → Backend deducts credits, returns updated usage
4. **Usage logged locally** → `Credit_Usage_Logger::log_usage()` stores details
5. **Cache updated** → `Usage_Tracker::update_usage()` stores totals

### Display Flow

**For Site-Wide Totals (Dashboard):**
```
API → Usage_Tracker::update_usage() → Transient Cache → Dashboard Display
```

**For Per-User Breakdown (Credit Usage Page):**
```
Local DB (wp_bbai_credit_usage) → Credit_Usage_Logger::get_usage_by_user() → Admin Page
```

---

## Database Tables

### `wp_bbai_credit_usage` (Local Tracking)
- Stores every generation with user attribution
- Used for detailed reporting and per-user breakdown
- Fast queries, no external API dependency

### `wp_options` - Transients (Cached Totals)
- `bbai_usage_cache` - Cached site-wide usage (5 min expiry)
- Updated from backend API
- Used for dashboard display

---

## Key Methods

### Site-Wide Usage (Total Pool)
```php
// Get current usage from cache (fetches from API if needed)
$usage = Usage_Tracker::get_stats_display();

// Returns: used, limit, remaining, percentage, plan, reset_date
```

### Site-Wide Usage (Aggregated from Logs)
```php
// Get total usage from local database
$site_usage = Credit_Usage_Logger::get_site_usage($filters);

// Returns: total_credits, total_images, total_cost, user_count
```

### Per-User Usage
```php
// Get breakdown by user
$user_breakdown = Credit_Usage_Logger::get_usage_by_user($filters);

// Returns: users array with total_credits, total_images, last_activity per user
```

### Single User Details
```php
// Get detailed per-image breakdown for a user
$user_details = Credit_Usage_Logger::get_user_details($user_id, $filters);

// Returns: items array with per-image credits, cost, model, source, timestamp
```

---

## Summary

**To check user usage:**
- Go to **Media → Credit Usage** in WordPress admin
- View user breakdown table or click "View Details" for per-image breakdown

**Site-wide usage tracking:**
- **Backend API** (via `Usage_Tracker`): Primary source, shows shared pool balance
- **Local database** (via `Credit_Usage_Logger`): Detailed per-user breakdown and history

Both systems work together to provide:
- ✅ Accurate site-wide totals (from backend)
- ✅ Detailed per-user breakdown (from local DB)
- ✅ Complete audit trail of all generations
- ✅ Fast reporting without API dependency


## Where to Check User Usage

### Location
**WordPress Admin → Media → Credit Usage**

Access URL: `/wp-admin/upload.php?page=bbai-credit-usage`

### What You'll See

#### 1. Summary Cards (Top of Page)
- **Total Credits Allocated**: Your plan limit (e.g., 50 for free, 1,000 for Pro)
- **Total Credits Used**: Total credits consumed across all users
- **Credits Remaining**: How many credits are left this month
- **Active Users**: Number of WordPress users who have generated alt text

#### 2. User Breakdown Table
Shows per-user statistics:
- **User**: Display name and email
- **Credits Used**: Total credits consumed by this user
- **Images Processed**: Number of images this user has generated alt text for
- **Total Cost**: Estimated API cost (if available)
- **Last Activity**: Most recent generation timestamp
- **Actions**: "View Details" button to see individual image breakdown

#### 3. Filters
- **Date Range**: Filter by date_from and date_to
- **User Filter**: Dropdown to filter by specific user
- **Source Filter**: Filter by source type (manual/auto/bulk/inline/queue)

#### 4. User Detail View
Click "View Details" on any user to see:
- Per-image breakdown with:
  - Image filename/title
  - Credits used per image
  - Cost per image
  - AI model used
  - Source type
  - Timestamp

---

## How Site-Wide Usage is Tracked

### Dual Tracking System

The plugin uses **two complementary systems** to track site-wide usage:

---

### Method 1: Backend API Tracking (Primary Source of Truth)

**File**: `includes/class-usage-tracker.php`

**How it works:**
1. Plugin calls `$api_client->get_usage()` to fetch current usage from backend API
2. Backend returns site-wide totals: `used`, `limit`, `remaining`, `plan`, `resetDate`
3. Plugin caches this in WordPress transient: `bbai_usage_cache` (5-minute expiry)
4. All users see the same shared balance (site-wide pool)

**Where it's used:**
- Dashboard tab (`Media → AI Alt Text`)
- Settings tab
- Throughout plugin UI
- Credit limit checks

**Code Flow:**
```php
// Fetch from backend API
$live_usage = $api_client->get_usage();

// Update local cache
Usage_Tracker::update_usage($live_usage);

// Display via cache
$usage_stats = Usage_Tracker::get_stats_display();
```

**Key Points:**
- ✅ **Single source of truth**: Backend API is authoritative
- ✅ **Shared pool**: All users see the same remaining balance
- ✅ **Real-time**: Fetches fresh data from backend (cached 5 minutes)
- ✅ **Per-site tracking**: Uses site fingerprint to prevent abuse

---

### Method 2: Local Database Tracking (Detailed Breakdown)

**File**: `includes/class-credit-usage-logger.php`

**How it works:**
1. Every generation logs to `wp_bbai_credit_usage` table:
   - `user_id` - Which WordPress user generated it
   - `attachment_id` - Which image was processed
   - `credits_used` - Tokens/credits consumed
   - `token_cost` - Actual API cost (if available)
   - `model` - AI model used (e.g., gpt-4o)
   - `source` - Generation source (manual/auto/bulk/inline/queue)
   - `generated_at` - Timestamp

2. Aggregates data for reporting:
   - `Credit_Usage_Logger::get_site_usage()` - Total site-wide usage
   - `Credit_Usage_Logger::get_usage_by_user()` - Per-user breakdown
   - `Credit_Usage_Logger::get_user_details()` - Detailed per-image view

**Where it's used:**
- Credit Usage admin page (`Media → Credit Usage`)
- User breakdown tables
- Detailed usage reports

**Code Flow:**
```php
// Log each generation
Credit_Usage_Logger::log_usage(
    $attachment_id,
    $user_id,
    $credits_used,
    $token_cost,
    $model,
    $source
);

// Aggregate for reports
$site_usage = Credit_Usage_Logger::get_site_usage($filters);
$user_breakdown = Credit_Usage_Logger::get_usage_by_user($filters);
```

**Key Points:**
- ✅ **Per-user tracking**: Shows which user generated each image
- ✅ **Detailed history**: Complete audit trail of all generations
- ✅ **Filterable**: Date ranges, users, sources
- ✅ **Local database**: Fast queries, no API calls needed

---

## How They Work Together

### Credit Consumption Flow

1. **User generates alt text** → `generate_and_save()` called
2. **Free credits allocated** (if first generation on site)
3. **API call made** → Backend deducts credits, returns updated usage
4. **Usage logged locally** → `Credit_Usage_Logger::log_usage()` stores details
5. **Cache updated** → `Usage_Tracker::update_usage()` stores totals

### Display Flow

**For Site-Wide Totals (Dashboard):**
```
API → Usage_Tracker::update_usage() → Transient Cache → Dashboard Display
```

**For Per-User Breakdown (Credit Usage Page):**
```
Local DB (wp_bbai_credit_usage) → Credit_Usage_Logger::get_usage_by_user() → Admin Page
```

---

## Database Tables

### `wp_bbai_credit_usage` (Local Tracking)
- Stores every generation with user attribution
- Used for detailed reporting and per-user breakdown
- Fast queries, no external API dependency

### `wp_options` - Transients (Cached Totals)
- `bbai_usage_cache` - Cached site-wide usage (5 min expiry)
- Updated from backend API
- Used for dashboard display

---

## Key Methods

### Site-Wide Usage (Total Pool)
```php
// Get current usage from cache (fetches from API if needed)
$usage = Usage_Tracker::get_stats_display();

// Returns: used, limit, remaining, percentage, plan, reset_date
```

### Site-Wide Usage (Aggregated from Logs)
```php
// Get total usage from local database
$site_usage = Credit_Usage_Logger::get_site_usage($filters);

// Returns: total_credits, total_images, total_cost, user_count
```

### Per-User Usage
```php
// Get breakdown by user
$user_breakdown = Credit_Usage_Logger::get_usage_by_user($filters);

// Returns: users array with total_credits, total_images, last_activity per user
```

### Single User Details
```php
// Get detailed per-image breakdown for a user
$user_details = Credit_Usage_Logger::get_user_details($user_id, $filters);

// Returns: items array with per-image credits, cost, model, source, timestamp
```

---

## Summary

**To check user usage:**
- Go to **Media → Credit Usage** in WordPress admin
- View user breakdown table or click "View Details" for per-image breakdown

**Site-wide usage tracking:**
- **Backend API** (via `Usage_Tracker`): Primary source, shows shared pool balance
- **Local database** (via `Credit_Usage_Logger`): Detailed per-user breakdown and history

Both systems work together to provide:
- ✅ Accurate site-wide totals (from backend)
- ✅ Detailed per-user breakdown (from local DB)
- ✅ Complete audit trail of all generations
- ✅ Fast reporting without API dependency

