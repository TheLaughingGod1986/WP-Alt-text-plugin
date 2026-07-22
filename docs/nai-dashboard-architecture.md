# nAi Dashboard Architecture

The connected BeepBeep AI dashboard is composed in `admin/partials/nai-dashboard.php`.
That file prepares dashboard state, derives view-only values, opens the shared nAi shell,
and then includes focused PHP components from `admin/components/dashboard/`.

## PHP Components

- `dashboard-quota-card.php`: exhausted entitlement notice.
- `dashboard-hero.php`: today's pass hero, queue preview, and primary dashboard CTA.
- `dashboard-stats.php`: library health and Autopilot card.
- `dashboard-progress.php`: library coverage progress bar.
- `dashboard-recent-activity.php`: latest uploads, generated items, and remaining work.
- `dashboard-actions.php`: footer metrics and allowance summary.
- `dashboard-prototype-overlays.php`: prototype-only onboarding, drawer, paywall, sign-out, toast, and tweak controls.

Guest/trial dashboard rendering still flows through the existing partials selected by
`admin/partials/dashboard-body.php`.

## JavaScript Modules

The active nAi dashboard scripts are plain browser scripts, dependency-ordered through
WordPress enqueue calls in `admin/traits/trait-core-assets.php`.

- `assets/js/nai-shared/dom.js`: show/hide helpers.
- `assets/js/nai-shared/modals.js`: modal open/close helpers.
- `assets/js/nai-shared/notices.js`: toast helper.
- `assets/js/nai-dashboard/state.js`: DOM bootstrap state.
- `assets/js/nai-dashboard/quota.js`: prototype paywall copy and opening.
- `assets/js/nai-dashboard/scan.js`: prototype onboarding/scan simulation.
- `assets/js/nai-dashboard/generation.js`: prototype generation drawer simulation.
- `assets/js/nai-dashboard/events.js`: event delegation and keyboard handling.
- `assets/js/nai-dashboard/index.js`: small initializer.

The legacy dashboard bundle remains in place for the older admin surfaces and existing
global generation/review flows. This refactor does not change AJAX action names, REST
routes, nonces, permissions, quota sources, or telemetry event names.
