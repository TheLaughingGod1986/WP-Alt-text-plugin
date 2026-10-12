<?php
/**
 * Processes one batch of images per call.
 *
 * Each batch costs a fixed number of queries — one discovery read, one usage
 * lookup, one multi-row upsert — regardless of how large the library is, and
 * the cursor only ever moves forward, so no image is audited twice in a run.
 *
 * @package BeepBeep_AI
 * @since   4.8.0
 */

namespace BeepBeepAI\AltTextGenerator\Audit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One batch of the audit.
 */
class Audit_Batch_Processor {

	/**
	 * Discovery service.
	 *
	 * @var Image_Discovery_Service
	 */
	private $discovery;

	/**
	 * Rule registry.
	 *
	 * @var Audit_Rule_Registry
	 */
	private $registry;

	/**
	 * Build the processor.
	 *
	 * @param Image_Discovery_Service|null $discovery Discovery service.
	 * @param Audit_Rule_Registry|null     $registry  Rule registry.
	 */
	public function __construct( $discovery = null, $registry = null ) {
		$this->discovery = $discovery instanceof Image_Discovery_Service ? $discovery : new Image_Discovery_Service();
		$this->registry  = $registry instanceof Audit_Rule_Registry ? $registry : new Audit_Rule_Registry();
	}

	/**
	 * Audit the next batch and return the updated run state.
	 *
	 * @param array<string,mixed> $state         Run state.
	 * @param array<string,int>   $duplicate_map Hash => group size.
	 * @return array<string,mixed>
	 */
	public function process_batch( array $state, array $duplicate_map = array() ) {
		if ( ! Audit_Progress_Service::is_running( $state ) ) {
			return $state;
		}

		$batch_size = Image_Discovery_Service::batch_size( null );
		$cursor     = max( 0, (int) $state['last_attachment_id'] );

		$contexts = $this->discovery->get_batch( $cursor, $batch_size, $duplicate_map );

		if ( empty( $contexts ) ) {
			// Nothing left to read: the run is done even if the projected total was
			// stale because images were deleted while the scan was in flight.
			$state['processed_images'] = max( (int) $state['processed_images'], (int) $state['total_images'] );

			return $state;
		}

		$records = array();

		foreach ( $contexts as $context ) {
			$attachment_id = $context->get_attachment_id();

			if ( $attachment_id <= 0 ) {
				continue;
			}

			// The cursor advances even for images that fail, so one bad row cannot
			// wedge the run into an endless loop over the same batch.
			$state['last_attachment_id'] = $attachment_id;
			++$state['processed_images'];

			try {
				$result    = $this->registry->evaluate( $context );
				$records[] = Image_Audit_Repository::build_record( $context, $result, (string) $state['run_id'] );

				$state = $this->tally( $state, $context, $result );
			} catch ( \Throwable $e ) {
				++$state['errors'];
				$this->log_image_failure( $attachment_id, $e );
			}
		}

		if ( ! empty( $records ) ) {
			Image_Audit_Repository::upsert_many( $records );
		}

		++$state['batch_number'];
		$state['processed_images'] = min( (int) $state['processed_images'], max( (int) $state['total_images'], (int) $state['processed_images'] ) );

		return $state;
	}

	/**
	 * Whether the run has reached the end of the library.
	 *
	 * @param array<string,mixed> $state Run state.
	 * @return bool
	 */
	public function is_finished( array $state ) {
		return (int) $state['processed_images'] >= (int) $state['total_images'];
	}

	/**
	 * Add one image's outcome to the live progress counters.
	 *
	 * These drive the progress display only. The authoritative figures are
	 * recomputed from the table when the run completes, so a resumed run cannot
	 * double-count.
	 *
	 * @param array<string,mixed> $state   Run state.
	 * @param Image_Audit_Context $context Image.
	 * @param array<string,mixed> $result  Evaluation result.
	 * @return array<string,mixed>
	 */
	private function tally( array $state, Image_Audit_Context $context, array $result ) {
		$codes = isset( $result['codes'] ) ? (array) $result['codes'] : array();

		if ( $context->is_decorative() ) {
			++$state['counters']['settled'];

			return $state;
		}

		if ( empty( $codes ) ) {
			++$state['counters']['passing'];

			return $state;
		}

		$severity = isset( $result['severity'] ) ? (string) $result['severity'] : Audit_Issue::SEVERITY_INFORMATION;

		if ( isset( $state['counters'][ $severity ] ) ) {
			++$state['counters'][ $severity ];
		}

		return $state;
	}

	/**
	 * Record a per-image failure without leaking anything sensitive.
	 *
	 * @param int        $attachment_id Attachment ID.
	 * @param \Throwable $error         Failure.
	 * @return void
	 */
	private function log_image_failure( $attachment_id, $error ) {
		if ( ! class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
			return;
		}

		\BeepBeepAI\AltTextGenerator\Debug_Log::log(
			'error',
			sprintf( 'Image audit failed for attachment %d.', (int) $attachment_id ),
			array( 'error_type' => get_class( $error ) ),
			'image_audit'
		);
	}
}
