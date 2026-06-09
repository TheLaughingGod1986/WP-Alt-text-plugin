# ALT Library JavaScript Architecture

This document locks down the Phase 2 ALT Library generation split. The extracted files are small browser globals, not bundled modules. `assets/js/bbai-admin.js` still owns the legacy workflows and delegates to these helpers when they are available.

## Enqueue Contract

The ALT Library helpers are enqueued only on the `bbai-library` admin page from `Core_Assets::enqueue_media_library_assets()` in `admin/traits/trait-core-assets.php`.

Current order:

1. `bbai-alt-library-generation-locks` -> `assets/js/alt-library/generation-locks.js`
2. `bbai-alt-library-generation-notices` -> `assets/js/alt-library/generation-notices.js`
3. `bbai-alt-library-bulk-selection` -> `assets/js/alt-library/bulk-selection.js`
4. `bbai-alt-library-row-updates` -> `assets/js/alt-library/row-updates.js`
5. `bbai-alt-library-state` -> `assets/js/alt-library/library-state.js`
6. `bbai-alt-library-api` -> `assets/js/alt-library/api.js`
7. `bbai-alt-library-bulk-progress` -> `assets/js/alt-library/bulk-progress.js`
8. `bbai-alt-library-bulk-orchestration` -> `assets/js/alt-library/bulk-orchestration.js`
9. `bbai-alt-library-generation-actions` -> `assets/js/alt-library/generation-actions.js`
10. `bbai-admin` -> `assets/js/bbai-admin.js`

`bbai-admin` receives each available helper handle as an explicit dependency. Missing optional helper files are skipped by the enqueue loop, and `bbai-admin.js` keeps fallback implementations for the current compatibility surface.

## Module Contracts

### `generation-locks.js`

Global export:

- `window.BBAIAltLibraryGenerationLocks.acquire(event, source, jobId, applyUi)`
- `window.BBAIAltLibraryGenerationLocks.clear(releaseUi)`
- `window.BBAIAltLibraryGenerationLocks.isLocked()`
- `window.BBAIAltLibraryGenerationLocks.set(source, jobId, applyUi)`
- `window.BBAIAltLibraryGenerationLocks.stopDuplicate(event)`

Required globals/config:

- Reads and writes `window.bbaiGenerationLock`.
- Reads and writes `window.bbaiGenerationInProgress`.
- Receives UI callbacks from `bbai-admin.js`; it does not directly mutate buttons.

Required DOM selectors:

- None directly.
- UI locking is applied by `bbaiApplyGenerationLockUI()` in `bbai-admin.js`.

Known callers:

- `bbaiIsGenerationLocked()`
- `bbaiStopDuplicateGenerationClick()`
- `bbaiAcquireGenerationLock()`
- `bbaiSetGenerationLock()`
- `bbaiClearGenerationLock()`
- `acquireGenerationActionLock()`
- `releaseGenerationActionLock()`

Fallback behaviour:

- `bbai-admin.js` contains local lock implementations and uses them when the helper global is unavailable.

Wrapper audit:

- Keep the `bbai-admin.js` wrappers for now. The helper is Library-only, while legacy generation globals can still be reached by other admin screens.

### `generation-notices.js`

Global export:

- `window.BBAIAltLibraryGenerationNotices.show(type, message, options)`

Required globals/config:

- Optionally uses `window.bbaiPushToast(type, message, { duration })`.
- Receives `options.fallback` from `bbai-admin.js`; currently this is `notifyLibraryFeedback`.

Required DOM selectors:

- None directly.

Known callers:

- `showGenerationNotice()` in `bbai-admin.js`.
- No-selection, queue-error, and generation preflight paths that route through the wrapper.

Fallback behaviour:

- If `window.bbaiPushToast` is unavailable, the module calls `options.fallback`.
- If the module is unavailable, `bbai-admin.js` uses its previous toast/fallback path.

Wrapper audit:

- The wrapper should stay until `notifyLibraryFeedback` and all notice callers are moved behind an explicit adapter.

### `generation-actions.js`

Global export:

- `window.BBAIAltLibraryGenerationActions.canStart(trigger, context)`
- `window.BBAIAltLibraryGenerationActions.isEntitlementExhausted()`
- `window.BBAIAltLibraryGenerationActions.primeCanonicalUsage(usage)`

Required globals/config:

- Reads `window.BBAIEntitlements` when present.
- Reads and writes `window.BBAI_STATE.usage` and `window.BBAI_STATE.plan` when entitlement usage must be made canonical before opening the upgrade modal.
- Receives legacy dependencies through `context`:
  - `configErrorMessage`
  - `event`
  - `exhaustedMessage`
  - `getLockedCtaSource`
  - `getUsageForQuotaChecks`
  - `handleLockedCtaClick`
  - `hasBulkConfig`
  - `isLockedControl`
  - `isOutOfCredits`
  - `modal`
  - `openUpgradeModal`
  - `requireBulkConfig`
  - `setGenerationInProgress`
  - `showNotice`

Required DOM selectors:

- None directly. The passed `trigger` is inspected only by injected legacy functions.

Known callers:

- `canStartGenerationAction()` in `bbai-admin.js`.
- Single regenerate and selected bulk generation preflight through the wrapper.

Fallback behaviour:

- `bbai-admin.js` contains the previous preflight logic and uses it when the helper global is unavailable.

Wrapper audit:

- Keep `canStartGenerationAction()` for now because it adapts private legacy closures into an explicit dependency object.
- Phase 3 can reduce the fallback body only after the generation flows are moved behind the same adapter contract.

### `api.js`

Global export:

- `window.BBAIAltLibraryApi.getAjaxUrl(config)`
- `window.BBAIAltLibraryApi.getNonce(config)`
- `window.BBAIAltLibraryApi.buildRegenerateSingleRequest(settings)`
- `window.BBAIAltLibraryApi.buildBulkQueueRequest(settings)`
- `window.BBAIAltLibraryApi.buildInlineGenerateRequest(settings)`
- `window.BBAIAltLibraryApi.normalizePayload(response)`
- `window.BBAIAltLibraryApi.isSuccess(response)`
- `window.BBAIAltLibraryApi.extractAltText(payload)`
- `window.BBAIAltLibraryApi.normalizeBulkQueueResponse(response, ids, defaultMessage)`
- `window.BBAIAltLibraryApi.normalizeBulkQueueXhrError(xhr, defaultMessage)`
- `window.BBAIAltLibraryApi.normalizeInlineGenerateResponse(response, id, messages)`
- `window.BBAIAltLibraryApi.normalizeInlineGenerateXhrError(xhr, messages)`

Required globals/config:

- Reads `window.bbai_ajax.ajax_url`, `window.bbai_ajax.ajaxurl`, and `window.bbai_ajax.nonce`.
- Falls back to `window.BBAI.nonce` and the injected legacy `config` object where the old code already did.
- Does not call `jQuery.ajax` directly.
- Does not update UI, locks, rows, quota state, analytics, or modals.

Required DOM selectors:

- None.

Known callers:

- Single regenerate row flow uses `buildRegenerateSingleRequest()`, `normalizePayload()`, and `extractAltText()`.
- Regenerate modal flow uses `buildRegenerateSingleRequest()`, `normalizePayload()`, and `extractAltText()`.
- Bulk queue flow uses `buildBulkQueueRequest()`, `normalizeBulkQueueResponse()`, and `normalizeBulkQueueXhrError()`.
- Inline generation flow uses `buildInlineGenerateRequest()`, `normalizeInlineGenerateResponse()`, and `normalizeInlineGenerateXhrError()`.

Backend contracts preserved:

- `beepbeepai_regenerate_single`
- `beepbeepai_bulk_queue`
- `beepbeepai_inline_generate`

Fallback behaviour:

- `bbai-admin.js` keeps local request construction fallbacks when the API helper global is unavailable.
- Existing orchestration, fallback REST queueing, row updates, usage refresh, and error handling remain in `bbai-admin.js`.

Wrapper audit:

- Keep the `bbai-admin.js` wrappers and fallback branches until orchestration is extracted.
- The adapter can be used as the boundary for moving selected bulk orchestration in a later phase.

### `bulk-selection.js`

Global export:

- `window.BBAIAltLibraryBulkSelection.isServerLockedBulkControl(element)`

Required globals/config:

- None.

Required DOM selectors/data/class contract:

- `data-bbai-action="open-upgrade"`
- `data-bbai-action="open-signup"`
- `data-bbai-action="open-usage"`
- `data-bbai-locked-cta="1"`
- `data-bbai-lock-control="1"`
- `.bbai-upgrade-required-action`
- `.bbai-is-locked`
- `.bbai-optimization-cta--locked`
- `.bbai-optimization-cta--disabled`

Known callers:

- `isServerLockedBulkControl()` in `bbai-admin.js`.
- Selection-state logic that must not unlock quota/server-locked controls.

Fallback behaviour:

- `bbai-admin.js` contains a duplicate local check and uses it when the helper global is unavailable.

Wrapper audit:

- The local duplicate body can eventually be removed when this helper is enqueued anywhere the selection-state wrapper may run.

### `library-state.js`

Global export:

- `window.BBAIAltLibraryState.applyGenerationSuccess(context, dependencies)`
- `window.BBAIAltLibraryState.applyRegenerateSuccessToRow(row, attachmentId, altText, payload, renderOptions, dependencies)`
- `window.BBAIAltLibraryState.applyOptimisticMissingResolved(dependencies)`
- `window.BBAIAltLibraryState.isMissingRow(row)`
- `window.BBAIAltLibraryState.syncLibraryRowAfterGeneration(id, altText, payload, dependencies)`

Required globals/config:

- Uses `window.document` only when a document is not injected.
- Receives legacy dependencies explicitly from `bbai-admin.js`:
  - `applyOptimisticMissingResolved`
  - `buildRenderOptions`
  - `coerceStats`
  - `dispatchStatsUpdated`
  - `document`
  - `flashSuccess`
  - `getWorkspaceRoot`
  - `refreshStats`
  - `refreshUsage`
  - `renderAltCell`
  - `setRuntimeState`
  - `syncPreview`
  - `updateCoverageCard`
  - `updateFilterCounts`
  - `updateLastUpdated`
  - `updateSelectionState`
  - `updateTrialUsage`

Required DOM selectors/data contract:

- `.bbai-library-row[data-attachment-id]`
- `data-alt-missing`
- `data-bbai-filter-exit-in-flight`
- `data-bbai-missing-count`

Known callers:

- `applyRegenerateSuccessToRow()` compatibility wrapper in `bbai-admin.js`.
- The single-regenerate success path in `bbai-admin.js`.
- `syncLibraryRowAfterGeneration()` compatibility wrapper in `bbai-admin.js`.

Fallback behaviour:

- If the module is unavailable, `bbai-admin.js` runs the previous inline row/count/filter state logic.
- `bbai-admin.js` still owns render primitives, preview modal sync, usage refresh, notices, quota UI, telemetry, and event binding.

Wrapper audit:

- Keep `applyRegenerateSuccessToRow()` and `syncLibraryRowAfterGeneration()` as stable legacy names.
- Keep `getLibraryStateAdapterDeps()` in `bbai-admin.js` until row rendering, stats refresh, and preview sync have stable adapter boundaries of their own.

### `bulk-orchestration.js`

Global export:

- `window.BBAIAltLibraryBulkOrchestration.runGenerateSelected(trigger, dependencies)`
- `window.BBAIAltLibraryBulkOrchestration.runRegenerateSelected(trigger, dependencies)`
- `window.BBAIAltLibraryBulkOrchestration.runSelectedBulk(config, trigger, dependencies)`

Required globals/config:

- No direct global state is required.
- Receives `window.BBAIAltLibraryApi` through the dependency object for the selected-bulk API boundary, but does not call AJAX directly.
- Receives all legacy orchestration dependencies explicitly from `bbai-admin.js`:
  - `acquireLock`
  - `buildGenerateProgressLabel`
  - `buildRegenerateProgressLabel`
  - `canStart`
  - `failureMessage`
  - `generateEmptyMessage`
  - `getGenerateSelectedIds`
  - `getRegenerateSelectedIds`
  - `handleLimitReached`
  - `hideBulkProgress`
  - `isLimitReachedError`
  - `logError`
  - `normalizeQueueResult`
  - `queueImages`
  - `regenerateEmptyMessage`
  - `setGenerationInProgress`
  - `setRuntimeState`
  - `showNotice`
  - `startGenerationFlow`

Required DOM selectors:

- None directly. Selected row lookup remains in `bbai-admin.js` through `getGenerateSelectedIds()` and `getRegenerateSelectedIds()`.

Known callers:

- `runBulkGenerateSelected()` in `bbai-admin.js`.
- `runBulkRegenerateSelected()` in `bbai-admin.js`.

Fallback behaviour:

- If the module is unavailable, `bbai-admin.js` runs the previous inline selected-bulk bodies.
- `bbai-admin.js` still binds click events and compatibility globals.

Wrapper audit:

- Keep `runBulkGenerateSelected()` and `runBulkRegenerateSelected()` as stable legacy names.
- Keep `getSelectedBulkOrchestrationDeps()` in `bbai-admin.js` until queueing, progress, quota, and telemetry are extracted behind stable adapters.

### `bulk-progress.js`

Global export:

- `window.BBAIAltLibraryBulkProgress.buildGenerateSelectedProgressLabel(count, i18n)`
- `window.BBAIAltLibraryBulkProgress.buildRegenerateSelectedProgressLabel(count, i18n)`
- `window.BBAIAltLibraryBulkProgress.normalizeSelectedBulkQueueResult(success, queued, error, processedIds, fallbackIds, responseData, options)`

Required globals/config:

- None.
- Receives `sprintf` and `_n` through an injected `i18n` object from `bbai-admin.js`.
- Does not call AJAX.
- Does not mutate DOM.
- Does not update rows, counts, filters, quota UI, modals, or analytics.

Required DOM selectors:

- None.

Known callers:

- `getSelectedBulkOrchestrationDeps()` in `bbai-admin.js` uses the label builders and passes `normalizeSelectedBulkQueueResult()` into `bulk-orchestration.js`.
- `bulk-orchestration.js` consumes the normalized queue result when the callback is injected.

Fallback behaviour:

- `bbai-admin.js` keeps local label builders when this helper is unavailable.
- `bulk-orchestration.js` keeps the previous inline queue callback branching when `normalizeQueueResult` is unavailable.

Wrapper audit:

- Keep the injected progress adapter until selected-bulk progress rendering and queueing have clearer module boundaries.

### `row-updates.js`

Global export:

- `window.BBAIAltLibraryRowUpdates.getAttachmentId(row)`
- `window.BBAIAltLibraryRowUpdates.isMissing(row)`

Required globals/config:

- None.

Required DOM selectors/data contract:

- `.bbai-library-row`
- `data-alt-missing`
- `data-attachment-id`

Known callers:

- Regenerate success paths in `bbai-admin.js` use `isMissing(row)` to determine whether counts and filters need to treat the row as previously missing.
- `getAttachmentId(row)` is reserved for the next extraction phase.

Fallback behaviour:

- `bbai-admin.js` falls back to directly reading `data-alt-missing` when the helper global is unavailable.

Wrapper audit:

- Keep the wrapper/fallback until row mutation, filter application, and stats refresh code are extracted together.

## What Still Lives In `bbai-admin.js`

The legacy file still owns most workflows and all UI mutation:

- `handleRegenerateSingle`
- `runBulkGenerateSelected` compatibility wrapper and fallback body
- `runBulkRegenerateSelected` compatibility wrapper and fallback body
- `handleGenerateMissing`
- `handleRegenerateAll`
- `queueImages`
- `queueImagesFallback`
- `startGenerationFlow`
- `generateAltTextForId`
- `applyRegenerateSuccessToRow`
- `syncLibraryRowAfterGeneration`
- `applyLibraryReviewFilters`
- library stats/count refresh logic
- upgrade/auth modal handling
- telemetry dispatch
- delegated click handling

## Compatibility Wrappers

Wrappers that should stay for now:

- `bbaiIsGenerationLocked()`
- `bbaiStopDuplicateGenerationClick()`
- `bbaiAcquireGenerationLock()`
- `bbaiSetGenerationLock()`
- `bbaiClearGenerationLock()`
- `canStartGenerationAction()`
- `runBulkGenerateSelected()`
- `runBulkRegenerateSelected()`
- row update helper checks around regenerate success

Wrappers that are candidates for later removal:

- The local duplicate body of `isServerLockedBulkControl()`.
- The local duplicate body of `showGenerationNotice()`.
- The fallback body inside `canStartGenerationAction()`, after all generation flows use the explicit adapter contract.
- The fallback bodies inside `runBulkGenerateSelected()` and `runBulkRegenerateSelected()`, after the selected-bulk module is treated as required for Library screens.

## Dependency Risks

- `generation-actions.js` is intentionally dependency-injected. Phase 3 should keep those dependencies explicit instead of reaching into private `bbai-admin.js` closure functions from a module.
- `generation-locks.js` owns the lock state but not the DOM busy UI. The `applyUi` and `releaseUi` callbacks must continue to be passed from the legacy file until button state is extracted.
- `bulk-selection.js` depends on server-rendered lock data attributes and classes. If locked CTA markup changes, this helper and the Playwright locked-control test must be updated together.
- `bulk-orchestration.js` depends on injected callbacks for queueing, progress, quota handling, and runtime state. Phase 5 should keep these adapters explicit instead of importing private legacy functions directly.
- `bulk-progress.js` is pure normalization only. It must not grow DOM rendering or count/filter responsibility.
- `row-updates.js` is intentionally small. Moving row mutation before filter/count refresh is extracted would risk stale rows or incorrect missing/review counts.
- The ALT Library helpers are Library-only. Non-Library screens must continue to work through `bbai-admin.js` fallbacks.

## Do Not Break Checklist

- Single regenerate sends one `beepbeepai_regenerate_single` request.
- Single regenerate shows a busy state and visible success/error feedback.
- Generate selected is disabled before row selection.
- Generate selected enables after selection unless the server/quota lock says otherwise.
- Generate selected sends one queue request and one inline generation request per selected item.
- Generate all preflight blocks out-of-credit states before starting work.
- Quota/out-of-credit flows open the existing upgrade or usage UI.
- Review filter query/hash activates the expected tab.
- Successful generation removes or updates rows without a page refresh.
- Missing/review counts do not drift after row updates.
- PostHog/telemetry events do not duplicate.
- Repeated clicks and repeated navigation do not create duplicate AJAX calls.

## Phase 6 Readiness

Phase 6 is safe for another narrow extraction if it keeps the adapter boundary explicit and uses the existing Playwright spec as a gate. Good candidates are queue transport helpers or selected-bulk progress rendering adapters. Row rendering, filter application, and count refresh should move together only after test coverage expands around those coupled behaviours.
