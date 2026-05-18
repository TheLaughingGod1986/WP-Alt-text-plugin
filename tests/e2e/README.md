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

## Activation analytics QA

Use PostHog debug mode (`WP_DEBUG` or `BBAI_DEBUG_POSTHOG`) and confirm the browser console prints `[BBAI analytics] event_name payload` without secrets.

- Logged-out dashboard shows the signup/generate CTA and emits `generate_cta_shown`.
- Signup CTA emits `signup_cta_clicked`, form submit emits `signup_started`, success emits `signup_succeeded`, and validation/API errors emit `signup_failed`.
- Login CTA emits `login_cta_clicked` and `login_modal_opened`; submit/success/failure emit `login_submitted`, `login_succeeded`, and `login_failed`.
- Dashboard main generate button emits `generate_cta_clicked`; if it cannot start, it emits `generation_click_noop` with a reason.
- A successful generation emits `generation_started`, at least one `generation_progress_updated` for batch progress, and `generation_completed`.
- A failed queue or image generation emits `generation_failed` with `endpoint`, `response_status` where available, `error_code`, and `error_message`.
- ALT Library page view emits `alt_library_viewed`; library generate/regenerate/copy/filter/edit actions emit their matching `alt_library_*` events.
- Upgrade CTAs emit `upgrade_clicked`; hosted checkout attempts emit `checkout_started`, failed session creation emits `checkout_failed`, and checkout returns with `checkout=success` emit `payment_succeeded`.
