<?php
/**
 * Guide (How to) tab content.
 * Uses consistent card structure and layout as the rest of the system.
 *
 * Expects $this (core class) and $usage_stats in scope.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plan detection
$has_license = false;
$license_data = null;
try {
    $has_license  = $this->api_client->has_active_license();
    $license_data = $this->api_client->get_license_data();
} catch (Exception $e) {
    $has_license  = false;
    $license_data = null;
}

$plan_slug = isset($usage_stats['plan']) ? $usage_stats['plan'] : 'free';
if ($has_license && $license_data && isset($license_data['organization'])) {
    $license_plan = strtolower($license_data['organization']['plan'] ?? 'free');
    if ($license_plan !== 'free') {
        $plan_slug = $license_plan;
    }
}

$is_free   = ($plan_slug === 'free');
$is_growth = ($plan_slug === 'pro' || $plan_slug === 'growth');
$is_agency = ($plan_slug === 'agency');
$is_pro    = ($is_growth || $is_agency);
?>

<div class="min-h-screen bg-slate-50 px-4 py-6 md:px-6">
    <div class="mx-auto max-w-6xl space-y-6 md:space-y-8">
        <header class="space-y-1">
            <h1 class="text-2xl font-semibold text-slate-900"><?php esc_html_e('How to Use BeepBeep AI', 'beepbeep-ai-alt-text-generator'); ?></h1>
            <p class="text-sm text-slate-600"><?php esc_html_e('Learn how to generate and manage alt text for your images.', 'beepbeep-ai-alt-text-generator'); ?></p>
        </header>

        <?php if (!$is_pro) : ?>
            <section class="rounded-3xl bg-white shadow-xl px-6 py-5 md:px-7 md:py-6">
                <p class="inline-flex items-center rounded-full bg-slate-50 px-3 py-1 text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase">
                    <?php esc_html_e('Pro Features', 'beepbeep-ai-alt-text-generator'); ?>
                </p>

                <ul class="mt-4 space-y-2 text-sm text-slate-800">
                    <li class="flex items-start gap-2">
                        <span class="mt-0.5 inline-flex h-4 w-4 items-center justify-center rounded-full bg-emerald-50 text-[10px] text-emerald-600">
                            &#10003;
                        </span>
                        <span><?php esc_html_e('Priority queue generation for faster alt text.', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="mt-0.5 inline-flex h-4 w-4 items-center justify-center rounded-full bg-emerald-50 text-[10px] text-emerald-600">
                            &#10003;
                        </span>
                        <span><?php esc_html_e('Bulk optimisation for large media libraries.', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="mt-0.5 inline-flex h-4 w-4 items-center justify-center rounded-full bg-emerald-50 text-[10px] text-emerald-600">
                            &#10003;
                        </span>
                        <span><?php esc_html_e('Multilingual alt text for global SEO.', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="mt-0.5 inline-flex h-4 w-4 items-center justify-center rounded-full bg-emerald-50 text-[10px] text-emerald-600">
                            &#10003;
                        </span>
                        <span><?php esc_html_e('Smart tone & style tuning to match your brand.', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="mt-0.5 inline-flex h-4 w-4 items-center justify-center rounded-full bg-emerald-50 text-[10px] text-emerald-600">
                            &#10003;
                        </span>
                        <span><?php esc_html_e('1,000 alt text generations per month on Growth.', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </li>
                </ul>
            </section>
        <?php endif; ?>

        <section class="rounded-3xl bg-white shadow-xl px-6 py-5 md:px-7 md:py-6">
            <h2 class="text-base font-semibold text-slate-900"><?php esc_html_e('Getting Started in 4 Easy Steps', 'beepbeep-ai-alt-text-generator'); ?></h2>

            <div class="mt-4 space-y-4 text-sm text-slate-800">
                <div class="flex gap-3">
                    <div class="mt-0.5 flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500 text-xs font-semibold text-white">1</div>
                    <div>
                        <p class="font-semibold"><?php esc_html_e('Upload Images', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <p class="text-xs text-slate-600"><?php esc_html_e('Add images to your WordPress Media Library.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <div class="mt-0.5 flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500 text-xs font-semibold text-white">2</div>
                    <div>
                        <p class="font-semibold"><?php esc_html_e('Bulk Optimise', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <p class="text-xs text-slate-600"><?php esc_html_e('Generate alt text for multiple images at once from the Dashboard.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <div class="mt-0.5 flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500 text-xs font-semibold text-white">3</div>
                    <div>
                        <p class="font-semibold"><?php esc_html_e('Review & Edit', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <p class="text-xs text-slate-600"><?php esc_html_e('Review and fine-tune generated alt text in the ALT Text Library.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <div class="mt-0.5 flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500 text-xs font-semibold text-white">4</div>
                    <div>
                        <p class="font-semibold"><?php esc_html_e('Regenerate if Needed', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <p class="text-xs text-slate-600"><?php esc_html_e('Use Regenerate to improve alt text quality at any time.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-3xl bg-white shadow-xl px-6 py-5 md:px-7 md:py-6">
            <div class="flex items-center gap-2">
                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-slate-50 text-[11px] text-slate-700">&#128161;</span>
                <h2 class="text-base font-semibold text-slate-900"><?php esc_html_e('Why Alt Text Matters', 'beepbeep-ai-alt-text-generator'); ?></h2>
            </div>

            <ul class="mt-3 space-y-2 text-sm text-slate-800">
                <li class="flex items-start gap-2">
                    <span class="mt-0.5 inline-flex h-4 w-4 items-center justify-center rounded-full bg-emerald-50 text-[10px] text-emerald-600">
                        &#10003;
                    </span>
                    <span><?php esc_html_e('Boosts SEO visibility and click-through rates.', 'beepbeep-ai-alt-text-generator'); ?></span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="mt-0.5 inline-flex h-4 w-4 items-center justify-center rounded-full bg-emerald-50 text-[10px] text-emerald-600">
                        &#10003;
                    </span>
                    <span><?php esc_html_e('Improves Google Images and visual search rankings.', 'beepbeep-ai-alt-text-generator'); ?></span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="mt-0.5 inline-flex h-4 w-4 items-center justify-center rounded-full bg-emerald-50 text-[10px] text-emerald-600">
                        &#10003;
                    </span>
                    <span><?php esc_html_e('Helps achieve WCAG accessibility compliance.', 'beepbeep-ai-alt-text-generator'); ?></span>
                </li>
            </ul>
        </section>

        <section class="grid gap-4 md:grid-cols-2">
            <div class="rounded-3xl bg-white shadow-xl px-6 py-5 md:px-7 md:py-6">
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-slate-50 text-[11px] text-slate-700">&#10022;</span>
                    <h2 class="text-base font-semibold text-slate-900"><?php esc_html_e('Tips for Better Alt Text', 'beepbeep-ai-alt-text-generator'); ?></h2>
                </div>
                <ul class="mt-3 space-y-2 text-sm text-slate-800">
                    <li><?php esc_html_e('Keep it concise and descriptive.', 'beepbeep-ai-alt-text-generator'); ?></li>
                    <li><?php esc_html_e('Be specific about what\'s important in the image.', 'beepbeep-ai-alt-text-generator'); ?></li>
                    <li><?php esc_html_e('Avoid repeating surrounding text or keywords unnaturally.', 'beepbeep-ai-alt-text-generator'); ?></li>
                    <li><?php esc_html_e('Describe context and intent, not just objects.', 'beepbeep-ai-alt-text-generator'); ?></li>
                </ul>
            </div>

            <div class="rounded-3xl bg-white shadow-xl px-6 py-5 md:px-7 md:py-6">
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-slate-50 text-[11px] text-slate-700">&#9881;</span>
                    <h2 class="text-base font-semibold text-slate-900"><?php esc_html_e('Key Features', 'beepbeep-ai-alt-text-generator'); ?></h2>
                </div>
                <ul class="mt-3 space-y-2 text-sm text-slate-800">
                    <li><?php esc_html_e('AI-powered alt text generation.', 'beepbeep-ai-alt-text-generator'); ?></li>
                    <li>
                        <?php esc_html_e('Bulk processing for entire media libraries', 'beepbeep-ai-alt-text-generator'); ?>
                        <?php if (!$is_pro) : ?>
                            <span class="ml-1 rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-slate-500"><?php esc_html_e('Pro', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <?php endif; ?>
                    </li>
                    <li><?php esc_html_e('SEO-optimised descriptions aligned with best practices.', 'beepbeep-ai-alt-text-generator'); ?></li>
                    <li>
                        <?php esc_html_e('Smart tone and style tuning', 'beepbeep-ai-alt-text-generator'); ?>
                        <?php if (!$is_pro) : ?>
                            <span class="ml-1 rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-slate-500"><?php esc_html_e('Pro', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <?php endif; ?>
                    </li>
                    <li>
                        <?php esc_html_e('Multilingual alt text support', 'beepbeep-ai-alt-text-generator'); ?>
                        <?php if (!$is_pro) : ?>
                            <span class="ml-1 rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-slate-500"><?php esc_html_e('Pro', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <?php endif; ?>
                    </li>
                    <li><?php esc_html_e('Accessibility tools to support WCAG guidelines.', 'beepbeep-ai-alt-text-generator'); ?></li>
                </ul>
            </div>
        </section>

        <!-- Bottom Upsell CTA (reusable component - same as dashboard) -->
        <?php
        // Set plan variables for bottom CTA component
        // $is_free, $is_growth, and $is_agency are already set above
        $bottom_upsell_partial = dirname(__FILE__) . '/bottom-upsell-cta.php';
        if (file_exists($bottom_upsell_partial)) {
            include $bottom_upsell_partial;
        }
        ?>
    </div>
</div>
