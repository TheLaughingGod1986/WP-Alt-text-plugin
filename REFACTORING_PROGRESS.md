# WordPress Plugin Review Refactoring Progress

**Date:** 2025-11-20  
**Status:** In Progress

## ✅ Completed Tasks

### 1. Plugin Header Metadata ✓
- ✅ Plugin Name: "AltText AI – Auto Image SEO & Accessibility"
- ✅ Plugin URI: https://wordpress.org/plugins/beepbeep-ai-alt-text-generator/
- ✅ Author URI: https://profiles.wordpress.org/beepbeepv2/
- ✅ Donate link: https://profiles.wordpress.org/beepbeepv2/

### 2. Readme.txt Updates ✓
- ✅ Contributors: beepbeepv2
- ✅ External Services disclosure added (exact format required)

### 3. REST API Namespace ✓
- ✅ Changed from `opptiai/v1` → `beepalt/v1`
- ✅ Updated all 9 REST routes in `class-opptiai-alt-rest-controller.php`

### 4. SQL Query Security ✓
- ✅ Fixed unsafe IN clause in `class-queue.php` (line 121-125)
- ✅ Fixed all DELETE queries to use `wpdb->prepare()`
- ✅ Fixed all SELECT queries to use `wpdb->prepare()`
- ✅ Updated `class-debug-log.php` queries

### 5. Removed Development Code ✓
- ✅ Removed Docker/localhost endpoints
- ✅ Removed `error_reporting()` calls (already removed)
- ✅ Removed update checker code (already removed)

### 6. Started Prefix Refactoring ✓
- ✅ Main plugin file constants: `OPPTIAI_ALT_*` → `BEEPALT_*`
- ✅ Main plugin functions: `activate_opptiai_alt()` → `beepalt_activate()`
- ✅ Main plugin functions: `run_opptiai_alt()` → `beepalt_run()`

## 🔄 In Progress

### Prefix Refactoring (beepalt_)
**Status:** Started - needs completion across all files

**Classes to Rename:**
- `Opptiai_Alt` → `BeepAlt`
- `Opptiai_Alt_Core` → `BeepAlt_Core`
- `Opptiai_Alt_Activator` → `BeepAlt_Activator`
- `Opptiai_Alt_Deactivator` → `BeepAlt_Deactivator`
- `Opptiai_Alt_REST_Controller` → `BeepAlt_REST_Controller`
- `Opptiai_Alt_Admin_Hooks` → `BeepAlt_Admin_Hooks`
- `Opptiai_Alt_Loader` → `BeepAlt_Loader`
- `Opptiai_Alt_I18n` → `BeepAlt_I18n`
- `Opptiai_Alt_Admin` → `BeepAlt_Admin`
- `AltText_AI_API_Client_V2` → `BeepAlt_API_Client_V2`
- `AltText_AI_Usage_Tracker` → `BeepAlt_Usage_Tracker`
- `AltText_AI_Queue` → `BeepAlt_Queue`
- `AltText_AI_Debug_Log` → `BeepAlt_Debug_Log`

**Constants to Rename:**
- `OPPTIAI_ALT_VERSION` → `BEEPALT_VERSION` (done)
- `OPPTIAI_ALT_PLUGIN_FILE` → `BEEPALT_PLUGIN_FILE` (done)
- `OPPTIAI_ALT_PLUGIN_DIR` → `BEEPALT_PLUGIN_DIR` (done)
- `OPPTIAI_ALT_PLUGIN_URL` → `BEEPALT_PLUGIN_URL` (done)
- `OPPTIAI_ALT_PLUGIN_BASENAME` → `BEEPALT_PLUGIN_BASENAME` (done)

**Options/Hooks to Rename:**
- All option keys: `opptiai_alt_*` → `beepalt_*`
- All action/filter hooks: `opptiai_*` → `beepalt_*`
- All AJAX actions: `wp_ajax_opptiai_*` → `wp_ajax_beepalt_*`
- All transient keys: `opptiai_*` → `beepalt_*`

## ⏳ Pending Tasks

### 7. Escape All Output
- Check all `echo $variable` statements
- Add `esc_html()`, `esc_attr()`, `esc_url()` where needed
- Fix any `print_r()` or `json_encode()` in output

### 8. Enqueue Assets
- Add versioning to all `wp_enqueue_script()` calls
- Update script/style handles to use `beepalt-` prefix
- Move inline scripts to enqueued files

### 9. Stripe URLs
- Create config/filter system for Stripe checkout URLs
- Remove hardcoded URLs

### 10. Folder/Slug Consistency
- Ensure main folder name = `beepbeep-ai-alt-text-generator`
- Ensure main file = `beepbeep-ai-alt-text-generator.php`

### 11. Final QA
- Run PHPCS + WPCS
- Test activation/deactivation
- Test with `WP_DEBUG=true`
- Build final ZIP

## 📊 Progress Summary

- **Completed:** 6/16 tasks (37.5%)
- **In Progress:** 1/16 tasks (6.25%)
- **Pending:** 9/16 tasks (56.25%)

## 🔧 Files Modified So Far

1. `opptiai-alt.php` - Header, constants, functions
2. `readme.txt` - Contributors, External Services
3. `admin/class-opptiai-alt-rest-controller.php` - REST namespace
4. `includes/class-queue.php` - SQL queries
5. `includes/class-debug-log.php` - SQL queries
6. `admin/class-opptiai-alt-core.php` - Docker endpoints removed
7. `includes/class-api-client-v2.php` - Docker endpoints removed
8. `assets/src/js/auth-modal.js` - Docker endpoints removed

## 📝 Notes

The prefix refactoring is the largest task and requires changes across all PHP files. This is being done systematically to avoid breaking references.

