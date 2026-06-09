# AGENTS.md

## Repo Workflows

- Run once per clone to keep Git using the repo-root `gitignore` file that avoids WordPress Plugin Check `hidden_files` noise:

```bash
./scripts/bootstrap-git-excludes.bash
```

- Sync the plugin into `wp-env` under the correct slug folder before running Plugin Check or testing in a local WordPress instance:

```bash
./scripts/sync-to-wpenv-slug.bash
```

- Build a distributable plugin zip from the repo root:

```bash
./scripts/build-plugin-zip.bash
```

- Prepare a release by bumping the plugin version, updating `CHANGELOG.md` and `readme.txt`, building a zip, syncing to `wp-env`, and verifying the synced version:

```bash
./scripts/release.bash 4.6.2 "Short release note"
```

- Deploy an already-prepared release to WordPress.org SVN after the local release build passes:

```bash
WPORG_SLUG=beepbeep-ai-alt-text-generator \
WPORG_USER=your-wporg-username \
WPORG_PASS=your-wporg-password \
./scripts/deploy-wporg.bash 4.6.2 "Short release note"
```

- If `wp-env` has duplicate BeepBeep / alt-text plugin folders or slug collisions, clean the `wp-env` plugin directories and reactivate only the canonical slug:

```bash
./scripts/reset-wpenv-plugin-clean.sh
```

## E2E Smoke Tests

- Playwright tests live in `tests/e2e/`.
- Install dependencies from that directory:

```bash
cd tests/e2e
npm install
npx playwright install chromium
```

- Run smoke tests against a local WordPress instance after exporting the expected env vars:

```bash
cd tests/e2e
export BBAI_E2E_BASE_URL=http://127.0.0.1:8888
export BBAI_E2E_STORAGE_STATE=./storageState.json
npm test
```

- The checked-in example env file lives at `tests/e2e/env.e2e.example`; copy its values into your shell or a local `.env` before running Playwright.

- Generate login storage state when needed:

```bash
cd tests/e2e
npx playwright codegen "$BBAI_E2E_BASE_URL/wp-admin" --save-storage=storageState.json
```

- For the destructive connected-entitlement fixture flow, opt in explicitly, verify the local one-credit fixture, then run only the guarded spec:

```bash
cd tests/e2e
export BBAI_E2E_BASE_URL=http://127.0.0.1:8888
export BBAI_E2E_CONNECTED_ENTITLEMENT=1
export BBAI_E2E_RUN_CONNECTED_GENERATION=1
export BBAI_E2E_ADMIN_USER=admin
export BBAI_E2E_ADMIN_PASS=password
cd ../..
node scripts/check-connected-entitlement-fixture.js
npx playwright test tests/e2e/nai-entitlement-connected.spec.ts
```

## Verification Notes

- `tests/e2e` tests skip when `BBAI_E2E_BASE_URL` is unset.
- For broader live backend/frontend truth checks, use `scripts/verify-live-truth-safe.mjs` with its required WordPress and Supabase env vars.
- `scripts/check-connected-entitlement-fixture.js` refuses non-local WordPress URLs and fails unless both destructive opt-in flags are set.
- TODO: `docs/design-system/REGRESSION.md` references `./scripts/ds-audit.sh`, but that script is not present in this checkout. Verify the intended replacement before documenting it as a supported command.
