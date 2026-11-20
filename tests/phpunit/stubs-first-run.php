<?php
/**
 * WordPress stubs for the first-run guidance tests. Screen, caps and redirects
 * come from $GLOBALS['fr'] so a test can set them. Loaded only inside
 * separate-process tests.
 */
declare(strict_types=1);

$GLOBALS['fr'] = array(
	'is_admin'   => true,
	'caps'       => array( 'manage_options' => true, 'upload_files' => true ),
	'filters'    => array(),
	'nonce_ok'   => true,
	'redirected' => null,
);

final class BBAI_Test_First_Run_Redirect extends \Exception {}

foreach ( array( '__', 'esc_html__', 'esc_attr__' ) as $fr_fn ) {
	if ( ! function_exists( $fr_fn ) ) {
		eval( 'function ' . $fr_fn . '( $s, $d = null ) { return $s; }' );
	}
}
foreach ( array( 'esc_html_e', 'esc_attr_e' ) as $fr_fn ) {
	if ( ! function_exists( $fr_fn ) ) {
		eval( 'function ' . $fr_fn . '( $s, $d = null ) { echo htmlspecialchars( (string) $s, ENT_QUOTES ); }' );
	}
}
if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $n, $d = null ) { return 1 === (int) $n ? $single : $plural; }
}
if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $n ) { return number_format( (float) $n ); }
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
if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( ...$a ) {
		if ( is_array( $a[0] ) ) {
			$args = $a[0];
			$url  = $a[1] ?? '';
		} else {
			$args = array( $a[0] => $a[1] );
			$url  = $a[2] ?? '';
		}
		return $url . ( false === strpos( (string) $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
	}
}
if ( ! function_exists( 'wp_nonce_url' ) ) {
	function wp_nonce_url( $url, $action = -1 ) { return add_query_arg( array( '_wpnonce' => 'nonce-' . $action ), $url ); }
}
if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) {
		echo '<input type="hidden" name="' . $name . '" value="nonce-' . $action . '" />'; // phpcs:ignore
	}
}
if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer( $action = -1 ) {
		if ( empty( $GLOBALS['fr']['nonce_ok'] ) ) {
			throw new \RuntimeException( 'bad nonce for ' . $action );
		}
		return 1;
	}
}
if ( ! function_exists( 'wp_get_referer' ) ) {
	function wp_get_referer() { return 'https://example.com/wp-admin/plugins.php'; }
}
if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( $url ) {
		$GLOBALS['fr']['redirected'] = $url;
		throw new BBAI_Test_First_Run_Redirect( (string) $url );
	}
}
if ( ! function_exists( 'remove_query_arg' ) ) {
	function remove_query_arg( $key, $url ) { return $url; }
}
if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $msg = '', $code = null ) { throw new \RuntimeException( 'wp_die: ' . $msg ); }
}
if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() { return (bool) $GLOBALS['fr']['is_admin']; }
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap ) { return ! empty( $GLOBALS['fr']['caps'][ $cap ] ); }
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $key ) { unset( $GLOBALS['bbai_test_options'][ $key ] ); return true; }
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $cb, ...$a ) { $GLOBALS['fr']['filters'][ $hook ][] = $cb; return true; }
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $cb, ...$a ) { $GLOBALS['fr']['filters'][ $hook ][] = $cb; return true; }
}
if ( ! defined( 'BEEPBEEP_AI_PLUGIN_BASENAME' ) ) {
	define( 'BEEPBEEP_AI_PLUGIN_BASENAME', 'beepbeep-ai-alt-text-generator/beepbeep-ai-alt-text-generator.php' );
}
