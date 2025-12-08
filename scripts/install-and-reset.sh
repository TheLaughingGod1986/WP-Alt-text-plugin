#!/bin/bash
# Install psql and reset usage - Step by step guide

echo "============================================================"
echo "Step 1: Install PostgreSQL Client Tools"
echo "============================================================"
echo ""
echo "Run this command in Terminal:"
echo "  brew install libpq"
echo "  brew link --force libpq"
echo ""
echo "Or install full PostgreSQL:"
echo "  brew install postgresql@17"
echo ""
read -p "Press Enter after you've installed psql..."

echo ""
echo "============================================================"
echo "Step 2: Verify psql is installed"
echo "============================================================"
psql --version || {
    echo "❌ psql not found. Please install it first."
    exit 1
}

echo "✓ psql is installed"
echo ""

echo "============================================================"
echo "Step 3: Find User ID"
echo "============================================================"
echo "Running: SELECT id, email, plan FROM users WHERE email = 'benoats@gmail.com';"
echo ""

USER_RESULT=$(render psql alttext-ai-db -- -c "SELECT id, email, plan FROM users WHERE email = 'benoats@gmail.com';" 2>&1)

if [ $? -ne 0 ]; then
    echo "❌ Error connecting to database:"
    echo "$USER_RESULT"
    exit 1
fi

echo "$USER_RESULT"
echo ""

# Extract user ID (simple extraction - may need adjustment)
USER_ID=$(echo "$USER_RESULT" | grep -oE '[0-9]+' | head -1)

if [ -z "$USER_ID" ]; then
    echo "⚠️  Could not extract user ID automatically."
    echo "Please copy the user ID from above and run:"
    echo ""
    echo "render psql alttext-ai-db -- -c \"DELETE FROM usage WHERE user_id = [USER_ID] AND created_at >= DATE_TRUNC('month', CURRENT_DATE);\""
    echo ""
    exit 1
fi

echo "Found User ID: $USER_ID"
echo ""

echo "============================================================"
echo "Step 4: Reset Usage Count"
echo "============================================================"
echo "Deleting usage records for current month..."
echo ""

DELETE_RESULT=$(render psql alttext-ai-db -- -c "DELETE FROM usage WHERE user_id = $USER_ID AND created_at >= DATE_TRUNC('month', CURRENT_DATE);" 2>&1)

if [ $? -eq 0 ]; then
    echo "✓ Usage records deleted"
    echo "$DELETE_RESULT"
else
    echo "❌ Error deleting records:"
    echo "$DELETE_RESULT"
    exit 1
fi

echo ""

echo "============================================================"
echo "Step 5: Verify Reset"
echo "============================================================"
VERIFY_RESULT=$(render psql alttext-ai-db -- -c "SELECT COUNT(*) as remaining FROM usage WHERE user_id = $USER_ID AND created_at >= DATE_TRUNC('month', CURRENT_DATE);" 2>&1)

echo "$VERIFY_RESULT"
echo ""

echo "============================================================"
echo "Step 6: Clear WordPress Cache"
echo "============================================================"
docker exec wp-alt-text-plugin-wordpress-1 php /var/www/html/wp-content/plugins/beepbeep-ai-alt-text-generator/scripts/clear-usage-cache.php

echo ""
echo "============================================================"
echo "✅ Reset Complete!"
echo "============================================================"
echo "Usage has been reset to 0. You can now generate alt text again."
echo ""


