<?php
/**
 * First-run guidance: a "Get started" link on the Plugins screen, a one-time
 * notice after activation, and the guest usage analytics card.
 *
 * - The notice is set on activation and cleared when dismissed or when the
 *   plugin's own screen is opened. No redirect.
 * - After an image upload that leaves an image without alt text, a one-time
 *   per-user notice points back to the plugin, and media-new.php shows a
 *   "Back to Generate ALT text" link once the upload finishes.
 * - The analytics card is off by default and saves through the same
 *   admin-post handler as the Settings toggle, so there is one stored choice.
 *   Rendering the card sends nothing.
 *
 * @package BeepBeep_AI
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * First-run guidance helpers.
 */
class BBAI_First_Run {

	/** Site option: set on activation, deleted on dismiss or first visit. */
	public const NOTICE_OPTION = 'bbai_first_run_notice_pending';

	/** admin-post action for dismissing the notice. */
	public const DISMISS_ACTION = 'bbai_first_run_notice_dismiss';

	/** User meta: set after an image upload with no alt text, deleted on dismiss or plugin visit. */
	public const UPLOAD_NOTICE_META = 'bbai_upload_return_pending';

	/** admin-post action for dismissing the after-upload notice. */
	public const UPLOAD_DISMISS_ACTION = 'bbai_upload_notice_dismiss';

	/** Plugin dashboard page slug. */
	public const PAGE_SLUG = 'bbai';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		if ( defined( 'BEEPBEEP_AI_PLUGIN_BASENAME' ) ) {
			add_filter( 'plugin_action_links_' . BEEPBEEP_AI_PLUGIN_BASENAME, array( __CLASS__, 'action_links' ) );
		}
		add_action( 'admin_init', array( __CLASS__, 'maybe_clear_on_plugin_screen' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_notice' ) );
		add_action( 'admin_post_' . self::DISMISS_ACTION, array( __CLASS__, 'handle_dismiss' ) );
		add_action( 'add_attachment', array( __CLASS__, 'maybe_mark_upload' ), 20 );
		add_action( 'admin_notices', array( __CLASS__, 'render_upload_notice' ) );
		add_action( 'admin_post_' . self::UPLOAD_DISMISS_ACTION, array( __CLASS__, 'handle_upload_dismiss' ) );
		add_action( 'post-upload-ui', array( __CLASS__, 'render_upload_return_link' ) );
	}

	/**
	 * URL of the plugin dashboard.
	 */
	public static function dashboard_url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * Whether the current user can use the plugin.
	 */
	public static function user_can_manage(): bool {
		return current_user_can( 'manage_bbbbai_text' ) || current_user_can( 'manage_options' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Plugin capability added to administrators on init.
	}

	/**
	 * Add "Get started" before the default links on the Plugins screen.
	 *
	 * @param mixed $links Existing action links.
	 * @return array<int|string,string>
	 */
	public static function action_links( $links ): array {
		$links = is_array( $links ) ? $links : array();
		if ( ! self::user_can_manage() ) {
			return $links;
		}
		$get_started = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( self::dashboard_url() ),
			esc_html__( 'Get started', 'beepbeep-ai-alt-text-generator' )
		);
		return array_merge( array( 'bbai_get_started' => $get_started ), $links );
	}

	/**
	 * Called from the activation hook (per site).
	 */
	public static function mark_activated(): void {
		update_option( self::NOTICE_OPTION, time(), false );
	}

	/**
	 * Whether the current request is one of this plugin's own admin pages.
	 */
	public static function is_plugin_screen(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page routing check.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		return '' !== $page && 0 === strpos( $page, 'bbai' );
	}

	/**
	 * Opening the plugin's screen counts as having found it.
	 */
	public static function maybe_clear_on_plugin_screen(): void {
		if ( ! self::is_plugin_screen() || ! self::user_can_manage() ) {
			return;
		}
		if ( false !== get_option( self::NOTICE_OPTION, false ) ) {
			delete_option( self::NOTICE_OPTION );
		}
		$user_id = get_current_user_id();
		if ( $user_id > 0 && get_user_meta( $user_id, self::UPLOAD_NOTICE_META, true ) ) {
			delete_user_meta( $user_id, self::UPLOAD_NOTICE_META );
		}
	}

	/**
	 * Admins who can both upload and use the plugin.
	 */
	public static function user_can_upload_and_manage(): bool {
		return current_user_can( 'upload_files' ) && self::user_can_manage();
	}

	/**
	 * After an image upload, remember a one-time notice for the uploader when
	 * the image has no alt text. Covers uploads from the empty-library state
	 * and any upload while images are missing alt. Sends nothing.
	 *
	 * @param int $attachment_id New attachment ID.
	 */
	public static function maybe_mark_upload( $attachment_id ): void {
		$attachment_id = (int) $attachment_id;
		$user_id       = get_current_user_id();
		if ( $attachment_id <= 0 || $user_id <= 0 || ! self::user_can_upload_and_manage() ) {
			return;
		}
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}
		$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( is_string( $alt ) && '' !== trim( $alt ) ) {
			return;
		}
		if ( ! get_user_meta( $user_id, self::UPLOAD_NOTICE_META, true ) ) {
			update_user_meta( $user_id, self::UPLOAD_NOTICE_META, time() );
		}
	}

	/**
	 * Whether the after-upload notice should render now.
	 */
	public static function should_show_upload_notice(): bool {
		if ( ! is_admin() || self::is_plugin_screen() || ! self::user_can_upload_and_manage() ) {
			return false;
		}
		$user_id = get_current_user_id();
		return $user_id > 0 && (bool) get_user_meta( $user_id, self::UPLOAD_NOTICE_META, true );
	}

	/**
	 * Render the one-time after-upload notice.
	 */
	public static function render_upload_notice(): void {
		if ( ! self::should_show_upload_notice() ) {
			return;
		}
		$dismiss = wp_nonce_url(
			add_query_arg( array( 'action' => self::UPLOAD_DISMISS_ACTION ), admin_url( 'admin-post.php' ) ),
			self::UPLOAD_DISMISS_ACTION
		);
		?>
		<div class="notice notice-info bbai-upload-return-notice bbai-preserve-notice" data-bbai-upload-return-notice="1">
			<p><?php esc_html_e( 'Generate ALT text for these images.', 'beepbeep-ai-alt-text-generator' ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( self::dashboard_url() ); ?>"><?php esc_html_e( 'Open OpptiAI Alt Text', 'beepbeep-ai-alt-text-generator' ); ?></a>
				<a class="button button-link" href="<?php echo esc_url( $dismiss ); ?>"><?php esc_html_e( 'Dismiss', 'beepbeep-ai-alt-text-generator' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle the after-upload notice dismiss link.
	 */
	public static function handle_upload_dismiss(): void {
		if ( ! self::user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'beepbeep-ai-alt-text-generator' ), 403 );
		}
		check_admin_referer( self::UPLOAD_DISMISS_ACTION );
		delete_user_meta( get_current_user_id(), self::UPLOAD_NOTICE_META );

		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url( 'upload.php' ) );
		exit;
	}

	/**
	 * On media-new.php, a "Back to Generate ALT text" link that appears once an
	 * upload has finished (or straight away if one already has).
	 */
	public static function render_upload_return_link(): void {
		if ( 'media-new.php' !== ( isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : '' ) || ! self::user_can_upload_and_manage() ) {
			return;
		}
		$user_id = get_current_user_id();
		$done    = $user_id > 0 && (bool) get_user_meta( $user_id, self::UPLOAD_NOTICE_META, true );
		?>
		<p class="bbai-upload-return-link" data-bbai-upload-return-link="1"<?php echo $done ? '' : ' hidden'; ?>>
			<a class="button" href="<?php echo esc_url( self::dashboard_url() ); ?>"><?php esc_html_e( 'Back to Generate ALT text', 'beepbeep-ai-alt-text-generator' ); ?></a>
		</p>
		<script>
		document.addEventListener( 'DOMContentLoaded', function () {
			var link = document.querySelector( '[data-bbai-upload-return-link]' );
			var list = document.getElementById( 'media-items' );
			if ( ! link || ! link.hidden || ! list || ! window.MutationObserver ) {
				return;
			}
			var observer = new MutationObserver( function () {
				if ( list.querySelector( '.edit-attachment' ) ) {
					link.hidden = false;
					observer.disconnect();
				}
			} );
			observer.observe( list, { childList: true, subtree: true } );
		} );
		</script>
		<?php
	}

	/**
	 * Whether the post-activation notice should render now.
	 */
	public static function should_show_notice(): bool {
		if ( ! is_admin() || ! self::user_can_manage() || self::is_plugin_screen() ) {
			return false;
		}
		return false !== get_option( self::NOTICE_OPTION, false );
	}

	/**
	 * Nonce'd dismiss URL.
	 */
	public static function dismiss_url(): string {
		return wp_nonce_url(
			add_query_arg( array( 'action' => self::DISMISS_ACTION ), admin_url( 'admin-post.php' ) ),
			self::DISMISS_ACTION
		);
	}

	/**
	 * Render the one-time post-activation notice.
	 */
	public static function render_notice(): void {
		if ( ! self::should_show_notice() ) {
			return;
		}
		?>
		<div class="notice notice-success bbai-first-run-notice bbai-preserve-notice" data-bbai-first-run-notice="1">
			<p><?php esc_html_e( 'OpptiAI Alt Text is ready. Upload images, then click Generate on the OpptiAI Alt Text screen.', 'beepbeep-ai-alt-text-generator' ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( self::dashboard_url() ); ?>"><?php esc_html_e( 'Open OpptiAI Alt Text', 'beepbeep-ai-alt-text-generator' ); ?></a>
				<a class="button button-link" href="<?php echo esc_url( self::dismiss_url() ); ?>"><?php esc_html_e( 'Dismiss', 'beepbeep-ai-alt-text-generator' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle the dismiss link.
	 */
	public static function handle_dismiss(): void {
		if ( ! self::user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'beepbeep-ai-alt-text-generator' ), 403 );
		}
		check_admin_referer( self::DISMISS_ACTION );
		delete_option( self::NOTICE_OPTION );

		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url( 'plugins.php' ) );
		exit;
	}

	/**
	 * Whether the guest usage analytics card should render. Only for admins
	 * who can save the choice, only while no choice has been saved, and never
	 * when wp-config forces the choice.
	 */
	public static function should_show_consent_card(): bool {
		if ( ! class_exists( BBAI_Telemetry::class ) || defined( 'BBAI_TELEMETRY_CONSENT' ) || ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		return ! self::consent_answered();
	}

	/**
	 * Whether an admin has saved a usage analytics choice (yes or no).
	 */
	public static function consent_answered(): bool {
		return 'settings' === get_option( BBAI_Telemetry::CONSENT_SOURCE_OPTION, '' )
			&& in_array( get_option( BBAI_Telemetry::CONSENT_OPTION, false ), array( 'yes', 'no', '1', '0', 1, 0, true ), true );
	}

	/**
	 * Render the guest usage analytics card, or the one-line "change it" form
	 * once answered (guests have no Settings tab). Outputs nothing else.
	 */
	public static function render_consent_card(): void {
		if ( ! class_exists( BBAI_Telemetry::class ) || defined( 'BBAI_TELEMETRY_CONSENT' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$action = BBAI_Telemetry::CONSENT_FORM_ACTION;
		if ( self::should_show_consent_card() ) {
			?>
			<section class="bbai-card bbai-guest-consent-card" data-bbai-guest-consent-card="1" aria-labelledby="bbai-guest-consent-title">
				<h2 id="bbai-guest-consent-title" class="bbai-guest-consent-card__title"><?php esc_html_e( 'Help improve OpptiAI Alt Text?', 'beepbeep-ai-alt-text-generator' ); ?></h2>
				<p class="bbai-guest-consent-card__body"><?php esc_html_e( 'Share usage events, such as which screens are opened and whether generation succeeds. No site URL, email, images or ALT text are sent. Nothing is shared unless you choose yes, and you can change this later.', 'beepbeep-ai-alt-text-generator' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bbai-guest-consent-card__form">
					<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
					<?php wp_nonce_field( $action ); ?>
					<input type="hidden" name="bbai_consent_source" value="guest_card">
					<button type="submit" name="bbai_usage_analytics" value="1" class="bbai-btn bbai-btn-secondary"><?php esc_html_e( 'Yes, share', 'beepbeep-ai-alt-text-generator' ); ?></button>
					<button type="submit" name="bbai_usage_analytics" value="0" class="bbai-btn bbai-btn-link"><?php esc_html_e( 'No thanks', 'beepbeep-ai-alt-text-generator' ); ?></button>
				</form>
			</section>
			<?php
			return;
		}

		$on = BBAI_Telemetry::stored_consent_is_yes();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bbai-guest-consent-line" data-bbai-guest-consent-line="1">
			<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
			<?php wp_nonce_field( $action ); ?>
			<input type="hidden" name="bbai_consent_source" value="guest_card">
			<input type="hidden" name="bbai_usage_analytics" value="<?php echo esc_attr( $on ? '0' : '1' ); ?>">
			<span><?php echo $on ? esc_html__( 'Usage analytics are on.', 'beepbeep-ai-alt-text-generator' ) : esc_html__( 'Usage analytics are off.', 'beepbeep-ai-alt-text-generator' ); ?></span>
			<button type="submit" class="bbai-btn bbai-btn-link"><?php echo $on ? esc_html__( 'Turn off', 'beepbeep-ai-alt-text-generator' ) : esc_html__( 'Turn on', 'beepbeep-ai-alt-text-generator' ); ?></button>
		</form>
		<?php
	}
}
