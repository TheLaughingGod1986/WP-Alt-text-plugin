# CHANGELOG_DEV

## 2026-03-05

- Refactored locked credit actions to a single delegated flow in `assets/src/js/bbai-admin.js`:
  - Added one locked-action binding registry (`bindLockedAction`) and one delegated click guard.
  - Added centralized lock helpers (`isCreditsLocked`, `enforceOutOfCreditsBulkLocks`).
  - Added centralized modal controls (`openUpgradeModal(reason, context)` + `closeUpgradeModal()`).
- Added one reusable, accessible "Monthly credits used up" modal (focus trap, ESC close, restore trigger focus) for locked CTAs.
- Removed native disabled behavior for credit-locked primary actions in Dashboard and ALT Library markup:
  - Replaced with `aria-disabled="true"`, lock metadata (`data-bbai-locked-cta`, `data-bbai-lock-reason`), and `bbai-is-locked` styling.
- Updated Dashboard and ALT Library credit-limit UX copy:
  - Positive progress message ("You optimized X images this month 🎉").
  - Reset timing/copy and explicit reset-date label.
  - Added "What's next" context panel for missing vs all-caught-up states.
- Added server-localized admin lock state payload (`bbai_admin`) in `admin/traits/trait-core-assets.php`:
  - `credits.used`, `credits.limit`, `credits.resetDate` (ISO), `credits.daysLeft`
  - `counts.missing`, `urls.upgrade`, `isLocked`
- Updated dashboard lock interception in `assets/src/js/bbai-dashboard.js` so locked actions open the shared lock modal (no swallowed clicks).
- Added a default-prevented guard to dashboard global click handling to avoid duplicate modal handling for the same CTA click.
- Added missing ALT Library "What's next" locked CTA buttons (generate vs re-optimise) so panel actions trigger the same reusable upgrade modal.
- Updated lock styling in `assets/css/unified.css` to keep locked CTAs clickable and non-broken (removed hard pointer-event blocking for lock classes).
