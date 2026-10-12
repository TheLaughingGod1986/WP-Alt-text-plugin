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
 * Remember an Auditor deep-link for the current user when an Alt Text admin
 * page is opened with source=auditor.
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
	set_transient( 'bbai_auditor_attr_' . $user_id, $attr, AUDITOR_ATTRIBUTION_TTL );
}
add_action( 'admin_init', __NAMESPACE__ . '\\remember_auditor_attribution' );

/**
 * Attribution to attach to a generate event: the request's own args first,
 * then the remembered deep-link. Empty array when the Auditor wasn't involved.
 *
 * @return array<string,string>
 */
function get_auditor_attribution(): array {
	$attr = auditor_attribution_from_request();
	if ( null === $attr ) {
		$user_id = get_current_user_id();
		$stored  = $user_id > 0 ? get_transient( 'bbai_auditor_attr_' . $user_id ) : false;
		$attr    = is_array( $stored ) && 'auditor' === ( $stored['source'] ?? '' ) ? $stored : null;
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
