<?php
/**
 * OpptiAI Framework - Permissions Handler
 *
 * @package OpptiAI\Framework\Security
 */

namespace OpptiAI\Framework\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Permissions {

	/**
	 * Custom capability for managing OpptiAI features
	 */
	const MANAGE_CAPABILITY = 'manage_ai_alt_text';

	/**
	 * Check if current user can manage OpptiAI features
	 *
	 * @return bool True if user has permission
	 */
	public static function can_manage() {
		return current_user_can( self::MANAGE_CAPABILITY ) || current_user_can( 'manage_options' );
	}

	/**
	 * Check if current user can edit media
	 *
	 * @return bool True if user has permission
	 */
	public static function can_edit_media() {
		return current_user_can( 'upload_files' ) || self::can_manage();
	}

	/**
	 * Check if current user is administrator
	 *
	 * @return bool True if user is admin
	 */
	public static function is_admin() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Verify nonce for security
	 *
	 * @param string $nonce  Nonce to verify
	 * @param string $action Action name
	 * @return bool True if valid
	 */
	public static function verify_nonce( $nonce, $action ) {
		return wp_verify_nonce( $nonce, $action ) !== false;
	}

	/**
	 * Check AJAX referer
	 *
	 * @param string $action Action name
	 * @param string $query_arg Query arg name (default: '_ajax_nonce')
	 * @return bool True if valid
	 */
	public static function check_ajax_referer( $action, $query_arg = '_ajax_nonce' ) {
		return check_ajax_referer( $action, $query_arg, false ) !== false;
	}

	/**
	 * Create nonce
	 *
	 * @param string $action Action name
	 * @return string Nonce
	 */
	public static function create_nonce( $action ) {
		return wp_create_nonce( $action );
	}

	/**
	 * Get nonce URL
	 *
	 * @param string $actionurl URL to add nonce to
	 * @param string $action    Action name
	 * @return string URL with nonce
	 */
	public static function nonce_url( $actionurl, $action ) {
		return wp_nonce_url( $actionurl, $action );
	}

	/**
	 * Get nonce field HTML
	 *
	 * @param string $action Action name
	 * @param string $name   Field name (default: '_wpnonce')
	 * @param bool   $referer Add referer field (default: true)
	 * @param bool   $echo    Echo or return (default: true)
	 * @return string Nonce field HTML
	 */
	public static function nonce_field( $action, $name = '_wpnonce', $referer = true, $echo = true ) {
		return wp_nonce_field( $action, $name, $referer, $echo );
	}

	/**
	 * Add custom capability to administrator role
	 *
	 * @return void
	 */
	public static function add_capability_to_admin() {
		$role = get_role( 'administrator' );
		if ( $role && ! $role->has_cap( self::MANAGE_CAPABILITY ) ) {
			$role->add_cap( self::MANAGE_CAPABILITY );
		}
	}

	/**
	 * Remove custom capability from all roles
	 *
	 * @return void
	 */
	public static function remove_capability() {
		$roles = array( 'administrator', 'editor', 'author', 'contributor' );
		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );
			if ( $role && $role->has_cap( self::MANAGE_CAPABILITY ) ) {
				$role->remove_cap( self::MANAGE_CAPABILITY );
			}
		}
	}
}
