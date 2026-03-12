<?php
/**
 * Shared admin layout helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'bbai_render_layout' ) ) {
	/**
	 * Render admin page content inside the shared layout shell.
	 *
	 * @param callable $content_callback Callback that outputs the page body.
	 */
	function bbai_render_layout( callable $content_callback ) {
		?>
		<div class="bbai-page">
			<div class="bbai-page-inner">
				<?php call_user_func( $content_callback ); ?>
			</div>
		</div>
		<?php
	}
}

if ( ! function_exists( 'bbai_render_layout_template' ) ) {
	/**
	 * Render a PHP partial inside the shared layout shell while preserving scope.
	 *
	 * @param string $partial          Absolute path to the partial.
	 * @param array  $context          Variables to extract for the partial.
	 * @param string $fallback_message Message shown when the partial is missing.
	 * @param object $scope            Optional object scope used when the partial relies on $this.
	 */
	function bbai_render_layout_template( $partial, array $context = [], $fallback_message = '', $scope = null ) {
		ob_start();

		$renderer = function () use ( $partial, $context, $fallback_message ) {
			if ( isset( $context['this'] ) ) {
				unset( $context['this'] );
			}

			extract( $context, EXTR_SKIP );

			if ( is_string( $partial ) && $partial !== '' && file_exists( $partial ) ) {
				include $partial;
			} elseif ( $fallback_message !== '' ) {
				echo esc_html( $fallback_message );
			}
		};

		if ( is_object( $scope ) ) {
			$renderer = \Closure::bind( $renderer, $scope, $scope );
		}

		$renderer();

		$content = (string) ob_get_clean();

		bbai_render_layout(
			static function () use ( $content ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Partial markup is escaped when rendered.
				echo $content;
			}
		);
	}
}
