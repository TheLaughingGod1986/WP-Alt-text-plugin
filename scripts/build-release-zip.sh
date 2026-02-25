#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="beepbeep-ai-alt-text-generator"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
DIST_DIR="${ROOT_DIR}/dist"
PLUGIN_STAGE="${DIST_DIR}/${PLUGIN_SLUG}"
DISTIGNORE_FILE="${ROOT_DIR}/.distignore"
OUT_DIR="${OUT_DIR:-${ROOT_DIR}/../plugin-builds}"
ZIP_PATH="${OUT_DIR}/${PLUGIN_SLUG}.zip"
TMP_STAGE_BASE="$(mktemp -d "${TMPDIR:-/tmp}/bbai-release-staging.XXXXXX")"
TMP_PLUGIN_STAGE="${TMP_STAGE_BASE}/${PLUGIN_SLUG}"

cleanup() {
  rm -rf "${TMP_STAGE_BASE}"
}
trap cleanup EXIT

fail() {
  printf '%s\n' "ERROR: $*" >&2
  exit 1
}

need_cmd() {
  command -v "$1" >/dev/null 2>&1 || fail "Missing required command: $1"
}

rsync_into_stage_dir() {
  local rel="$1"
  local src="${ROOT_DIR}/${rel}/"
  local dest="${TMP_PLUGIN_STAGE}/${rel}/"

  [[ -d "${src}" ]] || return 0
  mkdir -p "${dest}"

  rsync -a --delete --exclude-from="${DISTIGNORE_FILE}" "${src}" "${dest}"
}

copy_root_file_if_exists() {
  local rel="$1"
  local src="${ROOT_DIR}/${rel}"
  [[ -f "${src}" ]] || return 0
  cp -p "${src}" "${TMP_PLUGIN_STAGE}/"
}

prune_stage() {
  # Defense in depth: if .distignore changes, keep release ZIP clean.
  rm -rf \
    "${TMP_PLUGIN_STAGE}/scripts" \
    "${TMP_PLUGIN_STAGE}/tools" \
    "${TMP_PLUGIN_STAGE}/bin" \
    "${TMP_PLUGIN_STAGE}/tests" \
    "${TMP_PLUGIN_STAGE}/test" \
    "${TMP_PLUGIN_STAGE}/__tests__" \
    "${TMP_PLUGIN_STAGE}/cypress" \
    "${TMP_PLUGIN_STAGE}/playwright" \
    "${TMP_PLUGIN_STAGE}/docs" \
    "${TMP_PLUGIN_STAGE}/coverage" \
    "${TMP_PLUGIN_STAGE}/node_modules" \
    "${TMP_PLUGIN_STAGE}/vendor" \
    "${TMP_PLUGIN_STAGE}/vendor-bin" \
    "${TMP_PLUGIN_STAGE}/build" \
    "${TMP_PLUGIN_STAGE}/dist" \
    "${TMP_PLUGIN_STAGE}/release" \
    "${TMP_PLUGIN_STAGE}/release-staging" \
    "${TMP_PLUGIN_STAGE}/compressed_files" \
    "${TMP_PLUGIN_STAGE}/assets/wordpress-org" \
    "${TMP_PLUGIN_STAGE}/assets/src"

  find "${TMP_PLUGIN_STAGE}" -mindepth 1 -name '.*' -prune -exec rm -rf {} + 2>/dev/null || true
  find "${TMP_PLUGIN_STAGE}" -type f \( \
    -name '*.md' -o \
    -name '*.zip' -o -name '*.tar' -o -name '*.tar.gz' -o -name '*.tgz' -o \
    -name '*.gz' -o -name '*.bz2' -o -name '*.xz' -o -name '*.7z' -o -name '*.rar' -o -name '*.phar' -o \
    -name '*.sh' -o -name '*.command' -o -name '*.log' -o \
    -name '.gitignore' -o -name '.distignore' -o -name '.DS_Store' -o -name 'Thumbs.db' -o \
    -name '*.map' -o -name '*.py' -o -name '*.ps1' -o \
    -name '*.ts' -o -name '*.tsx' -o -name '*.jsx' -o \
    -name 'Makefile' -o -name 'makefile' -o -name 'GNUmakefile' -o \
    -name 'phpunit.xml' -o -name 'phpunit.xml.dist' -o -name 'phpstan*' -o -name 'psalm*' -o -name 'rector*' -o -name 'infection*' \
  \) -delete
  find "${TMP_PLUGIN_STAGE}" -depth -type d -empty -exec rmdir {} \; 2>/dev/null || true
}

scan_dist_forbidden_paths() {
  local list_file="${TMP_STAGE_BASE}/dist-file-list.txt"
  local forbidden_re='(^|/)\.[^/]+(/|$)|(^|/)scripts(/|$)|(^|/)tools(/|$)|(^|/)\.claude(/|$)|(^|/)\.git[^/]*(/|$)|(^|/)\.DS_Store$|(^|/)Thumbs\.db$|\.md$|\.(zip|tar|tgz|gz|bz2|xz|7z|rar|phar)$'

  (
    cd "${DIST_DIR}"
    find "${PLUGIN_SLUG}" -print | LC_ALL=C sort > "${list_file}"
  )

  if grep -E "${forbidden_re}" "${list_file}" >/dev/null; then
    printf '%s\n' "Forbidden paths found in dist stage:" >&2
    grep -E "${forbidden_re}" "${list_file}" >&2 || true
    fail "Forbidden files found in dist stage."
  fi
}

need_cmd rsync
need_cmd zip
need_cmd unzip
need_cmd find
need_cmd awk
need_cmd sort
need_cmd du
need_cmd grep

[[ -f "${ROOT_DIR}/${PLUGIN_SLUG}.php" ]] || fail "Main plugin file not found at ${ROOT_DIR}/${PLUGIN_SLUG}.php"
[[ -f "${DISTIGNORE_FILE}" ]] || fail "Missing ${DISTIGNORE_FILE}"

mkdir -p "${OUT_DIR}" "${DIST_DIR}"

local_out_abs="$(cd "${OUT_DIR}" && pwd)"
local_root_abs="${ROOT_DIR}"
case "${local_out_abs}/" in
  "${local_root_abs}/"*) fail "OUT_DIR must be outside plugin root (current: ${local_out_abs})" ;;
esac

rm -rf "${TMP_PLUGIN_STAGE}"
mkdir -p "${TMP_PLUGIN_STAGE}"

# Whitelist runtime directories.
for dir in admin assets includes templates languages; do
  rsync_into_stage_dir "${dir}"
done

# Whitelist runtime root files.
copy_root_file_if_exists "${PLUGIN_SLUG}.php"
copy_root_file_if_exists "readme.txt"
copy_root_file_if_exists "license.txt"
copy_root_file_if_exists "LICENSE"
copy_root_file_if_exists "uninstall.php"

# Preserve runtime fallback assets by copying assets/src into shipped assets/js + assets/css when present.
if [[ -d "${ROOT_DIR}/assets/src/js" ]]; then
  mkdir -p "${TMP_PLUGIN_STAGE}/assets"
  rsync -a --delete --exclude-from="${DISTIGNORE_FILE}" "${ROOT_DIR}/assets/src/js/" "${TMP_PLUGIN_STAGE}/assets/js/"
fi
if [[ -d "${ROOT_DIR}/assets/src/css" ]]; then
  mkdir -p "${TMP_PLUGIN_STAGE}/assets"
  rsync -a --delete --exclude-from="${DISTIGNORE_FILE}" "${ROOT_DIR}/assets/src/css/" "${TMP_PLUGIN_STAGE}/assets/css/"
fi

prune_stage

[[ -f "${TMP_PLUGIN_STAGE}/${PLUGIN_SLUG}.php" ]] || fail "Missing staged main plugin file."
[[ -f "${TMP_PLUGIN_STAGE}/readme.txt" ]] || fail "Missing staged readme.txt."
[[ -f "${TMP_PLUGIN_STAGE}/uninstall.php" ]] || fail "Missing staged uninstall.php."

rm -rf "${PLUGIN_STAGE}"
mkdir -p "${PLUGIN_STAGE}"
rsync -a --delete "${TMP_PLUGIN_STAGE}/" "${PLUGIN_STAGE}/"

scan_dist_forbidden_paths

rm -f "${ZIP_PATH}"
(
  cd "${DIST_DIR}"
  find "${PLUGIN_SLUG}" -print | LC_ALL=C sort | zip -X -q "${ZIP_PATH}" -@
)

# Verify ZIP shape and forbidden content.
ZIP_ENTRIES="$(unzip -Z1 "${ZIP_PATH}")"
TOP_DIRS="$(printf '%s\n' "${ZIP_ENTRIES}" | awk -F/ 'NF { print $1 }' | sort -u)"
TOP_COUNT="$(printf '%s\n' "${TOP_DIRS}" | awk 'NF { c++ } END { print c + 0 }')"
[[ "${TOP_COUNT}" -eq 1 ]] || fail "ZIP must contain exactly one top-level folder."
[[ "${TOP_DIRS}" == "${PLUGIN_SLUG}" ]] || fail "ZIP top-level folder must be ${PLUGIN_SLUG}/"

if printf '%s\n' "${ZIP_ENTRIES}" | grep -E '(^|/)\.[^/]+(/|$)|(^|/)scripts(/|$)|(^|/)tools(/|$)|(^|/)\.claude(/|$)|(^|/)\.git[^/]*(/|$)|\.gitignore$|\.distignore$|\.md$|(^|/)\.DS_Store$|(^|/)Thumbs\.db$|\.(zip|tar|tgz|gz|bz2|xz|7z|rar|phar)$' >/dev/null; then
  fail "Forbidden files found in release ZIP."
fi

ZIP_SIZE="$(du -h "${ZIP_PATH}" | awk '{ print $1 }')"

printf '%s\n' "Dist stage: ${PLUGIN_STAGE}"
printf '%s\n' "Built: ${ZIP_PATH}"
printf '%s\n' "Size: ${ZIP_SIZE}"
printf '%s\n' "Top-level folder: ${PLUGIN_SLUG}/"
printf '%s\n' "Top-level entries:"
printf '%s\n' "${ZIP_ENTRIES}" | awk -F/ -v slug="${PLUGIN_SLUG}" '$1 == slug && NF >= 2 && $2 != "" { print $2 }' | sort -u
printf '%s\n' "Release ZIP is clean."
printf '%s\n' "No forbidden files in ZIP"
