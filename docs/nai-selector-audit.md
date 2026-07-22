# NAI Selector Ownership Audit

This audit records the current `.nai-*` selector ownership after the Dashboard migration and before the ALT Library migration. The goal is to separate shared design primitives from page-specific selectors without breaking legacy `bbai-*` JavaScript hooks.

## CSS Architecture

- `assets/css/nai-dashboard.css`: compatibility entrypoint for the existing WordPress enqueue handle. It imports the split files.
- `assets/css/nai/nai-tokens.css`: shared variables only.
- `assets/css/nai/nai-shell.css`: WordPress admin host integration, NAI topbar, account menu, signed-out shell, drawer, toast, and prototype shell interactions.
- `assets/css/nai/nai-components.css`: reusable cards, buttons, chips, typography helpers, progress/ring widgets, page headers, generic modal, and legacy upgrade modal skinning.
- `assets/css/nai/nai-dashboard.css`: Dashboard hero, health/stat panels, dashboard activity, and Dashboard-specific responsive rules.
- `assets/css/nai/nai-library.css`: Library filter row, filter pills, search field, and library row styles.
- `assets/css/nai/nai-settings.css`: Settings plan card, settings rows, and settings-specific large toggles.
- `assets/css/nai/nai-autopilot.css`: Autopilot page hero, preset cards, schedule rows, segmented controls, text areas, and automation controls.

## Shared Selectors

| Selector family | Owner | Dependencies / notes |
|---|---|---|
| `.nai-app` | `nai-shell.css` | Root shell wrapper and token scope. Also used by `assets/js/nai-dashboard.js` for shell interactions. |
| `.nai-topbar*` | `nai-shell.css` | Shared across NAI admin pages. Contains account, upgrade, and navigation affordances. |
| `.nai-user-menu*` | `nai-shell.css` | Account menu only. Logout keeps `data-action="logout"` for existing flow. |
| `.nai-signedout*` | `nai-shell.css` | Logged-out shell state. |
| `.nai-screen` | `nai-components.css` | Page content root. Token scope is defined in `nai-tokens.css`. |
| `.nai-card` | `nai-components.css` | Base card primitive used by all current NAI pages. |
| `.nai-btn*` | `nai-components.css` | Shared button primitive. Must not replace existing `data-action` contracts. |
| `.nai-chip*` | `nai-components.css` | Shared status pill primitive. |
| `.nai-mono`, `.nai-tnum`, `.nai-eyebrow` | `nai-components.css` | Shared typography utilities. |
| `.nai-progress*` | `nai-components.css` | Shared horizontal progress primitive. |
| `.nai-ring*` | `nai-components.css` | Shared donut/ring primitive. Currently Dashboard-owned in markup but reusable. |
| `.nai-thumb`, `.nai-pulse-dot`, `.nai-ambient-pulse` | `nai-components.css` | Shared visual utilities. |
| `.nai-page-header*` | `nai-components.css` | Shared page heading pattern used by Library, Settings, and Autopilot. |
| `.nai-modal-backdrop`, `.nai-modal` | `nai-components.css` | Legacy modal surface and upgrade modal compatibility. Shell-specific modal selectors also exist under `.nai-app`. |
| `.nai-drawer*`, `.nai-toast` | `nai-shell.css` | Dashboard shell interaction layer; scoped under `.nai-app`. |
| `.nai-icon-btn` | `nai-shell.css` | Shell modal close button. Candidate for later promotion if another page needs icon-only buttons. |

## Dashboard-Only Selectors

| Selector family | Owner | Dependencies / notes |
|---|---|---|
| `.nai-screen--dashboard` | Dashboard page | Page root modifier. |
| `.nai-hero*` | `nai-dashboard.css` | Dashboard command hero. JS opens the generated drawer from `.nai-hero--interactive`; do not rename without updating `assets/js/nai-dashboard.js`. |
| `.nai-row-2` | `nai-dashboard.css` | Dashboard layout grid. |
| `.nai-health*` | `nai-dashboard.css` | Dashboard coverage/stat card. |
| `.nai-autopilot*` | `nai-dashboard.css` | Dashboard Autopilot teaser card, not the Autopilot page. Name is risky because it overlaps with Autopilot page ownership. |
| `.nai-activity*` | `nai-dashboard.css` | Dashboard recent activity rows. |
| `.nai-footer-metrics` | `nai-dashboard.css` | Dashboard footer metrics. |
| `.nai-toggle*` inside Dashboard CSS | `nai-dashboard.css` | Small Dashboard toggle skin. Settings/Autopilot also use large toggle variants. Candidate for later shared toggle component. |

## Library-Only Selectors

| Selector family | Owner | Dependencies / notes |
|---|---|---|
| `.nai-screen--library` | Library page | Page root modifier. |
| `.nai-filter-row` | `nai-library.css` | Filter navigation layout. Legacy JS still expects `#bbai-review-filter-tabs button[data-filter]`, so this cannot become the sole filter contract yet. |
| `.nai-filter-pill*` | `nai-library.css` | Visual filter pills. Current markup uses anchors and `data-bbai-library-filter`; legacy filter scripts use different hooks. |
| `.nai-search*` | `nai-library.css` | Search wrapper/input. Input ID `#bbai-library-search` remains the functional hook. |
| `.nai-lib-row` | `nai-library.css` | Visual library row. Legacy JS expects `.bbai-library-row`, `.bbai-library-row-check`, and row state classes. Add compatibility classes before deeper migration. |

## Settings-Only Selectors

| Selector family | Owner | Dependencies / notes |
|---|---|---|
| `.nai-screen--settings` | Settings page | Page root modifier. |
| `.nai-plan-card*` | `nai-settings.css` | Plan/usage panels. |
| `.nai-set-section*` | `nai-settings.css` | Settings section panels. |
| `.nai-set-row*` | `nai-settings.css` | Settings form-like rows. |
| `.nai-toggle--lg`, `.nai-toggle--ok` | `nai-settings.css` | Also used by Autopilot. Candidate for promotion to shared components after confirming Dashboard toggle differences. |

## Autopilot-Only Selectors

| Selector family | Owner | Dependencies / notes |
|---|---|---|
| `.nai-screen--autopilot` | Autopilot page | Page root modifier. |
| `.nai-ap-*` | `nai-autopilot.css` | Autopilot hero, status, and sections. |
| `.nai-preset*` | `nai-autopilot.css` | Tone preset controls. |
| `.nai-seg*` | `nai-autopilot.css` | Segmented controls. |
| `.nai-textarea` | `nai-autopilot.css` | Autopilot prompt/text field. |
| `.nai-preview*` | `nai-autopilot.css` | Autopilot preview panel. |
| `.nai-sched-row*` | `nai-autopilot.css` | Scheduling rows. |
| `.nai-label*` | `nai-autopilot.css` | Autopilot field labels. Candidate for shared form component later. |

## Risky Shared Selectors

- `.nai-coverage*` is currently styled in `nai-dashboard.css` but used by `admin/partials/nai-library.php` as well. It should move to `nai-components.css` in a later focused pass after visual regression checks.
- `.nai-autopilot*` names a Dashboard card while `.nai-ap-*` names the Autopilot page. The Dashboard selector should eventually be renamed or aliased, but not during this pass because markup and visual tests already target the current Dashboard.
- `.nai-toggle*` is split across Dashboard and Settings/Autopilot variants. Promote a shared toggle primitive only after checking all three page states.
- `.nai-modal` exists both as legacy/global modal styling and as `.nai-app .nai-modal` shell modal styling. Keep the current cascade order until modal consumers are audited.
- `body:has(.nai-app)` is used for admin host cleanup. Keep a fallback strategy in mind for older embedded browsers, but do not change without testing WordPress admin layouts.
- The compatibility entrypoint uses CSS `@import`. This is intentionally low-risk for the current enqueue handle, but individual enqueues may be preferable later for cache/version control.

## Duplicate / Overlapping Selectors

- `.nai-btn*` is intentionally duplicated between `.nai-screen` and selected `.nai-app` contexts so shell buttons and page buttons share visuals.
- `.nai-toggle*` appears in both Dashboard and Settings CSS with different dimensions.
- `.nai-modal*` appears in component CSS and shell CSS with different scopes.
- `.nai-screen` appears across page CSS files as a scoping prefix; ownership remains shared, while page modifiers are page-owned.

## Legacy Selectors Still Active

These selectors are still consumed by production JavaScript and must be preserved during Library migration unless all consumers are updated and tested:

- `.bbai-library-row`
- `.bbai-library-row-check`
- `.bbai-library-row--hidden`
- `.bbai-library-row--processing`
- `.bbai-library-row--done`
- `.bbai-library-row--approve-pending`
- `.bbai-library-row--approve-out`
- `.bbai-library-container`
- `.bbai-library-main`
- `.bbai-library-review-queue`
- `.bbai-library-filter-empty`
- `#bbai-library-table-body`
- `#bbai-library-selection-bar`
- `#bbai-library-search`
- `#bbai-select-all`
- `#bbai-review-filter-tabs`
- `#bbai-batch-generate`
- `#bbai-batch-regenerate`
- `#bbai-batch-clear`
- `#bbai-upgrade-modal`
- `.bbai-regenerate-modal`
- `.bbai-bulk-progress-modal`

## Migration Guidance

Treat `.nai-*` as the visual API and `bbai-*`, IDs, `data-action`, AJAX action names, REST paths, nonces, and analytics attributes as the behavior API. The Library migration should add compatibility classes/attributes first, verify the current JS flows, then move visual markup into NAI components.
