<?php
/**
 * Per-attachment generation lock with a wait-and-fallback path.
 *
 * @package BeepBeepAI\AltTextGenerator
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Generation_Lock {

	/** @var callable|null Test seam for sleeping (receives microseconds). */
	public static $sleeper = null;

	public static function key( int $attachment_id ): string {
		return 'bbai_gen_lock_' . $attachment_id;
	}

	/**
	 * Atomically claim an attachment. add_option() is atomic because option_name is UNIQUE.
	 */
	public static function acquire( int $attachment_id, int $ttl = 0 ): bool {
		if ( $attachment_id <= 0 ) {
			return true;
		}
		$ttl     = $ttl > 0 ? $ttl : (int) apply_filters( 'bbai_generation_lock_ttl', 3 * MINUTE_IN_SECONDS );
		$key     = self::key( $attachment_id );
		$expires = time() + max( 30, $ttl );

		if ( add_option( $key, $expires, '', 'no' ) ) {
			return true;
		}

		// Lock exists: take it over only if it has expired (crashed worker).
		wp_cache_delete( $key, 'options' );
		$current = (int) get_option( $key, 0 );
		if ( $current > 0 && $current >= time() ) {
			return false;
		}
		delete_option( $key );
		return (bool) add_option( $key, $expires, '', 'no' );
	}

	public static function release( int $attachment_id ): void {
		if ( $attachment_id > 0 ) {
			delete_option( self::key( $attachment_id ) );
		}
	}

	public static function is_held( int $attachment_id ): bool {
		if ( $attachment_id <= 0 ) {
			return false;
		}
		$key = self::key( $attachment_id );
		wp_cache_delete( $key, 'options' );
		$current = (int) get_option( $key, 0 );
		return $current > 0 && $current >= time();
	}

	private static function pause( int $usec ): void {
		if ( is_callable( self::$sleeper ) ) {
			call_user_func( self::$sleeper, $usec );
			return;
		}
		usleep( $usec );
	}

	/**
	 * Run $generate under the lock. If another run holds it, briefly re-check:
	 * - alt text appeared (differs from the value before we waited) -> return it;
	 * - lock gone and still no new alt text -> take the lock and generate ourselves;
	 * - still held when the wait runs out -> generation_in_progress error.
	 *
	 * The holder always releases in finally, so a holder that bails out
	 * before generating (automation off, quota gate, unsupported format...)
	 * frees the image immediately for the waiting request.
	 *
	 * @param int      $attachment_id
	 * @param callable $generate    fn(): string|\WP_Error
	 * @param callable $current_alt fn(): string  current saved alt text
	 * @return mixed
	 */
	public static function run( int $attachment_id, callable $generate, callable $current_alt ) {
		$alt_before = (string) call_user_func( $current_alt );
		$wait_ms    = max( 0, (int) apply_filters( 'bbai_generation_lock_wait_ms', 8000 ) );
		$step_ms    = max( 50, (int) apply_filters( 'bbai_generation_lock_poll_ms', 500 ) );
		$waited     = 0;

		while ( true ) {
			if ( self::acquire( $attachment_id ) ) {
				try {
					// The holder may have finished just before we took the lock.
					$alt_now = (string) call_user_func( $current_alt );
					if ( $waited > 0 && '' !== $alt_now && $alt_now !== $alt_before ) {
						return $alt_now;
					}
					return call_user_func( $generate );
				} finally {
					self::release( $attachment_id );
				}
			}

			$alt_now = (string) call_user_func( $current_alt );
			if ( '' !== $alt_now && $alt_now !== $alt_before ) {
				return $alt_now;
			}

			if ( $waited >= $wait_ms ) {
				return new \WP_Error(
					'generation_in_progress',
					__( 'This image is already being processed. Please wait a moment.', 'beepbeep-ai-alt-text-generator' ),
					array( 'attachment_id' => $attachment_id )
				);
			}
			self::pause( $step_ms * 1000 );
			$waited += $step_ms;
		}
	}
}
