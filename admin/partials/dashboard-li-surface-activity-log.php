<?php
/**
 * Logged-in surface: ActivityLog (QUEUED / PROCESSING states).
 *
 * Renders a live feed shell. The JS controller populates entries
 * via data-bbai-li-activity-log while polling is active.
 *
 * Required in parent scope:
 *   $bbai_li_state  array  The full DashboardState object.
 *
 * @package BeepBeep_AI
 * @since   5.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bbai_li_props      = $bbai_li_state['surface']['props'] ?? [];
$bbai_li_state_id   = (string) ( $bbai_li_state['state'] ?? 'PROCESSING' );
$bbai_li_job_done   = (int) ( $bbai_li_props['done'] ?? 0 );
$bbai_li_job_total  = max( 1, (int) ( $bbai_li_props['total'] ?? 1 ) );
$bbai_li_job_status = sanitize_key( (string) ( $bbai_li_props['job_status'] ?? 'running' ) );
$bbai_li_is_queued  = ( 'QUEUED' === $bbai_li_state_id || 'queued' === $bbai_li_job_status );
$bbai_li_job_pct    = min( 100, (int) round( ( $bbai_li_job_done / $bbai_li_job_total ) * 100 ) );

// Also pull from the donut which carries more precise job_pct when PROCESSING.
$bbai_li_donut_data  = $bbai_li_state['donut'] ?? [];
if ( ! $bbai_li_is_queued ) {
	$bbai_li_job_pct   = (int) ( $bbai_li_donut_data['job_pct'] ?? $bbai_li_job_pct );
	$bbai_li_job_done  = (int) ( $bbai_li_donut_data['job_done']  ?? $bbai_li_job_done );
	$bbai_li_job_total = max( 1, (int) ( $bbai_li_donut_data['job_total'] ?? $bbai_li_job_total ) );
} else {
	$bbai_li_job_done  = 0;
	$bbai_li_job_total = max( 1, (int) ( $bbai_li_donut_data['job_total'] ?? $bbai_li_job_total ) );
}

$bbai_li_surface_heading = __( 'Processing', 'beepbeep-ai-alt-text-generator' );
if ( $bbai_li_is_queued ) {
	$bbai_li_surface_heading = __( 'Queued', 'beepbeep-ai-alt-text-generator' );
} elseif ( 'paused' === $bbai_li_job_status ) {
	$bbai_li_surface_heading = __( 'Job paused', 'beepbeep-ai-alt-text-generator' );
}
?>

<div
	class="bbai-li-surface bbai-li-surface--activity-log"
	data-bbai-li-surface="ActivityLog"
	data-bbai-li-job-status="<?php echo esc_attr( $bbai_li_job_status ); ?>"
>

	<div class="bbai-li-surface__header">
		<div class="bbai-li-surface__header-text">
			<h3 class="bbai-li-surface__heading">
				<?php echo esc_html( $bbai_li_surface_heading ); ?>
			</h3>
			<p class="bbai-li-surface__subheading">
				<?php
				if ( $bbai_li_is_queued ) {
					echo esc_html( sprintf(
						/* translators: %s: queued image count */
						_n( '%s image queued for generation', '%s images queued for generation', $bbai_li_job_total, 'beepbeep-ai-alt-text-generator' ),
						number_format_i18n( $bbai_li_job_total )
					) );
				} else {
					echo esc_html( sprintf(
						/* translators: 1: done, 2: total */
						__( '%1$s of %2$s images processed', 'beepbeep-ai-alt-text-generator' ),
						number_format_i18n( $bbai_li_job_done ),
						number_format_i18n( $bbai_li_job_total )
					) );
				}
				?>
			</p>
		</div>
		<?php if ( ! $bbai_li_is_queued ) : ?>
		<span
			class="bbai-li-surface__progress-label"
			data-bbai-li-job-progress="1"
			aria-live="polite"
		><?php echo esc_html( $bbai_li_job_pct . '%' ); ?></span>
		<?php endif; ?>
	</div>

	<?php if ( ! $bbai_li_is_queued ) : ?>
	<?php /* Deterministic job progress bar */ ?>
	<div class="bbai-li-job-progress" data-bbai-li-job-progress-bar="1" aria-hidden="true">
		<div
			class="bbai-li-job-progress__fill"
			style="width: <?php echo esc_attr( (string) $bbai_li_job_pct ); ?>%;"
			data-bbai-li-job-pct="<?php echo esc_attr( (string) $bbai_li_job_pct ); ?>"
		></div>
	</div>
	<?php endif; ?>

	<?php /* JS controller writes <li> entries into this list */ ?>
	<ul
		class="bbai-li-feed"
		data-bbai-li-activity-log="1"
		aria-label="<?php echo esc_attr( $bbai_li_is_queued ? __( 'Queue status', 'beepbeep-ai-alt-text-generator' ) : __( 'Processing log', 'beepbeep-ai-alt-text-generator' ) ); ?>"
		aria-live="polite"
		role="log"
	>
		<li class="bbai-li-feed__empty" data-bbai-li-feed-empty="1">
			<?php echo esc_html( $bbai_li_is_queued ? __( 'No action needed. We\'ll start this queue automatically.', 'beepbeep-ai-alt-text-generator' ) : __( 'Starting…', 'beepbeep-ai-alt-text-generator' ) ); ?>
		</li>
	</ul>

</div>
