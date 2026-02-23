#!/usr/bin/env bash
set -euo pipefail

SLUG="beepbeep-ai-alt-text-generator"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="${REPO:-$(cd "${SCRIPT_DIR}/.." && pwd)}"
OUT_DIR="${OUT_DIR:-${REPO}/../plugin-builds}"
ZIP_NAME="${ZIP_NAME:-${SLUG}.zip}"

STAGE_BASE="$(mktemp -d "${TMPDIR:-/tmp}/bbai-build.XXXXXX")"
STAGE_DIR="${STAGE_BASE}/${SLUG}"

cleanup() {
  rm -rf "${STAGE_BASE}"
}
trap cleanup EXIT

copy_required() {
  local source="$1"
  local target="$2"

  if [[ ! -e "${source}" ]]; then
    echo "Missing required path: ${source}" >&2
    exit 1
  fi

  if [[ -d "${source}" ]]; then
    cp -R "${source}" "${target}"
  else
    cp "${source}" "${target}"
  fi
}

mkdir -p "${STAGE_DIR}"

copy_required "${REPO}/beepbeep-ai-alt-text-generator.php" "${STAGE_DIR}/"
copy_required "${REPO}/uninstall.php" "${STAGE_DIR}/"
copy_required "${REPO}/readme.txt" "${STAGE_DIR}/"
copy_required "${REPO}/includes" "${STAGE_DIR}/"
copy_required "${REPO}/admin" "${STAGE_DIR}/"
copy_required "${REPO}/templates" "${STAGE_DIR}/"
copy_required "${REPO}/languages" "${STAGE_DIR}/"
copy_required "${REPO}/assets" "${STAGE_DIR}/"

# Ensure runtime JS/CSS exists under assets/js and assets/css for release builds.
mkdir -p "${STAGE_DIR}/assets"
if [[ -d "${REPO}/assets/src/js" ]]; then
  cp -R "${REPO}/assets/src/js" "${STAGE_DIR}/assets/"
fi
if [[ -d "${REPO}/assets/src/css" ]]; then
  cp -R "${REPO}/assets/src/css" "${STAGE_DIR}/assets/"
fi

# Remove forbidden directories.
rm -rf "${STAGE_DIR}/assets/src" "${STAGE_DIR}/assets/wordpress-org"
find "${STAGE_DIR}" -type d \( \
  -name node_modules -o \
  -name .git -o \
  -name .github -o \
  -name .vscode \
\) -prune -exec rm -rf {} +

# Remove forbidden/reviewer-sensitive files from the distribution.
find "${STAGE_DIR}" -type f \( \
  -name '.DS_Store' -o \
  -name '*.py' -o \
  -name '*.sh' -o \
  -name '*.ps1' -o \
  -name '*.map' -o \
  -name '*.ts' -o \
  -name '*.tsx' -o \
  -name '*.jsx' -o \
  -name '*.zip' -o \
  -iname '*.md' \
\) -delete

find "${STAGE_DIR}" -depth -type d -empty -delete

for entry in \
  "${STAGE_DIR}/beepbeep-ai-alt-text-generator.php" \
  "${STAGE_DIR}/readme.txt" \
  "${STAGE_DIR}/uninstall.php"
do
  [[ -f "${entry}" ]] || { echo "Missing entrypoint in stage: ${entry}" >&2; exit 1; }
done

mkdir -p "${OUT_DIR}"
OUT_ZIP="${OUT_DIR}/${ZIP_NAME}"
rm -f "${OUT_ZIP}"

(
  cd "${STAGE_BASE}"
  zip -qr "${OUT_ZIP}" "${SLUG}"
)

echo "Built ZIP: ${OUT_ZIP}"
