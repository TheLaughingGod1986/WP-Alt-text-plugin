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
 * Longest check id accepted from a deep-link.
 */
const AUDITOR_FINDING_MAX_LEN = 64;

/**
 * Website Auditor check ids Alt Text recognises (Auditor v1 / patch 2 names).
 *
 * @return string[]
 */
function auditor_known_findings(): array {
	return array(
		'missing_alt',
		'needs_review',
		'missing_title',
		'duplicate_title',
		'missing_meta',
		'orphan_pages',
		'thin_links',
		'search_engines_discouraged',
		'https_not_enforced',
		'robots_txt',
		'sitemap_missing',
		'llms_txt',
		'noindex_pages',
		'canonical_missing',
		'og_tags_missing',
		'schema',
		'performance',
		'accessibility',
	);
}

/**
 * Auditor 0.8.x check ids renamed in Auditor v1 (old => new).
 *
 * @return array<string,string>
 */
function auditor_finding_aliases(): array {
	return array(
		'search_indexing' => 'search_engines_discouraged',
		'https'           => 'https_not_enforced',
		'sitemap'         => 'sitemap_missing',
		'canonical'       => 'canonical_missing',
		'open_graph'      => 'og_tags_missing',
	);
}

/**
 * Sanitise a raw check id, map an old id to its new name, and return '' for
 * anything Alt Text does not recognise.
 *
 * @param mixed $raw Raw request value.
 */
function normalize_auditor_finding( $raw ): string {
	if ( ! is_scalar( $raw ) ) {
		return '';
	}
	$raw = (string) $raw;
	if ( '' === $raw || strlen( $raw ) > 256 ) {
		return '';
	}
	$id = substr( sanitize_key( $raw ), 0, AUDITOR_FINDING_MAX_LEN );
	$aliases = auditor_finding_aliases();
	if ( isset( $aliases[ $id ] ) ) {
		$id = $aliases[ $id ];
	}
	return in_array( $id, auditor_known_findings(), true ) ? $id : '';
}

/**
 * Read and validate source/finding from the current request.
 *
 * Accepts `source=auditor` with the check id in `finding=` or
 * `auditor_source=` (Auditor v1 sends both). Old and new check ids resolve
 * to the same (new) id; unknown ids are dropped.
 *
 * @return array{source:string,finding:string}|null
 */
function auditor_attribution_from_request(): ?array {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only attribution tag from an admin link, no state change.
	$source = isset( $_REQUEST['source'] ) && is_scalar( $_REQUEST['source'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['source'] ) ) : '';
	if ( 'auditor' !== $source ) {
		return null;
	}
	$finding = '';
	foreach ( array( 'finding', 'auditor_source' ) as $param ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only attribution tag from an admin link, no state change.
		if ( ! isset( $_REQUEST[ $param ] ) ) {
			continue;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only attribution tag from an admin link, no state change.
		$raw = $_REQUEST[ $param ];
		$finding = normalize_auditor_finding( is_scalar( $raw ) ? wp_unslash( (string) $raw ) : null );
		if ( '' !== $finding ) {
			break;
		}
	}
	return array(
		'source'  => 'auditor',
		'finding' => $finding,
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
	$attr['landing']   = '';
	$attr['opened_at'] = time();
	set_transient( auditor_attribution_key( $user_id ), $attr, AUDITOR_ATTRIBUTION_TTL );

	$props = array( 'source' => 'auditor' );
	if ( '' !== $attr['finding'] ) {
		$props['finding'] = $attr['finding'];
	}
	$props['page'] = $page;
	// Same once-per-link guard as above; $insert_id also dedupes on PostHog's side.
	$props['$insert_id'] = 'auditor_deeplink_opened:' . md5( $user_id . '|' . $attr['finding'] . '|' . $attr['opened_at'] );
	if ( class_exists( __NAMESPACE__ . '\\BBAI_Telemetry' ) ) {
		// Local ring buffer / bbai_telemetry_event hook.
		BBAI_Telemetry::emit( 'auditor_deeplink_opened', $props );
		// PostHog, by the same server-side capture alt_generated uses
		// (consent and API-key checks live inside capture_posthog_event).
		$distinct_id = auditor_posthog_distinct_id();
		if ( '' !== $distinct_id ) {
			BBAI_Telemetry::capture_posthog_event( 'auditor_deeplink_opened', $distinct_id, $props );
		}
	}
}

/**
 * PostHog distinct_id for the deep-link event: this site's install ID (the
 * user is usually signed out of OpptiAI at this point, so there is no
 * account ID yet). Filterable.
 */
function auditor_posthog_distinct_id(): string {
	$id = '';
	if ( ! function_exists( __NAMESPACE__ . '\\get_site_identifier' ) && defined( 'BEEPBEEP_AI_PLUGIN_DIR' ) && is_readable( BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php' ) ) {
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
	}
	if ( function_exists( __NAMESPACE__ . '\\get_site_identifier' ) ) {
		$id = (string) get_site_identifier();
	}
	return (string) apply_filters( 'bbai_auditor_deeplink_distinct_id', $id );
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
		$args['finding']        = $attr['finding'];
		$args['auditor_source'] = $attr['finding'];
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
 * Extra properties for signup / sign-in success events while a Website
 * Auditor deep-link is remembered for this user. Uses its own keys so the
 * events' existing `source` (UI location) and `signup_source` (the user's own
 * "how did you find us" answer) are never touched.
 *
 * @return array<string,string>
 */
function auditor_signup_properties(): array {
	$stored = get_stored_auditor_attribution();
	if ( null === $stored ) {
		return array();
	}
	$out     = array( 'auditor_source' => 'auditor' );
	$finding = (string) ( $stored['finding'] ?? '' );
	if ( '' !== $finding ) {
		$out['auditor_finding'] = $finding;
	}
	return $out;
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
