<?php
/**
 * PHPUnit bootstrap file for BeepBeep AI Alt Text Generator tests.
 *
 * @package BeepBeepAI\AltText\Tests
 */

// Composer autoloader
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Load Mockery
require_once dirname( __DIR__ ) . '/vendor/mockery/mockery/library/Mockery.php';

// Define plugin constants for testing
define( 'BBAI_PLUGIN_FILE', dirname( __DIR__ ) . '/beepbeep-ai-alt-text-generator.php' );
define( 'BBAI_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'BBAI_PLUGIN_URL', 'http://localhost/wp-content/plugins/beepbeep-ai-alt-text-generator/' );
define( 'BBAI_VERSION', '5.0.0-dev' );

// WordPress test environment (if available)
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}

// Mock WordPress functions if WordPress test library not available
if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
    // Create minimal WordPress function mocks for unit testing
    require_once __DIR__ . '/wordpress-mocks.php';
} else {
    // Load WordPress test library
    require_once $_tests_dir . '/includes/functions.php';

    /**
     * Manually load the plugin being tested.
     */
    function _manually_load_plugin() {
        require dirname( __DIR__ ) . '/beepbeep-ai-alt-text-generator.php';
    }
    tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

    // Start up the WP testing environment
    require $_tests_dir . '/includes/bootstrap.php';
}

// Load test helpers
require_once __DIR__ . '/TestCase.php';

echo "PHPUnit Bootstrap Complete\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "PHPUnit Version: " . PHPUnit\Runner\Version::id() . "\n";
