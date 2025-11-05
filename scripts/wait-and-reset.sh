#!/bin/bash
# Wait for database to be ready and then reset usage

echo "Waiting for database to be ready..."
echo "This may take 1-2 minutes for free tier databases"
echo ""

# Check status
for i in {1..30}; do
    STATUS=$(render services list --output json 2>&1 | python3 -c "import sys, json; data = json.load(sys.stdin); db = [s for s in data if s.get('postgres', {}).get('name') == 'alttext-ai-db'][0]; print(db['postgres']['status'])" 2>/dev/null)
    
    if [ "$STATUS" = "available" ]; then
        echo "✓ Database is available!"
        echo ""
        echo "Now you can run the SQL commands via Render Dashboard:"
        echo "1. Go to: https://dashboard.render.com"
        echo "2. Click on 'alttext-ai-db'"
        echo "3. Click 'Connect' button"
        echo "4. Run the SQL commands from scripts/reset-usage.sql"
        echo ""
        exit 0
    else
        echo "Waiting... (Status: ${STATUS:-checking}) - Attempt $i/30"
        sleep 5
    fi
done

echo "⚠️  Database didn't become available after 2.5 minutes"
echo "Please check the Render dashboard manually"

