#!/usr/bin/env bash
# Point Git at repo-root `gitignore` (no leading dot) so WordPress Plugin Check does not report hidden_files.
# Run once per clone: ./scripts/bootstrap-git-excludes.bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
if [[ ! -f "$ROOT/gitignore" ]]; then
	echo "Missing $ROOT/gitignore" >&2
	exit 1
fi
git -C "$ROOT" config core.excludesFile "$ROOT/gitignore"
echo "Set core.excludesFile -> $ROOT/gitignore"
