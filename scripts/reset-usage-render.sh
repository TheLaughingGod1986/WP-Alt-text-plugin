#!/bin/bash
# Reset Usage via Render CLI
# Usage: ./scripts/reset-usage-render.sh [email] [database-service-name]

EMAIL="${1:-benoats@gmail.com}"
DB_SERVICE="${2:-alttext-ai-db}"

echo "🔄 Resetting Usage via Render CLI"
echo "Email: $EMAIL"
echo "Database Service: $DB_SERVICE"
echo "============================================================"
echo ""

# Step 1: Find user ID
echo "Step 1: Finding user ID..."
USER_QUERY="SELECT id, email, plan FROM users WHERE email = '$EMAIL' LIMIT 1;"

echo "Running query: $USER_QUERY"
echo ""

# Connect via Render CLI and get user ID
USER_RESULT=$(render psql "$DB_SERVICE" --command "$USER_QUERY" 2>&1)

if [ $? -ne 0 ]; then
    echo "❌ Error connecting to database. Trying alternative method..."
    echo ""
    echo "Please run this manually:"
    echo "1. render psql $DB_SERVICE"
    echo "2. Then run the SQL commands from the guide"
    exit 1
fi

echo "$USER_RESULT"
echo ""

# Extract user ID (this is a simple approach - you may need to adjust)
USER_ID=$(echo "$USER_RESULT" | grep -oE '[0-9]+' | head -1)

if [ -z "$USER_ID" ]; then
    echo "❌ Could not find user ID. Please run manually:"
    echo ""
    echo "render psql $DB_SERVICE"
    echo ""
    echo "Then run:"
    echo "SELECT id, email FROM users WHERE email = '$EMAIL';"
    echo ""
    echo "Then delete usage with:"
    echo "DELETE FROM usage WHERE user_id = [USER_ID] AND created_at >= DATE_TRUNC('month', CURRENT_DATE);"
    exit 1
fi

echo "✓ Found user ID: $USER_ID"
echo ""

# Step 2: Delete usage records
echo "Step 2: Resetting usage count..."
DELETE_QUERY="DELETE FROM usage WHERE user_id = $USER_ID AND created_at >= DATE_TRUNC('month', CURRENT_DATE);"

echo "Running: $DELETE_QUERY"
DELETE_RESULT=$(render psql "$DB_SERVICE" --command "$DELETE_QUERY" 2>&1)

if [ $? -eq 0 ]; then
    echo "✓ Usage records deleted"
    echo "$DELETE_RESULT"
else
    echo "❌ Error deleting records:"
    echo "$DELETE_RESULT"
    exit 1
fi

echo ""

# Step 3: Verify
echo "Step 3: Verifying reset..."
VERIFY_QUERY="SELECT COUNT(*) as used_count FROM usage WHERE user_id = $USER_ID AND created_at >= DATE_TRUNC('month', CURRENT_DATE);"

echo "Running: $VERIFY_QUERY"
VERIFY_RESULT=$(render psql "$DB_SERVICE" --command "$VERIFY_QUERY" 2>&1)

echo "$VERIFY_RESULT"
echo ""

echo "============================================================"
echo "✅ Reset Complete!"
echo ""
echo "Next steps:"
echo "1. Clear WordPress cache: php scripts/clear-usage-cache.php"
echo "2. Verify usage: php scripts/get-user-monthly-generations.php $EMAIL"
echo ""

