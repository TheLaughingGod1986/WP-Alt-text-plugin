#!/usr/bin/env bash
set -euo pipefail

# Release helper:
# - bump plugin version (header + constants)
# - append CHANGELOG.md entry
# - build a clean distributable zip
# - sync into wp-env slug folder
# - verify wp-env is running the new version
#
# Usage:
#   ./scripts/release.bash 4.6.2 "Short release note"
#
# Optional:
#   BBAI_WP_ENV_HASH=...   (overrides the hash used by sync-to-wpenv-slug.bash)

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_MAIN="${ROOT_DIR}/beepbeep-ai-alt-text-generator.php"
CHANGELOG="${ROOT_DIR}/CHANGELOG.md"
README_TXT="${ROOT_DIR}/readme.txt"
SYNC_SCRIPT="${ROOT_DIR}/scripts/sync-to-wpenv-slug.bash"

VERSION="${1:-}"
NOTE="${2:-}"

if [[ -z "${VERSION}" ]]; then
  echo "Usage: ./scripts/release.bash <version> \"<short note>\""
  exit 1
fi

if [[ ! -f "${PLUGIN_MAIN}" ]]; then
  echo "Plugin main file not found at: ${PLUGIN_MAIN}"
  exit 1
fi

if [[ ! -f "${CHANGELOG}" ]]; then
  echo "Missing ${CHANGELOG}. Create it first."
  exit 1
fi

if [[ ! -f "${README_TXT}" ]]; then
  echo "Missing ${README_TXT}. WordPress.org deploys require readme.txt."
  exit 1
fi

if [[ ! -x "${SYNC_SCRIPT}" ]]; then
  echo "Sync script not executable at: ${SYNC_SCRIPT}"
  echo "Try: chmod +x \"${SYNC_SCRIPT}\""
  exit 1
fi

if ! command -v zip >/dev/null 2>&1; then
  echo "'zip' not found. Install it to build the plugin zip."
  exit 1
fi

today="$(date +%Y-%m-%d)"

echo "Bumping plugin version to ${VERSION}"

# Update plugin header Version + constants (self-healing even if header was corrupted).
python3 - "${PLUGIN_MAIN}" "${VERSION}" <<'PY'
import re, sys

path = sys.argv[1]
version = sys.argv[2]

text = open(path, "r", encoding="utf-8").read()

# 1) Ensure plugin header has a proper "* Version: x.y.z" line.
lines = text.splitlines(True)
header_start = None
header_end = None
for i, line in enumerate(lines[:200]):
    if "/**" in line and header_start is None:
        header_start = i
    if header_start is not None and "*/" in line:
        header_end = i
        break

if header_start is None or header_end is None:
    raise SystemExit("Could not locate plugin header comment block.")

header = lines[header_start:header_end+1]

# Drop any previously corrupted "version" lines (e.g. "${1}4.6.1" or "$14.6.1").
header = [
    line for line in header
    if not re.match(r"^\s*\$\{?1\}?\d+\.\d+\.\d+\s*$", line.strip())
]

version_line_re = re.compile(r"^\s*\*\s*Version:\s*\d+\.\d+\.\d+\s*$")
found_version_line = False
for i, line in enumerate(header):
    if version_line_re.match(line.strip("\n")):
        header[i] = re.sub(r"(\*\s*Version:\s*)\d+\.\d+\.\d+", r"\g<1>"+version, header[i])
        found_version_line = True
        break

if not found_version_line:
    # If the header was corrupted (or missing version), insert a correct Version line
    # right after the Description line (or after Plugin Name as a fallback).
    insert_after = None
    for i, line in enumerate(header):
        if re.search(r"\*\s*Description:", line):
            insert_after = i
            break
    if insert_after is None:
        for i, line in enumerate(header):
            if re.search(r"\*\s*Plugin Name:", line):
                insert_after = i
                break
    if insert_after is None:
        insert_after = 0
    header.insert(insert_after + 1, f" * Version: {version}\n")

lines[header_start:header_end+1] = header
text = "".join(lines)

# 2) Keep constants in sync
text = re.sub(r"define\(\s*'BEEPBEEP_AI_VERSION',\s*'(\d+\.\d+\.\d+)'\s*\);",
              f"define( 'BEEPBEEP_AI_VERSION', '{version}' );", text)
text = re.sub(r"define\(\s*'BBAI_VERSION',\s*'(\d+\.\d+\.\d+)'\s*\);",
              f"define( 'BBAI_VERSION', '{version}' );", text)

open(path, "w", encoding="utf-8").write(text)
PY

echo "Updating readme.txt Stable tag"
python3 - "${README_TXT}" "${VERSION}" <<'PY'
import re, sys
path = sys.argv[1]
version = sys.argv[2]
lines = open(path, "r", encoding="utf-8").read().splitlines(True)

# Remove any previously corrupted stable-tag lines like "$14.6.1" or "${1}4.6.1".
out = []
for line in lines:
    if re.match(r"^\s*\$\{?1\}?\d+\.\d+\.\d+\s*$", line.strip()):
        continue
    out.append(line)
lines = out

stable_re = re.compile(r"^Stable tag:\s*.*$", re.IGNORECASE)
found = False
for i, line in enumerate(lines):
    if stable_re.match(line.strip("\n")):
        lines[i] = f"Stable tag: {version}\n"
        found = True
        break

if not found:
    # Insert after "Requires PHP" if present, otherwise near top.
    insert_after = None
    for i, line in enumerate(lines[:40]):
        if line.lower().startswith("requires php:"):
            insert_after = i
            break
    if insert_after is None:
        insert_after = min(10, len(lines)-1)
    lines.insert(insert_after + 1, f"Stable tag: {version}\n")

open(path, "w", encoding="utf-8").write("".join(lines))
PY

echo "Updating CHANGELOG.md"

# Prepend a release section under "Unreleased"
tmp="$(mktemp)"
awk -v v="${VERSION}" -v d="${today}" -v note="${NOTE}" '
  BEGIN { inserted=0 }
  /^### Unreleased/ {
    print $0 "\n"
    if (!inserted) {
      print "### " v " — " d
      if (note != "") {
        print ""
        print "- **Changed**: " note
      }
      print ""
      inserted=1
    }
    next
  }
  { print }
' "${CHANGELOG}" > "${tmp}"
mv "${tmp}" "${CHANGELOG}"

echo "Linting PHP"
php -l "${PLUGIN_MAIN}" >/dev/null

ZIP_OUT="${ROOT_DIR}/dist/beepbeep-ai-alt-text-generator-${VERSION}.zip"
mkdir -p "${ROOT_DIR}/dist"

echo "Building zip: ${ZIP_OUT}"

# Create zip from a staging dir so the archive root is the plugin slug folder.
stage="$(mktemp -d)"
trap 'rm -rf "${stage}"' EXIT

slug_dir="${stage}/beepbeep-ai-alt-text-generator"
mkdir -p "${slug_dir}"

rsync -av --delete \
  --exclude=".git/" \
  --exclude=".gitattributes" \
  --exclude=".gitignore" \
  --exclude=".claude/" \
  --exclude=".cursor/" \
  --exclude=".playwright-cli/" \
  --exclude=".playwright-mcp/" \
  --exclude=".vscode/" \
  --exclude=".wp-env.json" \
  --exclude=".wporg-svn/" \
  --exclude="AGENTS.md" \
  --exclude="build/" \
  --exclude="dist/" \
  --exclude="docs/" \
  --exclude="gitignore" \
  --exclude="jest.config.js" \
  --exclude="login-helper.js" \
  --exclude="node_modules/" \
  --exclude="output/" \
  --exclude="output-dashboard-after.png" \
  --exclude="output-dashboard-before.png" \
  --exclude="output-dashboard-final.png" \
  --exclude="package-lock.json" \
  --exclude="package.json" \
  --exclude="playwright-report/" \
  --exclude="playwright.config.ts" \
  --exclude="rescan-complete-feedback.png" \
  --exclude="scripts/" \
  --exclude="sync-to-wpenv.bash" \
  --exclude="test-results/" \
  --exclude="tests/" \
  --exclude="wp-login.js" \
  --exclude=".DS_Store" \
  --exclude="*/.DS_Store" \
  --exclude="assets/src/" \
  --exclude="assets/img/screenshots/" \
  "${ROOT_DIR}/" "${slug_dir}/" >/dev/null

(cd "${stage}" && zip -qr "${ZIP_OUT}" "beepbeep-ai-alt-text-generator")

echo "Syncing to wp-env"

# Allow overriding the hash without editing the sync script.
if [[ -n "${BBAI_WP_ENV_HASH:-}" ]]; then
  # shellcheck disable=SC2016
  perl -0pi -e 's/WP_ENV_HASH="[^"]+"/WP_ENV_HASH="'"${BBAI_WP_ENV_HASH}"'"/' "${SYNC_SCRIPT}"
fi

bash "${SYNC_SCRIPT}"

echo "Verifying wp-env is running version ${VERSION}"

verify_ok=0

if command -v wp-env >/dev/null 2>&1; then
  # This requires wp-env to be installed and running.
  if wp_env_version="$(wp-env run cli wp plugin get beepbeep-ai-alt-text-generator --field=version 2>/dev/null)"; then
    wp_env_version="$(echo "${wp_env_version}" | tr -d '\r\n' | head -n 1)"
    if [[ "${wp_env_version}" == "${VERSION}" ]]; then
      verify_ok=1
    else
      echo "wp-env reports version: ${wp_env_version} (expected ${VERSION})"
    fi
  fi
fi

if [[ "${verify_ok}" -ne 1 ]]; then
  # Fallback: read the synced file header.
  # sync-to-wpenv-slug.bash prints the destination; reconstruct it here too.
  WP_ENV_HASH="$(perl -ne 'print $1 if /^WP_ENV_HASH="([^"]+)"/' "${SYNC_SCRIPT}")"
  dest="${HOME}/.wp-env/${WP_ENV_HASH}/WordPress/wp-content/plugins/beepbeep-ai-alt-text-generator/beepbeep-ai-alt-text-generator.php"
  if [[ -f "${dest}" ]]; then
    dest_ver="$(awk -F': ' '/\\* Version:/{print $2; exit}' "${dest}" | tr -d '\r\n' || true)"
    if [[ "${dest_ver}" == "${VERSION}" ]]; then
      verify_ok=1
    else
      echo "Synced plugin file version: ${dest_ver:-unknown} (expected ${VERSION})"
    fi
  else
    echo "Could not find synced plugin file at: ${dest}"
  fi
fi

if [[ "${verify_ok}" -ne 1 ]]; then
  echo "Verification failed: wp-env does not appear updated to ${VERSION}."
  exit 1
fi

echo ""
echo "Release ready:"
echo "- Version: ${VERSION}"
echo "- Zip: ${ZIP_OUT}"
echo "- Synced + verified in wp-env"
