<?php
/**
 * v5.0 Integration Test
 *
 * Tests that the v5.0 service architecture is properly wired up.
 *
 * @package BeepBeep\AltText
 * @since   5.0.0
 */

// Simulate WordPress environment.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

// Load required files.
require_once __DIR__ . '/includes/core/class-container.php';
require_once __DIR__ . '/includes/core/class-event-bus.php';
require_once __DIR__ . '/includes/core/class-router.php';
require_once __DIR__ . '/includes/core/class-service-provider.php';

use BeepBeep\AltText\Core\Container;
use BeepBeep\AltText\Core\Service_Provider;

echo "Testing v5.0 Service Integration\n";
echo str_repeat( '=', 50 ) . "\n\n";

$passed = 0;
$failed = 0;

/**
 * Test helper function.
 */
function test( $description, $callback ) {
	global $passed, $failed;
	try {
		$result = $callback();
		if ( $result ) {
			echo "✓ {$description}\n";
			$passed++;
		} else {
			echo "✗ {$description}: Assertion failed\n";
			$failed++;
		}
	} catch ( Exception $e ) {
		echo "✗ {$description}: {$e->getMessage()}\n";
		$failed++;
	}
}

// Test 1: Container creation.
test(
	'Container can be created',
	function () {
		$container = new Container();
		return $container instanceof Container;
	}
);

// Test 2: Service registration.
test(
	'Services can be registered',
	function () {
		$container = new Container();
		$container->singleton(
			'test',
			function () {
				return 'test_value';
			}
		);
		return $container->has( 'test' );
	}
);

// Test 3: Service retrieval.
test(
	'Services can be retrieved',
	function () {
		$container = new Container();
		$container->singleton(
			'test',
			function () {
				return 'test_value';
			}
		);
		return 'test_value' === $container->get( 'test' );
	}
);

// Test 4: Core services registration.
test(
	'Core services (EventBus, Router) can be registered',
	function () {
		$container = new Container();

		// Register event bus.
		$container->singleton(
			'event_bus',
			function () {
				return new \BeepBeep\AltText\Core\Event_Bus();
			}
		);

		// Register router.
		$container->singleton(
			'router',
			function ( $c ) {
				return new \BeepBeep\AltText\Core\Router( $c );
			}
		);

		return $container->has( 'event_bus' ) && $container->has( 'router' );
	}
);

// Test 5: EventBus functionality.
test(
	'EventBus can emit and receive events',
	function () {
		$event_bus    = new \BeepBeep\AltText\Core\Event_Bus();
		$event_received = false;

		$event_bus->on(
			'test_event',
			function () use ( &$event_received ) {
				$event_received = true;
			}
		);

		$event_bus->emit( 'test_event' );

		return $event_received;
	}
);

// Test 6: Router can register routes.
test(
	'Router can register AJAX routes',
	function () {
		$container = new Container();
		$container->singleton(
			'test_controller',
			function () {
				return new stdClass();
			}
		);

		$router = new \BeepBeep\AltText\Core\Router( $container );
		$router->ajax( 'test_action', 'test_controller', 'test_method' );

		$routes = $router->get_ajax_routes();
		return isset( $routes['test_action'] );
	}
);

// Test 7: Dependency resolution.
test(
	'Container can resolve dependencies automatically',
	function () {
		$container = new Container();

		// Register event bus first.
		$container->singleton(
			'event_bus',
			function () {
				return new \BeepBeep\AltText\Core\Event_Bus();
			}
		);

		// Register router with dependency on event bus.
		$container->singleton(
			'router',
			function ( $c ) {
				return new \BeepBeep\AltText\Core\Router( $c );
			}
		);

		$router = $container->get( 'router' );
		return $router instanceof \BeepBeep\AltText\Core\Router;
	}
);

// Summary.
echo "\n" . str_repeat( '=', 50 ) . "\n";
$total = $passed + $failed;
echo "Test Results: {$total} tests\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";
echo str_repeat( '=', 50 ) . "\n";

if ( $failed > 0 ) {
	echo "\nIntegration test failed! Please fix errors before proceeding.\n";
	exit( 1 );
} else {
	echo "\n✓ All integration tests passed! v5.0 architecture is ready.\n";
	exit( 0 );
}
