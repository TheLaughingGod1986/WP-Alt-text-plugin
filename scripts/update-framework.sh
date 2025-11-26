#!/bin/bash
# Update framework submodule in current plugin
# Usage: ./scripts/update-framework.sh [version]
#   version: Tag or branch name (default: latest/main)

set -e

VERSION=${1:-latest}
PLUGIN_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

cd "$PLUGIN_ROOT"

if [ ! -d "framework" ] || [ ! -f ".gitmodules" ]; then
    echo "❌ Error: Framework submodule not found"
    echo "This script is for plugins using framework as a submodule"
    exit 1
fi

echo "Updating framework submodule..."
echo "Target version: $VERSION"
echo ""

cd framework

# Fetch latest changes
echo "Fetching latest changes..."
git fetch origin

# Checkout version
if [ "$VERSION" = "latest" ]; then
    echo "Checking out latest main branch..."
    git checkout main
    git pull origin main
    CURRENT_VERSION=$(git rev-parse --short HEAD)
else
    echo "Checking out version: $VERSION"
    git checkout "$VERSION"
    CURRENT_VERSION=$(git describe --tags --exact-match 2>/dev/null || git rev-parse --short HEAD)
fi

cd "$PLUGIN_ROOT"

# Show what changed
echo ""
echo "Framework updated to: $CURRENT_VERSION"
echo ""

# Stage the submodule update
git add framework

# Check if there are changes
if git diff --cached --quiet framework; then
    echo "No changes to commit (already at $CURRENT_VERSION)"
else
    echo "Staged framework update"
    echo ""
    echo "To commit this update, run:"
    echo "  git commit -m \"Update framework to $CURRENT_VERSION\""
fi

