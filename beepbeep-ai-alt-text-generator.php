<?php
/**
 * Plugin Name: BeepBeep AI – Alt Text Generator
 * Description: Automatically generates SEO-optimized AI alt text for WordPress.
 * Version: 4.2.3
 * Author: beepbeepv2
 * Author URI: https://oppti.dev
 * Plugin URI: https://wordpress.org/plugins/beepbeep-ai-alt-text-generator/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: beepbeep-ai-alt-text-generator
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'BEEPBEEP_AI_VERSION', '4.2.3' );
define( 'BBAI_VERSION', '4.2.3' ); // Legacy alias for compatibility
define( 'BEEPBEEP_AI_PLUGIN_FILE', __FILE__ );
define( 'BBAI_PLUGIN_FILE', __FILE__ ); // Legacy alias
define( 'BEEPBEEP_AI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BBAI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) ); // Legacy alias
define( 'BEEPBEEP_AI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BBAI_PLUGIN_URL', plugin_dir_url( __FILE__ ) ); // Legacy alias
define( 'BEEPBEEP_AI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'BBAI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) ); // Legacy alias

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-queue.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-debug-log.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-core.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-admin-hooks.php';

/**
 * Register the activation hook.
 */
function beepbeepai_activate() {
	require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-activator.php';
	\BeepBeepAI\AltTextGenerator\Activator::activate();
}

/**
 * Register the deactivation hook.
 */
function beepbeepai_deactivate() {
	require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-deactivator.php';
	\BeepBeepAI\AltTextGenerator\Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'beepbeepai_activate' );
register_deactivation_hook( __FILE__, 'beepbeepai_deactivate' );

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai.php';

/**
 * Kick off the plugin.
 */
function beepbeepai_run() {
	$plugin = new \BeepBeepAI\AltTextGenerator\Plugin();
	$plugin->run();
}

beepbeepai_run();
