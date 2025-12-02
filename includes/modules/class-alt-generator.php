<?php
/**
 * Alt Generator Module
 *
 * Handles alt text generation for images.
 *
 * @package Optti\Modules
 */

namespace Optti\Modules;

use Optti\Framework\Interfaces\ModuleInterface;
use Optti\Framework\ApiClient;
use Optti\Framework\LicenseManager;
use Optti\Framework\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Alt_Generator
 *
 * Module for generating alt text.
 */
class Alt_Generator implements ModuleInterface {

	/**
	 * Module ID.
	 */
	const MODULE_ID = 'alt_generator';

	/**
	 * Get module identifier.
	 *
	 * @return string Module ID.
	 */
	public function get_id() {
		return self::MODULE_ID;
	}

	/**
	 * Get module name.
	 *
	 * @return string Module name.
	 */
	public function get_name() {
		return 'Alt Text Generator';
	}

	/**
	 * Initialize the module.
	 *
	 * @return void
	 */
	public function init() {
		// Register hooks for auto-generation on upload.
		add_action( 'add_attachment', [ $this, 'maybe_generate_on_upload' ] );

		// Register AJAX handlers.
		add_action( 'wp_ajax_optti_generate_alt', [ $this, 'ajax_generate_alt' ] );
		add_action( 'wp_ajax_optti_regenerate_alt', [ $this, 'ajax_regenerate_alt' ] );

		// Register REST endpoints.
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	/**
	 * Check if module is active.
	 *
	 * @return bool True if active.
	 */
	public function is_active() {
		return true; // Always active for this plugin.
	}

	/**
	 * Generate alt text for an image.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $source Source of generation (manual, auto, etc.).
	 * @param bool   $regenerate Whether to regenerate existing alt text.
	 * @return string|\WP_Error Generated alt text or error.
	 */
	public function generate( $attachment_id, $source = 'manual', $regenerate = false ) {
		// Check if image.
		if ( ! $this->is_image( $attachment_id ) ) {
			return new \WP_Error(
				'not_image',
				__( 'Attachment is not an image.', 'beepbeep-ai-alt-text-generator' )
			);
		}

		// Check quota.
		$license = LicenseManager::instance();
		if ( ! $license->can_consume( 1 ) ) {
			return new \WP_Error(
				'quota_exhausted',
				__( 'Monthly quota exhausted. Please upgrade your plan.', 'beepbeep-ai-alt-text-generator' )
			);
		}

		// Get image data.
		$image_url = wp_get_attachment_url( $attachment_id );
		$title     = get_the_title( $attachment_id );
		$caption   = wp_get_attachment_caption( $attachment_id );
		$parsed_image_url = $image_url ? wp_parse_url( $image_url ) : null;
		$filename  = $parsed_image_url && isset( $parsed_image_url['path'] ) ? wp_basename( $parsed_image_url['path'] ) : '';

		// Build context.
		$context = [
			'filename'   => $filename,
			'title'      => $title,
			'caption'    => $caption,
			'post_title' => '',
		];

		// Get parent post context if available.
		$post = get_post( $attachment_id );
		if ( $post && $post->post_parent ) {
			$parent = get_post( $post->post_parent );
			if ( $parent ) {
				$context['post_title'] = $parent->post_title ?? '';
			}
		}

		// Call framework API.
		$api = ApiClient::instance();
		$response = $api->generate_alt_text( $attachment_id, $context, $regenerate );

		if ( is_wp_error( $response ) ) {
			Logger::log( 'error', 'Alt text generation failed', [
				'attachment_id' => $attachment_id,
				'error'         => $response->get_error_message(),
			], 'alt_generator' );
			return $response;
		}

		// Extract alt text from response.
		$alt_text = $response['alt_text'] ?? '';
		if ( empty( $alt_text ) ) {
			return new \WP_Error(
				'no_alt_text',
				__( 'No alt text returned from API.', 'beepbeep-ai-alt-text-generator' )
			);
		}

		// Save alt text.
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
		update_post_meta( $attachment_id, '_beepbeepai_generated_at', current_time( 'mysql' ) );
		update_post_meta( $attachment_id, '_beepbeepai_source', $source );

		// Clear caches.
		$cache_manager = \Optti\Framework\CacheManager::instance();
		$cache_manager->clear_media_caches();

		// Update quota cache.
		if ( isset( $response['usage'] ) ) {
			$license->clear_quota_cache();
			$cache_manager->clear_usage_caches();
		}

		Logger::log( 'info', 'Alt text generated', [
			'attachment_id' => $attachment_id,
			'source'         => $source,
		], 'alt_generator' );

		return $alt_text;
	}

	/**
	 * Maybe generate alt text on upload.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function maybe_generate_on_upload( $attachment_id ) {
		// Check if auto-generation is enabled.
		$settings = get_option( 'optti_settings', [] );
		if ( empty( $settings['enable_on_upload'] ) ) {
			return;
		}

		// Only generate for images.
		if ( ! $this->is_image( $attachment_id ) ) {
			return;
		}

		// Generate in background.
		$this->generate( $attachment_id, 'auto', false );
	}

	/**
	 * Check if attachment is an image.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if image.
	 */
	protected function is_image( $attachment_id ) {
		$mime_type = get_post_mime_type( $attachment_id );
		return strpos( $mime_type, 'image/' ) === 0;
	}

	/**
	 * AJAX handler: Generate alt text.
	 *
	 * @return void
	 */
	public function ajax_generate_alt() {
		check_ajax_referer( 'optti_generate_alt', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ] );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;
		if ( ! $attachment_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid attachment ID', 'beepbeep-ai-alt-text-generator' ) ] );
		}

		$result = $this->generate( $attachment_id, 'manual', false );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [
				'message' => $result->get_error_message(),
				'code'    => $result->get_error_code(),
			] );
		}

		wp_send_json_success( [
			'alt_text' => $result,
			'message'  => __( 'Alt text generated successfully.', 'beepbeep-ai-alt-text-generator' ),
		] );
	}

	/**
	 * AJAX handler: Regenerate alt text.
	 *
	 * @return void
	 */
	public function ajax_regenerate_alt() {
		check_ajax_referer( 'optti_regenerate_alt', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ] );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;
		if ( ! $attachment_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid attachment ID', 'beepbeep-ai-alt-text-generator' ) ] );
		}

		$result = $this->generate( $attachment_id, 'manual', true );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [
				'message' => $result->get_error_message(),
				'code'    => $result->get_error_code(),
			] );
		}

		wp_send_json_success( [
			'alt_text' => $result,
			'message'  => __( 'Alt text regenerated successfully.', 'beepbeep-ai-alt-text-generator' ),
		] );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route( 'optti/v1', '/generate', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'rest_generate' ],
			'permission_callback' => [ $this, 'rest_permission_check' ],
		] );
	}

	/**
	 * REST endpoint: Generate alt text.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response.
	 */
	public function rest_generate( $request ) {
		$attachment_id = $request->get_param( 'attachment_id' );
		$regenerate    = $request->get_param( 'regenerate' );

		if ( ! $attachment_id ) {
			return new \WP_Error(
				'missing_attachment_id',
				__( 'Attachment ID is required.', 'beepbeep-ai-alt-text-generator' ),
				[ 'status' => 400 ]
			);
		}

		$result = $this->generate( $attachment_id, 'rest', (bool) $regenerate );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response( [
			'alt_text' => $result,
		] );
	}

	/**
	 * REST permission check.
	 *
	 * @return bool True if allowed.
	 */
	public function rest_permission_check() {
		return current_user_can( 'manage_options' );
	}
}

