<?php
/**
 * Plugin Name: SEO AI Alt Text Generator - Auto Image SEO & Accessibility
 * Plugin URI: https://wordpress.org/plugins/ai-alt-text-generator/
 * Description: Advanced SEO AI automatically generates keyword-optimized alt text for WordPress images. Boost Google image rankings, improve accessibility (WCAG compliant), and enhance SEO with AI-powered descriptions. Free tier: 50 AI generations/month. Perfect for SEO optimization, image search rankings, accessibility, e-commerce, and content creators.
 * Version: 4.2.1
 * Author: Benjamin Oats
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-alt-gpt
 * Requires at least: 5.8
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Tags: SEO, SEO AI, image SEO, alt text SEO, SEO optimization, Google image SEO, image search ranking, AI alt text, artificial intelligence, automated SEO, keyword optimization, accessibility, alt text, images, AI, WCAG, screen reader, image optimization, bulk edit, WooCommerce, automatic alt text, image accessibility, bulk processing, accessibility compliance, image descriptions, WordPress SEO, SEO tool, image meta tags, search engine optimization
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'AI_ALT_GPT_VERSION', '4.2.1' );
define( 'AI_ALT_GPT_PLUGIN_FILE', __FILE__ );
define( 'AI_ALT_GPT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_ALT_GPT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AI_ALT_GPT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once AI_ALT_GPT_PLUGIN_DIR . 'includes/class-api-client-v2.php';
require_once AI_ALT_GPT_PLUGIN_DIR . 'includes/class-usage-tracker.php';
require_once AI_ALT_GPT_PLUGIN_DIR . 'includes/class-queue.php';
require_once AI_ALT_GPT_PLUGIN_DIR . 'admin/class-ai-alt-gpt-core.php';
require_once AI_ALT_GPT_PLUGIN_DIR . 'admin/class-ai-alt-gpt-admin-hooks.php';

/**
 * Register the activation hook.
 */
function activate_ai_alt_gpt() {
	require_once AI_ALT_GPT_PLUGIN_DIR . 'includes/class-ai-alt-gpt-activator.php';
	Ai_Alt_Gpt_Activator::activate();
}

/**
 * Register the deactivation hook.
 */
function deactivate_ai_alt_gpt() {
	require_once AI_ALT_GPT_PLUGIN_DIR . 'includes/class-ai-alt-gpt-deactivator.php';
	Ai_Alt_Gpt_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_ai_alt_gpt' );
register_deactivation_hook( __FILE__, 'deactivate_ai_alt_gpt' );

require_once AI_ALT_GPT_PLUGIN_DIR . 'includes/class-ai-alt-gpt.php';

/**
 * Kick off the plugin.
 */
function run_ai_alt_gpt() {
	$plugin = new Ai_Alt_Gpt();
	$plugin->run();
}

run_ai_alt_gpt();
