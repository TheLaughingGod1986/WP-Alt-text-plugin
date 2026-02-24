#!/bin/sh
set -eu

PLUGIN_SLUG="beepbeep-ai-alt-text-generator"
SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
REPO_ROOT=$(CDPATH= cd -- "${SCRIPT_DIR}/.." && pwd)
OUT_DIR=${OUT_DIR:-"${REPO_ROOT}/../plugin-builds"}
ZIP_NAME="${PLUGIN_SLUG}.zip"

fail() {
  printf '%s\n' "ERROR: $*" >&2
  exit 1
}

need_cmd() {
  command -v "$1" >/dev/null 2>&1 || fail "Missing required command: $1"
}

copy_path() {
  src=$1
  dest=$2
  [ -e "$src" ] || fail "Missing required path: $src"
  mkdir -p "$dest"

  if command -v rsync >/dev/null 2>&1; then
    rsync -a "$src" "$dest"/
  else
    base=$(dirname "$src")
    name=$(basename "$src")
    (
      cd "$base" &&
      tar -cf - "$name"
    ) | (
      cd "$dest" &&
      tar -xf -
    )
  fi
}

need_cmd zip
need_cmd unzip
need_cmd find
need_cmd awk
need_cmd sort
need_cmd wc
need_cmd grep

mkdir -p "$OUT_DIR"
OUT_DIR_ABS=$(CDPATH= cd -- "$OUT_DIR" && pwd)
REPO_ROOT_ABS=$(CDPATH= cd -- "$REPO_ROOT" && pwd)

case "${OUT_DIR_ABS}/" in
  "${REPO_ROOT_ABS}/"*)
    fail "Output directory must be outside plugin root. Use ../plugin-builds (default) or another external path."
    ;;
esac

STAGE_BASE=$(mktemp -d "${TMPDIR:-/tmp}/bbai-release.XXXXXX")
STAGE_PLUGIN="${STAGE_BASE}/${PLUGIN_SLUG}"
ZIP_PATH="${OUT_DIR_ABS}/${ZIP_NAME}"

cleanup() {
  rm -rf "$STAGE_BASE"
}
trap cleanup EXIT HUP INT TERM

mkdir -p "$STAGE_PLUGIN"

# Stage only production/runtime plugin files (whitelist).
copy_path "${REPO_ROOT_ABS}/${PLUGIN_SLUG}.php" "$STAGE_PLUGIN"
copy_path "${REPO_ROOT_ABS}/readme.txt" "$STAGE_PLUGIN"
copy_path "${REPO_ROOT_ABS}/uninstall.php" "$STAGE_PLUGIN"
copy_path "${REPO_ROOT_ABS}/admin" "$STAGE_PLUGIN"
copy_path "${REPO_ROOT_ABS}/includes" "$STAGE_PLUGIN"
copy_path "${REPO_ROOT_ABS}/templates" "$STAGE_PLUGIN"
copy_path "${REPO_ROOT_ABS}/languages" "$STAGE_PLUGIN"
copy_path "${REPO_ROOT_ABS}/assets" "$STAGE_PLUGIN"

# Optional runtime asset overlay (if JS/CSS are generated from src but runtime expects assets/js + assets/css).
if [ -d "${REPO_ROOT_ABS}/assets/src/js" ]; then
  mkdir -p "${STAGE_PLUGIN}/assets"
  copy_path "${REPO_ROOT_ABS}/assets/src/js" "${STAGE_PLUGIN}/assets"
fi
if [ -d "${REPO_ROOT_ABS}/assets/src/css" ]; then
  mkdir -p "${STAGE_PLUGIN}/assets"
  copy_path "${REPO_ROOT_ABS}/assets/src/css" "${STAGE_PLUGIN}/assets"
fi

# Explicitly remove files/dirs Plugin Check flags or that should never ship.
rm -rf \
  "${STAGE_PLUGIN}/assets/src" \
  "${STAGE_PLUGIN}/assets/wordpress-org" \
  "${STAGE_PLUGIN}/tools" \
  "${STAGE_PLUGIN}/scripts" \
  "${STAGE_PLUGIN}/release" \
  "${STAGE_PLUGIN}/plugin-builds" \
  "${STAGE_PLUGIN}/docs" \
  "${STAGE_PLUGIN}/tests" \
  "${STAGE_PLUGIN}/node_modules"

# Remove all dotfiles and dot-directories in staging (e.g. .gitignore, .distignore, .claude, hidden junk).
find "${STAGE_PLUGIN}" -mindepth 1 -name '.*' -prune -exec rm -rf {} +

# Remove internal docs, dev scripts, logs, archives and source/build artifacts from staged package.
find "${STAGE_PLUGIN}" -type f \( \
  -name '*.sh' -o \
  -name '*.zip' -o \
  -name '*.log' -o \
  -name '*.md' -o \
  -name 'Thumbs.db' -o \
  -name '.DS_Store' -o \
  -name '*.py' -o \
  -name '*.ps1' -o \
  -name '*.map' -o \
  -name '*.ts' -o \
  -name '*.tsx' -o \
  -name '*.jsx' \
\) -exec rm -f {} +

# Clean empty directories left by pruning.
find "${STAGE_PLUGIN}" -depth -type d -empty -exec rmdir {} \; 2>/dev/null || true

[ -f "${STAGE_PLUGIN}/${PLUGIN_SLUG}.php" ] || fail "Main plugin file missing from staging"
[ -f "${STAGE_PLUGIN}/readme.txt" ] || fail "readme.txt missing from staging"
[ -f "${STAGE_PLUGIN}/uninstall.php" ] || fail "uninstall.php missing from staging"

rm -f "$ZIP_PATH"
(
  cd "$STAGE_BASE" &&
  zip -qr "$ZIP_PATH" "$PLUGIN_SLUG"
)

ZIP_LIST=$(unzip -Z1 "$ZIP_PATH")
TOP_DIRS=$(printf '%s\n' "$ZIP_LIST" | awk -F/ 'NF { print $1 }' | sort -u)
TOP_COUNT=$(printf '%s\n' "$TOP_DIRS" | awk 'NF { n++ } END { print n + 0 }')

[ "$TOP_COUNT" -eq 1 ] || fail "ZIP must contain exactly one top-level folder"
[ "$TOP_DIRS" = "$PLUGIN_SLUG" ] || fail "ZIP top-level folder must be ${PLUGIN_SLUG}/"

printf '%s\n' "$ZIP_LIST" | grep -E '(^|/)\.gitignore$|(^|/)\.distignore$|(^|/)\.claude(/|$)|(^|/)RELEASE\.md$|(^|/)ADDITIONAL_IMPROVEMENTS\.md$|(^|/)CLAUDE_AUDIT\.md$' >/dev/null 2>&1 && \
  fail "ZIP still contains hidden/internal repo files"
printf '%s\n' "$ZIP_LIST" | grep -Ei '(^|/)\.DS_Store$|\.sh$|(^|/)(tools|scripts)/|\.(zip|tar|tgz|gz|bz2|xz|7z|rar)$' >/dev/null 2>&1 && \
  fail "ZIP still contains disallowed packaging files"

ZIP_BYTES=$(wc -c < "$ZIP_PATH" | awk '{ print $1 }')
ZIP_SIZE=$(du -h "$ZIP_PATH" | awk '{ print $1 }')

printf '%s\n' "Built ZIP: ${ZIP_PATH}"
printf '%s\n' "Size: ${ZIP_SIZE} (${ZIP_BYTES} bytes)"
printf '%s\n' "Top-level folder: ${PLUGIN_SLUG}/"
printf '%s\n' ""
printf '%s\n' "Quick verification:"
printf '%s\n' "  unzip -l '${ZIP_PATH}' | grep -E '\\.sh$|^.*tools/|^.*scripts/' || true"
printf '%s\n' "  unzip -l '${ZIP_PATH}' | grep -E '\\.gitignore$|\\.distignore$|\\.claude/|RELEASE\\.md$|ADDITIONAL_IMPROVEMENTS\\.md$|CLAUDE_AUDIT\\.md$' || true"
printf '%s\n' ""
printf '%s\n' "Plugin Check (example):"
printf '%s\n' "  WP_PATH=/absolute/path/to/wp"
printf '%s\n' "  wp --path=\"\$WP_PATH\" plugin check '${ZIP_PATH}' --format=table"
