<?php
/**
 * Phase 10 — Shared admin microcopy (single product voice).
 *
 * All functions return translated strings for the plugin text domain.
 * Prefer these over ad hoc __() in partials so naming stays consistent.
 *
 * @package BeepBeep_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('bbai_copy_td')) {
    /**
     * Text domain for BeepBeep AI admin strings.
     */
    function bbai_copy_td(): string
    {
        return 'beepbeep-ai-alt-text-generator';
    }

    /* --- Filters & workflow status (short labels) ------------------------ */

    function bbai_copy_filter_all(): string
    {
        return __('All', bbai_copy_td());
    }

    function bbai_copy_status_missing(): string
    {
        return __('Missing', bbai_copy_td());
    }

    function bbai_copy_status_needs_review(): string
    {
        return __('Needs review', bbai_copy_td());
    }

    function bbai_copy_status_optimized(): string
    {
        return __('Optimized', bbai_copy_td());
    }

    /* --- Score tier labels (quality column / pills) ---------------------- */

    function bbai_copy_score_excellent(): string
    {
        return __('Excellent', bbai_copy_td());
    }

    function bbai_copy_score_good(): string
    {
        return __('Good', bbai_copy_td());
    }

    function bbai_copy_score_needs_improvement(): string
    {
        return __('Needs improvement', bbai_copy_td());
    }

    function bbai_copy_score_poor(): string
    {
        return __('Poor', bbai_copy_td());
    }

    function bbai_copy_score_critical(): string
    {
        return __('Critical', bbai_copy_td());
    }

    /** Score band label (~70–89): not the same as workflow “Needs review”. */
    function bbai_copy_score_band_review(): string
    {
        return __('Review', bbai_copy_td());
    }

    /** Low score band; use workflow term, not “Weak”. */
    function bbai_copy_score_band_needs_review(): string
    {
        return bbai_copy_status_needs_review();
    }

    /* --- Attention / coverage lines (pluralisation) ------------------------ */

    function bbai_copy_attention_all_optimized(): string
    {
        return __('All images are optimized.', bbai_copy_td());
    }

    /**
     * Missing ALT + needs-review combined count (banners, credit support line).
     *
     * @param int $issue_count Total issues (>0 expected for inline fragments).
     */
    function bbai_copy_attention_issue_sentence(int $issue_count): string
    {
        if ($issue_count <= 0) {
            return bbai_copy_attention_all_optimized();
        }

        if (1 === $issue_count) {
            return __('1 image needs attention', bbai_copy_td());
        }

        return sprintf(
            /* translators: %s: formatted count (2+) */
            _n('%s image needs attention', '%s images need attention', $issue_count, bbai_copy_td()),
            number_format_i18n($issue_count)
        );
    }

    /**
     * @param int $missing Count of images without ALT.
     */
    function bbai_copy_library_missing_alt_headline(int $missing): string
    {
        $missing = max(0, $missing);

        return sprintf(
            /* translators: %s: formatted image count */
            _n('%s image is missing ALT text', '%s images are missing ALT text', $missing, bbai_copy_td()),
            number_format_i18n($missing)
        );
    }

    /**
     * @param int $count Images in needs-review state.
     */
    function bbai_copy_library_needs_review_headline(int $count): string
    {
        $count = max(0, $count);

        return sprintf(
            /* translators: %s: formatted image count */
            _n('%s image needs review', '%s images need review', $count, bbai_copy_td()),
            number_format_i18n($count)
        );
    }

    /* --- Primary / secondary CTAs ------------------------------------------ */

    function bbai_copy_cta_scan_media_library(): string
    {
        return __('Review ALT text', bbai_copy_td());
    }

    /** Primary CTA on first-run scan surfaces (onboarding hero, etc.). */
    function bbai_copy_cta_start_scanning(): string
    {
        return __('Start scanning', bbai_copy_td());
    }

    function bbai_copy_cta_rescan_media_library(): string
    {
        return __('Review ALT text', bbai_copy_td());
    }

    function bbai_copy_cta_open_alt_library(): string
    {
        return __('Open ALT Library', bbai_copy_td());
    }

    function bbai_copy_cta_fix_missing_alt(): string
    {
        return __('Generate missing ALT text', bbai_copy_td());
    }

    function bbai_copy_cta_improve_alt(): string
    {
        return __('Review ALT text', bbai_copy_td());
    }

    function bbai_copy_cta_generate_missing_images(): string
    {
        return __('Generate missing ALT text', bbai_copy_td());
    }

    function bbai_copy_cta_generate_alt(): string
    {
        return __('Generate missing ALT text', bbai_copy_td());
    }

    function bbai_copy_cta_review_optimized_images(): string
    {
        return __('Review ALT text', bbai_copy_td());
    }

    function bbai_copy_cta_automation_settings(): string
    {
        return __('Automation settings', bbai_copy_td());
    }

    function bbai_copy_cta_review_needs_review_filter(): string
    {
        return __('Review ALT text', bbai_copy_td());
    }

    function bbai_copy_cta_review_usage(): string
    {
        return __('Review ALT text', bbai_copy_td());
    }

    function bbai_copy_cta_upgrade_growth(): string
    {
        return __('Upgrade to Growth', bbai_copy_td());
    }

    function bbai_copy_cta_learn_how(): string
    {
        return __('Review ALT text', bbai_copy_td());
    }

    function bbai_copy_cta_enable_auto_optimization(): string
    {
        return __('Enable auto-optimization', bbai_copy_td());
    }

    function bbai_copy_cta_scan_new_uploads(): string
    {
        return __('Review ALT text', bbai_copy_td());
    }

    /* --- Section patterns -------------------------------------------------- */

    function bbai_copy_section_next_task(): string
    {
        return __('Next task', bbai_copy_td());
    }

    function bbai_copy_section_library_progress(): string
    {
        return __('ALT optimization progress', bbai_copy_td());
    }

    function bbai_copy_section_healthy_library(): string
    {
        return __('Healthy library', bbai_copy_td());
    }

    function bbai_copy_library_all_optimized_title(): string
    {
        return __('All images are optimized', bbai_copy_td());
    }

    function bbai_copy_helper_queue_missing_then_review(): string
    {
        return __('Fix missing ALT first, then review descriptions that need improvement.', bbai_copy_td());
    }

    function bbai_copy_helper_generate_then_review(): string
    {
        return __('Generate missing ALT text, then review descriptions that need improvement.', bbai_copy_td());
    }

    function bbai_copy_helper_healthy_keep_coverage(): string
    {
        return __('Run occasional scans or enable auto-optimization to keep coverage current.', bbai_copy_td());
    }

    function bbai_copy_helper_needs_review_actions(): string
    {
        return __('Approve ALT you accept, or regenerate to refine descriptions in the review queue.', bbai_copy_td());
    }

    function bbai_copy_helper_finish_review_queue(): string
    {
        return __('Approve or regenerate ALT to clear the review queue.', bbai_copy_td());
    }

    function bbai_copy_section_descriptions_can_improve(): string
    {
        return __('Descriptions can be improved', bbai_copy_td());
    }

    function bbai_copy_section_all_images_current(): string
    {
        return __('All images are current', bbai_copy_td());
    }

    function bbai_copy_library_fully_optimized_title(): string
    {
        return __('ALT Library is fully optimized', bbai_copy_td());
    }

    function bbai_copy_queue_clear_title(): string
    {
        return __('Queue clear', bbai_copy_td());
    }

    /**
     * @param int $count Rows in needs-review state.
     */
    function bbai_copy_queue_needs_review_title(int $count): string
    {
        $count = max(0, $count);

        return sprintf(
            /* translators: %s: formatted count */
            _n('%s item needs review', '%s items need review', $count, bbai_copy_td()),
            number_format_i18n($count)
        );
    }

    function bbai_copy_quota_monthly_limit_title(): string
    {
        return __('This month’s free allowance is used', bbai_copy_td());
    }

    /**
     * @param int $used Images optimized this period.
     */
    function bbai_copy_quota_monthly_used_line(int $used): string
    {
        $used = max(0, $used);

        return sprintf(
            /* translators: %s: formatted count */
            _n(
                'You optimized %s image this month.',
                'You optimized %s images this month.',
                $used,
                bbai_copy_td()
            ),
            number_format_i18n($used)
        );
    }

    /* --- Shared banner headlines / support (cross-page) -------------------- */

    function bbai_copy_banner_out_of_credits_title(): string
    {
        return __('You’ve used this month’s free allowance', bbai_copy_td());
    }

    function bbai_copy_banner_low_credits_title(): string
    {
        return __('You’re close to this month’s allowance', bbai_copy_td());
    }

    function bbai_copy_banner_needs_attention_title(): string
    {
        return __('Your library needs attention', bbai_copy_td());
    }

    function bbai_copy_banner_needs_attention_body(): string
    {
        return __('Some images are missing ALT text or need a stronger description.', bbai_copy_td());
    }

    function bbai_copy_banner_healthy_title(): string
    {
        return __('Your library is in great shape', bbai_copy_td());
    }

    function bbai_copy_banner_healthy_body(): string
    {
        return __('All images are optimized and up to date.', bbai_copy_td());
    }

    function bbai_copy_banner_first_run_title(): string
    {
        return __('Get started with your media library', bbai_copy_td());
    }

    function bbai_copy_banner_first_run_body(): string
    {
        return __('Scan your library to find missing ALT text and improve accessibility.', bbai_copy_td());
    }

    function bbai_copy_banner_first_run_status(): string
    {
        return __('Start by scanning your media library.', bbai_copy_td());
    }
}

if (!function_exists('bbai_alt_library_needs_review_url')) {
    /**
     * ALT Library deep link: “Needs review” filter only (plain navigation — no generation).
     */
    function bbai_alt_library_needs_review_url(): string
    {
        return add_query_arg(
            [
                'page'   => 'bbai-library',
                'status' => 'needs_review',
                'filter' => 'needs-review',
            ],
            admin_url('admin.php')
        ) . '#bbai-review-filter-tabs';
    }
}
