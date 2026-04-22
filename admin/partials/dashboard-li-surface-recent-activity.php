<?php
/**
 * Logged-in surface: RecentActivity (ALL_CLEAR state).
 *
 * Renders a read-only table of recently optimised images.
 * When $bbai_it_server_render is true, rows come from PHP (SSR).
 * JS controller can update/append entries via data-bbai-li-recent-activity.
 *
 * Required in parent scope:
 *   $bbai_li_state  array  The full DashboardState object.
 *
 * Optional (PHP SSR):
 *   $bbai_it_rows          list<array{id,filename,thumb_url,scope}>
 *   $bbai_it_server_render bool
 *
 * @package BeepBeep_AI
 * @since   5.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bbai_li_props      = $bbai_li_state['surface']['props'] ?? [];
$bbai_li_last_run   = $bbai_li_props['last_run_at'] ?? null;
$bbai_li_last_run_fmt = '';
if ( $bbai_li_last_run ) {
	$bbai_li_last_run_fmt = date_i18n(
		get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
		strtotime( $bbai_li_last_run )
	);
}

$bbai_it_rows          = is_array( $bbai_it_rows ?? null ) ? $bbai_it_rows : [];
$bbai_it_server_render = ! empty( $bbai_it_server_render );
$bbai_it_library_url   = admin_url( 'admin.php?page=bbai-library' );
?>

<div
	class="bbai-li-surface bbai-li-surface--recent-activity"
	data-bbai-li-surface="RecentActivity"
>

	<div class="bbai-li-surface__header">
		<div class="bbai-li-surface__header-text">
			<h3 class="bbai-li-surface__heading">
				<?php esc_html_e( 'Recently optimised', 'beepbeep-ai-alt-text-generator' ); ?>
			</h3>
			<?php if ( $bbai_li_last_run_fmt ) : ?>
				<p class="bbai-li-surface__subheading">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: formatted date/time */
							__( 'Last run: %s', 'beepbeep-ai-alt-text-generator' ),
							$bbai_li_last_run_fmt
						)
					);
					?>
				</p>
			<?php endif; ?>
		</div>

		<div class="bbai-li-surface__header-actions">
			<a
				href="<?php echo esc_url( $bbai_it_library_url ); ?>"
				class="bbai-li-surface__cta bbai-li-surface__cta--secondary"
			><?php esc_html_e( 'Open full library', 'beepbeep-ai-alt-text-generator' ); ?></a>
		</div>
	</div>

	<?php /* SSR table — shown immediately on first paint */ ?>
	<?php if ( $bbai_it_server_render && ! empty( $bbai_it_rows ) ) : ?>
	<table class="bbai-li-image-table__table" data-bbai-li-recent-table="1">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Image', 'beepbeep-ai-alt-text-generator' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Filename', 'beepbeep-ai-alt-text-generator' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'beepbeep-ai-alt-text-generator' ); ?></th>
				<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'beepbeep-ai-alt-text-generator' ); ?></span></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $bbai_it_rows as $bbai_it_row ) :
				$bbai_rid    = absint( $bbai_it_row['id'] ?? 0 );
				$bbai_rfile  = (string) ( $bbai_it_row['filename'] ?? '' );
				$bbai_rthumb = esc_url( (string) ( $bbai_it_row['thumb_url'] ?? '' ) );
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
					<span class="bbai-li-image-table__badge bbai-li-image-table__badge--optimized"><?php esc_html_e( 'Optimised', 'beepbeep-ai-alt-text-generator' ); ?></span>
				</td>
				<td class="bbai-li-image-table__col-action">
					<a
						href="<?php echo esc_url( add_query_arg( [ 'page' => 'bbai-library', 'attachment_id' => $bbai_rid ], admin_url( 'admin.php' ) ) ); ?>"
						class="bbai-li-image-table__row-btn button button-secondary"
					><?php esc_html_e( 'View', 'beepbeep-ai-alt-text-generator' ); ?></a>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php elseif ( $bbai_it_server_render ) : ?>
		<p class="bbai-li-image-table__empty"><?php esc_html_e( 'No images optimised yet.', 'beepbeep-ai-alt-text-generator' ); ?></p>
	<?php else : ?>
	<?php /* JS-only fallback feed (legacy — JS controller writes <li> entries) */ ?>
	<ul
		class="bbai-li-feed bbai-li-feed--recent"
		data-bbai-li-recent-activity="1"
		aria-label="<?php esc_attr_e( 'Recently processed images', 'beepbeep-ai-alt-text-generator' ); ?>"
	>
		<li class="bbai-li-feed__empty" data-bbai-li-feed-empty="1">
			<?php esc_html_e( 'Loading recent activity…', 'beepbeep-ai-alt-text-generator' ); ?>
		</li>
	</ul>
	<?php endif; ?>

</div>
