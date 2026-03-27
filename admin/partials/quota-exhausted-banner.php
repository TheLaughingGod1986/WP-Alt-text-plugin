<?php
/**
 * Usage limit banner — unified .bbai-banner shell (warning / paused tone).
 *
 * Expects:
 * - $bbai_usage_stats (array)
 *
 * @package BeepBeep_AI_Alt_Text_Generator
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!isset($bbai_usage_stats) || !is_array($bbai_usage_stats)) {
    return;
}

$bbai_banner_used = max(0, (int) ($bbai_usage_stats['used'] ?? 0));
$bbai_banner_limit = max(1, (int) ($bbai_usage_stats['limit'] ?? 50));

$bbai_banner_headline = bbai_copy_quota_monthly_limit_title();
$bbai_banner_supporting = bbai_copy_quota_monthly_used_line($bbai_banner_used);

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/ui-components.php';
bbai_ui_render(
    'banner',
    [
        'type'        => 'error',
        'title'       => $bbai_banner_headline,
        'description' => $bbai_banner_supporting,
        'primaryCTA'  => [
            'label'      => __('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'),
            'attributes' => ['data-action' => 'show-upgrade-modal'],
        ],
        'secondaryCTA' => [
            'label' => __('Review ALT text', 'beepbeep-ai-alt-text-generator'),
            'href'  => bbai_alt_library_needs_review_url(),
        ],
        'extra_class' => 'bbai-quota-exhausted-banner bbai-banner--limit-reached',
    ]
);
