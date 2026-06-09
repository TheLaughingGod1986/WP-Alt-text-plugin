<?php
/**
 * Shared nAi application shell/header.
 *
 * Expected optional locals:
 * - $nai_shell_active       dashboard|library|autopilot|settings
 * - $nai_shell_is_pro       bool
 * - $nai_shell_drawer_items array
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals are scoped to this included partial.

$nai_shell_active       = isset( $nai_shell_active ) ? sanitize_key( (string) $nai_shell_active ) : 'dashboard';
$nai_shell_is_pro       = ! empty( $nai_shell_is_pro );
$nai_shell_drawer_items = isset( $nai_shell_drawer_items ) && is_array( $nai_shell_drawer_items ) ? $nai_shell_drawer_items : array();
$nai_shell_prototype    = ! empty( $nai_shell_prototype );
$nai_shell_demo_trigger = isset( $nai_shell_demo_trigger ) ? sanitize_key( (string) $nai_shell_demo_trigger ) : '';
$nai_shell_user_email   = isset( $nai_shell_user_email ) ? sanitize_email( (string) $nai_shell_user_email ) : '';
$nai_shell_user_name    = isset( $nai_shell_user_name ) ? sanitize_text_field( (string) $nai_shell_user_name ) : '';
if ( '' === $nai_shell_user_email && isset( $bbai_client_user_email ) ) {
	$nai_shell_user_email = sanitize_email( (string) $bbai_client_user_email );
}
if ( '' === $nai_shell_user_email && isset( $bbai_account_summary['email'] ) ) {
	$nai_shell_user_email = sanitize_email( (string) $bbai_account_summary['email'] );
}
if ( '' === $nai_shell_user_email && function_exists( 'wp_get_current_user' ) ) {
	$nai_shell_current_user = wp_get_current_user();
	if ( $nai_shell_current_user && ! empty( $nai_shell_current_user->user_email ) ) {
		$nai_shell_user_email = sanitize_email( (string) $nai_shell_current_user->user_email );
	}
	if ( '' === $nai_shell_user_name && $nai_shell_current_user && ! empty( $nai_shell_current_user->display_name ) ) {
		$nai_shell_user_name = sanitize_text_field( (string) $nai_shell_current_user->display_name );
	}
}
if ( '' === $nai_shell_user_name ) {
	$nai_shell_user_name = '' !== $nai_shell_user_email ? $nai_shell_user_email : __( 'Connected account', 'beepbeep-ai-alt-text-generator' );
}
$nai_shell_initials_source = '' !== $nai_shell_user_name ? $nai_shell_user_name : $nai_shell_user_email;
$nai_shell_initials        = strtoupper( substr( preg_replace( '/[^A-Za-z0-9]/', '', (string) $nai_shell_initials_source ), 0, 2 ) );
if ( '' === $nai_shell_initials ) {
	$nai_shell_initials = 'BB';
}
$nai_shell_json         = function_exists( 'wp_json_encode' ) ? wp_json_encode( $nai_shell_drawer_items ) : json_encode( $nai_shell_drawer_items );
$nai_shell_icon         = static function ( string $name, int $size = 16, float $stroke = 1.75 ): string {
	$paths = array(
		'crown'  => '<path d="m2 19 2-11 5 5 3-7 3 7 5-5 2 11H2Z"/><path d="M2 21h20"/>',
		'info'   => '<circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v5h1"/>',
		'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
		'shield' => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6l-8-3Z"/>',
	);
	$body  = $paths[ $name ] ?? '';
	return sprintf(
		'<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="%2$s" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%3$s</svg>',
		$size,
		esc_attr( (string) $stroke ),
		$body // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG path data only.
	);
};

$nai_shell_nav_items = array(
	'dashboard' => array(
		'label' => __( 'Home', 'beepbeep-ai-alt-text-generator' ),
		'url'   => add_query_arg( array( 'page' => 'bbai', 'bbai_preview' => 'nai' ), admin_url( 'admin.php' ) ),
	),
	'library'   => array(
		'label' => __( 'Library', 'beepbeep-ai-alt-text-generator' ),
		'url'   => admin_url( 'admin.php?page=bbai-library' ),
	),
	'autopilot' => array(
		'label' => __( 'Autopilot', 'beepbeep-ai-alt-text-generator' ),
		'url'   => admin_url( 'admin.php?page=bbai-autopilot' ),
	),
	'settings'  => array(
		'label' => __( 'Settings', 'beepbeep-ai-alt-text-generator' ),
		'url'   => admin_url( 'admin.php?page=bbai-settings' ),
	),
);
?>
<div class="nai-app" data-nai-app-shell="1" data-nai-is-pro="<?php echo $nai_shell_is_pro ? '1' : '0'; ?>" data-nai-prototype="<?php echo $nai_shell_prototype ? '1' : '0'; ?>" data-nai-demo-trigger="<?php echo esc_attr( $nai_shell_demo_trigger ); ?>">
	<div hidden data-nai-drawer-items data-nai-drawer-items-json="<?php echo esc_attr( $nai_shell_json ); ?>"></div>
	<header class="nai-topbar" aria-label="<?php esc_attr_e( 'BeepBeep AI navigation', 'beepbeep-ai-alt-text-generator' ); ?>">
		<div class="nai-topbar__brand"><?php esc_html_e( 'BeepBeep AI', 'beepbeep-ai-alt-text-generator' ); ?></div>
		<nav class="nai-topbar__nav" aria-label="<?php esc_attr_e( 'BeepBeep AI sections', 'beepbeep-ai-alt-text-generator' ); ?>">
			<?php foreach ( $nai_shell_nav_items as $slug => $item ) : ?>
				<?php $active = $nai_shell_active === $slug; ?>
				<a
					class="nai-topbar__link<?php echo $active ? ' is-active' : ''; ?>"
					href="<?php echo esc_url( (string) $item['url'] ); ?>"
					<?php echo $active ? 'aria-current="page"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				>
					<?php echo esc_html( (string) $item['label'] ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<div class="nai-topbar__actions">
			<span class="nai-topbar__status" role="status">
				<span class="nai-pulse-dot" aria-hidden="true"></span>
				<?php esc_html_e( 'Active', 'beepbeep-ai-alt-text-generator' ); ?>
			</span>
			<?php if ( $nai_shell_is_pro ) : ?>
				<span class="nai-topbar__pro">
					<?php echo $nai_shell_icon( 'crown', 13, 2.2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php esc_html_e( 'Pro', 'beepbeep-ai-alt-text-generator' ); ?>
				</span>
			<?php else : ?>
				<button class="nai-btn nai-btn--pro nai-btn--sm" type="button" data-nai-open-paywall="default" data-bbai-pricing-variant="growth">
					<?php echo $nai_shell_icon( 'crown', 14, 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php esc_html_e( 'Upgrade to Pro', 'beepbeep-ai-alt-text-generator' ); ?>
				</button>
			<?php endif; ?>
			<div class="nai-user-menu" data-nai-user-menu>
				<button class="nai-topbar__user" type="button" aria-haspopup="menu" aria-expanded="false" aria-label="<?php esc_attr_e( 'Account menu', 'beepbeep-ai-alt-text-generator' ); ?>" data-nai-user-toggle>
					<span class="nai-topbar__avatar" aria-hidden="true"><?php echo esc_html( $nai_shell_initials ); ?></span>
					<span class="nai-topbar__chevron" aria-hidden="true">⌄</span>
				</button>
				<div class="nai-user-menu__panel" role="menu" hidden data-nai-user-panel>
					<div class="nai-user-menu__identity">
						<span class="nai-topbar__avatar" aria-hidden="true"><?php echo esc_html( $nai_shell_initials ); ?></span>
						<div>
							<div class="nai-user-menu__name"><?php echo esc_html( $nai_shell_user_name ); ?></div>
							<div class="nai-user-menu__email" data-user-email><?php echo esc_html( '' !== $nai_shell_user_email ? $nai_shell_user_email : __( 'Connected', 'beepbeep-ai-alt-text-generator' ) ); ?></div>
						</div>
					</div>
					<?php if ( $nai_shell_prototype ) : ?>
						<button class="nai-user-menu__item" type="button" role="menuitem" data-nai-open-onboarding><?php echo $nai_shell_icon( 'info', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Replay onboarding', 'beepbeep-ai-alt-text-generator' ); ?></button>
					<?php endif; ?>
					<button class="nai-user-menu__item nai-user-menu__item--danger" type="button" role="menuitem" data-action="logout"><?php echo $nai_shell_icon( 'logout', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Sign out', 'beepbeep-ai-alt-text-generator' ); ?></button>
				</div>
			</div>
		</div>
	</header>
	<div class="nai-signedout" hidden data-nai-signedout>
		<div class="nai-signedout__card">
			<div class="nai-signedout__brand"><?php echo $nai_shell_icon( 'shield', 24, 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><strong><?php esc_html_e( 'BeepBeep AI', 'beepbeep-ai-alt-text-generator' ); ?></strong><span><?php esc_html_e( 'Image SEO', 'beepbeep-ai-alt-text-generator' ); ?></span></div>
			<h2><?php esc_html_e( 'Welcome back', 'beepbeep-ai-alt-text-generator' ); ?></h2>
			<p><?php esc_html_e( 'Sign in to keep your images covered. Existing ALT text stays on your site while Autopilot is paused.', 'beepbeep-ai-alt-text-generator' ); ?></p>
			<label class="nai-field">
				<span><?php esc_html_e( 'Email', 'beepbeep-ai-alt-text-generator' ); ?></span>
				<input type="email" value="<?php echo esc_attr( $nai_shell_user_email ); ?>">
			</label>
			<label class="nai-field">
				<span><?php esc_html_e( 'Password', 'beepbeep-ai-alt-text-generator' ); ?></span>
				<input type="password" placeholder="<?php esc_attr_e( 'Enter your password', 'beepbeep-ai-alt-text-generator' ); ?>">
			</label>
			<button class="nai-btn nai-btn--primary nai-btn--md nai-btn--full" type="button" data-nai-signin><?php esc_html_e( 'Sign in', 'beepbeep-ai-alt-text-generator' ); ?></button>
		</div>
	</div>
