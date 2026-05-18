<?php
/**
 * Logged-in dashboard daily-pass surface.
 *
 * Expects parent scope from dashboard-body.php:
 *   $bbai_li_state  array  Full output from Logged_In_Dashboard_Resolver::resolve().
 *   $this           Core   Plugin core (used by legacy runtime partial only).
 *
 * @package BeepBeep_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bbai_li_state         = is_array( $bbai_li_state ?? null ) ? $bbai_li_state : [];
$bbai_li_state_raw     = (string) ( $bbai_li_state['state'] ?? '' );
$bbai_li_initial_json  = (string) wp_json_encode( $bbai_li_state );
$bbai_daily_segments   = is_array( $bbai_li_state['donut']['segments'] ?? null ) ? $bbai_li_state['donut']['segments'] : [];
$bbai_daily_missing    = max( 0, (int) ( $bbai_dashboard_root_missing_count ?? ( $bbai_daily_segments['missing'] ?? 0 ) ) );
$bbai_daily_review     = max( 0, (int) ( $bbai_dashboard_root_weak_count ?? ( $bbai_daily_segments['weak'] ?? 0 ) ) );
$bbai_daily_optimised  = max( 0, (int) ( $bbai_dashboard_root_optimized_count ?? ( $bbai_daily_segments['optimized'] ?? 0 ) ) );
$bbai_daily_total      = max( 0, (int) ( $bbai_dashboard_root_total_count ?? ( $bbai_li_state['meta']['total_images'] ?? 0 ) ) );
$bbai_daily_total      = max( $bbai_daily_total, $bbai_daily_missing + $bbai_daily_review + $bbai_daily_optimised );
$bbai_daily_covered    = max( 0, $bbai_daily_total - $bbai_daily_missing );
$bbai_daily_pct        = $bbai_daily_total > 0 ? (int) min( 100, round( ( 100 * $bbai_daily_covered ) / $bbai_daily_total ) ) : 0;
$bbai_daily_complete   = $bbai_daily_total > 0 && 0 === $bbai_daily_missing && 0 === $bbai_daily_review;
$bbai_daily_actionable = max( 0, $bbai_daily_missing + $bbai_daily_review );
$bbai_daily_focus      = $bbai_daily_complete ? 'complete' : ( $bbai_daily_missing > 0 ? 'missing' : 'review' );
$bbai_daily_ring_count = 'complete' === $bbai_daily_focus ? '✓' : number_format_i18n( 'missing' === $bbai_daily_focus ? $bbai_daily_missing : $bbai_daily_review );
$bbai_daily_card_mod   = 'complete' === $bbai_daily_focus ? 'bbai-daily-hero-card--complete' : ( 'review' === $bbai_daily_focus ? 'bbai-daily-hero-card--review' : 'bbai-daily-hero-card--incomplete' );
$bbai_daily_strip_mod  = 'complete' === $bbai_daily_focus ? 'bbai-daily-optimised-strip--complete' : ( 'review' === $bbai_daily_focus ? 'bbai-daily-optimised-strip--review' : 'bbai-daily-optimised-strip--incomplete' );
$bbai_daily_used       = max( 0, (int) ( $bbai_dashboard_root_credits_used ?? 0 ) );
$bbai_daily_limit      = max( 1, (int) ( $bbai_dashboard_root_credits_total ?? 50 ) );
$bbai_daily_remaining  = max( 0, (int) ( $bbai_dashboard_root_credits_left ?? max( 0, $bbai_daily_limit - $bbai_daily_used ) ) );
$bbai_daily_usage_pct  = (int) min( 100, round( ( 100 * $bbai_daily_used ) / $bbai_daily_limit ) );
$bbai_daily_library_url = $bbai_library_url ?? admin_url( 'admin.php?page=bbai-library' );
$bbai_daily_upload_url  = admin_url( 'upload.php' );
$bbai_daily_time_saved  = max( 0, $bbai_daily_optimised * 2 );
$bbai_daily_review_phrase = sprintf(
	/* translators: %s: review count. */
	_n( '%s is ready for review', '%s are ready for review', $bbai_daily_review, 'beepbeep-ai-alt-text-generator' ),
	number_format_i18n( $bbai_daily_review )
);
$bbai_daily_queue_sentence = $bbai_daily_missing > 0
	? sprintf(
		/* translators: 1: missing count, 2: ready-for-review phrase. */
		_n( '%1$s image needs ALT text and %2$s.', '%1$s images need ALT text and %2$s.', $bbai_daily_missing, 'beepbeep-ai-alt-text-generator' ),
		number_format_i18n( $bbai_daily_missing ),
		$bbai_daily_review_phrase
	)
	: sprintf(
		/* translators: %s: review count. */
		_n( '%s image is ready for review.', '%s images are ready for review.', $bbai_daily_review, 'beepbeep-ai-alt-text-generator' ),
		number_format_i18n( $bbai_daily_review )
	);
$bbai_daily_strip_title = $bbai_daily_complete
	? __( "You're 100% optimised", 'beepbeep-ai-alt-text-generator' )
	: ( 'review' === $bbai_daily_focus ? __( 'Review queue ready', 'beepbeep-ai-alt-text-generator' ) : __( 'Today’s pass needs attention', 'beepbeep-ai-alt-text-generator' ) );
$bbai_daily_strip_body  = $bbai_daily_complete
	? __( 'All images are done.', 'beepbeep-ai-alt-text-generator' )
	: $bbai_daily_queue_sentence;
$bbai_daily_coverage_pill = $bbai_daily_complete
	? __( 'All images optimised 🎉', 'beepbeep-ai-alt-text-generator' )
	: sprintf(
		/* translators: %s: percentage of images covered. */
		__( '%s%% images covered', 'beepbeep-ai-alt-text-generator' ),
		(string) $bbai_daily_pct
	);

$bbai_daily_primary = $bbai_daily_complete
	? [
		'label'  => __( 'Add new images', 'beepbeep-ai-alt-text-generator' ),
		'href'   => $bbai_daily_upload_url,
		'action' => 'navigate',
	]
	: [
		'label'       => $bbai_daily_missing > 0
			? sprintf(
				/* translators: %s: missing image count */
				_n( 'Generate ALT text for %s image', 'Generate ALT text for %s images', $bbai_daily_missing, 'beepbeep-ai-alt-text-generator' ),
				number_format_i18n( $bbai_daily_missing )
			)
			: sprintf(
				/* translators: %s: review image count */
				_n( 'Review %s image', 'Review %s images', $bbai_daily_review, 'beepbeep-ai-alt-text-generator' ),
				number_format_i18n( $bbai_daily_review )
			),
		'href'        => $bbai_daily_missing > 0 ? '#' : ( $bbai_needs_review_library_url ?? $bbai_daily_library_url ),
		'action'      => $bbai_daily_missing > 0 ? 'generate-missing' : 'navigate',
		'bbai_action' => $bbai_daily_missing > 0 ? 'generate_missing' : '',
		'busy_label'  => $bbai_daily_missing > 0 ? __( 'Loading…', 'beepbeep-ai-alt-text-generator' ) : '',
	];

$bbai_daily_secondary = $bbai_daily_complete
	? [
		'label'  => __( 'View library status', 'beepbeep-ai-alt-text-generator' ),
		'href'   => $bbai_daily_library_url,
		'action' => 'navigate',
	]
	: [
		'label'  => __( 'View library status', 'beepbeep-ai-alt-text-generator' ),
		'href'   => $bbai_daily_library_url,
		'action' => 'navigate',
	];

$bbai_daily_rescan = [
	'label'      => __( 'Re-scan library', 'beepbeep-ai-alt-text-generator' ),
	'href'       => '#',
	'action'     => 'rescan-media-library',
	'busy_label' => __( 'Scanning library…', 'beepbeep-ai-alt-text-generator' ),
];

$bbai_daily_upgrade = [
	'label'  => __( 'Upgrade plan', 'beepbeep-ai-alt-text-generator' ),
	'href'   => '#',
	'action' => 'show-upgrade-modal',
];

$bbai_daily_button_attrs = static function ( array $action ): string {
	$attrs = [ 'href="' . esc_url( (string) ( $action['href'] ?? '#' ) ) . '"' ];
	if ( ! empty( $action['action'] ) ) {
		$attrs[] = 'data-action="' . esc_attr( (string) $action['action'] ) . '"';
		$attrs[] = 'data-bbai-li-action="' . esc_attr( (string) $action['action'] ) . '"';
	}
	if ( ! empty( $action['bbai_action'] ) ) {
		$attrs[] = 'data-bbai-action="' . esc_attr( (string) $action['bbai_action'] ) . '"';
		$attrs[] = 'data-bbai-generation-action="1"';
	}
	if ( ! empty( $action['busy_label'] ) ) {
		$attrs[] = 'data-busy-label="' . esc_attr( (string) $action['busy_label'] ) . '"';
	}
	return implode( ' ', $attrs );
};
?>

<section
	class="bbai-logged-in-dashboard bbai-logged-in-dashboard--daily-pass"
	data-bbai-logged-in-dashboard
	data-bbai-li-ssr="1"
	data-state="<?php echo esc_attr( $bbai_li_state_raw ); ?>"
	data-bbai-li-initial-state="<?php echo esc_attr( $bbai_li_initial_json ); ?>"
	data-bbai-li-state-truth-url="<?php echo esc_url( rest_url( 'bbai/v1/dashboard/state-truth' ) ); ?>"
	data-bbai-counts-hash="<?php echo esc_attr( (string) ( $bbai_dashboard_root_counts_hash ?? '' ) ); ?>"
	aria-label="<?php echo esc_attr__( 'Logged-in dashboard', 'beepbeep-ai-alt-text-generator' ); ?>"
>
	<?php
	$bbai_dashboard_header_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-header.php';
	if ( is_readable( $bbai_dashboard_header_partial ) ) {
		require $bbai_dashboard_header_partial;
	}
	?>

	<div class="bbai-daily-top-grid">
		<section class="bbai-daily-hero-card bbai-daily-card <?php echo esc_attr( $bbai_daily_card_mod ); ?>" aria-labelledby="bbai-daily-hero-title" data-bbai-daily-hero-card="1">
			<div class="bbai-daily-hero-main">
				<div class="bbai-daily-donut" aria-hidden="true">
					<span data-bbai-daily-donut-value="1"><?php echo esc_html( $bbai_daily_ring_count ); ?></span>
				</div>
				<div class="bbai-daily-hero-copy">
					<p class="bbai-daily-badge" data-bbai-daily-badge="1"><?php echo esc_html( $bbai_daily_complete ? __( 'All optimised', 'beepbeep-ai-alt-text-generator' ) : __( 'Today’s pass', 'beepbeep-ai-alt-text-generator' ) ); ?></p>
					<h2 id="bbai-daily-hero-title" class="bbai-daily-hero-title" data-bbai-daily-title="1">
						<?php echo esc_html( $bbai_daily_complete ? __( 'Your media library is fully optimised', 'beepbeep-ai-alt-text-generator' ) : ( 'review' === $bbai_daily_focus ? __( 'Review AI suggestions', 'beepbeep-ai-alt-text-generator' ) : __( 'Finish today’s image ALT pass', 'beepbeep-ai-alt-text-generator' ) ) ); ?>
					</h2>
					<p class="bbai-daily-hero-text" data-bbai-daily-copy="1">
						<?php
						echo esc_html(
							$bbai_daily_complete
								? __( 'Everything is accessible, SEO-ready, and performing at its best.', 'beepbeep-ai-alt-text-generator' )
								: sprintf(
									/* translators: %s: queue sentence. */
									__( '%s Clear the queue in one focused pass.', 'beepbeep-ai-alt-text-generator' ),
									$bbai_daily_queue_sentence
								)
						);
						?>
					</p>
						<div class="bbai-daily-mini-stats">
							<span><i aria-hidden="true">▧</i><strong data-bbai-daily-optimised="1"><?php echo esc_html( number_format_i18n( $bbai_daily_optimised ) ); ?></strong><?php esc_html_e( 'Images optimised', 'beepbeep-ai-alt-text-generator' ); ?></span>
							<span><i aria-hidden="true">⚚</i><strong data-bbai-daily-accessible="1"><?php echo esc_html( sprintf(
								/* translators: %s: percentage of accessible images. */
								__( '%s%%', 'beepbeep-ai-alt-text-generator' ),
								(string) $bbai_daily_pct
							) ); ?></strong><?php esc_html_e( 'Accessible', 'beepbeep-ai-alt-text-generator' ); ?></span>
					</div>
				</div>
			</div>

			<div class="bbai-daily-hero-actions">
				<a class="bbai-daily-btn bbai-daily-btn--primary<?php echo $bbai_daily_missing > 0 ? ' bbai-generate-button' : ''; ?>" data-bbai-daily-primary-cta="1" <?php echo $bbai_daily_button_attrs( $bbai_daily_primary ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<span aria-hidden="true"><?php echo esc_html( $bbai_daily_missing > 0 ? '☁' : '✓' ); ?></span><?php echo esc_html( $bbai_daily_primary['label'] ); ?>
				</a>
				<a class="bbai-daily-btn bbai-daily-btn--secondary" data-bbai-daily-secondary-cta="1" <?php echo $bbai_daily_button_attrs( $bbai_daily_secondary ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<span aria-hidden="true">□</span><?php echo esc_html( $bbai_daily_secondary['label'] ); ?>
				</a>
			</div>

			<div class="bbai-daily-queue-row">
				<span><i class="bbai-daily-dot bbai-daily-dot--red" aria-hidden="true"></i><strong data-bbai-daily-missing="1"><?php echo esc_html( number_format_i18n( $bbai_daily_missing ) ); ?></strong><?php esc_html_e( 'images need ALT text', 'beepbeep-ai-alt-text-generator' ); ?></span>
				<span><i class="bbai-daily-dot bbai-daily-dot--amber" aria-hidden="true"></i><strong data-bbai-daily-review="1"><?php echo esc_html( number_format_i18n( $bbai_daily_review ) ); ?></strong><?php esc_html_e( 'ready for review', 'beepbeep-ai-alt-text-generator' ); ?></span>
			</div>

			<div class="bbai-daily-flow-row" aria-label="<?php esc_attr_e( 'ALT text workflow', 'beepbeep-ai-alt-text-generator' ); ?>">
				<span data-bbai-daily-flow-step="generate" class="<?php echo esc_attr( ! $bbai_daily_complete && $bbai_daily_missing > 0 ? 'is-active' : '' ); ?>"><?php esc_html_e( 'Generate', 'beepbeep-ai-alt-text-generator' ); ?></span>
				<i aria-hidden="true">→</i>
				<span data-bbai-daily-flow-step="review" class="<?php echo esc_attr( ! $bbai_daily_complete && 0 === $bbai_daily_missing && $bbai_daily_review > 0 ? 'is-active' : '' ); ?>"><?php esc_html_e( 'Review', 'beepbeep-ai-alt-text-generator' ); ?></span>
				<i aria-hidden="true">→</i>
				<span data-bbai-daily-flow-step="done" class="<?php echo esc_attr( $bbai_daily_complete ? 'is-active' : '' ); ?>"><?php esc_html_e( 'Done', 'beepbeep-ai-alt-text-generator' ); ?></span>
			</div>
		</section>

		<section class="bbai-daily-usage-panel bbai-daily-card" aria-label="<?php esc_attr_e( 'Usage and automation', 'beepbeep-ai-alt-text-generator' ); ?>">
			<div class="bbai-daily-usage-head">
				<p class="bbai-daily-eyebrow"><?php esc_html_e( 'Usage this month', 'beepbeep-ai-alt-text-generator' ); ?></p>
					<strong data-bbai-daily-usage-used="1"><?php echo esc_html( sprintf(
						/* translators: 1: used credit count, 2: monthly credit limit. */
						__( '%1$s / %2$s used', 'beepbeep-ai-alt-text-generator' ),
						number_format_i18n( $bbai_daily_used ),
						number_format_i18n( $bbai_daily_limit )
					) ); ?></strong>
					<div class="bbai-daily-usage-meta">
						<span><?php esc_html_e( 'Resets monthly', 'beepbeep-ai-alt-text-generator' ); ?></span>
						<span data-bbai-daily-usage-remaining="1"><?php echo esc_html( sprintf(
							/* translators: %s: remaining monthly credit count. */
							__( '%s remaining', 'beepbeep-ai-alt-text-generator' ),
							number_format_i18n( $bbai_daily_remaining )
						) ); ?></span>
				</div>
				<div class="bbai-daily-meter"><span data-bbai-daily-usage-meter="1" style="width: <?php echo esc_attr( (string) $bbai_daily_usage_pct ); ?>%;"></span></div>
			</div>

			<div class="bbai-daily-automation-box">
				<div>
					<h3><span aria-hidden="true">⚡</span><?php esc_html_e( 'Automate future uploads', 'beepbeep-ai-alt-text-generator' ); ?></h3>
					<p><?php esc_html_e( 'Automatically generate ALT text for new media uploads.', 'beepbeep-ai-alt-text-generator' ); ?></p>
				</div>
				<button type="button" class="bbai-daily-switch" data-action="show-upgrade-modal" aria-pressed="false"><span></span></button>
			</div>

			<div class="bbai-daily-side-actions">
				<a class="bbai-daily-btn bbai-daily-btn--secondary" <?php echo $bbai_daily_button_attrs( $bbai_daily_rescan ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<span aria-hidden="true">↻</span><?php echo esc_html( $bbai_daily_rescan['label'] ); ?>
				</a>
				<a class="bbai-daily-btn bbai-daily-btn--upgrade" <?php echo $bbai_daily_button_attrs( $bbai_daily_upgrade ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<span aria-hidden="true">♛</span><?php echo esc_html( $bbai_daily_upgrade['label'] ); ?>
				</a>
			</div>

			<div class="bbai-daily-credit-box">
					<strong data-bbai-daily-credit-copy="1"><?php echo esc_html( sprintf(
						/* translators: %s: remaining monthly credit count. */
						__( 'Only %s credits left this month', 'beepbeep-ai-alt-text-generator' ),
						number_format_i18n( $bbai_daily_remaining )
					) ); ?></strong>
				<div class="bbai-daily-meter"><span data-bbai-daily-credit-meter="1" style="width: <?php echo esc_attr( (string) $bbai_daily_usage_pct ); ?>%;"></span></div>
				<a href="#" data-action="show-upgrade-modal"><?php esc_html_e( 'Upgrade to automate ALT text', 'beepbeep-ai-alt-text-generator' ); ?><br><?php esc_html_e( '(up to 1,000 images/month)', 'beepbeep-ai-alt-text-generator' ); ?></a>
			</div>
		</section>
	</div>

	<section class="bbai-daily-optimised-strip bbai-daily-card <?php echo esc_attr( $bbai_daily_strip_mod ); ?>" aria-label="<?php esc_attr_e( 'Optimisation summary', 'beepbeep-ai-alt-text-generator' ); ?>" data-bbai-daily-strip="1">
		<div class="bbai-daily-strip-check" aria-hidden="true" data-bbai-daily-strip-icon="1"><?php echo esc_html( $bbai_daily_complete ? '✓' : ( 'review' === $bbai_daily_focus ? '✓' : '!' ) ); ?></div>
		<div>
			<strong data-bbai-daily-strip-title="1"><?php echo esc_html( $bbai_daily_strip_title ); ?></strong>
			<p data-bbai-daily-strip-body="1"><?php echo esc_html( $bbai_daily_strip_body ); ?></p>
			</div>
			<div class="bbai-daily-strip-progress">
				<span data-bbai-daily-strip-progress-text="1"><?php echo esc_html( sprintf(
					/* translators: 1: optimised image count, 2: total image count. */
					__( '%1$s / %2$s images optimised', 'beepbeep-ai-alt-text-generator' ),
					number_format_i18n( $bbai_daily_optimised ),
					number_format_i18n( $bbai_daily_total )
				) ); ?></span>
			<div class="bbai-daily-meter"><span data-bbai-daily-strip-meter="1" style="width: <?php echo esc_attr( (string) $bbai_daily_pct ); ?>%;"></span></div>
		</div>
		<a class="bbai-daily-btn bbai-daily-btn--primary" href="#" data-action="show-upgrade-modal"><span aria-hidden="true">⚡</span><?php esc_html_e( 'Keep optimising automatically', 'beepbeep-ai-alt-text-generator' ); ?></a>
		<a class="bbai-daily-btn bbai-daily-btn--secondary" href="<?php echo esc_url( $bbai_daily_library_url ); ?>" data-action="navigate"><?php esc_html_e( 'View library status', 'beepbeep-ai-alt-text-generator' ); ?></a>
	</section>

	<section class="bbai-daily-insights" aria-label="<?php echo esc_attr__( 'Library insights', 'beepbeep-ai-alt-text-generator' ); ?>">
		<article class="bbai-daily-insight bbai-daily-insight--accessibility bbai-daily-card">
			<div class="bbai-daily-insight-head">
				<div class="bbai-daily-insight-icon" aria-hidden="true">⚚</div>
				<div>
					<p class="bbai-daily-eyebrow"><?php esc_html_e( 'Accessibility', 'beepbeep-ai-alt-text-generator' ); ?></p>
					<span class="bbai-daily-pill"><?php echo esc_html( $bbai_daily_coverage_pill ); ?></span>
				</div>
			</div>
				<h3><?php echo esc_html( sprintf(
					/* translators: %s: percentage of accessible images. */
					__( '%s%% of images accessible', 'beepbeep-ai-alt-text-generator' ),
					(string) $bbai_daily_pct
				) ); ?></h3>
				<p><?php esc_html_e( 'Every image has useful ALT text.', 'beepbeep-ai-alt-text-generator' ); ?></p>
				<strong><?php echo esc_html( sprintf(
					/* translators: 1: covered image count, 2: total image count. */
					__( '%1$s of %2$s images covered', 'beepbeep-ai-alt-text-generator' ),
					number_format_i18n( $bbai_daily_covered ),
					number_format_i18n( $bbai_daily_total )
				) ); ?></strong>
			<p><?php esc_html_e( 'Keep coverage at 100% as you upload.', 'beepbeep-ai-alt-text-generator' ); ?></p>
			<a class="bbai-daily-btn bbai-daily-btn--secondary" href="#" data-action="show-upgrade-modal"><span aria-hidden="true">⚡</span><?php esc_html_e( 'Automate new uploads', 'beepbeep-ai-alt-text-generator' ); ?></a>
		</article>

		<article class="bbai-daily-insight bbai-daily-insight--time bbai-daily-card">
			<div class="bbai-daily-insight-head">
				<div class="bbai-daily-insight-icon" aria-hidden="true">◷</div>
				<div>
					<p class="bbai-daily-eyebrow"><?php esc_html_e( 'Time saved', 'beepbeep-ai-alt-text-generator' ); ?></p>
				</div>
			</div>
				<h3><?php echo esc_html( sprintf(
					/* translators: %s: estimated minutes saved. */
					_n( '%s min saved', '%s mins saved', $bbai_daily_time_saved, 'beepbeep-ai-alt-text-generator' ),
					number_format_i18n( $bbai_daily_time_saved )
				) ); ?></h3>
			<p><?php esc_html_e( 'Manual ALT writing avoided.', 'beepbeep-ai-alt-text-generator' ); ?></p>
			<strong><?php esc_html_e( 'Estimated from 2 mins per image.', 'beepbeep-ai-alt-text-generator' ); ?></strong>
			<p><?php esc_html_e( 'Turn on automation to keep saving time.', 'beepbeep-ai-alt-text-generator' ); ?></p>
			<a class="bbai-daily-btn bbai-daily-btn--secondary" href="#" data-action="show-upgrade-modal"><span aria-hidden="true">⚡</span><?php esc_html_e( 'Enable automation', 'beepbeep-ai-alt-text-generator' ); ?></a>
		</article>

		<article class="bbai-daily-insight bbai-daily-insight--seo bbai-daily-card">
			<div class="bbai-daily-insight-head">
				<div class="bbai-daily-insight-icon" aria-hidden="true">▟</div>
				<div>
					<p class="bbai-daily-eyebrow"><?php esc_html_e( 'SEO', 'beepbeep-ai-alt-text-generator' ); ?></p>
				</div>
			</div>
				<h3><?php echo esc_html( sprintf(
					/* translators: %s: optimised image count. */
					_n( '%s image optimised', '%s images optimised', $bbai_daily_optimised, 'beepbeep-ai-alt-text-generator' ),
					number_format_i18n( $bbai_daily_optimised )
				) ); ?></h3>
				<p><?php esc_html_e( 'Search engines can understand more of your media.', 'beepbeep-ai-alt-text-generator' ); ?></p>
				<strong><?php echo esc_html( sprintf(
					/* translators: %s: search-ready image count. */
					_n( '%s search-ready image', '%s search-ready images', $bbai_daily_optimised, 'beepbeep-ai-alt-text-generator' ),
					number_format_i18n( $bbai_daily_optimised )
				) ); ?></strong>
			<p><?php esc_html_e( 'Keep future uploads SEO-ready automatically.', 'beepbeep-ai-alt-text-generator' ); ?></p>
			<a class="bbai-daily-btn bbai-daily-btn--secondary" href="#" data-action="show-upgrade-modal"><span aria-hidden="true">⚡</span><?php esc_html_e( 'Automate future images', 'beepbeep-ai-alt-text-generator' ); ?></a>
		</article>
	</section>

	<footer class="bbai-daily-footer" aria-label="<?php echo esc_attr__( 'Dashboard help', 'beepbeep-ai-alt-text-generator' ); ?>">
		<strong><?php esc_html_e( 'Thank you for choosing BeepBeep AI. 💙', 'beepbeep-ai-alt-text-generator' ); ?></strong>
		<p><?php esc_html_e( 'Need help? Check our documentation or contact support.', 'beepbeep-ai-alt-text-generator' ); ?></p>
	</footer>

	<div class="bbai-legacy-dashboard-runtime" hidden aria-hidden="true">
		<?php
		$bbai_logged_in_hero_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-logged-in-hero.php';
		if ( is_readable( $bbai_logged_in_hero_partial ) ) {
			require $bbai_logged_in_hero_partial;
		}
		?>
	</div>
</section>
