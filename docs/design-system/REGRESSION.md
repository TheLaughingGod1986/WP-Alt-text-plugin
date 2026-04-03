# Design system regression guide

Protects the shared admin shell (typography rhythm, layout, tokens) from accidental drift.

## 1. Automated (Playwright)

Location: `tests/e2e/`

### Setup

```bash
cd tests/e2e
npm install
npx playwright install
```

### Environment

| Variable | Required | Purpose |
|----------|----------|---------|
| `BBAI_E2E_BASE_URL` | Yes for CI | e.g. `http://127.0.0.1:8888` |
| `BBAI_E2E_STORAGE_STATE` | Recommended | Path to `storageState.json` after WP login (see below) |

Copy `tests/e2e/env.e2e.example` to `.env` and adjust (Playwright does not load `.env` by default — export vars or use `dotenv` in config; the provided config reads `process.env`).

### Auth storage state (one-time)

1. Run WP locally with the plugin active.  
2. Log in as an admin user that can open BeepBeep AI screens.  
3. Generate storage:

```bash
cd tests/e2e
npx playwright codegen "$BBAI_E2E_BASE_URL/wp-admin" --save-storage=storageState.json
```

Log in in the browser, then close. Commit **`storageState.json` only for private fixtures repos**; for public CI use encrypted secrets or a dedicated test user + ephemeral env.

### Run

```bash
cd tests/e2e
BBAI_E2E_BASE_URL=http://127.0.0.1:8888 BBAI_E2E_STORAGE_STATE=./storageState.json npm test
```

### What the smoke spec asserts

`design-system.admin.spec.ts` (when env is set):

- `body.bbai-dashboard` on main plugin pages (dashboard, library, analytics, usage, settings).  
- `.bbai-content-shell` visible (layout shell).  
- Optional: screenshot to `test-results/` for manual diff (configure in spec).

Tests **skip** when `BBAI_E2E_BASE_URL` is unset so default clones do not fail CI.

## 2. Static audit script

From repo root:

```bash
./scripts/ds-audit.sh
```

Flags inline `<style` in admin partials and duplicate high-risk patterns (raw hex in new PHP). Exit 0 = informational; use in pre-commit or weekly.

## 3. Manual snapshot checklist (release)

On a clean admin user at **1280×800** and **390×844**:

| Page | Check |
|------|--------|
| Dashboard | Command hero, cards, primary CTA hierarchy |
| ALT Library | Filters, table/list, row actions, sticky bar |
| Analytics | Banner, metric grid, trend card |
| Usage | Insights card tones, activity table, billing block |
| Settings | Section cards, form controls alignment |

Capture PNGs into `docs/design-system/snapshots/YYYY-MM/` (optional repo policy).

## 4. CI recommendation

- **PR:** run `./scripts/ds-audit.sh` + `php -l` on changed PHP.  
- **Nightly / pre-release:** Playwright with secrets-provided `STORAGE_STATE` against staging URL.  
- Do not block public CI on Playwright until a headless WP + auth strategy exists.
