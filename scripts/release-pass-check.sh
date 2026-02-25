#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="beepbeep-ai-alt-text-generator"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
BUILD_SCRIPT="${BUILD_SCRIPT:-${ROOT_DIR}/scripts/build-release-zip.sh}"
ZIP_PATH="${ZIP_PATH:-${ROOT_DIR}/../plugin-builds/${PLUGIN_SLUG}.zip}"
HELPER_SCRIPT="${HELPER_SCRIPT:-${ROOT_DIR}/../wp-plugin-tools/release-pass-check.sh}"
LOG_PATH="${LOG_PATH:-${ROOT_DIR}/release-pass-check.log}"

fail() {
  printf '%s\n' "ERROR: $*" >&2
  exit 1
}

need_cmd() {
  command -v "$1" >/dev/null 2>&1 || fail "Missing required command: $1"
}

check_zip_forbidden_files() {
  local zip="$1"
  local zip_entries
  local forbidden_re='(^|/)\.[^/]+(/|$)|(^|/)scripts(/|$)|(^|/)tools(/|$)|(^|/)\.claude(/|$)|(^|/)\.git[^/]*(/|$)|\.gitignore$|\.distignore$|\.md$|(^|/)\.DS_Store$|(^|/)Thumbs\.db$'

  [[ -f "${zip}" ]] || fail "ZIP not found: ${zip}"

  zip_entries="$(unzip -Z1 "${zip}")" || fail "Unable to list ZIP contents: ${zip}"
  if printf '%s\n' "${zip_entries}" | grep -E "${forbidden_re}" >/dev/null; then
    printf '%s\n' "Forbidden files detected in ZIP:" >&2
    printf '%s\n' "${zip_entries}" | grep -E "${forbidden_re}" >&2 || true
    fail "Forbidden files found in ZIP."
  fi

  printf '%s\n' "No forbidden files in ZIP"
}

assert_no_repo_root_zip() {
  local root_zip
  root_zip="$(find "${ROOT_DIR}" -maxdepth 1 -type f -name '*.zip' -print -quit)"
  if [[ -n "${root_zip}" ]]; then
    fail "Repo root contains a ZIP file (${root_zip}). Release ZIPs must be written outside the plugin folder."
  fi
}

extract_summary_field() {
  local line="$1"
  local field="$2"
  printf '%s\n' "${line}" | sed -E "s/.*${field}=([^, ]+).*/\\1/"
}

extract_ready_for_upload() {
  local log_file="$1"
  awk '
    /^### READY_FOR_UPLOAD$/ { in_block=1; next }
    in_block && /^- / { gsub(/^- /, "", $0); print $0; exit }
  ' "${log_file}"
}

need_cmd unzip
need_cmd grep
need_cmd sed
need_cmd tee
need_cmd awk
need_cmd find

[[ -x "${BUILD_SCRIPT}" ]] || fail "Build script not found/executable: ${BUILD_SCRIPT}"
printf '%s\n' "Building release ZIP with ${BUILD_SCRIPT}"
"${BUILD_SCRIPT}"

assert_no_repo_root_zip
check_zip_forbidden_files "${ZIP_PATH}"

[[ -x "${HELPER_SCRIPT}" ]] || fail "Helper script not found/executable: ${HELPER_SCRIPT}"

mkdir -p "$(dirname "${LOG_PATH}")"
rm -f "${LOG_PATH}"

set +e
ZIP_PATH="${ZIP_PATH}" "${HELPER_SCRIPT}" | tee "${LOG_PATH}"
helper_rc=${PIPESTATUS[0]}
set -e

summary_line="$(grep -E '^- Plugin Check: exit code=' "${LOG_PATH}" | tail -n 1 || true)"
activate_line="$(grep -E '^- Install/Activate:' "${LOG_PATH}" | tail -n 1 || true)"
debug_line="$(grep -E '^- debug\.log:' "${LOG_PATH}" | tail -n 1 || true)"
ready_line="$(extract_ready_for_upload "${LOG_PATH}" || true)"

if [[ -z "${summary_line}" ]]; then
  fail "Plugin Check summary line not found in ${LOG_PATH}"
fi
if [[ -z "${activate_line}" ]]; then
  fail "Install/Activate summary line not found in ${LOG_PATH}"
fi

plugin_check_exit_code="$(extract_summary_field "${summary_line}" 'exit code')"
plugin_check_error_count="$(extract_summary_field "${summary_line}" 'error count')"
plugin_check_warning_count="$(extract_summary_field "${summary_line}" 'warning count')"
activate_status="$(printf '%s\n' "${activate_line}" | sed -E 's/^- Install\/Activate: *//')"

printf '%s\n' "ZIP path: ${ZIP_PATH}"
printf '%s\n' "Plugin Check summary: exit=${plugin_check_exit_code} errors=${plugin_check_error_count} warnings=${plugin_check_warning_count}"
printf '%s\n' "Activation summary: ${activate_status}"
if [[ -n "${debug_line}" ]]; then
  printf '%s\n' "${debug_line}"
fi
if [[ -n "${ready_line}" ]]; then
  printf '%s\n' "READY_FOR_UPLOAD=${ready_line}"
fi

[[ "${helper_rc}" -eq 0 ]] || fail "Validation helper failed (exit ${helper_rc}). See ${LOG_PATH}"
[[ "${activate_status}" == "PASS" ]] || fail "Plugin activation failed. See ${LOG_PATH}"
[[ "${plugin_check_error_count}" == "0" ]] || fail "Plugin Check errors remain (${plugin_check_error_count}). See ${LOG_PATH}"
if [[ -n "${ready_line}" ]]; then
  [[ "${ready_line}" == "YES" ]] || fail "READY_FOR_UPLOAD is ${ready_line}. See ${LOG_PATH}"
fi
