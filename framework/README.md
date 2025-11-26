# Optti WordPress Plugin Framework

A reusable, shareable framework for building Optti WordPress plugins.

## Overview

The Optti Framework provides a solid foundation for WordPress plugins that need:

- Backend connectivity to Optti backend services
- Shared PHP base classes (API client, license management, logging, caching)
- Shared JavaScript modules (settings UI, notifications, usage widgets)
- Shared CSS design system (brand, layout, typography, components)
- Clean versioning and reuse via Git submodules

## Installation

### As a Git Submodule

```bash
git submodule add git@github.com:optti/plugin-framework.git framework
cd framework
git checkout v1.0.0
cd ..
git add framework
git commit -m "Add Optti Framework v1.0.0"
```

### In Your Plugin

```php
// Bootstrap framework
require_once __DIR__ . '/framework/loader.php';

Optti_Framework::bootstrap( __FILE__, [
    'plugin_slug'  => 'my-plugin',
    'version'      => '1.0.0',
    'api_base_url' => 'https://alttext-ai-backend.onrender.com',
    'asset_url'    => plugin_dir_url( __FILE__ ) . 'framework/dist/',
    'asset_dir'    => plugin_dir_path( __FILE__ ) . 'framework/dist/',
] );

// Create your plugin class
class My_Plugin extends \Optti\Framework\PluginBase {
    // Implement abstract methods
}
```

## Quick Start

1. **Extend PluginBase**: Create a class that extends `\Optti\Framework\PluginBase`
2. **Implement Abstract Methods**: Provide `get_plugin_name()`, `get_plugin_slug()`, and `get_text_domain()`
3. **Register Modules**: Override `register_modules()` to register your plugin-specific modules
4. **Register Admin Menus**: Override `register_admin_menus()` to add your admin pages
5. **Build Assets**: Run `npm install && npm run build` in the framework directory

## Framework Components

### PHP Classes

- **PluginBase**: Abstract base class for all plugins
- **ApiClient**: Centralized API client for backend communication
- **LicenseManager**: License validation and quota management
- **Logger**: Database logging system
- **Cache**: Transient-based caching
- **Db**: Database helper utilities

### JavaScript Modules

- **api-client.js**: Backend API wrapper
- **admin-core.js**: Bootstrap for admin functionality
- **notifications.js**: Toast notification system
- **usage-widgets.js**: Reusable usage statistics widgets
- **settings-page.js**: Settings form handler
- **admin-header.js**: Shared admin header component
- **benefits-panel.js**: Benefits display panel
- **sites-manager.js**: Multi-site management component

### CSS Design System

- **tokens.css**: CSS variables (colors, spacing, typography)
- **components.css**: Reusable UI components
- **layout.css**: Layout utilities and grid system
- **dashboard.css**: Dashboard-specific styles

## Building Assets

```bash
cd framework
npm install
npm run build
```

This will generate:
- `framework/dist/admin.js` - Bundled JavaScript
- `framework/dist/admin.css` - Bundled CSS

## Documentation

- [Blueprint for New Plugins](docs/BLUEPRINT_NEW_PLUGIN.md)
- [Upgrade Guide](docs/UPGRADE_GUIDE.md)
- [Changelog](CHANGELOG.md)

## Versioning

The framework uses semantic versioning:

- **MAJOR** (1.0.0 → 2.0.0): Breaking API changes
- **MINOR** (1.0.0 → 1.1.0): New features, backward compatible
- **PATCH** (1.0.0 → 1.0.1): Bug fixes

## License

GPLv2 or later

