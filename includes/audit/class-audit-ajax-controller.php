<?php
/**
 * AJAX endpoints that drive an audit run from the browser.
 *
 * The scan is pumped by polling rather than cron, matching the pattern the
 * plugin already uses for the coverage scan: it works on hosts with unreliable
 * WP-Cron, and progress is visible while it happens.
 *
 * @package BeepBeep_AI
 * @since   4.8.0
 */

namespace BeepBeepAI\AltTextGenerator\Audit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Audit scan AJAX handlers.
 */
class Audit_Ajax_Controller {

	/**
	 * Nonce action, shared with the rest of the plugin's admin AJAX surface.
	 */
	const NONCE_ACTION = 'beepbeepai_nonce';

	/**
	 * Audit service.
	 *
	 * @var Image_Audit_Service
	 */
	private $service;

	/**
	 * Build the controller.
	 *
	 * @param Image_Audit_Service|null $service Audit service.
	 */
	public function __construct( $service = null ) {
		$this->service = $service instanceof Image_Audit_Service ? $service : new Image_Audit_Service();
	}

	/**
	 * Map AJAX actions to handlers.
	 *
	 * @return array<string,string>
	 */
	public static function actions() {
		return array(
			'bbai_audit_start'      => 'handle_start',
			'bbai_audit_resume'     => 'handle_resume',
			'bbai_audit_tick'       => 'handle_tick',
			'bbai_audit_cancel'     => 'handle_cancel',
			'bbai_audit_progress'   => 'handle_progress',
			'bbai_audit_rescan'     => 'handle_rescan',
			'bbai_audit_decorative' => 'handle_decorative',
			'bbai_audit_dismiss'    => 'handle_dismiss',
		);
	}

	/**
	 * Register the handlers.
	 *
	 * Logged-in only: there is no `wp_ajax_nopriv_` counterpart, so an audit can
	 * never be triggered anonymously.
	 *
	 * @return void
	 */
	public function register() {
		foreach ( self::actions() as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( $this, $method ) );
		}
	}

	/* ─── Handlers ─── */

	/**
	 * Start a run.
	 *
	 * @return void
	 */
	public function handle_start() {
		if ( ! $this->guard() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in guard().
		$restart = isset( $_POST['restart'] ) && in_array( sanitize_key( wp_unslash( $_POST['restart'] ) ), array( '1', 'true', 'yes' ), true );

		$this->respond( $this->service->start( get_current_user_id(), $restart ) );
	}

	/**
	 * Resume an interrupted run.
	 *
	 * @return void
	 */
	public function handle_resume() {
		if ( ! $this->guard() ) {
			return;
		}

		$this->respond( $this->service->resume( get_current_user_id() ) );
	}

	/**
	 * Process the next batch.
	 *
	 * @return void
	 */
	public function handle_tick() {
		if ( ! $this->guard() ) {
			return;
		}

		$this->respond( $this->service->tick( $this->requested_run_id() ) );
	}

	/**
	 * Cancel the current run.
	 *
	 * @return void
	 */
	public function handle_cancel() {
		if ( ! $this->guard() ) {
			return;
		}

		$this->respond( $this->service->cancel() );
	}

	/**
	 * Read progress without advancing the scan.
	 *
	 * @return void
	 */
	public function handle_progress() {
		if ( ! $this->guard() ) {
			return;
		}

		$this->respond( $this->service->describe( Audit_Progress_Service::get_state() ) );
	}

	/**
	 * Re-audit a single image after its alt text changed.
	 *
	 * @return void
	 */
	public function handle_rescan() {
		if ( ! $this->guard() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in guard().
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;

		if ( ! self::current_user_can_edit_attachment( $attachment_id ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to edit that image.', 'beepbeep-ai-alt-text-generator' ) ),
				403
			);

			return;
		}

		$this->respond( $this->service->rescan_attachment( $attachment_id ) );
	}

	/**
	 * Mark an image decorative, or take that decision back.
	 *
	 * @return void
	 */
	public function handle_decorative() {
		if ( ! $this->guard() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in guard().
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;

		if ( ! self::current_user_can_edit_attachment( $attachment_id ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to edit that image.', 'beepbeep-ai-alt-text-generator' ) ),
				403
			);

			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in guard().
		$decorative_raw = isset( $_POST['decorative'] ) ? sanitize_key( wp_unslash( $_POST['decorative'] ) ) : '';
		$decorative     = in_array( $decorative_raw, array( '1', 'true', 'yes' ), true );

		$this->respond( $this->service->set_decorative( $attachment_id, $decorative, get_current_user_id() ) );
	}

	/**
	 * Dismiss a finding without changing the image.
	 *
	 * @return void
	 */
	public function handle_dismiss() {
		if ( ! $this->guard() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in guard().
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;

		if ( ! self::current_user_can_edit_attachment( $attachment_id ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to edit that image.', 'beepbeep-ai-alt-text-generator' ) ),
				403
			);

			return;
		}

		$this->respond( $this->service->dismiss( $attachment_id ) );
	}

	/**
	 * Whether the current user may act on a specific attachment.
	 *
	 * The browser supplies the id, so it is checked against the real object
	 * rather than trusted — a user who can run an audit still cannot touch an
	 * attachment WordPress says they may not edit.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function current_user_can_edit_attachment( $attachment_id ) {
		$attachment_id = (int) $attachment_id;

		if ( $attachment_id <= 0 ) {
			return false;
		}

		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			return false;
		}

		return current_user_can( 'edit_post', $attachment_id );
	}

	/* ─── Plumbing ─── */

	/**
	 * Verify the nonce and capability, replying and returning false on failure.
	 *
	 * @return bool
	 */
	private function guard() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed. Refresh the page and try again.', 'beepbeep-ai-alt-text-generator' ) ),
				403
			);

			return false;
		}

		if ( ! self::current_user_can_run_audit() ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to run an image audit.', 'beepbeep-ai-alt-text-generator' ) ),
				403
			);

			return false;
		}

		return true;
	}

	/**
	 * Whether the current user may run or read an audit.
	 *
	 * Auditing reads every attachment in the library, so it sits behind the
	 * plugin's management capability rather than plain upload rights.
	 *
	 * @return bool
	 */
	public static function current_user_can_run_audit() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// 'manage_bbbbai_text' is the plugin's own capability, granted by Core::ensure_capability().
		$allowed = current_user_can( 'manage_options' ) || current_user_can( 'manage_bbbbai_text' );

		/**
		 * Filter whether the current user may run an image audit.
		 *
		 * @since 4.8.0
		 *
		 * @param bool $allowed Whether the user is permitted.
		 */
		return (bool) apply_filters( 'bbai_audit_user_can_run', $allowed );
	}

	/**
	 * The run id the caller believes is active.
	 *
	 * @return string
	 */
	private function requested_run_id() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in guard().
		$run_id = isset( $_POST['run_id'] ) ? sanitize_text_field( wp_unslash( $_POST['run_id'] ) ) : '';

		// Run ids are UUIDs or uniqid strings; reject anything else outright.
		return preg_match( '/^[A-Za-z0-9._-]{1,36}$/', $run_id ) ? $run_id : '';
	}

	/**
	 * Send a service result as JSON.
	 *
	 * @param array<string,mixed>|\WP_Error $result Service result.
	 * @return void
	 */
	private function respond( $result ) {
		if ( is_wp_error( $result ) ) {
			$data   = $result->get_error_data();
			$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;

			$payload = array(
				'message' => $result->get_error_message(),
				'code'    => $result->get_error_code(),
			);

			if ( is_array( $data ) && isset( $data['state'] ) ) {
				$payload['state'] = $data['state'];
			}

			wp_send_json_error( $payload, $status );

			return;
		}

		wp_send_json_success( $result );
	}
}
