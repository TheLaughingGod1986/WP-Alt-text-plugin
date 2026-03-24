<?php
/**
 * Dashboard hero — shared banner resolver (see includes/admin/banner-system.php).
 *
 * @package BeepBeep_AI_Alt_Text_Generator
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_state = $bbai_dashboard_state ?? [];
if (empty($bbai_state) || !is_array($bbai_state)) {
    return;
}

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/banner-system.php';

$bbai_credits_used      = max(0, (int) ($bbai_state['creditsUsed'] ?? 0));
$bbai_credits_limit     = max(1, (int) ($bbai_state['creditsLimit'] ?? 50));
$bbai_credits_remaining = max(0, (int) ($bbai_state['creditsRemaining'] ?? 0));
$bbai_missing_count     = max(0, (int) ($bbai_state['missingCount'] ?? 0));
$bbai_weak_count        = max(0, (int) ($bbai_state['weakCount'] ?? 0));
$bbai_library_url       = (string) ($bbai_state['libraryUrl'] ?? admin_url('admin.php?page=bbai-library'));
$bbai_settings_url      = (string) ($bbai_state['settingsUrl'] ?? admin_url('admin.php?page=bbai-settings'));

$bbai_snap = bbai_banner_snapshot_from_dashboard_state($bbai_state);

$bbai_command_hero = bbai_banner_build_command_hero(
    BBAI_BANNER_CTX_DASHBOARD,
    $bbai_snap,
    [
        'aria_label'         => __('Dashboard summary', 'beepbeep-ai-alt-text-generator'),
        'show_hero_loop'     => true,
        'icon_wrapper_attrs' => [
            'data-bbai-hero-icon' => '1',
        ],
        'headline_attrs'     => [
            'data-bbai-hero-headline' => '1',
        ],
        'section_data_attrs' => [
            'data-bbai-dashboard-hero'       => '1',
            'data-bbai-banner-used'          => (string) $bbai_credits_used,
            'data-bbai-banner-limit'         => (string) $bbai_credits_limit,
            'data-bbai-banner-remaining'     => (string) $bbai_credits_remaining,
            'data-bbai-banner-library-url'   => $bbai_library_url,
            'data-bbai-banner-missing-count' => (string) $bbai_missing_count,
            'data-bbai-banner-weak-count'    => (string) $bbai_weak_count,
            'data-bbai-banner-days-left'     => (string) (int) ($bbai_state['daysUntilReset'] ?? 0),
            'data-bbai-banner-settings-url'  => $bbai_settings_url,
        ],
    ]
);

require BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/shared-command-hero.php';
