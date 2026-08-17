<?php
/**
 * Phase 8 — internal UI Kit / component preview (development).
 *
 * Gated by Core::can_show_ui_kit_page(). Uses real shared classes + bbai_ui_render() only.
 *
 * @package BeepBeep_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/ui-components.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/banner-system.php';

/**
 * @param string $class_names Space-separated class list shown as reference.
 */
$bbai_ui_kit_class = static function (string $class_names): void {
    echo '<code class="bbai-ui-kit__class">' . esc_html($class_names) . '</code>';
};

$bbai_kit_snap = bbai_banner_snapshot_merge(
    [
        'missing_count'     => 2,
        'weak_count'        => 5,
        'total_images'      => 120,
        'credits_used'      => 12,
        'credits_limit'     => 50,
        'credits_remaining' => 38,
        'usage_percent'     => 24,
        'is_pro_plan'       => false,
        'is_first_run'      => false,
        'is_low_credits'    => false,
        'is_out_of_credits' => false,
        'plan_label'        => __('Preview plan', 'beepbeep-ai-alt-text-generator'),
    ]
);
$bbai_kit_hero = bbai_banner_build_command_hero(
    BBAI_BANNER_CTX_ANALYTICS,
    $bbai_kit_snap,
    [
        'page_hero_variant'  => 'warning',
        'aria_label'         => __('UI Kit preview banner', 'beepbeep-ai-alt-text-generator'),
        'section_data_attrs' => [
            'data-bbai-ui-kit-banner' => '1',
        ],
    ]
);
?>
<div class="bbai-ui-kit bbai-container" data-bbai-ui-kit="1">
    <div class="bbai-ui-kit__banner" role="note">
        <strong><?php esc_html_e('UI Kit (Phase 8)', 'beepbeep-ai-alt-text-generator'); ?></strong>
        —
        <?php esc_html_e('Internal preview only. Shown when WP_DEBUG is on or BBAI_UI_KIT is true, and the user can manage the plugin.', 'beepbeep-ai-alt-text-generator'); ?>
        <?php esc_html_e('Open from:', 'beepbeep-ai-alt-text-generator'); ?>
        <code>admin.php?page=bbai-ui-kit</code>
    </div>

    <!-- 1. Foundations -->
    <section class="bbai-ui-kit__section" id="bbai-ui-kit-foundations" aria-labelledby="bbai-ui-kit-foundations-h">
        <h2 id="bbai-ui-kit-foundations-h" class="bbai-ui-kit__h2"><?php esc_html_e('1. Foundations', 'beepbeep-ai-alt-text-generator'); ?></h2>
        <p class="bbai-ui-kit__sub"><?php esc_html_e('Typography uses bbai-section-title / bbai-section-label / bbai-section-description on real pages. Tokens: bbai-admin-foundation-tokens.css + unified.', 'beepbeep-ai-alt-text-generator'); ?></p>

        <div class="bbai-ui-kit__group">
            <?php $bbai_ui_kit_class('bbai-section-label'); ?>
            <p class="bbai-section-label"><?php esc_html_e('Section label (eyebrow)', 'beepbeep-ai-alt-text-generator'); ?></p>
            <?php $bbai_ui_kit_class('bbai-section-title'); ?>
            <h3 class="bbai-section-title"><?php esc_html_e('Section title (H3 in kit)', 'beepbeep-ai-alt-text-generator'); ?></h3>
            <?php $bbai_ui_kit_class('bbai-section-description'); ?>
            <p class="bbai-section-description"><?php esc_html_e('Section description — supporting copy for headers and forms.', 'beepbeep-ai-alt-text-generator'); ?></p>
        </div>

        <div class="bbai-ui-kit__group">
            <h3 class="bbai-ui-kit__h3"><?php esc_html_e('Semantic colour tokens (swatches)', 'beepbeep-ai-alt-text-generator'); ?></h3>
            <div class="bbai-ui-kit__token-grid">
                <div class="bbai-ui-kit__token">
                    <div class="bbai-ui-kit__swatch" style="background:var(--bbai-status-optimized-bg);border-color:var(--bbai-status-optimized-border);"></div>
                    <code>--bbai-status-optimized-*</code>
                </div>
                <div class="bbai-ui-kit__token">
                    <div class="bbai-ui-kit__swatch" style="background:var(--bbai-status-needs-review-bg);border-color:var(--bbai-status-needs-review-border);"></div>
                    <code>--bbai-status-needs-review-*</code>
                </div>
                <div class="bbai-ui-kit__token">
                    <div class="bbai-ui-kit__swatch" style="background:var(--bbai-status-missing-bg);border-color:var(--bbai-status-missing-border);"></div>
                    <code>--bbai-status-missing-*</code>
                </div>
            </div>
        </div>

        <div class="bbai-ui-kit__group">
            <h3 class="bbai-ui-kit__h3"><?php esc_html_e('Spacing, radius, shadow', 'beepbeep-ai-alt-text-generator'); ?></h3>
            <div class="bbai-ui-kit__row">
                <div class="bbai-ui-kit__token" style="padding:var(--bbai-admin-space-4);"><?php esc_html_e('Inset', 'beepbeep-ai-alt-text-generator'); ?> <code>--bbai-admin-space-4</code></div>
                <div class="bbai-card bbai-card--pad-sm" style="box-shadow:var(--bbai-surface-card-shadow);"><?php esc_html_e('Card shadow', 'beepbeep-ai-alt-text-generator'); ?></div>
                <div class="bbai-card bbai-card--pad-sm" style="border-radius:var(--bbai-surface-card-radius);"><?php esc_html_e('Card radius', 'beepbeep-ai-alt-text-generator'); ?></div>
            </div>
        </div>

        <div class="bbai-ui-kit__group bbai-ui-kit__focus-demo">
            <h3 class="bbai-ui-kit__h3"><?php esc_html_e('Focus ring (Tab to focus)', 'beepbeep-ai-alt-text-generator'); ?></h3>
            <?php $bbai_ui_kit_class('bbai-btn bbai-btn-primary :focus-visible'); ?>
            <button type="button" class="bbai-btn bbai-btn-primary bbai-btn-sm"><?php esc_html_e('Primary', 'beepbeep-ai-alt-text-generator'); ?></button>
        </div>
    </section>

    <!-- 2. Controls -->
    <section class="bbai-ui-kit__section" id="bbai-ui-kit-controls" aria-labelledby="bbai-ui-kit-controls-h">
        <h2 id="bbai-ui-kit-controls-h" class="bbai-ui-kit__h2"><?php esc_html_e('2. Controls', 'beepbeep-ai-alt-text-generator'); ?></h2>

        <div class="bbai-ui-kit__group">
            <h3 class="bbai-ui-kit__h3"><?php esc_html_e('Buttons', 'beepbeep-ai-alt-text-generator'); ?></h3>
            <div class="bbai-ui-kit__row">
                <div>
                    <?php $bbai_ui_kit_class('bbai-btn bbai-btn-primary'); ?>
                    <button type="button" class="bbai-btn bbai-btn-primary bbai-btn-sm"><?php esc_html_e('Primary', 'beepbeep-ai-alt-text-generator'); ?></button>
                </div>
                <div>
                    <?php $bbai_ui_kit_class('bbai-btn bbai-btn-secondary'); ?>
                    <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm"><?php esc_html_e('Secondary', 'beepbeep-ai-alt-text-generator'); ?></button>
                </div>
                <div>
                    <?php $bbai_ui_kit_class('bbai-btn bbai-btn-ghost'); ?>
                    <button type="button" class="bbai-btn bbai-btn-ghost bbai-btn-sm"><?php esc_html_e('Ghost', 'beepbeep-ai-alt-text-generator'); ?></button>
                </div>
                <div>
                    <?php $bbai_ui_kit_class('bbai-btn bbai-btn-primary bbai-btn-sm :disabled'); ?>
                    <button type="button" class="bbai-btn bbai-btn-primary bbai-btn-sm" disabled><?php esc_html_e('Disabled', 'beepbeep-ai-alt-text-generator'); ?></button>
                </div>
            </div>
        </div>

        <div class="bbai-ui-kit__group">
            <h3 class="bbai-ui-kit__h3"><?php esc_html_e('Icon button', 'beepbeep-ai-alt-text-generator'); ?></h3>
            <?php $bbai_ui_kit_class('bbai-btn bbai-btn--icon'); ?>
            <button type="button" class="bbai-btn bbai-btn--icon" aria-label="<?php esc_attr_e('Preview', 'beepbeep-ai-alt-text-generator'); ?>">
                <span aria-hidden="true">⋯</span>
            </button>
        </div>

        <div class="bbai-ui-kit__group">
            <h3 class="bbai-ui-kit__h3"><?php esc_html_e('Links', 'beepbeep-ai-alt-text-generator'); ?></h3>
            <?php $bbai_ui_kit_class('bbai-link'); ?>
            <p><a href="#" class="bbai-link" onclick="return false;"><?php esc_html_e('Default link', 'beepbeep-ai-alt-text-generator'); ?></a></p>
            <?php $bbai_ui_kit_class('bbai-link bbai-link--muted'); ?>
            <p><a href="#" class="bbai-link bbai-link--muted" onclick="return false;"><?php esc_html_e('Muted link', 'beepbeep-ai-alt-text-generator'); ?></a></p>
            <?php $bbai_ui_kit_class('bbai-link-btn bbai-link-sm'); ?>
            <p><a href="#" class="bbai-link-btn bbai-link-sm" onclick="return false;"><?php esc_html_e('Link button sm', 'beepbeep-ai-alt-text-generator'); ?></a></p>
        </div>

        <div class="bbai-ui-kit__group">
            <h3 class="bbai-ui-kit__h3"><?php esc_html_e('Fields', 'beepbeep-ai-alt-text-generator'); ?></h3>
            <div style="max-width:28rem;display:flex;flex-direction:column;gap:var(--bbai-space-3);">
                <?php $bbai_ui_kit_class('bbai-input'); ?>
                <label class="bbai-section-label" for="bbai-kit-input"><?php esc_html_e('Text input', 'beepbeep-ai-alt-text-generator'); ?></label>
                <input id="bbai-kit-input" type="text" class="bbai-input" placeholder="<?php esc_attr_e('Placeholder', 'beepbeep-ai-alt-text-generator'); ?>" />
                <?php $bbai_ui_kit_class('bbai-textarea'); ?>
                <label class="bbai-section-label" for="bbai-kit-ta"><?php esc_html_e('Textarea', 'beepbeep-ai-alt-text-generator'); ?></label>
                <textarea id="bbai-kit-ta" class="bbai-textarea" rows="2" placeholder="<?php esc_attr_e('Multi-line', 'beepbeep-ai-alt-text-generator'); ?>"></textarea>
                <?php $bbai_ui_kit_class('bbai-select'); ?>
                <label class="bbai-section-label" for="bbai-kit-sel"><?php esc_html_e('Select', 'beepbeep-ai-alt-text-generator'); ?></label>
                <select id="bbai-kit-sel" class="bbai-select">
                    <option><?php esc_html_e('Option A', 'beepbeep-ai-alt-text-generator'); ?></option>
                    <option><?php esc_html_e('Option B', 'beepbeep-ai-alt-text-generator'); ?></option>
                </select>
            </div>
        </div>
    </section>

    <!-- 3. Structural -->
    <section class="bbai-ui-kit__section" id="bbai-ui-kit-structural" aria-labelledby="bbai-ui-kit-structural-h">
        <h2 id="bbai-ui-kit-structural-h" class="bbai-ui-kit__h2"><?php esc_html_e('3. Structural components', 'beepbeep-ai-alt-text-generator'); ?></h2>

        <div class="bbai-ui-kit__group">
            <?php $bbai_ui_kit_class('bbai-card bbai-card--pad-md'); ?>
            <div class="bbai-card bbai-card--pad-md">
                <p class="bbai-section-title" style="margin-top:0;"><?php esc_html_e('Default card shell', 'beepbeep-ai-alt-text-generator'); ?></p>
                <p class="bbai-section-description"><?php esc_html_e('Padding via bbai-card--pad-md.', 'beepbeep-ai-alt-text-generator'); ?></p>
            </div>
        </div>

        <div class="bbai-ui-kit__group">
            <?php $bbai_ui_kit_class('bbai-card bbai-card--compact bbai-card--pad-sm'); ?>
            <div class="bbai-card bbai-card--compact bbai-card--pad-sm">
                <p class="bbai-section-description" style="margin:0;"><?php esc_html_e('Compact card', 'beepbeep-ai-alt-text-generator'); ?></p>
            </div>
        </div>

        <div class="bbai-ui-kit__group">
            <?php $bbai_ui_kit_class('bbai_ui_render( \'section-header\' )'); ?>
            <?php
            bbai_ui_render(
                'section-header',
                [
                    'class'       => 'bbai-ui-section-header bbai-section-header',
                    'eyebrow'     => __('Components', 'beepbeep-ai-alt-text-generator'),
                    'title'       => __('Section header partial', 'beepbeep-ai-alt-text-generator'),
                    'description' => __('Rendered via admin/partials/components/section-header.php.', 'beepbeep-ai-alt-text-generator'),
                ]
            );
            ?>
        </div>

        <div class="bbai-ui-kit__group">
            <?php $bbai_ui_kit_class('hr.bbai-surface-divider'); ?>
            <p class="bbai-section-description"><?php esc_html_e('Divider', 'beepbeep-ai-alt-text-generator'); ?></p>
            <hr class="bbai-surface-divider" />
        </div>
    </section>

    <!-- 4. Semantic -->
    <section class="bbai-ui-kit__section" id="bbai-ui-kit-semantic" aria-labelledby="bbai-ui-kit-semantic-h">
        <h2 id="bbai-ui-kit-semantic-h" class="bbai-ui-kit__h2"><?php esc_html_e('4. Semantic components', 'beepbeep-ai-alt-text-generator'); ?></h2>

        <div class="bbai-ui-kit__group">
            <h3 class="bbai-ui-kit__h3"><?php esc_html_e('Badges', 'beepbeep-ai-alt-text-generator'); ?></h3>
            <div class="bbai-ui-kit__row">
                <span class="bbai-badge bbai-badge--missing"><?php esc_html_e('Missing', 'beepbeep-ai-alt-text-generator'); ?></span>
                <span class="bbai-badge bbai-badge--needs-review"><?php esc_html_e('Needs review', 'beepbeep-ai-alt-text-generator'); ?></span>
                <span class="bbai-badge bbai-badge--optimized"><?php esc_html_e('Optimized', 'beepbeep-ai-alt-text-generator'); ?></span>
                <span class="bbai-badge bbai-badge--neutral"><?php esc_html_e('Neutral', 'beepbeep-ai-alt-text-generator'); ?></span>
            </div>
            <?php $bbai_ui_kit_class('bbai-badge bbai-badge--{variant}'); ?>
        </div>

        <div class="bbai-ui-kit__group">
            <h3 class="bbai-ui-kit__h3"><?php esc_html_e('Score pills', 'beepbeep-ai-alt-text-generator'); ?></h3>
            <div class="bbai-ui-kit__row">
                <span class="bbai-score-pill bbai-score-pill--excellent"><?php esc_html_e('Excellent', 'beepbeep-ai-alt-text-generator'); ?></span>
                <span class="bbai-score-pill bbai-score-pill--good"><?php esc_html_e('Good', 'beepbeep-ai-alt-text-generator'); ?></span>
                <span class="bbai-score-pill bbai-score-pill--needs-improvement"><?php esc_html_e('Needs improvement', 'beepbeep-ai-alt-text-generator'); ?></span>
                <span class="bbai-score-pill bbai-score-pill--poor"><?php esc_html_e('Poor', 'beepbeep-ai-alt-text-generator'); ?></span>
                <span class="bbai-score-pill bbai-score-pill--critical"><?php esc_html_e('Critical', 'beepbeep-ai-alt-text-generator'); ?></span>
            </div>
            <?php $bbai_ui_kit_class('bbai-score-pill bbai-score-pill--{tier}'); ?>
        </div>

        <div class="bbai-ui-kit__group">
            <h3 class="bbai-ui-kit__h3"><?php esc_html_e('Filter group (filter mode)', 'beepbeep-ai-alt-text-generator'); ?></h3>
            <?php $bbai_ui_kit_class('bbai_ui_render( \'filter-group\' )'); ?>
            <?php
            bbai_ui_render(
                'filter-group',
                [
                    'id'          => 'bbai-ui-kit-filters',
                    'aria_label'  => __('Preview filters', 'beepbeep-ai-alt-text-generator'),
                    'variant'     => 'horizontal',
                    'interaction_mode' => 'filter',
                    'default_filter'     => 'all',
                    'items'       => [
                        ['key' => 'all', 'label' => __('All', 'beepbeep-ai-alt-text-generator'), 'count' => 120, 'active' => true],
                        ['key' => 'missing', 'label' => __('Missing', 'beepbeep-ai-alt-text-generator'), 'count' => 3, 'active' => false, 'attention' => true],
                        ['key' => 'weak', 'label' => __('Review', 'beepbeep-ai-alt-text-generator'), 'count' => 7, 'active' => false, 'attention' => true],
                        ['key' => 'optimized', 'label' => __('Done', 'beepbeep-ai-alt-text-generator'), 'count' => 110, 'active' => false],
                    ],
                ]
            );
            ?>
        </div>

        <div class="bbai-ui-kit__group">
            <h3 class="bbai-ui-kit__h3"><?php esc_html_e('Row left accent', 'beepbeep-ai-alt-text-generator'); ?></h3>
            <div class="bbai-ui-kit__row" style="flex-direction:column;gap:var(--bbai-space-2);max-width:40rem;">
                <div class="bbai-row-accent bbai-row-accent--missing bbai-card bbai-card--pad-sm"><?php $bbai_ui_kit_class('bbai-row-accent bbai-row-accent--missing'); ?><?php esc_html_e('Missing rail / wash', 'beepbeep-ai-alt-text-generator'); ?></div>
                <div class="bbai-row-accent bbai-row-accent--needs-review bbai-card bbai-card--pad-sm"><?php $bbai_ui_kit_class('bbai-row-accent bbai-row-accent--needs-review'); ?><?php esc_html_e('Needs review', 'beepbeep-ai-alt-text-generator'); ?></div>
                <div class="bbai-row-accent bbai-row-accent--optimized bbai-card bbai-card--pad-sm"><?php $bbai_ui_kit_class('bbai-row-accent bbai-row-accent--optimized'); ?><?php esc_html_e('Optimized', 'beepbeep-ai-alt-text-generator'); ?></div>
            </div>
        </div>
    </section>

    <!-- 5. Table / row -->
    <section class="bbai-ui-kit__section" id="bbai-ui-kit-table" aria-labelledby="bbai-ui-kit-table-h">
        <h2 id="bbai-ui-kit-table-h" class="bbai-ui-kit__h2"><?php esc_html_e('5. Table / row system', 'beepbeep-ai-alt-text-generator'); ?></h2>

        <div class="bbai-ui-kit__group bbai-ui-kit__table-wrap">
            <?php $bbai_ui_kit_class('table.bbai-table'); ?>
            <table class="bbai-table widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Column A', 'beepbeep-ai-alt-text-generator'); ?></th>
                        <th><?php esc_html_e('Column B', 'beepbeep-ai-alt-text-generator'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php esc_html_e('Semantic table cell', 'beepbeep-ai-alt-text-generator'); ?></td>
                        <td><?php esc_html_e('Hover row for background', 'beepbeep-ai-alt-text-generator'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="bbai-ui-kit__group bbai-library-workspace">
            <h3 class="bbai-ui-kit__h3"><?php esc_html_e('Library-style row (simplified)', 'beepbeep-ai-alt-text-generator'); ?></h3>
            <?php $bbai_ui_kit_class('bbai-library-review-card bbai-row'); ?>
            <article class="bbai-library-review-card bbai-row bbai-card" style="padding:var(--bbai-space-3);">
                <div class="bbai-library-card__main bbai-row__main">
                    <div class="bbai-row__meta">
                        <strong><?php esc_html_e('Asset', 'beepbeep-ai-alt-text-generator'); ?></strong>
                        <div class="bbai-table-subtitle"><?php esc_html_e('example.jpg', 'beepbeep-ai-alt-text-generator'); ?></div>
                    </div>
                    <div class="bbai-row__status">
                        <span class="bbai-badge bbai-badge--optimized"><?php esc_html_e('Optimized', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </div>
                    <div class="bbai-row__content">
                        <p class="bbai-section-description" style="margin:0;"><?php esc_html_e('ALT preview text…', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                    <div class="bbai-row__actions bbai-row-actions">
                        <button type="button" class="bbai-row-action-btn bbai-row-action-btn--primary bbai-btn-sm"><?php esc_html_e('Regenerate', 'beepbeep-ai-alt-text-generator'); ?></button>
                    </div>
                </div>
            </article>
        </div>

        <div class="bbai-ui-kit__group bbai-credit-usage-page">
            <h3 class="bbai-ui-kit__h3"><?php esc_html_e('Phase 11 — product states', 'beepbeep-ai-alt-text-generator'); ?></h3>
            <?php $bbai_ui_kit_class('bbai_ui_render( \'product-state\' )'); ?>
            <div style="display:flex;flex-direction:column;gap:var(--bbai-space-6);max-width:42rem;">
                <?php
                bbai_ui_render(
                    'product-state',
                    [
                        'variant'       => 'loading',
                        'show_spinner'  => true,
                        'title'         => __('Loading', 'beepbeep-ai-alt-text-generator'),
                        'body'          => __('Fetching the latest data — this stays visible until the request finishes.', 'beepbeep-ai-alt-text-generator'),
                    ]
                );
                bbai_ui_render(
                    'product-state',
                    [
                        'variant'      => 'empty',
                        'title'        => __('Nothing here yet', 'beepbeep-ai-alt-text-generator'),
                        'body'         => __('Empty states use the same title, body, and actions structure everywhere.', 'beepbeep-ai-alt-text-generator'),
                        'actions_html' => '<button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" disabled>' . esc_html__('Primary action', 'beepbeep-ai-alt-text-generator') . '</button>',
                        'root_class'   => 'bbai-credit-activity-state',
                    ]
                );
                bbai_ui_render(
                    'product-state',
                    [
                        'variant'      => 'error',
                        'title'        => __('Something went wrong', 'beepbeep-ai-alt-text-generator'),
                        'body'         => __('Short explanation — no raw API output. Offer a clear next step.', 'beepbeep-ai-alt-text-generator'),
                        'actions_html' => '<button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" disabled>' . esc_html__('Retry', 'beepbeep-ai-alt-text-generator') . '</button>',
                    ]
                );
                bbai_ui_render(
                    'product-state',
                    [
                        'variant'      => 'retry',
                        'title'        => __('Couldn’t refresh', 'beepbeep-ai-alt-text-generator'),
                        'body'         => __('Retry resets the attempt; the previous message is replaced when a new load starts.', 'beepbeep-ai-alt-text-generator'),
                        'actions_html' => '<button type="button" class="bbai-btn bbai-btn-primary bbai-btn-sm" disabled>' . esc_html__('Try again', 'beepbeep-ai-alt-text-generator') . '</button>',
                    ]
                );
                bbai_ui_render(
                    'product-state',
                    [
                        'variant' => 'partial',
                        'title'   => __('Partially updated', 'beepbeep-ai-alt-text-generator'),
                        'body'    => __('Example: ALT saved, but the quality score is still loading. Nothing failed silently.', 'beepbeep-ai-alt-text-generator'),
                        'meta'    => __('You can keep working; we’ll finish the rest in the background.', 'beepbeep-ai-alt-text-generator'),
                    ]
                );
                ?>
            </div>
            <div class="bbai-ui-kit__pagination-demo" aria-hidden="true">
                <span class="page-numbers">1</span>
                <span class="page-numbers current">2</span>
                <span class="page-numbers">3</span>
            </div>
        </div>
    </section>

    <!-- 6. Composed -->
    <section class="bbai-ui-kit__section" id="bbai-ui-kit-composed" aria-labelledby="bbai-ui-kit-composed-h">
        <h2 id="bbai-ui-kit-composed-h" class="bbai-ui-kit__h2"><?php esc_html_e('6. Composed product patterns', 'beepbeep-ai-alt-text-generator'); ?></h2>

        <div class="bbai-ui-kit__group">
            <h3 class="bbai-ui-kit__h3"><?php esc_html_e('Command banner', 'beepbeep-ai-alt-text-generator'); ?></h3>
            <?php $bbai_ui_kit_class('bbai_ui_render( \'bbai-banner\' )'); ?>
            <?php bbai_ui_render('bbai-banner', ['command_hero' => $bbai_kit_hero]); ?>
        </div>

        <div class="bbai-ui-kit__group">
            <h3 class="bbai-ui-kit__h3"><?php esc_html_e('Dashboard stat cards', 'beepbeep-ai-alt-text-generator'); ?></h3>
            <?php $bbai_ui_kit_class('bbai_ui_render( \'metrics-grid\' | \'stat-card\' )'); ?>
            <?php
            bbai_ui_render('metrics-grid', ['phase' => 'open', 'aria_label' => __('Preview metrics', 'beepbeep-ai-alt-text-generator')]);
            bbai_ui_render('stat-card', ['value' => '1,240', 'label' => __('images optimised', 'beepbeep-ai-alt-text-generator')]);
            bbai_ui_render('stat-card', ['value' => '~2h', 'label' => __('time saved', 'beepbeep-ai-alt-text-generator')]);
            bbai_ui_render('stat-card', ['value' => '38', 'label' => __('credits left', 'beepbeep-ai-alt-text-generator'), 'root_class' => 'bbai-analytics-metric-card--warn']);
            bbai_ui_render('metrics-grid', ['phase' => 'close']);
            ?>
        </div>

        <div class="bbai-ui-kit__group">
            <h3 class="bbai-ui-kit__h3"><?php esc_html_e('Notice strips', 'beepbeep-ai-alt-text-generator'); ?></h3>
            <?php
            bbai_ui_render('notice-strip', ['variant' => 'neutral', 'message' => __('Neutral notice copy.', 'beepbeep-ai-alt-text-generator')]);
            bbai_ui_render('notice-strip', ['variant' => 'info', 'message' => __('Info notice copy.', 'beepbeep-ai-alt-text-generator')]);
            ?>
        </div>

        <div class="bbai-ui-kit__group">
            <h3 class="bbai-ui-kit__h3"><?php esc_html_e('Usage insights (standard card)', 'beepbeep-ai-alt-text-generator'); ?></h3>
            <?php $bbai_ui_kit_class('bbai-card | bbai-usage-insights-card | bbai-usage-insights-card--warning'); ?>
            <?php
            $bbai_ins_stack_lines = [
                __('Preview: same card partial as the Usage tab — not a command banner.', 'beepbeep-ai-alt-text-generator'),
                __('Tone: bbai-usage-insights-card--healthy|warning|danger on the section.', 'beepbeep-ai-alt-text-generator'),
            ];
            $bbai_ins_chips = [
                ['label' => __('Chip A', 'beepbeep-ai-alt-text-generator')],
                ['label' => __('Chip B', 'beepbeep-ai-alt-text-generator')],
            ];
            $bbai_ins_tone = 'warning';
            require BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/components/usage-insights-card.php';
            ?>
        </div>

        <div class="bbai-ui-kit__group">
            <h3 class="bbai-ui-kit__h3"><?php esc_html_e('Workspace shell', 'beepbeep-ai-alt-text-generator'); ?></h3>
            <?php $bbai_ui_kit_class('page-shell | workspace-grid | section-card | section-header | filter-group'); ?>
            <?php
            bbai_ui_render('page-shell', ['phase' => 'open', 'class' => 'bbai-ui-kit-workspace-shell']);
            bbai_ui_render('workspace-grid', ['phase' => 'open', 'class' => 'bbai-analytics-workspace']);
            bbai_ui_render('workspace-grid', ['phase' => 'main_open']);
            bbai_ui_render('section-card', ['phase' => 'open', 'tag' => 'div', 'class' => 'bbai-ui-kit-workspace-main']);
            bbai_ui_render(
                'section-header',
                [
                    'title'       => __('Workspace preview', 'beepbeep-ai-alt-text-generator'),
                    'description' => __('Filters + table region in real pages use this composition.', 'beepbeep-ai-alt-text-generator'),
                ]
            );
            bbai_ui_render(
                'filter-group',
                [
                    'id'         => 'bbai-ui-kit-ws-filters',
                    'aria_label' => __('Workspace filters', 'beepbeep-ai-alt-text-generator'),
                    'items'      => [
                        ['key' => 'all', 'label' => __('All', 'beepbeep-ai-alt-text-generator'), 'count' => 10, 'active' => true],
                        ['key' => 'missing', 'label' => __('Missing', 'beepbeep-ai-alt-text-generator'), 'count' => 2, 'active' => false],
                    ],
                ]
            );
            echo '<div class="bbai-table-wrap" style="margin-top:var(--bbai-space-4);"><table class="bbai-table"><thead><tr><th>';
            esc_html_e('Col', 'beepbeep-ai-alt-text-generator');
            echo '</th></tr></thead><tbody><tr><td>';
            esc_html_e('Row', 'beepbeep-ai-alt-text-generator');
            echo '</td></tr></tbody></table></div>';
            bbai_ui_render('section-card', ['phase' => 'close', 'close_tag' => 'div']);
            bbai_ui_render('workspace-grid', ['phase' => 'main_close']);
            bbai_ui_render(
                'sidebar-card',
                [
                    'title'  => __('Side rail', 'beepbeep-ai-alt-text-generator'),
                    'body'   => __('Short supporting copy.', 'beepbeep-ai-alt-text-generator'),
                    'aria_label' => __('Preview rail', 'beepbeep-ai-alt-text-generator'),
                ]
            );
            bbai_ui_render('workspace-grid', ['phase' => 'close']);
            bbai_ui_render('page-shell', ['phase' => 'close']);
            ?>
        </div>
    </section>
</div>
