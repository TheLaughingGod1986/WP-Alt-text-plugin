<?php
/**
 * Filterable, paginated audit findings.
 *
 * Every row action delegates to an existing workflow: generation goes through
 * the plugin's own generator, manual editing through the WordPress attachment
 * editor, and review through the ALT Library. Nothing here is a second copy of
 * a system that already exists.
 *
 * $bbai_ui keys:
 * - results: query_issues() payload
 * - counts: count_by_filter() payload
 * - display: get_display_data() keyed by attachment id
 * - filter: active filter slug
 * - base_url: page URL without filter/paging args
 *
 * @package BeepBeep_AI
 * @since   4.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeepBeepAI\AltTextGenerator\Audit\Audit_Entitlement_Service;
use BeepBeepAI\AltTextGenerator\Audit\Audit_Filters;
use BeepBeepAI\AltTextGenerator\Audit\Audit_Issue;
use BeepBeepAI\AltTextGenerator\Audit\Image_Audit_Repository;

$bbai_ui       = isset( $bbai_ui ) && is_array( $bbai_ui ) ? $bbai_ui : array();
$bbai_results  = isset( $bbai_ui['results'] ) && is_array( $bbai_ui['results'] ) ? $bbai_ui['results'] : array();
$bbai_counts   = isset( $bbai_ui['counts'] ) && is_array( $bbai_ui['counts'] ) ? $bbai_ui['counts'] : array();
$bbai_display  = isset( $bbai_ui['display'] ) && is_array( $bbai_ui['display'] ) ? $bbai_ui['display'] : array();
$bbai_filter   = Audit_Filters::sanitize( $bbai_ui['filter'] ?? Audit_Filters::ALL );
$bbai_base_url = isset( $bbai_ui['base_url'] ) ? (string) $bbai_ui['base_url'] : '';

$bbai_rows        = isset( $bbai_results['rows'] ) && is_array( $bbai_results['rows'] ) ? $bbai_results['rows'] : array();
$bbai_total       = max( 0, (int) ( $bbai_results['total'] ?? 0 ) );
$bbai_page        = max( 1, (int) ( $bbai_results['page'] ?? 1 ) );
$bbai_total_pages = max( 0, (int) ( $bbai_results['total_pages'] ?? 0 ) );

$bbai_library_url = add_query_arg(
	array(
		'page'   => 'bbai-library',
		'status' => 'needs_review',
	),
	admin_url( 'admin.php' )
);

$bbai_filter_url = static function ( $slug ) use ( $bbai_base_url ) {
	return add_query_arg( array( 'audit_filter' => $slug ), $bbai_base_url );
};
?>
<div class="bbai-audit-issues" data-bbai-audit-issues>

	<nav class="bbai-audit-issues__filters" aria-label="<?php esc_attr_e( 'Filter audit findings', 'beepbeep-ai-alt-text-generator' ); ?>">
		<ul class="bbai-audit-issues__filter-list" role="list">
			<?php foreach ( Audit_Filters::all() as $bbai_slug => $bbai_definition ) : ?>
				<?php $bbai_is_active = ( $bbai_slug === $bbai_filter ); ?>
				<li>
					<a
						class="bbai-audit-issues__filter<?php echo $bbai_is_active ? ' is-active' : ''; ?>"
						href="<?php echo esc_url( $bbai_filter_url( $bbai_slug ) ); ?>"
						aria-current="<?php echo $bbai_is_active ? 'page' : 'false'; ?>"
						data-bbai-audit-filter="<?php echo esc_attr( $bbai_slug ); ?>"
					>
						<span class="bbai-audit-issues__filter-label"><?php echo esc_html( $bbai_definition['label'] ); ?></span>
						<span class="bbai-audit-issues__filter-count">
							<?php echo esc_html( number_format_i18n( (int) ( $bbai_counts[ $bbai_slug ] ?? 0 ) ) ); ?>
						</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<p class="bbai-audit-issues__filter-help"><?php echo esc_html( Audit_Filters::description( $bbai_filter ) ); ?></p>
	</nav>

	<?php if ( empty( $bbai_rows ) ) : ?>

		<div class="bbai-audit-issues__empty">
			<p>
				<?php
				if ( Audit_Filters::ALL === $bbai_filter ) {
					esc_html_e( 'No issues detected by the current audit rules.', 'beepbeep-ai-alt-text-generator' );
				} else {
					esc_html_e( 'No images match this filter.', 'beepbeep-ai-alt-text-generator' );
				}
				?>
			</p>
			<?php if ( Audit_Filters::ALL !== $bbai_filter ) : ?>
				<p>
					<a href="<?php echo esc_url( $bbai_filter_url( Audit_Filters::ALL ) ); ?>">
						<?php esc_html_e( 'Show all issues', 'beepbeep-ai-alt-text-generator' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>

	<?php else : ?>

		<p class="bbai-audit-issues__summary" aria-live="polite">
			<?php
			printf(
				/* translators: 1: first row on this page, 2: last row on this page, 3: total rows. */
				esc_html__( 'Showing %1$s–%2$s of %3$s images.', 'beepbeep-ai-alt-text-generator' ),
				esc_html( number_format_i18n( ( ( $bbai_page - 1 ) * (int) $bbai_results['per_page'] ) + 1 ) ),
				esc_html( number_format_i18n( min( $bbai_total, $bbai_page * (int) $bbai_results['per_page'] ) ) ),
				esc_html( number_format_i18n( $bbai_total ) )
			);
			?>
		</p>

		<div class="bbai-audit-issues__scroll">
			<table class="bbai-audit-issues__table widefat striped">
				<caption class="screen-reader-text">
					<?php esc_html_e( 'Images with audit findings, most serious first.', 'beepbeep-ai-alt-text-generator' ); ?>
				</caption>
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Image', 'beepbeep-ai-alt-text-generator' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Current alt text', 'beepbeep-ai-alt-text-generator' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Findings', 'beepbeep-ai-alt-text-generator' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Used on', 'beepbeep-ai-alt-text-generator' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Last scanned', 'beepbeep-ai-alt-text-generator' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'beepbeep-ai-alt-text-generator' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $bbai_rows as $bbai_row ) : ?>
						<?php
						$bbai_id      = (int) $bbai_row['attachment_id'];
						$bbai_meta    = isset( $bbai_display[ $bbai_id ] ) ? $bbai_display[ $bbai_id ] : array();
						$bbai_codes   = Audit_Issue::decode_codes( (string) $bbai_row['issue_codes'] );
						$bbai_alt     = trim( (string) ( $bbai_meta['alt'] ?? '' ) );
						$bbai_title   = trim( (string) ( $bbai_meta['title'] ?? '' ) );
						$bbai_thumb   = (string) ( $bbai_meta['thumbnail_url'] ?? '' );
						$bbai_edit    = (string) ( $bbai_meta['edit_link'] ?? '' );
						$bbai_status  = (string) $bbai_row['audit_status'];
						$bbai_settled = in_array( $bbai_status, Image_Audit_Repository::settled_statuses(), true );

						// "Generate" is only offered where the plugin can plausibly help.
						$bbai_can_generate = false;
						foreach ( $bbai_codes as $bbai_code ) {
							$bbai_def = Audit_Issue::definition( $bbai_code );
							if ( null !== $bbai_def && ! empty( $bbai_def['auto_fixable'] ) ) {
								$bbai_can_generate = true;
								break;
							}
						}
						?>
						<tr data-bbai-audit-row data-attachment-id="<?php echo esc_attr( (string) $bbai_id ); ?>">
							<td class="bbai-audit-issues__image">
								<?php if ( '' !== $bbai_thumb ) : ?>
									<img
										class="bbai-audit-issues__thumb"
										src="<?php echo esc_url( $bbai_thumb ); ?>"
										alt=""
										width="48"
										height="48"
										loading="lazy"
										decoding="async"
									/>
								<?php else : ?>
									<span class="bbai-audit-issues__thumb bbai-audit-issues__thumb--placeholder" aria-hidden="true"></span>
								<?php endif; ?>
								<span class="bbai-audit-issues__name">
									<?php
									echo esc_html(
										'' !== $bbai_title
											? $bbai_title
											: sprintf(
												/* translators: %d: attachment ID. */
												__( 'Image #%d', 'beepbeep-ai-alt-text-generator' ),
												$bbai_id
											)
									);
									?>
								</span>
							</td>

							<td class="bbai-audit-issues__alt" data-bbai-audit-alt>
								<?php if ( '' !== $bbai_alt ) : ?>
									<span class="bbai-audit-issues__alt-value"><?php echo esc_html( $bbai_alt ); ?></span>
								<?php else : ?>
									<span class="bbai-audit-issues__alt-empty">
										<?php esc_html_e( 'None', 'beepbeep-ai-alt-text-generator' ); ?>
									</span>
								<?php endif; ?>
							</td>

							<td class="bbai-audit-issues__findings" data-bbai-audit-findings>
								<?php if ( empty( $bbai_codes ) ) : ?>
									<span class="bbai-audit-badge bbai-audit-badge--resolved">
										<?php
										echo Image_Audit_Repository::STATUS_DECORATIVE === $bbai_status
											? esc_html__( 'Marked decorative', 'beepbeep-ai-alt-text-generator' )
											: esc_html__( 'No issues detected', 'beepbeep-ai-alt-text-generator' );
										?>
									</span>
								<?php else : ?>
									<?php foreach ( $bbai_codes as $bbai_code ) : ?>
										<?php bbai_ui_render( 'audit-issue-badge', array( 'code' => $bbai_code ) ); ?>
									<?php endforeach; ?>
								<?php endif; ?>

								<?php if ( (int) $bbai_row['duplicate_group_size'] > 1 ) : ?>
									<span class="bbai-audit-issues__note">
										<?php
										printf(
											/* translators: %s: number of images sharing this alt text. */
											esc_html( _n( 'Shared with %s other image', 'Shared with %s other images', (int) $bbai_row['duplicate_group_size'] - 1, 'beepbeep-ai-alt-text-generator' ) ),
											esc_html( number_format_i18n( (int) $bbai_row['duplicate_group_size'] - 1 ) )
										);
										?>
									</span>
								<?php endif; ?>
							</td>

							<td class="bbai-audit-issues__context">
								<?php echo esc_html( (string) $bbai_row['context_summary'] ); ?>
							</td>

							<td class="bbai-audit-issues__scanned">
								<?php
								$bbai_scanned_at = (string) ( $bbai_row['last_scanned_at'] ?? '' );
								if ( '' !== $bbai_scanned_at ) {
									$bbai_scanned_ts = strtotime( $bbai_scanned_at );
									echo esc_html(
										false !== $bbai_scanned_ts
											? sprintf(
												/* translators: %s: human-readable time difference. */
												__( '%s ago', 'beepbeep-ai-alt-text-generator' ),
												human_time_diff( $bbai_scanned_ts, time() )
											)
											: $bbai_scanned_at
									);
								} else {
									echo esc_html__( 'Not scanned', 'beepbeep-ai-alt-text-generator' );
								}
								?>
							</td>

							<td class="bbai-audit-issues__actions">
								<?php if ( $bbai_can_generate && ! $bbai_settled ) : ?>
									<button
										type="button"
										class="button button-small bbai-audit-issues__generate"
										data-bbai-audit-generate="<?php echo esc_attr( (string) $bbai_id ); ?>"
									>
										<?php
										echo '' !== $bbai_alt
											? esc_html__( 'Regenerate', 'beepbeep-ai-alt-text-generator' )
											: esc_html__( 'Generate', 'beepbeep-ai-alt-text-generator' );
										?>
									</button>
								<?php endif; ?>

								<?php if ( '' !== $bbai_edit ) : ?>
									<a class="button button-small button-link" href="<?php echo esc_url( $bbai_edit ); ?>">
										<?php esc_html_e( 'Edit', 'beepbeep-ai-alt-text-generator' ); ?>
									</a>
								<?php endif; ?>

								<a class="button button-small button-link" href="<?php echo esc_url( $bbai_library_url ); ?>">
									<?php esc_html_e( 'Review', 'beepbeep-ai-alt-text-generator' ); ?>
								</a>

								<?php if ( Image_Audit_Repository::STATUS_DECORATIVE === $bbai_status ) : ?>
									<button
										type="button"
										class="button button-small button-link bbai-audit-issues__decorative"
										data-bbai-audit-decorative="<?php echo esc_attr( (string) $bbai_id ); ?>"
										data-decorative="0"
									>
										<?php esc_html_e( 'Not decorative', 'beepbeep-ai-alt-text-generator' ); ?>
									</button>
								<?php elseif ( ! $bbai_settled ) : ?>
									<button
										type="button"
										class="button button-small button-link bbai-audit-issues__decorative"
										data-bbai-audit-decorative="<?php echo esc_attr( (string) $bbai_id ); ?>"
										data-decorative="1"
										title="<?php esc_attr_e( 'Decorative images carry no meaning and should normally use an empty alt attribute.', 'beepbeep-ai-alt-text-generator' ); ?>"
									>
										<?php esc_html_e( 'Mark decorative', 'beepbeep-ai-alt-text-generator' ); ?>
									</button>

									<button
										type="button"
										class="button button-small button-link bbai-audit-issues__dismiss"
										data-bbai-audit-dismiss="<?php echo esc_attr( (string) $bbai_id ); ?>"
										title="<?php esc_attr_e( 'Hide this finding without changing the image.', 'beepbeep-ai-alt-text-generator' ); ?>"
									>
										<?php esc_html_e( 'Dismiss', 'beepbeep-ai-alt-text-generator' ); ?>
									</button>
								<?php endif; ?>

								<span class="bbai-audit-issues__row-status" data-bbai-audit-row-status role="status"></span>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php if ( Audit_Entitlement_Service::is_limited( $bbai_total ) ) : ?>
			<div class="bbai-audit-issues__upgrade">
				<p>
					<?php
					printf(
						/* translators: 1: number of findings shown, 2: total findings. */
						esc_html__( 'Your plan shows the first %1$s of %2$s findings. Upgrade to work through the full list and use bulk actions.', 'beepbeep-ai-alt-text-generator' ),
						esc_html( number_format_i18n( Audit_Entitlement_Service::result_limit() ) ),
						esc_html( number_format_i18n( $bbai_total ) )
					);
					?>
				</p>
				<a
					class="button button-primary"
					href="<?php echo esc_url( add_query_arg( array( 'page' => 'bbai-settings' ), admin_url( 'admin.php' ) ) ); ?>"
					data-bbai-audit-upgrade="audit_issues_table"
				>
					<?php esc_html_e( 'See plans', 'beepbeep-ai-alt-text-generator' ); ?>
				</a>
			</div>
		<?php endif; ?>

		<?php if ( $bbai_total_pages > 1 ) : ?>
			<nav class="bbai-audit-issues__pagination" aria-label="<?php esc_attr_e( 'Audit findings pages', 'beepbeep-ai-alt-text-generator' ); ?>">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg( 'audit_page', '%#%', $bbai_filter_url( $bbai_filter ) ),
							'format'    => '',
							'current'   => $bbai_page,
							'total'     => $bbai_total_pages,
							'prev_text' => __( '&laquo; Previous', 'beepbeep-ai-alt-text-generator' ),
							'next_text' => __( 'Next &raquo;', 'beepbeep-ai-alt-text-generator' ),
						)
					)
				);
				?>
			</nav>
		<?php endif; ?>

	<?php endif; ?>
</div>
