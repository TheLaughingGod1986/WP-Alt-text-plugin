<?php
/**
 * "More from Oppti" panel.
 *
 * A small, dismissible panel on this plugin's own Dashboard screen that lists
 * the other OpptiAI plugins this site does not have yet. Rules:
 * - Only on the plugin's own Dashboard screen (page=bbai), never as an admin
 *   notice and never on the WordPress dashboard.
 * - Only after a successful generation on this site (the site option set by
 *   BBAI_Review_Prompt::mark_first_success(), or the dashboard's own
 *   "has ever generated" signal). Hidden on empty and first-run error states.
 * - Lists only OpptiAI plugins that are not installed (checked locally).
 * - Dismissing hides it for that user for good (user meta).
 * - Links go to the in-dashboard installer search when the user can install
 *   plugins, otherwise to the WordPress.org listing. Nothing is installed
 *   automatically.
 * - Optional events go through the existing consented telemetry pipeline only.
 *
 * @package BeepBeep_AI
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cross-plugin panel controller.
 */
class BBAI_More_From_Oppti {

	/** This plugin's id in the catalog (also used in events and utm_content). */
	public const FROM = 'alt';

	/** User meta: set once the user dismissed the panel. */
	public const DISMISS_META = 'bbai_more_from_oppti_dismissed';

	/** admin-post / admin-ajax action for dismissal. */
	public const DISMISS_ACTION = 'bbai_more_from_oppti_dismiss';

	/** Plugin admin page that hosts the panel. */
	public const SCREEN_PAGE = 'bbai';

	/** UTM campaign for WordPress.org fallback links. */
	public const UTM_CAMPAIGN = 'push-nov26';

	/** Events the panel may send (through the consented telemetry bridge). */
	public const EVENTS = array( 'crosssell_clicked', 'crosssell_panel_shown', 'crosssell_panel_dismissed' );

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'admin_post_' . self::DISMISS_ACTION, array( __CLASS__, 'handle_dismiss' ) );
		add_action( 'wp_ajax_' . self::DISMISS_ACTION, array( __CLASS__, 'handle_dismiss' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * OpptiAI plugins. `kind` picks the row helper: credit ("Free to try."),
	 * free ("A free plugin."), none (Internal Linking leads on the result only).
	 * `login` marks the plugins the connected intro may name as using the shared
	 * Oppti login (Alt Text and Titles only; the Auditor never).
	 *
	 * @return array<string,array{slug:string,name:string,kind:string,login:bool,text_domains:string[]}>
	 */
	public static function catalog(): array {
		return array(
			'alt'     => array(
				'slug'         => 'beepbeep-ai-alt-text-generator',
				'name'         => 'OpptiAI Alt Text',
				'kind'         => 'credit',
				'login'        => true,
				'text_domains' => array( 'beepbeep-ai-alt-text-generator' ),
			),
			'titles'  => array(
				'slug'         => 'opptiai-titles',
				'name'         => 'OpptiAI Titles',
				'kind'         => 'credit',
				'login'        => true,
				'text_domains' => array( 'beepbeep-titles', 'opptiai-titles' ),
			),
			'auditor' => array(
				'slug'         => 'opptiai-website-auditor',
				'name'         => 'OpptiAI Website Auditor',
				'kind'         => 'free',
				'login'        => false,
				'text_domains' => array( 'opptiai-website-auditor' ),
			),
			'linking' => array(
				'slug'         => 'opptiai-internal-linking',
				'name'         => 'OpptiAI Internal Linking',
				'kind'         => 'none',
				'login'        => false,
				'text_domains' => array( 'opptiai-internal-linking' ),
			),
		);
	}

	/**
	 * All panel copy in one place so Marketing can reword it.
	 * Placeholder copy until Marketing sends the final strings.
	 *
	 * @return array<string,string>
	 */
	public static function copy(): array {
		return array(
			'heading'       => __( 'More from Oppti', 'beepbeep-ai-alt-text-generator' ),
			/* translators: %s: plugin name(s), for example "Titles". */
			'login_line'    => __( 'Your same Oppti login also works with %s.', 'beepbeep-ai-alt-text-generator' ),
			'guest_line'    => __( 'More from OpptiAI, for the rest of the job.', 'beepbeep-ai-alt-text-generator' ),
			/* translators: 1: comma separated plugin names, 2: last plugin name. */
			'join_two'      => __( '%1$s and %2$s', 'beepbeep-ai-alt-text-generator' ),
			'short_alt'     => __( 'Alt Text', 'beepbeep-ai-alt-text-generator' ),
			'short_titles'  => __( 'Titles', 'beepbeep-ai-alt-text-generator' ),
			'short_auditor' => __( 'Website Auditor', 'beepbeep-ai-alt-text-generator' ),
			'short_linking' => __( 'Internal Linking', 'beepbeep-ai-alt-text-generator' ),
			'desc_alt'      => __( 'Write alt text for images that have none.', 'beepbeep-ai-alt-text-generator' ),
			'desc_titles'   => __( 'Find weak SEO titles and meta descriptions.', 'beepbeep-ai-alt-text-generator' ),
			'desc_auditor'  => __( 'See what to fix on your site first.', 'beepbeep-ai-alt-text-generator' ),
			'desc_linking'  => __( 'Find pages with no internal links.', 'beepbeep-ai-alt-text-generator' ),
			'cta_alt'       => __( 'Install Alt Text', 'beepbeep-ai-alt-text-generator' ),
			'cta_titles'    => __( 'Install Titles', 'beepbeep-ai-alt-text-generator' ),
			'cta_auditor'   => __( 'Install Website Auditor', 'beepbeep-ai-alt-text-generator' ),
			'cta_linking'   => __( 'Install Internal Linking', 'beepbeep-ai-alt-text-generator' ),
			'helper_credit' => __( 'Free to try.', 'beepbeep-ai-alt-text-generator' ),
			'helper_free'   => __( 'A free plugin. No account needed.', 'beepbeep-ai-alt-text-generator' ),
			'wporg_suffix'  => __( '(opens WordPress.org)', 'beepbeep-ai-alt-text-generator' ),
			'dismiss'       => __( 'Dismiss', 'beepbeep-ai-alt-text-generator' ),
			'dismiss_label' => __( 'Dismiss this More from Oppti panel', 'beepbeep-ai-alt-text-generator' ),
		);
	}

	/**
	 * Ids of OpptiAI plugins present in a get_plugins() style list.
	 *
	 * @param array<string,array<string,mixed>> $plugins Plugin file => header data.
	 * @return string[]
	 */
	public static function installed_ids( array $plugins ): array {
		$found = array();
		foreach ( self::catalog() as $id => $entry ) {
			foreach ( $plugins as $file => $data ) {
				$dir    = dirname( (string) $file );
				$domain = is_array( $data ) && isset( $data['TextDomain'] ) ? (string) $data['TextDomain'] : '';
				if ( $dir === $entry['slug'] || ( '' !== $domain && in_array( $domain, $entry['text_domains'], true ) ) ) {
					$found[] = $id;
					break;
				}
			}
		}
		return $found;
	}

	/**
	 * Ids of OpptiAI plugins in a list of active plugin files.
	 *
	 * @param string[] $active_files Plugin basenames (active_plugins option).
	 * @return string[]
	 */
	public static function active_ids( array $active_files ): array {
		$found = array();
		foreach ( self::catalog() as $id => $entry ) {
			foreach ( $active_files as $file ) {
				if ( dirname( (string) $file ) === $entry['slug'] ) {
					$found[] = $id;
					break;
				}
			}
		}
		return $found;
	}

	/**
	 * Catalog ids to suggest: every OpptiAI plugin except this one and any already installed.
	 *
	 * @param string[] $installed_ids Installed ids.
	 * @param string[] $active_ids    Active ids (belt and braces).
	 * @return string[]
	 */
	public static function candidate_ids( array $installed_ids, array $active_ids = array() ): array {
		$out = array();
		foreach ( array_keys( self::catalog() ) as $id ) {
			if ( self::FROM === $id || in_array( $id, $installed_ids, true ) || in_array( $id, $active_ids, true ) ) {
				continue;
			}
			$out[] = $id;
		}
		return $out;
	}

	/**
	 * Install link for a catalog id.
	 *
	 * @param string $id          Catalog id.
	 * @param bool   $can_install Whether the user can install plugins on this site.
	 * @return array{url:string,target:string}
	 */
	public static function link_for( string $id, bool $can_install ): array {
		$catalog = self::catalog();
		$slug    = $catalog[ $id ]['slug'] ?? '';
		if ( $can_install ) {
			return array(
				'url'    => admin_url( 'plugin-install.php?s=' . rawurlencode( $slug ) . '&tab=search&type=term' ),
				'target' => 'installer',
			);
		}
		$query = array(
			'utm_source'   => 'plugin',
			'utm_medium'   => 'crosssell',
			'utm_campaign' => self::UTM_CAMPAIGN,
			'utm_content'  => 'xs-' . self::FROM . '-to-' . $id,
		);
		return array(
			'url'    => 'https://wordpress.org/plugins/' . $slug . '/?' . http_build_query( $query, '', '&' ),
			'target' => 'wporg',
		);
	}

	/**
	 * Join names into "A", "A and B" or "A, B and C".
	 *
	 * @param string[] $names Names.
	 */
	public static function join_names( array $names ): string {
		$names = array_values( $names );
		$copy  = self::copy();
		$count = count( $names );
		if ( 0 === $count ) {
			return '';
		}
		if ( 1 === $count ) {
			return $names[0];
		}
		$last = array_pop( $names );
		return sprintf( $copy['join_two'], implode( ', ', $names ), $last );
	}

	/**
	 * Intro line. Connected sites name only the suggested plugins that use the
	 * Oppti login (Alt Text, Titles); when only Internal Linking and/or the
	 * Auditor are left, there is no intro (rows only). Guests always get the
	 * plain intro and never the login line.
	 *
	 * @param bool     $connected     Site is connected to an Oppti account.
	 * @param string[] $candidate_ids Suggested ids.
	 */
	public static function intro_line( bool $connected, array $candidate_ids ): string {
		$copy    = self::copy();
		$catalog = self::catalog();
		if ( $connected ) {
			$names = array();
			foreach ( $candidate_ids as $id ) {
				if ( ! empty( $catalog[ $id ]['login'] ) ) {
					$names[] = $copy[ 'short_' . $id ] ?? $catalog[ $id ]['name'];
				}
			}
			// Only Internal Linking and/or the Auditor left: no intro, rows only.
			return empty( $names ) ? '' : sprintf( $copy['login_line'], self::join_names( $names ) );
		}
		return $copy['guest_line'];
	}

	/**
	 * Pure visibility rule.
	 *
	 * Returns 'visible' after a recorded success, 'await' when everything but a
	 * success is in place (markup is printed hidden and only revealed by a
	 * successful generation in this page view), or '' for no output at all.
	 *
	 * @param array<string,mixed> $state on_screen, can_manage, has_success, dismissed, candidates.
	 */
	public static function render_mode( array $state ): string {
		if ( empty( $state['on_screen'] ) || empty( $state['can_manage'] ) || ! empty( $state['dismissed'] ) || empty( $state['candidates'] ) ) {
			return '';
		}
		return ! empty( $state['has_success'] ) ? 'visible' : 'await';
	}

	/**
	 * Whether the panel is visible on page load.
	 *
	 * @param array<string,mixed> $state See render_mode().
	 */
	public static function should_render( array $state ): bool {
		return 'visible' === self::render_mode( $state );
	}

	/**
	 * Build the panel view model (no WordPress output).
	 *
	 * @param string[] $candidate_ids Suggested ids.
	 * @param bool     $connected     Connected to an Oppti account.
	 * @param bool     $can_install   User can install plugins.
	 * @return array<string,mixed>
	 */
	public static function view_model( array $candidate_ids, bool $connected, bool $can_install ): array {
		$catalog = self::catalog();
		$copy    = self::copy();
		$items   = array();
		foreach ( $candidate_ids as $id ) {
			if ( ! isset( $catalog[ $id ] ) ) {
				continue;
			}
			$link   = self::link_for( $id, $can_install );
			$kind   = $catalog[ $id ]['kind'];
			$helper = '';
			if ( 'credit' === $kind ) {
				$helper = $copy['helper_credit'];
			} elseif ( 'free' === $kind ) {
				$helper = $copy['helper_free'];
			}
			$items[] = array(
				'id'     => $id,
				'name'   => $catalog[ $id ]['name'],
				'desc'   => $copy[ 'desc_' . $id ] ?? '',
				'helper' => $helper,
				'url'    => $link['url'],
				'target' => $link['target'],
				'cta'    => $copy[ 'cta_' . $id ] ?? '',
				'suffix' => 'wporg' === $link['target'] ? $copy['wporg_suffix'] : '',
			);
		}
		return array(
			'heading'       => $copy['heading'],
			'intro'         => self::intro_line( $connected, $candidate_ids ),
			'connected'     => $connected,
			'items'         => $items,
			'dismiss'       => $copy['dismiss'],
			'dismiss_label' => $copy['dismiss_label'],
		);
	}

	/**
	 * Whether the current user may see the panel.
	 */
	private static function user_can_manage(): bool {
		return current_user_can( 'manage_bbbbai_text' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Whether the current request is this plugin's Dashboard screen.
	 */
	public static function is_panel_screen(): bool {
		if ( ! is_admin() ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page routing check.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		return self::SCREEN_PAGE === $page;
	}

	/**
	 * Whether this site has a successful generation on record.
	 *
	 * @param bool $dashboard_signal The dashboard's own "has ever generated" flag.
	 */
	public static function has_success( bool $dashboard_signal = false ): bool {
		return $dashboard_signal || (bool) get_option( 'bbai_first_success_at' );
	}

	/**
	 * Whether the user dismissed the panel.
	 *
	 * @param int $user_id User id (0 = current).
	 */
	public static function is_dismissed( int $user_id = 0 ): bool {
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();
		return $user_id <= 0 || (bool) get_user_meta( $user_id, self::DISMISS_META, true );
	}

	/**
	 * Installed / active OpptiAI ids on this site, checked locally.
	 *
	 * @return array{installed:string[],active:string[]}
	 */
	public static function local_state(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = function_exists( 'get_plugins' ) ? get_plugins() : array();
		return array(
			'installed' => self::installed_ids( is_array( $plugins ) ? $plugins : array() ),
			'active'    => self::active_ids( self::active_plugin_files() ),
		);
	}

	/**
	 * Active plugin basenames, including network-active ones.
	 *
	 * @return string[]
	 */
	public static function active_plugin_files(): array {
		$files = get_option( 'active_plugins', array() );
		$files = is_array( $files ) ? $files : array();
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			$network = get_site_option( 'active_sitewide_plugins', array() );
			if ( is_array( $network ) ) {
				$files = array_merge( $files, array_keys( $network ) );
			}
		}
		return array_values( array_filter( array_map( 'strval', $files ) ) );
	}

	/**
	 * Comma list of other OpptiAI plugins active right now (for plugin_activated).
	 */
	public static function sibling_list(): string {
		$ids = array_values( array_diff( self::active_ids( self::active_plugin_files() ), array( self::FROM ) ) );
		sort( $ids );
		return implode( ',', $ids );
	}

	/**
	 * Context for the current request, or null when the panel must not render.
	 *
	 * @param bool $connected        Site connected to an Oppti account.
	 * @param bool $dashboard_signal Dashboard "has ever generated" flag.
	 * @return array<string,mixed>|null
	 */
	public static function current_view( bool $connected, bool $dashboard_signal = false ): ?array {
		$on_screen  = self::is_panel_screen();
		$can_manage = self::user_can_manage();
		if ( ! $on_screen || ! $can_manage || self::is_dismissed() ) {
			return null;
		}
		$local      = self::local_state();
		$candidates = self::candidate_ids( $local['installed'], $local['active'] );
		$state      = array(
			'on_screen'   => $on_screen,
			'can_manage'  => $can_manage,
			'has_success' => self::has_success( $dashboard_signal ),
			'dismissed'   => false,
			'candidates'  => $candidates,
		);
		$mode       = self::render_mode( $state );
		if ( '' === $mode ) {
			return null;
		}
		$view         = self::view_model( $candidates, $connected, current_user_can( 'install_plugins' ) );
		$view['mode'] = $mode;
		return $view;
	}

	/**
	 * Render the panel on the Dashboard screen.
	 *
	 * @param bool $connected        Site connected to an Oppti account.
	 * @param bool $dashboard_signal Dashboard "has ever generated" flag.
	 */
	public static function render( bool $connected, bool $dashboard_signal = false ): void {
		$view = self::current_view( $connected, $dashboard_signal );
		if ( null === $view ) {
			return;
		}
		self::print_panel( $view );
	}

	/**
	 * Print panel markup for a view model.
	 *
	 * @param array<string,mixed> $view View model.
	 */
	public static function print_panel( array $view ): void {
		?>
		<section class="bbai-more-from-oppti" data-bbai-mfo="1" data-from="<?php echo esc_attr( self::FROM ); ?>" data-account-state="<?php echo esc_attr( ! empty( $view['connected'] ) ? 'connected' : 'guest' ); ?>" aria-labelledby="bbai-mfo-heading"
		<?php
		if ( isset( $view['mode'] ) && 'await' === $view['mode'] ) {
			echo ' hidden data-await-success="1"';
		}
		?>
		>
			<div class="bbai-more-from-oppti__head">
				<h3 id="bbai-mfo-heading" class="bbai-more-from-oppti__title"><?php echo esc_html( (string) $view['heading'] ); ?></h3>
				<form class="bbai-more-from-oppti__dismiss-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-bbai-mfo-dismiss-form="1">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::DISMISS_ACTION ); ?>" />
					<?php wp_nonce_field( self::DISMISS_ACTION, '_wpnonce', true, true ); ?>
					<button type="submit" class="button-link bbai-more-from-oppti__dismiss" data-bbai-mfo-dismiss="1" aria-label="<?php echo esc_attr( (string) $view['dismiss_label'] ); ?>"><?php echo esc_html( (string) $view['dismiss'] ); ?></button>
				</form>
			</div>
			<?php if ( '' !== (string) $view['intro'] ) : ?>
				<p class="bbai-more-from-oppti__intro"><?php echo esc_html( (string) $view['intro'] ); ?></p>
			<?php endif; ?>
			<ul class="bbai-more-from-oppti__list">
				<?php foreach ( (array) $view['items'] as $item ) : ?>
					<li class="bbai-more-from-oppti__item">
						<span class="bbai-more-from-oppti__desc"><?php echo esc_html( (string) $item['desc'] ); ?></span>
						<a
							class="bbai-more-from-oppti__cta"
							href="<?php echo esc_url( (string) $item['url'] ); ?>"
							data-bbai-mfo-link="1"
							data-to="<?php echo esc_attr( (string) $item['id'] ); ?>"
							data-link-target="<?php echo esc_attr( (string) $item['target'] ); ?>"
							<?php if ( 'wporg' === $item['target'] ) : ?>
								target="_blank" rel="noopener noreferrer"
							<?php endif; ?>
						><?php echo esc_html( (string) $item['cta'] ); ?>
							<?php if ( '' !== $item['suffix'] ) : ?>
								<span class="screen-reader-text"><?php echo esc_html( (string) $item['suffix'] ); ?></span>
							<?php endif; ?>
						</a>
						<?php if ( '' !== $item['helper'] ) : ?>
							<span class="bbai-more-from-oppti__helper"><?php echo esc_html( (string) $item['helper'] ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php
	}

	/**
	 * Enqueue the small panel script and style on the Dashboard screen only.
	 */
	public static function enqueue(): void {
		if ( ! self::is_panel_screen() || ! self::user_can_manage() || self::is_dismissed() ) {
			return;
		}
		$ver = defined( 'BEEPBEEP_AI_VERSION' ) ? (string) BEEPBEEP_AI_VERSION : false;
		wp_enqueue_style( 'bbai-more-from-oppti', BEEPBEEP_AI_PLUGIN_URL . 'assets/css/bbai-more-from-oppti.css', array(), $ver );
		wp_enqueue_script( 'bbai-more-from-oppti', BEEPBEEP_AI_PLUGIN_URL . 'assets/js/bbai-more-from-oppti.js', array(), $ver, true );
		wp_localize_script(
			'bbai-more-from-oppti',
			'BBAI_MORE_FROM_OPPTI',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'dismissAction' => self::DISMISS_ACTION,
				'dismissNonce'  => wp_create_nonce( self::DISMISS_ACTION ),
				'telemetry'     => array(
					'enabled' => class_exists( BBAI_Telemetry::class ) && BBAI_Telemetry::has_telemetry_consent(),
					'action'  => 'beepbeepai_telemetry',
					'nonce'   => wp_create_nonce( 'beepbeepai_nonce' ),
				),
			)
		);
	}

	/**
	 * Dismiss for good (admin-post without JS, admin-ajax with JS).
	 */
	public static function handle_dismiss(): void {
		$is_ajax = function_exists( 'wp_doing_ajax' ) && wp_doing_ajax();
		if ( ! self::user_can_manage() ) {
			if ( $is_ajax ) {
				wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
			}
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'beepbeep-ai-alt-text-generator' ) );
		}
		if ( $is_ajax ) {
			check_ajax_referer( self::DISMISS_ACTION, 'nonce' );
		} else {
			check_admin_referer( self::DISMISS_ACTION );
		}
		update_user_meta( get_current_user_id(), self::DISMISS_META, time() );
		if ( $is_ajax ) {
			wp_send_json_success( array( 'dismissed' => true ) );
		}
		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url( 'admin.php?page=' . self::SCREEN_PAGE ) );
		exit;
	}
}
