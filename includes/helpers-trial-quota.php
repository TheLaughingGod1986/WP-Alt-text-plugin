<?php
/**
 * Trial quota helper functions.
 *
 * Stores local trial usage counts keyed by site hash.
 *
 * @package BeepBeepAI\AltTextGenerator
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the local trial generation limit.
 *
 * @return int
 */
function bbai_get_trial_limit(): int {
	$limit = apply_filters( 'bbai_trial_limit', 5 );
	return max( 0, absint( $limit ) );
}

/**
 * Signed-up free plan offer shown to anonymous trial users.
 *
 * @return int
 */
function bbai_get_free_plan_offer(): int {
	$offer = apply_filters( 'bbai_free_plan_offer', 50 );
	return max( 0, absint( $offer ) );
}

/**
 * Remaining-credit threshold that counts as "near limit" for the anonymous trial.
 *
 * @param int $limit Trial limit.
 * @return int
 */
function bbai_get_trial_near_limit_threshold( int $limit = 0 ): int {
	$resolved_limit = $limit > 0 ? $limit : bbai_get_trial_limit();
	$threshold      = apply_filters( 'bbai_trial_near_limit_threshold', min( 2, max( 1, $resolved_limit - 1 ) ), $resolved_limit );
	return max( 1, absint( $threshold ) );
}

/**
 * Cookie name used to persist the anonymous dashboard identity.
 *
 * @return string
 */
function bbai_get_anon_cookie_name(): string {
	return 'bbai_anon_id';
}

/**
 * Validate an anonymous dashboard identity.
 *
 * @param string $anon_id Anonymous identity candidate.
 * @return bool
 */
function bbai_is_valid_anon_id( string $anon_id ): bool {
	$anon_id = trim( $anon_id );
	if ( '' === $anon_id ) {
		return false;
	}

	return 1 === preg_match( '/^anon_[a-z0-9]{20,80}$/', strtolower( $anon_id ) );
}

/**
 * Normalize a raw anonymous identity string.
 *
 * @param mixed $anon_id Raw anonymous identity.
 * @return string
 */
function bbai_normalize_anon_id( $anon_id ): string {
	if ( ! is_scalar( $anon_id ) ) {
		return '';
	}

	$normalized = strtolower( preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $anon_id ) );
	return bbai_is_valid_anon_id( $normalized ) ? $normalized : '';
}

/**
 * Read anonymous identity from the current request.
 *
 * @return string
 */
function bbai_get_request_anon_id(): string {
	$request_value = '';

	if ( isset( $_REQUEST['anon_id'] ) ) {
		$request_value = wp_unslash( $_REQUEST['anon_id'] );
	} elseif ( isset( $_SERVER['HTTP_X_BBAI_ANON_ID'] ) ) {
		$request_value = wp_unslash( $_SERVER['HTTP_X_BBAI_ANON_ID'] );
	}

	return bbai_normalize_anon_id( $request_value );
}

/**
 * Read anonymous identity from cookie storage.
 *
 * @return string
 */
function bbai_get_cookie_anon_id(): string {
	$cookie_name = bbai_get_anon_cookie_name();
	if ( empty( $_COOKIE[ $cookie_name ] ) ) {
		return '';
	}

	return bbai_normalize_anon_id( wp_unslash( $_COOKIE[ $cookie_name ] ) );
}

/**
 * Resolve the current anonymous identity, preferring request payload over cookie.
 *
 * @return string
 */
function bbai_get_anon_id(): string {
	$request_anon_id = bbai_get_request_anon_id();
	if ( '' !== $request_anon_id ) {
		return $request_anon_id;
	}

	return bbai_get_cookie_anon_id();
}

/**
 * Resolve current site hash used for trial tracking.
 *
 * @return string
 */
function bbai_get_trial_site_hash(): string {
	$site_hash = '';

	if ( ! function_exists( '\BeepBeepAI\AltTextGenerator\get_site_identifier' ) ) {
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
	}

	if ( function_exists( '\BeepBeepAI\AltTextGenerator\get_site_identifier' ) ) {
		$site_hash = (string) get_site_identifier();
	}

	if ( '' === $site_hash ) {
		if ( ! class_exists( '\BeepBeepAI\AltTextGenerator\Site_Fingerprint' ) ) {
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-site-fingerprint.php';
		}

		if ( class_exists( '\BeepBeepAI\AltTextGenerator\Site_Fingerprint' ) ) {
			$site_hash = (string) Site_Fingerprint::get_fingerprint();
		}
	}

	$site_hash = sanitize_key( $site_hash );

	// Final deterministic fallback for edge cases.
	if ( '' === $site_hash ) {
		$site_hash = md5( (string) get_site_url() );
	}

	return $site_hash;
}

/**
 * Build the option key for a trial usage counter.
 *
 * @param string $site_hash Site hash.
 * @return string
 */
function bbai_get_trial_option_key( string $site_hash ): string {
	$hash = sanitize_key( $site_hash );
	if ( '' === $hash ) {
		$hash = bbai_get_trial_site_hash();
	}

	return 'bbai_trial_usage_' . $hash;
}

/**
 * Resolve the trial identity key for the current anonymous visitor on this site.
 *
 * Falls back to the site hash when no anonymous identity is available.
 *
 * @param string $site_hash Site hash.
 * @param string $anon_id   Anonymous identity.
 * @return string
 */
function bbai_get_trial_identity_key( string $site_hash = '', string $anon_id = '' ): string {
	$site_hash = '' !== $site_hash ? sanitize_key( $site_hash ) : bbai_get_trial_site_hash();
	$anon_id   = '' !== $anon_id ? bbai_normalize_anon_id( $anon_id ) : bbai_get_anon_id();

	if ( '' === $anon_id ) {
		return $site_hash;
	}

	return sanitize_key( $site_hash . '_' . substr( hash( 'sha256', $anon_id ), 0, 24 ) );
}

/**
 * Get trial used count for a site hash.
 *
 * @param string $site_hash Site hash.
 * @return int
 */
function bbai_get_trial_used_count( string $site_hash = '' ): int {
	$hash  = bbai_get_trial_identity_key( $site_hash );
	$key   = bbai_get_trial_option_key( $hash );
	$used  = get_option( $key, 0 );
	$limit = bbai_get_trial_limit();

	return max( 0, min( $limit, absint( $used ) ) );
}

/**
 * Get remaining trial generations for a site hash.
 *
 * @param string $site_hash Site hash.
 * @return int
 */
function bbai_get_trial_remaining( string $site_hash = '' ): int {
	$limit = bbai_get_trial_limit();
	$used  = bbai_get_trial_used_count( $site_hash );

	return max( 0, $limit - $used );
}

/**
 * Increment trial usage by one after a successful generation.
 *
 * @param string $site_hash Site hash.
 * @return void
 */
function bbai_increment_trial_usage( string $site_hash = '' ): void {
	$hash  = bbai_get_trial_identity_key( $site_hash );
	$key   = bbai_get_trial_option_key( $hash );
	$limit = bbai_get_trial_limit();
	$used  = bbai_get_trial_used_count( $hash );

	if ( $used >= $limit ) {
		return;
	}

	update_option( $key, min( $limit, $used + 1 ), false );
}

/**
 * Check if a site's local trial is exhausted.
 *
 * @param string $site_hash Site hash.
 * @return bool
 */
function bbai_is_trial_exhausted( string $site_hash = '' ): bool {
	if ( class_exists( __NAMESPACE__ . '\Trial_Quota' ) && Trial_Quota::is_claimed_generation_active() ) {
		return false;
	}

	return bbai_get_trial_remaining( $site_hash ) <= 0;
}
