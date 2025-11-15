<?php
/**
 * OpptiAI Framework - UI Components
 *
 * Provides reusable HTML component builders for admin pages
 *
 * @package OpptiAI\Framework\UI
 */

namespace OpptiAI\Framework\UI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use OpptiAI\Framework\Helpers\Escaper;

class Components {

	/**
	 * Render a card container
	 *
	 * @param string $title   Card title
	 * @param string $content Card content (HTML)
	 * @param array  $args    Optional arguments (class, id, footer)
	 * @return string Card HTML
	 */
	public static function card( $title, $content, $args = array() ) {
		$defaults = array(
			'class'  => '',
			'id'     => '',
			'footer' => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		$class = 'opptiai-card ' . esc_attr( $args['class'] );
		$id    = $args['id'] ? ' id="' . esc_attr( $args['id'] ) . '"' : '';

		ob_start();
		?>
		<div class="<?php echo esc_attr( $class ); ?>"<?php echo $id; ?>>
			<?php if ( $title ) : ?>
				<div class="opptiai-card-header">
					<h3><?php echo esc_html( $title ); ?></h3>
				</div>
			<?php endif; ?>
			<div class="opptiai-card-body">
				<?php echo wp_kses_post( $content ); ?>
			</div>
			<?php if ( $args['footer'] ) : ?>
				<div class="opptiai-card-footer">
					<?php echo wp_kses_post( $args['footer'] ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a stat box
	 *
	 * @param string $label Stat label
	 * @param string $value Stat value
	 * @param array  $args  Optional arguments (class, icon, trend)
	 * @return string Stat box HTML
	 */
	public static function stat_box( $label, $value, $args = array() ) {
		$defaults = array(
			'class' => '',
			'icon'  => '',
			'trend' => '', // 'up', 'down', or empty
		);
		$args     = wp_parse_args( $args, $defaults );

		$class = 'opptiai-stat-box ' . esc_attr( $args['class'] );

		ob_start();
		?>
		<div class="<?php echo esc_attr( $class ); ?>">
			<?php if ( $args['icon'] ) : ?>
				<div class="opptiai-stat-icon">
					<?php echo wp_kses_post( $args['icon'] ); ?>
				</div>
			<?php endif; ?>
			<div class="opptiai-stat-content">
				<div class="opptiai-stat-label"><?php echo esc_html( $label ); ?></div>
				<div class="opptiai-stat-value"><?php echo esc_html( $value ); ?></div>
			</div>
			<?php if ( $args['trend'] ) : ?>
				<div class="opptiai-stat-trend opptiai-stat-trend-<?php echo esc_attr( $args['trend'] ); ?>">
					<?php echo 'up' === $args['trend'] ? '↑' : '↓'; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a button
	 *
	 * @param string $text Button text
	 * @param array  $args Optional arguments (class, type, id, onclick, href, disabled)
	 * @return string Button HTML
	 */
	public static function button( $text, $args = array() ) {
		$defaults = array(
			'class'    => '',
			'type'     => 'button', // 'button', 'submit', 'link'
			'id'       => '',
			'onclick'  => '',
			'href'     => '#',
			'disabled' => false,
			'variant'  => 'primary', // 'primary', 'secondary', 'danger', 'success'
		);
		$args     = wp_parse_args( $args, $defaults );

		$class    = 'opptiai-button opptiai-button-' . esc_attr( $args['variant'] ) . ' ' . esc_attr( $args['class'] );
		$disabled = $args['disabled'] ? ' disabled' : '';

		if ( 'link' === $args['type'] ) {
			$id      = $args['id'] ? ' id="' . esc_attr( $args['id'] ) . '"' : '';
			$onclick = $args['onclick'] ? ' onclick="' . esc_attr( $args['onclick'] ) . '"' : '';
			return sprintf(
				'<a href="%s" class="%s"%s%s%s>%s</a>',
				esc_url( $args['href'] ),
				esc_attr( $class ),
				$id,
				$onclick,
				$disabled,
				esc_html( $text )
			);
		}

		$id      = $args['id'] ? ' id="' . esc_attr( $args['id'] ) . '"' : '';
		$onclick = $args['onclick'] ? ' onclick="' . esc_attr( $args['onclick'] ) . '"' : '';
		$type    = $args['type'];

		return sprintf(
			'<button type="%s" class="%s"%s%s%s>%s</button>',
			esc_attr( $type ),
			esc_attr( $class ),
			$id,
			$onclick,
			$disabled ? ' disabled' : '',
			esc_html( $text )
		);
	}

	/**
	 * Render a table
	 *
	 * @param array $headers Table headers
	 * @param array $rows    Table rows (array of arrays)
	 * @param array $args    Optional arguments (class, id)
	 * @return string Table HTML
	 */
	public static function table( $headers, $rows, $args = array() ) {
		$defaults = array(
			'class' => '',
			'id'    => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		$class = 'opptiai-table ' . esc_attr( $args['class'] );
		$id    = $args['id'] ? ' id="' . esc_attr( $args['id'] ) . '"' : '';

		ob_start();
		?>
		<table class="<?php echo esc_attr( $class ); ?>"<?php echo $id; ?>>
			<thead>
				<tr>
					<?php foreach ( $headers as $header ) : ?>
						<th><?php echo esc_html( $header ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<?php foreach ( $row as $cell ) : ?>
							<td><?php echo wp_kses_post( $cell ); ?></td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a notice/alert
	 *
	 * @param string $message Notice message
	 * @param array  $args    Optional arguments (type, dismissible, class)
	 * @return string Notice HTML
	 */
	public static function notice( $message, $args = array() ) {
		$defaults = array(
			'type'        => 'info', // 'success', 'warning', 'error', 'info'
			'dismissible' => false,
			'class'       => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		$class = 'opptiai-notice opptiai-notice-' . esc_attr( $args['type'] ) . ' ' . esc_attr( $args['class'] );
		if ( $args['dismissible'] ) {
			$class .= ' opptiai-notice-dismissible';
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( $class ); ?>">
			<div class="opptiai-notice-content">
				<?php echo wp_kses_post( $message ); ?>
			</div>
			<?php if ( $args['dismissible'] ) : ?>
				<button type="button" class="opptiai-notice-dismiss" aria-label="<?php esc_attr_e( 'Dismiss', 'wp-alt-text-plugin' ); ?>">
					<span aria-hidden="true">&times;</span>
				</button>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a modal
	 *
	 * @param string $id      Modal ID
	 * @param string $title   Modal title
	 * @param string $content Modal content (HTML)
	 * @param array  $args    Optional arguments (footer, class, size)
	 * @return string Modal HTML
	 */
	public static function modal( $id, $title, $content, $args = array() ) {
		$defaults = array(
			'footer' => '',
			'class'  => '',
			'size'   => 'medium', // 'small', 'medium', 'large'
		);
		$args     = wp_parse_args( $args, $defaults );

		$class = 'opptiai-modal opptiai-modal-' . esc_attr( $args['size'] ) . ' ' . esc_attr( $args['class'] );

		ob_start();
		?>
		<div id="<?php echo esc_attr( $id ); ?>" class="<?php echo esc_attr( $class ); ?>" style="display: none;">
			<div class="opptiai-modal-overlay"></div>
			<div class="opptiai-modal-container">
				<div class="opptiai-modal-header">
					<h2><?php echo esc_html( $title ); ?></h2>
					<button type="button" class="opptiai-modal-close" aria-label="<?php esc_attr_e( 'Close', 'wp-alt-text-plugin' ); ?>">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="opptiai-modal-body">
					<?php echo wp_kses_post( $content ); ?>
				</div>
				<?php if ( $args['footer'] ) : ?>
					<div class="opptiai-modal-footer">
						<?php echo wp_kses_post( $args['footer'] ); ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a progress bar
	 *
	 * @param int   $percentage Progress percentage (0-100)
	 * @param array $args       Optional arguments (class, label)
	 * @return string Progress bar HTML
	 */
	public static function progress_bar( $percentage, $args = array() ) {
		$defaults = array(
			'class' => '',
			'label' => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		$percentage = max( 0, min( 100, intval( $percentage ) ) );
		$class      = 'opptiai-progress-bar ' . esc_attr( $args['class'] );

		ob_start();
		?>
		<div class="<?php echo esc_attr( $class ); ?>">
			<?php if ( $args['label'] ) : ?>
				<div class="opptiai-progress-label"><?php echo esc_html( $args['label'] ); ?></div>
			<?php endif; ?>
			<div class="opptiai-progress-track">
				<div class="opptiai-progress-fill" style="width: <?php echo esc_attr( $percentage ); ?>%;" role="progressbar" aria-valuenow="<?php echo esc_attr( $percentage ); ?>" aria-valuemin="0" aria-valuemax="100">
					<span class="opptiai-progress-text"><?php echo esc_html( $percentage ); ?>%</span>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render tabs navigation
	 *
	 * @param array $tabs        Array of tabs (id => label)
	 * @param string $active_tab Active tab ID
	 * @param array  $args       Optional arguments (class)
	 * @return string Tabs HTML
	 */
	public static function tabs( $tabs, $active_tab, $args = array() ) {
		$defaults = array(
			'class' => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		$class = 'opptiai-tabs ' . esc_attr( $args['class'] );

		ob_start();
		?>
		<nav class="<?php echo esc_attr( $class ); ?>">
			<?php foreach ( $tabs as $id => $label ) : ?>
				<a href="#<?php echo esc_attr( $id ); ?>" class="opptiai-tab<?php echo $active_tab === $id ? ' opptiai-tab-active' : ''; ?>" data-tab="<?php echo esc_attr( $id ); ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a tab panel
	 *
	 * @param string $id      Tab panel ID
	 * @param string $content Tab panel content (HTML)
	 * @param bool   $active  Whether tab is active
	 * @return string Tab panel HTML
	 */
	public static function tab_panel( $id, $content, $active = false ) {
		$class = 'opptiai-tab-panel';
		if ( ! $active ) {
			$class .= ' opptiai-tab-panel-hidden';
		}

		ob_start();
		?>
		<div id="<?php echo esc_attr( $id ); ?>" class="<?php echo esc_attr( $class ); ?>">
			<?php echo wp_kses_post( $content ); ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a form field
	 *
	 * @param string $type  Field type (text, email, password, textarea, select, checkbox)
	 * @param string $name  Field name
	 * @param mixed  $value Field value
	 * @param array  $args  Optional arguments (label, placeholder, required, options, class)
	 * @return string Form field HTML
	 */
	public static function form_field( $type, $name, $value, $args = array() ) {
		$defaults = array(
			'label'       => '',
			'placeholder' => '',
			'required'    => false,
			'options'     => array(), // For select fields
			'class'       => '',
			'id'          => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		$id       = $args['id'] ? $args['id'] : 'field-' . sanitize_key( $name );
		$class    = 'opptiai-form-field ' . esc_attr( $args['class'] );
		$required = $args['required'] ? ' required' : '';

		ob_start();
		?>
		<div class="<?php echo esc_attr( $class ); ?>">
			<?php if ( $args['label'] ) : ?>
				<label for="<?php echo esc_attr( $id ); ?>" class="opptiai-form-label">
					<?php echo esc_html( $args['label'] ); ?>
					<?php if ( $args['required'] ) : ?>
						<span class="opptiai-required">*</span>
					<?php endif; ?>
				</label>
			<?php endif; ?>
			<?php
			switch ( $type ) {
				case 'textarea':
					?>
					<textarea
						name="<?php echo esc_attr( $name ); ?>"
						id="<?php echo esc_attr( $id ); ?>"
						class="opptiai-form-control"
						placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
						<?php echo $required; ?>
					><?php echo esc_textarea( $value ); ?></textarea>
					<?php
					break;

				case 'select':
					?>
					<select
						name="<?php echo esc_attr( $name ); ?>"
						id="<?php echo esc_attr( $id ); ?>"
						class="opptiai-form-control"
						<?php echo $required; ?>
					>
						<?php foreach ( $args['options'] as $option_value => $option_label ) : ?>
							<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $value, $option_value ); ?>>
								<?php echo esc_html( $option_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php
					break;

				case 'checkbox':
					?>
					<label class="opptiai-checkbox-label">
						<input
							type="checkbox"
							name="<?php echo esc_attr( $name ); ?>"
							id="<?php echo esc_attr( $id ); ?>"
							class="opptiai-form-checkbox"
							value="1"
							<?php checked( $value, 1 ); ?>
							<?php echo $required; ?>
						/>
						<span><?php echo esc_html( $args['placeholder'] ); ?></span>
					</label>
					<?php
					break;

				default:
					?>
					<input
						type="<?php echo esc_attr( $type ); ?>"
						name="<?php echo esc_attr( $name ); ?>"
						id="<?php echo esc_attr( $id ); ?>"
						class="opptiai-form-control"
						value="<?php echo esc_attr( $value ); ?>"
						placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
						<?php echo $required; ?>
					/>
					<?php
					break;
			}
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a list (ul or ol)
	 *
	 * @param array  $items List items
	 * @param array  $args  Optional arguments (type, class)
	 * @return string List HTML
	 */
	public static function list_items( $items, $args = array() ) {
		$defaults = array(
			'type'  => 'ul', // 'ul' or 'ol'
			'class' => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		$class = 'opptiai-list ' . esc_attr( $args['class'] );
		$tag   = 'ul' === $args['type'] ? 'ul' : 'ol';

		ob_start();
		?>
		<<?php echo $tag; ?> class="<?php echo esc_attr( $class ); ?>">
			<?php foreach ( $items as $item ) : ?>
				<li><?php echo wp_kses_post( $item ); ?></li>
			<?php endforeach; ?>
		</<?php echo $tag; ?>>
		<?php
		return ob_get_clean();
	}
}
