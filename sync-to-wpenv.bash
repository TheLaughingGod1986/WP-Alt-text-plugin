#!/usr/bin/env bash
# Auto-sync plugin files to wp-env (and OrbStack container) on change.
#
# Usage:
#   ./sync-to-wpenv.bash              # watch mode (default)
#   ./sync-to-wpenv.bash --once       # one-shot sync, no watch
#
# This script delegates the actual sync to scripts/sync-to-wpenv-slug.bash,
# which auto-detects the wp-env hash, syncs into a correctly-named plugin
# slug folder, and also writes to the OrbStack container volume on macOS.
# Running fswatch here just re-invokes that script on each filesystem event.

set -euo pipefail

SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SYNC_SCRIPT="${SRC_DIR}/scripts/sync-to-wpenv-slug.bash"

if [[ ! -x "${SYNC_SCRIPT}" ]]; then
  echo "Could not find ${SYNC_SCRIPT}"
  echo "Run 'chmod +x scripts/sync-to-wpenv-slug.bash' if it exists but is not executable."
  exit 1
fi

run_sync() {
  "${SYNC_SCRIPT}"
}

# Initial sync.
run_sync

# --once: skip the watch loop (useful for CI or one-off pushes).
if [[ "${1:-}" == "--once" ]]; then
  exit 0
fi

if ! command -v fswatch >/dev/null 2>&1; then
  echo ""
  echo "fswatch is not installed — watch mode disabled."
  echo "Install with 'brew install fswatch' to enable continuous sync."
  exit 0
fi

echo ""
echo "Watching ${SRC_DIR} for changes (Ctrl+C to stop)..."

fswatch -o -r \
  --exclude='\.git' \
  --exclude='node_modules' \
  --exclude='\.claude' \
  --exclude='\.cursor' \
  --exclude='build' \
  --exclude='\.playwright' \
  --exclude='playwright-report' \
  --exclude='output' \
  --exclude='test-results' \
  --exclude='\._' \
  "${SRC_DIR}" | while read -r _count; do
    run_sync >/dev/null
    echo "--- synced at $(date +%H:%M:%S) ---"
  done
