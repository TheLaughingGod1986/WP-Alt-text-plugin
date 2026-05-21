## Changelog

All notable changes to this plugin are documented in this file.

The format is loosely based on Keep a Changelog, but optimized for internal release notes.

### Unreleased

### 4.6.13 — 2026-05-21

- **Changed**: Updated the public WordPress.org changelog so the latest dashboard performance and compatibility release notes appear correctly.
- **Changed**: Rebuilt the release package from the current local dashboard assets.

### 4.6.12 — 2026-05-20

- **Changed**: Rebuilt and republished the WordPress.org package from the latest local dashboard assets so live installs receive the current dashboard speed improvements.
- **Changed**: Refreshed trunk and release tag metadata for the WordPress 7.0 compatible package.

### 4.6.11 — 2026-05-20

- **Changed**: Improved admin dashboard loading performance and reduced perceived wait time during dashboard refreshes.
- **Changed**: Confirmed WordPress 7.0 compatibility for the current release package.

### 4.6.10 — 2026-05-20

- **Changed**: Rebuilt the WordPress.org package from the latest dashboard assets so the live plugin matches the current local dashboard polish.
- **Changed**: Refreshed bundled admin CSS and JavaScript for the credit progress, review banner, Autopilot CTA, and PostHog analytics reliability updates.

### 4.6.9 — 2026-05-19

- **Changed**: Improved analytics and telemetry reliability for cleaner event attribution.


### 4.6.8 — 2026-05-18

- **Changed**: Restored the logged-in dashboard presentation and refined the review-ready status card with calmer action hierarchy, tighter progress spacing, and more balanced alignment.
- **Changed**: Added image-specific live feed entries to the bulk generation modal so users can see which images are currently being processed.
- **Changed**: Release-polished the WordPress.org banner and icon artwork with improved supporting-copy readability and final production QA.
- **Fixed**: Aligned the dashboard insight card buttons across Accessibility, Time Saved, and SEO cards.
- **Fixed**: Bumped the plugin version above the current WordPress.org release so active installs are not incorrectly flagged as outdated.

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
