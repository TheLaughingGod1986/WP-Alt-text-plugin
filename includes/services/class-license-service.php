<?php
declare(strict_types=1);

namespace BeepBeep\AltText\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeepBeep\AltText\Core\Event_Bus;

/**
 * License Service
 *
 * Handles license activation, deactivation, and organization features.
 * Extracted from monolithic BbAI_Core class for better separation of concerns.
 *
 * @package BeepBeep\AltText\Services
 * @since   5.0.0
 */
class License_Service {
	/**
	 * API client instance.
	 *
	 * @var \BbAI_API_Client_V2
	 */
	private \BbAI_API_Client_V2 $api_client;

	/**
	 * Event bus instance.
	 *
	 * @var Event_Bus
	 */
	private Event_Bus $event_bus;

	/**
	 * Constructor.
	 *
	 * @since 5.0.0
	 *
	 * @param \BbAI_API_Client_V2 $api_client API client.
	 * @param Event_Bus           $event_bus Event bus.
	 */
	public function __construct( \BbAI_API_Client_V2 $api_client, Event_Bus $event_bus ) {
		$this->api_client = $api_client;
		$this->event_bus  = $event_bus;
	}

	/**
	 * Activate license key for this site.
	 *
	 * @since 5.0.0
	 *
	 * @param string $license_key License key (UUID format).
	 * @return array{success: bool, message: string, organization?: array, site?: array} Activation result.
	 */
	public function activate( string $license_key ): array {
		// Validate license key.
		if ( empty( $license_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'License key is required', 'opptiai-alt' ),
			);
		}

		// Validate UUID format.
		if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $license_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid license key format', 'opptiai-alt' ),
			);
		}

		// Attempt activation via API.
		$result = $this->api_client->activate_license( $license_key );

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		// Clear cached usage data.
		$this->clear_usage_cache();

		// Emit event.
		$this->event_bus->emit( 'license_activated', $result );

		return array(
			'success'      => true,
			'message'      => __( 'License activated successfully', 'opptiai-alt' ),
			'organization' => $result['organization'] ?? null,
			'site'         => $result['site'] ?? null,
		);
	}

	/**
	 * Deactivate license for this site.
	 *
	 * @since 5.0.0
	 *
	 * @return array{success: bool, message: string} Deactivation result.
	 */
	public function deactivate(): array {
		// Attempt deactivation via API.
		$result = $this->api_client->deactivate_license();

		// Clear cached usage data.
		$this->clear_usage_cache();

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		// Emit event.
		$this->event_bus->emit( 'license_deactivated', null );

		return array(
			'success' => true,
			'message' => __( 'License deactivated successfully', 'opptiai-alt' ),
		);
	}

	/**
	 * Get all sites using this license.
	 *
	 * Requires authentication.
	 *
	 * @since 5.0.0
	 *
	 * @return array{success: bool, message?: string, sites?: array} License sites result.
	 */
	public function get_license_sites(): array {
		// Must be authenticated.
		if ( ! $this->api_client->is_authenticated() ) {
			return array(
				'success' => false,
				'message' => __( 'Please log in to view license site usage', 'opptiai-alt' ),
			);
		}

		// Fetch license site usage from API.
		$result = $this->api_client->get_license_sites();

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message() ?: __( 'Failed to fetch license site usage', 'opptiai-alt' ),
			);
		}

		return array(
			'success' => true,
			'sites'   => $result,
		);
	}

	/**
	 * Disconnect a site from the license.
	 *
	 * Requires authentication.
	 *
	 * @since 5.0.0
	 *
	 * @param string $site_id Site ID to disconnect.
	 * @return array{success: bool, message: string, data?: array} Disconnect result.
	 */
	public function disconnect_site( string $site_id ): array {
		// Must be authenticated.
		if ( ! $this->api_client->is_authenticated() ) {
			return array(
				'success' => false,
				'message' => __( 'Please log in to disconnect license sites', 'opptiai-alt' ),
			);
		}

		// Validate site ID.
		if ( empty( $site_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Site ID is required', 'opptiai-alt' ),
			);
		}

		// Disconnect the site from the license.
		$result = $this->api_client->disconnect_license_site( $site_id );

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message() ?: __( 'Failed to disconnect site', 'opptiai-alt' ),
			);
		}

		// Emit event.
		$this->event_bus->emit( 'license_site_disconnected', array( 'site_id' => $site_id ) );

		return array(
			'success' => true,
			'message' => __( 'Site disconnected successfully', 'opptiai-alt' ),
			'data'    => $result,
		);
	}

	/**
	 * Check if license is active.
	 *
	 * @since 5.0.0
	 *
	 * @return bool True if license is active.
	 */
	public function has_active_license(): bool {
		return $this->api_client->has_active_license();
	}

	/**
	 * Get license data.
	 *
	 * @since 5.0.0
	 *
	 * @return array|null License data or null.
	 */
	public function get_license_data(): ?array {
		return $this->api_client->get_license_data();
	}

	/**
	 * Get organization data.
	 *
	 * @since 5.0.0
	 *
	 * @return array|null Organization data or null.
	 */
	public function get_organization(): ?array {
		$license_data = $this->get_license_data();
		return $license_data['organization'] ?? null;
	}

	/**
	 * Clear usage cache.
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	private function clear_usage_cache(): void {
		if ( class_exists( '\BbAI_Usage_Tracker' ) ) {
			\BbAI_Usage_Tracker::clear_cache();
		}
		delete_transient( 'bbai_usage_cache' );
		delete_transient( 'opptibbai_usage_cache' );
	}
}
