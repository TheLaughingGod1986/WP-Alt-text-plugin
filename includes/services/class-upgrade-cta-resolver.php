<?php
/**
 * Single resolver for upgrade / signup CTAs by auth + plan + quota.
 *
 * Business rule: logged-out / no SaaS connection → always “Create free account” first.
 * “Upgrade to Growth” only after the site is linked and the user is on the free plan (typically quota exhausted).
 *
 * @package BeepBeep_AI
 */

declare(strict_types=1);

namespace BeepBeepAI\AltTextGenerator\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Upgrade_Cta_Resolver {

	public const STATE_LOGGED_OUT = 'logged_out';
	public const STATE_FREE       = 'free';
	public const STATE_GROWTH     = 'growth';
	public const STATE_PRO        = 'pro';

	/**
	 * Resolve CTA state for UI, modals, and JS.
	 *
	 * @param array<string, mixed> $input {
	 *   @type bool   $has_connected_account True when SaaS account is linked (Auth_State::has_connected_account).
	 *   @type string $plan_slug             Normalized plan: free|growth|pro|agency.
	 *   @type int    $credits_remaining     Optional; <=0 with connection implies exhausted free quota.
	 *   @type bool   $quota_exhausted       Optional explicit flag.
	 * }
	 * @return array<string, mixed>
	 */
	public static function resolve( array $input ): array {
		if ( ! function_exists( 'bbai_copy_cta_upgrade_growth' ) ) {
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/content/bbai-admin-copy.php';
		}

		$connected = ! empty( $input['has_connected_account'] );
		$plan      = strtolower( (string) ( $input['plan_slug'] ?? 'free' ) );
		if ( 'pro' === $plan ) {
			$plan = 'growth';
		}

		$remaining = isset( $input['credits_remaining'] ) ? max( 0, (int) $input['credits_remaining'] ) : null;
		$exhausted = ! empty( $input['quota_exhausted'] );
		if ( $connected && ! $exhausted && null !== $remaining && $remaining <= 0 && in_array( $plan, [ 'free', 'trial' ], true ) ) {
			$exhausted = true;
		}

		if ( ! $connected ) {
			return self::payload_logged_out();
		}

		if ( 'agency' === $plan ) {
			return self::payload_pro_tier();
		}

		if ( 'growth' === $plan ) {
			return self::payload_growth_tier();
		}

		// Authenticated free (or trial slug treated as pre-paid ladder).
		if ( $exhausted ) {
			return self::payload_free_exhausted();
		}

		return self::payload_free_active();
	}

	/**
	 * Flat shape for wp_localize_script (camelCase keys for JS).
	 *
	 * @param array<string, mixed> $input Same as resolve().
	 * @return array<string, mixed>
	 */
	public static function for_localize_script( array $input ): array {
		$r = self::resolve( $input );

		return [
			'state'                 => $r['state'],
			'hasConnectedAccount'   => ! empty( $input['has_connected_account'] ),
			'primaryLabel'          => $r['primary_label'],
			'primaryAction'         => $r['primary_action'],
			'secondaryLabel'        => $r['secondary_label'],
			'secondaryAction'       => $r['secondary_action'],
			'tooltipLocked'         => $r['tooltip_locked'],
			'modalMode'             => $r['modal_mode'],
			'upgradeTarget'         => $r['upgrade_target'],
			'lockedLabels'          => $r['locked_labels'],
			'modalPrimaryLabel'     => $r['modal_primary_label'],
			'modalTitleDefault'     => $r['modal_title_default'],
			'modalSubtitleDefault'  => $r['modal_subtitle_default'],
			'showCreditPack'        => $r['show_credit_pack'],
			'analytics'             => $r['analytics'],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function payload_logged_out(): array {
		$primary   = __( 'Create free account', 'beepbeep-ai-alt-text-generator' );
		$secondary = __( 'View plans', 'beepbeep-ai-alt-text-generator' );
		$tooltip   = __( 'Create a free account to unlock AI generations', 'beepbeep-ai-alt-text-generator' );

		return [
			'state'                 => self::STATE_LOGGED_OUT,
			'primary_label'         => $primary,
			'primary_action'        => 'open_signup',
			'secondary_label'       => $secondary,
			'secondary_action'      => 'open_pricing',
			'tooltip_locked'        => $tooltip,
			'modal_mode'            => 'signup_first',
			'upgrade_target'        => null,
			'locked_labels'         => [
				'generate_missing' => __( 'Create a free account to continue generating ALT text', 'beepbeep-ai-alt-text-generator' ),
				'regenerate_single' => __( 'Create a free account to unlock AI regenerations', 'beepbeep-ai-alt-text-generator' ),
				'reoptimize_all'   => __( 'Create a free account to continue improving ALT text', 'beepbeep-ai-alt-text-generator' ),
				'default'          => __( 'Create free account to continue', 'beepbeep-ai-alt-text-generator' ),
			],
			'modal_primary_label'   => $primary,
			'modal_title_default'   => __( 'Create your free account', 'beepbeep-ai-alt-text-generator' ),
			'modal_subtitle_default' => __( 'Sign up free to unlock monthly generations, then pick a plan if you need more.', 'beepbeep-ai-alt-text-generator' ),
			'show_credit_pack'      => false,
			'analytics'             => [
				'primary'  => 'create_account_clicked',
				'secondary'=> 'pricing_viewed_logged_out',
				'locked'   => 'locked_action_signup_prompt',
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function payload_free_exhausted(): array {
		$growth    = function_exists( 'bbai_copy_cta_upgrade_growth' ) ? bbai_copy_cta_upgrade_growth() : __( 'Upgrade to Growth', 'beepbeep-ai-alt-text-generator' );
		$secondary = __( 'View plans', 'beepbeep-ai-alt-text-generator' );
		$tooltip   = __( 'Upgrade to Growth to continue generating', 'beepbeep-ai-alt-text-generator' );

		return [
			'state'                 => self::STATE_FREE,
			'primary_label'         => $growth,
			'primary_action'        => 'open_growth_upgrade',
			'secondary_label'       => $secondary,
			'secondary_action'      => 'open_pricing',
			'tooltip_locked'        => $tooltip,
			'modal_mode'            => 'growth_upgrade',
			'upgrade_target'        => 'growth',
			'locked_labels'         => [
				'generate_missing' => __( 'Upgrade to continue generating ALT text', 'beepbeep-ai-alt-text-generator' ),
				'regenerate_single' => __( 'Buy more credits to regenerate ALT text', 'beepbeep-ai-alt-text-generator' ),
				'reoptimize_all'   => __( 'Upgrade to continue improving ALT text', 'beepbeep-ai-alt-text-generator' ),
				'default'          => $growth,
			],
			'modal_primary_label'   => $growth,
			'modal_title_default'   => __( 'Continue improving your library', 'beepbeep-ai-alt-text-generator' ),
			'modal_subtitle_default' => __( 'You’ve reached this month’s free limit. Upgrade for more capacity or compare plans.', 'beepbeep-ai-alt-text-generator' ),
			'show_credit_pack'      => true,
			'analytics'             => [
				'primary'  => 'upgrade_growth_clicked',
				'secondary'=> 'pricing_viewed_authenticated',
				'locked'   => 'locked_action_upgrade_growth',
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function payload_free_active(): array {
		$view = __( 'View plans', 'beepbeep-ai-alt-text-generator' );

		return [
			'state'                 => self::STATE_FREE,
			'primary_label'         => $view,
			'primary_action'        => 'open_pricing',
			'secondary_label'       => $view,
			'secondary_action'      => 'open_pricing',
			'tooltip_locked'        => __( 'View plans to compare options for your site.', 'beepbeep-ai-alt-text-generator' ),
			'modal_mode'            => 'browse',
			'upgrade_target'        => null,
			'locked_labels'         => [
				'generate_missing' => __( 'View plans to unlock more generations', 'beepbeep-ai-alt-text-generator' ),
				'regenerate_single' => __( 'View plans for more regenerations', 'beepbeep-ai-alt-text-generator' ),
				'reoptimize_all'   => __( 'View plans for more capacity', 'beepbeep-ai-alt-text-generator' ),
				'default'          => $view,
			],
			'modal_primary_label'   => function_exists( 'bbai_copy_cta_upgrade_growth' ) ? bbai_copy_cta_upgrade_growth() : __( 'Upgrade to Growth', 'beepbeep-ai-alt-text-generator' ),
			'modal_title_default'   => __( 'Plans & pricing', 'beepbeep-ai-alt-text-generator' ),
			'modal_subtitle_default' => __( 'Compare plans anytime. Your free allowance stays available until you use it.', 'beepbeep-ai-alt-text-generator' ),
			'show_credit_pack'      => true,
			'analytics'             => [
				'primary'  => 'pricing_viewed_authenticated',
				'secondary'=> 'pricing_viewed_authenticated',
				'locked'   => 'locked_action_soft',
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function payload_growth_tier(): array {
		$pro = function_exists( 'bbai_copy_cta_upgrade_agency' ) ? bbai_copy_cta_upgrade_agency() : __( 'Upgrade to Pro', 'beepbeep-ai-alt-text-generator' );

		return [
			'state'                 => self::STATE_GROWTH,
			'primary_label'         => $pro,
			'primary_action'        => 'open_pro_upgrade',
			'secondary_label'       => __( 'View plans', 'beepbeep-ai-alt-text-generator' ),
			'secondary_action'      => 'open_pricing',
			'tooltip_locked'        => __( 'Upgrade to Pro for more capacity', 'beepbeep-ai-alt-text-generator' ),
			'modal_mode'            => 'pro_upgrade',
			'upgrade_target'        => 'pro',
			'locked_labels'         => [
				'generate_missing' => $pro,
				'regenerate_single' => __( 'Buy more credits to regenerate ALT text', 'beepbeep-ai-alt-text-generator' ),
				'reoptimize_all'   => $pro,
				'default'          => $pro,
			],
			'modal_primary_label'   => $pro,
			'modal_title_default'   => __( 'Manage your BeepBeep AI plan', 'beepbeep-ai-alt-text-generator' ),
			'modal_subtitle_default' => __( 'Compare plans or open billing to adjust your subscription.', 'beepbeep-ai-alt-text-generator' ),
			'show_credit_pack'      => true,
			'analytics'             => [
				'primary'  => 'upgrade_pro_clicked',
				'secondary'=> 'pricing_viewed_authenticated',
				'locked'   => 'locked_action_upgrade_pro',
			],
		];
	}

	/**
	 * Agency / top tier.
	 *
	 * @return array<string, mixed>
	 */
	private static function payload_pro_tier(): array {
		$usage = __( 'Usage & billing', 'beepbeep-ai-alt-text-generator' );

		return [
			'state'                 => self::STATE_PRO,
			'primary_label'         => $usage,
			'primary_action'        => 'open_usage',
			'secondary_label'       => __( 'View plans', 'beepbeep-ai-alt-text-generator' ),
			'secondary_action'      => 'open_pricing',
			'tooltip_locked'        => __( 'Open usage and billing to manage your plan or buy more credits.', 'beepbeep-ai-alt-text-generator' ),
			'modal_mode'            => 'manage',
			'upgrade_target'        => null,
			'locked_labels'         => [
				'generate_missing' => $usage,
				'regenerate_single' => $usage,
				'reoptimize_all'   => $usage,
				'default'          => $usage,
			],
			'modal_primary_label'   => __( 'Open billing portal', 'beepbeep-ai-alt-text-generator' ),
			'modal_title_default'   => __( 'Manage your BeepBeep AI plan', 'beepbeep-ai-alt-text-generator' ),
			'modal_subtitle_default' => __( 'Compare plans, manage billing, or add credits.', 'beepbeep-ai-alt-text-generator' ),
			'show_credit_pack'      => true,
			'analytics'             => [
				'primary'  => 'manage_plan_clicked',
				'secondary'=> 'pricing_viewed_authenticated',
				'locked'   => 'locked_action_manage_plan',
			],
		];
	}
}
