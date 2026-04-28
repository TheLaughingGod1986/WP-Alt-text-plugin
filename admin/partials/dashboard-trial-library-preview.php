<?php
/**
 * Guest: blurred library preview + unlock overlay (conversion-first, not live review).
 *
 * @package BeepBeep_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! empty( $bbai_has_connected_account ) ) {
	return;
}

$bbai_state_m = (int) ( $bbai_state_missing_count ?? 0 );
$bbai_state_w = (int) ( $bbai_state_weak_count ?? 0 );
$bbai_guest_preview_actionable = max( $bbai_state_m, $bbai_state_w, $bbai_state_m + $bbai_state_w );

$bbai_trial_preview_rows = ( isset( $this ) && is_object( $this ) && method_exists( $this, 'get_trial_dashboard_preview_rows' ) )
	? $this->get_trial_dashboard_preview_rows( 3 )
	: [];
$bbai_trial_preview_rows  = is_array( $bbai_trial_preview_rows ) ? $bbai_trial_preview_rows : [];
$bbai_trial_preview_total  = max( 0, (int) ( $bbai_state_total_images ?? count( $bbai_trial_preview_rows ) ) );
$bbai_trial_preview_extra  = max( 0, $bbai_trial_preview_total - count( $bbai_trial_preview_rows ) );
$bbai_trial_preview_generation_locked = true;

$bbai_locked_preview_overlay_copy = __( 'Review, edit, and optimise every ALT result.', 'beepbeep-ai-alt-text-generator' );
$bbai_locked_preview_trust_line   = __( 'No credit card required', 'beepbeep-ai-alt-text-generator' );
$bbai_locked_preview_context_line = $bbai_guest_preview_actionable > 0
	? sprintf(
		/* translators: %s: number of images with remaining optimisation work. */
		__( 'Create a free account to finish %s remaining images.', 'beepbeep-ai-alt-text-generator' ),
		number_format_i18n( $bbai_guest_preview_actionable )
	)
	: '';

$bbai_trial_lib_card_row_path = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/components/library-dashboard-card-row.php';
$bbai_trial_locked_overlay    = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-trial-locked-overlay-card.php';
?>
<section
	class="bbai-dashboard-trial-preview bbai-dashboard-trial-preview--guest-locked"
	data-bbai-trial-preview="1"
	data-bbai-trial-preview-limit="3"
	aria-labelledby="bbai-dashboard-locked-outer-heading"
>
	<div class="bbai-dashboard-locked-preview__header">
		<h2 id="bbai-dashboard-locked-outer-heading" class="bbai-dashboard-locked-preview__title">
			<?php esc_html_e( 'Unlock your full ALT library', 'beepbeep-ai-alt-text-generator' ); ?>
		</h2>
		<p class="bbai-dashboard-locked-preview__description">
			<?php esc_html_e( 'Sign up to review, edit, and optimise ALT text across your library.', 'beepbeep-ai-alt-text-generator' ); ?>
		</p>
	</div>

	<div class="bbai-dashboard-locked-preview__shell bbai-dashboard-trial-preview__shell">
		<div class="bbai-dashboard-trial-preview__blur-wrap">
			<?php if ( ! empty( $bbai_trial_preview_rows ) && is_readable( $bbai_trial_lib_card_row_path ) ) : ?>
				<div class="bbai-dashboard-trial-preview__list" role="list" data-bbai-trial-preview-list>
					<?php foreach ( $bbai_trial_preview_rows as $bbai_preview_row ) : ?>
						<?php
						if ( ! is_array( $bbai_preview_row ) ) {
							continue;
						}
						$bbai_dash_card_mode = 'trial';
						require $bbai_trial_lib_card_row_path;
						?>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="bbai-dashboard-trial-preview__list" role="presentation" data-bbai-trial-preview-skeleton>
					<?php
					$bbai_skel_set = [
						[ 'cc' => 'bbai-dashboard-locked-preview__badge--missing', 'lb' => __( 'Missing', 'beepbeep-ai-alt-text-generator' ) ],
						[ 'cc' => 'bbai-dashboard-locked-preview__badge--review', 'lb' => __( 'Review', 'beepbeep-ai-alt-text-generator' ) ],
						[ 'cc' => 'bbai-dashboard-locked-preview__badge--optimized', 'lb' => __( 'OK', 'beepbeep-ai-alt-text-generator' ) ],
					];
					foreach ( $bbai_skel_set as $bbai_skel_row ) :
						?>
					<div class="bbai-dashboard-locked-preview__row" aria-hidden="true">
						<div class="bbai-dashboard-locked-preview__thumb" aria-hidden="true"></div>
						<div class="bbai-dashboard-locked-preview__content">
							<div class="bbai-dashboard-locked-preview__meta">
								<span class="bbai-dashboard-locked-preview__filename" aria-hidden="true"></span>
								<span class="bbai-dashboard-locked-preview__badge <?php echo esc_attr( (string) ( $bbai_skel_row['cc'] ?? '' ) ); ?>"><?php echo esc_html( (string) ( $bbai_skel_row['lb'] ?? '' ) ); ?></span>
							</div>
							<div class="bbai-dashboard-locked-preview__alt-lines" aria-hidden="true">
								<span class="bbai-dashboard-locked-preview__line"></span>
								<span class="bbai-dashboard-locked-preview__line" style="width:78%"></span>
							</div>
						</div>
					</div>
						<?php
					endforeach;
					?>
				</div>
			<?php endif; ?>
		</div>

		<div class="bbai-dashboard-locked-preview__fade" aria-hidden="true"></div>

		<?php if ( is_readable( $bbai_trial_locked_overlay ) ) : ?>
			<?php require $bbai_trial_locked_overlay; ?>
		<?php endif; ?>
	</div>

	<p class="bbai-dashboard-trial-preview__empty" data-bbai-trial-preview-empty hidden></p>
</section>
