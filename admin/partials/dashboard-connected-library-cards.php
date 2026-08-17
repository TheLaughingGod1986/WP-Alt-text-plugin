<?php
/**
 * Connected-user dashboard library card list (PHP SSR, same card UI as trial preview).
 *
 * Expected scope:
 * - $bbai_connected_lib_rows           array  Row payloads (trial preview shape).
 * - $bbai_connected_lib_heading          string Section title.
 * - $bbai_connected_lib_description      string Optional subtitle (empty to omit).
 * - $bbai_connected_lib_empty_title      string Empty state title.
 * - $bbai_connected_lib_empty_copy       string Empty state body.
 *
 * @package BeepBeep_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bbai_connected_lib_rows = isset( $bbai_connected_lib_rows ) && is_array( $bbai_connected_lib_rows ) ? $bbai_connected_lib_rows : [];
$bbai_connected_lib_heading = isset( $bbai_connected_lib_heading ) ? (string) $bbai_connected_lib_heading : '';
$bbai_connected_lib_description = isset( $bbai_connected_lib_description ) ? (string) $bbai_connected_lib_description : '';
$bbai_connected_lib_empty_title = isset( $bbai_connected_lib_empty_title ) ? (string) $bbai_connected_lib_empty_title : '';
$bbai_connected_lib_empty_copy = isset( $bbai_connected_lib_empty_copy ) ? (string) $bbai_connected_lib_empty_copy : '';

$bbai_lib_card_row_path = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/components/library-dashboard-card-row.php';
$bbai_lib_row_readable = is_readable( $bbai_lib_card_row_path );
?>
<section
	class="bbai-dashboard-trial-preview bbai-dashboard-connected-library"
	data-bbai-connected-library="1"
	aria-labelledby="bbai-dashboard-connected-library-heading"
>
	<div class="bbai-dashboard-trial-preview__header">
		<div class="bbai-dashboard-trial-preview__copy">
			<h2 id="bbai-dashboard-connected-library-heading" class="bbai-dashboard-trial-preview__title">
				<?php echo esc_html( $bbai_connected_lib_heading ); ?>
			</h2>
			<?php if ( '' !== $bbai_connected_lib_description ) : ?>
				<p class="bbai-dashboard-trial-preview__description">
					<?php echo esc_html( $bbai_connected_lib_description ); ?>
				</p>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( ! empty( $bbai_connected_lib_rows ) && $bbai_lib_row_readable ) : ?>
		<div class="bbai-dashboard-trial-preview__list" role="list">
			<?php foreach ( $bbai_connected_lib_rows as $bbai_preview_row ) : ?>
				<?php
				if ( ! is_array( $bbai_preview_row ) ) {
					continue;
				}
				$bbai_dash_card_mode = 'connected';
				require $bbai_lib_card_row_path;
				?>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<div class="bbai-dashboard-trial-preview__empty bbai-dashboard-connected-library__empty">
			<p class="bbai-dashboard-trial-preview__empty-title"><?php echo esc_html( $bbai_connected_lib_empty_title ); ?></p>
			<p class="bbai-dashboard-trial-preview__empty-copy"><?php echo esc_html( $bbai_connected_lib_empty_copy ); ?></p>
		</div>
	<?php endif; ?>
</section>
