<?php
/**
 * Lock wait margin (25 s, wall-clock bounded) and queue handling when a
 * waiter gives up because another run holds the image.
 */
declare(strict_types=1);

namespace {
	use BeepBeepAI\AltTextGenerator\Generation_Lock;
	use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
	use PHPUnit\Framework\TestCase;

	#[RunTestsInSeparateProcesses]
	final class GenerationLockQueueTest extends TestCase {
		protected function setUp(): void {
			if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
				define( 'MINUTE_IN_SECONDS', 60 );
			}
			if ( ! function_exists( 'add_option' ) ) {
				function add_option( $key, $value = '', $dep = '', $autoload = 'yes' ) {
					if ( array_key_exists( $key, $GLOBALS['bbai_test_options'] ) ) { return false; }
					$GLOBALS['bbai_test_options'][ $key ] = $value;
					return true;
				}
			}
			if ( ! function_exists( 'delete_option' ) ) {
				function delete_option( $key ) { unset( $GLOBALS['bbai_test_options'][ $key ] ); return true; }
			}
			require_once __DIR__ . '/stubs-post-meta.php';
			if ( ! function_exists( '__' ) ) {
				function __( $text, $domain = 'default' ) { return $text; }
			}
			if ( ! class_exists( 'WP_Error', false ) ) {
				eval( 'class WP_Error { private $c; private $m; private $d; public function __construct( $c = "", $m = "", $d = "" ) { $this->c = $c; $this->m = $m; $this->d = $d; } public function get_error_code() { return $this->c; } public function get_error_data() { return $this->d; } }' );
			}
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-generation-lock.php';

			// Simulated time: each sleep advances it, and every clock read adds
			// 2 ms of overhead (DB reads), like a real loop.
			$GLOBALS['bbai_now']      = 1000.0;
			$GLOBALS['bbai_alt']      = '';
			$GLOBALS['bbai_backend']  = 0;
			$GLOBALS['bbai_on_tick']  = null;
			Generation_Lock::$sleeper = static function ( $usec ) {
				$GLOBALS['bbai_now'] += $usec / 1e6;
				if ( is_callable( $GLOBALS['bbai_on_tick'] ) ) {
					call_user_func( $GLOBALS['bbai_on_tick'] );
				}
			};
			Generation_Lock::$clock = static function () {
				$GLOBALS['bbai_now'] += 0.002;
				return $GLOBALS['bbai_now'];
			};
		}

		private function alt(): callable {
			return static function () { return $GLOBALS['bbai_alt']; };
		}

		private function backend(): callable {
			return static function () {
				$GLOBALS['bbai_backend']++;
				$GLOBALS['bbai_alt'] = 'generated';
				return 'generated';
			};
		}

		private function hold_lock( int $id, int $expires ): void {
			$GLOBALS['bbai_test_options'][ Generation_Lock::key( $id ) ] = $expires;
		}

		public function test_default_wait_is_25s_and_capped(): void {
			$this->assertSame( 25000, Generation_Lock::DEFAULT_WAIT_MS );
			$this->assertLessThanOrEqual( 25000, Generation_Lock::MAX_WAIT_MS );
		}

		public function test_waiter_gets_holder_alt_saved_just_after_15s(): void {
			$this->hold_lock( 7, time() + 120 );
			$start = $GLOBALS['bbai_now'];
			$GLOBALS['bbai_on_tick'] = static function () use ( $start ) {
				if ( $GLOBALS['bbai_now'] - $start >= 15.04 ) {
					update_post_meta( 7, 'x', 1 ); // DB write by the holder.
					$GLOBALS['bbai_alt'] = 'holder alt';
				}
			};
			$result = Generation_Lock::run( 7, $this->backend(), $this->alt() );
			$this->assertSame( 'holder alt', $result );
			$this->assertSame( 0, $GLOBALS['bbai_backend'] );
		}

		public function test_waiter_gives_up_within_wall_clock_limit(): void {
			$this->hold_lock( 7, time() + 120 );
			$start  = $GLOBALS['bbai_now'];
			$result = Generation_Lock::run( 7, $this->backend(), $this->alt() );
			$elapsed = $GLOBALS['bbai_now'] - $start;
			$this->assertInstanceOf( WP_Error::class, $result );
			$this->assertSame( 'generation_in_progress', $result->get_error_code() );
			$this->assertGreaterThanOrEqual( 24.9, $elapsed );
			$this->assertLessThan( 25.1, $elapsed, 'Read overhead must not push the wait past the limit.' );
			$this->assertSame( 0, $GLOBALS['bbai_backend'] );
		}

		public function test_queue_keeps_job_pending_without_saved_alt(): void {
			$this->assertSame( 'retry', Generation_Lock::queue_outcome_after_in_progress( 7, $this->alt() ) );
			$GLOBALS['bbai_alt'] = '   ';
			$this->assertSame( 'retry', Generation_Lock::queue_outcome_after_in_progress( 7, $this->alt() ) );
			$GLOBALS['bbai_alt'] = 'saved';
			$this->assertSame( 'complete', Generation_Lock::queue_outcome_after_in_progress( 7, $this->alt() ) );
		}

		public function test_retry_after_holder_saved_makes_no_backend_call(): void {
			$mark = Generation_Lock::QUEUE_IN_PROGRESS_MARK . ': This image is already being processed.';
			$GLOBALS['bbai_alt'] = 'holder alt';
			// Mirrors process_queue: skip check first, generate only if not skipped.
			$skip = Generation_Lock::queue_should_skip_retry( 7, $mark, $this->alt() );
			if ( ! $skip ) {
				Generation_Lock::run( 7, $this->backend(), $this->alt() );
			}
			$this->assertTrue( $skip );
			$this->assertSame( 0, $GLOBALS['bbai_backend'] );
			// Jobs not parked for in-progress are not skipped by this rule.
			$this->assertFalse( Generation_Lock::queue_should_skip_retry( 7, 'timeout', $this->alt() ) );
			$this->assertFalse( Generation_Lock::queue_should_skip_retry( 7, '', $this->alt() ) );
		}

		public function test_crashed_holder_job_is_retried_and_generates_after_lock_goes_stale(): void {
			$mark = Generation_Lock::QUEUE_IN_PROGRESS_MARK . ': held';
			// Holder crashed mid-run: lock still within its 3-minute TTL.
			$this->hold_lock( 7, time() + 120 );
			$first = Generation_Lock::run( 7, $this->backend(), $this->alt() );
			$this->assertSame( 'generation_in_progress', $first->get_error_code() );
			$this->assertSame( 'retry', Generation_Lock::queue_outcome_after_in_progress( 7, $this->alt() ) );
			$this->assertGreaterThan( 0, Generation_Lock::queue_retry_delay() );

			// Later retry, lock now past its TTL: not skipped, takes over and generates once.
			$this->hold_lock( 7, time() - 1 );
			$this->assertFalse( Generation_Lock::queue_should_skip_retry( 7, $mark, $this->alt() ) );
			$second = Generation_Lock::run( 7, $this->backend(), $this->alt() );
			$this->assertSame( 'generated', $second );
			$this->assertSame( 1, $GLOBALS['bbai_backend'] );
			$this->assertFalse( Generation_Lock::is_held( 7 ) );
		}
	}
}
