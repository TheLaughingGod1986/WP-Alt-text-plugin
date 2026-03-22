<?php
/**
 * Help tab content - Premium SaaS Design.
 * Uses consistent card structure and unified CSS system.
 *
 * Expects $this (core class) and $bbai_usage_stats in scope.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plan detection
$bbai_has_license = false;
$bbai_license_data = null;
try {
    $bbai_has_license  = $this->api_client->has_active_license();
    $bbai_license_data = $this->api_client->get_license_data();
} catch (Exception $e) {
    $bbai_has_license  = false;
    $bbai_license_data = null;
}

$bbai_plan_slug = isset($bbai_usage_stats['plan']) ? $bbai_usage_stats['plan'] : 'free';
if ($bbai_has_license && $bbai_license_data && isset($bbai_license_data['organization'])) {
    $bbai_license_plan = strtolower($bbai_license_data['organization']['plan'] ?? 'free');
    if ($bbai_license_plan !== 'free') {
        $bbai_plan_slug = $bbai_license_plan;
    }
}

$bbai_is_free   = ($bbai_plan_slug === 'free');
$bbai_is_growth = ($bbai_plan_slug === 'pro' || $bbai_plan_slug === 'growth');
$bbai_is_agency = ($bbai_plan_slug === 'agency');
$bbai_is_pro    = ($bbai_is_growth || $bbai_is_agency);
?>

<?php
$bbai_library_url = add_query_arg(['page' => 'bbai', 'tab' => 'library'], admin_url('admin.php'));
$bbai_library_missing_url = add_query_arg(['page' => 'bbai', 'tab' => 'library', 'status' => 'missing'], admin_url('admin.php'));
$bbai_settings_url = add_query_arg(['page' => 'bbai', 'tab' => 'settings'], admin_url('admin.php'));
?>
<div class="bbai-guide-page">
    <!-- Header Section -->
    <div class="bbai-guide-header bbai-page-section">
        <h1 class="bbai-page-title"><?php esc_html_e('Help Center', 'beepbeep-ai-alt-text-generator'); ?></h1>
        <p class="bbai-page-subtitle"><?php esc_html_e('Learn how scanning, generation, credits, and accessibility workflows work in BeepBeep AI.', 'beepbeep-ai-alt-text-generator'); ?></p>
    </div>

    <!-- Growth Features Block (Free users only) -->
    <?php if (!$bbai_is_pro) : ?>
    <div class="bbai-card bbai-guide-pro-card">
        <div class="bbai-guide-pro-header">
            <span class="bbai-badge bbai-badge--growth"><?php esc_html_e('Growth', 'beepbeep-ai-alt-text-generator'); ?></span>
            <h2 class="bbai-guide-pro-title"><?php esc_html_e('Unlock Growth Features', 'beepbeep-ai-alt-text-generator'); ?></h2>
        </div>
        <ul class="bbai-guide-pro-list">
            <li class="bbai-guide-pro-item">
                <svg width="20" height="20" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M13 4L6 11L3 8" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span><?php esc_html_e('1,000 alt text generations per month', 'beepbeep-ai-alt-text-generator'); ?></span>
            </li>
            <li class="bbai-guide-pro-item">
                <svg width="20" height="20" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M13 4L6 11L3 8" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span><?php esc_html_e('Bulk processing for entire media libraries', 'beepbeep-ai-alt-text-generator'); ?></span>
            </li>
            <li class="bbai-guide-pro-item">
                <svg width="20" height="20" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M13 4L6 11L3 8" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span><?php esc_html_e('Priority queue for faster results', 'beepbeep-ai-alt-text-generator'); ?></span>
            </li>
            <li class="bbai-guide-pro-item">
                <svg width="20" height="20" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M13 4L6 11L3 8" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span><?php esc_html_e('Smart tone & style tuning', 'beepbeep-ai-alt-text-generator'); ?></span>
            </li>
            <li class="bbai-guide-pro-item">
                <svg width="20" height="20" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M13 4L6 11L3 8" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span><?php esc_html_e('Multilingual alt text for global SEO', 'beepbeep-ai-alt-text-generator'); ?></span>
            </li>
        </ul>
        <div class="bbai-guide-pro-cta">
            <button type="button" class="bbai-btn bbai-btn-primary" data-action="show-upgrade-modal">
                <?php esc_html_e('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'); ?>
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M6 12L10 8L6 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Step-by-Step Guide -->
    <div class="bbai-card bbai-guide-steps-card bbai-page-section">
        <div class="bbai-guide-steps">
            <div class="bbai-guide-step bbai-guide-step-card">
                <div class="bbai-guide-step-icon" aria-hidden="true">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/><path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </div>
                <div class="bbai-guide-step-content">
                    <h3 class="bbai-guide-step-title"><?php esc_html_e('Scan Your Media Library', 'beepbeep-ai-alt-text-generator'); ?></h3>
                    <p class="bbai-guide-step-desc"><?php esc_html_e('Find images missing ALT text in seconds.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    <ul class="bbai-guide-step-bullets">
                        <li><?php esc_html_e('Click "Scan Media Library"', 'beepbeep-ai-alt-text-generator'); ?></li>
                        <li><?php esc_html_e('BeepBeep AI will analyze all images', 'beepbeep-ai-alt-text-generator'); ?></li>
                        <li><?php esc_html_e('Missing ALT text will appear in the ALT Library', 'beepbeep-ai-alt-text-generator'); ?></li>
                    </ul>
                    <a href="<?php echo esc_url($bbai_library_missing_url); ?>" class="bbai-btn bbai-btn-primary bbai-guide-step-cta"><?php esc_html_e('Scan Media Library', 'beepbeep-ai-alt-text-generator'); ?></a>
                </div>
            </div>
            <div class="bbai-guide-step bbai-guide-step-card">
                <div class="bbai-guide-step-icon" aria-hidden="true">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 2v4M12 18v4M2 12h4M18 12h4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </div>
                <div class="bbai-guide-step-content">
                    <h3 class="bbai-guide-step-title"><?php esc_html_e('Generate ALT Text', 'beepbeep-ai-alt-text-generator'); ?></h3>
                    <p class="bbai-guide-step-desc"><?php esc_html_e('Generate descriptive AI ALT text for missing images.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    <ul class="bbai-guide-step-bullets">
                        <li><?php esc_html_e('Select images', 'beepbeep-ai-alt-text-generator'); ?></li>
                        <li><?php esc_html_e('Click Generate ALT Text', 'beepbeep-ai-alt-text-generator'); ?></li>
                        <li><?php esc_html_e('AI will create accessible descriptions', 'beepbeep-ai-alt-text-generator'); ?></li>
                    </ul>
                </div>
            </div>
            <div class="bbai-guide-step bbai-guide-step-card">
                <div class="bbai-guide-step-icon" aria-hidden="true">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M9 11l3 3L22 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </div>
                <div class="bbai-guide-step-content">
                    <h3 class="bbai-guide-step-title"><?php esc_html_e('Review and Approve', 'beepbeep-ai-alt-text-generator'); ?></h3>
                    <p class="bbai-guide-step-desc"><?php esc_html_e('Review generated ALT text and edit if needed.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    <ul class="bbai-guide-step-bullets">
                        <li><?php esc_html_e('Open ALT Library', 'beepbeep-ai-alt-text-generator'); ?></li>
                        <li><?php esc_html_e('Review generated descriptions', 'beepbeep-ai-alt-text-generator'); ?></li>
                        <li><?php esc_html_e('Edit or regenerate anytime', 'beepbeep-ai-alt-text-generator'); ?></li>
                    </ul>
                    <a href="<?php echo esc_url($bbai_library_url); ?>" class="bbai-btn bbai-btn-secondary bbai-guide-step-cta"><?php esc_html_e('Open ALT Library', 'beepbeep-ai-alt-text-generator'); ?></a>
                </div>
            </div>
            <div class="bbai-guide-step bbai-guide-step-card">
                <div class="bbai-guide-step-icon" aria-hidden="true">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M12 6v6l4 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </div>
                <div class="bbai-guide-step-content">
                    <h3 class="bbai-guide-step-title"><?php esc_html_e('Automate Your Workflow', 'beepbeep-ai-alt-text-generator'); ?></h3>
                    <p class="bbai-guide-step-desc"><?php esc_html_e('Enable automation to keep your media library optimized.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    <ul class="bbai-guide-step-bullets">
                        <li><?php esc_html_e('Turn on auto-generation in Settings', 'beepbeep-ai-alt-text-generator'); ?></li>
                        <li><?php esc_html_e('Automatically generate ALT text for new uploads', 'beepbeep-ai-alt-text-generator'); ?></li>
                    </ul>
                    <a href="<?php echo esc_url($bbai_settings_url); ?>" class="bbai-btn bbai-btn-secondary bbai-guide-step-cta"><?php esc_html_e('Open Settings', 'beepbeep-ai-alt-text-generator'); ?></a>
                </div>
            </div>
        </div>
    </div>

    <!-- Final CTA Card -->
    <div class="bbai-card bbai-guide-cta-card bbai-page-section">
        <h2 class="bbai-guide-cta-title"><?php esc_html_e('Start Optimizing Your Images', 'beepbeep-ai-alt-text-generator'); ?></h2>
        <p class="bbai-guide-cta-desc"><?php esc_html_e('Generate ALT text for your entire media library in minutes.', 'beepbeep-ai-alt-text-generator'); ?></p>
        <a href="<?php echo esc_url($bbai_library_missing_url); ?>" class="bbai-btn bbai-btn-primary bbai-guide-cta-btn"><?php esc_html_e('Scan Media Library', 'beepbeep-ai-alt-text-generator'); ?></a>
    </div>

    <!-- Why Alt Text Matters -->
    <div class="bbai-card bbai-guide-why-card bbai-page-section">
        <h2 class="bbai-card-title"><?php esc_html_e('Why Alt Text Matters', 'beepbeep-ai-alt-text-generator'); ?></h2>
        <div class="bbai-guide-why-grid">
            <div class="bbai-guide-why-item">
                <div class="bbai-guide-why-icon bbai-guide-why-icon--seo">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M21 21L16.5 16.5M19 11C19 15.4183 15.4183 19 11 19C6.58172 19 3 15.4183 3 11C3 6.58172 6.58172 3 11 3C15.4183 3 19 6.58172 19 11Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="bbai-guide-why-text">
                    <h4><?php esc_html_e('Boost SEO Rankings', 'beepbeep-ai-alt-text-generator'); ?></h4>
                    <p><?php esc_html_e('Search engines index alt text to understand image content and improve page relevance.', 'beepbeep-ai-alt-text-generator'); ?></p>
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
                    <h4><?php esc_html_e('Google Images Traffic', 'beepbeep-ai-alt-text-generator'); ?></h4>
                    <p><?php esc_html_e('Well-described images rank higher in Google Images and visual search results.', 'beepbeep-ai-alt-text-generator'); ?></p>
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
                    <h4><?php esc_html_e('Accessibility Compliance', 'beepbeep-ai-alt-text-generator'); ?></h4>
                    <p><?php esc_html_e('Meet WCAG guidelines and make your site accessible to screen reader users.', 'beepbeep-ai-alt-text-generator'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tips & Features Side-by-Side -->
    <div class="bbai-guide-two-col bbai-page-section">
        <!-- Tips Card -->
        <div class="bbai-card bbai-guide-tips-card">
            <h2 class="bbai-card-title"><?php esc_html_e('Tips for Better Alt Text', 'beepbeep-ai-alt-text-generator'); ?></h2>
            <ul class="bbai-guide-tips-list">
                <li class="bbai-guide-tip">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span><?php esc_html_e('Keep it concise: 80-125 characters is ideal', 'beepbeep-ai-alt-text-generator'); ?></span>
                </li>
                <li class="bbai-guide-tip">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span><?php esc_html_e('Describe what matters most in the image', 'beepbeep-ai-alt-text-generator'); ?></span>
                </li>
                <li class="bbai-guide-tip">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span><?php esc_html_e('Avoid keyword stuffing or repeating page text', 'beepbeep-ai-alt-text-generator'); ?></span>
                </li>
                <li class="bbai-guide-tip">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span><?php esc_html_e('Include context: what, who, where, why', 'beepbeep-ai-alt-text-generator'); ?></span>
                </li>
            </ul>
        </div>

        <!-- Features Card -->
        <div class="bbai-card bbai-guide-features-card">
            <h2 class="bbai-card-title"><?php esc_html_e('Key Features', 'beepbeep-ai-alt-text-generator'); ?></h2>
            <ul class="bbai-guide-features-list">
                <li class="bbai-guide-feature">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M8 2L10 6L14 8L10 10L8 14L6 10L2 8L6 6L8 2Z" stroke="currentColor" stroke-width="1.5"/>
                    </svg>
                    <span><?php esc_html_e('AI-powered alt text generation', 'beepbeep-ai-alt-text-generator'); ?></span>
                </li>
                <li class="bbai-guide-feature">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <rect x="2" y="2" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M5 8H11M8 5V11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span>
                        <?php esc_html_e('Bulk processing', 'beepbeep-ai-alt-text-generator'); ?>
                        <?php if (!$bbai_is_pro) : ?>
                            <span class="bbai-badge bbai-badge--pro-sm"><?php esc_html_e('Growth', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <?php endif; ?>
                    </span>
                </li>
                <li class="bbai-guide-feature">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M8 5V8L10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span>
                        <?php esc_html_e('Tone & style tuning', 'beepbeep-ai-alt-text-generator'); ?>
                        <?php if (!$bbai_is_pro) : ?>
                            <span class="bbai-badge bbai-badge--pro-sm"><?php esc_html_e('Growth', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <?php endif; ?>
                    </span>
                </li>
                <li class="bbai-guide-feature">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M5 8H11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span>
                        <?php esc_html_e('Multilingual support', 'beepbeep-ai-alt-text-generator'); ?>
                        <?php if (!$bbai_is_pro) : ?>
                            <span class="bbai-badge bbai-badge--pro-sm"><?php esc_html_e('Growth', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <?php endif; ?>
                    </span>
                </li>
                <li class="bbai-guide-feature">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span><?php esc_html_e('SEO-optimized descriptions', 'beepbeep-ai-alt-text-generator'); ?></span>
                </li>
                <li class="bbai-guide-feature">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M8 6V8M8 10H8.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span><?php esc_html_e('WCAG accessibility tools', 'beepbeep-ai-alt-text-generator'); ?></span>
                </li>
            </ul>
        </div>
    </div>

    <div class="bbai-guide-two-col bbai-page-section">
        <div class="bbai-card bbai-guide-features-card">
            <h2 class="bbai-card-title"><?php esc_html_e('Credits & Plans', 'beepbeep-ai-alt-text-generator'); ?></h2>
            <ul class="bbai-guide-features-list">
                <li class="bbai-guide-feature">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M8 5V8L10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span><?php esc_html_e('Free includes 50 AI generations each month.', 'beepbeep-ai-alt-text-generator'); ?></span>
                </li>
                <li class="bbai-guide-feature">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M8 2L10 6L14 8L10 10L8 14L6 10L2 8L6 6L8 2Z" stroke="currentColor" stroke-width="1.5"/>
                    </svg>
                    <span><?php esc_html_e('Growth increases your monthly allowance and unlocks bulk workflows.', 'beepbeep-ai-alt-text-generator'); ?></span>
                </li>
                <li class="bbai-guide-feature">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <rect x="2" y="2" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M5 8H11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span><?php esc_html_e('Open Usage to check credits remaining, reset timing, and recent activity.', 'beepbeep-ai-alt-text-generator'); ?></span>
                </li>
            </ul>
        </div>

        <div class="bbai-card bbai-guide-features-card">
            <h2 class="bbai-card-title"><?php esc_html_e('FAQs & Troubleshooting', 'beepbeep-ai-alt-text-generator'); ?></h2>
            <ul class="bbai-guide-features-list">
                <li class="bbai-guide-feature">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span><?php esc_html_e('Run a scan first if the ALT Library looks empty or out of date.', 'beepbeep-ai-alt-text-generator'); ?></span>
                </li>
                <li class="bbai-guide-feature">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M3 8H13M8 3V13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span><?php esc_html_e('Retry generation from the ALT Library if an image description fails the first time.', 'beepbeep-ai-alt-text-generator'); ?></span>
                </li>
                <li class="bbai-guide-feature">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M8 6V8M8 10H8.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span><?php esc_html_e('Use Settings → Debug for support logs and system checks when troubleshooting.', 'beepbeep-ai-alt-text-generator'); ?></span>
                </li>
            </ul>
        </div>
    </div>

</div>

</div>



