<?php
/**
 * Phase 15 — Monetisation model: canonical tiers, feature matrix, upgrade logic helpers.
 *
 * Maps product strategy (Free / Growth / Pro) onto existing billing slugs:
 * - API `free`  → canonical Free
 * - API `pro` / `growth` → canonical Growth (single-site / creator scale)
 * - API `agency` / `enterprise` → canonical Pro (multi-site / scale)
 *
 * Do not change scoring or generation here; use `bbai_monetisation_feature_enabled()`
 * and client payload for gates and messaging only.
 *
 * @package BeepBeep_AI
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const BBAI_MONET_TIER_FREE   = 'free';
const BBAI_MONET_TIER_GROWTH = 'growth';
const BBAI_MONET_TIER_PRO    = 'pro';

/**
 * Canonical tier from API / license plan slug.
 */
function bbai_monetisation_canonical_tier_from_slug(string $slug): string {
    $slug = strtolower(trim($slug));
    if ('' === $slug || 'free' === $slug || 'trial' === $slug || 'starter' === $slug) {
        return BBAI_MONET_TIER_FREE;
    }
    if (in_array($slug, ['agency', 'enterprise'], true)) {
        return BBAI_MONET_TIER_PRO;
    }
    if (in_array($slug, ['pro', 'growth'], true)) {
        return BBAI_MONET_TIER_GROWTH;
    }
    return BBAI_MONET_TIER_FREE;
}

/**
 * Numeric rank for comparisons (higher = more capable).
 */
function bbai_monetisation_tier_rank(string $tier): int {
    switch ($tier) {
        case BBAI_MONET_TIER_PRO:
            return 2;
        case BBAI_MONET_TIER_GROWTH:
            return 1;
        default:
            return 0;
    }
}

/**
 * Default monthly credit ceilings for positioning (actual limits come from API / org).
 *
 * @return array<string, array{label:string,monthly_credits:int,automation:bool,sites:string}>
 */
function bbai_monetisation_default_tier_positioning(): array {
    return [
        BBAI_MONET_TIER_FREE => [
            'label'             => __('Free', 'beepbeep-ai-alt-text-generator'),
            'monthly_credits'   => 50,
            'automation'        => false,
            'sites'             => __('1 site', 'beepbeep-ai-alt-text-generator'),
        ],
        BBAI_MONET_TIER_GROWTH => [
            'label'             => __('Growth', 'beepbeep-ai-alt-text-generator'),
            'monthly_credits'   => 1000,
            'automation'        => true,
            'sites'             => __('1 site · higher quota', 'beepbeep-ai-alt-text-generator'),
        ],
        BBAI_MONET_TIER_PRO => [
            'label'             => __('Pro', 'beepbeep-ai-alt-text-generator'),
            'monthly_credits'   => 10000,
            'automation'        => true,
            'sites'             => __('Agency & multi-site', 'beepbeep-ai-alt-text-generator'),
        ],
    ];
}

/**
 * Feature flags by canonical tier. Values are defaults; filters may override per site.
 *
 * @return array<string, array<string, bool>>
 */
function bbai_monetisation_default_feature_matrix(): array {
    return [
        BBAI_MONET_TIER_FREE => [
            'on_upload_automation'     => false,
            'bulk_generation'          => true,
            'billing_portal'           => false,
            'usage_analytics_extended' => false,
            'agency_overview_ui'       => false,
            'csv_usage_export'         => false,
            'priority_api_queue'       => false,
            'white_label_exports'      => false,
            'webhooks'                 => false,
        ],
        BBAI_MONET_TIER_GROWTH => [
            'on_upload_automation'     => true,
            'bulk_generation'          => true,
            'billing_portal'           => true,
            'usage_analytics_extended' => true,
            'agency_overview_ui'       => false,
            'csv_usage_export'         => true,
            'priority_api_queue'       => false,
            'white_label_exports'      => false,
            'webhooks'                 => false,
        ],
        BBAI_MONET_TIER_PRO => [
            'on_upload_automation'     => true,
            'bulk_generation'          => true,
            'billing_portal'           => true,
            'usage_analytics_extended' => true,
            'agency_overview_ui'       => true,
            'csv_usage_export'         => true,
            'priority_api_queue'       => true,
            'white_label_exports'      => true,
            'webhooks'                 => true,
        ],
    ];
}

/**
 * Minimum canonical tier required for a feature (for upgrade CTAs).
 */
function bbai_monetisation_min_tier_for_feature(string $feature): string {
    $map = [
        'on_upload_automation'     => BBAI_MONET_TIER_GROWTH,
        'billing_portal'           => BBAI_MONET_TIER_GROWTH,
        'usage_analytics_extended' => BBAI_MONET_TIER_GROWTH,
        'csv_usage_export'         => BBAI_MONET_TIER_GROWTH,
        'agency_overview_ui'       => BBAI_MONET_TIER_PRO,
        'priority_api_queue'       => BBAI_MONET_TIER_PRO,
        'white_label_exports'      => BBAI_MONET_TIER_PRO,
        'webhooks'                 => BBAI_MONET_TIER_PRO,
        'bulk_generation'          => BBAI_MONET_TIER_FREE,
    ];
    return $map[ $feature ] ?? BBAI_MONET_TIER_FREE;
}

/**
 * Whether the current site/user effective tier allows a feature.
 */
function bbai_monetisation_feature_enabled(string $feature, ?string $canonical_tier = null): bool {
    if (null === $canonical_tier) {
        $canonical_tier = bbai_monetisation_current_canonical_tier();
    }
    $matrix = apply_filters('bbai_monetisation_feature_matrix', bbai_monetisation_default_feature_matrix());
    $tier   = $canonical_tier;
    if (!isset($matrix[ $tier ])) {
        $tier = BBAI_MONET_TIER_FREE;
    }
    $allowed = !empty($matrix[ $tier ][ $feature ]);
    return (bool) apply_filters('bbai_monetisation_feature_enabled', $allowed, $feature, $canonical_tier);
}

/**
 * Current canonical tier (uses plan slug only — avoid circular calls via Plan_Helpers::get_canonical_monetisation_tier()).
 */
function bbai_monetisation_current_canonical_tier(): string {
    if (class_exists(\BeepBeepAI\AltTextGenerator\Admin\Plan_Helpers::class)) {
        return bbai_monetisation_canonical_tier_from_slug(\BeepBeepAI\AltTextGenerator\Admin\Plan_Helpers::get_plan_slug());
    }
    return BBAI_MONET_TIER_FREE;
}

/**
 * Stripe / checkout SKU to emphasise for upsell (maps to existing modal price keys).
 */
function bbai_monetisation_recommended_price_key(?string $canonical_tier = null): string {
    if (null === $canonical_tier) {
        $canonical_tier = bbai_monetisation_current_canonical_tier();
    }
    if (BBAI_MONET_TIER_FREE === $canonical_tier) {
        return 'pro';
    }
    if (BBAI_MONET_TIER_GROWTH === $canonical_tier) {
        return 'agency';
    }
    return '';
}

/**
 * Structured upgrade triggers (thresholds mirror BBAI_Upgrade_State + product rules).
 *
 * @return array<string, int|float|bool>
 */
function bbai_monetisation_upgrade_trigger_thresholds(): array {
    return [
        'low_credit_percent'       => 80,
        'inactive_scan_days'       => 7,
        'high_engagement_credits'  => 15,
        'arpu_nudge_credits'       => 8,
    ];
}

/**
 * Payload for wp_localize_script — feature gates + tier labels without PII.
 *
 * @return array<string, mixed>
 */
function bbai_monetisation_client_bootstrap(): array {
    $slug     = 'free';
    if (class_exists(\BeepBeepAI\AltTextGenerator\Admin\Plan_Helpers::class)) {
        $slug = \BeepBeepAI\AltTextGenerator\Admin\Plan_Helpers::get_plan_slug();
    }
    $canonical = bbai_monetisation_canonical_tier_from_slug($slug);
    $matrix    = apply_filters('bbai_monetisation_feature_matrix', bbai_monetisation_default_feature_matrix());
    $features  = isset($matrix[ $canonical ]) ? $matrix[ $canonical ] : $matrix[ BBAI_MONET_TIER_FREE ];

    $out = [
        'plan_slug'           => $slug,
        'canonical_tier'      => $canonical,
        'tier_rank'           => bbai_monetisation_tier_rank($canonical),
        'features'            => $features,
        'recommended_price'   => bbai_monetisation_recommended_price_key($canonical),
        'triggers'            => bbai_monetisation_upgrade_trigger_thresholds(),
        'positioning'         => bbai_monetisation_default_tier_positioning(),
    ];

    return apply_filters('bbai_monetisation_client_bootstrap', $out);
}

/**
 * Value / ROI strings for modals, banners, and usage page (extend via filter).
 *
 * @param array<string, string|int|float> $vars
 */
function bbai_monetisation_message(string $key, array $vars = []): string {
    $templates = [
        'roi_minutes' => sprintf(
            /* translators: %d: estimated minutes saved */
            __('Save ~%d minutes of manual ALT work every time you run a batch.', 'beepbeep-ai-alt-text-generator'),
            (int) ( $vars['minutes'] ?? 30 )
        ),
        'urgency_credit_wall' => __('You have hit your monthly limit. Upgrade to keep optimising new uploads.', 'beepbeep-ai-alt-text-generator'),
        'value_automation' => __('Growth and Pro automatically add ALT text when you upload — no backlog.', 'beepbeep-ai-alt-text-generator'),
        'upsell_bulk' => __('Bulk fixes burn credits fast on Free. Growth gives you room to finish the library.', 'beepbeep-ai-alt-text-generator'),
        'upsell_pro_agency' => __('Pro is built for agencies: higher quotas, overview, and room to expand.', 'beepbeep-ai-alt-text-generator'),
        'soft_success' => __('Nice — coverage improved. Lock it in with automation on Growth.', 'beepbeep-ai-alt-text-generator'),
    ];
    $templates = apply_filters('bbai_monetisation_message_templates', $templates, $key, $vars);
    return isset($templates[ $key ]) ? (string) $templates[ $key ] : '';
}
