<?php
/**
 * Single library review card row for dashboard trial preview and connected SSR.
 *
 * Expected scope:
 * - $bbai_preview_row      array  Row payload from Core (trial preview shape).
 * - $bbai_dash_card_mode   string 'trial' or 'connected'.
 * Trial mode also uses: $bbai_trial_preview_generation_locked, $bbai_state_missing_count (optional).
 *
 * @package BeepBeep_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $bbai_preview_row ) || ! is_array( $bbai_preview_row ) ) {
	return;
}

$bbai_dash_card_mode = isset( $bbai_dash_card_mode ) ? (string) $bbai_dash_card_mode : 'trial';
if ( ! in_array( $bbai_dash_card_mode, [ 'trial', 'connected' ], true ) ) {
	$bbai_dash_card_mode = 'trial';
}

$bbai_attachment_id = isset( $bbai_preview_row['attachment_id'] ) ? (int) $bbai_preview_row['attachment_id'] : 0;
if ( $bbai_attachment_id <= 0 ) {
	return;
}

$bbai_status = isset( $bbai_preview_row['status'] ) ? sanitize_key( (string) $bbai_preview_row['status'] ) : 'missing';
$bbai_status_label = isset( $bbai_preview_row['status_label'] ) ? (string) $bbai_preview_row['status_label'] : __( 'Missing', 'beepbeep-ai-alt-text-generator' );
$bbai_quality_class = isset( $bbai_preview_row['quality_class'] ) ? sanitize_html_class( (string) $bbai_preview_row['quality_class'] ) : 'poor';
$bbai_quality_label = isset( $bbai_preview_row['quality_label'] ) ? (string) $bbai_preview_row['quality_label'] : __( 'Weak', 'beepbeep-ai-alt-text-generator' );
$bbai_quality_score = isset( $bbai_preview_row['quality_score'] ) ? max( 0, (int) $bbai_preview_row['quality_score'] ) : 0;
$bbai_score_tier = isset( $bbai_preview_row['score_tier'] ) ? sanitize_html_class( (string) $bbai_preview_row['score_tier'] ) : ( 'optimized' === $bbai_status ? 'good' : ( 'weak' === $bbai_status ? 'review' : 'missing' ) );
$bbai_clean_alt = isset( $bbai_preview_row['clean_alt'] ) ? trim( (string) $bbai_preview_row['clean_alt'] ) : '';
$bbai_has_alt = '' !== $bbai_clean_alt;
$bbai_thumb_url = isset( $bbai_preview_row['thumb_url'] ) ? (string) $bbai_preview_row['thumb_url'] : '';
$bbai_image_url = isset( $bbai_preview_row['image_url'] ) ? (string) $bbai_preview_row['image_url'] : '';
$bbai_image_title = isset( $bbai_preview_row['image_title'] ) ? (string) $bbai_preview_row['image_title'] : '';
$bbai_display_filename = isset( $bbai_preview_row['display_filename'] ) ? (string) $bbai_preview_row['display_filename'] : $bbai_image_title;
$bbai_file_meta = isset( $bbai_preview_row['file_meta'] ) ? (string) $bbai_preview_row['file_meta'] : '';
$bbai_last_updated = isset( $bbai_preview_row['last_updated'] ) ? (string) $bbai_preview_row['last_updated'] : '';
$bbai_row_state_rank = isset( $bbai_preview_row['row_state_rank'] ) ? (int) $bbai_preview_row['row_state_rank'] : ( 'missing' === $bbai_status ? 0 : ( 'weak' === $bbai_status ? 1 : 2 ) );
$bbai_quality_tooltip = isset( $bbai_preview_row['quality_tooltip'] ) ? (string) $bbai_preview_row['quality_tooltip'] : '';
$bbai_created_ts = isset( $bbai_preview_row['created_ts'] ) ? (int) $bbai_preview_row['created_ts'] : 0;
$bbai_updated_ts = isset( $bbai_preview_row['updated_ts'] ) ? (int) $bbai_preview_row['updated_ts'] : 0;
$bbai_file_size_bytes = isset( $bbai_preview_row['file_size_bytes'] ) ? (int) $bbai_preview_row['file_size_bytes'] : 0;
$bbai_filename_sort = isset( $bbai_preview_row['filename_sort'] ) ? (string) $bbai_preview_row['filename_sort'] : strtolower( $bbai_display_filename );
$bbai_alt_preview = $bbai_has_alt ? wp_html_excerpt( $bbai_clean_alt, 160, '…' ) : '';
$bbai_alt_preview_id = 'bbai-dashboard-card-alt-' . $bbai_attachment_id;
$bbai_has_long_alt_preview = function_exists( 'mb_strlen' ) ? mb_strlen( $bbai_clean_alt ) > 160 : strlen( $bbai_clean_alt ) > 160;

$bbai_primary_action = 'preview-image';
$bbai_primary_label = __( 'Done', 'beepbeep-ai-alt-text-generator' );
$bbai_primary_class = 'bbai-dashboard-trial-preview__action bbai-dashboard-trial-preview__action--done';
$bbai_primary_title = __( 'Preview this ALT text', 'beepbeep-ai-alt-text-generator' );
$bbai_primary_modal_context = 'optimized';

if ( 'connected' === $bbai_dash_card_mode ) {
	if ( 'missing' === $bbai_status ) {
		$bbai_primary_action = 'regenerate-single';
		$bbai_primary_label = __( 'Fix', 'beepbeep-ai-alt-text-generator' );
		$bbai_primary_class = 'bbai-dashboard-trial-preview__action bbai-dashboard-trial-preview__action--generate';
		$bbai_primary_title = __( 'Fix this image', 'beepbeep-ai-alt-text-generator' );
	} elseif ( 'weak' === $bbai_status ) {
		$bbai_primary_label = __( 'Review', 'beepbeep-ai-alt-text-generator' );
		$bbai_primary_class = 'bbai-dashboard-trial-preview__action bbai-dashboard-trial-preview__action--review';
		$bbai_primary_title = __( 'Review generated ALT text', 'beepbeep-ai-alt-text-generator' );
		$bbai_primary_modal_context = 'review';
	}
} else {
	$bbai_trial_preview_generation_locked = ! empty( $bbai_trial_preview_generation_locked );

	if ( 'missing' === $bbai_status ) {
		if ( $bbai_trial_preview_generation_locked ) {
			$bbai_trial_preview_remaining_count = isset( $bbai_state_missing_count ) ? max( 0, (int) $bbai_state_missing_count ) : 0;
			$bbai_primary_action = 'show-dashboard-auth';
			$bbai_primary_label = sprintf(
				/* translators: %s: remaining image count. */
				__( 'Fix your %s remaining images', 'beepbeep-ai-alt-text-generator' ),
				number_format_i18n( $bbai_trial_preview_remaining_count )
			);
			$bbai_primary_class = 'bbai-dashboard-trial-preview__action bbai-dashboard-trial-preview__action--locked';
			$bbai_primary_title = $bbai_primary_label;
			$bbai_primary_modal_context = 'fix';
		} else {
			$bbai_primary_action = 'regenerate-single';
			$bbai_primary_label = __( 'Fix', 'beepbeep-ai-alt-text-generator' );
			$bbai_primary_class = 'bbai-dashboard-trial-preview__action bbai-dashboard-trial-preview__action--generate';
			$bbai_primary_title = __( 'Fix this image', 'beepbeep-ai-alt-text-generator' );
		}
	} elseif ( 'weak' === $bbai_status ) {
		$bbai_primary_label = __( 'Review', 'beepbeep-ai-alt-text-generator' );
		$bbai_primary_class = 'bbai-dashboard-trial-preview__action bbai-dashboard-trial-preview__action--review';
		$bbai_primary_title = __( 'Review generated ALT text', 'beepbeep-ai-alt-text-generator' );
		$bbai_primary_modal_context = 'review';
	}
}

$bbai_is_connected_card = ( 'connected' === $bbai_dash_card_mode );
?>
				<article
					class="bbai-library-row bbai-library-review-card bbai-row bbai-dashboard-trial-preview__row"
					data-bbai-trial-preview-row="<?php echo $bbai_is_connected_card ? '0' : '1'; ?>"
					data-bbai-connected-library-row="<?php echo $bbai_is_connected_card ? '1' : '0'; ?>"
					data-attachment-id="<?php echo esc_attr( $bbai_attachment_id ); ?>"
					data-id="<?php echo esc_attr( $bbai_attachment_id ); ?>"
					data-status="<?php echo esc_attr( $bbai_status ); ?>"
					data-review-state="<?php echo esc_attr( $bbai_status ); ?>"
					data-quality="<?php echo esc_attr( $bbai_quality_class ); ?>"
					data-alt-missing="<?php echo $bbai_has_alt ? 'false' : 'true'; ?>"
					data-approved="<?php echo ! empty( $bbai_preview_row['user_approved'] ) ? 'true' : 'false'; ?>"
					data-alt-full="<?php echo esc_attr( $bbai_clean_alt ); ?>"
					data-image-title="<?php echo esc_attr( $bbai_image_title ); ?>"
					data-image-url="<?php echo esc_url( $bbai_image_url ); ?>"
					data-file-name="<?php echo esc_attr( $bbai_display_filename ); ?>"
					data-file-name-sort="<?php echo esc_attr( $bbai_filename_sort ); ?>"
					data-file-meta="<?php echo esc_attr( $bbai_file_meta ); ?>"
					data-file-size-bytes="<?php echo esc_attr( (string) $bbai_file_size_bytes ); ?>"
					data-last-updated="<?php echo esc_attr( $bbai_last_updated ); ?>"
					data-created-ts="<?php echo esc_attr( (string) $bbai_created_ts ); ?>"
					data-updated-ts="<?php echo esc_attr( (string) $bbai_updated_ts ); ?>"
					data-state-rank="<?php echo esc_attr( (string) $bbai_row_state_rank ); ?>"
					data-status-label="<?php echo esc_attr( $bbai_status_label ); ?>"
					data-review-summary="<?php echo esc_attr( $bbai_status_label ); ?>"
					data-quality-label="<?php echo esc_attr( $bbai_quality_label ); ?>"
					data-quality-class="<?php echo esc_attr( $bbai_quality_class ); ?>"
					data-quality-score="<?php echo esc_attr( (string) $bbai_quality_score ); ?>"
					data-score-tier="<?php echo esc_attr( $bbai_score_tier ); ?>"
					data-quality-tooltip="<?php echo esc_attr( $bbai_quality_tooltip ); ?>"
					role="listitem"
				>
					<div class="bbai-dashboard-trial-preview__media">
						<?php if ( '' !== $bbai_thumb_url ) : ?>
							<button
								type="button"
								class="bbai-library-thumbnail-button bbai-dashboard-trial-preview__thumb-button"
								data-action="preview-image"
								data-attachment-id="<?php echo esc_attr( $bbai_attachment_id ); ?>"
								aria-label="<?php esc_attr_e( 'Preview image', 'beepbeep-ai-alt-text-generator' ); ?>"
							>
								<img src="<?php echo esc_url( $bbai_thumb_url ); ?>" alt="" class="bbai-library-thumbnail" loading="lazy" decoding="async" />
							</button>
						<?php else : ?>
							<div class="bbai-library-thumbnail-placeholder bbai-dashboard-trial-preview__thumb-placeholder">
								<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
									<path d="M2 6L10 1L18 6V16C18 16.5304 17.7893 17.0391 17.4142 17.4142C17.0391 17.7893 16.5304 18 16 18H4C3.46957 18 2.96086 17.7893 2.58579 17.4142C2.21071 17.0391 2 16.5304 2 16V6Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
									<path d="M7 18V10H13V18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
								</svg>
							</div>
						<?php endif; ?>
					</div>

					<div class="bbai-library-cell--alt-text bbai-library-card__main bbai-dashboard-trial-preview__main">
						<div class="bbai-library-card__col bbai-library-card__col--meta bbai-dashboard-trial-preview__meta">
							<div class="bbai-library-card__meta-wrap">
								<p class="bbai-library-card__filename" title="<?php echo esc_attr( $bbai_display_filename ); ?>"><?php echo esc_html( $bbai_display_filename ); ?></p>
								<p class="bbai-library-card__meta"><?php echo esc_html( $bbai_file_meta ); ?></p>
							</div>
						</div>

						<div class="bbai-library-card__col bbai-library-card__col--status bbai-dashboard-trial-preview__status">
							<div class="bbai-library-status-tags" role="group" aria-label="<?php esc_attr_e( 'Status and quality score', 'beepbeep-ai-alt-text-generator' ); ?>">
								<span class="bbai-library-status-badge bbai-library-status-badge--<?php echo esc_attr( $bbai_status ); ?>"><?php echo esc_html( $bbai_status_label ); ?></span>
								<?php if ( $bbai_has_alt ) : ?>
									<span class="bbai-library-score-badge bbai-library-score-badge--<?php echo esc_attr( $bbai_score_tier ); ?>" title="<?php echo esc_attr( $bbai_quality_tooltip ); ?>">
										<span class="bbai-library-score-badge__value" aria-hidden="true"><?php echo esc_html( (string) $bbai_quality_score ); ?></span>
										<span class="bbai-library-score-badge__label" aria-hidden="true"><?php esc_html_e( 'Score', 'beepbeep-ai-alt-text-generator' ); ?></span>
									</span>
								<?php else : ?>
									<span class="bbai-library-score-badge bbai-library-score-badge--missing" title="<?php echo esc_attr( $bbai_quality_tooltip ); ?>">
										<span class="bbai-library-score-badge__value" aria-hidden="true">—</span>
										<span class="bbai-library-score-badge__label"><?php esc_html_e( 'No ALT', 'beepbeep-ai-alt-text-generator' ); ?></span>
									</span>
								<?php endif; ?>
							</div>
						</div>

						<div class="bbai-library-card__review-body bbai-dashboard-trial-preview__body">
							<div class="bbai-library-card__col bbai-library-card__col--alt bbai-dashboard-trial-preview__alt">
								<div class="bbai-library-alt-slot" data-bbai-alt-slot="1">
									<div class="bbai-library-alt-preview-card bbai-library-alt-preview-card--v2 bbai-library-alt-preview-card--queue<?php echo $bbai_has_long_alt_preview ? ' bbai-library-alt-preview-card--collapsible' : ''; ?>" data-bbai-alt-preview-card data-action="<?php echo esc_attr( $bbai_has_alt ? 'preview-image' : $bbai_primary_action ); ?>" data-attachment-id="<?php echo esc_attr( $bbai_attachment_id ); ?>">
										<?php if ( $bbai_has_alt ) : ?>
											<p id="<?php echo esc_attr( $bbai_alt_preview_id ); ?>" class="bbai-alt-text-preview" title="<?php echo esc_attr( $bbai_clean_alt ); ?>"><?php echo esc_html( $bbai_alt_preview ); ?></p>
											<?php if ( $bbai_has_long_alt_preview ) : ?>
												<button type="button" class="bbai-library-alt-expand" data-action="toggle-alt-preview" aria-expanded="false" aria-controls="<?php echo esc_attr( $bbai_alt_preview_id ); ?>"><?php esc_html_e( 'Show more', 'beepbeep-ai-alt-text-generator' ); ?></button>
											<?php endif; ?>
										<?php else : ?>
											<span class="bbai-alt-text-missing"><?php esc_html_e( 'No ALT yet — fix it from the dashboard.', 'beepbeep-ai-alt-text-generator' ); ?></span>
										<?php endif; ?>
									</div>
								</div>
							</div>

							<div
								class="bbai-dashboard-trial-preview__row-action"
								<?php echo $bbai_is_connected_card ? 'data-bbai-connected-library-row-action' : 'data-bbai-trial-preview-row-action'; ?>
							>
								<button
									type="button"
									class="<?php echo esc_attr( $bbai_primary_class ); ?>"
									data-action="<?php echo esc_attr( $bbai_primary_action ); ?>"
									data-attachment-id="<?php echo esc_attr( $bbai_attachment_id ); ?>"
									<?php echo 'show-dashboard-auth' === $bbai_primary_action ? 'data-bbai-modal-context="' . esc_attr( $bbai_primary_modal_context ) . '"' : ''; ?>
									title="<?php echo esc_attr( $bbai_primary_title ); ?>"
								>
									<?php echo esc_html( $bbai_primary_label ); ?>
								</button>
							</div>
						</div>
					</div>
				</article>
