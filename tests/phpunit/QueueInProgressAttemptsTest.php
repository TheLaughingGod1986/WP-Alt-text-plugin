<?php
/**
 * In-progress queue retries don't use up attempts, up to a deferral cap.
 */
declare(strict_types=1);

namespace {
	use BeepBeepAI\AltTextGenerator\Generation_Lock;
	use BeepBeepAI\AltTextGenerator\Queue;
	use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
	use PHPUnit\Framework\TestCase;

	#[RunTestsInSeparateProcesses]
	final class QueueInProgressAttemptsTest extends TestCase {
		protected function setUp(): void {
			if ( ! defined( 'MINUTE_IN_SECONDS' ) ) { define( 'MINUTE_IN_SECONDS', 60 ); }
			require_once __DIR__ . '/stubs-post-meta.php';
			if ( ! function_exists( '__' ) ) { function __( $t, $d = 'default' ) { return $t; } }
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-generation-lock.php';
		}

		/** Simulates process_queue's in-progress branch on one job row. */
		private function park( array &$job, int $max_attempts = 3 ): string {
			$plan = Generation_Lock::queue_defer_plan( $job['last_error'], 'busy' );
			if ( $plan['refund_attempt'] ) {
				$job['attempts'] = max( 0, $job['attempts'] - 1 ); // mark_deferred
				$job['last_error'] = $plan['message'];
				return 'deferred';
			}
			$job['last_error'] = $plan['message'];
			return $job['attempts'] >= $max_attempts ? 'failed' : 'retry';
		}

		public function test_in_progress_retries_do_not_use_attempts(): void {
			$job = array( 'attempts' => 0, 'last_error' => '' );
			for ( $i = 1; $i <= Generation_Lock::QUEUE_MAX_DEFERRALS; $i++ ) {
				$job['attempts']++; // claim_batch
				$this->assertSame( 'deferred', $this->park( $job ) );
				$this->assertSame( 0, $job['attempts'] );
				$this->assertSame( $i, Generation_Lock::queue_deferral_count( $job['last_error'] ) );
			}
		}

		public function test_real_failure_after_deferrals_keeps_full_retries(): void {
			$job = array( 'attempts' => 0, 'last_error' => '' );
			for ( $i = 0; $i < 5; $i++ ) { $job['attempts']++; $this->park( $job ); }
			// Now a real failure on the next claim: attempts is 1 of 3, so it is retried.
			$job['attempts']++;
			$this->assertSame( 1, $job['attempts'] );
			$this->assertLessThan( 3, $job['attempts'] );
		}

		public function test_cap_stops_refunding_so_it_cannot_loop_forever(): void {
			$job = array( 'attempts' => 0, 'last_error' => '' );
			$outcomes = array();
			for ( $i = 0; $i < 30; $i++ ) {
				$job['attempts']++;
				$o = $this->park( $job );
				$outcomes[] = $o;
				if ( 'failed' === $o ) { break; }
			}
			$this->assertSame( 'failed', end( $outcomes ), 'Past the cap, attempts count and the job ends.' );
			$this->assertSame( Generation_Lock::QUEUE_MAX_DEFERRALS + 3, count( $outcomes ) );
			$this->assertStringContainsString( 'deferral cap reached', $job['last_error'] );
		}

		public function test_deferral_count_parsing(): void {
			$this->assertSame( 0, Generation_Lock::queue_deferral_count( '' ) );
			$this->assertSame( 0, Generation_Lock::queue_deferral_count( 'timeout' ) );
			$this->assertSame( 1, Generation_Lock::queue_deferral_count( 'generation_in_progress: old marker' ) );
			$this->assertSame( 7, Generation_Lock::queue_deferral_count( 'generation_in_progress#7: busy' ) );
		}

		public function test_saved_alt_still_skips_with_counted_marker(): void {
			$this->assertTrue( Generation_Lock::queue_should_skip_retry( 3, 'generation_in_progress#4: busy', static function () { return 'saved'; } ) );
		}

		public function test_mark_deferred_refunds_attempt_in_sql(): void {
			if ( ! class_exists( 'BeepBeepAI\\AltTextGenerator\\BBAI_Cache', false ) ) {
				eval( 'namespace BeepBeepAI\\AltTextGenerator; class BBAI_Cache { public static function bump( $g ) {} }' );
			}
			if ( ! function_exists( 'esc_sql' ) ) { function esc_sql( $v ) { return $v; } }
			if ( ! function_exists( 'wp_strip_all_tags' ) ) { function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); } }
			if ( ! function_exists( 'wp_trim_words' ) ) { function wp_trim_words( $v, $n = 55, $m = '' ) { return (string) $v; } }
			$GLOBALS['wpdb'] = new class() {
				public $prefix = 'wp_';
				public $queries = array();
				public function prepare( $q, ...$a ) { return vsprintf( str_replace( array( '%s', '%d' ), array( "'%s'", '%d' ), $q ), $a ); }
				public function query( $q ) { $this->queries[] = $q; return 1; }
			};
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-queue.php';
			Queue::mark_deferred( 42, 'generation_in_progress#1: busy' );
			$q = $GLOBALS['wpdb']->queries[0];
			$this->assertStringContainsString( 'attempts = GREATEST(attempts - 1, 0)', $q );
			$this->assertStringContainsString( "status = 'pending'", $q );
			$this->assertStringContainsString( 'WHERE id = 42', $q );
		}
	}
}
