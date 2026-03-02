#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
MAIN_FILE="${ROOT_DIR}/beepbeep-ai-alt-text-generator.php"
CORE_FILE="${ROOT_DIR}/admin/class-bbai-core.php"
README_FILE="${ROOT_DIR}/readme.txt"

DRY_RUN="${DRY_RUN:-0}"
AUTO_BUMP_PATCH="${AUTO_BUMP_PATCH:-0}"
RELEASE_VERSION="${RELEASE_VERSION:-}"
RELEASE_NOTES="${RELEASE_NOTES:-}"
RELEASE_NOTES_FILE="${RELEASE_NOTES_FILE:-}"
RELEASE_UPGRADE_NOTE="${RELEASE_UPGRADE_NOTE:-}"

fail() {
  printf '%s\n' "ERROR: $*" >&2
  exit 1
}

need_cmd() {
  command -v "$1" >/dev/null 2>&1 || fail "Missing required command: $1"
}

trim() {
  local s="$1"
  s="${s#${s%%[![:space:]]*}}"
  s="${s%${s##*[![:space:]]}}"
  printf '%s' "$s"
}

extract_current_version() {
  awk -F': ' '/^ \* Version: / { print $2; exit }' "${MAIN_FILE}"
}

bump_patch() {
  local version="$1"
  IFS='.' read -r major minor patch <<<"${version}"
  [[ -n "${major:-}" && -n "${minor:-}" && -n "${patch:-}" ]] || fail "Unable to parse semantic version: ${version}"
  [[ "${major}" =~ ^[0-9]+$ && "${minor}" =~ ^[0-9]+$ && "${patch}" =~ ^[0-9]+$ ]] || fail "Version components must be numeric: ${version}"
  patch="$((patch + 1))"
  printf '%s.%s.%s' "${major}" "${minor}" "${patch}"
}

normalize_notes_from_env() {
  local normalized=""
  local raw="${RELEASE_NOTES}"
  local line=""

  raw="${raw//$'\r'/}"
  raw="${raw//|/$'\n'}"

  while IFS= read -r line; do
    line="$(trim "${line}")"
    [[ -n "${line}" ]] || continue
    line="${line#\* }"
    normalized+="* ${line}"$'\n'
  done <<<"${raw}"

  printf '%s' "${normalized}"
}

normalize_notes_from_file() {
  local file_path="$1"
  local normalized=""
  local line=""

  [[ -f "${file_path}" ]] || fail "RELEASE_NOTES_FILE not found: ${file_path}"

  while IFS= read -r line; do
    line="$(trim "${line}")"
    [[ -n "${line}" ]] || continue
    line="${line#\* }"
    normalized+="* ${line}"$'\n'
  done <"${file_path}"

  printf '%s' "${normalized}"
}

run_or_print() {
  if [[ "${DRY_RUN}" == "1" ]]; then
    printf '[DRY_RUN]'
    for arg in "$@"; do
      printf ' %q' "${arg}"
    done
    printf '\n'
    return 0
  fi

  "$@"
}

need_cmd awk
need_cmd perl

[[ -f "${MAIN_FILE}" ]] || fail "Missing main plugin file: ${MAIN_FILE}"
[[ -f "${CORE_FILE}" ]] || fail "Missing core file: ${CORE_FILE}"
[[ -f "${README_FILE}" ]] || fail "Missing readme: ${README_FILE}"

CURRENT_VERSION="$(extract_current_version)"
[[ -n "${CURRENT_VERSION}" ]] || fail "Could not extract current plugin version"

if [[ -z "${RELEASE_VERSION}" ]]; then
  if [[ "${AUTO_BUMP_PATCH}" == "1" ]]; then
    RELEASE_VERSION="$(bump_patch "${CURRENT_VERSION}")"
  else
    fail "Set RELEASE_VERSION (or set AUTO_BUMP_PATCH=1)"
  fi
fi

if [[ -n "${RELEASE_NOTES_FILE}" ]]; then
  NORMALIZED_NOTES="$(normalize_notes_from_file "${RELEASE_NOTES_FILE}")"
elif [[ -n "${RELEASE_NOTES}" ]]; then
  NORMALIZED_NOTES="$(normalize_notes_from_env)"
else
  NORMALIZED_NOTES="$(cat <<'EON'
* Maintenance release with bug fixes and stability improvements.
* Improved WooCommerce, Image SEO, and Accessibility workflows.
* Internal performance and reliability optimizations.
EON
)"
fi

[[ -n "${NORMALIZED_NOTES}" ]] || fail "No changelog notes provided"

if [[ -z "${RELEASE_UPGRADE_NOTE}" ]]; then
  RELEASE_UPGRADE_NOTE="Recommended maintenance update with WooCommerce, Image SEO, and Accessibility reliability improvements."
fi

CHANGELOG_BLOCK="$(printf '= %s =\n%s\n\n' "${RELEASE_VERSION}" "${NORMALIZED_NOTES}")"
UPGRADE_BLOCK="$(printf '= %s =\n%s\n\n' "${RELEASE_VERSION}" "${RELEASE_UPGRADE_NOTE}")"

printf '%s\n' "Updating release metadata"
printf '%s\n' "- Current version: ${CURRENT_VERSION}"
printf '%s\n' "- Target version:  ${RELEASE_VERSION}"

if [[ "${DRY_RUN}" == "1" ]]; then
  printf '%s\n' "[DRY_RUN] Update ${MAIN_FILE}, ${CORE_FILE}, and ${README_FILE}"
else
  perl -0777 -i -pe "
    s/^ \\* Version: .*/ * Version: ${RELEASE_VERSION}/m;
    s/define\\( 'BEEPBEEP_AI_VERSION', '[^']*' \\);/define( 'BEEPBEEP_AI_VERSION', '${RELEASE_VERSION}' );/;
    s/define\\( 'BBAI_VERSION', '[^']*' \\);/define( 'BBAI_VERSION', '${RELEASE_VERSION}' );/;
  " "${MAIN_FILE}"

  perl -0777 -i -pe "
    s/^\\s*define\\('BBAI_VERSION',\\s*defined\\('BEEPBEEP_AI_VERSION'\\)\\s*\\?\\s*BEEPBEEP_AI_VERSION\\s*:\\s*'[^']+'\\);/    define('BBAI_VERSION', defined('BEEPBEEP_AI_VERSION') ? BEEPBEEP_AI_VERSION : '${RELEASE_VERSION}');/m;
  " "${CORE_FILE}"

  REL_VERSION="${RELEASE_VERSION}" \
  REL_CHANGELOG_BLOCK="${CHANGELOG_BLOCK}" \
  REL_UPGRADE_BLOCK="${UPGRADE_BLOCK}" \
  perl -0777 -i -pe '
    my $v = $ENV{REL_VERSION};
    my $chg = $ENV{REL_CHANGELOG_BLOCK};
    my $upg = $ENV{REL_UPGRADE_BLOCK};

    s/^Stable tag:\s*.*/Stable tag: $v/m;

    s{(== Changelog ==\n\n)(.*?)(\n== [^\n]+ ==\n)}{
      my ($head, $body, $tail) = ($1, $2, $3);
      if ($body =~ /^= \Q$v\E =\n(?:\* .*\n)+(?:\n)?/m) {
        $body =~ s/^= \Q$v\E =\n(?:\* .*\n)+(?:\n)?/$chg/sm;
      } else {
        $body = $chg . $body;
      }
      $head . $body . $tail;
    }es;

    s{(== Upgrade Notice ==\n\n)(.*?)(\n== [^\n]+ ==\n|\z)}{
      my ($head, $body, $tail) = ($1, $2, $3);
      if ($body =~ /^= \Q$v\E =\n[^\n]*\n\n?/m) {
        $body =~ s/^= \Q$v\E =\n[^\n]*\n\n?/$upg/sm;
      } else {
        $body = $upg . $body;
      }
      $head . $body . $tail;
    }es;
  ' "${README_FILE}"
fi

printf '%s\n' "Release metadata update complete."
printf '%s\n' "- Version: ${RELEASE_VERSION}"
