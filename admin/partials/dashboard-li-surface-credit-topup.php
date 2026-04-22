<?php
/**
 * Logged-in surface: CreditTopUp (QUOTA_EXHAUSTED state).
 *
 * Operational context only — no marketing copy.
 * Plan table is populated by JS from /plans.
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

$bbai_li_props         = $bbai_li_state['surface']['props'] ?? [];
$bbai_li_queued_count  = max( 0, (int) ( $bbai_li_props['queued_count'] ?? 0 ) );
$bbai_li_billing_url   = esc_url( admin_url( 'admin.php?page=bbai-credit-usage' ) );
?>

<div
	class="bbai-li-surface bbai-li-surface--credit-topup"
	data-bbai-li-surface="CreditTopUp"
>

	<div class="bbai-li-surface__header">
		<h3 class="bbai-li-surface__heading">
			<?php esc_html_e( 'Add credits', 'beepbeep-ai-alt-text-generator' ); ?>
		</h3>
		<?php if ( $bbai_li_queued_count > 0 ) : ?>
			<p class="bbai-li-surface__subheading">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: number of images waiting */
						_n( '%s image is waiting to be processed.', '%s images are waiting to be processed.', $bbai_li_queued_count, 'beepbeep-ai-alt-text-generator' ),
						number_format_i18n( $bbai_li_queued_count )
					)
				);
				?>
			</p>
		<?php endif; ?>
	</div>

	<?php /* Plan rows populated by JS controller from /plans */ ?>
	<div
		class="bbai-li-credit-plans"
		data-bbai-li-credit-plans="1"
		aria-label="<?php esc_attr_e( 'Credit plans', 'beepbeep-ai-alt-text-generator' ); ?>"
	>
		<p class="bbai-li-credit-plans__loading" data-bbai-li-plans-loading="1">
			<?php esc_html_e( 'Loading plans…', 'beepbeep-ai-alt-text-generator' ); ?>
		</p>
	</div>

	<div class="bbai-li-surface__actions">
		<a
			href="<?php echo $bbai_li_billing_url; ?>"
			class="bbai-li-surface__cta bbai-li-surface__cta--primary"
			data-action="add-credits"
		><?php esc_html_e( 'Manage billing', 'beepbeep-ai-alt-text-generator' ); ?></a>
	</div>

</div>
