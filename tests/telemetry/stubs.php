<?php
/**
 * Dev-only WordPress stubs for the telemetry opt-in scripts. No network:
 * wp_remote_post / wp_remote_request only record what would have been sent.
 */
namespace {
	define( 'ABSPATH', sys_get_temp_dir() . '/' );
	define( 'BEEPBEEP_AI_VERSION', '4.6.141' );
	define( 'BEEPBEEP_AI_PLUGIN_DIR', dirname( __DIR__, 2 ) . '/' );
	define( 'BEEPBEEP_AI_PLUGIN_BASENAME', 'beepbeep-ai-alt-text-generator/beepbeep-ai-alt-text-generator.php' );
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'HOUR_IN_SECONDS', 3600 );

	$GLOBALS['o']      = array();
	$GLOBALS['http']   = array();
	$GLOBALS['tr']     = array();
	$GLOBALS['caps']   = true;
	$GLOBALS['uid']    = 7;
	$GLOBALS['nonce']  = true;

	class WP_Error {
		private $c; private $m; private $d;
		public function __construct( $c = '', $m = '', $d = null ) { $this->c = $c; $this->m = $m; $this->d = $d; }
		public function get_error_code() { return $this->c; }
		public function get_error_message() { return $this->m; }
		public function get_error_data() { return $this->d; }
	}
	class Bbai_Test_Exit extends \Exception {}

	function is_wp_error( $v ) { return $v instanceof WP_Error; }
	function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['o'] ) ? $GLOBALS['o'][ $k ] : $d; }
	function update_option( $k, $v, $a = null ) { $GLOBALS['o'][ $k ] = $v; return true; }
	function delete_option( $k ) { unset( $GLOBALS['o'][ $k ] ); return true; }
	function get_transient( $k ) { return $GLOBALS['tr'][ $k ] ?? false; }
	function set_transient( $k, $v, $t = 0 ) { $GLOBALS['tr'][ $k ] = $v; return true; }
	function delete_transient( $k ) { unset( $GLOBALS['tr'][ $k ] ); return true; }
	function apply_filters( $h, $v ) { return $v; }
	function do_action( ...$a ) {}
	function add_action( ...$a ) { return true; }
	function add_filter( ...$a ) { return true; }
	function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
	function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ); }
	function wp_unslash( $v ) { return $v; }
	function esc_url_raw( $u ) { return (string) $u; }
	function esc_html__( $s ) { return $s; }
	function __( $s ) { return $s; }
	function untrailingslashit( $s ) { return rtrim( (string) $s, '/' ); }
	function trailingslashit( $s ) { return rtrim( (string) $s, '/' ) . '/'; }
	function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f ); }
	function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
	function home_url( $p = '' ) { return 'https://customer-site.example' . $p; }
	function get_site_url() { return 'https://customer-site.example'; }
	function admin_url( $p = '' ) { return 'https://customer-site.example/wp-admin/' . $p; }
	function add_query_arg( ...$a ) {
		$args = is_array( $a[0] ) ? $a[0] : array( $a[0] => $a[1] );
		$url  = is_array( $a[0] ) ? $a[1] : $a[2];
		return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . http_build_query( $args );
	}
	function get_bloginfo( $k = '' ) { return '6.8.1'; }
	function wp_get_environment_type() { return 'production'; }
	function get_current_user_id() { return (int) $GLOBALS['uid']; }
	function get_user_meta( $u, $k, $s = false ) { return $GLOBALS['um'][ $u ][ $k ] ?? ''; }
	function update_user_meta( $u, $k, $v ) { $GLOBALS['um'][ $u ][ $k ] = $v; return true; }
	function current_user_can( $c ) { return (bool) $GLOBALS['caps']; }
	function check_admin_referer( $a ) { if ( ! $GLOBALS['nonce'] ) { throw new Bbai_Test_Exit( 'nonce' ); } return 1; }
	function wp_die( ...$a ) { throw new Bbai_Test_Exit( 'die' ); }
	function wp_safe_redirect( $u ) { $GLOBALS['redirect'] = $u; throw new Bbai_Test_Exit( 'redirect' ); }
	function is_admin() { return true; }
	function wp_doing_ajax() { return false; }
	function absint( $v ) { return abs( (int) $v ); }
	function get_current_blog_id() { return 1; }
	function get_current_network_id() { return 1; }
	function is_multisite() { return false; }
	function wp_generate_password( $l = 12, $s = true ) { return str_repeat( 'a', (int) $l ); }
	function wp_generate_uuid4() { return '0f1e2d3c-4b5a-4697-8877-665544332211'; }
	function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
	function wp_remote_post( $url, $args ) { $GLOBALS['http'][] = array( 'url' => $url, 'body' => $args['body'] ?? '' ); return array(); }
	function wp_remote_request( $url, $args ) { $GLOBALS['http'][] = array( 'url' => $url, 'body' => $args['body'] ?? '' ); return new WP_Error( 'http_request_failed', 'offline test' ); }
	function wp_remote_retrieve_response_code( $r ) { return 0; }
	function wp_remote_retrieve_body( $r ) { return ''; }
	function wp_remote_retrieve_headers( $r ) { return array(); }

	$GLOBALS['wpdb'] = (object) array( 'prefix' => 'wp_' );
	$GLOBALS['fail'] = 0;
	function check( $cond, $label ) { if ( ! $cond ) { fwrite( STDERR, "FAIL: $label\n" ); $GLOBALS['fail']++; } }
	function posthog_requests() { return array_values( array_filter( $GLOBALS['http'], function ( $r ) { return false !== strpos( $r['url'], 'posthog' ); } ) ); }
}
