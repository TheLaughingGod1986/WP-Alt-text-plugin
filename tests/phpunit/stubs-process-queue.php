<?php
/**
 * WordPress stubs for loading the real Core and Queue in a separate process.
 * $wpdb is SQLite-backed; `attempts - 1` goes through bbai_unsigned_sub(),
 * which errors below zero like a MySQL UNSIGNED column in strict mode.
 */
declare(strict_types=1);

namespace {
	foreach ( array( 'MINUTE_IN_SECONDS' => 60, 'HOUR_IN_SECONDS' => 3600, 'DAY_IN_SECONDS' => 86400, 'WEEK_IN_SECONDS' => 604800, 'ARRAY_A' => 'ARRAY_A', 'OBJECT' => 'OBJECT', 'OBJECT_K' => 'OBJECT_K', 'ARRAY_N' => 'ARRAY_N' ) as $bbai_k => $bbai_v ) {
		if ( ! defined( $bbai_k ) ) {
			define( $bbai_k, $bbai_v );
		}
	}
	foreach ( array( 'BEEPBEEP_AI_PLUGIN_URL' => 'https://example.com/wp-content/plugins/beepbeep-ai-alt-text-generator/', 'BEEPBEEP_AI_PLUGIN_BASENAME' => 'beepbeep-ai-alt-text-generator/beepbeep-ai-alt-text-generator.php' ) as $bbai_k => $bbai_v ) {
		if ( ! defined( $bbai_k ) ) {
			define( $bbai_k, $bbai_v );
		}
	}
	require_once __DIR__ . '/stubs-post-meta.php';
	require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-cache.php';
	$GLOBALS['bbai_test_scheduled'] = array();

	if ( ! class_exists( 'WP_Error', false ) ) {
		class WP_Error {
			private $c; private $m; private $d;
			public function __construct( $c = '', $m = '', $d = '' ) { $this->c = $c; $this->m = $m; $this->d = $d; }
			public function get_error_code() { return $this->c; }
			public function get_error_message() { return $this->m; }
			public function get_error_data() { return $this->d; }
		}
	}

	$bbai_noop = array( 'add_action', 'add_filter', 'do_action', 'register_activation_hook', 'register_deactivation_hook', 'add_shortcode', 'wp_cache_set', 'remove_action', 'remove_filter' );
	foreach ( $bbai_noop as $bbai_fn ) {
		if ( ! function_exists( $bbai_fn ) ) {
			eval( "function {$bbai_fn}( ...\$a ) { return true; }" );
		}
	}
	if ( ! function_exists( '__' ) ) { function __( $t, $d = 'default' ) { return $t; } }
	if ( ! function_exists( 'esc_html__' ) ) { function esc_html__( $t, $d = 'default' ) { return $t; } }
	if ( ! function_exists( 'absint' ) ) { function absint( $v ) { return abs( (int) $v ); } }
	if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); } }
	if ( ! function_exists( 'sanitize_key' ) ) { function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); } }
	if ( ! function_exists( 'wp_parse_args' ) ) { function wp_parse_args( $a, $d = array() ) { return array_merge( (array) $d, (array) $a ); } }
	if ( ! function_exists( 'is_admin' ) ) { function is_admin() { return false; } }
	if ( ! function_exists( 'get_transient' ) ) { function get_transient( $k ) { return false; } }
	if ( ! function_exists( 'set_transient' ) ) { function set_transient( ...$a ) { return true; } }
	if ( ! function_exists( 'delete_transient' ) ) { function delete_transient( $k ) { return true; } }
	if ( ! function_exists( 'wp_cache_get' ) ) { function wp_cache_get( ...$a ) { return false; } }
	if ( ! function_exists( 'home_url' ) ) { function home_url( $p = '' ) { return 'https://example.com' . $p; } }
	if ( ! function_exists( 'admin_url' ) ) { function admin_url( $p = '' ) { return 'https://example.com/wp-admin/' . $p; } }
	if ( ! function_exists( 'esc_html' ) ) { function esc_html( $v ) { return (string) $v; } }
	if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $v ) { return (string) $v; } }
	if ( ! function_exists( 'esc_url' ) ) { function esc_url( $v ) { return (string) $v; } }
	if ( ! function_exists( 'wp_json_encode' ) ) { function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); } }
	if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 0; } }
	if ( ! function_exists( 'current_user_can' ) ) { function current_user_can( ...$a ) { return false; } }
	if ( ! function_exists( 'is_multisite' ) ) { function is_multisite() { return false; } }
	if ( ! function_exists( 'is_wp_error' ) ) { function is_wp_error( $v ) { return $v instanceof \WP_Error; } }
	if ( ! function_exists( 'esc_sql' ) ) { function esc_sql( $v ) { return $v; } }
	if ( ! function_exists( 'wp_strip_all_tags' ) ) { function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); } }
	if ( ! function_exists( 'wp_trim_words' ) ) { function wp_trim_words( $v, $n = 55, $m = '' ) { return (string) $v; } }
	if ( ! function_exists( 'current_time' ) ) { function current_time( $t, $gmt = 0 ) { return gmdate( 'Y-m-d H:i:s' ); } }
	if ( ! function_exists( 'get_post_mime_type' ) ) { function get_post_mime_type( $id ) { return 'image/jpeg'; } }
	if ( ! function_exists( 'wp_next_scheduled' ) ) { function wp_next_scheduled( $h ) { return false; } }
	if ( ! function_exists( 'wp_schedule_single_event' ) ) { function wp_schedule_single_event( $t, $h ) { $GLOBALS['bbai_test_scheduled'][] = array( $t, $h ); return true; } }
	if ( ! function_exists( 'add_option' ) ) {
		function add_option( $key, $value = '', $dep = '', $autoload = 'yes' ) {
			if ( array_key_exists( $key, $GLOBALS['bbai_test_options'] ) ) { return false; }
			$GLOBALS['bbai_test_options'][ $key ] = $value;
			return true;
		}
	}
	if ( ! function_exists( 'delete_option' ) ) { function delete_option( $key ) { unset( $GLOBALS['bbai_test_options'][ $key ] ); return true; } }

	if ( ! class_exists( 'BBAI_Test_Sqlite_Wpdb', false ) ) {
		class BBAI_Test_Sqlite_Wpdb {
			public $prefix = 'wp_';
			public $pdo;
			public $errors = array();
			public $last_error = '';

			public function __construct() {
				$this->pdo = new \PDO( 'sqlite::memory:' );
				$this->pdo->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
				$this->pdo->sqliteCreateFunction(
					'bbai_unsigned_sub',
					static function ( $a, $b ) {
						if ( (int) $a - (int) $b < 0 ) {
							throw new \RuntimeException( 'BIGINT UNSIGNED value is out of range' );
						}
						return (int) $a - (int) $b;
					},
					2
				);
			}

			public function get_charset_collate() { return ''; }

			public function prepare( $q, ...$args ) {
				if ( 1 === count( $args ) && is_array( $args[0] ) ) {
					$args = $args[0];
				}
				$i = 0;
				return preg_replace_callback(
					'/%[sdf]/',
					function ( $m ) use ( &$i, $args ) {
						$v = $args[ $i++ ] ?? '';
						return '%s' === $m[0] ? $this->pdo->quote( (string) $v ) : (string) ( '%d' === $m[0] ? (int) $v : (float) $v );
					},
					$q
				);
			}

			/** MySQL -> SQLite, keeping UNSIGNED semantics for the attempts decrement. */
			private function translate( string $q ): string {
				$q = str_replace( 'attempts - 1', 'bbai_unsigned_sub(attempts, 1)', $q );
				$q = preg_replace( '/\bGREATEST\(/', 'MAX(', $q );
				return preg_replace( '/\bIF\(/', 'IIF(', $q );
			}

			private function run( string $q ) {
				try {
					return $this->pdo->query( $this->translate( $q ) );
				} catch ( \Throwable $e ) {
					$this->last_error = $e->getMessage();
					$this->errors[]   = $e->getMessage();
					return false;
				}
			}

			public function query( $q ) {
				$st = $this->run( (string) $q );
				return false === $st ? false : $st->rowCount();
			}

			public function get_results( $q, $out = OBJECT ) {
				$st = $this->run( (string) $q );
				if ( false === $st ) {
					return null;
				}
				$rows = $st->fetchAll( \PDO::FETCH_ASSOC );
				return ARRAY_A === $out ? $rows : array_map( static function ( $r ) { return (object) $r; }, $rows );
			}

			public function get_var( $q ) {
				$st = $this->run( (string) $q );
				if ( false === $st ) {
					return null;
				}
				$v = $st->fetchColumn();
				return false === $v ? null : $v;
			}

			public function get_row( $q, $out = OBJECT ) {
				$rows = $this->get_results( $q, $out );
				return $rows ? $rows[0] : null;
			}

			public function update( $table, $data, $where, $format = null, $where_format = null ) {
				$set = array();
				foreach ( $data as $k => $v ) {
					$set[] = "{$k} = " . ( null === $v ? 'NULL' : $this->pdo->quote( (string) $v ) );
				}
				$cond = array();
				foreach ( $where as $k => $v ) {
					$cond[] = "{$k} = " . $this->pdo->quote( (string) $v );
				}
				return $this->query( "UPDATE {$table} SET " . implode( ', ', $set ) . ' WHERE ' . implode( ' AND ', $cond ) );
			}
		}
	}
}
