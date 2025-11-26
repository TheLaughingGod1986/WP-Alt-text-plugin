# New Plugin Blueprint

This document describes how to create a new Optti WordPress plugin using the Optti Framework.

## Folder Structure

```
my-plugin/
├── my-plugin.php (main file)
├── includes/
│   └── class-my-plugin.php (extends PluginBase)
├── views/
│   ├── settings.php
│   └── dashboard.php
├── assets/
│   ├── css/
│   │   └── my-plugin-specific.css
│   └── js/
│       └── my-plugin-specific.js
└── framework/ (Git submodule)
    └── ...
```

## Main Plugin File Template

```php
<?php
/**
 * Plugin Name: My Optti Plugin
 * Description: Description
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPLv2 or later
 * Text Domain: my-optti-plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'MY_PLUGIN_VERSION', '1.0.0' );
define( 'MY_PLUGIN_FILE', __FILE__ );
define( 'MY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MY_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Bootstrap framework
require_once __DIR__ . '/framework/loader.php';

Optti_Framework::bootstrap( __FILE__, [
    'plugin_slug'  => 'my-optti-plugin',
    'version'      => MY_PLUGIN_VERSION,
    'api_base_url' => 'https://alttext-ai-backend.onrender.com',
    'asset_url'    => MY_PLUGIN_URL . 'framework/dist/',
    'asset_dir'    => MY_PLUGIN_DIR . 'framework/dist/',
] );

// Load plugin class
require_once __DIR__ . '/includes/class-my-plugin.php';

// Global plugin instance
global $my_plugin_instance;

/**
 * Register activation hook.
 */
function my_plugin_activate() {
    global $my_plugin_instance;
    if ( $my_plugin_instance ) {
        $my_plugin_instance->activate();
    }
}

/**
 * Register deactivation hook.
 */
function my_plugin_deactivate() {
    global $my_plugin_instance;
    if ( $my_plugin_instance ) {
        $my_plugin_instance->deactivate();
    }
}

register_activation_hook( __FILE__, 'my_plugin_activate' );
register_deactivation_hook( __FILE__, 'my_plugin_deactivate' );

/**
 * Initialize plugin.
 */
function my_plugin_init() {
    global $my_plugin_instance;
    $config = Optti_Framework::get_config();
    $my_plugin_instance = new My_Plugin( MY_PLUGIN_FILE, $config );
}

my_plugin_init();
```

## Plugin Class Template

```php
<?php
/**
 * My Plugin Class
 *
 * @package MyPlugin
 */

namespace MyPlugin;

use Optti\Framework\PluginBase;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class My_Plugin extends PluginBase {

    /**
     * Get plugin name.
     *
     * @return string Plugin name.
     */
    public function get_plugin_name() {
        return __( 'My Optti Plugin', 'my-optti-plugin' );
    }

    /**
     * Get plugin slug.
     *
     * @return string Plugin slug.
     */
    public function get_plugin_slug() {
        return 'my-optti-plugin';
    }

    /**
     * Get text domain for translations.
     *
     * @return string Text domain.
     */
    protected function get_text_domain() {
        return 'my-optti-plugin';
    }

    /**
     * Register plugin-specific modules.
     *
     * @return void
     */
    protected function register_modules() {
        // Register your modules here.
        // Example:
        // require_once MY_PLUGIN_DIR . 'includes/modules/class-my-module.php';
        // $this->register_module( new \MyPlugin\Modules\My_Module() );
    }

    /**
     * Register admin menus.
     *
     * @return void
     */
    public function register_admin_menus() {
        add_menu_page(
            __( 'My Plugin', 'my-optti-plugin' ),
            __( 'My Plugin', 'my-optti-plugin' ),
            'manage_options',
            'my-optti-plugin',
            [ $this, 'render_dashboard_page' ],
            'dashicons-admin-generic',
            30
        );
    }

    /**
     * Render dashboard page.
     *
     * @return void
     */
    public function render_dashboard_page() {
        include MY_PLUGIN_DIR . 'views/dashboard.php';
    }

    /**
     * Register REST routes.
     *
     * @return void
     */
    public function register_rest_routes() {
        register_rest_route( 'my-plugin/v1', '/settings', [
            'methods'  => 'POST',
            'callback' => [ $this, 'handle_settings_save' ],
            'permission_callback' => function() {
                return current_user_can( 'manage_options' );
            },
        ] );
    }

    /**
     * Register AJAX handlers.
     *
     * @return void
     */
    protected function register_ajax_handlers() {
        add_action( 'wp_ajax_my_plugin_action', [ $this, 'handle_ajax_action' ] );
    }

    /**
     * Handle AJAX action.
     *
     * @return void
     */
    public function handle_ajax_action() {
        check_ajax_referer( 'my-plugin-nonce', 'nonce' );
        // Handle AJAX request.
        wp_send_json_success( [ 'message' => 'Success' ] );
    }
}
```

## Git Submodule Setup

After creating your plugin directory:

```bash
cd my-plugin
git init
git submodule add git@github.com:optti/plugin-framework.git framework
cd framework
git checkout v1.0.0
cd ..
git add .
git commit -m "Initial commit"
```

## Next Steps

1. Implement your plugin-specific modules
2. Create admin pages and views
3. Add plugin-specific CSS/JS
4. Test thoroughly
5. Deploy

