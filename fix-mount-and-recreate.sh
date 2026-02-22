#!/usr/bin/env bash
# fix-mount-and-recreate.sh
# Removes the virtiofs bind-mount of the plugin directory from docker-compose.yml
# so that WP-CLI can manage the plugin folder normally, then recreates containers.
#
# Usage:
#   ./fix-mount-and-recreate.sh
#
# Env overrides:
#   CID            – container ID/name (auto-detected if omitted)
#   PLUGIN_SLUG    – default: beepbeep-ai-alt-text-generator
#   COMPOSE_FILE   – override compose file path (auto-detected from container labels)
set -euo pipefail

PLUGIN_SLUG="${PLUGIN_SLUG:-beepbeep-ai-alt-text-generator}"
PLUGIN_DEST="/var/www/html/wp-content/plugins/${PLUGIN_SLUG}"

# ── 1. Find the WordPress container ──────────────────────────────────────────
echo "==> Step 1: Locating WordPress container..."
if [[ -z "${CID:-}" ]]; then
  CID=$(docker ps -a --filter "label=com.docker.compose.service=wordpress" \
        --format '{{.ID}}' | head -1)
  if [[ -z "$CID" ]]; then
    CID=$(docker ps -a --filter "ancestor=wordpress" --format '{{.ID}}' | head -1)
  fi
fi

if [[ -z "$CID" ]]; then
  echo "ERROR: Could not find a WordPress container. Set CID env var." >&2
  exit 1
fi
echo "    Container: $CID"

# ── 2. Inspect mounts ───────────────────────────────────────────────────────
echo "==> Step 2: Checking mounts for plugin directory..."
MOUNT_JSON=$(docker inspect "$CID" --format '{{json .Mounts}}')
HAS_PLUGIN_MOUNT=$(echo "$MOUNT_JSON" | python3 -c "
import json, sys
mounts = json.load(sys.stdin)
for m in mounts:
    if m.get('Destination','').rstrip('/') == '${PLUGIN_DEST}'.rstrip('/'):
        print('yes')
        sys.exit(0)
print('no')
")

if [[ "$HAS_PLUGIN_MOUNT" != "yes" ]]; then
  echo "    No bind mount found for ${PLUGIN_DEST}. Nothing to fix."
  echo "    (Current mounts: $(echo "$MOUNT_JSON" | python3 -c "
import json,sys
for m in json.load(sys.stdin):
    print(f\"      {m.get('Source','')} -> {m.get('Destination','')} ({m.get('Type','')})\")" ))"
  exit 0
fi
echo "    Found bind mount targeting ${PLUGIN_DEST}"

# ── 3. Locate compose project + config ──────────────────────────────────────
echo "==> Step 3: Locating compose config..."
LABELS_JSON=$(docker inspect "$CID" --format '{{json .Config.Labels}}')
COMPOSE_PROJECT=$(echo "$LABELS_JSON" | python3 -c "import json,sys; print(json.load(sys.stdin).get('com.docker.compose.project',''))")
COMPOSE_WORKING_DIR=$(echo "$LABELS_JSON" | python3 -c "import json,sys; print(json.load(sys.stdin).get('com.docker.compose.project.working_dir',''))")
COMPOSE_CONFIG_FILES=$(echo "$LABELS_JSON" | python3 -c "import json,sys; print(json.load(sys.stdin).get('com.docker.compose.project.config_files',''))")

echo "    Project:     ${COMPOSE_PROJECT:-<unknown>}"
echo "    Working dir: ${COMPOSE_WORKING_DIR:-<unknown>}"
echo "    Config file: ${COMPOSE_CONFIG_FILES:-<unknown>}"

# Determine the compose file to edit
if [[ -n "${COMPOSE_FILE:-}" ]]; then
  echo "    Using COMPOSE_FILE override: $COMPOSE_FILE"
elif [[ -n "$COMPOSE_CONFIG_FILES" ]]; then
  COMPOSE_FILE="$COMPOSE_CONFIG_FILES"
else
  # Try common locations
  for candidate in \
    "${COMPOSE_WORKING_DIR}/docker-compose.yml" \
    "${COMPOSE_WORKING_DIR}/docker-compose.yaml" \
    "${COMPOSE_WORKING_DIR}/compose.yml" \
    "${COMPOSE_WORKING_DIR}/compose.yaml"; do
    if [[ -f "$candidate" ]]; then
      COMPOSE_FILE="$candidate"
      break
    fi
  done
fi

# ── 4. Edit or recreate compose file ────────────────────────────────────────
if [[ -n "${COMPOSE_FILE:-}" && -f "$COMPOSE_FILE" ]]; then
  echo "==> Step 4: Patching compose file: $COMPOSE_FILE"
  BACKUP="${COMPOSE_FILE}.bak.$(date +%Y%m%d-%H%M%S)"
  cp "$COMPOSE_FILE" "$BACKUP"
  echo "    Backup saved: $BACKUP"

  # Remove lines that mount to the plugin directory
  # Matches patterns like:  - ./:/var/www/html/wp-content/plugins/beepbeep-ai-alt-text-generator
  #                    or:  - .:/var/www/html/wp-content/plugins/beepbeep-ai-alt-text-generator:rw
  python3 - "$COMPOSE_FILE" "$PLUGIN_SLUG" <<'PYEOF'
import sys, re

compose_file = sys.argv[1]
slug = sys.argv[2]
pattern = re.compile(r'^\s*-\s*.+/wp-content/plugins/' + re.escape(slug) + r'\b.*$')

with open(compose_file, 'r') as f:
    lines = f.readlines()

new_lines = [l for l in lines if not pattern.match(l)]
removed = len(lines) - len(new_lines)

with open(compose_file, 'w') as f:
    f.writelines(new_lines)

print(f"    Removed {removed} mount line(s) matching plugin slug.")
PYEOF

else
  echo "==> Step 4: Compose file not found – creating a minimal one..."
  COMPOSE_FILE="${COMPOSE_WORKING_DIR:-$(pwd)}/docker-compose.yml"

  # Extract current env vars, image, ports from the container to rebuild
  IMAGE=$(docker inspect "$CID" --format '{{.Config.Image}}')
  ENV_JSON=$(docker inspect "$CID" --format '{{json .Config.Env}}')

  # Get the volume name for /var/www/html
  VOL_NAME=$(echo "$MOUNT_JSON" | python3 -c "
import json, sys
for m in json.load(sys.stdin):
    if m.get('Destination','') == '/var/www/html' and m.get('Type','') == 'volume':
        print(m['Name']); break
" || true)

  # Also find db container info
  DB_CID=$(docker ps -a --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
           --filter "label=com.docker.compose.service=db" --format '{{.ID}}' | head -1)
  DB_IMAGE=$(docker inspect "$DB_CID" --format '{{.Config.Image}}' 2>/dev/null || echo "mysql:8.0")

  cat > "$COMPOSE_FILE" <<YAML
services:
  db:
    image: ${DB_IMAGE}
    volumes:
      - db_data:/var/lib/mysql
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: somewordpress
      MYSQL_DATABASE: wordpress
      MYSQL_USER: wordpress
      MYSQL_PASSWORD: wordpress

  wordpress:
    image: ${IMAGE}
    depends_on:
      - db
    ports:
      - "8080:80"
    restart: unless-stopped
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_USER: wordpress
      WORDPRESS_DB_PASSWORD: wordpress
      WORDPRESS_DB_NAME: wordpress
    volumes:
      - ${VOL_NAME:-wordpress_data}:/var/www/html

  phpmyadmin:
    image: phpmyadmin/phpmyadmin
    depends_on:
      - db
    ports:
      - "8081:80"
    environment:
      PMA_HOST: db

volumes:
  db_data:
  ${VOL_NAME:-wordpress_data}:
    external: true
YAML
  echo "    Created $COMPOSE_FILE (without plugin bind mount)"
fi

# ── 5. Recreate containers ──────────────────────────────────────────────────
echo "==> Step 5: Recreating containers..."
COMPOSE_ARGS=()
if [[ -n "${COMPOSE_PROJECT:-}" ]]; then
  COMPOSE_ARGS+=(-p "$COMPOSE_PROJECT")
fi
# Handle multiple config files (comma-separated)
IFS=',' read -ra FILES <<< "${COMPOSE_FILE}"
for f in "${FILES[@]}"; do
  COMPOSE_ARGS+=(-f "$f")
done

echo "    Running: docker compose ${COMPOSE_ARGS[*]} down"
docker compose "${COMPOSE_ARGS[@]}" down || {
  echo "WARN: docker compose down failed, trying to stop/rm container directly..."
  docker stop "$CID" 2>/dev/null || true
  docker rm "$CID" 2>/dev/null || true
}

echo "    Running: docker compose ${COMPOSE_ARGS[*]} up -d"
docker compose "${COMPOSE_ARGS[@]}" up -d

# ── 6. Verify ────────────────────────────────────────────────────────────────
echo "==> Step 6: Verifying fix..."
sleep 3

NEW_CID=$(docker compose "${COMPOSE_ARGS[@]}" ps -q wordpress 2>/dev/null | head -1)
if [[ -z "$NEW_CID" ]]; then
  NEW_CID=$(docker ps --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
            --filter "label=com.docker.compose.service=wordpress" --format '{{.ID}}' | head -1)
fi

if [[ -z "$NEW_CID" ]]; then
  echo "ERROR: WordPress container not running after recreation." >&2
  exit 1
fi
echo "    New container: $NEW_CID"

STILL_MOUNTED=$(docker exec "$NEW_CID" sh -c "mount | grep '${PLUGIN_SLUG}'" 2>/dev/null || true)
if [[ -n "$STILL_MOUNTED" ]]; then
  echo "ERROR: Plugin directory is still a mountpoint!" >&2
  echo "    $STILL_MOUNTED" >&2
  exit 1
fi

echo "    Plugin directory is NOT a mountpoint. Fix verified."
echo ""
echo "==> Done. WordPress container recreated without plugin bind mount."
echo "    Container ID: $NEW_CID"
