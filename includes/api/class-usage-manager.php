<?php
/**
 * Usage Manager
 * Handles usage tracking and credit operations
 */

namespace BeepBeepAI\AltTextGenerator\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class Usage_Manager {

	private $request_handler;
	private $has_active_license_callback;
	private $get_license_data_callback;
	private $get_license_key_callback;
	private $set_license_key_callback;
	private $extract_license_snapshot_callback;
	private $apply_license_snapshot_callback;
	private $sync_license_usage_snapshot_callback;
	private $format_license_usage_from_cache_callback;
	private $is_authenticated_callback;

	public function __construct( $request_handler, $callbacks = array() ) {
		$this->request_handler                          = $request_handler;
		$this->has_active_license_callback              = $callbacks['has_active_license'] ?? null;
		$this->get_license_data_callback                = $callbacks['get_license_data'] ?? null;
		$this->get_license_key_callback                 = $callbacks['get_license_key'] ?? null;
		$this->set_license_key_callback                 = $callbacks['set_license_key'] ?? null;
		$this->extract_license_snapshot_callback        = $callbacks['extract_license_snapshot'] ?? null;
		$this->apply_license_snapshot_callback          = $callbacks['apply_license_snapshot'] ?? null;
		$this->sync_license_usage_snapshot_callback     = $callbacks['sync_license_usage_snapshot'] ?? null;
		$this->format_license_usage_from_cache_callback = $callbacks['format_license_usage_from_cache'] ?? null;
		$this->is_authenticated_callback                = $callbacks['is_authenticated'] ?? null;
	}

	/**
	 * Get usage information
	 */
	public function get_usage() {
		$has_license   = $this->has_active_license_callback ? call_user_func( $this->has_active_license_callback ) : false;
		$license_cache = $has_license && $this->get_license_data_callback ? call_user_func( $this->get_license_data_callback ) : null;

		// Do NOT include user ID - usage must be tracked per-site
		$response = $this->request_handler->make_request( '/usage', 'GET', null, null, false );

		if ( is_wp_error( $response ) ) {
			// Try cached usage as fallback
			if ( $has_license && $license_cache && $this->format_license_usage_from_cache_callback ) {
				$cached_usage = call_user_func( $this->format_license_usage_from_cache_callback, $license_cache );
				if ( $cached_usage ) {
					return $cached_usage;
				}
			}

			if ( ! $has_license ) {
				require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
				$cached_usage = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_cached_usage( false );
				if ( is_array( $cached_usage ) && isset( $cached_usage['remaining'] ) && is_numeric( $cached_usage['remaining'] ) && $cached_usage['remaining'] >= 0 ) {
					return $cached_usage;
				}
			}

			return $response;
		}

		if ( $response['success'] && isset( $response['data']['usage'] ) ) {
			$usage = $response['data']['usage'];

			if ( $has_license && is_array( $usage ) ) {
				// Extract and apply license snapshot
				if ( $this->extract_license_snapshot_callback && $this->apply_license_snapshot_callback ) {
					$snapshot = call_user_func( $this->extract_license_snapshot_callback, $response['data'] );
					if ( $snapshot ) {
						call_user_func( $this->apply_license_snapshot_callback, $snapshot );
					} else {
						// Extract license key from response
						$license_key_from_response = null;
						if ( isset( $response['data']['site']['licenseKey'] ) ) {
							$license_key_from_response = $response['data']['site']['licenseKey'];
						} elseif ( isset( $response['data']['licenseKey'] ) ) {
							$license_key_from_response = $response['data']['licenseKey'];
						} elseif ( isset( $response['data']['license']['licenseKey'] ) ) {
							$license_key_from_response = $response['data']['license']['licenseKey'];
						}

						$current_key = $this->get_license_key_callback ? call_user_func( $this->get_license_key_callback ) : '';
						if ( ! empty( $license_key_from_response ) && empty( $current_key ) && $this->set_license_key_callback ) {
							call_user_func( $this->set_license_key_callback, $license_key_from_response );
						}
					}
				}

				// Sync license usage snapshot
				if ( $this->sync_license_usage_snapshot_callback ) {
					$full_snapshot = array_merge(
						$response['data']['site'] ?? array(),
						$response['data']['organization'] ?? array(),
						array(
							'licenseKey' => $response['data']['site']['licenseKey'] ?? $response['data']['licenseKey'] ?? $response['data']['license']['licenseKey'] ?? '',
							'createdAt'  => $response['data']['site']['createdAt'] ?? $response['data']['site']['created_at'] ?? $response['data']['createdAt'] ?? $response['data']['created_at'] ?? '',
							'updatedAt'  => $response['data']['site']['updatedAt'] ?? $response['data']['site']['updated_at'] ?? $response['data']['updatedAt'] ?? $response['data']['updated_at'] ?? '',
						)
					);
					call_user_func(
						$this->sync_license_usage_snapshot_callback,
						$usage,
						$response['data']['organization'] ?? array(),
						$response['data']['site'] ?? array(),
						$full_snapshot
					);
				}
			} else {
				// Update usage cache for non-license accounts
				require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
				\BeepBeepAI\AltTextGenerator\Usage_Tracker::update_usage( $usage );
			}

			return $usage;
		}

		// Fallback to cached usage
		if ( $has_license && $license_cache && $this->format_license_usage_from_cache_callback ) {
			$cached_usage = call_user_func( $this->format_license_usage_from_cache_callback, $license_cache );
			if ( $cached_usage ) {
				return $cached_usage;
			}
		}

		if ( ! $has_license ) {
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
			$cached_usage = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_cached_usage( false );
			if ( is_array( $cached_usage ) && isset( $cached_usage['remaining'] ) && is_numeric( $cached_usage['remaining'] ) && $cached_usage['remaining'] >= 0 ) {
				return $cached_usage;
			}
		}

		return new \WP_Error(
			'usage_failed',
			$response['data']['error'] ?? __( 'Failed to get usage info', 'beepbeep-ai-alt-text-generator' )
		);
	}

	/**
	 * Check if user has reached their limit
	 */
	public function has_reached_limit() {
		if ( $this->has_active_license_callback && call_user_func( $this->has_active_license_callback ) ) {
			return false;
		}

		try {
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
			$cached_usage = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_cached_usage( false );

			if ( is_array( $cached_usage ) && isset( $cached_usage['remaining'] ) && is_numeric( $cached_usage['remaining'] ) ) {
				$remaining = intval( $cached_usage['remaining'] );
				if ( $remaining > 0 ) {
					return false;
				}
				if ( $remaining === 0 ) {
					return true;
				}
			}

			// Try API as fallback
			$usage = $this->get_usage();

			if ( is_wp_error( $usage ) ) {
				$error_code = $usage->get_error_code();
				if ( $error_code === 'auth_required' || $error_code === 'user_not_found' ) {
					return false;
				}
				return false;
			}

			if ( isset( $usage['remaining'] ) && is_numeric( $usage['remaining'] ) ) {
				$remaining = intval( $usage['remaining'] );
				if ( $remaining === 0 && ( ! is_array( $cached_usage ) || ! isset( $cached_usage['remaining'] ) || intval( $cached_usage['remaining'] ) === 0 ) ) {
					return true;
				}
				if ( $remaining > 0 ) {
					return false;
				}
			}

			return false;
		} catch ( \Exception $e ) {
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
			$cached_usage = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_cached_usage( false );

			if ( is_array( $cached_usage ) && isset( $cached_usage['remaining'] ) && is_numeric( $cached_usage['remaining'] ) && $cached_usage['remaining'] > 0 ) {
				return false;
			}

			if ( is_array( $cached_usage ) && isset( $cached_usage['remaining'] ) && is_numeric( $cached_usage['remaining'] ) && $cached_usage['remaining'] === 0 ) {
				return true;
			}

			return false;
		}
	}

	/**
	 * Get percentage of quota used
	 */
	public function get_usage_percentage() {
		$usage = $this->get_usage();

		if ( is_wp_error( $usage ) ) {
			return 0;
		}

		$used  = $usage['used'] ?? 0;
		$limit = $usage['limit'] ?? 50;

		if ( $limit == 0 ) {
			return 0;
		}
		return min( 100, round( ( $used / $limit ) * 100 ) );
	}

	/**
	 * Get credit balance
	 */
	public function get_credit_balance() {
		if ( ! $this->is_authenticated_callback || ! call_user_func( $this->is_authenticated_callback ) ) {
			return new \WP_Error(
				'not_authenticated',
				__( 'Must be authenticated to get credit balance', 'beepbeep-ai-alt-text-generator' )
			);
		}

		$response = $this->request_handler->make_request( '/credits/balance', 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( $response['success'] && isset( $response['data'] ) ) {
			return $response['data'];
		}

		return new \WP_Error(
			'credit_balance_failed',
			$response['data']['error'] ?? __( 'Failed to get credit balance', 'beepbeep-ai-alt-text-generator' )
		);
	}

	/**
	 * Get available credit packs
	 */
	public function get_credit_packs() {
		if ( ! $this->is_authenticated_callback || ! call_user_func( $this->is_authenticated_callback ) ) {
			return new \WP_Error(
				'not_authenticated',
				__( 'Must be authenticated to get credit packs', 'beepbeep-ai-alt-text-generator' )
			);
		}

		$response = $this->request_handler->make_request( '/credits/packs', 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( $response['success'] && isset( $response['data'] ) ) {
			$packs = is_array( $response['data'] ) ? $response['data'] : array();

			return array_map(
				function ( $pack ) {
					return array(
						'id'      => $pack['id'] ?? '',
						'credits' => isset( $pack['credits'] ) ? intval( $pack['credits'] ) : 0,
						'price'   => isset( $pack['price'] ) ? intval( $pack['price'] ) : 0,
					);
				},
				$packs
			);
		}

		return new \WP_Error(
			'credit_packs_failed',
			$response['data']['error'] ?? __( 'Failed to get credit packs', 'beepbeep-ai-alt-text-generator' )
		);
	}
}
