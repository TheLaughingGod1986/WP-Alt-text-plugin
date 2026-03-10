#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG="beepbeep-ai-alt-text-generator"
BUILD_DIR="$ROOT_DIR/build"
OUTPUT_ZIP="${1:-$BUILD_DIR/$PLUGIN_SLUG.zip}"
TMP_DIR="$(mktemp -d)"
STAGE_DIR="$TMP_DIR/$PLUGIN_SLUG"

cleanup() {
    rm -rf "$TMP_DIR"
}

trap cleanup EXIT

mkdir -p "$BUILD_DIR"
rm -f "$OUTPUT_ZIP"

rsync -a \
    --exclude '.git/' \
    --exclude '.vscode/' \
    --exclude 'build/' \
    --exclude 'scripts/' \
    --exclude '.gitignore' \
    --exclude '.gitattributes' \
    --exclude '.distignore' \
    --exclude '.DS_Store' \
    --exclude '*/.DS_Store' \
    --exclude 'assets/img/screenshots/' \
    "$ROOT_DIR/" "$STAGE_DIR/"

(cd "$TMP_DIR" && zip -qr "$OUTPUT_ZIP" "$PLUGIN_SLUG")

echo "Created $OUTPUT_ZIP"
