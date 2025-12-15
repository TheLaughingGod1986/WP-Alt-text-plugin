<?php
/**
 * License Manager
 * Handles license key/data management and license-related API endpoints
 */

namespace BeepBeepAI\AltTextGenerator\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class License_Manager {

	private $request_handler;
	private $license_key_option_key  = 'optti_license_key';
	private $license_data_option_key = 'optti_license_data';
	private $encrypt_secret_callback;
	private $maybe_decrypt_secret_callback;
	private $get_site_id_callback;
	private $is_authenticated_callback;

	public function __construct( $request_handler, $callbacks = array() ) {
		$this->request_handler               = $request_handler;
		$this->encrypt_secret_callback       = $callbacks['encrypt_secret'] ?? null;
		$this->maybe_decrypt_secret_callback = $callbacks['maybe_decrypt_secret'] ?? null;
		$this->get_site_id_callback          = $callbacks['get_site_id'] ?? null;
		$this->is_authenticated_callback     = $callbacks['is_authenticated'] ?? null;
	}

	/**
	 * Get stored license key
	 */
	public function get_license_key() {
		$key = get_option( $this->license_key_option_key, '' );
		if ( $key !== '' && $key !== false ) {
			$decrypted = $this->maybe_decrypt_secret_callback ? call_user_func( $this->maybe_decrypt_secret_callback, $key ) : $key;
			if ( ! empty( $decrypted ) ) {
				return $decrypted;
			}
		}

		// Check framework snapshot
		if ( function_exists( 'opptiai_framework' ) ) {
			$framework = opptiai_framework();
			if ( $framework && isset( $framework->licensing ) ) {
				$snapshot = $framework->licensing->get_snapshot();
				if ( ! empty( $snapshot ) && isset( $snapshot['licenseKey'] ) ) {
					return $snapshot['licenseKey'];
				}
			}
		}

		// Check license data
		$license_data = $this->get_license_data();
		if ( ! empty( $license_data ) ) {
			if ( isset( $license_data['licenseKey'] ) && ! empty( $license_data['licenseKey'] ) ) {
				return $license_data['licenseKey'];
			}
			if ( isset( $license_data['site'] ) && is_array( $license_data['site'] ) && isset( $license_data['site']['licenseKey'] ) && ! empty( $license_data['site']['licenseKey'] ) ) {
				return $license_data['site']['licenseKey'];
			}
		}

		return '';
	}

	/**
	 * Store license key
	 */
	public function set_license_key( $license_key ) {
		if ( empty( $license_key ) ) {
			$this->clear_license_key();
			return;
		}

		$stored = $this->encrypt_secret_callback ? call_user_func( $this->encrypt_secret_callback, $license_key ) : $license_key;
		if ( empty( $stored ) ) {
			$stored = $license_key;
		}
		update_option( $this->license_key_option_key, $stored, false );
	}

	/**
	 * Clear stored license key
	 */
	public function clear_license_key() {
		delete_option( $this->license_key_option_key );
		delete_option( $this->license_data_option_key );
	}

	/**
	 * Get stored license data
	 */
	public function get_license_data() {
		// Use empty array as default instead of null to prevent WordPress core deprecation warnings
		$data = get_option( $this->license_data_option_key, array() );
		return ( ! empty( $data ) && is_array( $data ) ) ? $data : null;
	}

	/**
	 * Store license data
	 */
	public function set_license_data( $license_data ) {
		update_option( $this->license_data_option_key, $license_data, false );
	}

	/**
	 * Check if license key is active
	 */
	public function has_active_license() {
		$license_key  = $this->get_license_key();
		$license_data = $this->get_license_data();

		// If we have both key and data, license is active
		if ( ! empty( $license_key ) && ! empty( $license_data ) &&
			isset( $license_data['organization'] ) &&
			isset( $license_data['site'] ) ) {
			return true;
		}

		// Check framework snapshot
		if ( function_exists( 'opptiai_framework' ) ) {
			$framework = opptiai_framework();
			if ( $framework && isset( $framework->licensing ) ) {
				$snapshot = $framework->licensing->get_snapshot();
				if ( ! empty( $snapshot ) && isset( $snapshot['plan'] ) ) {
					return true;
				}
			}
		}

		// Check if we have license data without a key
		if ( ! empty( $license_data ) &&
			isset( $license_data['organization'] ) &&
			isset( $license_data['organization']['plan'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Activate license key
	 */
	public function activate_license( $license_key ) {
		$site_id = $this->get_site_id_callback ? call_user_func( $this->get_site_id_callback ) : '';

		$data = array(
			'licenseKey'       => $license_key,
			'siteHash'         => $site_id,
			'siteUrl'          => get_site_url(),
			'installId'        => $site_id,
			'pluginVersion'    => defined( 'BBAI_VERSION' ) ? BBAI_VERSION : '1.0.0',
			'wordpressVersion' => get_bloginfo( 'version' ),
			'phpVersion'       => PHP_VERSION,
			'isMultisite'      => is_multisite(),
		);

		$response = $this->request_handler->make_request( '/api/license/activate', 'POST', $data );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( $response['success'] ) {
			$this->set_license_key( $license_key );
			$this->set_license_data(
				array(
					'organization' => $response['data']['organization'] ?? array(),
					'site'         => $response['data']['site'] ?? array(),
					'activated_at' => current_time( 'mysql' ),
				)
			);

			return array(
				'success'      => true,
				'organization' => $response['data']['organization'] ?? array(),
				'site'         => $response['data']['site'] ?? array(),
			);
		}

		return new \WP_Error(
			'license_activation_failed',
			$response['data']['error'] ?? __( 'Failed to activate license', 'beepbeep-ai-alt-text-generator' )
		);
	}

	/**
	 * Deactivate license
	 */
	public function deactivate_license() {
		$this->clear_license_key();
		return array(
			'success' => true,
			'message' => __( 'License deactivated successfully', 'beepbeep-ai-alt-text-generator' ),
		);
	}

	/**
	 * Auto-attach license to this site
	 */
	public function auto_attach_license( $args = array() ) {
		$site_hash = $this->get_site_id_callback ? call_user_func( $this->get_site_id_callback ) : '';

		$defaults = array(
			'siteUrl'   => get_site_url(),
			'siteHash'  => $site_hash,
			'installId' => $site_hash,
		);

		$payload = array_merge(
			$defaults,
			array_filter(
				(array) $args,
				static function ( $value ) {
					return $value !== null && $value !== '';
				}
			)
		);

		$response = $this->request_handler->make_request( '/api/licenses/auto-attach', 'POST', $payload, null, true );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Handle nested response structure
		$response_data = $response['data'] ?? array();
		if ( isset( $response_data['data'] ) && is_array( $response_data['data'] ) ) {
			$response_data = $response_data['data'];
		}

		$snapshot = $this->extract_license_snapshot( $response_data );

		if ( $response['success'] && $snapshot ) {
			$this->apply_license_snapshot( $snapshot );
			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				\BeepBeepAI\AltTextGenerator\Debug_Log::log(
					'info',
					'License auto-attach succeeded',
					array(
						'plan'       => $snapshot['plan'] ?? '',
						'tokenLimit' => $snapshot['tokenLimit'] ?? '',
						'siteHash'   => $payload['siteHash'] ?? '',
					),
					'licensing'
				);
			}
			return array(
				'success' => true,
				'message' => $response_data['message'] ?? __( 'License attached successfully', 'beepbeep-ai-alt-text-generator' ),
				'license' => $snapshot,
				'site'    => isset( $response_data['site'] ) ? $response_data['site'] : array(),
			);
		}

		$error_message       = $response_data['message'] ?? __( 'Failed to auto-attach license', 'beepbeep-ai-alt-text-generator' );
		$error_details       = $response_data['error_details'] ?? $response_data['error'] ?? '';
		$error_details_lower = is_string( $error_details ) ? strtolower( $error_details ) : '';
		$is_schema_error     = strpos( $error_details_lower, 'column' ) !== false &&
							( strpos( $error_details_lower, 'schema cache' ) !== false ||
							strpos( $error_details_lower, 'not found' ) !== false ||
							strpos( $error_details_lower, 'does not exist' ) !== false );

		if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
			$log_level = $is_schema_error ? 'warning' : 'error';
			\BeepBeepAI\AltTextGenerator\Debug_Log::log(
				$log_level,
				'License auto-attach failed',
				array(
					'siteHash'         => $payload['siteHash'] ?? '',
					'response'         => $response_data,
					'response_success' => $response['success'] ?? false,
					'has_snapshot'     => ! empty( $snapshot ),
					'is_schema_error'  => $is_schema_error,
				),
				'licensing'
			);
		}

		if ( $is_schema_error ) {
			$error_message = __( 'License auto-attachment is temporarily unavailable due to backend maintenance. Your plugin will continue to work normally.', 'beepbeep-ai-alt-text-generator' );
		}

		return new \WP_Error(
			'auto_attach_failed',
			$error_message,
			array(
				'is_schema_error' => $is_schema_error,
				'siteHash'        => $payload['siteHash'] ?? '',
			)
		);
	}

	/**
	 * Get license site usage statistics
	 */
	public function get_license_sites() {
		$is_authenticated = $this->is_authenticated_callback ? call_user_func( $this->is_authenticated_callback ) : false;
		$has_license      = $this->has_active_license();

		if ( ! $is_authenticated && ! $has_license ) {
			return new \WP_Error( 'not_authenticated', __( 'Must be authenticated or have an active license to view license site usage', 'beepbeep-ai-alt-text-generator' ) );
		}

		$response = $this->request_handler->make_request( '/api/licenses/sites', 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( $response['success'] ) {
			return $response['data'] ?? array( 'sites' => array() );
		}

		return new \WP_Error( 'api_error', $response['data']['message'] ?? __( 'Failed to fetch license site usage', 'beepbeep-ai-alt-text-generator' ) );
	}

	/**
	 * Disconnect a site from the license
	 */
	public function disconnect_license_site( $site_id ) {
		if ( ! $this->is_authenticated_callback || ! call_user_func( $this->is_authenticated_callback ) ) {
			return new \WP_Error( 'not_authenticated', __( 'Must be authenticated to disconnect license sites', 'beepbeep-ai-alt-text-generator' ) );
		}

		$response = $this->request_handler->make_request( '/api/licenses/sites/' . urlencode( $site_id ), 'DELETE' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( $response['success'] ) {
			return $response['data'] ?? array( 'message' => __( 'Site disconnected successfully', 'beepbeep-ai-alt-text-generator' ) );
		}

		return new \WP_Error( 'api_error', $response['data']['message'] ?? __( 'Failed to disconnect site', 'beepbeep-ai-alt-text-generator' ) );
	}

	/**
	 * Format license usage from cache
	 */
	public function format_license_usage_from_cache( $license_data ) {
		if ( empty( $license_data ) || ! isset( $license_data['organization'] ) ) {
			return null;
		}

		$org       = $license_data['organization'];
		$limit     = isset( $org['tokenLimit'] ) ? intval( $org['tokenLimit'] ) : 10000;
		$remaining = isset( $org['tokensRemaining'] ) ? intval( $org['tokensRemaining'] ) : $limit;
		$used      = max( 0, $limit - $remaining );

		$reset_ts = 0;
		if ( ! empty( $org['resetDate'] ) ) {
			$reset_ts = strtotime( $org['resetDate'] );
		}
		if ( $reset_ts <= 0 ) {
			$reset_ts = strtotime( 'first day of next month' );
		}

		return array(
			'used'                => $used,
			'limit'               => $limit,
			'remaining'           => $remaining,
			'plan'                => strtolower( $org['plan'] ?? 'agency' ),
			'resetDate'           => $org['resetDate'] ?? '',
			'reset_timestamp'     => $reset_ts,
			'seconds_until_reset' => max( 0, $reset_ts - current_time( 'timestamp' ) ),
		);
	}

	/**
	 * Sync license usage snapshot
	 */
	public function sync_license_usage_snapshot( $usage, $organization = array(), $site_data = array(), $snapshot = array() ) {
		$existing_license = $this->get_license_data();
		$updated_license  = is_array( $existing_license ) ? $existing_license : array();
		$org              = isset( $updated_license['organization'] ) && is_array( $updated_license['organization'] )
			? $updated_license['organization']
			: array();

		// Extract and store license key from site_data
		if ( ! empty( $site_data ) && is_array( $site_data ) && isset( $site_data['licenseKey'] ) && ! empty( $site_data['licenseKey'] ) ) {
			$current_key = $this->get_license_key();
			if ( empty( $current_key ) ) {
				$this->set_license_key( $site_data['licenseKey'] );
			}
		}

		if ( is_array( $organization ) && ! empty( $organization ) ) {
			$org = array_merge( $org, $organization );
		}

		if ( isset( $usage['limit'] ) ) {
			$org['tokenLimit'] = intval( $usage['limit'] );
		}

		if ( isset( $usage['remaining'] ) ) {
			$org['tokensRemaining'] = max( 0, intval( $usage['remaining'] ) );
		} elseif ( isset( $usage['used'] ) && isset( $org['tokenLimit'] ) ) {
			$org['tokensRemaining'] = max( 0, intval( $org['tokenLimit'] ) - intval( $usage['used'] ) );
		}

		if ( ! empty( $usage['resetDate'] ) ) {
			$org['resetDate'] = sanitize_text_field( $usage['resetDate'] );
		} elseif ( ! empty( $usage['nextReset'] ) ) {
			$org['resetDate'] = sanitize_text_field( $usage['nextReset'] );
		}

		if ( ! empty( $usage['plan'] ) ) {
			$org['plan'] = sanitize_text_field( $usage['plan'] );
		}

		$updated_license['organization'] = $org;

		if ( ! empty( $site_data ) ) {
			$updated_license['site'] = array_merge( $updated_license['site'] ?? array(), (array) $site_data );
		}

		// Preserve timestamps
		if ( ! empty( $snapshot ) && is_array( $snapshot ) ) {
			if ( ! empty( $snapshot['createdAt'] ) || ! empty( $snapshot['created_at'] ) ) {
				$updated_license['created_at'] = $snapshot['createdAt'] ?? $snapshot['created_at'] ?? '';
			} elseif ( empty( $updated_license['created_at'] ) ) {
				$updated_license['created_at'] = current_time( 'mysql' );
			}
			if ( ! empty( $snapshot['updatedAt'] ) || ! empty( $snapshot['updated_at'] ) ) {
				$updated_license['updated_at'] = $snapshot['updatedAt'] ?? $snapshot['updated_at'] ?? '';
			} else {
				$updated_license['updated_at'] = current_time( 'mysql' );
			}
		} else {
			if ( empty( $updated_license['created_at'] ) ) {
				$updated_license['created_at'] = current_time( 'mysql' );
			}
			$updated_license['updated_at'] = current_time( 'mysql' );
		}

		$this->set_license_data( $updated_license );
	}

	/**
	 * Apply license snapshot
	 */
	public function apply_license_snapshot( $snapshot ) {
		if ( empty( $snapshot ) || ! is_array( $snapshot ) ) {
			return;
		}

		if ( ! empty( $snapshot['licenseKey'] ) ) {
			$this->set_license_key( $snapshot['licenseKey'] );
		}

		$usage_payload = array(
			'limit'           => isset( $snapshot['tokenLimit'] ) ? intval( $snapshot['tokenLimit'] ) : 0,
			'remaining'       => isset( $snapshot['tokensRemaining'] ) ? intval( $snapshot['tokensRemaining'] ) : 0,
			'plan'            => isset( $snapshot['plan'] ) ? sanitize_text_field( $snapshot['plan'] ) : 'free',
			'resetDate'       => isset( $snapshot['resetDate'] ) ? sanitize_text_field( $snapshot['resetDate'] ) : '',
			'reset_timestamp' => isset( $snapshot['reset_timestamp'] ) ? intval( $snapshot['reset_timestamp'] ) : 0,
		);

		$organization = array(
			'plan'               => $usage_payload['plan'],
			'tokenLimit'         => $usage_payload['limit'],
			'tokensRemaining'    => $usage_payload['remaining'],
			'resetDate'          => $usage_payload['resetDate'],
			'reset_timestamp'    => $usage_payload['reset_timestamp'],
			'autoAttachStatus'   => isset( $snapshot['autoAttachStatus'] ) ? $snapshot['autoAttachStatus'] : '',
			'licenseEmailSentAt' => isset( $snapshot['licenseEmailSentAt'] ) ? $snapshot['licenseEmailSentAt'] : '',
		);

		$site_data = array(
			'siteUrl'            => isset( $snapshot['siteUrl'] ) ? $snapshot['siteUrl'] : '',
			'siteHash'           => isset( $snapshot['siteHash'] ) ? $snapshot['siteHash'] : '',
			'installId'          => isset( $snapshot['installId'] ) ? $snapshot['installId'] : '',
			'autoAttachStatus'   => isset( $snapshot['autoAttachStatus'] ) ? $snapshot['autoAttachStatus'] : '',
			'licenseKey'         => isset( $snapshot['licenseKey'] ) ? $snapshot['licenseKey'] : '',
			'licenseEmailSentAt' => isset( $snapshot['licenseEmailSentAt'] ) ? $snapshot['licenseEmailSentAt'] : '',
			'created_at'         => isset( $snapshot['createdAt'] ) ? $snapshot['createdAt'] : ( isset( $snapshot['created_at'] ) ? $snapshot['created_at'] : '' ),
			'updated_at'         => isset( $snapshot['updatedAt'] ) ? $snapshot['updatedAt'] : ( isset( $snapshot['updated_at'] ) ? $snapshot['updated_at'] : '' ),
		);

		$this->sync_license_usage_snapshot( $usage_payload, $organization, $site_data, $snapshot );

		// Store license key and timestamps in license data
		if ( ! empty( $snapshot['licenseKey'] ) || ! empty( $snapshot['createdAt'] ) || ! empty( $snapshot['updatedAt'] ) ) {
			$license_data = $this->get_license_data();
			if ( empty( $license_data ) ) {
				$license_data = array();
			}
			if ( ! empty( $snapshot['licenseKey'] ) ) {
				$license_data['licenseKey'] = $snapshot['licenseKey'];
			}
			if ( ! empty( $snapshot['createdAt'] ) || ! empty( $snapshot['created_at'] ) ) {
				$license_data['created_at'] = $snapshot['createdAt'] ?? $snapshot['created_at'] ?? '';
			}
			if ( ! empty( $snapshot['updatedAt'] ) || ! empty( $snapshot['updated_at'] ) ) {
				$license_data['updated_at'] = $snapshot['updatedAt'] ?? $snapshot['updated_at'] ?? '';
			}
			$this->set_license_data( $license_data );
		}

		// Sync with framework
		if ( function_exists( 'opptiai_framework' ) ) {
			$framework = opptiai_framework();
			if ( $framework && isset( $framework->licensing ) && method_exists( $framework->licensing, 'sync_snapshot' ) ) {
				$framework_snapshot = $snapshot;
				if ( ! isset( $framework_snapshot['maskedLicenseKey'] ) && ! empty( $framework_snapshot['licenseKey'] ) ) {
					$framework_snapshot['maskedLicenseKey'] = $this->mask_license_key( $framework_snapshot['licenseKey'] );
				}
				unset( $framework_snapshot['licenseKey'] );
				$framework->licensing->sync_snapshot( $framework_snapshot );
			}
		}
	}

	/**
	 * Extract license snapshot from API response
	 */
	public function extract_license_snapshot( $data ) {
		if ( ! is_array( $data ) ) {
			return null;
		}

		if ( isset( $data['license'] ) && is_array( $data['license'] ) ) {
			return $data['license'];
		}

		if ( isset( $data['License'] ) && is_array( $data['License'] ) ) {
			return $data['License'];
		}

		if ( isset( $data['licenseSnapshot'] ) && is_array( $data['licenseSnapshot'] ) ) {
			return $data['licenseSnapshot'];
		}

		return null;
	}

	/**
	 * Mask license key for display
	 */
	public function mask_license_key( $license_key ) {
		$license_key = (string) $license_key;
		if ( strlen( $license_key ) <= 8 ) {
			return str_repeat( '•', max( 0, strlen( $license_key ) - 4 ) ) . substr( $license_key, -4 );
		}

		return substr( $license_key, 0, 4 ) . str_repeat( '•', strlen( $license_key ) - 8 ) . substr( $license_key, -4 );
	}
}
