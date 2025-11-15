<?php
/**
 * Plugin Name: WP Alt Text AI – Auto Image SEO & Accessibility
 * Plugin URI: https://wordpress.org/plugins/wp-alt-text-plugin/
 * Description: Automatically generates SEO-optimized and WCAG-compliant alt text for WordPress images using AI. Free tier includes 50 AI generations per month. Improves image search rankings, accessibility, and SEO.
 * Version: 4.3.0
 * Author: Benjamin Oats
 * Author URI: https://oppti.ai
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-alt-text-plugin
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Tags: SEO, accessibility, alt text, images, AI, automation, wcag, media library, image SEO, alt text SEO
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'OPPTIAI_ALT_VERSION', '4.3.0' );
define( 'OPPTIAI_ALT_PLUGIN_FILE', __FILE__ );
define( 'OPPTIAI_ALT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OPPTIAI_ALT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'OPPTIAI_ALT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Load OpptiAI Framework
require_once OPPTIAI_ALT_PLUGIN_DIR . 'opptiai-framework/init.php';

// Load plugin-specific classes
require_once OPPTIAI_ALT_PLUGIN_DIR . 'includes/class-api-client-v2.php';
require_once OPPTIAI_ALT_PLUGIN_DIR . 'includes/class-usage-tracker.php';
require_once OPPTIAI_ALT_PLUGIN_DIR . 'includes/class-queue.php';
require_once OPPTIAI_ALT_PLUGIN_DIR . 'includes/class-debug-log.php';
require_once OPPTIAI_ALT_PLUGIN_DIR . 'admin/class-opptiai-alt-core.php';
require_once OPPTIAI_ALT_PLUGIN_DIR . 'admin/class-opptiai-alt-admin-hooks.php';

/**
 * Register the activation hook.
 */
function activate_opptiai_alt() {
	require_once OPPTIAI_ALT_PLUGIN_DIR . 'includes/class-opptiai-alt-activator.php';
	Opptiai_Alt_Activator::activate();
}

/**
 * Register the deactivation hook.
 */
function deactivate_opptiai_alt() {
	require_once OPPTIAI_ALT_PLUGIN_DIR . 'includes/class-opptiai-alt-deactivator.php';
	Opptiai_Alt_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_opptiai_alt' );
register_deactivation_hook( __FILE__, 'deactivate_opptiai_alt' );

require_once OPPTIAI_ALT_PLUGIN_DIR . 'includes/class-opptiai-alt.php';

/**
 * Kick off the plugin.
 */
function run_opptiai_alt() {
	$plugin = new Opptiai_Alt();
	$plugin->run();
}

run_opptiai_alt();
