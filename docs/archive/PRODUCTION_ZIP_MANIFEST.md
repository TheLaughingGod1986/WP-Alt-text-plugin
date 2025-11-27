# Production ZIP Manifest - Version 4.2.0

## Package Information
- **File**: `dist/opptiai-alt-text-generator-4.2.0.zip`
- **Size**: 132KB
- **Version**: 4.2.0
- **Build Date**: 2025-01-XX
- **Status**: ✅ Production Ready

## Included Files (Clean Plugin Only)

### Core Plugin Files
- ✅ `opptiai-alt.php` - Main plugin file (v4.2.0)
- ✅ `readme.txt` - WordPress.org readme (v4.2.0)
- ✅ `LICENSE` - GPL v2 license

### Directory Structure
- ✅ `assets/` - All CSS and JavaScript files (production only)
- ✅ `includes/` - PHP classes (API client, queue, usage tracker)
- ✅ `templates/` - Upgrade modal template

## Excluded Files (Development/Test Files)

The following have been **automatically excluded** from the production ZIP:

### Development Files
- ❌ All `.md` files (documentation)
- ❌ All `.sh` files (build scripts)
- ❌ All `.py` files (Python scripts)
- ❌ All `.log` files
- ❌ All `.backup` and `.bak` files
- ❌ `.env` files
- ❌ `docker-compose.yml`
- ❌ `Dockerfile*`
- ❌ Configuration examples

### Test Files
- ❌ `tests/` directory
- ❌ `*test*.php` files
- ❌ `*Test.php` files
- ❌ `*spec*.js` files
- ❌ Test images (`test*.jpg`, `test*.png`, `test*.gif`)

### WordPress.org Assets (Not needed at runtime)
- ❌ `assets/wordpress-org/` directory
- ❌ Screenshot placeholders
- ❌ Banner/icon creation scripts

### Demo/Mock Files
- ❌ `demo*.html`
- ❌ `DEMO.*`
- ❌ `mock-backend.js`

### System Files
- ❌ `.DS_Store`
- ❌ `Thumbs.db`
- ❌ `.gitkeep`
- ❌ Hidden files

### Other Excluded
- ❌ `node_modules/` (if any)
- ❌ `backend/` directory
- ❌ `local-test/` directory
- ❌ `dist/` directory
- ❌ `deploy-package/` directory

## What's Included (Essential Only)

### Assets Directory
- `ai-alt-admin.js` - Bulk operations
- `ai-alt-dashboard.js` - Dashboard logic + auth
- `ai-alt-dashboard.css` - Main dashboard styles
- `auth-modal.js` - Authentication modal
- `auth-modal.css` - Auth modal styles
- `upgrade-modal.js` - Upgrade modal stub
- `upgrade-modal.css` - Upgrade modal styles
- `modern-style.css` - Modern UI styles
- `design-system.css` - Design tokens
- `components.css` - Reusable components
- `button-enhancements.css` - Button styles
- `dashboard-tailwind.css` - Dashboard utilities
- `guide-settings-pages.css` - Guide/Settings page styles

### Includes Directory
- `class-api-client-v2.php` - Backend API client
- `class-queue.php` - Background queue handler
- `class-usage-tracker.php` - Usage tracking

### Templates Directory
- `upgrade-modal.php` - Upgrade modal template

## Production Checklist

### ✅ Code Quality
- [x] No console.log statements (wrapped in debug mode)
- [x] No test files included
- [x] No development scripts included
- [x] No documentation files included (except readme.txt)
- [x] No backup files included
- [x] Production-ready code only

### ✅ Security
- [x] No exposed credentials
- [x] No .env files
- [x] No sensitive configuration
- [x] Input sanitization verified
- [x] Nonce verification in place

### ✅ WordPress Standards
- [x] Main plugin file has correct headers
- [x] readme.txt follows WordPress.org format
- [x] LICENSE file included
- [x] Proper file structure
- [x] No prohibited functions

## Installation

### Via WordPress Admin
1. Go to **Plugins → Add New**
2. Click **Upload Plugin**
3. Select `opptiai-alt-text-generator-4.2.0.zip`
4. Click **Install Now**
5. Click **Activate Plugin**

### Via WP-CLI
```bash
wp plugin install dist/opptiai-alt-text-generator-4.2.0.zip --activate
```

## Post-Installation

After installation, users will need to:
1. Create a free account (50 generations/month)
2. Or upgrade to Pro/Agency for unlimited
3. Access via **Media → AI ALT Text**

## Features Included

### ✅ Authentication
- User registration
- User login
- Password reset (requires backend endpoints)
- Logout

### ✅ Account Management
- Subscription information display
- Payment method display
- Stripe Customer Portal integration

### ✅ Core Functionality
- Automatic alt text generation
- Bulk operations
- Queue system
- Usage tracking
- Dashboard interface
- Upgrade modal

### ✅ Accessibility
- ARIA labels throughout
- Keyboard navigation
- Screen reader support
- WCAG 2.1 compliance

## Version History

- **4.2.0** (Current) - Password reset, account management, production cleanup
- **4.1.0** - SEO optimization, plugin metadata
- **3.0.0** - Major UI/UX overhaul

## Support

For issues or questions:
- Check plugin documentation in "How To" tab
- Review PASSWORD_RESET_TROUBLESHOOTING.md if password reset not working
- Verify backend endpoints are implemented (see BACKEND_INTEGRATION.md)

---

**Ready for Production Deployment** ✅

