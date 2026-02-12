#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_SLUG="beepbeep-ai-alt-text-generator"
PARENT_DIR="$(cd "${ROOT_DIR}/.." && pwd)"
OUTPUT_DIR="${OUTPUT_DIR:-${PARENT_DIR}/plugin-builds}"
MAIN_FILE="${ROOT_DIR}/beepbeep-ai-alt-text-generator.php"
DISTIGNORE_FILE="${ROOT_DIR}/.distignore"

if [[ ! -f "${MAIN_FILE}" ]]; then
	echo "Error: Missing ${MAIN_FILE}" >&2
	exit 1
fi

VERSION="$(sed -n 's/^ \* Version: //p' "${MAIN_FILE}" | head -n 1 | tr -d '\r')"
if [[ -z "${VERSION}" ]]; then
	echo "Error: Unable to detect plugin version from ${MAIN_FILE}" >&2
	exit 1
fi

TMP_DIR="$(mktemp -d)"
STAGE_DIR="${TMP_DIR}/${PLUGIN_SLUG}"
ZIP_PATH="${OUTPUT_DIR}/${PLUGIN_SLUG}-${VERSION}.zip"

cleanup() {
	rm -rf "${TMP_DIR}"
}
trap cleanup EXIT

mkdir -p "${OUTPUT_DIR}"

echo "Staging ${PLUGIN_SLUG} from repository root..."
RSYNC_ARGS=(-a --delete --exclude "${PLUGIN_SLUG}.zip")
if [[ -f "${DISTIGNORE_FILE}" ]]; then
	RSYNC_ARGS+=(--exclude-from="${DISTIGNORE_FILE}")
fi
rsync "${RSYNC_ARGS[@]}" "${ROOT_DIR}/" "${STAGE_DIR}/"

echo "Pruning non-production artifacts..."
# Remove hidden files/directories from the staged plugin to comply with WordPress.org packaging rules.
find "${STAGE_DIR}" -mindepth 1 -name '.*' -prune -exec rm -rf {} +

find "${STAGE_DIR}" -type d \
	\( -name '.git' -o -name '.github' -o -name 'node_modules' -o -name 'vendor' -o -name 'tests' -o -name 'test' -o -name 'plugincheck' -o -name 'plugin-check' -o -name 'reports' -o -name 'report' \) \
	-prune -exec rm -rf {} +

find "${STAGE_DIR}" -type f \
	\( -name '*.patch' -o -name 'plugincheck-*.json' -o -name 'plugincheck-*.txt' -o -name '*textdomain-migration*' -o -name 'dist-textdomain-modified-files.txt' -o -name 'dist-opptiai-i18n-calls-before.txt' -o -name 'dist-opptiai-i18n-calls-after.txt' -o -name 'dist-*-before.txt' -o -name 'dist-*-after.txt' -o -name 'dist-*-modified-files.txt' -o -name '*findings*.txt' -o -name '*.log' -o -name '*.zip' -o -name '*.sh' -o -name '*.command' \) \
	-delete

find "${STAGE_DIR}" -type f -name '*.md' -delete

if [[ ! -f "${STAGE_DIR}/beepbeep-ai-alt-text-generator.php" ]]; then
	echo "Error: staged plugin is missing main file." >&2
	exit 1
fi

rm -f "${ZIP_PATH}"
(
	cd "${TMP_DIR}"
	zip -qr "${ZIP_PATH}" "${PLUGIN_SLUG}" \
		-x "${PLUGIN_SLUG}/.gitattributes" \
		-x "${PLUGIN_SLUG}/build-plugin.sh" \
		-x "${PLUGIN_SLUG}/*.sh" \
		-x "${PLUGIN_SLUG}/*.zip" \
		-x "${PLUGIN_SLUG}/dist/*.zip" \
		-x "${PLUGIN_SLUG}/dist/*/*.zip"
)

echo "Built ${ZIP_PATH}"
