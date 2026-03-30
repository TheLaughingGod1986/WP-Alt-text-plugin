# Design system reference

Lightweight map of primitives and rules. **Do not add a second token layer** (no new `--bbai-library-*` palettes in PHP inline styles; extend foundation tokens instead).

---

## 1. Foundations / tokens

| Layer | File | Use |
|--------|------|-----|
| Unified scale | `assets/css/unified.css` (built from `assets/css/unified/_tokens.css`) | `--bbai-space-*`, `--bbai-text-*`, `--bbai-primary`, borders, radii |
| Admin semantic | `assets/css/system/bbai-admin-foundation-tokens.css` | `--bbai-admin-color-*`, `--bbai-admin-space-*`, typography aliases |
| Workflow status | Same file + `bbai-admin-semantic-status.css` | `--bbai-status-optimized-*`, `--bbai-status-needs-review-*`, `--bbai-status-missing-*` |

**Rule:** New colors/spacing for admin UI → add or reuse a token in foundation or unified; avoid raw hex in new partials.

---

## 2. Buttons

| Class family | Defined in |
|--------------|------------|
| `bbai-btn`, `bbai-btn-primary`, `bbai-btn-secondary`, `bbai-btn-sm`, … | `bbai-admin-controls.css` |
| `bbai-ds-btn*` | Aliases in same file |
| Toolbar / icon | `bbai-btn--icon`, `bbai-toolbar__icon-btn` |

**Rule:** Use `bbai-btn` + variant. No page-specific button box-shadow or radius unless documented in this file.

---

## 3. Links

| Class | Use |
|-------|-----|
| `bbai-link`, `bbai-link--muted`, `bbai-link-btn`, `bbai-link-sm` | Muted vs action links; link-styled buttons |

---

## 4. Inputs, textareas, selects

| Class | Notes |
|-------|--------|
| `bbai-input`, `bbai-textarea`, `bbai-select` | Pair with labels using `bbai-section-label` / form patterns in Settings |
| `bbai-ds-input`, … | DS aliases |

Focus rings are centralized in `bbai-admin-controls.css`.

---

## 5. Cards / surfaces

| Class | Notes |
|-------|--------|
| `bbai-card` | Default shell: radius, border, shadow (`bbai-admin-surfaces.css`) |
| `bbai-card--soft` | Muted background |
| `bbai-card--pad-md`, etc. | Padding utilities |

**Rule:** Billing / insights / contributors should compose `bbai-card` or shared surface variables, not one-off borders.

---

## 6. Section headers

Compose:

- Container: `bbai-section-header` + often `bbai-ui-section-header`
- Eyebrow: `bbai-section-label`
- Title: `bbai-section-title`
- Description: `bbai-section-description` or `bbai-section-meta`

Typography scale lives in `bbai-section-header.css`. Surfaces file defines flex layout for headers.

---

## 7. Filters / badges

| Concern | System |
|---------|--------|
| Filter pills / tabs | `bbai-admin-semantic-status.css` + library filter classes (`bbai-alt-review-filters__*`) |
| Status badges | `bbai-badge`, `bbai-badge--optimized`, `bbai-badge--needs-review`, `bbai-badge--missing`, `bbai-badge--neutral` |
| Score pills | `bbai-score-pill` |

**Rule:** Semantic colours for ALT workflow → always from `--bbai-status-*`, not ad hoc oranges/reds/greens.

---

## 8. Tables / rows

| Pattern | File |
|---------|------|
| Semantic `<table>` | `.bbai-table` in `bbai-admin-table-workspace.css` |
| Workspace / library rows | `.bbai-row`, `.bbai-row-actions`, rail modifiers |
| Table meta / pagination | `.bbai-table-meta` (library usage) |

**Rule:** New data tables on dashboard pages → use `bbai-table` + tokens for header/hover cells.

---

## 9. Semantic states

| State | Token group |
|-------|-------------|
| Success / optimized | `--bbai-status-optimized-*` |
| Warning / needs review | `--bbai-status-needs-review-*` |
| Error / missing | `--bbai-status-missing-*` |
| Neutral / info | `--bbai-ds-status-neutral-*`, `--bbai-ds-status-info-*` |

Usage insights tones (`healthy` / `warning` / `danger`) map to these in `bbai-admin-page-adoption.css`.

---

## 10. Page map (high level)

| Screen | Main partials / CSS |
|--------|---------------------|
| Dashboard | `dashboard-body.php`, `status-card-refresh.css`, dashboard components |
| ALT Library | `library-tab.php`, `library-workspace.php`, `bbai-admin-library-workspace-polish.css`, workspace inline (see backlog) |
| Analytics | `analytics-tab.php`, `analytics-page.css`, UI components |
| Usage | `credit-usage-content.php`, `bbai-admin-page-adoption.css`, `unified` `.bbai-credit-usage-page` |
| Settings | `settings-tab.php`, `bbai-admin-surfaces` (plan card), shared cards |

---

## 11. Anti-patterns

1. **Inline `<style>` blocks** in PHP for layout or colour — extract to `assets/css/system/` or feature CSS and enqueue (exception: tiny one-off hidden in workspace is last resort; must be commented).
2. **Duplicate page wrappers** — `body.bbai-dashboard` + `bbai-content-shell` already normalize width; avoid per-page `#wpcontent` hacks unless shared in `status-card-refresh.css`.
3. **Page-only button styles** — use controls + modifiers; scope with a data-attribute or BEM block only if unavoidable.
4. **New semantic colours** — must extend `--bbai-status-*` or `--bbai-admin-semantic-*`, not `#f59e0b` literals in new code.
5. **Parallel class systems** — prefer `bbai-section-title` over bespoke `__title` without the shared class when the element is a section heading.

---

## 12. Known backlog (audit)

- `library-workspace.php` still contains a large inline `<style id="bbai-library-workspace-styles">` with local `--bbai-library-*` variables; **migrate incrementally** to foundation tokens + enqueued CSS.
- Other inline styles: `debug-tab.php`, `dashboard-body.php` (minimal), `dashboard-logged-out.php`, `upgrade-panel.php` — triage per file.
- `unified.css` / `_pages.css` retain legacy `.bbai-credit-usage-page` rules; **page-adoption** layers on top — consolidation is a follow-up with visual QA.
