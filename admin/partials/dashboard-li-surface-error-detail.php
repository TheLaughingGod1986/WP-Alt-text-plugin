<?php
/**
 * Logged-in surface: ErrorDetailPanel (ERROR state).
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

$bbai_li_props    = $bbai_li_state['surface']['props'] ?? [];
$bbai_li_err_code = sanitize_key( (string) ( $bbai_li_props['error_code'] ?? '' ) );
$bbai_li_err_msg  = esc_html( (string) ( $bbai_li_props['error_message'] ?? '' ) );
$bbai_li_is_no_key = 'NO_API_KEY' === $bbai_li_err_code;
?>

<div
	class="bbai-li-surface bbai-li-surface--error-detail"
	data-bbai-li-surface="ErrorDetailPanel"
	data-bbai-li-error-code="<?php echo esc_attr( $bbai_li_err_code ); ?>"
>

	<div class="bbai-li-surface__header">
		<h3 class="bbai-li-surface__heading">
			<?php if ( $bbai_li_is_no_key ) : ?>
				<?php esc_html_e( 'Setup required', 'beepbeep-ai-alt-text-generator' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Error details', 'beepbeep-ai-alt-text-generator' ); ?>
			<?php endif; ?>
		</h3>
	</div>

	<?php if ( $bbai_li_err_msg ) : ?>
		<p class="bbai-li-surface__message bbai-li-surface__message--error">
			<?php echo $bbai_li_err_msg; ?>
		</p>
	<?php endif; ?>

	<?php if ( $bbai_li_is_no_key ) : ?>
		<a
			href="<?php echo esc_url( admin_url( 'admin.php?page=bbai-settings' ) ); ?>"
			class="bbai-li-surface__action-link"
		>
			<?php esc_html_e( 'Open settings →', 'beepbeep-ai-alt-text-generator' ); ?>
		</a>
	<?php else : ?>
		<?php /* Failed items list — populated by JS controller from /list?scope=failed */ ?>
		<ul
			class="bbai-li-feed bbai-li-feed--failed"
			data-bbai-li-failed-items="1"
			aria-label="<?php esc_attr_e( 'Failed images', 'beepbeep-ai-alt-text-generator' ); ?>"
		>
			<li class="bbai-li-feed__empty" data-bbai-li-feed-empty="1">
				<?php esc_html_e( 'Loading failed items…', 'beepbeep-ai-alt-text-generator' ); ?>
			</li>
		</ul>

		<div class="bbai-li-surface__actions">
			<a
				href="#"
				class="bbai-li-surface__cta bbai-li-surface__cta--primary"
				data-action="retry-failed"
			><?php esc_html_e( 'Retry failed items', 'beepbeep-ai-alt-text-generator' ); ?></a>
		</div>
	<?php endif; ?>

</div>
