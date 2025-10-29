#!/usr/bin/env bash

# Build WordPress Plugin Package
# Produces a clean ZIP under dist/ ready for WordPress.org submission.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT_DIR"

PLUGIN_SLUG="ai-alt-text-generator"
BUILD_DIR="${ROOT_DIR}/build"
DIST_DIR="${ROOT_DIR}/dist"
PLUGIN_DIR="${BUILD_DIR}/${PLUGIN_SLUG}"

# Read version from the plugin header; fall back to timestamp
VERSION="$(grep -m1 "Version:" ai-alt-gpt.php | sed -E 's/.*Version:[[:space:]]*([0-9.]+).*/\1/' | tr -d '\r')"
if [[ -z "${VERSION}" ]]; then
  VERSION="latest-$(date +%Y%m%d%H%M)"
fi
ZIP_FILE="${DIST_DIR}/${PLUGIN_SLUG}-${VERSION}.zip"

echo "🚀 Building WordPress plugin package"
echo "📦 Slug:    ${PLUGIN_SLUG}"
echo "🏷️  Version: ${VERSION}"
echo ""

echo "🧹 Cleaning previous output"
rm -rf "$BUILD_DIR"
mkdir -p "$PLUGIN_DIR"
mkdir -p "$DIST_DIR"

echo "🔧 Building minified assets (Phase 2 optimization)"
# Check if node_modules exist, if not install
if [ ! -d "node_modules" ]; then
    echo "📦 Installing build dependencies..."
    npm install --quiet
fi

# Run minification if script exists
if [ -f "scripts/minify-js.js" ] && [ -f "scripts/minify-css.js" ]; then
    echo "🚀 Running asset minification..."
    npm run build:assets 2>/dev/null || {
        echo "⚠️  Minification failed, continuing with unminified assets"
    }
else
    echo "⚠️  Minification scripts not found, skipping..."
fi

echo "📋 Copying core files"
rsync -a \
  ai-alt-gpt.php LICENSE readme.txt \
  "$PLUGIN_DIR/"

# Copy assets - we'll filter out unminified files after
rsync -a \
  --exclude='wordpress-org' \
  --exclude='wordpress-org/**' \
  assets includes templates \
  "$PLUGIN_DIR/"

# Remove unminified JS/CSS files (only keep .min versions in production)
echo "🗑️  Removing unminified source files (keeping only .min versions)..."
find "$PLUGIN_DIR/assets" -type f \( -name "*.js" ! -name "*.min.js" \) -delete 2>/dev/null || true
find "$PLUGIN_DIR/assets" -type f \( -name "*.css" ! -name "*.min.css" \) -delete 2>/dev/null || true

echo "🧼 Stripping development artefacts and test files"
# Remove WordPress.org submission assets FIRST (not needed at runtime)
rm -rf "$PLUGIN_DIR/assets/wordpress-org" 2>/dev/null || true

# Remove backup files
find "$PLUGIN_DIR" -name "*.backup" -delete
find "$PLUGIN_DIR" -name "*.bak" -delete

# Remove development scripts
find "$PLUGIN_DIR" -name "*.sh" -delete
find "$PLUGIN_DIR" -name "*.py" -delete

# Remove logs and temporary files
find "$PLUGIN_DIR" -name "*.log" -delete
find "$PLUGIN_DIR" -name ".DS_Store" -delete
find "$PLUGIN_DIR" -name "Thumbs.db" -delete
find "$PLUGIN_DIR" -name ".gitkeep" -delete

# Remove documentation (keep only readme.txt for WordPress.org)
find "$PLUGIN_DIR" -name "*.md" -delete
find "$PLUGIN_DIR" -name "*.txt" -not -name "readme.txt" -delete

# Remove test files and directories
find "$PLUGIN_DIR" -path "*/tests/*" -delete
find "$PLUGIN_DIR" -name "*test*.php" -delete
find "$PLUGIN_DIR" -name "*Test.php" -delete
find "$PLUGIN_DIR" -name "*spec*.js" -delete

# Remove test images and demo files
find "$PLUGIN_DIR" -name "demo*.html" -delete
find "$PLUGIN_DIR" -name "DEMO.*" -delete
find "$PLUGIN_DIR" -name "test*.jpg" -delete
find "$PLUGIN_DIR" -name "test*.png" -delete
find "$PLUGIN_DIR" -name "test*.gif" -delete

# Remove configuration examples and env files
find "$PLUGIN_DIR" -name ".env*" -delete
find "$PLUGIN_DIR" -name "env.example" -delete
find "$PLUGIN_DIR" -name "*example*" -type f -delete

# Remove docker and deployment files
find "$PLUGIN_DIR" -name "docker-compose.yml" -delete
find "$PLUGIN_DIR" -name "Dockerfile*" -delete
find "$PLUGIN_DIR" -name ".dockerignore" -delete

# Remove node_modules if any were accidentally copied
find "$PLUGIN_DIR" -type d -name "node_modules" -exec rm -rf {} + 2>/dev/null || true

# Remove any hidden files except essential ones
find "$PLUGIN_DIR" -name ".*" -not -name ".git" -type f -delete 2>/dev/null || true

# Final cleanup: ensure wordpress-org is completely removed
rm -rf "$PLUGIN_DIR/assets/wordpress-org" 2>/dev/null || true

echo "🗜️  Creating ZIP archive"
pushd "$BUILD_DIR" > /dev/null
zip -qr "$ZIP_FILE" "$PLUGIN_SLUG"
popd > /dev/null

echo "🧽 Removing build directory"
rm -rf "$BUILD_DIR"

FILE_SIZE="$(du -h "$ZIP_FILE" | awk '{print $1}')"
echo ""
echo "✅ Build complete"
echo "📦 Package: ${ZIP_FILE}"
echo "💾 Size:    ${FILE_SIZE}"
echo ""
echo "Next steps:"
echo " 1. Upload the ZIP to WordPress (Plugins → Add New → Upload Plugin)"
echo " 2. Or via WP-CLI: wp plugin install ${ZIP_FILE} --activate"
echo ""
