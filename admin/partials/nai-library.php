<?php
/**
 * nAi Library — Linear/Vercel-inspired ALT Library view.
 *
 * Consumes the locals prepared by library-tab.php and renders the
 * design-language layout: page header, coverage strip, filter pills,
 * sticky bulk action bar, lighter image rows.
 *
 * Action buttons preserve the same data-action / data-attachment-id
 * attributes the legacy library JS targets, so existing handlers
 * (generate-single, regenerate-selected, edit-alt-inline, etc.)
 * keep working without changes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals are scoped to this included partial.

// Defensive defaults so partial renders even when an unexpected upstream
// branch did not compute everything.
$nai_lib_total         = isset( $bbai_cov_total ) ? (int) $bbai_cov_total : ( isset( $bbai_total_images ) ? (int) $bbai_total_images : 0 );
$nai_lib_optimized     = isset( $bbai_cov_optimized ) ? (int) $bbai_cov_optimized : 0;
$nai_lib_needs_review  = isset( $bbai_cov_needs_review ) ? (int) $bbai_cov_needs_review : 0;
$nai_lib_missing       = isset( $bbai_cov_missing ) ? (int) $bbai_cov_missing : 0;
$nai_lib_opt_pct       = isset( $bbai_cov_opt_pct ) ? (int) $bbai_cov_opt_pct : ( $nai_lib_total > 0 ? (int) round( ( $nai_lib_optimized / $nai_lib_total ) * 100 ) : 0 );
$nai_lib_is_pro        = ! empty( $bbai_is_pro );
$nai_lib_limit_lock    = ! empty( $bbai_limit_reached_state );
$nai_lib_active_filter = isset( $bbai_default_review_filter ) ? (string) $bbai_default_review_filter : 'all';
$nai_lib_current_page  = isset( $bbai_current_page ) ? max( 1, (int) $bbai_current_page ) : 1;
$nai_lib_total_pages   = isset( $bbai_total_pages ) ? max( 1, (int) $bbai_total_pages ) : 1;
$nai_lib_images        = isset( $bbai_all_images ) && is_array( $bbai_all_images ) ? $bbai_all_images : array();
$nai_lib_row_states    = isset( $bbai_library_row_states ) && is_array( $bbai_library_row_states ) ? $bbai_library_row_states : array();

$nai_lib_library_url = add_query_arg( array( 'page' => 'bbai-library' ), admin_url( 'admin.php' ) );

$nai_lib_filter_items = array(
	array(
		'key'   => 'all',
		'label' => __( 'All', 'beepbeep-ai-alt-text-generator' ),
		'count' => $nai_lib_total,
	),
	array(
		'key'   => 'missing',
		'label' => __( 'Needs ALT', 'beepbeep-ai-alt-text-generator' ),
		'count' => $nai_lib_missing,
	),
	array(
		'key'   => 'weak',
		'label' => __( 'Low quality', 'beepbeep-ai-alt-text-generator' ),
		'count' => $nai_lib_needs_review,
	),
	array(
		'key'   => 'optimized',
		'label' => __( 'Optimised', 'beepbeep-ai-alt-text-generator' ),
		'count' => $nai_lib_optimized,
	),
);

$nai_lib_filter_href = static function ( string $key ) use ( $nai_lib_library_url ): string {
	if ( 'all' === $key ) {
		return $nai_lib_library_url;
	}
	$status_for_url = ( 'weak' === $key ) ? 'needs_review' : $key;
	return add_query_arg(
		array(
			'status' => $status_for_url,
			'filter' => $key,
		),
		$nai_lib_library_url
	);
};

$nai_lib_icon = static function ( string $name, int $size = 16, float $stroke = 1.75 ): string {
	$paths = array(
		'search'        => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
		'filter'        => '<path d="M3 5h18M6 12h12M10 19h4"/>',
		'sparkles'      => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M5.6 18.4l2.8-2.8M15.6 8.4l2.8-2.8"/>',
		'edit'          => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/>',
		'check'         => '<path d="m5 12 5 5 9-11"/>',
		'crown'         => '<path d="m2 19 2-11 5 5 3-7 3 7 5-5 2 11H2Z"/><path d="M2 21h20"/>',
		'chevron-left'  => '<path d="m15 18-6-6 6-6"/>',
		'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
		'refresh'       => '<path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/>',
		'x'             => '<path d="M18 6 6 18M6 6l12 12"/>',
	);
	$body  = $paths[ $name ] ?? '';
	return sprintf(
		'<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="%2$s" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%3$s</svg>',
		$size,
		esc_attr( (string) $stroke ),
		$body // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG path data only.
	);
};

$nai_lib_row_status_class = array(
	'optimized' => 'nai-chip--ok',
	'weak'      => 'nai-chip--warn',
	'missing'   => 'nai-chip--danger',
);
$nai_lib_row_status_label = array(
	'optimized' => __( 'Optimised', 'beepbeep-ai-alt-text-generator' ),
	'weak'      => __( 'Needs review', 'beepbeep-ai-alt-text-generator' ),
	'missing'   => __( 'Missing ALT', 'beepbeep-ai-alt-text-generator' ),
);
$nai_shell_active = 'library';
$nai_shell_is_pro = $nai_lib_is_pro;
require BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/nai-shell-open.php';
?>
<div class="nai-screen nai-screen--library bbai-library-container bbai-library-main"
	data-nai-screen="library"
	data-bbai-library-is-pro="<?php echo $nai_lib_is_pro ? '1' : '0'; ?>"
	data-bbai-entitlement-can-generate="<?php echo $nai_lib_limit_lock ? 'false' : 'true'; ?>"
	data-bbai-settings-url="<?php echo esc_url( admin_url( 'admin.php?page=bbai-settings' ) ); ?>"
	data-bbai-bulk-mode="true"
	data-bbai-has-selection="false"
	data-bbai-empty-filter="<?php esc_attr_e( 'No images match this filter.', 'beepbeep-ai-alt-text-generator' ); ?>"
	data-bbai-empty-filter-hint="<?php esc_attr_e( 'Try another status or search term.', 'beepbeep-ai-alt-text-generator' ); ?>">

	<div class="nai-page-header">
		<div class="nai-eyebrow"><?php esc_html_e( 'Library', 'beepbeep-ai-alt-text-generator' ); ?></div>
		<div class="nai-page-header__row">
			<div>
				<h1 class="nai-page-header__title"><?php esc_html_e( 'Every image, scored and ready', 'beepbeep-ai-alt-text-generator' ); ?></h1>
				<p class="nai-page-header__sub">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: optimised count, 2: total images. */
							__( '%1$s of %2$s images optimised. Filter, review, and generate what needs attention.', 'beepbeep-ai-alt-text-generator' ),
							number_format_i18n( $nai_lib_optimized ),
							number_format_i18n( $nai_lib_total )
						)
					);
					?>
				</p>
			</div>
			<button type="button" class="nai-btn nai-btn--secondary nai-btn--sm" data-action="rescan-media-library">
				<?php echo $nai_lib_icon( 'refresh', 14, 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php esc_html_e( 'Rescan library', 'beepbeep-ai-alt-text-generator' ); ?>
			</button>
		</div>
	</div>

	<?php // -------- Coverage strip -------- ?>
	<div class="nai-card nai-coverage" style="margin-top:0;margin-bottom:14px;">
		<div class="nai-coverage__inner">
			<div class="nai-coverage__row">
				<div class="nai-coverage__head">
					<span class="nai-eyebrow"><?php esc_html_e( 'Library coverage', 'beepbeep-ai-alt-text-generator' ); ?></span>
					<span class="nai-mono nai-tnum nai-coverage__num"><?php echo esc_html( (string) $nai_lib_opt_pct ); ?>%</span>
					<span class="nai-coverage__sub"><?php esc_html_e( 'optimised', 'beepbeep-ai-alt-text-generator' ); ?></span>
				</div>
				<div class="nai-tnum nai-coverage__stats">
					<span class="nai-mono nai-coverage__stats-strong"><?php echo esc_html( number_format_i18n( $nai_lib_optimized ) ); ?></span>
					<?php esc_html_e( 'optimised', 'beepbeep-ai-alt-text-generator' ); ?>
					<span> · </span>
					<span class="nai-mono nai-coverage__stats-strong"><?php echo esc_html( number_format_i18n( $nai_lib_needs_review ) ); ?></span>
					<?php esc_html_e( 'to review', 'beepbeep-ai-alt-text-generator' ); ?>
					<span> · </span>
					<span class="nai-mono nai-coverage__stats-strong"><?php echo esc_html( number_format_i18n( $nai_lib_missing ) ); ?></span>
					<?php esc_html_e( 'missing', 'beepbeep-ai-alt-text-generator' ); ?>
				</div>
			</div>
			<div class="nai-progress <?php echo $nai_lib_opt_pct >= 90 ? '' : 'nai-progress--primary'; ?>">
				<div class="nai-progress__bar" style="width:<?php echo (int) $nai_lib_opt_pct; ?>%;"></div>
			</div>
		</div>
	</div>

	<div class="nai-card nai-entitlement-notice" role="status" data-bbai-entitlement-exhausted aria-hidden="<?php echo $nai_lib_limit_lock ? 'false' : 'true'; ?>" <?php echo $nai_lib_limit_lock ? '' : 'hidden'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<div class="nai-eyebrow"><?php esc_html_e( 'Monthly allowance used', 'beepbeep-ai-alt-text-generator' ); ?></div>
		<p><?php esc_html_e( 'You can still review and edit existing ALT text. Upgrade to generate new ALT text now.', 'beepbeep-ai-alt-text-generator' ); ?></p>
	</div>

	<?php // -------- Filter pills + search -------- ?>
	<?php // Compatibility hook: legacy filter JS requires #bbai-review-filter-tabs button[data-filter]. ?>
	<div id="bbai-review-filter-tabs" class="nai-filter-row" role="navigation" aria-label="<?php esc_attr_e( 'Filter images by status', 'beepbeep-ai-alt-text-generator' ); ?>" data-bbai-default-filter="<?php echo esc_attr( $nai_lib_active_filter ); ?>">
		<?php foreach ( $nai_lib_filter_items as $item ) : ?>
			<?php $nai_lib_filter_active = $item['key'] === $nai_lib_active_filter; ?>
			<button type="button"
				class="nai-filter-pill bbai-filter-group__item <?php echo $nai_lib_filter_active ? 'nai-filter-pill--active bbai-filter-group__item--active bbai-alt-review-filters__btn--active' : ''; ?>"
				data-filter="<?php echo esc_attr( $item['key'] ); ?>"
				data-bbai-filter-label="<?php echo esc_attr( $item['label'] ); ?>"
				data-bbai-filter-target="<?php echo esc_attr( $item['key'] ); ?>"
				data-bbai-library-filter="<?php echo esc_attr( $item['key'] ); ?>"
				data-bbai-filter-href="<?php echo esc_url( $nai_lib_filter_href( $item['key'] ) ); ?>"
				aria-pressed="<?php echo $nai_lib_filter_active ? 'true' : 'false'; ?>">
				<span class="bbai-filter-group__label"><?php echo esc_html( $item['label'] ); ?></span>
				<span class="nai-filter-pill__count bbai-filter-group__count"><?php echo esc_html( number_format_i18n( $item['count'] ) ); ?></span>
			</button>
		<?php endforeach; ?>

		<div class="nai-search">
			<span class="nai-search__icon"><?php echo $nai_lib_icon( 'search', 14, 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<input type="search" id="bbai-library-search" class="nai-search__input" placeholder="<?php esc_attr_e( 'Search filename', 'beepbeep-ai-alt-text-generator' ); ?>" />
		</div>
	</div>

	<?php if ( empty( $nai_lib_images ) ) : ?>

		<div class="nai-card" data-bbai-library-empty-state style="padding:32px 24px;text-align:center;">
			<div class="nai-eyebrow" style="margin-bottom:6px;"><?php esc_html_e( 'Nothing here', 'beepbeep-ai-alt-text-generator' ); ?></div>
			<p style="font-size:13.5px;color:var(--nai-text-2);margin:0 0 14px;line-height:1.5;"><?php esc_html_e( 'No images match this filter. Try another or upload some to your media library.', 'beepbeep-ai-alt-text-generator' ); ?></p>
			<a class="nai-btn nai-btn--secondary nai-btn--sm" href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>">
				<?php esc_html_e( 'Open Media Library', 'beepbeep-ai-alt-text-generator' ); ?>
			</a>
		</div>

	<?php else : ?>

		<?php // Contextual bulk action bar (hidden until something is selected) ?>
		<div class="nai-card" id="bbai-library-selection-bar" data-bbai-library-selection-bar style="padding:10px 14px;margin-bottom:10px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
			<label style="display:inline-flex;align-items:center;gap:8px;font-size:13px;color:var(--nai-text-2);font-weight:500;">
				<input type="checkbox" id="bbai-select-all" class="bbai-checkbox" />
				<?php esc_html_e( 'Select images', 'beepbeep-ai-alt-text-generator' ); ?>
			</label>
			<span class="nai-mono nai-tnum" id="bbai-selected-count" data-bbai-selected-count style="font-size:12px;color:var(--nai-text-3);">0 <?php esc_html_e( 'selected', 'beepbeep-ai-alt-text-generator' ); ?></span>
			<span style="flex:1;"></span>
			<button type="button"
					class="nai-btn nai-btn--primary nai-btn--sm <?php echo $nai_lib_limit_lock ? 'bbai-is-locked' : ''; ?>"
					id="bbai-batch-generate"
					data-action="generate-selected"
					data-bbai-lock-preserve-label="1"
					<?php if ( $nai_lib_limit_lock ) : ?>
						data-bbai-action="open-upgrade"
						data-bbai-locked-cta="1"
						data-bbai-lock-reason="generate_missing"
						aria-disabled="true"
					<?php endif; ?>>
				<?php echo $nai_lib_icon( 'sparkles', 13, 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php esc_html_e( 'Generate ALT', 'beepbeep-ai-alt-text-generator' ); ?>
			</button>
			<button type="button"
					class="nai-btn nai-btn--secondary nai-btn--sm <?php echo $nai_lib_limit_lock ? 'bbai-is-locked' : ''; ?>"
					id="bbai-batch-regenerate"
					data-action="regenerate-selected"
					data-bbai-lock-preserve-label="1"
					<?php if ( $nai_lib_limit_lock ) : ?>
						data-bbai-action="open-upgrade"
						data-bbai-locked-cta="1"
						data-bbai-lock-reason="reoptimize_all"
						aria-disabled="true"
					<?php endif; ?>>
				<?php esc_html_e( 'Regenerate', 'beepbeep-ai-alt-text-generator' ); ?>
			</button>
			<button type="button" class="nai-btn nai-btn--ghost nai-btn--sm" id="bbai-batch-clear" data-action="clear-selection">
				<?php echo $nai_lib_icon( 'x', 12, 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php esc_html_e( 'Clear', 'beepbeep-ai-alt-text-generator' ); ?>
			</button>
		</div>

		<?php // -------- Row list -------- ?>
		<div class="nai-card" style="padding:0;overflow:hidden;">
			<?php // Compatibility hook: legacy Library JS mutates #bbai-library-table-body and .bbai-library-row children. ?>
			<div id="bbai-library-table-body" class="bbai-library-review-queue" role="list" style="display:flex;flex-direction:column;">
				<?php
				foreach ( $nai_lib_images as $nai_lib_idx => $nai_lib_image ) :
					$nai_lib_attach_id = (int) $nai_lib_image->ID;
					$nai_lib_alt_raw   = isset( $nai_lib_image->alt_text ) ? (string) $nai_lib_image->alt_text : '';
					$nai_lib_alt       = trim( $nai_lib_alt_raw );
					$nai_lib_has_alt   = '' !== $nai_lib_alt;

					$nai_lib_thumb_src = wp_get_attachment_image_src( $nai_lib_attach_id, 'thumbnail' );
					$nai_lib_thumb_url = $nai_lib_thumb_src && isset( $nai_lib_thumb_src[0] ) ? (string) $nai_lib_thumb_src[0] : '';
					$nai_lib_attached  = get_attached_file( $nai_lib_attach_id );
					$nai_lib_filename  = $nai_lib_attached ? basename( $nai_lib_attached ) : '';
					if ( '' === $nai_lib_filename && isset( $nai_lib_image->post_title ) ) {
						$nai_lib_filename = (string) $nai_lib_image->post_title;
					}
					if ( '' === $nai_lib_filename ) {
						$nai_lib_filename = sprintf( '#%d', $nai_lib_attach_id );
					}
					$nai_lib_display_name = strlen( $nai_lib_filename ) > 56 ? wp_html_excerpt( $nai_lib_filename, 53, '…' ) : $nai_lib_filename;
					$nai_lib_modified     = isset( $nai_lib_image->post_modified ) ? date_i18n( 'j M Y', strtotime( (string) $nai_lib_image->post_modified ) ) : '';

					$nai_lib_state    = isset( $nai_lib_row_states[ $nai_lib_idx ] ) && is_array( $nai_lib_row_states[ $nai_lib_idx ] ) ? $nai_lib_row_states[ $nai_lib_idx ] : array();
					$nai_lib_status   = isset( $nai_lib_state['status'] ) ? (string) $nai_lib_state['status'] : ( $nai_lib_has_alt ? 'weak' : 'missing' );
					$nai_lib_chip_cls = $nai_lib_row_status_class[ $nai_lib_status ] ?? 'nai-chip--warn';
					$nai_lib_chip_lbl = $nai_lib_row_status_label[ $nai_lib_status ] ?? __( 'Needs review', 'beepbeep-ai-alt-text-generator' );

					$nai_lib_alt_preview = $nai_lib_has_alt ? $nai_lib_alt : __( 'No ALT text yet.', 'beepbeep-ai-alt-text-generator' );
					$nai_lib_alt_dim     = $nai_lib_has_alt ? '' : 'color:var(--nai-text-3);font-style:italic;';
					$nai_lib_quality     = 'optimized' === $nai_lib_status ? 'good' : 'poor';
					$nai_lib_quality_lbl = 'optimized' === $nai_lib_status ? __( 'Good', 'beepbeep-ai-alt-text-generator' ) : __( 'Needs review', 'beepbeep-ai-alt-text-generator' );
					$nai_lib_file_meta   = trim( $nai_lib_modified );
					?>
					<?php // Compatibility hook: legacy JS requires .bbai-library-row plus attachment/status/ALT data attributes. ?>
					<div class="nai-lib-row bbai-library-row"
						role="listitem"
						style="display:flex;align-items:center;gap:14px;padding:10px 16px;border-top:1px solid var(--nai-hairline);"
						data-attachment-id="<?php echo esc_attr( (string) $nai_lib_attach_id ); ?>"
						data-id="<?php echo esc_attr( (string) $nai_lib_attach_id ); ?>"
						data-status="<?php echo esc_attr( $nai_lib_status ); ?>"
						data-review-state="<?php echo esc_attr( $nai_lib_status ); ?>"
						data-alt-missing="<?php echo $nai_lib_has_alt ? 'false' : 'true'; ?>"
						data-alt-full="<?php echo esc_attr( $nai_lib_alt ); ?>"
						data-ai-source="<?php echo $nai_lib_has_alt ? 'ai' : ''; ?>"
						data-quality="<?php echo esc_attr( $nai_lib_quality ); ?>"
						data-quality-class="<?php echo esc_attr( $nai_lib_quality ); ?>"
						data-quality-label="<?php echo esc_attr( $nai_lib_quality_lbl ); ?>"
						data-image-title="<?php echo esc_attr( $nai_lib_display_name ); ?>"
						data-image-url="<?php echo esc_url( $nai_lib_thumb_url ); ?>"
						data-file-name="<?php echo esc_attr( $nai_lib_filename ); ?>"
						data-file-meta="<?php echo esc_attr( $nai_lib_file_meta ); ?>"
						data-last-updated="<?php echo esc_attr( $nai_lib_modified ); ?>">
						<label style="display:inline-flex;align-items:center;flex-shrink:0;">
							<?php // Compatibility hook: legacy bulk JS requires .bbai-library-row-check. ?>
							<input type="checkbox" class="bbai-checkbox bbai-library-row-check bbai-image-checkbox" value="<?php echo esc_attr( (string) $nai_lib_attach_id ); ?>" data-attachment-id="<?php echo esc_attr( (string) $nai_lib_attach_id ); ?>" aria-label="
							<?php
								/* translators: %s: filename */
								printf( esc_attr__( 'Select %s', 'beepbeep-ai-alt-text-generator' ), esc_attr( $nai_lib_display_name ) );
							?>
							" />
						</label>
						<?php if ( '' !== $nai_lib_thumb_url ) : ?>
							<button type="button" data-action="preview-image" data-attachment-id="<?php echo esc_attr( (string) $nai_lib_attach_id ); ?>" style="border:0;padding:0;background:transparent;cursor:zoom-in;flex-shrink:0;">
								<img class="bbai-library-thumbnail" src="<?php echo esc_url( $nai_lib_thumb_url ); ?>" alt="" loading="lazy" decoding="async" style="width:44px;height:44px;border-radius:6px;object-fit:cover;display:block;border:1px solid var(--nai-border);" />
							</button>
						<?php else : ?>
							<div class="nai-thumb" style="flex-shrink:0;"></div>
						<?php endif; ?>

						<div class="bbai-library-cell--alt-text" style="min-width:0;flex:1;">
							<div style="font-size:13px;font-weight:600;color:var(--nai-text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr( $nai_lib_filename ); ?>">
								<?php echo esc_html( $nai_lib_display_name ); ?>
							</div>
							<div data-bbai-alt-slot style="font-size:11.5px;color:var(--nai-text-3);margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;<?php echo esc_attr( $nai_lib_dim ?? '' ); ?>" title="<?php echo esc_attr( $nai_lib_has_alt ? $nai_lib_alt : '' ); ?>">
								<span style="<?php echo esc_attr( $nai_lib_alt_dim ); ?>"><?php echo esc_html( $nai_lib_alt_preview ); ?></span>
							</div>
						</div>

						<div style="flex-shrink:0;display:inline-flex;align-items:center;gap:6px;">
							<span class="nai-chip <?php echo esc_attr( $nai_lib_chip_cls ); ?>">
								<span class="nai-chip__dot"></span>
								<?php echo esc_html( $nai_lib_chip_lbl ); ?>
							</span>
						</div>

						<div style="flex-shrink:0;display:inline-flex;align-items:center;gap:6px;">
							<button type="button"
									class="nai-btn nai-btn--secondary nai-btn--sm"
									data-action="edit-alt-inline"
									data-attachment-id="<?php echo esc_attr( (string) $nai_lib_attach_id ); ?>"
									title="<?php esc_attr_e( 'Edit ALT text', 'beepbeep-ai-alt-text-generator' ); ?>">
								<?php echo $nai_lib_icon( 'edit', 12, 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<?php esc_html_e( 'Edit', 'beepbeep-ai-alt-text-generator' ); ?>
							</button>
							<button type="button"
									class="nai-btn <?php echo $nai_lib_limit_lock ? 'nai-btn--secondary bbai-is-locked' : 'nai-btn--primary'; ?> nai-btn--sm"
									data-action="regenerate-single"
									data-attachment-id="<?php echo esc_attr( (string) $nai_lib_attach_id ); ?>"
									data-bbai-lock-preserve-label="1"
									<?php if ( $nai_lib_limit_lock ) : ?>
										data-bbai-action="open-upgrade"
										data-bbai-locked-cta="1"
										data-bbai-lock-reason="regenerate_single"
										aria-disabled="true"
									<?php endif; ?>>
								<?php echo $nai_lib_icon( 'sparkles', 12, 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<?php echo esc_html( $nai_lib_has_alt ? __( 'Regenerate', 'beepbeep-ai-alt-text-generator' ) : __( 'Generate', 'beepbeep-ai-alt-text-generator' ) ); ?>
							</button>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<?php // -------- Pagination -------- ?>
		<?php if ( $nai_lib_total_pages > 1 ) : ?>
			<div data-bbai-library-pagination style="display:flex;align-items:center;justify-content:space-between;margin-top:14px;font-size:12px;color:var(--nai-text-3);">
				<span class="nai-mono nai-tnum">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: current page, 2: total pages. */
							__( 'Page %1$d of %2$d', 'beepbeep-ai-alt-text-generator' ),
							$nai_lib_current_page,
							$nai_lib_total_pages
						)
					);
					?>
				</span>
				<div style="display:inline-flex;gap:6px;">
					<?php if ( $nai_lib_current_page > 1 ) : ?>
						<a class="nai-btn nai-btn--secondary nai-btn--sm" href="<?php echo esc_url( add_query_arg( 'alt_page', max( 1, $nai_lib_current_page - 1 ) ) ); ?>">
							<?php echo $nai_lib_icon( 'chevron-left', 12, 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php esc_html_e( 'Previous', 'beepbeep-ai-alt-text-generator' ); ?>
						</a>
					<?php else : ?>
						<button class="nai-btn nai-btn--secondary nai-btn--sm" disabled type="button">
							<?php echo $nai_lib_icon( 'chevron-left', 12, 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php esc_html_e( 'Previous', 'beepbeep-ai-alt-text-generator' ); ?>
						</button>
					<?php endif; ?>
					<?php if ( $nai_lib_current_page < $nai_lib_total_pages ) : ?>
						<a class="nai-btn nai-btn--secondary nai-btn--sm" href="<?php echo esc_url( add_query_arg( 'alt_page', min( $nai_lib_total_pages, $nai_lib_current_page + 1 ) ) ); ?>">
							<?php esc_html_e( 'Next', 'beepbeep-ai-alt-text-generator' ); ?>
							<?php echo $nai_lib_icon( 'chevron-right', 12, 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</a>
					<?php else : ?>
						<button class="nai-btn nai-btn--secondary nai-btn--sm" disabled type="button">
							<?php esc_html_e( 'Next', 'beepbeep-ai-alt-text-generator' ); ?>
							<?php echo $nai_lib_icon( 'chevron-right', 12, 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</button>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

	<?php endif; // empty images ?>

	<?php if ( ! $nai_lib_is_pro ) : ?>
		<div class="nai-card" style="margin-top:18px;padding:14px 18px;display:flex;align-items:center;gap:14px;background:linear-gradient(180deg, #EFF4FE 0%, #F8FAFC 100%);border-color:var(--nai-primary-border);">
			<div style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;background:var(--nai-primary);color:#fff;flex-shrink:0;">
				<?php echo $nai_lib_icon( 'crown', 16, 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<div style="flex:1;min-width:0;">
				<div style="font-size:13.5px;font-weight:600;color:var(--nai-text);"><?php esc_html_e( "Don't optimise images one by one.", 'beepbeep-ai-alt-text-generator' ); ?></div>
				<div style="font-size:12px;color:var(--nai-text-2);margin-top:2px;line-height:1.45;"><?php esc_html_e( 'Autopilot covers new uploads automatically with 1,000 AI generations per month and no daily cap inside that allowance.', 'beepbeep-ai-alt-text-generator' ); ?></div>
			</div>
			<button type="button" class="nai-btn nai-btn--pro nai-btn--sm" data-action="show-upgrade-modal" data-nai-open-paywall="default" data-bbai-pricing-variant="growth">
				<?php esc_html_e( 'Upgrade to Pro', 'beepbeep-ai-alt-text-generator' ); ?>
			</button>
		</div>
	<?php endif; ?>

	<?php require BEEPBEEP_AI_PLUGIN_DIR . 'admin/components/dashboard/dashboard-prototype-overlays.php'; ?>

</div>
</div>
