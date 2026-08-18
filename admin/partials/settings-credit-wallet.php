<?php
/**
 * Settings Account — OpptiAI Credit Wallet (locked Titles parity).
 *
 * Settings / Account only (not Dashboard). Signed-in Free/paid only.
 * Guests never see this partial (parent settings-tab gates it).
 *
 * @package BeepBeep_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Signed-in only — never render for guests / logged-out Settings.
$bbai_wallet_signed_in = ( isset( $bbai_is_authenticated ) && $bbai_is_authenticated )
	|| ( isset( $bbai_has_license ) && $bbai_has_license );
if ( ! $bbai_wallet_signed_in ) {
	return;
}

$bbai_wallet_usage = is_array( $bbai_usage_box ?? null ) ? $bbai_usage_box : [];
$bbai_wallet_raw   = [];
if ( class_exists( '\BeepBeepAI\AltTextGenerator\Usage_Tracker' ) ) {
	$bbai_wallet_raw = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_local_usage_snapshot();
	if ( ! is_array( $bbai_wallet_raw ) ) {
		$bbai_wallet_raw = [];
	}
}

// Prefer live GET /api/usage figures — never invent a Free 25 limit.
$bbai_wallet_used = max( 0, (int) ( $bbai_used_credits ?? $bbai_wallet_usage['used'] ?? $bbai_wallet_raw['used'] ?? 0 ) );
$bbai_wallet_limit_candidate = $bbai_total_credits ?? $bbai_wallet_usage['limit'] ?? $bbai_wallet_raw['limit'] ?? null;
$bbai_wallet_has_limit       = null !== $bbai_wallet_limit_candidate && '' !== $bbai_wallet_limit_candidate && (int) $bbai_wallet_limit_candidate > 0;
$bbai_wallet_limit           = $bbai_wallet_has_limit ? (int) $bbai_wallet_limit_candidate : 0;
if ( isset( $bbai_wallet_usage['remaining'] ) || isset( $bbai_wallet_raw['remaining'] ) ) {
	$bbai_wallet_remain = max( 0, (int) ( $bbai_wallet_usage['remaining'] ?? $bbai_wallet_raw['remaining'] ?? 0 ) );
} elseif ( $bbai_wallet_has_limit ) {
	$bbai_wallet_remain = max( 0, $bbai_wallet_limit - $bbai_wallet_used );
} else {
	$bbai_wallet_remain = 0;
}
$bbai_wallet_pct = $bbai_wallet_has_limit
	? (int) min( 100, round( ( 100 * $bbai_wallet_used ) / $bbai_wallet_limit ) )
	: 0;
$bbai_wallet_reset = (string) ( $bbai_reset_label ?? $bbai_wallet_usage['reset_date'] ?? __( 'next month', 'beepbeep-ai-alt-text-generator' ) );

$bbai_wallet_plan_slug = strtolower( (string) ( $bbai_plan_normalized ?? $bbai_wallet_usage['plan'] ?? $bbai_wallet_raw['plan'] ?? 'free' ) );
if ( 'growth' === $bbai_wallet_plan_slug ) {
	$bbai_wallet_plan_slug = 'pro';
}
$bbai_wallet_is_starter = ! empty( $bbai_is_starter ) || in_array( $bbai_wallet_plan_slug, [ 'starter', 'basic' ], true );
$bbai_wallet_is_growth  = ! empty( $bbai_is_pro ) || in_array( $bbai_wallet_plan_slug, [ 'pro', 'growth' ], true );
$bbai_wallet_is_agency  = ! empty( $bbai_is_agency ) || 'agency' === $bbai_wallet_plan_slug;
$bbai_wallet_is_paid    = $bbai_wallet_is_starter || $bbai_wallet_is_growth || $bbai_wallet_is_agency || ! empty( $bbai_has_paid_plan );

if ( $bbai_wallet_is_agency ) {
	$bbai_wallet_plan_chip     = __( 'Agency', 'beepbeep-ai-alt-text-generator' );
	$bbai_wallet_service_title = __( 'OpptiAI Agency service', 'beepbeep-ai-alt-text-generator' );
} elseif ( $bbai_wallet_is_growth ) {
	$bbai_wallet_plan_chip     = __( 'Growth', 'beepbeep-ai-alt-text-generator' );
	$bbai_wallet_service_title = __( 'OpptiAI Growth service', 'beepbeep-ai-alt-text-generator' );
} elseif ( $bbai_wallet_is_starter ) {
	$bbai_wallet_plan_chip     = __( 'Starter', 'beepbeep-ai-alt-text-generator' );
	$bbai_wallet_service_title = __( 'OpptiAI Starter service', 'beepbeep-ai-alt-text-generator' );
} else {
	$bbai_wallet_plan_chip     = __( 'Free', 'beepbeep-ai-alt-text-generator' );
	$bbai_wallet_service_title = __( 'OpptiAI Free service', 'beepbeep-ai-alt-text-generator' );
}

$bbai_wallet_service_desc = $bbai_wallet_has_limit
	? sprintf(
		/* translators: %d: monthly shared credit limit from GET /api/usage. */
		__( '%d AI service credits per cycle · shared across your OpptiAI plugins · usable manually, in bulk, or with Autopilot.', 'beepbeep-ai-alt-text-generator' ),
		$bbai_wallet_limit
	)
	: __( 'AI service credits shared across your OpptiAI plugins · usable manually, in bulk, or with Autopilot.', 'beepbeep-ai-alt-text-generator' );

// Same usage_by_feature / feature_usage split Titles already reads.
$bbai_wallet_usage_source = [];
foreach ( [ 'usage_by_feature', 'feature_usage', 'usage_by_plugin', 'plugin_usage', 'usage_breakdown', 'credit_usage' ] as $bbai_wallet_key ) {
	if ( isset( $bbai_wallet_usage[ $bbai_wallet_key ] ) && is_array( $bbai_wallet_usage[ $bbai_wallet_key ] ) ) {
		$bbai_wallet_usage_source = $bbai_wallet_usage[ $bbai_wallet_key ];
		break;
	}
	if ( isset( $bbai_wallet_raw[ $bbai_wallet_key ] ) && is_array( $bbai_wallet_raw[ $bbai_wallet_key ] ) ) {
		$bbai_wallet_usage_source = $bbai_wallet_raw[ $bbai_wallet_key ];
		break;
	}
}

$bbai_wallet_aliases = [
	'alt'             => 'alt_text',
	'alttext'         => 'alt_text',
	'image_alt'       => 'alt_text',
	'image_alt_text'  => 'alt_text',
	'titles'          => 'title_meta',
	'title'           => 'title_meta',
	'titles_meta'     => 'title_meta',
	'titles_and_meta' => 'title_meta',
	'linking'         => 'internal_linking',
	'internal_link'   => 'internal_linking',
	'internal_links'  => 'internal_linking',
	'oppti_linking'   => 'internal_linking',
	'schema_markup'   => 'schema',
	'rich_snippets'   => 'schema',
];

$bbai_wallet_split = [];
foreach ( $bbai_wallet_usage_source as $bbai_wallet_feature_key => $bbai_wallet_feature_value ) {
	if ( is_int( $bbai_wallet_feature_key ) && is_array( $bbai_wallet_feature_value ) ) {
		$bbai_wallet_feature_key = $bbai_wallet_feature_value['feature_type']
			?? $bbai_wallet_feature_value['feature']
			?? $bbai_wallet_feature_value['plugin_id']
			?? $bbai_wallet_feature_value['plugin']
			?? $bbai_wallet_feature_value['id']
			?? $bbai_wallet_feature_value['name']
			?? 'other';
	}
	$bbai_wallet_feature_id = sanitize_key( (string) $bbai_wallet_feature_key );
	$bbai_wallet_feature_id = $bbai_wallet_aliases[ $bbai_wallet_feature_id ] ?? $bbai_wallet_feature_id;
	$bbai_wallet_feature_used = is_array( $bbai_wallet_feature_value )
		? ( $bbai_wallet_feature_value['credits_used'] ?? $bbai_wallet_feature_value['used'] ?? $bbai_wallet_feature_value['credits'] ?? $bbai_wallet_feature_value['count'] ?? 0 )
		: $bbai_wallet_feature_value;
	$bbai_wallet_split[ $bbai_wallet_feature_id ] = ( $bbai_wallet_split[ $bbai_wallet_feature_id ] ?? 0 ) + max( 0, (int) $bbai_wallet_feature_used );
}

if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
// WP.org folder opptiai-titles; main file confirmed beepbeep-titles.php (same as Dashboard cross-sell).
$bbai_wallet_titles_file   = 'opptiai-titles/beepbeep-titles.php';
$bbai_wallet_titles_active = function_exists( 'is_plugin_active' ) && is_plugin_active( $bbai_wallet_titles_file );
$bbai_wallet_titles_admin  = admin_url( 'admin.php?page=beepbeep-titles' );

$bbai_wallet_icon = static function ( string $name ): string {
	$paths = [
		'crown'  => '<path d="m2 19 2-11 5 5 3-7 3 7 5-5 2 11H2Z"/><path d="M2 21h20"/>',
		'shield' => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6l-8-3Z"/>',
		'info'   => '<circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v5h1"/>',
		'image'  => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/>',
		'edit'   => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/>',
		'link'   => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
		'trend'  => '<path d="M3 17 9 11l4 4 8-8"/><path d="M14 7h7v7"/>',
	];
	$body = $paths[ $name ] ?? '';
	return sprintf(
		'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
		$body
	);
};

// Roster: this plugin first, then Titles, Internal Linking, Schema.
$bbai_wallet_catalog = [
	[
		'id'        => 'alt_text',
		'label'     => __( 'Image ALT Text', 'beepbeep-ai-alt-text-generator' ),
		'icon'      => 'image',
		'class'     => 'bbai-wallet-feature--alt',
		'current'   => true,
		'installed' => true,
	],
	[
		'id'        => 'title_meta',
		'label'     => __( 'Titles & Meta Descriptions', 'beepbeep-ai-alt-text-generator' ),
		'icon'      => 'edit',
		'class'     => 'bbai-wallet-feature--titles',
		'current'   => false,
		'installed' => $bbai_wallet_titles_active,
	],
	[
		'id'        => 'internal_linking',
		'label'     => __( 'Internal Linking', 'beepbeep-ai-alt-text-generator' ),
		'icon'      => 'link',
		'class'     => 'bbai-wallet-feature--linking',
		'current'   => false,
		'installed' => false,
	],
	[
		'id'        => 'schema',
		'label'     => __( 'Schema & Rich Snippets', 'beepbeep-ai-alt-text-generator' ),
		'icon'      => 'trend',
		'class'     => 'bbai-wallet-feature--schema',
		'current'   => false,
		'installed' => false,
	],
];

$bbai_wallet_attributed = 0;
$bbai_wallet_rows       = [];
$bbai_wallet_has_split  = ! empty( $bbai_wallet_split );
foreach ( $bbai_wallet_catalog as $bbai_wallet_cat ) {
	$row_used = (int) ( $bbai_wallet_split[ $bbai_wallet_cat['id'] ] ?? 0 );
	$bbai_wallet_attributed += $row_used;
	$bbai_wallet_cat['used'] = $row_used;
	$bbai_wallet_rows[]      = $bbai_wallet_cat;
}

// Itemise only when attributed credits reconcile with total used — never invent splits.
$bbai_wallet_show_breakdown = $bbai_wallet_used > 0 && $bbai_wallet_has_split && $bbai_wallet_attributed >= $bbai_wallet_used;
$bbai_wallet_progress_mod   = $bbai_wallet_pct > 90 ? 'is-danger' : ( $bbai_wallet_pct > 75 ? 'is-warn' : 'is-ok' );
$bbai_wallet_plan_mod       = $bbai_wallet_is_paid ? 'bbai-wallet-plan--paid' : '';
?>

<section class="bbai-wallet-plan <?php echo esc_attr( $bbai_wallet_plan_mod ); ?>" aria-label="<?php esc_attr_e( 'OpptiAI service plan', 'beepbeep-ai-alt-text-generator' ); ?>">
	<div class="bbai-wallet-plan__row">
		<div class="bbai-wallet-plan__icon" aria-hidden="true">
			<?php echo $bbai_wallet_icon( $bbai_wallet_is_paid ? 'crown' : 'shield' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?>
		</div>
		<div class="bbai-wallet-plan__copy">
			<div class="bbai-wallet-plan__name">
				<span><?php echo esc_html( $bbai_wallet_service_title ); ?></span>
				<span class="bbai-wallet-chip<?php echo $bbai_wallet_is_paid ? ' bbai-wallet-chip--paid' : ''; ?>"><?php echo esc_html( $bbai_wallet_plan_chip ); ?></span>
			</div>
			<p class="bbai-wallet-plan__desc"><?php echo esc_html( $bbai_wallet_service_desc ); ?></p>
		</div>
		<div class="bbai-wallet-plan__actions">
			<?php if ( $bbai_wallet_is_growth || $bbai_wallet_is_agency ) : ?>
				<button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" data-action="manage-subscription">
					<?php esc_html_e( 'Manage billing', 'beepbeep-ai-alt-text-generator' ); ?>
				</button>
			<?php else : ?>
				<button type="button" class="bbai-btn bbai-btn-primary bbai-btn-sm" data-action="show-upgrade-modal" data-bbai-pricing-variant="pro">
					<?php esc_html_e( 'View Growth plan', 'beepbeep-ai-alt-text-generator' ); ?>
				</button>
			<?php endif; ?>
		</div>
	</div>
	<div class="bbai-wallet-plan__usage">
		<div class="bbai-wallet-plan__usage-head">
			<span><?php esc_html_e( 'This billing cycle', 'beepbeep-ai-alt-text-generator' ); ?></span>
			<span class="bbai-wallet-plan__usage-num">
				<?php
				if ( $bbai_wallet_has_limit ) {
					echo esc_html(
						sprintf(
							/* translators: 1: credits used, 2: monthly credit limit from GET /api/usage. */
							__( '%1$s / %2$s AI service credits used', 'beepbeep-ai-alt-text-generator' ),
							number_format_i18n( $bbai_wallet_used ),
							number_format_i18n( $bbai_wallet_limit )
						)
					);
				} else {
					echo esc_html(
						sprintf(
							/* translators: %s: credits used. */
							__( '%s AI service credits used', 'beepbeep-ai-alt-text-generator' ),
							number_format_i18n( $bbai_wallet_used )
						)
					);
				}
				?>
			</span>
		</div>
		<?php if ( $bbai_wallet_has_limit ) : ?>
		<div class="bbai-wallet-progress bbai-wallet-progress--<?php echo esc_attr( $bbai_wallet_progress_mod ); ?>" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( (string) $bbai_wallet_pct ); ?>">
			<span style="width:<?php echo esc_attr( (string) $bbai_wallet_pct ); ?>%;"></span>
		</div>
		<?php endif; ?>
		<div class="bbai-wallet-plan__usage-foot">
			<?php if ( $bbai_wallet_has_limit ) : ?>
			<span><?php echo esc_html( sprintf(
				/* translators: %s: remaining credits. */
				__( '%s remaining', 'beepbeep-ai-alt-text-generator' ),
				number_format_i18n( $bbai_wallet_remain )
			) ); ?></span>
			<?php endif; ?>
			<span><?php echo esc_html( sprintf(
				/* translators: %s: reset date from usage payload. */
				__( 'Resets %s', 'beepbeep-ai-alt-text-generator' ),
				$bbai_wallet_reset
			) ); ?></span>
		</div>
	</div>
</section>

<section class="bbai-wallet-card" aria-labelledby="bbai-wallet-card-title">
	<div class="bbai-wallet-card__head">
		<div>
			<div class="bbai-wallet-card__eyebrow"><?php esc_html_e( 'OpptiAI Credit Wallet', 'beepbeep-ai-alt-text-generator' ); ?></div>
			<h3 id="bbai-wallet-card-title" class="bbai-wallet-card__title"><?php esc_html_e( 'Credit usage', 'beepbeep-ai-alt-text-generator' ); ?></h3>
		</div>
		<div class="bbai-wallet-card__total">
			<strong><?php echo esc_html( number_format_i18n( $bbai_wallet_used ) ); ?></strong>
			<?php if ( $bbai_wallet_has_limit ) : ?>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: monthly credit limit. */
						__( '/ %s credits used', 'beepbeep-ai-alt-text-generator' ),
						number_format_i18n( $bbai_wallet_limit )
					)
				);
				?>
				<span class="bbai-wallet-card__dot" aria-hidden="true">·</span>
				<span><?php echo esc_html( sprintf(
					/* translators: %s: remaining credits. */
					__( '%s remaining', 'beepbeep-ai-alt-text-generator' ),
					number_format_i18n( $bbai_wallet_remain )
				) ); ?></span>
			<?php else : ?>
				<?php echo esc_html__( ' credits used', 'beepbeep-ai-alt-text-generator' ); ?>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( ! $bbai_wallet_show_breakdown && $bbai_wallet_has_limit ) : ?>
		<div class="bbai-wallet-card__shared">
			<div class="bbai-wallet-card__shared-head">
				<span class="bbai-wallet-card__eyebrow"><?php esc_html_e( 'Shared usage', 'beepbeep-ai-alt-text-generator' ); ?></span>
				<span><?php echo esc_html( number_format_i18n( $bbai_wallet_used ) . ' / ' . number_format_i18n( $bbai_wallet_limit ) ); ?></span>
			</div>
			<div class="bbai-wallet-progress bbai-wallet-progress--<?php echo esc_attr( $bbai_wallet_progress_mod ); ?>" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( (string) $bbai_wallet_pct ); ?>">
				<span style="width:<?php echo esc_attr( (string) $bbai_wallet_pct ); ?>%;"></span>
			</div>
		</div>
	<?php endif; ?>

	<div class="bbai-wallet-card__rows">
		<?php foreach ( $bbai_wallet_rows as $bbai_wallet_row ) : ?>
			<?php
			$row_id = (string) $bbai_wallet_row['id'];
			// Numbers only when a real split reconciles — never fake per-plugin counts.
			// ALT Text + Titles can show used/% when billed; IL/Schema stay roster-only.
			$bbai_wallet_billed     = $bbai_wallet_show_breakdown && in_array( $row_id, [ 'alt_text', 'title_meta' ], true );
			$bbai_wallet_has_number = $bbai_wallet_billed;
			$bbai_wallet_row_pct    = ( $bbai_wallet_has_number && $bbai_wallet_used > 0 )
				? (int) min( 100, round( ( 100 * $bbai_wallet_row['used'] ) / $bbai_wallet_used ) )
				: 0;
			$bbai_wallet_row_classes = 'bbai-wallet-feature ' . $bbai_wallet_row['class'];
			if ( ! empty( $bbai_wallet_row['current'] ) ) {
				$bbai_wallet_row_classes .= ' bbai-wallet-feature--current';
			}
			if ( empty( $bbai_wallet_row['installed'] ) && empty( $bbai_wallet_row['current'] ) ) {
				$bbai_wallet_row_classes .= ' bbai-wallet-feature--uninstalled';
			}
			?>
			<div class="<?php echo esc_attr( $bbai_wallet_row_classes ); ?>">
				<div class="bbai-wallet-feature__icon" aria-hidden="true">
					<?php echo $bbai_wallet_icon( (string) $bbai_wallet_row['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<div class="bbai-wallet-feature__main">
					<div class="bbai-wallet-feature__label">
						<span><?php echo esc_html( (string) $bbai_wallet_row['label'] ); ?></span>
						<?php if ( ! empty( $bbai_wallet_row['current'] ) ) : ?>
							<span class="bbai-wallet-chip"><?php esc_html_e( 'This plugin', 'beepbeep-ai-alt-text-generator' ); ?></span>
						<?php elseif ( 'title_meta' === $row_id && ! empty( $bbai_wallet_row['installed'] ) ) : ?>
							<a class="bbai-wallet-feature__open" href="<?php echo esc_url( $bbai_wallet_titles_admin ); ?>"><?php esc_html_e( 'Open', 'beepbeep-ai-alt-text-generator' ); ?></a>
						<?php else : ?>
							<span class="bbai-wallet-feature__muted"><?php esc_html_e( 'Not installed', 'beepbeep-ai-alt-text-generator' ); ?></span>
						<?php endif; ?>
					</div>
					<?php if ( $bbai_wallet_has_number ) : ?>
						<div
							class="bbai-wallet-feature__track"
							role="progressbar"
							aria-label="<?php echo esc_attr( sprintf(
								/* translators: 1: plugin label, 2: credits used by plugin, 3: percent of used credits. */
								__( '%1$s: %2$s credits, %3$s%% of used credits', 'beepbeep-ai-alt-text-generator' ),
								(string) $bbai_wallet_row['label'],
								number_format_i18n( $bbai_wallet_row['used'] ),
								(string) $bbai_wallet_row_pct
							) ); ?>"
							aria-valuemin="0"
							aria-valuemax="100"
							aria-valuenow="<?php echo esc_attr( (string) $bbai_wallet_row_pct ); ?>"
						>
							<span style="width:<?php echo esc_attr( (string) $bbai_wallet_row_pct ); ?>%;"></span>
						</div>
					<?php endif; ?>
				</div>
				<?php if ( $bbai_wallet_has_number ) : ?>
					<div class="bbai-wallet-feature__value">
						<strong><?php echo esc_html( number_format_i18n( $bbai_wallet_row['used'] ) ); ?></strong>
						<span><?php echo esc_html( (string) $bbai_wallet_row_pct ); ?>%</span>
					</div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="bbai-wallet-card__note">
		<span class="bbai-wallet-card__note-icon" aria-hidden="true"><?php echo $bbai_wallet_icon( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<span>
			<?php
			echo esc_html(
				$bbai_wallet_show_breakdown
					? sprintf(
						/* translators: %s: credit reset date. */
						__( 'One monthly credit balance shared across every OpptiAI solution on this site. The breakdown above shows which plugin consumed each credit · resets %s.', 'beepbeep-ai-alt-text-generator' ),
						$bbai_wallet_reset
					)
					: sprintf(
						/* translators: %s: credit reset date. */
						__( "One monthly credit balance shared across every OpptiAI solution on this site. Per-plugin credits aren't itemised yet — the total above is shared across these plugins · resets %s.", 'beepbeep-ai-alt-text-generator' ),
						$bbai_wallet_reset
					)
			);
			?>
		</span>
	</div>
</section>
