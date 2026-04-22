<?php
/**
 * Logged-in surface: QuickStartChecklist (NO_IMAGES state).
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

$bbai_li_surface = $bbai_li_state['surface']['props'] ?? [];
$bbai_li_steps   = is_array( $bbai_li_surface['steps'] ?? null ) ? $bbai_li_surface['steps'] : [];
?>

<div class="bbai-li-surface bbai-li-surface--quick-start" data-bbai-li-surface="QuickStartChecklist">

	<h3 class="bbai-li-surface__heading">
		<?php esc_html_e( 'Get started', 'beepbeep-ai-alt-text-generator' ); ?>
	</h3>

	<?php if ( ! empty( $bbai_li_steps ) ) : ?>
		<ol class="bbai-li-checklist">
			<?php foreach ( $bbai_li_steps as $bbai_li_step ) :
				$bbai_li_step_label = esc_html( (string) ( $bbai_li_step['label'] ?? '' ) );
				$bbai_li_step_href  = esc_url( (string) ( $bbai_li_step['href'] ?? '#' ) );
			?>
				<li class="bbai-li-checklist__item">
					<a href="<?php echo $bbai_li_step_href; ?>" class="bbai-li-checklist__link">
						<?php echo $bbai_li_step_label; ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ol>
	<?php endif; ?>

</div>
