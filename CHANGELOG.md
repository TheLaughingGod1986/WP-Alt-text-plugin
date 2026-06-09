## Changelog

All notable changes to this plugin are documented in this file.

The format is loosely based on Keep a Changelog, but optimized for internal release notes.

### 4.6.14 — 2026-05-29

- **Changed**: Refactored the nAi dashboard into smaller PHP components and focused JavaScript modules while preserving existing dashboard behaviour.
- **Changed**: Split ALT Library generation state helpers into focused legacy-compatible modules for locks, notices, bulk orchestration, API request construction, and row/count/filter state.
- **Fixed**: Added regression coverage and release compliance cleanup for dashboard and ALT Library generation flows.

### 4.6.4 — 2026-05-14

- **Fixed**: Background generation persistence now survives page navigation, refreshes, and multi-tab sessions across all plugin pages.
- **Fixed**: In-browser background jobs now sync progress via the shared bbaiJobState subscription, removing duplicate polling and stale state after navigation.
- **Fixed**: ALT Library pagination now automatically reloads to show remaining filtered items after approving all visible items on the current page.
- **Fixed**: Floating job widget "Review images" button is now visible after generation completes (WordPress admin CSS override was making text invisible).
- **Fixed**: Removed misleading "Review suggestions." suffix from the generation-complete widget status line.
- **Fixed**: Plugin Check `MissingTranslatorsComment` warnings resolved by adding translator comments to all i18n calls with printf placeholders.
- **Fixed**: Dashboard insight cards (Accessibility, Time Saved, SEO) now have horizontally aligned value headings and CTA buttons across all three cards.

### 4.6.3 — 2026-05-06

- **Changed**: Updated the WordPress.org large and small banners with correctly sized Better Alt Text, Better Accessibility artwork.
- **Changed**: Refreshed the WordPress.org thumbnail icon and improved readme SEO coverage for AI alt text, image SEO, accessibility, WooCommerce, and missing alt attributes.
- **Changed**: Polished the dashboard insight cards with clearer outcome copy, proof badges, stronger visual hierarchy, and aligned refreshed-state values.
- **Fixed**: Manual Re-scan library feedback now shows persistent inline completion or failure messaging after the scan finishes.
- **Fixed**: Scan completion messaging now reattaches if a dashboard render replaces the re-scan link.
- **Fixed**: All-optimised dashboard presentation, retention-strip copy, and state consistency after generation, review, approval, and polling.
- **Fixed**: Upgrade CTA and modal stacking edge cases around generation-complete and automation prompts.

### 4.6.2 — 2026-04-29

- **Changed**: Replaced the WordPress.org banner and thumbnail icon with refreshed Better Alt Text, Better Accessibility artwork.
- **Changed**: Standardised generation button loading states with one spinner, one clear label, hard disabled buttons, and duplicate-click protection.
- **Changed**: Improved the bulk generation modal with dynamic steps, real progress, live feed entries, compact layout, and clearer completion actions.
- **Changed**: Added contextual first-success, review, credit, and upgrade prompts that wait until meaningful moments instead of interrupting active work.
- **Changed**: Polished dashboard spacing, CTA alignment, retention-strip copy, and completion/review states.
- **Fixed**: Dashboard polling is now idempotent so unchanged state no longer flickers, replays completion animations, or replaces stable DOM.
- **Fixed**: Dashboard state stays in sync after generation and approval, including the donut, right card, credits card, and middle progress CTA.
- **Fixed**: Removed stale all-optimised, Done, and review-ready state leaks after approving or generating images.
- **Fixed**: Monthly credit usage display and progress bars now update from backend truth after generation.
- **Fixed**: Manual dashboard re-scans now show clear completion or error feedback instead of silently returning to the idle link.
- **Fixed**: Automatic optimisation CTAs are clickable, trackable, and connected to the existing upgrade modal.
- **Fixed**: Upgrade modal stacking from the generation-complete automation link now opens pricing above the dismissed progress modal.


### 4.6.1 — 2026-04-29

- **Changed**: Maintenance release.
