<?php
/**
 * Page Renderer Class
 *
 * Provides a master template wrapper for admin pages.
 *
 * @package Optti\Admin
 */

namespace Optti\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Page_Renderer
 *
 * Handles page rendering with template wrapper.
 */
class Page_Renderer {

	/**
	 * Render a page with template wrapper.
	 *
	 * @param string   $page_slug Page slug.
	 * @param string   $page_title Page title.
	 * @param callable $content_callback Content callback.
	 * @return void
	 */
	public static function render( $page_slug, $page_title, $content_callback ) {
		// Get current page.
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 'optti';

		// Get menu items.
		$menu_items = self::get_menu_items();

		// Render header.
		self::render_header( $page_title, $menu_items, $current_page );

		// Render content.
		if ( is_callable( $content_callback ) ) {
			call_user_func( $content_callback );
		}

		// Render footer.
		self::render_footer();
	}

	/**
	 * Get menu items.
	 *
	 * @return array Menu items.
	 */
	protected static function get_menu_items() {
		return array(
			'optti'           => array(
				'title' => __( 'Dashboard', 'beepbeep-ai-alt-text-generator' ),
				'url'   => admin_url( 'admin.php?page=optti' ),
				'icon'  => 'dashicons-dashboard',
			),
			'optti-settings'  => array(
				'title' => __( 'Settings', 'beepbeep-ai-alt-text-generator' ),
				'url'   => admin_url( 'admin.php?page=optti-settings' ),
				'icon'  => 'dashicons-admin-settings',
			),
			'optti-license'   => array(
				'title' => __( 'License', 'beepbeep-ai-alt-text-generator' ),
				'url'   => admin_url( 'admin.php?page=optti-license' ),
				'icon'  => 'dashicons-admin-network',
			),
			'optti-analytics' => array(
				'title' => __( 'Analytics', 'beepbeep-ai-alt-text-generator' ),
				'url'   => admin_url( 'admin.php?page=optti-analytics' ),
				'icon'  => 'dashicons-chart-bar',
			),
		);
	}

	/**
	 * Render page header.
	 *
	 * @param string $page_title Page title.
	 * @param array  $menu_items Menu items.
	 * @param string $current_page Current page slug.
	 * @return void
	 */
	protected static function render_header( $page_title, $menu_items, $current_page ) {
		?>
		<div class="wrap optti-admin-wrap">
			<div class="optti-admin-header">
				<h1 class="optti-admin-title">
					<?php echo esc_html( $page_title ); ?>
				</h1>
			</div>

			<nav class="optti-admin-nav">
				<ul class="optti-admin-nav-list">
					<?php foreach ( $menu_items as $slug => $item ) : ?>
						<li class="optti-admin-nav-item <?php echo esc_attr( $slug === $current_page ? 'active' : '' ); ?>">
							<a href="<?php echo esc_url( $item['url'] ); ?>" class="optti-admin-nav-link">
								<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>"></span>
								<?php echo esc_html( $item['title'] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>

			<div class="optti-admin-content">
		<?php
	}

	/**
	 * Render page footer.
	 *
	 * @return void
	 */
	protected static function render_footer() {
		?>
			</div><!-- .optti-admin-content -->
		</div><!-- .optti-admin-wrap -->
		<?php
	}
}

