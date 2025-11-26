# Optti WordPress Plugin Framework - Migration Plan

**Version:** 1.0  
**Author:** Ben Oats  
**Date:** 2025-01-XX

## Overview

This document outlines the complete migration plan to transform the legacy plugin into the new Optti WordPress Plugin Framework architecture. The plan includes class renaming, legacy code removal, and a phased implementation approach.

---

## 1. Class Mapping & Renaming Strategy

### 1.1 Framework Layer Classes

| Current Class | New Class | Location | Status |
|--------------|-----------|----------|--------|
| `BeepBeepAI\AltTextGenerator\Plugin` | `Optti\Framework\Plugin` | `framework/class-plugin.php` | ⏳ To Create |
| `BeepBeepAI\AltTextGenerator\API_Client_V2` | `Optti\Framework\API` | `framework/class-api.php` | ⏳ To Migrate |
| `BeepBeepAI\AltTextGenerator\Usage_Tracker` | `Optti\Framework\DB` (partial) | `framework/class-db.php` | ⏳ To Migrate |
| `BeepBeepAI\AltTextGenerator\Debug_Log` | `Optti\Framework\Logger` | `framework/class-logger.php` | ⏳ To Migrate |
| N/A | `Optti\Framework\License` | `framework/class-license.php` | ⏳ To Create |
| N/A | `Optti\Framework\Cache` | `framework/class-cache.php` | ⏳ To Create |
| N/A | `Optti\Framework\Traits\Singleton` | `framework/traits/trait-singleton.php` | ⏳ To Create |
| N/A | `Optti\Framework\Traits\API_Response` | `framework/traits/trait-api-response.php` | ⏳ To Create |
| N/A | `Optti\Framework\Traits\Settings` | `framework/traits/trait-settings.php` | ⏳ To Create |
| N/A | `Optti\Framework\Interfaces\Module` | `framework/interfaces/interface-module.php` | ⏳ To Create |
| N/A | `Optti\Framework\Interfaces\Service` | `framework/interfaces/interface-service.php` | ⏳ To Create |
| N/A | `Optti\Framework\Interfaces\Cache` | `framework/interfaces/interface-cache.php` | ⏳ To Create |

### 1.2 Admin Layer Classes

| Current Class | New Class | Location | Status |
|--------------|-----------|----------|--------|
| `BeepBeepAI\AltTextGenerator\Admin` | `Optti\Admin\Admin_Menu` | `admin/class-admin-menu.php` | ⏳ To Migrate |
| `BeepBeepAI\AltTextGenerator\Admin_Hooks` | `Optti\Admin\Admin_Notices` (partial) | `admin/class-admin-notices.php` | ⏳ To Migrate |
| `BeepBeepAI\AltTextGenerator\Core` | Split into modules | `includes/modules/` | ⏳ To Refactor |
| `BeepBeepAI\AltTextGenerator\REST_Controller` | Keep as-is (move to framework) | `framework/class-api.php` (partial) | ⏳ To Review |
| `BeepBeepAI\AltTextGenerator\Credit_Usage_Page` | Move to modules | `includes/modules/class-metrics.php` | ⏳ To Migrate |
| N/A | `Optti\Admin\Admin_Assets` | `admin/class-admin-assets.php` | ⏳ To Create |

### 1.3 Module Classes

| Current Class/Feature | New Class | Location | Status |
|----------------------|-----------|----------|--------|
| Alt text generation logic (from `Core`) | `Optti\Modules\Alt_Generator` | `includes/modules/class-alt-generator.php` | ⏳ To Extract |
| Media library integration (from `Core`) | `Optti\Modules\Image_Scanner` | `includes/modules/class-image-scanner.php` | ⏳ To Extract |
| Bulk processing (from `Core`) | `Optti\Modules\Bulk_Processor` | `includes/modules/class-bulk-processor.php` | ⏳ To Extract |
| Usage tracking (from `Usage_Tracker`) | `Optti\Modules\Metrics` | `includes/modules/class-metrics.php` | ⏳ To Migrate |
| Queue system | `Optti\Framework\Queue` (or module) | `framework/class-queue.php` | ⏳ To Review |

### 1.4 Legacy Classes to Remove

| Class | Reason | Replacement |
|-------|--------|-------------|
| `BeepBeepAI\AltTextGenerator\Loader` | Replaced by `Optti\Framework\Plugin` | `framework/class-plugin.php` |
| `BeepBeepAI\AltTextGenerator\Activator` | Move to `Optti\Framework\Plugin` | `framework/class-plugin.php` |
| `BeepBeepAI\AltTextGenerator\Deactivator` | Move to `Optti\Framework\Plugin` | `framework/class-plugin.php` |
| `BeepBeepAI\AltTextGenerator\Token_Quota_Service` | Merge into `Optti\Framework\License` | `framework/class-license.php` |
| `BeepBeepAI\AltTextGenerator\Site_Fingerprint` | Merge into `Optti\Framework\License` | `framework/class-license.php` |
| `BeepBeepAI\AltTextGenerator\Credit_Usage_Logger` | Merge into `Optti\Modules\Metrics` | `includes/modules/class-metrics.php` |
| `BeepBeepAI\AltTextGenerator\Migrate_Usage` | One-time migration, remove after | N/A |
| `BeepBeepAI\AltTextGenerator\OptiAI_Migration` | One-time migration, remove after | N/A |
| Duplicate classes in `beepbeep-ai-alt-text-generator/` | Legacy directory | Remove entire directory |

---

## 2. Legacy Code Removal Checklist

### 2.1 Files to Delete

```
❌ beepbeep-ai-alt-text-generator/ (entire legacy directory)
❌ includes/class-bbai-loader.php (replaced by framework)
❌ includes/class-bbai-activator.php (move to framework)
❌ includes/class-bbai-deactivator.php (move to framework)
❌ includes/class-opptiai-migration.php (one-time migration)
❌ includes/class-bbai-migrate-usage.php (one-time migration)
❌ includes/class-token-quota-service.php (merge into License)
❌ includes/class-site-fingerprint.php (merge into License)
❌ includes/class-credit-usage-logger.php (merge into Metrics)
❌ admin/class-opptiai-alt-core.php (duplicate)
❌ admin/class-opptiai-alt-rest-controller.php (duplicate)
❌ framework/loader.php (replace with new loader)
```

### 2.2 Constants to Update

| Old Constant | New Constant | Location |
|-------------|--------------|----------|
| `BEEPBEEP_AI_VERSION` | `OPTTI_VERSION` | Main plugin file |
| `BEEPBEEP_AI_PLUGIN_FILE` | `OPTTI_PLUGIN_FILE` | Main plugin file |
| `BEEPBEEP_AI_PLUGIN_DIR` | `OPTTI_PLUGIN_DIR` | Main plugin file |
| `BEEPBEEP_AI_PLUGIN_URL` | `OPTTI_PLUGIN_URL` | Main plugin file |
| `BEEPBEEP_AI_PLUGIN_BASENAME` | `OPTTI_PLUGIN_BASENAME` | Main plugin file |
| `BBAI_*` (all legacy aliases) | Remove | All files |

### 2.3 Option Keys to Migrate

| Old Option Key | New Option Key | Migration Strategy |
|---------------|----------------|-------------------|
| `beepbeepai_settings` | `optti_settings` | Migrate on first load |
| `beepbeepai_jwt_token` | `optti_jwt_token` | Migrate on first load |
| `beepbeepai_user_data` | `optti_user_data` | Migrate on first load |
| `beepbeepai_site_id` | `optti_site_id` | Migrate on first load |
| `bbai_*` (all variants) | `optti_*` | Migrate on first load |

### 2.4 Namespace Changes

| Old Namespace | New Namespace |
|--------------|---------------|
| `BeepBeepAI\AltTextGenerator` | `Optti\Framework` (core) |
| `BeepBeepAI\AltTextGenerator` | `Optti\Admin` (admin) |
| `BeepBeepAI\AltTextGenerator` | `Optti\Modules` (modules) |

---

## 3. Phased Migration Plan

### PHASE 1: Framework Bootstrap ⏳

**Goal:** Create the core framework foundation

1. **Create framework structure**
   - ✅ Create `framework/loader.php` (new implementation)
   - ✅ Create `framework/class-plugin.php` (main plugin loader)
   - ✅ Create `framework/traits/trait-singleton.php`
   - ✅ Create `framework/interfaces/interface-module.php`
   - ✅ Create `framework/interfaces/interface-service.php`
   - ✅ Create `framework/interfaces/interface-cache.php`

2. **Build core classes**
   - ✅ Create `framework/class-api.php` (migrate from `API_Client_V2`)
   - ✅ Create `framework/class-logger.php` (migrate from `Debug_Log`)
   - ✅ Create `framework/class-cache.php` (new)
   - ✅ Create `framework/class-db.php` (extract from `Usage_Tracker`)

3. **Update main plugin file**
   - ✅ Rename `beepbeep-ai-alt-text-generator.php` → `beepbeep-ai-alt-text-generator.php` (keep name, update internals)
   - ✅ Update to use new framework loader
   - ✅ Remove legacy class loading
   - ✅ Update constants to `OPTTI_*`

**Deliverable:** Core framework operational, plugin loads without errors

---

### PHASE 2: Licensing Engine ⏳

**Goal:** Implement unified licensing system

1. **Create licensing class**
   - ✅ Create `framework/class-license.php`
   - ✅ Migrate logic from `Token_Quota_Service`
   - ✅ Migrate logic from `Site_Fingerprint`
   - ✅ Add validate/activate/deactivate methods
   - ✅ Add admin notices integration
   - ✅ Add cron checks for expiration
   - ✅ Integrate with site URL binding

2. **Update API class**
   - ✅ Add license endpoints: `/license/validate`, `/license/activate`, `/license/deactivate`
   - ✅ Add auth endpoints: `/auth/login`, `/auth/register`, `/auth/refresh`
   - ✅ Update base URL to `https://backend.optti.dev`

3. **Remove legacy licensing code**
   - ❌ Delete `includes/class-token-quota-service.php`
   - ❌ Delete `includes/class-site-fingerprint.php`

**Deliverable:** Unified licensing system working across all Optti plugins

---

### PHASE 3: API Integration ⏳

**Goal:** Centralize all backend API calls

1. **Enhance API class**
   - ✅ Add base URL: `https://backend.optti.dev`
   - ✅ Create request handler with retry logic
   - ✅ Add error normalization
   - ✅ Add retry policy (exponential backoff)
   - ✅ Add token refresh logic
   - ✅ Add secure token storage (WP transients)
   - ✅ Add plugin-site binding

2. **Update all API calls**
   - ✅ Replace all direct `API_Client_V2` usage with `Optti\Framework\API`
   - ✅ Update error handling to use normalized errors
   - ✅ Update authentication flow

3. **Remove legacy API code**
   - ❌ Delete `includes/class-api-client-v2.php` (after migration)

**Deliverable:** All API calls go through unified framework API class

---

### PHASE 4: Admin UI Framework ⏳

**Goal:** Build reusable admin interface system

1. **Create admin classes**
   - ✅ Create `admin/class-admin-menu.php` (migrate from `Admin`)
   - ✅ Create `admin/class-admin-assets.php` (extract from `Admin`)
   - ✅ Create `admin/class-admin-notices.php` (migrate from `Admin_Hooks`)
   - ✅ Create page renderer system
   - ✅ Create master template wrapper

2. **Create admin pages**
   - ✅ Create `admin/pages/dashboard.php`
   - ✅ Create `admin/pages/settings.php`
   - ✅ Create `admin/pages/license.php`
   - ✅ Create `admin/pages/analytics.php`

3. **Create templates**
   - ✅ Create `templates/dashboard/` directory
   - ✅ Create `templates/onboarding/` directory
   - ✅ Create `templates/settings/` directory
   - ✅ Create `templates/emails/` directory

4. **Remove legacy admin code**
   - ❌ Delete `admin/class-bbai-admin.php` (after migration)
   - ❌ Delete `admin/class-bbai-admin-hooks.php` (after migration)
   - ❌ Delete `admin/class-opptiai-alt-core.php` (duplicate)
   - ❌ Delete `admin/class-opptiai-alt-rest-controller.php` (duplicate)

**Deliverable:** Reusable admin UI system for all Optti plugins

---

### PHASE 5: Modules Implementation ⏳

**Goal:** Extract features into independent modules

1. **Create module structure**
   - ✅ Create `includes/modules/` directory
   - ✅ Create `includes/modules/class-alt-generator.php` (extract from `Core`)
   - ✅ Create `includes/modules/class-image-scanner.php` (extract from `Core`)
   - ✅ Create `includes/modules/class-bulk-processor.php` (extract from `Core`)
   - ✅ Create `includes/modules/class-metrics.php` (migrate from `Usage_Tracker`)

2. **Implement module registration**
   - ✅ Update `framework/class-plugin.php` to support module registry
   - ✅ Register modules: `Plugin::instance()->register_module(new Alt_Generator_Module())`

3. **Refactor Core class**
   - ✅ Break down `admin/class-bbai-core.php` into modules
   - ✅ Move alt text generation → `Alt_Generator` module
   - ✅ Move media library logic → `Image_Scanner` module
   - ✅ Move bulk processing → `Bulk_Processor` module
   - ✅ Move usage tracking → `Metrics` module

4. **Remove legacy module code**
   - ❌ Delete `admin/class-bbai-core.php` (after extraction)
   - ❌ Delete `includes/class-usage-tracker.php` (after migration)
   - ❌ Delete `includes/class-credit-usage-logger.php` (after migration)

**Deliverable:** All features as independent, registerable modules

---

### PHASE 6: Dashboard & UI ⏳

**Goal:** Build enterprise-grade dashboard

1. **Create dashboard page**
   - ✅ Implement "Plugin Health" section
     - License status
     - Active site
     - Subscription renewal date
     - Credits remaining
     - Total images processed
     - Optti score
   - ✅ Implement "Image Insights" section
     - Top images improved
     - Estimated SEO gain
     - Accessibility grade
     - Keyword opportunities

2. **Create other pages**
   - ✅ License management page
   - ✅ Usage Analytics page
   - ✅ Settings panel
   - ✅ Agency sites manager

3. **Update templates**
   - ✅ Create dashboard templates
   - ✅ Create onboarding wizard templates
   - ✅ Create settings form templates

**Deliverable:** Complete admin dashboard matching Figma spec

---

### PHASE 7: Cleanup & Optimization ⏳

**Goal:** Remove all legacy code and optimize

1. **Final cleanup**
   - ❌ Delete `beepbeep-ai-alt-text-generator/` directory (entire legacy folder)
   - ❌ Delete all migration classes
   - ❌ Delete duplicate classes
   - ❌ Remove legacy constants
   - ❌ Remove legacy option keys (after migration)

2. **Update build scripts**
   - ✅ Update `build-plugin.sh` for new structure
   - ✅ Auto bump version
   - ✅ Auto regenerate `readme.txt`
   - ✅ Generate `.zip` for WordPress.org + premium build

3. **Update documentation**
   - ✅ Update README.md
   - ✅ Update readme.txt
   - ✅ Create developer documentation

**Deliverable:** Clean, optimized codebase with no legacy code

---

## 4. Renaming Execution Order

### Step 1: Create New Framework Classes
1. Create new framework classes with correct namespaces
2. Implement functionality (migrate from old classes)
3. Test new classes independently

### Step 2: Update Dependencies
1. Update main plugin file to use new framework
2. Update admin classes to use new framework
3. Update modules to use new framework

### Step 3: Migrate Data
1. Create migration script for option keys
2. Run migration on plugin activation
3. Verify data migration

### Step 4: Remove Legacy Code
1. Remove old class files
2. Remove legacy directories
3. Remove unused constants and functions

### Step 5: Update References
1. Search and replace all class references
2. Update all namespace imports
3. Update all function calls

---

## 5. Testing Checklist

### After Each Phase:
- [ ] Plugin activates without errors
- [ ] No PHP warnings or notices
- [ ] Admin pages load correctly
- [ ] API calls work
- [ ] License validation works
- [ ] No broken functionality

### Final Testing:
- [ ] All features work as before
- [ ] No legacy code references
- [ ] All classes use new namespaces
- [ ] Build script generates correct zip
- [ ] WordPress.org compliance maintained

---

## 6. Risk Mitigation

### Backup Strategy
- Create git branch: `framework-migration`
- Commit after each phase
- Tag stable points

### Rollback Plan
- Keep old classes until Phase 7
- Use feature flags if needed
- Maintain backward compatibility during transition

### Data Migration
- Create migration script for option keys
- Test migration on staging
- Provide rollback for option keys

---

## 7. Timeline Estimate

| Phase | Estimated Time | Dependencies |
|-------|---------------|--------------|
| Phase 1: Framework Bootstrap | 2-3 days | None |
| Phase 2: Licensing Engine | 1-2 days | Phase 1 |
| Phase 3: API Integration | 1-2 days | Phase 1, 2 |
| Phase 4: Admin UI Framework | 2-3 days | Phase 1 |
| Phase 5: Modules Implementation | 3-4 days | Phase 1, 4 |
| Phase 6: Dashboard & UI | 2-3 days | Phase 4, 5 |
| Phase 7: Cleanup & Optimization | 1-2 days | All phases |

**Total Estimated Time:** 12-19 days

---

## 8. Success Criteria

✅ **Framework Complete When:**
- All core classes use `Optti\Framework` namespace
- All admin classes use `Optti\Admin` namespace
- All modules use `Optti\Modules` namespace
- No legacy `BeepBeepAI` or `BbAI` classes remain
- Plugin structure matches specification exactly
- All features work as before
- Code is cleaner and more maintainable
- Framework is reusable for future plugins

---

## Notes

- Maintain WordPress.org compliance throughout
- Keep all existing functionality working
- Test thoroughly after each phase
- Document any deviations from plan
- Update this plan as migration progresses

