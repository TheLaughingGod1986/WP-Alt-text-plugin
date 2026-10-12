<?php
/**
 * Website Auditor attribution.
 *
 * The OpptiAI Website Auditor's Fix buttons open Alt Text admin pages with
 * `source=auditor&finding=<type>`. Generation itself runs later in an AJAX or
 * REST request that does not carry those args, so the page load stores them
 * for the current user for a short window and the generate event reads them
 * back (or reads them straight from the request when they are present).
 *
 * @package BeepBeep_AI
 */

namespace BeepBeepAI\AltTextGenerator;

defined( 'ABSPATH' ) || exit;

const AUDITOR_ATTRIBUTION_TTL = 30 * MINUTE_IN_SECONDS;

/**
 * Read and validate source/finding from the current request.
 *
 * @return array{source:string,finding:string}|null
 */
function auditor_attribution_from_request(): ?array {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only attribution tag from an admin link, no state change.
	$source = isset( $_REQUEST['source'] ) ? sanitize_key( wp_unslash( $_REQUEST['source'] ) ) : '';
	if ( 'auditor' !== $source ) {
		return null;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only attribution tag from an admin link, no state change.
	$finding = isset( $_REQUEST['finding'] ) ? sanitize_key( wp_unslash( $_REQUEST['finding'] ) ) : '';
	return array(
		'source'  => 'auditor',
		'finding' => substr( $finding, 0, 64 ),
	);
}

/**
 * Transient key holding the current user's Auditor deep-link.
 */
function auditor_attribution_key( int $user_id ): string {
	return 'bbai_auditor_attr_' . $user_id;
}

/**
 * Stored deep-link for the current user, or null.
 *
 * @return array<string,mixed>|null
 */
function get_stored_auditor_attribution(): ?array {
	$user_id = get_current_user_id();
	if ( $user_id <= 0 ) {
		return null;
	}
	$stored = get_transient( auditor_attribution_key( $user_id ) );
	return is_array( $stored ) && 'auditor' === ( $stored['source'] ?? '' ) ? $stored : null;
}

/**
 * Remember an Auditor deep-link for the current user when an Alt Text admin
 * page is opened with source=auditor, and fire the `auditor_deeplink_opened`
 * event once per deep-link. The guest redirect (bbai-library -> bbai) keeps
 * the args on the URL, so the second page load sees the stored link for the
 * same finding and does not fire again.
 */
function remember_auditor_attribution(): void {
	if ( ! is_admin() || wp_doing_ajax() ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing only.
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( 0 !== strpos( $page, 'bbai' ) ) {
		return;
	}
	$attr    = auditor_attribution_from_request();
	$user_id = get_current_user_id();
	if ( null === $attr || $user_id <= 0 ) {
		return;
	}
	$stored = get_stored_auditor_attribution();
	if ( null !== $stored && (string) ( $stored['finding'] ?? '' ) === $attr['finding'] ) {
		return; // Same deep-link already recorded and tracked.
	}
	$attr['landing'] = '';
	set_transient( auditor_attribution_key( $user_id ), $attr, AUDITOR_ATTRIBUTION_TTL );

	$props = array( 'source' => 'auditor' );
	if ( '' !== $attr['finding'] ) {
		$props['finding'] = $attr['finding'];
	}
	$props['page'] = $page;
	if ( class_exists( __NAMESPACE__ . '\\BBAI_Telemetry' ) ) {
		BBAI_Telemetry::emit( 'auditor_deeplink_opened', $props );
	}
}
add_action( 'admin_init', __NAMESPACE__ . '\\remember_auditor_attribution' );

/**
 * Carry source=auditor&finding=<type> from the current request onto a
 * redirect URL (used by the signed-out guards so the dashboard, guest trial
 * and sign-up screens still know where the user came from).
 */
function with_auditor_args( string $url ): string {
	$attr = auditor_attribution_from_request();
	if ( null === $attr ) {
		return $url;
	}
	$args = array( 'source' => 'auditor' );
	if ( '' !== $attr['finding'] ) {
		$args['finding'] = $attr['finding'];
	}
	return add_query_arg( $args, $url );
}

/**
 * Called by the signed-out guard: once this user connects or signs in, send
 * them to the view the Auditor's Fix button meant to open.
 */
function mark_auditor_landing_pending(): void {
	$stored = get_stored_auditor_attribution();
	if ( null === $stored || 'pending' === ( $stored['landing'] ?? '' ) ) {
		return;
	}
	$stored['landing'] = 'pending';
	set_transient( auditor_attribution_key( get_current_user_id() ), $stored, AUDITOR_ATTRIBUTION_TTL );
}

/**
 * For a connected user with a pending Auditor landing, return the library URL
 * to land on (once) and mark it done. Null when there is nothing to do.
 */
function consume_auditor_landing(): ?string {
	$stored = get_stored_auditor_attribution();
	if ( null === $stored || 'pending' !== ( $stored['landing'] ?? '' ) ) {
		return null;
	}
	$stored['landing'] = 'done';
	set_transient( auditor_attribution_key( get_current_user_id() ), $stored, AUDITOR_ATTRIBUTION_TTL );

	$args = array(
		'page'   => 'bbai-library',
		'source' => 'auditor',
	);
	$finding = (string) ( $stored['finding'] ?? '' );
	if ( '' !== $finding ) {
		$args['finding'] = $finding;
	}
	if ( 'missing_alt' === $finding ) {
		$args['status'] = 'missing';
	}
	return add_query_arg( $args, admin_url( 'admin.php' ) );
}

/**
 * Attribution to attach to a generate event: the request's own args first,
 * then the remembered deep-link. Empty array when the Auditor wasn't involved.
 *
 * @return array<string,string>
 */
function get_auditor_attribution(): array {
	$attr = auditor_attribution_from_request();
	if ( null === $attr ) {
		$attr = get_stored_auditor_attribution();
	}
	if ( null === $attr ) {
		return array();
	}
	$out = array( 'source' => 'auditor' );
	if ( '' !== (string) ( $attr['finding'] ?? '' ) ) {
		$out['finding'] = (string) $attr['finding'];
	}
	return $out;
}
