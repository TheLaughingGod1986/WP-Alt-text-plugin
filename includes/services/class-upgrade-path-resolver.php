<?php
/**
 * Single ladder for monetisation CTAs: Trial → Free account → Growth → Agency.
 *
 * All upgrade surfaces should derive primary/secondary actions from this resolver
 * so the UI never shows competing next steps (e.g. signup + paid upgrade for trial).
 *
 * @package BeepBeep_AI
 */

namespace BeepBeepAI\AltTextGenerator\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Upgrade_Path_Resolver {

	public const STEP_TRIAL  = 'trial';
	public const STEP_FREE   = 'free';
	public const STEP_GROWTH = 'growth';
	public const STEP_AGENCY = 'agency';

	/**
	 * Resolve ladder step and monetisation metadata.
	 *
	 * @param array<string, mixed> $input Keys: is_guest_trial (bool), plan_slug (string, optional when guest).
	 * @return array<string, mixed> Serializable shape safe for wp_localize_script / JSON.
	 */
	public static function resolve( array $input ): array {
		$guest = ! empty( $input['is_guest_trial'] );
		if ( $guest ) {
			return self::trial_row();
		}

		$slug = strtolower( (string) ( $input['plan_slug'] ?? 'free' ) );
		if ( 'agency' === $slug ) {
			return self::agency_row();
		}
		if ( in_array( $slug, [ 'pro', 'growth' ], true ) ) {
			return self::growth_row( $slug );
		}

		return self::free_row();
	}

	/**
	 * Primary + secondary actions for credit-warning banners (low / out of credits).
	 *
	 * @param array<string, mixed> $snapshot Banner snapshot (plan_slug, is_anonymous_trial, library URLs, etc.).
	 * @param string               $page_context BBAI_BANNER_CTX_*.
	 * @return array{0:?array<string,mixed>,1:?array<string,mixed>} [ primary, secondary ]
	 */
	public static function credit_banner_actions( array $snapshot, string $page_context ): array {
		if ( ! function_exists( 'bbai_copy_cta_review_usage' ) ) {
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/content/bbai-admin-copy.php';
		}

		$guest = array_key_exists( 'has_connected_account', $snapshot )
			? empty( $snapshot['has_connected_account'] )
			: ( ! empty( $snapshot['is_anonymous_trial'] ) || ! empty( $snapshot['is_trial'] ) );

		$row = self::resolve(
			[
				'is_guest_trial' => $guest,
				'plan_slug'      => (string) ( $snapshot['plan_slug'] ?? 'free' ),
			]
		);

		$library_url = (string) ( $snapshot['library_url'] ?? admin_url( 'admin.php?page=bbai-library' ) );
		$needs_review = (string) ( $snapshot['needs_review_library_url'] ?? '' );
		if ( '' === $needs_review && function_exists( 'bbai_alt_library_needs_review_url' ) ) {
			$needs_review = bbai_alt_library_needs_review_url();
		}

		$review = [
			'label'        => bbai_copy_cta_review_usage(),
			'href'         => '' !== $needs_review ? $needs_review : $library_url,
			'attributes'   => [],
		];

		if ( self::STEP_TRIAL === $row['step'] ) {
			$signup = [
				'label'      => function_exists( 'bbai_copy_cta_create_free_account' ) ? bbai_copy_cta_create_free_account() : __( 'Create free account', 'beepbeep-ai-alt-text-generator' ),
				'attributes' => [
					'data-action'                 => 'show-auth-modal',
					'data-auth-tab'               => 'register',
					'data-bbai-analytics-upgrade' => 'trial_create_account_clicked',
				],
			];
			// Trial ladder: secondary is browse-only pricing, never a paid-plan primary.
			$view_plans = [
				'label'      => function_exists( 'bbai_copy_cta_view_plans' ) ? bbai_copy_cta_view_plans() : __( 'View plans', 'beepbeep-ai-alt-text-generator' ),
				'attributes' => [
					'data-action'                 => 'show-upgrade-modal',
					'data-bbai-pricing-variant'   => 'browse',
					'data-bbai-analytics-upgrade' => 'pricing_viewed_from_trial',
				],
			];
			if ( BBAI_BANNER_CTX_LIBRARY === $page_context && function_exists( 'bbai_banner_merge_library_attrs' ) ) {
				$view_plans['attributes'] = bbai_banner_merge_library_attrs( $view_plans['attributes'] );
			}
			return [ $signup, $view_plans ];
		}

		if ( self::STEP_AGENCY === $row['step'] ) {
			$settings = (string) ( $snapshot['settings_url'] ?? admin_url( 'admin.php?page=bbai-settings' ) );
			$usage    = (string) ( $snapshot['usage_url'] ?? admin_url( 'admin.php?page=bbai-credit-usage' ) );
			$primary  = [
				'label'      => __( 'Usage & billing', 'beepbeep-ai-alt-text-generator' ),
				'href'       => $usage,
				'attributes' => [
					'data-bbai-analytics-upgrade' => 'agency_manage_usage_clicked',
				],
			];
			return [ $primary, $review ];
		}

		if ( self::STEP_GROWTH === $row['step'] ) {
			$primary = [
				'label'      => function_exists( 'bbai_copy_cta_upgrade_agency' ) ? bbai_copy_cta_upgrade_agency() : __( 'Upgrade to Agency', 'beepbeep-ai-alt-text-generator' ),
				'attributes' => [
					'data-action'                 => 'show-upgrade-modal',
					'data-bbai-pricing-variant'   => 'agency',
					'data-bbai-analytics-upgrade' => 'growth_upgrade_agency_clicked',
				],
			];
			return [ $primary, $review ];
		}

		// Logged-in free: one paid primary; secondary supports workflow without second paid CTA.
		$primary = [
			'label'      => function_exists( 'bbai_copy_cta_upgrade_growth' ) ? bbai_copy_cta_upgrade_growth() : __( 'Upgrade to Growth', 'beepbeep-ai-alt-text-generator' ),
			'attributes' => [
				'data-action'                 => 'show-upgrade-modal',
				'data-bbai-pricing-variant'   => 'growth',
				'data-bbai-analytics-upgrade' => 'free_upgrade_growth_clicked',
			],
		];
		return [ $primary, $review ];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function trial_row(): array {
		return [
			'step'             => self::STEP_TRIAL,
			'primary_kind'     => 'signup',
			'pricing_variant'  => 'browse',
			'next_paid_target' => 'growth',
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function free_row(): array {
		return [
			'step'             => self::STEP_FREE,
			'primary_kind'     => 'upgrade_growth',
			'pricing_variant'  => 'growth',
			'next_paid_target' => 'growth',
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function growth_row( string $slug ): array {
		return [
			'step'             => self::STEP_GROWTH,
			'primary_kind'     => 'upgrade_agency',
			'pricing_variant'  => 'agency',
			'next_paid_target' => 'agency',
			'plan_slug'        => $slug,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function agency_row(): array {
		return [
			'step'             => self::STEP_AGENCY,
			'primary_kind'     => 'none',
			'pricing_variant'  => 'browse',
			'next_paid_target' => '',
		];
	}
}
