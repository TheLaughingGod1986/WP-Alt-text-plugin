# Connected Entitlement E2E Fixture

## Purpose

`tests/e2e/nai-entitlement-connected.spec.ts` proves that a real backend final-credit generation changes the WordPress admin UI from one remaining credit to exhausted state. The run is intentionally destructive to a dedicated test entitlement: it consumes one generation credit.

Use a dedicated test backend account and dedicated local wp-env site only. Never link this workflow to a customer account, a shared QA account whose credits matter, or a production WordPress installation.

## Required Environment

Export these values in the shell used for the fixture check and Playwright run:

```bash
export BBAI_E2E_BASE_URL=http://localhost:8888
export BBAI_E2E_CONNECTED_ENTITLEMENT=1
export BBAI_E2E_RUN_CONNECTED_GENERATION=1
export BBAI_E2E_ADMIN_USER=admin
export BBAI_E2E_ADMIN_PASS=password
```

The test accepts these aliases when needed:

```bash
export WP_BASE_URL=http://localhost:8888
export WP_ADMIN_USER=admin
export WP_ADMIN_PASS=password
```

Do not commit credentials. The opt-in flags mean the operator understands that the suite submits one real generation request against the configured test entitlement.

## Local wp-env State

Before preparing backend allowance:

1. Start the local WordPress environment at `http://localhost:8888`.
2. Sync the working plugin into the canonical wp-env slug:

   ```bash
   ./scripts/sync-to-wpenv-slug.bash
   ```

3. Log into the plugin inside local `wp-admin` and connect it to a dedicated backend test account/site.
4. Ensure the Library can show at least one image eligible for generation. The E2E suite seeds a minimal local attachment when a wp-env Docker CLI container is available; it does not seed or mutate backend account data.

The fixture checker refuses non-local WordPress URLs. This prevents accidentally initiating the workflow through a customer or production admin installation.

## Backend Fixture State

The connected backend account/site must be an isolated test record with canonical `entitlement_state` satisfying all of:

```json
{
  "token_limit": 50,
  "tokens_used_this_month": 49,
  "tokens_remaining": 1,
  "can_generate": true,
  "is_logged_in": true
}
```

`token_limit` may differ from `50` if the backend test plan uses another controlled allowance. In all cases, `tokens_used_this_month` must equal `token_limit - 1`, and `tokens_remaining` must be exactly `1`.

This repository does not expose a safe, test-only backend mutation endpoint or a verified database schema for resetting entitlement records. For that reason it does not include an automated preparation/reset script. Prepare and reset the dedicated record using the backend team's approved test-data process, restricted to that test account/site.

## Verify Before Running

The checker is read-only. It logs into local WordPress, calls the production plugin REST routes, reads canonical `entitlement_state`, and verifies the one-credit precondition without printing credentials or private tokens.

```bash
node scripts/check-connected-entitlement-fixture.js
```

Expected output includes a non-sensitive entitlement summary followed by:

```text
PASS: connected entitlement fixture is prepared with exactly one remaining credit.
```

The checker fails unless both destructive opt-in flags are explicitly set, the WordPress URL is local, the connected API returns canonical entitlement state, and exactly one credit remains.

For optional read-only confirmation that the linked site record exists in Supabase, use the existing broader inspector with service-role values supplied only through the local shell:

```bash
WP_BASE_URL="$BBAI_E2E_BASE_URL" \
WP_ADMIN_USER="$BBAI_E2E_ADMIN_USER" \
WP_ADMIN_PASS="$BBAI_E2E_ADMIN_PASS" \
RUN_GENERATE=false \
node scripts/verify-live-truth-safe.mjs
```

That inspection also requires `SUPABASE_URL` and `SUPABASE_SERVICE_ROLE_KEY`; do not place either value in repository files or terminal transcripts shared outside the test team.

## Run The Connected Suite

Run the fixture checker immediately before the destructive Playwright suite:

```bash
node scripts/check-connected-entitlement-fixture.js
npx playwright test tests/e2e/nai-entitlement-connected.spec.ts
```

The real sequence is:

1. Confirm one remaining canonical credit.
2. Generate once through the existing Library action path.
3. Assert canonical state becomes exhausted in Library and Dashboard.
4. Attempt the existing generation path after a controlled stale-client replay and confirm the backend denial preserves exhausted state.

The suite also verifies terminal bulk entitlement ingestion with a mocked terminal poll response through real loaded production JavaScript. It does not run a real bulk generation job.

## Cleanup And Reset

After a successful destructive run, the dedicated test entitlement should be exhausted:

```json
{
  "tokens_remaining": 0,
  "can_generate": false,
  "quota_state": "exhausted"
}
```

Reset procedure:

1. Confirm the record being reset belongs to the dedicated connected E2E account/site, not a customer or general QA account.
2. Use the approved backend test-data process to restore `tokens_used_this_month` to `token_limit - 1` for that isolated record.
3. Leave the local wp-env plugin connected to that same dedicated record.
4. Rerun `node scripts/check-connected-entitlement-fixture.js`; do not rerun Playwright until it reports `PASS`.

Do not create raw production SQL or automate service-role writes from this plugin repository until the backend provides an explicitly isolated fixture-reset contract with test-account guards.

## Validation Log

### 2026-05-26 - Local fixture not ready

The read-only checker was run against the linked local wp-env plugin instance. The usage and dashboard state endpoints returned agreeing canonical entitlement values. No account identifier, credential, token, or customer data was printed or used for mutation.

Observed canonical state:

```json
{
  "plan": "free",
  "token_limit": 50,
  "tokens_used_this_month": 1,
  "tokens_remaining": 49,
  "can_generate": true,
  "quota_state": "active",
  "is_logged_in": true
}
```

This state does not satisfy the required dedicated one-credit fixture (`tokens_remaining: 1`). The destructive final-credit generation and quota-denial tests were not run. The locally linked record must be confirmed as a dedicated test record and reset through an approved backend test-data process before the Carlo-style final-credit path can be validated live.

Safe verification completed during this attempt:

- `npx playwright test tests/e2e/nai-design.spec.ts`: `25 passed`.
- `BBAI_E2E_BASE_URL=http://localhost:8888 npx playwright test tests/e2e/nai-library-live.spec.ts`: `7 passed`.
- `BBAI_E2E_BASE_URL=http://localhost:8888 npx playwright test tests/e2e/nai-entitlement-connected.spec.ts`: `1 passed`, `2 skipped`; only the non-destructive mocked terminal bulk contract ran.

## Expected Skip Behaviour

Without `BBAI_E2E_CONNECTED_ENTITLEMENT=1` and `BBAI_E2E_RUN_CONNECTED_GENERATION=1`, the real generation and quota-denial cases skip without consuming credits. If opt-in flags are present but the account is not prepared with one remaining credit, those destructive cases also skip with an instruction to run the fixture checker. The non-destructive bulk terminal contract may still run against local wp-env.
