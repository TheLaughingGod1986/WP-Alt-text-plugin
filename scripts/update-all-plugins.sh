#!/bin/bash
# Batch update framework in all plugins
# Usage: ./scripts/update-all-plugins.sh [version] [plugins_dir]
#   version: Tag or branch name (default: latest)
#   plugins_dir: Directory containing plugin folders (default: ..)

set -e

FRAMEWORK_VERSION=${1:-latest}
PLUGINS_DIR=${2:-..}
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# List of plugin directories (update this with your actual plugin names)
PLUGINS=(
    "WP-Alt-text-plugin"
    # Add other plugin directories here as you create them
    # "meta-seo-generator"
    # "image-optimizer"
)

echo "=========================================="
echo "Batch Framework Update"
echo "=========================================="
echo "Target version: $FRAMEWORK_VERSION"
echo "Plugins directory: $PLUGINS_DIR"
echo ""

SUCCESS_COUNT=0
FAILED_COUNT=0
SKIPPED_COUNT=0

for plugin in "${PLUGINS[@]}"; do
    PLUGIN_PATH="$PLUGINS_DIR/$plugin"
    
    if [ ! -d "$PLUGIN_PATH" ]; then
        echo "⏭️  Skipping $plugin (directory not found)"
        SKIPPED_COUNT=$((SKIPPED_COUNT + 1))
        continue
    fi
    
    echo "----------------------------------------"
    echo "Updating: $plugin"
    echo "----------------------------------------"
    
    cd "$PLUGIN_PATH"
    
    # Check if framework submodule exists
    if [ ! -d "framework" ] || [ ! -f ".gitmodules" ]; then
        echo "⏭️  Skipping $plugin (no framework submodule)"
        SKIPPED_COUNT=$((SKIPPED_COUNT + 1))
        cd - > /dev/null
        continue
    fi
    
    # Run update script if it exists, otherwise do it manually
    if [ -f "scripts/update-framework.sh" ]; then
        if ./scripts/update-framework.sh "$FRAMEWORK_VERSION"; then
            echo "✅ $plugin updated successfully"
            SUCCESS_COUNT=$((SUCCESS_COUNT + 1))
        else
            echo "❌ Failed to update $plugin"
            FAILED_COUNT=$((FAILED_COUNT + 1))
        fi
    else
        # Manual update
        cd framework
        git fetch origin
        if [ "$FRAMEWORK_VERSION" = "latest" ]; then
            git checkout main
            git pull origin main
        else
            git checkout "$FRAMEWORK_VERSION"
        fi
        cd ..
        git add framework
        echo "✅ $plugin updated (manual)"
        SUCCESS_COUNT=$((SUCCESS_COUNT + 1))
    fi
    
    cd - > /dev/null
    echo ""
done

echo "=========================================="
echo "Update Summary"
echo "=========================================="
echo "✅ Successful: $SUCCESS_COUNT"
echo "❌ Failed: $FAILED_COUNT"
echo "⏭️  Skipped: $SKIPPED_COUNT"
echo ""

if [ $FAILED_COUNT -gt 0 ]; then
    exit 1
fi

