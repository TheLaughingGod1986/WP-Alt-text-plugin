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
if ( ! defined( 'BEEPBEEP_AI_DB_VERSION' ) ) {
	// Kept in step with the plugin file by AuditMigrationTest.
	define( 'BEEPBEEP_AI_DB_VERSION', '1.1.0' );
}

function esc_sql( $value ) {
	return is_string( $value ) ? addslashes( $value ) : $value;
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

$GLOBALS['bbai_test_filters'] = array();

function apply_filters( $tag, $value, ...$args ) {
	if ( empty( $GLOBALS['bbai_test_filters'][ $tag ] ) ) {
		return $value;
	}

	foreach ( $GLOBALS['bbai_test_filters'][ $tag ] as $callback ) {
		$value = $callback( $value, ...$args );
	}

	return $value;
}

function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['bbai_test_filters'][ $tag ][] = $callback;
	return true;
}

function remove_all_filters( $tag = null ) {
	if ( null === $tag ) {
		$GLOBALS['bbai_test_filters'] = array();
		return;
	}

	unset( $GLOBALS['bbai_test_filters'][ $tag ] );
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

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

function add_option( $key, $value, $deprecated = '', $autoload = true ) {
	// Mirrors core: fails when the option already exists. This is what makes the
	// audit run lock atomic in production, so the double must behave the same.
	if ( array_key_exists( $key, $GLOBALS['bbai_test_options'] ) ) {
		return false;
	}

	$GLOBALS['bbai_test_options'][ $key ] = $value;

	return true;
}

function current_time( $type = 'mysql', $gmt = 0 ) {
	return 'mysql' === $type ? gmdate( 'Y-m-d H:i:s' ) : time();
}

function wp_generate_uuid4() {
	return sprintf( '%08x-0000-4000-8000-%012x', wp_rand_seed(), wp_rand_seed() );
}

function wp_rand_seed() {
	static $counter = 0;
	return ++$counter + 1000;
}

function is_user_logged_in() {
	return ! empty( $GLOBALS['bbai_test_current_user'] );
}

function current_user_can( $capability ) {
	return ! empty( $GLOBALS['bbai_test_caps'][ $capability ] );
}

function absint( $value ) {
	return abs( (int) $value );
}

function get_the_title( $post_id = 0 ) {
	return $GLOBALS['bbai_test_post_titles'][ (int) $post_id ] ?? '';
}

$GLOBALS['bbai_test_post_meta'] = array();

function get_post_meta( $post_id, $key = '', $single = false ) {
	$bucket = $GLOBALS['bbai_test_post_meta'][ (int) $post_id ] ?? array();
	$value  = $bucket[ $key ] ?? '';

	return $single ? $value : ( '' === $value ? array() : array( $value ) );
}

function update_post_meta( $post_id, $key, $value ) {
	$GLOBALS['bbai_test_post_meta'][ (int) $post_id ][ $key ] = $value;
	return true;
}

function delete_post_meta( $post_id, $key ) {
	unset( $GLOBALS['bbai_test_post_meta'][ (int) $post_id ][ $key ] );
	return true;
}

function get_post_type( $post_id = 0 ) {
	return $GLOBALS['bbai_test_post_types'][ (int) $post_id ] ?? 'attachment';
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

class WP_Error {

	private $code;
	private $message;
	private $data;

	public function __construct( $code = '', $message = '', $data = null ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}

	public function get_error_data() {
		return $this->data;
	}
}

function __( $text, $domain = 'default' ) {
	return $text;
}

function esc_html__( $text, $domain = 'default' ) {
	return $text;
}

function _n( $single, $plural, $number, $domain = 'default' ) {
	return 1 === (int) $number ? $single : $plural;
}

$GLOBALS['bbai_test_options']['beepbeepai_site_id'] = 'test_site_install_id_0123456789ab';

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-telemetry.php';

// Image accessibility audit engine — pure rule classes, testable without WordPress.
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-alt-quality-scorer.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/audit/class-audit-issue.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/audit/class-audit-vocabulary.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/audit/class-image-audit-context.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/audit/class-audit-rule-interface.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/audit/class-abstract-audit-rule.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/audit/rules/class-missing-alt-rule.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/audit/rules/class-empty-alt-review-rule.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/audit/rules/class-filename-alt-rule.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/audit/rules/class-generic-alt-rule.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/audit/rules/class-duplicate-alt-rule.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/audit/rules/class-alt-too-short-rule.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/audit/rules/class-alt-too-long-rule.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/audit/rules/class-keyword-stuffing-rule.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/audit/rules/class-orphaned-attachment-rule.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/audit/rules/class-no-usage-context-rule.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/audit/class-audit-rule-registry.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/audit/class-image-accessibility-score-service.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/audit/class-audit-filters.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/audit/class-audit-entitlement-service.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/audit/class-audit-telemetry-service.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/audit/class-decorative-image-service.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/audit/class-image-audit-repository.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/audit/class-image-discovery-service.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/audit/class-audit-progress-service.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/audit/class-audit-batch-processor.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/audit/class-image-audit-service.php';

require_once __DIR__ . '/audit/helpers/class-bbai-fake-wpdb.php';
