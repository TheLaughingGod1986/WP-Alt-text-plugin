<?php
/**
 * Site Identifier Helper
 * Provides a stable, site-wide identifier for quota tracking and abuse prevention
 */

namespace BeepBeepAI\AltTextGenerator;

if (!defined('ABSPATH')) {
	exit;
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
	$option_key = 'beepbeepai_site_id';
	$site_id = get_option($option_key, '');
	
	// If site ID exists and is valid, return it
	if (!empty($site_id) && is_string($site_id) && strlen($site_id) >= 16) {
		return $site_id;
	}
	
	// Generate a new site ID
	// Use site URL + salt for stability, but add randomness for uniqueness
	$site_url = get_site_url();
	$site_url_hash = md5($site_url);
	
	// Generate a secure random component
	if (function_exists('random_bytes')) {
		$random = bin2hex(random_bytes(16));
	} elseif (function_exists('openssl_random_pseudo_bytes')) {
		$random = bin2hex(openssl_random_pseudo_bytes(16));
	} else {
		$random = wp_generate_password(32, false);
	}
	
	// Combine for a stable but unique identifier
	$site_id = md5($site_url_hash . $random . time());
	
	// Save with autoload for performance
	update_option($option_key, $site_id, true);
	
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

