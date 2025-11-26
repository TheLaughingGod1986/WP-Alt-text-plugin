<?php
/**
 * Plugin Scaffold Script
 *
 * Creates a new Optti plugin from a template.
 *
 * Usage: php create-plugin.php --slug=my-plugin --name="My Plugin" --description="Plugin description"
 */

if ( php_sapi_name() !== 'cli' ) {
	die( 'This script can only be run from the command line.' );
}

// Parse command line arguments.
$options = getopt( '', [ 'slug:', 'name:', 'description:', 'author:', 'version:' ] );

$plugin_slug = $options['slug'] ?? '';
$plugin_name = $options['name'] ?? '';
$description = $options['description'] ?? 'A new Optti plugin';
$author = $options['author'] ?? 'Optti';
$version = $options['version'] ?? '1.0.0';

if ( empty( $plugin_slug ) || empty( $plugin_name ) ) {
	echo "Usage: php create-plugin.php --slug=my-plugin --name=\"My Plugin\" [--description=\"Description\"] [--author=\"Author\"] [--version=\"1.0.0\"]\n";
	exit( 1 );
}

// Validate slug format.
if ( ! preg_match( '/^[a-z0-9-]+$/', $plugin_slug ) ) {
	echo "Error: Plugin slug must contain only lowercase letters, numbers, and hyphens.\n";
	exit( 1 );
}

$plugin_dir = dirname( __DIR__, 2 ) . '/' . $plugin_slug;

if ( file_exists( $plugin_dir ) ) {
	echo "Error: Directory {$plugin_dir} already exists.\n";
	exit( 1 );
}

// Create directory structure.
mkdir( $plugin_dir, 0755, true );
mkdir( $plugin_dir . '/includes', 0755, true );
mkdir( $plugin_dir . '/views', 0755, true );
mkdir( $plugin_dir . '/assets/css', 0755, true );
mkdir( $plugin_dir . '/assets/js', 0755, true );

// Generate main plugin file.
$main_file = <<<PHP
<?php
/**
 * Plugin Name: {$plugin_name}
 * Description: {$description}
 * Version: {$version}
 * Author: {$author}
 * License: GPLv2 or later
 * Text Domain: {$plugin_slug}
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( '{$constant_prefix}_VERSION', '{$version}' );
define( '{$constant_prefix}_FILE', __FILE__ );
define( '{$constant_prefix}_DIR', plugin_dir_path( __FILE__ ) );
define( '{$constant_prefix}_URL', plugin_dir_url( __FILE__ ) );
define( '{$constant_prefix}_BASENAME', plugin_basename( __FILE__ ) );

// Bootstrap framework
require_once __DIR__ . '/framework/loader.php';

Optti_Framework::bootstrap( __FILE__, [
	'plugin_slug'  => '{$plugin_slug}',
	'version'      => {$constant_prefix}_VERSION,
	'api_base_url' => 'https://alttext-ai-backend.onrender.com',
	'asset_url'    => {$constant_prefix}_URL . 'framework/dist/',
	'asset_dir'    => {$constant_prefix}_DIR . 'framework/dist/',
] );

// Load plugin class
require_once __DIR__ . '/includes/class-{$class_name}.php';

// Global plugin instance
global \${$var_name}_instance;

/**
 * Register activation hook.
 */
function {$plugin_slug}_activate() {
	global \${$var_name}_instance;
	if ( \${$var_name}_instance ) {
		\${$var_name}_instance->activate();
	}
}

/**
 * Register deactivation hook.
 */
function {$plugin_slug}_deactivate() {
	global \${$var_name}_instance;
	if ( \${$var_name}_instance ) {
		\${$var_name}_instance->deactivate();
	}
}

register_activation_hook( __FILE__, '{$plugin_slug}_activate' );
register_deactivation_hook( __FILE__, '{$plugin_slug}_deactivate' );

/**
 * Initialize plugin.
 */
function {$plugin_slug}_init() {
	global \${$var_name}_instance;
	\$config = Optti_Framework::get_config();
	\${$var_name}_instance = new {$namespace}\\{$class_name}( {$constant_prefix}_FILE, \$config );
}

{$plugin_slug}_init();
PHP;

$constant_prefix = strtoupper( str_replace( '-', '_', $plugin_slug ) );
$class_name = str_replace( ' ', '_', ucwords( str_replace( '-', ' ', $plugin_slug ) ) );
$var_name = str_replace( '-', '_', $plugin_slug );
$namespace = str_replace( ' ', '', ucwords( str_replace( '-', ' ', $plugin_slug ) ) );

file_put_contents( $plugin_dir . '/' . $plugin_slug . '.php', $main_file );

// Generate plugin class file.
$class_file = <<<PHP
<?php
/**
 * {$plugin_name} Class
 *
 * @package {$namespace}
 */

namespace {$namespace};

use Optti\\Framework\\PluginBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class {$class_name} extends PluginBase {

	/**
	 * Get plugin name.
	 *
	 * @return string Plugin name.
	 */
	public function get_plugin_name() {
		return __( '{$plugin_name}', '{$plugin_slug}' );
	}

	/**
	 * Get plugin slug.
	 *
	 * @return string Plugin slug.
	 */
	public function get_plugin_slug() {
		return '{$plugin_slug}';
	}

	/**
	 * Get text domain for translations.
	 *
	 * @return string Text domain.
	 */
	protected function get_text_domain() {
		return '{$plugin_slug}';
	}

	/**
	 * Register plugin-specific modules.
	 *
	 * @return void
	 */
	protected function register_modules() {
		// Register your modules here.
	}

	/**
	 * Register admin menus.
	 *
	 * @return void
	 */
	public function register_admin_menus() {
		add_menu_page(
			__( '{$plugin_name}', '{$plugin_slug}' ),
			__( '{$plugin_name}', '{$plugin_slug}' ),
			'manage_options',
			'{$plugin_slug}',
			[ \$this, 'render_dashboard_page' ],
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
		echo '<div class="wrap">';
		echo '<h1>' . esc_html( \$this->get_plugin_name() ) . '</h1>';
		echo '<p>Welcome to your new Optti plugin!</p>';
		echo '</div>';
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		// Register your REST routes here.
	}

	/**
	 * Register AJAX handlers.
	 *
	 * @return void
	 */
	protected function register_ajax_handlers() {
		// Register your AJAX handlers here.
	}
}
PHP;

file_put_contents( $plugin_dir . '/includes/class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php', $class_file );

// Create README.
$readme = <<<MD
# {$plugin_name}

{$description}

## Installation

1. Upload the plugin to your WordPress plugins directory
2. Activate the plugin
3. Configure settings

## Development

This plugin uses the Optti Framework as a Git submodule.

To set up the framework:

\`\`\`bash
git submodule add git@github.com:optti/plugin-framework.git framework
cd framework
git checkout v1.0.0
\`\`\`

## License

GPLv2 or later
MD;

file_put_contents( $plugin_dir . '/README.md', $readme );

echo "Plugin scaffolded successfully!\n";
echo "Location: {$plugin_dir}\n";
echo "\n";
echo "Next steps:\n";
echo "1. cd {$plugin_dir}\n";
echo "2. git init\n";
echo "3. git submodule add git@github.com:optti/plugin-framework.git framework\n";
echo "4. cd framework && git checkout v1.0.0 && cd ..\n";
echo "5. git add . && git commit -m 'Initial commit'\n";

