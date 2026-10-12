<?php
/**
 * Audit run controls and progress.
 *
 * Renders every state server-side so the page is useful before JavaScript runs
 * and remains usable if it fails; the controller then swaps states in place.
 *
 * $bbai_ui keys:
 * - state: describe() payload from Image_Audit_Service
 * - has_run: whether an audit has ever completed
 *
 * @package BeepBeep_AI
 * @since   4.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeepBeepAI\AltTextGenerator\Audit\Audit_Progress_Service;

$bbai_ui      = isset( $bbai_ui ) && is_array( $bbai_ui ) ? $bbai_ui : array();
$bbai_state   = isset( $bbai_ui['state'] ) && is_array( $bbai_ui['state'] ) ? $bbai_ui['state'] : array();
$bbai_has_run = ! empty( $bbai_ui['has_run'] );

$bbai_status     = (string) ( $bbai_state['status'] ?? Audit_Progress_Service::STATUS_NOT_STARTED );
$bbai_percent    = max( 0, min( 100, (int) ( $bbai_state['percent'] ?? 0 ) ) );
$bbai_processed  = max( 0, (int) ( $bbai_state['processed_images'] ?? 0 ) );
$bbai_total      = max( 0, (int) ( $bbai_state['total_images'] ?? 0 ) );
$bbai_errors     = max( 0, (int) ( $bbai_state['errors'] ?? 0 ) );
$bbai_can_resume = ! empty( $bbai_state['can_resume'] );
$bbai_is_stale   = ! empty( $bbai_state['is_stale'] );
$bbai_message    = trim( (string) ( $bbai_state['message'] ?? '' ) );

$bbai_is_running = in_array( $bbai_status, array( Audit_Progress_Service::STATUS_RUNNING, Audit_Progress_Service::STATUS_QUEUED ), true );

// A stale run is offered as resumable rather than shown as still working.
$bbai_show_progress = $bbai_is_running && ! $bbai_is_stale;
$bbai_show_resume   = $bbai_can_resume && ( $bbai_is_stale || Audit_Progress_Service::STATUS_FAILED === $bbai_status );
?>
<div
	class="bbai-audit-run bbai-dashboard-surface-card bbai-card"
	data-bbai-audit-run
	data-status="<?php echo esc_attr( $bbai_status ); ?>"
	data-run-id="<?php echo esc_attr( (string) ( $bbai_state['run_id'] ?? '' ) ); ?>"
>
	<div class="bbai-audit-run__actions">
		<button
			type="button"
			class="button button-primary bbai-audit-run__start"
			data-bbai-audit-action="start"
			<?php disabled( $bbai_show_progress ); ?>
		>
			<?php
			echo $bbai_has_run
				? esc_html__( 'Run image audit again', 'beepbeep-ai-alt-text-generator' )
				: esc_html__( 'Run image audit', 'beepbeep-ai-alt-text-generator' );
			?>
		</button>

		<button
			type="button"
			class="button bbai-audit-run__resume"
			data-bbai-audit-action="resume"
			<?php echo $bbai_show_resume ? '' : 'hidden'; ?>
		>
			<?php esc_html_e( 'Resume audit', 'beepbeep-ai-alt-text-generator' ); ?>
		</button>

		<button
			type="button"
			class="button bbai-audit-run__cancel"
			data-bbai-audit-action="cancel"
			<?php echo $bbai_show_progress ? '' : 'hidden'; ?>
		>
			<?php esc_html_e( 'Cancel', 'beepbeep-ai-alt-text-generator' ); ?>
		</button>
	</div>

	<div
		class="bbai-audit-run__progress"
		data-bbai-audit-progress
		<?php echo $bbai_show_progress ? '' : 'hidden'; ?>
	>
		<div
			class="bbai-audit-run__track"
			role="progressbar"
			aria-valuenow="<?php echo esc_attr( (string) $bbai_percent ); ?>"
			aria-valuemin="0"
			aria-valuemax="100"
			aria-label="<?php esc_attr_e( 'Image audit progress', 'beepbeep-ai-alt-text-generator' ); ?>"
			data-bbai-audit-bar
		>
			<span class="bbai-audit-run__fill" style="width: <?php echo esc_attr( (string) $bbai_percent ); ?>%;"></span>
		</div>

		<p class="bbai-audit-run__status" data-bbai-audit-status aria-live="polite">
			<?php
			printf(
				/* translators: 1: images scanned so far, 2: images in total, 3: percentage complete. */
				esc_html__( 'Scanned %1$s of %2$s images (%3$d%%).', 'beepbeep-ai-alt-text-generator' ),
				esc_html( number_format_i18n( $bbai_processed ) ),
				esc_html( number_format_i18n( $bbai_total ) ),
				(int) $bbai_percent
			);
			?>
		</p>
	</div>

	<p
		class="bbai-audit-run__notice bbai-audit-run__notice--error"
		data-bbai-audit-error
		role="alert"
		<?php echo '' !== $bbai_message ? '' : 'hidden'; ?>
	>
		<?php echo esc_html( $bbai_message ); ?>
	</p>

	<?php if ( $bbai_is_stale && $bbai_can_resume ) : ?>
		<p class="bbai-audit-run__notice bbai-audit-run__notice--warning">
			<?php esc_html_e( 'The last audit stopped before it finished — the browser tab was probably closed. Resume it to carry on from where it left off, or start a new one.', 'beepbeep-ai-alt-text-generator' ); ?>
		</p>
	<?php endif; ?>

	<?php if ( $bbai_errors > 0 ) : ?>
		<p class="bbai-audit-run__notice bbai-audit-run__notice--warning" data-bbai-audit-errors>
			<?php
			printf(
				/* translators: %s: number of images that could not be checked. */
				esc_html( _n( '%s image could not be checked and was skipped.', '%s images could not be checked and were skipped.', $bbai_errors, 'beepbeep-ai-alt-text-generator' ) ),
				esc_html( number_format_i18n( $bbai_errors ) )
			);
			?>
		</p>
	<?php endif; ?>

	<?php if ( ! empty( $bbai_state['duplicate_truncated'] ) ) : ?>
		<p class="bbai-audit-run__notice bbai-audit-run__notice--info">
			<?php esc_html_e( 'Duplicate detection covered the most repeated alt text values on this site, not every one.', 'beepbeep-ai-alt-text-generator' ); ?>
		</p>
	<?php endif; ?>

	<noscript>
		<p class="bbai-audit-run__notice bbai-audit-run__notice--info">
			<?php esc_html_e( 'Running an audit needs JavaScript, because the scan works through your images in batches.', 'beepbeep-ai-alt-text-generator' ); ?>
		</p>
	</noscript>
</div>
