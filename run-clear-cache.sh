#!/bin/bash
# Clear Usage Cache Script Runner
# This script will attempt to find WordPress and run the cache clearing script

echo "🔄 Clearing Usage Cache..."
echo ""

# Try to find WordPress root
WP_ROOT=""
POSSIBLE_ROOTS=(
    "$(dirname "$(dirname "$(dirname "$(dirname "$(readlink -f "$0")")")")")"
    "/var/www/html"
    "$(pwd)"
    "$(dirname "$(pwd)")"
)

for root in "${POSSIBLE_ROOTS[@]}"; do
    if [ -f "$root/wp-load.php" ]; then
        WP_ROOT="$root"
        echo "✓ Found WordPress at: $WP_ROOT"
        break
    fi
done

if [ -z "$WP_ROOT" ]; then
    echo "❌ Could not find WordPress root directory."
    echo ""
    echo "Please run the PHP script directly from your WordPress root:"
    echo "  php wp-content/plugins/beepbeep-ai-alt-text-generator/clear-usage-cache.php"
    echo ""
    exit 1
fi

# Run the cache clearing script
SCRIPT_PATH="$WP_ROOT/wp-content/plugins/beepbeep-ai-alt-text-generator/clear-usage-cache.php"

if [ ! -f "$SCRIPT_PATH" ]; then
    echo "❌ Could not find cache clearing script at: $SCRIPT_PATH"
    exit 1
fi

cd "$WP_ROOT"
php "$SCRIPT_PATH"

