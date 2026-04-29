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

- Generate login storage state when needed:

```bash
cd tests/e2e
npx playwright codegen "$BBAI_E2E_BASE_URL/wp-admin" --save-storage=storageState.json
```

## Verification Notes

- `tests/e2e` tests skip when `BBAI_E2E_BASE_URL` is unset.
- For broader live backend/frontend truth checks, use `scripts/verify-live-truth-safe.mjs` with its required WordPress and Supabase env vars.
- TODO: `docs/design-system/REGRESSION.md` references `./scripts/ds-audit.sh`, but that script is not present in this checkout. Verify the intended replacement before documenting it as a supported command.
