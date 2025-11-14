#!/bin/bash
#
# Create Production Distribution Package
# Excludes development files and creates a clean ZIP for distribution
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_ROOT"

VERSION=$(grep "Version:" opptiai-alt.php | head -1 | sed -E "s/.*Version: ([0-9.]+).*/\1/" | tr -d '[:space:]')
PLUGIN_NAME="opptiai-alt-text-generator"
ZIP_NAME="${PLUGIN_NAME}-${VERSION}.zip"

echo "📦 Creating distribution package: ${ZIP_NAME}"
echo "   Version: ${VERSION}"
echo ""

# Clean up any existing distribution
if [ -f "$ZIP_NAME" ]; then
    echo "   ⚠️  Removing existing ${ZIP_NAME}..."
    rm "$ZIP_NAME"
fi

# Create temporary directory for clean build
TEMP_DIR=$(mktemp -d)
trap "rm -rf $TEMP_DIR" EXIT

echo "   📁 Preparing files..."

# Copy core files
mkdir -p "$TEMP_DIR/$PLUGIN_NAME"
cp opptiai-alt.php "$TEMP_DIR/$PLUGIN_NAME/"
cp readme.txt "$TEMP_DIR/$PLUGIN_NAME/"
cp LICENSE "$TEMP_DIR/$PLUGIN_NAME/"

# Copy directories (exclude development files)
cp -r includes "$TEMP_DIR/$PLUGIN_NAME/"
cp -r admin "$TEMP_DIR/$PLUGIN_NAME/"
cp -r public "$TEMP_DIR/$PLUGIN_NAME/"
cp -r templates "$TEMP_DIR/$PLUGIN_NAME/"
cp -r languages "$TEMP_DIR/$PLUGIN_NAME/"

# Copy assets (only minified and required files)
mkdir -p "$TEMP_DIR/$PLUGIN_NAME/assets"
cp assets/*.min.js "$TEMP_DIR/$PLUGIN_NAME/assets/" 2>/dev/null || true
cp assets/*.min.css "$TEMP_DIR/$PLUGIN_NAME/assets/" 2>/dev/null || true
cp assets/*.svg "$TEMP_DIR/$PLUGIN_NAME/assets/" 2>/dev/null || true

# Remove source files from assets (only keep minified)
find "$TEMP_DIR/$PLUGIN_NAME/assets" -name "*.js" ! -name "*.min.js" -delete 2>/dev/null || true
find "$TEMP_DIR/$PLUGIN_NAME/assets" -name "*.css" ! -name "*.min.css" -delete 2>/dev/null || true

# Remove development files if they exist
find "$TEMP_DIR/$PLUGIN_NAME" -name "*.backup.*" -delete 2>/dev/null || true
find "$TEMP_DIR/$PLUGIN_NAME" -name "test-*.php" -delete 2>/dev/null || true
find "$TEMP_DIR/$PLUGIN_NAME" -name "*.md" ! -name "README.md" -delete 2>/dev/null || true

echo "   📦 Creating ZIP archive..."

# Create ZIP from temp directory
cd "$TEMP_DIR"
zip -r "$PROJECT_ROOT/$ZIP_NAME" "$PLUGIN_NAME" -q
cd "$PROJECT_ROOT"

# Get file size
FILE_SIZE=$(du -h "$ZIP_NAME" | cut -f1)

echo ""
echo "✅ Distribution package created!"
echo ""
echo "   📦 Package: ${ZIP_NAME}"
echo "   📏 Size: ${FILE_SIZE}"
echo "   📍 Location: $(pwd)/${ZIP_NAME}"
echo ""
echo "📋 Package contents:"
unzip -l "$ZIP_NAME" | tail -n +4 | head -n 20 | sed 's/^/      /'
echo "   ... (and more)"
echo ""
echo "✅ Ready for distribution!"





