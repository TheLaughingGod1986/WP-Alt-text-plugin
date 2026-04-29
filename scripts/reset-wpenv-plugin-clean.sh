#!/usr/bin/env bash
set -euo pipefail

CANONICAL_SLUG="beepbeep-ai-alt-text-generator"
PLUGIN_ROOT="/var/www/html/wp-content/plugins"
CANONICAL_DIR="${PLUGIN_ROOT}/${CANONICAL_SLUG}"
CANONICAL_FILE="${CANONICAL_DIR}/beepbeep-ai-alt-text-generator.php"
SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

log() {
  printf '%s\n' "$*"
}

fail() {
  printf 'ERROR: %s\n' "$*" >&2
  exit 1
}

command -v docker >/dev/null 2>&1 || fail "docker is required but was not found."

CLI_CONTAINER="$(
  docker ps \
    --filter "label=com.docker.compose.service=cli" \
    --format '{{.ID}} {{.Names}}' |
    awk '$2 !~ /tests-cli/ { print $1; exit }'
)"

if [[ -z "${CLI_CONTAINER}" ]]; then
  fail "Could not find a running wp-env CLI container. Start wp-env first, then re-run this script."
fi

log "Using wp-env CLI container: ${CLI_CONTAINER}"

log ""
log "Plugin folders before cleanup:"
docker exec "${CLI_CONTAINER}" sh -lc "find '${PLUGIN_ROOT}' -maxdepth 1 -mindepth 1 -type d -print | sort" || true

log ""
log "Deactivating BeepBeep / ALT text duplicate plugin slugs if present..."
PLUGIN_SLUGS="$(
  docker exec "${CLI_CONTAINER}" wp plugin list --field=name --path=/var/www/html 2>/dev/null || true
)"

if [[ -n "${PLUGIN_SLUGS}" ]]; then
  while IFS= read -r slug; do
    [[ -z "${slug}" ]] && continue
    case "${slug}" in
      "${CANONICAL_SLUG}")
        ;;
      *beepbeep*|*bbai*|*alt-text*|*Alt-text*|*WP-Alt-text-plugin*)
        log "Destructive action: deactivating duplicate plugin slug '${slug}'"
        docker exec "${CLI_CONTAINER}" wp plugin deactivate "${slug}" --path=/var/www/html || true
        ;;
    esac
  done <<< "${PLUGIN_SLUGS}"
fi

log ""
log "Removing duplicate BeepBeep / ALT text plugin folders inside wp-env only..."
DUPLICATE_DIRS="$(
  docker exec "${CLI_CONTAINER}" sh -lc "
    for dir in '${PLUGIN_ROOT}'/*; do
      [ -d \"\$dir\" ] || continue
      base=\$(basename \"\$dir\")
      case \"\$base\" in
        '${CANONICAL_SLUG}')
          ;;
        *beepbeep*|*bbai*|*alt-text*|*Alt-text*|WP-Alt-text-plugin)
          printf '%s\n' \"\$dir\"
          ;;
      esac
    done
  "
)"

if [[ -n "${DUPLICATE_DIRS}" ]]; then
  while IFS= read -r duplicate_dir; do
    [[ -z "${duplicate_dir}" ]] && continue
    log "Destructive action: removing duplicate wp-env folder '${duplicate_dir}'"
    docker exec "${CLI_CONTAINER}" rm -rf "${duplicate_dir}"
  done <<< "${DUPLICATE_DIRS}"
else
  log "No duplicate BeepBeep / ALT text plugin folders found."
fi

log ""
log "Ensuring canonical plugin folder exists: ${CANONICAL_DIR}"
docker exec "${CLI_CONTAINER}" mkdir -p "${CANONICAL_DIR}"

CANONICAL_MOUNT_SOURCE="$(
  docker inspect "${CLI_CONTAINER}" \
    --format '{{range .Mounts}}{{if eq .Destination "/var/www/html/wp-content/plugins/beepbeep-ai-alt-text-generator"}}{{.Source}}{{end}}{{end}}'
)"

if [[ "${CANONICAL_MOUNT_SOURCE}" == "${SRC_DIR}" ]]; then
  log "Canonical folder is already bind-mounted from the source repo; skipping file copy to avoid modifying source files through the mount."
else
  log "Syncing current repo into canonical wp-env folder via tar copy..."
  log "Excluding: .git, node_modules, tests, reports, logs, *.zip, .wp-env"
  tar \
    --exclude='.git' \
    --exclude='node_modules' \
    --exclude='tests' \
    --exclude='reports' \
    --exclude='logs' \
    --exclude='*.zip' \
    --exclude='.wp-env' \
    -C "${SRC_DIR}" \
    -cf - . |
    docker exec -i "${CLI_CONTAINER}" tar -C "${CANONICAL_DIR}" -xf -
fi

if ! docker exec "${CLI_CONTAINER}" test -f "${CANONICAL_FILE}"; then
  fail "Canonical plugin file is missing after sync: ${CANONICAL_FILE}"
fi

log ""
log "Activating canonical plugin only: ${CANONICAL_SLUG}"
docker exec "${CLI_CONTAINER}" wp plugin activate "${CANONICAL_SLUG}" --path=/var/www/html

log ""
log "Flushing WordPress cache..."
docker exec "${CLI_CONTAINER}" wp cache flush --path=/var/www/html

log ""
log "Plugin folders after cleanup:"
docker exec "${CLI_CONTAINER}" sh -lc "find '${PLUGIN_ROOT}' -maxdepth 1 -mindepth 1 -type d -print | sort"

log ""
log "Final plugin list:"
docker exec "${CLI_CONTAINER}" wp plugin list --path=/var/www/html
