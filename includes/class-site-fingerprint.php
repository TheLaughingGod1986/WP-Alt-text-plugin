<?php
/**
 * Site Fingerprint Manager for BeepBeep AI
 * Generates and validates unique site fingerprint for abuse prevention
 */

namespace BeepBeepAI\AltTextGenerator;

if (!defined('ABSPATH')) {
	exit;
}

class Site_Fingerprint {
	const OPTION_KEY = 'beepbeepai_site_fingerprint';
	const INSTALL_TIMESTAMP_KEY = 'beepbeepai_install_timestamp';
	const SECRET_KEY_LENGTH = 32;

	/**
	 * Generate and store site fingerprint.
	 *
	 * @return string The generated fingerprint.
	 */
	public static function generate() {
		// Check if already exists
		$existing = get_option(self::OPTION_KEY);
		if (!empty($existing)) {
			return $existing;
		}

		// Generate fingerprint components
		$site_url = get_site_url();
		global $wpdb;
		$db_prefix = $wpdb->prefix;
		
		// Get or create install timestamp
		$install_timestamp = get_option(self::INSTALL_TIMESTAMP_KEY);
		if (!$install_timestamp) {
			$install_timestamp = time();
			update_option(self::INSTALL_TIMESTAMP_KEY, $install_timestamp, false);
		}

		// Get or generate secret key (stored in options, not exposed)
		$secret_key = get_option('beepbeepai_site_secret_key');
		if (empty($secret_key)) {
			$secret_key = wp_generate_password(self::SECRET_KEY_LENGTH, true, true);
			update_option('beepbeepai_site_secret_key', $secret_key, false);
		}

		// Generate fingerprint hash
		$components = [
			$site_url,
			$db_prefix,
			$install_timestamp,
			$secret_key,
		];

		$fingerprint_string = implode('|', $components);
		$fingerprint = hash('sha256', $fingerprint_string);

		// Store fingerprint
		update_option(self::OPTION_KEY, $fingerprint, false);

		return $fingerprint;
	}

	/**
	 * Get the current site fingerprint.
	 *
	 * @return string The site fingerprint, or empty string if not generated.
	 */
	public static function get_fingerprint() {
		$fingerprint = get_option(self::OPTION_KEY);
		if (empty($fingerprint)) {
			// Auto-generate if not exists
			return self::generate();
		}
		return $fingerprint;
	}

	/**
	 * Get the install timestamp.
	 *
	 * @return int Unix timestamp.
	 */
	public static function get_install_timestamp() {
		$timestamp = get_option(self::INSTALL_TIMESTAMP_KEY);
		if (!$timestamp) {
			// Generate fingerprint will also set timestamp
			self::generate();
			$timestamp = get_option(self::INSTALL_TIMESTAMP_KEY);
		}
		return intval($timestamp);
	}

	/**
	 * Validate the current fingerprint matches stored fingerprint.
	 *
	 * @return array Validation result with 'valid' (bool) and 'message' (string).
	 */
	public static function validate_fingerprint() {
		$stored = get_option(self::OPTION_KEY);
		if (empty($stored)) {
			// Not yet generated, generate it now
			self::generate();
			return [
				'valid' => true,
				'message' => __('Site fingerprint generated successfully.', 'opptiai-alt'),
			];
		}

		// Regenerate fingerprint with current components
		$site_url = get_site_url();
		global $wpdb;
		$db_prefix = $wpdb->prefix;
		$install_timestamp = get_option(self::INSTALL_TIMESTAMP_KEY);
		$secret_key = get_option('beepbeepai_site_secret_key');

		if (empty($secret_key) || empty($install_timestamp)) {
			// Missing components, regenerate
			$current = self::generate();
			if ($current === $stored) {
				return [
					'valid' => true,
					'message' => __('Site fingerprint validated successfully.', 'opptiai-alt'),
				];
			}
		}

		// Check if components match
		$components = [
			$site_url,
			$db_prefix,
			$install_timestamp,
			$secret_key,
		];

		$fingerprint_string = implode('|', $components);
		$current_fingerprint = hash('sha256', $fingerprint_string);

		if ($current_fingerprint === $stored) {
			return [
				'valid' => true,
				'message' => __('Site fingerprint validated successfully.', 'opptiai-alt'),
			];
		}

		// Fingerprint mismatch - log warning
		$message = sprintf(
			/* translators: 1: stored fingerprint, 2: current fingerprint */
			__('Site fingerprint mismatch detected. Site URL, database prefix, or install timestamp may have changed. Stored: %1$s, Current: %2$s', 'opptiai-alt'),
			substr($stored, 0, 16) . '...',
			substr($current_fingerprint, 0, 16) . '...'
		);

		if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
			\BeepBeepAI\AltTextGenerator\Debug_Log::log('warning', 'Site fingerprint validation failed', [
				'stored' => substr($stored, 0, 16) . '...',
				'current' => substr($current_fingerprint, 0, 16) . '...',
				'site_url' => $site_url,
				'db_prefix' => $db_prefix,
			], 'fingerprint');
		}

		return [
			'valid' => false,
			'message' => $message,
		];
	}

	/**
	 * Check fingerprint on credit operation (before API call).
	 *
	 * @param bool $block_on_mismatch Optional. Whether to block operation on mismatch. Default false.
	 * @return \WP_Error|true True if valid, WP_Error if invalid and blocking is enabled.
	 */
	public static function check_on_credit_operation($block_on_mismatch = false) {
		$validation = self::validate_fingerprint();

		if (!$validation['valid']) {
			// Log warning regardless
			if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
				\BeepBeepAI\AltTextGenerator\Debug_Log::log('warning', 'Site fingerprint check failed during credit operation', [
					'message' => $validation['message'],
				], 'fingerprint');
			}

			// Block if configured
			if ($block_on_mismatch) {
				return new \WP_Error(
					'fingerprint_mismatch',
					__('Site fingerprint validation failed. Credit operations are blocked. Please contact support.', 'opptiai-alt'),
					['validation' => $validation]
				);
			}
		}

		return true;
	}

	/**
	 * Reset fingerprint (use with caution - only for legitimate site migrations).
	 *
	 * @return string New fingerprint.
	 */
	public static function reset_fingerprint() {
		delete_option(self::OPTION_KEY);
		delete_option(self::INSTALL_TIMESTAMP_KEY);
		delete_option('beepbeepai_site_secret_key');
		return self::generate();
	}
}

