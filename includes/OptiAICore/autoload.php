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

// This copy's own version. Compared below against whatever OPTIAI_CORE_VERSION
// a sibling OptiAI plugin may have already defined (WP loads plugins in a
// fixed but not-guaranteed order, so either plugin could load first).
$optiai_core_this_copy_version = '0.2.0';

if ( ! defined( 'OPTIAI_CORE_VERSION' ) ) {
	define( 'OPTIAI_CORE_VERSION', $optiai_core_this_copy_version );
} elseif ( OPTIAI_CORE_VERSION !== $optiai_core_this_copy_version && function_exists( 'update_option' ) ) {
	// A sibling OptiAI plugin already defined a different version. Both
	// copies keep working (each has its own vendored classes), but a
	// shared-table schema drift between them is now diagnosable instead of
	// silently confusing — surfaced as a dismissible admin notice by
	// whichever plugin's Module_Registry checks it, not from here directly
	// (this file must stay framework-light and side-effect-free otherwise).
	update_option( 'optiai_core_version_mismatch', array(
		'seen_at' => current_time( 'mysql' ),
		'active'  => OPTIAI_CORE_VERSION,
		'this_copy' => $optiai_core_this_copy_version,
	), false );
}
unset( $optiai_core_this_copy_version );

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
