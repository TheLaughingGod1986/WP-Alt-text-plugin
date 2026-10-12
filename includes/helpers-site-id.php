<?php
/**
 * Site Identifier Helper
 * Provides a stable, site-wide identifier for quota tracking and abuse prevention
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve option key used for storing site identifier.
 *
 * In multisite, keep identifiers scoped per blog to avoid cross-site collisions.
 *
 * @return string
 */
function bbai_get_site_identifier_option_key() {
	if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_current_blog_id' ) ) {
		return 'beepbeepai_site_id_' . absint( get_current_blog_id() );
	}

	return 'beepbeepai_site_id';
}

/**
 * Sibling OpptiAI site-id option keys (read-only adoption).
 *
 * Credits are pooled per backend canonical site. Every OpptiAI plugin on one
 * WordPress install must agree on one identifier — otherwise each mints its
 * own wallet. We adopt a sibling's id before minting; we never write to
 * another plugin's option names.
 *
 * @return string[]
 */
function bbai_sibling_site_identifier_keys() {
	$keys = array();

	if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_current_blog_id' ) ) {
		$blog_id = absint( get_current_blog_id() );
		$keys[]  = 'beepbeepai_site_id_' . $blog_id;
	}

	$keys[] = 'beepti_site_id';         // OpptiAI Titles
	$keys[] = 'oppti_linking_site_id';   // OpptiAI Internal Linking
	$keys[] = 'oppti_optimizer_site_id'; // OpptiAI Website Auditor

	return $keys;
}

/**
 * Whether a stored value is a usable OpptiAI site identifier.
 *
 * Prefer the shared 32-char md5 shape used by AltText/Titles/Linking. Reject
 * the legacy Auditor `oppti_` prefix so we don't pull a divergent key into
 * the shared wallet once a proper sibling id exists.
 *
 * @param mixed $value Option value.
 * @return bool
 */
function bbai_is_valid_site_identifier( $value ) {
	if ( ! is_string( $value ) || strlen( $value ) < 16 ) {
		return false;
	}
	if ( 0 === strpos( $value, 'oppti_' ) ) {
		return false;
	}
	return true;
}

/**
 * Get or generate a stable site identifier
 *
 * This identifier is used to:
 * - Track quota per-site (not per-user)
 * - Prevent multiple free accounts per site
 * - Link all users on a site to the same backend account
 *
 * @return string A stable 32-character site identifier
 */
function get_site_identifier() {
	$legacy_option_key = 'beepbeepai_site_id';
	$option_key        = bbai_get_site_identifier_option_key();
	$site_id           = get_option( $option_key, '' );

	// If site ID exists and is valid, return it
	if ( bbai_is_valid_site_identifier( $site_id ) ) {
		return $site_id;
	}

	// Backward compatibility: migrate legacy key to multisite-scoped key.
	if ( $option_key !== $legacy_option_key ) {
		$legacy_site_id = get_option( $legacy_option_key, '' );
		if ( bbai_is_valid_site_identifier( $legacy_site_id ) ) {
			update_option( $option_key, $legacy_site_id, true );
			return $legacy_site_id;
		}
	}

	// Adopt a sibling OpptiAI plugin's id before minting a new wallet.
	foreach ( bbai_sibling_site_identifier_keys() as $sibling_key ) {
		if ( $sibling_key === $option_key || $sibling_key === $legacy_option_key ) {
			continue;
		}
		$sibling_id = get_option( $sibling_key, '' );
		if ( bbai_is_valid_site_identifier( $sibling_id ) ) {
			// Prefer canonical 32-char ids when available.
			if ( strlen( $sibling_id ) === 32 || ! bbai_is_valid_site_identifier( $site_id ) ) {
				update_option( $option_key, $sibling_id, true );
				return $sibling_id;
			}
		}
	}

	// Generate a new site ID
	// Use site URL + salt for stability, but add randomness for uniqueness
	$site_url        = get_site_url();
	$network_context = 'single';
	if ( function_exists( 'is_multisite' ) && is_multisite() ) {
		$blog_id         = function_exists( 'get_current_blog_id' ) ? absint( get_current_blog_id() ) : 0;
		$network_id      = function_exists( 'get_current_network_id' ) ? absint( get_current_network_id() ) : 0;
		$network_context = 'network:' . $network_id . '|blog:' . $blog_id;
	}
	$site_url_hash = md5( $site_url . '|' . $network_context );

	// Generate a secure random component
	if ( function_exists( 'random_bytes' ) ) {
		$random = bin2hex( random_bytes( 16 ) );
	} elseif ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
		$random = bin2hex( openssl_random_pseudo_bytes( 16 ) );
	} else {
		$random = wp_generate_password( 32, false );
	}

	// Combine for a stable but unique identifier
	$site_id = md5( $site_url_hash . $random . time() );

	// Save with autoload for performance
	update_option( $option_key, $site_id, true );

	return $site_id;
}

/**
 * Get site identifier (alias for consistency)
 *
 * @return string
 */
function beepbeepai_get_site_identifier() {
	return get_site_identifier();
}
