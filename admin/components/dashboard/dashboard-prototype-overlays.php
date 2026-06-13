<?php
/**
 * nAi dashboard component.
 *
 * @package BeepBeep_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$nai_scan_total   = isset( $nai_total ) ? max( 0, (int) $nai_total ) : 0;
$nai_scan_missing = isset( $nai_missing ) ? max( 0, (int) $nai_missing ) : 0;
$nai_scan_review  = isset( $nai_weak ) ? max( 0, (int) $nai_weak ) : 0;
$nai_show_tweak_bar = ! empty( $nai_show_tweaks ) || ! empty( $nai_shell_prototype );
if ( ! isset( $nai_icon ) || ! is_callable( $nai_icon ) ) {
	$nai_icon = static function ( string $name, int $size = 16, float $stroke = 1.75 ): string {
		$paths = array(
			'check'  => '<path d="m5 12 5 5 9-11"/>',
			'crown'  => '<path d="m2 19 2-11 5 5 3-7 3 7 5-5 2 11H2Z"/><path d="M2 21h20"/>',
			'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
			'shield' => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6l-8-3Z"/>',
			'x'      => '<path d="M18 6 6 18M6 6l12 12"/>',
		);
		$body  = $paths[ $name ] ?? '';
		return sprintf(
			'<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="%2$s" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%3$s</svg>',
			$size,
			esc_attr( (string) $stroke ),
			$body // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG path data only.
		);
	};
}
?>
	<?php // -------- Prototype pop-outs: onboarding, generation drawer, paywall, sign-out, toast -------- ?>
	<div class="nai-modal" hidden data-nai-modal="onboarding" aria-hidden="true">
		<div class="nai-modal__backdrop" data-nai-close-modal></div>
		<section class="nai-modal__card nai-modal__card--onboarding" role="dialog" aria-modal="true" aria-labelledby="nai-onboarding-title">
			<div class="nai-steps"><span class="is-active"></span><span></span><span></span></div>
			<div class="nai-modal__eyebrow" data-nai-onboarding-step><?php esc_html_e( 'Step 1 of 3 · Welcome', 'beepbeep-ai-alt-text-generator' ); ?></div>
			<div class="nai-onboarding-panel is-active" data-step="0">
				<div class="nai-modal__icon"><?php echo $nai_icon( 'shield', 26, 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<h2 id="nai-onboarding-title"><?php esc_html_e( 'Welcome to BeepBeep AI.', 'beepbeep-ai-alt-text-generator' ); ?></h2>
				<p><?php esc_html_e( 'Image SEO that runs continuously in the background. We monitor your media library, generate ALT text for new uploads, and keep your score climbing.', 'beepbeep-ai-alt-text-generator' ); ?></p>
				<div class="nai-onboarding-list">
					<span><?php echo $nai_icon( 'check', 13, 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Daily scans for missing ALT text', 'beepbeep-ai-alt-text-generator' ); ?></span>
					<span><?php echo $nai_icon( 'check', 13, 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( '5 images per day included free', 'beepbeep-ai-alt-text-generator' ); ?></span>
					<span><?php echo $nai_icon( 'check', 13, 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Autopilot available with Pro', 'beepbeep-ai-alt-text-generator' ); ?></span>
				</div>
			</div>
			<div class="nai-onboarding-panel" data-step="1">
				<h2><?php esc_html_e( 'Scanning your library', 'beepbeep-ai-alt-text-generator' ); ?></h2>
				<p><?php esc_html_e( "We'll look at every image and tell you what's missing. This won't use any credits.", 'beepbeep-ai-alt-text-generator' ); ?></p>
				<div class="nai-scan-box">
					<div><span data-nai-scan-label><?php esc_html_e( 'Reading media library…', 'beepbeep-ai-alt-text-generator' ); ?></span><strong data-nai-scan-pct>0%</strong></div>
					<div class="nai-progress"><div class="nai-progress__bar" style="width:0%;" data-nai-scan-bar></div></div>
					<div class="nai-scan-stats">
						<span>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: total media library image count. */
									__( '%s total', 'beepbeep-ai-alt-text-generator' ),
									number_format_i18n( $nai_scan_total )
								)
							);
							?>
						</span>
						<span>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: images missing ALT text count. */
									__( '%s missing ALT', 'beepbeep-ai-alt-text-generator' ),
									number_format_i18n( $nai_scan_missing )
								)
							);
							?>
						</span>
						<span>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: images that need ALT text review count. */
									__( '%s to review', 'beepbeep-ai-alt-text-generator' ),
									number_format_i18n( $nai_scan_review )
								)
							);
							?>
						</span>
					</div>
				</div>
			</div>
			<div class="nai-onboarding-panel" data-step="2">
				<div class="nai-modal__icon nai-modal__icon--ok"><?php echo $nai_icon( 'check', 26, 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<h2><?php esc_html_e( 'Your site is ready', 'beepbeep-ai-alt-text-generator' ); ?></h2>
				<p><?php esc_html_e( "We'll guide you through images gradually, then Autopilot can keep new uploads covered automatically.", 'beepbeep-ai-alt-text-generator' ); ?></p>
			</div>
			<div class="nai-modal__actions">
				<button class="nai-btn nai-btn--ghost nai-btn--md" type="button" data-nai-onboarding-back><?php esc_html_e( 'Back', 'beepbeep-ai-alt-text-generator' ); ?></button>
				<button class="nai-btn nai-btn--primary nai-btn--md" type="button" data-nai-onboarding-next><?php esc_html_e( 'Continue', 'beepbeep-ai-alt-text-generator' ); ?></button>
			</div>
		</section>
	</div>

	<div class="nai-modal" hidden data-nai-modal="paywall" aria-hidden="true">
		<div class="nai-modal__backdrop" data-nai-close-modal></div>
		<section class="nai-modal__card nai-modal__card--paywall" role="dialog" aria-modal="true" aria-labelledby="nai-paywall-title">
			<button class="nai-icon-btn nai-modal__close" type="button" data-nai-close-modal aria-label="<?php esc_attr_e( 'Close', 'beepbeep-ai-alt-text-generator' ); ?>"><?php echo $nai_icon( 'x', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
			<div class="nai-paywall__hero">
				<div class="nai-paywall__icon"><?php echo $nai_icon( 'crown', 22, 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<div>
					<span class="nai-chip nai-chip--primary"><?php esc_html_e( 'BeepBeep AI Pro', 'beepbeep-ai-alt-text-generator' ); ?></span>
					<h2 id="nai-paywall-title" data-nai-paywall-title><?php esc_html_e( 'Never worry about missing ALT again', 'beepbeep-ai-alt-text-generator' ); ?></h2>
					<p data-nai-paywall-subtitle><?php esc_html_e( 'Continuous, automated image SEO for your WordPress site — quietly running in the background.', 'beepbeep-ai-alt-text-generator' ); ?></p>
					<div class="nai-paywall__urgency" hidden data-nai-paywall-urgency></div>
				</div>
			</div>
			<div class="nai-paywall__columns">
				<div class="nai-paywall__column"><strong><?php esc_html_e( 'Free', 'beepbeep-ai-alt-text-generator' ); ?></strong><span><?php esc_html_e( '5 AI generations per day', 'beepbeep-ai-alt-text-generator' ); ?></span><span><?php esc_html_e( 'Up to 50 per month', 'beepbeep-ai-alt-text-generator' ); ?></span><span><?php esc_html_e( 'Manual generation only', 'beepbeep-ai-alt-text-generator' ); ?></span></div>
				<div class="nai-paywall__column"><strong><?php esc_html_e( 'Starter', 'beepbeep-ai-alt-text-generator' ); ?></strong><span><?php esc_html_e( '100 AI generations per month', 'beepbeep-ai-alt-text-generator' ); ?></span><span><?php esc_html_e( 'No daily cap within your 100', 'beepbeep-ai-alt-text-generator' ); ?></span><span><?php esc_html_e( 'Great for small sites', 'beepbeep-ai-alt-text-generator' ); ?></span></div>
				<div class="nai-paywall__column nai-paywall__column--pro"><strong><?php esc_html_e( 'Pro', 'beepbeep-ai-alt-text-generator' ); ?></strong><span><?php esc_html_e( '1,000 AI generations per month', 'beepbeep-ai-alt-text-generator' ); ?></span><span><?php esc_html_e( 'No daily cap within your 1,000', 'beepbeep-ai-alt-text-generator' ); ?></span><span><?php esc_html_e( 'Autopilot for new uploads', 'beepbeep-ai-alt-text-generator' ); ?></span></div>
			</div>
			<?php
			// Real Stripe checkout: reuse the no-JS direct-checkout handler
			// (Core::maybe_handle_direct_checkout) which creates a session for
			// the chosen plan's price and redirects. Price labels are filled
			// client-side from the live /plans catalog so they match Stripe.
			$nai_checkout_url = static function ( string $plan ): string {
				return add_query_arg(
					array(
						'page'        => 'bbai-checkout',
						'plan'        => $plan,
						'_bbai_nonce' => wp_create_nonce( 'bbai_direct_checkout' ),
					),
					admin_url( 'admin.php' )
				);
			};
			?>
			<a class="nai-btn nai-btn--pro nai-btn--lg nai-btn--full" href="<?php echo esc_url( $nai_checkout_url( 'pro' ) ); ?>" target="_blank" rel="noopener noreferrer" data-nai-paywall-cta data-nai-paywall-plan="pro">
				<?php esc_html_e( 'Upgrade to Pro', 'beepbeep-ai-alt-text-generator' ); ?><span data-nai-paywall-price="pro"></span>
			</a>
			<a class="nai-btn nai-btn--ghost nai-btn--full nai-paywall__starter-cta" href="<?php echo esc_url( $nai_checkout_url( 'starter' ) ); ?>" target="_blank" rel="noopener noreferrer" data-nai-paywall-cta data-nai-paywall-plan="starter">
				<?php esc_html_e( 'Or start with Starter', 'beepbeep-ai-alt-text-generator' ); ?><span data-nai-paywall-price="starter"></span>
			</a>
		</section>
	</div>

	<div class="nai-modal" hidden data-nai-modal="signout" aria-hidden="true">
		<div class="nai-modal__backdrop" data-nai-close-modal></div>
		<section class="nai-modal__card nai-modal__card--signout" role="dialog" aria-modal="true" aria-labelledby="nai-signout-title">
			<div class="nai-modal__icon"><?php echo $nai_icon( 'logout', 20, 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<h2 id="nai-signout-title"><?php esc_html_e( 'Sign out of BeepBeep AI?', 'beepbeep-ai-alt-text-generator' ); ?></h2>
			<p><?php esc_html_e( 'Autopilot will pause and no new images will be optimised until you sign back in. Previously generated ALT text stays on your site.', 'beepbeep-ai-alt-text-generator' ); ?></p>
			<div class="nai-modal__actions">
				<button class="nai-btn nai-btn--ghost nai-btn--md" type="button" data-nai-close-modal><?php esc_html_e( 'Stay signed in', 'beepbeep-ai-alt-text-generator' ); ?></button>
				<button class="nai-btn nai-btn--secondary nai-btn--md" type="button" data-nai-confirm-signout><?php esc_html_e( 'Sign out', 'beepbeep-ai-alt-text-generator' ); ?></button>
			</div>
		</section>
	</div>

		<?php if ( $nai_show_tweak_bar ) : ?>
		<div class="nai-tweaks" aria-label="<?php esc_attr_e( 'nAi demo triggers', 'beepbeep-ai-alt-text-generator' ); ?>">
			<div class="nai-tweaks__title"><?php esc_html_e( 'Tweaks', 'beepbeep-ai-alt-text-generator' ); ?></div>
			<button type="button" data-nai-open-onboarding><?php esc_html_e( 'Replay onboarding', 'beepbeep-ai-alt-text-generator' ); ?></button>
			<button type="button" data-nai-open-paywall="default"><?php esc_html_e( 'Open paywall', 'beepbeep-ai-alt-text-generator' ); ?></button>
			<button type="button" data-nai-open-drawer><?php esc_html_e( 'Generation drawer', 'beepbeep-ai-alt-text-generator' ); ?></button>
			<button type="button" data-nai-open-paywall="daily-limit"><?php esc_html_e( 'Trigger daily limit', 'beepbeep-ai-alt-text-generator' ); ?></button>
			<button type="button" data-nai-open-paywall="monthly-limit"><?php esc_html_e( 'Trigger monthly limit', 'beepbeep-ai-alt-text-generator' ); ?></button>
			<button type="button" data-nai-open-signout><?php esc_html_e( 'Trigger sign-out', 'beepbeep-ai-alt-text-generator' ); ?></button>
		</div>
		<?php endif; ?>
