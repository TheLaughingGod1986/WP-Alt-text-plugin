<?php
/**
 * Admin Notices Class
 *
 * Handles admin notice display and management.
 *
 * @package Optti\Admin
 */

namespace Optti\Admin;

use Optti\Framework\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Notices
 *
 * Manages admin notices.
 */
class Admin_Notices {

	use Singleton;

	/**
	 * Notices to display.
	 *
	 * @var array
	 */
	protected $notices = array();

	/**
	 * Initialize admin notices.
	 */
	protected function __construct() {
		add_action( 'admin_notices', array( $this, 'display_notices' ) );
		add_action( 'admin_init', array( $this, 'dismiss_notices' ) );
	}

	/**
	 * Add a notice.
	 *
	 * @param string $id Notice ID.
	 * @param string $message Notice message.
	 * @param string $type Notice type (success, error, warning, info).
	 * @param bool   $dismissible Whether notice is dismissible.
	 * @return void
	 */
	public function add( $id, $message, $type = 'info', $dismissible = true ) {
		$this->notices[ $id ] = array(
			'message'     => $message,
			'type'        => $type,
			'dismissible' => $dismissible,
		);
	}

	/**
	 * Remove a notice.
	 *
	 * @param string $id Notice ID.
	 * @return void
	 */
	public function remove( $id ) {
		unset( $this->notices[ $id ] );
	}

	/**
	 * Display all notices.
	 *
	 * @return void
	 */
	public function display_notices() {
		foreach ( $this->notices as $id => $notice ) {
			// Check if notice was dismissed.
			if ( $this->is_dismissed( $id ) ) {
				continue;
			}

			$classes = array(
				'notice',
				'notice-' . $notice['type'],
			);

			if ( $notice['dismissible'] ) {
				$classes[] = 'is-dismissible';
			}

			?>
			<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-notice-id="<?php echo esc_attr( $id ); ?>">
				<p><?php echo wp_kses_post( $notice['message'] ); ?></p>
				<?php if ( $notice['dismissible'] ) : ?>
					<button type="button" class="notice-dismiss" data-dismiss-notice="<?php echo esc_attr( $id ); ?>">
						<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'beepbeep-ai-alt-text-generator' ); ?></span>
					</button>
				<?php endif; ?>
			</div>
			<?php
		}
	}

	/**
	 * Handle notice dismissal.
	 *
	 * @return void
	 */
	public function dismiss_notices() {
		if ( ! isset( $_GET['optti_dismiss_notice'] ) || ! isset( $_GET['_wpnonce'] ) ) {
			return;
		}

		$notice_id = sanitize_text_field( wp_unslash( $_GET['optti_dismiss_notice'] ) );

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'optti_dismiss_notice_' . $notice_id ) ) {
			return;
		}

		$this->dismiss( $notice_id );
		wp_safe_redirect( remove_query_arg( array( 'optti_dismiss_notice', '_wpnonce' ) ) );
		exit;
	}

	/**
	 * Dismiss a notice.
	 *
	 * @param string $id Notice ID.
	 * @return void
	 */
	public function dismiss( $id ) {
		$dismissed = get_user_meta( get_current_user_id(), 'optti_dismissed_notices', true );
		if ( ! is_array( $dismissed ) ) {
			$dismissed = array();
		}
		$dismissed[] = $id;
		update_user_meta( get_current_user_id(), 'optti_dismissed_notices', array_unique( $dismissed ) );
	}

	/**
	 * Check if notice is dismissed.
	 *
	 * @param string $id Notice ID.
	 * @return bool True if dismissed.
	 */
	protected function is_dismissed( $id ) {
		$dismissed = get_user_meta( get_current_user_id(), 'optti_dismissed_notices', true );
		return is_array( $dismissed ) && in_array( $id, $dismissed, true );
	}

	/**
	 * Get dismiss URL.
	 *
	 * @param string $id Notice ID.
	 * @return string Dismiss URL.
	 */
	public function get_dismiss_url( $id ) {
		return wp_nonce_url(
			add_query_arg( 'optti_dismiss_notice', $id ),
			'optti_dismiss_notice_' . $id
		);
	}
}

