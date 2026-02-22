#!/usr/bin/env bash
# zip-smoke-test.sh
# Copies a plugin ZIP into the WordPress container, installs/activates it via
# WP-CLI, runs plugin-check, and prints diagnostics.
#
# Usage:
#   ./zip-smoke-test.sh
#   ZIP_PATH=/path/to/plugin.zip ./zip-smoke-test.sh
#
# Env overrides:
#   CID            – container ID/name (auto-detected if omitted)
#   PLUGIN_SLUG    – default: beepbeep-ai-alt-text-generator
#   ZIP_PATH       – default: searches common locations
set -euo pipefail

PLUGIN_SLUG="${PLUGIN_SLUG:-beepbeep-ai-alt-text-generator}"
REMOTE_ZIP="/tmp/${PLUGIN_SLUG}.zip"

# ── Helper ───────────────────────────────────────────────────────────────────
wp_exec() {
  docker exec "$CID" wp --allow-root --path=/var/www/html "$@"
}

# ── 1. Find WordPress container ─────────────────────────────────────────────
echo "==> Step 1: Locating WordPress container..."
if [[ -z "${CID:-}" ]]; then
  CID=$(docker ps --filter "label=com.docker.compose.service=wordpress" \
        --format '{{.ID}}' | head -1)
  if [[ -z "$CID" ]]; then
    CID=$(docker ps --filter "ancestor=wordpress" --format '{{.ID}}' | head -1)
  fi
fi

if [[ -z "$CID" ]]; then
  echo "ERROR: No running WordPress container found. Set CID env var." >&2
  exit 1
fi
echo "    Container: $CID"

# Confirm plugin dir is NOT a mountpoint
STILL_MOUNTED=$(docker exec "$CID" sh -c "mount | grep '${PLUGIN_SLUG}'" 2>/dev/null || true)
if [[ -n "$STILL_MOUNTED" ]]; then
  echo "ERROR: Plugin directory is still a mountpoint. Run fix-mount-and-recreate.sh first." >&2
  echo "    $STILL_MOUNTED" >&2
  exit 1
fi

# ── 2. Locate ZIP file ──────────────────────────────────────────────────────
echo "==> Step 2: Locating ZIP file..."
if [[ -z "${ZIP_PATH:-}" ]]; then
  # Search common locations
  for candidate in \
    "./${PLUGIN_SLUG}.zip" \
    "../plugin-builds/${PLUGIN_SLUG}.zip" \
    "../plugin-builds/${PLUGIN_SLUG}-local-artifact.zip" \
    "$(find "$(pwd)" -maxdepth 2 -name "${PLUGIN_SLUG}*.zip" -type f 2>/dev/null | head -1)"; do
    if [[ -n "$candidate" && -f "$candidate" ]]; then
      ZIP_PATH="$candidate"
      break
    fi
  done
fi

if [[ -z "${ZIP_PATH:-}" || ! -f "$ZIP_PATH" ]]; then
  echo "ERROR: No ZIP file found. Set ZIP_PATH env var." >&2
  echo "    Searched for ${PLUGIN_SLUG}*.zip in current and plugin-builds dirs." >&2
  exit 1
fi
ZIP_PATH="$(cd "$(dirname "$ZIP_PATH")" && pwd)/$(basename "$ZIP_PATH")"
echo "    ZIP: $ZIP_PATH ($(du -h "$ZIP_PATH" | cut -f1))"

# ── 3. Install WP-CLI if needed ─────────────────────────────────────────────
echo "==> Step 3: Ensuring WP-CLI is available..."
if ! docker exec "$CID" which wp >/dev/null 2>&1; then
  echo "    Installing WP-CLI..."
  docker exec "$CID" bash -c '
    curl -sO https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar &&
    chmod +x wp-cli.phar &&
    mv wp-cli.phar /usr/local/bin/wp
  '
fi
echo "    WP-CLI version: $(wp_exec --version 2>&1)"

# ── 4. Copy ZIP into container ───────────────────────────────────────────────
echo "==> Step 4: Copying ZIP into container..."
docker cp "$ZIP_PATH" "${CID}:${REMOTE_ZIP}"
echo "    Copied to ${CID}:${REMOTE_ZIP}"

# ── 5. Clean up any existing install ────────────────────────────────────────
echo "==> Step 5: Removing existing plugin install (if any)..."
wp_exec plugin deactivate "$PLUGIN_SLUG" 2>/dev/null || true
wp_exec plugin delete "$PLUGIN_SLUG" 2>/dev/null || true

# ── 6. Install + activate from ZIP ──────────────────────────────────────────
echo "==> Step 6: Installing plugin from ZIP..."
wp_exec plugin install "$REMOTE_ZIP" --force --activate
echo "    Plugin installed and activated."

# Verify
echo ""
echo "--- Plugin status ---"
wp_exec plugin status "$PLUGIN_SLUG"
echo ""

# ── 7. Install plugin-check if needed ───────────────────────────────────────
echo "==> Step 7: Ensuring plugin-check is installed..."
if ! wp_exec plugin is-installed plugin-check 2>/dev/null; then
  echo "    Installing plugin-check..."
  wp_exec plugin install plugin-check --activate
elif ! wp_exec plugin is-active plugin-check 2>/dev/null; then
  wp_exec plugin activate plugin-check
fi
echo "    plugin-check is active."

# ── 8. Run plugin check ─────────────────────────────────────────────────────
echo "==> Step 8: Running plugin check..."
echo ""
echo "--- plugin-check output ---"
# plugin-check may return non-zero for warnings; capture but don't fail
wp_exec plugin check "$PLUGIN_SLUG" --format=table || true
echo "--- end plugin-check ---"
echo ""

# ── 9. Debug log ─────────────────────────────────────────────────────────────
echo "==> Step 9: Checking debug.log..."
DEBUG_LOG=$(docker exec "$CID" sh -c "
  if [ -f /var/www/html/wp-content/debug.log ]; then
    echo '--- Last 80 lines of debug.log ---'
    tail -80 /var/www/html/wp-content/debug.log
    echo '--- end debug.log ---'
  else
    echo '    No debug.log found.'
  fi
" 2>&1)
echo "$DEBUG_LOG"

# ── 10. Summary ──────────────────────────────────────────────────────────────
echo ""
echo "==> Smoke test complete."
echo "    Plugin: $PLUGIN_SLUG"
echo "    Container: $CID"
PLUGIN_VER=$(wp_exec plugin get "$PLUGIN_SLUG" --field=version 2>/dev/null || echo "unknown")
echo "    Version: $PLUGIN_VER"
PLUGIN_STATUS=$(wp_exec plugin get "$PLUGIN_SLUG" --field=status 2>/dev/null || echo "unknown")
echo "    Status: $PLUGIN_STATUS"
