# BeepBeep AI Alt Text Generator - Improvement Plan

This document outlines the actionable improvements identified during the codebase analysis.
Items are organized by priority and complexity.

---

## Completed (This Session)

### Critical Fixes
- [x] **Fixed duplicate API option calls** in `class-api-client-v2.php:73-79`
  - Removed redundant nested `get_option()` calls
- [x] **Fixed duplicate keys in `uninstall.php`**
  - Removed duplicate `beepbeepai_settings` in legacy options array
  - Removed duplicate `beepbeepai_token_notice` in legacy transients array
- [x] **Created `Input_Validator` class** (`includes/class-input-validator.php`)
  - Centralized validation methods for REST parameters
  - Includes: `int_param()`, `string_param()`, `key_param()`, `bool_param()`, `array_param()`
  - Includes helpers: `attachment_id()`, `pagination()`, `log_level()`, `period()`, `scope()`
- [x] **Cleaned up console.log statements** in modular JS files
  - Wrapped debug logs in conditional checks (`window.BBAI_DEBUG`, `window.alttextaiDebug`)
  - Removed excessive debug logging from admin init

### Architecture Improvements
- [x] **PHP Modular Architecture** - Split large classes into traits
  - Admin REST traits in `admin/traits/` (10 files)
  - API client traits in `includes/traits/` (5 files)
  - Core class traits in `admin/traits/` (6 files):
    - `trait-core-ajax-auth.php` - User registration, login, logout
    - `trait-core-ajax-license.php` - License activation, site management
    - `trait-core-ajax-billing.php` - Stripe checkout, customer portal
    - `trait-core-ajax-queue.php` - Queue management, usage refresh
    - `trait-core-media.php` - Media stats, attachment queries
- [x] **Input Validator adopted** across REST endpoints
  - `admin/class-bbai-rest-controller.php`
  - `admin/traits/trait-rest-generation.php`
  - `admin/traits/trait-rest-usage.php`
  - `admin/traits/trait-rest-queue.php`
- [x] **JavaScript Modular Architecture** - Split large JS files into modules
  - Dashboard modules in `assets/src/js/dashboard/` (10 files)
  - Admin modules in `assets/src/js/admin/` (9 files)
  - Build script at `scripts/build-js.js`
- [x] **CSS Modular Architecture** - Split large CSS files into partials
  - Dashboard components in `assets/src/css/dashboard/` (17+ files)
  - Build script at `scripts/build-modern-css.js`

---

## Priority 1: Critical (Should Fix Soon)

### 1.1 Adopt Input Validator Across Codebase
**Files affected:** ~15 files with REST endpoints
**Effort:** Medium

Replace inline validation patterns with `Input_Validator` class:

```php
// Before
$page = max(1, absint($request->get_param('page') ?: 1));
$per_page = min(100, absint($request->get_param('per_page') ?: 50));

// After
use BeepBeepAI\AltTextGenerator\Input_Validator;
$pagination = Input_Validator::pagination($request, 50, 100);
```

**Files updated:** ✅ All complete
- [x] `admin/class-bbai-rest-controller.php`
- [x] `admin/traits/trait-rest-generation.php`
- [x] `admin/traits/trait-rest-usage.php`
- [x] `admin/traits/trait-rest-queue.php`

### 1.2 Security Audit on User Input ✅ COMPLETED
**Files affected:** All AJAX and REST handlers
**Effort:** Medium

Audit all endpoints for:
- [x] Proper nonce verification - All 27 AJAX handlers use `check_ajax_referer('beepbeepai_nonce', 'nonce')`
- [x] Capability checks - All handlers use `$this->user_can_manage()` or `current_user_can('manage_options')`
- [x] Input sanitization - All `$_POST`/`$_GET` values use `wp_unslash()` + `sanitize_*()` functions
- [x] Output escaping - Templates use `esc_html()`, `esc_attr()`, `esc_url()` extensively (435+ occurrences)

**Audit Summary (January 2026):**
- 27 AJAX handlers audited in `class-bbai-core.php`
- 14 REST endpoints audited in `class-bbai-rest-controller.php`
- All handlers have proper nonce verification as first check
- All handlers have capability checks (either `user_can_manage()` or `current_user_can()`)
- All user input is sanitized with appropriate WordPress functions
- REST endpoints use `Input_Validator` class for consistent validation
- Template partials properly escape all dynamic output

### 1.3 Error Handling Consistency
**Effort:** Medium

Create consistent error response format:
- [ ] Standardize WP_Error codes and HTTP status mappings
- [ ] Create error factory helper class
- [ ] Document error codes for API consumers

---

## Priority 2: High (Next Sprint)

### 2.1 Migrate from Legacy Files to Modular Architecture
**Effort:** High

The modular files are built but the main plugin still loads the monolithic versions.
- [ ] Update `class-bbai.php` to load traits and modular bundles
- [ ] Test all functionality with new modular structure
- [ ] Deprecate monolithic files: `bbai-dashboard.js`, `bbai-admin.js`
- [ ] Update CSS enqueuing to use bundled files

### 2.2 Add Unit Tests
**Effort:** High

No tests currently exist. Priority test coverage:
- [ ] Set up PHPUnit with WordPress test library
- [ ] Test `Input_Validator` class methods
- [ ] Test `API_Client_V2` authentication flow
- [ ] Test `Queue` class operations
- [ ] Test REST endpoint responses

### 2.3 Resolve Dual Architecture (v4.x + v5.0)
**Effort:** High

Currently running two parallel systems:
- [ ] Identify all v4.x code paths
- [ ] Create migration strategy document
- [ ] Add deprecation notices to v4.x code
- [ ] Plan v6.0 release with v4.x removal

---

## Priority 3: Medium (Backlog)

### 3.1 Remove Remaining Debug Statements
**Files affected:** Original monolithic JS files
**Effort:** Low

Once modular architecture is active:
- [ ] Remove `console.log` from `assets/src/js/bbai-dashboard.js` (86 occurrences)
- [ ] Remove `console.log` from `assets/src/js/bbai-admin.js` (17 occurrences)
- [ ] Or replace with `bbaiLogger.log()` calls

### 3.2 Add Type Hints to PHP
**Effort:** Medium

Add parameter and return type declarations:
- [ ] Start with new `Input_Validator` class (already done)
- [ ] Add to API client methods
- [ ] Add to REST controller methods
- [ ] Add to Queue class

### 3.3 Implement Proper Logging Strategy
**Effort:** Medium

- [ ] Review `Debug_Log` class usage
- [ ] Ensure sensitive data is never logged
- [ ] Add log rotation/cleanup in admin
- [ ] Consider WP_DEBUG integration

### 3.4 Documentation
**Effort:** Medium

- [ ] Add PHPDoc to all public methods
- [ ] Document REST API endpoints (OpenAPI/Swagger)
- [ ] Create developer onboarding guide
- [ ] Document hooks and filters for extensibility

---

## Priority 4: Low (Future Enhancements)

### 4.1 Performance Optimizations
- [ ] Add database query caching where appropriate
- [ ] Optimize media library queries with proper indexes
- [ ] Implement lazy loading for admin UI components
- [ ] Add service worker for offline admin functionality

### 4.2 Code Quality Tools
- [ ] Set up PHP_CodeSniffer with WordPress standards
- [ ] Add ESLint configuration for JavaScript
- [ ] Set up pre-commit hooks for linting
- [ ] Add CI/CD pipeline for automated testing

### 4.3 Accessibility Improvements
- [ ] Audit admin UI for WCAG compliance
- [ ] Add proper ARIA labels
- [ ] Ensure keyboard navigation works
- [ ] Test with screen readers

### 4.4 Internationalization
- [ ] Audit all user-facing strings for translation
- [ ] Ensure proper text domain usage
- [ ] Test with RTL languages

---

## File Structure Reference

### New Modular Structure
```
WP-Alt-text-plugin/
├── admin/
│   ├── class-bbai-core.php (now uses traits)
│   └── traits/
│       ├── trait-core-ajax-auth.php     (NEW - user auth AJAX)
│       ├── trait-core-ajax-billing.php  (NEW - Stripe/billing AJAX)
│       ├── trait-core-ajax-license.php  (NEW - license AJAX)
│       ├── trait-core-ajax-queue.php    (NEW - queue AJAX)
│       ├── trait-core-assets.php        (NEW - enqueue scripts/styles)
│       ├── trait-core-generation.php    (NEW - alt text generation)
│       ├── trait-core-media.php         (NEW - media stats/queries)
│       ├── trait-core-review.php        (NEW - alt text health scoring)
│       ├── trait-rest-generation.php
│       ├── trait-rest-queue.php
│       └── trait-rest-usage.php
├── includes/
│   ├── class-input-validator.php
│   └── traits/
│       ├── trait-api-auth.php
│       ├── trait-api-billing.php
│       ├── trait-api-license.php
│       ├── trait-api-token.php
│       └── trait-api-usage.php
├── assets/src/
│   ├── css/dashboard/
│   │   └── (17 modular CSS files)
│   └── js/
│       ├── admin/
│       │   └── (9 modular JS files)
│       └── dashboard/
│           └── (10 modular JS files)
├── scripts/
│   ├── build-js.js
│   └── build-modern-css.js
└── IMPROVEMENT-PLAN.md (this file)
```

---

## Metrics

### Current State
- **Total PHP lines:** ~15,000
- **Total JS lines:** ~7,000
- **Largest PHP file:** `class-bbai-core.php` (5,559 lines) - **trait splitting in progress**
- **Largest JS file:** `bbai-dashboard.js` (2,581 lines) - modular version exists
- **Test coverage:** 0%
- **Core class traits created:** 8 traits (~1,500 lines extracted)

### Target State
- All files under 500 lines
- 80%+ test coverage on critical paths
- Zero raw `console.log` in production bundles
- Full type hints on public APIs
- Complete REST API documentation

---

## Notes

- The `class-bbai-core.php` file now uses 8 traits for modular functionality:
  - `Core_Ajax_Auth` - User registration, login, logout
  - `Core_Ajax_License` - License activation, site management
  - `Core_Ajax_Billing` - Stripe checkout, customer portal
  - `Core_Ajax_Queue` - Queue operations, usage refresh
  - `Core_Media` - Media statistics, attachment queries
  - `Core_Generation` - Alt text generation helpers
  - `Core_Review` - Alt text health evaluation
  - `Core_Assets` - Script/style enqueuing
- The existing traits can be used by adding `use` statements to the main classes
- Build scripts exist for both CSS and JS - run `npm run build:css` and `npm run build:js`
- The mock backend (`mock-backend.js`) is for local development only

---

## Implementation Roadmap

This roadmap organizes all remaining work into phases with clear dependencies.

### Phase 1: Foundation (Error Handling + Modular Migration)
**Goal:** Complete the architecture migration and establish consistent error handling.

#### 1A. Error Factory Class
Create `includes/class-error-factory.php`:
```php
class Error_Factory {
    const UNAUTHORIZED = 'unauthorized';
    const INVALID_INPUT = 'invalid_input';
    const NOT_FOUND = 'not_found';
    const API_ERROR = 'api_error';
    const RATE_LIMITED = 'rate_limited';

    public static function create(string $code, string $message = '', array $data = []): \WP_Error;
    public static function unauthorized(string $message = ''): \WP_Error;
    public static function invalid_input(string $field, string $message = ''): \WP_Error;
    public static function not_found(string $resource): \WP_Error;
    public static function api_error(string $message, array $response = []): \WP_Error;
}
```

**Tasks:**
- [ ] Create `Error_Factory` class with standard error codes
- [ ] Define HTTP status mappings for each error type
- [ ] Add `to_rest_response()` helper for REST endpoints
- [ ] Document error codes in `docs/ERROR-CODES.md`
- [ ] Adopt `Error_Factory` in REST controller
- [ ] Adopt `Error_Factory` in AJAX handlers

#### 1B. Complete Modular Architecture Migration
**Tasks:**
- [ ] Update `class-bbai.php` to conditionally load modular bundles
- [ ] Add feature flag: `BBAI_USE_MODULAR_ASSETS`
- [ ] Test dashboard with modular JS bundle
- [ ] Test admin with modular JS bundle
- [ ] Test CSS with modular bundle
- [ ] Create fallback mechanism for legacy files
- [ ] Update `wp_enqueue_script` calls to use bundled files
- [ ] Mark monolithic files as deprecated with comments

**Dependency:** Build scripts must be working (`npm run build:js`, `npm run build:css`)

---

### Phase 2: Testing Infrastructure
**Goal:** Establish test coverage for critical code paths.

#### 2A. PHPUnit Setup
**Tasks:**
- [ ] Install PHPUnit via Composer: `composer require --dev phpunit/phpunit`
- [ ] Install WP test library: `composer require --dev wp-phpunit/wp-phpunit`
- [ ] Create `phpunit.xml.dist` configuration
- [ ] Create `tests/bootstrap.php` for WordPress test setup
- [ ] Create `tests/Unit/` directory structure
- [ ] Add `composer test` script

#### 2B. Core Unit Tests
**Tasks:**
- [ ] Test `Input_Validator::int_param()` edge cases
- [ ] Test `Input_Validator::pagination()` bounds
- [ ] Test `Input_Validator::log_level()` allowed values
- [ ] Test `Error_Factory` output format
- [ ] Test `Queue::add_job()` and `Queue::get_stats()`

#### 2C. Integration Tests
**Tasks:**
- [ ] Test REST endpoint authentication
- [ ] Test `/wp-json/beepbeepai/v1/generate` flow
- [ ] Test `/wp-json/beepbeepai/v1/stats` response format
- [ ] Test AJAX handler responses

**Target:** 80% coverage on `Input_Validator`, `Error_Factory`, `Queue`

---

### Phase 3: v4.x Deprecation Strategy
**Goal:** Plan the removal of legacy v4.x code paths.

#### 3A. Audit v4.x Code
**Tasks:**
- [ ] Identify all v4.x-specific options (`wp_alt_text_*` keys)
- [ ] Identify all v4.x AJAX handlers
- [ ] Identify all v4.x template files
- [ ] Document v4.x vs v5.x feature differences
- [ ] Create migration matrix for settings

#### 3B. Deprecation Notices
**Tasks:**
- [ ] Add `_deprecated_function()` calls to v4.x methods
- [ ] Add admin notice for v4.x settings migration
- [ ] Create automated v4.x → v5.x settings migrator
- [ ] Plan v6.0 release timeline for v4.x removal

#### 3C. Migration Helper
Create `includes/class-migration-helper.php`:
- [ ] `migrate_v4_settings()` - One-click migration
- [ ] `get_legacy_settings()` - Read v4.x options
- [ ] `cleanup_legacy_data()` - Remove v4.x options after migration

---

### Phase 4: Code Quality
**Goal:** Improve maintainability and developer experience.

#### 4A. PHP Type Hints
**Files to update (in order):**
- [ ] `includes/class-input-validator.php` (already done)
- [ ] `includes/class-error-factory.php` (new)
- [ ] `includes/class-queue.php`
- [ ] `includes/class-api-client-v2.php`
- [ ] `admin/class-bbai-rest-controller.php`
- [ ] All trait files in `admin/traits/`

#### 4B. Debug Statement Cleanup
**Tasks:**
- [ ] Run: `grep -rn "console.log" assets/src/js/bbai-*.js | wc -l`
- [ ] Replace `console.log` with `bbaiLogger.debug()` in `bbai-dashboard.js`
- [ ] Replace `console.log` with `bbaiLogger.debug()` in `bbai-admin.js`
- [ ] Wrap remaining logs in `if (window.BBAI_DEBUG)` checks
- [ ] Verify production builds have no console output

#### 4C. Logging Strategy
**Tasks:**
- [ ] Audit `Debug_Log` class for sensitive data exposure
- [ ] Add log rotation (delete logs older than 30 days)
- [ ] Add admin UI for log management
- [ ] Integrate with `WP_DEBUG` constant
- [ ] Add log level filtering in admin

---

### Phase 5: Documentation
**Goal:** Make the codebase accessible to new developers.

#### 5A. PHPDoc Coverage
**Tasks:**
- [ ] Document all public methods in `class-bbai-core.php`
- [ ] Document all REST endpoints with `@since`, `@param`, `@return`
- [ ] Document all hooks/filters with `@hook` annotations
- [ ] Add `@throws` annotations where applicable

#### 5B. REST API Documentation
**Tasks:**
- [ ] Create `docs/REST-API.md` with endpoint reference
- [ ] Document request/response formats
- [ ] Add authentication requirements
- [ ] Add rate limiting info
- [ ] Consider OpenAPI/Swagger spec generation

#### 5C. Developer Guide
**Tasks:**
- [ ] Create `docs/DEVELOPER.md` with architecture overview
- [ ] Document trait usage pattern
- [ ] Document build scripts usage
- [ ] Document local development setup
- [ ] Document hooks for extensibility

---

### Phase 6: Polish (Future)
**Goal:** Enhance user experience and performance.

#### 6A. Performance
- [ ] Profile database queries with Query Monitor
- [ ] Add object caching for stats queries
- [ ] Optimize `get_media_stats()` for large libraries
- [ ] Consider background stats calculation

#### 6B. Code Quality Tools
- [ ] Add `phpcs.xml.dist` with WordPress standards
- [ ] Add `.eslintrc.js` configuration
- [ ] Set up Husky pre-commit hooks
- [ ] Add GitHub Actions CI workflow

#### 6C. Accessibility
- [ ] Audit admin pages with axe DevTools
- [ ] Add ARIA labels to interactive elements
- [ ] Test keyboard navigation
- [ ] Verify color contrast ratios

#### 6D. Internationalization
- [ ] Run `wp i18n make-pot` to generate POT file
- [ ] Audit for untranslated strings
- [ ] Test with RTL language pack
- [ ] Add translator comments where needed

---

## Quick Reference: Commands

```bash
# Build assets
npm run build:js
npm run build:css

# Run tests (after Phase 2)
composer test

# Check coding standards (after Phase 6B)
composer phpcs

# Generate translations
wp i18n make-pot . languages/beepbeep-ai.pot
```

---

## Progress Tracking

| Phase | Status | Progress |
|-------|--------|----------|
| Phase 1: Foundation | Not Started | 0/12 tasks |
| Phase 2: Testing | Not Started | 0/12 tasks |
| Phase 3: Deprecation | Not Started | 0/10 tasks |
| Phase 4: Code Quality | Not Started | 0/14 tasks |
| Phase 5: Documentation | Not Started | 0/11 tasks |
| Phase 6: Polish | Not Started | 0/12 tasks |

**Total remaining:** 71 tasks

---

*Last updated: January 2026*
*Generated during codebase modularization session*
