# WordPress Plugin Framework - Quick Start Guide

> **Get started building modern WordPress plugins in 10 minutes**

This guide will walk you through creating your first plugin using the framework extracted from the BeepBeep AI Alt Text Generator.

---

## 📋 Prerequisites

- PHP 7.4 or higher
- WordPress 5.8 or higher
- Basic understanding of OOP PHP
- Familiarity with WordPress plugin development

---

## 🚀 Quick Start (5 Minutes)

### Step 1: Copy the Boilerplate

```bash
# Copy the framework-boilerplate directory
cp -r framework-boilerplate/ /path/to/wordpress/wp-content/plugins/my-awesome-plugin/

# Navigate to your new plugin
cd /path/to/wordpress/wp-content/plugins/my-awesome-plugin/
```

### Step 2: Customize Plugin Header

Edit `plugin-name.php`:

```php
/**
 * Plugin Name: My Awesome Plugin
 * Description: Does awesome things
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: my-awesome-plugin
 */
```

### Step 3: Update Constants

Replace all `MYPLUGIN_` prefixes with your plugin prefix:

```php
// Before
define( 'MYPLUGIN_VERSION', '1.0.0' );

// After
define( 'MYAWESOMEPLUGIN_VERSION', '1.0.0' );
```

### Step 4: Update Namespaces

Find and replace `MyPlugin` namespace with yours:

```bash
# Using sed (Linux/Mac)
find . -type f -name "*.php" -exec sed -i 's/namespace MyPlugin/namespace MyAwesomePlugin/g' {} +
find . -type f -name "*.php" -exec sed -i 's/\\MyPlugin\\/\\MyAwesomePlugin\\/g' {} +

# Or manually update in your IDE
```

### Step 5: Activate Plugin

```bash
# In WordPress admin
wp-admin/plugins.php → Activate "My Awesome Plugin"

# Or via WP-CLI
wp plugin activate my-awesome-plugin
```

**Done!** Your plugin is now running with the framework.

---

## 📝 Create Your First Feature (5 Minutes)

Let's create a simple feature that processes text.

### 1. Create the Service

**File**: `includes/services/class-text-service.php`

```php
<?php
declare(strict_types=1);

namespace MyAwesomePlugin\Services;

use MyAwesomePlugin\Core\Event_Bus;

class Text_Service {
    private Event_Bus $event_bus;

    public function __construct(Event_Bus $event_bus) {
        $this->event_bus = $event_bus;
    }

    public function process_text(string $text): array {
        if (empty($text)) {
            return [
                'success' => false,
                'message' => 'Text cannot be empty'
            ];
        }

        // Process the text
        $processed = strtoupper($text);

        // Emit event
        $this->event_bus->emit('text.processed', [
            'original' => $text,
            'processed' => $processed
        ]);

        return [
            'success' => true,
            'data' => $processed
        ];
    }
}
```

### 2. Create the Controller

**File**: `includes/controllers/class-text-controller.php`

```php
<?php
declare(strict_types=1);

namespace MyAwesomePlugin\Controllers;

use MyAwesomePlugin\Services\Text_Service;

class Text_Controller {
    private Text_Service $text_service;

    public function __construct(Text_Service $text_service) {
        $this->text_service = $text_service;
    }

    public function process(): array {
        // Permission check
        if (!current_user_can('edit_posts')) {
            return [
                'success' => false,
                'message' => 'Permission denied'
            ];
        }

        // Sanitize input
        $text = sanitize_text_field($_POST['text'] ?? '');

        // Delegate to service
        return $this->text_service->process_text($text);
    }
}
```

### 3. Register in Service Provider

**File**: `includes/core/class-service-provider.php`

```php
private static function register_services(Container $container): void {
    $container->singleton('service.text', function($c) {
        return new \MyAwesomePlugin\Services\Text_Service(
            $c->get('event_bus')
        );
    });
}

private static function register_controllers(Container $container): void {
    $container->singleton('controller.text', function($c) {
        return new \MyAwesomePlugin\Controllers\Text_Controller(
            $c->get('service.text')
        );
    });
}
```

### 4. Register Route

**File**: `includes/bootstrap.php`

```php
function myawesomeplugin_register_routes(Container $container): void {
    $router = $container->get('router');

    // Register AJAX route
    $router->ajax('process_text', 'controller.text', 'process');

    $router->init();
}
```

### 5. Test Your Feature

**JavaScript** (in your admin page):

```javascript
jQuery.ajax({
    url: ajaxurl,
    type: 'POST',
    data: {
        action: 'process_text',
        text: 'hello world',
        nonce: '<?php echo wp_create_nonce("process_text"); ?>'
    },
    success: function(response) {
        console.log(response); // {success: true, data: "HELLO WORLD"}
    }
});
```

**Done!** You've created a complete feature with service, controller, and routing.

---

## 🎓 Understanding the Architecture

### The Request Flow

```
1. User Action (AJAX/REST)
        ↓
2. WordPress Hook (wp_ajax_*)
        ↓
3. Router (validates nonce, auth)
        ↓
4. Controller (sanitizes input)
        ↓
5. Service (business logic)
        ↓
6. Event Bus (emits events)
        ↓
7. Response (JSON)
```

### Core Components

| Component | Purpose | Location |
|-----------|---------|----------|
| **Container** | Dependency injection | `includes/core/class-container.php` |
| **Event Bus** | Pub/sub events | `includes/core/class-event-bus.php` |
| **Router** | HTTP routing | `includes/core/class-router.php` |
| **Service Provider** | Service registry | `includes/core/class-service-provider.php` |
| **Services** | Business logic | `includes/services/` |
| **Controllers** | HTTP handling | `includes/controllers/` |

---

## 🔧 Common Tasks

### Add a New AJAX Endpoint

```php
// 1. Create controller method
class My_Controller {
    public function my_action(): array {
        // Handle request
    }
}

// 2. Register in Service Provider
$container->singleton('controller.my', function($c) {
    return new My_Controller($c->get('service.my'));
});

// 3. Register route in bootstrap.php
$router->ajax('my_action', 'controller.my', 'my_action');
```

### Add a REST API Endpoint

```php
// In bootstrap.php
$router->rest(
    '/items',              // /wp-json/myplugin/v1/items
    'controller.items',    // Controller service
    'get_items',          // Method
    'GET'                 // HTTP method
);
```

### Listen to Events

```php
$event_bus = myawesomeplugin_service('event_bus');

$event_bus->on('text.processed', function($data) {
    // Log to file
    error_log('Processed: ' . $data['processed']);
});
```

### Emit Events

```php
// In your service
$this->event_bus->emit('custom.event', [
    'key' => 'value'
]);
```

### Use a Service Anywhere

```php
// Get service from container
$text_service = myawesomeplugin_service('service.text');
$result = $text_service->process_text('hello');
```

---

## 📁 File Organization

```
my-awesome-plugin/
├── plugin-name.php              # Main file
├── includes/
│   ├── core/                    # Framework (don't modify)
│   │   ├── class-container.php
│   │   ├── class-event-bus.php
│   │   ├── class-router.php
│   │   └── class-service-provider.php
│   ├── services/                # Add your services here
│   │   └── class-example-service.php
│   ├── controllers/             # Add your controllers here
│   │   └── class-example-controller.php
│   ├── class-plugin.php         # Main plugin class
│   ├── class-loader.php         # Hook loader
│   ├── class-activator.php      # Activation logic
│   ├── class-deactivator.php    # Deactivation logic
│   └── bootstrap.php            # DI setup & routes
├── admin/                       # Admin UI files
├── assets/                      # JS/CSS assets
└── tests/                       # PHPUnit tests
```

---

## ✅ Best Practices

### ✅ DO

- **Keep controllers thin** - Only input validation and delegation
- **Put business logic in services** - Framework-agnostic
- **Use type hints** - `declare(strict_types=1);`
- **Emit events** - For cross-cutting concerns
- **Inject dependencies** - Via constructor
- **Write tests** - Services are easy to test

### ❌ DON'T

- **Don't put logic in controllers** - Delegate to services
- **Don't use global variables** - Use DI container
- **Don't skip type declarations** - Use strict types
- **Don't tightly couple** - Inject dependencies
- **Don't forget nonces** - Router handles it automatically
- **Don't skip permission checks** - Always check capabilities

---

## 🧪 Writing Tests

### Unit Test Example

```php
<?php
use PHPUnit\Framework\TestCase;
use Mockery;

class Text_Service_Test extends TestCase {
    public function test_process_text_success() {
        // Mock event bus
        $event_mock = Mockery::mock(Event_Bus::class);
        $event_mock->shouldReceive('emit')->once();

        // Create service with mock
        $service = new Text_Service($event_mock);

        // Test
        $result = $service->process_text('hello');

        $this->assertTrue($result['success']);
        $this->assertEquals('HELLO', $result['data']);
    }
}
```

### Integration Test Example

```php
<?php
class Plugin_Integration_Test extends WP_UnitTestCase {
    private $container;

    public function setUp(): void {
        parent::setUp();
        $this->container = myawesomeplugin_init();
    }

    public function test_full_flow() {
        $service = $this->container->get('service.text');
        $result = $service->process_text('hello world');

        $this->assertTrue($result['success']);
    }
}
```

---

## 📚 Next Steps

1. **Read the full architecture doc**: `PLUGIN_FRAMEWORK_ARCHITECTURE.md`
2. **Explore the source plugin**: See how patterns are used in production
3. **Write tests**: Use the testing examples above
4. **Customize**: Add your own services and features

---

## 🆘 Troubleshooting

### "Service not found in container"

**Problem**: `Exception: Service 'service.example' not found`

**Solution**: Register the service in `class-service-provider.php`:

```php
$container->singleton('service.example', function($c) {
    return new Example_Service($c->get('event_bus'));
});
```

### "Call to undefined function"

**Problem**: Helper functions like `myawesomeplugin_service()` not found

**Solution**: Update function prefixes in `bootstrap.php` to match your plugin name.

### AJAX returns -1

**Problem**: AJAX request returns `-1`

**Solution**:
1. Verify nonce is being sent: `nonce: '<?php echo wp_create_nonce("action_name"); ?>'`
2. Check route is registered in `bootstrap.php`
3. Verify action name matches: `wp_ajax_action_name`

### Permission denied

**Problem**: Controller returns "Permission denied"

**Solution**: Check capability in controller matches user:

```php
if (!current_user_can('edit_posts')) {  // Use appropriate capability
    return ['success' => false, 'message' => 'Permission denied'];
}
```

---

## 💡 Tips & Tricks

### Debugging

```php
// Enable debug logging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// In your code
error_log('Debug: ' . print_r($data, true));
```

### Check Registered Services

```php
$container = myawesomeplugin_container();
$services = $container->getRegistered();
error_log(print_r($services, true));
```

### Check Registered Routes

```php
$router = myawesomeplugin_service('router');
$routes = $router->get_ajax_routes();
error_log(print_r($routes, true));
```

---

## 🎯 Example Plugins You Can Build

- **Contact Form** - Service for email sending, controller for form submission
- **Custom Dashboard** - Service for data aggregation, REST endpoints for charts
- **Import/Export** - Service for data transformation, background processing with events
- **Analytics Tracker** - Service for tracking, event listeners for WordPress actions
- **API Integration** - Service for external API, caching layer, REST proxy

---

## 📖 Additional Resources

- **Full Architecture Guide**: `PLUGIN_FRAMEWORK_ARCHITECTURE.md`
- **Source Plugin**: BeepBeep AI Alt Text Generator (includes/)
- **WordPress Plugin Handbook**: https://developer.wordpress.org/plugins/
- **PHP DI Patterns**: https://phptherightway.com/#dependency_injection

---

**Questions?** Check the architecture documentation or examine the source plugin for real-world examples.

**Happy Coding!** 🚀
