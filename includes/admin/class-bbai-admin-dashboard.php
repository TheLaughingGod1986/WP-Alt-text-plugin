<?php
/**
 * Admin dashboard UI for the BeepBeep AI plugin.
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Dashboard {
	const MENU_SLUG   = 'beepbeep-ai';
	const PARENT_SLUG = 'bbai';

	/**
	 * @var string
	 */
	private $page_hook = '';

	/**
	 * Register the Dashboard submenu page.
	 */
	public function register_menu() {
		$this->page_hook = add_submenu_page(
			self::PARENT_SLUG,
			__( 'Dashboard', 'opptiai-alt' ),
			__( 'Dashboard', 'opptiai-alt' ),
			$this->get_capability(),
			self::MENU_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Enqueue dashboard assets only on the dashboard page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( $hook !== $this->page_hook ) {
			return;
		}

		$is_authenticated = $this->is_authenticated();
		$css_rel          = $is_authenticated ? 'assets/admin/dashboard.css' : 'assets/admin/logged-out.css';
		$css_handle       = $is_authenticated ? 'bbai-admin-dashboard' : 'bbai-logged-out';
		$js_rel           = 'assets/admin/dashboard.js';

		$css_path = BEEPBEEP_AI_PLUGIN_DIR . $css_rel;
		$js_path  = BEEPBEEP_AI_PLUGIN_DIR . $js_rel;

		wp_enqueue_style(
			$css_handle,
			BEEPBEEP_AI_PLUGIN_URL . $css_rel,
			[],
			file_exists( $css_path ) ? filemtime( $css_path ) : BEEPBEEP_AI_VERSION
		);

		if ( $is_authenticated ) {
			wp_enqueue_script(
				'bbai-admin-dashboard',
				BEEPBEEP_AI_PLUGIN_URL . $js_rel,
				[ 'jquery' ],
				file_exists( $js_path ) ? filemtime( $js_path ) : BEEPBEEP_AI_VERSION,
				true
			);

			wp_localize_script(
				'bbai-admin-dashboard',
				'BBAI_DASHBOARD',
				[
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'bbai_dashboard_actions' ),
					'actions' => [
						'generate_missing' => 'bbai_generate_missing',
						'reoptimize_all'   => 'bbai_reoptimize_all',
					],
					'strings' => [
						'working' => __( 'Request submitted. This may take a moment.', 'opptiai-alt' ),
						'success' => __( 'Completed.', 'opptiai-alt' ),
						'error'   => __( 'Something went wrong. Please try again.', 'opptiai-alt' ),
						'close'   => __( 'Close', 'opptiai-alt' ),
					],
				]
			);
		}
	}

	/**
	 * Render the dashboard page.
	 */
	public function render_page() {
		if ( ! current_user_can( $this->get_capability() ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to access this page.', 'opptiai-alt' ) . '</p></div>';
			return;
		}

		if ( ! $this->is_authenticated() ) {
			echo '<div class="wrap bbai-dashboard">';
			$this->render_logged_out_onboarding();
			echo '</div>';
			return;
		}

		$data       = $this->get_dashboard_data();
		$month      = $data['month_label'] ? $data['month_label'] : date_i18n( 'F' );
		$used       = (int) $data['used'];
		$limit      = (int) $data['limit'];
		$remaining  = (int) $data['generations_remaining'];
		$percent    = min( 100, max( 0, (int) $data['percent'] ) );
		$reset_date = $data['reset_date'];

		$radius        = 46;
		$circumference = 2 * pi() * $radius;
		?>
		<div class="wrap bbai-dashboard">
			<div class="bbai-dashboard__notices" id="bbai-dashboard-notices" aria-live="polite"></div>

			<div class="bbai-dashboard__grid bbai-dashboard__grid--top">
				<section class="bbai-card bbai-progress-card">
					<div class="bbai-card__header">
						<h1 class="bbai-card__title"><?php esc_html_e( 'Alt Text Progress This Month', 'opptiai-alt' ); ?></h1>
						<p class="bbai-card__subtitle">
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: used images count, 2: plan limit */
									__( '%1$s of %2$s images used this month', 'opptiai-alt' ),
									number_format_i18n( $used ),
									number_format_i18n( $limit )
								)
							);
							?>
						</p>
					</div>

					<div class="bbai-progress-card__body">
						<div class="bbai-progress-ring" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( $percent ); ?>" aria-label="<?php esc_attr_e( 'Alt text progress', 'opptiai-alt' ); ?>">
							<svg width="120" height="120" viewBox="0 0 120 120" aria-hidden="true">
								<circle class="bbai-progress-ring__track" cx="60" cy="60" r="<?php echo esc_attr( $radius ); ?>" fill="none" stroke="#e3e7f0" stroke-width="10" />
								<circle
									class="bbai-progress-ring__value"
									cx="60"
									cy="60"
									r="<?php echo esc_attr( $radius ); ?>"
									fill="none"
									stroke="#7da0d6"
									stroke-width="10"
									stroke-linecap="round"
									stroke-dasharray="<?php echo esc_attr( $circumference ); ?>"
									stroke-dashoffset="<?php echo esc_attr( $circumference ); ?>"
									data-progress="<?php echo esc_attr( $percent ); ?>"
									data-circumference="<?php echo esc_attr( $circumference ); ?>"
								/>
							</svg>
							<div class="bbai-progress-ring__label">
								<span class="bbai-progress-ring__value-text"><?php echo esc_html( $percent ); ?>%</span>
								<span class="bbai-progress-ring__caption"><?php esc_html_e( 'Complete', 'opptiai-alt' ); ?></span>
							</div>
						</div>

						<div class="bbai-progress-meta">
							<div class="bbai-progress-bar" role="presentation">
								<span class="bbai-progress-bar__fill" style="width: <?php echo esc_attr( $percent ); ?>%"></span>
							</div>
							<div class="bbai-progress-meta__row">
								<span class="bbai-progress-meta__complete"><?php echo esc_html( $percent ); ?>% <?php esc_html_e( 'Complete', 'opptiai-alt' ); ?></span>
								<label class="bbai-progress-meta__select">
									<span class="screen-reader-text"><?php esc_html_e( 'Progress view', 'opptiai-alt' ); ?></span>
									<select class="bbai-progress-meta__select-input" aria-label="<?php esc_attr_e( 'Progress view', 'opptiai-alt' ); ?>">
										<option><?php esc_html_e( 'Complements', 'opptiai-alt' ); ?></option>
									</select>
								</label>
							</div>
							<p class="bbai-progress-meta__reset"><?php echo esc_html( sprintf(
								/* translators: 1: reset date */
								__( 'Resets %s', 'opptiai-alt' ),
								$reset_date
							) ); ?></p>
						</div>

						<div class="bbai-progress-actions bbai-progress-actions--primary">
							<button type="button" class="button bbai-dashboard-btn bbai-dashboard-btn--primary bbai-dashboard-btn--full" data-bbai-action="generate_missing">
								<span class="bbai-dashboard-btn__text"><?php esc_html_e( 'Generate Missing', 'opptiai-alt' ); ?></span>
								<span class="bbai-dashboard-btn__icon" aria-hidden="true">
									<svg viewBox="0 0 16 16" focusable="false" aria-hidden="true">
										<path d="M6 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
									</svg>
								</span>
								<span class="bbai-dashboard-btn__spinner" aria-hidden="true"></span>
							</button>
						</div>
						<p class="bbai-progress-card__note"><?php esc_html_e( 'Free plan includes 50 images per month. Growth includes 1,000 images per month.', 'opptiai-alt' ); ?></p>
						<div class="bbai-progress-actions bbai-progress-actions--secondary">
							<button type="button" class="button bbai-dashboard-btn bbai-dashboard-btn--secondary bbai-dashboard-btn--pill" data-bbai-action="reoptimize_all">
								<span class="bbai-dashboard-btn__text"><?php esc_html_e( 'Re-optimise All', 'opptiai-alt' ); ?></span>
								<span class="bbai-dashboard-btn__spinner" aria-hidden="true"></span>
							</button>
						</div>
					</div>
				</section>

				<aside class="bbai-card bbai-upgrade-card">
					<div class="bbai-upgrade-card__header">
						<h2 class="bbai-card__title bbai-card__title--light"><?php esc_html_e( 'Upgrade to Growth', 'opptiai-alt' ); ?></h2>
						<p class="bbai-upgrade-card__subtitle"><?php esc_html_e( 'Automate alt text generation and scale image optimisation each month.', 'opptiai-alt' ); ?></p>
						<ul class="bbai-upgrade-card__list bbai-upgrade-card__list--dots">
							<li><?php esc_html_e( '1,000 AI alt texts per month', 'opptiai-alt' ); ?></li>
							<li><?php esc_html_e( 'Bulk processing for the media library', 'opptiai-alt' ); ?></li>
							<li><?php esc_html_e( 'Priority queue for faster results', 'opptiai-alt' ); ?></li>
							<li><?php esc_html_e( 'Multilingual support for global SEO', 'opptiai-alt' ); ?></li>
						</ul>
					</div>
					<div class="bbai-upgrade-card__body">
						<button type="button" class="button bbai-dashboard-btn bbai-dashboard-btn--primary bbai-dashboard-btn--full" data-action="show-upgrade-modal" data-bbai-modal-open>
							<?php esc_html_e( 'Upgrade to Growth', 'opptiai-alt' ); ?>
						</button>
						<ul class="bbai-upgrade-card__list bbai-upgrade-card__list--checks">
							<li><?php esc_html_e( '1,000 AI alt texts per month', 'opptiai-alt' ); ?></li>
							<li><?php esc_html_e( 'Bulk processing for the media library', 'opptiai-alt' ); ?></li>
							<li><?php esc_html_e( 'Priority queue for faster results', 'opptiai-alt' ); ?></li>
							<li><?php esc_html_e( 'Multilingual support for global SEO', 'opptiai-alt' ); ?></li>
						</ul>
						<button type="button" class="bbai-upgrade-card__link" data-action="show-upgrade-modal" data-bbai-modal-open>
							<span><?php esc_html_e( 'Compare plans', 'opptiai-alt' ); ?></span>
							<span class="bbai-upgrade-card__link-icon" aria-hidden="true">
								<svg viewBox="0 0 16 16" focusable="false" aria-hidden="true">
									<path d="M6 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
								</svg>
							</span>
						</button>
					</div>
				</aside>
			</div>

			<div class="bbai-dashboard__grid bbai-dashboard__grid--stats">
				<div class="bbai-card bbai-stat-card">
					<div class="bbai-stat-card__icon" aria-hidden="true">
						<svg viewBox="0 0 20 20" focusable="false" aria-hidden="true">
							<circle cx="10" cy="10" r="7" fill="none" stroke="currentColor" stroke-width="1.5" />
							<path d="M10 6v4l2.5 1.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
						</svg>
					</div>
					<p class="bbai-stat-card__label">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: minutes saved */
								__( 'Saved ~%s minutes', 'opptiai-alt' ),
								number_format_i18n( $data['time_saved_minutes'] )
							)
						);
						?>
					</p>
					<p class="bbai-stat-card__note"><?php esc_html_e( 'Estimated manual work saved.', 'opptiai-alt' ); ?></p>
				</div>
				<div class="bbai-card bbai-stat-card">
					<div class="bbai-stat-card__icon" aria-hidden="true">
						<svg viewBox="0 0 20 20" focusable="false" aria-hidden="true">
							<rect x="3" y="3" width="14" height="14" rx="3" fill="none" stroke="currentColor" stroke-width="1.5" />
							<path d="M6 10l2.5 2.5L14 7" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
						</svg>
					</div>
					<p class="bbai-stat-card__label">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: images optimized */
								__( '%s Images Optimized', 'opptiai-alt' ),
								number_format_i18n( $data['images_optimized'] )
							)
						);
						?>
					</p>
					<p class="bbai-stat-card__note"><?php esc_html_e( 'Automatically generates alt text for existing images.', 'opptiai-alt' ); ?></p>
				</div>
				<div class="bbai-card bbai-stat-card">
					<div class="bbai-stat-card__icon" aria-hidden="true">
						<svg viewBox="0 0 20 20" focusable="false" aria-hidden="true">
							<path d="M4 14V9M10 14V6M16 14V4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
							<path d="M3 14h14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
						</svg>
					</div>
					<p class="bbai-stat-card__label">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: coverage percentage */
								__( 'Coverage Up %s%%', 'opptiai-alt' ),
								number_format_i18n( $data['coverage_percent'] )
							)
						);
						?>
					</p>
					<p class="bbai-stat-card__note"><?php esc_html_e( 'Estimated improvement in alt text coverage.', 'opptiai-alt' ); ?></p>
				</div>
			</div>

			<div class="bbai-info-bar" role="status">
				<span class="bbai-info-bar__icon" aria-hidden="true">i</span>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: optimized images count, 2: remaining generations */
						__( 'You have optimized %1$s images so far. You have %2$s generations remaining this month.', 'opptiai-alt' ),
						number_format_i18n( $used ),
						number_format_i18n( $remaining )
					)
				);
				?>
			</div>

			<div class="bbai-dashboard__grid bbai-dashboard__grid--mid">
				<section class="bbai-card bbai-monthly-card">
					<div class="bbai-monthly-card__header">
						<h3 class="bbai-card__title bbai-card__title--small"><?php esc_html_e( 'Monthly Progress', 'opptiai-alt' ); ?></h3>
						<span class="bbai-monthly-card__chevron" aria-hidden="true">
							<svg viewBox="0 0 16 16" focusable="false" aria-hidden="true">
								<path d="M6 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
							</svg>
						</span>
					</div>
					<div class="bbai-progress-bar bbai-progress-bar--thin" role="presentation">
						<span class="bbai-progress-bar__fill" style="width: <?php echo esc_attr( $percent ); ?>%"></span>
					</div>
					<div class="bbai-monthly-card__row">
						<span class="bbai-monthly-card__label">
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: progress used */
									__( '%s progress used', 'opptiai-alt' ),
									number_format_i18n( $used )
								)
							);
							?>
						</span>
						<span class="bbai-monthly-card__value">
							<span class="bbai-monthly-card__value-number"><?php echo esc_html( number_format_i18n( $used ) ); ?></span>
							<span class="bbai-monthly-card__value-label"><?php esc_html_e( 'generations', 'opptiai-alt' ); ?></span>
						</span>
					</div>
					<div class="bbai-progress-bar bbai-progress-bar--muted" role="presentation">
						<span class="bbai-progress-bar__fill" style="width: <?php echo esc_attr( $percent ); ?>%"></span>
					</div>
					<button type="button" class="bbai-monthly-card__upgrade" data-action="show-upgrade-modal" data-bbai-modal-open>
						<span><?php esc_html_e( 'Upgrade for 1,000 generations every month.', 'opptiai-alt' ); ?></span>
						<span class="bbai-monthly-card__upgrade-icon" aria-hidden="true">
							<svg viewBox="0 0 16 16" focusable="false" aria-hidden="true">
								<path d="M6 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
							</svg>
						</span>
					</button>
				</section>

				<aside class="bbai-card bbai-upgrade-card bbai-upgrade-card--small">
					<h3 class="bbai-card__title bbai-card__title--small"><?php esc_html_e( 'Upgrade to Growth', 'opptiai-alt' ); ?></h3>
					<ul class="bbai-upgrade-card__list bbai-upgrade-card__list--checks">
						<li><?php esc_html_e( '1,000 AI alt texts per month', 'opptiai-alt' ); ?></li>
						<li><?php esc_html_e( 'Bulk processing for the media library', 'opptiai-alt' ); ?></li>
						<li><?php esc_html_e( 'Priority queue for faster results', 'opptiai-alt' ); ?></li>
					</ul>
					<button type="button" class="bbai-upgrade-card__link" data-action="show-upgrade-modal" data-bbai-modal-open>
						<span><?php esc_html_e( 'Compare plans', 'opptiai-alt' ); ?></span>
						<span class="bbai-upgrade-card__link-icon" aria-hidden="true">
							<svg viewBox="0 0 16 16" focusable="false" aria-hidden="true">
								<path d="M6 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
							</svg>
						</span>
					</button>
					<button type="button" class="button bbai-dashboard-btn bbai-dashboard-btn--primary bbai-dashboard-btn--full" data-action="show-upgrade-modal" data-bbai-modal-open>
						<?php esc_html_e( 'Upgrade to Growth', 'opptiai-alt' ); ?>
					</button>
				</aside>
			</div>

			<div class="bbai-trust-badges" role="list">
				<div class="bbai-trust-badge" role="listitem">
					<span class="bbai-trust-badge__icon" aria-hidden="true">
						<svg viewBox="0 0 20 20" role="presentation" focusable="false" aria-hidden="true">
							<path d="M4 10l4 4 8-8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
						</svg>
					</span>
					<span><?php esc_html_e( 'WCAG Compliant', 'opptiai-alt' ); ?></span>
				</div>
				<div class="bbai-trust-badge" role="listitem">
					<span class="bbai-trust-badge__icon" aria-hidden="true">
						<svg viewBox="0 0 20 20" role="presentation" focusable="false" aria-hidden="true">
							<path d="M6 9V7a4 4 0 018 0v2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
							<rect x="4" y="9" width="12" height="8" rx="2" fill="none" stroke="currentColor" stroke-width="2" />
						</svg>
					</span>
					<span><?php esc_html_e( 'GDPR Ready', 'opptiai-alt' ); ?></span>
				</div>
				<div class="bbai-trust-badge" role="listitem">
					<span class="bbai-trust-badge__icon" aria-hidden="true">
						<svg viewBox="0 0 20 20" role="presentation" focusable="false" aria-hidden="true">
							<circle cx="10" cy="10" r="7" fill="none" stroke="currentColor" stroke-width="2" />
							<path d="M10 6v4l3 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
						</svg>
					</span>
					<span><?php esc_html_e( '99.9% Uptime', 'opptiai-alt' ); ?></span>
				</div>
			</div>

			<div class="bbai-testimonials">
				<div class="bbai-card bbai-testimonial-card">
					<div class="bbai-testimonial-card__header">
						<div class="bbai-testimonial-card__avatar" aria-hidden="true">SC</div>
						<div>
							<p class="bbai-testimonial-card__name"><?php esc_html_e( 'Sean Chen', 'opptiai-alt' ); ?></p>
							<p class="bbai-testimonial-card__role"><?php esc_html_e( 'University Blog', 'opptiai-alt' ); ?></p>
						</div>
					</div>
					<p class="bbai-testimonial-card__quote">&quot;<?php esc_html_e( 'Saves me so much time when I update large galleries.', 'opptiai-alt' ); ?>&quot;</p>
				</div>
				<div class="bbai-card bbai-testimonial-card">
					<div class="bbai-testimonial-card__header">
						<div class="bbai-testimonial-card__avatar" aria-hidden="true">MR</div>
						<div>
							<p class="bbai-testimonial-card__name"><?php esc_html_e( 'Michael Rodriguez', 'opptiai-alt' ); ?></p>
							<p class="bbai-testimonial-card__role"><?php esc_html_e( 'Ecommerce Store Owner', 'opptiai-alt' ); ?></p>
						</div>
					</div>
					<p class="bbai-testimonial-card__quote">&quot;<?php esc_html_e( 'Boosted our SEO automatically across every product image.', 'opptiai-alt' ); ?>&quot;</p>
				</div>
				<div class="bbai-card bbai-testimonial-card">
					<div class="bbai-testimonial-card__header">
						<div class="bbai-testimonial-card__avatar" aria-hidden="true">ET</div>
						<div>
							<p class="bbai-testimonial-card__name"><?php esc_html_e( 'Emma Thompson', 'opptiai-alt' ); ?></p>
							<p class="bbai-testimonial-card__role"><?php esc_html_e( 'Food Blog, Simply Recipes', 'opptiai-alt' ); ?></p>
						</div>
					</div>
					<p class="bbai-testimonial-card__quote">&quot;<?php esc_html_e( 'Perfect for WooCommerce and client sites.', 'opptiai-alt' ); ?>&quot;</p>
				</div>
			</div>
		</div>

		<div class="bbai-modal" id="bbai-dashboard-modal" aria-hidden="true">
			<div class="bbai-modal__overlay" data-bbai-modal-close></div>
			<div class="bbai-modal__content" role="dialog" aria-modal="true" aria-labelledby="bbai-modal-title" aria-describedby="bbai-modal-description">
				<button type="button" class="bbai-modal__close" data-bbai-modal-close aria-label="<?php esc_attr_e( 'Close modal', 'opptiai-alt' ); ?>">x</button>

				<div class="bbai-modal__header">
					<h2 id="bbai-modal-title"><?php esc_html_e( 'Choose your BeepBeep AI plan', 'opptiai-alt' ); ?></h2>
					<p id="bbai-modal-description"><?php esc_html_e( 'Choose a plan that matches your monthly usage. Cancel anytime.', 'opptiai-alt' ); ?></p>
				</div>

				<div class="bbai-modal__plans">
					<div class="bbai-plan-card">
						<h3><?php esc_html_e( 'Free', 'opptiai-alt' ); ?></h3>
						<p class="bbai-plan-card__price">£0</p>
						<p class="bbai-plan-card__limit">50 / month</p>
						<ul>
							<li><?php esc_html_e( 'Core alt text automation', 'opptiai-alt' ); ?></li>
							<li><?php esc_html_e( 'Accessible outputs', 'opptiai-alt' ); ?></li>
						</ul>
						<button type="button" class="button bbai-dashboard-btn bbai-dashboard-btn--secondary" data-bbai-modal-close><?php esc_html_e( 'Stay on Free', 'opptiai-alt' ); ?></button>
					</div>

					<div class="bbai-plan-card bbai-plan-card--featured">
						<span class="bbai-plan-card__badge"><?php esc_html_e( 'Most Popular', 'opptiai-alt' ); ?></span>
						<h3><?php esc_html_e( 'Growth', 'opptiai-alt' ); ?></h3>
						<p class="bbai-plan-card__price">£12.99</p>
						<p class="bbai-plan-card__limit">1,000 / month</p>
						<ul>
							<li><?php esc_html_e( 'Bulk generation workflows', 'opptiai-alt' ); ?></li>
							<li><?php esc_html_e( 'Priority support', 'opptiai-alt' ); ?></li>
							<li><?php esc_html_e( 'Advanced analytics', 'opptiai-alt' ); ?></li>
						</ul>
						<button type="button" class="button bbai-dashboard-btn bbai-dashboard-btn--primary" data-bbai-modal-close><?php esc_html_e( 'Upgrade to Growth', 'opptiai-alt' ); ?></button>
					</div>

					<div class="bbai-plan-card">
						<h3><?php esc_html_e( 'Agency', 'opptiai-alt' ); ?></h3>
						<p class="bbai-plan-card__price">£49.99</p>
						<p class="bbai-plan-card__limit">10,000+ / month</p>
						<ul>
							<li><?php esc_html_e( 'Multi-site management', 'opptiai-alt' ); ?></li>
							<li><?php esc_html_e( 'Dedicated support', 'opptiai-alt' ); ?></li>
							<li><?php esc_html_e( 'Client reporting tools', 'opptiai-alt' ); ?></li>
						</ul>
						<button type="button" class="button bbai-dashboard-btn bbai-dashboard-btn--secondary" data-bbai-modal-close><?php esc_html_e( 'Upgrade to Agency', 'opptiai-alt' ); ?></button>
					</div>
				</div>

				<div class="bbai-modal__credits">
					<div>
						<h3><?php esc_html_e( 'One-time credits', 'opptiai-alt' ); ?></h3>
						<p><?php esc_html_e( '£9.99 Buy 100 credits', 'opptiai-alt' ); ?></p>
					</div>
					<button type="button" class="button bbai-dashboard-btn bbai-dashboard-btn--secondary" data-bbai-modal-close><?php esc_html_e( 'Buy credits', 'opptiai-alt' ); ?></button>
				</div>

				<div class="bbai-modal__faq">
					<h3><?php esc_html_e( 'FAQ', 'opptiai-alt' ); ?></h3>
					<details>
						<summary><?php esc_html_e( 'Can I downgrade anytime?', 'opptiai-alt' ); ?></summary>
						<p><?php esc_html_e( 'Yes. You can downgrade or cancel anytime.', 'opptiai-alt' ); ?></p>
					</details>
					<details>
						<summary><?php esc_html_e( 'Do credits roll over?', 'opptiai-alt' ); ?></summary>
						<p><?php esc_html_e( 'Monthly credits reset each month. One-time credits do not expire.', 'opptiai-alt' ); ?></p>
					</details>
					<details>
						<summary><?php esc_html_e( 'Will it work with WooCommerce and all themes?', 'opptiai-alt' ); ?></summary>
						<p><?php esc_html_e( 'Yes. It works with WooCommerce, Gutenberg, and most themes.', 'opptiai-alt' ); ?></p>
					</details>
				</div>
			</div>
		</div>
	}

	/**
	 * Check whether the plugin is authenticated.
	 *
	 * @return bool
	 */
	private function is_authenticated() {
		if ( function_exists( 'bbai_is_authenticated' ) ) {
			return (bool) \bbai_is_authenticated();
		}

		return function_exists( 'is_user_logged_in' ) ? is_user_logged_in() : false;
	}

	/**
	 * Render the logged-out onboarding screen.
	 */
	private function render_logged_out_onboarding() {
		$partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-logged-out.php';
		if ( file_exists( $partial ) ) {
			include $partial;
			return;
		}

		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Please sign in to access the dashboard.', 'opptiai-alt' ) . '</p></div>';
	}

	/**
	 * Return dashboard data (stubbed for now).
	 *
	 * @return array
	 */
	public function get_dashboard_data() {
		$data = [
			'month_label'          => date_i18n( 'F' ),
			'used'                 => 20,
			'limit'                => 50,
			'percent'              => 40,
			'reset_date'           => date_i18n( 'M j, Y', strtotime( 'first day of next month' ) ),
			'time_saved_minutes'   => 48,
			'images_optimized'     => 5,
			'coverage_percent'     => 63,
			'generations_remaining' => 30,
		];

		return apply_filters( 'bbai_dashboard_data', $data );
	}

	/**
	 * AJAX stub for generating missing alt text.
	 */
	public function ajax_generate_missing() {
		$this->handle_ajax_stub( __( 'Completed.', 'opptiai-alt' ) );
	}

	/**
	 * AJAX stub for re-optimizing all images.
	 */
	public function ajax_reoptimize_all() {
		$this->handle_ajax_stub( __( 'Completed.', 'opptiai-alt' ) );
	}

	/**
	 * Shared AJAX stub handler.
	 *
	 * @param string $message Success message.
	 */
	private function handle_ajax_stub( $message ) {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'bbai_dashboard_actions' ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'opptiai-alt' ) ], 403 );
		}

		if ( ! current_user_can( $this->get_capability() ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized', 'opptiai-alt' ) ] );
		}

		wp_send_json_success( [ 'message' => $message ] );
	}

	/**
	 * Determine capability for the dashboard.
	 *
	 * @return string
	 */
	private function get_capability() {
		if ( class_exists( '\\BeepBeepAI\\AltTextGenerator\\Core' ) ) {
			return Core::CAPABILITY;
		}

		return 'manage_options';
	}
}
