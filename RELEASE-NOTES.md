# Release Notes - BeepBeep AI – Alt Text Generator

## Version 4.4.0
**Release Date:** January 16, 2025  
**Build Type:** Production Release for WordPress.org  
**ZIP File:** `beepbeep-ai-alt-text-generator-4.4.0.zip`

---

## Package Information

- **Plugin Name:** BeepBeep AI – Alt Text Generator
- **Plugin Slug:** `beepbeep-ai-alt-text-generator`
- **Version:** 4.4.0
- **Text Domain:** `beepbeep-ai-alt-text-generator`
- **Requires WordPress:** 5.8+
- **Tested up to:** 6.8
- **Requires PHP:** 7.4+

---

## Package Contents

This production ZIP contains:

### Core Files
- `beepbeep-ai-alt-text-generator.php` - Main plugin file
- `readme.txt` - WordPress.org readme with full documentation
- `uninstall.php` - Clean uninstall routine

### Directories
- `/admin` - Admin interface files (PHP classes, partials, React components, traits)
- `/includes` - Core functionality (services, controllers, helpers, queue system)
- `/templates` - PHP template files
- `/languages` - Internationalization files (`.pot` file)
- `/assets` - Production assets only:
  - `/css` - Compiled and minified CSS files
  - `/js` - Compiled and minified JavaScript bundles
  - `/dist` - Additional minified assets
  - `/img` - Image assets and testimonials

### Build Artifacts
- All JavaScript bundles built and minified
- All CSS files compiled and minified
- Source files excluded from production build

---

## Excluded from Production Build

The following development artifacts are **not** included in this release:

- `/assets/src` - Source CSS/JS files (not needed, only compiled files included)
- `node_modules/` - Dependencies (not needed, assets are pre-built)
- `vendor/` - Composer dependencies (not used in this plugin)
- `scripts/` - Build scripts (not needed by end users)
- `docs/` - Documentation files
- `.git/`, `.github/` - Version control files
- Test files, mock data, design files
- Development configuration files (`.vscode`, `.idea`, etc.)
- Documentation markdown files (`.md` files excluded except this release notes)

---

## Build Process

1. **JavaScript Build:** Ran `node scripts/build-js.js`
   - Bundled dashboard and admin JavaScript
   - Minified standalone JavaScript files
   - Output: `assets/js/*.bundle.js`, `assets/js/*.bundle.min.js`, `assets/dist/js/*.min.js`

2. **CSS Build:** Ran `node scripts/build-css.js`
   - Compiled unified CSS bundle
   - Minified CSS files
   - Output: `assets/css/unified.css`, `assets/css/unified.min.css`, `assets/dist/css/*.min.css`

3. **Quality Checks:**
   - ✅ All PHP files syntax validated (`php -l`)
   - ✅ No debug code in production files
   - ✅ Dev artifacts removed
   - ✅ Version consistency verified (4.4.0 in header and readme.txt)

---

## Installation Instructions

1. **For WordPress.org Upload:**
   - Upload `beepbeep-ai-alt-text-generator-4.4.0.zip` to WordPress.org plugin repository
   - The ZIP structure is correct for direct upload (contains plugin folder at root level)

2. **For Manual Installation:**
   - Extract the ZIP file
   - Upload the `beepbeep-ai-alt-text-generator` folder to `/wp-content/plugins/`
   - Activate the plugin through WordPress admin

---

## Important Notes

### Dependencies
- **No build step required** - All assets are pre-built and included
- **No Node.js required** - Production ZIP contains only compiled assets
- **No Composer required** - Plugin uses no external PHP dependencies

### Security
- All user inputs are sanitized
- All outputs are escaped
- Nonces verified on all form submissions
- SQL queries use prepared statements
- Capability checks on all admin actions

### External Services
This plugin connects to external services for alt text generation:
- **Backend API:** `https://alttext-ai-backend.onrender.com` (HTTPS)
- **Stripe:** For payment processing (buy.stripe.com)
- See `readme.txt` for full privacy and external services documentation

---

## Version 4.4.0 Changelog Highlights

Based on `readme.txt` changelog:

- 🔧 Fixed credit usage display not updating after alt text generation
- 🔧 Fixed backend cached responses now include accurate credit counts
- 🔧 Plugin now fetches fresh usage data when generation response lacks credits
- 🛡️ Security: Fixed output escaping for pagination links
- 🛡️ Security: Fixed ARIA attribute escaping in navigation
- 🧹 Codebase cleanup for WordPress.org submission
- 🧹 Removed development files, scripts, and legacy code
- ⚡ Improved API response handling for production backend

---

## Package Statistics

- **Total Files:** 221 files
- **Package Size:** ~3.5 MB (compressed)
- **PHP Files:** 89 files
- **Asset Files:** Compiled and minified CSS/JS
- **Language Files:** 1 `.pot` file (English source)

---

## WordPress.org Submission Readiness

This package has been prepared specifically for WordPress.org plugin repository submission:

✅ Valid plugin header with all required fields  
✅ Consistent version numbering (4.4.0)  
✅ Proper text domain (`beepbeep-ai-alt-text-generator`)  
✅ Complete `readme.txt` with FAQ, changelog, screenshots  
✅ Clean codebase (no dev artifacts)  
✅ Pre-built assets (no build step required)  
✅ All security best practices implemented  
✅ Comprehensive uninstall routine  
✅ GPL-compatible license  

---

## Next Steps

1. Upload `beepbeep-ai-alt-text-generator-4.4.0.zip` to WordPress.org
2. Plugin will be reviewed by WordPress.org team
3. Once approved, it will be available in the plugin directory

---

**Built:** January 16, 2025  
**Build Environment:** macOS (Darwin)  
**Node.js:** v20.19.6  
**Package Prepared By:** Senior WordPress Release Engineer

---

*This release package is production-ready and suitable for distribution via WordPress.org plugin repository.*
