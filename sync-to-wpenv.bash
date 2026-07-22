#!/bin/bash
# Auto-sync plugin files to wp-env on change.
# Usage: ./sync-to-wpenv.bash

SRC="/Users/ben/code/web/WP-Alt-text-plugin/"
DEST="/Users/ben/.wp-env/06fe8883b07a5e21412cec8c726b075e/WordPress/wp-content/plugins/beepbeep-ai-alt-text-generator/"
EXCLUDE="--exclude=.git --exclude=.gitattributes --exclude=.claude --exclude=.cursor --exclude=node_modules --exclude=sync-to-wpenv.bash"

echo "Watching $SRC for changes..."
echo "Syncing to $DEST"
echo "Press Ctrl+C to stop."
echo ""

# Initial sync
rsync -av --delete $EXCLUDE "$SRC" "$DEST" 2>&1 | tail -3

# Watch and sync on changes
fswatch -o -r --exclude='\.git' --exclude='node_modules' --exclude='\.claude' "$SRC" | while read -r _count; do
    rsync -av --delete $EXCLUDE "$SRC" "$DEST" 2>&1 | grep -v '^$' | tail -5
    echo "--- synced at $(date +%H:%M:%S) ---"
done
