<?php
/**
 * Orchestrates audit runs.
 *
 * The single entry point the admin layer talks to: start, tick, cancel, and
 * read the summary. Everything about how a run is stored, locked or batched is
 * behind this class.
 *
 * @package BeepBeep_AI
 * @since   4.8.0
 */

namespace BeepBeepAI\AltTextGenerator\Audit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Audit run orchestration.
 */
class Image_Audit_Service {

	/**
	 * Discovery service.
	 *
	 * @var Image_Discovery_Service
	 */
	private $discovery;

	/**
	 * Batch processor.
	 *
	 * @var Audit_Batch_Processor
	 */
	private $processor;

	/**
	 * Build the service.
	 *
	 * @param Image_Discovery_Service|null $discovery Discovery service.
	 * @param Audit_Batch_Processor|null   $processor Batch processor.
	 */
	public function __construct( $discovery = null, $processor = null ) {
		$this->discovery = $discovery instanceof Image_Discovery_Service ? $discovery : new Image_Discovery_Service();
		$this->processor = $processor instanceof Audit_Batch_Processor
			? $processor
			: new Audit_Batch_Processor( $this->discovery );
	}

	/**
	 * Start a run, or refuse and explain why.
	 *
	 * Refusing rather than restarting is deliberate: silently throwing away a
	 * half-finished scan of a large library is worse than making the caller
	 * choose between resuming and starting over.
	 *
	 * @param int  $user_id User starting the run.
	 * @param bool $restart Whether to discard an existing incomplete run.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function start( $user_id = 0, $restart = false ) {
		if ( ! Image_Audit_Repository::table_exists( true ) ) {
			return new \WP_Error(
				'bbai_audit_no_table',
				__( 'The audit storage table is missing. Deactivate and reactivate OpptiAI Alt Text to create it.', 'beepbeep-ai-alt-text-generator' ),
				array( 'status' => 500 )
			);
		}

		$existing = Audit_Progress_Service::get_state();

		if ( ! $restart && Audit_Progress_Service::can_resume( $existing ) && ! Audit_Progress_Service::is_stale( $existing ) ) {
			return new \WP_Error(
				'bbai_audit_in_progress',
				__( 'An audit is already in progress. Resume it, or start a new one to discard the partial results.', 'beepbeep-ai-alt-text-generator' ),
				array(
					'status' => 409,
					'state'  => $this->describe( $existing ),
				)
			);
		}

		Audit_Progress_Service::clear_stale_lock();

		/*
		 * An explicit restart abandons the previous run, so its lock goes with it —
		 * but only that one. A lock held by some other run still blocks, so one
		 * admin choosing "start over" cannot yank the scan out from under another.
		 */
		if ( $restart ) {
			$lock = Audit_Progress_Service::get_lock();

			if ( is_array( $lock ) && (string) ( $lock['run_id'] ?? '' ) === (string) $existing['run_id'] ) {
				Audit_Progress_Service::release_lock();
			}
		}

		$run_id = $this->generate_run_id();

		if ( ! Audit_Progress_Service::acquire_lock( $run_id, $user_id ) ) {
			return new \WP_Error(
				'bbai_audit_locked',
				__( 'Another audit is running right now. Try again in a few minutes.', 'beepbeep-ai-alt-text-generator' ),
				array( 'status' => 409 )
			);
		}

		// A fresh run starts from a clean slate so removed images cannot linger as
		// findings; a resume keeps whatever the previous run already wrote.
		Image_Audit_Repository::truncate_for_site();

		$duplicates = $this->discovery->build_duplicate_map();
		Audit_Progress_Service::save_duplicate_map( $duplicates['map'], $duplicates['truncated'] );

		$total = $this->discovery->count_images();
		$state = Audit_Progress_Service::start_run( $total, $user_id, $run_id );

		$state['duplicate_groups']    = count( $duplicates['map'] );
		$state['duplicate_truncated'] = $duplicates['truncated'];
		$state                        = Audit_Progress_Service::save_state( $state );

		Audit_Telemetry_Service::emit(
			Audit_Telemetry_Service::STARTED,
			array(
				'total_images_bucket' => Audit_Telemetry_Service::size_bucket( $total ),
				'scan_size_bucket'    => Audit_Telemetry_Service::size_bucket( $total ),
				'is_resume'           => false,
			),
			$run_id
		);

		if ( 0 === $total ) {
			return $this->finish( $state );
		}

		return $this->describe( $state );
	}

	/**
	 * Resume an interrupted run.
	 *
	 * @param int $user_id User resuming.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function resume( $user_id = 0 ) {
		$state = Audit_Progress_Service::get_state();

		if ( ! Audit_Progress_Service::can_resume( $state ) ) {
			return new \WP_Error(
				'bbai_audit_not_resumable',
				__( 'There is no interrupted audit to resume.', 'beepbeep-ai-alt-text-generator' ),
				array( 'status' => 400 )
			);
		}

		if ( ! Audit_Progress_Service::acquire_lock( (string) $state['run_id'], $user_id ) ) {
			return new \WP_Error(
				'bbai_audit_locked',
				__( 'Another audit is running right now. Try again in a few minutes.', 'beepbeep-ai-alt-text-generator' ),
				array( 'status' => 409 )
			);
		}

		$state['status']  = Audit_Progress_Service::STATUS_RUNNING;
		$state['message'] = '';

		return $this->describe( Audit_Progress_Service::save_state( $state ) );
	}

	/**
	 * Process one batch.
	 *
	 * @param string $run_id Expected run identifier, guarding against a stale poll.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function tick( $run_id = '' ) {
		$state = Audit_Progress_Service::get_state();

		if ( '' !== $run_id && (string) $state['run_id'] !== (string) $run_id ) {
			return new \WP_Error(
				'bbai_audit_run_mismatch',
				__( 'That audit is no longer running. Refresh the page to see the latest results.', 'beepbeep-ai-alt-text-generator' ),
				array( 'status' => 409 )
			);
		}

		if ( ! Audit_Progress_Service::is_running( $state ) ) {
			return $this->describe( $state );
		}

		$duplicates = Audit_Progress_Service::get_duplicate_map();

		try {
			$state = $this->processor->process_batch( $state, $duplicates['map'] );
		} catch ( \Throwable $e ) {
			Audit_Telemetry_Service::emit(
				Audit_Telemetry_Service::FAILED,
				array(
					'batch_number'     => (int) $state['batch_number'],
					// Class name only — never the message, which can echo site content.
					'error_type'       => strtolower( basename( str_replace( '\\', '/', get_class( $e ) ) ) ),
					'scan_size_bucket' => Audit_Telemetry_Service::size_bucket( (int) $state['total_images'] ),
				),
				(string) $state['run_id']
			);

			$state = Audit_Progress_Service::fail_run(
				$state,
				__( 'The audit stopped unexpectedly. You can resume it from where it left off.', 'beepbeep-ai-alt-text-generator' )
			);

			return $this->describe( $state );
		}

		// Sampled, not per batch: telemetry cost must not grow with library size.
		if ( Audit_Telemetry_Service::should_report_batch( (int) $state['batch_number'] ) ) {
			Audit_Telemetry_Service::emit(
				Audit_Telemetry_Service::BATCH_COMPLETED,
				array(
					'batch_number'     => (int) $state['batch_number'],
					'batch_size'       => Image_Discovery_Service::batch_size( null ),
					'scan_size_bucket' => Audit_Telemetry_Service::size_bucket( (int) $state['total_images'] ),
				),
				$state['run_id'] . ':' . $state['batch_number']
			);
		}

		if ( $this->processor->is_finished( $state ) ) {
			return $this->finish( $state );
		}

		Audit_Progress_Service::refresh_lock( (string) $state['run_id'] );

		return $this->describe( Audit_Progress_Service::save_state( $state ) );
	}

	/**
	 * Cancel the current run, keeping whatever has already been found.
	 *
	 * @return array<string,mixed>
	 */
	public function cancel() {
		$state = Audit_Progress_Service::get_state();

		if ( ! Audit_Progress_Service::is_running( $state ) ) {
			return $this->describe( $state );
		}

		Audit_Telemetry_Service::emit(
			Audit_Telemetry_Service::CANCELLED,
			array(
				'batch_number'     => (int) $state['batch_number'],
				'percent_bucket'   => Audit_Telemetry_Service::percent_bucket( Audit_Progress_Service::percent( $state ) ),
				'scan_size_bucket' => Audit_Telemetry_Service::size_bucket( (int) $state['total_images'] ),
			),
			(string) $state['run_id']
		);

		$state = Audit_Progress_Service::cancel_run( $state );

		// Partial findings are still worth showing, so refresh the summary.
		Audit_Progress_Service::save_summary( $this->build_summary( $state ) );

		return $this->describe( $state );
	}

	/**
	 * Re-audit one image and update its stored finding.
	 *
	 * Called after alt text changes so the table and the score reflect the fix
	 * without needing a whole new site scan.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function rescan_attachment( $attachment_id ) {
		$attachment_id = (int) $attachment_id;

		if ( $attachment_id <= 0 ) {
			return new \WP_Error(
				'bbai_audit_invalid_attachment',
				__( 'That image could not be found.', 'beepbeep-ai-alt-text-generator' ),
				array( 'status' => 400 )
			);
		}

		if ( ! Image_Audit_Repository::table_exists() ) {
			return new \WP_Error(
				'bbai_audit_no_table',
				__( 'The audit storage table is missing.', 'beepbeep-ai-alt-text-generator' ),
				array( 'status' => 500 )
			);
		}

		$rows = $this->discovery->get_batch_rows( $attachment_id + 1, 1 );
		$row  = null;

		foreach ( $rows as $candidate ) {
			if ( (int) $candidate['ID'] === $attachment_id ) {
				$row = $candidate;
				break;
			}
		}

		if ( null === $row ) {
			// The image is gone; drop its finding rather than leaving a ghost row.
			Image_Audit_Repository::delete_row( $attachment_id );

			return new \WP_Error(
				'bbai_audit_attachment_missing',
				__( 'That image is no longer in the Media Library.', 'beepbeep-ai-alt-text-generator' ),
				array( 'status' => 404 )
			);
		}

		$duplicates = Audit_Progress_Service::get_duplicate_map();
		$usage      = $this->discovery->resolve_usage( array( $attachment_id ) );
		$context    = $this->discovery->build_context( $row, $usage, $duplicates['map'] );

		$previous = Image_Audit_Repository::get_row( $attachment_id );
		$registry = new Audit_Rule_Registry();
		$result   = $registry->evaluate( $context );

		Image_Audit_Repository::upsert_many(
			array( Image_Audit_Repository::build_record( $context, $result, (string) Audit_Progress_Service::get_state()['run_id'] ) )
		);

		// Only report a resolution when something was actually outstanding before.
		$had_issues = null !== $previous && '' !== (string) $previous['issue_codes'];

		if ( $had_issues && empty( $result['codes'] ) ) {
			$previous_codes = Audit_Issue::decode_codes( (string) $previous['issue_codes'] );

			Audit_Telemetry_Service::emit(
				Audit_Telemetry_Service::ISSUE_RESOLVED,
				array(
					'issue_type' => empty( $previous_codes ) ? 'none' : $previous_codes[0],
					'resolution' => $context->is_decorative() ? 'decorative' : 'generated',
				),
				(string) $attachment_id
			);
		}

		$this->refresh_summary();

		return array(
			'attachment_id' => $attachment_id,
			'issue_codes'   => $result['codes'],
			'severity'      => $result['severity'],
			'resolved'      => empty( $result['codes'] ),
		);
	}

	/**
	 * Record a decorative decision and re-audit the image.
	 *
	 * @param int  $attachment_id Attachment ID.
	 * @param bool $decorative    Desired state.
	 * @param int  $user_id       Who decided.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function set_decorative( $attachment_id, $decorative, $user_id = 0 ) {
		$attachment_id = (int) $attachment_id;
		$decorative    = (bool) $decorative;

		if ( $attachment_id <= 0 ) {
			return new \WP_Error(
				'bbai_audit_invalid_attachment',
				__( 'That image could not be found.', 'beepbeep-ai-alt-text-generator' ),
				array( 'status' => 400 )
			);
		}

		Decorative_Image_Service::set( $attachment_id, $decorative, $user_id );

		// Re-audit so the row, the counts and the score all agree immediately.
		$result = $this->rescan_attachment( $attachment_id );

		Audit_Telemetry_Service::emit(
			Audit_Telemetry_Service::MARKED_DECORATIVE,
			array( 'reversed' => ! $decorative ),
			(string) $attachment_id
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array_merge(
			$result,
			array(
				'decorative' => $decorative,
				'status'     => $decorative
					? Image_Audit_Repository::STATUS_DECORATIVE
					: Image_Audit_Repository::STATUS_OPEN,
			)
		);
	}

	/**
	 * Dismiss a finding without changing the image.
	 *
	 * For cases the rules cannot judge — an image the administrator has looked
	 * at and decided is fine as it stands.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function dismiss( $attachment_id ) {
		$attachment_id = (int) $attachment_id;

		if ( $attachment_id <= 0 ) {
			return new \WP_Error(
				'bbai_audit_invalid_attachment',
				__( 'That image could not be found.', 'beepbeep-ai-alt-text-generator' ),
				array( 'status' => 400 )
			);
		}

		$row = Image_Audit_Repository::get_row( $attachment_id );

		if ( null === $row ) {
			return new \WP_Error(
				'bbai_audit_no_finding',
				__( 'There is no audit finding for that image.', 'beepbeep-ai-alt-text-generator' ),
				array( 'status' => 404 )
			);
		}

		if ( ! Image_Audit_Repository::set_status( $attachment_id, Image_Audit_Repository::STATUS_DISMISSED ) ) {
			return new \WP_Error(
				'bbai_audit_dismiss_failed',
				__( 'That finding could not be dismissed. Please try again.', 'beepbeep-ai-alt-text-generator' ),
				array( 'status' => 500 )
			);
		}

		$codes = Audit_Issue::decode_codes( (string) $row['issue_codes'] );

		Audit_Telemetry_Service::emit(
			Audit_Telemetry_Service::ISSUE_RESOLVED,
			array(
				'issue_type' => empty( $codes ) ? 'none' : $codes[0],
				'resolution' => 'dismissed',
			),
			(string) $attachment_id
		);

		$this->refresh_summary();

		return array(
			'attachment_id' => $attachment_id,
			'status'        => Image_Audit_Repository::STATUS_DISMISSED,
			'resolved'      => true,
		);
	}

	/**
	 * The stored summary, or a zeroed one when nothing has been scanned.
	 *
	 * @return array<string,mixed>
	 */
	public function get_summary() {
		$summary = Audit_Progress_Service::get_summary();

		if ( null !== $summary ) {
			return $summary;
		}

		return $this->build_summary( Audit_Progress_Service::get_state() );
	}

	/**
	 * Recompute and store the summary from the audit table.
	 *
	 * @return array<string,mixed>
	 */
	public function refresh_summary() {
		$summary = $this->build_summary( Audit_Progress_Service::get_state() );

		Audit_Progress_Service::save_summary( $summary );

		return $summary;
	}

	/**
	 * Finish a run: recompute the authoritative summary and release the lock.
	 *
	 * @param array<string,mixed> $state Run state.
	 * @return array<string,mixed>
	 */
	private function finish( array $state ) {
		$started_at = max( 0, (int) $state['started_at'] );
		$state      = Audit_Progress_Service::complete_run( $state );
		$summary    = $this->build_summary( $state );

		Audit_Progress_Service::save_summary( $summary );

		Audit_Telemetry_Service::emit(
			Audit_Telemetry_Service::COMPLETED,
			array(
				'total_images_bucket'   => Audit_Telemetry_Service::size_bucket( (int) $summary['total_images'] ),
				'issue_count_bucket'    => Audit_Telemetry_Service::issue_bucket( (int) $summary['images_with_issues'] ),
				'score_bucket'          => Audit_Telemetry_Service::score_bucket( (int) $summary['score'] ),
				'audit_duration_bucket' => Audit_Telemetry_Service::duration_bucket(
					$started_at > 0 ? time() - $started_at : 0
				),
				'error_count_bucket'    => Audit_Telemetry_Service::issue_bucket( (int) $state['errors'] ),
			),
			(string) $state['run_id']
		);

		return $this->describe( $state );
	}

	/**
	 * Build the summary payload from stored findings.
	 *
	 * Counters come from the table rather than the run state, so a resumed or
	 * partially cancelled run reports what is actually stored.
	 *
	 * @param array<string,mixed> $state Run state.
	 * @return array<string,mixed>
	 */
	private function build_summary( array $state ) {
		$counters = Image_Audit_Repository::get_counters();

		$score = Image_Accessibility_Score_Service::calculate(
			array(
				'total_images'   => max( (int) $state['total_images'], (int) $counters['scanned_images'] ),
				'scanned_images' => (int) $counters['scanned_images'],
				'critical'       => (int) $counters['critical'],
				'warning'        => (int) $counters['warning'],
				'review'         => (int) $counters['review'],
				'information'    => (int) $counters['information'],
				'settled'        => (int) $counters['settled'],
			)
		);

		return array_merge(
			$score,
			array(
				'missing_alt_count'   => Image_Audit_Repository::count_by_issue( Audit_Issue::MISSING_ALT ),
				'duplicate_alt_count' => Image_Audit_Repository::count_by_issue( Audit_Issue::DUPLICATE_ALT ),
				'empty_alt_count'     => Image_Audit_Repository::count_by_issue( Audit_Issue::EMPTY_ALT_REVIEW_REQUIRED ),
				'weak_alt_count'      => $this->count_weak_alt(),
				'duplicate_truncated' => ! empty( $state['duplicate_truncated'] ),
				'run_status'          => (string) $state['status'],
				'last_completed_at'   => Audit_Progress_Service::last_completed_at(),
				'generated_at'        => time(),
			)
		);
	}

	/**
	 * Images whose alt text exists but reads poorly.
	 *
	 * Counts distinct images: one image carrying both `filename_as_alt` and
	 * `alt_too_short` is one image with weak alt text, not two.
	 *
	 * @return int
	 */
	private function count_weak_alt() {
		return Image_Audit_Repository::count_by_any_issue( Audit_Filters::weak_alt_codes() );
	}

	/**
	 * Shape run state for the admin layer.
	 *
	 * @param array<string,mixed> $state Run state.
	 * @return array<string,mixed>
	 */
	public function describe( array $state ) {
		return array(
			'run_id'              => (string) $state['run_id'],
			'status'              => (string) $state['status'],
			'done'                => in_array( $state['status'], Audit_Progress_Service::terminal_statuses(), true ),
			'can_resume'          => Audit_Progress_Service::can_resume( $state ),
			'is_stale'            => Audit_Progress_Service::is_stale( $state ),
			'percent'             => Audit_Progress_Service::percent( $state ),
			'total_images'        => (int) $state['total_images'],
			'processed_images'    => (int) $state['processed_images'],
			'batch_number'        => (int) $state['batch_number'],
			'errors'              => (int) $state['errors'],
			'counters'            => $state['counters'],
			'duplicate_truncated' => ! empty( $state['duplicate_truncated'] ),
			'started_at'          => (int) $state['started_at'],
			'completed_at'        => (int) $state['completed_at'],
			'last_completed_at'   => Audit_Progress_Service::last_completed_at(),
			'message'             => (string) $state['message'],
		);
	}

	/**
	 * A unique identifier for a run.
	 *
	 * @return string
	 */
	private function generate_run_id() {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}

		return uniqid( 'bbai_audit_', true );
	}
}
