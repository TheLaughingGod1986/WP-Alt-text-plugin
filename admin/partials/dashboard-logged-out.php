<?php
/**
 * Logged-Out Dashboard State
 * Conversion-focused onboarding for unauthenticated users.
 *
 * @package BeepBeep_AI
 * @since 6.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/ui-components.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-trial-quota.php';

$bbai_trial_status = \BeepBeepAI\AltTextGenerator\Trial_Quota::get_status();
$bbai_trial_limit = max(0, (int) ($bbai_trial_status['limit'] ?? \BeepBeepAI\AltTextGenerator\Trial_Quota::get_limit()));
$bbai_trial_used = max(0, (int) ($bbai_trial_status['used'] ?? 0));
$bbai_trial_used = min($bbai_trial_used, $bbai_trial_limit);
$bbai_trial_remaining = max(0, (int) ($bbai_trial_status['remaining'] ?? max(0, $bbai_trial_limit - $bbai_trial_used)));
$bbai_trial_exhausted = isset($bbai_trial_status['exhausted'])
    ? (bool) $bbai_trial_status['exhausted']
    : ($bbai_trial_remaining <= 0);
$bbai_trial_exhausted = $bbai_trial_exhausted || $bbai_trial_remaining <= 0;

$bbai_extract_stat_count = static function (array $stats, array $keys): ?int {
    foreach ($keys as $key) {
        if (isset($stats[$key]) && is_numeric($stats[$key])) {
            return max(0, (int) $stats[$key]);
        }
    }

    return null;
};

$bbai_dashboard_stats_payload = [];
if (isset($this) && is_object($this) && method_exists($this, 'get_dashboard_stats_payload')) {
    $bbai_dashboard_stats_payload = $this->get_dashboard_stats_payload(false);
} elseif (isset($bbai_stats) && is_array($bbai_stats)) {
    $bbai_dashboard_stats_payload = $bbai_stats;
}

$bbai_current_site_missing_alt_count = is_array($bbai_dashboard_stats_payload)
    ? $bbai_extract_stat_count($bbai_dashboard_stats_payload, ['images_missing_alt', 'missing', 'missing_alt'])
    : null;
$bbai_total_images_count = is_array($bbai_dashboard_stats_payload)
    ? $bbai_extract_stat_count($bbai_dashboard_stats_payload, ['total_images', 'total'])
    : null;

if (
    (null === $bbai_current_site_missing_alt_count || null === $bbai_total_images_count)
    && isset($this)
    && is_object($this)
    && method_exists($this, 'get_alt_text_coverage_scan')
) {
    $bbai_coverage_payload = $this->get_alt_text_coverage_scan(false);
    if (is_array($bbai_coverage_payload)) {
        if (null === $bbai_current_site_missing_alt_count) {
            $bbai_current_site_missing_alt_count = $bbai_extract_stat_count($bbai_coverage_payload, ['images_missing_alt', 'missing', 'missing_alt']);
        }
        if (null === $bbai_total_images_count) {
            $bbai_total_images_count = $bbai_extract_stat_count($bbai_coverage_payload, ['total_images', 'total']);
        }
    }
}

if (
    null === $bbai_current_site_missing_alt_count
    && isset($this)
    && is_object($this)
    && method_exists($this, 'get_missing_attachment_ids')
) {
    $bbai_missing_batch_size = 250;
    $bbai_missing_offset = 0;
    $bbai_missing_count_from_query = 0;

    do {
        $bbai_missing_batch = $this->get_missing_attachment_ids($bbai_missing_batch_size, $bbai_missing_offset);
        $bbai_missing_batch_count = is_array($bbai_missing_batch) ? count($bbai_missing_batch) : 0;
        $bbai_missing_count_from_query += $bbai_missing_batch_count;
        $bbai_missing_offset += $bbai_missing_batch_count;
    } while ($bbai_missing_batch_count === $bbai_missing_batch_size);

    $bbai_current_site_missing_alt_count = max(0, $bbai_missing_count_from_query);
}

if (null === $bbai_current_site_missing_alt_count || null === $bbai_total_images_count) {
    global $wpdb;

    if (isset($wpdb->posts, $wpdb->postmeta)) {
        if (null === $bbai_current_site_missing_alt_count) {
            $bbai_missing_alt_count_query = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
                 WHERE p.post_type = 'attachment'
                 AND p.post_mime_type LIKE 'image/%'
                 AND p.post_status = 'inherit'
                 AND (pm.meta_value IS NULL OR TRIM(pm.meta_value) = '')"
            ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Final fallback when dashboard stats and plugin queries are unavailable.

            if (null !== $bbai_missing_alt_count_query) {
                $bbai_current_site_missing_alt_count = max(0, (int) $bbai_missing_alt_count_query);
            }
        }

        if (null === $bbai_total_images_count) {
            $bbai_total_images_query = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->posts} p
                 WHERE p.post_type = 'attachment'
                 AND p.post_mime_type LIKE 'image/%'
                 AND p.post_status = 'inherit'"
            ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Final fallback when dashboard stats and plugin queries are unavailable.

            if (null !== $bbai_total_images_query) {
                $bbai_total_images_count = max(0, (int) $bbai_total_images_query);
            }
        }
    }
}

$bbai_has_missing_count = null !== $bbai_current_site_missing_alt_count;
$bbai_has_total_images_count = null !== $bbai_total_images_count && $bbai_total_images_count >= 0;
$bbai_fixed_count = max(0, min($bbai_trial_used, $bbai_trial_limit));
$bbai_missing_alt_count = $bbai_has_missing_count
    ? max(0, (int) $bbai_current_site_missing_alt_count)
    : null;
$bbai_remaining_site_count = null !== $bbai_missing_alt_count
    ? max(0, $bbai_missing_alt_count)
    : null;
$bbai_optimized_count = ($bbai_has_total_images_count && null !== $bbai_missing_alt_count && $bbai_total_images_count >= $bbai_missing_alt_count)
    ? max(0, $bbai_total_images_count - $bbai_missing_alt_count)
    : null;
$bbai_has_optimized_count = null !== $bbai_optimized_count;
$bbai_show_progress_state = null !== $bbai_missing_alt_count;

if ($bbai_trial_exhausted) {
    $bbai_hero_state = 'trial_complete';
} elseif ($bbai_fixed_count > 0) {
    $bbai_hero_state = 'trial_progress';
} else {
    $bbai_hero_state = 'trial_available';
}

$bbai_format_number = static function (?int $value): string {
    return number_format_i18n(max(0, (int) $value));
};

$bbai_build_headline = static function (string $state, ?int $missing_count, int $fixed_count) use ($bbai_format_number): string {
    if ('trial_available' === $state) {
        if (null !== $missing_count && $missing_count > 0) {
            return sprintf(
                /* translators: %s: number of images without alt text. */
                _n(
                    'We found %s image missing alt text 👀',
                    'We found %s images missing alt text 👀',
                    $missing_count,
                    'beepbeep-ai-alt-text-generator'
                ),
                $bbai_format_number($missing_count)
            );
        }

        return __('Start fixing missing alt text across your media library', 'beepbeep-ai-alt-text-generator');
    }

    if (null !== $missing_count) {
        return sprintf(
            /* translators: 1: number of fixed images, 2: number of images still missing alt text. */
            _n(
                'You’ve fixed %1$s image. %2$s left.',
                'You’ve fixed %1$s images. %2$s left.',
                max(1, $fixed_count),
                'beepbeep-ai-alt-text-generator'
            ),
            $bbai_format_number(max(0, $fixed_count)),
            $bbai_format_number($missing_count)
        );
    }

    return sprintf(
        /* translators: %s: number of fixed images. */
        _n(
            'You’ve fixed %s image so far.',
            'You’ve fixed %s images so far.',
            max(1, $fixed_count),
            'beepbeep-ai-alt-text-generator'
        ),
        $bbai_format_number(max(0, $fixed_count))
    );
};

$bbai_build_primary_label = static function (string $state, int $trial_remaining, int $trial_limit) use ($bbai_format_number): string {
    if ('trial_complete' === $state) {
        return __('Fix the remaining images', 'beepbeep-ai-alt-text-generator');
    }

    if ('trial_progress' === $state) {
        return sprintf(
            /* translators: %s: number of remaining free images. */
            _n(
                'Fix %s more image free',
                'Fix %s more images free',
                max(1, $trial_remaining),
                'beepbeep-ai-alt-text-generator'
            ),
            $bbai_format_number(max(1, $trial_remaining))
        );
    }

    $trial_count = max(1, min($trial_limit, max($trial_remaining, $trial_limit)));
    return sprintf(
        /* translators: %s: number of free images. */
        _n(
            'Generate alt text for %s image free',
            'Generate alt text for %s images free',
            $trial_count,
            'beepbeep-ai-alt-text-generator'
        ),
        $bbai_format_number($trial_count)
    );
};

$bbai_build_value_eyebrow = static function (string $state): string {
    return __('Free account value', 'beepbeep-ai-alt-text-generator');
};

$bbai_build_available_body = static function (int $trial_limit) use ($bbai_format_number): string {
    return __('Start fixing them for free. No account needed.', 'beepbeep-ai-alt-text-generator');
};

$bbai_hero_title = $bbai_build_headline(
    'trial_available',
    $bbai_missing_alt_count,
    $bbai_fixed_count
);
$bbai_show_impact_line = $bbai_has_missing_count
    && null !== $bbai_missing_alt_count
    && $bbai_missing_alt_count > 0;
$bbai_hero_impact = __('This is hurting your SEO and accessibility.', 'beepbeep-ai-alt-text-generator');
$bbai_hero_benefit = 'trial_available' === $bbai_hero_state
    ? sprintf(
        /* translators: %s: number of free trial images. */
        _n(
            'Your first %s fix is ready now.',
            'Your first %s fixes are ready now.',
            max(1, $bbai_trial_limit),
            'beepbeep-ai-alt-text-generator'
        ),
        $bbai_format_number(max(1, $bbai_trial_limit))
    )
    : '';
$bbai_hero_body = 'trial_available' === $bbai_hero_state
    ? $bbai_build_available_body($bbai_trial_limit)
    : __('Keep going and clean up your media library faster.', 'beepbeep-ai-alt-text-generator');
$bbai_primary_label = $bbai_build_primary_label($bbai_hero_state, $bbai_trial_remaining, $bbai_trial_limit);
$bbai_secondary_label = __('Continue fixing with a free account', 'beepbeep-ai-alt-text-generator');
$bbai_helper_text = __('No credit card required • 50 free credits every month', 'beepbeep-ai-alt-text-generator');
$bbai_primary_cta_proof = 'trial_complete' === $bbai_hero_state
    ? __('No credit card required • Takes ~10 seconds', 'beepbeep-ai-alt-text-generator')
    : __('No account needed • Takes ~10 seconds', 'beepbeep-ai-alt-text-generator');
$bbai_primary_cta_prompt = '';
$bbai_value_eyebrow = __('After your free fixes', 'beepbeep-ai-alt-text-generator');
$bbai_hero_kicker = 'trial_available' === $bbai_hero_state
    ? __('Scan complete', 'beepbeep-ai-alt-text-generator')
    : __('Progress started', 'beepbeep-ai-alt-text-generator');
$bbai_callout_lead = __('Continue fixing your media library without starting over.', 'beepbeep-ai-alt-text-generator');
$bbai_callout_trust = __('No credit card required. Starts instantly.', 'beepbeep-ai-alt-text-generator');
$bbai_callout_renewal = __('Renews automatically every month.', 'beepbeep-ai-alt-text-generator');
$bbai_callout_cta_note = __('No credit card required • 50 free credits every month', 'beepbeep-ai-alt-text-generator');
$bbai_show_secondary_action = true;
$bbai_show_login_link = 'trial_complete' === $bbai_hero_state;
$bbai_show_site_card = $bbai_has_missing_count;
$bbai_site_card_label = __('Still need alt text', 'beepbeep-ai-alt-text-generator');
$bbai_site_card_value = $bbai_format_number(
    ('trial_available' === $bbai_hero_state ? $bbai_missing_alt_count : $bbai_remaining_site_count) ?? 0
);
$bbai_site_card_caption = __('images still need alt text', 'beepbeep-ai-alt-text-generator');
if ($bbai_has_missing_count && $bbai_remaining_site_count <= 0) {
    $bbai_status_text = __('Your media library already looks covered.', 'beepbeep-ai-alt-text-generator');
} else {
    $bbai_status_text = __('Works with WordPress Media Library, WooCommerce, and most themes.', 'beepbeep-ai-alt-text-generator');
}

$bbai_progress_summary = '';
$bbai_progress_detail = '';
$bbai_progress_note = '';
$bbai_progress_label = __('Start fixing your images', 'beepbeep-ai-alt-text-generator');
$bbai_progress_percent = (int) round(($bbai_fixed_count / max(1, $bbai_trial_limit)) * 100);
if ($bbai_show_progress_state) {
    $bbai_progress_summary = sprintf(
        /* translators: %s: number of images still missing alt text. */
        _n(
            '%s image needs fixing',
            '%s images need fixing',
            max(0, (int) $bbai_missing_alt_count),
            'beepbeep-ai-alt-text-generator'
        ),
        $bbai_format_number($bbai_missing_alt_count)
    );

    if ($bbai_has_optimized_count) {
        $bbai_progress_detail = sprintf(
            /* translators: %s: number of images already optimized. */
            _n(
                '%s image already optimized',
                '%s images already optimized',
                max(0, (int) $bbai_optimized_count),
                'beepbeep-ai-alt-text-generator'
            ),
            $bbai_format_number($bbai_optimized_count)
        );
    }

    $bbai_progress_note = sprintf(
        /* translators: 1: number of free fixes used, 2: trial limit. */
        _n(
            '%1$s / %2$s free fix used',
            '%1$s / %2$s free fixes used',
            max(1, (int) $bbai_trial_limit),
            'beepbeep-ai-alt-text-generator'
        ),
        $bbai_format_number($bbai_fixed_count),
        $bbai_format_number(max(1, $bbai_trial_limit))
    );
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
$bbai_page_input = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : 'bbai';
$bbai_current_page = $bbai_page_input ?: 'bbai';
$bbai_fallback_url = admin_url('admin.php?page=' . $bbai_current_page);
?>

<div
    class="bbai-logged-out"
    role="main"
    data-hero-state="<?php echo esc_attr($bbai_hero_state); ?>"
    data-has-missing-count="<?php echo $bbai_has_missing_count ? '1' : '0'; ?>"
    aria-label="<?php esc_attr_e('Get started with BeepBeep AI', 'beepbeep-ai-alt-text-generator'); ?>"
>
    <div class="bbai-logged-out__container">
        <div class="bbai-logged-out__card bbai-ftue-card">
            <section class="bbai-ftue-hero" aria-labelledby="bbai-ftue-hero-title">
                <div class="bbai-ftue-hero__shell">
                    <div class="bbai-ftue-hero__main">
                        <div class="bbai-ftue-hero__topline">
                            <p class="bbai-ftue-hero__kicker" id="bbai-ftue-kicker"><?php echo esc_html($bbai_hero_kicker); ?></p>
                        </div>

                        <header class="bbai-logged-out__header bbai-ftue-hero__header">
                            <h1 id="bbai-ftue-hero-title" class="bbai-logged-out__title bbai-ftue-hero__title">
                                <?php echo esc_html($bbai_hero_title); ?>
                            </h1>
                            <p class="bbai-ftue-hero__impact" id="bbai-ftue-hero-impact" <?php echo $bbai_show_impact_line ? '' : 'hidden'; ?>>
                                <?php echo esc_html($bbai_hero_impact); ?>
                            </p>
                            <p class="bbai-logged-out__subtitle bbai-ftue-hero__body" id="bbai-ftue-hero-body">
                                <?php echo esc_html($bbai_hero_body); ?>
                            </p>
                            <p class="bbai-ftue-hero__benefit" id="bbai-ftue-hero-benefit" <?php echo '' !== $bbai_hero_benefit ? '' : 'hidden'; ?>>
                                <?php echo esc_html($bbai_hero_benefit); ?>
                            </p>
                        </header>

                        <section class="bbai-ftue-library-progress bbai-ftue-library-progress--hero" id="bbai-ftue-library-progress" <?php echo $bbai_show_progress_state ? '' : 'hidden'; ?>>
                            <p class="bbai-ftue-library-progress__label" id="bbai-library-progress-label">
                                <?php echo esc_html($bbai_progress_label); ?>
                            </p>
                            <strong class="bbai-ftue-library-progress__summary" id="bbai-library-progress-summary">
                                <?php echo esc_html($bbai_progress_summary); ?>
                            </strong>
                            <p class="bbai-ftue-library-progress__detail" id="bbai-library-progress-detail" <?php echo '' !== $bbai_progress_detail ? '' : 'hidden'; ?>>
                                <?php echo esc_html($bbai_progress_detail); ?>
                            </p>
                            <p class="bbai-ftue-library-progress__note" id="bbai-library-progress-note">
                                <?php echo esc_html($bbai_progress_note); ?>
                            </p>
                            <div class="bbai-ftue-library-progress__bar" aria-hidden="true">
                                <span
                                    class="bbai-ftue-library-progress__fill"
                                    id="bbai-library-progress-fill"
                                    style="width: 0%;"
                                ></span>
                            </div>
                        </section>

                        <div class="bbai-ftue-actions">
                            <div class="bbai-ftue-actions__buttons">
                                <button type="button" class="button button-primary button-large bbai-btn bbai-btn-primary bbai-btn-lg" id="bbai-ftue-primary-button">
                                    <?php echo esc_html($bbai_primary_label); ?>
                                </button>
                                <a
                                    class="button button-secondary bbai-btn bbai-btn-secondary"
                                    id="bbai-secondary-action"
                                    href="<?php echo esc_url($bbai_fallback_url); ?>"
                                    data-action="show-auth-modal"
                                    data-auth-tab="register"
                                    <?php echo $bbai_show_secondary_action ? '' : 'hidden'; ?>
                                >
                                    <?php echo esc_html($bbai_secondary_label); ?>
                                </a>
                            </div>
                            <p class="bbai-ftue-actions__proof" id="bbai-ftue-cta-proof">
                                <?php echo esc_html($bbai_primary_cta_proof); ?>
                            </p>
                            <p class="bbai-ftue-actions__prompt" id="bbai-ftue-cta-prompt" hidden>
                                <?php echo esc_html($bbai_primary_cta_prompt); ?>
                            </p>
                            <p class="bbai-ftue-helper" id="bbai-ftue-helper-text">
                                <?php echo esc_html($bbai_helper_text); ?>
                            </p>
                            <p class="bbai-ftue-scan-status" id="bbai-scan-status" role="status" aria-live="polite">
                                <?php echo esc_html($bbai_status_text); ?>
                            </p>
                            <a
                                class="bbai-ftue-secondary-link bbai-link bbai-link--muted"
                                id="bbai-conversion-login-btn"
                                href="<?php echo esc_url($bbai_fallback_url); ?>"
                                data-action="show-auth-modal"
                                data-auth-tab="login"
                                <?php echo $bbai_show_login_link ? '' : 'hidden'; ?>
                            >
                                <?php esc_html_e('Already have an account? Log in', 'beepbeep-ai-alt-text-generator'); ?>
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
                    </div>

                    <aside class="bbai-ftue-hero__rail">
                        <section class="bbai-ftue-value-card" id="bbai-ftue-callout">
                            <p class="bbai-ftue-value-card__eyebrow" id="bbai-ftue-badge"><?php echo esc_html($bbai_value_eyebrow); ?></p>
                            <div class="bbai-ftue-value-card__headline-row">
                                <div class="bbai-ftue-value-card__metric">50</div>
                                <div class="bbai-ftue-value-card__headline-copy">
                                    <h2 class="bbai-ftue-value-card__headline"><?php esc_html_e('Get 50 free credits every month', 'beepbeep-ai-alt-text-generator'); ?></h2>
                                    <p class="bbai-ftue-value-card__lead" id="bbai-ftue-callout-lead"><?php echo esc_html($bbai_callout_lead); ?></p>
                                </div>
                            </div>
                            <p class="bbai-ftue-value-card__trust" id="bbai-ftue-callout-trust"><?php echo esc_html($bbai_callout_trust); ?></p>
                            <p class="bbai-ftue-value-card__renewal" id="bbai-ftue-callout-renewal"><?php echo esc_html($bbai_callout_renewal); ?></p>
                            <ul class="bbai-ftue-value-card__benefits">
                                <li><?php esc_html_e('Save your cleanup progress', 'beepbeep-ai-alt-text-generator'); ?></li>
                                <li><?php esc_html_e('Continuously improve your SEO and accessibility', 'beepbeep-ai-alt-text-generator'); ?></li>
                                <li><?php esc_html_e('Fix your entire media library faster', 'beepbeep-ai-alt-text-generator'); ?></li>
                            </ul>
                            <section class="bbai-ftue-site-card" id="bbai-ftue-site-card" <?php echo $bbai_show_site_card ? '' : 'hidden'; ?>>
                                <p class="bbai-ftue-site-card__label" id="bbai-ftue-site-card-label"><?php echo esc_html($bbai_site_card_label); ?></p>
                                <div class="bbai-ftue-site-card__value" id="bbai-ftue-site-card-value"><?php echo esc_html($bbai_site_card_value); ?></div>
                                <p class="bbai-ftue-site-card__caption" id="bbai-ftue-site-card-caption"><?php echo esc_html($bbai_site_card_caption); ?></p>
                                <div class="bbai-ftue-site-card__meta">
                                    <article class="bbai-ftue-inline-stat" id="bbai-ftue-stat-optimized-wrap" <?php echo $bbai_has_optimized_count ? '' : 'hidden'; ?>>
                                        <span class="bbai-ftue-inline-stat__label"><?php esc_html_e('Already optimized', 'beepbeep-ai-alt-text-generator'); ?></span>
                                        <strong class="bbai-ftue-inline-stat__value" id="bbai-ftue-stat-optimized"><?php echo esc_html($bbai_format_number($bbai_optimized_count)); ?></strong>
                                    </article>
                                    <article class="bbai-ftue-inline-stat">
                                        <span class="bbai-ftue-inline-stat__label"><?php esc_html_e('Free fixes left', 'beepbeep-ai-alt-text-generator'); ?></span>
                                        <strong class="bbai-ftue-inline-stat__value" id="bbai-ftue-stat-free-left"><?php echo esc_html($bbai_format_number($bbai_trial_remaining)); ?></strong>
                                    </article>
                                </div>
                            </section>
                            <a
                                class="button button-primary bbai-btn bbai-btn-rail"
                                href="<?php echo esc_url($bbai_fallback_url); ?>"
                                data-action="show-auth-modal"
                                data-auth-tab="register"
                            >
                                <?php esc_html_e('Create free account', 'beepbeep-ai-alt-text-generator'); ?>
                            </a>
                            <p class="bbai-ftue-value-card__cta-note" id="bbai-ftue-callout-cta-note"><?php echo esc_html($bbai_callout_cta_note); ?></p>
                        </section>
                    </aside>
                </div>
            </section>

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
                    <?php esc_html_e('Your first free fixes', 'beepbeep-ai-alt-text-generator'); ?>
                </h2>
                <ul class="bbai-ftue-instant-win__list" id="bbai-instant-win-list"></ul>
            </section>

            <p class="bbai-ftue-empty-message" id="bbai-ftue-empty-message" hidden></p>

            <section class="bbai-ftue-preview" aria-labelledby="bbai-preview-title">
                <h2 id="bbai-preview-title" class="bbai-logged-out__section-title">
                    <?php esc_html_e('Preview', 'beepbeep-ai-alt-text-generator'); ?>
                </h2>
                <p class="bbai-ftue-preview__line">
                    <strong><?php esc_html_e('Before:', 'beepbeep-ai-alt-text-generator'); ?></strong>
                    <span id="bbai-preview-before"><?php esc_html_e('No alt text', 'beepbeep-ai-alt-text-generator'); ?></span>
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
                        <p class="bbai-ftue-step__text"><?php esc_html_e('Find images missing alt text.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </li>
                    <li class="bbai-ftue-step">
                        <span class="bbai-ftue-step__label"><?php esc_html_e('Step 2', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <p class="bbai-ftue-step__text"><?php esc_html_e('Generate clear, SEO-ready alt text.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </li>
                    <li class="bbai-ftue-step">
                        <span class="bbai-ftue-step__label"><?php esc_html_e('Step 3', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <p class="bbai-ftue-step__text"><?php esc_html_e('Apply the fixes across your media library.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </li>
                    <li class="bbai-ftue-step">
                        <span class="bbai-ftue-step__label"><?php esc_html_e('Step 4', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <p class="bbai-ftue-step__text"><?php esc_html_e('Create a free account to keep going with 50 credits every month.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </li>
                </ol>
            </section>

        </div>
    </div>
</div>

<style>
.bbai-logged-out__container {
    width: 100%;
    max-width: 1200px !important;
    margin: 0 auto;
}

.bbai-logged-out__card {
    width: 100%;
    text-align: left;
}

.bbai-ftue-card {
    width: 100%;
    max-width: none;
    margin: 24px auto;
    padding: 36px;
    border: 1px solid #d7e3ec;
    border-radius: 20px;
    background:
        radial-gradient(circle at top right, rgba(56, 189, 248, 0.08), transparent 34%),
        radial-gradient(circle at bottom left, rgba(16, 185, 129, 0.06), transparent 28%),
        linear-gradient(180deg, #fbfdff 0%, #f3f8fc 100%);
    box-shadow: 0 28px 60px rgba(15, 23, 42, 0.10);
}

.bbai-ftue-hero {
    margin-bottom: 34px;
}

.bbai-ftue-hero__shell {
    display: grid;
    grid-template-columns: minmax(0, 1.28fr) minmax(360px, 0.92fr);
    gap: 34px;
    align-items: start;
    animation: bbai-ftue-enter 460ms cubic-bezier(0.2, 0.8, 0.2, 1) both;
}

.bbai-ftue-hero__main,
.bbai-ftue-value-card,
.bbai-ftue-preview,
.bbai-ftue-step,
.bbai-ftue-instant-win,
.bbai-ftue-progress,
.bbai-ftue-empty-message {
    border-radius: 24px;
}

.bbai-ftue-hero__main {
    position: relative;
    padding: 44px 42px 38px;
    border: 1px solid rgba(211, 225, 236, 0.96);
    background:
        radial-gradient(circle at top left, rgba(125, 211, 252, 0.16), transparent 34%),
        linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 251, 255, 0.96) 100%);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.94),
        0 18px 34px rgba(148, 163, 184, 0.10);
}

.bbai-ftue-hero__rail {
    display: flex;
    flex-direction: column;
}

.bbai-ftue-hero__topline {
    margin-bottom: 16px;
}

.bbai-ftue-hero__kicker {
    display: inline-flex;
    align-items: center;
    margin: 0;
    padding: 7px 12px;
    border-radius: 999px;
    background: #eaf4ff;
    color: #0f4c81;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.bbai-ftue-hero__header {
    margin-bottom: 0;
    text-align: left;
}

.bbai-ftue-hero__title {
    max-width: 720px;
    margin: 0 0 10px;
    font-size: clamp(40px, 4.2vw, 52px);
    line-height: 1;
    letter-spacing: -0.04em;
    color: #0f172a;
}

.bbai-ftue-hero__impact {
    max-width: 620px;
    margin: 0 0 10px;
    font-size: 18px;
    line-height: 1.45;
    font-weight: 600;
    color: #0f3b68;
}

.bbai-ftue-hero__impact[hidden] {
    display: none !important;
}

.bbai-ftue-hero__body {
    max-width: 600px;
    margin: 0;
    font-size: 17px;
    line-height: 1.62;
    color: #475569;
}

.bbai-ftue-hero__benefit {
    max-width: 600px;
    margin: 8px 0 0;
    font-size: 14px;
    line-height: 1.6;
    font-weight: 600;
    color: #0f4c81;
}

.bbai-ftue-hero__benefit[hidden] {
    display: none !important;
}

.bbai-ftue-library-progress {
    margin: 14px 0 0;
    padding: 18px 20px 18px;
    border: 1px solid #d9e7f1;
    background: rgba(247, 251, 255, 0.9);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

.bbai-ftue-library-progress.is-live {
    border-color: #c8def0;
    background: linear-gradient(180deg, rgba(241, 248, 255, 0.98) 0%, rgba(233, 244, 255, 0.94) 100%);
}

.bbai-ftue-library-progress.is-idle .bbai-ftue-library-progress__fill {
    background: linear-gradient(90deg, rgba(14, 165, 233, 0.82) 0%, rgba(56, 189, 248, 0.9) 42%, rgba(16, 185, 129, 0.84) 100%);
    background-size: 180% 100%;
    animation: bbai-ftue-progress-shimmer 4.8s ease-in-out infinite;
}

.bbai-ftue-library-progress__label {
    margin: 0 0 10px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #64748b;
}

.bbai-ftue-library-progress__summary {
    display: block;
    font-size: 22px;
    font-weight: 700;
    color: #0f172a;
}

.bbai-ftue-library-progress__detail {
    margin: 4px 0 0;
    font-size: 14px;
    line-height: 1.6;
    color: #334155;
}

.bbai-ftue-library-progress__detail[hidden] {
    display: none !important;
}

.bbai-ftue-library-progress__bar {
    height: 8px;
    margin: 12px 0 0;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.08);
    overflow: hidden;
}

.bbai-ftue-library-progress__fill {
    display: block;
    width: 0;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #0ea5e9 0%, #10b981 100%);
    transition: width 0.25s ease;
}

.bbai-ftue-library-progress.is-live .bbai-ftue-library-progress__fill {
    background: linear-gradient(90deg, #0ea5e9 0%, #38bdf8 42%, #10b981 100%);
    background-size: 180% 100%;
    animation: bbai-ftue-progress-shimmer 1.2s linear infinite;
}

.bbai-ftue-library-progress__note {
    margin: 10px 0 0;
    font-size: 13px;
    line-height: 1.5;
    font-weight: 600;
    color: #0f4c81;
}

.bbai-ftue-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
    margin-top: 24px;
}

.bbai-ftue-actions__buttons {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    width: 100%;
    max-width: 620px;
}

.bbai-ftue-actions .button {
    width: 100%;
    min-height: 56px;
    min-width: 0;
    padding: 10px 18px !important;
    justify-content: center;
    border-radius: 14px !important;
    font-size: 14px;
    font-weight: 700;
    line-height: 1.25 !important;
    text-align: center;
    white-space: normal !important;
    transition:
        transform 180ms ease,
        box-shadow 180ms ease,
        background-color 180ms ease,
        border-color 180ms ease,
        color 180ms ease;
}

.bbai-ftue-actions .bbai-btn-primary.button.button-primary {
    border: none !important;
    background: linear-gradient(180deg, #12b981 0%, #0e9f6e 100%) !important;
    color: #ffffff !important;
    box-shadow: 0 14px 28px rgba(16, 185, 129, 0.22) !important;
}

.bbai-ftue-actions .bbai-btn-primary.button.button-primary:hover {
    background: linear-gradient(180deg, #10ad79 0%, #0b9063 100%) !important;
    box-shadow: 0 16px 30px rgba(16, 185, 129, 0.24) !important;
    transform: translateY(-1px);
}

.bbai-ftue-actions .bbai-btn-secondary.button.button-secondary {
    border: 1px solid #d7e4ef !important;
    background: rgba(255, 255, 255, 0.84) !important;
    color: #16406f !important;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9) !important;
    font-weight: 600;
}

.bbai-ftue-actions .bbai-btn-secondary.button.button-secondary:hover {
    background: #ffffff !important;
    border-color: #c7d9e8 !important;
    transform: translateY(-1px);
}

.bbai-ftue-actions .button:focus-visible,
.bbai-btn-rail.button.button-primary:focus-visible {
    outline: 2px solid rgba(14, 165, 233, 0.55);
    outline-offset: 2px;
}

.bbai-ftue-helper {
    max-width: 600px;
    margin: 2px 0 0;
    font-size: 13px;
    line-height: 1.55;
    font-weight: 600;
    color: #16406f;
}

.bbai-ftue-actions__proof,
.bbai-ftue-actions__prompt {
    margin: 0;
    max-width: 600px;
    font-size: 12.5px;
    line-height: 1.5;
    color: #64748b;
}

.bbai-ftue-actions__proof {
    margin-top: 8px;
    font-weight: 700;
    color: #0f3b68;
}

.bbai-ftue-actions__prompt {
    color: #334155;
}

.bbai-ftue-actions__prompt[hidden] {
    display: none !important;
}

.bbai-ftue-scan-status {
    margin: 4px 0 0;
    font-size: 12px;
    line-height: 1.6;
    color: #64748b;
}

.bbai-ftue-scan-status.is-error {
    color: #b32d2e;
}

.bbai-ftue-secondary-link {
    font-size: 13px;
}

.bbai-ftue-hidden-signup {
    display: none !important;
}

.bbai-ftue-value-card {
    display: flex;
    flex-direction: column;
    gap: 0;
    min-height: 100%;
    position: relative;
    overflow: hidden;
    isolation: isolate;
    padding: 28px;
    background:
        radial-gradient(circle at top right, rgba(125, 211, 252, 0.22), transparent 24%),
        linear-gradient(160deg, #14315b 0%, #12548d 58%, #1097d9 100%);
    background-size: 120% 120%;
    box-shadow: 0 16px 28px rgba(15, 23, 42, 0.13);
    color: #ffffff;
    animation: bbai-ftue-value-drift 16s ease-in-out infinite alternate;
}

.bbai-ftue-value-card::before {
    content: '';
    position: absolute;
    inset: -24% auto auto -18%;
    width: 260px;
    height: 260px;
    border-radius: 999px;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.16) 0%, rgba(255, 255, 255, 0) 72%);
    opacity: 0.75;
    filter: blur(12px);
    animation: bbai-ftue-value-glow 12s ease-in-out infinite alternate;
    pointer-events: none;
}

.bbai-ftue-value-card > * {
    position: relative;
    z-index: 1;
}

.bbai-ftue-value-card__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 16px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.76);
}

.bbai-ftue-value-card__eyebrow::after {
    content: '';
    width: 34px;
    height: 1px;
    background: rgba(255, 255, 255, 0.28);
}

.bbai-ftue-value-card__headline-row {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 20px;
}

.bbai-ftue-value-card__metric {
    margin: 0;
    font-size: clamp(60px, 7vw, 80px);
    line-height: 0.9;
    font-weight: 800;
    letter-spacing: -0.06em;
}

.bbai-ftue-value-card__headline-copy {
    flex: 1;
    padding-top: 6px;
}

.bbai-ftue-value-card__headline {
    margin: 0 0 10px;
    font-size: 31px;
    line-height: 1.04;
    letter-spacing: -0.04em;
    color: #ffffff;
}

.bbai-ftue-value-card__lead {
    margin: 0;
    max-width: 280px;
    font-size: 17px;
    line-height: 1.5;
    color: rgba(255, 255, 255, 0.92);
}

.bbai-ftue-value-card__trust,
.bbai-ftue-value-card__renewal,
.bbai-ftue-value-card__cta-note {
    margin: 0;
    font-size: 13px;
    line-height: 1.6;
    color: rgba(255, 255, 255, 0.82);
}

.bbai-ftue-value-card__trust {
    margin-bottom: 4px;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.96);
}

.bbai-ftue-value-card__renewal {
    margin-bottom: 18px;
}

.bbai-ftue-value-card__benefits {
    margin: 0 0 24px;
    padding: 0;
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.bbai-ftue-value-card__benefits li {
    position: relative;
    padding-left: 18px;
    font-size: 14px;
    line-height: 1.55;
    color: rgba(255, 255, 255, 0.92);
}

.bbai-ftue-value-card__benefits li::before {
    content: '';
    position: absolute;
    top: 8px;
    left: 0;
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: #a5f3fc;
}

.bbai-ftue-site-card {
    margin: 0 0 22px;
    padding: 20px 22px;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.97);
    color: #0f172a;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.94);
}

.bbai-ftue-site-card__label {
    margin: 0 0 12px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #64748b;
}

.bbai-ftue-site-card__value {
    margin: 0;
    font-size: clamp(50px, 6vw, 64px);
    line-height: 0.94;
    font-weight: 800;
    letter-spacing: -0.06em;
    color: #0f172a;
}

.bbai-ftue-site-card__caption {
    margin: 10px 0 0;
    font-size: 16px;
    line-height: 1.45;
    font-weight: 700;
    color: #1e293b;
}

.bbai-ftue-site-card__meta {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    margin-top: 18px;
    padding-top: 18px;
    border-top: 1px solid #e2e8f0;
}

.bbai-ftue-inline-stat__label {
    display: block;
    margin-bottom: 8px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #64748b;
}

.bbai-ftue-inline-stat__value {
    display: block;
    font-size: 32px;
    line-height: 1;
    font-weight: 800;
    letter-spacing: -0.04em;
    color: #0f172a;
}

.bbai-btn-rail.button.button-primary {
    width: 100%;
    min-height: 52px;
    justify-content: center;
    font-weight: 700;
    border-radius: 14px !important;
    border: none !important;
    background: #ffffff !important;
    color: #0f172a !important;
    box-shadow: 0 8px 20px rgba(10, 21, 42, 0.14) !important;
    transition: transform 180ms ease, box-shadow 180ms ease, background-color 180ms ease;
}

.bbai-btn-rail.button.button-primary:hover {
    background: #f8fbff !important;
    box-shadow: 0 10px 22px rgba(10, 21, 42, 0.16) !important;
    transform: translateY(-1px);
}

.bbai-ftue-value-card__cta-note {
    margin-top: 10px;
    text-align: center;
}

.bbai-ftue-progress,
.bbai-ftue-instant-win,
.bbai-ftue-empty-message,
.bbai-ftue-preview,
.bbai-logged-out__how-it-works {
    max-width: 960px;
    margin-left: auto;
    margin-right: auto;
}

.bbai-ftue-progress {
    margin-bottom: 18px;
    padding: 16px 18px;
    border: 1px solid #dbe7f3;
    background: #f8fbff;
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
    background: #dbe7f3;
    overflow: hidden;
}

.bbai-ftue-progress__fill {
    display: block;
    width: 0;
    height: 100%;
    background: linear-gradient(90deg, #0ea5e9 0%, #10b981 100%);
    transition: width 0.2s ease;
}

.bbai-ftue-progress__message {
    margin: 8px 0 0;
}

.bbai-ftue-instant-win {
    margin-bottom: 20px;
    padding: 20px 22px;
    border: 1px solid #dbe7f3;
    background: #ffffff;
}

.bbai-ftue-instant-win__list {
    margin: 0;
    padding: 0;
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.bbai-ftue-instant-win__item {
    padding: 12px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #f8fafc;
}

.bbai-ftue-instant-win__filename {
    margin: 0 0 6px;
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
}

.bbai-ftue-instant-win__pair {
    margin: 0;
    font-size: 13px;
    color: #1f2937;
}

.bbai-ftue-instant-win__pair + .bbai-ftue-instant-win__pair {
    margin-top: 4px;
}

.bbai-ftue-instant-win__label {
    font-weight: 700;
}

.bbai-ftue-empty-message {
    margin-bottom: 20px;
    padding: 12px 14px;
    border-left: 4px solid #2271b1;
    background: #eff6ff;
    color: #1e3a5f;
}

.bbai-ftue-preview {
    margin-bottom: 26px;
    padding: 22px 24px;
    border: 1px solid #dfe7ef;
    background: rgba(255, 255, 255, 0.84);
}

.bbai-ftue-preview__line {
    margin: 10px 0;
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
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
}

.bbai-ftue-step {
    padding: 16px 18px;
    border: 1px solid #dfe7ef;
    background: rgba(255, 255, 255, 0.84);
}

.bbai-ftue-step__label {
    display: block;
    margin-bottom: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
}

.bbai-ftue-step__text {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    line-height: 1.55;
    color: #0f172a;
}

@keyframes bbai-ftue-enter {
    from {
        opacity: 0;
        transform: translateY(12px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes bbai-ftue-progress-shimmer {
    0% {
        background-position: 180% 0;
    }
    100% {
        background-position: -180% 0;
    }
}

@keyframes bbai-ftue-value-drift {
    0% {
        background-position: 0% 0%;
    }
    100% {
        background-position: 100% 100%;
    }
}

@keyframes bbai-ftue-value-glow {
    0% {
        transform: translate3d(0, 0, 0);
        opacity: 0.62;
    }
    100% {
        transform: translate3d(36px, 18px, 0);
        opacity: 0.84;
    }
}

@media (prefers-reduced-motion: reduce) {
    .bbai-ftue-hero__shell,
    .bbai-ftue-value-card,
    .bbai-ftue-value-card::before,
    .bbai-ftue-library-progress__fill,
    .bbai-ftue-actions .button,
    .bbai-btn-rail.button.button-primary {
        animation: none !important;
        transition: none !important;
    }
}

@media (max-width: 1120px) {
    .bbai-ftue-hero__shell {
        grid-template-columns: 1fr;
    }

    .bbai-ftue-card {
        padding: 30px;
    }

    .bbai-ftue-hero__main,
    .bbai-ftue-value-card {
        padding: 30px;
    }

    .bbai-ftue-steps {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 782px) {
    .bbai-ftue-card {
        padding: 18px;
        border-radius: 18px;
    }

    .bbai-ftue-hero__main,
    .bbai-ftue-value-card,
    .bbai-ftue-preview,
    .bbai-ftue-step,
    .bbai-ftue-instant-win,
    .bbai-ftue-progress,
    .bbai-ftue-empty-message {
        padding: 24px 22px;
        border-radius: 20px;
    }

    .bbai-ftue-hero__title {
        font-size: 40px;
    }

    .bbai-ftue-actions__buttons,
    .bbai-ftue-site-card__meta {
        grid-template-columns: 1fr;
    }

    .bbai-ftue-value-card__headline-row {
        flex-direction: column;
        gap: 12px;
    }

    .bbai-ftue-value-card__headline-copy {
        padding-top: 0;
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
        'trustLine' => __('Works with WordPress Media Library, WooCommerce, and most themes.', 'beepbeep-ai-alt-text-generator'),
        'statusReady' => __('Works with WordPress Media Library, WooCommerce, and most themes.', 'beepbeep-ai-alt-text-generator'),
        'statusProgress' => __('Works with WordPress Media Library, WooCommerce, and most themes.', 'beepbeep-ai-alt-text-generator'),
        'statusSignup' => __('Works with WordPress Media Library, WooCommerce, and most themes.', 'beepbeep-ai-alt-text-generator'),
        'statusFallback' => __('Works with WordPress Media Library, WooCommerce, and most themes.', 'beepbeep-ai-alt-text-generator'),
        'statusGenerating' => __('Generating alt text for your free fixes…', 'beepbeep-ai-alt-text-generator'),
        'scanNoMissing' => __('Your media library already looks covered.', 'beepbeep-ai-alt-text-generator'),
        'liveProgressSummary' => __('Generating alt text for your free fixes…', 'beepbeep-ai-alt-text-generator'),
        'liveProgressDetail' => __('This usually takes a few seconds.', 'beepbeep-ai-alt-text-generator'),
        /* translators: %s: number of images without alt text. */
        'heroFoundSingle' => __('We found %s image missing alt text 👀', 'beepbeep-ai-alt-text-generator'),
        /* translators: %s: number of images without alt text. */
        'heroFoundPlural' => __('We found %s images missing alt text 👀', 'beepbeep-ai-alt-text-generator'),
        'heroFallback' => __('Start fixing missing alt text across your media library', 'beepbeep-ai-alt-text-generator'),
        'heroKickerAvailable' => __('Scan complete', 'beepbeep-ai-alt-text-generator'),
        'heroKickerProgress' => __('Progress started', 'beepbeep-ai-alt-text-generator'),
        'heroKickerComplete' => __('Progress started', 'beepbeep-ai-alt-text-generator'),
        'heroImpactAvailable' => __('This is hurting your SEO and accessibility.', 'beepbeep-ai-alt-text-generator'),
        /* translators: %s: number of free trial images. */
        'heroBenefitAvailableSingle' => __('Your first %s fix is ready now.', 'beepbeep-ai-alt-text-generator'),
        /* translators: %s: number of free trial images. */
        'heroBenefitAvailablePlural' => __('Your first %s fixes are ready now.', 'beepbeep-ai-alt-text-generator'),
        'heroAvailableBodySingle' => __('Start fixing them for free. No account needed.', 'beepbeep-ai-alt-text-generator'),
        'heroAvailableBodyPlural' => __('Start fixing them for free. No account needed.', 'beepbeep-ai-alt-text-generator'),
        'heroProgressBody' => __('Keep going and clean up your media library faster.', 'beepbeep-ai-alt-text-generator'),
        'heroCompleteBody' => __('Keep going and clean up your media library faster.', 'beepbeep-ai-alt-text-generator'),
        /* translators: %s: number of free images. */
        'primaryGenerateSingle' => __('Generate alt text for %s image free', 'beepbeep-ai-alt-text-generator'),
        /* translators: %s: number of free images. */
        'primaryGeneratePlural' => __('Generate alt text for %s images free', 'beepbeep-ai-alt-text-generator'),
        /* translators: %s: number of remaining free trial images. */
        'primaryContinueSingle' => __('Fix %s more image free', 'beepbeep-ai-alt-text-generator'),
        /* translators: %s: number of remaining free trial images. */
        'primaryContinuePlural' => __('Fix %s more images free', 'beepbeep-ai-alt-text-generator'),
        'primaryComplete' => __('Fix the remaining images', 'beepbeep-ai-alt-text-generator'),
        'secondaryUnlock' => __('Continue fixing with a free account', 'beepbeep-ai-alt-text-generator'),
        'helperAvailable' => __('No credit card required • 50 free credits every month', 'beepbeep-ai-alt-text-generator'),
        'helperComplete' => __('No credit card required • 50 free credits every month', 'beepbeep-ai-alt-text-generator'),
        'ctaProofFree' => __('No account needed • Takes ~10 seconds', 'beepbeep-ai-alt-text-generator'),
        'ctaProofSignup' => __('No credit card required • Takes ~10 seconds', 'beepbeep-ai-alt-text-generator'),
        'ctaPromptAvailable' => __('', 'beepbeep-ai-alt-text-generator'),
        'ctaPromptProgress' => __('', 'beepbeep-ai-alt-text-generator'),
        'ctaPromptComplete' => __('', 'beepbeep-ai-alt-text-generator'),
        'loginLink' => __('Already have an account? Log in', 'beepbeep-ai-alt-text-generator'),
        'siteCardLabelAvailable' => __('Still need alt text', 'beepbeep-ai-alt-text-generator'),
        'siteCardLabelProgress' => __('Still need alt text', 'beepbeep-ai-alt-text-generator'),
        'siteCardCaption' => __('images still need alt text', 'beepbeep-ai-alt-text-generator'),
        /* translators: %s: number of images still missing alt text. */
        'missingSummarySingle' => __('%s image needs fixing', 'beepbeep-ai-alt-text-generator'),
        /* translators: %s: number of images still missing alt text. */
        'missingSummaryPlural' => __('%s images need fixing', 'beepbeep-ai-alt-text-generator'),
        /* translators: %s: number of images already optimized. */
        'optimizedSummarySingle' => __('%s image already optimized', 'beepbeep-ai-alt-text-generator'),
        /* translators: %s: number of images already optimized. */
        'optimizedSummaryPlural' => __('%s images already optimized', 'beepbeep-ai-alt-text-generator'),
        /* translators: 1: number of free fixes used, 2: total free fixes in trial. */
        'trialUsageSingle' => __('%1$s / %2$s free fix used', 'beepbeep-ai-alt-text-generator'),
        /* translators: 1: number of free fixes used, 2: total free fixes in trial. */
        'trialUsagePlural' => __('%1$s / %2$s free fixes used', 'beepbeep-ai-alt-text-generator'),
        /* translators: 1: number of completed free fixes, 2: total requested free fixes. */
        'liveCompletionSingle' => __('%1$s / %2$s free image completed', 'beepbeep-ai-alt-text-generator'),
        /* translators: 1: number of completed free fixes, 2: total requested free fixes. */
        'liveCompletionPlural' => __('%1$s / %2$s free images completed', 'beepbeep-ai-alt-text-generator'),
        'buttonGenerating' => __('Generating alt text…', 'beepbeep-ai-alt-text-generator'),
        'progressStarting' => __('Starting generation…', 'beepbeep-ai-alt-text-generator'),
        'progressDone' => __('Generation complete', 'beepbeep-ai-alt-text-generator'),
        'instantTitleDefault' => __('Your first fixes', 'beepbeep-ai-alt-text-generator'),
        /* translators: %d: number of images. */
        'instantTitleFormat' => __('Your latest %d fixes', 'beepbeep-ai-alt-text-generator'),
        'instantBefore' => __('Before', 'beepbeep-ai-alt-text-generator'),
        'instantAfter' => __('After', 'beepbeep-ai-alt-text-generator'),
        'beforeEmpty' => __('No alt text', 'beepbeep-ai-alt-text-generator'),
        'friendlyEmpty' => __('No images are currently missing alt text. Upload an image to keep your library optimized.', 'beepbeep-ai-alt-text-generator'),
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
    var totalImagesCount = <?php echo null !== $bbai_total_images_count ? (int) $bbai_total_images_count : 'null'; ?>;
    var missingAltCount = <?php echo null !== $bbai_missing_alt_count ? (int) $bbai_missing_alt_count : 'null'; ?>;
    var remainingSiteCount = <?php echo null !== $bbai_remaining_site_count ? (int) $bbai_remaining_site_count : 'null'; ?>;
    var hasMissingCount = <?php echo $bbai_has_missing_count ? 'true' : 'false'; ?>;

    var rootEl = document.querySelector('.bbai-logged-out');
    var primaryButtonEl = document.getElementById('bbai-ftue-primary-button');
    var secondaryActionEl = document.getElementById('bbai-secondary-action');
    var helperTextEl = document.getElementById('bbai-ftue-helper-text');
    var loginLinkEl = document.getElementById('bbai-conversion-login-btn');
    var heroKickerEl = document.getElementById('bbai-ftue-kicker');
    var heroTitleEl = document.getElementById('bbai-ftue-hero-title');
    var heroImpactEl = document.getElementById('bbai-ftue-hero-impact');
    var heroBodyEl = document.getElementById('bbai-ftue-hero-body');
    var heroBenefitEl = document.getElementById('bbai-ftue-hero-benefit');
    var scanStatusEl = document.getElementById('bbai-scan-status');
    var previewBeforeEl = document.getElementById('bbai-preview-before');
    var previewAfterEl = document.getElementById('bbai-preview-after');
    var signupTriggerEl = document.getElementById('bbai-signup-trigger');
    var ctaProofEl = document.getElementById('bbai-ftue-cta-proof');
    var ctaPromptEl = document.getElementById('bbai-ftue-cta-prompt');

    var libraryProgressEl = document.getElementById('bbai-ftue-library-progress');
    var libraryProgressSummaryEl = document.getElementById('bbai-library-progress-summary');
    var libraryProgressDetailEl = document.getElementById('bbai-library-progress-detail');
    var libraryProgressNoteEl = document.getElementById('bbai-library-progress-note');
    var libraryProgressFillEl = document.getElementById('bbai-library-progress-fill');
    var statOptimizedWrapEl = document.getElementById('bbai-ftue-stat-optimized-wrap');
    var statOptimizedEl = document.getElementById('bbai-ftue-stat-optimized');
    var statFreeLeftEl = document.getElementById('bbai-ftue-stat-free-left');

    var siteCardEl = document.getElementById('bbai-ftue-site-card');
    var siteCardLabelEl = document.getElementById('bbai-ftue-site-card-label');
    var siteCardValueEl = document.getElementById('bbai-ftue-site-card-value');
    var siteCardCaptionEl = document.getElementById('bbai-ftue-site-card-caption');

    var progressEl = document.getElementById('bbai-ftue-progress');
    var progressLabelEl = document.getElementById('bbai-progress-label');
    var progressCountEl = document.getElementById('bbai-progress-count');
    var progressFillEl = document.getElementById('bbai-progress-fill');
    var progressMessageEl = document.getElementById('bbai-progress-message');

    var instantWinEl = document.getElementById('bbai-instant-win');
    var instantTitleEl = document.getElementById('bbai-instant-win-title');
    var instantListEl = document.getElementById('bbai-instant-win-list');
    var emptyMessageEl = document.getElementById('bbai-ftue-empty-message');

    var requestInFlight = false;
    var trialStartedTracked = false;
    var trialExhaustedTracked = false;
    var trialCompleteStateTracked = false;
    var loggedOutConversionStateTracked = false;
    var trialProgressStateTracked = false;
    var activeProgressCurrent = 0;
    var activeProgressTotal = 0;
    var introAnimationPlayed = false;
    var reduceMotionQuery = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;

    function emitAnalyticsEvent(eventName, properties) {
        try {
            document.dispatchEvent(new CustomEvent('bbai:analytics', {
                detail: Object.assign({ event: eventName }, properties || {})
            }));
        } catch (error) {
            // Ignore analytics dispatch failures.
        }
    }

    function getMissingAltCount() {
        if (!hasMissingCount || null === missingAltCount || typeof missingAltCount === 'undefined') {
            return null;
        }
        return Math.max(0, parseInt(missingAltCount, 10) || 0);
    }

    function getTotalImagesCount() {
        if (null === totalImagesCount || typeof totalImagesCount === 'undefined') {
            return null;
        }
        return Math.max(0, parseInt(totalImagesCount, 10) || 0);
    }

    function getOptimizedCount() {
        var totalCount = getTotalImagesCount();
        var totalMissingCount = getMissingAltCount();

        if (null === totalCount || null === totalMissingCount || totalCount < totalMissingCount) {
            return null;
        }

        return Math.max(0, totalCount - totalMissingCount);
    }

    function getFixedCount() {
        return Math.max(0, Math.min(parseInt(trialUsed, 10) || 0, parseInt(trialLimit, 10) || 0));
    }

    function getRemainingSiteCount() {
        if (!hasMissingCount) {
            return null;
        }
        if (null !== remainingSiteCount && typeof remainingSiteCount !== 'undefined') {
            return Math.max(0, parseInt(remainingSiteCount, 10) || 0);
        }

        var totalMissingCount = getMissingAltCount();
        if (null === totalMissingCount) {
            return null;
        }

        return Math.max(0, totalMissingCount - getFixedCount());
    }

    function getRemainingCount() {
        return getRemainingSiteCount();
    }

    function getTotalProblemCount() {
        return getMissingAltCount();
    }

    function showProgressState() {
        return null !== getMissingAltCount();
    }

    function getHeroState() {
        if (trialExhausted || trialRemaining <= 0) {
            return 'trial_complete';
        }
        if (getFixedCount() > 0) {
            return 'trial_progress';
        }
        return 'trial_available';
    }

    function syncAnalyticsContext() {
        var analyticsContext = {
            remaining_free_images: trialRemaining,
            trial_exhausted: trialExhausted,
            trial_used: getFixedCount(),
            missing_alt_count: hasMissingCount ? getMissingAltCount() : null,
            remaining_missing_images: hasMissingCount ? getRemainingSiteCount() : null,
            total_problem_count: showProgressState() ? getTotalImagesCount() : null,
            hero_state: getHeroState()
        };

        window.missingAltCount = getMissingAltCount();

        if (window.bbaiAnalytics && typeof window.bbaiAnalytics.updateContext === 'function') {
            window.bbaiAnalytics.updateContext(analyticsContext);
        }
    }

    function maybeTrackLoggedOutStateExposure() {
        var heroState = getHeroState();

        if ('trial_progress' === heroState && !trialProgressStateTracked) {
            trialProgressStateTracked = true;
            emitAnalyticsEvent('trial_progress_state_shown', {
                source: 'guest_dashboard',
                remaining_free_images: trialRemaining,
                fixed_images: getFixedCount(),
                missing_alt_count: getRemainingSiteCount()
            });
        }

        if ('trial_complete' !== heroState) {
            return;
        }

        if (!trialCompleteStateTracked) {
            trialCompleteStateTracked = true;
            emitAnalyticsEvent('trial_complete_state_shown', {
                source: 'guest_dashboard',
                remaining_free_images: trialRemaining,
                fixed_images: getFixedCount(),
                missing_alt_count: getRemainingSiteCount()
            });
        }

        if (!loggedOutConversionStateTracked) {
            loggedOutConversionStateTracked = true;
            emitAnalyticsEvent('logged_out_conversion_state_shown', {
                source: 'guest_dashboard',
                remaining_free_images: trialRemaining,
                fixed_images: getFixedCount(),
                missing_alt_count: getRemainingSiteCount()
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
            remaining_free_images: trialRemaining,
            fixed_images: getFixedCount(),
            missing_alt_count: getRemainingSiteCount()
        }, properties || {}));
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

    function getPluralText(singleTemplate, pluralTemplate, count) {
        return count === 1 ? singleTemplate : pluralTemplate;
    }

    function prefersReducedMotion() {
        return !!(reduceMotionQuery && reduceMotionQuery.matches);
    }

    function getProblemHeadline(count, forceCountHeadline) {
        if (null === count) {
            return i18n.heroFallback;
        }

        if (!forceCountHeadline && count <= 0) {
            return i18n.heroFallback;
        }

        return simpleSprintf(
            getPluralText(i18n.heroFoundSingle, i18n.heroFoundPlural, count),
            formatNumber(count)
        );
    }

    function getHeroBenefitCopy(heroState, availableCount) {
        if ('trial_available' !== heroState) {
            return '';
        }

        return simpleSprintf(
            getPluralText(i18n.heroBenefitAvailableSingle, i18n.heroBenefitAvailablePlural, availableCount),
            formatNumber(availableCount)
        );
    }

    function setHeroTitleWithCount(count, forceCountHeadline) {
        if (!heroTitleEl) {
            return;
        }

        heroTitleEl.textContent = getProblemHeadline(count, !!forceCountHeadline);
    }

    function setStaticProgressCopy(missingCount, optimizedCount, fixedCount, safeTrialLimit) {
        if (libraryProgressSummaryEl) {
            if (null !== missingCount) {
                libraryProgressSummaryEl.textContent = simpleSprintf(
                    getPluralText(i18n.missingSummarySingle, i18n.missingSummaryPlural, Math.max(0, missingCount)),
                    formatNumber(missingCount)
                );
            } else {
                libraryProgressSummaryEl.textContent = '';
            }
        }

        if (libraryProgressDetailEl) {
            if (null !== optimizedCount) {
                libraryProgressDetailEl.hidden = false;
                libraryProgressDetailEl.textContent = simpleSprintf(
                    getPluralText(i18n.optimizedSummarySingle, i18n.optimizedSummaryPlural, Math.max(0, optimizedCount)),
                    formatNumber(optimizedCount)
                );
            } else {
                libraryProgressDetailEl.hidden = true;
                libraryProgressDetailEl.textContent = '';
            }
        }

        if (libraryProgressNoteEl) {
            libraryProgressNoteEl.textContent = simpleSprintf(
                getPluralText(i18n.trialUsageSingle, i18n.trialUsagePlural, safeTrialLimit),
                formatNumber(fixedCount),
                formatNumber(safeTrialLimit)
            );
        }
    }

    function animateValue(from, to, duration, onUpdate, onComplete) {
        var startTime = null;
        var startValue = Math.max(0, parseInt(from, 10) || 0);
        var endValue = Math.max(0, parseInt(to, 10) || 0);

        if (prefersReducedMotion() || startValue === endValue) {
            onUpdate(endValue);
            if (typeof onComplete === 'function') {
                onComplete(endValue);
            }
            return;
        }

        function step(timestamp) {
            if (null === startTime) {
                startTime = timestamp;
            }

            var progress = Math.min(1, (timestamp - startTime) / Math.max(1, duration));
            var eased = 1 - Math.pow(1 - progress, 3);
            var currentValue = Math.round(startValue + ((endValue - startValue) * eased));

            onUpdate(currentValue);

            if (progress < 1) {
                window.requestAnimationFrame(step);
                return;
            }

            if (typeof onComplete === 'function') {
                onComplete(endValue);
            }
        }

        window.requestAnimationFrame(step);
    }

    function getHeroCopy() {
        var heroState = getHeroState();
        var totalMissingCount = getMissingAltCount();
        var availableCount = Math.max(1, Math.min(trialLimit || trialRemaining || 1, Math.max(trialRemaining || 0, trialLimit || 1)));
        var primaryCount = Math.max(1, trialRemaining || 1);
        var copy = {
            state: heroState,
            kicker: i18n.heroKickerAvailable,
            title: '',
            impact: '',
            showImpact: false,
            body: '',
            benefit: '',
            primary: '',
            secondary: i18n.secondaryUnlock,
            helper: i18n.helperAvailable,
            ctaProof: i18n.ctaProofFree,
            ctaPrompt: i18n.ctaPromptAvailable,
            showSecondary: true,
            showLogin: false,
            showBenefit: false
        };

        copy.title = getProblemHeadline(totalMissingCount, false);
        copy.impact = i18n.heroImpactAvailable;
        copy.showImpact = null !== totalMissingCount && totalMissingCount > 0;

        if ('trial_complete' === heroState) {
            copy.kicker = i18n.heroKickerComplete;
            copy.body = i18n.heroCompleteBody;
            copy.primary = i18n.primaryComplete;
            copy.helper = i18n.helperComplete;
            copy.ctaProof = i18n.ctaProofSignup;
            copy.ctaPrompt = i18n.ctaPromptComplete;
            copy.showLogin = true;
            return copy;
        }

        if ('trial_progress' === heroState) {
            copy.kicker = i18n.heroKickerProgress;
            copy.body = i18n.heroProgressBody;
            copy.primary = simpleSprintf(
                getPluralText(i18n.primaryContinueSingle, i18n.primaryContinuePlural, primaryCount),
                formatNumber(primaryCount)
            );
            copy.helper = i18n.helperComplete;
            copy.ctaProof = i18n.ctaProofFree;
            copy.ctaPrompt = i18n.ctaPromptProgress;
            return copy;
        }

        copy.kicker = i18n.heroKickerAvailable;
        copy.body = simpleSprintf(
            getPluralText(i18n.heroAvailableBodySingle, i18n.heroAvailableBodyPlural, availableCount),
            formatNumber(availableCount)
        );
        copy.benefit = getHeroBenefitCopy(heroState, availableCount);
        copy.showBenefit = !!copy.benefit;
        copy.primary = simpleSprintf(
            getPluralText(i18n.primaryGenerateSingle, i18n.primaryGeneratePlural, availableCount),
            formatNumber(availableCount)
        );

        return copy;
    }

    function getSiteCardCopy() {
        var totalMissingCount = getMissingAltCount();

        if (null === totalMissingCount) {
            return {
                show: false
            };
        }

        return {
            show: true,
            label: i18n.siteCardLabelAvailable,
            value: formatNumber(totalMissingCount),
            caption: i18n.siteCardCaption
        };
    }

    function getDefaultStatusMessage() {
        var remainingCount = getRemainingSiteCount();
        var heroState = getHeroState();

        if (null !== remainingCount && remainingCount <= 0) {
            return i18n.scanNoMissing;
        }
        if ('trial_complete' === heroState) {
            return i18n.statusSignup;
        }
        if ('trial_progress' === heroState) {
            return i18n.statusProgress;
        }
        if ('trial_available' === heroState) {
            return i18n.statusReady;
        }
        return i18n.statusFallback;
    }

    function updateScanStatus(message, isError) {
        if (!scanStatusEl) {
            return;
        }
        scanStatusEl.textContent = message;
        scanStatusEl.classList.toggle('is-error', !!isError);
    }

    function revealFeedbackRegion(element) {
        if (!element || typeof element.getBoundingClientRect !== 'function') {
            return;
        }

        window.requestAnimationFrame(function() {
            var rect = element.getBoundingClientRect();
            var viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;

            if (rect.top >= 0 && rect.bottom <= viewportHeight) {
                return;
            }

            element.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        });
    }

    function refreshPassiveStatus() {
        if (requestInFlight) {
            return;
        }
        updateScanStatus(getDefaultStatusMessage(), false);
    }

    function updatePrimaryButtonState() {
        if (!primaryButtonEl) {
            return;
        }

        if (requestInFlight) {
            primaryButtonEl.disabled = true;
            primaryButtonEl.textContent = i18n.buttonGenerating;
            return;
        }

        primaryButtonEl.disabled = false;
        primaryButtonEl.textContent = getHeroCopy().primary;
    }

    function updateHeroStats() {
        var optimizedCount = getOptimizedCount();

        if (statOptimizedWrapEl) {
            statOptimizedWrapEl.hidden = null === optimizedCount;
        }
        if (statOptimizedEl && null !== optimizedCount) {
            statOptimizedEl.textContent = formatNumber(optimizedCount);
        }
        if (statFreeLeftEl) {
            statFreeLeftEl.textContent = formatNumber(Math.max(0, parseInt(trialRemaining, 10) || 0));
        }
    }

    function updateHeroUi() {
        var heroCopy = getHeroCopy();

        if (rootEl) {
            rootEl.setAttribute('data-hero-state', heroCopy.state);
        }
        if (heroKickerEl) {
            heroKickerEl.textContent = heroCopy.kicker;
        }
        if (heroTitleEl) {
            heroTitleEl.textContent = heroCopy.title;
        }
        if (heroImpactEl) {
            heroImpactEl.hidden = !heroCopy.showImpact;
            heroImpactEl.textContent = heroCopy.impact;
        }
        if (heroBodyEl) {
            heroBodyEl.textContent = heroCopy.body;
        }
        if (heroBenefitEl) {
            heroBenefitEl.hidden = !heroCopy.showBenefit;
            heroBenefitEl.textContent = heroCopy.benefit;
        }
        if (secondaryActionEl) {
            secondaryActionEl.hidden = !heroCopy.showSecondary;
            secondaryActionEl.textContent = heroCopy.secondary;
        }
        if (ctaProofEl) {
            ctaProofEl.textContent = heroCopy.ctaProof;
        }
        if (ctaPromptEl) {
            ctaPromptEl.hidden = !heroCopy.ctaPrompt;
            ctaPromptEl.textContent = heroCopy.ctaPrompt;
        }
        if (helperTextEl) {
            helperTextEl.textContent = heroCopy.helper;
        }
        if (loginLinkEl) {
            loginLinkEl.hidden = !heroCopy.showLogin;
            loginLinkEl.textContent = i18n.loginLink;
        }

        updatePrimaryButtonState();
    }

    function updateSiteCard() {
        var siteCopy = getSiteCardCopy();

        if (!siteCardEl) {
            return;
        }

        siteCardEl.hidden = !siteCopy.show;
        if (!siteCopy.show) {
            return;
        }

        if (siteCardLabelEl) {
            siteCardLabelEl.textContent = siteCopy.label;
        }
        if (siteCardValueEl) {
            siteCardValueEl.textContent = siteCopy.value;
        }
        if (siteCardCaptionEl) {
            siteCardCaptionEl.textContent = siteCopy.caption;
        }
    }

    function updateStaticProgress() {
        var fixedCount = getFixedCount();
        var totalMissingCount = getMissingAltCount();
        var optimizedCount = getOptimizedCount();
        var shouldShow = showProgressState();
        var safeTrialLimit = Math.max(1, parseInt(trialLimit, 10) || 1);

        if (!libraryProgressEl) {
            return;
        }

        libraryProgressEl.hidden = !shouldShow;
        libraryProgressEl.classList.toggle('is-live', !!requestInFlight);
        libraryProgressEl.classList.toggle('is-idle', !requestInFlight && fixedCount <= 0);
        if (!shouldShow) {
            return;
        }

        if (requestInFlight) {
            if (libraryProgressSummaryEl) {
                libraryProgressSummaryEl.textContent = i18n.liveProgressSummary;
            }
            if (libraryProgressDetailEl) {
                libraryProgressDetailEl.hidden = false;
                libraryProgressDetailEl.textContent = i18n.liveProgressDetail;
            }
            if (libraryProgressNoteEl) {
                libraryProgressNoteEl.textContent = simpleSprintf(
                    getPluralText(i18n.liveCompletionSingle, i18n.liveCompletionPlural, Math.max(0, activeProgressCurrent)),
                    formatNumber(activeProgressCurrent),
                    formatNumber(Math.max(1, activeProgressTotal))
                );
            }
            if (libraryProgressFillEl) {
                libraryProgressFillEl.style.width = (activeProgressCurrent > 0
                    ? Math.min(100, Math.round((activeProgressCurrent / Math.max(1, activeProgressTotal)) * 100))
                    : 18) + '%';
            }
            return;
        }

        setStaticProgressCopy(totalMissingCount, optimizedCount, fixedCount, safeTrialLimit);
        if (libraryProgressFillEl) {
            libraryProgressFillEl.style.width = (fixedCount > 0
                ? Math.min(100, Math.round((fixedCount / safeTrialLimit) * 100))
                : 12) + '%';
        }
    }

    function primeIntroMetrics() {
        if (prefersReducedMotion() || requestInFlight || introAnimationPlayed) {
            return;
        }

        var totalMissingCount = getMissingAltCount();
        if (null === totalMissingCount) {
            return;
        }

        setHeroTitleWithCount(0, true);

        if (siteCardValueEl) {
            siteCardValueEl.textContent = '0';
        }
        if (statOptimizedEl && null !== getOptimizedCount()) {
            statOptimizedEl.textContent = '0';
        }
        if (statFreeLeftEl) {
            statFreeLeftEl.textContent = '0';
        }

        setStaticProgressCopy(0, null !== getOptimizedCount() ? 0 : null, getFixedCount(), Math.max(1, parseInt(trialLimit, 10) || 1));
        if (libraryProgressFillEl) {
            libraryProgressFillEl.style.width = '0%';
        }
    }

    function animateIntroMetrics() {
        if (prefersReducedMotion() || requestInFlight || introAnimationPlayed) {
            return;
        }

        var totalMissingCount = getMissingAltCount();
        if (null === totalMissingCount) {
            return;
        }

        introAnimationPlayed = true;

        var targetOptimizedCount = getOptimizedCount();
        var targetFreeLeftCount = Math.max(0, parseInt(trialRemaining, 10) || 0);
        var safeTrialLimit = Math.max(1, parseInt(trialLimit, 10) || 1);
        var animatedMissingCount = 0;
        var animatedOptimizedCount = null !== targetOptimizedCount ? 0 : null;

        animateValue(0, totalMissingCount, 760, function(currentValue) {
            if (requestInFlight) {
                return;
            }

            animatedMissingCount = currentValue;
            setHeroTitleWithCount(currentValue, true);

            if (siteCardValueEl) {
                siteCardValueEl.textContent = formatNumber(currentValue);
            }

            setStaticProgressCopy(animatedMissingCount, animatedOptimizedCount, getFixedCount(), safeTrialLimit);
        }, function() {
            if (requestInFlight) {
                return;
            }

            updateStaticProgress();
            updateSiteCard();
        });

        if (null !== targetOptimizedCount) {
            animateValue(0, targetOptimizedCount, 680, function(currentValue) {
                if (requestInFlight) {
                    return;
                }

                animatedOptimizedCount = currentValue;
                if (statOptimizedEl) {
                    statOptimizedEl.textContent = formatNumber(currentValue);
                }
                setStaticProgressCopy(animatedMissingCount, currentValue, getFixedCount(), safeTrialLimit);
            }, function() {
                if (requestInFlight) {
                    return;
                }

                updateHeroStats();
                updateStaticProgress();
            });
        }

        animateValue(0, targetFreeLeftCount, 620, function(currentValue) {
            if (requestInFlight) {
                return;
            }

            if (statFreeLeftEl) {
                statFreeLeftEl.textContent = formatNumber(currentValue);
            }
        }, function() {
            if (requestInFlight) {
                return;
            }

            updateHeroStats();
        });
    }

    function syncUi() {
        updateHeroUi();
        updateHeroStats();
        updateSiteCard();
        updateStaticProgress();
        refreshPassiveStatus();
        syncAnalyticsContext();
        maybeTrackLoggedOutStateExposure();
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
        var authTrigger = signupTriggerEl || document.querySelector('[data-action="show-auth-modal"][data-auth-tab="register"]');
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

    function showProgress(current, total, message) {
        if (!progressEl) {
            return;
        }

        var safeTotal = Math.max(1, parseInt(total, 10) || 1);
        var safeCurrent = Math.max(0, parseInt(current, 10) || 0);
        var width = Math.min(100, Math.round((safeCurrent / safeTotal) * 100));
        var wasHidden = progressEl.hidden;
        activeProgressCurrent = safeCurrent;
        activeProgressTotal = safeTotal;

        progressEl.hidden = false;
        if (wasHidden) {
            revealFeedbackRegion(progressEl);
        }
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

        updateStaticProgress();
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
        var beforeText = first.previous_alt ? first.previous_alt : i18n.beforeEmpty;
        if (previewBeforeEl) {
            previewBeforeEl.textContent = beforeText;
        }
        if (previewAfterEl && first.new_alt) {
            previewAfterEl.textContent = '"' + first.new_alt + '"';
            previewAfterEl.classList.add('bbai-ftue-preview__generated');
        }
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
                    syncUi();
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
            var missingRaw = parseInt(data.missing_alt_count, 10);

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
            if (!isNaN(missingRaw) && missingRaw >= 0) {
                hasMissingCount = true;
                remainingSiteCount = Math.max(0, missingRaw);
                missingAltCount = Math.max(0, missingRaw);
            }

            trialExhausted = !!data.trial_exhausted || trialRemaining <= 0;
            syncUi();
        }).catch(function() {
            // Keep server-rendered values if hydration fails.
        });
    }

    function getPrimaryRequestedCount() {
        var heroState = getHeroState();

        if ('trial_complete' === heroState) {
            return 0;
        }
        if ('trial_progress' === heroState) {
            return Math.max(1, trialRemaining);
        }
        return Math.max(1, Math.min(trialLimit || trialRemaining || 1, Math.max(trialRemaining || 0, trialLimit || 1)));
    }

    function runDemoBatch(limit) {
        if (requestInFlight) {
            return;
        }

        if ('trial_complete' === getHeroState()) {
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
                missing_alt_count: getRemainingSiteCount()
            });
        }
        emitAnalyticsEvent('trial_generation_started', {
            source: 'guest_dashboard',
            requested_count: limit,
            remaining_free_images: trialRemaining,
            missing_alt_count: getRemainingSiteCount()
        });
        updatePrimaryButtonState();
        updateScanStatus(i18n.statusGenerating, false);
        showProgress(0, Math.max(1, limit), i18n.progressStarting);

        postAction('bbai_trial_demo_generate_batch', { limit: limit }).then(function(resp) {
            if (!resp || !resp.success) {
                var errorData = resp && resp.data ? resp.data : {};
                var errorCode = String(errorData.code || '').toLowerCase();

                if (errorCode === 'bbai_trial_exhausted') {
                    trialRemaining = 0;
                    trialExhausted = true;
                    syncUi();
                    maybeTrackTrialExhausted({
                        source: 'guest_dashboard',
                        requested_count: limit,
                        remaining_free_images: 0
                    });
                    showEmptyMessage(errorData.message || i18n.helperComplete);
                    showProgress(0, 1, errorData.message || i18n.helperComplete);
                    updateScanStatus(errorData.message || i18n.helperComplete, false);
                    return;
                }

                showEmptyMessage(errorData.message || i18n.networkError);
                showProgress(0, 1, errorData.message || i18n.networkError);
                updateScanStatus(errorData.message || i18n.networkError, true);
                return;
            }

            var data = resp.data || {};
            var remainingRaw = parseInt(
                typeof data.remaining_free_images !== 'undefined' ? data.remaining_free_images : data.remaining,
                10
            );
            var usedRaw = parseInt(data.used, 10);
            var limitRaw = parseInt(data.limit, 10);
            var missingRaw = parseInt(data.missing_alt_count, 10);

            if (!isNaN(remainingRaw)) {
                trialRemaining = Math.max(0, remainingRaw);
            }
            if (!isNaN(usedRaw) && usedRaw >= 0) {
                trialUsed = usedRaw;
            } else {
                trialUsed = Math.max(0, trialLimit - trialRemaining);
            }
            if (!isNaN(limitRaw) && limitRaw > 0) {
                trialLimit = limitRaw;
            }
            if (!isNaN(missingRaw) && missingRaw >= 0) {
                hasMissingCount = true;
                remainingSiteCount = Math.max(0, missingRaw);
                missingAltCount = Math.max(0, missingRaw);
            }

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
                    missing_alt_count: getRemainingSiteCount()
                });
            }

            if (generatedCount > 0) {
                updatePreviewFromSummary(summary);
                renderInstantWin(summary);
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

            if (null !== getRemainingSiteCount() && getRemainingSiteCount() <= 0 && generatedCount <= 0) {
                showEmptyMessage(i18n.friendlyEmpty, data.upload_url || uploadUrl, i18n.friendlyUpload);
            } else if (generatedCount > 0) {
                hideEmptyMessage();
            }

            syncUi();
        }).catch(function() {
            showEmptyMessage(i18n.networkError);
            showProgress(0, 1, i18n.networkError);
            updateScanStatus(i18n.networkError, true);
        }).finally(function() {
            requestInFlight = false;
            activeProgressCurrent = 0;
            activeProgressTotal = 0;
            updatePrimaryButtonState();
            updateStaticProgress();
            refreshPassiveStatus();
        });
    }

    if (primaryButtonEl) {
        primaryButtonEl.addEventListener('click', function(event) {
            event.preventDefault();

            var heroState = getHeroState();
            var requestedCount = getPrimaryRequestedCount();
            var source = 'guest_dashboard_primary';

            if ('trial_progress' === heroState) {
                source = 'guest_dashboard_continue';
            } else if ('trial_complete' === heroState) {
                source = 'guest_dashboard_conversion';
            }

            emitAnalyticsEvent('trial_cta_clicked', {
                source: source,
                requested_count: requestedCount,
                remaining_free_images: trialRemaining,
                fixed_images: getFixedCount(),
                missing_alt_count: getRemainingSiteCount()
            });

            if ('trial_complete' === heroState) {
                triggerSignupFlow();
                return;
            }

            runDemoBatch(requestedCount);
        });
    }

    if (secondaryActionEl) {
        secondaryActionEl.addEventListener('click', function() {
            emitAnalyticsEvent('trial_cta_clicked', {
                source: 'guest_dashboard_secondary',
                requested_count: getPrimaryRequestedCount(),
                remaining_free_images: trialRemaining,
                fixed_images: getFixedCount(),
                missing_alt_count: getRemainingSiteCount()
            });
        });
    }

    if (null !== getRemainingSiteCount() && getRemainingSiteCount() <= 0 && getFixedCount() <= 0) {
        showEmptyMessage(i18n.friendlyEmpty, uploadUrl, i18n.friendlyUpload);
    } else {
        hideEmptyMessage();
    }

    syncUi();

    if (!prefersReducedMotion() && null !== getMissingAltCount()) {
        primeIntroMetrics();
        window.setTimeout(function() {
            window.requestAnimationFrame(function() {
                animateIntroMetrics();
            });
        }, 120);
        window.setTimeout(function() {
            hydrateTrialStateFromServer();
        }, 920);
    } else {
        hydrateTrialStateFromServer();
    }
})();
</script>
