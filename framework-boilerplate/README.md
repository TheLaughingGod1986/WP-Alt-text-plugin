# WordPress Plugin Framework Boilerplate

> **Production-ready plugin starter template with modern architecture**

This boilerplate provides a complete WordPress plugin framework with dependency injection, event-driven architecture, and clean separation of concerns.

---

## ✨ Features

✅ **Dependency Injection Container** - Manage services and dependencies
✅ **Event-Driven Architecture** - Decouple components with pub/sub pattern
✅ **AJAX & REST Router** - Simplified endpoint registration
✅ **Service Layer** - Framework-agnostic business logic
✅ **Controller Layer** - Clean HTTP request handling
✅ **Fully Documented** - Every file includes detailed comments
✅ **Production-Tested** - Extracted from real WordPress plugin with 166 tests

---

## 🚀 Quick Start

### 1. Copy This Directory

```bash
cp -r framework-boilerplate/ /path/to/wordpress/wp-content/plugins/your-plugin-name/
```

### 2. Find & Replace

Replace these throughout all files:

| Find | Replace With | Example |
|------|--------------|---------|
| `MyPlugin` | `YourPlugin` | `YourAwesomePlugin` |
| `my-plugin` | `your-plugin` | `your-awesome-plugin` |
| `MYPLUGIN_` | `YOURPLUGIN_` | `YOURPLUGIN_VERSION` |
| `myplugin_` | `yourplugin_` | `yourplugin_init()` |

**Tip**: Use your IDE's "Find & Replace in Files" feature

### 3. Update Plugin Header

Edit `plugin-name.php`:

```php
/**
 * Plugin Name: Your Awesome Plugin
 * Description: Your plugin description
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yoursite.com
 * Text Domain: your-plugin
 */
```

### 4. Rename Main File

```bash
mv plugin-name.php your-plugin-name.php
```

### 5. Activate & Test

- Activate plugin in WordPress admin
- Check for any errors
- Verify framework is initialized

---

## 📁 File Structure

```
framework-boilerplate/
├── plugin-name.php                    # Main plugin file (rename this)
├── includes/
│   ├── core/                          # Framework Core (Don't modify)
│   │   ├── class-container.php        # ✨ DI Container
│   │   ├── class-event-bus.php        # ✨ Event System
│   │   ├── class-router.php           # ✨ HTTP Router
│   │   └── class-service-provider.php # ✨ Service Registry (Edit this)
│   ├── services/                      # Your Business Logic (Add services here)
│   │   └── class-example-service.php  # Template service
│   ├── controllers/                   # Your HTTP Handlers (Add controllers here)
│   │   └── class-example-controller.php # Template controller
│   ├── class-plugin.php               # Main plugin class
│   ├── class-loader.php               # Hook loader
│   ├── class-activator.php            # Activation logic (Edit this)
│   ├── class-deactivator.php          # Deactivation logic (Edit this)
│   └── bootstrap.php                  # Initialize framework (Edit routes here)
├── admin/                             # Admin UI (Add admin pages here)
├── tests/                             # PHPUnit tests (Add tests here)
└── README.md                          # This file
```

---

## 🎯 What to Edit

### ✏️ Always Edit

1. **`includes/core/class-service-provider.php`** - Register your services
2. **`includes/bootstrap.php`** - Register your routes
3. **`includes/class-activator.php`** - Add activation logic
4. **`includes/services/`** - Add your service classes
5. **`includes/controllers/`** - Add your controller classes

### ❌ Never Edit

1. **`includes/core/class-container.php`** - Framework DI container
2. **`includes/core/class-event-bus.php`** - Framework event system
3. **`includes/core/class-router.php`** - Framework router

### 🔧 Optionally Edit

1. **`includes/class-plugin.php`** - Main plugin orchestration
2. **`includes/class-loader.php`** - Hook loader
3. **`includes/class-deactivator.php`** - Deactivation cleanup

---

## 📝 Creating Your First Feature

### Step 1: Create a Service

**File**: `includes/services/class-greeting-service.php`

```php
<?php
declare(strict_types=1);

namespace YourPlugin\Services;

use YourPlugin\Core\Event_Bus;

class Greeting_Service {
    private Event_Bus $event_bus;

    public function __construct(Event_Bus $event_bus) {
        $this->event_bus = $event_bus;
    }

    public function greet(string $name): array {
        if (empty($name)) {
            return ['success' => false, 'message' => 'Name required'];
        }

        $greeting = "Hello, {$name}!";

        $this->event_bus->emit('greeting.sent', ['name' => $name]);

        return ['success' => true, 'greeting' => $greeting];
    }
}
```

### Step 2: Create a Controller

**File**: `includes/controllers/class-greeting-controller.php`

```php
<?php
declare(strict_types=1);

namespace YourPlugin\Controllers;

use YourPlugin\Services\Greeting_Service;

class Greeting_Controller {
    private Greeting_Service $greeting_service;

    public function __construct(Greeting_Service $greeting_service) {
        $this->greeting_service = $greeting_service;
    }

    public function handle_greeting(): array {
        if (!current_user_can('read')) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        $name = sanitize_text_field($_POST['name'] ?? '');

        return $this->greeting_service->greet($name);
    }
}
```

### Step 3: Register in Service Provider

**Edit**: `includes/core/class-service-provider.php`

```php
private static function register_services(Container $container): void {
    // Your new service
    $container->singleton('service.greeting', function($c) {
        return new \YourPlugin\Services\Greeting_Service(
            $c->get('event_bus')
        );
    });
}

private static function register_controllers(Container $container): void {
    // Your new controller
    $container->singleton('controller.greeting', function($c) {
        return new \YourPlugin\Controllers\Greeting_Controller(
            $c->get('service.greeting')
        );
    });
}
```

### Step 4: Register Route

**Edit**: `includes/bootstrap.php`

```php
function yourplugin_register_routes(Container $container): void {
    $router = $container->get('router');

    // Your new route
    $router->ajax('send_greeting', 'controller.greeting', 'handle_greeting');

    $router->init();
}
```

### Step 5: Test

**JavaScript**:

```javascript
jQuery.ajax({
    url: ajaxurl,
    type: 'POST',
    data: {
        action: 'send_greeting',
        name: 'John',
        nonce: '<?php echo wp_create_nonce("send_greeting"); ?>'
    },
    success: function(response) {
        console.log(response.data.greeting); // "Hello, John!"
    }
});
```

**Done!** ✅

---

## 🧩 Framework Components

### Container (DI)

```php
// Get service from container
$service = yourplugin_service('service.greeting');

// Get container
$container = yourplugin_container();
```

### Event Bus

```php
$event_bus = yourplugin_service('event_bus');

// Listen to event
$event_bus->on('greeting.sent', function($data) {
    error_log('Greeted: ' . $data['name']);
});

// Emit event
$event_bus->emit('custom.event', ['key' => 'value']);
```

### Router

```php
$router = yourplugin_service('router');

// AJAX route
$router->ajax('action_name', 'controller.name', 'method_name');

// REST route
$router->rest('/endpoint', 'controller.name', 'method_name', 'GET');
```

---

## 📚 Documentation

- **Architecture Guide**: `../PLUGIN_FRAMEWORK_ARCHITECTURE.md` (60+ pages)
- **Quick Start Guide**: `../PLUGIN_FRAMEWORK_QUICKSTART.md` (20+ pages)
- **Inline Comments**: Every file includes detailed PHPDoc

---

## ✅ Checklist After Setup

- [ ] Renamed `plugin-name.php` to your plugin name
- [ ] Updated plugin header (name, description, author)
- [ ] Find & replaced all namespaces (`MyPlugin` → `YourPlugin`)
- [ ] Find & replaced all constants (`MYPLUGIN_` → `YOURPLUGIN_`)
- [ ] Find & replaced all functions (`myplugin_` → `yourplugin_`)
- [ ] Updated text domain (`my-plugin` → `your-plugin`)
- [ ] Created first service in `includes/services/`
- [ ] Created first controller in `includes/controllers/`
- [ ] Registered service in Service Provider
- [ ] Registered route in bootstrap
- [ ] Tested AJAX/REST endpoint
- [ ] Plugin activated without errors

---

## 🎓 Learning Path

1. **Start here** - Copy boilerplate, follow quick start
2. **Read inline docs** - Every file is heavily commented
3. **Create first feature** - Service + Controller + Route
4. **Understand flow** - Request → Router → Controller → Service
5. **Add events** - Use Event Bus for cross-cutting concerns
6. **Write tests** - Services are easy to unit test
7. **Read architecture doc** - Deep dive into patterns

---

## 🆘 Common Issues

### Plugin doesn't activate

**Check**:
- PHP version (7.4+)
- WordPress version (5.8+)
- No syntax errors (check error log)
- All required files present

### AJAX returns -1

**Check**:
- Route registered in `bootstrap.php`
- Nonce created with correct action name
- Action name matches route: `wp_ajax_action_name`

### "Service not found"

**Check**:
- Service registered in `class-service-provider.php`
- Namespace matches in registration
- No typos in service name

---

## 🔧 Advanced Usage

### Add Admin Page

```php
// In includes/class-plugin.php
private function define_admin_hooks() {
    $this->loader->add_action('admin_menu', $this, 'add_admin_menu');
}

public function add_admin_menu() {
    add_menu_page(
        'My Plugin',
        'My Plugin',
        'manage_options',
        'my-plugin',
        [$this, 'admin_page_callback']
    );
}
```

### Add Shortcode

```php
// In includes/class-plugin.php
private function define_public_hooks() {
    $this->loader->add_action('init', $this, 'register_shortcodes');
}

public function register_shortcodes() {
    add_shortcode('my_shortcode', [$this, 'shortcode_callback']);
}
```

### Schedule Cron

```php
// In includes/class-activator.php
private static function schedule_events() {
    if (!wp_next_scheduled('myplugin_daily_task')) {
        wp_schedule_event(time(), 'daily', 'myplugin_daily_task');
    }
}
```

---

## 🎯 What This Boilerplate Gives You

✅ **Production-Ready** - Used in live WordPress plugin
✅ **Tested Architecture** - 166 tests, 100% passing
✅ **Modern PHP** - Strict types, dependency injection
✅ **Scalable** - Grows with your plugin
✅ **Maintainable** - Clear separation of concerns
✅ **Testable** - Easy unit and integration testing
✅ **Documented** - Extensive inline documentation
✅ **Best Practices** - Follows WordPress & PHP standards

---

## 📞 Need Help?

- **Architecture Doc**: `../PLUGIN_FRAMEWORK_ARCHITECTURE.md`
- **Quick Start**: `../PLUGIN_FRAMEWORK_QUICKSTART.md`
- **Source Plugin**: Browse `includes/` for real examples
- **WordPress Docs**: https://developer.wordpress.org/plugins/

---

**Built From**: BeepBeep AI Alt Text Generator Plugin
**Framework Version**: 5.0.0
**Tested With**: WordPress 6.0+ | PHP 7.4-8.3

---

**Start building your plugin now!** 🚀
