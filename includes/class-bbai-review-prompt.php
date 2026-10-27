<?php
/**
 * One-time WordPress.org review prompt.
 *
 * Shown as an inline admin notice on the plugin's own admin pages only, after the
 * site's first successful ALT text generation. Once the user dismisses it or clicks
 * through, it is never shown to that user again. No outbound calls, no tracking.
 *
 * @package BeepBeep_AI
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Review prompt controller.
 */
class BBAI_Review_Prompt {

	/** Site option: unix time of the first successful generation. */
	public const FIRST_SUCCESS_OPTION = 'bbai_first_success_at';

	/** User meta: set once the user dismissed or clicked the prompt. */
	public const DONE_META = 'bbai_review_prompt_done';

	/** admin-post action name. */
	public const ACTION = 'bbai_review_prompt';

	/** WordPress.org review form for this plugin. */
	public const REVIEW_URL = 'https://wordpress.org/support/plugin/beepbeep-ai-alt-text-generator/reviews/#new-post';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'bbai_alt_text_generated', array( __CLASS__, 'mark_first_success' ), 10, 0 );
		add_action( 'bbai_alt_text_generated', array( __CLASS__, 'clear_fixed_count_cache' ), 10, 0 );
		add_action( 'admin_notices', array( __CLASS__, 'render_notice' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_action' ) );
	}

	/**
	 * Record the first real success (idempotent; never overwrites).
	 */
	public static function mark_first_success(): void {
		if ( get_option( self::FIRST_SUCCESS_OPTION ) ) {
			return;
		}
		add_option( self::FIRST_SUCCESS_OPTION, time(), '', false );
	}

	/**
	 * Keep the "You fixed X images" count fresh after each generation.
	 */
	public static function clear_fixed_count_cache(): void {
		delete_transient( 'bbai_nudge_fixed_count' );
	}

	/**
	 * Whether the current user can manage the plugin.
	 */
	private static function user_can_manage(): bool {
		return current_user_can( 'manage_bbbbai_text' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Whether the current request is one of this plugin's own admin pages.
	 */
	private static function is_plugin_screen(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page routing check.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( '' === $page || 0 !== strpos( $page, 'bbai' ) ) {
			return false;
		}
		return ! in_array( $page, array( 'bbai-checkout', 'bbai-onboarding' ), true );
	}

	/**
	 * Whether the prompt should render for the current user and screen.
	 */
	public static function should_show(): bool {
		if ( ! is_admin() || ! self::user_can_manage() || ! self::is_plugin_screen() ) {
			return false;
		}
		if ( ! get_option( self::FIRST_SUCCESS_OPTION ) ) {
			return false;
		}
		$user_id = get_current_user_id();
		if ( get_user_meta( $user_id, self::DONE_META, true ) ) {
			return false;
		}
		// Respect answers given to the earlier review asks.
		if ( get_option( 'bbai_review_already_reviewed', false ) || get_user_meta( $user_id, 'bbai_phase17_review_prompt_dismissed', true ) ) {
			return false;
		}
		return true;
	}

	/**
	 * This inline notice is the plugin's single review ask; the older dashboard
	 * overlay and the QA-engagement notice stand down while it is loaded.
	 */
	public static function owns_review_ask(): bool {
		return true;
	}

	/**
	 * Build an admin-post URL for a prompt action.
	 *
	 * @param string $choice `review` or `dismiss`.
	 */
	private static function action_url( string $choice ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::ACTION,
					'choice' => $choice,
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION
		);
	}

	/**
	 * Render the inline notice.
	 */
	public static function render_notice(): void {
		if ( ! self::should_show() ) {
			return;
		}
		?>
		<div class="notice notice-info bbai-review-prompt bbai-preserve-notice" data-bbai-review-prompt="1">
			<p>
				<strong><?php esc_html_e( 'Is OpptiAI Alt Text helping?', 'beepbeep-ai-alt-text-generator' ); ?></strong>
				<?php esc_html_e( 'A quick review on WordPress.org helps other site owners find it.', 'beepbeep-ai-alt-text-generator' ); ?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( self::action_url( 'review' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Leave a review', 'beepbeep-ai-alt-text-generator' ); ?></a>
				<a class="button button-link" href="<?php echo esc_url( self::action_url( 'dismiss' ) ); ?>"><?php esc_html_e( 'No thanks', 'beepbeep-ai-alt-text-generator' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Allow the fixed WordPress.org host for the review redirect only.
	 *
	 * @param string[] $hosts Allowed hosts.
	 * @return string[]
	 */
	public static function allow_wporg_host( $hosts ): array {
		$hosts   = is_array( $hosts ) ? $hosts : array();
		$hosts[] = 'wordpress.org';
		return $hosts;
	}

	/**
	 * Handle review / dismiss clicks.
	 */
	public static function handle_action(): void {
		if ( ! self::user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'beepbeep-ai-alt-text-generator' ) );
		}
		check_admin_referer( self::ACTION );

		$user_id = get_current_user_id();
		update_user_meta( $user_id, self::DONE_META, 1 );
		// Close out the older review asks too, so nobody is asked twice.
		update_user_meta( $user_id, 'bbai_phase17_review_prompt_dismissed', 1 );
		delete_transient( 'bbai_phase17_review_prompt_' . $user_id );
		$choice = isset( $_GET['choice'] ) ? sanitize_key( wp_unslash( $_GET['choice'] ) ) : '';
		if ( 'review' === $choice ) {
			update_option( 'bbai_review_already_reviewed', true, false );
			add_filter( 'allowed_redirect_hosts', array( __CLASS__, 'allow_wporg_host' ) );
			wp_safe_redirect( self::REVIEW_URL );
			exit;
		}

		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url( 'admin.php?page=bbai' ) );
		exit;
	}
}
