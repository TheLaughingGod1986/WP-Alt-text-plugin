#!/bin/bash
# Check database usage via Render CLI

EMAIL="benoats@gmail.com"

echo "🔍 Checking database for user: $EMAIL"
echo "============================================================"
echo ""

# Create a temporary SQL file
SQL_FILE=$(mktemp)
cat > "$SQL_FILE" <<EOF
SELECT id, email, plan FROM users WHERE email = '$EMAIL';

\q
EOF

echo "Querying user information..."
echo ""

# Try to use psql directly with Render connection string
# First, let's try a different approach - using render db connection string
echo "Note: Render CLI psql is interactive. Please run these commands manually:"
echo ""
echo "1. Connect to database:"
echo "   render psql alttext-ai-db"
echo ""
echo "2. Then run this SQL:"
echo "   SELECT id, email, plan FROM users WHERE email = 'benoats@gmail.com';"
echo ""
echo "3. Check current month usage (replace [USER_ID] with the ID from step 2):"
echo "   SELECT COUNT(*) as usage_count FROM usage WHERE user_id = [USER_ID] AND created_at >= DATE_TRUNC('month', CURRENT_DATE);"
echo ""
echo "4. To reset usage (replace [USER_ID]):"
echo "   DELETE FROM usage WHERE user_id = [USER_ID] AND created_at >= DATE_TRUNC('month', CURRENT_DATE);"
echo ""

rm "$SQL_FILE"


