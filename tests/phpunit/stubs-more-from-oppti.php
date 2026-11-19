<?php
/**
 * WordPress stubs for the "More from Oppti" panel tests. Every value the panel
 * reads (screen, caps, plugins, user meta, consent) comes from $GLOBALS so a
 * test can set it.
 */
declare(strict_types=1);

$GLOBALS['mfo'] = array(
	'is_admin'  => true,
	'caps'      => array( 'manage_options' => true, 'install_plugins' => true ),
	'plugins'   => array(),
	'user_meta' => array(),
	'user_id'   => 7,
	'enqueued'  => array(),
	'localized' => array(),
);

foreach ( array( '__', 'esc_html__' ) as $mfo_fn ) {
	if ( ! function_exists( $mfo_fn ) ) {
		eval( 'function ' . $mfo_fn . '( $s, $d = null ) { return $s; }' );
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) { return strtolower( (string) preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) ); }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $v ) { return $v; }
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) { return 'https://example.com/wp-admin/' . ltrim( (string) $path, '/' ); }
}
if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() { return (bool) $GLOBALS['mfo']['is_admin']; }
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap ) { return ! empty( $GLOBALS['mfo']['caps'][ $cap ] ); }
}
if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() { return (int) $GLOBALS['mfo']['user_id']; }
}
if ( ! function_exists( 'get_user_meta' ) ) {
	function get_user_meta( $user_id, $key = '', $single = false ) { return $GLOBALS['mfo']['user_meta'][ $user_id ][ $key ] ?? ''; }
}
if ( ! function_exists( 'update_user_meta' ) ) {
	function update_user_meta( $user_id, $key, $value ) { $GLOBALS['mfo']['user_meta'][ $user_id ][ $key ] = $value; return true; }
}
if ( ! function_exists( 'get_plugins' ) ) {
	function get_plugins() { return $GLOBALS['mfo']['plugins']; }
}
if ( ! function_exists( 'is_multisite' ) ) {
	function is_multisite() { return false; }
}
if ( ! function_exists( 'get_site_option' ) ) {
	function get_site_option( $k, $d = false ) { return $d; }
}
if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) {
		echo '<input type="hidden" name="' . $name . '" value="nonce-' . $action . '" />'; // phpcs:ignore
	}
}
if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) { return 'nonce-' . $action; }
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( ...$a ) { return true; }
}
if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $h, ...$a ) { $GLOBALS['mfo']['enqueued'][] = $h; }
}
if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $h, ...$a ) { $GLOBALS['mfo']['enqueued'][] = $h; }
}
if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( $h, $name, $data ) { $GLOBALS['mfo']['localized'][ $name ] = $data; return true; }
}
if ( ! defined( 'BEEPBEEP_AI_PLUGIN_URL' ) ) {
	define( 'BEEPBEEP_AI_PLUGIN_URL', 'https://example.com/wp-content/plugins/beepbeep-ai-alt-text-generator/' );
}
