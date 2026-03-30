# BeepBeep AI — design system E2E smoke tests

Playwright checks that main plugin admin routes expose the shared shell (`body.bbai-dashboard`, `.bbai-content-shell`) and reasonable `.wrap` width.

## Quick start

```bash
npm install
npx playwright install chromium   # once
```

Without a running WordPress instance, tests **skip** (no `BBAI_E2E_BASE_URL`).

With WordPress:

```bash
export BBAI_E2E_BASE_URL=http://127.0.0.1:8888
# After logging in once, save storage (see ../../docs/design-system/REGRESSION.md):
export BBAI_E2E_STORAGE_STATE=./storageState.json
npm test
```

See `../../docs/design-system/REGRESSION.md` for CI and snapshot guidance.
