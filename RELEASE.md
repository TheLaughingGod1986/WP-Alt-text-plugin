# Release Process

## Directory Layout

```
WP-Alt-text-plugin/          ← Git repo root (runtime + source files only)
├── admin/                   ← Runtime code
├── includes/                ← Runtime code
├── assets/                  ← Runtime assets
└── .distignore              ← Exclusion list consumed by build script

../wp-plugin-tools/          ← Dev/release tooling (NOT shipped)
├── scripts/build-zip.sh     ← Release packaging script
└── release-pass-check.sh    ← Docker-based smoke + Plugin Check helper
```

## Rules

1. **Shell scripts and dev tooling must NEVER ship in the plugin ZIP.** Keep them outside the plugin repo (recommended: sibling `../wp-plugin-tools/`).

2. **The `tools/` and `scripts/` directories are excluded from distribution** via `.distignore` and explicit build-script pruning (defense in depth).

3. **All release ZIPs must be built via staging** — never `zip -r` from the repo root directly.

## Building a Release ZIP

```bash
../wp-plugin-tools/scripts/build-zip.sh
```

The script will:
- Stage only runtime files into a temp directory (excluding `*.sh`, `*.zip`, `*.md`, `tools/`, `.git/`, etc.)
- Fail the build if any forbidden files leak through
- Create a ZIP at `../plugin-builds/beepbeep-ai-alt-text-generator.zip`
- Verify the ZIP structure (single top-level folder, no scripts, no archives)

## Verifying

```bash
# List ZIP contents
unzip -Z1 ../plugin-builds/beepbeep-ai-alt-text-generator.zip

# Run full pass check (Docker-based)
ZIP_PATH='../plugin-builds/beepbeep-ai-alt-text-generator.zip' ../wp-plugin-tools/release-pass-check.sh
```

## Why Staging is Required

WordPress Plugin Check flags `application_detected` if the ZIP contains shell scripts (`.sh`), regardless of where they sit. Direct `zip -r` from the repo root will include dev scripts. The build script uses rsync with `.distignore` exclusions to guarantee a clean package.
