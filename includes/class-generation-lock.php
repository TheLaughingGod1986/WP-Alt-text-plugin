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

	/** @var callable|null Test seam for the wall clock (returns seconds as float). */
	public static $clock = null;

	/** Prefix written to a queue job's last_error when a waiter gave up on a held image. */
	const QUEUE_IN_PROGRESS_MARK = 'generation_in_progress';

	/**
	 * Max times a queue job is parked for "in progress" without using up an
	 * attempt. At the default 60 s retry delay, 10 deferrals is ~10 min, well
	 * past the 3-minute lock TTL, so a crashed holder's lock has long expired
	 * and normal takeover applies. Past the cap, in-progress retries count as
	 * normal attempts, so a job can never loop forever.
	 */
	const QUEUE_MAX_DEFERRALS = 10;

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
	 * Default time a waiting request re-checks before giving up.
	 *
	 * 25 s: the holder's backend call is ~7-8 s typically, and the backend
	 * makes a duplicate request wait up to 20 s for the first one before it
	 * replays or answers 409, so the waiter should outlast that 20 s plus
	 * the holder's DB write. 25 s still leaves ~5 s of a common 30 s PHP
	 * max_execution_time for the request's own work. The filter can lower it
	 * but never raise it past MAX_WAIT_MS. The wait is bounded by wall-clock
	 * time, not just by summed sleeps, so read overhead can't push it past
	 * the limit (QA saw 15.04 s against the old 15 s counter).
	 */
	const DEFAULT_WAIT_MS = 25000;
	const MAX_WAIT_MS     = 25000;

	private static function now(): float {
		return is_callable( self::$clock ) ? (float) call_user_func( self::$clock ) : microtime( true );
	}

	public static function run( int $attachment_id, callable $generate, callable $current_alt, bool $fresh_attempt = false ) {
		$read_alt   = static function () use ( $attachment_id, $current_alt ) {
			self::flush_meta_cache( $attachment_id );
			return (string) call_user_func( $current_alt );
		};
		$alt_before = $read_alt();
		$wait_ms    = min( self::MAX_WAIT_MS, max( 0, (int) apply_filters( 'bbai_generation_lock_wait_ms', self::DEFAULT_WAIT_MS ) ) );
		$step_ms    = max( 50, (int) apply_filters( 'bbai_generation_lock_poll_ms', 500 ) );
		$waited     = 0;
		$started    = self::now();

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

			$elapsed_ms = (int) round( ( self::now() - $started ) * 1000 );
			if ( $waited >= $wait_ms || $elapsed_ms >= $wait_ms ) {
				return new \WP_Error(
					'generation_in_progress',
					__( 'This image is already being processed. Please wait a moment.', 'beepbeep-ai-alt-text-generator' ),
					array( 'attachment_id' => $attachment_id )
				);
			}
			$sleep_ms = min( $step_ms, max( 1, $wait_ms - max( $waited, $elapsed_ms ) ) );
			self::pause( $sleep_ms * 1000 );
			$waited += $sleep_ms;
		}
	}

	/**
	 * Queue: what to do with a job whose run returned generation_in_progress.
	 * 'complete' only if alt text is actually saved now; otherwise 'retry' so
	 * the job stays pending. It is never marked done without saved alt text.
	 */
	public static function queue_outcome_after_in_progress( int $attachment_id, callable $current_alt ): string {
		self::flush_meta_cache( $attachment_id );
		return '' !== trim( (string) call_user_func( $current_alt ) ) ? 'complete' : 'retry';
	}

	/**
	 * Queue: before re-running a job that was parked because another run held
	 * the image, skip it (no backend call) if the holder has since saved alt.
	 */
	public static function queue_should_skip_retry( int $attachment_id, string $last_error, callable $current_alt ): bool {
		if ( 0 !== strpos( $last_error, self::QUEUE_IN_PROGRESS_MARK ) ) {
			return false;
		}
		return 'complete' === self::queue_outcome_after_in_progress( $attachment_id, $current_alt );
	}

	/**
	 * Queue: how many times this job was already parked for in-progress,
	 * read from its last_error marker ("generation_in_progress#N: ...").
	 */
	public static function queue_deferral_count( string $last_error ): int {
		if ( preg_match( '/^' . preg_quote( self::QUEUE_IN_PROGRESS_MARK, '/' ) . '#(\d+):/', $last_error, $m ) ) {
			return (int) $m[1];
		}
		return 0 === strpos( $last_error, self::QUEUE_IN_PROGRESS_MARK ) ? 1 : 0;
	}

	/**
	 * Queue: decide how to park a job after generation_in_progress.
	 *
	 * @return array{refund_attempt:bool,message:string,deferrals:int}
	 */
	public static function queue_defer_plan( string $previous_last_error, string $message ): array {
		$count = self::queue_deferral_count( $previous_last_error ) + 1;
		$capped = $count > (int) apply_filters( 'bbai_generation_lock_queue_max_deferrals', self::QUEUE_MAX_DEFERRALS );
		return array(
			'refund_attempt' => ! $capped,
			'message'        => self::QUEUE_IN_PROGRESS_MARK . '#' . $count . ': ' . ( $capped ? 'deferral cap reached, counting as an attempt. ' : '' ) . $message,
			'deferrals'      => $count,
		);
	}

	/**
	 * Queue: seconds before a parked job is retried. A crashed holder's lock
	 * expires after the lock TTL (3 min); retries keep coming until then,
	 * and the first retry after expiry takes the lock and generates.
	 */
	public static function queue_retry_delay(): int {
		return max( 30, (int) apply_filters( 'bbai_generation_lock_queue_retry_delay', MINUTE_IN_SECONDS ) );
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
