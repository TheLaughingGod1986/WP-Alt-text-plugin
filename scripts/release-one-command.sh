#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
PLUGIN_SLUG="beepbeep-ai-alt-text-generator"
ZIP_PATH_DEFAULT="${ROOT_DIR}/../plugin-builds/${PLUGIN_SLUG}.zip"

NOTES_FILE="${RELEASE_NOTES_FILE:-${ROOT_DIR}/release-notes.txt}"
VERSION="${RELEASE_VERSION:-}"
UPGRADE_NOTE="${RELEASE_UPGRADE_NOTE:-}"

[[ -f "${NOTES_FILE}" ]] || {
  printf '%s\n' "ERROR: release notes file not found: ${NOTES_FILE}" >&2
  exit 1
}

if [[ -z "${VERSION}" ]]; then
  AUTO_BUMP_PATCH="${AUTO_BUMP_PATCH:-1}"
else
  AUTO_BUMP_PATCH="0"
fi

AUTO_UPDATE_RELEASE_METADATA=1 \
AUTO_BUMP_PATCH="${AUTO_BUMP_PATCH}" \
RELEASE_VERSION="${VERSION}" \
RELEASE_NOTES_FILE="${NOTES_FILE}" \
RELEASE_UPGRADE_NOTE="${UPGRADE_NOTE}" \
"${ROOT_DIR}/scripts/build-release-zip.sh"

ZIP_PATH="${ZIP_PATH:-${ZIP_PATH_DEFAULT}}"
[[ -f "${ZIP_PATH}" ]] || {
  printf '%s\n' "ERROR: ZIP not found after build: ${ZIP_PATH}" >&2
  exit 1
}

ZIP_SIZE="$(du -h "${ZIP_PATH}" | awk '{print $1}')"
ZIP_SHA256="$(shasum -a 256 "${ZIP_PATH}" | awk '{print $1}')"

printf '%s\n' "Release ZIP ready"
printf '%s\n' "- Path: ${ZIP_PATH}"
printf '%s\n' "- Size: ${ZIP_SIZE}"
printf '%s\n' "- SHA256: ${ZIP_SHA256}"
