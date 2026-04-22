<?php
/**
 * Shared image-table component.
 *
 * Renders the surface shell + table skeleton for MissingAltTable and
 * ReviewQueue. Rows are always populated by the JS controller via REST.
 *
 * Required in parent scope:
 *   $bbai_it_component   string  Component name: 'MissingAltTable' | 'ReviewQueue'
 *   $bbai_it_scope       string  Table scope attr: 'missing' | 'needs_review'
 *   $bbai_it_heading     string  Translated heading text.
 *   $bbai_it_loading     string  Translated loading message.
 *   $bbai_it_alt_col     string  Translated ALT-text column header.
 *   $bbai_it_primary_cta array   [ 'label' => string, 'href' => string, 'action' => string ]
 *   $bbai_it_library_url string  URL for "Open full library" link.
 *
 * Optional (PHP SSR — first paint without JS):
 *   $bbai_it_server_render bool   When true, rows come from PHP; loading spinner hidden.
 *   $bbai_it_rows           list<array{id:int,filename:string,thumb_url:string,scope:string}>
 *   $bbai_it_empty_message  string Shown when SSR and no rows.
 *
 * @package BeepBeep_AI
 * @since   5.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bbai_it_component   = (string) ( $bbai_it_component   ?? '' );
$bbai_it_scope       = (string) ( $bbai_it_scope       ?? '' );
$bbai_it_heading     = (string) ( $bbai_it_heading     ?? '' );
$bbai_it_subheading  = (string) ( $bbai_it_subheading  ?? '' );
$bbai_it_loading     = (string) ( $bbai_it_loading     ?? '' );
$bbai_it_alt_col     = (string) ( $bbai_it_alt_col     ?? '' );
$bbai_it_primary_cta = is_array( $bbai_it_primary_cta ?? null ) ? $bbai_it_primary_cta : [];
$bbai_it_library_url = (string) ( $bbai_it_library_url ?? '' );

$bbai_it_rows            = is_array( $bbai_it_rows ?? null ) ? $bbai_it_rows : [];
$bbai_it_empty_message   = (string) ( $bbai_it_empty_message ?? __( 'No rows to show.', 'beepbeep-ai-alt-text-generator' ) );
$bbai_it_server_render   = ! empty( $bbai_it_server_render );

$bbai_it_surface_mod = sanitize_html_class( strtolower( str_replace( 'AltTable', '-alt-table',
	str_replace( 'Queue', '-queue', preg_replace( '/([A-Z])/', '-$1', lcfirst( $bbai_it_component ) ) )
) ) );
// Simpler: derive modifier from component name map.
$bbai_it_modifier_map = [
	'MissingAltTable' => 'missing-alt',
	'ReviewQueue'     => 'review-queue',
];
$bbai_it_modifier = sanitize_html_class( $bbai_it_modifier_map[ $bbai_it_component ] ?? 'table' );
?>

<div
	class="bbai-li-surface bbai-li-surface--<?php echo $bbai_it_modifier; ?>"
	data-bbai-li-surface="<?php echo esc_attr( $bbai_it_component ); ?>"
>

	<div class="bbai-li-surface__header">
		<div class="bbai-li-surface__header-text">
			<h3 class="bbai-li-surface__heading"><?php echo esc_html( $bbai_it_heading ); ?></h3>
			<?php if ( $bbai_it_subheading ) : ?>
				<p class="bbai-li-surface__subheading"><?php echo esc_html( $bbai_it_subheading ); ?></p>
			<?php endif; ?>
		</div>

		<div class="bbai-li-surface__header-actions">
			<?php if ( ! empty( $bbai_it_primary_cta['label'] ) ) : ?>
				<a
					href="<?php echo esc_url( $bbai_it_primary_cta['href'] ?? '#' ); ?>"
					class="bbai-li-surface__cta bbai-li-surface__cta--primary"
					data-action="<?php echo esc_attr( $bbai_it_primary_cta['action'] ?? '' ); ?>"
				><?php echo esc_html( $bbai_it_primary_cta['label'] ); ?></a>
			<?php endif; ?>

			<?php if ( $bbai_it_library_url ) : ?>
				<a
					href="<?php echo esc_url( $bbai_it_library_url ); ?>"
					class="bbai-li-surface__cta bbai-li-surface__cta--secondary"
				><?php esc_html_e( 'Open full library', 'beepbeep-ai-alt-text-generator' ); ?></a>
			<?php endif; ?>
		</div>
	</div>

	<?php /* Table: PHP SSR when $bbai_it_server_render; else JS fills via REST. */ ?>
	<div
		class="bbai-li-image-table"
		data-bbai-li-image-table="<?php echo esc_attr( $bbai_it_component ); ?>"
		data-bbai-li-table-scope="<?php echo esc_attr( $bbai_it_scope ); ?>"
	>
		<div class="bbai-li-image-table__loading" data-bbai-li-table-loading="1" <?php echo $bbai_it_server_render ? 'hidden' : ''; ?>>
			<?php echo esc_html( $bbai_it_loading ); ?>
		</div>

		<?php if ( $bbai_it_server_render && empty( $bbai_it_rows ) ) : ?>
			<p class="bbai-li-image-table__empty"><?php echo esc_html( $bbai_it_empty_message ); ?></p>
		<?php endif; ?>

		<table class="bbai-li-image-table__table" <?php echo ( $bbai_it_server_render && ! empty( $bbai_it_rows ) ) ? '' : 'hidden'; ?>>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Image', 'beepbeep-ai-alt-text-generator' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Filename', 'beepbeep-ai-alt-text-generator' ); ?></th>
					<th scope="col"><?php echo esc_html( $bbai_it_alt_col ); ?></th>
					<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'beepbeep-ai-alt-text-generator' ); ?></span></th>
				</tr>
			</thead>
			<tbody data-bbai-li-table-body="1">
				<?php if ( $bbai_it_server_render && ! empty( $bbai_it_rows ) ) : ?>
					<?php foreach ( $bbai_it_rows as $bbai_it_row ) : ?>
						<?php
						$bbai_rid   = absint( $bbai_it_row['id'] ?? 0 );
						$bbai_rfile = (string) ( $bbai_it_row['filename'] ?? '' );
						$bbai_rthumb = esc_url( (string) ( $bbai_it_row['thumb_url'] ?? '' ) );
						$bbai_rscope = (string) ( $bbai_it_row['scope'] ?? $bbai_it_scope );
						$bbai_is_miss = ( 'needs_review' !== $bbai_rscope );
						?>
						<tr class="bbai-li-image-table__row" data-attachment-id="<?php echo esc_attr( (string) $bbai_rid ); ?>">
							<td class="bbai-li-image-table__col-thumb">
								<?php if ( $bbai_rthumb ) : ?>
									<img src="<?php echo $bbai_rthumb; ?>" alt="" width="40" height="40" loading="lazy" class="bbai-li-image-table__thumb" />
								<?php else : ?>
									<span class="bbai-li-image-table__thumb-placeholder" aria-hidden="true"></span>
								<?php endif; ?>
							</td>
							<td class="bbai-li-image-table__col-file"><?php echo esc_html( $bbai_rfile ); ?></td>
							<td class="bbai-li-image-table__col-status">
								<?php if ( $bbai_is_miss ) : ?>
									<span class="bbai-li-image-table__badge bbai-li-image-table__badge--missing"><?php esc_html_e( 'Missing', 'beepbeep-ai-alt-text-generator' ); ?></span>
								<?php else : ?>
									<span class="bbai-li-image-table__badge bbai-li-image-table__badge--review"><?php esc_html_e( 'Needs review', 'beepbeep-ai-alt-text-generator' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="bbai-li-image-table__col-action">
								<?php if ( $bbai_is_miss ) : ?>
									<button type="button" class="bbai-li-image-table__row-btn button button-primary" data-action="generate-single" data-attachment-id="<?php echo esc_attr( (string) $bbai_rid ); ?>">
										<?php esc_html_e( 'Generate', 'beepbeep-ai-alt-text-generator' ); ?>
									</button>
								<?php else : ?>
									<button type="button" class="bbai-li-image-table__row-btn button button-secondary" data-action="review-single" data-attachment-id="<?php echo esc_attr( (string) $bbai_rid ); ?>">
										<?php esc_html_e( 'Review', 'beepbeep-ai-alt-text-generator' ); ?>
									</button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<div class="bbai-li-image-table__pagination" data-bbai-li-table-pagination="1" hidden></div>
	</div>

</div>
