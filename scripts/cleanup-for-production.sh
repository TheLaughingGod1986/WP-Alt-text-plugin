#!/bin/bash
#
# Production Cleanup Script
# Removes development files, backup files, and test files
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_ROOT"

echo "🧹 Starting Production Cleanup..."
echo ""

# Files to remove (backup and test files)
FILES_TO_REMOVE=(
    "opptiai-alt-js-backup.php"
    "opptiai-alt-simple-backup.php"
    "test-frontend-password-reset.php"
    "mock-backend.js"
    "simple-stripe-integration.php"
    "fix-api-url.php"
    "update-checkout.php"
    "DEMO.html"
    "landing-page.html"
)

REMOVED_COUNT=0
for file in "${FILES_TO_REMOVE[@]}"; do
    if [ -f "$file" ]; then
        echo "  ❌ Removing: $file"
        rm "$file"
        REMOVED_COUNT=$((REMOVED_COUNT + 1))
    else
        echo "  ⏭️  Skipping (not found): $file"
    fi
done

echo ""
echo "✅ Removed $REMOVED_COUNT file(s)"
echo ""
echo "📋 Production cleanup complete!"
echo ""
echo "⚠️  Note: Manual review still needed for:"
echo "   - Console.log statements (wrapped in debug checks)"
echo "   - Error logging patterns"
echo "   - Asset minification"
echo ""






