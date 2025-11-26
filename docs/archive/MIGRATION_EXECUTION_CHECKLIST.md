# Framework Migration Execution Checklist

Use this checklist to track progress during the actual migration. Check off items as you complete them.

## Pre-Migration Setup

- [ ] Create git branch: `framework-migration`
- [ ] Backup database (option keys)
- [ ] Document current plugin functionality
- [ ] Review FRAMEWORK_MIGRATION_PLAN.md
- [ ] Review CLASS_RENAMING_REFERENCE.md

---

## PHASE 1: Framework Bootstrap

### 1.1 Create Framework Structure
- [ ] Create `framework/traits/` directory
- [ ] Create `framework/interfaces/` directory
- [ ] Create `framework/traits/trait-singleton.php`
- [ ] Create `framework/interfaces/interface-module.php`
- [ ] Create `framework/interfaces/interface-service.php`
- [ ] Create `framework/interfaces/interface-cache.php`

### 1.2 Create Core Framework Classes
- [ ] Create `framework/class-plugin.php` (main loader)
- [ ] Create `framework/class-api.php` (migrate from `API_Client_V2`)
- [ ] Create `framework/class-logger.php` (migrate from `Debug_Log`)
- [ ] Create `framework/class-cache.php` (new implementation)
- [ ] Create `framework/class-db.php` (extract from `Usage_Tracker`)

### 1.3 Update Main Plugin File
- [ ] Update `beepbeep-ai-alt-text-generator.php` to use new framework
- [ ] Update constants: `BEEPBEEP_AI_*` → `OPTTI_*`
- [ ] Remove legacy class loading
- [ ] Update to use `framework/loader.php`

### 1.4 Testing
- [ ] Plugin activates without errors
- [ ] No PHP warnings or notices
- [ ] Framework classes load correctly
- [ ] Commit: "Phase 1: Framework Bootstrap Complete"

---

## PHASE 2: Licensing Engine

### 2.1 Create License Class
- [ ] Create `framework/class-license.php`
- [ ] Migrate logic from `Token_Quota_Service`
- [ ] Migrate logic from `Site_Fingerprint`
- [ ] Implement `validate()` method
- [ ] Implement `activate()` method
- [ ] Implement `deactivate()` method
- [ ] Add admin notices integration
- [ ] Add cron checks for expiration
- [ ] Integrate with site URL binding

### 2.2 Update API Class
- [ ] Add `/license/validate` endpoint
- [ ] Add `/license/activate` endpoint
- [ ] Add `/license/deactivate` endpoint
- [ ] Add `/auth/login` endpoint
- [ ] Add `/auth/register` endpoint
- [ ] Add `/auth/refresh` endpoint
- [ ] Update base URL to `https://backend.optti.dev`

### 2.3 Remove Legacy Licensing Code
- [ ] Delete `includes/class-token-quota-service.php`
- [ ] Delete `includes/class-site-fingerprint.php`
- [ ] Update all references to use new `License` class

### 2.4 Testing
- [ ] License validation works
- [ ] License activation works
- [ ] License deactivation works
- [ ] Admin notices display correctly
- [ ] Cron jobs run correctly
- [ ] Commit: "Phase 2: Licensing Engine Complete"

---

## PHASE 3: API Integration

### 3.1 Enhance API Class
- [ ] Set base URL: `https://backend.optti.dev`
- [ ] Implement request handler with retry logic
- [ ] Add error normalization
- [ ] Add retry policy (exponential backoff)
- [ ] Add token refresh logic
- [ ] Add secure token storage (WP transients)
- [ ] Add plugin-site binding

### 3.2 Update All API Calls
- [ ] Find all `API_Client_V2` usage
- [ ] Replace with `Optti\Framework\API`
- [ ] Update error handling
- [ ] Update authentication flow
- [ ] Test all API endpoints

### 3.3 Remove Legacy API Code
- [ ] Delete `includes/class-api-client-v2.php` (after migration)
- [ ] Update all imports/references

### 3.4 Testing
- [ ] All API calls work
- [ ] Error handling works correctly
- [ ] Token refresh works
- [ ] Retry logic works
- [ ] Commit: "Phase 3: API Integration Complete"

---

## PHASE 4: Admin UI Framework

### 4.1 Create Admin Classes
- [ ] Create `admin/class-admin-menu.php` (migrate from `Admin`)
- [ ] Create `admin/class-admin-assets.php` (extract from `Admin`)
- [ ] Create `admin/class-admin-notices.php` (migrate from `Admin_Hooks`)
- [ ] Create page renderer system
- [ ] Create master template wrapper

### 4.2 Create Admin Pages
- [ ] Create `admin/pages/` directory
- [ ] Create `admin/pages/dashboard.php`
- [ ] Create `admin/pages/settings.php`
- [ ] Create `admin/pages/license.php`
- [ ] Create `admin/pages/analytics.php`

### 4.3 Create Templates
- [ ] Create `templates/dashboard/` directory
- [ ] Create `templates/onboarding/` directory
- [ ] Create `templates/settings/` directory
- [ ] Create `templates/emails/` directory

### 4.4 Remove Legacy Admin Code
- [ ] Delete `admin/class-bbai-admin.php` (after migration)
- [ ] Delete `admin/class-bbai-admin-hooks.php` (after migration)
- [ ] Delete `admin/class-opptiai-alt-core.php` (duplicate)
- [ ] Delete `admin/class-opptiai-alt-rest-controller.php` (duplicate)

### 4.5 Testing
- [ ] Admin pages load correctly
- [ ] Admin menu displays correctly
- [ ] Assets load correctly
- [ ] Notices display correctly
- [ ] Commit: "Phase 4: Admin UI Framework Complete"

---

## PHASE 5: Modules Implementation

### 5.1 Create Module Structure
- [ ] Create `includes/modules/` directory
- [ ] Create `includes/modules/class-alt-generator.php`
- [ ] Create `includes/modules/class-image-scanner.php`
- [ ] Create `includes/modules/class-bulk-processor.php`
- [ ] Create `includes/modules/class-metrics.php`

### 5.2 Extract Module Logic
- [ ] Extract alt text generation from `Core` → `Alt_Generator`
- [ ] Extract media library logic from `Core` → `Image_Scanner`
- [ ] Extract bulk processing from `Core` → `Bulk_Processor`
- [ ] Migrate usage tracking from `Usage_Tracker` → `Metrics`
- [ ] Migrate credit usage from `Credit_Usage_Logger` → `Metrics`

### 5.3 Implement Module Registration
- [ ] Update `framework/class-plugin.php` to support module registry
- [ ] Implement `register_module()` method
- [ ] Register all modules in main plugin file
- [ ] Test module registration

### 5.4 Remove Legacy Module Code
- [ ] Delete `admin/class-bbai-core.php` (after extraction)
- [ ] Delete `includes/class-usage-tracker.php` (after migration)
- [ ] Delete `includes/class-credit-usage-logger.php` (after migration)

### 5.5 Testing
- [ ] All modules register correctly
- [ ] Alt text generation works
- [ ] Image scanning works
- [ ] Bulk processing works
- [ ] Metrics tracking works
- [ ] Commit: "Phase 5: Modules Implementation Complete"

---

## PHASE 6: Dashboard & UI

### 6.1 Create Dashboard Page
- [ ] Implement "Plugin Health" section
  - [ ] License status
  - [ ] Active site
  - [ ] Subscription renewal date
  - [ ] Credits remaining
  - [ ] Total images processed
  - [ ] Optti score
- [ ] Implement "Image Insights" section
  - [ ] Top images improved
  - [ ] Estimated SEO gain
  - [ ] Accessibility grade
  - [ ] Keyword opportunities

### 6.2 Create Other Pages
- [ ] License management page
- [ ] Usage Analytics page
- [ ] Settings panel
- [ ] Agency sites manager

### 6.3 Update Templates
- [ ] Create dashboard templates
- [ ] Create onboarding wizard templates
- [ ] Create settings form templates

### 6.4 Testing
- [ ] Dashboard displays correctly
- [ ] All sections show correct data
- [ ] All pages work correctly
- [ ] Templates render correctly
- [ ] Commit: "Phase 6: Dashboard & UI Complete"

---

## PHASE 7: Cleanup & Optimization

### 7.1 Final Cleanup
- [ ] Delete `beepbeep-ai-alt-text-generator/` directory (entire legacy folder)
- [ ] Delete `includes/class-opptiai-migration.php`
- [ ] Delete `includes/class-bbai-migrate-usage.php`
- [ ] Delete `includes/class-bbai-loader.php`
- [ ] Delete `includes/class-bbai-activator.php`
- [ ] Delete `includes/class-bbai-deactivator.php`
- [ ] Remove all legacy constants
- [ ] Remove all legacy option keys (after migration)

### 7.2 Update Build Scripts
- [ ] Update `build-plugin.sh` for new structure
- [ ] Add auto version bump
- [ ] Add auto `readme.txt` regeneration
- [ ] Update zip generation for WordPress.org
- [ ] Update zip generation for premium build

### 7.3 Update Documentation
- [ ] Update README.md
- [ ] Update readme.txt
- [ ] Create developer documentation
- [ ] Update this checklist with any deviations

### 7.4 Final Testing
- [ ] Plugin activates without errors
- [ ] All features work as before
- [ ] No legacy code references
- [ ] All classes use new namespaces
- [ ] Build script generates correct zip
- [ ] WordPress.org compliance maintained
- [ ] No PHP warnings or notices
- [ ] All tests pass

### 7.5 Final Commit
- [ ] Commit: "Phase 7: Cleanup & Optimization Complete"
- [ ] Tag: `v5.0.0-framework-migration`
- [ ] Merge to main branch (after review)

---

## Post-Migration

- [ ] Update version number to 5.0.0
- [ ] Update CHANGELOG.md
- [ ] Test on staging environment
- [ ] Test on production environment
- [ ] Monitor for issues
- [ ] Update documentation
- [ ] Announce framework migration complete

---

## Notes

Use this section to document any issues, deviations, or additional work needed:

```
[Add notes here as you work through the migration]
```

