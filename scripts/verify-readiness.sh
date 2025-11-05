#!/bin/bash
#
# Verify Production Readiness
# Runs checks to ensure everything is ready for deployment
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_ROOT"

echo "🔍 Verifying Production Readiness..."
echo ""

ERRORS=0
WARNINGS=0

# Check 1: Distribution package exists
echo "📦 Checking distribution package..."
if [ -f "ai-alt-text-generator-4.2.1.zip" ]; then
    SIZE=$(ls -lh ai-alt-text-generator-4.2.1.zip | awk '{print $5}')
    echo "   ✅ Package exists ($SIZE)"
else
    echo "   ❌ Package not found!"
    ERRORS=$((ERRORS + 1))
fi

# Check 2: Critical documentation files
echo ""
echo "📚 Checking documentation..."
DOCS=(
    "CONTRIBUTING.md"
    "CODE_OF_CONDUCT.md"
    "LAUNCH_READY.md"
    "PROJECT_STATUS.md"
    "QUICK_DEPLOY.md"
    "README.md"
)

for doc in "${DOCS[@]}"; do
    if [ -f "$doc" ]; then
        echo "   ✅ $doc"
    else
        echo "   ❌ $doc missing!"
        ERRORS=$((ERRORS + 1))
    fi
done

# Check 3: GitHub templates
echo ""
echo "📋 Checking GitHub templates..."
if [ -f ".github/PULL_REQUEST_TEMPLATE.md" ]; then
    echo "   ✅ PR template exists"
else
    echo "   ⚠️  PR template missing"
    WARNINGS=$((WARNINGS + 1))
fi

if [ -f ".github/ISSUE_TEMPLATE/bug_report.md" ]; then
    echo "   ✅ Bug report template exists"
else
    echo "   ⚠️  Bug report template missing"
    WARNINGS=$((WARNINGS + 1))
fi

# Check 4: PHP syntax
echo ""
echo "🔧 Checking PHP syntax..."
PHP_FILES=$(find includes admin public -name "*.php" 2>/dev/null | head -5)
for file in $PHP_FILES; do
    if php -l "$file" > /dev/null 2>&1; then
        echo "   ✅ $file"
    else
        echo "   ❌ Syntax error in $file"
        ERRORS=$((ERRORS + 1))
    fi
done

# Check 5: Main plugin file
echo ""
echo "📝 Checking main plugin file..."
if [ -f "ai-alt-gpt.php" ]; then
    VERSION=$(grep "Version:" ai-alt-gpt.php | head -1 | sed -E "s/.*Version: ([0-9.]+).*/\1/" | tr -d '[:space:]')
    if [ "$VERSION" = "4.2.1" ]; then
        echo "   ✅ Main plugin file exists (Version: $VERSION)"
    else
        echo "   ⚠️  Version mismatch: $VERSION (expected: 4.2.1)"
        WARNINGS=$((WARNINGS + 1))
    fi
else
    echo "   ❌ Main plugin file missing!"
    ERRORS=$((ERRORS + 1))
fi

# Check 6: Minified assets
echo ""
echo "📦 Checking minified assets..."
MIN_JS=$(find assets -name "*.min.js" 2>/dev/null | wc -l | tr -d ' ')
MIN_CSS=$(find assets -name "*.min.css" 2>/dev/null | wc -l | tr -d ' ')
echo "   ✅ Minified JS files: $MIN_JS"
echo "   ✅ Minified CSS files: $MIN_CSS"

# Summary
echo ""
echo "════════════════════════════════════════════════"
if [ $ERRORS -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo "✅ All checks passed! Ready for production."
    exit 0
elif [ $ERRORS -eq 0 ]; then
    echo "⚠️  Checks passed with $WARNINGS warning(s)"
    exit 0
else
    echo "❌ Found $ERRORS error(s) and $WARNINGS warning(s)"
    echo "   Please fix errors before deploying."
    exit 1
fi





