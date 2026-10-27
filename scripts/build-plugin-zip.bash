#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG="beepbeep-ai-alt-text-generator"
BUILD_DIR="$ROOT_DIR/build"
OUTPUT_ZIP="${1:-$BUILD_DIR/$PLUGIN_SLUG.zip}"
# zip runs from a temp dir, so make the output path absolute.
case "$OUTPUT_ZIP" in
    /*) ;;
    *) OUTPUT_ZIP="$PWD/$OUTPUT_ZIP" ;;
esac
TMP_DIR="$(mktemp -d)"
STAGE_DIR="$TMP_DIR/$PLUGIN_SLUG"

cleanup() {
    rm -rf "$TMP_DIR"
}

trap cleanup EXIT

mkdir -p "$BUILD_DIR"
rm -f "$OUTPUT_ZIP"

# Build from the committed tree (git archive), never from the working copy, so
# untracked notes, local edits and a worktree's .git file cannot ship.
# BBAI_BUILD_FROM_WORKTREE=1 builds the working copy instead (local debugging only).
SRC_DIR="$ROOT_DIR"
if [ "${BBAI_BUILD_FROM_WORKTREE:-0}" != "1" ] && git -C "$ROOT_DIR" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    if [ -n "$(git -C "$ROOT_DIR" status --porcelain --untracked-files=no)" ]; then
        echo "WARNING: uncommitted changes are NOT in this zip (it is built from HEAD $(git -C "$ROOT_DIR" rev-parse --short HEAD))." >&2
    fi
    SRC_DIR="$TMP_DIR/src"
    mkdir -p "$SRC_DIR"
    git -C "$ROOT_DIR" archive --format=tar HEAD | tar -x -C "$SRC_DIR"
fi

# '.git' (no trailing slash) matches both a .git directory and the .git *file*
# a git worktree has, at any depth.
rsync -a \
    --exclude '.git' \
    --exclude 'HANDOVER.md' \
    --exclude 'HANDOVER*.md' \
    --exclude '*BRIEF*.md' \
    --exclude '*brief*.md' \
    --exclude '.github/' \
    --exclude '.editorconfig' \
    --exclude '.distignore' \
    --exclude 'composer.json' \
    --exclude 'composer.lock' \
    --exclude 'phpunit.xml' \
    --exclude 'phpunit.xml.dist' \
    --exclude '.phpunit.result.cache' \
    --exclude 'phpcs.xml' \
    --exclude 'phpcs.xml.dist' \
    --exclude '.phpcs.xml.dist' \
    --exclude '/*.py' \
    --exclude '/*.png' \
    --exclude '/*.zip' \
    --exclude '/*.log' \
    --exclude '.gitattributes' \
    --exclude '.gitignore' \
    --exclude '.claude/' \
    --exclude '.cursor/' \
    --exclude '.playwright-cli/' \
    --exclude '.playwright-mcp/' \
    --exclude '.vscode/' \
    --exclude 'build/' \
    --exclude 'dist/' \
    --exclude 'docs/' \
    --exclude 'output/' \
    --exclude 'playwright-report/' \
    --exclude 'scripts/' \
    --exclude 'sync-to-wpenv.bash' \
    --exclude 'gitignore' \
    --exclude 'tests/' \
    --exclude 'test-results/' \
    --exclude '.wp-env.json' \
    --exclude '.wporg-svn/' \
    --exclude 'AGENTS.md' \
    --exclude 'node_modules/' \
    --exclude 'package.json' \
    --exclude 'package-lock.json' \
    --exclude 'jest.config.js' \
    --exclude 'playwright.config.ts' \
    --exclude 'output-dashboard-after.png' \
    --exclude 'output-dashboard-before.png' \
    --exclude 'output-dashboard-final.png' \
    --exclude 'rescan-complete-feedback.png' \
    --exclude 'login-helper.js' \
    --exclude 'wp-login.js' \
    --exclude '.DS_Store' \
    --exclude '*/.DS_Store' \
    --exclude 'assets/img/screenshots/' \
    --exclude-from="$ROOT_DIR/.distignore" \
    "$SRC_DIR/" "$STAGE_DIR/"

(cd "$TMP_DIR" && zip -qr "$OUTPUT_ZIP" "$PLUGIN_SLUG")

# Guard: never ship git metadata or internal agent notes.
if unzip -Z1 "$OUTPUT_ZIP" | grep -Ei '(^|/)\.git(/|$)|(^|/)HANDOVER[^/]*\.md$|(^|/)AGENTS\.md$|brief[^/]*\.md$|(^|/)\.(claude|cursor)(/|$)|(^|/)node_modules/|(^|/)docs/|(^|/)tests/'; then
    echo "ERROR: $OUTPUT_ZIP contains git metadata, internal notes or dev files (listed above)" >&2
    rm -f "$OUTPUT_ZIP"
    exit 1
fi

echo "Created $OUTPUT_ZIP"
