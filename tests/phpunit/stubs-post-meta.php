<?php
/**
 * Post-meta stubs with a simulated persistent object cache: get_post_meta()
 * serves the cached copy until wp_cache_delete( $id, 'post_meta' ), like WP
 * with Redis/Memcached when another PHP process writes the DB.
 */
declare(strict_types=1);

$GLOBALS['bbai_test_meta']       = $GLOBALS['bbai_test_meta'] ?? array(); // "DB"
$GLOBALS['bbai_test_meta_cache'] = $GLOBALS['bbai_test_meta_cache'] ?? array(); // object cache

if ( ! function_exists( 'wp_cache_delete' ) ) {
	function wp_cache_delete( $key, $group = '' ) {
		if ( 'post_meta' === $group ) {
			unset( $GLOBALS['bbai_test_meta_cache'][ (int) $key ] );
		}
		return true;
	}
}
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $id, $key = '', $single = false ) {
		$id = (int) $id;
		if ( ! array_key_exists( $id, $GLOBALS['bbai_test_meta_cache'] ) ) {
			$GLOBALS['bbai_test_meta_cache'][ $id ] = $GLOBALS['bbai_test_meta'][ $id ] ?? array();
		}
		return $GLOBALS['bbai_test_meta_cache'][ $id ][ $key ] ?? '';
	}
}
if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $id, $key, $value ) {
		$GLOBALS['bbai_test_meta'][ (int) $id ][ $key ] = $value;
		unset( $GLOBALS['bbai_test_meta_cache'][ (int) $id ] );
		return true;
	}
}
if ( ! function_exists( 'delete_post_meta' ) ) {
	function delete_post_meta( $id, $key ) {
		unset( $GLOBALS['bbai_test_meta'][ (int) $id ][ $key ] );
		unset( $GLOBALS['bbai_test_meta_cache'][ (int) $id ] );
		return true;
	}
}
if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4() {
		return bin2hex( random_bytes( 16 ) );
	}
}
