#!/bin/bash
# Auto-sync plugin files to wp-env on change.
# Usage: ./sync-to-wpenv.bash

SRC="/Users/ben/code/web/WP-Alt-text-plugin/"
# wp-env bind-mounts the plugin from build/wpenv-plugin (see `docker inspect`),
# NOT the .wp-env/<hash> WordPress dir — sync there or the running site won't update.
DEST="/Users/ben/code/web/WP-Alt-text-plugin/build/wpenv-plugin/beepbeep-ai-alt-text-generator/"
# Exclude build/ so we never rsync the destination into itself (and never loop fswatch).
EXCLUDE="--exclude=.git --exclude=.gitattributes --exclude=.claude --exclude=.cursor --exclude=node_modules --exclude=build --exclude=sync-to-wpenv.bash"

echo "Watching $SRC for changes..."
echo "Syncing to $DEST"
echo "Press Ctrl+C to stop."
echo ""

# Initial sync
rsync -av --delete $EXCLUDE "$SRC" "$DEST" 2>&1 | tail -3

# Watch and sync on changes
fswatch -o -r --exclude='\.git' --exclude='node_modules' --exclude='\.claude' --exclude='/build' "$SRC" | while read -r _count; do
    rsync -av --delete $EXCLUDE "$SRC" "$DEST" 2>&1 | grep -v '^$' | tail -5
    echo "--- synced at $(date +%H:%M:%S) ---"
done
