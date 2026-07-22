<?php
/**
 * nAi Guest Audit — conversion-focused signed-out dashboard.
 *
 * Replaces the plain guest funnel hero with a "Website Image SEO &
 * Accessibility Audit" surface that previews the product's value (audit
 * KPIs, accessibility + SEO findings, time saved, before/after, Autopilot)
 * and drives sign-in / account creation through the existing auth modal.
 *
 * Consumes the scan counts prepared by dashboard-body.php:
 * $bbai_state_missing_count, $bbai_state_weak_count,
 * $bbai_state_optimized_count, $bbai_state_total_images.
 *
 * @package BeepBeepAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals are scoped to this included partial.

$bbai_ga_missing   = isset( $bbai_state_missing_count ) ? max( 0, (int) $bbai_state_missing_count ) : 0;
$bbai_ga_weak      = isset( $bbai_state_weak_count ) ? max( 0, (int) $bbai_state_weak_count ) : 0;
$bbai_ga_optimized = isset( $bbai_state_optimized_count ) ? max( 0, (int) $bbai_state_optimized_count ) : 0;
$bbai_ga_total     = isset( $bbai_state_total_images ) ? max( 0, (int) $bbai_state_total_images ) : 0;

$bbai_ga_covered   = max( 0, $bbai_ga_total - $bbai_ga_missing );
$bbai_ga_coverage  = $bbai_ga_total > 0 ? (int) round( ( $bbai_ga_covered / $bbai_ga_total ) * 100 ) : 0;
$bbai_ga_seo       = $bbai_ga_total > 0 ? (int) round( ( $bbai_ga_optimized / $bbai_ga_total ) * 100 ) : 0;
$bbai_ga_attention = $bbai_ga_missing + $bbai_ga_weak;
$bbai_ga_fix_count = $bbai_ga_missing > 0 ? $bbai_ga_missing : $bbai_ga_weak;

if ( $bbai_ga_coverage >= 95 ) {
	$bbai_ga_wcag_risk = __( 'Low', 'beepbeep-ai-alt-text-generator' );
} elseif ( $bbai_ga_coverage >= 50 ) {
	$bbai_ga_wcag_risk = __( 'Medium', 'beepbeep-ai-alt-text-generator' );
} else {
	$bbai_ga_wcag_risk = __( 'High', 'beepbeep-ai-alt-text-generator' );
}

// Estimated improvement is a marketing range (mirrors the design), not a measurement.
$bbai_ga_gain = __( '12–18%', 'beepbeep-ai-alt-text-generator' );

// Manual effort estimate: ~30s of writing per image.
$bbai_ga_manual_minutes = (int) ceil( $bbai_ga_fix_count * 0.5 );
$bbai_ga_saved_minutes  = max( 0, $bbai_ga_manual_minutes - 1 );

// Ring tone tracks urgency: green when essentially done, amber mid, red when poor.
if ( $bbai_ga_coverage >= 95 ) {
	$bbai_ga_ring_tone = 'nai-ring--ok';
} elseif ( $bbai_ga_coverage >= 50 ) {
	$bbai_ga_ring_tone = 'nai-ring--warn';
} else {
	$bbai_ga_ring_tone = 'nai-ring--danger';
}

/**
 * Inline icon helper. Mirrors the design's Icon component.
 */
$bbai_ga_icon = static function ( string $name, int $size = 16, float $stroke = 1.75 ): string {
	if ( 'logo' === $name ) {
		return sprintf(
			'<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="6" fill="currentColor"/><path d="M7.5 16V9.5l4 5V9.5M14.5 9.5h2.5M15.75 9.5V16" stroke="white" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
			$size
		);
	}
	$paths = array(
		'alert'       => '<path d="M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><path d="M12 9v4M12 17h.01"/>',
		'check'       => '<path d="m5 12 5 5 9-11"/>',
		'shield'      => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6l-8-3Z"/>',
		'trend'       => '<path d="m3 17 6-6 4 4 8-8"/><path d="M14 7h7v7"/>',
		'zap'         => '<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/>',
		'info'        => '<circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v5h1"/>',
		'x'           => '<path d="M18 6 6 18M6 6l12 12"/>',
		'clock'       => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'arrow-right' => '<path d="M5 12h14M13 5l7 7-7 7"/>',
	);
	$body  = $paths[ $name ] ?? '';
	return sprintf(
		'<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="%2$s" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%3$s</svg>',
		$size,
		esc_attr( (string) $stroke ),
		$body // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG path data only.
	);
};

/**
 * Sign In / Create Account CTA pair, wired to the existing auth modal.
 */
$bbai_ga_cta_pair = static function ( string $context ) use ( $bbai_ga_icon ): void {
	?>
	<div class="nai-audit__cta-row">
		<a
			href="#"
			class="nai-btn nai-btn--lg nai-btn--primary"
			data-action="show-auth-modal"
			data-auth-tab="login"
			data-bbai-modal-context="login"
			data-bbai-source="<?php echo esc_attr( 'guest_audit_' . $context ); ?>"
		>
			<?php echo $bbai_ga_icon( 'arrow-right', 16, 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?>
			<?php esc_html_e( 'Sign In', 'beepbeep-ai-alt-text-generator' ); ?>
		</a>
		<a
			href="#"
			class="nai-btn nai-btn--lg nai-btn--secondary"
			data-action="show-auth-modal"
			data-auth-tab="register"
			data-bbai-modal-context="register"
			data-bbai-source="<?php echo esc_attr( 'guest_audit_' . $context ); ?>"
		>
			<?php esc_html_e( 'Create Account', 'beepbeep-ai-alt-text-generator' ); ?>
		</a>
	</div>
	<?php
};

/**
 * Audit metric row list.
 *
 * @param array $metrics Rows of [label, value, tone].
 */
$bbai_ga_metric_rows = static function ( array $metrics ): void {
	?>
	<div class="nai-audit__metrics">
		<?php foreach ( $metrics as $bbai_ga_metric ) : ?>
			<div class="nai-audit__metric-row">
				<span class="nai-audit__metric-label"><?php echo esc_html( (string) $bbai_ga_metric['label'] ); ?></span>
				<span class="nai-mono nai-tnum nai-audit__metric-value nai-audit__metric-value--<?php echo esc_attr( (string) $bbai_ga_metric['tone'] ); ?>"><?php echo esc_html( (string) $bbai_ga_metric['value'] ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
};

/**
 * SVG progress ring (matches .nai-ring markup used by the connected dashboard).
 */
$bbai_ga_ring_open = static function ( int $size, int $stroke, int $pct, string $tone_class ): void {
	$bbai_ga_r    = ( $size - $stroke ) / 2;
	$bbai_ga_circ = 2 * M_PI * $bbai_ga_r;
	$bbai_ga_dash = ( max( 0, min( 100, $pct ) ) / 100 ) * $bbai_ga_circ;
	?>
	<div class="nai-ring <?php echo esc_attr( $tone_class ); ?>" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: percentage. */ __( 'Accessibility coverage: %s%%', 'beepbeep-ai-alt-text-generator' ), number_format_i18n( $pct ) ) ); ?>">
		<svg width="<?php echo (int) $size; ?>" height="<?php echo (int) $size; ?>">
			<circle class="nai-ring__track" cx="<?php echo (int) ( $size / 2 ); ?>" cy="<?php echo (int) ( $size / 2 ); ?>" r="<?php echo esc_attr( (string) $bbai_ga_r ); ?>" stroke-width="<?php echo (int) $stroke; ?>"/>
			<circle class="nai-ring__fill" cx="<?php echo (int) ( $size / 2 ); ?>" cy="<?php echo (int) ( $size / 2 ); ?>" r="<?php echo esc_attr( (string) $bbai_ga_r ); ?>" stroke-width="<?php echo (int) $stroke; ?>" stroke-dasharray="<?php echo esc_attr( sprintf( '%F %F', $bbai_ga_dash, $bbai_ga_circ ) ); ?>"/>
		</svg>
		<div class="nai-ring__center">
	<?php
};
?>
<section
	class="nai-screen nai-screen--guest-audit nai-audit"
	data-bbai-guest-audit="1"
	aria-labelledby="bbai-guest-audit-heading"
>

	<?php /* Header row — brand + signed-out badge */ ?>
	<div class="nai-audit__header">
		<div class="nai-audit__brand">
			<span class="nai-audit__brand-logo"><?php echo $bbai_ga_icon( 'logo', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?></span>
			<span class="nai-audit__brand-name"><?php esc_html_e( 'BeepBeep AI', 'beepbeep-ai-alt-text-generator' ); ?></span>
			<span class="nai-audit__brand-divider" aria-hidden="true"></span>
			<span class="nai-audit__brand-sub"><?php esc_html_e( 'Image SEO', 'beepbeep-ai-alt-text-generator' ); ?></span>
		</div>
		<span class="nai-audit__signedout-chip">
			<span class="nai-audit__signedout-dot" aria-hidden="true"></span>
			<?php esc_html_e( 'Signed Out', 'beepbeep-ai-alt-text-generator' ); ?>
		</span>
	</div>

	<?php /* HERO — headline + CTAs, paired with the headline circular progress */ ?>
	<div class="nai-audit__hero">
		<div class="nai-card nai-audit__hero-card">
			<?php if ( $bbai_ga_attention > 0 ) : ?>
				<span class="nai-audit__hero-pill nai-audit__hero-pill--warn">
					<?php echo $bbai_ga_icon( 'alert', 12 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?>
					<?php esc_html_e( 'Audit complete · action needed', 'beepbeep-ai-alt-text-generator' ); ?>
				</span>
			<?php else : ?>
				<span class="nai-audit__hero-pill nai-audit__hero-pill--ok">
					<?php echo $bbai_ga_icon( 'check', 12, 2.6 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?>
					<?php esc_html_e( 'Audit complete', 'beepbeep-ai-alt-text-generator' ); ?>
				</span>
			<?php endif; ?>
			<h1 id="bbai-guest-audit-heading" class="nai-audit__hero-title">
				<?php
				if ( $bbai_ga_attention > 0 ) {
					esc_html_e( 'Your website has images that need attention', 'beepbeep-ai-alt-text-generator' );
				} else {
					esc_html_e( 'See your full image SEO and accessibility report', 'beepbeep-ai-alt-text-generator' );
				}
				?>
			</h1>
			<p class="nai-audit__hero-sub">
				<?php if ( $bbai_ga_fix_count > 0 ) : ?>
					<?php
					printf(
						/* translators: %s: number of images, wrapped in a highlight span. */
						esc_html__( 'Sign in to see your full accessibility and image SEO report — and fix %s in minutes.', 'beepbeep-ai-alt-text-generator' ),
						'<span class="nai-audit__hero-sub-strong">' . esc_html(
							sprintf(
								/* translators: %s: number of images. */
								_n( '%s image', '%s images', $bbai_ga_fix_count, 'beepbeep-ai-alt-text-generator' ),
								number_format_i18n( $bbai_ga_fix_count )
							)
						) . '</span>'
					);
					?>
				<?php else : ?>
					<?php esc_html_e( 'Sign in to see your full accessibility and image SEO report.', 'beepbeep-ai-alt-text-generator' ); ?>
				<?php endif; ?>
			</p>
			<?php $bbai_ga_cta_pair( 'hero' ); ?>
			<div class="nai-audit__hero-reassure">
				<?php
				$bbai_ga_reassurances = array(
					__( 'No credit card', 'beepbeep-ai-alt-text-generator' ),
					__( 'WCAG-aware', 'beepbeep-ai-alt-text-generator' ),
					__( 'Set up in 1 minute', 'beepbeep-ai-alt-text-generator' ),
				);
				foreach ( $bbai_ga_reassurances as $bbai_ga_reassurance ) :
					?>
					<span class="nai-audit__hero-reassure-item">
						<?php echo $bbai_ga_icon( 'check', 12, 2.6 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?>
						<?php echo esc_html( $bbai_ga_reassurance ); ?>
					</span>
				<?php endforeach; ?>
			</div>
		</div>

		<?php /* Headline circular progress */ ?>
		<div class="nai-card nai-audit__hero-ring-card">
			<div class="nai-eyebrow"><?php esc_html_e( 'Accessibility coverage', 'beepbeep-ai-alt-text-generator' ); ?></div>
			<?php $bbai_ga_ring_open( 148, 12, $bbai_ga_coverage, $bbai_ga_ring_tone ); ?>
				<span class="nai-mono nai-tnum nai-audit__ring-value"><?php echo esc_html( number_format_i18n( $bbai_ga_coverage ) ); ?><span class="nai-audit__ring-unit">%</span></span>
			</div>
		</div>
			<div class="nai-audit__ring-caption">
				<?php if ( $bbai_ga_fix_count > 0 ) : ?>
					<?php
					printf(
						/* translators: %s: number of images, wrapped in a highlight span. */
						esc_html__( '%s need attention', 'beepbeep-ai-alt-text-generator' ),
						'<span class="nai-mono nai-tnum nai-audit__ring-caption-count">' . esc_html(
							sprintf(
								/* translators: %s: number of images. */
								_n( '%s image', '%s images', $bbai_ga_fix_count, 'beepbeep-ai-alt-text-generator' ),
								number_format_i18n( $bbai_ga_fix_count )
							)
						) . '</span>'
					);
					?>
				<?php else : ?>
					<?php esc_html_e( 'All images covered', 'beepbeep-ai-alt-text-generator' ); ?>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<?php /* KPI ROW */ ?>
	<div class="nai-audit__section-label"><?php esc_html_e( 'Website health overview', 'beepbeep-ai-alt-text-generator' ); ?></div>
	<div class="nai-audit__kpi-grid">
		<div class="nai-card nai-audit__kpi">
			<div class="nai-audit__kpi-head">
				<span class="nai-audit__kpi-label"><?php esc_html_e( 'Accessibility coverage', 'beepbeep-ai-alt-text-generator' ); ?></span>
				<span class="nai-audit__kpi-icon nai-audit__kpi-icon--warn"><?php echo $bbai_ga_icon( 'shield', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?></span>
			</div>
			<div class="nai-mono nai-tnum nai-audit__kpi-value"><?php echo esc_html( number_format_i18n( $bbai_ga_coverage ) ); ?>%</div>
			<div class="nai-audit__kpi-foot"><?php esc_html_e( 'Goal: 95%+', 'beepbeep-ai-alt-text-generator' ); ?></div>
		</div>
		<div class="nai-card nai-audit__kpi">
			<div class="nai-audit__kpi-head">
				<span class="nai-audit__kpi-label"><?php esc_html_e( 'Images covered', 'beepbeep-ai-alt-text-generator' ); ?></span>
				<span class="nai-audit__kpi-icon nai-audit__kpi-icon--ok"><?php echo $bbai_ga_icon( 'check', 13, 2.2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?></span>
			</div>
			<div class="nai-mono nai-tnum nai-audit__kpi-value"><?php echo esc_html( number_format_i18n( $bbai_ga_covered ) ); ?></div>
			<div class="nai-audit__kpi-foot">
				<?php
				printf(
					/* translators: %s: total number of images. */
					esc_html__( 'of %s total', 'beepbeep-ai-alt-text-generator' ),
					esc_html( number_format_i18n( $bbai_ga_total ) )
				);
				?>
			</div>
		</div>
		<div class="nai-card nai-audit__kpi">
			<div class="nai-audit__kpi-head">
				<span class="nai-audit__kpi-label"><?php esc_html_e( 'Missing ALT text', 'beepbeep-ai-alt-text-generator' ); ?></span>
				<span class="nai-audit__kpi-icon nai-audit__kpi-icon--danger"><?php echo $bbai_ga_icon( 'alert', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?></span>
			</div>
			<div class="nai-mono nai-tnum nai-audit__kpi-value"><?php echo esc_html( number_format_i18n( $bbai_ga_missing ) ); ?></div>
			<div class="nai-audit__kpi-foot"><?php esc_html_e( 'Blocking screen readers', 'beepbeep-ai-alt-text-generator' ); ?></div>
		</div>
		<div class="nai-card nai-audit__kpi">
			<div class="nai-audit__kpi-head">
				<span class="nai-audit__kpi-label"><?php esc_html_e( 'SEO readiness', 'beepbeep-ai-alt-text-generator' ); ?></span>
				<span class="nai-audit__kpi-icon nai-audit__kpi-icon--primary"><?php echo $bbai_ga_icon( 'trend', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?></span>
			</div>
			<div class="nai-mono nai-tnum nai-audit__kpi-value"><?php echo esc_html( number_format_i18n( $bbai_ga_seo ) ); ?>%</div>
			<div class="nai-audit__kpi-foot">
				<?php
				printf(
					/* translators: %s: estimated improvement range. */
					esc_html__( '+%s potential', 'beepbeep-ai-alt-text-generator' ),
					esc_html( $bbai_ga_gain )
				);
				?>
			</div>
		</div>
	</div>

	<?php /* AUDIT CARDS — accessibility + SEO */ ?>
	<div class="nai-audit__cards-grid">
		<div class="nai-card nai-audit__audit-card">
			<div class="nai-audit__audit-head">
				<span class="nai-audit__audit-icon nai-audit__audit-icon--danger"><?php echo $bbai_ga_icon( 'shield', 17 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?></span>
				<div>
					<div class="nai-eyebrow"><?php esc_html_e( 'Accessibility', 'beepbeep-ai-alt-text-generator' ); ?></div>
					<h3 class="nai-audit__audit-title"><?php esc_html_e( 'Accessibility Overview', 'beepbeep-ai-alt-text-generator' ); ?></h3>
				</div>
			</div>
			<div class="nai-audit__callout nai-audit__callout--danger">
				<?php echo $bbai_ga_icon( 'alert', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?>
				<span>
					<?php
					printf(
						/* translators: %s: number of images. */
						esc_html( _n( '%s image cannot currently be described properly by screen readers.', '%s images cannot currently be described properly by screen readers.', $bbai_ga_missing, 'beepbeep-ai-alt-text-generator' ) ),
						esc_html( number_format_i18n( $bbai_ga_missing ) )
					);
					?>
				</span>
			</div>
			<?php
			$bbai_ga_metric_rows(
				array(
					array(
						'label' => __( 'Images missing ALT text', 'beepbeep-ai-alt-text-generator' ),
						'value' => number_format_i18n( $bbai_ga_missing ),
						'tone'  => 'danger',
					),
					array(
						'label' => __( 'Generic ALT text detected', 'beepbeep-ai-alt-text-generator' ),
						'value' => number_format_i18n( $bbai_ga_weak ),
						'tone'  => 'warn',
					),
					array(
						'label' => __( 'Accessibility coverage score', 'beepbeep-ai-alt-text-generator' ),
						'value' => number_format_i18n( $bbai_ga_coverage ) . '%',
						'tone'  => 'warn',
					),
					array(
						'label' => __( 'WCAG risk level', 'beepbeep-ai-alt-text-generator' ),
						'value' => $bbai_ga_wcag_risk,
						'tone'  => 'warn',
					),
				)
			);
			?>
		</div>
		<div class="nai-card nai-audit__audit-card">
			<div class="nai-audit__audit-head">
				<span class="nai-audit__audit-icon nai-audit__audit-icon--primary"><?php echo $bbai_ga_icon( 'trend', 17 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?></span>
				<div>
					<div class="nai-eyebrow nai-eyebrow--primary"><?php esc_html_e( 'Image SEO', 'beepbeep-ai-alt-text-generator' ); ?></div>
					<h3 class="nai-audit__audit-title"><?php esc_html_e( 'Image SEO Opportunity', 'beepbeep-ai-alt-text-generator' ); ?></h3>
				</div>
			</div>
			<div class="nai-audit__callout nai-audit__callout--primary">
				<?php echo $bbai_ga_icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?>
				<span><?php esc_html_e( 'Adding AI-generated ALT text could improve image discoverability and search engine understanding.', 'beepbeep-ai-alt-text-generator' ); ?></span>
			</div>
			<?php
			$bbai_ga_metric_rows(
				array(
					array(
						'label' => __( 'Missing ALT text', 'beepbeep-ai-alt-text-generator' ),
						'value' => number_format_i18n( $bbai_ga_missing ),
						'tone'  => 'danger',
					),
					array(
						'label' => __( 'Images not optimised', 'beepbeep-ai-alt-text-generator' ),
						'value' => number_format_i18n( $bbai_ga_missing + $bbai_ga_weak ),
						'tone'  => 'warn',
					),
					array(
						'label' => __( 'SEO readiness score', 'beepbeep-ai-alt-text-generator' ),
						'value' => number_format_i18n( $bbai_ga_seo ) . '%',
						'tone'  => 'primary',
					),
					array(
						'label' => __( 'Estimated improvement', 'beepbeep-ai-alt-text-generator' ),
						'value' => $bbai_ga_gain,
						'tone'  => 'ok',
					),
				)
			);
			?>
		</div>
	</div>

	<?php /* TIME SAVED + BEFORE/AFTER */ ?>
	<div class="nai-audit__time-grid">
		<div class="nai-card nai-audit__time-card">
			<div class="nai-audit__card-head">
				<div class="nai-eyebrow"><?php esc_html_e( 'Time saved', 'beepbeep-ai-alt-text-generator' ); ?></div>
				<h3 class="nai-audit__audit-title">
					<?php
					printf(
						/* translators: %s: number of images. */
						esc_html__( '%s images, handled for you', 'beepbeep-ai-alt-text-generator' ),
						'<span class="nai-mono nai-tnum">' . esc_html( number_format_i18n( $bbai_ga_fix_count ) ) . '</span>'
					);
					?>
				</h3>
			</div>
			<div class="nai-audit__time-body">
				<div class="nai-audit__compare">
					<div class="nai-audit__compare-head">
						<span class="nai-audit__compare-label"><?php esc_html_e( 'Manual process', 'beepbeep-ai-alt-text-generator' ); ?></span>
						<span class="nai-mono nai-tnum nai-audit__compare-value">
							<?php
							printf(
								/* translators: %s: number of minutes. */
								esc_html( _n( '%s minute', '%s minutes', $bbai_ga_manual_minutes, 'beepbeep-ai-alt-text-generator' ) ),
								esc_html( number_format_i18n( $bbai_ga_manual_minutes ) )
							);
							?>
						</span>
					</div>
					<div class="nai-audit__compare-track"><span class="nai-audit__compare-fill nai-audit__compare-fill--danger" style="width: 100%;"></span></div>
				</div>
				<div class="nai-audit__compare">
					<div class="nai-audit__compare-head">
						<span class="nai-audit__compare-label"><?php esc_html_e( 'With BeepBeep AI', 'beepbeep-ai-alt-text-generator' ); ?></span>
						<span class="nai-mono nai-tnum nai-audit__compare-value"><?php esc_html_e( '30 seconds', 'beepbeep-ai-alt-text-generator' ); ?></span>
					</div>
					<div class="nai-audit__compare-track"><span class="nai-audit__compare-fill nai-audit__compare-fill--ok" style="width: 6%;"></span></div>
				</div>
				<div class="nai-audit__time-total">
					<span class="nai-audit__time-total-label">
						<?php echo $bbai_ga_icon( 'clock', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?>
						<?php esc_html_e( 'Time saved', 'beepbeep-ai-alt-text-generator' ); ?>
					</span>
					<span class="nai-mono nai-tnum nai-audit__time-total-value">
						<?php
						printf(
							/* translators: %s: number of minutes. */
							esc_html__( '%s min', 'beepbeep-ai-alt-text-generator' ),
							esc_html( number_format_i18n( $bbai_ga_saved_minutes ) )
						);
						?>
					</span>
				</div>
			</div>
		</div>

		<div class="nai-card nai-audit__ba-card">
			<div class="nai-audit__card-head">
				<div class="nai-eyebrow"><?php esc_html_e( 'AI generated ALT text example', 'beepbeep-ai-alt-text-generator' ); ?></div>
				<h3 class="nai-audit__audit-title"><?php esc_html_e( 'Before / After', 'beepbeep-ai-alt-text-generator' ); ?></h3>
			</div>
			<div class="nai-audit__ba-body">
				<div class="nai-audit__ba-thumb" aria-hidden="true"><?php esc_html_e( 'product photo', 'beepbeep-ai-alt-text-generator' ); ?></div>
				<div class="nai-audit__ba-rows">
					<div class="nai-audit__ba-row nai-audit__ba-row--before">
						<div class="nai-audit__ba-row-head">
							<?php echo $bbai_ga_icon( 'x', 12, 2.6 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?>
							<span><?php esc_html_e( 'Before', 'beepbeep-ai-alt-text-generator' ); ?></span>
						</div>
						<div class="nai-mono nai-audit__ba-text nai-audit__ba-text--empty">alt=""&nbsp;&nbsp;<?php esc_html_e( '(empty)', 'beepbeep-ai-alt-text-generator' ); ?></div>
					</div>
					<div class="nai-audit__ba-row nai-audit__ba-row--after">
						<div class="nai-audit__ba-row-head">
							<?php echo $bbai_ga_icon( 'check', 12, 2.6 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?>
							<span><?php esc_html_e( 'After', 'beepbeep-ai-alt-text-generator' ); ?></span>
						</div>
						<div class="nai-mono nai-audit__ba-text">&quot;<?php esc_html_e( 'Golden Retriever playing fetch in a green park', 'beepbeep-ai-alt-text-generator' ); ?>&quot;</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<?php /* AUTOPILOT */ ?>
	<div class="nai-card nai-audit__autopilot">
		<div class="nai-audit__autopilot-grid">
			<div class="nai-audit__autopilot-main">
				<div class="nai-audit__autopilot-eyebrow">
					<span class="nai-audit__autopilot-zap"><?php echo $bbai_ga_icon( 'zap', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?></span>
					<span class="nai-eyebrow"><?php esc_html_e( 'Autopilot', 'beepbeep-ai-alt-text-generator' ); ?></span>
				</div>
				<h2 class="nai-audit__autopilot-title"><?php esc_html_e( 'Never write ALT text manually again', 'beepbeep-ai-alt-text-generator' ); ?></h2>
				<div class="nai-audit__autopilot-checklist">
					<?php
					$bbai_ga_checklist = array(
						__( 'Generate ALT text automatically', 'beepbeep-ai-alt-text-generator' ),
						__( 'Improve accessibility', 'beepbeep-ai-alt-text-generator' ),
						__( 'Improve image SEO', 'beepbeep-ai-alt-text-generator' ),
						__( 'Works on upload', 'beepbeep-ai-alt-text-generator' ),
						__( 'Review and approve changes', 'beepbeep-ai-alt-text-generator' ),
						__( 'Bulk generate existing images', 'beepbeep-ai-alt-text-generator' ),
					);
					foreach ( $bbai_ga_checklist as $bbai_ga_check_item ) :
						?>
						<div class="nai-audit__check-item">
							<span class="nai-audit__check-badge"><?php echo $bbai_ga_icon( 'check', 11, 3 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?></span>
							<?php echo esc_html( $bbai_ga_check_item ); ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<?php /* Progress visualization */ ?>
			<div class="nai-audit__autopilot-side">
				<?php $bbai_ga_ring_open( 120, 10, $bbai_ga_coverage, $bbai_ga_ring_tone ); ?>
					<span class="nai-mono nai-tnum nai-audit__ring-value--sm"><?php echo esc_html( number_format_i18n( $bbai_ga_coverage ) ); ?>%</span>
				</div>
			</div>
				<div class="nai-audit__autopilot-side-title"><?php esc_html_e( 'Accessibility coverage', 'beepbeep-ai-alt-text-generator' ); ?></div>
				<div class="nai-audit__autopilot-side-sub">
					<?php
					printf(
						/* translators: %s: number of images. */
						esc_html( _n( '%s image needs attention', '%s images need attention', $bbai_ga_fix_count, 'beepbeep-ai-alt-text-generator' ) ),
						esc_html( number_format_i18n( $bbai_ga_fix_count ) )
					);
					?>
				</div>
				<div class="nai-audit__autopilot-progress">
					<div class="nai-progress"><span class="nai-progress__bar" style="width: <?php echo esc_attr( (string) $bbai_ga_coverage ); ?>%;"></span></div>
					<div class="nai-mono nai-audit__autopilot-progress-meta">
						<span>
							<?php
							printf(
								/* translators: %s: number of images covered. */
								esc_html__( '%s covered', 'beepbeep-ai-alt-text-generator' ),
								esc_html( number_format_i18n( $bbai_ga_covered ) )
							);
							?>
						</span>
						<span>
							<?php
							printf(
								/* translators: %s: number of images remaining. */
								esc_html__( '%s remaining', 'beepbeep-ai-alt-text-generator' ),
								esc_html( number_format_i18n( $bbai_ga_missing ) )
							);
							?>
						</span>
					</div>
				</div>
			</div>
		</div>
	</div>

	<?php /* FINAL CTA */ ?>
	<div class="nai-card nai-audit__final-cta">
		<h2 class="nai-audit__final-title">
			<?php
			if ( $bbai_ga_fix_count > 0 ) {
				printf(
					/* translators: %s: number of images. */
					esc_html( _n( 'Fix %s image in minutes', 'Fix %s images in minutes', $bbai_ga_fix_count, 'beepbeep-ai-alt-text-generator' ) ),
					esc_html( number_format_i18n( $bbai_ga_fix_count ) )
				);
			} else {
				esc_html_e( 'Put your ALT text on Autopilot', 'beepbeep-ai-alt-text-generator' );
			}
			?>
		</h2>
		<p class="nai-audit__final-sub"><?php esc_html_e( 'Sign in to generate ALT text, improve accessibility and unlock image SEO improvements.', 'beepbeep-ai-alt-text-generator' ); ?></p>
		<?php $bbai_ga_cta_pair( 'footer' ); ?>
	</div>

</section>
