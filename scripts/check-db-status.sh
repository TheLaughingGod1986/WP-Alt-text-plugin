#!/bin/bash
# Check Render Database Status

echo "Checking Render database status..."
echo "============================================================"

# Check if database service exists
echo "1. Checking database service..."
DB_INFO=$(timeout 10 render services list --output json 2>&1 | grep -i "alttext-ai-db" | head -5)

if [ -z "$DB_INFO" ]; then
    echo "   ⚠️  Could not find database service"
    echo "   Try: render services list"
else
    echo "   ✓ Database service found"
    echo "$DB_INFO"
fi

echo ""
echo "2. Checking database connection..."
echo "   Note: render psql opens an interactive session"
echo "   If it hangs, the database might be:"
echo "   - Suspended (free tier auto-suspends)"
echo "   - Having connectivity issues"
echo "   - Waiting for input in interactive mode"
echo ""

echo "3. Alternative Solutions:"
echo "   A. Use Render Dashboard: https://dashboard.render.com"
echo "   B. Use direct PostgreSQL connection string"
echo "   C. Create a backend API endpoint to reset usage"
echo "   D. Check if database is suspended (free tier)"
echo ""

