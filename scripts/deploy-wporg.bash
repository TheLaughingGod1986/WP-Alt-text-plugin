#!/usr/bin/env bash
set -euo pipefail

# Deploy to WordPress.org SVN.
#
# This script:
# - runs the local release build (zip + changelog + wp-env verify)
# - checks readme.txt Stable tag matches
# - exports a clean copy to SVN trunk
# - creates/updates the SVN tag for the version
# - commits trunk + tag
#
# Prereqs:
# - svn installed and authenticated (or provide creds via env)
#
# Usage:
#   WPORG_SLUG=beepbeep-ai-alt-text-generator \
#   WPORG_USER=your-wporg-username \
#   WPORG_PASS=your-wporg-password \
#   ./scripts/deploy-wporg.bash 4.6.2 "Dashboard lock + UX polish"
#
# Notes:
# - This does NOT change backend logic; it only ships what’s already in the repo.
# - WP.org may take time to update “Last updated” due to caching.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION="${1:-}"
NOTE="${2:-}"

if [[ -z "${VERSION}" ]]; then
  echo "Usage: ./scripts/deploy-wporg.bash <version> \"<short note>\""
  exit 1
fi

if ! command -v svn >/dev/null 2>&1; then
  echo "'svn' not found. Install Subversion to deploy to WordPress.org."
  exit 1
fi

WPORG_SLUG="${WPORG_SLUG:-beepbeep-ai-alt-text-generator}"
WPORG_USER="${WPORG_USER:-}"
WPORG_PASS="${WPORG_PASS:-}"

SVN_URL="https://plugins.svn.wordpress.org/${WPORG_SLUG}"
WORK_DIR="${ROOT_DIR}/.wporg-svn"

echo "Running release build (zip + changelog + wp-env verify)"
bash "${ROOT_DIR}/scripts/release.bash" "${VERSION}" "${NOTE}"

stable_tag="$(awk -F': ' '/^Stable tag:/{print $2; exit}' "${ROOT_DIR}/readme.txt" | tr -d '\r\n' || true)"
if [[ "${stable_tag}" != "${VERSION}" ]]; then
  echo "readme.txt Stable tag (${stable_tag:-missing}) does not match version ${VERSION}"
  echo "Update readme.txt then re-run."
  exit 1
fi

echo "Preparing SVN working copy at ${WORK_DIR}"
if [[ ! -d "${WORK_DIR}/.svn" ]]; then
  rm -rf "${WORK_DIR}"
  svn checkout "${SVN_URL}" "${WORK_DIR}"
else
  echo "Updating existing SVN working copy"
  svn update "${WORK_DIR}"
fi

echo "Syncing plugin into trunk"
mkdir -p "${WORK_DIR}/trunk"

rsync -av --delete \
  --exclude=".git" \
  --exclude=".wp-env.json" \
  --exclude="node_modules" \
  --exclude=".playwright-cli" \
  --exclude=".playwright-mcp" \
  --exclude="scripts" \
  --exclude="docs" \
  --exclude="tests" \
  --exclude="output" \
  --exclude="dist" \
  --exclude="package.json" \
  --exclude="package-lock.json" \
  --exclude="test-results" \
  --exclude="playwright-report" \
  --exclude=".cursor" \
  --exclude=".claude" \
  --exclude=".wporg-svn" \
  "${ROOT_DIR}/" "${WORK_DIR}/trunk/" >/dev/null

echo "Ensuring tag ${VERSION}"
mkdir -p "${WORK_DIR}/tags/${VERSION}"
rsync -av --delete "${WORK_DIR}/trunk/" "${WORK_DIR}/tags/${VERSION}/" >/dev/null

pushd "${WORK_DIR}" >/dev/null

echo "SVN add/remove"
svn status | awk '/^\?/ {print $2}' | xargs -I{} svn add --force "{}" >/dev/null 2>&1 || true
svn status | awk '/^!/ {print $2}' | xargs -I{} svn rm --force "{}" >/dev/null 2>&1 || true

echo "Committing to WP.org SVN"
msg="Release ${VERSION}"
if [[ -n "${NOTE}" ]]; then
  msg="${msg} — ${NOTE}"
fi

if [[ -n "${WPORG_USER}" && -n "${WPORG_PASS}" ]]; then
  svn commit -m "${msg}" --username "${WPORG_USER}" --password "${WPORG_PASS}" --no-auth-cache
else
  svn commit -m "${msg}"
fi

popd >/dev/null

echo ""
echo "WP.org deploy complete:"
echo "- ${SVN_URL}/tags/${VERSION}"
echo "- ${SVN_URL}/trunk"
echo ""
echo "If the plugin page still shows the old version, wait for WP.org cache refresh."

