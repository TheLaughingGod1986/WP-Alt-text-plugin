<?php
/**
 * Guide (How to) tab content.
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

$is_pro    = ($plan_slug === 'pro' || $plan_slug === 'agency');
$is_agency = ($plan_slug === 'agency');
?>
<!-- How to Use Page -->
<div class="bbai-guide-wrapper">
    <div class="bbai-guide-container">
        <!-- Header Section -->
        <div class="bbai-guide-header">
            <h1 class="bbai-guide-title"><?php esc_html_e('How to Use BeepBeep AI', 'beepbeep-ai-alt-text-generator'); ?></h1>
            <p class="bbai-guide-subtitle"><?php esc_html_e('Learn how to generate and manage alt text for your images.', 'beepbeep-ai-alt-text-generator'); ?></p>
        </div>

        <!-- Pro Features Card (LOCKED) -->
        <?php if (!$is_pro) : ?>
        <div class="bbai-guide-pro-card">
            <div class="bbai-guide-pro-ribbon">
                <?php esc_html_e('LOCKED PRO FEATURES', 'beepbeep-ai-alt-text-generator'); ?>
            </div>
            <div class="bbai-guide-pro-features">
                <div class="bbai-guide-pro-feature">
                    <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                        <circle cx="8" cy="8" r="8" fill="#16a34a"/>
                        <path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span><?php esc_html_e('Priority queue generation', 'beepbeep-ai-alt-text-generator'); ?></span>
                </div>
                <div class="bbai-guide-pro-feature">
                    <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                        <circle cx="8" cy="8" r="8" fill="#16a34a"/>
                        <path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span><?php esc_html_e('Bulk optimisation for large libraries', 'beepbeep-ai-alt-text-generator'); ?></span>
                </div>
                <div class="bbai-guide-pro-feature">
                    <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                        <circle cx="8" cy="8" r="8" fill="#16a34a"/>
                        <path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span><?php esc_html_e('Multilingual alt text', 'beepbeep-ai-alt-text-generator'); ?></span>
                </div>
                <div class="bbai-guide-pro-feature">
                    <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                        <circle cx="8" cy="8" r="8" fill="#16a34a"/>
                        <path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span><?php esc_html_e('Smart tone + style tuning', 'beepbeep-ai-alt-text-generator'); ?></span>
                </div>
                <div class="bbai-guide-pro-feature">
                    <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                        <circle cx="8" cy="8" r="8" fill="#16a34a"/>
                        <path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span><?php esc_html_e('1,000 alt text generations per month', 'beepbeep-ai-alt-text-generator'); ?></span>
                </div>
            </div>
            <div class="bbai-guide-pro-cta">
                <a href="#" class="bbai-guide-pro-link" data-action="show-upgrade-modal">
                    <?php esc_html_e('Upgrade to Pro', 'beepbeep-ai-alt-text-generator'); ?> →
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Getting Started Card -->
        <div class="bbai-guide-steps-card">
            <h2 class="bbai-guide-steps-title">
                <?php esc_html_e('Getting Started in 4 Easy Steps', 'beepbeep-ai-alt-text-generator'); ?>
            </h2>
            <div class="bbai-guide-steps-list">
                <div class="bbai-guide-step">
                    <div class="bbai-guide-step-badge">
                        <span class="bbai-guide-step-number">1</span>
                    </div>
                    <div class="bbai-guide-step-content">
                        <h3 class="bbai-guide-step-title"><?php esc_html_e('Upload Images', 'beepbeep-ai-alt-text-generator'); ?></h3>
                        <p class="bbai-guide-step-description"><?php esc_html_e('Add images to your WordPress Media Library.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                </div>
                <div class="bbai-guide-step">
                    <div class="bbai-guide-step-badge">
                        <span class="bbai-guide-step-number">2</span>
                    </div>
                    <div class="bbai-guide-step-content">
                        <h3 class="bbai-guide-step-title"><?php esc_html_e('Bulk Optimize', 'beepbeep-ai-alt-text-generator'); ?></h3>
                        <p class="bbai-guide-step-description"><?php esc_html_e('Generate alt text for multiple images at once from the Dashboard.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                </div>
                <div class="bbai-guide-step">
                    <div class="bbai-guide-step-badge">
                        <span class="bbai-guide-step-number">3</span>
                    </div>
                    <div class="bbai-guide-step-content">
                        <h3 class="bbai-guide-step-title"><?php esc_html_e('Review & Edit', 'beepbeep-ai-alt-text-generator'); ?></h3>
                        <p class="bbai-guide-step-description"><?php esc_html_e('Refine generated alt text in the ALT Library.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                </div>
                <div class="bbai-guide-step">
                    <div class="bbai-guide-step-badge">
                        <span class="bbai-guide-step-number">4</span>
                    </div>
                    <div class="bbai-guide-step-content">
                        <h3 class="bbai-guide-step-title"><?php esc_html_e('Regenerate if Needed', 'beepbeep-ai-alt-text-generator'); ?></h3>
                        <p class="bbai-guide-step-description"><?php esc_html_e('Use the regenerate feature to improve alt text quality anytime.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Why Alt Text Matters Section -->
        <div class="bbai-guide-why-card">
            <div class="bbai-guide-why-icon">💡</div>
            <h3 class="bbai-guide-why-title">
                <?php esc_html_e('Why Alt Text Matters', 'beepbeep-ai-alt-text-generator'); ?>
            </h3>
            <ul class="bbai-guide-why-list">
                <li class="bbai-guide-why-item">
                    <span class="bbai-guide-why-check">✓</span>
                    <span><?php esc_html_e('Boosts SEO visibility by up to 20%', 'beepbeep-ai-alt-text-generator'); ?></span>
                </li>
                <li class="bbai-guide-why-item">
                    <span class="bbai-guide-why-check">✓</span>
                    <span><?php esc_html_e('Improves Google Images ranking', 'beepbeep-ai-alt-text-generator'); ?></span>
                </li>
                <li class="bbai-guide-why-item">
                    <span class="bbai-guide-why-check">✓</span>
                    <span><?php esc_html_e('Helps achieve WCAG compliance for accessibility', 'beepbeep-ai-alt-text-generator'); ?></span>
                </li>
            </ul>
        </div>

        <!-- Two Column Layout -->
        <div class="bbai-guide-grid">
            <!-- Tips Card -->
            <div class="bbai-guide-card">
                <h3 class="bbai-guide-card-title">
                    <?php esc_html_e('Tips for Better Alt Text', 'beepbeep-ai-alt-text-generator'); ?>
                </h3>
                <div class="bbai-guide-tips-list">
                    <div class="bbai-guide-tip">
                        <div class="bbai-guide-tip-icon">✓</div>
                        <div class="bbai-guide-tip-content">
                            <div class="bbai-guide-tip-title"><?php esc_html_e('Keep it concise', 'beepbeep-ai-alt-text-generator'); ?></div>
                        </div>
                    </div>
                    <div class="bbai-guide-tip">
                        <div class="bbai-guide-tip-icon">✓</div>
                        <div class="bbai-guide-tip-content">
                            <div class="bbai-guide-tip-title"><?php esc_html_e('Be specific', 'beepbeep-ai-alt-text-generator'); ?></div>
                        </div>
                    </div>
                    <div class="bbai-guide-tip">
                        <div class="bbai-guide-tip-icon">✓</div>
                        <div class="bbai-guide-tip-content">
                            <div class="bbai-guide-tip-title"><?php esc_html_e('Avoid redundancy', 'beepbeep-ai-alt-text-generator'); ?></div>
                        </div>
                    </div>
                    <div class="bbai-guide-tip">
                        <div class="bbai-guide-tip-icon">✓</div>
                        <div class="bbai-guide-tip-content">
                            <div class="bbai-guide-tip-title"><?php esc_html_e('Think context', 'beepbeep-ai-alt-text-generator'); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Features Card -->
            <div class="bbai-guide-card">
                <h3 class="bbai-guide-card-title">
                    <?php esc_html_e('Key Features', 'beepbeep-ai-alt-text-generator'); ?>
                </h3>
                <div class="bbai-guide-features-list">
                    <div class="bbai-guide-feature">
                        <div class="bbai-guide-feature-icon">🤖</div>
                        <div class="bbai-guide-feature-content">
                            <div class="bbai-guide-feature-title"><?php esc_html_e('AI-Powered', 'beepbeep-ai-alt-text-generator'); ?></div>
                        </div>
                    </div>
                    <div class="bbai-guide-feature">
                        <div class="bbai-guide-feature-icon">≡</div>
                        <div class="bbai-guide-feature-content">
                            <div class="bbai-guide-feature-title">
                                <?php esc_html_e('Bulk Processing', 'beepbeep-ai-alt-text-generator'); ?>
                                <?php if (!$is_pro) : ?>
                                    <span class="bbai-guide-feature-lock">🔒</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="bbai-guide-feature">
                        <div class="bbai-guide-feature-icon">◆</div>
                        <div class="bbai-guide-feature-content">
                            <div class="bbai-guide-feature-title"><?php esc_html_e('SEO Optimized', 'beepbeep-ai-alt-text-generator'); ?></div>
                        </div>
                    </div>
                    <div class="bbai-guide-feature">
                        <div class="bbai-guide-feature-icon">🎨</div>
                        <div class="bbai-guide-feature-content">
                            <div class="bbai-guide-feature-title">
                                <?php esc_html_e('Smart tone tuning', 'beepbeep-ai-alt-text-generator'); ?>
                                <?php if (!$is_pro) : ?>
                                    <span class="bbai-guide-feature-lock">🔒</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="bbai-guide-feature">
                        <div class="bbai-guide-feature-icon">🌍</div>
                        <div class="bbai-guide-feature-content">
                            <div class="bbai-guide-feature-title">
                                <?php esc_html_e('Multilingual alt text', 'beepbeep-ai-alt-text-generator'); ?>
                                <?php if (!$is_pro) : ?>
                                    <span class="bbai-guide-feature-lock">🔒</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="bbai-guide-feature">
                        <div class="bbai-guide-feature-icon">♿</div>
                        <div class="bbai-guide-feature-content">
                            <div class="bbai-guide-feature-title"><?php esc_html_e('Accessibility', 'beepbeep-ai-alt-text-generator'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upgrade CTA Banner -->
        <?php if (!$is_agency) : ?>
        <div class="bbai-guide-cta-card">
            <h3 class="bbai-guide-cta-title">
                <span class="bbai-guide-cta-icon">⚡</span>
                <?php esc_html_e('Ready for More?', 'beepbeep-ai-alt-text-generator'); ?>
            </h3>
            <p class="bbai-guide-cta-text">
                <?php esc_html_e('Save hours each month with automated alt text generation. Upgrade for 1,000 images/month and priority processing.', 'beepbeep-ai-alt-text-generator'); ?>
            </p>
            <button type="button" class="bbai-guide-cta-btn" data-action="show-upgrade-modal">
                <span><?php esc_html_e('View Plans & Pricing', 'beepbeep-ai-alt-text-generator'); ?></span>
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                    <path d="M6 12L10 8L6 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span class="bbai-guide-cta-badge-new"><?php esc_html_e('NEW', 'beepbeep-ai-alt-text-generator'); ?></span>
            </button>
        </div>
        <?php endif; ?>
    </div>
</div><!-- End guide wrapper -->
