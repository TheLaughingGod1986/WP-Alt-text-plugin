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
	$limit = apply_filters( 'bbai_trial_limit', 3 );
	return max( 0, absint( $limit ) );
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
 * Get trial used count for a site hash.
 *
 * @param string $site_hash Site hash.
 * @return int
 */
function bbai_get_trial_used_count( string $site_hash = '' ): int {
	$hash  = '' !== $site_hash ? sanitize_key( $site_hash ) : bbai_get_trial_site_hash();
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
	$hash  = '' !== $site_hash ? sanitize_key( $site_hash ) : bbai_get_trial_site_hash();
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
	return bbai_get_trial_remaining( $site_hash ) <= 0;
}

