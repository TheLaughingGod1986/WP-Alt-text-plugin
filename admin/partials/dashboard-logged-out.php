<?php
/**
 * Logged-Out Dashboard State
 * Clean onboarding screen for unauthenticated users.
 * Includes trial mode banner when free generations remain.
 *
 * @package BeepBeep_AI
 * @since 6.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/ui-components.php';

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-trial-quota.php';
$bbai_trial_status      = \BeepBeepAI\AltTextGenerator\Trial_Quota::get_status();
$bbai_trial_limit       = max(0, (int) ($bbai_trial_status['limit'] ?? \BeepBeepAI\AltTextGenerator\Trial_Quota::get_limit()));
$bbai_trial_used        = max(0, (int) ($bbai_trial_status['used'] ?? 0));
$bbai_trial_remaining   = max(0, (int) ($bbai_trial_status['remaining'] ?? max(0, $bbai_trial_limit - $bbai_trial_used)));
$bbai_trial_exhausted   = isset($bbai_trial_status['exhausted'])
    ? (bool) $bbai_trial_status['exhausted']
    : ($bbai_trial_remaining <= 0);
$bbai_trial_exhausted   = $bbai_trial_exhausted || $bbai_trial_remaining <= 0;
/**
 * Single source of truth for the logged-out hero state.
 *
 * trial_available — free trial credits remain.
 * trial_complete  — no free trial credits remain; show signup/login instead.
 */
$bbai_ftue_post_free_trial = $bbai_trial_exhausted || $bbai_trial_remaining <= 0;
$bbai_hero_state           = $bbai_ftue_post_free_trial ? 'trial_complete' : 'trial_available';

// Count images missing alt text.
$bbai_missing_alt_count = (int) $GLOBALS['wpdb']->get_var(
    "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->posts} p
     LEFT JOIN {$GLOBALS['wpdb']->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
     WHERE p.post_type = 'attachment'
     AND p.post_mime_type LIKE 'image/%'
     AND p.post_status = 'inherit'
     AND (pm.meta_value IS NULL OR pm.meta_value = '')"
); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time count for UI display.

$bbai_missing_alt_count_display = number_format_i18n(absint($bbai_missing_alt_count));
$bbai_missing_alt_message = sprintf(
    /* translators: %s: count of images missing alt text. */
    _n(
        'You have %s image missing alt text',
        'You have %s images missing alt text',
        absint($bbai_missing_alt_count),
        'beepbeep-ai-alt-text-generator'
    ),
    $bbai_missing_alt_count_display
);
$bbai_remaining_without_alt_message = sprintf(
    /* translators: %s: count of images still missing alt text. */
    _n(
        'You still have %s image without alt text.',
        'You still have %s images without alt text.',
        absint($bbai_missing_alt_count),
        'beepbeep-ai-alt-text-generator'
    ),
    $bbai_missing_alt_count_display
);

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
$bbai_page_input = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : 'bbai';
$bbai_current_page = $bbai_page_input ?: 'bbai';
$bbai_fallback_url = admin_url('admin.php?page=' . $bbai_current_page);
?>

<div class="bbai-logged-out" role="main" data-hero-state="<?php echo esc_attr($bbai_hero_state); ?>" aria-label="<?php esc_attr_e('Get started with BeepBeep AI', 'beepbeep-ai-alt-text-generator'); ?>">
    <div class="bbai-logged-out__container">
        <div class="bbai-logged-out__card bbai-ftue-card">
            <div id="bbai-ftue-panel-trial" class="bbai-ftue-panel bbai-ftue-panel--trial"<?php echo $bbai_ftue_post_free_trial ? ' hidden' : ''; ?>>
                <?php
                bbai_ui_render(
                    'banner',
                    [
                        'type'        => 'info',
                        'title'       => __('Free trial active', 'beepbeep-ai-alt-text-generator'),
                        'description' => sprintf(
                            /* translators: 1: remaining count, 2: total limit */
                            __('Trial: %1$d of %2$d free generations remaining', 'beepbeep-ai-alt-text-generator'),
                            (int) $bbai_trial_remaining,
                            (int) $bbai_trial_limit
                        ),
                        'description_attrs' => [
                            'id' => 'bbai-trial-banner-text',
                            'data-bbai-trial-banner-text' => '1',
                        ],
                        'extra_class' => 'bbai-trial-banner bbai-unified-trial-banner',
                    ]
                );
                ?>

                <header class="bbai-logged-out__header">
                    <h1 id="bbai-logged-out-title-trial" class="bbai-logged-out__title">
                        <?php esc_html_e('Automatically generate alt text for your WordPress and WooCommerce images', 'beepbeep-ai-alt-text-generator'); ?>
                    </h1>
                    <p class="bbai-logged-out__subtitle">
                        <?php esc_html_e('Optimized for WooCommerce product images and WordPress media libraries.', 'beepbeep-ai-alt-text-generator'); ?>
                    </p>
                    <p class="bbai-ftue-scan-status" id="bbai-scan-status" role="status" aria-live="polite">
                        <?php esc_html_e('Scanning your media library for images missing alt text...', 'beepbeep-ai-alt-text-generator'); ?>
                    </p>
                    <p class="bbai-ftue-missing-count" id="bbai-missing-summary" role="status" aria-live="polite">
                        <?php echo esc_html($bbai_missing_alt_message); ?>
                    </p>
                </header>

                <?php if ('trial_available' === $bbai_hero_state) : ?>
                <div class="bbai-ftue-actions" id="bbai-ftue-trial-actions">
                    <button type="button" class="button button-primary button-large bbai-btn bbai-btn-primary bbai-btn-lg" id="bbai-trial-generate-btn">
                        <?php esc_html_e('Generate alt text for 3 images (free)', 'beepbeep-ai-alt-text-generator'); ?>
                    </button>
                    <p class="description bbai-ftue-action-note" id="bbai-ftue-action-note">
                        <?php esc_html_e('No account needed. Takes ~10 seconds.', 'beepbeep-ai-alt-text-generator'); ?>
                    </p>
                    <a
                        class="button button-secondary bbai-btn bbai-btn-secondary"
                        id="bbai-secondary-action"
                        href="<?php echo esc_url($bbai_fallback_url); ?>"
                        data-action="show-auth-modal"
                        data-auth-tab="register"
                    >
                        <?php esc_html_e('Unlock 50/month (free)', 'beepbeep-ai-alt-text-generator'); ?>
                    </a>
                    <a
                        id="bbai-signup-trigger"
                        class="bbai-ftue-hidden-signup"
                        href="<?php echo esc_url($bbai_fallback_url); ?>"
                        data-action="show-auth-modal"
                        data-auth-tab="register"
                        aria-hidden="true"
                        tabindex="-1"
                    >
                        <?php esc_html_e('Sign up', 'beepbeep-ai-alt-text-generator'); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <div id="bbai-ftue-panel-conversion" class="bbai-ftue-panel bbai-ftue-panel--conversion"<?php echo $bbai_ftue_post_free_trial ? '' : ' hidden'; ?>>
                <header class="bbai-logged-out__header">
                    <h1 id="bbai-logged-out-title-conversion" class="bbai-logged-out__title">
                        <?php esc_html_e('You’ve generated your first alt text 🎉', 'beepbeep-ai-alt-text-generator'); ?>
                    </h1>
                    <p class="bbai-logged-out__subtitle">
                        <?php esc_html_e('Continue generating alt text across your media library.', 'beepbeep-ai-alt-text-generator'); ?>
                    </p>
                    <p class="bbai-ftue-missing-count" id="bbai-missing-summary-conversion" role="status" aria-live="polite">
                        <?php echo esc_html($bbai_remaining_without_alt_message); ?>
                    </p>
                </header>

                <div class="bbai-ftue-actions" id="bbai-ftue-conversion-actions">
                    <a
                        class="button button-primary button-large bbai-btn bbai-btn-primary bbai-btn-lg"
                        id="bbai-conversion-register-btn"
                        href="<?php echo esc_url($bbai_fallback_url); ?>"
                        data-action="show-auth-modal"
                        data-auth-tab="register"
                    >
                        <?php esc_html_e('Create free account (50 images/month)', 'beepbeep-ai-alt-text-generator'); ?>
                    </a>
                    <a
                        class="bbai-ftue-secondary-link bbai-link bbai-link--muted"
                        id="bbai-conversion-login-btn"
                        href="<?php echo esc_url($bbai_fallback_url); ?>"
                        data-action="show-auth-modal"
                        data-auth-tab="login"
                    >
                        <?php esc_html_e('Already have an account? Log in', 'beepbeep-ai-alt-text-generator'); ?>
                    </a>
                </div>

                <section class="bbai-ftue-benefits" aria-label="<?php esc_attr_e('Benefits', 'beepbeep-ai-alt-text-generator'); ?>">
                    <ul class="bbai-ftue-benefits__list">
                        <li><?php esc_html_e('Keep product and catalog images optimized for SEO.', 'beepbeep-ai-alt-text-generator'); ?></li>
                        <li><?php esc_html_e('Maintain accessibility coverage across your media library.', 'beepbeep-ai-alt-text-generator'); ?></li>
                        <li><?php esc_html_e('Scale alt text updates quickly with bulk generation.', 'beepbeep-ai-alt-text-generator'); ?></li>
                    </ul>
                </section>
            </div>

            <section class="bbai-ftue-progress" id="bbai-ftue-progress" hidden aria-live="polite">
                <div class="bbai-ftue-progress__row">
                    <strong id="bbai-progress-label"><?php esc_html_e('Generating alt text...', 'beepbeep-ai-alt-text-generator'); ?></strong>
                    <span id="bbai-progress-count">0 / 0</span>
                </div>
                <div class="bbai-ftue-progress__bar" aria-hidden="true">
                    <span class="bbai-ftue-progress__fill" id="bbai-progress-fill"></span>
                </div>
                <p class="description bbai-ftue-progress__message" id="bbai-progress-message"></p>
            </section>

            <section class="bbai-ftue-instant-win" id="bbai-instant-win" hidden aria-live="polite">
                <h2 class="bbai-logged-out__section-title" id="bbai-instant-win-title">
                    <?php esc_html_e('✅ Generated alt text for 3 images', 'beepbeep-ai-alt-text-generator'); ?>
                </h2>
                <ul class="bbai-ftue-instant-win__list" id="bbai-instant-win-list"></ul>
                <?php if ('trial_available' === $bbai_hero_state) : ?>
                <div class="bbai-ftue-instant-win__actions" id="bbai-instant-win-actions" hidden>
                    <button type="button" class="button button-secondary bbai-btn bbai-btn-secondary" id="bbai-demo-generate-more-btn">
                        <?php esc_html_e('Generate 7 more (free trial)', 'beepbeep-ai-alt-text-generator'); ?>
                    </button>
                    <a
                        class="button button-primary bbai-btn bbai-btn-primary"
                        id="bbai-demo-unlock-btn"
                        href="<?php echo esc_url($bbai_fallback_url); ?>"
                        data-action="show-auth-modal"
                        data-auth-tab="register"
                    >
                        <?php esc_html_e('Unlock 50/month (free)', 'beepbeep-ai-alt-text-generator'); ?>
                    </a>
                </div>
                <?php endif; ?>
            </section>

            <p class="bbai-ftue-empty-message" id="bbai-ftue-empty-message" hidden></p>

            <section class="bbai-ftue-preview" aria-labelledby="bbai-preview-title">
                <h2 id="bbai-preview-title" class="bbai-logged-out__section-title">
                    <?php esc_html_e('Preview', 'beepbeep-ai-alt-text-generator'); ?>
                </h2>
                <p class="bbai-ftue-preview__line">
                    <strong><?php esc_html_e('Before:', 'beepbeep-ai-alt-text-generator'); ?></strong>
                    <span id="bbai-preview-before"><?php esc_html_e('Scanning your media library...', 'beepbeep-ai-alt-text-generator'); ?></span>
                </p>
                <p class="bbai-ftue-preview__line">
                    <strong><?php esc_html_e('After (generated):', 'beepbeep-ai-alt-text-generator'); ?></strong>
                    <span id="bbai-preview-after">&quot;Red running shoes with white sole and breathable mesh upper&quot;</span>
                </p>
            </section>

            <div class="bbai-logged-out__divider" aria-hidden="true"></div>

            <section class="bbai-logged-out__how-it-works" aria-labelledby="bbai-how-it-works-title">
                <h2 id="bbai-how-it-works-title" class="bbai-logged-out__section-title">
                    <?php esc_html_e('How it works', 'beepbeep-ai-alt-text-generator'); ?>
                </h2>

                <ol class="bbai-ftue-steps">
                    <li class="bbai-ftue-step">
                        <span class="bbai-ftue-step__label"><?php esc_html_e('Step 1', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <p class="bbai-ftue-step__text"><?php esc_html_e('Scan your media library', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </li>
                    <li class="bbai-ftue-step">
                        <span class="bbai-ftue-step__label"><?php esc_html_e('Step 2', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <p class="bbai-ftue-step__text"><?php esc_html_e('AI generates alt text for images', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </li>
                    <li class="bbai-ftue-step">
                        <span class="bbai-ftue-step__label"><?php esc_html_e('Step 3', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <p class="bbai-ftue-step__text"><?php esc_html_e('Apply the alt text automatically', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </li>
                    <li class="bbai-ftue-step">
                        <span class="bbai-ftue-step__label"><?php esc_html_e('Step 4', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <p class="bbai-ftue-step__text"><?php esc_html_e('Create an account for bulk generation', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </li>
                </ol>
            </section>

            <div class="bbai-logged-out__pill" role="note">
                <span class="bbai-logged-out__pill-icon" aria-hidden="true">
                    <svg viewBox="0 0 122.52 122.523" role="presentation" focusable="false">
                        <g fill="currentColor">
                            <path d="M8.708,61.26c0,20.802,12.089,38.779,29.619,47.298L13.258,39.872C10.342,46.408,8.708,53.63,8.708,61.26z"/>
                            <path d="M96.74,58.608c0-6.495-2.333-10.993-4.334-14.494c-2.664-4.329-5.161-7.995-5.161-12.324 c0-4.831,3.664-9.328,8.825-9.328c0.233,0,0.454,0.029,0.681,0.042c-9.35-8.566-21.807-13.796-35.489-13.796 c-18.36,0-34.513,9.42-43.91,23.688c1.233,0.037,2.395,0.063,3.382,0.063c5.497,0,14.006-0.667,14.006-0.667 c2.833-0.167,3.167,3.994,0.337,4.329c0,0-2.847,0.335-6.015,0.501L48.2,93.547l11.501-34.493l-8.188-22.434 c-2.83-0.166-5.511-0.501-5.511-0.501c-2.832-0.166-2.5-4.496,0.332-4.329c0,0,8.679,0.667,13.843,0.667 c5.496,0,14.006-0.667,14.006-0.667c2.835-0.167,3.168,3.994,0.337,4.329c0,0-2.853,0.335-6.015,0.501l18.992,56.494l5.242-17.517 C94.612,69.188,96.74,63.439,96.74,58.608z"/>
                            <path d="M62.184,65.857l-15.768,45.819c4.708,1.384,9.687,2.141,14.846,2.141c6.12,0,11.989-1.058,17.452-2.979 c-0.141-0.225-0.269-0.464-0.374-0.724L62.184,65.857z"/>
                            <path d="M107.376,36.046c0.226,1.674,0.354,3.471,0.354,5.404c0,5.333-0.996,11.328-3.996,18.824l-16.053,46.413 c15.624-9.111,26.133-26.038,26.133-45.426C113.813,52.756,111.456,43.753,107.376,36.046z"/>
                            <path d="M61.262,0C27.483,0,0,27.481,0,61.26c0,33.783,27.483,61.263,61.262,61.263 c33.778,0,61.265-27.48,61.265-61.263C122.526,27.481,95.04,0,61.262,0z M61.262,119.715c-32.23,0-58.453-26.223-58.453-58.455 c0-32.23,26.222-58.451,58.453-58.451c32.229,0,58.45,26.221,58.45,58.451C119.712,93.492,93.491,119.715,61.262,119.715z"/>
                        </g>
                    </svg>
                </span>
                <span class="bbai-logged-out__pill-text">
                    <?php esc_html_e('Works with the WordPress Media Library, WooCommerce, and most themes.', 'beepbeep-ai-alt-text-generator'); ?>
                </span>
            </div>
        </div>
    </div>
</div>

<style>
.bbai-ftue-card {
    max-width: 980px;
    margin: 24px auto;
    padding: 32px;
}

.bbai-ftue-scan-status {
    margin: 14px 0 6px;
    font-size: 13px;
    color: #646970;
}

.bbai-ftue-scan-status.is-error {
    color: #b32d2e;
}

.bbai-ftue-missing-count {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #1d2327;
}

.bbai-ftue-actions {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    margin: 22px 0;
}

.bbai-ftue-actions .button {
    min-width: 280px;
    justify-content: center;
}

.bbai-ftue-action-note {
    margin: 0;
}

.bbai-ftue-secondary-link {
    font-size: 13px;
}

.bbai-ftue-hidden-signup {
    display: none !important;
}

.bbai-ftue-progress {
    max-width: 700px;
    margin: 0 auto 20px;
    padding: 12px 14px;
    border: 1px solid #dcdcde;
    border-radius: 6px;
    background: #f6f7f7;
}

.bbai-ftue-progress__row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
    font-size: 13px;
}

.bbai-ftue-progress__bar {
    width: 100%;
    height: 8px;
    border-radius: 999px;
    background: #dcdcde;
    overflow: hidden;
}

.bbai-ftue-progress__fill {
    display: block;
    width: 0;
    height: 100%;
    background: #2271b1;
    transition: width 0.2s ease;
}

.bbai-ftue-progress__message {
    margin: 8px 0 0;
}

.bbai-ftue-instant-win {
    max-width: 700px;
    margin: 0 auto 20px;
    padding: 14px;
    border: 1px solid #bfdbfe;
    border-radius: 10px;
    background: #f0f9ff;
}

.bbai-ftue-instant-win__list {
    margin: 0;
    padding: 0;
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.bbai-ftue-instant-win__item {
    padding: 10px 12px;
    border: 1px solid #dbeafe;
    border-radius: 8px;
    background: #fff;
}

.bbai-ftue-instant-win__filename {
    margin: 0 0 6px;
    font-size: 13px;
    font-weight: 600;
    color: #1d2327;
}

.bbai-ftue-instant-win__pair {
    margin: 0;
    font-size: 13px;
    color: #1d2327;
}

.bbai-ftue-instant-win__pair + .bbai-ftue-instant-win__pair {
    margin-top: 4px;
}

.bbai-ftue-instant-win__label {
    font-weight: 600;
}

.bbai-ftue-instant-win__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 14px;
}

.bbai-ftue-empty-message {
    max-width: 700px;
    margin: 0 auto 20px;
    padding: 10px 12px;
    border-left: 4px solid #2563eb;
    background: #eff6ff;
}

.bbai-ftue-preview {
    max-width: 700px;
    margin: 0 auto 24px;
    padding: 14px;
    border: 1px solid #dcdcde;
    border-radius: 6px;
    background: #fff;
}

.bbai-ftue-preview__line {
    margin: 8px 0;
    font-size: 14px;
    color: #1d2327;
}

.bbai-ftue-preview__generated {
    font-style: italic;
}

.bbai-ftue-steps {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.bbai-ftue-step {
    border: 1px solid #dcdcde;
    border-radius: 6px;
    background: #f6f7f7;
    padding: 12px;
}

.bbai-ftue-step__label {
    display: block;
    margin-bottom: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #646970;
}

.bbai-ftue-step__text {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    color: #1d2327;
}

.bbai-ftue-benefits {
    max-width: 700px;
    margin: 0 auto 20px;
    padding: 12px 14px;
    border: 1px solid #dcdcde;
    border-radius: 6px;
    background: #f6f7f7;
}

.bbai-ftue-benefits__list {
    margin: 0;
    padding-left: 18px;
}

.bbai-ftue-benefits__list li {
    margin: 0 0 6px;
    color: #1d2327;
}

.bbai-ftue-benefits__list li:last-child {
    margin-bottom: 0;
}

@media (max-width: 782px) {
    .bbai-ftue-card {
        padding: 22px 16px;
    }

    .bbai-ftue-actions .button {
        width: 100%;
        min-width: 0;
    }

    .bbai-ftue-steps {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
(function() {
    'use strict';

    var i18n = <?php echo wp_json_encode([
        /* translators: %s: number of images */
        'missingSingle' => __('You have %s image missing alt text', 'beepbeep-ai-alt-text-generator'),
        /* translators: %s: number of images */
        'missingPlural' => __('You have %s images missing alt text', 'beepbeep-ai-alt-text-generator'),
        'scanReady' => __('Scan complete.', 'beepbeep-ai-alt-text-generator'),
        'scanNoMissing' => __('Scan complete. All images already have alt text.', 'beepbeep-ai-alt-text-generator'),
        /* translators: %s: number of images */
        'remainingSingle' => __('You still have %s image without alt text.', 'beepbeep-ai-alt-text-generator'),
        /* translators: %s: number of images */
        'remainingPlural' => __('You still have %s images without alt text.', 'beepbeep-ai-alt-text-generator'),
        'buttonGenerate' => __('Generate alt text for 3 images (free)', 'beepbeep-ai-alt-text-generator'),
        'buttonGenerating' => __('Generating alt text…', 'beepbeep-ai-alt-text-generator'),
        /* translators: 1: current count, 2: total limit */
        'bannerFormat' => __('Trial: %1$d of %2$d free generations remaining', 'beepbeep-ai-alt-text-generator'),
        'bannerExhausted' => __('Free trial exhausted - create a free account to continue', 'beepbeep-ai-alt-text-generator'),
        'progressStarting' => __('Starting generation…', 'beepbeep-ai-alt-text-generator'),
        'progressDone' => __('Generation complete', 'beepbeep-ai-alt-text-generator'),
        'instantTitleDefault' => __('✅ Generated alt text for 3 images', 'beepbeep-ai-alt-text-generator'),
        /* translators: %d: number of images */
        'instantTitleFormat' => __('✅ Generated alt text for %d images', 'beepbeep-ai-alt-text-generator'),
        'instantBefore' => __('Before', 'beepbeep-ai-alt-text-generator'),
        'instantAfter' => __('After', 'beepbeep-ai-alt-text-generator'),
        'beforeEmpty' => __('No alt text', 'beepbeep-ai-alt-text-generator'),
        'moreSeven' => __('Generate 7 more (free trial)', 'beepbeep-ai-alt-text-generator'),
        /* translators: %d: number of remaining free generations */
        'moreDynamic' => __('Generate %d more (free trial)', 'beepbeep-ai-alt-text-generator'),
        'unlockCta' => __('Unlock 50/month (free)', 'beepbeep-ai-alt-text-generator'),
        'friendlyEmpty' => __('No images are currently missing alt text. Upload an image to try the demo.', 'beepbeep-ai-alt-text-generator'),
        'friendlyUpload' => __('Upload an image', 'beepbeep-ai-alt-text-generator'),
        'networkError' => __('Network error. Please try again.', 'beepbeep-ai-alt-text-generator'),
    ]); ?>;

    var ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
    var nonce = <?php echo wp_json_encode(wp_create_nonce('beepbeepai_nonce')); ?>;
    var signupUrl = <?php echo wp_json_encode($bbai_fallback_url); ?>;
    var uploadUrl = <?php echo wp_json_encode(admin_url('media-new.php')); ?>;
    var trialRemaining = <?php echo (int) $bbai_trial_remaining; ?>;
    var trialLimit = <?php echo (int) $bbai_trial_limit; ?>;
    var trialUsed = <?php echo (int) $bbai_trial_used; ?>;
    var trialExhausted = <?php echo $bbai_trial_exhausted ? 'true' : 'false'; ?>;
    var missingAltCount = <?php echo (int) $bbai_missing_alt_count; ?>;

    var btn = document.getElementById('bbai-trial-generate-btn');
    var panelTrial = document.getElementById('bbai-ftue-panel-trial');
    var panelConversion = document.getElementById('bbai-ftue-panel-conversion');
    var scanStatusEl = document.getElementById('bbai-scan-status');
    var missingSummaryEl = document.getElementById('bbai-missing-summary');
    var missingSummaryConversionEl = document.getElementById('bbai-missing-summary-conversion');
    var previewBeforeEl = document.getElementById('bbai-preview-before');
    var previewAfterEl = document.getElementById('bbai-preview-after');
    var bannerTextEl = document.getElementById('bbai-trial-banner-text');

    var progressEl = document.getElementById('bbai-ftue-progress');
    var progressLabelEl = document.getElementById('bbai-progress-label');
    var progressCountEl = document.getElementById('bbai-progress-count');
    var progressFillEl = document.getElementById('bbai-progress-fill');
    var progressMessageEl = document.getElementById('bbai-progress-message');

    var instantWinEl = document.getElementById('bbai-instant-win');
    var instantTitleEl = document.getElementById('bbai-instant-win-title');
    var instantListEl = document.getElementById('bbai-instant-win-list');
    var generateMoreBtn = document.getElementById('bbai-demo-generate-more-btn');
    var unlockBtn = document.getElementById('bbai-demo-unlock-btn');
    var emptyMessageEl = document.getElementById('bbai-ftue-empty-message');
    var instantWinActionsEl = document.getElementById('bbai-instant-win-actions');

    var requestInFlight = false;
    var trialStartedTracked = false;
    var trialExhaustedTracked = false;
    var trialCompleteStateTracked = false;
    var loggedOutConversionStateTracked = false;

    function emitAnalyticsEvent(eventName, properties) {
        try {
            document.dispatchEvent(new CustomEvent('bbai:analytics', {
                detail: Object.assign({ event: eventName }, properties || {})
            }));
        } catch (error) {
            // Ignore analytics dispatch failures.
        }
    }

    function syncAnalyticsContext() {
        if (window.bbaiAnalytics && typeof window.bbaiAnalytics.updateContext === 'function') {
            window.bbaiAnalytics.updateContext({
                remaining_free_images: trialRemaining,
                trial_exhausted: trialExhausted,
                missing_alt_count: missingAltCount
            });
        }
    }

    function maybeTrackLoggedOutStateExposure() {
        if (getHeroState() !== 'trial_complete') {
            return;
        }

        if (!trialCompleteStateTracked) {
            trialCompleteStateTracked = true;
            emitAnalyticsEvent('trial_complete_state_shown', {
                source: 'guest_dashboard',
                remaining_free_images: trialRemaining
            });
        }

        if (!loggedOutConversionStateTracked) {
            loggedOutConversionStateTracked = true;
            emitAnalyticsEvent('logged_out_conversion_state_shown', {
                source: 'guest_dashboard',
                remaining_free_images: trialRemaining
            });
        }
    }

    function maybeTrackTrialExhausted(properties) {
        if (trialExhaustedTracked || !trialExhausted) {
            return;
        }

        trialExhaustedTracked = true;
        emitAnalyticsEvent('trial_exhausted', Object.assign({
            source: 'guest_dashboard',
            remaining_free_images: trialRemaining
        }, properties || {}));
    }

    /**
     * Central hero state resolver — single source of truth for JS.
     * Mirrors the PHP $bbai_hero_state variable.
     *
     * Returns: 'trial_available' | 'trial_complete'
     */
    function getHeroState() {
        if (trialExhausted || trialRemaining <= 0) {
            return 'trial_complete';
        }
        return 'trial_available';
    }

    // Keep isPostFreeTrial as a thin alias so any future callers still work.
    function isPostFreeTrial() {
        return getHeroState() === 'trial_complete';
    }

    function syncPostFreeTrialUi() {
        var state = getHeroState();
        var post  = (state === 'trial_complete');

        // Stamp the resolved state on the root element for CSS / debug targeting.
        var rootEl = document.querySelector('.bbai-logged-out');
        if (rootEl) {
            rootEl.setAttribute('data-hero-state', state);
        }

        if (panelTrial) {
            panelTrial.hidden = post;
        }
        if (panelConversion) {
            panelConversion.hidden = !post;
        }
        if (post) {
            if (progressEl) {
                progressEl.hidden = true;
            }
            if (instantWinEl) {
                instantWinEl.hidden = true;
            }
            if (instantWinActionsEl) {
                instantWinActionsEl.hidden = true;
            }
        }

        syncAnalyticsContext();
        maybeTrackLoggedOutStateExposure();
    }

    function formatNumber(value) {
        var parsed = parseInt(value, 10);
        return isNaN(parsed) || parsed < 0 ? '0' : parsed.toLocaleString();
    }

    function simpleSprintf(format) {
        var args = Array.prototype.slice.call(arguments, 1);
        var i = 0;
        return String(format).replace(/%[0-9]*\$?[sd]/g, function() {
            return typeof args[i] === 'undefined' ? '' : String(args[i++]);
        });
    }

    function clampText(text, maxLength) {
        var value = String(text || '').trim();
        if (value.length <= maxLength) {
            return value;
        }
        return value.slice(0, maxLength - 1) + '…';
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = String(text || '');
        return div.innerHTML;
    }

    function postAction(action, extraData) {
        var formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', nonce);

        if (extraData && typeof extraData === 'object') {
            Object.keys(extraData).forEach(function(key) {
                formData.append(key, extraData[key]);
            });
        }

        return fetch(ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        }).then(function(response) {
            return response.json();
        });
    }

    function triggerSignupFlow() {
        var authTrigger = document.getElementById('bbai-signup-trigger') || document.querySelector('[data-action="show-auth-modal"][data-auth-tab="register"]');
        if (authTrigger) {
            authTrigger.click();
            return;
        }

        if (typeof CustomEvent === 'function') {
            document.dispatchEvent(new CustomEvent('bbai:show-auth-modal', {
                detail: { tab: 'register' }
            }));
        }

        window.setTimeout(function() {
            window.location.href = signupUrl;
        }, 60);
    }

    function updateMissingSummary() {
        var post = isPostFreeTrial();
        var template = post
            ? (missingAltCount === 1 ? i18n.remainingSingle : i18n.remainingPlural)
            : (missingAltCount === 1 ? i18n.missingSingle : i18n.missingPlural);
        var text = simpleSprintf(template, formatNumber(missingAltCount));
        if (missingSummaryEl) {
            missingSummaryEl.textContent = text;
        }
        if (missingSummaryConversionEl) {
            missingSummaryConversionEl.textContent = text;
        }
        window.missingAltCount = missingAltCount;
    }

    function updateScanStatus(message, isError) {
        if (!scanStatusEl) {
            return;
        }
        scanStatusEl.textContent = message;
        scanStatusEl.classList.toggle('is-error', !!isError);
    }

    function updateBanner() {
        if (!bannerTextEl || !panelTrial || panelTrial.hidden) {
            return;
        }

        if (trialRemaining <= 0) {
            bannerTextEl.textContent = i18n.bannerExhausted;
            return;
        }

        bannerTextEl.textContent = simpleSprintf(i18n.bannerFormat, formatNumber(trialRemaining), formatNumber(trialLimit));
    }

    function hydrateTrialStateFromServer() {
        return postAction('bbai_trial_get_missing', { limit: 1 }).then(function(resp) {
            if (!resp || !resp.success) {
                var dataErr = resp && resp.data ? resp.data : {};
                var codeErr = String(dataErr.code || '').toLowerCase();
                if (codeErr === 'bbai_trial_exhausted') {
                    trialRemaining = 0;
                    trialUsed = Math.max(trialUsed, trialLimit);
                    trialExhausted = true;
                    syncPostFreeTrialUi();
                    updateBanner();
                }
                return;
            }

            var data = resp.data || {};
            var remainingRaw = parseInt(
                typeof data.remaining_free_images !== 'undefined' ? data.remaining_free_images : data.remaining,
                10
            );
            var limitRaw = parseInt(data.limit, 10);
            var usedRaw = parseInt(data.used, 10);

            if (!isNaN(limitRaw) && limitRaw >= 0) {
                trialLimit = limitRaw;
            }
            if (!isNaN(usedRaw) && usedRaw >= 0) {
                trialUsed = usedRaw;
            }
            if (!isNaN(remainingRaw) && remainingRaw >= 0) {
                trialRemaining = remainingRaw;
            } else if (trialLimit > 0 && trialUsed >= 0) {
                trialRemaining = Math.max(0, trialLimit - trialUsed);
            }

            trialExhausted = !!data.trial_exhausted || trialRemaining <= 0;
            syncPostFreeTrialUi();
            updateBanner();
        }).catch(function() {
            // Keep server-rendered values if hydration fails.
        });
    }

    function showProgress(current, total, message) {
        if (!progressEl) {
            return;
        }

        var safeTotal = Math.max(1, parseInt(total, 10) || 1);
        var safeCurrent = Math.max(0, parseInt(current, 10) || 0);
        var width = Math.min(100, Math.round((safeCurrent / safeTotal) * 100));

        progressEl.hidden = false;
        if (progressLabelEl) {
            progressLabelEl.textContent = requestInFlight ? i18n.buttonGenerating : i18n.progressDone;
        }
        if (progressCountEl) {
            progressCountEl.textContent = safeCurrent + ' / ' + safeTotal;
        }
        if (progressFillEl) {
            progressFillEl.style.width = width + '%';
        }
        if (progressMessageEl && typeof message === 'string') {
            progressMessageEl.textContent = message;
        }
    }

    function hideEmptyMessage() {
        if (!emptyMessageEl) {
            return;
        }
        emptyMessageEl.hidden = true;
        emptyMessageEl.textContent = '';
    }

    function showEmptyMessage(message, linkHref, linkText) {
        if (!emptyMessageEl) {
            return;
        }

        if (linkHref && linkText) {
            emptyMessageEl.innerHTML = escapeHtml(message) + ' <a href="' + escapeHtml(linkHref) + '">' + escapeHtml(linkText) + '</a>';
        } else {
            emptyMessageEl.textContent = message;
        }
        emptyMessageEl.hidden = false;
    }

    function updateMoreButtonLabel() {
        if (!generateMoreBtn) {
            return;
        }

        if (trialRemaining <= 0) {
            generateMoreBtn.disabled = true;
            generateMoreBtn.textContent = i18n.moreSeven;
            return;
        }

        var nextCount = Math.min(7, trialRemaining);
        generateMoreBtn.textContent = nextCount === 7
            ? i18n.moreSeven
            : simpleSprintf(i18n.moreDynamic, nextCount);
        generateMoreBtn.disabled = requestInFlight;
    }

    function setPrimaryButtonState() {
        if (!btn) {
            return;
        }
        if (isPostFreeTrial()) {
            return;
        }
        if (requestInFlight) {
            btn.disabled = true;
            btn.textContent = i18n.buttonGenerating;
            return;
        }
        btn.disabled = false;
        btn.textContent = i18n.buttonGenerate;
    }

    function renderInstantWin(summary) {
        if (!instantWinEl || !instantListEl || !instantTitleEl) {
            return;
        }

        var items = Array.isArray(summary) ? summary : [];
        var generatedCount = items.length;
        instantTitleEl.textContent = generatedCount === 3
            ? i18n.instantTitleDefault
            : simpleSprintf(i18n.instantTitleFormat, generatedCount);

        instantListEl.innerHTML = items.map(function(item) {
            var filename = item && item.filename ? item.filename : ('Image #' + (item && item.attachment_id ? item.attachment_id : ''));
            var beforeText = item && item.previous_alt ? item.previous_alt : i18n.beforeEmpty;
            var afterText = item && item.new_alt ? item.new_alt : '';

            return '' +
                '<li class="bbai-ftue-instant-win__item">' +
                '<p class="bbai-ftue-instant-win__filename">' + escapeHtml(clampText(filename, 90)) + '</p>' +
                '<p class="bbai-ftue-instant-win__pair"><span class="bbai-ftue-instant-win__label">' + escapeHtml(i18n.instantBefore) + ':</span> ' + escapeHtml(clampText(beforeText, 90)) + '</p>' +
                '<p class="bbai-ftue-instant-win__pair"><span class="bbai-ftue-instant-win__label">' + escapeHtml(i18n.instantAfter) + ':</span> ' + escapeHtml(clampText(afterText, 90)) + '</p>' +
                '</li>';
        }).join('');

        instantWinEl.hidden = generatedCount <= 0;
    }

    function updatePreviewFromSummary(summary) {
        if (!Array.isArray(summary) || !summary.length) {
            return;
        }

        var first = summary[0];
        if (previewBeforeEl) {
            previewBeforeEl.textContent = first.filename || ('Image #' + first.attachment_id);
        }
        if (previewAfterEl && first.new_alt) {
            previewAfterEl.textContent = '"' + first.new_alt + '"';
            previewAfterEl.classList.add('bbai-ftue-preview__generated');
        }
    }

    function runDemoBatch(limit) {
        if (requestInFlight) {
            return;
        }

        if (isPostFreeTrial()) {
            triggerSignupFlow();
            return;
        }

        hideEmptyMessage();
        requestInFlight = true;
        if (!trialStartedTracked && trialUsed <= 0) {
            trialStartedTracked = true;
            emitAnalyticsEvent('trial_started', {
                source: 'guest_dashboard',
                requested_count: limit,
                remaining_free_images: trialRemaining,
                missing_alt_count: missingAltCount
            });
        }
        emitAnalyticsEvent('trial_generation_started', {
            source: 'guest_dashboard',
            requested_count: limit,
            remaining_free_images: trialRemaining,
            missing_alt_count: missingAltCount
        });
        setPrimaryButtonState();
        updateMoreButtonLabel();
        showProgress(0, Math.max(1, limit), i18n.progressStarting);

        postAction('bbai_trial_demo_generate_batch', { limit: limit }).then(function(resp) {
            if (!resp || !resp.success) {
                var errorData = resp && resp.data ? resp.data : {};
                var errorCode = String(errorData.code || '').toLowerCase();

                if (errorCode === 'bbai_trial_exhausted') {
                    trialRemaining = 0;
                    trialExhausted = true;
                    syncPostFreeTrialUi();
                    maybeTrackTrialExhausted({
                        source: 'guest_dashboard',
                        requested_count: limit,
                        remaining_free_images: 0
                    });
                    showEmptyMessage(errorData.message || i18n.bannerExhausted);
                    showProgress(0, 1, errorData.message || i18n.bannerExhausted);
                    updateScanStatus(errorData.message || i18n.bannerExhausted, false);
                    updateMissingSummary();
                    return;
                }

                showEmptyMessage(errorData.message || i18n.networkError);
                showProgress(0, 1, errorData.message || i18n.networkError);
                updateScanStatus(errorData.message || i18n.networkError, true);
                return;
            }

            var data = resp.data || {};
            // Fix: parseInt("0") is falsy, so `|| fallback` would silently keep the old
            // value when the server returns 0 (trial exhausted). Use isNaN guard instead.
            var _remaining = parseInt(
                typeof data.remaining_free_images !== 'undefined' ? data.remaining_free_images : data.remaining,
                10
            );
            if (!isNaN(_remaining)) {
                trialRemaining = Math.max(0, _remaining);
            }
            var _limit = parseInt(data.limit, 10);
            if (!isNaN(_limit) && _limit > 0) {
                trialLimit = _limit;
            }
            var _used = parseInt(data.used, 10);
            if (!isNaN(_used) && _used >= 0) {
                trialUsed = _used;
            } else {
                trialUsed = Math.max(0, trialLimit - trialRemaining);
            }
            missingAltCount = Math.max(0, parseInt(data.missing_alt_count, 10) || 0);

            var summary = Array.isArray(data.summary) ? data.summary : [];
            var generatedCount = parseInt(data.generated_count, 10) || summary.length;
            var acceptedCount = parseInt(data.accepted_count, 10);
            if (isNaN(acceptedCount)) {
                acceptedCount = generatedCount;
            }

            showProgress(generatedCount, Math.max(1, parseInt(data.attempted, 10) || limit), data.message || i18n.progressDone);

            if (generatedCount > 0 || acceptedCount > 0) {
                emitAnalyticsEvent('trial_generation_completed', {
                    source: 'guest_dashboard',
                    requested_count: limit,
                    processed_count: generatedCount,
                    accepted_count: acceptedCount,
                    remaining_free_images: trialRemaining,
                    missing_alt_count: missingAltCount
                });
            }

            if (generatedCount > 0) {
                updatePreviewFromSummary(summary);
                updateScanStatus(i18n.scanReady, false);
                if (isPostFreeTrial()) {
                    syncPostFreeTrialUi();
                } else {
                    renderInstantWin(summary);
                    if (instantWinActionsEl) {
                        instantWinActionsEl.hidden = false;
                    }
                }
            } else {
                var emptyMessage = data.message || i18n.friendlyEmpty;
                showEmptyMessage(emptyMessage, data.upload_url || uploadUrl, i18n.friendlyUpload);
            }

            if (data.no_missing_images) {
                showEmptyMessage(i18n.friendlyEmpty, data.upload_url || uploadUrl, i18n.friendlyUpload);
                updateScanStatus(i18n.scanNoMissing, false);
            }

            if (data.trial_exhausted || trialRemaining <= 0) {
                trialRemaining = 0;
                trialExhausted = true;
                maybeTrackTrialExhausted({
                    source: 'guest_dashboard',
                    processed_count: generatedCount,
                    accepted_count: acceptedCount,
                    remaining_free_images: 0
                });
            }

            syncPostFreeTrialUi();
            updateBanner();
            updateMissingSummary();
        }).catch(function() {
            showEmptyMessage(i18n.networkError);
            showProgress(0, 1, i18n.networkError);
            updateScanStatus(i18n.networkError, true);
        }).finally(function() {
            requestInFlight = false;
            setPrimaryButtonState();
            updateMoreButtonLabel();
            updateBanner();
        });
    }

    if (btn) {
        btn.addEventListener('click', function(event) {
            event.preventDefault();
            emitAnalyticsEvent('trial_cta_clicked', {
                source: 'guest_dashboard_primary',
                requested_count: Math.min(3, trialRemaining),
                remaining_free_images: trialRemaining
            });
            if (isPostFreeTrial()) {
                triggerSignupFlow();
                return;
            }
            runDemoBatch(Math.min(3, trialRemaining));
        });
    }

    if (generateMoreBtn) {
        generateMoreBtn.addEventListener('click', function(event) {
            event.preventDefault();
            emitAnalyticsEvent('trial_cta_clicked', {
                source: 'guest_dashboard_expand',
                requested_count: Math.min(7, trialRemaining),
                remaining_free_images: trialRemaining
            });
            if (isPostFreeTrial()) {
                triggerSignupFlow();
                return;
            }
            if (trialRemaining <= 0) {
                triggerSignupFlow();
                return;
            }
            runDemoBatch(Math.min(7, trialRemaining));
        });
    }

    if (unlockBtn) {
        unlockBtn.addEventListener('click', function(event) {
            event.preventDefault();
            triggerSignupFlow();
        });
    }

    if (missingAltCount <= 0) {
        showEmptyMessage(i18n.friendlyEmpty, uploadUrl, i18n.friendlyUpload);
        updateScanStatus(i18n.scanNoMissing, false);
    } else {
        updateScanStatus(i18n.scanReady, false);
    }

    syncPostFreeTrialUi();
    updateMissingSummary();
    updateBanner();
    setPrimaryButtonState();
    updateMoreButtonLabel();
    hydrateTrialStateFromServer();
})();
</script>
