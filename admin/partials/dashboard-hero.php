<?php
/**
 * Guest-only (no connected account): conversion-first funnel. Two-card hero + value strip.
 *
 * @package BeepBeepAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! empty( $bbai_has_connected_account ) ) {
	return;
}

$bbai_state_missing_count     = isset( $bbai_state_missing_count ) ? max( 0, (int) $bbai_state_missing_count ) : 0;
$bbai_state_weak_count        = isset( $bbai_state_weak_count ) ? max( 0, (int) $bbai_state_weak_count ) : 0;
$bbai_state_optimized_count  = isset( $bbai_state_optimized_count ) ? max( 0, (int) $bbai_state_optimized_count ) : 0;
$bbai_state_total_images       = isset( $bbai_state_total_images ) ? max( 0, (int) $bbai_state_total_images ) : 0;
$bbai_guest_images_with_alt    = $bbai_state_total_images > 0
	? max( 0, $bbai_state_total_images - $bbai_state_missing_count )
	: max( 0, $bbai_state_optimized_count + $bbai_state_weak_count );
$bbai_guest_coverage_pct       = $bbai_state_total_images > 0
	? (int) max( 0, min( 100, round( 100 * $bbai_guest_images_with_alt / max( 1, $bbai_state_total_images ) ) ) )
	: 0;
$bbai_guest_completion_tone    = $bbai_guest_coverage_pct >= 90 ? 'healthy' : ( $bbai_guest_coverage_pct >= 70 ? 'warning' : 'danger' );
$bbai_guest_attention_total    = $bbai_state_missing_count + $bbai_state_weak_count;
$bbai_guest_actionable_count   = max(
	$bbai_state_missing_count,
	$bbai_state_weak_count,
	$bbai_guest_attention_total
);

$bbai_gt_src = isset( $bbai_product_state_model['trial'] ) && is_array( $bbai_product_state_model['trial'] )
	? $bbai_product_state_model['trial']
	: [];
$bbai_gt_limit     = max( 1, (int) ( $bbai_gt_src['limit'] ?? 5 ) );
$bbai_gt_used      = max( 0, min( $bbai_gt_limit, (int) ( $bbai_gt_src['used'] ?? 0 ) ) );
$bbai_gt_remaining = max( 0, (int) ( $bbai_gt_src['remaining'] ?? max( 0, $bbai_gt_limit - $bbai_gt_used ) ) );
$bbai_gt_exhausted = ! empty( $bbai_gt_src['exhausted'] ) || $bbai_gt_remaining <= 0;

if ( $bbai_gt_exhausted ) {
	$bbai_guest_hero_variant = 'exhausted';
} elseif ( $bbai_gt_used > 0 ) {
	$bbai_guest_hero_variant = 'in_progress';
} else {
	$bbai_guest_hero_variant = 'fresh';
}

$bbai_guest_completion_colour = '#22c55e';
if ( 'warning' === $bbai_guest_completion_tone ) {
	$bbai_guest_completion_colour = '#f59e0b';
} elseif ( 'danger' === $bbai_guest_completion_tone ) {
	$bbai_guest_completion_colour = '#ef4444';
}
$bbai_donut_background = sprintf(
	'conic-gradient(%1$s 0deg %2$sdeg, #e5e7eb %2$sdeg 360deg)',
	$bbai_guest_completion_colour,
	(int) round( 360 * $bbai_guest_coverage_pct / 100 )
);

$bbai_free_plan_monthly = max(
	0,
	(int) (
		( isset( $bbai_free_plan_offer ) && (int) $bbai_free_plan_offer > 0 )
			? (int) $bbai_free_plan_offer
			: (int) ( $bbai_gt_src['monthly_free_limit'] ?? 15 )
	)
);

if ( 'exhausted' === $bbai_guest_hero_variant ) {
	$bbai_donut_tone        = $bbai_guest_completion_tone;
	// Completion-based conversion: emphasize finishing the remaining work (not "trial complete").
	$bbai_guest_remaining_images = max( 0, (int) $bbai_state_missing_count );
	$bbai_guest_fixed_images     = $bbai_guest_images_with_alt;
	$bbai_guest_pct              = $bbai_guest_coverage_pct;

	$bbai_donut_center_val  = $bbai_guest_pct > 0 ? (string) $bbai_guest_pct . '%' : '✓';
	$bbai_donut_center_sub  = __( 'Complete', 'beepbeep-ai-alt-text-generator' );
	$bbai_left_helper       = $bbai_guest_remaining_images === 1
		? __( '1 image remaining', 'beepbeep-ai-alt-text-generator' )
		: sprintf(
			/* translators: %s: images remaining. */
			__( '%s images remaining', 'beepbeep-ai-alt-text-generator' ),
			number_format_i18n( $bbai_guest_remaining_images )
		);

	$bbai_left_meta_parts = [];
	$bbai_left_meta_parts[] = sprintf(
		/* translators: 1: used generations, 2: trial limit */
		__( '%1$s / %2$s free generations used', 'beepbeep-ai-alt-text-generator' ),
		number_format_i18n( $bbai_gt_used ),
		number_format_i18n( $bbai_gt_limit )
	);
	if ( $bbai_guest_fixed_images > 0 ) {
		$bbai_left_meta_lines = [];
		$bbai_left_meta_lines[] = sprintf(
			/* translators: %s: images fixed count */
			__( '%s images fixed', 'beepbeep-ai-alt-text-generator' ),
			number_format_i18n( $bbai_guest_fixed_images )
		);
		$bbai_left_meta_lines[] = sprintf(
			/* translators: 1: used generations, 2: trial limit */
			__( '%1$s / %2$s free generations used', 'beepbeep-ai-alt-text-generator' ),
			number_format_i18n( $bbai_gt_used ),
			number_format_i18n( $bbai_gt_limit )
		);
	}
	$bbai_left_helper_meta = implode( ' · ', array_filter( $bbai_left_meta_parts ) );

	$bbai_guest_title = sprintf(
		/* translators: %s: free monthly generations available after signup. */
		__( 'Your free trial made an impact — keep going with %s free every month', 'beepbeep-ai-alt-text-generator' ),
		number_format_i18n( $bbai_free_plan_monthly )
	);
	$bbai_guest_body = $bbai_guest_remaining_images > 0
		? sprintf(
			/* translators: 1: remaining images count, 2: free monthly generations available after signup. */
			_n(
				'Create your free account to fix the final %1$s image and unlock %2$s free generations every month.',
				'Create your free account to fix the remaining %1$s images and unlock %2$s free generations every month.',
				$bbai_guest_remaining_images,
				'beepbeep-ai-alt-text-generator'
			),
			number_format_i18n( $bbai_guest_remaining_images ),
			number_format_i18n( $bbai_free_plan_monthly )
		)
		: sprintf(
			/* translators: %s: free monthly generations available after signup. */
			__( 'Create your free account now to keep generating ALT text with %s free generations every month for life. No credit card needed.', 'beepbeep-ai-alt-text-generator' ),
			number_format_i18n( $bbai_free_plan_monthly )
		);

	$bbai_primary_register = [
		'label'       => __( 'Get 15 Free Generations Every Month', 'beepbeep-ai-alt-text-generator' ),
		'class'       => 'bbai-btn bbai-btn-primary bbai-li-btn-primary',
		'action'      => 'show-auth-modal',
		'auth_tab'    => 'signup',
		'analytics'   => 'guest_hero_primary_register_exhausted',
		'is_button'   => false,
	];
	$bbai_secondary_login = [
		'label'     => __( 'Already have an account? Log in', 'beepbeep-ai-alt-text-generator' ),
		'class'     => 'bbai-guest-hero__text-link',
		'action'   => 'show-auth-modal',
		'auth_tab' => 'login',
		'is_text'   => true,
		'is_button' => false,
	];
	$bbai_show_generate_primary = false;
} elseif ( 'in_progress' === $bbai_guest_hero_variant ) {
	$bbai_donut_tone         = $bbai_guest_completion_tone;
	$bbai_donut_center_val   = $bbai_guest_coverage_pct > 0 ? (string) $bbai_guest_coverage_pct . '%' : '0%';
	$bbai_donut_center_sub   = __( 'Complete', 'beepbeep-ai-alt-text-generator' );
	$bbai_left_helper        = $bbai_state_missing_count > 0
		? sprintf(
			/* translators: %s: number of images missing ALT text */
			_n( '%s image remaining', '%s images remaining', $bbai_state_missing_count, 'beepbeep-ai-alt-text-generator' ),
			number_format_i18n( $bbai_state_missing_count )
		)
		: '';

	$bbai_guest_title = $bbai_state_missing_count > 0
		? sprintf(
			/* translators: %s: images still missing ALT text. */
			_n( 'Only %s image needs fixing', 'Only %s images need fixing', $bbai_state_missing_count, 'beepbeep-ai-alt-text-generator' ),
			number_format_i18n( $bbai_state_missing_count )
		)
		: __( 'Your images are almost fully optimised', 'beepbeep-ai-alt-text-generator' );
	$bbai_guest_body  = __( 'Use your remaining free credits to complete your image optimisation.', 'beepbeep-ai-alt-text-generator' );

	$bbai_n_rem = max( 0, (int) $bbai_gt_remaining );
	$bbai_show_generate_primary = $bbai_n_rem > 0 && $bbai_state_missing_count > 0;
	$bbai_primary_generate = [
		'label'       => __( 'Generate ALT Text', 'beepbeep-ai-alt-text-generator' ),
		'class'       => 'bbai-btn bbai-btn-primary bbai-li-btn-primary',
		'action'      => 'generate-missing',
		'bbai_action' => 'generate_missing',
		'is_button'   => false,
	];
	$bbai_primary_register = [
		'label'       => __( 'Create Free Account', 'beepbeep-ai-alt-text-generator' ),
		'class'       => ( ! $bbai_show_generate_primary && $bbai_n_rem > 0 )
			? 'bbai-btn bbai-btn-primary bbai-li-btn-primary'
			: 'bbai-btn bbai-btn-secondary bbai-li-btn-secondary',
		'action'      => 'show-auth-modal',
		'auth_tab'    => 'signup',
		'analytics'   => ( ! $bbai_show_generate_primary && $bbai_n_rem > 0 ) ? 'guest_hero_primary_register_mid' : 'guest_hero_secondary_register',
		'is_button'   => false,
	];
	$bbai_secondary_login = [
		'label'     => __( 'Already have an account? Log in', 'beepbeep-ai-alt-text-generator' ),
		'class'     => 'bbai-guest-hero__text-link',
		'action'    => 'show-auth-modal',
		'auth_tab'  => 'login',
		'is_text'   => true,
		'is_button' => false,
	];
} else {
	$bbai_donut_tone       = $bbai_guest_completion_tone;
	$bbai_donut_center_val = $bbai_guest_coverage_pct > 0 ? (string) $bbai_guest_coverage_pct . '%' : '0%';

	if ( $bbai_state_missing_count > 0 ) {
		$bbai_donut_center_sub = __( 'Complete', 'beepbeep-ai-alt-text-generator' );
		$bbai_left_helper = sprintf(
			/* translators: %s: number of images needing ALT text. */
			_n( '%s image remaining', '%s images remaining', $bbai_state_missing_count, 'beepbeep-ai-alt-text-generator' ),
			number_format_i18n( $bbai_state_missing_count )
		);
		$bbai_left_helper_meta = __( 'Based on your last scan.', 'beepbeep-ai-alt-text-generator' );
	} elseif ( $bbai_state_weak_count > 0 ) {
		$bbai_donut_center_val  = number_format_i18n( $bbai_state_weak_count );
		$bbai_donut_center_sub = _n(
			'image ready for review',
			'images ready for review',
			$bbai_state_weak_count,
			'beepbeep-ai-alt-text-generator'
		);
		$bbai_left_helper       = __( 'Based on your last scan.', 'beepbeep-ai-alt-text-generator' );
	} else {
		$bbai_donut_center_sub = __( 'Coverage', 'beepbeep-ai-alt-text-generator' );
		$bbai_left_helper       = __( 'Scan your library to surface images that need ALT text.', 'beepbeep-ai-alt-text-generator' );
	}

	if ( $bbai_state_missing_count <= 0 && $bbai_state_weak_count > 0 ) {
		$bbai_guest_title = __( 'Your images are ready for review', 'beepbeep-ai-alt-text-generator' );
		$bbai_guest_body  = __( 'Create a free account to review your results and keep improving your media library.', 'beepbeep-ai-alt-text-generator' );
	} else {
		$bbai_guest_title = sprintf(
			/* translators: %d: free ALT text generations available without signup. */
			__( 'Fix up to %d images free — no signup needed', 'beepbeep-ai-alt-text-generator' ),
			(int) $bbai_gt_limit
		);
		$bbai_guest_body  = sprintf(
			/* translators: %d: free trial generations. */
			__( 'Click Generate below and we’ll create ALT text for up to %d images, improving accessibility and helping image SEO.', 'beepbeep-ai-alt-text-generator' ),
			(int) $bbai_gt_limit
		);
	}

	// Only show "Generate" when there is actually missing ALT to fix.
	$bbai_show_generate_primary = $bbai_gt_remaining > 0 && $bbai_state_missing_count > 0;
	$bbai_primary_generate    = [
		'label'       => sprintf(
			/* translators: %d: free generations available. */
			__( 'Generate Up to %d ALT Texts Free', 'beepbeep-ai-alt-text-generator' ),
			(int) $bbai_gt_limit
		),
		'class'       => 'bbai-btn bbai-btn-primary bbai-li-btn-primary',
		'action'      => 'generate-missing',
		'bbai_action' => 'generate_missing',
		'is_button'   => false,
	];
	$bbai_primary_register = [
		'label'     => __( 'Create Free Account', 'beepbeep-ai-alt-text-generator' ),
		'class'     => $bbai_show_generate_primary
			? 'bbai-btn bbai-btn-secondary bbai-li-btn-secondary'
			: 'bbai-btn bbai-btn-primary bbai-li-btn-primary',
		'action'    => 'show-auth-modal',
		'auth_tab'  => 'signup',
		'analytics' => 'guest_hero_secondary_register',
		'is_button' => false,
	];
	$bbai_secondary_login = [
		'label'     => __( 'Already have an account? Log in', 'beepbeep-ai-alt-text-generator' ),
		'class'     => 'bbai-guest-hero__text-link',
		'action'    => 'show-auth-modal',
		'auth_tab'  => 'login',
		'is_text'   => true,
		'is_button' => false,
	];
}

$bbai_trial_meter_pct = $bbai_gt_limit > 0 ? (int) min( 100, round( 100 * $bbai_gt_used / $bbai_gt_limit ) ) : 0;
$bbai_trial_usage_line = sprintf(
	/* translators: 1: used free generations, 2: total free generations. */
	__( '%1$s / %2$s free generations used', 'beepbeep-ai-alt-text-generator' ),
	number_format_i18n( $bbai_gt_used ),
	number_format_i18n( $bbai_gt_limit )
);
$bbai_trial_remaining_line = sprintf(
	/* translators: 1: remaining free generations, 2: monthly generations after free signup. */
	_n(
		'%1$s free generation left. Create a free account to unlock %2$s free each month',
		'%1$s free generations left. Create a free account to unlock %2$s free each month',
		$bbai_gt_remaining,
		'beepbeep-ai-alt-text-generator'
	),
	number_format_i18n( $bbai_gt_remaining ),
	number_format_i18n( $bbai_free_plan_monthly )
);

// Optional secondary helper line under the donut (small, low-emphasis).
$bbai_left_helper_meta = isset( $bbai_left_helper_meta ) ? (string) $bbai_left_helper_meta : '';

?>
<section
	class="bbai-li-hero-grid bbai-li-hero-grid--guest-funnel"
	data-bbai-funnel-hero="1"
	data-bbai-funnel-hero-state="guest_server"
	data-bbai-hero-ui-state="guest_server"
	data-bbai-guest-hero-static="1"
	aria-labelledby="bbai-guest-hero-heading"
>

	<div class="bbai-li-card bbai-li-card--donut bbai-guest-site-health-card">

		<div class="bbai-guest-site-health-card__header">
			<p class="bbai-guest-site-health-card__eyebrow"><?php esc_html_e( 'Site Health', 'beepbeep-ai-alt-text-generator' ); ?></p>
			<p class="bbai-guest-site-health-card__message" data-bbai-guest-site-health-message>
				<?php
				echo esc_html(
					$bbai_state_missing_count > 0
						? sprintf(
							/* translators: %s: images still missing ALT text. */
							_n( 'You’re almost there. Only %s image still needs ALT text.', 'You’re almost there. Only %s images still need ALT text.', $bbai_state_missing_count, 'beepbeep-ai-alt-text-generator' ),
							number_format_i18n( $bbai_state_missing_count )
						)
						: __( 'Your scanned images have ALT text coverage.', 'beepbeep-ai-alt-text-generator' )
				);
				?>
			</p>
		</div>
		<div class="bbai-li-donut-area">
			<div
				class="bbai-command-donut bbai-command-donut--funnel bbai-li-donut bbai-command-donut--<?php echo esc_attr( $bbai_donut_tone ); ?> bbai-guest-site-health-card__donut"
				data-bbai-status-donut
				data-bbai-donut-optimized="<?php echo esc_attr( (string) $bbai_state_optimized_count ); ?>"
				data-bbai-donut-weak="<?php echo esc_attr( (string) $bbai_state_weak_count ); ?>"
				data-bbai-donut-missing="<?php echo esc_attr( (string) $bbai_state_missing_count ); ?>"
				data-bbai-donut-total="<?php echo esc_attr( (string) $bbai_state_total_images ); ?>"
				aria-hidden="false"
				style="background: <?php echo esc_attr( $bbai_donut_background ); ?>;"
			>
				<span class="bbai-command-donut__inner"></span>
				<span class="bbai-command-donut__center bbai-li-donut__center">
					<span class="bbai-command-donut__center-value bbai-li-donut__value bbai-command-donut__center-value--<?php echo esc_attr( $bbai_donut_tone ); ?>" data-bbai-funnel-donut-value>
						<?php echo esc_html( $bbai_donut_center_val ); ?>
					</span>
					<span
						class="bbai-command-donut__center-label bbai-li-donut__sub-label"
						data-bbai-funnel-donut-label
						<?php echo '' === (string) $bbai_donut_center_sub ? 'hidden' : ''; ?>
					>
						<?php echo esc_html( $bbai_donut_center_sub ); ?>
					</span>
				</span>
			</div>

			<?php if ( 'in_progress' === $bbai_guest_hero_variant ) : ?>
				<p class="bbai-guest-hero__trial-meter-intro"><?php esc_html_e( 'Free trial', 'beepbeep-ai-alt-text-generator' ); ?></p>
				<div class="bbai-guest-trial-meter" data-bbai-guest-trial-meter role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( (string) $bbai_gt_limit ); ?>" aria-valuenow="<?php echo esc_attr( (string) $bbai_gt_used ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: used count, 2: limit count */ __( '%1$s / %2$s free generations used', 'beepbeep-ai-alt-text-generator' ), (string) (int) $bbai_gt_used, (string) (int) $bbai_gt_limit ) ); ?>">
					<span class="bbai-guest-trial-meter__track">
						<span class="bbai-guest-trial-meter__fill" data-bbai-guest-trial-meter-fill style="width: <?php echo esc_attr( (string) $bbai_trial_meter_pct ); ?>%;"></span>
					</span>
				</div>
				<p class="bbai-guest-hero__trial-meter-caption" data-bbai-guest-trial-meter-caption>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: used count, 2: limit count */
							__( '%1$s / %2$s free generations used', 'beepbeep-ai-alt-text-generator' ),
							number_format_i18n( $bbai_gt_used ),
							number_format_i18n( $bbai_gt_limit )
						)
					);
					?>
				</p>
			<?php endif; ?>

			<p class="bbai-li-donut__helper">
				<?php if ( $bbai_left_helper ) : ?>
					<span class="bbai-li-donut__helper-main" data-bbai-guest-images-remaining-line><?php echo esc_html( $bbai_left_helper ); ?></span>
				<?php else : ?>
					<span class="bbai-li-donut__helper-main">&nbsp;</span>
				<?php endif; ?>
				<?php if ( isset( $bbai_left_meta_lines ) && is_array( $bbai_left_meta_lines ) && ! empty( $bbai_left_meta_lines ) ) : ?>
					<span class="bbai-li-donut__helper-meta bbai-li-donut__helper-meta--stack">
						<?php foreach ( $bbai_left_meta_lines as $bbai_meta_line ) : ?>
							<span class="bbai-li-donut__helper-meta-line"><?php echo esc_html( (string) $bbai_meta_line ); ?></span>
						<?php endforeach; ?>
					</span>
				<?php elseif ( $bbai_left_helper_meta !== '' ) : ?>
					<span class="bbai-li-donut__helper-meta"><?php echo esc_html( $bbai_left_helper_meta ); ?></span>
				<?php endif; ?>
			</p>
		</div>
		<div class="bbai-guest-site-health-card__stats" aria-label="<?php esc_attr_e( 'Image optimisation summary', 'beepbeep-ai-alt-text-generator' ); ?>">
			<div class="bbai-guest-site-health-card__stat">
				<span><?php esc_html_e( 'Images Scanned', 'beepbeep-ai-alt-text-generator' ); ?></span>
				<strong data-bbai-guest-stat-total><?php echo esc_html( number_format_i18n( $bbai_state_total_images ) ); ?></strong>
			</div>
			<div class="bbai-guest-site-health-card__stat">
				<span><?php esc_html_e( 'Images With ALT Text', 'beepbeep-ai-alt-text-generator' ); ?></span>
				<strong data-bbai-guest-stat-with-alt><?php echo esc_html( number_format_i18n( $bbai_guest_images_with_alt ) ); ?></strong>
			</div>
			<div class="bbai-guest-site-health-card__stat">
				<span><?php esc_html_e( 'Images Missing ALT Text', 'beepbeep-ai-alt-text-generator' ); ?></span>
				<strong data-bbai-guest-stat-missing><?php echo esc_html( number_format_i18n( $bbai_state_missing_count ) ); ?></strong>
			</div>
			<div class="bbai-guest-site-health-card__stat">
				<span><?php esc_html_e( 'Coverage', 'beepbeep-ai-alt-text-generator' ); ?></span>
				<strong data-bbai-guest-stat-coverage><?php echo esc_html( number_format_i18n( $bbai_guest_coverage_pct ) ); ?>%</strong>
			</div>
		</div>
		<div class="bbai-guest-site-health-card__progress" aria-hidden="true">
			<span class="bbai-guest-site-health-card__progress-track">
				<span class="bbai-guest-site-health-card__progress-fill bbai-guest-site-health-card__progress-fill--<?php echo esc_attr( $bbai_guest_completion_tone ); ?>" style="width: <?php echo esc_attr( (string) $bbai_guest_coverage_pct ); ?>%;" data-bbai-guest-coverage-fill></span>
			</span>
			<span class="bbai-guest-site-health-card__progress-label" data-bbai-guest-coverage-label>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: coverage percentage. */
						__( '%s%% Complete', 'beepbeep-ai-alt-text-generator' ),
						number_format_i18n( $bbai_guest_coverage_pct )
					)
				);
				?>
			</span>
		</div>
	</div>

	<div class="bbai-li-card bbai-li-card--content">

		<div class="bbai-li-card-section bbai-li-card-section--intro">
			<h1 id="bbai-guest-hero-heading" class="bbai-li-headline" data-bbai-guest-trial-title><?php echo esc_html( $bbai_guest_title ); ?></h1>
			<p class="bbai-li-support" data-bbai-guest-trial-body><?php echo esc_html( $bbai_guest_body ); ?></p>
		</div>

		<div class="bbai-li-card-section bbai-li-card-section--actions bbai-guest-hero__cta-block">
			<div class="bbai-action-block">
				<div class="bbai-li-cta-row bbai-li-cta-group bbai-cta-group bbai-guest-hero__cta-cluster">
				<?php if ( ! empty( $bbai_show_generate_primary ) && isset( $bbai_primary_generate ) ) : ?>
					<a
						href="#"
						class="<?php echo esc_attr( $bbai_primary_generate['class'] ); ?>"
						data-action="<?php echo esc_attr( $bbai_primary_generate['action'] ); ?>"
						data-bbai-action="<?php echo esc_attr( $bbai_primary_generate['bbai_action'] ?? '' ); ?>"
						data-bbai-funnel-hero-cta=""
						data-bbai-funnel-hero-primary=""
					><?php echo esc_html( $bbai_primary_generate['label'] ); ?></a>
				<?php endif; ?>

					<a
						href="#"
						class="<?php echo esc_attr( $bbai_primary_register['class'] ); ?>"
						<?php if ( ! empty( $bbai_primary_register['action'] ) ) : ?>
							data-action="<?php echo esc_attr( $bbai_primary_register['action'] ); ?>"
						<?php endif; ?>
						<?php if ( ! empty( $bbai_primary_register['auth_tab'] ) ) : ?>
							data-auth-tab="<?php echo esc_attr( $bbai_primary_register['auth_tab'] ); ?>"
						<?php endif; ?>
						<?php if ( 'exhausted' === $bbai_guest_hero_variant ) : ?>
							data-bbai-trial-complete-cta="create_account"
						<?php endif; ?>
						<?php if ( ! empty( $bbai_primary_register['analytics'] ) ) : ?>
							data-bbai-analytics-upgrade="<?php echo esc_attr( $bbai_primary_register['analytics'] ); ?>"
						<?php endif; ?>
						data-bbai-modal-context="<?php echo esc_attr( 'exhausted' === $bbai_guest_hero_variant ? 'register_exhausted' : 'register' ); ?>"
						data-bbai-guest-register-cta
						data-bbai-funnel-hero-cta=""
						<?php echo ! empty( $bbai_show_generate_primary ) && isset( $bbai_primary_generate ) ? 'data-bbai-funnel-hero-secondary=""' : 'data-bbai-funnel-hero-primary=""'; ?>
					><?php echo esc_html( $bbai_primary_register['label'] ); ?></a>

					<p class="bbai-guest-hero__login">
						<a
							href="#"
							class="<?php echo esc_attr( $bbai_secondary_login['class'] ); ?>"
							data-action="<?php echo esc_attr( $bbai_secondary_login['action'] ); ?>"
							data-auth-tab="<?php echo esc_attr( $bbai_secondary_login['auth_tab'] ); ?>"
							data-bbai-modal-context="login"
							data-bbai-funnel-hero-secondary=""
							<?php if ( 'exhausted' === $bbai_guest_hero_variant ) : ?>
								data-bbai-trial-complete-cta="login"
							<?php endif; ?>
						><?php echo esc_html( $bbai_secondary_login['label'] ); ?></a>
					</p>
				</div>
				<?php if ( 'fresh' === $bbai_guest_hero_variant ) : ?>
					<p class="bbai-guest-hero__cta-hint" data-bbai-guest-trial-promise>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: free generations available without signup. */
								__( '✓ %d free generations now · No signup · No credit card', 'beepbeep-ai-alt-text-generator' ),
								(int) $bbai_gt_limit
							)
						);
						?>
					</p>
				<?php elseif ( 'exhausted' === $bbai_guest_hero_variant ) : ?>
					<p class="bbai-guest-hero__cta-hint" data-bbai-guest-trial-promise>
						<?php esc_html_e( '15 free generations every month for life · No credit card needed', 'beepbeep-ai-alt-text-generator' ); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( 'exhausted' !== $bbai_guest_hero_variant ) : ?>
			<div class="bbai-li-card-section bbai-li-card-section--monetisation bbai-guest-hero__trial-blurb">
				<p class="bbai-guest-hero__trial-line">
					<span class="bbai-guest-hero__trial-line-main" data-bbai-guest-trial-usage-line>
						<?php echo esc_html( $bbai_trial_usage_line ); ?>
					</span>
					<span class="bbai-guest-hero__trial-line-sub" data-bbai-guest-trial-remaining-line>
						<?php echo esc_html( $bbai_trial_remaining_line ); ?>
					</span>
				</p>
				<ul class="bbai-guest-register-benefits" aria-label="<?php esc_attr_e( 'Free account benefits', 'beepbeep-ai-alt-text-generator' ); ?>">
					<li><span aria-hidden="true">✓</span><?php esc_html_e( '15 free AI generations every month for life', 'beepbeep-ai-alt-text-generator' ); ?></li>
					<li><span aria-hidden="true">✓</span><?php esc_html_e( 'No credit card needed', 'beepbeep-ai-alt-text-generator' ); ?></li>
					<li><span aria-hidden="true">✓</span><?php esc_html_e( 'Full ALT Library access', 'beepbeep-ai-alt-text-generator' ); ?></li>
					<li><span aria-hidden="true">✓</span><?php esc_html_e( 'Bulk optimisation tools', 'beepbeep-ai-alt-text-generator' ); ?></li>
				</ul>
			</div>
		<?php else : ?>
			<div class="bbai-li-card-section bbai-li-card-section--monetisation bbai-guest-hero__trial-blurb">
				<ul class="bbai-guest-register-benefits" aria-label="<?php esc_attr_e( 'Free account benefits', 'beepbeep-ai-alt-text-generator' ); ?>">
					<li><span aria-hidden="true">✓</span><?php esc_html_e( '15 free AI generations every month for life', 'beepbeep-ai-alt-text-generator' ); ?></li>
					<li><span aria-hidden="true">✓</span><?php esc_html_e( 'No credit card needed', 'beepbeep-ai-alt-text-generator' ); ?></li>
					<li><span aria-hidden="true">✓</span><?php esc_html_e( 'Keep your current accessibility progress', 'beepbeep-ai-alt-text-generator' ); ?></li>
					<li><span aria-hidden="true">✓</span><?php esc_html_e( 'Full ALT Library and image SEO tools', 'beepbeep-ai-alt-text-generator' ); ?></li>
				</ul>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php if ( 'exhausted' !== $bbai_guest_hero_variant ) : ?>
	<section class="bbai-guest-benefits-card" aria-labelledby="bbai-guest-benefits-title">
		<h2 id="bbai-guest-benefits-title" class="bbai-guest-benefits-card__title"><?php esc_html_e( 'Why ALT Text Matters', 'beepbeep-ai-alt-text-generator' ); ?></h2>
		<div class="bbai-guest-benefits-card__grid">
			<div class="bbai-guest-benefits-card__item">
				<strong><?php esc_html_e( 'Accessibility', 'beepbeep-ai-alt-text-generator' ); ?></strong>
				<span><?php esc_html_e( 'Help screen readers understand images.', 'beepbeep-ai-alt-text-generator' ); ?></span>
			</div>
			<div class="bbai-guest-benefits-card__item">
				<strong><?php esc_html_e( 'SEO', 'beepbeep-ai-alt-text-generator' ); ?></strong>
				<span><?php esc_html_e( 'Improve image discoverability in search engines.', 'beepbeep-ai-alt-text-generator' ); ?></span>
			</div>
			<div class="bbai-guest-benefits-card__item">
				<strong><?php esc_html_e( 'Efficiency', 'beepbeep-ai-alt-text-generator' ); ?></strong>
				<span><?php esc_html_e( 'Generate ALT text instantly using AI.', 'beepbeep-ai-alt-text-generator' ); ?></span>
			</div>
			<div class="bbai-guest-benefits-card__item">
				<strong><?php esc_html_e( 'Compliance', 'beepbeep-ai-alt-text-generator' ); ?></strong>
				<span><?php esc_html_e( 'Support accessibility best practices.', 'beepbeep-ai-alt-text-generator' ); ?></span>
			</div>
		</div>
	</section>
	<div class="bbai-guest-value-cards bbai-dashboard-value-strip bbai-dashboard-value-strip--guest-funnel bbai-benefits-row bbai-trust-grid" aria-label="<?php esc_attr_e( 'Built for WordPress Site Owners', 'beepbeep-ai-alt-text-generator' ); ?>">
		<div class="bbai-dashboard-value-strip__item bbai-benefit-item bbai-trust-grid__item">
			<span class="bbai-dashboard-value-strip__icon bbai-benefit-icon bbai-trust-grid__icon" aria-hidden="true">✓</span>
			<span class="bbai-benefit-text bbai-trust-grid__text"><?php esc_html_e( 'Save hours of manual work', 'beepbeep-ai-alt-text-generator' ); ?></span>
		</div>
		<div class="bbai-dashboard-value-strip__item bbai-benefit-item bbai-trust-grid__item">
			<span class="bbai-dashboard-value-strip__icon bbai-benefit-icon bbai-trust-grid__icon" aria-hidden="true">✓</span>
			<span class="bbai-benefit-text bbai-trust-grid__text"><?php esc_html_e( 'Improve accessibility', 'beepbeep-ai-alt-text-generator' ); ?></span>
		</div>
		<div class="bbai-dashboard-value-strip__item bbai-benefit-item bbai-trust-grid__item">
			<span class="bbai-dashboard-value-strip__icon bbai-benefit-icon bbai-trust-grid__icon" aria-hidden="true">✓</span>
			<span class="bbai-benefit-text bbai-trust-grid__text"><?php esc_html_e( 'Manage image optimisation from one dashboard', 'beepbeep-ai-alt-text-generator' ); ?></span>
		</div>
	</div>
<?php endif; ?>
