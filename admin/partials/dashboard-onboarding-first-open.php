<?php
/**
 * Activation-first dashboard for users who have not completed first ALT generation.
 *
 * Expects parent scope from dashboard-body.php: $bbai_ftue_phase ('pre_scan'|'post_scan'),
 * counts, credits, guide URL, library URLs, $bbai_ftue_session_images, etc.
 *
 * @package BeepBeep_AI
 */

if (!defined('ABSPATH')) {
    exit;
}
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/ui-components.php';

$bbai_ob_phase = isset($bbai_ftue_phase) ? (string) $bbai_ftue_phase : '';
if (!in_array($bbai_ob_phase, ['pre_scan', 'post_scan'], true)) {
    return;
}

$bbai_missing_ob = isset($missingCount) ? max(0, (int) $missingCount) : 0;
$bbai_weak_ob    = isset($weakCount) ? max(0, (int) $weakCount) : 0;
$bbai_total_ob   = isset($totalImages) ? max(0, (int) $totalImages) : 0;
$bbai_credits_ob = isset($creditsRemaining) ? max(0, (int) $creditsRemaining) : 0;
$bbai_usage_ob   = isset($usagePercent) ? max(0, min(100, (int) $usagePercent)) : 0;
$bbai_is_pro_ob  = !empty($isProPlan);
$bbai_attn_ob    = isset($bbai_attn_total_need) ? max(0, (int) $bbai_attn_total_need) : ($bbai_missing_ob + $bbai_weak_ob);
$bbai_session_ob = isset($bbai_ftue_session_images) ? max(0, (int) $bbai_ftue_session_images) : 0;

$bbai_opt_ob = isset($optimizedCount) ? max(0, (int) $optimizedCount) : 0;
$bbai_credits_used_ob = isset($creditsUsed) ? max(0, (int) $creditsUsed) : 0;
/** At least one image shows ALT in library (optimized or needs-review bucket) — not “credits spent”. */
$bbai_has_visible_alt_ob = $bbai_opt_ob > 0 || $bbai_weak_ob > 0;
/**
 * Credits consumed but no row yet in optimized or needs-review — matches dashboard coverage “processing”.
 * Weak > 0 means there is visible copy to review; do not treat as pipeline limbo.
 */
$bbai_ob_processing = $bbai_credits_used_ob > 0 && ! $bbai_has_visible_alt_ob && $bbai_total_ob > 0;

$bbai_starter_n = (int) min(5, max(1, $bbai_missing_ob > 0 ? $bbai_missing_ob : ($bbai_weak_ob > 0 ? $bbai_weak_ob : 1)));
if ($bbai_credits_ob > 0) {
    $bbai_starter_n = (int) min($bbai_starter_n, $bbai_credits_ob);
}

$bbai_ftue_dashboard_url = admin_url('admin.php?page=bbai');

$bbai_show_quota_nudge_ob = !$bbai_is_pro_ob && $bbai_usage_ob >= 65 && $bbai_credits_ob > 0 && $bbai_has_visible_alt_ob && ! $bbai_ob_processing;
$bbai_show_engagement_nudge = !$bbai_is_pro_ob && $bbai_session_ob >= 3 && $bbai_has_visible_alt_ob && ! $bbai_ob_processing;
$bbai_guide_ob               = isset($bbai_guide_url) ? (string) $bbai_guide_url : admin_url('admin.php?page=bbai-guide');
$bbai_missing_library_ob     = add_query_arg(['page' => 'bbai-library', 'status' => 'missing'], admin_url('admin.php'));
$bbai_needs_review_ob        = bbai_alt_library_needs_review_url();
$bbai_library_ob             = add_query_arg(['page' => 'bbai-library'], admin_url('admin.php'));
?>
<section
    class="bbai-onboarding-dashboard"
    data-bbai-onboarding-first-open="1"
    data-bbai-onboarding-phase="<?php echo esc_attr($bbai_ob_phase); ?>"
    aria-label="<?php esc_attr_e('Get started with BeepBeep AI', 'beepbeep-ai-alt-text-generator'); ?>"
>
    <?php if ('pre_scan' === $bbai_ob_phase) : ?>
        <header class="bbai-onboarding-dashboard__hero-panel">
            <h2 class="bbai-onboarding-dashboard__hero-title bbai-section-title">
                <?php esc_html_e('Scan your media library', 'beepbeep-ai-alt-text-generator'); ?>
            </h2>
            <p class="bbai-onboarding-dashboard__hero-lead bbai-section-description">
                <?php esc_html_e('We index your uploads and flag images that are missing ALT text, so you can see what needs attention before you generate anything.', 'beepbeep-ai-alt-text-generator'); ?>
            </p>
            <div class="bbai-onboarding-dashboard__hero-actions">
                <button
                    type="button"
                    class="bbai-btn bbai-btn-primary bbai-btn-lg"
                    data-bbai-action="scan-opportunity"
                ><?php echo esc_html(bbai_copy_cta_start_scanning()); ?></button>
                <a class="bbai-btn bbai-btn-secondary bbai-btn-lg" href="<?php echo esc_url($bbai_guide_ob); ?>">
                    <?php echo esc_html(bbai_copy_cta_learn_how()); ?>
                </a>
            </div>
        </header>

        <section class="bbai-onboarding-dashboard__block" aria-labelledby="bbai-onb-steps-heading">
            <h3 id="bbai-onb-steps-heading" class="bbai-onboarding-dashboard__block-title bbai-section-label">
                <?php esc_html_e('What happens next', 'beepbeep-ai-alt-text-generator'); ?>
            </h3>
            <ol class="bbai-onboarding-dashboard__steps">
                <li class="bbai-onboarding-dashboard__step">
                    <span class="bbai-onboarding-dashboard__step-index" aria-hidden="true">1</span>
                    <div class="bbai-onboarding-dashboard__step-body">
                        <strong class="bbai-onboarding-dashboard__step-title"><?php esc_html_e('Scan your media library', 'beepbeep-ai-alt-text-generator'); ?></strong>
                        <span class="bbai-onboarding-dashboard__step-text"><?php esc_html_e('Find images that are missing ALT text so you know what to fix next.', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </div>
                </li>
                <li class="bbai-onboarding-dashboard__step">
                    <span class="bbai-onboarding-dashboard__step-index" aria-hidden="true">2</span>
                    <div class="bbai-onboarding-dashboard__step-body">
                        <strong class="bbai-onboarding-dashboard__step-title"><?php esc_html_e('Generate ALT text', 'beepbeep-ai-alt-text-generator'); ?></strong>
                        <span class="bbai-onboarding-dashboard__step-text"><?php esc_html_e('Create AI descriptions for images that are missing ALT text.', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </div>
                </li>
                <li class="bbai-onboarding-dashboard__step">
                    <span class="bbai-onboarding-dashboard__step-index" aria-hidden="true">3</span>
                    <div class="bbai-onboarding-dashboard__step-body">
                        <strong class="bbai-onboarding-dashboard__step-title"><?php esc_html_e('Review ALT text', 'beepbeep-ai-alt-text-generator'); ?></strong>
                        <span class="bbai-onboarding-dashboard__step-text"><?php esc_html_e('Open the ALT Library to check wording and approve changes before they go live.', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </div>
                </li>
            </ol>
        </section>

        <section class="bbai-onboarding-dashboard__block bbai-onboarding-dashboard__block--why" aria-labelledby="bbai-onb-why-heading">
            <h3 id="bbai-onb-why-heading" class="bbai-onboarding-dashboard__block-title bbai-section-label">
                <?php esc_html_e('Why it matters', 'beepbeep-ai-alt-text-generator'); ?>
            </h3>
            <ul class="bbai-onboarding-dashboard__why-grid">
                <li class="bbai-onboarding-dashboard__why-item bbai-card bbai-card--soft bbai-card--pad-md">
                    <strong><?php esc_html_e('Stronger accessibility', 'beepbeep-ai-alt-text-generator'); ?></strong>
                    <span><?php esc_html_e('Screen readers and assistive tech rely on clear image descriptions.', 'beepbeep-ai-alt-text-generator'); ?></span>
                </li>
                <li class="bbai-onboarding-dashboard__why-item bbai-card bbai-card--soft bbai-card--pad-md">
                    <strong><?php esc_html_e('Better image SEO', 'beepbeep-ai-alt-text-generator'); ?></strong>
                    <span><?php esc_html_e('Search engines use ALT text to understand what your images show.', 'beepbeep-ai-alt-text-generator'); ?></span>
                </li>
                <li class="bbai-onboarding-dashboard__why-item bbai-card bbai-card--soft bbai-card--pad-md">
                    <strong><?php esc_html_e('Less manual work', 'beepbeep-ai-alt-text-generator'); ?></strong>
                    <span><?php esc_html_e('Skip writing hundreds of descriptions by hand.', 'beepbeep-ai-alt-text-generator'); ?></span>
                </li>
            </ul>
        </section>

        <div class="bbai-onboarding-dashboard__trust" role="list">
            <span class="bbai-onboarding-dashboard__trust-pill" role="listitem"><?php esc_html_e('Bulk generation', 'beepbeep-ai-alt-text-generator'); ?></span>
            <span class="bbai-onboarding-dashboard__trust-pill" role="listitem"><?php esc_html_e('Quality signals', 'beepbeep-ai-alt-text-generator'); ?></span>
            <span class="bbai-onboarding-dashboard__trust-pill" role="listitem"><?php esc_html_e('Accessibility-first', 'beepbeep-ai-alt-text-generator'); ?></span>
            <span class="bbai-onboarding-dashboard__trust-pill" role="listitem"><?php esc_html_e('SEO-friendly output', 'beepbeep-ai-alt-text-generator'); ?></span>
        </div>

    <?php else : ?>
        <div
            class="bbai-activation-ftue bbai-onboarding-dashboard__activation-panel"
            data-bbai-activation-ftue="post_scan"
            data-bbai-ftue-processing="<?php echo $bbai_ob_processing ? '1' : '0'; ?>"
            aria-label="<?php esc_attr_e('Next step after scan', 'beepbeep-ai-alt-text-generator'); ?>"
        >
            <div class="bbai-activation-ftue__inner bbai-activation-ftue__inner--generate">
                <?php if ($bbai_total_ob <= 0) : ?>
                    <h2 class="bbai-activation-ftue__title bbai-section-title"><?php esc_html_e('Scan your media library', 'beepbeep-ai-alt-text-generator'); ?></h2>
                    <p class="bbai-activation-ftue__lead bbai-section-description">
                        <?php esc_html_e('No images found yet. Add files to the media library, then run a scan to find images missing ALT text.', 'beepbeep-ai-alt-text-generator'); ?>
                    </p>
                    <div class="bbai-activation-ftue__actions">
                        <a class="bbai-btn bbai-btn-primary bbai-btn-lg" href="<?php echo esc_url(admin_url('upload.php')); ?>">
                            <?php esc_html_e('Upload images', 'beepbeep-ai-alt-text-generator'); ?>
                        </a>
                        <button
                            type="button"
                            class="bbai-btn bbai-btn-secondary bbai-btn-lg"
                            data-bbai-action="scan-opportunity"
                        ><?php echo esc_html(bbai_copy_cta_start_scanning()); ?></button>
                    </div>
                <?php elseif ($bbai_ob_processing) : ?>
                    <h2 class="bbai-activation-ftue__title bbai-section-title"><?php esc_html_e('Saving your ALT text', 'beepbeep-ai-alt-text-generator'); ?></h2>
                    <p class="bbai-activation-ftue__lead bbai-section-description">
                        <?php esc_html_e('Generation has started. Descriptions usually appear in the ALT Library within a few seconds—refresh this page if counts stay at zero.', 'beepbeep-ai-alt-text-generator'); ?>
                    </p>
                    <div class="bbai-activation-ftue__urgent" role="status">
                        <strong><?php esc_html_e('Processing', 'beepbeep-ai-alt-text-generator'); ?></strong>
                        <span><?php esc_html_e('We’re writing descriptions to your media library—this is not finished until you see updated counts or rows in the library.', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </div>
                    <div class="bbai-activation-ftue__actions">
                        <a class="bbai-btn bbai-btn-primary bbai-btn-lg" href="<?php echo esc_url($bbai_ftue_dashboard_url); ?>">
                            <?php esc_html_e('Refresh dashboard', 'beepbeep-ai-alt-text-generator'); ?>
                        </a>
                        <a class="bbai-btn bbai-btn-secondary bbai-btn-lg" href="<?php echo esc_url($bbai_library_ob); ?>">
                            <?php echo esc_html(bbai_copy_cta_open_alt_library()); ?>
                        </a>
                    </div>
                <?php elseif ($bbai_missing_ob > 0 && $bbai_credits_ob > 0) : ?>
                    <h2 class="bbai-activation-ftue__title bbai-section-title"><?php esc_html_e('Generate ALT text', 'beepbeep-ai-alt-text-generator'); ?></h2>
                    <p class="bbai-activation-ftue__lead bbai-section-description">
                        <?php
                        printf(
                            /* translators: %s: number of images missing ALT */
                            esc_html__('%s images are missing ALT text. Generate descriptions next—then you can review and approve them in the ALT Library.', 'beepbeep-ai-alt-text-generator'),
                            esc_html(number_format_i18n($bbai_missing_ob))
                        );
                        ?>
                    </p>
                    <div class="bbai-activation-ftue__urgent" role="status">
                        <strong><?php esc_html_e('Recommended next step', 'beepbeep-ai-alt-text-generator'); ?></strong>
                        <span>
                            <?php
                            printf(
                                /* translators: %s: number of images */
                                esc_html__('Start with %s images so you can confirm quality before doing the rest.', 'beepbeep-ai-alt-text-generator'),
                                esc_html(number_format_i18n($bbai_starter_n))
                            );
                            ?>
                        </span>
                    </div>
                    <div class="bbai-activation-ftue__actions">
                        <button
                            type="button"
                            class="bbai-btn bbai-btn-primary bbai-btn-lg"
                            data-action="generate-missing"
                            data-bbai-starter-cap="<?php echo esc_attr((string) $bbai_starter_n); ?>"
                        >
                            <?php echo esc_html(bbai_copy_cta_generate_missing_images()); ?>
                        </button>
                        <a class="bbai-btn bbai-btn-secondary bbai-btn-lg" href="<?php echo esc_url($bbai_missing_library_ob); ?>">
                            <?php echo esc_html(bbai_copy_cta_open_alt_library()); ?>
                        </a>
                    </div>
                <?php elseif ($bbai_missing_ob > 0 && $bbai_credits_ob <= 0) : ?>
                    <h2 class="bbai-activation-ftue__title bbai-section-title"><?php esc_html_e('Generate ALT text', 'beepbeep-ai-alt-text-generator'); ?></h2>
                    <p class="bbai-activation-ftue__lead bbai-section-description">
                        <?php
                        printf(
                            /* translators: %s: number of images missing ALT */
                            esc_html__('%s images are missing ALT text. Add credits or upgrade to generate AI descriptions.', 'beepbeep-ai-alt-text-generator'),
                            esc_html(number_format_i18n($bbai_missing_ob))
                        );
                        ?>
                    </p>
                    <div class="bbai-activation-ftue__actions">
                        <button type="button" class="bbai-btn bbai-btn-primary bbai-btn-lg" data-action="show-upgrade-modal" data-bbai-locked-source="ftue_missing_no_credits">
                            <?php echo esc_html(bbai_copy_cta_upgrade_growth()); ?>
                        </button>
                        <a class="bbai-btn bbai-btn-secondary bbai-btn-lg" href="<?php echo esc_url($bbai_missing_library_ob); ?>">
                            <?php echo esc_html(bbai_copy_cta_open_alt_library()); ?>
                        </a>
                    </div>
                <?php elseif ($bbai_weak_ob > 0 && $bbai_credits_ob > 0) : ?>
                    <h2 class="bbai-activation-ftue__title bbai-section-title"><?php esc_html_e('Review ALT text', 'beepbeep-ai-alt-text-generator'); ?></h2>
                    <p class="bbai-activation-ftue__lead bbai-section-description">
                        <?php
                        printf(
                            /* translators: %s: number of images */
                            esc_html__('%s images have ALT text but may need a stronger description. Open the ALT Library to edit or regenerate suggestions before publishing.', 'beepbeep-ai-alt-text-generator'),
                            esc_html(number_format_i18n($bbai_weak_ob))
                        );
                        ?>
                    </p>
                    <div class="bbai-activation-ftue__actions">
                        <a class="bbai-btn bbai-btn-primary bbai-btn-lg" href="<?php echo esc_url($bbai_needs_review_ob); ?>">
                            <?php echo esc_html(bbai_copy_cta_open_alt_library()); ?>
                        </a>
                        <a class="bbai-btn bbai-btn-secondary bbai-btn-lg" href="<?php echo esc_url($bbai_guide_ob); ?>">
                            <?php echo esc_html(bbai_copy_cta_learn_how()); ?>
                        </a>
                    </div>
                <?php else : ?>
                    <h2 class="bbai-activation-ftue__title bbai-section-title">
                        <?php
                        echo $bbai_has_visible_alt_ob
                            ? esc_html__('Review ALT text', 'beepbeep-ai-alt-text-generator')
                            : esc_html__('Next steps', 'beepbeep-ai-alt-text-generator');
                        ?>
                    </h2>
                    <p class="bbai-activation-ftue__lead bbai-section-description">
                        <?php
                        if ($bbai_has_visible_alt_ob) {
                            esc_html_e('ALT text is visible in your library. Open the ALT Library to double-check wording and approve anything you still want to change.', 'beepbeep-ai-alt-text-generator');
                        } elseif ($bbai_attn_ob > 0) {
                            printf(
                                /* translators: %s: number of images needing attention */
                                esc_html__('%s images still need attention. Open the ALT Library to see details.', 'beepbeep-ai-alt-text-generator'),
                                esc_html(number_format_i18n($bbai_attn_ob))
                            );
                        } else {
                            esc_html_e('Nothing is flagged right now. You can still open the ALT Library to review descriptions anytime.', 'beepbeep-ai-alt-text-generator');
                        }
                        ?>
                    </p>
                    <div class="bbai-activation-ftue__actions">
                        <a class="bbai-btn bbai-btn-primary bbai-btn-lg" href="<?php echo esc_url($bbai_library_ob); ?>">
                            <?php echo esc_html(bbai_copy_cta_open_alt_library()); ?>
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($bbai_show_quota_nudge_ob) : ?>
                    <?php
                    bbai_ui_render(
                        'banner',
                        [
                            'type'        => 'warning',
                            'title'       => __('Keep your library moving', 'beepbeep-ai-alt-text-generator'),
                            'description' => __('You’re close to this month’s allowance. Continue generating ALT text without interruption.', 'beepbeep-ai-alt-text-generator'),
                            'primaryCTA'  => [
                                'label'      => __('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'),
                                'attributes' => [
                                    'data-action'            => 'show-upgrade-modal',
                                    'data-bbai-locked-source'=> 'ftue_quota',
                                ],
                            ],
                            'secondaryCTA' => [
                                'label'      => __('Review ALT text', 'beepbeep-ai-alt-text-generator'),
                                'href'       => $bbai_needs_review_ob,
                            ],
                            'extra_class' => 'bbai-onboarding-banner bbai-onboarding-banner--quota',
                        ]
                    );
                    ?>
                <?php elseif ($bbai_show_engagement_nudge) : ?>
                    <?php
                    bbai_ui_render(
                        'banner',
                        [
                            'type'        => 'info',
                            'title'       => __('Continue improving your library', 'beepbeep-ai-alt-text-generator'),
                            'description' => __('Growth unlocks higher monthly limits and automation for every new upload.', 'beepbeep-ai-alt-text-generator'),
                            'primaryCTA'  => [
                                'label'      => __('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'),
                                'attributes' => [
                                    'data-action'            => 'show-upgrade-modal',
                                    'data-bbai-locked-source'=> 'ftue_engagement',
                                ],
                            ],
                            'secondaryCTA' => [
                                'label'      => __('Review ALT text', 'beepbeep-ai-alt-text-generator'),
                                'href'       => $bbai_needs_review_ob,
                            ],
                            'extra_class' => 'bbai-onboarding-banner bbai-onboarding-banner--engagement',
                        ]
                    );
                    ?>
                <?php endif; ?>
            </div>
        </div>

        <section class="bbai-onboarding-dashboard__block" aria-labelledby="bbai-onb-steps-post-heading">
            <h3 id="bbai-onb-steps-post-heading" class="bbai-onboarding-dashboard__block-title bbai-section-label">
                <?php esc_html_e('What happens next', 'beepbeep-ai-alt-text-generator'); ?>
            </h3>
            <ol class="bbai-onboarding-dashboard__steps bbai-onboarding-dashboard__steps--post">
                <li class="bbai-onboarding-dashboard__step bbai-onboarding-dashboard__step--done">
                    <span class="bbai-onboarding-dashboard__step-index" aria-hidden="true">✓</span>
                    <div class="bbai-onboarding-dashboard__step-body">
                        <strong class="bbai-onboarding-dashboard__step-title"><?php esc_html_e('Scan your media library', 'beepbeep-ai-alt-text-generator'); ?></strong>
                        <span class="bbai-onboarding-dashboard__step-text"><?php esc_html_e('Done — we’ve indexed your uploads and flagged images missing ALT text.', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </div>
                </li>
                <li class="bbai-onboarding-dashboard__step">
                    <span class="bbai-onboarding-dashboard__step-index" aria-hidden="true">2</span>
                    <div class="bbai-onboarding-dashboard__step-body">
                        <strong class="bbai-onboarding-dashboard__step-title"><?php esc_html_e('Generate ALT text', 'beepbeep-ai-alt-text-generator'); ?></strong>
                        <span class="bbai-onboarding-dashboard__step-text">
                            <?php
                            if ($bbai_ob_processing) {
                                esc_html_e('Generation is running—wait until library counts update, then review.', 'beepbeep-ai-alt-text-generator');
                            } elseif ($bbai_missing_ob > 0 && $bbai_credits_ob > 0) {
                                esc_html_e('Use the primary action above to generate ALT text in a small batch first.', 'beepbeep-ai-alt-text-generator');
                            } elseif ($bbai_missing_ob > 0 && $bbai_credits_ob <= 0) {
                                esc_html_e('You still have images without ALT text—add credits or upgrade, then generate from the dashboard or library.', 'beepbeep-ai-alt-text-generator');
                            } elseif ($bbai_missing_ob <= 0 && $bbai_weak_ob > 0) {
                                esc_html_e('Nothing is missing right now—move on to review or improve descriptions that need a second look.', 'beepbeep-ai-alt-text-generator');
                            } elseif ($bbai_has_visible_alt_ob) {
                                esc_html_e('You have descriptions in the library—add more from the ALT Library or dashboard whenever you need to.', 'beepbeep-ai-alt-text-generator');
                            } else {
                                esc_html_e('Generate ALT text whenever you have images without descriptions.', 'beepbeep-ai-alt-text-generator');
                            }
                            ?>
                        </span>
                    </div>
                </li>
                <li class="bbai-onboarding-dashboard__step">
                    <span class="bbai-onboarding-dashboard__step-index" aria-hidden="true">3</span>
                    <div class="bbai-onboarding-dashboard__step-body">
                        <strong class="bbai-onboarding-dashboard__step-title">
                            <?php
                            echo $bbai_ob_processing
                                ? esc_html__('Wait for results', 'beepbeep-ai-alt-text-generator')
                                : esc_html__('Review ALT text', 'beepbeep-ai-alt-text-generator');
                            ?>
                        </strong>
                        <span class="bbai-onboarding-dashboard__step-text">
                            <?php
                            if ($bbai_ob_processing) {
                                esc_html_e('Refresh the dashboard or open the library until you see new descriptions—then review and approve.', 'beepbeep-ai-alt-text-generator');
                            } elseif ($bbai_has_visible_alt_ob) {
                                esc_html_e('Open the ALT Library to read each description, tweak wording, and approve when you’re ready to publish.', 'beepbeep-ai-alt-text-generator');
                            } else {
                                esc_html_e('After you generate ALT text, open the ALT Library to review wording—you won’t see AI descriptions there until they appear in the library.', 'beepbeep-ai-alt-text-generator');
                            }
                            ?>
                        </span>
                    </div>
                </li>
            </ol>
        </section>

        <section class="bbai-onboarding-dashboard__block bbai-onboarding-dashboard__block--why" aria-labelledby="bbai-onb-why-post-heading">
            <h3 id="bbai-onb-why-post-heading" class="bbai-onboarding-dashboard__block-title bbai-section-label">
                <?php esc_html_e('Why it matters', 'beepbeep-ai-alt-text-generator'); ?>
            </h3>
            <ul class="bbai-onboarding-dashboard__why-grid">
                <li class="bbai-onboarding-dashboard__why-item bbai-card bbai-card--soft bbai-card--pad-md">
                    <strong><?php esc_html_e('Stronger accessibility', 'beepbeep-ai-alt-text-generator'); ?></strong>
                    <span><?php esc_html_e('Clear ALT text supports every visitor.', 'beepbeep-ai-alt-text-generator'); ?></span>
                </li>
                <li class="bbai-onboarding-dashboard__why-item bbai-card bbai-card--soft bbai-card--pad-md">
                    <strong><?php esc_html_e('Better image SEO', 'beepbeep-ai-alt-text-generator'); ?></strong>
                    <span><?php esc_html_e('Help search engines connect images to your content.', 'beepbeep-ai-alt-text-generator'); ?></span>
                </li>
                <li class="bbai-onboarding-dashboard__why-item bbai-card bbai-card--soft bbai-card--pad-md">
                    <strong><?php esc_html_e('Less manual work', 'beepbeep-ai-alt-text-generator'); ?></strong>
                    <span><?php esc_html_e('Ship descriptions faster across your whole library.', 'beepbeep-ai-alt-text-generator'); ?></span>
                </li>
            </ul>
        </section>

        <div class="bbai-onboarding-dashboard__trust" role="list">
            <span class="bbai-onboarding-dashboard__trust-pill" role="listitem"><?php esc_html_e('Bulk generation', 'beepbeep-ai-alt-text-generator'); ?></span>
            <span class="bbai-onboarding-dashboard__trust-pill" role="listitem"><?php esc_html_e('Quality signals', 'beepbeep-ai-alt-text-generator'); ?></span>
            <span class="bbai-onboarding-dashboard__trust-pill" role="listitem"><?php esc_html_e('Accessibility-first', 'beepbeep-ai-alt-text-generator'); ?></span>
            <span class="bbai-onboarding-dashboard__trust-pill" role="listitem"><?php esc_html_e('SEO-friendly output', 'beepbeep-ai-alt-text-generator'); ?></span>
        </div>
    <?php endif; ?>
</section>
