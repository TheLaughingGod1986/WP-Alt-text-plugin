#!/bin/bash
# Check framework version in all plugins
# Usage: ./scripts/check-framework-versions.sh [plugins_dir]
#   plugins_dir: Directory containing plugin folders (default: ..)

PLUGINS_DIR=${1:-..}
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# List of plugin directories (update this with your actual plugin names)
PLUGINS=(
    "WP-Alt-text-plugin"
    # Add other plugin directories here as you create them
    # "meta-seo-generator"
    # "image-optimizer"
)

echo "=========================================="
echo "Framework Version Check"
echo "=========================================="
echo ""

for plugin in "${PLUGINS[@]}"; do
    PLUGIN_PATH="$PLUGINS_DIR/$plugin"
    
    if [ ! -d "$PLUGIN_PATH" ]; then
        echo "❌ $plugin: Directory not found"
        continue
    fi
    
    if [ ! -d "$PLUGIN_PATH/framework" ]; then
        echo "⏭️  $plugin: No framework directory"
        continue
    fi
    
    cd "$PLUGIN_PATH/framework"
    
    # Get version information
    if git rev-parse --git-dir > /dev/null 2>&1; then
        # Try to get tag
        TAG=$(git describe --tags --exact-match 2>/dev/null || echo "")
        COMMIT=$(git rev-parse --short HEAD)
        BRANCH=$(git rev-parse --abbrev-ref HEAD)
        
        if [ -n "$TAG" ]; then
            echo "✅ $plugin: $TAG ($COMMIT)"
        else
            echo "📌 $plugin: $BRANCH ($COMMIT)"
        fi
    else
        echo "⚠️  $plugin: Not a Git repository"
    fi
    
    cd - > /dev/null
done

echo ""
echo "=========================================="

