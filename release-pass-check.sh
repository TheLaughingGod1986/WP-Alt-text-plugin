#!/usr/bin/env bash
set -euo pipefail

SLUG="beepbeep-ai-alt-text-generator"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

ZIP_BASENAME="${SLUG}.zip"
ZIP_PATH_INPUT="${ZIP_PATH:-}"

NETWORK_NAME="${NETWORK_NAME:-wp-plugin-pass-net}"
DB_CONTAINER="${DB_CONTAINER:-wp-plugin-pass-db}"
WP_CONTAINER="${WP_CONTAINER:-wp-plugin-pass-test}"
DB_IMAGE="${DB_IMAGE:-mariadb:11}"
WP_IMAGE="${WP_IMAGE:-wordpress:latest}"

DB_NAME="${DB_NAME:-wordpress}"
DB_USER="${DB_USER:-wordpress}"
DB_PASSWORD="${DB_PASSWORD:-wordpress}"
DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-rootpass}"

WP_ADMIN_USER="${WP_ADMIN_USER:-admin}"
WP_ADMIN_PASSWORD="${WP_ADMIN_PASSWORD:-password}"
WP_ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@example.com}"
WP_SITE_TITLE="${WP_SITE_TITLE:-Plugin Pass Test}"
WP_HOST_BIND="${WP_HOST_BIND:-127.0.0.1}"
WP_PORT="${WP_PORT:-}"

WP_CONTENT_PATH="/var/www/html"
WP_PLUGIN_DIR="${WP_CONTENT_PATH}/wp-content/plugins/${SLUG}"
WP_ZIP_TMP="/tmp/${SLUG}.zip"
WPCLI_PHAR="/tmp/wp-cli.phar"

WORK_DIR="$(mktemp -d "${TMPDIR:-/tmp}/release-pass-check.XXXXXX")"
DETAILS_FILE="${WORK_DIR}/details.txt"
PLUGIN_CHECK_OUT="${WORK_DIR}/plugin-check.out"
PLUGIN_CHECK_WARNINGS_TOP="${WORK_DIR}/plugin-check-warnings-top20.txt"
DEBUG_LOG_TAIL="${WORK_DIR}/debug-tail.txt"
REDFLAG_OUT="${WORK_DIR}/redflags.txt"
REMOTE_SCAN_OUT="${WORK_DIR}/remote-scan.txt"
SECURITY_SCAN_OUT="${WORK_DIR}/security-scan.txt"
PRECHECK_ZIP_LIST="${WORK_DIR}/zip-list.txt"

READY_FOR_UPLOAD="YES"

PLUGIN_CHECK_EXIT_CODE="N/A"
PLUGIN_CHECK_ERROR_COUNT="N/A"
PLUGIN_CHECK_WARNING_COUNT="N/A"
INSTALL_ACTIVATE_STATUS="FAIL"
REINSTALL_CYCLE_STATUS="FAIL"
DEBUG_LOG_STATUS="ISSUES"
REDFLAGS_STATUS="ISSUES"

FAIL_REASON=""
FAIL_ROOT_CAUSE=""
FAIL_MINIMAL_FIX=""
FAIL_COMMAND=""
FAIL_OUTPUT_FILE=""

declare -a RERUN_COMMANDS=()

append_detail() {
  printf '%s\n' "$*" >> "${DETAILS_FILE}"
}

append_detail_file() {
  local label="$1"
  local file="$2"
  append_detail "${label}"
  if [[ -f "${file}" ]]; then
    while IFS= read -r line; do
      append_detail "${line}"
    done < "${file}"
  else
    append_detail "(missing file: ${file})"
  fi
}

section() {
  printf '\n========== %s ==========\n' "$1"
}

info() {
  printf '[INFO] %s\n' "$*"
}

warn() {
  printf '[WARN] %s\n' "$*" >&2
}

record_failure() {
  local reason="$1"
  local command="$2"
  local output_file="${3:-}"
  local root_cause="$4"
  local minimal_fix="$5"
  READY_FOR_UPLOAD="NO"
  FAIL_REASON="${reason}"
  FAIL_COMMAND="${command}"
  FAIL_OUTPUT_FILE="${output_file}"
  FAIL_ROOT_CAUSE="${root_cause}"
  FAIL_MINIMAL_FIX="${minimal_fix}"
}

run_capture() {
  local outfile="$1"
  shift
  set +e
  "$@" > "${outfile}" 2>&1
  local rc=$?
  set -e
  return "${rc}"
}

docker_exec_sh_capture() {
  local outfile="$1"
  shift
  local cmd="$*"
  run_capture "${outfile}" docker exec "${WP_CONTAINER}" sh -lc "${cmd}"
}

db_exec_sh_capture() {
  local outfile="$1"
  shift
  local cmd="$*"
  run_capture "${outfile}" docker exec "${DB_CONTAINER}" sh -lc "${cmd}"
}

wp_cli_capture() {
  local outfile="$1"
  shift
  run_capture "${outfile}" docker exec \
    -e "WP_CLI_PHP_ARGS=-d error_reporting=E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED" \
    "${WP_CONTAINER}" php "${WPCLI_PHAR}" \
    --allow-root \
    --path="${WP_CONTENT_PATH}" \
    "$@"
}

cleanup_docker() {
  docker rm -f "${WP_CONTAINER}" >/dev/null 2>&1 || true
  docker rm -f "${DB_CONTAINER}" >/dev/null 2>&1 || true
  docker network rm "${NETWORK_NAME}" >/dev/null 2>&1 || true
}

cleanup() {
  cleanup_docker
  rm -rf "${WORK_DIR}"
}

trap cleanup EXIT

find_free_port() {
  local start="${1:-8089}"
  local end="${2:-8120}"
  local p
  for p in $(seq "${start}" "${end}"); do
    if command -v lsof >/dev/null 2>&1; then
      if lsof -nP -iTCP:"${p}" -sTCP:LISTEN >/dev/null 2>&1; then
        continue
      fi
      printf '%s\n' "${p}"
      return 0
    fi
    if command -v nc >/dev/null 2>&1; then
      if nc -z "${WP_HOST_BIND}" "${p}" >/dev/null 2>&1; then
        continue
      fi
      printf '%s\n' "${p}"
      return 0
    fi
    # Fallback: first candidate.
    printf '%s\n' "${p}"
    return 0
  done
  return 1
}

locate_zip() {
  local candidates=()
  if [[ -n "${ZIP_PATH_INPUT}" ]]; then
    candidates+=("${ZIP_PATH_INPUT}")
  fi

  candidates+=(
    "${SCRIPT_DIR}/../plugin-builds/${ZIP_BASENAME}"
    "${SCRIPT_DIR}/plugin-builds/${ZIP_BASENAME}"
    "${SCRIPT_DIR}/${ZIP_BASENAME}"
    "./plugin-builds/${ZIP_BASENAME}"
    "./${ZIP_BASENAME}"
  )

  local c
  for c in "${candidates[@]}"; do
    if [[ -f "${c}" ]]; then
      python3 - <<'PY' "${c}"
import os, sys
print(os.path.abspath(sys.argv[1]))
PY
      return 0
    fi
  done
  return 1
}

verify_zip_structure() {
  local zip="$1"
  if ! run_capture "${PRECHECK_ZIP_LIST}" unzip -Z1 "${zip}"; then
    record_failure \
      "ZIP preflight failed (unable to list archive)" \
      "unzip -Z1 ${zip}" \
      "${PRECHECK_ZIP_LIST}" \
      "The ZIP is missing, corrupted, or unreadable." \
      "Rebuild the distributable ZIP and ensure it is a valid archive."
    return 1
  fi

  local top_count top_name
  top_count="$(awk -F/ 'NF{print $1}' "${PRECHECK_ZIP_LIST}" | sort -u | wc -l | tr -d ' ')"
  top_name="$(awk -F/ 'NF{print $1}' "${PRECHECK_ZIP_LIST}" | sort -u | head -n1)"
  if [[ "${top_count}" != "1" || "${top_name}" != "${SLUG}" ]]; then
    record_failure \
      "ZIP preflight failed (top-level folder mismatch)" \
      "unzip -Z1 ${zip}" \
      "${PRECHECK_ZIP_LIST}" \
      "The ZIP does not contain exactly one top-level plugin folder named ${SLUG}/." \
      "Rebuild the ZIP so all files are rooted under ${SLUG}/ only."
    return 1
  fi

  if grep -Ei '\.(zip|tar|tgz|gz|bz2|xz|7z)$' "${PRECHECK_ZIP_LIST}" >/dev/null; then
    record_failure \
      "ZIP preflight failed (nested archive detected)" \
      "unzip -Z1 ${zip} | grep -Ei '\\.(zip|tar|tgz|gz|bz2|xz|7z)$'" \
      "${PRECHECK_ZIP_LIST}" \
      "The plugin ZIP contains nested compressed artifacts, which are review-sensitive." \
      "Remove nested archives from the distributable package and rebuild."
    return 1
  fi

  append_detail "ZIP preflight: PASS (${zip})"
  return 0
}

wait_for_mariadb() {
  local attempts=60
  local i out="${WORK_DIR}/db-ping.txt"
  for i in $(seq 1 "${attempts}"); do
    if db_exec_sh_capture "${out}" "mariadb-admin ping -h 127.0.0.1 -uroot -p\"${DB_ROOT_PASSWORD}\" --silent"; then
      return 0
    fi
    sleep 2
  done
  append_detail_file "MariaDB ping output (last attempt):" "${out}"
  return 1
}

wait_for_wordpress_http() {
  local url="$1"
  local attempts=90
  local i out="${WORK_DIR}/wp-http.txt"
  for i in $(seq 1 "${attempts}"); do
    if run_capture "${out}" curl -fsS -o /dev/null "${url}"; then
      return 0
    fi
    sleep 2
  done
  append_detail_file "WordPress HTTP readiness output (last attempt):" "${out}"
  return 1
}

install_wp_cli_in_container() {
  local out="${WORK_DIR}/wpcli-install.txt"
  local phar_url="https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar"
  local cmd="
set -e
if command -v curl >/dev/null 2>&1; then
  curl -fsSL -o '${WPCLI_PHAR}' '${phar_url}'
elif command -v wget >/dev/null 2>&1; then
  wget -q -O '${WPCLI_PHAR}' '${phar_url}'
else
  echo 'Neither curl nor wget is available in the WordPress container.'
  exit 1
fi
php '${WPCLI_PHAR}' --info
"
  if ! docker_exec_sh_capture "${out}" "${cmd}"; then
    record_failure \
      "WP-CLI installation inside container failed" \
      "docker exec ${WP_CONTAINER} sh -lc '<download wp-cli.phar && php /tmp/wp-cli.phar --info>'" \
      "${out}" \
      "The disposable WordPress container could not download or run WP-CLI (network/tooling issue)." \
      "Ensure the container has outbound network access and curl or wget is available, then rerun."
    return 1
  fi
  append_detail_file "WP-CLI install/info output:" "${out}"
  return 0
}

wp_cli_ok_or_fail() {
  local reason="$1"
  local root_cause="$2"
  local minimal_fix="$3"
  local outfile="$4"
  shift 4
  if ! wp_cli_capture "${outfile}" "$@"; then
    record_failure "${reason}" "wp ${*}" "${outfile}" "${root_cause}" "${minimal_fix}"
    return 1
  fi
  return 0
}

install_or_setup_wordpress() {
  local out="${WORK_DIR}/wp-core-check.txt"
  if wp_cli_capture "${out}" core is-installed; then
    append_detail "WordPress core is already installed in disposable container."
    return 0
  fi

  local cfg_out="${WORK_DIR}/wp-config-perms.txt"
  docker_exec_sh_capture "${cfg_out}" "test -w '${WP_CONTENT_PATH}' && echo 'wp_root_writable' || (ls -ld '${WP_CONTENT_PATH}'; exit 1)" || true
  append_detail_file "WordPress path write check:" "${cfg_out}"

  if ! wp_cli_ok_or_fail \
      "WordPress core install failed" \
      "The WordPress container is up, but wp core install could not complete (DB not ready, invalid DB env, or wp-config issue)." \
      "Confirm DB credentials/env variables and container readiness, then rerun the script." \
      "${WORK_DIR}/wp-core-install.txt" \
      core install \
      --url="http://localhost:${WP_PORT}" \
      --title="${WP_SITE_TITLE}" \
      --admin_user="${WP_ADMIN_USER}" \
      --admin_password="${WP_ADMIN_PASSWORD}" \
      --admin_email="${WP_ADMIN_EMAIL}"; then
    return 1
  fi
  append_detail_file "WordPress core install output:" "${WORK_DIR}/wp-core-install.txt"
  return 0
}

ensure_not_mountpoint() {
  local out="${WORK_DIR}/mountpoint-check.txt"
  local cmd="
set -e
p='${WP_PLUGIN_DIR}'
if [ ! -d \"\$p\" ]; then
  echo 'plugin_dir_missing'
  exit 2
fi
if grep -F \" \$p \" /proc/self/mountinfo >/dev/null 2>&1; then
  echo \"MOUNTPOINT: \$p\"
  exit 1
fi
echo \"NOT_MOUNTPOINT: \$p\"
"
  if ! docker_exec_sh_capture "${out}" "${cmd}"; then
    record_failure \
      "Plugin directory mountpoint check failed" \
      "docker exec ${WP_CONTAINER} sh -lc 'grep /proc/self/mountinfo for ${WP_PLUGIN_DIR}'" \
      "${out}" \
      "The plugin directory is a mountpoint (or the check failed), which can break delete/uninstall tests under virtiofs." \
      "Use the disposable container without bind-mounting the plugin directory, then rerun."
    return 1
  fi
  append_detail_file "Mountpoint check output:" "${out}"
  return 0
}

toggle_debug_logging() {
  local out="${WORK_DIR}/debug-config.txt"
  : > "${out}"
  if ! wp_cli_capture "${WORK_DIR}/debug-config-1.txt" config set WP_DEBUG true --raw --type=constant; then
    cat "${WORK_DIR}/debug-config-1.txt" >> "${out}"
    record_failure \
      "Failed to enable WP_DEBUG in wp-config.php" \
      "wp config set WP_DEBUG true --raw --type=constant" \
      "${WORK_DIR}/debug-config-1.txt" \
      "wp-config.php could not be modified by WP-CLI in the disposable environment." \
      "Ensure wp-config.php is writable in the container, or preconfigure debug constants before rerunning."
    return 1
  fi
  cat "${WORK_DIR}/debug-config-1.txt" >> "${out}"
  if ! wp_cli_capture "${WORK_DIR}/debug-config-2.txt" config set WP_DEBUG_LOG true --raw --type=constant; then
    cat "${WORK_DIR}/debug-config-2.txt" >> "${out}"
    record_failure \
      "Failed to enable WP_DEBUG_LOG in wp-config.php" \
      "wp config set WP_DEBUG_LOG true --raw --type=constant" \
      "${WORK_DIR}/debug-config-2.txt" \
      "wp-config.php could not be modified by WP-CLI in the disposable environment." \
      "Ensure wp-config.php is writable in the container, or preconfigure debug constants before rerunning."
    return 1
  fi
  cat "${WORK_DIR}/debug-config-2.txt" >> "${out}"
  if ! wp_cli_capture "${WORK_DIR}/debug-config-3.txt" config set WP_DEBUG_DISPLAY false --raw --type=constant; then
    cat "${WORK_DIR}/debug-config-3.txt" >> "${out}"
    record_failure \
      "Failed to disable WP_DEBUG_DISPLAY in wp-config.php" \
      "wp config set WP_DEBUG_DISPLAY false --raw --type=constant" \
      "${WORK_DIR}/debug-config-3.txt" \
      "wp-config.php could not be modified by WP-CLI in the disposable environment." \
      "Ensure wp-config.php is writable in the container, or preconfigure debug constants before rerunning."
    return 1
  fi
  cat "${WORK_DIR}/debug-config-3.txt" >> "${out}"
  append_detail_file "wp-config debug constant updates:" "${out}"
  return 0
}

run_runtime_health_checks() {
  local ok=0
  local out="${WORK_DIR}/runtime-health.txt"
  : > "${out}"

  if ! wp_cli_capture "${WORK_DIR}/runtime-home.txt" option get home; then
    cat "${WORK_DIR}/runtime-home.txt" >> "${out}"
    record_failure \
      "Runtime health check failed (wp option get home)" \
      "wp option get home" \
      "${WORK_DIR}/runtime-home.txt" \
      "WordPress CLI is unhealthy after plugin activation (bootstrap/runtime issue)." \
      "Inspect the command output and debug log, fix the runtime error, then rerun."
    return 1
  fi
  cat "${WORK_DIR}/runtime-home.txt" >> "${out}"

  if ! wp_cli_capture "${WORK_DIR}/runtime-admin-init.txt" --user="${WP_ADMIN_USER}" eval 'if ( function_exists("set_current_screen") ) { set_current_screen("dashboard"); } do_action("admin_init"); echo "admin_init_ok\n";'; then
    cat "${WORK_DIR}/runtime-admin-init.txt" >> "${out}"
    record_failure \
      "Runtime health check failed (admin_init)" \
      "wp --user=${WP_ADMIN_USER} eval 'if ( function_exists(\"set_current_screen\") ) { set_current_screen(\"dashboard\"); } do_action(\"admin_init\"); echo \"admin_init_ok\";'" \
      "${WORK_DIR}/runtime-admin-init.txt" \
      "A plugin callback is erroring during admin_init." \
      "Review the failing stack trace/output, patch the callback, and rerun."
    return 1
  fi
  cat "${WORK_DIR}/runtime-admin-init.txt" >> "${out}"

  if ! wp_cli_capture "${WORK_DIR}/runtime-admin-hooks.txt" --user="${WP_ADMIN_USER}" eval '$_GET["page"]="bbai"; if ( function_exists("set_current_screen") ) { set_current_screen("toplevel_page_bbai"); } do_action("admin_menu"); do_action("admin_enqueue_scripts","toplevel_page_bbai"); echo "bbai_admin_hooks_ok\n";'; then
    cat "${WORK_DIR}/runtime-admin-hooks.txt" >> "${out}"
    record_failure \
      "Runtime health check failed (plugin admin hooks)" \
      "wp --user=${WP_ADMIN_USER} eval '...\ndo_action(\"admin_menu\"); do_action(\"admin_enqueue_scripts\",\"toplevel_page_bbai\");'" \
      "${WORK_DIR}/runtime-admin-hooks.txt" \
      "The plugin admin page registration/enqueue flow failed under CLI-triggered admin hooks." \
      "Inspect the output and debug log, patch the failing hook callback, then rerun."
    return 1
  fi
  cat "${WORK_DIR}/runtime-admin-hooks.txt" >> "${out}"

  append_detail_file "Runtime health command output:" "${out}"
  ok=1
  return 0
}

collect_debug_log_status() {
  local out="${DEBUG_LOG_TAIL}"
  local cmd="
if [ -f '${WP_CONTENT_PATH}/wp-content/debug.log' ]; then
  tail -n 200 '${WP_CONTENT_PATH}/wp-content/debug.log'
else
  echo '__NO_DEBUG_LOG__'
fi
"
  if ! docker_exec_sh_capture "${out}" "${cmd}"; then
    record_failure \
      "Failed to inspect wp-content/debug.log" \
      "docker exec ${WP_CONTAINER} sh -lc 'tail -n 200 wp-content/debug.log'" \
      "${out}" \
      "The disposable container could not read debug.log after runtime checks." \
      "Verify filesystem permissions inside the container and rerun."
    return 1
  fi

  local fatal_count warn_count notice_count repeated_warn_count
  fatal_count="$(grep -Eic 'PHP Fatal error|Fatal error:|Uncaught ' "${out}" || true)"
  warn_count="$(grep -Eic 'PHP Warning| Warning:' "${out}" || true)"
  notice_count="$(grep -Eic 'PHP Notice| Notice:' "${out}" || true)"
  repeated_warn_count="$(
    grep -Ei 'PHP Warning| Warning:|PHP Notice| Notice:' "${out}" 2>/dev/null | \
    sed -E 's/^\\[[^]]+\\] //' | \
    sort | uniq -cd | awk '$1 >= 2 {c++} END{print c+0}'
  )"

  if [[ "${fatal_count}" != "0" || "${warn_count}" != "0" || "${notice_count}" != "0" ]]; then
    DEBUG_LOG_STATUS="ISSUES"
    record_failure \
      "Runtime health failed (debug.log contains fatal/warning/notice entries)" \
      "tail -n 200 wp-content/debug.log" \
      "${out}" \
      "Plugin activation/runtime hooks emitted PHP fatals, warnings, or notices in debug.log." \
      "Fix the logged runtime issue(s), then rerun the smoke test and confirm debug.log is clean."
    append_detail "debug.log counters: fatals=${fatal_count} warnings=${warn_count} notices=${notice_count} repeated_warning_notice_groups=${repeated_warn_count}"
    return 1
  fi

  DEBUG_LOG_STATUS="CLEAN"
  append_detail "debug.log counters: fatals=${fatal_count} warnings=${warn_count} notices=${notice_count} repeated_warning_notice_groups=${repeated_warn_count}"
  return 0
}

run_redflag_scans() {
  local dangerous_pat='(^|[^[:alnum:]_])(eval|gzinflate|shell_exec|exec|system|passthru)[[:space:]]*\('
  local out1="${REDFLAG_OUT}"
  local out2="${REMOTE_SCAN_OUT}"
  local out3="${SECURITY_SCAN_OUT}"
  : > "${out1}"
  : > "${out2}"
  : > "${out3}"

  docker_exec_sh_capture "${out1}" "
grep -RInE --include='*.php' '${dangerous_pat}' '${WP_PLUGIN_DIR}' | \
grep -vE ':[0-9]+:[[:space:]]*(//|/\\*)' || true
" 
  if [[ -s "${out1}" ]]; then
    REDFLAGS_STATUS="ISSUES"
    record_failure \
      "Red flags scan found dangerous PHP function usage" \
      "grep -RInE '${dangerous_pat}' ${WP_PLUGIN_DIR}" \
      "${out1}" \
      "The plugin contains obvious review-sensitive patterns (eval/exec/base64_decode/etc.)." \
      "Remove the flagged pattern(s) or justify/replace them with safe WordPress APIs, then rerun."
    return 1
  fi

  docker_exec_sh_capture "${out2}" "
set -e
grep -RInE --include='*.php' 'wp_remote_(get|post|request)[[:space:]]*\(' '${WP_PLUGIN_DIR}' > /tmp/wp-remote-calls.txt || true
if [ ! -s /tmp/wp-remote-calls.txt ]; then
  echo 'NO_REMOTE_CALLS'
  exit 0
fi
echo 'REMOTE_CALLS:'
cat /tmp/wp-remote-calls.txt
echo
echo 'POTENTIAL_MISSING_TIMEOUTS:'
while IFS=: read -r file line _; do
  start=\"\$line\"
  end=\$(( line + 12 ))
  if ! sed -n \"\${start},\${end}p\" \"\$file\" | grep -q 'timeout'; then
    echo \"\$file:\$line\"
  fi
done < /tmp/wp-remote-calls.txt
" || true

  if grep -q '^POTENTIAL_MISSING_TIMEOUTS:$' "${out2}" && \
     awk 'f{if(length) print} /^POTENTIAL_MISSING_TIMEOUTS:/{f=1; next}' "${out2}" | grep -q .; then
    REDFLAGS_STATUS="ISSUES"
    record_failure \
      "Remote request timeout scan found potential missing timeouts" \
      "grep wp_remote_* + context timeout check in ${WP_PLUGIN_DIR}" \
      "${out2}" \
      "One or more WP HTTP API calls may be missing explicit timeout arguments near the call site." \
      "Add explicit timeout values to the flagged wp_remote_* calls, then rerun."
    return 1
  fi

  docker_exec_sh_capture "${out3}" "
set -e
echo 'NONCE_CHECKS:' 
grep -RInE --include='*.php' 'check_admin_referer|check_ajax_referer|wp_verify_nonce' '${WP_PLUGIN_DIR}/admin' '${WP_PLUGIN_DIR}/includes' || true
echo
echo 'CAPABILITY_CHECKS:'
grep -RInE --include='*.php' 'current_user_can[[:space:]]*\(' '${WP_PLUGIN_DIR}/admin' '${WP_PLUGIN_DIR}/includes' || true
" || true

  local nonce_count cap_count
  nonce_count="$(grep -Eic 'check_admin_referer|check_ajax_referer|wp_verify_nonce' "${out3}" || true)"
  cap_count="$(grep -Eic 'current_user_can[[:space:]]*\(' "${out3}" || true)"
  if [[ "${nonce_count}" == "0" || "${cap_count}" == "0" ]]; then
    REDFLAGS_STATUS="ISSUES"
    record_failure \
      "Security basics scan failed (nonce/capability checks not found)" \
      "grep nonce/capability checks in ${WP_PLUGIN_DIR}/admin and ${WP_PLUGIN_DIR}/includes" \
      "${out3}" \
      "The codebase scan did not find expected nonce and/or capability checks in admin/includes code paths." \
      "Add nonce verification and current_user_can checks to admin/POST/AJAX handlers, then rerun."
    return 1
  fi

  REDFLAGS_STATUS="CLEAN"
  append_detail_file "Red flags scan (dangerous functions):" "${out1}"
  append_detail_file "Remote request timeout scan (best effort):" "${out2}"
  append_detail_file "Security basics grep (best effort):" "${out3}"
  return 0
}

run_plugin_check() {
  local out="${PLUGIN_CHECK_OUT}"

  if ! wp_cli_capture "${WORK_DIR}/plugin-check-install.txt" plugin install plugin-check --activate --force; then
    record_failure \
      "Failed to install/activate plugin-check plugin" \
      "wp plugin install plugin-check --activate --force" \
      "${WORK_DIR}/plugin-check-install.txt" \
      "The disposable environment could not install the Plugin Check plugin (network or wp.org issue)." \
      "Ensure outbound network access from the container and rerun."
    return 1
  fi
  append_detail_file "plugin-check install/activate output:" "${WORK_DIR}/plugin-check-install.txt"

  set +e
  wp_cli_capture "${out}" plugin check "${SLUG}" --format=table
  local rc=$?
  set -e
  PLUGIN_CHECK_EXIT_CODE="${rc}"

  PLUGIN_CHECK_ERROR_COUNT="$(awk -F '\t' '$3 == "ERROR" { c++ } END { print c + 0 }' "${out}" 2>/dev/null || echo 0)"
  PLUGIN_CHECK_WARNING_COUNT="$(awk -F '\t' '$3 == "WARNING" { c++ } END { print c + 0 }' "${out}" 2>/dev/null || echo 0)"

  grep -Ei 'WARNING' "${out}" | head -n 20 > "${PLUGIN_CHECK_WARNINGS_TOP}" || true

  append_detail_file "Plugin Check full output:" "${out}"

  # Plugin Check can register admin_init hooks that pollute the target plugin runtime smoke test.
  wp_cli_capture "${WORK_DIR}/plugin-check-deactivate.txt" plugin deactivate plugin-check >/dev/null 2>&1 || true

  if [[ "${PLUGIN_CHECK_ERROR_COUNT}" != "0" ]]; then
    record_failure \
      "Plugin Check reported ERRORs" \
      "wp plugin check ${SLUG} --format=table" \
      "${out}" \
      "Plugin Check found at least one error-level issue." \
      "Fix the reported ERROR items and rerun Plugin Check and the smoke test script."
    return 1
  fi

  # Warnings are reported but do not hard-fail unless command execution itself failed unexpectedly.
  if [[ "${rc}" -ne 0 && "${PLUGIN_CHECK_ERROR_COUNT}" == "0" ]]; then
    warn "Plugin Check exited non-zero without parsing ERROR lines; continuing but marking warnings for review."
  fi

  return 0
}

print_final_report() {
  echo
  echo "### READY_FOR_UPLOAD"
  if [[ "${READY_FOR_UPLOAD}" == "YES" ]]; then
    echo "- YES"
  else
    echo "- NO"
  fi
  echo
  echo "### SUMMARY TABLE"
  echo "- Plugin Check: exit code=${PLUGIN_CHECK_EXIT_CODE}, error count=${PLUGIN_CHECK_ERROR_COUNT}, warning count=${PLUGIN_CHECK_WARNING_COUNT}"
  echo "- Install/Activate: ${INSTALL_ACTIVATE_STATUS}"
  echo "- Reinstall cycle: ${REINSTALL_CYCLE_STATUS}"
  echo "- debug.log: ${DEBUG_LOG_STATUS}"
  echo "- Red flags scan: ${REDFLAGS_STATUS}"
  echo
  echo "### DETAILS"

  if [[ "${READY_FOR_UPLOAD}" == "NO" ]]; then
    echo "- FAIL reason: ${FAIL_REASON}"
    if [[ -n "${FAIL_COMMAND}" ]]; then
      echo "- Failing command: \`${FAIL_COMMAND}\`"
    fi
    if [[ -n "${FAIL_OUTPUT_FILE}" && -f "${FAIL_OUTPUT_FILE}" ]]; then
      echo "- Exact failing command output:"
      echo '```text'
      cat "${FAIL_OUTPUT_FILE}"
      echo '```'
    fi
    if [[ -n "${FAIL_ROOT_CAUSE}" ]]; then
      echo "- Likely root cause: ${FAIL_ROOT_CAUSE}"
    fi
    if [[ -n "${FAIL_MINIMAL_FIX}" ]]; then
      echo "- Minimal fix: ${FAIL_MINIMAL_FIX}"
    fi
  else
    if [[ "${PLUGIN_CHECK_WARNING_COUNT}" != "0" && "${PLUGIN_CHECK_WARNING_COUNT}" != "N/A" ]]; then
      echo "- Remaining Plugin Check warnings: ${PLUGIN_CHECK_WARNING_COUNT}"
      echo "- Classification:"
      echo "  - Harmless style warnings: review the table output and triage formatting/doc notices."
      echo "  - Should fix before upload: any escaping/sanitization/performance warning not marked as informational."
      echo "  - Likely reviewer blocker: anything security, capability, nonce, remote request, or filesystem related."
      if [[ -s "${PLUGIN_CHECK_WARNINGS_TOP}" ]]; then
        echo "- Top 20 warnings:"
        echo '```text'
        cat "${PLUGIN_CHECK_WARNINGS_TOP}"
        echo '```'
      fi
    else
      echo "- Plugin Check warnings: none detected (or command output contained no WARNING rows)."
    fi
    echo "- Smoke tests passed: install/activate, deactivate/delete/reinstall, runtime hook checks, debug.log scan."
  fi

  if [[ -f "${DETAILS_FILE}" && -s "${DETAILS_FILE}" ]]; then
    echo "- Additional notes (captured during run):"
    echo '```text'
    cat "${DETAILS_FILE}"
    echo '```'
  fi

  echo
  echo "### COMMANDS TO RE-RUN"
  local zip_to_use="${ZIP_PATH_FOUND:-${ZIP_PATH_INPUT:-${ZIP_BASENAME}}}"
  local script_cmd="ZIP_PATH='${zip_to_use}' ./release-pass-check.sh"
  echo "- Full pass check: \`${script_cmd}\`"
  echo "- Plugin Check only (inside disposable env script flow): rerun the script and inspect the \"Plugin Check full output\" section"
  echo "- Smoke tests only (inside disposable env script flow): rerun the script and inspect Install/Activate + Reinstall + debug.log sections"
}

main() {
  section "Phase 0: Preflight"
  if ! ZIP_PATH_FOUND="$(locate_zip)"; then
    record_failure \
      "ZIP discovery failed" \
      "ZIP_PATH env or ../plugin-builds|./plugin-builds|./${ZIP_BASENAME}" \
      "" \
      "The submission ZIP could not be located in the expected paths." \
      "Set ZIP_PATH explicitly or place ${ZIP_BASENAME} in ../plugin-builds, ./plugin-builds, or the current directory."
    print_final_report
    exit 1
  fi
  info "Using ZIP: ${ZIP_PATH_FOUND}"
  append_detail "Resolved ZIP path: ${ZIP_PATH_FOUND}"
  verify_zip_structure "${ZIP_PATH_FOUND}" || { print_final_report; exit 1; }

  if [[ -z "${WP_PORT}" ]]; then
    WP_PORT="$(find_free_port 8089 8120)" || {
      record_failure \
        "Failed to find a free localhost port for the WordPress container" \
        "find_free_port 8089..8120" \
        "" \
        "No free port was found in the configured range." \
        "Set WP_PORT to an available port and rerun."
      print_final_report
      exit 1
    }
  fi
  append_detail "Using host port: ${WP_PORT}"

  section "Phase 1: Disposable Docker WordPress"
  cleanup_docker
  docker network create "${NETWORK_NAME}" >/dev/null
  info "Created docker network: ${NETWORK_NAME}"

  docker run -d \
    --name "${DB_CONTAINER}" \
    --network "${NETWORK_NAME}" \
    -e MARIADB_DATABASE="${DB_NAME}" \
    -e MARIADB_USER="${DB_USER}" \
    -e MARIADB_PASSWORD="${DB_PASSWORD}" \
    -e MARIADB_ROOT_PASSWORD="${DB_ROOT_PASSWORD}" \
    "${DB_IMAGE}" >/dev/null
  info "Started DB container: ${DB_CONTAINER}"

  docker run -d \
    --name "${WP_CONTAINER}" \
    --network "${NETWORK_NAME}" \
    -p "${WP_HOST_BIND}:${WP_PORT}:80" \
    -e WORDPRESS_DB_HOST="${DB_CONTAINER}:3306" \
    -e WORDPRESS_DB_NAME="${DB_NAME}" \
    -e WORDPRESS_DB_USER="${DB_USER}" \
    -e WORDPRESS_DB_PASSWORD="${DB_PASSWORD}" \
    "${WP_IMAGE}" >/dev/null
  info "Started WordPress container: ${WP_CONTAINER} (http://localhost:${WP_PORT})"

  if ! wait_for_mariadb; then
    record_failure \
      "MariaDB did not become ready in time" \
      "docker exec ${DB_CONTAINER} mariadb-admin ping ..." \
      "${WORK_DIR}/db-ping.txt" \
      "The MariaDB container failed startup or credentials are incorrect." \
      "Check DB container logs and image pull/start status, then rerun."
    print_final_report
    exit 1
  fi

  if ! wait_for_wordpress_http "http://localhost:${WP_PORT}/"; then
    record_failure \
      "WordPress HTTP endpoint did not become ready in time" \
      "curl -fsS http://localhost:${WP_PORT}/" \
      "${WORK_DIR}/wp-http.txt" \
      "The WordPress container did not become reachable (startup failure or port conflict)." \
      "Check container logs/port mapping and rerun."
    print_final_report
    exit 1
  fi

  install_wp_cli_in_container || { print_final_report; exit 1; }
  install_or_setup_wordpress || { print_final_report; exit 1; }

  section "Phase 2: Install + Activate ZIP"
  if ! run_capture "${WORK_DIR}/docker-cp-zip.txt" docker cp "${ZIP_PATH_FOUND}" "${WP_CONTAINER}:${WP_ZIP_TMP}"; then
    record_failure \
      "Failed to copy plugin ZIP into WordPress container" \
      "docker cp ${ZIP_PATH_FOUND} ${WP_CONTAINER}:${WP_ZIP_TMP}" \
      "${WORK_DIR}/docker-cp-zip.txt" \
      "Docker copy failed (container unavailable or path issue)." \
      "Verify the container is running and the ZIP path is correct, then rerun."
    print_final_report
    exit 1
  fi

  if ! wp_cli_ok_or_fail \
      "Plugin install+activate from ZIP failed" \
      "The plugin ZIP could not be installed/activated in WordPress (packaging/runtime issue)." \
      "Review the command output, fix the packaging/runtime error, rebuild the ZIP, and rerun." \
      "${WORK_DIR}/plugin-install-activate.txt" \
      plugin install "${WP_ZIP_TMP}" --force --activate; then
    print_final_report
    exit 1
  fi

  if ! wp_cli_ok_or_fail \
      "Plugin status check failed after activation" \
      "WP-CLI could not confirm plugin status after installation." \
      "Inspect wp-cli output and container health, then rerun." \
      "${WORK_DIR}/plugin-status-1.txt" \
      plugin status "${SLUG}"; then
    print_final_report
    exit 1
  fi
  INSTALL_ACTIVATE_STATUS="PASS"
  append_detail_file "Plugin install+activate output:" "${WORK_DIR}/plugin-install-activate.txt"
  append_detail_file "Plugin status (after activate):" "${WORK_DIR}/plugin-status-1.txt"

  if ! ensure_not_mountpoint; then
    print_final_report
    exit 1
  fi

  section "Phase 2b: Reinstall cycle (deactivate/delete/reinstall)"
  if ! wp_cli_ok_or_fail \
      "Plugin deactivate failed" \
      "The plugin could not be cleanly deactivated." \
      "Inspect deactivation hook errors and fix them, then rerun." \
      "${WORK_DIR}/plugin-deactivate.txt" \
      plugin deactivate "${SLUG}"; then
    print_final_report
    exit 1
  fi
  if ! wp_cli_ok_or_fail \
      "Plugin delete failed" \
      "The plugin could not be deleted (uninstall hook/filesystem issue)." \
      "Inspect uninstall hook or filesystem errors, fix them, then rerun." \
      "${WORK_DIR}/plugin-delete.txt" \
      plugin delete "${SLUG}"; then
    print_final_report
    exit 1
  fi
  if ! wp_cli_ok_or_fail \
      "Plugin reinstall+activate failed after delete" \
      "The plugin could not be reinstalled after deletion (packaging/uninstall residue issue)." \
      "Fix the packaging/uninstall issue and rerun." \
      "${WORK_DIR}/plugin-reinstall.txt" \
      plugin install "${WP_ZIP_TMP}" --force --activate; then
    print_final_report
    exit 1
  fi
  if ! wp_cli_ok_or_fail \
      "Plugin status check failed after reinstall" \
      "WP-CLI could not confirm plugin status after reinstall." \
      "Inspect wp-cli output and container health, then rerun." \
      "${WORK_DIR}/plugin-status-2.txt" \
      plugin status "${SLUG}"; then
    print_final_report
    exit 1
  fi
  REINSTALL_CYCLE_STATUS="PASS"
  append_detail_file "Plugin deactivate output:" "${WORK_DIR}/plugin-deactivate.txt"
  append_detail_file "Plugin delete output:" "${WORK_DIR}/plugin-delete.txt"
  append_detail_file "Plugin reinstall+activate output:" "${WORK_DIR}/plugin-reinstall.txt"
  append_detail_file "Plugin status (after reinstall):" "${WORK_DIR}/plugin-status-2.txt"

  section "Phase 3: Plugin Check"
  if ! run_plugin_check; then
    print_final_report
    exit 1
  fi

  section "Phase 4: Runtime health + debug.log"
  toggle_debug_logging || { print_final_report; exit 1; }
  run_runtime_health_checks || { print_final_report; exit 1; }
  collect_debug_log_status || { print_final_report; exit 1; }

  section "Phase 5: Static red flags + security basics (best effort)"
  run_redflag_scans || { print_final_report; exit 1; }

  READY_FOR_UPLOAD="YES"
  print_final_report
  exit 0
}

main "$@"
