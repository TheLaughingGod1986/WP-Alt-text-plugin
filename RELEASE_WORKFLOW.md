# Release Workflow & Preparation Guide

> **Complete guide for releasing new versions of the WordPress plugin**

---

## 🎯 Overview

This guide provides a comprehensive workflow for preparing, testing, and releasing new versions of the BeepBeep AI Alt Text Generator plugin.

---

## 📋 Release Checklist

### Pre-Release (1-2 weeks before)

#### Code Preparation
- [ ] All planned features merged to main branch
- [ ] All tests passing (`vendor/bin/phpunit`)
- [ ] Code coverage at target level (aim for 80%+)
- [ ] No critical or high-priority bugs
- [ ] Code review completed
- [ ] Security audit passed

#### Version Updates
- [ ] Update version in main plugin file header
- [ ] Update `BEEPBEEP_AI_VERSION` constant
- [ ] Update `package.json` version
- [ ] Update `README.md` stable tag
- [ ] Update `readme.txt` stable tag (for WordPress.org)

#### Documentation
- [ ] CHANGELOG.md updated with new features/fixes
- [ ] README.md updated if needed
- [ ] Inline documentation (PHPDoc) complete
- [ ] API documentation updated (if changed)
- [ ] Screenshots updated (if UI changed)

---

### Testing Phase (3-7 days before)

#### Automated Testing
- [ ] All unit tests pass
- [ ] Integration tests pass
- [ ] CI/CD pipeline green
- [ ] Build process completes successfully
- [ ] Bundle size within limits (<400KB)

#### Manual Testing
- [ ] Test on fresh WordPress install
- [ ] Test with minimum WordPress version (5.8)
- [ ] Test with latest WordPress version
- [ ] Test with PHP 7.4, 8.0, 8.1, 8.2, 8.3
- [ ] Test plugin activation
- [ ] Test plugin deactivation
- [ ] Test plugin uninstall
- [ ] Test upgrade from previous version

#### Feature Testing
- [ ] Test all major features
- [ ] Test all AJAX endpoints
- [ ] Test all REST API endpoints
- [ ] Test admin UI functionality
- [ ] Test queue processing
- [ ] Test AI generation
- [ ] Test user authentication
- [ ] Test license management

#### Compatibility Testing
- [ ] Test with popular themes (Astra, GeneratePress, etc.)
- [ ] Test with popular plugins (WooCommerce, Yoast, etc.)
- [ ] Test with block editor (Gutenberg)
- [ ] Test with classic editor
- [ ] Test with multisite (if applicable)

#### Browser Testing
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Mobile browsers

---

### Release Day

#### Final Checks
- [ ] Pull latest changes from main branch
- [ ] Run full test suite one final time
- [ ] Build production assets (`npm run build`)
- [ ] Verify bundle sizes
- [ ] Check for debugging code/console.logs
- [ ] Verify no credentials in code

#### Create Release
- [ ] Create git tag: `git tag -a v4.2.3 -m "Release 4.2.3"`
- [ ] Push tag: `git push origin v4.2.3`
- [ ] GitHub Actions creates release automatically
- [ ] Download release ZIP from GitHub
- [ ] Verify ZIP contents

#### WordPress.org Deployment
- [ ] SVN checkout: `svn co https://plugins.svn.wordpress.org/beepbeep-ai-alt-text-generator`
- [ ] Update trunk with new version
- [ ] Update readme.txt
- [ ] Update assets (screenshots, banner, icon)
- [ ] Create new tag in SVN
- [ ] Commit to SVN
- [ ] Verify on WordPress.org

---

### Post-Release (1-3 days after)

#### Monitoring
- [ ] Monitor error logs
- [ ] Check support forums
- [ ] Monitor reviews/ratings
- [ ] Check analytics for adoption rate
- [ ] Monitor API usage/errors

#### Communication
- [ ] Publish release notes
- [ ] Update documentation site
- [ ] Announce on social media (if applicable)
- [ ] Email notification to users (if applicable)
- [ ] Update changelog on website

#### Validation
- [ ] Verify update works for existing users
- [ ] Verify fresh installs work
- [ ] Check WordPress.org plugin page
- [ ] Verify assets display correctly
- [ ] Check compatibility reports

---

## 📦 Version Management

### Semantic Versioning

This plugin follows [Semantic Versioning](https://semver.org/):

**Format**: `MAJOR.MINOR.PATCH`

- **MAJOR** (4.x.x): Breaking changes, major rewrites
- **MINOR** (x.2.x): New features, backward-compatible
- **PATCH** (x.x.3): Bug fixes, minor improvements

### Version Update Locations

When bumping version, update these files:

#### 1. Main Plugin File
```php
// beepbeep-ai-alt-text-generator.php
/**
 * Version: 4.2.3  // ← Update this
 */

define( 'BEEPBEEP_AI_VERSION', '4.2.3' ); // ← Update this
define( 'BBAI_VERSION', '4.2.3' );        // ← Update this
```

#### 2. Package Files
```json
// package.json
{
  "version": "4.2.3"  // ← Update this
}
```

#### 3. README Files
```markdown
<!-- README.md -->
**Stable tag:** 4.2.3  ← Update this

<!-- readme.txt (WordPress.org) -->
Stable tag: 4.2.3  ← Update this
```

#### 4. Changelog
```markdown
<!-- CHANGELOG.md -->
## [4.2.3] - 2025-12-19  ← Add entry
```

---

## 📝 Changelog Format

### CHANGELOG.md Template

```markdown
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- New features go here

### Changed
- Changes to existing features

### Fixed
- Bug fixes

### Deprecated
- Soon-to-be removed features

### Removed
- Removed features

### Security
- Security fixes

## [4.2.3] - 2025-12-19

### Added
- Performance monitoring utilities
- Developer API documentation
- Security hardening guide

### Changed
- Improved queue processing performance
- Updated build process with compression

### Fixed
- Fixed memory leak in queue processor
- Resolved race condition in usage tracker

### Security
- Enhanced input validation
- Added rate limiting to API endpoints

## [4.2.0] - 2025-11-15

### Added
- Service-oriented architecture with DI container
- Event-driven system with Event Bus
- Comprehensive testing suite (166 tests)

[Unreleased]: https://github.com/user/repo/compare/v4.2.3...HEAD
[4.2.3]: https://github.com/user/repo/compare/v4.2.0...v4.2.3
[4.2.0]: https://github.com/user/repo/releases/tag/v4.2.0
```

---

## 🚀 Release Commands

### Quick Reference

```bash
# 1. Update version numbers
# (Manually update files listed above)

# 2. Update changelog
code CHANGELOG.md

# 3. Commit version bump
git add .
git commit -m "chore: bump version to 4.2.3"

# 4. Create tag
git tag -a v4.2.3 -m "Release version 4.2.3

- Add performance monitoring
- Improve security hardening
- Update documentation

See CHANGELOG.md for full details."

# 5. Push changes and tag
git push origin main
git push origin v4.2.3

# 6. GitHub Actions will automatically:
# - Run tests
# - Build assets
# - Create GitHub release
# - Generate release ZIP

# 7. Download release asset
gh release download v4.2.3

# 8. Deploy to WordPress.org (if applicable)
# See WordPress.org deployment section below
```

---

## 🏷️ Git Tagging Best Practices

### Creating Tags

```bash
# Annotated tag (recommended)
git tag -a v4.2.3 -m "Release 4.2.3: Performance improvements"

# View tag details
git show v4.2.3

# List all tags
git tag -l

# Push specific tag
git push origin v4.2.3

# Push all tags
git push --tags
```

### Tag Message Format

```
Release v4.2.3: Brief summary

Major Changes:
- Feature 1
- Feature 2
- Bug fix 1

Breaking Changes:
- None

Migration Notes:
- None required

See CHANGELOG.md for complete details.
```

---

## 🌐 WordPress.org Deployment

### First-Time Setup

```bash
# 1. Checkout SVN repository
svn co https://plugins.svn.wordpress.org/beepbeep-ai-alt-text-generator beepbeep-svn
cd beepbeep-svn

# 2. Directory structure
# trunk/      - Development version
# tags/       - Released versions (4.2.0, 4.2.1, etc.)
# assets/     - Plugin assets (screenshots, banners, icons)
```

### Deploying New Version

```bash
# 1. Update trunk
cd beepbeep-svn/trunk
# Copy new plugin files to trunk/
rsync -av --exclude='.git' /path/to/plugin/ ./

# 2. Update readme.txt
# Ensure stable tag matches new version

# 3. Add new files (if any)
svn add --force * --auto-props --parents --depth infinity -q

# 4. Commit trunk
svn ci -m "Update trunk to version 4.2.3"

# 5. Create tag from trunk
svn cp trunk tags/4.2.3

# 6. Commit tag
svn ci -m "Tagging version 4.2.3"

# 7. Update assets (if changed)
cd ../assets
# Copy new screenshots/banners
svn ci -m "Update assets for 4.2.3"
```

### WordPress.org readme.txt

```
=== BeepBeep AI - Alt Text Generator ===
Contributors: beepbeepv2
Tags: alt text, ai, accessibility, seo, images
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 4.2.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered alt text generation for WordPress images.

== Description ==

BeepBeep AI automatically generates SEO-optimized alt text for your WordPress images using advanced AI technology.

**Features:**
* Automatic alt text generation
* Bulk processing
* Queue management
* Usage tracking
* WordPress integration

== Installation ==

1. Upload plugin to `/wp-content/plugins/`
2. Activate through 'Plugins' menu
3. Configure settings under Settings > BeepBeep AI
4. Start generating alt text!

== Frequently Asked Questions ==

= How does it work? =

The plugin uses advanced AI to analyze images and generate descriptive alt text.

= Is it free? =

Yes! Free tier includes 50 images/month.

== Screenshots ==

1. Dashboard overview
2. Bulk generation interface
3. Settings page
4. Queue monitor

== Changelog ==

= 4.2.3 - 2025-12-19 =
* Added: Performance monitoring utilities
* Added: Developer API documentation
* Improved: Security hardening
* Fixed: Memory leak in queue processor

= 4.2.0 - 2025-11-15 =
* Added: Service-oriented architecture
* Added: Comprehensive testing suite
* Improved: Overall performance

== Upgrade Notice ==

= 4.2.3 =
Minor update with performance improvements and enhanced security.
```

---

## 📊 Release Notes Template

### GitHub Release Notes

**Title**: `Release v4.2.3 - Performance & Security Improvements`

**Body**:

```markdown
## 🎉 What's New in v4.2.3

### ✨ New Features

- **Performance Monitoring** - Track key metrics and identify bottlenecks
- **Developer API** - Comprehensive hooks and filters documentation
- **Security Guide** - Enhanced security hardening documentation

### 🔧 Improvements

- Improved queue processing performance (30% faster)
- Enhanced asset compression (87.6% size reduction)
- Better error handling and logging

### 🐛 Bug Fixes

- Fixed memory leak in queue processor (#123)
- Resolved race condition in usage tracker (#124)
- Corrected timezone handling in logs (#125)

### 🔒 Security

- Enhanced input validation across all endpoints
- Added rate limiting to prevent abuse
- Improved authentication token handling

### 📚 Documentation

- Added comprehensive security audit guide
- Created release workflow documentation
- Updated API documentation

---

## 📦 Installation

Download `beepbeep-ai-alt-text-generator-4.2.3.zip` below and install via WordPress admin.

## ⬆️ Upgrading

Automatic updates available through WordPress admin. No manual steps required.

## 🔗 Links

- [Changelog](CHANGELOG.md)
- [Documentation](README.md)
- [Support Forum](https://wordpress.org/support/plugin/beepbeep-ai-alt-text-generator/)

---

**Full Changelog**: https://github.com/user/repo/compare/v4.2.0...v4.2.3
```

---

## 🔄 Rollback Procedures

### If Release Has Critical Bug

#### 1. Immediate Action

```bash
# Revert WordPress.org to previous version
cd beepbeep-svn
svn cp tags/4.2.2 trunk
svn ci -m "Rollback to 4.2.2 due to critical bug"

# Update readme.txt stable tag
# Change: Stable tag: 4.2.2
svn ci -m "Update stable tag to 4.2.2"
```

#### 2. GitHub

```bash
# Create hotfix branch
git checkout -b hotfix/4.2.4

# Apply fix
# ... make changes ...

# Test thoroughly
vendor/bin/phpunit

# Release hotfix
git tag -a v4.2.4 -m "Hotfix: Critical bug fix"
git push origin v4.2.4
```

#### 3. Communication

- [ ] Post notice in support forums
- [ ] Update release notes with known issues
- [ ] Email affected users (if possible)
- [ ] Post status update

---

## 📈 Post-Release Metrics

### Track These Metrics

**Adoption**:
- Active installs (WordPress.org stats)
- Update rate (% of users on latest version)
- New installs per day

**Quality**:
- Support tickets opened
- Bug reports filed
- Average rating changes
- Review sentiment

**Performance**:
- Error rate (from logs)
- API success rate
- Average response times
- Queue processing times

**Example Dashboard**:

```
Release: v4.2.3 (2025-12-19)

Adoption (Day 7):
├─ Active Installs: 10,000 (+500)
├─ On Latest: 65% (6,500 users)
└─ Update Rate: +10% daily

Quality:
├─ Support Tickets: 3 (▼ from 8)
├─ Bug Reports: 1 (▼ from 5)
├─ Rating: 4.8/5.0 (▲ from 4.7)
└─ Reviews: 12 new (10 positive)

Performance:
├─ Error Rate: 0.1% (▼ from 0.3%)
├─ API Success: 99.8%
├─ Avg Response: 245ms
└─ Queue Process: 150 img/min
```

---

## 🎯 Release Schedule

### Recommended Cadence

**Patch Releases** (4.2.x):
- Frequency: As needed (bug fixes)
- Notice: 1-3 days
- Testing: 1-2 days

**Minor Releases** (4.x.0):
- Frequency: Monthly or bi-monthly
- Notice: 1-2 weeks
- Testing: 3-7 days
- Beta period: Optional

**Major Releases** (x.0.0):
- Frequency: Annually
- Notice: 1 month+
- Testing: 2-4 weeks
- Beta period: Recommended
- RC period: Recommended

---

## ✅ Quality Gates

### Minimum Requirements for Release

**Code Quality**:
- ✅ All tests passing
- ✅ No critical bugs
- ✅ Code review completed
- ✅ Security audit passed

**Documentation**:
- ✅ Changelog updated
- ✅ README current
- ✅ API docs updated
- ✅ Inline docs complete

**Testing**:
- ✅ Manual testing completed
- ✅ Compatibility tested
- ✅ Performance acceptable
- ✅ No regressions found

**Infrastructure**:
- ✅ CI/CD pipeline green
- ✅ Build successful
- ✅ Assets optimized
- ✅ Version updated everywhere

**If ANY gate fails**: DO NOT RELEASE

---

## 📞 Emergency Contacts

### Critical Issues

**Plugin Breaks Sites**:
1. Rollback immediately (see Rollback Procedures)
2. Post notice on WordPress.org
3. Disable auto-updates if possible
4. Work on hotfix

**Security Vulnerability**:
1. **DO NOT** publicly disclose details
2. Create private fix
3. Coordinate with WordPress security team
4. Release emergency patch
5. Coordinate disclosure timing

---

## 📚 Additional Resources

- [WordPress Plugin Handbook - Releases](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/)
- [Semantic Versioning](https://semver.org/)
- [Keep a Changelog](https://keepachangelog.com/)
- [GitHub Releases](https://docs.github.com/en/repositories/releasing-projects-on-github)

---

**Last Updated**: 2025-12-19
**Template Version**: 1.0.0
**Status**: ✅ Production Ready
