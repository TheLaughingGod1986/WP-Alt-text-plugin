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

$bbai_donut_background = 'conic-gradient(#d7dee8 0deg 360deg)';
if ( 'exhausted' !== $bbai_guest_hero_variant && $bbai_state_total_images > 0 && is_callable( $bbai_build_donut_background ) ) {
	$bbai_donut_background = $bbai_build_donut_background(
		$bbai_state_optimized_count,
		$bbai_state_weak_count,
		$bbai_state_missing_count,
		max( 1, $bbai_state_total_images )
	);
} elseif ( 'exhausted' === $bbai_guest_hero_variant ) {
	$bbai_donut_background = 'conic-gradient(#cbd5e1 0deg 360deg)';
}

$bbai_free_plan_monthly = max(
	0,
	(int) (
		( isset( $bbai_free_plan_offer ) && (int) $bbai_free_plan_offer > 0 )
			? (int) $bbai_free_plan_offer
			: (int) ( $bbai_gt_src['monthly_free_limit'] ?? 50 )
	)
);

if ( 'exhausted' === $bbai_guest_hero_variant ) {
	$bbai_donut_tone        = 'neutral';
	// Completion-based conversion: emphasize finishing the remaining work (not "trial complete").
	$bbai_guest_remaining_images = max( 0, (int) $bbai_state_missing_count );
	$bbai_guest_fixed_images = $bbai_state_total_images > 0
		? max( 0, (int) ( $bbai_state_total_images - $bbai_state_missing_count ) )
		: max( 0, (int) $bbai_state_optimized_count + (int) $bbai_state_weak_count );
	$bbai_guest_pct = $bbai_state_total_images > 0
		? (int) max( 0, min( 100, round( 100 * ( ( $bbai_state_total_images - $bbai_state_missing_count ) / max( 1, $bbai_state_total_images ) ) ) ) )
		: 0;

	$bbai_donut_center_val  = $bbai_guest_pct > 0 ? (string) $bbai_guest_pct . '%' : '✓';
	$bbai_donut_center_sub  = __( 'Almost done', 'beepbeep-ai-alt-text-generator' );
	$bbai_left_helper       = $bbai_guest_remaining_images === 1
		? __( '1 image remaining', 'beepbeep-ai-alt-text-generator' )
		: __( 'Keep going', 'beepbeep-ai-alt-text-generator' );

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

	// Avoid a second "98% complete" banner — keep the goal-oriented CTA and let the modal do the conversion work.
	$bbai_guest_title = $bbai_guest_remaining_images === 1
		? __( 'Finish your last image', 'beepbeep-ai-alt-text-generator' )
		: __( 'Keep optimising your library', 'beepbeep-ai-alt-text-generator' );
	$bbai_guest_body  = ( $bbai_guest_fixed_images > 0 && $bbai_guest_remaining_images > 0 )
		? sprintf(
			/* translators: 1: fixed images count, 2: remaining images count */
			__( 'You’ve fixed %1$s images. Just %2$s more to go.', 'beepbeep-ai-alt-text-generator' ),
			number_format_i18n( $bbai_guest_fixed_images ),
			number_format_i18n( $bbai_guest_remaining_images )
		)
		: __( 'You’re one step away from a fully optimised site.', 'beepbeep-ai-alt-text-generator' );
	if ( 1 === (int) $bbai_guest_remaining_images && $bbai_guest_fixed_images > 0 ) {
		$bbai_guest_body = __( 'You’ve fixed 51 images. Just 1 more to complete your library.', 'beepbeep-ai-alt-text-generator' );
	}

	$bbai_primary_register = [
		'label'       => __( 'Finish the last image', 'beepbeep-ai-alt-text-generator' ),
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
	$bbai_donut_tone         = $bbai_guest_actionable_count > 0 ? 'problem' : 'neutral';
	$bbai_donut_center_val   = number_format_i18n( $bbai_gt_remaining );
	$bbai_donut_center_sub = _n(
		'free generation left',
		'free generations left',
		$bbai_gt_remaining,
		'beepbeep-ai-alt-text-generator'
	);
	$bbai_left_helper        = $bbai_state_missing_count > 0
		? sprintf(
			/* translators: %s: number of images missing ALT text */
			__( '%s images are hurting your SEO and accessibility', 'beepbeep-ai-alt-text-generator' ),
			number_format_i18n( $bbai_state_missing_count )
		)
		: '';

	$bbai_guest_title = sprintf(
		/* translators: %d: number of free generations left */
		__( 'You have %d free generations left', 'beepbeep-ai-alt-text-generator' ),
		(int) $bbai_gt_remaining
	);
	$bbai_guest_body  = __( 'Keep fixing your images, then create a free account to unlock your full ALT library.', 'beepbeep-ai-alt-text-generator' );

	$bbai_n_rem = max( 0, (int) $bbai_gt_remaining );
	$bbai_show_generate_primary = $bbai_n_rem > 0 && $bbai_state_missing_count > 0;
	$bbai_primary_generate = [
		'label'       => __( 'Generate free ALT text', 'beepbeep-ai-alt-text-generator' ),
		'class'       => 'bbai-btn bbai-btn-primary bbai-li-btn-primary',
		'action'      => 'generate-missing',
		'bbai_action' => 'generate_missing',
		'is_button'   => false,
	];
	$bbai_primary_register = [
		'label'       => __( 'Create free account', 'beepbeep-ai-alt-text-generator' ),
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
	$bbai_donut_tone = $bbai_guest_actionable_count > 0 ? 'problem' : 'neutral';
	$bbai_donut_center_val = '' !== number_format_i18n( max( 0, $bbai_state_missing_count ) )
		? number_format_i18n( max( 0, $bbai_state_missing_count ) )
		: '0';

	if ( $bbai_state_missing_count > 0 ) {
		// Keep the donut clean: show the number only. Explanatory copy lives under the donut.
		$bbai_donut_center_sub = '';
		$bbai_left_helper = sprintf(
			/* translators: %s: number of images needing ALT text. */
			_n( '%s image needs ALT text', '%s images need ALT text', $bbai_state_missing_count, 'beepbeep-ai-alt-text-generator' ),
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
		$bbai_guest_title = __( 'Your images are missing ALT text', 'beepbeep-ai-alt-text-generator' );
		$bbai_guest_body  = sprintf(
			/* translators: %d: free trial generations. */
			__( 'Generate your first %d ALT texts for free. No account needed.', 'beepbeep-ai-alt-text-generator' ),
			(int) $bbai_gt_limit
		);
	}

	// Only show "Generate" when there is actually missing ALT to fix.
	$bbai_show_generate_primary = $bbai_gt_remaining > 0 && $bbai_state_missing_count > 0;
	$bbai_primary_generate    = [
		'label'       => __( 'Generate free ALT text', 'beepbeep-ai-alt-text-generator' ),
		'class'       => 'bbai-btn bbai-btn-primary bbai-li-btn-primary',
		'action'      => 'generate-missing',
		'bbai_action' => 'generate_missing',
		'is_button'   => false,
	];
	$bbai_primary_register = [
		'label'     => __( 'Create free account', 'beepbeep-ai-alt-text-generator' ),
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

	<div class="bbai-li-card bbai-li-card--donut">

		<div class="bbai-li-donut-area">
			<div
				class="bbai-command-donut bbai-command-donut--funnel bbai-li-donut bbai-command-donut--<?php echo esc_attr( $bbai_donut_tone ); ?>"
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
				<div class="bbai-guest-trial-meter" role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( (string) $bbai_gt_limit ); ?>" aria-valuenow="<?php echo esc_attr( (string) $bbai_gt_used ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: used count, 2: limit count */ __( '%1$s / %2$s free generations used', 'beepbeep-ai-alt-text-generator' ), (string) (int) $bbai_gt_used, (string) (int) $bbai_gt_limit ) ); ?>">
					<span class="bbai-guest-trial-meter__track">
						<span class="bbai-guest-trial-meter__fill" style="width: <?php echo esc_attr( (string) $bbai_trial_meter_pct ); ?>%;"></span>
					</span>
				</div>
				<p class="bbai-guest-hero__trial-meter-caption">
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
					<span class="bbai-li-donut__helper-main"><?php echo esc_html( $bbai_left_helper ); ?></span>
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
	</div>

	<div class="bbai-li-card bbai-li-card--content">

		<div class="bbai-li-card-section bbai-li-card-section--intro">
			<h1 id="bbai-guest-hero-heading" class="bbai-li-headline"><?php echo esc_html( $bbai_guest_title ); ?></h1>
			<p class="bbai-li-support"><?php echo esc_html( $bbai_guest_body ); ?></p>
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
			</div>
		</div>

		<?php if ( 'exhausted' !== $bbai_guest_hero_variant ) : ?>
			<div class="bbai-li-card-section bbai-li-card-section--monetisation bbai-guest-hero__trial-blurb">
				<p class="bbai-guest-hero__trial-line">
					<span class="bbai-guest-hero__trial-line-main">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: number of free generations. */
								__( '%s free generations included', 'beepbeep-ai-alt-text-generator' ),
								number_format_i18n( $bbai_gt_limit )
							)
						);
						?>
					</span>
					<span class="bbai-guest-hero__trial-line-sub">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: monthly generations after free signup. */
								__( 'Create a free account to unlock %s/month', 'beepbeep-ai-alt-text-generator' ),
								number_format_i18n( $bbai_free_plan_monthly )
							)
						);
						?>
					</span>
				</p>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php if ( 'exhausted' !== $bbai_guest_hero_variant ) : ?>
	<div class="bbai-guest-value-cards bbai-dashboard-value-strip bbai-dashboard-value-strip--guest-funnel bbai-benefits-row bbai-trust-grid" aria-label="<?php esc_attr_e( 'Why fix ALT text', 'beepbeep-ai-alt-text-generator' ); ?>">
		<div class="bbai-dashboard-value-strip__item bbai-benefit-item bbai-trust-grid__item">
			<span class="bbai-dashboard-value-strip__icon bbai-benefit-icon bbai-trust-grid__icon" aria-hidden="true">✓</span>
			<span class="bbai-benefit-text bbai-trust-grid__text"><?php esc_html_e( 'Improve image SEO', 'beepbeep-ai-alt-text-generator' ); ?></span>
		</div>
		<div class="bbai-dashboard-value-strip__item bbai-benefit-item bbai-trust-grid__item">
			<span class="bbai-dashboard-value-strip__icon bbai-benefit-icon bbai-trust-grid__icon" aria-hidden="true">✓</span>
			<span class="bbai-benefit-text bbai-trust-grid__text"><?php esc_html_e( 'Boost accessibility', 'beepbeep-ai-alt-text-generator' ); ?></span>
		</div>
		<div class="bbai-dashboard-value-strip__item bbai-benefit-item bbai-trust-grid__item">
			<span class="bbai-dashboard-value-strip__icon bbai-benefit-icon bbai-trust-grid__icon" aria-hidden="true">✓</span>
			<span class="bbai-benefit-text bbai-trust-grid__text"><?php esc_html_e( 'Save manual writing time', 'beepbeep-ai-alt-text-generator' ); ?></span>
		</div>
	</div>
<?php endif; ?>
