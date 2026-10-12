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

	/** Post meta holding the in-flight generation attempt: array{id:string,started_at:int}. */
	const ATTEMPT_META = '_bbai_gen_attempt';

	/** @var array<int,string> Idempotency-Key of the attempt currently being generated, per attachment. */
	private static $active_keys = array();

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

	/**
	 * Drop the object-cache copy of this attachment's post meta so the next
	 * get_post_meta() reads the DB. Another PHP process (cron vs click) writes
	 * the alt text; with a persistent object cache our copy can be stale.
	 */
	public static function flush_meta_cache( int $attachment_id ): void {
		if ( $attachment_id > 0 ) {
			wp_cache_delete( $attachment_id, 'post_meta' );
		}
	}

	/** Read the stored attempt, uncached. */
	public static function get_attempt( int $attachment_id ): array {
		self::flush_meta_cache( $attachment_id );
		$attempt = get_post_meta( $attachment_id, self::ATTEMPT_META, true );
		if ( ! is_array( $attempt ) || empty( $attempt['id'] ) ) {
			return array();
		}
		$ttl = (int) apply_filters( 'bbai_generation_lock_ttl', 3 * MINUTE_IN_SECONDS );
		if ( (int) ( $attempt['started_at'] ?? 0 ) < time() - max( 30, $ttl ) ) {
			return array(); // Abandoned by a crashed worker: start fresh.
		}
		return $attempt;
	}

	/**
	 * Waiter path only ($may_reuse: we found the lock held): reuse a running
	 * attempt younger than the lock TTL so a second POST hits the backend's
	 * Idempotency-Key dedupe. Anything else (first holder, explicit Regenerate,
	 * stale attempt) starts and stores a new attempt. A finished attempt is always cleared, so a later
	 * Generate/Regenerate gets a new key and never a 24h replay of old alt text.
	 */
	public static function begin_attempt( int $attachment_id, bool $may_reuse = false ): array {
		$attempt = $may_reuse ? self::get_attempt( $attachment_id ) : array();
		if ( empty( $attempt ) ) {
			$attempt = array(
				'id'         => function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : bin2hex( random_bytes( 16 ) ),
				'started_at' => time(),
			);
			update_post_meta( $attachment_id, self::ATTEMPT_META, $attempt );
			self::flush_meta_cache( $attachment_id );
		}
		return $attempt;
	}

	public static function clear_attempt( int $attachment_id ): void {
		delete_post_meta( $attachment_id, self::ATTEMPT_META );
		self::flush_meta_cache( $attachment_id );
	}

	/** Deterministic key: same site + attachment + attempt => same key in every process. */
	public static function idempotency_key( int $attachment_id, string $attempt_id ): string {
		return 'bbai-' . hash( 'sha256', get_site_url() . '|' . $attachment_id . '|' . $attempt_id );
	}

	/** Key for the generation currently running for this attachment in this request, or ''. */
	public static function active_key( int $attachment_id ): string {
		return self::$active_keys[ $attachment_id ] ?? '';
	}

	/** Add the shared Idempotency-Key to an /api/alt-text request's headers. */
	public static function apply_idempotency_header( array $headers, int $attachment_id ): array {
		$key = self::active_key( $attachment_id );
		if ( '' !== $key && empty( $headers['Idempotency-Key'] ) ) {
			$headers['Idempotency-Key']     = $key;
			$headers['X-Generation-Run-ID'] = $key;
		}
		return $headers;
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
	/**
	 * Default time a waiting request re-checks before giving up. The backend
	 * generation call alone takes ~7-8 s, so this must comfortably exceed it.
	 * Stays far below the 3-minute lock TTL.
	 */
	const DEFAULT_WAIT_MS = 15000;

	public static function run( int $attachment_id, callable $generate, callable $current_alt, bool $fresh_attempt = false ) {
		$read_alt   = static function () use ( $attachment_id, $current_alt ) {
			self::flush_meta_cache( $attachment_id );
			return (string) call_user_func( $current_alt );
		};
		$alt_before = $read_alt();
		$wait_ms    = max( 0, (int) apply_filters( 'bbai_generation_lock_wait_ms', self::DEFAULT_WAIT_MS ) );
		$step_ms    = max( 50, (int) apply_filters( 'bbai_generation_lock_poll_ms', 500 ) );
		$waited     = 0;

		while ( true ) {
			if ( self::acquire( $attachment_id ) ) {
				try {
					// The holder may have finished just before we took the lock.
					$alt_now = $read_alt();
					if ( $waited > 0 && '' !== $alt_now && $alt_now !== $alt_before ) {
						return $alt_now;
					}
					return self::generate_with_attempt( $attachment_id, $generate, $waited > 0 && ! $fresh_attempt );
				} finally {
					self::release( $attachment_id );
				}
			}

			$alt_now = $read_alt();
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

	private static function generate_with_attempt( int $attachment_id, callable $generate, bool $may_reuse ) {
		if ( $attachment_id <= 0 ) {
			return call_user_func( $generate );
		}
		$attempt = self::begin_attempt( $attachment_id, $may_reuse );
		self::$active_keys[ $attachment_id ] = self::idempotency_key( $attachment_id, (string) $attempt['id'] );
		try {
			return call_user_func( $generate );
		} finally {
			unset( self::$active_keys[ $attachment_id ] );
			// Success or a returned failure: the attempt is over. If we were
			// killed mid-request (fatal/timeout), finally may not run and the
			// attempt stays so the next run reuses the key and gets deduped.
			self::clear_attempt( $attachment_id );
		}
	}
}
