#!/bin/bash

# Create a clean WordPress.org submission zip
# This excludes development files and includes only plugin files

cd "$(dirname "$0")"

ZIP_NAME="beepbeep-ai-alt-text-generator-4.2.3-wp-submission.zip"

# Remove existing zip if it exists
rm -f "$ZIP_NAME"

# Create the zip file with only essential plugin files
# Note: We'll include readme.txt separately to avoid exclusion
zip -r "$ZIP_NAME" \
  beepbeep-ai-alt-text-generator.php \
  LICENSE \
  uninstall.php \
  admin \
  includes \
  assets \
  languages \
  templates \
  -x "*.DS_Store" \
  -x "*/.git/*" \
  -x "*/.gitkeep" \
  -x "*node_modules/*" \
  -x "*.zip" \
  -x "*.md" \
  -x "*.sh" \
  -x "*.sql" \
  -x "dist/*" \
  -x "docs/*" \
  -x "scripts/*" \
  -x "beepbeep-ai-alt-text-generator/*" \
  -x "docker-compose.yml"

# Add readme.txt separately
zip -u "$ZIP_NAME" readme.txt

echo "✅ Created: $ZIP_NAME"
echo ""
echo "Files included:"
unzip -l "$ZIP_NAME" | head -30

