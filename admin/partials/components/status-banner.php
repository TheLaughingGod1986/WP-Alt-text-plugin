<?php
/**
 * Canonical top status / command hero banner (Analytics, ALT Library, Usage, Dashboard).
 *
 * Single source of truth for banner shell, grid, typography, and CTA column.
 * Action area: components/action-stack.php. CTAs: bbai_command_hero_render_* → components/ui-button.php.
 *
 * $bbai_ui keys:
 * - command_hero: array (hero config — see legacy shared-command-hero docs)
 *
 * Legacy: dashboard-success-banner may load via shared-command-hero.php shim, which sets $bbai_ui['command_hero'].
 *
 * @package BeepBeep_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_ui = isset($bbai_ui) && is_array($bbai_ui) ? $bbai_ui : [];
$bbai_command_hero = isset($bbai_ui['command_hero']) && is_array($bbai_ui['command_hero'])
    ? $bbai_ui['command_hero']
    : (isset($bbai_command_hero) && is_array($bbai_command_hero) ? $bbai_command_hero : []);

$bbai_ch = $bbai_command_hero;

$bbai_ch_wrapper_extra = (string) ($bbai_ch['wrapper_extra_class'] ?? '');
$bbai_ch_section_extra = (string) ($bbai_ch['section_extra_class'] ?? '');
$bbai_ch_section_id = (string) ($bbai_ch['section_id'] ?? '');
$bbai_ch_aria = (string) ($bbai_ch['aria_label'] ?? '');
$bbai_ch_semantic_state = (string) ($bbai_ch['semantic_state'] ?? 'default');
$bbai_ch_tone = (string) ($bbai_ch['tone'] ?? 'healthy');
$bbai_ch_title = (string) ($bbai_ch['title'] ?? '');
$bbai_ch_body = (string) ($bbai_ch['body'] ?? '');
$bbai_ch_status = (string) ($bbai_ch['status_line'] ?? '');
$bbai_ch_note = (string) ($bbai_ch['note'] ?? '');
$bbai_ch_icon_html = isset($bbai_ch['icon_html']) && is_string($bbai_ch['icon_html']) && $bbai_ch['icon_html'] !== ''
    ? $bbai_ch['icon_html']
    : null;
$bbai_ch_icon_wrapper_attrs = isset($bbai_ch['icon_wrapper_attrs']) && is_array($bbai_ch['icon_wrapper_attrs'])
    ? $bbai_ch['icon_wrapper_attrs']
    : [];
$bbai_ch_headline_attrs = isset($bbai_ch['headline_attrs']) && is_array($bbai_ch['headline_attrs'])
    ? $bbai_ch['headline_attrs']
    : [];
$bbai_ch_subtext_attrs = isset($bbai_ch['subtext_attrs']) && is_array($bbai_ch['subtext_attrs'])
    ? $bbai_ch['subtext_attrs']
    : [];

$bbai_ch_show_progress = !empty($bbai_ch['show_progress']);
$bbai_ch_progress_percent = (int) ($bbai_ch['progress_percent'] ?? 0);
$bbai_ch_progress_percent = min(100, max(0, $bbai_ch_progress_percent));
$bbai_ch_progress_aria = trim((string) ($bbai_ch['progress_aria_valuetext'] ?? ''));

$bbai_ch_show_loop = !empty($bbai_ch['show_hero_loop']);
$bbai_ch_settings_url = (string) ($bbai_ch['settings_url'] ?? admin_url('admin.php?page=bbai-settings'));

$bbai_ch_primary_html = (string) ($bbai_ch['primary_html'] ?? '');
$bbai_ch_secondary_html = (string) ($bbai_ch['secondary_html'] ?? '');
$bbai_ch_tertiary_html = (string) ($bbai_ch['tertiary_html'] ?? '');
$bbai_ch_actions_append = (string) ($bbai_ch['actions_append_html'] ?? '');

$bbai_ch_inline_stats = isset($bbai_ch['inline_stats']) && is_array($bbai_ch['inline_stats']) ? $bbai_ch['inline_stats'] : [];

$bbai_ch_section_data = isset($bbai_ch['section_data_attrs']) && is_array($bbai_ch['section_data_attrs'])
    ? $bbai_ch['section_data_attrs']
    : [];

$bbai_ch_eyebrow = trim((string) ($bbai_ch['eyebrow'] ?? ''));
$bbai_ch_eyebrow_show = '' !== $bbai_ch_eyebrow;

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/command-hero.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/page-hero.php';

if (null === $bbai_ch_icon_html) {
    $bbai_ch_icon_html = bbai_command_hero_icon_markup($bbai_ch_tone);
}

$bbai_ch_icon_attr_pairs = ['class="bbai-dashboard-hero__icon bbai-banner__icon bbai-page-hero__icon"', 'aria-hidden="true"'];
foreach ($bbai_ch_icon_wrapper_attrs as $bbai_ch_i_name => $bbai_ch_i_val) {
    if (null === $bbai_ch_i_val || '' === $bbai_ch_i_val) {
        continue;
    }
    $bbai_ch_icon_attr_pairs[] = sprintf('%s="%s"', esc_attr((string) $bbai_ch_i_name), esc_attr((string) $bbai_ch_i_val));
}
$bbai_ch_icon_attr_str = implode(' ', $bbai_ch_icon_attr_pairs);

$bbai_ch_headline_pairs = ['class="bbai-dashboard-hero__headline bbai-banner__headline"'];
foreach ($bbai_ch_headline_attrs as $bbai_ch_h_name => $bbai_ch_h_val) {
    if (null === $bbai_ch_h_val || '' === $bbai_ch_h_val) {
        continue;
    }
    $bbai_ch_headline_pairs[] = sprintf('%s="%s"', esc_attr((string) $bbai_ch_h_name), esc_attr((string) $bbai_ch_h_val));
}
$bbai_ch_headline_attr_str = implode(' ', $bbai_ch_headline_pairs);

$bbai_ch_subtext_pairs = ['class="bbai-dashboard-hero__subtext bbai-banner__subtext"', 'data-bbai-hero-subtext="1"'];
foreach ($bbai_ch_subtext_attrs as $bbai_ch_s_name => $bbai_ch_s_val) {
    if (null === $bbai_ch_s_val || '' === $bbai_ch_s_val) {
        continue;
    }
    $bbai_ch_subtext_pairs[] = sprintf('%s="%s"', esc_attr((string) $bbai_ch_s_name), esc_attr((string) $bbai_ch_s_val));
}
$bbai_ch_subtext_attr_str = implode(' ', $bbai_ch_subtext_pairs);

$bbai_ch_section_attr_extra = '';
foreach ($bbai_ch_section_data as $bbai_ch_dk => $bbai_ch_dv) {
    if (!is_string($bbai_ch_dk) || $bbai_ch_dk === '') {
        continue;
    }
    $bbai_ch_section_attr_extra .= sprintf(' %s="%s"', esc_attr($bbai_ch_dk), esc_attr((string) $bbai_ch_dv));
}

$bbai_ch_section_id_attr = '' !== $bbai_ch_section_id ? ' id="' . esc_attr($bbai_ch_section_id) . '"' : '';
$bbai_ch_has_primary = '' !== trim($bbai_ch_primary_html);
$bbai_ch_has_secondary = '' !== trim($bbai_ch_secondary_html);
$bbai_ch_has_tertiary = '' !== trim($bbai_ch_tertiary_html);
$bbai_ch_status_show = '' !== trim($bbai_ch_status);
$bbai_ch_note_show = '' !== trim($bbai_ch_note);

$bbai_ch_banner_variant = isset($bbai_ch['banner_variant']) && in_array((string) $bbai_ch['banner_variant'], ['success', 'warning'], true)
    ? (string) $bbai_ch['banner_variant']
    : (in_array($bbai_ch_tone, ['attention', 'paused', 'setup'], true) ? 'warning' : 'success');

$bbai_ch_page_hero_variant_explicit = (string) ($bbai_ch['page_hero_variant'] ?? '');
$bbai_ch_page_hero_variant = bbai_page_hero_resolve_variant(
    $bbai_ch_tone,
    $bbai_ch_semantic_state,
    $bbai_ch_banner_variant,
    $bbai_ch_page_hero_variant_explicit
);

$bbai_ch_section_class = trim(implode(' ', array_filter([
    'bbai-status-banner',
    'bbai-page-hero',
    'bbai-page-hero--' . $bbai_ch_page_hero_variant,
    'bbai-dashboard-hero',
    'bbai-dashboard-hero--command',
    'bbai-dashboard-section',
    'bbai-banner',
    'bbai-banner--' . $bbai_ch_banner_variant,
    '' !== $bbai_ch_section_extra ? trim($bbai_ch_section_extra) : '',
])));
?>
<div class="bbai-hero-full bbai-command-hero-host bbai-page-hero-host<?php echo '' !== $bbai_ch_wrapper_extra ? ' ' . esc_attr(trim($bbai_ch_wrapper_extra)) : ''; ?>">
    <div class="bbai-hero-inner bbai-page-hero__frame">
        <section<?php echo $bbai_ch_section_id_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            class="<?php echo esc_attr($bbai_ch_section_class); ?>"
            data-bbai-status-banner="1"
            data-bbai-shared-command-hero="1"
            data-bbai-page-hero="1"
            data-page-hero-variant="<?php echo esc_attr($bbai_ch_page_hero_variant); ?>"
            data-state="<?php echo esc_attr($bbai_ch_semantic_state); ?>"
            data-tone="<?php echo esc_attr($bbai_ch_tone); ?>"
            <?php echo $bbai_ch_section_attr_extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            aria-label="<?php echo esc_attr($bbai_ch_aria); ?>"
        >
            <div class="bbai-dashboard-hero-inner bbai-banner__inner bbai-page-hero__inner">
                <div class="bbai-dashboard-hero__layout bbai-banner__layout bbai-page-hero__layout">
                    <div class="bbai-dashboard-hero-copy bbai-banner__content bbai-page-hero__content">
                        <div class="bbai-dashboard-hero__heading-row bbai-banner__head">
                            <div <?php echo $bbai_ch_icon_attr_str; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                                <?php echo $bbai_ch_icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </div>
                            <div class="bbai-dashboard-hero__heading-copy bbai-banner__title-wrap bbai-page-hero__title-wrap">
                                <p class="bbai-page-hero__eyebrow bbai-banner__eyebrow" data-bbai-page-hero-eyebrow<?php echo $bbai_ch_eyebrow_show ? '' : ' hidden'; ?>><?php echo esc_html($bbai_ch_eyebrow); ?></p>
                                <h1 <?php echo $bbai_ch_headline_attr_str; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html($bbai_ch_title); ?></h1>
                            </div>
                        </div>

                        <p <?php echo $bbai_ch_subtext_attr_str; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html($bbai_ch_body); ?></p>
                        <p class="bbai-dashboard-hero__next-step bbai-banner__meta" data-bbai-hero-next-step<?php echo $bbai_ch_status_show ? '' : ' hidden'; ?>><?php echo esc_html($bbai_ch_status); ?></p>

                        <?php if ($bbai_ch_show_progress) : ?>
                        <div class="bbai-dashboard-hero__usage-block bbai-banner__utility bbai-banner__utility--progress-only">
                            <div class="bbai-dashboard-hero-progress bbai-banner__progress" aria-label="<?php esc_attr_e('Credit usage this cycle', 'beepbeep-ai-alt-text-generator'); ?>">
                                <div class="bbai-dashboard-hero-progress-track bbai-banner__progress-track" role="progressbar" aria-valuenow="<?php echo esc_attr((string) $bbai_ch_progress_percent); ?>" aria-valuemin="0" aria-valuemax="100"<?php echo '' !== $bbai_ch_progress_aria ? ' aria-valuetext="' . esc_attr($bbai_ch_progress_aria) . '"' : ''; ?>>
                                    <span class="bbai-dashboard-hero-progress-fill bbai-banner__progress-fill" data-bbai-banner-progress data-bbai-banner-progress-target="<?php echo esc_attr((string) $bbai_ch_progress_percent); ?>" style="width: <?php echo esc_attr((string) $bbai_ch_progress_percent); ?>%;"></span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($bbai_ch_inline_stats)) : ?>
                        <div class="bbai-dashboard-hero__inline-stats bbai-banner__inline-stats" aria-hidden="true">
                            <?php foreach ($bbai_ch_inline_stats as $bbai_ch_stat) : ?>
                                <?php
                                if (!is_array($bbai_ch_stat)) {
                                    continue;
                                }
                                $bbai_ch_sl = (string) ($bbai_ch_stat['label'] ?? '');
                                $bbai_ch_sv = (string) ($bbai_ch_stat['value'] ?? '');
                                ?>
                                <div class="bbai-dashboard-hero__inline-stat">
                                    <span class="bbai-dashboard-hero__inline-stat-label"><?php echo esc_html($bbai_ch_sl); ?></span>
                                    <strong class="bbai-dashboard-hero__inline-stat-value"><?php echo esc_html($bbai_ch_sv); ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <p class="bbai-dashboard-hero__note bbai-banner__note" data-bbai-hero-note<?php echo $bbai_ch_note_show ? '' : ' hidden'; ?>><?php echo esc_html($bbai_ch_note); ?></p>

                        <?php if ($bbai_ch_show_loop) : ?>
                        <div class="bbai-dashboard-hero__loop bbai-banner__loop" data-bbai-hero-loop hidden>
                            <p class="bbai-dashboard-hero__loop-label"><?php esc_html_e('Stay ahead in search', 'beepbeep-ai-alt-text-generator'); ?></p>
                            <div class="bbai-dashboard-hero__loop-actions">
                                <a href="<?php echo esc_url($bbai_ch_settings_url); ?>" class="bbai-dashboard-hero__loop-link bbai-dashboard-hero__loop-link--secondary" data-bbai-hero-loop-scan><?php esc_html_e('Auto-optimise future uploads', 'beepbeep-ai-alt-text-generator'); ?></a>
                                <a href="#" class="bbai-dashboard-hero__loop-link bbai-dashboard-hero__loop-link--secondary" data-action="show-upgrade-modal" data-bbai-hero-loop-settings><?php esc_html_e('See upgrade options', 'beepbeep-ai-alt-text-generator'); ?></a>
                            </div>
                            <p class="bbai-dashboard-hero__loop-tension" data-bbai-hero-loop-tension hidden></p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php
                    $bbai_action_stack_cfg = [
                        'has_primary'    => $bbai_ch_has_primary,
                        'has_secondary'  => $bbai_ch_has_secondary,
                        'has_tertiary'   => $bbai_ch_has_tertiary,
                        'primary_html'   => $bbai_ch_primary_html,
                        'secondary_html' => $bbai_ch_secondary_html,
                        'tertiary_html'  => $bbai_ch_tertiary_html,
                        'actions_append' => $bbai_ch_actions_append,
                        'alignment'      => (string) ($bbai_ch['actions_alignment'] ?? 'default'),
                    ];
                    require BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/components/action-stack.php';
                    ?>
                </div>
            </div>
        </section>
    </div>
</div>
