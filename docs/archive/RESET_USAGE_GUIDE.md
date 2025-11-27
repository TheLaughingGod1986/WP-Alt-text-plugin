# Reset User Usage Count - Guide

## Quick Reset Methods

### Method 1: Direct Database Access (Recommended if you have DB access)

Connect to your `alttext-ai-db` database and run:

#### For PostgreSQL:
```sql
-- Find user ID
SELECT id, email, plan FROM users WHERE email = 'benoats@gmail.com';

-- Reset usage for current month (replace USER_ID with actual ID)
DELETE FROM usage 
WHERE user_id = USER_ID 
AND created_at >= DATE_TRUNC('month', CURRENT_DATE);

-- Verify reset
SELECT COUNT(*) as used_count 
FROM usage 
WHERE user_id = USER_ID 
AND created_at >= DATE_TRUNC('month', CURRENT_DATE);
```

#### For MySQL/MariaDB:
```sql
-- Find user ID
SELECT id, email, plan FROM users WHERE email = 'benoats@gmail.com';

-- Reset usage for current month (replace USER_ID with actual ID)
DELETE FROM usage 
WHERE user_id = USER_ID 
AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01');

-- Verify reset
SELECT COUNT(*) as used_count 
FROM usage 
WHERE user_id = USER_ID 
AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01');
```

### Method 2: Configure Direct Database Access in WordPress

Add to `wp-config.php`:

```php
// Direct Database Access for AltText AI
define('ALTTEXT_AI_DB_ENABLED', true);
define('ALTTEXT_AI_DB_TYPE', 'postgresql'); // or 'mysql'
define('ALTTEXT_AI_DB_HOST', 'your-db-host:5432');
define('ALTTEXT_AI_DB_NAME', 'alttext-ai-db');
define('ALTTEXT_AI_DB_USER', 'your-db-username');
define('ALTTEXT_AI_DB_PASSWORD', 'your-secure-password');
define('ALTTEXT_AI_DB_USER_TABLE', 'users');
define('ALTTEXT_AI_DB_USAGE_TABLE', 'usage');
```

Then run:
```bash
php scripts/reset-usage.php benoats@gmail.com
```

### Method 3: Backend API Endpoint (If Available)

If your backend API has a reset endpoint, you can call it:

```bash
curl -X POST https://alttext-ai-backend.onrender.com/api/reset-usage \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"email": "benoats@gmail.com"}'
```

### Method 4: Manual WordPress Cache Clear

After resetting in the database, clear WordPress cache:

```bash
php scripts/clear-usage-cache.php
```

## Verification

After resetting, verify the usage count:

```bash
php scripts/get-user-monthly-generations.php benoats@gmail.com
```

You should see:
- Used: 0
- Remaining: 50
- Generation should now work!

## Troubleshooting

### If usage still shows 50 after reset:
1. Check if the database DELETE actually worked
2. Clear WordPress cache: `php scripts/clear-usage-cache.php`
3. Wait a few seconds and refresh from API
4. Check if there are multiple records or a different schema

### If you can't access the database:
1. Contact backend support to reset usage
2. Wait for monthly reset (1st of next month)
3. Check backend logs for usage tracking issues

## Important Notes

- ⚠️ **Backup first**: Always backup your database before running DELETE commands
- 🔒 **Security**: Only reset usage if you have proper authorization
- 📊 **Tracking**: This resets the count but doesn't affect historical data
- 🔄 **Cache**: WordPress cache may take a few minutes to update after database reset

