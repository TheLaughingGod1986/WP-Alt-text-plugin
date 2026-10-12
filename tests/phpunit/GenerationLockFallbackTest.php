<?php
/**
 * Lock-quit fallback: a waiting request must not leave the image with no alt text.
 */
declare(strict_types=1);

namespace {
	use BeepBeepAI\AltTextGenerator\Generation_Lock;
	use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
	use PHPUnit\Framework\TestCase;

	#[RunTestsInSeparateProcesses]
	final class GenerationLockFallbackTest extends TestCase {
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
			if ( ! function_exists( 'wp_cache_delete' ) ) {
				function wp_cache_delete( $key, $group = '' ) { return true; }
			}
			if ( ! function_exists( '__' ) ) {
				function __( $text, $domain = 'default' ) { return $text; }
			}
			if ( ! class_exists( 'WP_Error', false ) ) {
				eval( 'class WP_Error { private $c; private $m; private $d; public function __construct( $c = "", $m = "", $d = "" ) { $this->c = $c; $this->m = $m; $this->d = $d; } public function get_error_code() { return $this->c; } public function get_error_data() { return $this->d; } }' );
			}
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-generation-lock.php';
			Generation_Lock::$sleeper = null;
			$GLOBALS['bbai_alt'] = '';
		}

		private function alt(): callable {
			return static function () { return $GLOBALS['bbai_alt']; };
		}

		private function hold_lock( int $id ): void {
			$this->assertTrue( Generation_Lock::acquire( $id ) );
		}

		public function test_holder_that_bails_without_generating_releases_lock(): void {
			$result = Generation_Lock::run( 7, static function () {
				return new WP_Error( 'bbai_automation_unavailable', 'off' );
			}, $this->alt() );
			$this->assertSame( 'bbai_automation_unavailable', $result->get_error_code() );
			$this->assertFalse( Generation_Lock::is_held( 7 ) );
		}

		public function test_holder_that_throws_releases_lock(): void {
			try {
				Generation_Lock::run( 7, static function () { throw new RuntimeException( 'boom' ); }, $this->alt() );
				$this->fail( 'expected exception' );
			} catch ( RuntimeException $e ) {
				$this->assertFalse( Generation_Lock::is_held( 7 ) );
			}
		}

		public function test_waiter_generates_itself_when_holder_quits_without_alt(): void {
			$this->hold_lock( 9 );
			$calls = 0;
			// While we wait, the holder quits early without generating.
			Generation_Lock::$sleeper = static function () { Generation_Lock::release( 9 ); };
			$result = Generation_Lock::run( 9, static function () use ( &$calls ) {
				$calls++;
				$GLOBALS['bbai_alt'] = 'A red bicycle';
				return 'A red bicycle';
			}, $this->alt() );
			$this->assertSame( 'A red bicycle', $result );
			$this->assertSame( 1, $calls );
			$this->assertFalse( Generation_Lock::is_held( 9 ) );
		}

		public function test_waiter_returns_holders_alt_without_generating(): void {
			$this->hold_lock( 11 );
			Generation_Lock::$sleeper = static function () { $GLOBALS['bbai_alt'] = 'Holder alt'; };
			$calls  = 0;
			$result = Generation_Lock::run( 11, static function () use ( &$calls ) { $calls++; return 'x'; }, $this->alt() );
			$this->assertSame( 'Holder alt', $result );
			$this->assertSame( 0, $calls );
			$this->assertTrue( Generation_Lock::is_held( 11 ), 'waiter must not release a lock it does not own' );
		}

		public function test_waiter_returns_alt_when_holder_finished_and_released(): void {
			$this->hold_lock( 12 );
			Generation_Lock::$sleeper = static function () {
				$GLOBALS['bbai_alt'] = 'Done by holder';
				Generation_Lock::release( 12 );
			};
			$calls  = 0;
			$result = Generation_Lock::run( 12, static function () use ( &$calls ) { $calls++; return 'x'; }, $this->alt() );
			$this->assertSame( 'Done by holder', $result );
			$this->assertSame( 0, $calls );
		}

		public function test_regenerate_waiter_ignores_preexisting_alt(): void {
			$GLOBALS['bbai_alt'] = 'Old alt';
			$this->hold_lock( 13 );
			Generation_Lock::$sleeper = static function () { Generation_Lock::release( 13 ); };
			$calls  = 0;
			$result = Generation_Lock::run( 13, static function () use ( &$calls ) { $calls++; return 'New alt'; }, $this->alt() );
			$this->assertSame( 'New alt', $result );
			$this->assertSame( 1, $calls );
		}

		public function test_still_held_after_wait_returns_in_progress(): void {
			$this->hold_lock( 14 );
			$pauses = 0;
			Generation_Lock::$sleeper = static function () use ( &$pauses ) { $pauses++; };
			$calls  = 0;
			$result = Generation_Lock::run( 14, static function () use ( &$calls ) { $calls++; return 'x'; }, $this->alt() );
			$this->assertInstanceOf( WP_Error::class, $result );
			$this->assertSame( 'generation_in_progress', $result->get_error_code() );
			$this->assertSame( 0, $calls );
			$this->assertGreaterThan( 0, $pauses );
			$this->assertTrue( Generation_Lock::is_held( 14 ) );
		}

		public function test_expired_lock_is_taken_over(): void {
			$GLOBALS['bbai_test_options'][ Generation_Lock::key( 15 ) ] = time() - 10;
			$result = Generation_Lock::run( 15, static function () { return 'ok'; }, $this->alt() );
			$this->assertSame( 'ok', $result );
			$this->assertFalse( Generation_Lock::is_held( 15 ) );
		}
	}
}
