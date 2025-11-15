<?php
/**
 * OpptiAI Framework - Layout Handler
 *
 * Provides consistent page layout for all admin pages
 *
 * @package OpptiAI\Framework\UI
 */

namespace OpptiAI\Framework\UI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Layout {

	/**
	 * Render page header
	 *
	 * @param string $title Page title
	 * @param array  $args  Optional arguments (subtitle, breadcrumbs)
	 * @return void
	 */
	public static function header( $title, $args = array() ) {
		$defaults = array(
			'subtitle'    => '',
			'breadcrumbs' => array(),
		);
		$args     = wp_parse_args( $args, $defaults );

		?>
		<div class="opptiai-page-header">
			<?php if ( ! empty( $args['breadcrumbs'] ) ) : ?>
				<nav class="opptiai-breadcrumbs">
					<?php foreach ( $args['breadcrumbs'] as $crumb ) : ?>
						<?php if ( isset( $crumb['url'] ) ) : ?>
							<a href="<?php echo esc_url( $crumb['url'] ); ?>"><?php echo esc_html( $crumb['label'] ); ?></a>
							<span class="opptiai-breadcrumb-separator">/</span>
						<?php else : ?>
							<span><?php echo esc_html( $crumb['label'] ); ?></span>
						<?php endif; ?>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>
			<h1 class="opptiai-page-title"><?php echo esc_html( $title ); ?></h1>
			<?php if ( $args['subtitle'] ) : ?>
				<p class="opptiai-page-subtitle"><?php echo esc_html( $args['subtitle'] ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render sidebar navigation
	 *
	 * @param array $items Menu items (array of arrays with 'label', 'url', 'active', 'icon')
	 * @return void
	 */
	public static function sidebar( $items = array() ) {
		if ( empty( $items ) ) {
			return;
		}

		?>
		<aside class="opptiai-sidebar">
			<nav class="opptiai-sidebar-nav">
				<?php foreach ( $items as $item ) : ?>
					<?php
					$class = 'opptiai-sidebar-item';
					if ( ! empty( $item['active'] ) ) {
						$class .= ' opptiai-sidebar-item-active';
					}
					?>
					<a href="<?php echo esc_url( $item['url'] ); ?>" class="<?php echo esc_attr( $class ); ?>">
						<?php if ( ! empty( $item['icon'] ) ) : ?>
							<span class="opptiai-sidebar-icon"><?php echo wp_kses_post( $item['icon'] ); ?></span>
						<?php endif; ?>
						<span class="opptiai-sidebar-label"><?php echo esc_html( $item['label'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</nav>
		</aside>
		<?php
	}

	/**
	 * Render page footer
	 *
	 * @param array $args Optional arguments (text, links)
	 * @return void
	 */
	public static function footer( $args = array() ) {
		$defaults = array(
			'text'  => '',
			'links' => array(),
		);
		$args     = wp_parse_args( $args, $defaults );

		?>
		<footer class="opptiai-page-footer">
			<?php if ( $args['text'] ) : ?>
				<div class="opptiai-footer-text"><?php echo esc_html( $args['text'] ); ?></div>
			<?php endif; ?>
			<?php if ( ! empty( $args['links'] ) ) : ?>
				<div class="opptiai-footer-links">
					<?php foreach ( $args['links'] as $link ) : ?>
						<a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener noreferrer">
							<?php echo esc_html( $link['label'] ); ?>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</footer>
		<?php
	}

	/**
	 * Start page wrapper
	 *
	 * @param array $args Optional arguments (class)
	 * @return void
	 */
	public static function start_wrapper( $args = array() ) {
		$defaults = array(
			'class' => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		$class = 'opptiai-page-wrapper ' . esc_attr( $args['class'] );
		?>
		<div class="wrap opptiai-admin-wrap">
			<div class="<?php echo esc_attr( $class ); ?>">
		<?php
	}

	/**
	 * End page wrapper
	 *
	 * @return void
	 */
	public static function end_wrapper() {
		?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a complete admin page with layout
	 *
	 * @param string   $title    Page title
	 * @param callable $callback Content callback function
	 * @param array    $args     Optional arguments (sidebar_items, footer, breadcrumbs)
	 * @return void
	 */
	public static function render_page( $title, $callback, $args = array() ) {
		$defaults = array(
			'sidebar_items' => array(),
			'footer'        => array(),
			'breadcrumbs'   => array(),
			'subtitle'      => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		self::start_wrapper();

		if ( ! empty( $args['sidebar_items'] ) ) {
			self::sidebar( $args['sidebar_items'] );
		}

		?>
		<div class="opptiai-page-content">
			<?php
			self::header(
				$title,
				array(
					'subtitle'    => $args['subtitle'],
					'breadcrumbs' => $args['breadcrumbs'],
				)
			);

			if ( is_callable( $callback ) ) {
				call_user_func( $callback );
			}

			if ( ! empty( $args['footer'] ) ) {
				self::footer( $args['footer'] );
			}
			?>
		</div>
		<?php

		self::end_wrapper();
	}
}
