<?php
/**
 * BBAI_Upgrade_State — central adaptive upgrade state resolver.
 *
 * Single source of truth for every upgrade prompt in the plugin.
 * Pass site + usage data, receive a fully-resolved panel config
 * ready to hand to upgrade-panel.php.
 *
 * @package BeepBeep_AI_Alt_Text_Generator
 * @since   6.3.0
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves the adaptive upgrade panel state.
 */
class BBAI_Upgrade_State {

	// ── State constants ──────────────────────────────────────────
	const STATE_BLOCKED  = 'blocked';   // 0 credits left, not pro.
	const STATE_LOW      = 'low_credits'; // >80% credits used, not pro.
	const STATE_SUCCESS  = 'success';   // 100% coverage, not pro (soft upsell).
	const STATE_INACTIVE = 'inactive';  // No scan in 7+ days, coverage < 100%.
	const STATE_DEFAULT  = 'default';   // Free plan, no specific urgency.
	const STATE_HIDDEN   = 'hidden';    // Nothing to show.

	// ── Thresholds ───────────────────────────────────────────────
	const INACTIVE_DAYS  = 7;
	const LOW_CREDIT_PCT = 80;

	/**
	 * Resolve upgrade panel state from usage and coverage data.
	 *
	 * @param array $args {
	 *     @type int    $used          Credits consumed this cycle.
	 *     @type int    $limit         Total credit allowance (>= 1).
	 *     @type int    $remaining     Credits remaining (derived if omitted).
	 *     @type int    $coverage_pct  ALT coverage 0–100.
	 *     @type int    $total_images  Total images in the media library.
	 *     @type int    $last_scan_ts  Unix timestamp of last scan (0 = unknown).
	 *     @type bool   $is_pro        True if user is on growth/pro tier.
	 *     @type bool   $is_agency     True if user is on agency tier.
	 *     @type int    $days_reset    Days until credit cycle resets.
	 *     @type string $upgrade_url   URL to pricing / upgrade page.
	 *     @type string $context       'dashboard'|'library'|'analytics'|'usage'.
	 * }
	 *
	 * @return array {
	 *     @type bool        $visible   Whether the panel should be rendered.
	 *     @type string      $state     One of the STATE_* constants.
	 *     @type string      $tone      'urgent'|'warning'|'healthy'|'neutral'.
	 *     @type string      $headline  Short panel headline.
	 *     @type string      $body      1–2 sentence context.
	 *     @type array       $primary   ['label','action','href','target'].
	 *     @type array|null  $secondary Same shape as $primary, or null.
	 * }
	 */
	public static function resolve( array $args ): array {

		$used         = max( 0, (int) ( $args['used']         ?? 0 ) );
		$limit        = max( 1, (int) ( $args['limit']        ?? 50 ) );
		$remaining    = max( 0, (int) ( $args['remaining']    ?? ( $limit - $used ) ) );
		$coverage_pct = max( 0, min( 100, (int) ( $args['coverage_pct']  ?? 0 ) ) );
		$total_images = max( 0, (int) ( $args['total_images'] ?? 0 ) );
		$last_scan_ts = max( 0, (int) ( $args['last_scan_ts'] ?? 0 ) );
		$is_pro       = ! empty( $args['is_pro'] );
		$is_agency    = ! empty( $args['is_agency'] );
		$days_reset   = max( 0, (int) ( $args['days_reset']   ?? 0 ) );
		$upgrade_url  = (string) ( $args['upgrade_url']       ?? '' );
		$context      = (string) ( $args['context']           ?? 'default' );

		$used_pct        = (int) round( ( $used / $limit ) * 100 );
		$days_since_scan = $last_scan_ts > 0
			? (int) floor( ( time() - $last_scan_ts ) / DAY_IN_SECONDS )
			: -1;

		// Agency users always get the hidden panel — full access, nothing to upsell.
		if ( $is_agency ) {
			return self::hidden();
		}

		// ── State resolution (priority order) ────────────────────
		if ( $remaining <= 0 && ! $is_pro ) {
			$state = self::STATE_BLOCKED;

		} elseif ( $used_pct >= self::LOW_CREDIT_PCT && ! $is_pro ) {
			$state = self::STATE_LOW;

		} elseif ( $coverage_pct >= 100 && $total_images > 0 ) {
			// Pro users at 100% coverage need no upgrade prompt.
			return $is_pro ? self::hidden() : self::build( self::STATE_SUCCESS, [] );

		} elseif (
			$days_since_scan >= self::INACTIVE_DAYS
			&& $coverage_pct < 100
			&& ! $is_pro
		) {
			$state = self::STATE_INACTIVE;

		} elseif ( $is_pro ) {
			// Pro user in a normal working state — nothing to show.
			return self::hidden();

		} else {
			$state = self::STATE_DEFAULT;
		}

		return self::build(
			$state,
			[
				'used'            => $used,
				'limit'           => $limit,
				'remaining'       => $remaining,
				'used_pct'        => $used_pct,
				'coverage_pct'    => $coverage_pct,
				'days_reset'      => $days_reset,
				'days_since_scan' => $days_since_scan,
				'upgrade_url'     => $upgrade_url ?: 'https://beepbeep.ai/pricing',
				'context'         => $context,
			]
		);
	}

	// ── Private helpers ──────────────────────────────────────────

	/** @return array */
	private static function hidden(): array {
		return [ 'visible' => false, 'state' => self::STATE_HIDDEN ];
	}

	/**
	 * Build fully-resolved panel config for a given state.
	 *
	 * @param string $state One of the STATE_* constants.
	 * @param array  $data  Derived numeric/string values.
	 * @return array
	 */
	private static function build( string $state, array $data ): array {

		$upgrade_url = ! empty( $data['upgrade_url'] ) ? $data['upgrade_url'] : 'https://beepbeep.ai/pricing';

		// Reusable CTAs (all states share these).
		$enable_auto = [
			'label'  => __( 'Enable automatic optimisation', 'beepbeep-ai-alt-text-generator' ),
			'action' => 'show-upgrade-modal',
			'href'   => '#',
			'target' => '',
		];

		$buy_credits = [
			'label'  => __( 'Buy extra credits', 'beepbeep-ai-alt-text-generator' ),
			'action' => '',
			'href'   => $upgrade_url,
			'target' => '_blank',
		];

		switch ( $state ) {

			// ── Blocked: 0 credits, free plan ───────────────────
			case self::STATE_BLOCKED:
				return [
					'visible'   => true,
					'state'     => $state,
					'tone'      => 'urgent',
					'headline'  => __( 'New images are not being optimised', 'beepbeep-ai-alt-text-generator' ),
					'body'      => sprintf(
						/* translators: %d: monthly credit limit */
						__( "You've used all %d credits this month. New uploads won't receive ALT text until your allowance resets — or you upgrade.", 'beepbeep-ai-alt-text-generator' ),
						(int) ( $data['limit'] ?? 50 )
					),
					'primary'   => $enable_auto,
					'secondary' => $buy_credits,
				];

			// ── Low credits: >80 % used, free plan ──────────────
			case self::STATE_LOW:
				return [
					'visible'   => true,
					'state'     => $state,
					'tone'      => 'warning',
					'headline'  => __( 'Running low on credits', 'beepbeep-ai-alt-text-generator' ),
					'body'      => sprintf(
						/* translators: 1: credits used, 2: credit limit */
						__( "You've used %1\$d of %2\$d credits this month. Keep generating without interruption by enabling automatic optimisation.", 'beepbeep-ai-alt-text-generator' ),
						(int) ( $data['used']  ?? 0 ),
						(int) ( $data['limit'] ?? 50 )
					),
					'primary'   => $enable_auto,
					'secondary' => $buy_credits,
				];

			// ── Success: 100 % coverage, free plan ──────────────
			case self::STATE_SUCCESS:
				return [
					'visible'   => true,
					'state'     => $state,
					'tone'      => 'healthy',
					'headline'  => __( "You're fully optimised", 'beepbeep-ai-alt-text-generator' ),
					'body'      => __( 'Every image has ALT text. Enable automatic optimisation to keep your coverage complete as you upload new images — no manual scanning needed.', 'beepbeep-ai-alt-text-generator' ),
					'primary'   => $enable_auto,
					'secondary' => null,
				];

			// ── Inactive: no scan in 7+ days ────────────────────
			case self::STATE_INACTIVE:
				return [
					'visible'   => true,
					'state'     => $state,
					'tone'      => 'warning',
					'headline'  => __( 'New images may be missing ALT text', 'beepbeep-ai-alt-text-generator' ),
					'body'      => __( "You haven't scanned recently. New uploads aren't checked automatically — enable auto-optimisation to keep your coverage complete.", 'beepbeep-ai-alt-text-generator' ),
					'primary'   => $enable_auto,
					'secondary' => [
						'label'  => __( 'Scan now', 'beepbeep-ai-alt-text-generator' ),
						'action' => 'scan-opportunity',
						'href'   => '#',
						'target' => '',
					],
				];

			// ── Default: free plan, no specific urgency ──────────
			default:
				return [
					'visible'   => true,
					'state'     => $state,
					'tone'      => 'neutral',
					'headline'  => __( 'Automate your image optimisation', 'beepbeep-ai-alt-text-generator' ),
					'body'      => __( 'Process every new image as you upload it. No manual scanning, no missed images, no extra work.', 'beepbeep-ai-alt-text-generator' ),
					'primary'   => $enable_auto,
					'secondary' => null,
				];
		}
	}
}
