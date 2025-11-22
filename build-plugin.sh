#!/usr/bin/env bash

# Build WordPress Plugin Package
# Produces a clean ZIP ready for WordPress.org submission or distribution.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT_DIR"

PLUGIN_SLUG="beepbeep-ai-alt-text-generator"
BUILD_DIR="${ROOT_DIR}/build"
DIST_DIR="${ROOT_DIR}/dist"
PLUGIN_DIR="${BUILD_DIR}/${PLUGIN_SLUG}"

# Read version from the plugin header
VERSION="$(grep -m1 "Version:" beepbeep-ai-alt-text-generator.php | sed -E 's/.*Version:[[:space:]]*([0-9.]+).*/\1/' | tr -d '\r')"
if [[ -z "${VERSION}" ]]; then
  VERSION="latest-$(date +%Y%m%d%H%M)"
fi
ZIP_FILE="${DIST_DIR}/${PLUGIN_SLUG}-${VERSION}.zip"

echo "🚀 Building WordPress plugin package"
echo "📦 Slug:    ${PLUGIN_SLUG}"
echo "🏷️  Version: ${VERSION}"
echo ""

echo "🧹 Cleaning previous output"
rm -rf "$BUILD_DIR" "$DIST_DIR"
mkdir -p "$PLUGIN_DIR"
mkdir -p "$DIST_DIR"

echo "📋 Copying core plugin files"
cp beepbeep-ai-alt-text-generator.php "$PLUGIN_DIR/"
cp readme.txt "$PLUGIN_DIR/" 2>/dev/null || true
cp LICENSE "$PLUGIN_DIR/" 2>/dev/null || true
cp uninstall.php "$PLUGIN_DIR/" 2>/dev/null || true

echo "📁 Copying plugin directories"
# Copy admin directory
if [ -d "admin" ]; then
    rsync -a --exclude='*.md' --exclude='*.txt' --exclude='.DS_Store' admin/ "$PLUGIN_DIR/admin/"
fi

# Copy includes directory
if [ -d "includes" ]; then
    rsync -a --exclude='*.md' --exclude='*.txt' --exclude='.DS_Store' includes/ "$PLUGIN_DIR/includes/"
fi

# Copy templates directory
if [ -d "templates" ]; then
    rsync -a --exclude='*.md' --exclude='*.txt' --exclude='.DS_Store' templates/ "$PLUGIN_DIR/templates/"
fi

# Copy languages directory
if [ -d "languages" ]; then
    rsync -a --exclude='*.md' --exclude='*.txt' --exclude='.DS_Store' languages/ "$PLUGIN_DIR/languages/"
fi

# Copy assets directory (exclude wordpress-org and source files)
if [ -d "assets" ]; then
    rsync -a \
      --exclude='wordpress-org' \
      --exclude='wordpress-org/**' \
      --exclude='*.md' \
      --exclude='.DS_Store' \
      assets/ "$PLUGIN_DIR/assets/"
    
    # Remove source files if dist exists (keep only compiled/minified)
    if [ -d "$PLUGIN_DIR/assets/dist" ]; then
        echo "🗑️  Removing source files (keeping only dist)..."
        rm -rf "$PLUGIN_DIR/assets/src" 2>/dev/null || true
    fi
fi

echo "🧼 Cleaning development files"
# Remove WordPress.org submission assets (not needed at runtime)
rm -rf "$PLUGIN_DIR/assets/wordpress-org" 2>/dev/null || true

# Remove backup files
find "$PLUGIN_DIR" -name "*.backup" -delete 2>/dev/null || true
find "$PLUGIN_DIR" -name "*.bak" -delete 2>/dev/null || true

# Remove development scripts
find "$PLUGIN_DIR" -name "*.sh" -delete 2>/dev/null || true
find "$PLUGIN_DIR" -name "*.py" -delete 2>/dev/null || true

# Remove logs and temporary files
find "$PLUGIN_DIR" -name "*.log" -delete 2>/dev/null || true
find "$PLUGIN_DIR" -name ".DS_Store" -delete 2>/dev/null || true
find "$PLUGIN_DIR" -name "Thumbs.db" -delete 2>/dev/null || true
find "$PLUGIN_DIR" -name ".gitkeep" -delete 2>/dev/null || true

# Remove documentation (keep only readme.txt)
find "$PLUGIN_DIR" -name "*.md" -delete 2>/dev/null || true
find "$PLUGIN_DIR" -name "*.txt" -not -name "readme.txt" -delete 2>/dev/null || true

# Remove test files
find "$PLUGIN_DIR" -path "*/tests/*" -delete 2>/dev/null || true
find "$PLUGIN_DIR" -name "*test*.php" -delete 2>/dev/null || true
find "$PLUGIN_DIR" -name "*Test.php" -delete 2>/dev/null || true
find "$PLUGIN_DIR" -name "*spec*.js" -delete 2>/dev/null || true
find "$PLUGIN_DIR" -name "check-*.php" -delete 2>/dev/null || true
find "$PLUGIN_DIR" -name "test-*.php" -delete 2>/dev/null || true
find "$PLUGIN_DIR" -name "mock-*.js" -delete 2>/dev/null || true

# Remove node_modules if any were accidentally copied
find "$PLUGIN_DIR" -type d -name "node_modules" -exec rm -rf {} + 2>/dev/null || true

# Remove any hidden files except essential ones
find "$PLUGIN_DIR" -name ".*" -not -name "." -not -name ".." -type f -delete 2>/dev/null || true

echo "🗜️  Creating ZIP archive"
cd "$BUILD_DIR"
zip -qr "$ZIP_FILE" "$PLUGIN_SLUG"
cd "$ROOT_DIR"

echo "🧽 Removing build directory"
rm -rf "$BUILD_DIR"

FILE_SIZE="$(du -h "$ZIP_FILE" | awk '{print $1}')"
echo ""
echo "✅ Build complete!"
echo "📦 Package: ${ZIP_FILE}"
echo "💾 Size:    ${FILE_SIZE}"
echo ""
echo "Next steps:"
echo " 1. Upload the ZIP to WordPress (Plugins → Add New → Upload Plugin)"
echo " 2. Or via WP-CLI: wp plugin install ${ZIP_FILE} --activate"
echo ""
