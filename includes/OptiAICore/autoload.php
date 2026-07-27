<?php
/**
 * Minimal PSR-4-ish autoloader for the OptiAI\Core namespace.
 *
 * Each plugin requires this file once from its own bootstrap. No Composer
 * dependency — this package ships as plain vendored source so it works
 * inside a WordPress.org plugin zip with zero build step.
 *
 * @package OptiAI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'OPTIAI_CORE_VERSION' ) ) {
	define( 'OPTIAI_CORE_VERSION', '0.1.0' );
}

spl_autoload_register( static function ( $class ) {
	if ( strpos( $class, 'OptiAI\\Core\\' ) !== 0 ) {
		return;
	}
	$relative = substr( $class, strlen( 'OptiAI\\Core\\' ) );
	$path     = __DIR__ . '/' . str_replace( '\\', '/', $relative ) . '.php';
	if ( file_exists( $path ) ) {
		require $path;
	}
} );
