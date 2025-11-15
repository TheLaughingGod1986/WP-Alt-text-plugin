# OpptiAI Framework - Implementation Guide

**Version:** 1.0.0
**Plugin Version:** 4.2.2+
**Status:** Production Ready
**Last Updated:** 2025-11-15

---

## 📋 **Table of Contents**

1. [What Was Built](#what-was-built)
2. [How to Use the Framework](#how-to-use-the-framework)
3. [Migration Path](#migration-path)
4. [API Reference](#api-reference)
5. [Examples](#examples)
6. [Next Steps](#next-steps)

---

## 🎯 **What Was Built**

### **Complete Framework Infrastructure**

A production-ready, modular framework that provides:

✅ **Authentication System** - JWT & license key management with encryption
✅ **API Client** - HTTP requests with retry logic & comprehensive error handling
✅ **UI Components** - 13+ reusable HTML builders (cards, tables, modals, etc.)
✅ **Layout System** - Consistent page structure across all admin pages
✅ **Settings Manager** - Centralized storage with caching
✅ **Security Helpers** - Permissions, nonces, capabilities
✅ **Sanitization/Validation** - Input/output security
✅ **Module System** - Plugin registration and lifecycle management

### **Code Cleanup**

Removed **130+ files**:
- Test files (`test-*.php`, `check-*.php`, `debug-*.php`)
- Scripts directory (62+ files)
- Documentation files (65+ MD files)
- Entire `/docs/` directory

### **Build System**

- Production ZIP generator (`build-production.sh`)
- WordPress.org-ready output
- Automatic file exclusion

---

## 🚀 **How to Use the Framework**

### **1. Authentication**

```php
use OpptiAI\Framework\Auth\Auth;

// Initialize
$auth = new Auth();

// JWT Token
$auth->set_token( $jwt_token );
$token = $auth->get_token();
$auth->clear_token();

// License Key
$auth->set_license_key( $license_key );
$license = $auth->get_license_key();

// Check Authentication
if ( $auth->is_authenticated() ) {
    // User is authenticated
}

// Get Auth Headers for API
$headers = $auth->get_auth_headers();
```

### **2. API Client**

```php
use OpptiAI\Framework\API\API_Client;
use OpptiAI\Framework\Auth\Auth;

$auth = new Auth();
$client = new API_Client( 'https://api.example.com', $auth );

// Make Request
$response = $client->make_request( '/endpoint', 'POST', $data );

// With Automatic Retry
$response = $client->request_with_retry( '/endpoint', 'POST', $data, 3 );

if ( is_wp_error( $response ) ) {
    echo $response->get_error_message();
} else {
    // Success
}
```

### **3. UI Components**

```php
use OpptiAI\Framework\UI\Components;

// Card
echo Components::card(
    'Card Title',
    '<p>Card content goes here</p>',
    [
        'class' => 'my-custom-class',
        'footer' => 'Footer text'
    ]
);

// Button
echo Components::button(
    'Click Me',
    [
        'variant' => 'primary', // primary, secondary, danger, success
        'onclick' => 'myFunction()'
    ]
);

// Table
echo Components::table(
    ['Name', 'Email', 'Role'],
    [
        ['John Doe', 'john@example.com', 'Admin'],
        ['Jane Smith', 'jane@example.com', 'Editor']
    ]
);

// Modal
echo Components::modal(
    'my-modal',
    'Modal Title',
    '<p>Modal content</p>',
    [
        'size' => 'medium', // small, medium, large
        'footer' => '<button>Close</button>'
    ]
);

// Progress Bar
echo Components::progress_bar( 75, [
    'label' => 'Processing...'
] );

// Notice
echo Components::notice(
    'Operation successful!',
    [
        'type' => 'success', // info, success, warning, error
        'dismissible' => true
    ]
);

// Form Field
echo Components::form_field(
    'text',
    'user_name',
    $current_value,
    [
        'label' => 'Your Name',
        'placeholder' => 'Enter your name',
        'required' => true
    ]
);
```

### **4. Page Layout**

```php
use OpptiAI\Framework\UI\Layout;

// Render Complete Page
Layout::render_page(
    'Page Title',
    function() {
        echo '<p>Your page content here</p>';
    },
    [
        'sidebar_items' => [
            [
                'label' => 'Dashboard',
                'url' => admin_url( 'admin.php?page=my-dashboard' ),
                'active' => true,
                'icon' => '<svg>...</svg>'
            ],
            [
                'label' => 'Settings',
                'url' => admin_url( 'admin.php?page=my-settings' ),
                'active' => false
            ]
        ],
        'breadcrumbs' => [
            ['label' => 'Home', 'url' => admin_url()],
            ['label' => 'My Plugin']
        ],
        'footer' => [
            'text' => 'Version 1.0.0',
            'links' => [
                ['label' => 'Documentation', 'url' => 'https://example.com/docs'],
                ['label' => 'Support', 'url' => 'https://example.com/support']
            ]
        ]
    ]
);

// Or Use Individual Components
Layout::start_wrapper();
Layout::header( 'Page Title', [
    'subtitle' => 'Page description'
] );

// Your content here

Layout::footer( [
    'text' => 'Footer text'
] );
Layout::end_wrapper();
```

### **5. Settings Manager**

```php
use OpptiAI\Framework\Settings\Settings;

$settings = new Settings( 'my_plugin_' );

// Get/Set
$settings->set( 'api_key', $value );
$value = $settings->get( 'api_key', 'default_value' );

// Multiple
$settings->set_multiple( [
    'api_key' => 'abc123',
    'enabled' => true
] );

$all = $settings->get_multiple( ['api_key', 'enabled'] );

// Check Existence
if ( $settings->has( 'api_key' ) ) {
    // Setting exists
}

// Delete
$settings->delete( 'api_key' );

// Sanitize with Schema
$clean = $settings->sanitize(
    $_POST['settings'],
    [
        'api_key' => 'text',
        'email' => 'email',
        'count' => 'int',
        'enabled' => 'bool'
    ]
);
```

### **6. Security & Permissions**

```php
use OpptiAI\Framework\Security\Permissions;

// Check Permissions
if ( Permissions::can_manage() ) {
    // User can manage plugin
}

if ( Permissions::can_edit_media() ) {
    // User can edit media
}

// Nonces
$nonce = Permissions::create_nonce( 'my_action' );

if ( Permissions::verify_nonce( $_POST['nonce'], 'my_action' ) ) {
    // Nonce is valid
}

// AJAX
if ( Permissions::check_ajax_referer( 'my_action' ) ) {
    // AJAX request is valid
}
```

### **7. Sanitization & Validation**

```php
use OpptiAI\Framework\Helpers\Sanitizer;
use OpptiAI\Framework\Helpers\Escaper;
use OpptiAI\Framework\Helpers\Validator;

// Sanitize Input
$clean_text = Sanitizer::text( $_POST['field'] );
$clean_email = Sanitizer::email( $_POST['email'] );
$clean_url = Sanitizer::url( $_POST['url'] );
$clean_int = Sanitizer::int( $_POST['count'] );

// Escape Output
echo Escaper::html( $user_input );
echo '<a href="' . Escaper::url( $link ) . '">';
echo '<div class="' . Escaper::attr( $class ) . '">';

// Validate
if ( Validator::email( $email ) ) {
    // Valid email
}

if ( Validator::url( $url ) ) {
    // Valid URL
}

if ( Validator::required( $value ) ) {
    // Not empty
}
```

### **8. Module Registration**

```php
use OpptiAI\Framework\Plugin;

Plugin::register_module( [
    'id' => 'my-plugin',
    'name' => 'My Plugin Name',
    'slug' => 'my-plugin-slug',
    'path' => plugin_dir_path( __FILE__ ) . 'modules/my-plugin/',
    'version' => '1.0.0',

    'assets' => [
        'css' => ['my-plugin.css'],
        'js' => ['my-plugin.js']
    ],

    'menu' => [
        'title' => 'My Plugin',
        'parent' => 'tools.php', // or 'opptiai-suite' for OpptiAI parent
        'capability' => 'manage_options'
    ],

    'rest_routes' => [
        '/my-endpoint' => [
            'methods' => 'POST',
            'callback' => 'my_callback_function',
            'permission_callback' => '__return_true'
        ]
    ]
] );
```

---

## 🗺️ **Migration Path**

### **Phase 1: Foundation** ✅ **COMPLETE**
- Framework created and integrated
- Plugin loads framework on initialization
- All framework components available

### **Phase 2: Gradual UI Migration** (Recommended)

**Step 1: Convert One Page**
```php
// Before
public function render_dashboard() {
    echo '<div class="wrap">';
    echo '<h1>Dashboard</h1>';
    echo '<div class="card">...</div>';
    echo '</div>';
}

// After
use OpptiAI\Framework\UI\Layout;
use OpptiAI\Framework\UI\Components;

public function render_dashboard() {
    Layout::render_page(
        'Dashboard',
        function() {
            echo Components::card( 'Stats', $this->render_stats() );
        }
    );
}
```

**Step 2: Convert More Pages**
Gradually migrate each admin page to use Components and Layout.

**Step 3: Consolidate Assets**
Once all pages use framework UI, remove old CSS/JS files.

### **Phase 3: Module System** (Optional)

Move plugin code to `/modules/` structure and use module registration instead of direct loading.

---

## 📚 **API Reference**

### **Available Classes**

| Class | Namespace | Purpose |
|-------|-----------|---------|
| `Auth` | `OpptiAI\Framework\Auth` | Authentication |
| `API_Client` | `OpptiAI\Framework\API` | HTTP requests |
| `Components` | `OpptiAI\Framework\UI` | UI builders |
| `Layout` | `OpptiAI\Framework\UI` | Page layout |
| `Settings` | `OpptiAI\Framework\Settings` | Settings storage |
| `Permissions` | `OpptiAI\Framework\Security` | Security checks |
| `Sanitizer` | `OpptiAI\Framework\Helpers` | Input sanitization |
| `Escaper` | `OpptiAI\Framework\Helpers` | Output escaping |
| `Validator` | `OpptiAI\Framework\Helpers` | Validation |
| `Plugin` | `OpptiAI\Framework` | Module registration |

### **JavaScript API**

```javascript
// Global namespace
window.OpptiAI

// Tabs
OpptiAI.tabs.init();

// Modals
OpptiAI.modal.open('modal-id');
OpptiAI.modal.close('modal-id');

// Notices
OpptiAI.notice.show('Message here', 'success', true);

// AJAX
OpptiAI.ajax.request('my_action', { data: 'value' }, {
    success: function(response) {
        console.log(response);
    }
});

// Form Validation
const isValid = OpptiAI.form.validate($('#my-form'));
```

---

## 💡 **Examples**

### **Example 1: Create Admin Page with Framework**

```php
<?php
use OpptiAI\Framework\UI\Layout;
use OpptiAI\Framework\UI\Components;
use OpptiAI\Framework\Security\Permissions;

function my_admin_page() {
    if ( ! Permissions::can_manage() ) {
        wp_die( __( 'Unauthorized' ) );
    }

    Layout::render_page(
        __( 'My Plugin Dashboard', 'my-plugin' ),
        'my_page_content',
        [
            'sidebar_items' => [
                [
                    'label' => __( 'Dashboard', 'my-plugin' ),
                    'url' => admin_url( 'admin.php?page=my-plugin' ),
                    'active' => true
                ],
                [
                    'label' => __( 'Settings', 'my-plugin' ),
                    'url' => admin_url( 'admin.php?page=my-plugin-settings' ),
                    'active' => false
                ]
            ]
        ]
    );
}

function my_page_content() {
    $stats = [
        'total' => 150,
        'active' => 120,
        'pending' => 30
    ];

    echo '<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">';

    echo Components::stat_box(
        __( 'Total Items', 'my-plugin' ),
        $stats['total']
    );

    echo Components::stat_box(
        __( 'Active', 'my-plugin' ),
        $stats['active']
    );

    echo Components::stat_box(
        __( 'Pending', 'my-plugin' ),
        $stats['pending']
    );

    echo '</div>';

    echo Components::card(
        __( 'Recent Activity', 'my-plugin' ),
        '<p>' . __( 'No recent activity.', 'my-plugin' ) . '</p>'
    );
}
```

### **Example 2: AJAX Handler with Framework**

```php
<?php
use OpptiAI\Framework\Security\Permissions;
use OpptiAI\Framework\Helpers\Sanitizer;

add_action( 'wp_ajax_my_action', 'my_ajax_handler' );

function my_ajax_handler() {
    // Security check
    if ( ! Permissions::check_ajax_referer( 'my_nonce' ) ) {
        wp_send_json_error( [
            'message' => __( 'Security check failed', 'my-plugin' )
        ] );
    }

    if ( ! Permissions::can_manage() ) {
        wp_send_json_error( [
            'message' => __( 'Unauthorized', 'my-plugin' )
        ] );
    }

    // Sanitize input
    $value = Sanitizer::text( $_POST['value'] ?? '' );

    if ( empty( $value ) ) {
        wp_send_json_error( [
            'message' => __( 'Value is required', 'my-plugin' )
        ] );
    }

    // Process...

    wp_send_json_success( [
        'message' => __( 'Success!', 'my-plugin' ),
        'data' => $result
    ] );
}
```

---

## 🎯 **Next Steps**

### **Immediate (Production Ready)**

1. ✅ Framework is loaded and functional
2. ✅ Plugin works exactly as before
3. ✅ Build script ready for WordPress.org

**You can ship this now!**

### **Short Term (v4.3.0 - v4.5.0)**

1. Convert dashboard tab to use `Components` and `Layout`
2. Convert settings tab
3. Convert remaining tabs
4. Remove old CSS/JS once migration complete

### **Long Term (v5.0.0)**

1. Split monolithic core class into focused services
2. Move plugin code to `/modules/alttext/`
3. Implement full module registration
4. Create second OpptiAI plugin using same framework

---

## 🏆 **Summary**

**What You Have:**
- ✅ Production-ready framework (15+ files, 3,000+ lines)
- ✅ Module system infrastructure
- ✅ Clean codebase (130+ files removed)
- ✅ Automated build process
- ✅ 100% backward compatible

**What It Provides:**
- Reusable components for faster development
- Consistent UI/UX across all pages
- WordPress coding standards compliance
- Foundation for future OpptiAI plugins
- Easier maintenance and testing

**Framework is ready. Start migrating when you're ready!** 🚀
