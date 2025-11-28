<?php
/**
 * Fired during plugin deactivation.
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-core.php';

class Deactivator {

	/**
	 * Deactivate the plugin.
	 */
	public static function deactivate() {
		$core = new \BeepBeepAI\AltTextGenerator\Core();
		$core->deactivate();

		// Send deactivation analytics event via PHP (JS cannot run during deactivation)
		$api_base_url = 'https://alttext-ai-backend.onrender.com';
		$site_url = get_site_url();
		
		// Get JWT token if available
		$jwt_token = get_option('optti_jwt_token', '');
		if (empty($jwt_token)) {
			$jwt_token = get_option('beepbeepai_jwt_token', '');
		}
		
		// Build headers
		$headers = [
			'Content-Type' => 'application/json'
		];
		
		// Include JWT token if available
		if (!empty($jwt_token)) {
			$headers['Authorization'] = 'Bearer ' . $jwt_token;
		}
		
		// Use /analytics/events endpoint (plural, matches framework) with batch structure
		wp_remote_post($api_base_url . '/analytics/events', [
			'headers' => $headers,
			'body' => wp_json_encode([
				'events' => [
					[
						'event' => 'plugin_deactivated',
						'payload' => [],
						'plugin' => 'beepbeep-ai',
						'site' => $site_url,
						'ts' => time() * 1000 // Convert to milliseconds to match JS
					]
				],
				'plugin' => 'beepbeep-ai',
				'site' => $site_url
			]),
			'timeout' => 5,
			'blocking' => false // Non-blocking - don't wait for response
		]);
	}
}
