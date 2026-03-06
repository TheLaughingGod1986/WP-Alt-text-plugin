#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="beepbeep-ai-alt-text-generator"
WP_ROOT="${WP_ROOT:-/var/www/html}"
CID="${CID:-}"
WPCLI_PHAR="${WPCLI_PHAR:-/tmp/wp-cli.phar}"
WPCLI_MODE=""
TEST_SITE_SLUG="${TEST_SITE_SLUG:-bbai-ms-$(date +%s)}"
TEST_SITE_TITLE="${TEST_SITE_TITLE:-BBAI Multisite Test}"
TEST_SITE_EMAIL="${TEST_SITE_EMAIL:-admin@example.com}"
KEEP_TEST_SITE="${KEEP_TEST_SITE:-0}"
CREATED_SITE_ID=""

fail() {
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

wp_cli() {
	if [[ "${WPCLI_MODE}" == "binary" ]]; then
		docker exec "${CID}" wp --allow-root --path="${WP_ROOT}" "$@"
	else
		docker exec "${CID}" php "${WPCLI_PHAR}" --allow-root --path="${WP_ROOT}" "$@"
	fi
}

find_wp_container() {
	local candidate

	if [[ -n "${CID}" ]]; then
		docker_exec_sh "test -f '${WP_ROOT}/wp-config.php'" >/dev/null 2>&1 || fail "CID=${CID} does not contain ${WP_ROOT}/wp-config.php"
		return
	fi

	while IFS= read -r candidate; do
		[[ -n "${candidate}" ]] || continue
		if docker exec "${candidate}" sh -lc "test -f '${WP_ROOT}/wp-config.php'" >/dev/null 2>&1; then
			CID="${candidate}"
			return
		fi
	done < <(docker ps -q)

	fail "No running WordPress container found with ${WP_ROOT}/wp-config.php present."
}

ensure_wp_cli() {
	if docker_exec_sh "command -v wp >/dev/null 2>&1"; then
		WPCLI_MODE="binary"
		return
	fi

	docker_exec_sh "test -f '${WPCLI_PHAR}'" || fail "Neither wp binary nor ${WPCLI_PHAR} exists in container ${CID}."
	WPCLI_MODE="phar"
}

cleanup() {
	if [[ -n "${CREATED_SITE_ID}" && "${KEEP_TEST_SITE}" != "1" ]]; then
		wp_cli site delete "${CREATED_SITE_ID}" --yes >/dev/null 2>&1 || true
	fi
}
trap cleanup EXIT

check_site_bootstrap() {
	local site_url="$1"
	local label="$2"
	local report
	local missing
	local logs_ready
	local cron_ready

	report="$(wp_cli --url="${site_url}" eval '
global $wpdb;
$tables = array(
	$wpdb->prefix . "bbai_queue",
	$wpdb->prefix . "bbai_logs",
	$wpdb->prefix . "bbai_credit_usage",
	$wpdb->prefix . "bbai_usage_logs",
	$wpdb->prefix . "bbai_contact_submissions",
);
$missing = array();
foreach ($tables as $table) {
	$exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
	if ($exists !== $table) {
		$missing[] = $table;
	}
}
$logs_ready = get_option("bbai_logs_ready", false) ? "1" : "0";
$cron_ready = wp_next_scheduled("bbai_process_queue") ? "1" : "0";
echo "missing=" . implode(",", $missing) . " logs_ready={$logs_ready} cron_ready={$cron_ready}";
' | tr -d '\r')"

	missing="$(printf '%s' "${report}" | sed -E 's/^missing=([^ ]*).*/\1/')"
	logs_ready="$(printf '%s' "${report}" | sed -E 's/.*logs_ready=([01]).*/\1/')"
	cron_ready="$(printf '%s' "${report}" | sed -E 's/.*cron_ready=([01]).*/\1/')"

	if [[ -n "${missing}" ]]; then
		fail "${label}: missing plugin tables (${missing})."
	fi
	if [[ "${logs_ready}" != "1" ]]; then
		fail "${label}: option bbai_logs_ready was not initialized."
	fi
	if [[ "${cron_ready}" != "1" ]]; then
		fail "${label}: bbai_process_queue cron hook is not scheduled."
	fi
}

need_cmd docker
need_cmd sed

find_wp_container
ensure_wp_cli

wp_cli core is-installed >/dev/null 2>&1 || fail "WordPress is not installed in ${CID}:${WP_ROOT}."

is_multisite="$(wp_cli eval 'echo is_multisite() ? "1" : "0";' | tr -d '\r')"
if [[ "${is_multisite}" != "1" ]]; then
	fail "This WordPress instance is not multisite-enabled. Enable multisite first, then rerun this test."
fi

printf '%s\n' "Using container: ${CID}"
printf '%s\n' "Reactivating ${PLUGIN_SLUG} network-wide..."
wp_cli plugin deactivate "${PLUGIN_SLUG}" --network >/dev/null 2>&1 || true
wp_cli plugin activate "${PLUGIN_SLUG}" --network >/dev/null

wp_cli plugin is-active "${PLUGIN_SLUG}" --network >/dev/null || fail "Plugin is not network-active after activation."

main_site_url="$(wp_cli option get siteurl | tr -d '\r')"
check_site_bootstrap "${main_site_url}" "Main site (${main_site_url})"

CREATED_SITE_ID="$(wp_cli site create --slug="${TEST_SITE_SLUG}" --title="${TEST_SITE_TITLE}" --email="${TEST_SITE_EMAIL}" --porcelain | tr -d '\r')"
new_site_url="$(wp_cli eval "echo get_site_url((int) ${CREATED_SITE_ID});" | tr -d '\r')"

check_site_bootstrap "${new_site_url}" "New site (${new_site_url})"

printf '\n%s\n' "Multisite verification passed."
printf '%s\n' "Main site: ${main_site_url}"
printf '%s\n' "New site: ${new_site_url} (site_id=${CREATED_SITE_ID})"
if [[ "${KEEP_TEST_SITE}" != "1" ]]; then
	printf '%s\n' "Cleanup: test site will be deleted automatically."
else
	printf '%s\n' "Cleanup skipped (KEEP_TEST_SITE=1)."
fi
