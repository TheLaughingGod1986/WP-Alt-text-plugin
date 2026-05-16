## Changelog

All notable changes to this plugin are documented in this file.

The format is loosely based on Keep a Changelog, but optimized for internal release notes.

### Unreleased

### 4.6.7 — 2026-05-16

- **Fixed**: Fatal error on dashboard load caused by `self::null` typo in `Plan_Helpers::get_plan_data()` — corrected to `null !== self::$cached_plan_data`.

### 4.6.6 — 2026-05-16

- **Changed**: Redesigned the logged-out/guest dashboard with a premium, high-converting layout — updated hero left card with donut chart and feature tiles, right card with benefit checklist, trust strip with four items and subtitles, and a full-width library preview table showing real AI suggestion previews.
- **Fixed**: Close button in the bulk progress completion modal now correctly dismisses the modal when the job has images ready to review (was routing to an unhandled action and silently doing nothing).
- **Fixed**: Primary action button text in the bulk progress completion modal is now white on the green gradient background (colour was not being applied, making "Review images" text invisible).

### 4.6.5 — 2026-05-15

- **Fixed**: Cleaned up browser console output by removing unconditional USAGE CHECK and generation result debug logs, and gating approve-all logging behind BBAI_DEBUG flag.
- **Fixed**: PostHog 401 retry flood resolved by adding on_xhr_error handler to opt out of capturing on Unauthorized responses.
- **Fixed**: aria-hidden accessibility warning resolved by blurring focused elements before setting aria-hidden on modal close.
- **Changed**: Added coloured icon circles to the three dashboard insight cards (Accessibility, Time Saved, SEO).

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
