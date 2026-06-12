<?php
/**
 * nAi Settings screen — plan summary, account, notifications, advanced.
 *
 * Pure presentation surface that wraps the design language around values
 * already resolved by Plan_Helpers + Usage_Helper. Form actions stay on the
 * existing settings endpoints so this can be used as the public view.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals are scoped to this included partial.

if ( ! class_exists( \BeepBeepAI\AltTextGenerator\Admin\Plan_Helpers::class, false ) ) {
	require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/class-plan-helpers.php';
}
if ( ! class_exists( \BeepBeepAI\AltTextGenerator\Services\Usage_Helper::class, false ) ) {
	require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-usage-helper.php';
}

$nai_set_options = is_array( get_option( 'bbai_options', array() ) ) ? get_option( 'bbai_options', array() ) : array();
$nai_set_plan    = class_exists( '\BeepBeepAI\AltTextGenerator\Admin\Plan_Helpers' )
	? \BeepBeepAI\AltTextGenerator\Admin\Plan_Helpers::get_plan_data()
	: array();
$nai_set_plan_slug = sanitize_key( (string) ( $nai_set_plan['plan_slug'] ?? 'free' ) );

$nai_set_api_client = isset( $this, $this->api_client ) && is_object( $this->api_client ) ? $this->api_client : null;
$nai_set_usage      = class_exists( '\BeepBeepAI\AltTextGenerator\Services\Usage_Helper' )
	? \BeepBeepAI\AltTextGenerator\Services\Usage_Helper::get_usage( $nai_set_api_client, true )
	: array();
if ( ! is_array( $nai_set_usage ) ) {
	$nai_set_usage = array();
}
$nai_set_used     = max( 0, (int) ( $nai_set_usage['used'] ?? $nai_set_usage['credits_used'] ?? 0 ) );
$nai_set_limit    = max( 1, (int) ( $nai_set_usage['limit'] ?? $nai_set_usage['credits_total'] ?? 50 ) );
$nai_set_min_limit = array(
	'starter'    => 100,
	'growth'     => 1000,
	'pro'        => 1000,
	'agency'     => 1000,
	'enterprise' => 1000,
);
if ( isset( $nai_set_min_limit[ $nai_set_plan_slug ] ) && $nai_set_limit < $nai_set_min_limit[ $nai_set_plan_slug ] ) {
	$nai_set_plan_slug = 'free';
}
$nai_set_is_paid      = in_array( $nai_set_plan_slug, array( 'starter', 'growth', 'pro', 'agency', 'enterprise' ), true );
$nai_set_can_autopilot = class_exists( '\BeepBeepAI\AltTextGenerator\Admin\Plan_Helpers' )
	? \BeepBeepAI\AltTextGenerator\Admin\Plan_Helpers::plan_can_use_autopilot( $nai_set_plan_slug )
	: in_array( $nai_set_plan_slug, array( 'growth', 'pro', 'agency', 'enterprise' ), true );
$nai_set_is_pro       = $nai_set_can_autopilot;
$nai_set_remain   = max( 0, $nai_set_limit - $nai_set_used );
$nai_set_pct      = $nai_set_limit > 0 ? min( 100, (int) round( ( $nai_set_used / $nai_set_limit ) * 100 ) ) : 0;
$nai_set_reset    = isset( $nai_set_usage['reset_date'] ) ? (string) $nai_set_usage['reset_date'] : '';
$nai_set_resetfmt = '' !== $nai_set_reset ? gmdate( 'F j, Y', strtotime( $nai_set_reset ) ) : __( 'next month', 'beepbeep-ai-alt-text-generator' );

$nai_set_user_email = '';
if ( isset( $this, $this->api_client ) && is_object( $this->api_client ) && method_exists( $this->api_client, 'get_user_data' ) ) {
	$nai_set_user_data  = (array) $this->api_client->get_user_data();
	$nai_set_user_email = sanitize_email( (string) ( $nai_set_user_data['email'] ?? '' ) );
}

$nai_set_has_active_subscription = false;
$nai_set_billing_sources         = array(
	$nai_set_usage,
	is_array( $nai_set_usage['billing'] ?? null ) ? $nai_set_usage['billing'] : array(),
	is_array( $nai_set_usage['entitlement_state'] ?? null ) ? $nai_set_usage['entitlement_state'] : array(),
);
foreach ( $nai_set_billing_sources as $nai_set_billing_source ) {
	if ( ! is_array( $nai_set_billing_source ) ) {
		continue;
	}
	$nai_set_sub_id = (string) ( $nai_set_billing_source['stripe_subscription_id'] ?? $nai_set_billing_source['subscription_id'] ?? '' );
	$nai_set_sub_status = strtolower( (string) ( $nai_set_billing_source['subscription_status'] ?? $nai_set_billing_source['stripe_subscription_status'] ?? $nai_set_billing_source['status'] ?? '' ) );
	if ( '' !== trim( $nai_set_sub_id ) && ( '' === $nai_set_sub_status || in_array( $nai_set_sub_status, array( 'active', 'trialing', 'past_due' ), true ) ) ) {
		$nai_set_has_active_subscription = true;
		break;
	}
}

$nai_set_auto_on   = ! empty( $nai_set_options['auto_generate'] ) && $nai_set_can_autopilot;
$nai_set_notif_dig = ! empty( $nai_set_options['weekly_digest'] ) && $nai_set_can_autopilot;
$nai_set_notif_lim = ! isset( $nai_set_options['quota_warnings'] ) || ! empty( $nai_set_options['quota_warnings'] );
$nai_set_notif_new = ! isset( $nai_set_options['new_upload_alerts'] ) || ! empty( $nai_set_options['new_upload_alerts'] );

$nai_set_full_url  = admin_url( 'admin.php?page=bbai-settings&tab=general' );
$nai_set_usage_url = admin_url( 'admin.php?page=bbai-credit-usage' );

$nai_set_icon = static function ( string $name, int $size = 16, float $stroke = 1.75 ): string {
	$paths = array(
		'crown'    => '<path d="m2 19 2-11 5 5 3-7 3 7 5-5 2 11H2Z"/><path d="M2 21h20"/>',
		'shield'   => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6l-8-3Z"/>',
		'external' => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6M10 14 21 3"/>',
	);
	$body  = $paths[ $name ] ?? '';
	return sprintf(
		'<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="%2$s" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%3$s</svg>',
		$size,
		esc_attr( (string) $stroke ),
		$body // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG path data only.
	);
};

$nai_set_render_row = static function ( string $label, string $desc, string $right_html ) {
	?>
	<div class="nai-set-row">
		<div class="nai-set-row__main">
			<div class="nai-set-row__label"><?php echo esc_html( $label ); ?></div>
			<div class="nai-set-row__desc"><?php echo esc_html( $desc ); ?></div>
		</div>
		<div class="nai-set-row__right"><?php echo $right_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Toggle markup built locally. ?></div>
	</div>
	<?php
};

$nai_set_toggle_html = static function ( bool $on, string $aria_label = '' ): string {
	$classes = 'nai-toggle nai-toggle--lg' . ( $on ? ' nai-toggle--on nai-toggle--ok' : '' );
	return sprintf(
		'<span class="%1$s" role="img" aria-label="%2$s"><span class="nai-toggle__knob"></span></span>',
		esc_attr( $classes ),
		esc_attr( $aria_label )
	);
};

$nai_set_progress_tone = $nai_set_pct >= 90 ? 'nai-progress--danger' : ( $nai_set_pct >= 75 ? 'nai-progress--warn' : 'nai-progress--primary' );
$nai_shell_active = 'settings';
$nai_shell_is_pro = $nai_set_can_autopilot;
require BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/nai-shell-open.php';

$nai_set_plan_label = array(
	'starter'    => __( 'Starter', 'beepbeep-ai-alt-text-generator' ),
	'growth'     => __( 'Growth', 'beepbeep-ai-alt-text-generator' ),
	'pro'        => __( 'Growth', 'beepbeep-ai-alt-text-generator' ),
	'agency'     => __( 'Agency', 'beepbeep-ai-alt-text-generator' ),
	'enterprise' => __( 'Enterprise', 'beepbeep-ai-alt-text-generator' ),
	'free'       => __( 'Free', 'beepbeep-ai-alt-text-generator' ),
)[ $nai_set_plan_slug ] ?? __( 'Free', 'beepbeep-ai-alt-text-generator' );
$nai_set_plan_name = $nai_set_is_paid
	? sprintf(
		/* translators: %s: paid plan label. */
		__( 'BeepBeep AI %s', 'beepbeep-ai-alt-text-generator' ),
		$nai_set_plan_label
	)
	: __( 'Free plan', 'beepbeep-ai-alt-text-generator' );
?>
<div class="nai-screen nai-screen--settings" data-nai-screen="settings">

	<div class="nai-page-header">
		<div class="nai-eyebrow"><?php esc_html_e( 'Settings', 'beepbeep-ai-alt-text-generator' ); ?></div>
		<h1 class="nai-page-header__title"><?php esc_html_e( 'Plan & preferences', 'beepbeep-ai-alt-text-generator' ); ?></h1>
		<p class="nai-page-header__sub"><?php esc_html_e( 'Manage your account, notifications, and advanced options.', 'beepbeep-ai-alt-text-generator' ); ?></p>
	</div>

	<?php // -------- PLAN CARD -------- ?>
	<div class="nai-plan-card <?php echo $nai_set_is_paid ? 'nai-plan-card--pro' : ''; ?>">
		<div class="nai-plan-card__row">
			<div class="nai-plan-card__icon">
				<?php echo $nai_set_icon( $nai_set_is_paid ? 'crown' : 'shield', 18, 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<div style="flex:1;min-width:0;">
				<div class="nai-plan-card__name">
					<?php echo esc_html( $nai_set_plan_name ); ?>
					<span class="nai-chip <?php echo $nai_set_is_paid ? 'nai-chip--primary' : ''; ?>"><?php echo esc_html( $nai_set_plan_label ); ?></span>
				</div>
				<div class="nai-plan-card__desc">
					<?php
					echo esc_html(
							$nai_set_can_autopilot
								? __( '1,000 AI generations per month · no daily cap within your monthly allowance · bulk processing and Autopilot.', 'beepbeep-ai-alt-text-generator' )
								: ( $nai_set_is_paid
								? __( '100 AI generations per month · manual generation only.', 'beepbeep-ai-alt-text-generator' )
								: sprintf(
								/* translators: %d: monthly free generations included with Free plan. */
								__( 'Up to %d AI generations per month · manual generation only.', 'beepbeep-ai-alt-text-generator' ),
								$nai_set_limit
							) )
					);
					?>
				</div>
			</div>
			<?php if ( $nai_set_is_paid && $nai_set_has_active_subscription ) : ?>
				<a class="nai-btn nai-btn--secondary nai-btn--sm" href="<?php echo esc_url( $nai_set_usage_url ); ?>">
					<?php echo $nai_set_icon( 'external', 14, 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php esc_html_e( 'Manage billing', 'beepbeep-ai-alt-text-generator' ); ?>
				</a>
			<?php elseif ( $nai_set_is_paid ) : ?>
				<a class="nai-btn nai-btn--secondary nai-btn--sm" href="<?php echo esc_url( $nai_set_usage_url ); ?>">
					<?php echo $nai_set_icon( 'external', 14, 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php esc_html_e( 'View usage', 'beepbeep-ai-alt-text-generator' ); ?>
				</a>
			<?php else : ?>
					<button class="nai-btn nai-btn--pro nai-btn--sm" type="button" data-nai-open-paywall="default" data-bbai-pricing-variant="starter">
						<?php echo $nai_set_icon( 'crown', 14, 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php esc_html_e( 'Choose a plan', 'beepbeep-ai-alt-text-generator' ); ?>
				</button>
			<?php endif; ?>
		</div>

		<div class="nai-plan-card__usage">
			<?php if ( $nai_set_is_paid ) : ?>
				<div style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;">
					<div style="display:flex;align-items:center;gap:8px;">
						<span class="nai-pulse-dot" style="width:6px;height:6px;"></span>
						<span style="font-size:12px;color:var(--nai-text-2);font-weight:500;"><?php echo esc_html( $nai_set_can_autopilot ? __( 'Continuous optimisation available', 'beepbeep-ai-alt-text-generator' ) : __( 'Manual generation plan', 'beepbeep-ai-alt-text-generator' ) ); ?></span>
					</div>
					<div class="nai-tnum" style="font-size:11.5px;color:var(--nai-text-3);">
						<span class="nai-mono" style="color:var(--nai-text-2);font-weight:500;"><?php echo esc_html( number_format_i18n( $nai_set_used ) ); ?></span>
						<?php esc_html_e( 'images improved this cycle', 'beepbeep-ai-alt-text-generator' ); ?>
						<span style="margin:0 6px;opacity:0.6;">·</span>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: renewal date. */
								__( 'Renews %s', 'beepbeep-ai-alt-text-generator' ),
								$nai_set_resetfmt
							)
						);
						?>
					</div>
				</div>
			<?php else : ?>
				<div class="nai-plan-card__usage-head">
					<span><?php esc_html_e( 'This billing cycle', 'beepbeep-ai-alt-text-generator' ); ?></span>
					<span class="nai-plan-card__usage-num"><span data-bbai-entitlement-used><?php echo esc_html( number_format_i18n( $nai_set_used ) ); ?></span> / <span data-bbai-entitlement-limit><?php echo esc_html( number_format_i18n( $nai_set_limit ) ); ?></span> <?php esc_html_e( 'AI generations used', 'beepbeep-ai-alt-text-generator' ); ?></span>
				</div>
				<div class="nai-progress <?php echo esc_attr( $nai_set_progress_tone ); ?>" style="height:5px;">
					<div class="nai-progress__bar" style="width:<?php echo (int) $nai_set_pct; ?>%;"></div>
				</div>
				<div class="nai-plan-card__usage-foot">
					<span><span data-bbai-entitlement-remaining><?php echo esc_html( number_format_i18n( $nai_set_remain ) ); ?></span> <?php esc_html_e( 'remaining', 'beepbeep-ai-alt-text-generator' ); ?></span>
					<span>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: monthly reset date. */
								__( 'Resets %s', 'beepbeep-ai-alt-text-generator' ),
								$nai_set_resetfmt
							)
						);
						?>
					</span>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<?php // -------- ACCOUNT -------- ?>
	<div class="nai-set-section">
		<div class="nai-set-section__head">
			<div class="nai-eyebrow" style="margin-bottom:2px;"><?php esc_html_e( 'Sign-in', 'beepbeep-ai-alt-text-generator' ); ?></div>
			<h2 class="nai-set-section__title"><?php esc_html_e( 'Account', 'beepbeep-ai-alt-text-generator' ); ?></h2>
		</div>
		<?php
		$nai_set_render_row(
			__( 'Email', 'beepbeep-ai-alt-text-generator' ),
			__( 'The address connected to your BeepBeep AI account.', 'beepbeep-ai-alt-text-generator' ),
			'<span class="nai-mono" style="font-size:13px;color:var(--nai-text-2);">' . esc_html( '' !== $nai_set_user_email ? $nai_set_user_email : __( 'Not connected', 'beepbeep-ai-alt-text-generator' ) ) . '</span>'
		);
		$nai_set_render_row(
			__( 'Connection', 'beepbeep-ai-alt-text-generator' ),
			__( 'Last successful sync with BeepBeep AI servers.', 'beepbeep-ai-alt-text-generator' ),
			'<span class="nai-chip nai-chip--ok"><span class="nai-chip__dot" style="background:var(--nai-ok)"></span>' . esc_html__( 'Connected', 'beepbeep-ai-alt-text-generator' ) . '</span>'
		);
		?>
	</div>

	<?php // -------- NOTIFICATIONS -------- ?>
	<div class="nai-set-section">
		<div class="nai-set-section__head">
			<div class="nai-eyebrow" style="margin-bottom:2px;"><?php esc_html_e( 'Email & in-app', 'beepbeep-ai-alt-text-generator' ); ?></div>
			<h2 class="nai-set-section__title"><?php esc_html_e( 'Notifications', 'beepbeep-ai-alt-text-generator' ); ?></h2>
		</div>
		<?php
		$nai_set_render_row(
			__( 'New upload alerts', 'beepbeep-ai-alt-text-generator' ),
			__( 'In-app banner when fresh uploads need attention.', 'beepbeep-ai-alt-text-generator' ),
			$nai_set_toggle_html( $nai_set_notif_new, __( 'New upload alerts', 'beepbeep-ai-alt-text-generator' ) )
		);
		$nai_set_render_row(
			__( 'Weekly digest', 'beepbeep-ai-alt-text-generator' ),
			$nai_set_can_autopilot
				? __( 'Sunday email with coverage + activity summary.', 'beepbeep-ai-alt-text-generator' )
				: __( 'Growth · Sunday email with coverage + activity summary.', 'beepbeep-ai-alt-text-generator' ),
			$nai_set_toggle_html( $nai_set_notif_dig, __( 'Weekly digest', 'beepbeep-ai-alt-text-generator' ) )
		);
		$nai_set_render_row(
			__( 'Quota warnings', 'beepbeep-ai-alt-text-generator' ),
			__( "Email me when I'm close to my monthly credit limit.", 'beepbeep-ai-alt-text-generator' ),
			$nai_set_toggle_html( $nai_set_notif_lim, __( 'Quota warnings', 'beepbeep-ai-alt-text-generator' ) )
		);
		?>
	</div>

	<?php // -------- DANGER ZONE -------- ?>
	<div class="nai-set-section">
		<div class="nai-set-section__head">
			<div class="nai-eyebrow" style="margin-bottom:2px;"><?php esc_html_e( 'Destructive', 'beepbeep-ai-alt-text-generator' ); ?></div>
			<h2 class="nai-set-section__title"><?php esc_html_e( 'Danger zone', 'beepbeep-ai-alt-text-generator' ); ?></h2>
		</div>
		<?php
		$nai_set_render_row(
			__( 'Reset generated ALT text', 'beepbeep-ai-alt-text-generator' ),
			__( 'Clear all BeepBeep AI-generated ALT from your library. This cannot be undone.', 'beepbeep-ai-alt-text-generator' ),
			'<a class="nai-btn nai-btn--secondary nai-btn--sm" href="' . esc_url( $nai_set_full_url ) . '">' . esc_html__( 'Reset…', 'beepbeep-ai-alt-text-generator' ) . '</a>'
		);
		$nai_set_render_row(
			__( 'Delete data on uninstall', 'beepbeep-ai-alt-text-generator' ),
			__( 'Remove all BeepBeep AI settings and history when the plugin is uninstalled.', 'beepbeep-ai-alt-text-generator' ),
			$nai_set_toggle_html( ! empty( $nai_set_options['uninstall_remove_data'] ), __( 'Delete data on uninstall', 'beepbeep-ai-alt-text-generator' ) )
		);
		?>
	</div>

	<div style="display:flex;justify-content:flex-end;gap:8px;margin-top:18px;">
		<a class="nai-btn nai-btn--secondary nai-btn--md" href="<?php echo esc_url( $nai_set_full_url ); ?>"><?php esc_html_e( 'Open full settings', 'beepbeep-ai-alt-text-generator' ); ?></a>
	</div>

	<?php require BEEPBEEP_AI_PLUGIN_DIR . 'admin/components/dashboard/dashboard-prototype-overlays.php'; ?>
</div>
</div>
