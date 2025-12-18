#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="beepbeep-ai-alt-text-generator"
OUTPUT_ZIP="${PLUGIN_SLUG}.zip"

echo "Building ${OUTPUT_ZIP} with git archive (export-ignore respected)..."
git archive --format=zip --output "${OUTPUT_ZIP}" HEAD

echo "Archive created: ${OUTPUT_ZIP}"
echo "Verifying exclusions (.gitignore, .gitmodules, scripts/, tests/, vendor/, node_modules/, dev scripts)..."
unzip -l "${OUTPUT_ZIP}" | grep -E "(.gitignore|.gitmodules|scripts/|tests/|vendor/|node_modules/|check-|test-|force-|reset-credits|activate-license-direct|debug-)" || true

echo "If no unwanted entries are listed above, the archive is clean."
