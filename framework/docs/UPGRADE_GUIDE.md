# Upgrade Guide

This guide explains how to upgrade your plugin from one framework version to another.

## General Upgrade Process

1. **Backup**: Always backup your plugin before upgrading
2. **Check Changelog**: Review [CHANGELOG.md](../CHANGELOG.md) for breaking changes
3. **Update Submodule**: Update the framework submodule reference
4. **Test**: Thoroughly test your plugin after upgrading
5. **Update Code**: Apply any necessary code changes for breaking changes

## Upgrading Framework Submodule

### Method 1: Update to Latest Version

```bash
cd framework
git fetch origin
git checkout v1.1.0  # Replace with desired version
cd ..
git add framework
git commit -m "Upgrade framework to v1.1.0"
```

### Method 2: Update to Latest Main Branch

```bash
cd framework
git pull origin main
cd ..
git add framework
git commit -m "Update framework to latest"
```

## Version-Specific Upgrades

### Upgrading from v1.0.0 to v1.1.0

**Breaking Changes:**
- None

**New Features:**
- Added `get_usage()` method to PluginBase
- Added `get_billing_portal_url()` method to PluginBase
- Added `record_analytics_event()` method to PluginBase

**Migration Steps:**
1. Update submodule to v1.1.0
2. No code changes required

### Upgrading from v1.0.0 to v2.0.0

**Breaking Changes:**
- `Optti\Framework\API` renamed to `Optti\Framework\ApiClient`
- `Optti\Framework\License` renamed to `Optti\Framework\LicenseManager`
- `Optti\Framework\Plugin` renamed to `Optti\Framework\PluginBase`
- `Optti\Framework\Interfaces\Module` renamed to `Optti\Framework\Interfaces\ModuleInterface`
- `Optti\Framework\Interfaces\Service` renamed to `Optti\Framework\Interfaces\ServiceInterface`
- `Optti\Framework\Interfaces\Cache` renamed to `Optti\Framework\Interfaces\CacheInterface`
- `Optti\Framework\Traits\API_Response` renamed to `Optti\Framework\Traits\ApiResponse`

**Migration Steps:**

1. **Update Class References:**
   ```php
   // Old
   use Optti\Framework\API;
   use Optti\Framework\License;
   
   // New
   use Optti\Framework\ApiClient;
   use Optti\Framework\LicenseManager;
   ```

2. **Update Method Calls:**
   ```php
   // Old
   $api = API::instance();
   $license = License::instance();
   
   // New
   $api = ApiClient::instance();
   $license = LicenseManager::instance();
   ```

3. **Update Interface Implementations:**
   ```php
   // Old
   class My_Module implements Module {
   
   // New
   class My_Module implements ModuleInterface {
   ```

4. **Update Trait Usage:**
   ```php
   // Old
   use API_Response;
   
   // New
   use ApiResponse;
   ```

## Testing After Upgrade

After upgrading, test the following:

1. Plugin activation/deactivation
2. Admin pages load correctly
3. API calls work
4. License validation works
5. Settings save correctly
6. All modules function properly

## Getting Help

If you encounter issues during upgrade:

1. Check the [CHANGELOG.md](../CHANGELOG.md) for known issues
2. Review the [README.md](../README.md) for usage examples
3. Open an issue on GitHub

