#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="beepbeep-ai-alt-text-generator"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
ZIP_PATH="${ZIP_PATH:-${ROOT_DIR}/../plugin-builds/${PLUGIN_SLUG}.zip}"
VERIFY_DISPOSABLE_ON_MOUNT="${VERIFY_DISPOSABLE_ON_MOUNT:-0}"
DISPOSABLE_HELPER_SCRIPT="${DISPOSABLE_HELPER_SCRIPT:-${ROOT_DIR}/../wp-plugin-tools/release-pass-check.sh}"

WP_ROOT="/var/www/html"
PLUGIN_DIR="${WP_ROOT}/wp-content/plugins/${PLUGIN_SLUG}"
DEBUG_LOG_PATH="${WP_ROOT}/wp-content/debug.log"
ZIP_IN_CONTAINER="/tmp/${PLUGIN_SLUG}.zip"
WPCLI_PHAR="/tmp/wp-cli.phar"

TMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/bbai-verify-release.XXXXXX")"
ZIP_LISTING_FILE="${TMP_DIR}/zip-listing.txt"
ZIP_ENTRIES_FILE="${TMP_DIR}/zip-entries.txt"
FORBIDDEN_FILE="${TMP_DIR}/forbidden.txt"
PLUGIN_CHECK_OUT="${TMP_DIR}/plugin-check.out"
DEBUG_LOG_TAIL="${TMP_DIR}/debug-tail.txt"
WP_INSTALL_OUT="${TMP_DIR}/wp-install.out"
WP_PLUGIN_CHECK_INSTALL_OUT="${TMP_DIR}/wp-plugin-check-install.out"
DISPOSABLE_HELPER_LOG="${TMP_DIR}/disposable-helper.log"

SUMMARY_PRINTED=0
READY_FOR_UPLOAD="NO"
FAIL_REASON=""

CID=""
WPCLI_MODE=""
PLUGIN_DIR_MOUNT_STATUS="NOT_CHECKED"

ZIP_SIZE="UNKNOWN"
ZIP_SHA256="UNKNOWN"
ZIP_TOP_FOLDER="UNKNOWN"
ZIP_TOP_COUNT="0"

FORBIDDEN_FILES_STATUS="NOT_SCANNED"

PLUGIN_CHECK_EXIT_CODE="N/A"
PLUGIN_CHECK_ERROR_COUNT="N/A"
PLUGIN_CHECK_WARNING_COUNT="N/A"

ACTIVATION_STATUS="NOT_RUN"
DEBUG_LOG_STATUS="NOT_CHECKED"

cleanup() {
  rm -rf "${TMP_DIR}"
}

print_summary() {
  printf '\n%s\n' "=== VERIFY RELEASE SUMMARY ==="
  printf '%s\n' "ZIP path: ${ZIP_PATH}"
  printf '%s\n' "ZIP size: ${ZIP_SIZE}"
  printf '%s\n' "SHA256: ${ZIP_SHA256}"
  printf '%s\n' "Top-level folder: ${ZIP_TOP_FOLDER}"
  printf '%s\n' "Top-level folder count: ${ZIP_TOP_COUNT}"
  printf '%s\n' "Found forbidden files: ${FORBIDDEN_FILES_STATUS}"
  if [[ -s "${FORBIDDEN_FILE}" ]]; then
    cat "${FORBIDDEN_FILE}"
  fi
  printf '%s\n' "Plugin dir mount status: ${PLUGIN_DIR_MOUNT_STATUS}"
  printf '%s\n' "Plugin Check exit: ${PLUGIN_CHECK_EXIT_CODE}"
  printf '%s\n' "Plugin Check errors: ${PLUGIN_CHECK_ERROR_COUNT}"
  printf '%s\n' "Plugin Check warnings: ${PLUGIN_CHECK_WARNING_COUNT}"
  printf '%s\n' "Activation: ${ACTIVATION_STATUS}"
  printf '%s\n' "Debug log: ${DEBUG_LOG_STATUS}"
  if [[ -n "${FAIL_REASON}" ]]; then
    printf '%s\n' "Failure reason: ${FAIL_REASON}"
  fi
  printf '%s\n' "READY_FOR_UPLOAD=${READY_FOR_UPLOAD}"
  SUMMARY_PRINTED=1
}

on_exit() {
  local rc=$?
  trap - EXIT
  if [[ "${SUMMARY_PRINTED}" -eq 0 ]]; then
    if [[ "${rc}" -eq 0 ]]; then
      READY_FOR_UPLOAD="YES"
    else
      READY_FOR_UPLOAD="NO"
    fi
    print_summary
  fi
  cleanup
  exit "${rc}"
}
trap on_exit EXIT

fail() {
  FAIL_REASON="$*"
  READY_FOR_UPLOAD="NO"
  printf '%s\n' "ERROR: $*" >&2
  exit 1
}

need_cmd() {
  command -v "$1" >/dev/null 2>&1 || fail "Missing required command: $1"
}

docker_exec_sh() {
  local cmd="$1"
  docker exec "${CID}" sh -lc "${cmd}"
}

docker_exec_sh_capture() {
  local outfile="$1"
  shift
  local cmd="$*"
  docker exec "${CID}" sh -lc "${cmd}" > "${outfile}" 2>&1
}

wp_cli() {
  if [[ "${WPCLI_MODE}" == "binary" ]]; then
    docker exec "${CID}" wp --allow-root --path="${WP_ROOT}" "$@"
  else
    docker exec "${CID}" php "${WPCLI_PHAR}" --allow-root --path="${WP_ROOT}" "$@"
  fi
}

ensure_zip_metadata() {
  [[ -f "${ZIP_PATH}" ]] || fail "ZIP not found: ${ZIP_PATH}"

  ls -lh "${ZIP_PATH}"

  ZIP_SHA256="$(shasum -a 256 "${ZIP_PATH}" | awk '{print $1}')"
  ZIP_SIZE="$(ls -lh "${ZIP_PATH}" | awk '{print $5}')"
  printf '%s\n' "ZIP SHA256: ${ZIP_SHA256}"
}

scan_zip_contents() {
  local forbidden_re
  forbidden_re='\.md$|\.log$|(^|[[:space:]])scripts/|/scripts/|(^|[[:space:]])tests/|/tests/|(^|[[:space:]])tools/|/tools/|\.git|\.github|\.claude|(^|[[:space:]])dist/|/dist/|node_modules/|vendor/bin/|\.DS_Store|Thumbs\.db|\.sh$'

  unzip -l "${ZIP_PATH}" > "${ZIP_LISTING_FILE}" || fail "Unable to list ZIP contents: ${ZIP_PATH}"
  unzip -Z1 "${ZIP_PATH}" > "${ZIP_ENTRIES_FILE}" || fail "Unable to read ZIP entries: ${ZIP_PATH}"

  grep -Ei "${forbidden_re}" "${ZIP_LISTING_FILE}" > "${FORBIDDEN_FILE}" || true

  if [[ -s "${FORBIDDEN_FILE}" ]]; then
    FORBIDDEN_FILES_STATUS="FOUND"
    fail "Forbidden files found in ZIP contents."
  fi

  FORBIDDEN_FILES_STATUS="NONE"
}

validate_zip_structure() {
  local tops

  tops="$(awk -F/ 'NF { print $1 }' "${ZIP_ENTRIES_FILE}" | sort -u)"
  ZIP_TOP_COUNT="$(printf '%s\n' "${tops}" | awk 'NF { c++ } END { print c + 0 }')"
  ZIP_TOP_FOLDER="$(printf '%s\n' "${tops}" | head -n 1)"

  if [[ "${ZIP_TOP_COUNT}" != "1" || "${ZIP_TOP_FOLDER}" != "${PLUGIN_SLUG}" ]]; then
    fail "ZIP must contain exactly one top-level folder named ${PLUGIN_SLUG}."
  fi
}

find_wp_container() {
  local candidate
  while IFS= read -r candidate; do
    [[ -n "${candidate}" ]] || continue
    if docker exec "${candidate}" sh -lc "test -f '${WP_ROOT}/wp-config.php'" >/dev/null 2>&1; then
      CID="${candidate}"
      return 0
    fi
  done < <(docker ps -q)

  fail "No running WordPress container found with ${WP_ROOT}/wp-config.php present."
}

check_plugin_mount_status() {
  local mount_check
  mount_check="$(docker_exec_sh "
p='${PLUGIN_DIR}'
if [ -d \"\$p\" ] && grep -F \" \$p \" /proc/self/mountinfo >/dev/null 2>&1; then
  echo MOUNTED
else
  echo NOT_MOUNTED
fi
" | tr -d '\r')"

  if [[ "${mount_check}" == "MOUNTED" ]]; then
    PLUGIN_DIR_MOUNT_STATUS="MOUNTED"
  else
    PLUGIN_DIR_MOUNT_STATUS="NOT_MOUNTED"
  fi
}

parse_helper_summary_line() {
  local line="$1"
  local field="$2"
  printf '%s\n' "${line}" | sed -E "s/.*${field}=([^, ]+).*/\\1/"
}

extract_helper_ready_for_upload() {
  local log_file="$1"
  awk '
    /^### READY_FOR_UPLOAD$/ { in_block=1; next }
    in_block && /^- / { gsub(/^- /, "", $0); print $0; exit }
  ' "${log_file}"
}

run_disposable_helper_fallback() {
  local summary_line
  local activate_line
  local debug_line
  local ready_line
  local helper_rc

  [[ -x "${DISPOSABLE_HELPER_SCRIPT}" ]] || fail "Disposable helper not found/executable: ${DISPOSABLE_HELPER_SCRIPT}"

  printf '%s\n' "Plugin dir is bind-mounted; plugin-check will scan repo files not ZIP." >&2
  printf '%s\n' "Falling back to disposable non-mounted WordPress validation via ${DISPOSABLE_HELPER_SCRIPT}" >&2

  set +e
  ZIP_PATH="${ZIP_PATH}" "${DISPOSABLE_HELPER_SCRIPT}" | tee "${DISPOSABLE_HELPER_LOG}"
  helper_rc=${PIPESTATUS[0]}
  set -e

  summary_line="$(grep -E '^- Plugin Check: exit code=' "${DISPOSABLE_HELPER_LOG}" | tail -n 1 || true)"
  activate_line="$(grep -E '^- Install/Activate:' "${DISPOSABLE_HELPER_LOG}" | tail -n 1 || true)"
  debug_line="$(grep -E '^- debug\.log:' "${DISPOSABLE_HELPER_LOG}" | tail -n 1 || true)"
  ready_line="$(extract_helper_ready_for_upload "${DISPOSABLE_HELPER_LOG}" || true)"

  [[ -n "${summary_line}" ]] || fail "Disposable helper did not produce Plugin Check summary."
  [[ -n "${activate_line}" ]] || fail "Disposable helper did not produce Install/Activate summary."

  PLUGIN_DIR_MOUNT_STATUS="NOT_MOUNTED (disposable helper fallback)"
  PLUGIN_CHECK_EXIT_CODE="$(parse_helper_summary_line "${summary_line}" 'exit code')"
  PLUGIN_CHECK_ERROR_COUNT="$(parse_helper_summary_line "${summary_line}" 'error count')"
  PLUGIN_CHECK_WARNING_COUNT="$(parse_helper_summary_line "${summary_line}" 'warning count')"
  ACTIVATION_STATUS="$(printf '%s\n' "${activate_line}" | sed -E 's/^- Install\/Activate: *//')"
  if [[ -n "${debug_line}" ]]; then
    DEBUG_LOG_STATUS="$(printf '%s\n' "${debug_line}" | sed -E 's/^- debug\.log: *//')"
  fi

  if [[ "${helper_rc}" -ne 0 ]]; then
    fail "Disposable helper validation failed (exit ${helper_rc})."
  fi
  [[ "${PLUGIN_CHECK_ERROR_COUNT}" == "0" ]] || fail "Disposable helper reported Plugin Check errors (${PLUGIN_CHECK_ERROR_COUNT})."
  [[ "${ACTIVATION_STATUS}" == "PASS" ]] || fail "Disposable helper activation failed (${ACTIVATION_STATUS})."
  if [[ -n "${ready_line}" && "${ready_line}" != "YES" ]]; then
    fail "Disposable helper READY_FOR_UPLOAD=${ready_line}."
  fi

  READY_FOR_UPLOAD="YES"
}

ensure_wp_cli() {
  if docker_exec_sh "command -v wp >/dev/null 2>&1"; then
    WPCLI_MODE="binary"
    return 0
  fi

  docker_exec_sh "
set -e
if [ -f '${WPCLI_PHAR}' ]; then
  php '${WPCLI_PHAR}' --info >/dev/null 2>&1 && exit 0
  rm -f '${WPCLI_PHAR}'
fi
if command -v curl >/dev/null 2>&1; then
  curl -fsSL -o '${WPCLI_PHAR}' 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar'
elif command -v wget >/dev/null 2>&1; then
  wget -q -O '${WPCLI_PHAR}' 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar'
else
  echo 'Neither curl nor wget is available in the container.' >&2
  exit 1
fi
php '${WPCLI_PHAR}' --info >/dev/null
" >/dev/null

  WPCLI_MODE="phar"
}

prepare_debug_log() {
  docker_exec_sh "
mkdir -p '${WP_ROOT}/wp-content'
rm -f '${DEBUG_LOG_PATH}' || true
touch '${DEBUG_LOG_PATH}' 2>/dev/null || true
"
}

hard_reinstall_plugin_from_zip() {
  set +e
  wp_cli plugin deactivate "${PLUGIN_SLUG}" >/dev/null 2>&1
  wp_cli plugin uninstall "${PLUGIN_SLUG}" --deactivate --skip-delete >/dev/null 2>&1
  set -e

  docker_exec_sh "rm -rf '${PLUGIN_DIR}'"

  docker cp "${ZIP_PATH}" "${CID}:${ZIP_IN_CONTAINER}"

  if wp_cli plugin install "${ZIP_IN_CONTAINER}" --force --activate > "${WP_INSTALL_OUT}" 2>&1; then
    ACTIVATION_STATUS="PASS"
  else
    ACTIVATION_STATUS="FAIL"
    cat "${WP_INSTALL_OUT}" >&2 || true
    fail "Plugin install/activate from ZIP failed."
  fi
}

run_plugin_check() {
  if ! wp_cli plugin install plugin-check --activate --force > "${WP_PLUGIN_CHECK_INSTALL_OUT}" 2>&1; then
    cat "${WP_PLUGIN_CHECK_INSTALL_OUT}" >&2 || true
    fail "Failed to install/activate plugin-check plugin."
  fi

  set +e
  wp_cli plugin check "${PLUGIN_SLUG}" --format=table > "${PLUGIN_CHECK_OUT}" 2>&1
  PLUGIN_CHECK_EXIT_CODE="$?"
  set -e

  cat "${PLUGIN_CHECK_OUT}"

  PLUGIN_CHECK_ERROR_COUNT="$(grep -E -c '(^|\|)[[:space:]]*ERROR[[:space:]]*(\||$)' "${PLUGIN_CHECK_OUT}" || true)"
  PLUGIN_CHECK_WARNING_COUNT="$(grep -E -c '(^|\|)[[:space:]]*WARNING[[:space:]]*(\||$)' "${PLUGIN_CHECK_OUT}" || true)"
  PLUGIN_CHECK_ERROR_COUNT="${PLUGIN_CHECK_ERROR_COUNT:-0}"
  PLUGIN_CHECK_WARNING_COUNT="${PLUGIN_CHECK_WARNING_COUNT:-0}"

  if [[ "${PLUGIN_CHECK_ERROR_COUNT}" != "0" ]]; then
    fail "Plugin Check reported ${PLUGIN_CHECK_ERROR_COUNT} error(s)."
  fi

  if [[ "${PLUGIN_CHECK_EXIT_CODE}" != "0" && "${PLUGIN_CHECK_EXIT_CODE}" != "1" ]]; then
    fail "wp plugin check exited with unexpected code ${PLUGIN_CHECK_EXIT_CODE}."
  fi
}

check_debug_log() {
  if ! docker_exec_sh "test -f '${DEBUG_LOG_PATH}'"; then
    DEBUG_LOG_STATUS="CLEAN (no debug.log)"
    return 0
  fi

  docker_exec_sh "tail -n 200 '${DEBUG_LOG_PATH}' 2>/dev/null || true" > "${DEBUG_LOG_TAIL}" || true

  if grep -Eiq 'Fatal error|Uncaught Error|Warning' "${DEBUG_LOG_TAIL}"; then
    DEBUG_LOG_STATUS="ISSUES"
    fail "debug.log contains fatal/uncaught/warning entries."
  fi

  DEBUG_LOG_STATUS="CLEAN"
}

need_cmd docker
need_cmd unzip
need_cmd grep
need_cmd awk
need_cmd shasum
need_cmd ls
need_cmd docker
need_cmd tee
need_cmd sed

ensure_zip_metadata
scan_zip_contents
validate_zip_structure
find_wp_container
check_plugin_mount_status
if [[ "${PLUGIN_DIR_MOUNT_STATUS}" == "MOUNTED" ]]; then
  if [[ "${VERIFY_DISPOSABLE_ON_MOUNT}" == "1" ]]; then
    run_disposable_helper_fallback
    print_summary
    exit 0
  fi
  fail "Plugin dir is bind-mounted; plugin-check will scan repo files not ZIP."
fi
ensure_wp_cli
prepare_debug_log
hard_reinstall_plugin_from_zip
run_plugin_check
check_debug_log

READY_FOR_UPLOAD="YES"
print_summary
