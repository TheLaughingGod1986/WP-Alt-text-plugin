<?php
/**
 * Minimal WordPress stubs for telemetry unit tests.
 */

declare(strict_types=1);

$GLOBALS['bbai_test_options']  = array();
$GLOBALS['bbai_test_user_meta'] = array();

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/' );
}
if ( ! defined( 'BEEPBEEP_AI_VERSION' ) ) {
	define( 'BEEPBEEP_AI_VERSION', '4.6.109-test' );
}
if ( ! defined( 'BEEPBEEP_AI_PLUGIN_DIR' ) ) {
	define( 'BEEPBEEP_AI_PLUGIN_DIR', dirname( __DIR__, 2 ) . '/' );
}
if ( ! defined( 'BEEPBEEP_AI_PLUGIN_FILE' ) ) {
	define( 'BEEPBEEP_AI_PLUGIN_FILE', BEEPBEEP_AI_PLUGIN_DIR . 'beepbeep-ai-alt-text-generator.php' );
}
if ( ! defined( 'BEEPBEEP_AI_PLUGIN_BASENAME' ) ) {
	define( 'BEEPBEEP_AI_PLUGIN_BASENAME', 'beepbeep-ai-alt-text-generator/beepbeep-ai-alt-text-generator.php' );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

function get_option( $key, $default = false ) {
	return $GLOBALS['bbai_test_options'][ $key ] ?? $default;
}

function update_option( $key, $value, $autoload = true ) {
	$GLOBALS['bbai_test_options'][ $key ] = $value;
	return true;
}

function delete_option( $key ) {
	unset( $GLOBALS['bbai_test_options'][ $key ] );
}

function home_url( $path = '' ) {
	return 'https://example.com' . $path;
}

function get_site_url() {
	return 'https://example.com';
}

function get_bloginfo( $show = '' ) {
	return 'version' === $show ? '6.4.2' : '';
}

function esc_url_raw( $url ) {
	return is_string( $url ) ? $url : '';
}

function sanitize_text_field( $str ) {
	return is_string( $str ) ? trim( strip_tags( $str ) ) : '';
}

function sanitize_key( $key ) {
	$key = is_string( $key ) ? strtolower( $key ) : '';
	return preg_replace( '/[^a-z0-9_\-]/', '', $key ) ?? '';
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

function untrailingslashit( $url ) {
	return rtrim( $url, '/' );
}

function get_current_user_id() {
	return 1;
}

function get_user_meta( $uid, $key, $single = false ) {
	$bucket = $GLOBALS['bbai_test_user_meta'][ $uid ] ?? array();
	return $bucket[ $key ] ?? '';
}

function update_user_meta( $uid, $key, $value ) {
	if ( ! isset( $GLOBALS['bbai_test_user_meta'][ $uid ] ) ) {
		$GLOBALS['bbai_test_user_meta'][ $uid ] = array();
	}
	$GLOBALS['bbai_test_user_meta'][ $uid ][ $key ] = $value;
	return true;
}

function apply_filters( $tag, $value ) {
	return $value;
}

function do_action( $tag, ...$args ) {
	if ( isset( $GLOBALS['bbai_test_actions'][ $tag ] ) ) {
		foreach ( $GLOBALS['bbai_test_actions'][ $tag ] as $callback ) {
			$callback( ...$args );
		}
	}
}

function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['bbai_test_actions'][ $tag ][] = $callback;
}

function remove_all_actions( $tag ) {
	unset( $GLOBALS['bbai_test_actions'][ $tag ] );
}

$GLOBALS['bbai_test_actions'] = array();

if ( ! function_exists( 'mb_substr' ) ) {
	function mb_substr( $str, $start, $length = null ) {
		return null === $length ? substr( $str, $start ) : substr( $str, $start, $length );
	}
}

function wp_generate_password( $length = 12, $special_chars = true ) {
	return str_repeat( 'a', (int) $length );
}

function wp_unslash( $value ) {
	return $value;
}

if ( ! function_exists( 'random_bytes' ) ) {
	function random_bytes( $length ) {
		return str_repeat( 'b', $length );
	}
}

function bbai_is_authenticated() {
	return ! empty( $GLOBALS['bbai_test_options']['beepbeepai_jwt_token'] );
}

$GLOBALS['bbai_test_options']['beepbeepai_site_id'] = 'test_site_install_id_0123456789ab';

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-telemetry.php';
