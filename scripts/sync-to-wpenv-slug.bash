#!/usr/bin/env bash
set -euo pipefail

# Sync this repo into wp-env under the *correct plugin slug folder name* so tools
# like "Plugin Check" don't infer an incorrect text domain from the bind-mount
# folder name (e.g. "WP-Alt-text-plugin").
#
# Usage:
#   ./scripts/sync-to-wpenv-slug.bash
#
# Then in wp-admin → Tools → Plugin Check, select:
#   "BeepBeep AI – Alt Text Generator" (folder: beepbeep-ai-alt-text-generator)

SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CLEAN_SRC_DIR="${SRC_DIR}/build/wpenv-plugin/beepbeep-ai-alt-text-generator/"

# Auto-detect the wp-env hash by finding any ~/.wp-env/<hash>/WordPress directory.
# The hash changes whenever wp-env is recreated, so we detect it rather than
# hardcoding it (a hardcoded hash breaks the sync on any other machine or after
# a 'wp-env destroy && wp-env start').
WP_ENV_HASH=""
if [[ -d "${HOME}/.wp-env" ]]; then
  for candidate in "${HOME}"/.wp-env/*/WordPress; do
    if [[ -d "${candidate}" ]]; then
      WP_ENV_HASH="$(basename "$(dirname "${candidate}")")"
      break
    fi
  done
fi

if [[ -z "${WP_ENV_HASH}" ]]; then
  echo "No wp-env WordPress directory found under ~/.wp-env/"
  echo ""
  echo "Start wp-env first (e.g. 'npx wp-env start' or 'wp-env start'), then re-run."
  exit 1
fi

DEST_DIR="${HOME}/.wp-env/${WP_ENV_HASH}/WordPress/wp-content/plugins/beepbeep-ai-alt-text-generator/"

echo "Detected wp-env hash: ${WP_ENV_HASH}"

mkdir -p "${CLEAN_SRC_DIR}" "${DEST_DIR}"

rsync -av --delete \
  --exclude=".git" \
  --exclude=".gitattributes" \
  --exclude=".gitignore" \
  --exclude=".wp-env.json" \
  --exclude=".vscode" \
  --exclude="AGENTS.md" \
  --exclude="build" \
  --exclude="node_modules" \
  --exclude=".playwright-cli" \
  --exclude=".playwright-mcp" \
  --exclude="playwright-report" \
  --exclude="output" \
  --exclude="dist" \
  --exclude="docs" \
  --exclude="scripts" \
  --exclude="tests" \
  --exclude=".wporg-svn" \
  --exclude="package.json" \
  --exclude="package-lock.json" \
  --exclude="tests/e2e/test-results" \
  --exclude="test-results" \
  --exclude="login-helper.js" \
  --exclude="wp-login.js" \
  --exclude="playwright.config.ts" \
  --exclude="jest.config.js" \
  --exclude="output-dashboard-after.png" \
  --exclude="output-dashboard-before.png" \
  --exclude="output-dashboard-final.png" \
  --exclude="rescan-complete-feedback.png" \
  --exclude="sync-to-wpenv.bash" \
  --exclude=".cursor" \
  --exclude=".claude" \
  "${SRC_DIR}/" "${CLEAN_SRC_DIR}"

# Excluded paths are not removed by rsync --delete; strip dev-only / artifact dirs from the clean mirror.
rm -rf \
  "${CLEAN_SRC_DIR}/playwright-report" \
  "${CLEAN_SRC_DIR}/.playwright-mcp" \
  "${CLEAN_SRC_DIR}/build" \
  "${CLEAN_SRC_DIR}/dist" \
  "${CLEAN_SRC_DIR}/docs" \
  "${CLEAN_SRC_DIR}/output" \
  "${CLEAN_SRC_DIR}/scripts" \
  "${CLEAN_SRC_DIR}/tests" \
  "${CLEAN_SRC_DIR}/.wporg-svn" \
  "${CLEAN_SRC_DIR}/.gitignore" \
  "${CLEAN_SRC_DIR}/test-results" \
  "${CLEAN_SRC_DIR}/.wp-env.json" \
  "${CLEAN_SRC_DIR}/.vscode" \
  "${CLEAN_SRC_DIR}/AGENTS.md" \
  "${CLEAN_SRC_DIR}/package.json" \
  "${CLEAN_SRC_DIR}/package-lock.json" \
  "${CLEAN_SRC_DIR}/login-helper.js" \
  "${CLEAN_SRC_DIR}/wp-login.js" \
  "${CLEAN_SRC_DIR}/playwright.config.ts" \
  "${CLEAN_SRC_DIR}/jest.config.js" \
  "${CLEAN_SRC_DIR}/output-dashboard-after.png" \
  "${CLEAN_SRC_DIR}/output-dashboard-before.png" \
  "${CLEAN_SRC_DIR}/output-dashboard-final.png" \
  "${CLEAN_SRC_DIR}/rescan-complete-feedback.png" \
  "${CLEAN_SRC_DIR}/sync-to-wpenv.bash" \
  "${CLEAN_SRC_DIR}/.cursor" \
  "${CLEAN_SRC_DIR}/.claude"

rsync -a --delete "${CLEAN_SRC_DIR}/" "${DEST_DIR}"

echo ""
echo "Clean source:"
echo "  ${CLEAN_SRC_DIR}"
echo "Synced to wp-env fallback path:"
echo "  ${DEST_DIR}"
