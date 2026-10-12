<?php
/**
 * Run state and locking for the image audit.
 *
 * State lives in a non-autoloaded option rather than a transient: a persistent
 * object cache is free to evict transients at any time, and losing the cursor
 * mid-run would silently restart a scan of a large library.
 *
 * @package BeepBeep_AI
 * @since   4.8.0
 */

namespace BeepBeepAI\AltTextGenerator\Audit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns the lifecycle of an audit run.
 */
class Audit_Progress_Service {

	const STATE_OPTION          = 'bbai_audit_run_state';
	const SUMMARY_OPTION        = 'bbai_audit_summary';
	const DUPLICATE_MAP_OPTION  = 'bbai_audit_duplicate_map';
	const LAST_COMPLETED_OPTION = 'bbai_audit_last_completed_at';
	const LOCK_OPTION           = 'bbai_audit_lock';

	/* ─── Run statuses ─── */

	const STATUS_NOT_STARTED = 'not_started';
	const STATUS_QUEUED      = 'queued';
	const STATUS_RUNNING     = 'running';
	const STATUS_COMPLETED   = 'completed';
	const STATUS_FAILED      = 'failed';
	const STATUS_CANCELLED   = 'cancelled';

	/**
	 * How long a lock is honoured without a heartbeat, in seconds.
	 *
	 * A batch that takes longer than this has almost certainly died with the
	 * request that started it, so the lock becomes stealable.
	 */
	const LOCK_TTL = 300;

	/**
	 * Statuses from which a run can be picked up again.
	 *
	 * @return string[]
	 */
	public static function resumable_statuses() {
		return array( self::STATUS_RUNNING, self::STATUS_QUEUED, self::STATUS_FAILED );
	}

	/**
	 * Statuses that mean no run is in flight.
	 *
	 * @return string[]
	 */
	public static function terminal_statuses() {
		return array( self::STATUS_COMPLETED, self::STATUS_CANCELLED );
	}

	/**
	 * A blank run state.
	 *
	 * @return array<string,mixed>
	 */
	public static function empty_state() {
		return array(
			'run_id'              => '',
			'status'              => self::STATUS_NOT_STARTED,
			'total_images'        => 0,
			'processed_images'    => 0,
			'last_attachment_id'  => 0,
			'batch_number'        => 0,
			'errors'              => 0,
			'started_at'          => 0,
			'updated_at'          => 0,
			'completed_at'        => 0,
			'started_by'          => 0,
			'duplicate_groups'    => 0,
			'duplicate_truncated' => false,
			'message'             => '',
			'counters'            => array(
				'critical'    => 0,
				'warning'     => 0,
				'review'      => 0,
				'information' => 0,
				'settled'     => 0,
				'passing'     => 0,
			),
		);
	}

	/**
	 * Current run state, normalised.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_state() {
		$stored = get_option( self::STATE_OPTION, array() );

		if ( ! is_array( $stored ) ) {
			return self::empty_state();
		}

		$state = array_merge( self::empty_state(), $stored );

		if ( ! in_array( $state['status'], self::all_statuses(), true ) ) {
			$state['status'] = self::STATUS_NOT_STARTED;
		}

		$state['counters'] = array_merge( self::empty_state()['counters'], (array) $state['counters'] );

		return $state;
	}

	/**
	 * Every valid run status.
	 *
	 * @return string[]
	 */
	public static function all_statuses() {
		return array(
			self::STATUS_NOT_STARTED,
			self::STATUS_QUEUED,
			self::STATUS_RUNNING,
			self::STATUS_COMPLETED,
			self::STATUS_FAILED,
			self::STATUS_CANCELLED,
		);
	}

	/**
	 * Persist run state.
	 *
	 * Written with autoload off — this option changes on every batch and has no
	 * business being loaded on every front-end request.
	 *
	 * @param array<string,mixed> $state Run state.
	 * @return array<string,mixed>
	 */
	public static function save_state( array $state ) {
		$state['updated_at'] = time();

		update_option( self::STATE_OPTION, $state, false );

		return $state;
	}

	/**
	 * Whether a run is currently in flight.
	 *
	 * @param array<string,mixed>|null $state Optional pre-loaded state.
	 * @return bool
	 */
	public static function is_running( $state = null ) {
		$state = null === $state ? self::get_state() : $state;

		return in_array( $state['status'], array( self::STATUS_RUNNING, self::STATUS_QUEUED ), true );
	}

	/**
	 * Whether an interrupted run can be picked up where it left off.
	 *
	 * @param array<string,mixed>|null $state Optional pre-loaded state.
	 * @return bool
	 */
	public static function can_resume( $state = null ) {
		$state = null === $state ? self::get_state() : $state;

		if ( ! in_array( $state['status'], self::resumable_statuses(), true ) ) {
			return false;
		}

		return (int) $state['processed_images'] < (int) $state['total_images'];
	}

	/**
	 * Whether a run looks abandoned rather than active.
	 *
	 * @param array<string,mixed>|null $state Optional pre-loaded state.
	 * @return bool
	 */
	public static function is_stale( $state = null ) {
		$state = null === $state ? self::get_state() : $state;

		if ( ! self::is_running( $state ) ) {
			return false;
		}

		return ( time() - (int) $state['updated_at'] ) > self::LOCK_TTL;
	}

	/* ─── Locking ─── */

	/**
	 * Take the run lock.
	 *
	 * add_option() is the atomic primitive here: option_name carries a unique
	 * index, so two simultaneous requests cannot both succeed. An expired lock is
	 * cleared first, which is how a run killed mid-request recovers.
	 *
	 * @param string $run_id  Run identifier.
	 * @param int    $user_id User starting the run.
	 * @return bool True when the lock was taken.
	 */
	public static function acquire_lock( $run_id, $user_id = 0 ) {
		self::clear_stale_lock();

		$lock = array(
			'run_id'      => (string) $run_id,
			'user_id'     => (int) $user_id,
			'acquired_at' => time(),
			'expires_at'  => time() + self::LOCK_TTL,
		);

		if ( add_option( self::LOCK_OPTION, $lock, '', false ) ) {
			return true;
		}

		// Already held by this same run (a retried request) — treat as success.
		$existing = self::get_lock();

		return is_array( $existing ) && '' !== (string) $run_id && ( $existing['run_id'] ?? '' ) === (string) $run_id;
	}

	/**
	 * Current lock, if any.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function get_lock() {
		$lock = get_option( self::LOCK_OPTION, null );

		return is_array( $lock ) ? $lock : null;
	}

	/**
	 * Push the lock expiry forward. Called after each batch.
	 *
	 * @param string $run_id Run identifier.
	 * @return void
	 */
	public static function refresh_lock( $run_id ) {
		$lock = self::get_lock();

		if ( ! is_array( $lock ) || ( $lock['run_id'] ?? '' ) !== (string) $run_id ) {
			return;
		}

		$lock['expires_at'] = time() + self::LOCK_TTL;

		update_option( self::LOCK_OPTION, $lock, false );
	}

	/**
	 * Release the lock.
	 *
	 * @return void
	 */
	public static function release_lock() {
		delete_option( self::LOCK_OPTION );
	}

	/**
	 * Drop a lock whose holder has gone away.
	 *
	 * @return bool True when a stale lock was cleared.
	 */
	public static function clear_stale_lock() {
		$lock = self::get_lock();

		if ( ! is_array( $lock ) ) {
			return false;
		}

		if ( (int) ( $lock['expires_at'] ?? 0 ) > time() ) {
			return false;
		}

		self::release_lock();

		return true;
	}

	/* ─── Lifecycle ─── */

	/**
	 * Begin a run.
	 *
	 * @param int    $total_images Images in scope.
	 * @param int    $user_id      User starting the run.
	 * @param string $run_id       Run identifier.
	 * @return array<string,mixed>
	 */
	public static function start_run( $total_images, $user_id, $run_id ) {
		$state = self::empty_state();

		$state['run_id']       = (string) $run_id;
		$state['status']       = $total_images > 0 ? self::STATUS_RUNNING : self::STATUS_COMPLETED;
		$state['total_images'] = max( 0, (int) $total_images );
		$state['started_at']   = time();
		$state['started_by']   = (int) $user_id;

		if ( 0 === $state['total_images'] ) {
			$state['completed_at'] = time();
		}

		return self::save_state( $state );
	}

	/**
	 * Mark a run finished.
	 *
	 * @param array<string,mixed> $state Run state.
	 * @return array<string,mixed>
	 */
	public static function complete_run( array $state ) {
		$state['status']           = self::STATUS_COMPLETED;
		$state['completed_at']     = time();
		$state['processed_images'] = max( (int) $state['processed_images'], 0 );

		update_option( self::LAST_COMPLETED_OPTION, time(), false );
		delete_option( self::DUPLICATE_MAP_OPTION );
		self::release_lock();

		return self::save_state( $state );
	}

	/**
	 * Mark a run cancelled. Findings already written are kept.
	 *
	 * @param array<string,mixed>|null $state Optional pre-loaded state.
	 * @return array<string,mixed>
	 */
	public static function cancel_run( $state = null ) {
		$state = null === $state ? self::get_state() : $state;

		$state['status']  = self::STATUS_CANCELLED;
		$state['message'] = '';

		delete_option( self::DUPLICATE_MAP_OPTION );
		self::release_lock();

		return self::save_state( $state );
	}

	/**
	 * Mark a run failed, keeping the cursor so it can be resumed.
	 *
	 * @param array<string,mixed> $state   Run state.
	 * @param string              $message Operator-facing reason.
	 * @return array<string,mixed>
	 */
	public static function fail_run( array $state, $message = '' ) {
		$state['status']  = self::STATUS_FAILED;
		$state['message'] = is_string( $message ) ? $message : '';

		self::release_lock();

		return self::save_state( $state );
	}

	/* ─── Duplicate map ─── */

	/**
	 * Store the run-scoped duplicate map.
	 *
	 * @param array $map       Hash => group size.
	 * @param bool  $truncated Whether the group cap was hit.
	 * @return void
	 */
	public static function save_duplicate_map( array $map, $truncated = false ) {
		update_option(
			self::DUPLICATE_MAP_OPTION,
			array(
				'map'       => $map,
				'truncated' => (bool) $truncated,
			),
			false
		);
	}

	/**
	 * Read the run-scoped duplicate map.
	 *
	 * @return array{map:array<string,int>,truncated:bool}
	 */
	public static function get_duplicate_map() {
		$stored = get_option( self::DUPLICATE_MAP_OPTION, array() );

		if ( ! is_array( $stored ) || ! isset( $stored['map'] ) || ! is_array( $stored['map'] ) ) {
			return array(
				'map'       => array(),
				'truncated' => false,
			);
		}

		return array(
			'map'       => $stored['map'],
			'truncated' => ! empty( $stored['truncated'] ),
		);
	}

	/* ─── Reporting ─── */

	/**
	 * Progress percentage.
	 *
	 * @param array<string,mixed> $state Run state.
	 * @return int
	 */
	public static function percent( array $state ) {
		$total = max( 0, (int) $state['total_images'] );

		if ( $total <= 0 ) {
			return self::STATUS_COMPLETED === $state['status'] ? 100 : 0;
		}

		$processed = max( 0, min( $total, (int) $state['processed_images'] ) );

		return (int) round( ( $processed / $total ) * 100 );
	}

	/**
	 * When the last successful scan finished.
	 *
	 * @return int Unix timestamp, 0 when never.
	 */
	public static function last_completed_at() {
		return max( 0, (int) get_option( self::LAST_COMPLETED_OPTION, 0 ) );
	}

	/**
	 * Store the completed summary.
	 *
	 * @param array<string,mixed> $summary Summary payload.
	 * @return void
	 */
	public static function save_summary( array $summary ) {
		update_option( self::SUMMARY_OPTION, $summary, false );
	}

	/**
	 * Read the last completed summary.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function get_summary() {
		$summary = get_option( self::SUMMARY_OPTION, null );

		return is_array( $summary ) ? $summary : null;
	}

	/**
	 * Forget everything about audit runs. Used by the reset path and uninstall.
	 *
	 * @return void
	 */
	public static function reset() {
		delete_option( self::STATE_OPTION );
		delete_option( self::SUMMARY_OPTION );
		delete_option( self::DUPLICATE_MAP_OPTION );
		delete_option( self::LAST_COMPLETED_OPTION );
		self::release_lock();
	}
}
