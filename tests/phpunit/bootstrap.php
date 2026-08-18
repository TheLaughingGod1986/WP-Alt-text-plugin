<?php
/**
 * Minimal WordPress stubs for site-identifier unit tests.
 */

declare(strict_types=1);

$GLOBALS['bbai_test_options'] = array();

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/' );
}
if ( ! defined( 'BEEPBEEP_AI_VERSION' ) ) {
	define( 'BEEPBEEP_AI_VERSION', '4.6.130-test' );
}
if ( ! defined( 'BEEPBEEP_AI_PLUGIN_DIR' ) ) {
	define( 'BEEPBEEP_AI_PLUGIN_DIR', dirname( __DIR__, 2 ) . '/' );
}

function get_option( $key, $default = false ) {
	return $GLOBALS['bbai_test_options'][ $key ] ?? $default;
}

function update_option( $key, $value, $autoload = true ) {
	$GLOBALS['bbai_test_options'][ $key ] = $value;
	return true;
}

function get_site_url() {
	return 'https://example.com';
}

function wp_generate_password( $length = 12, $special_chars = true ) {
	return str_repeat( 'a', (int) $length );
}

function apply_filters( $tag, $value ) {
	return $value;
}

$GLOBALS['bbai_test_options']['beepbeepai_site_id'] = 'test_site_install_id_0123456789ab';

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
