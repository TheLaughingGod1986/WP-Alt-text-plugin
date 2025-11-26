# Optti WordPress Plugin Framework - Usage Guide

**Version:** 5.0.0  
**Last Updated:** 2025-01-XX

## Table of Contents

1. [Getting Started](#getting-started)
2. [Framework Core](#framework-core)
3. [Admin System](#admin-system)
4. [Modules](#modules)
5. [API Client](#api-client)
6. [License System](#license-system)
7. [Logging](#logging)
8. [Caching](#caching)
9. [Best Practices](#best-practices)

---

## Getting Started

### Initialization

The framework is automatically initialized when the plugin loads:

```php
// In beepbeep-ai-alt-text-generator.php
$plugin = \Optti\Framework\Plugin::instance();
```

### Accessing the Framework

```php
// Get plugin instance
$plugin = \Optti\Framework\Plugin::instance();

// Get a module
$alt_generator = $plugin->get_module( 'alt_generator' );

// Get framework services
$api = \Optti\Framework\API::instance();
$license = \Optti\Framework\License::instance();
$logger = \Optti\Framework\Logger::instance();
```

---

## Framework Core

### Plugin Class

The main plugin orchestrator:

```php
$plugin = \Optti\Framework\Plugin::instance();

// Get module
$module = $plugin->get_module( 'alt_generator' );

// Register custom module
$plugin->register_module( new MyCustomModule() );
```

### Module Interface

All modules implement the `Module` interface:

```php
namespace Optti\Modules;

use Optti\Framework\Interfaces\Module;

class MyModule implements Module {
    public function get_id() {
        return 'my_module';
    }
    
    public function get_name() {
        return 'My Module';
    }
    
    public function init() {
        // Initialize module
    }
    
    public function is_active() {
        return true;
    }
}
```

---

## Admin System

### Admin Menu

```php
$menu = \Optti\Admin\Admin_Menu::instance();

// Register custom page
$menu->register_page( 'my-page', 'My Page', 'manage_options', function() {
    echo 'Page content';
} );
```

### Admin Assets

```php
$assets = \Optti\Admin\Admin_Assets::instance();

// Enqueue custom script
$assets->enqueue_script( 'my-script', OPTTI_PLUGIN_URL . 'assets/js/my-script.js' );

// Enqueue custom style
$assets->enqueue_style( 'my-style', OPTTI_PLUGIN_URL . 'assets/css/my-style.css' );
```

### Admin Notices

```php
$notices = \Optti\Admin\Admin_Notices::instance();

// Add notice
$notices->add( 'my_notice', 'This is a notice', 'success', true );

// Remove notice
$notices->remove( 'my_notice' );
```

### Page Rendering

```php
use Optti\Admin\Page_Renderer;

Page_Renderer::render( 'my-page', 'My Page Title', function() {
    // Page content
} );
```

---

## Modules

### Alt Generator Module

Generate alt text for images:

```php
$plugin = \Optti\Framework\Plugin::instance();
$alt_generator = $plugin->get_module( 'alt_generator' );

// Generate alt text
$result = $alt_generator->generate( $attachment_id, 'manual', false );

if ( is_wp_error( $result ) ) {
    // Handle error
} else {
    // Success - $result contains alt text
}
```

### Image Scanner Module

Scan media library:

```php
$scanner = $plugin->get_module( 'image_scanner' );

// Get missing alt text images
$missing = $scanner->get_missing_alt_text( 50, 0 );

// Get statistics
$stats = $scanner->get_stats();
```

### Bulk Processor Module

Process images in bulk:

```php
$bulk = $plugin->get_module( 'bulk_processor' );

// Queue images for processing
$queued = $bulk->queue_images( $attachment_ids, 'bulk-generate' );
```

### Metrics Module

Get analytics and metrics:

```php
$metrics = $plugin->get_module( 'metrics' );

// Get usage statistics
$usage = $metrics->get_usage_stats();

// Get media statistics
$media = $metrics->get_media_stats();

// Get SEO metrics
$seo = $metrics->get_seo_metrics();
```

---

## API Client

### Basic Usage

```php
$api = \Optti\Framework\API::instance();

// Make a request
$response = $api->request( '/endpoint', 'GET', $data, $args );

// Check for errors
if ( is_wp_error( $response ) ) {
    $error = $response->get_error_message();
} else {
    // Success
    $data = $response;
}
```

### Convenience Methods

```php
// Generate alt text
$result = $api->generate_alt_text( $attachment_id, $context, $regenerate );

// Get user info
$user = $api->get_user_info();

// Get subscription info
$subscription = $api->get_subscription_info();

// Get plans
$plans = $api->get_plans();
```

### Authentication

```php
// Check if authenticated
if ( $api->is_authenticated() ) {
    // User is logged in
}

// Login
$result = $api->login( $email, $password );

// Register
$result = $api->register( $email, $password, $name );
```

---

## License System

### License Management

```php
$license = \Optti\Framework\License::instance();

// Check if license is active
if ( $license->has_active_license() ) {
    // License is active
}

// Get license data
$license_data = $license->get_license_data();

// Activate license
$result = $license->activate( $license_key );

// Deactivate license
$result = $license->deactivate();
```

### Quota Management

```php
// Get quota
$quota = $license->get_quota();

// Check if can consume credits
if ( $license->can_consume( 1 ) ) {
    // Can use 1 credit
}

// Get site ID
$site_id = $license->get_site_id();

// Get fingerprint
$fingerprint = $license->get_fingerprint();
```

---

## Logging

### Basic Logging

```php
$logger = \Optti\Framework\Logger::instance();

// Log info
$logger->log( 'info', 'Message', $context, 'module' );

// Log error
$logger->log( 'error', 'Error message', $context, 'module' );

// Log warning
$logger->log( 'warning', 'Warning message', $context, 'module' );
```

### Log Levels

- `info` - Informational messages
- `warning` - Warning messages
- `error` - Error messages
- `debug` - Debug messages (development only)

### Context Data

```php
$context = [
    'attachment_id' => 123,
    'user_id' => 456,
    'action' => 'generate',
];

$logger->log( 'info', 'Alt text generated', $context, 'alt_generator' );
```

---

## Caching

### Basic Caching

```php
$cache = \Optti\Framework\Cache::instance();

// Get cached value
$value = $cache->get( 'key', 'default' );

// Set cached value
$cache->set( 'key', $value, 3600 ); // 1 hour

// Delete cached value
$cache->delete( 'key' );

// Clear all cache
$cache->clear();
```

### Cache Expiration

```php
// Cache for 1 hour
$cache->set( 'key', $value, HOUR_IN_SECONDS );

// Cache for 1 day
$cache->set( 'key', $value, DAY_IN_SECONDS );

// Cache forever (until manually deleted)
$cache->set( 'key', $value, 0 );
```

---

## Best Practices

### 1. Use Singleton Pattern

Always use `instance()` method:

```php
// ✅ Good
$api = \Optti\Framework\API::instance();

// ❌ Bad
$api = new \Optti\Framework\API();
```

### 2. Check for Errors

Always check for `WP_Error`:

```php
$result = $api->request( '/endpoint', 'GET' );

if ( is_wp_error( $result ) ) {
    // Handle error
    $error = $result->get_error_message();
    return;
}

// Use result
$data = $result;
```

### 3. Use Modules

Access functionality through modules:

```php
// ✅ Good
$plugin = \Optti\Framework\Plugin::instance();
$alt_generator = $plugin->get_module( 'alt_generator' );

// ❌ Bad - Direct class instantiation
$alt_generator = new \Optti\Modules\Alt_Generator();
```

### 4. Log Important Events

Log important operations:

```php
$logger = \Optti\Framework\Logger::instance();
$logger->log( 'info', 'Operation completed', $context, 'module' );
```

### 5. Cache Expensive Operations

Cache expensive operations:

```php
$cache = \Optti\Framework\Cache::instance();
$key = 'expensive_operation_' . $id;

$result = $cache->get( $key );
if ( false === $result ) {
    $result = expensive_operation();
    $cache->set( $key, $result, HOUR_IN_SECONDS );
}
```

---

## Troubleshooting

### Module Not Found

If a module is not found:

```php
$module = $plugin->get_module( 'module_id' );
if ( ! $module ) {
    // Module not registered
    // Check if module file is loaded
    // Check if module is registered in Plugin class
}
```

### API Errors

Handle API errors gracefully:

```php
$response = $api->request( '/endpoint', 'GET' );

if ( is_wp_error( $response ) ) {
    $code = $response->get_error_code();
    $message = $response->get_error_message();
    
    // Handle specific error codes
    if ( 'auth_required' === $code ) {
        // User needs to authenticate
    } elseif ( 'quota_exhausted' === $code ) {
        // Quota exhausted
    }
}
```

---

## Additional Resources

- **Migration Plan:** See `FRAMEWORK_MIGRATION_PLAN.md`
- **Phase Documentation:** See `PHASE_*_COMPLETE.md` files
- **Code Examples:** See framework and module files
- **Inline Documentation:** See code comments

---

**Framework Version:** 5.0.0  
**Last Updated:** 2025-01-XX

