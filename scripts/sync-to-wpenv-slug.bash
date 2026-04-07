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
#   the plugin in folder "beepbeep-ai-alt-text-generator".

SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# wp-env project hash for this repo (matches current docker container names).
WP_ENV_HASH="06fe8883b07a5e21412cec8c726b075e"

DEST_DIR="${HOME}/.wp-env/${WP_ENV_HASH}/WordPress/wp-content/plugins/beepbeep-ai-alt-text-generator/"

if [[ ! -d "${HOME}/.wp-env/${WP_ENV_HASH}/WordPress" ]]; then
  echo "wp-env WordPress directory not found at:"
  echo "  ${HOME}/.wp-env/${WP_ENV_HASH}/WordPress"
  echo ""
  echo "Start wp-env first (e.g. 'wp-env start'), then re-run."
  exit 1
fi

mkdir -p "${DEST_DIR}"

rsync -av --delete \
  --exclude=".git" \
  --exclude="node_modules" \
  --exclude=".playwright-cli" \
  --exclude="output" \
  --exclude="tests/e2e/test-results" \
  "${SRC_DIR}/" "${DEST_DIR}"

echo ""
echo "Synced to:"
echo "  ${DEST_DIR}"
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

# wp-env project hash for this repo (matches current docker container names).
WP_ENV_HASH="06fe8883b07a5e21412cec8c726b075e"

DEST_DIR="${HOME}/.wp-env/${WP_ENV_HASH}/WordPress/wp-content/plugins/beepbeep-ai-alt-text-generator/"

if [[ ! -d "${HOME}/.wp-env/${WP_ENV_HASH}/WordPress" ]]; then
  echo "wp-env WordPress directory not found at:"
  echo "  ${HOME}/.wp-env/${WP_ENV_HASH}/WordPress"
  echo ""
  echo "Start wp-env first (e.g. 'wp-env start'), then re-run."
  exit 1
fi

mkdir -p "${DEST_DIR}"

rsync -av --delete \
  --exclude=".git" \
  --exclude="node_modules" \
  --exclude=".playwright-cli" \
  --exclude="output" \
  --exclude="tests/e2e/test-results" \
  "${SRC_DIR}/" "${DEST_DIR}"

echo ""
echo "Synced to:"
echo "  ${DEST_DIR}"
