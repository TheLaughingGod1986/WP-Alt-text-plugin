<?php
/**
 * nAi Autopilot screen — generation preferences + scheduled background work.
 *
 * Pure presentation; reads the BeepBeep plugin option for auto_generate to
 * surface the master toggle's state. Form submits stay on existing settings
 * endpoints so this view is safe to drop in without rewiring backend code.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$nai_ap_options = is_array( get_option( 'bbai_options', array() ) ) ? get_option( 'bbai_options', array() ) : array();
$nai_ap_is_pro  = ! empty( $bbai_state_is_pro_plan ?? false );
if ( ! $nai_ap_is_pro && function_exists( '\BeepBeepAI\AltTextGenerator\Admin\Plan_Helpers::get_plan_data' ) ) {
	// noop — guard for static analysis. Plan data not strictly needed here.
	$nai_ap_is_pro = false;
}

$nai_ap_auto_on        = ! empty( $nai_ap_options['auto_generate'] ) && $nai_ap_is_pro;
$nai_ap_settings_url   = admin_url( 'admin.php?page=bbai-settings' );
$nai_ap_upgrade_action = 'show-upgrade-modal';

$nai_ap_presets = array(
	array(
		'id'    => 'descriptive',
		'label' => __( 'Descriptive', 'beepbeep-ai-alt-text-generator' ),
		'desc'  => __( "Natural, neutral describing of what's in the image.", 'beepbeep-ai-alt-text-generator' ),
	),
	array(
		'id'    => 'seo',
		'label' => __( 'SEO-focused', 'beepbeep-ai-alt-text-generator' ),
		'desc'  => __( 'Pulls in keywords from the page context where relevant.', 'beepbeep-ai-alt-text-generator' ),
	),
	array(
		'id'    => 'ecommerce',
		'label' => __( 'E-commerce', 'beepbeep-ai-alt-text-generator' ),
		'desc'  => __( 'Product-first phrasing: material, colour, finish, key features.', 'beepbeep-ai-alt-text-generator' ),
	),
	array(
		'id'    => 'accessibility',
		'label' => __( 'Accessibility', 'beepbeep-ai-alt-text-generator' ),
		'desc'  => __( 'Optimised for screen readers — content first, no fluff.', 'beepbeep-ai-alt-text-generator' ),
	),
);

$nai_ap_lengths = array(
	array(
		'id'    => 'short',
		'label' => __( 'Short', 'beepbeep-ai-alt-text-generator' ),
		'range' => __( '5–10 words', 'beepbeep-ai-alt-text-generator' ),
	),
	array(
		'id'    => 'medium',
		'label' => __( 'Medium', 'beepbeep-ai-alt-text-generator' ),
		'range' => __( '10–18 words', 'beepbeep-ai-alt-text-generator' ),
	),
	array(
		'id'    => 'long',
		'label' => __( 'Long', 'beepbeep-ai-alt-text-generator' ),
		'range' => __( '18–28 words', 'beepbeep-ai-alt-text-generator' ),
	),
);

$nai_ap_active_style  = isset( $nai_ap_options['generation_style'] ) ? sanitize_key( (string) $nai_ap_options['generation_style'] ) : 'descriptive';
$nai_ap_active_length = isset( $nai_ap_options['generation_length'] ) ? sanitize_key( (string) $nai_ap_options['generation_length'] ) : 'medium';
$nai_ap_instructions  = isset( $nai_ap_options['custom_instructions'] ) ? (string) $nai_ap_options['custom_instructions'] : '';

$nai_ap_icon = static function ( string $name, int $size = 16, float $stroke = 1.75 ): string {
	$paths = array(
		'zap'      => '<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/>',
		'crown'    => '<path d="m2 19 2-11 5 5 3-7 3 7 5-5 2 11H2Z"/><path d="M2 21h20"/>',
		'check'    => '<path d="m5 12 5 5 9-11"/>',
		'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
		'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
		'bell'     => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10 21a2 2 0 0 0 4 0"/>',
		'lock'     => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 1 1 8 0v4"/>',
		'eye'      => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
	);
	$body  = $paths[ $name ] ?? '';
	return sprintf(
		'<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="%2$s" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%3$s</svg>',
		$size,
		esc_attr( (string) $stroke ),
		$body // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG path data only.
	);
};

// Sample preview text for the active style.
$nai_ap_samples = array(
	'descriptive'   => __( 'Wooden cafe table with a white ceramic mug holding a cappuccino, leaf-shaped foam art on top, soft morning light streaming in from the left window.', 'beepbeep-ai-alt-text-generator' ),
	'seo'           => __( 'Cappuccino latte art in a white ceramic mug, handcrafted by our downtown barista, served fresh each morning at our flagship coffee shop.', 'beepbeep-ai-alt-text-generator' ),
	'ecommerce'     => __( 'Matte white 12oz ceramic cappuccino mug with hand-poured leaf-pattern foam art, dishwasher and microwave safe.', 'beepbeep-ai-alt-text-generator' ),
	'accessibility' => __( 'A white ceramic mug containing a cappuccino with leaf-shaped foam latte art, resting on a wooden cafe table.', 'beepbeep-ai-alt-text-generator' ),
);
$nai_ap_sample  = $nai_ap_samples[ $nai_ap_active_style ] ?? $nai_ap_samples['descriptive'];
?>
<div class="nai-screen nai-screen--autopilot" data-nai-screen="autopilot">

	<div class="nai-page-header">
		<div class="nai-eyebrow"><?php esc_html_e( 'Autopilot', 'beepbeep-ai-alt-text-generator' ); ?></div>
		<h1 class="nai-page-header__title"><?php esc_html_e( 'Hands-off image SEO', 'beepbeep-ai-alt-text-generator' ); ?></h1>
		<p class="nai-page-header__sub"><?php esc_html_e( 'Decide how BeepBeep AI writes ALT text — and let it run quietly in the background on every new upload.', 'beepbeep-ai-alt-text-generator' ); ?></p>
	</div>

	<?php // -------- HERO: master Autopilot toggle / upsell -------- ?>
	<?php if ( ! $nai_ap_is_pro ) : ?>
		<div class="nai-ap-hero nai-ap-hero--upsell">
			<div class="nai-ap-hero__row">
				<div class="nai-ap-hero__icon nai-ap-hero__icon--brand"><?php echo $nai_ap_icon( 'crown', 17, 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<div class="nai-ap-hero__body">
					<div class="nai-ap-hero__title">
						<?php esc_html_e( 'Auto-optimise new uploads', 'beepbeep-ai-alt-text-generator' ); ?>
						<span class="nai-chip nai-chip--primary"><?php esc_html_e( 'Pro', 'beepbeep-ai-alt-text-generator' ); ?></span>
					</div>
					<div class="nai-ap-hero__desc"><?php esc_html_e( 'Every image uploaded to your media library gets ALT text in seconds. Free users can still generate manually from the Library.', 'beepbeep-ai-alt-text-generator' ); ?></div>
				</div>
				<button class="nai-btn nai-btn--pro nai-btn--sm" type="button" data-action="<?php echo esc_attr( $nai_ap_upgrade_action ); ?>" data-bbai-pricing-variant="growth">
					<?php echo $nai_ap_icon( 'crown', 14, 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php esc_html_e( 'Upgrade', 'beepbeep-ai-alt-text-generator' ); ?>
				</button>
			</div>
		</div>
	<?php else : ?>
		<div class="nai-ap-hero <?php echo $nai_ap_auto_on ? 'nai-ap-hero--on' : ''; ?>">
			<div class="nai-ap-hero__row">
				<div class="nai-ap-hero__icon <?php echo $nai_ap_auto_on ? 'nai-ap-hero__icon--on' : ''; ?>"><?php echo $nai_ap_icon( 'zap', 18, 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<div class="nai-ap-hero__body">
					<div class="nai-ap-hero__title">
						<?php esc_html_e( 'Auto-optimise new uploads', 'beepbeep-ai-alt-text-generator' ); ?>
						<?php if ( $nai_ap_auto_on ) : ?>
							<span class="nai-chip nai-chip--ok"><span class="nai-chip__dot" style="background:var(--nai-ok)"></span><?php esc_html_e( 'Running', 'beepbeep-ai-alt-text-generator' ); ?></span>
						<?php else : ?>
							<span class="nai-chip"><?php esc_html_e( 'Paused', 'beepbeep-ai-alt-text-generator' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="nai-ap-hero__desc">
						<?php
						echo esc_html(
							$nai_ap_auto_on
								? __( 'BeepBeep AI will generate ALT text for every new image the moment it hits your media library.', 'beepbeep-ai-alt-text-generator' )
								: __( 'Turn this on so you never have to think about ALT text again.', 'beepbeep-ai-alt-text-generator' )
						);
						?>
					</div>
				</div>
				<a class="nai-toggle nai-toggle--lg <?php echo $nai_ap_auto_on ? 'nai-toggle--on nai-toggle--ok' : ''; ?>" href="<?php echo esc_url( $nai_ap_settings_url ); ?>" role="switch" aria-checked="<?php echo $nai_ap_auto_on ? 'true' : 'false'; ?>" aria-label="<?php esc_attr_e( 'Toggle Autopilot in settings', 'beepbeep-ai-alt-text-generator' ); ?>">
					<span class="nai-toggle__knob"></span>
				</a>
			</div>
		</div>
	<?php endif; ?>

	<?php // -------- Generation preferences -------- ?>
	<div class="nai-ap-section">
		<div class="nai-eyebrow"><?php esc_html_e( 'Generation', 'beepbeep-ai-alt-text-generator' ); ?></div>
		<h2><?php esc_html_e( 'How BeepBeep AI writes', 'beepbeep-ai-alt-text-generator' ); ?></h2>
		<div class="nai-ap-section__sub"><?php esc_html_e( 'These preferences apply to every image — manual and automated.', 'beepbeep-ai-alt-text-generator' ); ?></div>
	</div>

	<div class="nai-card" style="margin-bottom:12px;">
		<div style="padding:16px 20px;border-bottom:1px solid var(--nai-hairline);">
			<div class="nai-label"><?php esc_html_e( 'Description style', 'beepbeep-ai-alt-text-generator' ); ?></div>
			<div class="nai-preset-grid">
				<?php foreach ( $nai_ap_presets as $preset ) : ?>
					<a class="nai-preset <?php echo $preset['id'] === $nai_ap_active_style ? 'nai-preset--active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'nai_style', $preset['id'], $nai_ap_settings_url ) ); ?>">
						<div class="nai-preset__head">
							<span class="nai-preset__radio"></span>
							<span class="nai-preset__label"><?php echo esc_html( $preset['label'] ); ?></span>
						</div>
						<div class="nai-preset__desc"><?php echo esc_html( $preset['desc'] ); ?></div>
					</a>
				<?php endforeach; ?>
			</div>
		</div>

		<div style="padding:16px 20px;border-bottom:1px solid var(--nai-hairline);">
			<div class="nai-label"><?php esc_html_e( 'Length', 'beepbeep-ai-alt-text-generator' ); ?></div>
			<div class="nai-seg">
				<?php foreach ( $nai_ap_lengths as $length_opt ) : ?>
					<a class="nai-seg__btn <?php echo $length_opt['id'] === $nai_ap_active_length ? 'nai-seg__btn--on' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'nai_length', $length_opt['id'], $nai_ap_settings_url ) ); ?>">
						<?php echo esc_html( $length_opt['label'] ); ?>
						<span class="nai-seg__range"><?php echo esc_html( $length_opt['range'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>

		<div style="padding:16px 20px;">
			<div style="display:flex;align-items:baseline;justify-content:space-between;">
				<div class="nai-label">
					<?php esc_html_e( 'Custom instructions', 'beepbeep-ai-alt-text-generator' ); ?>
					<span class="nai-label__hint"><?php esc_html_e( 'Optional', 'beepbeep-ai-alt-text-generator' ); ?></span>
				</div>
				<span class="nai-mono" style="font-size:11px;color:var(--nai-text-3);"><?php echo esc_html( (string) strlen( $nai_ap_instructions ) ); ?>/280</span>
			</div>
			<textarea class="nai-textarea" rows="3" placeholder="<?php esc_attr_e( 'e.g. "Mention our brand name when relevant, never use marketing fluff."', 'beepbeep-ai-alt-text-generator' ); ?>" readonly><?php echo esc_textarea( $nai_ap_instructions ); ?></textarea>
		</div>
	</div>

	<?php // -------- Live preview -------- ?>
	<div class="nai-preview">
		<div class="nai-preview__head">
			<span style="display:inline-flex;align-items:center;gap:7px;">
				<span style="color:var(--nai-text-3);display:inline-flex;"><?php echo $nai_ap_icon( 'eye', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span class="nai-eyebrow"><?php esc_html_e( 'Live preview', 'beepbeep-ai-alt-text-generator' ); ?></span>
			</span>
			<span class="nai-mono" style="font-size:11px;color:var(--nai-text-3);">
				<?php
				foreach ( $nai_ap_presets as $preset ) {
					if ( $preset['id'] === $nai_ap_active_style ) {
						echo esc_html( $preset['label'] );
						break;
					}
				}
				?>
			</span>
		</div>
		<div class="nai-preview__body">
			<div class="nai-thumb nai-preview__thumb" style="background-color:oklch(0.93 0.02 30);background-image:repeating-linear-gradient(45deg, oklch(0.88 0.025 30) 0, oklch(0.88 0.025 30) 6px, oklch(0.93 0.02 30) 6px, oklch(0.93 0.02 30) 12px);">sample</div>
			<div style="flex:1;min-width:0;">
				<div class="nai-eyebrow" style="margin-bottom:5px;"><?php esc_html_e( 'Generated ALT', 'beepbeep-ai-alt-text-generator' ); ?></div>
				<div class="nai-preview__alt"><?php echo esc_html( $nai_ap_sample ); ?></div>
			</div>
		</div>
	</div>

	<?php // -------- Scheduled background work (Pro) -------- ?>
	<div class="nai-ap-section">
		<div class="nai-eyebrow"><?php esc_html_e( 'Run on a schedule', 'beepbeep-ai-alt-text-generator' ); ?></div>
		<h2><?php esc_html_e( 'Background work', 'beepbeep-ai-alt-text-generator' ); ?></h2>
		<div class="nai-ap-section__sub"><?php esc_html_e( 'Optional Pro extras that keep your library healthy without you opening BeepBeep AI.', 'beepbeep-ai-alt-text-generator' ); ?></div>
	</div>

	<div class="nai-card" style="margin-bottom:14px;">
		<?php
		$nai_ap_sched_rows = array(
			array(
				'icon'  => 'calendar',
				'title' => __( 'Daily library scan', 'beepbeep-ai-alt-text-generator' ),
				'desc'  => __( 'Sweep your media library every morning to catch missing or low-quality ALT text.', 'beepbeep-ai-alt-text-generator' ),
				'on'    => $nai_ap_is_pro,
			),
			array(
				'icon'  => 'mail',
				'title' => __( 'Weekly digest email', 'beepbeep-ai-alt-text-generator' ),
				'desc'  => __( 'A Sunday health report: what was optimised, what needs review, coverage trend.', 'beepbeep-ai-alt-text-generator' ),
				'on'    => $nai_ap_is_pro,
			),
			array(
				'icon'  => 'bell',
				'title' => __( 'SEO drift alerts', 'beepbeep-ai-alt-text-generator' ),
				'desc'  => __( 'Notify when content updates make existing ALT text stale or off-topic.', 'beepbeep-ai-alt-text-generator' ),
				'on'    => false,
			),
		);
		foreach ( $nai_ap_sched_rows as $row ) :
			$row_locked = ! $nai_ap_is_pro;
			$icon_cls   = $row_locked ? 'nai-sched-row__icon--locked' : ( $row['on'] ? 'nai-sched-row__icon--on' : '' );
			?>
			<div class="nai-sched-row">
				<div class="nai-sched-row__icon <?php echo esc_attr( $icon_cls ); ?>">
					<?php echo $nai_ap_icon( $row_locked ? 'lock' : $row['icon'], 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<div class="nai-sched-row__body">
					<div class="nai-sched-row__title">
						<?php echo esc_html( $row['title'] ); ?>
						<?php if ( $row_locked ) : ?>
							<span class="nai-chip"><?php esc_html_e( 'Pro', 'beepbeep-ai-alt-text-generator' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="nai-sched-row__desc"><?php echo esc_html( $row['desc'] ); ?></div>
				</div>
				<span class="nai-toggle nai-toggle--lg <?php echo $row['on'] && ! $row_locked ? 'nai-toggle--on nai-toggle--ok' : ''; ?>" role="img" aria-label="<?php echo esc_attr( $row['on'] && ! $row_locked ? __( 'Enabled', 'beepbeep-ai-alt-text-generator' ) : __( 'Disabled', 'beepbeep-ai-alt-text-generator' ) ); ?>">
					<span class="nai-toggle__knob"></span>
				</span>
			</div>
		<?php endforeach; ?>
	</div>

	<div style="display:flex;justify-content:flex-end;gap:8px;margin-top:18px;">
		<a class="nai-btn nai-btn--ghost nai-btn--md" href="<?php echo esc_url( $nai_ap_settings_url ); ?>"><?php esc_html_e( 'Open full settings', 'beepbeep-ai-alt-text-generator' ); ?></a>
	</div>
</div>
