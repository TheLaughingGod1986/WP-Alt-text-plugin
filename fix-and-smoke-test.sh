#!/usr/bin/env bash
###############################################################################
# fix-and-smoke-test.sh
#
# Single script that:
#   1. Finds the WordPress container
#   2. Clones it via `docker run` WITHOUT the plugin bind-mount
#   3. Verifies mount is gone
#   4. Installs plugin from ZIP via WP-CLI
#   5. Runs wp plugin check
#   6. Tails debug.log
#   7. Prints READY_FOR_UPLOAD=YES or READY_FOR_UPLOAD=NO
#
# Env overrides:
#   CID            – source container ID/name  (auto-detected)
#   PLUGIN_SLUG    – default: beepbeep-ai-alt-text-generator
#   ZIP_PATH       – default: auto-search ../plugin-builds/
#   NEW_NAME       – name for recreated container (default: wp-smoke-test)
###############################################################################
set -euo pipefail

PLUGIN_SLUG="${PLUGIN_SLUG:-beepbeep-ai-alt-text-generator}"
PLUGIN_DEST="/var/www/html/wp-content/plugins/${PLUGIN_SLUG}"
NEW_NAME="${NEW_NAME:-wp-smoke-test}"
REMOTE_ZIP="/tmp/${PLUGIN_SLUG}.zip"
PASS=true
TMPDIR_FIX=$(mktemp -d)
trap 'rm -rf "$TMPDIR_FIX"' EXIT

log()  { echo "==> $*"; }
info() { echo "    $*"; }
fail() { echo "FAIL: $*" >&2; PASS=false; }
die()  { echo "FATAL: $*" >&2; echo ""; echo "READY_FOR_UPLOAD=NO"; exit 1; }

wp_exec() {
  docker exec "$NEW_CID" wp --allow-root --path=/var/www/html "$@"
}

fetch_cmd() {
  if docker exec "$1" which curl >/dev/null 2>&1; then
    echo "curl -sfL -o"
  elif docker exec "$1" which wget >/dev/null 2>&1; then
    echo "wget -qO"
  else
    echo ""
  fi
}

###############################################################################
# PHASE 1 — LOCATE SOURCE CONTAINER
###############################################################################
log "Phase 1: Locating WordPress container..."

if [[ -z "${CID:-}" ]]; then
  for filter in \
    "label=com.docker.compose.service=wordpress" \
    "ancestor=wordpress"; do
    CID=$(docker ps --filter "$filter" --format '{{.ID}}' | head -1)
    [[ -n "$CID" ]] && break
    CID=$(docker ps -a --filter "$filter" --format '{{.ID}}' | head -1)
    [[ -n "$CID" ]] && break
  done
fi
[[ -z "${CID:-}" ]] && die "Could not find a WordPress container. Set CID env var."
info "Source container: $CID"
info "Status: $(docker inspect "$CID" --format '{{.State.Status}}')"

###############################################################################
# PHASE 2 — INSPECT CONTAINER CONFIG (using temp files for safe JSON handling)
###############################################################################
log "Phase 2: Inspecting container configuration..."

IMAGE=$(docker inspect "$CID" --format '{{.Config.Image}}')
info "Image: $IMAGE"

# Dump full inspect JSON to temp file for reliable parsing
docker inspect "$CID" > "$TMPDIR_FIX/inspect.json"

# Use python3 to extract everything we need in one pass
python3 - "$TMPDIR_FIX/inspect.json" "$PLUGIN_DEST" "$TMPDIR_FIX" <<'PYEOF'
import json, sys, os

inspect_file = sys.argv[1]
plugin_dest  = sys.argv[2]
outdir       = sys.argv[3]

with open(inspect_file) as f:
    data = json.load(f)[0]

hc = data.get("HostConfig", {})
config = data.get("Config", {})
net_settings = data.get("NetworkSettings", {})

# --- Binds: split into plugin mount vs retained ---
binds = hc.get("Binds") or []
retained_binds = []
found_plugin_mount = False
for b in binds:
    parts = b.split(":")
    dest = parts[1] if len(parts) >= 2 else ""
    if dest.rstrip("/") == plugin_dest.rstrip("/"):
        found_plugin_mount = True
        with open(os.path.join(outdir, "removed_mount.txt"), "w") as f:
            f.write(b)
    else:
        retained_binds.append(b)

with open(os.path.join(outdir, "has_plugin_mount.txt"), "w") as f:
    f.write("yes" if found_plugin_mount else "no")

with open(os.path.join(outdir, "retained_binds.json"), "w") as f:
    json.dump(retained_binds, f)

# --- Named volume mounts ---
mounts = data.get("Mounts") or []
vol_lines = []
for m in mounts:
    if m.get("Type") == "volume":
        mode = "rw" if m.get("RW", True) else "ro"
        vol_lines.append(f"{m['Name']}:{m['Destination']}:{mode}")
with open(os.path.join(outdir, "volumes.txt"), "w") as f:
    f.write("\n".join(vol_lines))

# --- Env vars (skip internal ones) ---
skip_prefixes = ("PATH=", "HOSTNAME=", "HOME=")
env_lines = [e for e in (config.get("Env") or []) if not e.startswith(skip_prefixes)]
with open(os.path.join(outdir, "env.txt"), "w") as f:
    f.write("\n".join(env_lines))

# --- Port bindings ---
pb = hc.get("PortBindings") or {}
port_lines = []
for container_port, hosts in pb.items():
    cp = container_port.split("/")[0]
    for h in (hosts or []):
        hip = h.get("HostIp", "")
        hp = h.get("HostPort", "")
        if hip:
            port_lines.append(f"{hip}:{hp}:{cp}")
        else:
            port_lines.append(f"{hp}:{cp}")
with open(os.path.join(outdir, "ports.txt"), "w") as f:
    f.write("\n".join(port_lines))

# --- Network ---
nets = (net_settings.get("Networks") or {})
net_name = next(iter(nets), "")
with open(os.path.join(outdir, "network.txt"), "w") as f:
    f.write(net_name)

print(f"  Binds total={len(binds)} retained={len(retained_binds)} plugin_mount={'FOUND' if found_plugin_mount else 'none'}")
print(f"  Volumes: {len(vol_lines)}, Ports: {len(port_lines)}, Env: {len(env_lines)}, Network: {net_name}")
PYEOF

HAS_PLUGIN_MOUNT=$(cat "$TMPDIR_FIX/has_plugin_mount.txt")
if [[ "$HAS_PLUGIN_MOUNT" == "yes" ]]; then
  info "Plugin bind mount detected: $(cat "$TMPDIR_FIX/removed_mount.txt")"
  info "Will be REMOVED in new container."
else
  info "No plugin bind mount found (may already be removed)."
fi

###############################################################################
# PHASE 3 — LOCATE ZIP
###############################################################################
log "Phase 3: Locating plugin ZIP..."

if [[ -z "${ZIP_PATH:-}" ]]; then
  SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
  for candidate in \
    "${SCRIPT_DIR}/${PLUGIN_SLUG}.zip" \
    "${SCRIPT_DIR}/../plugin-builds/${PLUGIN_SLUG}.zip" \
    "${SCRIPT_DIR}/../plugin-builds/${PLUGIN_SLUG}-local-artifact.zip"; do
    if [[ -f "$candidate" ]]; then
      ZIP_PATH="$candidate"
      break
    fi
  done
  if [[ -z "${ZIP_PATH:-}" ]]; then
    ZIP_PATH=$(find "${SCRIPT_DIR}/.." -maxdepth 3 -name "${PLUGIN_SLUG}.zip" -type f 2>/dev/null | head -1 || true)
  fi
fi

if [[ -z "${ZIP_PATH:-}" || ! -f "${ZIP_PATH:-}" ]]; then
  die "No ZIP found. Set ZIP_PATH env var. Searched for ${PLUGIN_SLUG}.zip"
fi
ZIP_PATH="$(cd "$(dirname "$ZIP_PATH")" && pwd)/$(basename "$ZIP_PATH")"
info "ZIP: $ZIP_PATH ($(du -h "$ZIP_PATH" | cut -f1 | xargs))"

###############################################################################
# PHASE 4 — STOP OLD + CREATE NEW CONTAINER
###############################################################################
log "Phase 4: Recreating WordPress container without plugin mount..."

# Stop source if running
OLD_STATUS=$(docker inspect "$CID" --format '{{.State.Status}}' 2>/dev/null || echo "unknown")
if [[ "$OLD_STATUS" == "running" ]]; then
  info "Stopping source container $CID..."
  docker stop "$CID" >/dev/null
fi

# Remove any previous smoke-test container
if docker inspect "$NEW_NAME" >/dev/null 2>&1; then
  info "Removing previous $NEW_NAME container..."
  docker rm -f "$NEW_NAME" >/dev/null 2>&1 || true
fi

# Remove old container to free port
info "Removing old container $CID to free port binding..."
docker rm "$CID" >/dev/null 2>&1 || true

# Build docker run args from extracted config
RUN_ARGS=(docker run -d --name "$NEW_NAME")

# Ports
while IFS= read -r line; do
  [[ -n "$line" ]] && RUN_ARGS+=(-p "$line")
done < "$TMPDIR_FIX/ports.txt"

# Env vars
while IFS= read -r line; do
  [[ -n "$line" ]] && RUN_ARGS+=(-e "$line")
done < "$TMPDIR_FIX/env.txt"

# Named volumes
while IFS= read -r line; do
  [[ -n "$line" ]] && RUN_ARGS+=(-v "$line")
done < "$TMPDIR_FIX/volumes.txt"

# Retained binds (everything except plugin mount)
while IFS= read -r line; do
  [[ -n "$line" ]] && RUN_ARGS+=(-v "$line")
done < <(python3 -c "
import json
with open('$TMPDIR_FIX/retained_binds.json') as f:
    for b in json.load(f):
        print(b)
")

# Network
NETWORK=$(cat "$TMPDIR_FIX/network.txt")
if [[ -n "$NETWORK" ]]; then
  RUN_ARGS+=(--network "$NETWORK")
fi

RUN_ARGS+=("$IMAGE")

info "Command: ${RUN_ARGS[*]}"
NEW_CID=$("${RUN_ARGS[@]}")
NEW_CID="${NEW_CID:0:12}"
info "New container: $NEW_CID ($NEW_NAME)"

# Wait for running state
info "Waiting for container..."
for i in $(seq 1 30); do
  STATUS=$(docker inspect "$NEW_CID" --format '{{.State.Status}}' 2>/dev/null || echo "unknown")
  if [[ "$STATUS" == "running" ]]; then
    break
  fi
  if [[ "$STATUS" == "exited" || "$STATUS" == "dead" ]]; then
    echo "--- Container logs (last 30 lines) ---"
    docker logs "$NEW_CID" 2>&1 | tail -30
    echo "---"
    die "Container exited unexpectedly (status: $STATUS)"
  fi
  sleep 1
done
info "Container status: running"

###############################################################################
# PHASE 5 — VERIFY MOUNT IS GONE
###############################################################################
log "Phase 5: Verifying plugin directory is NOT a mountpoint..."
sleep 2

MOUNT_CHECK=$(docker exec "$NEW_CID" sh -c "mount | grep '$PLUGIN_SLUG'" 2>/dev/null || true)
if [[ -n "$MOUNT_CHECK" ]]; then
  fail "Plugin directory is STILL a mountpoint: $MOUNT_CHECK"
  echo ""
  echo "READY_FOR_UPLOAD=NO"
  exit 1
fi

# Fallback: mountpoint command
MP_CHECK=$(docker exec "$NEW_CID" sh -c "
  if command -v mountpoint >/dev/null 2>&1 && [ -d '$PLUGIN_DEST' ]; then
    mountpoint -q '$PLUGIN_DEST' 2>/dev/null && echo mounted || echo clean
  else
    echo skip
  fi
" 2>/dev/null || echo "skip")

if [[ "$MP_CHECK" == "mounted" ]]; then
  fail "mountpoint(1) says plugin dir is still mounted!"
  echo ""
  echo "READY_FOR_UPLOAD=NO"
  exit 1
fi

info "Confirmed: plugin directory is NOT a mountpoint."

###############################################################################
# PHASE 6 — INSTALL WP-CLI
###############################################################################
log "Phase 6: Ensuring WP-CLI is available..."

if ! docker exec "$NEW_CID" which wp >/dev/null 2>&1; then
  FETCHER=$(fetch_cmd "$NEW_CID")
  if [[ -z "$FETCHER" ]]; then
    info "Installing curl inside container..."
    docker exec "$NEW_CID" bash -c "apt-get update -qq && apt-get install -y -qq curl >/dev/null 2>&1" || true
    FETCHER="curl -sfL -o"
  fi
  info "Downloading WP-CLI..."
  docker exec "$NEW_CID" bash -c "$FETCHER /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && chmod +x /usr/local/bin/wp"
fi

info "WP-CLI: $(wp_exec --version 2>&1 || echo 'unknown')"

###############################################################################
# PHASE 7 — WAIT FOR DATABASE
###############################################################################
log "Phase 7: Waiting for database..."

for i in $(seq 1 45); do
  if wp_exec db check >/dev/null 2>&1; then
    info "Database ready."
    break
  fi
  if [[ $i -eq 45 ]]; then
    fail "Database not reachable after 45s."
  fi
  sleep 1
done

###############################################################################
# PHASE 8 — INSTALL PLUGIN FROM ZIP
###############################################################################
log "Phase 8: Installing plugin from ZIP..."

docker cp "$ZIP_PATH" "${NEW_CID}:${REMOTE_ZIP}"
info "Copied ZIP into container."

wp_exec plugin deactivate "$PLUGIN_SLUG" 2>/dev/null || true
wp_exec plugin delete "$PLUGIN_SLUG" 2>/dev/null || true

INSTALL_OUTPUT=$(wp_exec plugin install "$REMOTE_ZIP" --force --activate 2>&1) && {
  info "Plugin installed and activated."
} || {
  fail "Plugin install failed!"
  echo "$INSTALL_OUTPUT"
}
echo "$INSTALL_OUTPUT" | grep -v "^$" | while IFS= read -r line; do info "  $line"; done

echo ""
echo "--- Plugin info ---"
wp_exec plugin get "$PLUGIN_SLUG" --format=table 2>/dev/null || fail "Could not query plugin"
echo ""

###############################################################################
# PHASE 9 — PLUGIN CHECK
###############################################################################
log "Phase 9: Running plugin-check..."

if ! wp_exec plugin is-installed plugin-check 2>/dev/null; then
  info "Installing plugin-check..."
  wp_exec plugin install plugin-check --activate 2>&1 || true
elif ! wp_exec plugin is-active plugin-check 2>/dev/null; then
  wp_exec plugin activate plugin-check 2>&1 || true
fi

echo ""
echo "--- plugin-check output ---"
PCHECK_EXIT=0
wp_exec plugin check "$PLUGIN_SLUG" --format=table 2>&1 || PCHECK_EXIT=$?
echo "--- end plugin-check (exit=$PCHECK_EXIT) ---"
echo ""

###############################################################################
# PHASE 10 — DEBUG LOG
###############################################################################
log "Phase 10: debug.log..."

docker exec "$NEW_CID" sh -c "
  if [ -f /var/www/html/wp-content/debug.log ]; then
    echo '--- Last 80 lines of debug.log ---'
    tail -80 /var/www/html/wp-content/debug.log
    echo '--- end debug.log ---'
  else
    echo '    No debug.log found.'
  fi
" 2>&1

###############################################################################
# PHASE 11 — VERDICT
###############################################################################
echo ""
echo "==========================================================================="
log "SUMMARY"
echo "==========================================================================="
PLUGIN_VER=$(wp_exec plugin get "$PLUGIN_SLUG" --field=version 2>/dev/null || echo "unknown")
PLUGIN_STATUS=$(wp_exec plugin get "$PLUGIN_SLUG" --field=status 2>/dev/null || echo "unknown")
info "Plugin:    $PLUGIN_SLUG"
info "Version:   $PLUGIN_VER"
info "Status:    $PLUGIN_STATUS"
info "Container: $NEW_CID ($NEW_NAME)"
info "Image:     $IMAGE"
info "Mount:     REMOVED"
info "ZIP used:  $ZIP_PATH"
info "Check:     exit=$PCHECK_EXIT"
echo "==========================================================================="
echo ""

if [[ "$PASS" == true && "$PLUGIN_STATUS" == "active" ]]; then
  echo "READY_FOR_UPLOAD=YES"
else
  echo "READY_FOR_UPLOAD=NO"
fi
