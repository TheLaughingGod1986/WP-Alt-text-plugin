<?php
/**
 * Guest: library preview with table layout (conversion-first).
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

$bbai_locked_preview_overlay_copy = $bbai_trial_exhausted
	? __( 'Create a free account to review, edit, and optimise all your images in one place.', 'beepbeep-ai-alt-text-generator' )
	: __( 'Create a free account to review and fix all remaining images.', 'beepbeep-ai-alt-text-generator' );
$bbai_locked_preview_context_line = '';
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
		<div class="bbai-guest-preview__header-left">
			<p class="bbai-guest-preview__header-title"><?php esc_html_e( 'ALT Library preview', 'beepbeep-ai-alt-text-generator' ); ?></p>
			<span class="bbai-guest-preview__lock-badge">
				<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
				<?php esc_html_e( 'Locked', 'beepbeep-ai-alt-text-generator' ); ?>
			</span>
		</div>
		<?php if ( ! $bbai_trial_exhausted ) : ?>
		<p class="bbai-guest-preview__header-sub"><?php esc_html_e( 'See a preview of your images and AI suggestions. Create an account to unlock the full library.', 'beepbeep-ai-alt-text-generator' ); ?></p>
		<?php endif; ?>
	</div>

	<div class="bbai-dashboard-locked-preview__shell bbai-dashboard-trial-preview__shell">
		<?php if ( $bbai_trial_exhausted ) : ?>
		<div class="bbai-dashboard-trial-preview__blur-wrap" aria-hidden="true" inert>
		<?php endif; ?>

		<div class="bbai-gpl-table" role="presentation">
			<div class="bbai-gpl-header-row" aria-hidden="true">
				<div class="bbai-gpl-col-thumb"></div>
				<div class="bbai-gpl-col-file"></div>
				<div class="bbai-gpl-col-suggestion"><?php esc_html_e( 'AI suggestion preview', 'beepbeep-ai-alt-text-generator' ); ?></div>
				<div class="bbai-gpl-col-status"><?php esc_html_e( 'Status', 'beepbeep-ai-alt-text-generator' ); ?></div>
				<div class="bbai-gpl-col-action"></div>
			</div>

			<?php if ( ! empty( $bbai_trial_preview_rows ) && is_readable( $bbai_trial_lib_card_row_path ) ) : ?>
				<?php foreach ( $bbai_trial_preview_rows as $bbai_preview_row ) : ?>
					<?php
					if ( ! is_array( $bbai_preview_row ) ) {
						continue;
					}
					$bbai_dash_card_mode = 'trial';
					require $bbai_trial_lib_card_row_path;
					?>
				<?php endforeach; ?>
			<?php else : ?>
			<div class="bbai-gpl-row" aria-hidden="true">
				<div class="bbai-gpl-col-thumb"><div class="bbai-gpl-thumb bbai-gpl-thumb--lake"></div></div>
				<div class="bbai-gpl-col-file">
					<span class="bbai-gpl-filename">mountain-lake.jpg</span>
					<span class="bbai-gpl-date"><?php esc_html_e( 'Uploaded 2 days ago', 'beepbeep-ai-alt-text-generator' ); ?></span>
					<span class="bbai-gpl-badge bbai-gpl-badge--missing"><?php esc_html_e( 'Missing', 'beepbeep-ai-alt-text-generator' ); ?></span>
				</div>
				<div class="bbai-gpl-col-suggestion">
					<span class="bbai-gpl-suggestion-text">&ldquo;<?php esc_html_e( 'Snowy mountain lake surrounded by tall pine trees under a clear blue sky.', 'beepbeep-ai-alt-text-generator' ); ?>&rdquo;</span>
				</div>
				<div class="bbai-gpl-col-status">
					<span class="bbai-gpl-status-badge bbai-gpl-status-badge--missing">
						<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
						<?php esc_html_e( 'Missing ALT', 'beepbeep-ai-alt-text-generator' ); ?>
					</span>
				</div>
				<div class="bbai-gpl-col-action">
					<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
					<?php esc_html_e( 'Sign up to view and fix', 'beepbeep-ai-alt-text-generator' ); ?>
				</div>
			</div>
			<div class="bbai-gpl-row" aria-hidden="true">
				<div class="bbai-gpl-col-thumb"><div class="bbai-gpl-thumb bbai-gpl-thumb--coffee"></div></div>
				<div class="bbai-gpl-col-file">
					<span class="bbai-gpl-filename">coffee-shop.jpg</span>
					<span class="bbai-gpl-date"><?php esc_html_e( 'Uploaded 3 days ago', 'beepbeep-ai-alt-text-generator' ); ?></span>
					<span class="bbai-gpl-badge bbai-gpl-badge--review"><?php esc_html_e( 'Review', 'beepbeep-ai-alt-text-generator' ); ?></span>
				</div>
				<div class="bbai-gpl-col-suggestion">
					<span class="bbai-gpl-suggestion-text">&ldquo;<?php esc_html_e( 'Cozy coffee shop interior with wooden tables, warm lights, and plants.', 'beepbeep-ai-alt-text-generator' ); ?>&rdquo;</span>
				</div>
				<div class="bbai-gpl-col-status">
					<span class="bbai-gpl-status-badge bbai-gpl-status-badge--review">
						<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
						<?php esc_html_e( 'Needs review', 'beepbeep-ai-alt-text-generator' ); ?>
					</span>
				</div>
				<div class="bbai-gpl-col-action">
					<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
					<?php esc_html_e( 'Sign up to view and fix', 'beepbeep-ai-alt-text-generator' ); ?>
				</div>
			</div>
			<div class="bbai-gpl-row" aria-hidden="true">
				<div class="bbai-gpl-col-thumb"><div class="bbai-gpl-thumb bbai-gpl-thumb--sunset"></div></div>
				<div class="bbai-gpl-col-file">
					<span class="bbai-gpl-filename">sunset-beach.jpg</span>
					<span class="bbai-gpl-date"><?php esc_html_e( 'Uploaded 5 days ago', 'beepbeep-ai-alt-text-generator' ); ?></span>
					<span class="bbai-gpl-badge bbai-gpl-badge--optimized"><?php esc_html_e( 'Optimized', 'beepbeep-ai-alt-text-generator' ); ?></span>
				</div>
				<div class="bbai-gpl-col-suggestion">
					<span class="bbai-gpl-suggestion-text">&ldquo;<?php esc_html_e( 'Sunset over the ocean with waves rolling onto a sandy beach.', 'beepbeep-ai-alt-text-generator' ); ?>&rdquo;</span>
				</div>
				<div class="bbai-gpl-col-status">
					<span class="bbai-gpl-status-badge bbai-gpl-status-badge--optimized">
						<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
						<?php esc_html_e( 'Has ALT', 'beepbeep-ai-alt-text-generator' ); ?>
					</span>
				</div>
				<div class="bbai-gpl-col-action">
					<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
					<?php esc_html_e( 'Sign up to view and fix', 'beepbeep-ai-alt-text-generator' ); ?>
				</div>
			</div>
			<?php endif; ?>

			<?php if ( ! $bbai_trial_exhausted ) : ?>
			<div class="bbai-gpl-footer">
				<span class="bbai-gpl-footer__text">
					<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
					<?php esc_html_e( 'Unlock full access to all images, AI suggestions, and bulk tools.', 'beepbeep-ai-alt-text-generator' ); ?>
				</span>
				<a
					href="#"
					class="bbai-btn bbai-gpl-footer__cta"
					data-action="show-dashboard-auth"
					data-auth-tab="register"
					data-bbai-modal-context="library_footer"
					data-bbai-analytics-upgrade="library_footer_create_account"
				><?php esc_html_e( 'Create free account', 'beepbeep-ai-alt-text-generator' ); ?></a>
			</div>
			<?php endif; ?>
		</div>

		<?php if ( $bbai_trial_exhausted ) : ?>
		<div class="bbai-dashboard-locked-preview__fade" aria-hidden="true"></div>
		<?php if ( is_readable( $bbai_trial_locked_overlay ) ) : ?>
			<?php require $bbai_trial_locked_overlay; ?>
		<?php endif; ?>
		</div>
		<?php endif; ?>
	</div>

	<p class="bbai-dashboard-trial-preview__empty" data-bbai-trial-preview-empty hidden></p>
</section>

<?php if ( ! $bbai_trial_exhausted ) : ?>
<div class="bbai-guest-reassurance bbai-guest-reassurance--grid" aria-label="<?php esc_attr_e( 'Why you can trust BeepBeep AI', 'beepbeep-ai-alt-text-generator' ); ?>">
	<div class="bbai-guest-reassurance__card">
		<span class="bbai-guest-reassurance__icon" aria-hidden="true">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
		</span>
		<strong class="bbai-guest-reassurance__title"><?php esc_html_e( 'Safe & Secure', 'beepbeep-ai-alt-text-generator' ); ?></strong>
		<span class="bbai-guest-reassurance__desc"><?php esc_html_e( 'Your original ALT text is never overwritten automatically.', 'beepbeep-ai-alt-text-generator' ); ?></span>
	</div>
	<div class="bbai-guest-reassurance__card">
		<span class="bbai-guest-reassurance__icon" aria-hidden="true">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
		</span>
		<strong class="bbai-guest-reassurance__title"><?php esc_html_e( 'Review first', 'beepbeep-ai-alt-text-generator' ); ?></strong>
		<span class="bbai-guest-reassurance__desc"><?php esc_html_e( 'All AI suggestions require your approval before saving.', 'beepbeep-ai-alt-text-generator' ); ?></span>
	</div>
	<div class="bbai-guest-reassurance__card">
		<span class="bbai-guest-reassurance__icon" aria-hidden="true">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
		</span>
		<strong class="bbai-guest-reassurance__title"><?php esc_html_e( 'Works with WordPress', 'beepbeep-ai-alt-text-generator' ); ?></strong>
		<span class="bbai-guest-reassurance__desc"><?php esc_html_e( 'Seamlessly integrated with your existing media library.', 'beepbeep-ai-alt-text-generator' ); ?></span>
	</div>
	<div class="bbai-guest-reassurance__card">
		<span class="bbai-guest-reassurance__icon" aria-hidden="true">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
		</span>
		<strong class="bbai-guest-reassurance__title"><?php esc_html_e( 'Privacy focused', 'beepbeep-ai-alt-text-generator' ); ?></strong>
		<span class="bbai-guest-reassurance__desc"><?php esc_html_e( 'We don\'t use your content to train public models.', 'beepbeep-ai-alt-text-generator' ); ?></span>
	</div>
</div>
<?php endif; ?>
