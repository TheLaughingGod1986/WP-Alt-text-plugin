# WordPress Plugin Framework Architecture

> **Version**: 5.0.0
> **Extracted from**: BeepBeep AI Alt Text Generator Plugin
> **Architecture**: Modern Service-Oriented Architecture with Dependency Injection

---

## 📚 Table of Contents

1. [Overview](#overview)
2. [Core Architecture Patterns](#core-architecture-patterns)
3. [Framework Components](#framework-components)
4. [Project Structure](#project-structure)
5. [Implementation Guide](#implementation-guide)
6. [Best Practices](#best-practices)
7. [Testing Strategy](#testing-strategy)
8. [Migration Path](#migration-path)

---

## Overview

This document describes a modern, reusable WordPress plugin framework architecture extracted from a production plugin. The framework implements industry-standard patterns including:

- **Dependency Injection Container** for service management
- **Event-Driven Architecture** with publish-subscribe pattern
- **Service-Oriented Architecture** for business logic separation
- **Controller Layer** for request handling
- **Router** for AJAX and REST API endpoints

### Key Benefits

✅ **Testable**: Full dependency injection enables easy unit testing
✅ **Maintainable**: Clear separation of concerns
✅ **Scalable**: Modular architecture grows with your needs
✅ **Reusable**: Framework components work across any WordPress plugin
✅ **Modern**: Follows current PHP and WordPress best practices
✅ **Type-Safe**: Full PHP 7.4+ type declarations

---

## Core Architecture Patterns

### 1. Dependency Injection Container

**Purpose**: Manage service lifecycle and dependencies automatically

**Location**: `includes/core/class-container.php`

**Features**:
- Service factory registration
- Singleton pattern support
- Service aliasing
- Auto-wiring via reflection
- Constructor dependency injection

**Example**:
```php
$container = new Container();

// Register singleton service
$container->singleton('api.client', function($c) {
    return new API_Client(
        $c->get('config')
    );
});

// Auto-wire class with dependencies
$service = $container->make(MyService::class);

// Get service
$api = $container->get('api.client');
```

**Benefits**:
- Eliminates global state
- Makes dependencies explicit
- Enables easy mocking for tests
- Promotes loose coupling

---

### 2. Event Bus (Publish-Subscribe)

**Purpose**: Enable decoupled communication between components

**Location**: `includes/core/class-event-bus.php`

**Features**:
- Event subscription with priorities
- One-time event handlers
- Synchronous and asynchronous emission
- Automatic error handling
- Event listener management

**Example**:
```php
$event_bus = new Event_Bus();

// Subscribe to event
$event_bus->on('user.registered', function($data) {
    // Send welcome email
    send_welcome_email($data['email']);
}, 10);

// Subscribe once
$event_bus->once('plugin.activated', function($data) {
    // One-time setup
});

// Emit event
$event_bus->emit('user.registered', [
    'email' => 'user@example.com',
    'name' => 'John Doe'
]);

// Async emission (uses WordPress action scheduler)
$event_bus->emit_async('heavy.process', $data);
```

**Benefits**:
- Reduces coupling between modules
- Easy to extend without modifying existing code
- Natural fit for WordPress hooks
- Enables event-driven workflows

---

### 3. Router

**Purpose**: Route HTTP requests to appropriate controllers

**Location**: `includes/core/class-router.php`

**Features**:
- AJAX route registration
- REST API route registration
- Automatic nonce verification
- Permission checking
- Controller dispatch with DI
- Error handling and JSON responses

**Example**:
```php
$router = new Router($container);

// Register AJAX route
$router->ajax(
    'my_action',           // AJAX action name
    'controller.my',       // Controller service name
    'handle_action',       // Method name
    true                   // Require auth
);

// Register REST route
$router->rest(
    '/users/(?P<id>\d+)', // Route pattern
    'controller.users',    // Controller service
    'get_user',           // Method
    'GET'                 // HTTP method
);

// Initialize (registers WordPress hooks)
$router->init();
```

**Benefits**:
- Centralizes route configuration
- Automatic security (nonce, auth)
- Type-safe controller dispatch
- Consistent error handling

---

### 4. Service Provider

**Purpose**: Central registry for all application services

**Location**: `includes/core/class-service-provider.php`

**Features**:
- Organized service registration
- Dependency graph management
- Service categorization
- Clear service boundaries

**Example**:
```php
class Service_Provider {
    public static function register(Container $container): void {
        // Core services
        self::register_core($container);

        // Business services
        self::register_services($container);

        // Controllers
        self::register_controllers($container);
    }

    private static function register_services(Container $container): void {
        $container->singleton('service.auth', function($c) {
            return new Authentication_Service(
                $c->get('api.client'),
                $c->get('event_bus')
            );
        });
    }

    private static function register_controllers(Container $container): void {
        $container->singleton('controller.auth', function($c) {
            return new Auth_Controller(
                $c->get('service.auth')
            );
        });
    }
}
```

**Benefits**:
- Single source of truth for services
- Easy to understand dependencies
- Enables service discovery
- Simplifies testing setup

---

### 5. Service Layer Pattern

**Purpose**: Encapsulate business logic separate from HTTP layer

**Location**: `includes/services/`

**Structure**:
```php
class Authentication_Service {
    private API_Client $api_client;
    private Event_Bus $event_bus;

    public function __construct(
        API_Client $api_client,
        Event_Bus $event_bus
    ) {
        $this->api_client = $api_client;
        $this->event_bus = $event_bus;
    }

    public function register(string $email, string $password): array {
        // Validation
        if (empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'Invalid input'];
        }

        // Business logic
        $result = $this->api_client->register($email, $password);

        // Emit event
        if ($result['success']) {
            $this->event_bus->emit('user.registered', $result);
        }

        return $result;
    }
}
```

**Benefits**:
- Business logic is framework-agnostic
- Easy to test without WordPress
- Reusable across different entry points (AJAX, REST, CLI)
- Clear responsibility boundaries

---

### 6. Controller Layer Pattern

**Purpose**: Handle HTTP requests and delegate to services

**Location**: `includes/controllers/`

**Structure**:
```php
class Auth_Controller {
    private Authentication_Service $auth_service;

    public function __construct(Authentication_Service $auth_service) {
        $this->auth_service = $auth_service;
    }

    public function register(): array {
        // Permission check
        if (!current_user_can('manage_options')) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        // Input sanitization
        $email = sanitize_email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // Delegate to service
        return $this->auth_service->register($email, $password);
    }
}
```

**Benefits**:
- Thin controllers (single responsibility)
- Input validation at boundary
- Security checks centralized
- Easy to test

---

### 7. Hook Loader Pattern

**Purpose**: Clean registration of WordPress hooks

**Location**: `includes/class-loader.php`

**Structure**:
```php
class Loader {
    protected $actions = [];
    protected $filters = [];

    public function add_action($hook, $component, $callback, $priority = 10) {
        $this->actions[] = [
            'hook' => $hook,
            'component' => $component,
            'callback' => $callback,
            'priority' => $priority
        ];
    }

    public function run() {
        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority']
            );
        }
    }
}
```

**Benefits**:
- Centralized hook management
- Deferred registration
- Easy to track all hooks
- Testable hook logic

---

## Framework Components

### Directory Structure

```
plugin-root/
├── plugin-name.php                    # Main plugin file
├── includes/
│   ├── core/                          # Framework core
│   │   ├── class-container.php        # DI Container
│   │   ├── class-event-bus.php        # Event system
│   │   ├── class-router.php           # HTTP router
│   │   └── class-service-provider.php # Service registry
│   ├── services/                      # Business logic
│   │   ├── class-authentication-service.php
│   │   ├── class-license-service.php
│   │   └── class-usage-service.php
│   ├── controllers/                   # HTTP controllers
│   │   ├── class-auth-controller.php
│   │   ├── class-license-controller.php
│   │   └── class-generation-controller.php
│   ├── class-plugin.php               # Main plugin class
│   ├── class-loader.php               # Hook loader
│   ├── class-activator.php            # Activation logic
│   ├── class-deactivator.php          # Deactivation logic
│   └── bootstrap.php                  # Initialize framework
├── admin/                             # Admin UI
│   ├── class-admin.php
│   └── partials/
├── assets/                            # Frontend assets
│   ├── src/
│   │   ├── js/
│   │   └── css/
│   └── dist/
├── tests/                             # Test suite
│   ├── bootstrap.php
│   ├── unit/
│   └── integration/
└── vendor/                            # Composer dependencies
```

---

## Project Structure

### 1. Main Plugin File

**File**: `plugin-name.php`

**Purpose**: Plugin header, constants, and initialization

```php
<?php
/**
 * Plugin Name: My Awesome Plugin
 * Description: Description of plugin
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: my-plugin
 */

if (!defined('ABSPATH')) exit;

// Plugin constants
define('MYPLUGIN_VERSION', '1.0.0');
define('MYPLUGIN_PLUGIN_FILE', __FILE__);
define('MYPLUGIN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MYPLUGIN_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoload dependencies
require_once MYPLUGIN_PLUGIN_DIR . 'vendor/autoload.php';

// Load framework
require_once MYPLUGIN_PLUGIN_DIR . 'includes/core/class-container.php';
require_once MYPLUGIN_PLUGIN_DIR . 'includes/core/class-event-bus.php';
require_once MYPLUGIN_PLUGIN_DIR . 'includes/core/class-router.php';
require_once MYPLUGIN_PLUGIN_DIR . 'includes/core/class-service-provider.php';

// Load services and controllers
foreach (glob(MYPLUGIN_PLUGIN_DIR . 'includes/services/*.php') as $file) {
    require_once $file;
}
foreach (glob(MYPLUGIN_PLUGIN_DIR . 'includes/controllers/*.php') as $file) {
    require_once $file;
}

// Load bootstrap
require_once MYPLUGIN_PLUGIN_DIR . 'includes/bootstrap.php';

// Activation/Deactivation hooks
register_activation_hook(__FILE__, 'myplugin_activate');
register_deactivation_hook(__FILE__, 'myplugin_deactivate');

// Initialize plugin
function myplugin_run() {
    $plugin = new \MyPlugin\Plugin();
    $plugin->run();
}
myplugin_run();
```

---

### 2. Bootstrap File

**File**: `includes/bootstrap.php`

**Purpose**: Initialize DI container and register services

```php
<?php
declare(strict_types=1);

use MyPlugin\Core\Container;
use MyPlugin\Core\Service_Provider;
use MyPlugin\Core\Router;

function myplugin_init(): Container {
    static $container = null;

    if (null !== $container) {
        return $container;
    }

    // Create container
    $container = new Container();

    // Register services
    Service_Provider::register($container);

    // Setup routes
    myplugin_register_routes($container);

    return $container;
}

function myplugin_register_routes(Container $container): void {
    $router = $container->get('router');

    // Register AJAX routes
    $router->ajax('my_action', 'controller.my', 'handle_action');

    // Initialize router
    $router->init();
}

// Helper functions
function myplugin_container(): Container {
    return myplugin_init();
}

function myplugin_service(string $name) {
    return myplugin_container()->get($name);
}

// Initialize on WordPress init
add_action('init', 'myplugin_init', 5);
```

---

### 3. Service Provider

**File**: `includes/core/class-service-provider.php`

```php
<?php
declare(strict_types=1);

namespace MyPlugin\Core;

class Service_Provider {
    public static function register(Container $container): void {
        self::register_core($container);
        self::register_services($container);
        self::register_controllers($container);
    }

    private static function register_core(Container $container): void {
        $container->singleton('event_bus', fn($c) => new Event_Bus());
        $container->singleton('router', fn($c) => new Router($c));
    }

    private static function register_services(Container $container): void {
        // Register your services here
        $container->singleton('service.my', function($c) {
            return new \MyPlugin\Services\My_Service(
                $c->get('event_bus')
            );
        });
    }

    private static function register_controllers(Container $container): void {
        // Register controllers
        $container->singleton('controller.my', function($c) {
            return new \MyPlugin\Controllers\My_Controller(
                $c->get('service.my')
            );
        });
    }
}
```

---

## Implementation Guide

### Step 1: Setup Base Structure

```bash
# Create directory structure
mkdir -p my-plugin/{includes/{core,services,controllers},admin,assets/{src,dist},tests}

# Copy framework files
cp core/*.php my-plugin/includes/core/

# Create main plugin file
touch my-plugin/my-plugin.php
```

---

### Step 2: Create Service

```php
<?php
// includes/services/class-my-service.php
declare(strict_types=1);

namespace MyPlugin\Services;

use MyPlugin\Core\Event_Bus;

class My_Service {
    private Event_Bus $event_bus;

    public function __construct(Event_Bus $event_bus) {
        $this->event_bus = $event_bus;
    }

    public function do_something(string $input): array {
        // Business logic here
        $result = process($input);

        // Emit event
        $this->event_bus->emit('my_service.processed', $result);

        return ['success' => true, 'data' => $result];
    }
}
```

---

### Step 3: Create Controller

```php
<?php
// includes/controllers/class-my-controller.php
declare(strict_types=1);

namespace MyPlugin\Controllers;

use MyPlugin\Services\My_Service;

class My_Controller {
    private My_Service $my_service;

    public function __construct(My_Service $my_service) {
        $this->my_service = $my_service;
    }

    public function handle_action(): array {
        // Permission check
        if (!current_user_can('edit_posts')) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        // Sanitize input
        $input = sanitize_text_field($_POST['input'] ?? '');

        // Delegate to service
        return $this->my_service->do_something($input);
    }
}
```

---

### Step 4: Register Service and Route

```php
<?php
// In Service_Provider
private static function register_services(Container $container): void {
    $container->singleton('service.my', function($c) {
        return new \MyPlugin\Services\My_Service(
            $c->get('event_bus')
        );
    });
}

private static function register_controllers(Container $container): void {
    $container->singleton('controller.my', function($c) {
        return new \MyPlugin\Controllers\My_Controller(
            $c->get('service.my')
        );
    });
}

// In bootstrap
function myplugin_register_routes(Container $container): void {
    $router = $container->get('router');
    $router->ajax('my_action', 'controller.my', 'handle_action');
    $router->init();
}
```

---

### Step 5: Use Event Bus

```php
<?php
// Listen to events
$event_bus = myplugin_service('event_bus');

$event_bus->on('my_service.processed', function($data) {
    // React to event
    update_option('last_processed', $data);
});

// Emit from anywhere
$event_bus->emit('custom.event', ['foo' => 'bar']);
```

---

## Best Practices

### 1. Dependency Injection

**✅ DO**: Inject dependencies via constructor
```php
class My_Service {
    public function __construct(
        API_Client $api,
        Event_Bus $events
    ) {
        $this->api = $api;
        $this->events = $events;
    }
}
```

**❌ DON'T**: Use global state
```php
class My_Service {
    public function do_thing() {
        global $api_client;  // ❌ Bad
        $api_client->call();
    }
}
```

---

### 2. Service Separation

**✅ DO**: Keep services focused and single-purpose
```php
class Authentication_Service { /* auth only */ }
class License_Service { /* licensing only */ }
class Usage_Service { /* usage tracking only */ }
```

**❌ DON'T**: Create god objects
```php
class Core_Service {
    // Does auth, licensing, usage, generation, etc ❌
}
```

---

### 3. Controller Responsibility

**✅ DO**: Keep controllers thin
```php
class My_Controller {
    public function handle(): array {
        // 1. Validate permissions
        // 2. Sanitize input
        // 3. Delegate to service
        // 4. Return result
    }
}
```

**❌ DON'T**: Put business logic in controllers
```php
class My_Controller {
    public function handle(): array {
        // Complex business logic ❌
        // Database queries ❌
        // API calls ❌
    }
}
```

---

### 4. Event Usage

**✅ DO**: Use events for cross-cutting concerns
```php
$event_bus->emit('user.registered', $user);
// Other modules can listen without coupling
```

**❌ DON'T**: Use events for direct communication
```php
// Service A
$event_bus->emit('get.user', $id);

// Service B (listening)
$event_bus->on('get.user', function($id) {
    return get_user($id); // ❌ Wrong pattern
});
```

---

### 5. Type Safety

**✅ DO**: Use strict types and type hints
```php
declare(strict_types=1);

public function process(string $input): array {
    // ...
}
```

**❌ DON'T**: Skip type declarations
```php
public function process($input) {  // ❌ Untyped
    // ...
}
```

---

## Testing Strategy

### Unit Testing with DI

```php
<?php
// tests/unit/services/test-authentication-service.php

use PHPUnit\Framework\TestCase;
use Mockery;

class Authentication_Service_Test extends TestCase {
    public function test_register_success() {
        // Mock dependencies
        $api_mock = Mockery::mock(API_Client::class);
        $api_mock->shouldReceive('register')
            ->once()
            ->andReturn(['success' => true]);

        $event_mock = Mockery::mock(Event_Bus::class);
        $event_mock->shouldReceive('emit')
            ->once()
            ->with('user.registered', Mockery::any());

        // Create service with mocks
        $service = new Authentication_Service($api_mock, $event_mock);

        // Test
        $result = $service->register('test@example.com', 'password');

        $this->assertTrue($result['success']);
    }
}
```

---

### Integration Testing

```php
<?php
// tests/integration/test-auth-flow.php

class Auth_Flow_Test extends WP_UnitTestCase {
    private Container $container;

    public function setUp(): void {
        parent::setUp();
        $this->container = myplugin_init();
    }

    public function test_full_auth_flow() {
        // Get real services from container
        $auth_service = $this->container->get('service.auth');

        // Test real flow
        $result = $auth_service->register('test@example.com', 'pass');

        $this->assertTrue($result['success']);
        $this->assertNotEmpty(get_option('auth_token'));
    }
}
```

---

## Migration Path

### From Monolithic to Framework

**Phase 1**: Extract core framework
1. Copy Container, Event Bus, Router to `includes/core/`
2. No changes to existing code yet

**Phase 2**: Create service layer
1. Identify business logic in existing code
2. Extract to service classes
3. Inject dependencies via constructor

**Phase 3**: Create controllers
1. Extract HTTP handling from monolithic classes
2. Create thin controllers
3. Delegate to services

**Phase 4**: Wire everything together
1. Create Service Provider
2. Register services in container
3. Update routes to use controllers

**Phase 5**: Test and refine
1. Add unit tests for services
2. Add integration tests
3. Refactor as needed

---

## Conclusion

This framework provides a solid foundation for building modern, maintainable WordPress plugins. Key takeaways:

✅ **Dependency Injection** eliminates global state
✅ **Service Layer** encapsulates business logic
✅ **Event Bus** enables decoupled architecture
✅ **Router** simplifies HTTP handling
✅ **Testing** becomes straightforward with DI

The patterns are battle-tested and production-proven, extracted from a real WordPress plugin with 166 passing tests and 100% coverage.

---

**Next Steps**: See `PLUGIN_BOILERPLATE.md` for a ready-to-use plugin starter template.
