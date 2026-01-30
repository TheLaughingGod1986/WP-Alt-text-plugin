<?php
/**
 * Guide (How to) tab content - Premium SaaS Design.
 * Uses consistent card structure and unified CSS system.
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

<div class="bbai-dashboard-container">
    <!-- Header Section -->
    <div class="bbai-guide-header">
        <h1 class="bbai-page-title"><?php esc_html_e('How to Use BeepBeep AI', 'opptiai-alt'); ?></h1>
        <p class="bbai-page-subtitle"><?php esc_html_e('Generate SEO-optimized alt text for your images in minutes, not hours.', 'opptiai-alt'); ?></p>
    </div>

    <!-- Growth Features Block (Free users only) -->
    <?php if (!$is_pro) : ?>
    <div class="bbai-card bbai-guide-pro-card">
        <div class="bbai-guide-pro-header">
            <span class="bbai-badge bbai-badge--growth"><?php esc_html_e('Growth', 'opptiai-alt'); ?></span>
            <h2 class="bbai-guide-pro-title"><?php esc_html_e('Unlock Growth Features', 'opptiai-alt'); ?></h2>
        </div>
        <ul class="bbai-guide-pro-list">
            <li class="bbai-guide-pro-item">
                <svg width="20" height="20" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M13 4L6 11L3 8" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span><?php esc_html_e('1,000 alt text generations per month', 'opptiai-alt'); ?></span>
            </li>
            <li class="bbai-guide-pro-item">
                <svg width="20" height="20" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M13 4L6 11L3 8" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span><?php esc_html_e('Bulk processing for entire media libraries', 'opptiai-alt'); ?></span>
            </li>
            <li class="bbai-guide-pro-item">
                <svg width="20" height="20" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M13 4L6 11L3 8" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span><?php esc_html_e('Priority queue for faster results', 'opptiai-alt'); ?></span>
            </li>
            <li class="bbai-guide-pro-item">
                <svg width="20" height="20" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M13 4L6 11L3 8" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span><?php esc_html_e('Smart tone & style tuning', 'opptiai-alt'); ?></span>
            </li>
            <li class="bbai-guide-pro-item">
                <svg width="20" height="20" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M13 4L6 11L3 8" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span><?php esc_html_e('Multilingual alt text for global SEO', 'opptiai-alt'); ?></span>
            </li>
        </ul>
        <div class="bbai-guide-pro-cta">
            <button type="button" class="bbai-btn bbai-btn-primary" data-action="show-upgrade-modal">
                <?php esc_html_e('Upgrade to Growth', 'opptiai-alt'); ?>
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M6 12L10 8L6 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Getting Started Block -->
    <div class="bbai-card bbai-guide-steps-card">
        <h2 class="bbai-card-title"><?php esc_html_e('Get Started in 4 Steps', 'opptiai-alt'); ?></h2>
        <div class="bbai-guide-steps">
            <div class="bbai-guide-step">
                <div class="bbai-guide-step-number">1</div>
                <div class="bbai-guide-step-content">
                    <h3 class="bbai-guide-step-title"><?php esc_html_e('Upload Images', 'opptiai-alt'); ?></h3>
                    <p class="bbai-guide-step-desc"><?php esc_html_e('Add images to your WordPress Media Library as usual.', 'opptiai-alt'); ?></p>
                </div>
            </div>
            <div class="bbai-guide-step">
                <div class="bbai-guide-step-number">2</div>
                <div class="bbai-guide-step-content">
                    <h3 class="bbai-guide-step-title"><?php esc_html_e('Generate Alt Text', 'opptiai-alt'); ?></h3>
                    <p class="bbai-guide-step-desc"><?php esc_html_e('Click "Generate Missing" on the Dashboard to process images in bulk.', 'opptiai-alt'); ?></p>
                </div>
            </div>
            <div class="bbai-guide-step">
                <div class="bbai-guide-step-number">3</div>
                <div class="bbai-guide-step-content">
                    <h3 class="bbai-guide-step-title"><?php esc_html_e('Review & Edit', 'opptiai-alt'); ?></h3>
                    <p class="bbai-guide-step-desc"><?php esc_html_e('Fine-tune generated alt text in the ALT Library tab.', 'opptiai-alt'); ?></p>
                </div>
            </div>
            <div class="bbai-guide-step">
                <div class="bbai-guide-step-number">4</div>
                <div class="bbai-guide-step-content">
                    <h3 class="bbai-guide-step-title"><?php esc_html_e('Regenerate Anytime', 'opptiai-alt'); ?></h3>
                    <p class="bbai-guide-step-desc"><?php esc_html_e('Use "Re-optimise All" to refresh alt text after changing settings.', 'opptiai-alt'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Why Alt Text Matters -->
    <div class="bbai-card bbai-guide-why-card">
        <h2 class="bbai-card-title"><?php esc_html_e('Why Alt Text Matters', 'opptiai-alt'); ?></h2>
        <div class="bbai-guide-why-grid">
            <div class="bbai-guide-why-item">
                <div class="bbai-guide-why-icon bbai-guide-why-icon--seo">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M21 21L16.5 16.5M19 11C19 15.4183 15.4183 19 11 19C6.58172 19 3 15.4183 3 11C3 6.58172 6.58172 3 11 3C15.4183 3 19 6.58172 19 11Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="bbai-guide-why-text">
                    <h4><?php esc_html_e('Boost SEO Rankings', 'opptiai-alt'); ?></h4>
                    <p><?php esc_html_e('Search engines index alt text to understand image content and improve page relevance.', 'opptiai-alt'); ?></p>
                </div>
            </div>
            <div class="bbai-guide-why-item">
                <div class="bbai-guide-why-icon bbai-guide-why-icon--images">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <rect x="3" y="3" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                        <circle cx="8.5" cy="8.5" r="1.5" fill="currentColor"/>
                        <path d="M21 15L16 10L5 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="bbai-guide-why-text">
                    <h4><?php esc_html_e('Google Images Traffic', 'opptiai-alt'); ?></h4>
                    <p><?php esc_html_e('Well-described images rank higher in Google Images and visual search results.', 'opptiai-alt'); ?></p>
                </div>
            </div>
            <div class="bbai-guide-why-item">
                <div class="bbai-guide-why-icon bbai-guide-why-icon--accessibility">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                        <path d="M12 6V12L16 14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="bbai-guide-why-text">
                    <h4><?php esc_html_e('Accessibility Compliance', 'opptiai-alt'); ?></h4>
                    <p><?php esc_html_e('Meet WCAG guidelines and make your site accessible to screen reader users.', 'opptiai-alt'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tips & Features Side-by-Side -->
    <div class="bbai-guide-two-col">
        <!-- Tips Card -->
        <div class="bbai-card bbai-guide-tips-card">
            <h2 class="bbai-card-title"><?php esc_html_e('Tips for Better Alt Text', 'opptiai-alt'); ?></h2>
            <ul class="bbai-guide-tips-list">
                <li class="bbai-guide-tip">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span><?php esc_html_e('Keep it concise: 80-125 characters is ideal', 'opptiai-alt'); ?></span>
                </li>
                <li class="bbai-guide-tip">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span><?php esc_html_e('Describe what matters most in the image', 'opptiai-alt'); ?></span>
                </li>
                <li class="bbai-guide-tip">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span><?php esc_html_e('Avoid keyword stuffing or repeating page text', 'opptiai-alt'); ?></span>
                </li>
                <li class="bbai-guide-tip">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span><?php esc_html_e('Include context: what, who, where, why', 'opptiai-alt'); ?></span>
                </li>
            </ul>
        </div>

        <!-- Features Card -->
        <div class="bbai-card bbai-guide-features-card">
            <h2 class="bbai-card-title"><?php esc_html_e('Key Features', 'opptiai-alt'); ?></h2>
            <ul class="bbai-guide-features-list">
                <li class="bbai-guide-feature">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M8 2L10 6L14 8L10 10L8 14L6 10L2 8L6 6L8 2Z" stroke="currentColor" stroke-width="1.5"/>
                    </svg>
                    <span><?php esc_html_e('AI-powered alt text generation', 'opptiai-alt'); ?></span>
                </li>
                <li class="bbai-guide-feature">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <rect x="2" y="2" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M5 8H11M8 5V11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span>
                        <?php esc_html_e('Bulk processing', 'opptiai-alt'); ?>
                        <?php if (!$is_pro) : ?>
                            <span class="bbai-badge bbai-badge--pro-sm"><?php esc_html_e('Growth', 'opptiai-alt'); ?></span>
                        <?php endif; ?>
                    </span>
                </li>
                <li class="bbai-guide-feature">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M8 5V8L10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span>
                        <?php esc_html_e('Tone & style tuning', 'opptiai-alt'); ?>
                        <?php if (!$is_pro) : ?>
                            <span class="bbai-badge bbai-badge--pro-sm"><?php esc_html_e('Growth', 'opptiai-alt'); ?></span>
                        <?php endif; ?>
                    </span>
                </li>
                <li class="bbai-guide-feature">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M5 8H11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span>
                        <?php esc_html_e('Multilingual support', 'opptiai-alt'); ?>
                        <?php if (!$is_pro) : ?>
                            <span class="bbai-badge bbai-badge--pro-sm"><?php esc_html_e('Growth', 'opptiai-alt'); ?></span>
                        <?php endif; ?>
                    </span>
                </li>
                <li class="bbai-guide-feature">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span><?php esc_html_e('SEO-optimized descriptions', 'opptiai-alt'); ?></span>
                </li>
                <li class="bbai-guide-feature">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M8 6V8M8 10H8.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span><?php esc_html_e('WCAG accessibility tools', 'opptiai-alt'); ?></span>
                </li>
            </ul>
        </div>
    </div>

    <!-- Bottom Upsell CTA (reusable component) -->
    <?php
    $bottom_upsell_partial = plugin_dir_path( BBAI_PLUGIN_FILE ) . 'admin/partials/bottom-upsell-cta.php';
    if (file_exists($bottom_upsell_partial)) {
        include $bottom_upsell_partial;
    }
    ?>
</div>
