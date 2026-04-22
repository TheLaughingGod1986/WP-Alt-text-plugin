<?php
/**
 * Logged-in dashboard stat strip: pills + usage bar.
 *
 * Renders exclusively from $bbai_li_state. Zero-count pills are never rendered.
 * Pills and the usage bar come only from resolver output.
 *
 * Required in parent scope:
 *   $bbai_li_state  array  The full DashboardState object from the resolver.
 *
 * @package BeepBeep_AI
 * @since   5.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $bbai_li_state ) || ! is_array( $bbai_li_state ) ) {
	?>
	<div class="bbai-li-stat-strip bbai-li-stat-strip--fallback" data-bbai-li-stat-strip="1" aria-hidden="true"></div>
	<?php
	return;
}

$bbai_li_pills = is_array( $bbai_li_state['pills'] ?? null ) ? $bbai_li_state['pills'] : [];
$bbai_li_usage = is_array( $bbai_li_state['usage'] ?? null ) ? $bbai_li_state['usage'] : [];
$bbai_li_state_id = $bbai_li_state['state'] ?? '';

// Usage bar values.
$bbai_li_usage_hidden = ! empty( $bbai_li_usage['hidden'] );
$bbai_li_usage_pct    = max( 0, min( 100, (int) ( $bbai_li_usage['pct'] ?? 0 ) ) );
$bbai_li_usage_color  = sanitize_html_class( $bbai_li_usage['color'] ?? 'green' );
$bbai_li_usage_label  = esc_html( (string) ( $bbai_li_usage['label'] ?? '' ) );
$bbai_li_billing_url  = admin_url( 'admin.php?page=bbai-credit-usage' );
?>

<div
	class="bbai-li-stat-strip"
	data-bbai-li-stat-strip="1"
	data-bbai-li-state="<?php echo esc_attr( $bbai_li_state_id ); ?>"
>

	<?php /* ── Pills ─────────────────────────────────────────────────────── */ ?>
	<?php if ( ! empty( $bbai_li_pills ) ) : ?>
		<ul
			class="bbai-li-pills"
			data-bbai-li-pills="1"
			aria-label="<?php esc_attr_e( 'Image status counts', 'beepbeep-ai-alt-text-generator' ); ?>"
		>
			<?php foreach ( $bbai_li_pills as $bbai_li_pill ) :
				$bbai_li_pill_id    = sanitize_html_class( (string) ( $bbai_li_pill['id'] ?? '' ) );
				$bbai_li_pill_color = sanitize_html_class( (string) ( $bbai_li_pill['color'] ?? 'gray' ) );
				$bbai_li_pill_label = esc_html( (string) ( $bbai_li_pill['label'] ?? '' ) );
				$bbai_li_pill_count = (int) ( $bbai_li_pill['count'] ?? 0 );
				if ( $bbai_li_pill_count <= 0 ) continue;
			?>
				<li
					class="bbai-li-pill bbai-li-pill--<?php echo $bbai_li_pill_id; ?> bbai-li-pill--<?php echo $bbai_li_pill_color; ?>"
					data-bbai-li-pill="<?php echo $bbai_li_pill_id; ?>"
					aria-label="<?php echo $bbai_li_pill_label; ?>"
				>
					<span class="bbai-li-pill__count"><?php echo number_format_i18n( $bbai_li_pill_count ); ?></span>
					<span class="bbai-li-pill__label"><?php echo $bbai_li_pill_label; ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php /* ── Usage bar ─────────────────────────────────────────────────── */ ?>
	<?php if ( ! $bbai_li_usage_hidden ) : ?>
		<a
			class="bbai-li-usage-bar bbai-li-usage-bar--<?php echo $bbai_li_usage_color; ?>"
			href="<?php echo esc_url( $bbai_li_billing_url ); ?>"
			data-bbai-li-usage-bar="1"
			title="<?php echo $bbai_li_usage_label; ?>"
			aria-label="<?php echo $bbai_li_usage_label; ?>"
		>
			<div class="bbai-li-usage-bar__track">
				<div
					class="bbai-li-usage-bar__fill"
					style="width: <?php echo esc_attr( $bbai_li_usage_pct ); ?>%;"
					data-bbai-li-usage-pct="<?php echo esc_attr( $bbai_li_usage_pct ); ?>"
				></div>
			</div>
			<span class="bbai-li-usage-bar__label"><?php echo $bbai_li_usage_label; ?></span>
		</a>
	<?php endif; ?>

</div>
