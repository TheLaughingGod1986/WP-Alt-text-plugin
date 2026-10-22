<?php
/**
 * No-op WordPress stubs for driving the admin asset path in unit tests.
 */
declare(strict_types=1);

if ( ! defined( 'BEEPBEEP_AI_PLUGIN_URL' ) ) {
	define( 'BEEPBEEP_AI_PLUGIN_URL', 'https://example.com/wp-content/plugins/beepbeep-ai-alt-text-generator/' );
}
$GLOBALS['bbai_test_localized'] = array();

$bbai_stub_passthrough = array( '__', 'esc_url', 'esc_url_raw', 'sanitize_text_field', 'sanitize_email', 'untrailingslashit', 'wp_unslash', 'esc_html__', 'esc_attr__', 'esc_html', 'esc_attr' );
foreach ( $bbai_stub_passthrough as $bbai_fn ) {
	if ( ! function_exists( $bbai_fn ) ) {
		eval( 'function ' . $bbai_fn . '( $v = "", ...$rest ) { return $v; }' );
	}
}
$bbai_stub_noop = array( 'wp_dequeue_script', 'wp_deregister_script', 'remove_action', 'wp_enqueue_script', 'wp_enqueue_style', 'wp_register_script', 'wp_register_style', 'wp_add_inline_script', 'add_filter', 'add_action', 'set_transient', 'wp_set_script_translations', 'wp_add_inline_style' );
foreach ( $bbai_stub_noop as $bbai_fn ) {
	if ( ! function_exists( $bbai_fn ) ) {
		eval( 'function ' . $bbai_fn . '( ...$args ) { return true; }' );
	}
}
if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( $handle, $name, $data ) { $GLOBALS['bbai_test_localized'][ $name ] = $data; return true; }
}
if ( ! function_exists( 'wp_script_is' ) ) {
	function wp_script_is( $handle, $list = 'enqueued' ) { return true; }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $v ) ); }
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $v ) { return abs( (int) $v ); }
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) { return 'https://example.com/wp-admin/' . ltrim( (string) $path, '/' ); }
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) { return 'https://example.com/' . ltrim( (string) $path, '/' ); }
}
if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( $path = '' ) { return 'https://example.com/wp-json/' . ltrim( (string) $path, '/' ); }
}
if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( ...$args ) {
		if ( is_array( $args[0] ) ) { return ( $args[1] ?? '' ) . '?' . http_build_query( $args[0] ); }
		return ( $args[2] ?? '' ) . '?' . $args[0] . '=' . $args[1];
	}
}
if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) { return 'nonce'; }
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
}
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
}
if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() { return 1; }
}
if ( ! function_exists( 'get_user_meta' ) ) {
	function get_user_meta( $id, $key = '', $single = false ) { return $single ? '' : array(); }
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) { return false; }
}
if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '' ) { return 'en-US'; }
}
if ( ! function_exists( 'wp_get_environment_type' ) ) {
	function wp_get_environment_type() { return 'production'; }
}
