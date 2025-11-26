# Changelog

All notable changes to the Optti WordPress Plugin Framework will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2024-01-XX

### Added

- Initial release of Optti Framework
- **PHP Core:**
  - `PluginBase` abstract class for plugin development
  - `ApiClient` for backend API communication
  - `LicenseManager` for license validation and quota management
  - `Logger` for database logging
  - `Cache` for transient-based caching
  - `Db` for database helper utilities
  - `CacheManager` for centralized cache invalidation
  - `DbOptimizer` for database performance optimization
  - PSR-4 autoloader system
  - Bootstrap system via `Optti_Framework::bootstrap()`

- **JavaScript Modules:**
  - `api-client.js` - Backend API wrapper
  - `admin-core.js` - Admin bootstrap
  - `notifications.js` - Toast notification system
  - `usage-widgets.js` - Usage statistics widgets
  - `settings-page.js` - Settings form handler
  - `admin-header.js` - Shared admin header component
  - `benefits-panel.js` - Benefits display panel
  - `sites-manager.js` - Multi-site management component

- **CSS Design System:**
  - `tokens.css` - CSS variables for design tokens
  - `components.css` - Reusable UI components
  - `layout.css` - Layout utilities and grid system
  - `dashboard.css` - Dashboard-specific styles

- **Build System:**
  - Webpack configuration for JS/CSS bundling
  - Babel transpilation for ES5+ support
  - CSS processing with mini-css-extract-plugin

- **Documentation:**
  - README.md with overview and quick start
  - BLUEPRINT_NEW_PLUGIN.md for creating new plugins
  - UPGRADE_GUIDE.md for version upgrades
  - CHANGELOG.md for version history

- **Tools:**
  - `create-plugin.php` scaffold script for new plugins

### Features

- Plugin-agnostic API client with configurable base URL
- License management with quota tracking
- Centralized logging system
- Caching system with automatic invalidation
- Shared admin UI components
- Reusable JavaScript modules
- Consistent design system
- Git submodule support for versioning

## [Unreleased]

### Planned

- PHPUnit test suite
- Jest test suite for JavaScript
- Dummy plugin for integration testing
- Additional shared UX components
- Enhanced documentation

