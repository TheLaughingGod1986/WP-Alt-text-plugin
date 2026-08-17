<?php
/**
 * Adaptive Upgrade Panel — reusable component.
 *
 * Renders a contextual, state-aware upgrade strip across all plugin pages.
 * Designed to replace all static upgrade cards / upsell blocks.
 *
 * Expects ONE variable to be set before including:
 *   $bbai_panel  (array)  — from BBAI_Upgrade_State::resolve().
 *
 * Usage:
 *   require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-upgrade-state.php';
 *   $bbai_panel = \BeepBeepAI\AltTextGenerator\BBAI_Upgrade_State::resolve([
 *       'used'         => $used,
 *       'limit'        => $limit,
 *       'remaining'    => $remaining,
 *       'coverage_pct' => $coverage_pct,
 *       'total_images' => $total_images,
 *       'is_pro'       => $is_pro,
 *       'is_agency'    => $is_agency,
 *       'days_reset'   => $days_reset,
 *       'upgrade_url'  => $upgrade_url,
 *       'context'      => 'library', // 'dashboard'|'library'|'analytics'|'usage'
 *   ]);
 *   include BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/upgrade-panel.php';
 *
 * @package BeepBeep_AI_Alt_Text_Generator
 * @since   6.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bbai_panel = isset( $bbai_panel ) && is_array( $bbai_panel ) ? $bbai_panel : [];

// Nothing to render — hidden state, pro plan, agency, etc.
if ( empty( $bbai_panel['visible'] ) ) {
	return;
}

$bbai_up_state     = (string) ( $bbai_panel['state']    ?? 'default' );
$bbai_up_tone      = (string) ( $bbai_panel['tone']     ?? 'neutral' );
$bbai_up_headline  = (string) ( $bbai_panel['headline'] ?? '' );
$bbai_up_body      = (string) ( $bbai_panel['body']     ?? '' );
$bbai_up_primary   = is_array( $bbai_panel['primary']   ?? null ) ? $bbai_panel['primary']   : [];
$bbai_up_secondary = is_array( $bbai_panel['secondary'] ?? null ) ? $bbai_panel['secondary'] : [];

// Build primary button attributes.
$bbai_up_primary_action = trim( (string) ( $bbai_up_primary['action'] ?? '' ) );
$bbai_up_primary_href   = trim( (string) ( $bbai_up_primary['href']   ?? '#' ) );
$bbai_up_primary_label  = trim( (string) ( $bbai_up_primary['label']  ?? '' ) );

// Build secondary link attributes.
$bbai_up_secondary_label  = trim( (string) ( $bbai_up_secondary['label']  ?? '' ) );
$bbai_up_secondary_action = trim( (string) ( $bbai_up_secondary['action'] ?? '' ) );
$bbai_up_secondary_href   = trim( (string) ( $bbai_up_secondary['href']   ?? '#' ) );
$bbai_up_secondary_target = trim( (string) ( $bbai_up_secondary['target'] ?? '' ) );

$bbai_up_has_secondary = '' !== $bbai_up_secondary_label;
?>
<style id="bbai-upgrade-panel-style">
/*
 * Upgrade Panel — scoped styles
 * All rules namespaced to .bbai-upgrade-panel to avoid conflicts.
 */
.bbai-upgrade-panel {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    padding: 20px 24px;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    margin-bottom: 20px;
    position: relative;
    overflow: hidden;
}

/* Subtle grain texture overlay */
.bbai-upgrade-panel::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: linear-gradient(135deg, transparent 55%, rgba(15,23,42,0.02) 100%);
    pointer-events: none;
}

/* ── Tones ─────────────────────────────────────────────────────── */
.bbai-upgrade-panel--urgent {
    border-color: #fca5a5;
    background: linear-gradient(135deg, #fff5f5 0%, #ffffff 60%);
}
.bbai-upgrade-panel--warning {
    border-color: #fde68a;
    background: linear-gradient(135deg, #fffdf0 0%, #ffffff 60%);
}
.bbai-upgrade-panel--healthy {
    border-color: #bbf7d0;
    background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 60%);
}
.bbai-upgrade-panel--neutral {
    border-color: #e2e8f0;
    background: linear-gradient(135deg, #f8fafc 0%, #ffffff 60%);
}

/* ── Icon ──────────────────────────────────────────────────────── */
.bbai-upgrade-panel__icon {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    margin-top: 1px;
}
.bbai-upgrade-panel--urgent  .bbai-upgrade-panel__icon { background: #fee2e2; color: #dc2626; }
.bbai-upgrade-panel--warning .bbai-upgrade-panel__icon { background: #fef3c7; color: #d97706; }
.bbai-upgrade-panel--healthy .bbai-upgrade-panel__icon { background: #dcfce7; color: #16a34a; }
.bbai-upgrade-panel--neutral .bbai-upgrade-panel__icon { background: #f1f5f9; color: #475569; }

/* ── Copy ──────────────────────────────────────────────────────── */
.bbai-upgrade-panel__copy {
    flex: 1;
    min-width: 0;
}
.bbai-upgrade-panel__headline {
    margin: 0 0 4px;
    font-size: 15px;
    font-weight: 700;
    line-height: 1.3;
    color: #0f172a;
}
.bbai-upgrade-panel__body {
    margin: 0;
    font-size: 13px;
    line-height: 1.55;
    color: #475569;
    max-width: 540px;
}

/* ── Actions ───────────────────────────────────────────────────── */
.bbai-upgrade-panel__actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: nowrap;
    flex: 0 0 auto;
}

.bbai-upgrade-panel__btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 10px 18px;
    border-radius: 10px;
    border: none;
    font-size: 13px;
    font-weight: 700;
    line-height: 1;
    cursor: pointer;
    white-space: nowrap;
    text-decoration: none;
    transition: background .14s ease, box-shadow .14s ease, border-color .14s ease;
}

/* Primary */
.bbai-upgrade-panel__btn--primary {
    background: #0f172a;
    color: #ffffff;
    border: 1px solid #0f172a;
}
.bbai-upgrade-panel__btn--primary:hover,
.bbai-upgrade-panel__btn--primary:focus-visible {
    background: #1e293b;
    color: #ffffff;
    box-shadow: 0 6px 16px rgba(15,23,42,0.18);
}
.bbai-upgrade-panel--urgent .bbai-upgrade-panel__btn--primary {
    background: #dc2626;
    border-color: #dc2626;
}
.bbai-upgrade-panel--urgent .bbai-upgrade-panel__btn--primary:hover {
    background: #b91c1c;
    border-color: #b91c1c;
}

/* Secondary (ghost) */
.bbai-upgrade-panel__btn--secondary {
    background: transparent;
    color: #475569;
    border: 1.5px solid #d1d5db;
}
.bbai-upgrade-panel__btn--secondary:hover,
.bbai-upgrade-panel__btn--secondary:focus-visible {
    border-color: #94a3b8;
    background: #f8fafc;
    color: #0f172a;
}

/* ── Responsive ────────────────────────────────────────────────── */
@media (max-width: 900px) {
    .bbai-upgrade-panel {
        flex-wrap: wrap;
    }
    .bbai-upgrade-panel__actions {
        flex-wrap: wrap;
        width: 100%;
    }
    .bbai-upgrade-panel__btn {
        flex: 1;
        justify-content: center;
    }
}
@media (max-width: 600px) {
    .bbai-upgrade-panel {
        padding: 16px 18px;
        gap: 14px;
    }
    .bbai-upgrade-panel__icon { display: none; }
}
</style>

<div
    class="bbai-upgrade-panel bbai-upgrade-panel--<?php echo esc_attr( $bbai_up_tone ); ?>"
    data-bbai-upgrade-panel="1"
    data-bbai-panel-state="<?php echo esc_attr( $bbai_up_state ); ?>"
    role="region"
    aria-label="<?php esc_attr_e( 'Upgrade suggestion', 'beepbeep-ai-alt-text-generator' ); ?>"
>
    <!-- Icon -->
    <div class="bbai-upgrade-panel__icon" aria-hidden="true">
        <?php if ( 'urgent' === $bbai_up_tone ) : ?>
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                <path d="M9 2L16.5 15H1.5L9 2Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                <path d="M9 7V10.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                <circle cx="9" cy="13" r="1" fill="currentColor"/>
            </svg>
        <?php elseif ( 'warning' === $bbai_up_tone ) : ?>
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                <path d="M9 2L16.5 15H1.5L9 2Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                <path d="M9 7V10.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                <circle cx="9" cy="13" r="1" fill="currentColor"/>
            </svg>
        <?php elseif ( 'healthy' === $bbai_up_tone ) : ?>
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                <circle cx="9" cy="9" r="7.5" stroke="currentColor" stroke-width="1.6"/>
                <path d="M5.5 9L7.8 11.3L12.5 6.7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        <?php else : ?>
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                <path d="M9 3.5L11.5 8.5H16L12.5 11.5L14 16L9 13L4 16L5.5 11.5L2 8.5H6.5L9 3.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
            </svg>
        <?php endif; ?>
    </div>

    <!-- Copy -->
    <div class="bbai-upgrade-panel__copy">
        <?php if ( '' !== $bbai_up_headline ) : ?>
            <p class="bbai-upgrade-panel__headline"><?php echo esc_html( $bbai_up_headline ); ?></p>
        <?php endif; ?>
        <?php if ( '' !== $bbai_up_body ) : ?>
            <p class="bbai-upgrade-panel__body"><?php echo esc_html( $bbai_up_body ); ?></p>
        <?php endif; ?>
    </div>

    <!-- Actions -->
    <?php if ( '' !== $bbai_up_primary_label ) : ?>
        <div class="bbai-upgrade-panel__actions">

            <!-- Primary CTA -->
            <button
                type="button"
                class="bbai-upgrade-panel__btn bbai-upgrade-panel__btn--primary"
                <?php if ( '' !== $bbai_up_primary_action ) : ?>
                    data-action="<?php echo esc_attr( $bbai_up_primary_action ); ?>"
                <?php endif; ?>
                aria-label="<?php echo esc_attr( $bbai_up_primary_label ); ?>"
            >
                <?php echo esc_html( $bbai_up_primary_label ); ?>
            </button>

            <!-- Secondary CTA (optional) -->
            <?php if ( $bbai_up_has_secondary ) : ?>
                <?php
                $bbai_up_sec_is_external = '_blank' === $bbai_up_secondary_target;
                $bbai_up_sec_tag         = '' !== $bbai_up_secondary_action && '#' === $bbai_up_secondary_href
                    ? 'button'
                    : 'a';
                ?>
                <<?php echo esc_html( $bbai_up_sec_tag ); ?>
                    class="bbai-upgrade-panel__btn bbai-upgrade-panel__btn--secondary"
                    <?php if ( 'button' === $bbai_up_sec_tag ) : ?>
                        type="button"
                        data-action="<?php echo esc_attr( $bbai_up_secondary_action ); ?>"
                    <?php else : ?>
                        href="<?php echo esc_url( $bbai_up_secondary_href ); ?>"
                        <?php if ( $bbai_up_sec_is_external ) : ?>
                            target="_blank"
                            rel="noopener noreferrer"
                        <?php endif; ?>
                    <?php endif; ?>
                    aria-label="<?php echo esc_attr( $bbai_up_secondary_label ); ?>"
                ><?php echo esc_html( $bbai_up_secondary_label ); ?></<?php echo esc_html( $bbai_up_sec_tag ); ?>>
            <?php endif; ?>

        </div>
    <?php endif; ?>
</div><!-- /.bbai-upgrade-panel -->
