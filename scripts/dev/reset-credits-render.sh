#!/bin/bash
# Reset Credits on Render Database
# This will reset your credits to 0 via Render database

EMAIL="benoats@gmail.com"
DB_SERVICE="alttext-ai-db"

echo "🔄 Resetting Credits on Render Database"
echo "Email: $EMAIL"
echo "Database: $DB_SERVICE"
echo "============================================================"
echo ""

echo "Step 1: Finding user ID..."
USER_QUERY="SELECT id, email, plan FROM users WHERE email = '$EMAIL' LIMIT 1;"

echo "Running: $USER_QUERY"
echo ""

# Use Render CLI to execute query
if command -v render >/dev/null 2>&1; then
    echo "Using Render CLI..."
    USER_RESULT=$(render psql "$DB_SERVICE" --command "$USER_QUERY" 2>&1)
    
    if [ $? -eq 0 ]; then
        echo "$USER_RESULT"
        echo ""
        
        # Extract user ID
        USER_ID=$(echo "$USER_RESULT" | grep -oP '\d+' | head -1)
        
        if [ -n "$USER_ID" ]; then
            echo "✓ Found user ID: $USER_ID"
            echo ""
            
            echo "Step 2: Resetting usage to 0..."
            DELETE_QUERY="DELETE FROM usage WHERE user_id = $USER_ID AND created_at >= DATE_TRUNC('month', CURRENT_DATE);"
            
            echo "Running: $DELETE_QUERY"
            DELETE_RESULT=$(render psql "$DB_SERVICE" --command "$DELETE_QUERY" 2>&1)
            
            if [ $? -eq 0 ]; then
                echo "✓ Usage records deleted"
                echo ""
                
                echo "Step 3: Verifying reset..."
                VERIFY_QUERY="SELECT COUNT(*) as remaining_usage FROM usage WHERE user_id = $USER_ID AND created_at >= DATE_TRUNC('month', CURRENT_DATE);"
                VERIFY_RESULT=$(render psql "$DB_SERVICE" --command "$VERIFY_QUERY" 2>&1)
                
                echo "$VERIFY_RESULT"
                echo ""
                echo "✅ Credits Reset Complete!"
                echo ""
                echo "Your credits have been reset to 0. Refresh your WordPress admin page to see the changes."
            else
                echo "❌ Error deleting records:"
                echo "$DELETE_RESULT"
            fi
        else
            echo "❌ Could not extract user ID from result"
            echo "Please run the SQL manually in Render Dashboard"
        fi
    else
        echo "❌ Error connecting to Render database"
        echo "$USER_RESULT"
        echo ""
        echo "Please use the Render Dashboard method below"
    fi
else
    echo "⚠ Render CLI not found. Please use Render Dashboard method."
fi

echo ""
echo "============================================================"
echo "ALTERNATIVE: Use Render Dashboard"
echo "============================================================"
echo ""
echo "1. Go to: https://dashboard.render.com"
echo "2. Navigate to: $DB_SERVICE service"
echo "3. Click: 'Connect' button"
echo "4. Paste and run this SQL:"
echo ""
echo "   -- Reset usage for current month"
echo "   DELETE FROM usage"
echo "   WHERE user_id = (SELECT id FROM users WHERE email = '$EMAIL')"
echo "   AND created_at >= DATE_TRUNC('month', CURRENT_DATE);"
echo ""
echo "5. Verify with:"
echo "   SELECT COUNT(*) as remaining_usage"
echo "   FROM usage"
echo "   WHERE user_id = (SELECT id FROM users WHERE email = '$EMAIL')"
echo "   AND created_at >= DATE_TRUNC('month', CURRENT_DATE);"
echo ""

