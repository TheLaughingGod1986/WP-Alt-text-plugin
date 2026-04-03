<?php
/**
 * Single top-of-screen plan banner: FREE → GROWTH → PRO (Agency).
 *
 * One monetisation surface for Dashboard + ALT Library. Copy and CTAs are plan-driven only.
 *
 * @package BeepBeep_AI
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const BBAI_PLAN_TOP_TIER_FREE   = 'free';
const BBAI_PLAN_TOP_TIER_GROWTH = 'growth';
const BBAI_PLAN_TOP_TIER_PRO    = 'pro';

/**
 * Normalise SaaS plan slug into a coarse product tier for the top banner.
 *
 * @param array<string, mixed> $input Keys: plan_slug, is_agency (optional bool).
 */
function bbai_plan_top_banner_normalize_tier(array $input): string
{
    $slug = strtolower(trim((string) ($input['plan_slug'] ?? 'free')));
    if (!empty($input['is_agency']) || in_array($slug, ['agency', 'enterprise'], true)) {
        return BBAI_PLAN_TOP_TIER_PRO;
    }
    if (in_array($slug, ['growth', 'pro'], true)) {
        return BBAI_PLAN_TOP_TIER_GROWTH;
    }

    return BBAI_PLAN_TOP_TIER_FREE;
}

/**
 * Resolve headline, subtext, and CTAs for the unified plan banner.
 *
 * @param array<string, mixed> $input {
 *   @type bool   $has_connected_account
 *   @type bool   $is_guest_trial         Anonymous / guest shell (trial or no SaaS account).
 *   @type bool   $is_anonymous_trial     Alias for guest trial when building from mixed callers.
 *   @type string $plan_slug
 *   @type bool   $is_agency
 *   @type int    $free_plan_offer
 *   @type string $usage_url
 *   @type string $billing_portal_url
 * }
 *
 * @return array{
 *   tier: string,
 *   headline: string,
 *   subtext: string,
 *   tone: string,
 *   banner_variant: string,
 *   semantic_state: string,
 *   primary_action: ?array<string, mixed>,
 *   secondary_action: ?array<string, mixed>
 * }
 */
function bbai_plan_top_banner_resolve(array $input): array
{
    $guest = !empty($input['is_guest_trial']) || !empty($input['is_anonymous_trial']);
    $has_connected = !empty($input['has_connected_account']);
    $free_offer = max(1, (int) ($input['free_plan_offer'] ?? 50));
    $usage_url = trim((string) ($input['usage_url'] ?? ''));
    if ('' === $usage_url) {
        $usage_url = admin_url('admin.php?page=bbai-credit-usage');
    }
    $billing = trim((string) ($input['billing_portal_url'] ?? ''));
    if ('' === $billing && class_exists(\BeepBeepAI\AltTextGenerator\Usage_Tracker::class)) {
        $billing = (string) \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_billing_portal_url();
    }
    if ('' === $billing) {
        $billing = $usage_url;
    }

    $tier_input = $input;
    if ($guest) {
        $tier_input['plan_slug'] = 'free';
        $tier_input['is_agency'] = false;
    }
    $tier = bbai_plan_top_banner_normalize_tier($tier_input);

    $signup_primary = [
        'label'      => __('Create free account', 'beepbeep-ai-alt-text-generator'),
        'attributes' => [
            'data-action'                 => 'show-auth-modal',
            'data-auth-tab'               => 'register',
            'data-bbai-analytics-upgrade' => 'plan_banner_create_account',
        ],
    ];
    // Logged-in free: secondary can emphasise Growth in the modal. Guests: browse-only ladder (trial → free account), not Growth-first.
    $view_plans_secondary_guest = [
        'label'      => __('View plans', 'beepbeep-ai-alt-text-generator'),
        'href'       => '#',
        'attributes' => [
            'data-action'                 => 'show-upgrade-modal',
            'data-bbai-pricing-variant'   => 'browse',
            'data-bbai-analytics-upgrade' => 'plan_banner_view_plans',
        ],
    ];
    $view_plans_secondary = [
        'label'      => __('View plans', 'beepbeep-ai-alt-text-generator'),
        'href'       => '#',
        'attributes' => [
            'data-action'                 => 'show-upgrade-modal',
            'data-bbai-pricing-variant'   => 'growth',
            'data-bbai-analytics-upgrade' => 'plan_banner_view_plans',
        ],
    ];
    $upgrade_growth_primary = [
        'label'      => __('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'),
        'href'       => '#',
        'attributes' => [
            'data-action'                 => 'show-upgrade-modal',
            'data-bbai-pricing-variant'   => 'growth',
            'data-bbai-analytics-upgrade' => 'plan_banner_upgrade_growth',
        ],
    ];
    $usage_secondary = [
        'label' => __('Usage & billing', 'beepbeep-ai-alt-text-generator'),
        'href'  => $usage_url,
        'attributes' => [
            'data-bbai-analytics-upgrade' => 'plan_banner_usage_billing',
        ],
    ];
    $upgrade_pro_primary = [
        'label'      => __('Upgrade to Pro', 'beepbeep-ai-alt-text-generator'),
        'href'       => '#',
        'attributes' => [
            'data-action'            => 'show-upgrade-modal',
            'data-bbai-pricing-variant' => 'agency',
            'data-bbai-analytics-upgrade' => 'plan_banner_upgrade_pro',
        ],
    ];
    $manage_plan_secondary = [
        'label' => __('Manage plan', 'beepbeep-ai-alt-text-generator'),
        'href'  => $billing,
        'attributes' => [
            'data-bbai-analytics-upgrade' => 'plan_banner_manage_plan',
        ],
    ];

    if (BBAI_PLAN_TOP_TIER_PRO === $tier) {
        return [
            'tier'             => $tier,
            'headline'         => __('You’re on the Pro plan', 'beepbeep-ai-alt-text-generator'),
            'subtext'          => __('You’re using the highest available plan.', 'beepbeep-ai-alt-text-generator'),
            'tone'             => 'healthy',
            'banner_variant'   => 'success',
            'semantic_state'   => 'plan-pro',
            'primary_action'   => null,
            'secondary_action' => $manage_plan_secondary,
        ];
    }

    if (BBAI_PLAN_TOP_TIER_GROWTH === $tier) {
        return [
            'tier'             => $tier,
            'headline'         => __('Need more capacity?', 'beepbeep-ai-alt-text-generator'),
            'subtext'          => __('Upgrade to Pro for higher limits and advanced workflows.', 'beepbeep-ai-alt-text-generator'),
            'tone'             => 'attention',
            'banner_variant'   => 'warning',
            'semantic_state'   => 'plan-growth',
            'primary_action'   => $upgrade_pro_primary,
            'secondary_action' => $view_plans_secondary,
        ];
    }

    $headline = __('Continue with free credits', 'beepbeep-ai-alt-text-generator');
    $subtext  = sprintf(
        /* translators: %d: monthly free generations after creating an account. */
        __('Create an account to get %d free generations each month, or upgrade for more.', 'beepbeep-ai-alt-text-generator'),
        $free_offer
    );

    if (!$has_connected || $guest) {
        return [
            'tier'             => BBAI_PLAN_TOP_TIER_FREE,
            'headline'         => $headline,
            'subtext'          => $subtext,
            'tone'             => 'healthy',
            'banner_variant'   => 'success',
            'semantic_state'   => 'plan-free',
            'primary_action'   => $signup_primary,
            'secondary_action' => $view_plans_secondary_guest,
        ];
    }

    /* Connected Free: distinct from guests — Growth upgrade first, attention shell. */
    return [
        'tier'             => BBAI_PLAN_TOP_TIER_FREE,
        'headline'         => __('Grow beyond the Free plan', 'beepbeep-ai-alt-text-generator'),
        'subtext'          => sprintf(
            /* translators: %d: monthly generations included on the Free plan. */
            __('Upgrade to Growth for higher limits, priority processing, and bulk optimisation across your library. Your Free plan still includes %d generations each month.', 'beepbeep-ai-alt-text-generator'),
            $free_offer
        ),
        'tone'             => 'attention',
        'banner_variant'   => 'warning',
        'semantic_state'   => 'plan-free-account',
        'primary_action'   => $upgrade_growth_primary,
        'secondary_action' => $usage_secondary,
    ];
}

/**
 * Build command_hero payload for status-banner.php (bbai-banner).
 *
 * @param array<string, mixed> $opts Passed through (aria_label, section_data_attrs, icon/headline attrs, etc.).
 *
 * @return array<string, mixed>
 */
function bbai_plan_top_banner_build_command_hero(string $page_context, array $input, array $opts = []): array
{
    require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/banner-system.php';
    require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/command-hero.php';

    $resolved = bbai_plan_top_banner_resolve($input);
    $tier     = (string) $resolved['tier'];

    $primary_a   = $resolved['primary_action'];
    $secondary_a = $resolved['secondary_action'];

    if (BBAI_BANNER_CTX_LIBRARY === $page_context) {
        if (is_array($primary_a) && isset($primary_a['attributes']) && is_array($primary_a['attributes'])) {
            $primary_a['attributes'] = bbai_banner_merge_library_attrs($primary_a['attributes']);
        }
        if (is_array($secondary_a) && isset($secondary_a['attributes']) && is_array($secondary_a['attributes'])) {
            $secondary_a['attributes'] = bbai_banner_merge_library_attrs($secondary_a['attributes']);
        }
    }

    $primary_html   = $primary_a ? bbai_command_hero_render_action($primary_a, 'primary') : '';
    $secondary_html = $secondary_a ? bbai_command_hero_render_action($secondary_a, 'secondary') : '';

    $section_data = isset($opts['section_data_attrs']) && is_array($opts['section_data_attrs']) ? $opts['section_data_attrs'] : [];
    $bbai_plan_banner_logical = str_replace('-', '_', (string) $resolved['semantic_state']);

    $section_data['data-bbai-shared-banner']        = '1';
    $section_data['data-bbai-top-banner-mode']      = 'plan';
    $section_data['data-bbai-plan-tier']            = $tier;
    $section_data['data-bbai-banner-logical-state'] = $bbai_plan_banner_logical;
    $section_data['data-bbai-dashboard-hero']       = '1';

    $hero = [
        'semantic_state'         => (string) $resolved['semantic_state'],
        'tone'                   => (string) $resolved['tone'],
        'banner_variant'         => (string) $resolved['banner_variant'],
        'suppress_banner_render'   => false,
        'aria_label'               => (string) ($opts['aria_label'] ?? __('BeepBeep AI', 'beepbeep-ai-alt-text-generator')),
        'eyebrow'                  => (string) ($opts['eyebrow'] ?? ''),
        'title'                    => (string) $resolved['headline'],
        'body'                     => (string) $resolved['subtext'],
        'status_line'              => '',
        'note'                     => '',
        'show_progress'            => false,
        'progress_percent'         => 0,
        'progress_aria_valuetext'  => '',
        'show_hero_loop'           => false,
        'settings_url'             => (string) ($input['settings_url'] ?? admin_url('admin.php?page=bbai-settings')),
        'primary_html'             => $primary_html,
        'secondary_html'           => $secondary_html,
        'tertiary_html'            => '',
        'actions_append_html'      => (string) ($opts['actions_append_html'] ?? ''),
        'inline_stats'             => [],
        'section_extra_class'      => (string) ($opts['section_extra_class'] ?? ''),
        'wrapper_extra_class'      => trim('bbai-status-banner-host bbai-plan-top-banner-host ' . (string) ($opts['wrapper_extra_class'] ?? '')),
        'section_data_attrs'       => $section_data,
        'icon_wrapper_attrs'       => isset($opts['icon_wrapper_attrs']) && is_array($opts['icon_wrapper_attrs']) ? $opts['icon_wrapper_attrs'] : [],
        'headline_attrs'           => isset($opts['headline_attrs']) && is_array($opts['headline_attrs']) ? $opts['headline_attrs'] : [],
        'subtext_attrs'            => isset($opts['subtext_attrs']) && is_array($opts['subtext_attrs']) ? $opts['subtext_attrs'] : [],
        'banner_logical_state'     => $bbai_plan_banner_logical,
    ];

    if ('' === (string) ($opts['page_hero_variant'] ?? '')) {
        unset($hero['page_hero_variant']);
    } else {
        $hero['page_hero_variant'] = (string) $opts['page_hero_variant'];
    }

    if (array_key_exists('suppress_host', $opts)) {
        $hero['suppress_host'] = (bool) $opts['suppress_host'];
    }
    if (!empty($opts['heading_tag'])) {
        $ht = strtolower((string) $opts['heading_tag']);
        if (in_array($ht, ['h1', 'h2'], true)) {
            $hero['heading_tag'] = $ht;
        }
    }

    return $hero;
}
