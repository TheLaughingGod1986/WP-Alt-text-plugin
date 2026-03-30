# BeepBeep AI — Admin design system

Internal reference for the shared WordPress admin UI (Phases 1–6 + hardening).

## Documents

| File | Purpose |
|------|---------|
| [REFERENCE.md](./REFERENCE.md) | Tokens, components, file map, anti-patterns |
| [REGRESSION.md](./REGRESSION.md) | Automated checks, snapshots, CI notes |
| [snapshots/](./snapshots/) | Optional manual baseline PNGs (see README there) |

## Source-of-truth CSS (load order)

Enqueued from `admin/traits/trait-core-assets.php` (dashboard shell pages):

1. `unified.css` — global tokens and legacy compositions  
2. `bbai-section-header.css` — section label / title / meta scale  
3. `bbai-admin-foundation-tokens.css` — `--bbai-admin-*`, `--bbai-status-*`  
4. `bbai-admin-controls.css` — buttons, links, inputs  
5. `bbai-admin-surfaces.css` — cards, section header layout  
6. `bbai-admin-design-system.css` — DS aliases  
7. `bbai-admin-semantic-status.css` — badges, filters, score pills, row rails  
8. `bbai-admin-table-workspace.css` — `.bbai-table`, `.bbai-row`, workspace rows  
9. `bbai-admin-page-adoption.css` — cross-page usage / shared shells  
10. `bbai-admin-library-workspace-polish.css` — library list/table polish (scoped `body.bbai-dashboard`)  

Feature CSS (e.g. `status-card-refresh.css`, `analytics-page.css`) layers on top; new work should not reintroduce parallel token sets.

## Body scope

Plugin admin screens add `body.bbai-dashboard` via `filter_admin_body_class` in `admin/class-bbai-core.php`. Most system rules are scoped with `body.bbai-dashboard` so core WP admin is unaffected.

## Contributing

See Cursor rule `.cursor/rules/bbai-design-system.mdc` and [REFERENCE.md](./REFERENCE.md) anti-patterns.
