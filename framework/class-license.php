<?php
/**
 * License Class
 *
 * Handles license validation, activation, deactivation, and quota management.
 * Migrated from Token_Quota_Service and Site_Fingerprint.
 *
 * @package Optti\Framework
 */

namespace Optti\Framework;

use Optti\Framework\Traits\Singleton;
use Optti\Framework\Interfaces\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class License
 *
 * Unified licensing system for Optti plugins.
 */
class License implements Service {

	use Singleton;

	/**
	 * Option keys.
	 *
	 * @var array
	 */
	protected $option_keys = [
		'license_key'  => 'optti_license_key',
		'license_data' => 'optti_license_data',
		'fingerprint'  => 'optti_site_fingerprint',
		'install_time' => 'optti_install_timestamp',
		'secret_key'   => 'optti_site_secret_key',
		'quota_cache'  => 'optti_quota_cache',
		'last_check'   => 'optti_license_last_check',
	];

	/**
	 * Cache expiry for quota (5 minutes).
	 */
	const QUOTA_CACHE_EXPIRY = 300;

	/**
	 * Secret key length.
	 */
	const SECRET_KEY_LENGTH = 32;

	/**
	 * Initialize the license service.
	 */
	public function init() {
		// Generate fingerprint if not exists.
		$this->get_fingerprint();

		// Schedule cron checks.
		$this->schedule_cron_checks();

		// Add admin notices hook.
		add_action( 'admin_notices', [ $this, 'show_admin_notices' ] );
	}

	/**
	 * Check if service is available.
	 *
	 * @return bool True if available.
	 */
	public function is_available() {
		return true;
	}

	/**
	 * Get license key.
	 *
	 * @return string License key.
	 */
	public function get_license_key() {
		$api = API::instance();
		return $api->get_license_key();
	}

	/**
	 * Set license key.
	 *
	 * @param string $license_key License key.
	 * @return void
	 */
	public function set_license_key( $license_key ) {
		$api = API::instance();
		$api->set_license_key( $license_key );
	}

	/**
	 * Get license data.
	 *
	 * @return array|null License data.
	 */
	public function get_license_data() {
		$api = API::instance();
		return $api->get_license_data();
	}

	/**
	 * Set license data.
	 *
	 * @param array $license_data License data.
	 * @return void
	 */
	public function set_license_data( $license_data ) {
		$api = API::instance();
		$api->set_license_data( $license_data );
	}

	/**
	 * Check if license is active.
	 *
	 * @return bool True if active.
	 */
	public function has_active_license() {
		$license_key = $this->get_license_key();
		$license_data = $this->get_license_data();

		if ( ! empty( $license_key ) && ! empty( $license_data ) ) {
			if ( isset( $license_data['organization'] ) && isset( $license_data['site'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Validate license with backend.
	 *
	 * @param bool $force_refresh Force refresh from backend.
	 * @return array|\WP_Error Validation result.
	 */
	public function validate( $force_refresh = false ) {
		$license_key = $this->get_license_key();

		if ( empty( $license_key ) ) {
			return new \WP_Error(
				'no_license',
				__( 'No license key found.', 'beepbeep-ai-alt-text-generator' )
			);
		}

		// Check cache unless forcing refresh.
		if ( ! $force_refresh ) {
			$last_check = get_transient( $this->option_keys['last_check'] );
			if ( false !== $last_check ) {
				$cached_data = $this->get_license_data();
				if ( ! empty( $cached_data ) ) {
					return [
						'valid'   => true,
						'license' => $cached_data,
					];
				}
			}
		}

		// Validate with backend.
		$api = API::instance();
		$response = $api->validate_license( $license_key );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Update license data.
		if ( isset( $response['license'] ) ) {
			$this->set_license_data( $response['license'] );
		}

		// Cache validation result.
		set_transient( $this->option_keys['last_check'], time(), HOUR_IN_SECONDS );

		return [
			'valid'   => true,
			'license' => $this->get_license_data(),
		];
	}

	/**
	 * Activate license.
	 *
	 * @param string $license_key License key.
	 * @return array|\WP_Error Activation result.
	 */
	public function activate( $license_key ) {
		if ( empty( $license_key ) ) {
			return new \WP_Error(
				'invalid_license',
				__( 'License key is required.', 'beepbeep-ai-alt-text-generator' )
			);
		}

		// Validate format (UUID).
		if ( ! $this->is_valid_license_format( $license_key ) ) {
			return new \WP_Error(
				'invalid_format',
				__( 'Invalid license key format. License keys must be in UUID format.', 'beepbeep-ai-alt-text-generator' )
			);
		}

		// Activate with backend.
		$api = API::instance();
		$response = $api->activate_license( $license_key, $this->get_fingerprint() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Store license key and data.
		$this->set_license_key( $license_key );
		if ( isset( $response['license'] ) ) {
			$this->set_license_data( $response['license'] );
		}

		// Clear quota cache.
		$this->clear_quota_cache();

		Logger::log( 'info', 'License activated', [
			'license_key' => substr( $license_key, 0, 8 ) . '...',
		], 'license' );

		return [
			'success' => true,
			'license' => $this->get_license_data(),
		];
	}

	/**
	 * Deactivate license.
	 *
	 * @return array|\WP_Error Deactivation result.
	 */
	public function deactivate() {
		$license_key = $this->get_license_key();

		if ( empty( $license_key ) ) {
			return new \WP_Error(
				'no_license',
				__( 'No license key to deactivate.', 'beepbeep-ai-alt-text-generator' )
			);
		}

		// Deactivate with backend.
		$api = API::instance();
		$response = $api->deactivate_license( $license_key );

		// Clear local data regardless of backend response.
		$this->set_license_key( '' );
		$this->set_license_data( null );
		$this->clear_quota_cache();
		delete_transient( $this->option_keys['last_check'] );

		Logger::log( 'info', 'License deactivated', [], 'license' );

		return [
			'success' => true,
			'message' => __( 'License deactivated successfully.', 'beepbeep-ai-alt-text-generator' ),
		];
	}

	/**
	 * Get site quota information.
	 *
	 * @param bool $force_refresh Force refresh from backend.
	 * @return array|\WP_Error Quota data.
	 */
	public function get_quota( $force_refresh = false ) {
		// Check cache first unless forcing refresh.
		if ( ! $force_refresh ) {
			$cached = get_transient( $this->option_keys['quota_cache'] );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		// Get from API.
		$api = API::instance();
		$usage = $api->get_usage();

		if ( is_wp_error( $usage ) ) {
			// Return cached data if available, even if stale.
			$cached = get_transient( $this->option_keys['quota_cache'] );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
			return $usage;
		}

		// Format quota data.
		$quota = [
			'plan_type' => $usage['plan'] ?? 'free',
			'limit'     => isset( $usage['limit'] ) ? max( 0, intval( $usage['limit'] ) ) : 50,
			'used'       => isset( $usage['used'] ) ? max( 0, intval( $usage['used'] ) ) : 0,
			'remaining' => 0,
			'resets_at' => 0,
		];

		// Calculate reset timestamp.
		if ( ! empty( $usage['resetDate'] ) ) {
			$reset_ts = strtotime( $usage['resetDate'] );
			if ( $reset_ts > 0 ) {
				$quota['resets_at'] = $reset_ts;
			}
		}

		if ( $quota['resets_at'] <= 0 && ! empty( $usage['resetTimestamp'] ) ) {
			$quota['resets_at'] = intval( $usage['resetTimestamp'] );
		}

		if ( $quota['resets_at'] <= 0 ) {
			$quota['resets_at'] = strtotime( 'first day of next month' );
		}

		// Calculate remaining.
		$quota['remaining'] = max( 0, $quota['limit'] - $quota['used'] );

		// Cache the result.
		set_transient( $this->option_keys['quota_cache'], $quota, self::QUOTA_CACHE_EXPIRY );

		return $quota;
	}

	/**
	 * Check if site can consume specified tokens.
	 *
	 * @param int $tokens Number of tokens to check.
	 * @return bool True if can consume.
	 */
	public function can_consume( $tokens ) {
		$tokens = max( 0, intval( $tokens ) );

		if ( $tokens <= 0 ) {
			return true;
		}

		$quota = $this->get_quota();

		if ( is_wp_error( $quota ) ) {
			// On error, allow generation (backend will enforce limits).
			return true;
		}

		return isset( $quota['remaining'] ) && intval( $quota['remaining'] ) > 0;
	}

	/**
	 * Generate and store site fingerprint.
	 *
	 * @return string Fingerprint.
	 */
	public function get_fingerprint() {
		$fingerprint = get_option( $this->option_keys['fingerprint'] );

		if ( ! empty( $fingerprint ) ) {
			return $fingerprint;
		}

		// Generate fingerprint components.
		$site_url = get_site_url();
		global $wpdb;
		$db_prefix = $wpdb->prefix;

		// Get or create install timestamp.
		$install_timestamp = get_option( $this->option_keys['install_time'] );
		if ( ! $install_timestamp ) {
			$install_timestamp = time();
			update_option( $this->option_keys['install_time'], $install_timestamp, false );
		}

		// Get or generate secret key.
		$secret_key = get_option( $this->option_keys['secret_key'] );
		if ( empty( $secret_key ) ) {
			if ( \function_exists( 'wp_generate_password' ) ) {
				$secret_key = \wp_generate_password( self::SECRET_KEY_LENGTH, true, true );
			} else {
				// Fallback if wp_generate_password is not available.
				$secret_key = bin2hex( random_bytes( self::SECRET_KEY_LENGTH ) );
			}
			update_option( $this->option_keys['secret_key'], $secret_key, false );
		}

		// Generate fingerprint hash.
		$components = [
			$site_url,
			$db_prefix,
			$install_timestamp,
			$secret_key,
		];

		$fingerprint_string = implode( '|', $components );
		$fingerprint = hash( 'sha256', $fingerprint_string );

		// Store fingerprint.
		update_option( $this->option_keys['fingerprint'], $fingerprint, false );

		return $fingerprint;
	}

	/**
	 * Validate fingerprint.
	 *
	 * @return array Validation result.
	 */
	public function validate_fingerprint() {
		$stored = get_option( $this->option_keys['fingerprint'] );

		if ( empty( $stored ) ) {
			$this->get_fingerprint();
			return [
				'valid'   => true,
				'message' => __( 'Site fingerprint generated successfully.', 'beepbeep-ai-alt-text-generator' ),
			];
		}

		// Regenerate and compare.
		$current = $this->get_fingerprint();

		if ( $current === $stored ) {
			return [
				'valid'   => true,
				'message' => __( 'Site fingerprint validated successfully.', 'beepbeep-ai-alt-text-generator' ),
			];
		}

		Logger::log( 'warning', 'Site fingerprint mismatch', [
			'stored'  => substr( $stored, 0, 16 ) . '...',
			'current' => substr( $current, 0, 16 ) . '...',
		], 'license' );

		return [
			'valid'   => false,
			'message' => __( 'Site fingerprint mismatch detected. Site may have been migrated.', 'beepbeep-ai-alt-text-generator' ),
		];
	}

	/**
	 * Schedule cron checks for license expiration.
	 *
	 * @return void
	 */
	protected function schedule_cron_checks() {
		if ( ! wp_next_scheduled( 'optti_license_check' ) ) {
			wp_schedule_event( time(), 'daily', 'optti_license_check' );
		}

		add_action( 'optti_license_check', [ $this, 'check_expiration' ] );
	}

	/**
	 * Check license expiration (cron callback).
	 *
	 * @return void
	 */
	public function check_expiration() {
		if ( ! $this->has_active_license() ) {
			return;
		}

		$license_data = $this->get_license_data();

		if ( empty( $license_data ) || ! isset( $license_data['expires_at'] ) ) {
			return;
		}

		$expires_at = intval( $license_data['expires_at'] );
		$now = time();

		// Check if expired.
		if ( $expires_at > 0 && $expires_at < $now ) {
			Logger::log( 'warning', 'License expired', [
				'expires_at' => date( 'Y-m-d H:i:s', $expires_at ),
			], 'license' );

			// Clear license data.
			$this->set_license_key( '' );
			$this->set_license_data( null );
		} elseif ( $expires_at > 0 && $expires_at < ( $now + WEEK_IN_SECONDS ) ) {
			// Expiring within a week.
			Logger::log( 'info', 'License expiring soon', [
				'expires_at' => date( 'Y-m-d H:i:s', $expires_at ),
			], 'license' );
		}
	}

	/**
	 * Show admin notices for license status.
	 *
	 * @return void
	 */
	public function show_admin_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check if license is invalid or expired.
		$validation = $this->validate();

		if ( is_wp_error( $validation ) ) {
			$error_code = $validation->get_error_code();

			if ( 'no_license' === $error_code ) {
				// No license - this is fine for free users.
				return;
			}

			// Show error notice.
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php esc_html_e( 'Optti License Error:', 'beepbeep-ai-alt-text-generator' ); ?></strong>
					<?php echo esc_html( $validation->get_error_message() ); ?>
				</p>
			</div>
			<?php
			return;
		}

		// Check for expiration warnings.
		$license_data = $this->get_license_data();

		if ( ! empty( $license_data ) && isset( $license_data['expires_at'] ) ) {
			$expires_at = intval( $license_data['expires_at'] );
			$now = time();

			if ( $expires_at > 0 && $expires_at < ( $now + WEEK_IN_SECONDS ) && $expires_at > $now ) {
				?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'Optti License Expiring Soon:', 'beepbeep-ai-alt-text-generator' ); ?></strong>
						<?php
						printf(
							esc_html__( 'Your license expires on %s. Please renew to continue using premium features.', 'beepbeep-ai-alt-text-generator' ),
							date_i18n( get_option( 'date_format' ), $expires_at )
						);
						?>
					</p>
				</div>
				<?php
			}
		}
	}

	/**
	 * Clear quota cache.
	 *
	 * @return void
	 */
	public function clear_quota_cache() {
		delete_transient( $this->option_keys['quota_cache'] );
	}

	/**
	 * Check if license key format is valid (UUID).
	 *
	 * @param string $license_key License key.
	 * @return bool True if valid format.
	 */
	protected function is_valid_license_format( $license_key ) {
		// UUID format: 8-4-4-4-12 hexadecimal characters.
		$pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
		return preg_match( $pattern, $license_key ) === 1;
	}
}

