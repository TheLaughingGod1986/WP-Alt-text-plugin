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

## Build ZIP

```bash
./scripts/build-release-zip.sh
```

The repo-local build script will:
- Stage runtime files into `dist/beepbeep-ai-alt-text-generator/` using `.distignore`
- Delete any stray `*.zip` files in the repo root before building
- Fail if forbidden files leak into the dist stage (`scripts/`, `tests/`, `tools/`, `dist/`, `.git*`, `.github/`, `.claude/`, `*.md`, `*.log`, `*.sh`, nested archives)
- Write the release ZIP to `../plugin-builds/beepbeep-ai-alt-text-generator.zip` (never the repo root)
- Validate the ZIP structure and contents, then print `No forbidden files in ZIP`

## Verify ZIP

```bash
./scripts/verify-release.sh
```

`scripts/verify-release.sh`:
- Validates ZIP contents (forbidden file scan + single top-level folder)
- Detects whether the plugin directory inside the running WordPress container is bind-mounted
- Fails fast with: `Plugin dir is bind-mounted; plugin-check will scan repo files not ZIP.`
- Reinstalls the plugin from the ZIP and runs `wp plugin check beepbeep-ai-alt-text-generator --format=table`
- Prints a final summary including SHA256, mount status, Plugin Check counts, activation status, debug.log status, and `READY_FOR_UPLOAD=YES/NO`

Optional fallback when the local WP container is bind-mounted:

```bash
VERIFY_DISPOSABLE_ON_MOUNT=1 ./scripts/verify-release.sh
```

This delegates to the disposable Docker validation helper (`../wp-plugin-tools/release-pass-check.sh`), which runs in a non-mounted container.

## Why Staging is Required

WordPress Plugin Check flags `application_detected` if the ZIP contains shell scripts (`.sh`), regardless of where they sit. Direct `zip -r` from the repo root will include dev scripts. The build script uses rsync with `.distignore` exclusions to guarantee a clean package.
