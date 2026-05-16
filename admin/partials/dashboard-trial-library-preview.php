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

$bbai_trial_src       = isset( $bbai_product_state_model['trial'] ) && is_array( $bbai_product_state_model['trial'] )
	? $bbai_product_state_model['trial']
	: array();
$bbai_trial_remaining = max( 0, (int) ( $bbai_trial_src['remaining'] ?? 0 ) );
$bbai_trial_exhausted = ! empty( $bbai_trial_src['exhausted'] ) || $bbai_trial_remaining <= 0;

$bbai_state_m                  = (int) ( $bbai_state_missing_count ?? 0 );
$bbai_state_w                  = (int) ( $bbai_state_weak_count ?? 0 );
$bbai_guest_preview_actionable = max( $bbai_state_m, $bbai_state_w, $bbai_state_m + $bbai_state_w );

$bbai_trial_preview_rows              = ( isset( $this ) && is_object( $this ) && method_exists( $this, 'get_trial_dashboard_preview_rows' ) )
	? $this->get_trial_dashboard_preview_rows( 3 )
	: array();
$bbai_trial_preview_rows              = is_array( $bbai_trial_preview_rows ) ? $bbai_trial_preview_rows : array();
$bbai_trial_preview_total             = max( 0, (int) ( $bbai_state_total_images ?? count( $bbai_trial_preview_rows ) ) );
$bbai_trial_preview_extra             = max( 0, $bbai_trial_preview_total - count( $bbai_trial_preview_rows ) );
$bbai_trial_preview_generation_locked = true;

// Locked preview copy: trial-complete variant is conversion-first and non-repetitive.
$bbai_locked_preview_overlay_copy = $bbai_trial_exhausted
	? __( 'Create a free account to review, edit, and optimise all your images in one place.', 'beepbeep-ai-alt-text-generator' )
	: __( 'Create a free account to review and fix all remaining images.', 'beepbeep-ai-alt-text-generator' );
$bbai_locked_preview_context_line = '';
$bbai_locked_preview_waiting_line = $bbai_guest_preview_actionable > 0
	? sprintf(
		/* translators: %s: number of images requiring attention. */
		_n( '%s image needs ALT text', '%s images need ALT text', $bbai_guest_preview_actionable, 'beepbeep-ai-alt-text-generator' ),
		number_format_i18n( $bbai_guest_preview_actionable )
	)
	: __( 'ALT Library preview (locked)', 'beepbeep-ai-alt-text-generator' );
$bbai_locked_preview_monthly_free = max( 0, (int) ( $bbai_free_plan_offer ?? 50 ) );

$bbai_trial_lib_card_row_path = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/components/library-dashboard-card-row.php';
$bbai_trial_locked_overlay    = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-trial-locked-overlay-card.php';
?>
<section
	class="bbai-dashboard-trial-preview<?php echo $bbai_trial_exhausted ? ' bbai-dashboard-trial-preview--guest-locked bbai-dashboard-trial-preview--exhausted' : ''; ?>"
	id="library"
	data-bbai-trial-preview="1"
	data-bbai-trial-preview-limit="3"
	aria-labelledby="bbai-dashboard-locked-outer-heading"
>
	<h2 id="bbai-dashboard-locked-outer-heading" class="screen-reader-text">
		<?php esc_html_e( 'ALT Library preview', 'beepbeep-ai-alt-text-generator' ); ?>
	</h2>

	<?php if ( $bbai_trial_exhausted ) : ?>
		<div class="bbai-dashboard-trial-preview__header" aria-hidden="true">
			<div class="bbai-dashboard-trial-preview__copy">
				<h3 class="bbai-dashboard-trial-preview__title"><?php esc_html_e( 'Your final image', 'beepbeep-ai-alt-text-generator' ); ?></h3>
			</div>
		</div>
	<?php endif; ?>

	<div class="bbai-guest-preview__header" aria-hidden="true">
		<p class="bbai-guest-preview__header-title"><?php esc_html_e( 'ALT Library', 'beepbeep-ai-alt-text-generator' ); ?></p>
		<span class="bbai-guest-preview__lock-badge">
			<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
			<?php esc_html_e( 'Locked', 'beepbeep-ai-alt-text-generator' ); ?>
		</span>
	</div>
	<div class="bbai-dashboard-locked-preview__shell bbai-dashboard-trial-preview__shell">
		<?php if ( $bbai_trial_exhausted ) : ?>
		<div class="bbai-dashboard-trial-preview__blur-wrap" aria-hidden="true" inert>
		<?php endif; ?>
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
					<div class="bbai-dashboard-locked-preview__row" aria-hidden="true">
						<div class="bbai-dashboard-locked-preview__thumb"></div>
						<div class="bbai-dashboard-locked-preview__content">
							<div class="bbai-dashboard-locked-preview__meta">
								<span class="bbai-dashboard-locked-preview__filename" style="width:48%"></span>
								<span class="bbai-dashboard-locked-preview__badge bbai-dashboard-locked-preview__badge--missing"><?php esc_html_e( 'Missing', 'beepbeep-ai-alt-text-generator' ); ?></span>
							</div>
							<div class="bbai-dashboard-locked-preview__alt-lines">
								<span class="bbai-dashboard-locked-preview__line" style="width:86%"></span>
								<span class="bbai-dashboard-locked-preview__line" style="width:64%"></span>
							</div>
						</div>
					</div>
					<div class="bbai-dashboard-locked-preview__row" aria-hidden="true">
						<div class="bbai-dashboard-locked-preview__thumb"></div>
						<div class="bbai-dashboard-locked-preview__content">
							<div class="bbai-dashboard-locked-preview__meta">
								<span class="bbai-dashboard-locked-preview__filename" style="width:55%"></span>
								<span class="bbai-dashboard-locked-preview__badge bbai-dashboard-locked-preview__badge--review"><?php esc_html_e( 'Review', 'beepbeep-ai-alt-text-generator' ); ?></span>
							</div>
							<div class="bbai-dashboard-locked-preview__alt-lines">
								<span class="bbai-dashboard-locked-preview__alt-snippet"><?php esc_html_e( 'A professional product display showing the new collection against a clean white background', 'beepbeep-ai-alt-text-generator' ); ?></span>
							</div>
							<span class="bbai-dashboard-locked-preview__ai-label">
								<svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
								<?php esc_html_e( 'AI suggestion', 'beepbeep-ai-alt-text-generator' ); ?>
							</span>
						</div>
					</div>
					<div class="bbai-dashboard-locked-preview__row" aria-hidden="true">
						<div class="bbai-dashboard-locked-preview__thumb"></div>
						<div class="bbai-dashboard-locked-preview__content">
							<div class="bbai-dashboard-locked-preview__meta">
								<span class="bbai-dashboard-locked-preview__filename" style="width:40%"></span>
								<span class="bbai-dashboard-locked-preview__badge bbai-dashboard-locked-preview__badge--optimized"><?php esc_html_e( 'Done', 'beepbeep-ai-alt-text-generator' ); ?></span>
							</div>
							<div class="bbai-dashboard-locked-preview__alt-lines">
								<span class="bbai-dashboard-locked-preview__alt-snippet"><?php esc_html_e( 'Three colleagues collaborating at a bright modern office desk, reviewing documents together', 'beepbeep-ai-alt-text-generator' ); ?></span>
							</div>
							<span class="bbai-dashboard-locked-preview__ai-label">
								<svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
								<?php esc_html_e( 'AI suggestion', 'beepbeep-ai-alt-text-generator' ); ?>
							</span>
						</div>
					</div>
				</div>
			<?php endif; ?>
		<?php if ( $bbai_trial_exhausted ) : ?>
		</div>
		<?php endif; ?>

	<div class="bbai-guest-preview__fade" aria-hidden="true"></div>

		<p class="bbai-dashboard-trial-preview__waiting-label" aria-hidden="true">
			<?php echo esc_html( $bbai_locked_preview_waiting_line ); ?>
		</p>

		<?php if ( $bbai_trial_exhausted ) : ?>
		<div class="bbai-dashboard-locked-preview__fade" aria-hidden="true"></div>
		<?php if ( is_readable( $bbai_trial_locked_overlay ) ) : ?>
			<?php require $bbai_trial_locked_overlay; ?>
		<?php endif; ?>
		<?php endif; ?>
	</div>

	<p class="bbai-dashboard-trial-preview__empty" data-bbai-trial-preview-empty hidden></p>
</section>
<?php if ( ! $bbai_trial_exhausted ) : ?>
<div class="bbai-guest-reassurance" aria-label="<?php esc_attr_e( 'Reassurance', 'beepbeep-ai-alt-text-generator' ); ?>">
	<span class="bbai-guest-reassurance__item"><?php esc_html_e( 'AI suggestions are always reviewable before publishing', 'beepbeep-ai-alt-text-generator' ); ?></span>
	<span class="bbai-guest-reassurance__dot" aria-hidden="true"></span>
	<span class="bbai-guest-reassurance__item"><?php esc_html_e( 'Your original ALT text is never overwritten automatically', 'beepbeep-ai-alt-text-generator' ); ?></span>
	<span class="bbai-guest-reassurance__dot" aria-hidden="true"></span>
	<span class="bbai-guest-reassurance__item"><?php esc_html_e( 'Works with your existing WordPress media library', 'beepbeep-ai-alt-text-generator' ); ?></span>
</div>
<?php endif; ?>
