<?php
/**
 * Upgrade View - OptiAI Design System
 *
 * @package BeepBeepAI\AltTextGenerator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Extract variables
$current_plan = $current_plan ?? 'free';
$pricing_data = $pricing_data ?? array();
$is_pro       = $is_pro ?? false;
$is_agency    = $is_agency ?? false;

// Define plans
$plans = array(
	'free'   => array(
		'name'         => __( 'Free', 'beepbeep-ai-alt-text-generator' ),
		'price'        => __( 'Free', 'beepbeep-ai-alt-text-generator' ),
		'price_annual' => __( 'Free', 'beepbeep-ai-alt-text-generator' ),
		'features'     => array(
			__( '100 image generations/month', 'beepbeep-ai-alt-text-generator' ),
			__( 'Basic alt text generation', 'beepbeep-ai-alt-text-generator' ),
			__( 'Standard queue processing', 'beepbeep-ai-alt-text-generator' ),
			__( 'Community support', 'beepbeep-ai-alt-text-generator' ),
		),
		'current'      => $current_plan === 'free',
	),
	'pro'    => array(
		'name'                   => __( 'Pro', 'beepbeep-ai-alt-text-generator' ),
		'price'                  => '$29',
		'price_annual'           => '$290',
		'price_per_month_annual' => '$24.17',
		'features'               => array(
			__( 'Unlimited image generations', 'beepbeep-ai-alt-text-generator' ),
			__( 'Priority queue processing', 'beepbeep-ai-alt-text-generator' ),
			__( 'Bulk optimization for large libraries', 'beepbeep-ai-alt-text-generator' ),
			__( 'Multilingual AI alt text', 'beepbeep-ai-alt-text-generator' ),
			__( 'Faster & more descriptive alt text', 'beepbeep-ai-alt-text-generator' ),
			__( 'Priority support', 'beepbeep-ai-alt-text-generator' ),
		),
		'recommended'            => true,
		'current'                => $is_pro,
	),
	'agency' => array(
		'name'                   => __( 'Agency', 'beepbeep-ai-alt-text-generator' ),
		'price'                  => '$99',
		'price_annual'           => '$990',
		'price_per_month_annual' => '$82.50',
		'features'               => array(
			__( 'Everything in Pro', 'beepbeep-ai-alt-text-generator' ),
			__( 'Multi-site license', 'beepbeep-ai-alt-text-generator' ),
			__( 'White-label options', 'beepbeep-ai-alt-text-generator' ),
			__( 'Advanced analytics', 'beepbeep-ai-alt-text-generator' ),
			__( 'Dedicated support', 'beepbeep-ai-alt-text-generator' ),
			__( 'Custom integrations', 'beepbeep-ai-alt-text-generator' ),
		),
		'current'                => $is_agency,
	),
);
?>

<div class="optiai-admin-page">
	<div class="optiai-upgrade">
		<!-- Header -->
		<div class="optiai-header">
			<h1 class="optiai-heading"><?php esc_html_e( 'Upgrade', 'beepbeep-ai-alt-text-generator' ); ?></h1>
			<p class="optiai-subtitle"><?php esc_html_e( 'Unlock unlimited AI-powered alt text generation', 'beepbeep-ai-alt-text-generator' ); ?></p>
		</div>

		<!-- Guarantee Badge -->
		<div class="optiai-guarantee-badge">
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
				<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
				<path d="M9 12l2 2 4-4"/>
			</svg>
			<span><?php esc_html_e( '30-day money-back guarantee', 'beepbeep-ai-alt-text-generator' ); ?></span>
		</div>

		<!-- Pricing Cards -->
		<div class="optiai-pricing-grid">
			<?php foreach ( $plans as $plan_id => $plan ) : ?>
				<div class="optiai-pricing-card <?php echo esc_attr( $plan['recommended'] ?? false ? 'optiai-pricing-card--recommended' : '' ); ?> <?php echo esc_attr( $plan['current'] ?? false ? 'optiai-pricing-card--current' : '' ); ?>">
					<?php if ( $plan['recommended'] ?? false ) : ?>
						<div class="optiai-pricing-badge"><?php esc_html_e( 'Most Popular', 'beepbeep-ai-alt-text-generator' ); ?></div>
					<?php endif; ?>
					
					<?php if ( $plan['current'] ?? false ) : ?>
						<div class="optiai-pricing-badge optiai-pricing-badge--current"><?php esc_html_e( 'Current Plan', 'beepbeep-ai-alt-text-generator' ); ?></div>
					<?php endif; ?>

					<h3 class="optiai-pricing-name"><?php echo esc_html( $plan['name'] ); ?></h3>
					
					<div class="optiai-pricing-price">
						<span class="optiai-pricing-amount"><?php echo esc_html( $plan['price'] ); ?></span>
						<?php if ( $plan_id !== 'free' ) : ?>
							<span class="optiai-pricing-period">/month</span>
						<?php endif; ?>
					</div>

					<?php if ( $plan_id !== 'free' && isset( $plan['price_per_month_annual'] ) ) : ?>
						<div class="optiai-pricing-annual">
							<span class="optiai-pricing-annual-amount"><?php echo esc_html( $plan['price_annual'] ); ?></span>
							<span class="optiai-pricing-annual-label"><?php esc_html_e( 'per year', 'beepbeep-ai-alt-text-generator' ); ?></span>
							<span class="optiai-pricing-discount"><?php esc_html_e( 'Save 20%', 'beepbeep-ai-alt-text-generator' ); ?></span>
						</div>
					<?php endif; ?>

					<ul class="optiai-pricing-features">
						<?php foreach ( $plan['features'] as $feature ) : ?>
							<li>
								<svg width="20" height="20" viewBox="0 0 20 20" fill="none">
									<path d="M16.667 5L7.5 14.167 3.333 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
								<?php echo esc_html( $feature ); ?>
							</li>
						<?php endforeach; ?>
					</ul>

					<div class="optiai-pricing-actions">
						<?php if ( $plan['current'] ?? false ) : ?>
							<button class="optiai-button optiai-button-secondary" disabled>
								<?php esc_html_e( 'Current Plan', 'beepbeep-ai-alt-text-generator' ); ?>
							</button>
						<?php else : ?>
							<button class="optiai-button" data-action="show-upgrade-modal" data-plan="<?php echo esc_attr( $plan_id ); ?>">
								<?php esc_html_e( 'Upgrade Now', 'beepbeep-ai-alt-text-generator' ); ?>
							</button>
							<a href="<?php echo esc_url( function_exists( 'opptiai_framework' ) ? opptiai_framework()->licensing->get_upgrade_url() : '#' ); ?>" class="optiai-button-secondary" target="_blank">
								<?php esc_html_e( 'View Details', 'beepbeep-ai-alt-text-generator' ); ?>
							</a>
							<?php if ( $plan_id !== 'free' ) : ?>
								<a href="#" class="optiai-link" data-action="buy-extra-credits">
									<?php esc_html_e( 'Buy Extra Credits', 'beepbeep-ai-alt-text-generator' ); ?>
								</a>
							<?php endif; ?>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<!-- Feature Comparison Grid -->
		<div class="optiai-card">
			<h3 class="optiai-heading"><?php esc_html_e( 'Feature Comparison', 'beepbeep-ai-alt-text-generator' ); ?></h3>
			<div class="optiai-comparison-table-wrapper">
				<table class="optiai-table optiai-comparison-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Feature', 'beepbeep-ai-alt-text-generator' ); ?></th>
							<th><?php esc_html_e( 'Free', 'beepbeep-ai-alt-text-generator' ); ?></th>
							<th><?php esc_html_e( 'Pro', 'beepbeep-ai-alt-text-generator' ); ?></th>
							<th><?php esc_html_e( 'Agency', 'beepbeep-ai-alt-text-generator' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Monthly Generations', 'beepbeep-ai-alt-text-generator' ); ?></td>
							<td>100</td>
							<td><?php esc_html_e( 'Unlimited', 'beepbeep-ai-alt-text-generator' ); ?></td>
							<td><?php esc_html_e( 'Unlimited', 'beepbeep-ai-alt-text-generator' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Priority Processing', 'beepbeep-ai-alt-text-generator' ); ?></td>
							<td>✗</td>
							<td>✓</td>
							<td>✓</td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Bulk Optimization', 'beepbeep-ai-alt-text-generator' ); ?></td>
							<td>✗</td>
							<td>✓</td>
							<td>✓</td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Multilingual Support', 'beepbeep-ai-alt-text-generator' ); ?></td>
							<td>✗</td>
							<td>✓</td>
							<td>✓</td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Multi-Site License', 'beepbeep-ai-alt-text-generator' ); ?></td>
							<td>✗</td>
							<td>✗</td>
							<td>✓</td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Support Level', 'beepbeep-ai-alt-text-generator' ); ?></td>
							<td><?php esc_html_e( 'Community', 'beepbeep-ai-alt-text-generator' ); ?></td>
							<td><?php esc_html_e( 'Priority', 'beepbeep-ai-alt-text-generator' ); ?></td>
							<td><?php esc_html_e( 'Dedicated', 'beepbeep-ai-alt-text-generator' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<!-- Annual Discount Message -->
		<div class="optiai-card optiai-discount-banner">
			<div class="optiai-discount-content">
				<svg width="32" height="32" viewBox="0 0 32 32" fill="none">
					<circle cx="16" cy="16" r="14" stroke="currentColor" stroke-width="2"/>
					<path d="M16 8v8l6 4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
				</svg>
				<div>
					<h4><?php esc_html_e( 'Save 20% with Annual Plans', 'beepbeep-ai-alt-text-generator' ); ?></h4>
					<p><?php esc_html_e( 'Choose annual billing and save on your subscription.', 'beepbeep-ai-alt-text-generator' ); ?></p>
				</div>
			</div>
		</div>
	</div>
</div>
