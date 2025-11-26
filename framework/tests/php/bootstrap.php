<?php
/**
 * PHPUnit Bootstrap
 *
 * Sets up the testing environment.
 */

// Load WordPress test environment if available.
if ( file_exists( '/tmp/wordpress-tests-lib/includes/bootstrap.php' ) ) {
	require_once '/tmp/wordpress-tests-lib/includes/bootstrap.php';
}

// Load framework.
require_once dirname( __DIR__, 2 ) . '/loader.php';

