# Quick Reset Usage Guide

## Step 1: Install psql

Run in Terminal:
```bash
brew install libpq
brew link --force libpq
```

Verify installation:
```bash
psql --version
```

## Step 2: Find User ID

```bash
render psql alttext-ai-db -- -c "SELECT id, email, plan FROM users WHERE email = 'benoats@gmail.com';"
```

**Note the `id` value** from the output (e.g., `123`)

## Step 3: Reset Usage (Replace `[USER_ID]` with actual ID)

```bash
render psql alttext-ai-db -- -c "DELETE FROM usage WHERE user_id = [USER_ID] AND created_at >= DATE_TRUNC('month', CURRENT_DATE);"
```

## Step 4: Verify

```bash
render psql alttext-ai-db -- -c "SELECT COUNT(*) FROM usage WHERE user_id = [USER_ID] AND created_at >= DATE_TRUNC('month', CURRENT_DATE);"
```

Should return `0`.

## Step 5: Clear WordPress Cache

```bash
docker exec wp-alt-text-plugin-wordpress-1 php /var/www/html/wp-content/plugins/ai-alt-gpt/scripts/clear-usage-cache.php
```

## Alternative: Use the Automated Script

After installing psql, you can run:

```bash
./scripts/install-and-reset.sh
```

This will guide you through all steps automatically.


