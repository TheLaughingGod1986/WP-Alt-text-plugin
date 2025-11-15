<?php
/**
 * OpptiAI Framework - Authentication Handler
 *
 * Handles JWT token and license key authentication for OpptiAI plugins
 *
 * @package OpptiAI\Framework\Auth
 */

namespace OpptiAI\Framework\Auth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Auth {

	/**
	 * Option keys
	 */
	private $token_option_key = 'opptiai_alt_jwt_token';
	private $user_option_key = 'opptiai_alt_user_data';
	private $site_id_option_key = 'opptiai_alt_site_id';
	private $license_key_option_key = 'opptiai_alt_license_key';
	private $license_data_option_key = 'opptiai_alt_license_data';
	private $encryption_prefix = 'enc:';

	/**
	 * Get stored JWT token
	 *
	 * @return string Token or empty string
	 */
	public function get_token() {
		$token = get_option( $this->token_option_key, '' );
		if ( '' === $token || false === $token ) {
			$legacy = get_option( 'alttextai_jwt_token', '' );
			if ( ! empty( $legacy ) ) {
				$this->set_token( $legacy );
				$token = $legacy;
			}
		}
		$token = is_string( $token ) ? $token : '';
		return $this->maybe_decrypt_secret( $token );
	}

	/**
	 * Store JWT token
	 *
	 * @param string $token JWT token
	 * @return void
	 */
	public function set_token( $token ) {
		if ( empty( $token ) ) {
			$this->clear_token();
			return;
		}

		$stored = $this->encrypt_secret( $token );
		if ( empty( $stored ) ) {
			$stored = $token;
		}
		update_option( $this->token_option_key, $stored, false );
	}

	/**
	 * Clear stored token
	 *
	 * @return void
	 */
	public function clear_token() {
		delete_option( $this->token_option_key );
		delete_option( 'alttextai_jwt_token' );
		delete_option( $this->user_option_key );
		delete_option( 'alttextai_user_data' );
	}

	/**
	 * Get stored user data
	 *
	 * @return array|null User data or null
	 */
	public function get_user_data() {
		$data = get_option( $this->user_option_key, null );
		if ( ( false === $data || null === $data ) ) {
			$legacy = get_option( 'alttextai_user_data', null );
			if ( null !== $legacy && false !== $legacy ) {
				update_option( $this->user_option_key, $legacy );
				$data = $legacy;
			}
		}
		return ( false !== $data && null !== $data ) ? $data : null;
	}

	/**
	 * Store user data
	 *
	 * @param array $user_data User data array
	 * @return void
	 */
	public function set_user_data( $user_data ) {
		update_option( $this->user_option_key, $user_data, false );
	}

	/**
	 * Get stored license key
	 *
	 * @return string License key or empty string
	 */
	public function get_license_key() {
		$key = get_option( $this->license_key_option_key, '' );
		if ( '' === $key || false === $key ) {
			return '';
		}
		return $this->maybe_decrypt_secret( $key );
	}

	/**
	 * Store license key
	 *
	 * @param string $license_key License key
	 * @return void
	 */
	public function set_license_key( $license_key ) {
		if ( empty( $license_key ) ) {
			$this->clear_license_key();
			return;
		}

		$stored = $this->encrypt_secret( $license_key );
		if ( empty( $stored ) ) {
			$stored = $license_key;
		}
		update_option( $this->license_key_option_key, $stored, false );
	}

	/**
	 * Clear stored license key
	 *
	 * @return void
	 */
	public function clear_license_key() {
		delete_option( $this->license_key_option_key );
		delete_option( $this->license_data_option_key );
	}

	/**
	 * Get stored license data
	 *
	 * @return array|null License data or null
	 */
	public function get_license_data() {
		$data = get_option( $this->license_data_option_key, null );
		return ( false !== $data && null !== $data ) ? $data : null;
	}

	/**
	 * Store license data
	 *
	 * @param array $license_data License data
	 * @return void
	 */
	public function set_license_data( $license_data ) {
		update_option( $this->license_data_option_key, $license_data, false );
	}

	/**
	 * Check if license key is active
	 *
	 * @return bool True if active license exists
	 */
	public function has_active_license() {
		$license_key  = $this->get_license_key();
		$license_data = $this->get_license_data();

		return ! empty( $license_key ) && ! empty( $license_data ) &&
			   isset( $license_data['organization'] ) &&
			   isset( $license_data['site'] );
	}

	/**
	 * Check if user is authenticated (JWT or License Key)
	 *
	 * @param callable $validate_callback Optional callback to validate token with backend
	 * @return bool True if authenticated
	 */
	public function is_authenticated( $validate_callback = null ) {
		// Check license key first (agency license)
		if ( $this->has_active_license() ) {
			return true;
		}

		// Check JWT token (personal account)
		$token = $this->get_token();

		if ( empty( $token ) ) {
			return false;
		}

		// In local development, just check if token exists
		if ( defined( 'WP_LOCAL_DEV' ) && WP_LOCAL_DEV ) {
			return true;
		}

		// Validate token if callback provided
		if ( $validate_callback && is_callable( $validate_callback ) ) {
			$last_check      = get_transient( 'opptiai_alt_token_last_check' );
			$should_validate = false === $last_check;

			if ( $should_validate ) {
				$is_valid = call_user_func( $validate_callback, $token );

				if ( ! $is_valid ) {
					$this->clear_token();
					return false;
				}

				// Cache result for 5 minutes
				set_transient( 'opptiai_alt_token_last_check', time(), 5 * MINUTE_IN_SECONDS );
			}
		}

		return true;
	}

	/**
	 * Get or generate unique site ID
	 *
	 * @return string Site ID
	 */
	public function get_site_id() {
		$site_id = get_option( $this->site_id_option_key, '' );

		if ( empty( $site_id ) ) {
			// Generate unique site ID based on site URL + timestamp
			$site_url = get_site_url();
			$site_id  = md5( $site_url . time() . wp_generate_password( 20, false ) );
			update_option( $this->site_id_option_key, $site_id, false );
		}

		return $site_id;
	}

	/**
	 * Get authentication headers for API requests
	 *
	 * @return array Headers array
	 */
	public function get_auth_headers() {
		$token       = $this->get_token();
		$license_key = $this->get_license_key();
		$site_id     = $this->get_site_id();

		$headers = array(
			'Content-Type' => 'application/json',
			'X-Site-Hash'  => $site_id,
			'X-Site-URL'   => get_site_url(),
		);

		// Priority: License key > JWT token
		if ( ! empty( $license_key ) ) {
			$headers['X-License-Key'] = $license_key;
		} elseif ( $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		return $headers;
	}

	/**
	 * Encrypt secret values before persisting
	 *
	 * @param string $value Value to encrypt
	 * @return string Encrypted value or original if encryption fails
	 */
	private function encrypt_secret( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}

		if ( ! function_exists( 'openssl_encrypt' ) || ( ! function_exists( 'random_bytes' ) && ! function_exists( 'openssl_random_pseudo_bytes' ) ) ) {
			return $value;
		}

		$key = substr( hash( 'sha256', wp_salt( 'auth' ) ), 0, 32 );
		$iv  = function_exists( 'random_bytes' ) ? @random_bytes( 16 ) : openssl_random_pseudo_bytes( 16 );
		if ( false === $iv ) {
			return $value;
		}

		$cipher = openssl_encrypt( $value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		if ( false === $cipher ) {
			return $value;
		}

		return $this->encryption_prefix . base64_encode( $iv . $cipher );
	}

	/**
	 * Decrypt stored secrets
	 *
	 * @param string $value Value to decrypt
	 * @return string Decrypted value or original if not encrypted
	 */
	private function maybe_decrypt_secret( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}

		if ( 0 !== strpos( $value, $this->encryption_prefix ) ) {
			return $value;
		}

		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return substr( $value, strlen( $this->encryption_prefix ) );
		}

		$payload = base64_decode( substr( $value, strlen( $this->encryption_prefix ) ), true );
		if ( false === $payload || strlen( $payload ) < 17 ) {
			return '';
		}

		$iv     = substr( $payload, 0, 16 );
		$cipher = substr( $payload, 16 );
		$key    = substr( hash( 'sha256', wp_salt( 'auth' ) ), 0, 32 );
		$plain  = openssl_decrypt( $cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		return false !== $plain ? $plain : '';
	}

	/**
	 * Clear all authentication data
	 *
	 * @return void
	 */
	public function logout() {
		$this->clear_token();
		$this->clear_license_key();
		delete_transient( 'opptiai_alt_token_last_check' );
	}
}
