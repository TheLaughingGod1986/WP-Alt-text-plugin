#!/bin/bash
# Simple script to reset usage via Render CLI
# You'll need to provide the database service name

EMAIL="benoats@gmail.com"

echo "🔄 Resetting Usage for: $EMAIL"
echo "============================================================"
echo ""
echo "Step 1: Find user ID"
echo "Run this command:"
echo ""
echo "render psql <database-service-name> --command \"SELECT id, email, plan FROM users WHERE email = '$EMAIL';\""
echo ""
echo "Step 2: After you get the user ID, run:"
echo ""
echo "render psql <database-service-name> --command \"DELETE FROM usage WHERE user_id = [USER_ID] AND created_at >= DATE_TRUNC('month', CURRENT_DATE);\""
echo ""
echo "Step 3: Verify:"
echo ""
echo "render psql <database-service-name> --command \"SELECT COUNT(*) FROM usage WHERE user_id = [USER_ID] AND created_at >= DATE_TRUNC('month', CURRENT_DATE);\""
echo ""
echo "============================================================"
echo ""
echo "To find your database service name, run:"
echo "render services list"
echo ""

