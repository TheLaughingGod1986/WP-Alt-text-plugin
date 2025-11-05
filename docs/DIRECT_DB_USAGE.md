# Direct Database Usage Access

⚠️ **SECURITY WARNING**: Direct database access bypasses the API layer and should only be used in secure, private environments.

## Overview

By default, the plugin fetches usage data from the backend API (`https://alttext-ai-backend.onrender.com`). This is the **recommended** approach as it:
- Maintains proper security boundaries
- Handles authentication and rate limiting
- Provides better error handling
- Works across different network configurations

However, if you have **secure, private network access** to the backend database (`alttext-ai-db`), you can optionally configure direct database access for faster, more reliable usage data retrieval.

## Configuration

### Step 1: Add Database Credentials to `wp-config.php`

Add these constants to your `wp-config.php` file (before the "That's all, stop editing!" line):

```php
// Direct Database Access for AltText AI Usage (Optional)
// ⚠️ Only enable if you have secure, private network access to backend database
define('ALTTEXT_AI_DB_ENABLED', true);
define('ALTTEXT_AI_DB_TYPE', 'postgresql'); // or 'mysql'
define('ALTTEXT_AI_DB_HOST', 'your-db-host:5432'); // Include port if needed
define('ALTTEXT_AI_DB_NAME', 'alttext-ai-db');
define('ALTTEXT_AI_DB_USER', 'your-db-username');
define('ALTTEXT_AI_DB_PASSWORD', 'your-secure-password');
define('ALTTEXT_AI_DB_USER_TABLE', 'users'); // Your users table name
define('ALTTEXT_AI_DB_USAGE_TABLE', 'usage'); // Your usage table name
```

### Step 2: Update Table Names (if different)

If your database schema uses different table names, adjust:
- `ALTTEXT_AI_DB_USER_TABLE` - table where user accounts are stored
- `ALTTEXT_AI_DB_USAGE_TABLE` - table where usage/generation counts are stored

### Step 3: Adjust Database Queries (if needed)

The default queries in `includes/class-direct-db-usage.php` assume a specific schema. You may need to adjust them based on your actual database structure:

**Expected Schema:**
- Users table: `id`, `email`, `plan`
- Usage table: `user_id`, `generation_count`, `created_at`

## Security Best Practices

1. **Never commit credentials to git** - Add `wp-config.php` to `.gitignore`
2. **Use environment variables** - Store credentials in environment variables if possible
3. **Restrict network access** - Only allow database connections from trusted IPs
4. **Use read-only credentials** - Create a database user with only SELECT permissions
5. **Enable SSL/TLS** - Use encrypted database connections

## Usage

Once configured, the plugin will automatically use direct database access when:
1. `ALTTEXT_AI_DB_ENABLED` is set to `true`
2. Database credentials are properly configured
3. User is authenticated (email is available)

The dashboard will fetch usage data directly from the database, ensuring the progress bar always shows accurate, real-time usage.

## Fallback Behavior

If direct database access fails or is not configured, the plugin automatically falls back to using the API (default behavior).

## Troubleshooting

### "Database credentials not configured"
- Ensure all `ALTTEXT_AI_DB_*` constants are defined in `wp-config.php`

### "Database connection failed"
- Verify database host and port are correct
- Check network connectivity to database server
- Ensure database server allows connections from WordPress server

### "User not found in database"
- Verify the email address matches exactly (case-sensitive depending on database)
- Check that the user exists in the users table

### "Database query failed"
- Your database schema may differ from the expected schema
- Adjust the SQL queries in `includes/class-direct-db-usage.php` to match your schema

## Recommended Approach

**For most users**: Use the API (default behavior) - it's secure, reliable, and requires no configuration.

**For advanced users with private network access**: Use direct database access for faster, more reliable usage data retrieval.

