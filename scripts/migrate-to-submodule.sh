#!/bin/bash
# Script to complete framework submodule migration
# Run this AFTER creating the GitHub repository: TheLaughingGod1986/plugin-framework

set -e

echo "=========================================="
echo "Framework Submodule Migration Script"
echo "=========================================="
echo ""

# Check if GitHub repo exists
REPO_URL="https://github.com/TheLaughingGod1986/plugin-framework.git"
echo "Checking if repository exists: $REPO_URL"

if git ls-remote --exit-code --heads "$REPO_URL" main > /dev/null 2>&1; then
    echo "✅ Repository exists!"
    echo ""
else
    echo "❌ Repository not found!"
    echo ""
    echo "Please create the GitHub repository first:"
    echo "1. Go to https://github.com/new"
    echo "2. Repository name: plugin-framework"
    echo "3. Owner: TheLaughingGod1986"
    echo "4. Set as Public or Private"
    echo "5. DO NOT initialize with README (we'll push our own)"
    echo "6. Click 'Create repository'"
    echo ""
    echo "Then push the framework:"
    echo "  cd /tmp/framework-extraction"
    echo "  ./push-to-github.sh"
    echo ""
    echo "After that, run this script again."
    exit 1
fi

# Remove existing framework directory if it exists
if [ -d "framework" ] && [ ! -f "framework/.git" ]; then
    echo "Removing existing framework directory..."
    rm -rf framework
fi

# Remove framework from .gitmodules if it exists
if [ -f ".gitmodules" ] && grep -q "framework" .gitmodules; then
    echo "Removing existing submodule entry..."
    git submodule deinit -f framework 2>/dev/null || true
    git rm -f framework 2>/dev/null || true
    rm -rf .git/modules/framework 2>/dev/null || true
fi

# Add framework as submodule
echo "Adding framework as Git submodule..."
git submodule add "$REPO_URL" framework

# Checkout specific version
echo "Checking out v1.0.0..."
cd framework
git checkout v1.0.0
cd ..

# Commit submodule
echo "Committing submodule reference..."
git add .gitmodules framework
git commit -m "Add framework as Git submodule v1.0.0" || echo "No changes to commit"

echo ""
echo "✅ Framework submodule migration complete!"
echo ""
echo "Next steps:"
echo "1. Test the plugin to ensure everything works"
echo "2. Push changes: git push"
echo "3. For future updates, use: ./scripts/update-framework.sh [version]"

