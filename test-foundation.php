<?php
/**
 * Foundation Components Test
 *
 * Tests the v5.0 core framework components.
 *
 * @package BeepBeep\AltText
 * @since   5.0.0
 */

require_once __DIR__ . '/includes/core/class-container.php';
require_once __DIR__ . '/includes/core/class-event-bus.php';
require_once __DIR__ . '/includes/core/class-router.php';

use BeepBeep\AltText\Core\Container;
use BeepBeep\AltText\Core\Event_Bus;
use BeepBeep\AltText\Core\Router;

/**
 * Test results tracker.
 */
class Test_Results {
	private static int $passed = 0;
	private static int $failed = 0;
	private static array $failures = array();

	public static function pass( string $test ): void {
		self::$passed++;
		echo "✓ {$test}\n";
	}

	public static function fail( string $test, string $reason ): void {
		self::$failed++;
		self::$failures[] = array( $test, $reason );
		echo "✗ {$test}: {$reason}\n";
	}

	public static function summary(): void {
		$total = self::$passed + self::$failed;
		echo "\n" . str_repeat( '=', 50 ) . "\n";
		echo "Test Results: {$total} tests\n";
		echo "Passed: " . self::$passed . "\n";
		echo "Failed: " . self::$failed . "\n";

		if ( self::$failed > 0 ) {
			echo "\nFailures:\n";
			foreach ( self::$failures as list( $test, $reason ) ) {
				echo "  - {$test}: {$reason}\n";
			}
		}

		echo str_repeat( '=', 50 ) . "\n";
	}

	public static function get_failed_count(): int {
		return self::$failed;
	}
}

echo "Testing v5.0 Foundation Components\n";
echo str_repeat( '=', 50 ) . "\n\n";

// Test 1: Container - Basic Registration.
echo "Testing Container...\n";
$container = new Container();

$container->register(
	'test_service',
	function () {
		return 'test_value';
	}
);

if ( 'test_value' === $container->get( 'test_service' ) ) {
	Test_Results::pass( 'Container: Basic registration' );
} else {
	Test_Results::fail( 'Container: Basic registration', 'Value mismatch' );
}

// Test 2: Container - Singleton.
$call_count = 0;
$container->singleton(
	'singleton_service',
	function () use ( &$call_count ) {
		$call_count++;
		return new stdClass();
	}
);

$instance1 = $container->get( 'singleton_service' );
$instance2 = $container->get( 'singleton_service' );

if ( 1 === $call_count && $instance1 === $instance2 ) {
	Test_Results::pass( 'Container: Singleton pattern' );
} else {
	Test_Results::fail( 'Container: Singleton pattern', "Called {$call_count} times or instances differ" );
}

// Test 3: Container - Has method.
if ( $container->has( 'test_service' ) && ! $container->has( 'nonexistent' ) ) {
	Test_Results::pass( 'Container: has() method' );
} else {
	Test_Results::fail( 'Container: has() method', 'Incorrect existence check' );
}

// Test 4: Container - Alias.
$container->alias( 'test_alias', 'test_service' );
if ( 'test_value' === $container->get( 'test_alias' ) ) {
	Test_Results::pass( 'Container: Alias registration' );
} else {
	Test_Results::fail( 'Container: Alias registration', 'Alias not working' );
}

// Test 5: Container - Instance binding.
$obj = new stdClass();
$obj->foo = 'bar';
$container->instance( 'bound_instance', $obj );

if ( 'bar' === $container->get( 'bound_instance' )->foo ) {
	Test_Results::pass( 'Container: Instance binding' );
} else {
	Test_Results::fail( 'Container: Instance binding', 'Instance not bound correctly' );
}

// Test 6: EventBus - Event emission.
echo "\nTesting EventBus...\n";
$event_bus = new Event_Bus();
$event_data = null;

$event_bus->on(
	'test_event',
	function ( $data ) use ( &$event_data ) {
		$event_data = $data;
	}
);

$event_bus->emit( 'test_event', 'event_payload' );

if ( 'event_payload' === $event_data ) {
	Test_Results::pass( 'EventBus: Event emission and listening' );
} else {
	Test_Results::fail( 'EventBus: Event emission and listening', 'Event data not received' );
}

// Test 7: EventBus - Once.
$once_count = 0;
$event_bus->once(
	'once_event',
	function () use ( &$once_count ) {
		$once_count++;
	}
);

$event_bus->emit( 'once_event' );
$event_bus->emit( 'once_event' );

if ( 1 === $once_count ) {
	Test_Results::pass( 'EventBus: once() method' );
} else {
	Test_Results::fail( 'EventBus: once() method', "Executed {$once_count} times" );
}

// Test 8: EventBus - Priority.
$execution_order = array();
$event_bus->on(
	'priority_event',
	function () use ( &$execution_order ) {
		$execution_order[] = 'second';
	},
	20
);
$event_bus->on(
	'priority_event',
	function () use ( &$execution_order ) {
		$execution_order[] = 'first';
	},
	10
);

$event_bus->emit( 'priority_event' );

if ( array( 'first', 'second' ) === $execution_order ) {
	Test_Results::pass( 'EventBus: Priority ordering' );
} else {
	Test_Results::fail( 'EventBus: Priority ordering', 'Wrong execution order: ' . implode( ', ', $execution_order ) );
}

// Test 9: EventBus - Listener count.
if ( 2 === $event_bus->listener_count( 'priority_event' ) ) {
	Test_Results::pass( 'EventBus: listener_count()' );
} else {
	Test_Results::fail( 'EventBus: listener_count()', 'Wrong count: ' . $event_bus->listener_count( 'priority_event' ) );
}

// Test 10: Router - AJAX route registration.
echo "\nTesting Router...\n";
$router = new Router( $container );

$router->ajax( 'test_action', 'controller.test', 'handleTest' );
$ajax_routes = $router->get_ajax_routes();

if ( isset( $ajax_routes['test_action'] ) && 'controller.test' === $ajax_routes['test_action']['controller'] ) {
	Test_Results::pass( 'Router: AJAX route registration' );
} else {
	Test_Results::fail( 'Router: AJAX route registration', 'Route not registered correctly' );
}

// Test 11: Router - REST route registration.
$router->rest( '/test-endpoint', 'controller.test', 'handleRest', 'GET' );
$rest_routes = $router->get_rest_routes();

if ( isset( $rest_routes['/test-endpoint'] ) && 'GET' === $rest_routes['/test-endpoint']['methods'] ) {
	Test_Results::pass( 'Router: REST route registration' );
} else {
	Test_Results::fail( 'Router: REST route registration', 'Route not registered correctly' );
}

// Summary.
echo "\n";
Test_Results::summary();

exit( 0 === Test_Results::get_failed_count() ? 0 : 1 );
