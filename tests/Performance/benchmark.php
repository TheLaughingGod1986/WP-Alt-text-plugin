<?php
/**
 * Performance Benchmark Script
 *
 * Measures execution time and memory usage for key operations.
 *
 * Usage: php tests/Performance/benchmark.php
 *
 * @package BeepBeepAI\AltText\Tests\Performance
 */

// Load Composer autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Benchmark result class
 */
class BenchmarkResult {
	public string $name;
	public float $time_ms;
	public int $memory_bytes;
	public int $iterations;

	public function __construct( string $name, float $time_ms, int $memory_bytes, int $iterations ) {
		$this->name         = $name;
		$this->time_ms      = $time_ms;
		$this->memory_bytes = $memory_bytes;
		$this->iterations   = $iterations;
	}

	public function avg_time_ms(): float {
		return $this->time_ms / $this->iterations;
	}

	public function format_memory(): string {
		$units = array( 'B', 'KB', 'MB', 'GB' );
		$bytes = $this->memory_bytes;
		$i     = 0;
		while ( $bytes >= 1024 && $i < count( $units ) - 1 ) {
			$bytes /= 1024;
			$i++;
		}
		return round( $bytes, 2 ) . ' ' . $units[ $i ];
	}
}

/**
 * Benchmark runner
 */
class BenchmarkRunner {
	private array $results = array();

	/**
	 * Run a benchmark
	 *
	 * @param string   $name Benchmark name.
	 * @param callable $callback Function to benchmark.
	 * @param int      $iterations Number of iterations.
	 */
	public function benchmark( string $name, callable $callback, int $iterations = 1000 ) {
		// Warm up
		for ( $i = 0; $i < 10; $i++ ) {
			$callback();
		}

		// Garbage collect before benchmark
		gc_collect_cycles();

		$memory_start = memory_get_usage();
		$time_start   = microtime( true );

		// Run benchmark
		for ( $i = 0; $i < $iterations; $i++ ) {
			$callback();
		}

		$time_end   = microtime( true );
		$memory_end = memory_get_usage();

		$time_ms      = ( $time_end - $time_start ) * 1000;
		$memory_bytes = $memory_end - $memory_start;

		$this->results[] = new BenchmarkResult( $name, $time_ms, $memory_bytes, $iterations );
	}

	/**
	 * Print results
	 */
	public function print_results() {
		echo "\n";
		echo "========================================\n";
		echo "Performance Benchmark Results\n";
		echo "========================================\n\n";

		echo "PHP Version: " . PHP_VERSION . "\n";
		echo "Date: " . date( 'Y-m-d H:i:s' ) . "\n\n";

		foreach ( $this->results as $result ) {
			echo "Test: {$result->name}\n";
			echo "  Total Time: " . round( $result->time_ms, 2 ) . " ms\n";
			echo "  Avg Time:   " . round( $result->avg_time_ms(), 4 ) . " ms\n";
			echo "  Memory:     {$result->format_memory()}\n";
			echo "  Iterations: {$result->iterations}\n\n";
		}

		// Summary
		$total_time   = array_sum( array_map( fn( $r ) => $r->time_ms, $this->results ) );
		$total_memory = array_sum( array_map( fn( $r ) => $r->memory_bytes, $this->results ) );

		echo "========================================\n";
		echo "Summary\n";
		echo "========================================\n";
		echo "Total Tests:  " . count( $this->results ) . "\n";
		echo "Total Time:   " . round( $total_time, 2 ) . " ms\n";
		echo "Total Memory: " . $this->format_bytes( $total_memory ) . "\n\n";
	}

	private function format_bytes( int $bytes ): string {
		$units = array( 'B', 'KB', 'MB', 'GB' );
		$i     = 0;
		while ( $bytes >= 1024 && $i < count( $units ) - 1 ) {
			$bytes /= 1024;
			$i++;
		}
		return round( $bytes, 2 ) . ' ' . $units[ $i ];
	}
}

// Initialize benchmark runner
$runner = new BenchmarkRunner();

echo "Starting Performance Benchmarks...\n";

// Benchmark 1: Array operations (baseline)
$runner->benchmark(
	'Array Creation (1000 elements)',
	function () {
		$arr = array();
		for ( $i = 0; $i < 1000; $i++ ) {
			$arr[] = $i;
		}
	},
	1000
);

// Benchmark 2: String operations
$runner->benchmark(
	'String Concatenation',
	function () {
		$str = '';
		for ( $i = 0; $i < 100; $i++ ) {
			$str .= 'test';
		}
	},
	1000
);

// Benchmark 3: JSON encoding
$runner->benchmark(
	'JSON Encode/Decode',
	function () {
		$data = array(
			'success' => true,
			'data'    => array(
				'id'   => 123,
				'name' => 'Test',
				'tags' => array( 'tag1', 'tag2', 'tag3' ),
			),
		);
		$json = json_encode( $data );
		json_decode( $json, true );
	},
	10000
);

// Benchmark 4: Array operations
$runner->benchmark(
	'Array Map/Filter',
	function () {
		$arr = range( 1, 100 );
		$arr = array_map(
			function ( $n ) {
				return $n * 2;
			},
			$arr
		);
		$arr = array_filter(
			$arr,
			function ( $n ) {
				return $n > 50;
			}
		);
	},
	1000
);

// Benchmark 5: Function calls
$runner->benchmark(
	'Function Calls',
	function () {
		$result = 0;
		for ( $i = 0; $i < 100; $i++ ) {
			$result += strlen( 'test' );
		}
	},
	1000
);

// Benchmark 6: Object instantiation
$runner->benchmark(
	'Object Creation',
	function () {
		for ( $i = 0; $i < 10; $i++ ) {
			$obj        = new stdClass();
			$obj->id    = $i;
			$obj->name  = 'Test ' . $i;
			$obj->value = $i * 100;
		}
	},
	1000
);

// Print results
$runner->print_results();

echo "Benchmark complete!\n\n";
