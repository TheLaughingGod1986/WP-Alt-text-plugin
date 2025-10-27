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

echo "📋 Copying core files"
rsync -a \
  ai-alt-gpt.php LICENSE readme.txt \
  "$PLUGIN_DIR/"

rsync -a \
  assets includes templates \
  "$PLUGIN_DIR/"

echo "🧼 Stripping development artefacts"
find "$PLUGIN_DIR" -name "*.backup" -delete
find "$PLUGIN_DIR" -name "*.bak" -delete
find "$PLUGIN_DIR" -name "*.sh" -delete
find "$PLUGIN_DIR" -name "*.py" -delete
find "$PLUGIN_DIR" -name "*.log" -delete
find "$PLUGIN_DIR" -name "*.md" -delete
find "$PLUGIN_DIR" -name ".DS_Store" -delete

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
