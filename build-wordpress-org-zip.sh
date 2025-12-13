#!/bin/bash
#
# Build WordPress.org Submission ZIP
# This script creates a clean plugin ZIP suitable for WordPress.org submission
#

set -e  # Exit on any error

PLUGIN_SLUG="beepbeep-ai-alt-text-generator"
VERSION="4.2.3"
BUILD_DIR="build"
DIST_DIR="dist"
ZIP_NAME="${PLUGIN_SLUG}.${VERSION}.zip"

echo "======================================"
echo "WordPress.org Submission ZIP Builder"
echo "======================================"
echo ""
echo "Plugin: ${PLUGIN_SLUG}"
echo "Version: ${VERSION}"
echo ""

# Clean previous builds
echo "→ Cleaning previous builds..."
rm -rf "${BUILD_DIR}"
rm -rf "${DIST_DIR}"
mkdir -p "${BUILD_DIR}/${PLUGIN_SLUG}"
mkdir -p "${DIST_DIR}"

# Copy only production files
echo "→ Copying production files..."

# Main plugin file and uninstall
cp beepbeep-ai-alt-text-generator.php "${BUILD_DIR}/${PLUGIN_SLUG}/"
cp uninstall.php "${BUILD_DIR}/${PLUGIN_SLUG}/"
cp readme.txt "${BUILD_DIR}/${PLUGIN_SLUG}/"

# Copy directories (exclude development files)
echo "  - Copying admin/"
cp -r admin "${BUILD_DIR}/${PLUGIN_SLUG}/"

echo "  - Copying includes/"
cp -r includes "${BUILD_DIR}/${PLUGIN_SLUG}/"

echo "  - Copying assets/"
cp -r assets "${BUILD_DIR}/${PLUGIN_SLUG}/"

echo "  - Copying templates/"
cp -r templates "${BUILD_DIR}/${PLUGIN_SLUG}/"

# Copy languages if exists
if [ -d "languages" ]; then
    echo "  - Copying languages/"
    cp -r languages "${BUILD_DIR}/${PLUGIN_SLUG}/"
fi

# Remove development/test files from copied directories
echo "→ Removing development files..."

# Remove test/debug files
find "${BUILD_DIR}" -type f -name "*test*.php" -delete
find "${BUILD_DIR}" -type f -name "*debug*.php" -delete
find "${BUILD_DIR}" -type f -name "*reset*.php" -delete
find "${BUILD_DIR}" -type f -name "*clear*.php" -delete
find "${BUILD_DIR}" -type f -name "*check*.php" -delete
find "${BUILD_DIR}" -type f -name "*diagnose*.php" -delete
find "${BUILD_DIR}" -type f -name "*fix*.php" -delete

# Remove all .md files except README.md (if present)
find "${BUILD_DIR}" -type f -name "*.md" ! -name "README.md" -delete

# Remove version control
find "${BUILD_DIR}" -type d -name ".git" -exec rm -rf {} + 2>/dev/null || true
find "${BUILD_DIR}" -type d -name ".github" -exec rm -rf {} + 2>/dev/null || true
find "${BUILD_DIR}" -type f -name ".gitignore" -delete

# Remove node modules
find "${BUILD_DIR}" -type d -name "node_modules" -exec rm -rf {} + 2>/dev/null || true

# Remove common development files
find "${BUILD_DIR}" -type f -name "*.sh" -delete
find "${BUILD_DIR}" -type f -name "*.sql" -delete
find "${BUILD_DIR}" -type f -name ".DS_Store" -delete
find "${BUILD_DIR}" -type f -name "Thumbs.db" -delete
find "${BUILD_DIR}" -type f -name "*.log" -delete

# Remove source/uncompiled assets if any
find "${BUILD_DIR}" -type d -name "src" -path "*/assets/src" -exec rm -rf {} + 2>/dev/null || true

echo "→ Creating ZIP archive..."
cd "${BUILD_DIR}"
zip -r "../${DIST_DIR}/${ZIP_NAME}" "${PLUGIN_SLUG}" -q
cd ..

# Get file size
SIZE=$(du -h "${DIST_DIR}/${ZIP_NAME}" | cut -f1)

echo ""
echo "======================================"
echo "✅ Build Complete!"
echo "======================================"
echo ""
echo "Output: ${DIST_DIR}/${ZIP_NAME}"
echo "Size: ${SIZE}"
echo ""
echo "Contents:"
unzip -l "${DIST_DIR}/${ZIP_NAME}" | head -30
echo ""
echo "Total files:"
unzip -l "${DIST_DIR}/${ZIP_NAME}" | tail -1
echo ""
echo "======================================"
echo "Next Steps:"
echo "======================================"
echo "1. Extract and test the ZIP on a fresh WordPress install"
echo "2. Verify no PHP errors with WP_DEBUG enabled"
echo "3. Check https://oppti.dev/privacy is live"
echo "4. Check https://oppti.dev/terms is live"
echo "5. Create screenshot/banner PNG images"
echo "6. Submit to wordpress.org/plugins/developers/add/"
echo ""
