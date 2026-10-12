<?php
/**
 * #53 QA fail: the waiter read a stale object-cache alt and POSTed a second
 * generation with an unrelated Idempotency-Key. These tests cover fresh reads
 * and the shared, attempt-scoped key.
 */
declare(strict_types=1);

namespace {
	use BeepBeepAI\AltTextGenerator\Generation_Lock;
	use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
	use PHPUnit\Framework\TestCase;

	#[RunTestsInSeparateProcesses]
	final class GenerationLockStaleCacheTest extends TestCase {
		const ALT = '_wp_attachment_image_alt';

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
				eval( 'class WP_Error { private $c; public function __construct( $c = "", $m = "", $d = "" ) { $this->c = $c; } public function get_error_code() { return $this->c; } }' );
			}
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-generation-lock.php';
			Generation_Lock::$sleeper = null;
		}

		/** The real current-alt reader used by Core::generate_and_save(). */
		private function alt( int $id ): callable {
			return static function () use ( $id ) { return trim( (string) get_post_meta( $id, self::ALT, true ) ); };
		}

		/** What the API client would send for this attachment right now. */
		private static function sent_key( int $id ): string {
			$h = Generation_Lock::apply_idempotency_header( array( 'X-Image-ID' => (string) $id ), $id );
			return (string) ( $h['Idempotency-Key'] ?? '' );
		}

		public function test_waiter_reads_fresh_alt_despite_warm_object_cache(): void {
			$id = 21;
			$this->assertTrue( Generation_Lock::acquire( $id ) ); // holder = other process
			$this->assertSame( '', get_post_meta( $id, self::ALT, true ) ); // warm cache with empty alt
			Generation_Lock::$sleeper = static function () use ( $id ) {
				// Holder (another PHP process) writes the DB, not our cache, then releases.
				$GLOBALS['bbai_test_meta'][ $id ][ self::ALT ] = 'Cron alt';
				Generation_Lock::release( $id );
			};
			$calls  = 0;
			$result = Generation_Lock::run( $id, static function () use ( &$calls ) { $calls++; return 'second charge'; }, $this->alt( $id ) );
			$this->assertSame( 'Cron alt', $result );
			$this->assertSame( 0, $calls, 'waiter must not call the API when the holder already saved alt text' );
		}

		public function test_waiter_taking_fresh_lock_reuses_holders_key(): void {
			$id = 22;
			// Holder process: took the lock and started an attempt, then died mid-POST
			// (its finally never ran), or released without saving.
			$this->assertTrue( Generation_Lock::acquire( $id ) );
			$holder = array( 'id' => 'holder-attempt-1', 'started_at' => time() );
			$GLOBALS['bbai_test_meta'][ $id ][ Generation_Lock::ATTEMPT_META ] = $holder;
			get_post_meta( $id, self::ALT, true ); // warm cache from before the attempt existed
			$GLOBALS['bbai_test_meta_cache'][ $id ] = array();
			$holder_key = Generation_Lock::idempotency_key( $id, $holder['id'] );
			Generation_Lock::$sleeper = static function () use ( $id ) { Generation_Lock::release( $id ); };

			$sent = null;
			Generation_Lock::run( $id, static function () use ( $id, &$sent ) { $sent = self::sent_key( $id ); return 'alt'; }, $this->alt( $id ) );
			$this->assertNotSame( '', (string) $sent, 'queue/cron generate path must send an Idempotency-Key' );
			$this->assertSame( $holder_key, $sent, 'waiter must reuse the holder attempt key' );
		}

		public function test_generate_then_regenerate_use_different_keys(): void {
			$id   = 23;
			$keys = array();
			$gen  = static function () use ( $id, &$keys ) {
				$keys[] = self::sent_key( $id );
				update_post_meta( $id, self::ALT, 'alt ' . count( $keys ) );
				return 'ok';
			};
			Generation_Lock::run( $id, $gen, $this->alt( $id ) ); // Generate
			Generation_Lock::run( $id, $gen, $this->alt( $id ) ); // Regenerate
			$this->assertCount( 2, $keys );
			$this->assertNotSame( '', $keys[0] );
			$this->assertNotSame( $keys[0], $keys[1], 'a new user action must not replay the old attempt' );
			$this->assertSame( '', get_post_meta( $id, Generation_Lock::ATTEMPT_META, true ), 'attempt cleared after success' );
		}

		/** Holder process left a running attempt aged $age seconds; lock held, then released while we wait. */
		private function waiter_key_with_holder_attempt( int $id, int $age, bool $regenerate = false ): array {
			$this->assertTrue( Generation_Lock::acquire( $id ) );
			$GLOBALS['bbai_test_meta'][ $id ][ Generation_Lock::ATTEMPT_META ] = array( 'id' => 'holder-' . $id, 'started_at' => time() - $age );
			Generation_Lock::$sleeper = static function () use ( $id ) { Generation_Lock::release( $id ); };
			$sent = '';
			Generation_Lock::run( $id, static function () use ( $id, &$sent ) { $sent = self::sent_key( $id ); return 'alt'; }, $this->alt( $id ), $regenerate );
			return array( $sent, Generation_Lock::idempotency_key( $id, 'holder-' . $id ) );
		}

		public function test_d_waiter_does_not_reuse_stale_attempt(): void {
			[ $sent, $holder ] = $this->waiter_key_with_holder_attempt( 31, 3 * MINUTE_IN_SECONDS + 5 );
			$this->assertNotSame( '', $sent );
			$this->assertNotSame( $holder, $sent, 'attempt older than the 3 min lock TTL is stale' );
		}

		public function test_f_waiter_reuses_fresh_attempt(): void {
			[ $sent, $holder ] = $this->waiter_key_with_holder_attempt( 32, 3 * MINUTE_IN_SECONDS - 10 );
			$this->assertSame( $holder, $sent );
		}

		public function test_e_regenerate_never_reuses_fresh_attempt(): void {
			[ $sent, $holder ] = $this->waiter_key_with_holder_attempt( 33, 5, true );
			$this->assertNotSame( '', $sent );
			$this->assertNotSame( $holder, $sent, 'explicit Regenerate must start a new attempt' );
		}

		public function test_e2_first_holder_ignores_leftover_fresh_attempt(): void {
			$id = 34;
			$GLOBALS['bbai_test_meta'][ $id ][ Generation_Lock::ATTEMPT_META ] = array( 'id' => 'left', 'started_at' => time() );
			$sent = '';
			Generation_Lock::run( $id, static function () use ( $id, &$sent ) { $sent = self::sent_key( $id ); return 'alt'; }, $this->alt( $id ) );
			$this->assertNotSame( '', $sent );
			$this->assertNotSame( Generation_Lock::idempotency_key( $id, 'left' ), $sent, 'no wait = new user action = new key' );
		}

		public function test_key_is_deterministic_per_site_attachment_attempt(): void {
			$this->assertSame( Generation_Lock::idempotency_key( 5, 'a' ), Generation_Lock::idempotency_key( 5, 'a' ) );
			$this->assertNotSame( Generation_Lock::idempotency_key( 5, 'a' ), Generation_Lock::idempotency_key( 6, 'a' ) );
		}

		public function test_api_client_generate_path_applies_shared_key(): void {
			$src = (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php' );
			$fn  = substr( $src, (int) strpos( $src, 'public function generate_alt_text(' ) );
			$pos_apply = strpos( $fn, 'Generation_Lock::apply_idempotency_header(' );
			$pos_post  = strpos( $fn, "request_with_retry( \$endpoint, 'POST'" );
			$this->assertNotFalse( $pos_apply );
			$this->assertNotFalse( $pos_post );
			$this->assertLessThan( $pos_post, $pos_apply );
		}
	}
}
