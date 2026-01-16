<?php
/**
 * Onboarding Modal Template
 * Welcome modal with interactive tour for first-time users
 *
 * @package BeepBeep_AI
 * @since 6.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if onboarding is completed
$user_id = get_current_user_id();
$onboarding_completed = false;
if (class_exists('\BeepBeepAI\AltTextGenerator\Onboarding')) {
    $onboarding_completed = \BeepBeepAI\AltTextGenerator\Onboarding::is_completed($user_id);
}

// Only show if not completed
if ($onboarding_completed) {
    return;
}
?>

<div id="bbai-onboarding-modal" class="bbai-onboarding-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="bbai-onboarding-title">
    <div class="bbai-onboarding-modal-overlay"></div>
    <div class="bbai-onboarding-modal-content">
        <!-- Step 1: Welcome -->
        <div class="bbai-onboarding-step bbai-onboarding-step--active" data-step="1">
            <div class="bbai-onboarding-header">
                <div class="bbai-onboarding-icon">
                    <svg width="64" height="64" viewBox="0 0 64 64" fill="none">
                        <circle cx="32" cy="32" r="30" fill="url(#bbai-onboarding-gradient)" stroke="white" stroke-width="2"/>
                        <path d="M20 32l8 8 16-16" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                        <defs>
                            <linearGradient id="bbai-onboarding-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:#10B981;stop-opacity:1" />
                                <stop offset="100%" style="stop-color:#059669;stop-opacity:1" />
                            </linearGradient>
                        </defs>
                    </svg>
                </div>
                <h2 class="bbai-onboarding-title" id="bbai-onboarding-title">
                    <?php esc_html_e('Welcome to BeepBeep AI!', 'beepbeep-ai-alt-text-generator'); ?>
                </h2>
                <p class="bbai-onboarding-subtitle">
                    <?php esc_html_e('Let\'s get you started with automated alt text generation in 3 quick steps.', 'beepbeep-ai-alt-text-generator'); ?>
                </p>
            </div>
            <div class="bbai-onboarding-body">
                <div class="bbai-onboarding-features">
                    <div class="bbai-onboarding-feature">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="10" stroke="#10B981" stroke-width="2"/>
                            <path d="M8 12l2 2 4-4" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span><?php esc_html_e('AI-powered alt text generation', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </div>
                    <div class="bbai-onboarding-feature">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="10" stroke="#10B981" stroke-width="2"/>
                            <path d="M8 12l2 2 4-4" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span><?php esc_html_e('WCAG-compliant descriptions', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </div>
                    <div class="bbai-onboarding-feature">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="10" stroke="#10B981" stroke-width="2"/>
                            <path d="M8 12l2 2 4-4" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span><?php esc_html_e('SEO-optimized for Google Images', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </div>
                </div>
            </div>
            <div class="bbai-onboarding-footer">
                <button type="button" class="bbai-btn-secondary" data-onboarding-action="skip">
                    <?php esc_html_e('Skip tour', 'beepbeep-ai-alt-text-generator'); ?>
                </button>
                <button type="button" class="bbai-btn-primary" data-onboarding-action="next">
                    <?php esc_html_e('Get Started', 'beepbeep-ai-alt-text-generator'); ?>
                </button>
            </div>
        </div>

        <!-- Step 2: Quick Start Checklist -->
        <div class="bbai-onboarding-step" data-step="2">
            <div class="bbai-onboarding-header">
                <h2 class="bbai-onboarding-title">
                    <?php esc_html_e('Quick Start Checklist', 'beepbeep-ai-alt-text-generator'); ?>
                </h2>
                <p class="bbai-onboarding-subtitle">
                    <?php esc_html_e('Follow these steps to start generating alt text:', 'beepbeep-ai-alt-text-generator'); ?>
                </p>
            </div>
            <div class="bbai-onboarding-body">
                <div class="bbai-onboarding-checklist">
                    <div class="bbai-onboarding-checklist-item" data-checklist-step="connect">
                        <div class="bbai-onboarding-checklist-number">1</div>
                        <div class="bbai-onboarding-checklist-content">
                            <h3><?php esc_html_e('Connect Your Account', 'beepbeep-ai-alt-text-generator'); ?></h3>
                            <p><?php esc_html_e('Log in or create a free account to get started with 50 AI alt text generations per month.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        </div>
                    </div>
                    <div class="bbai-onboarding-checklist-item" data-checklist-step="upload">
                        <div class="bbai-onboarding-checklist-number">2</div>
                        <div class="bbai-onboarding-checklist-content">
                            <h3><?php esc_html_e('Upload Images', 'beepbeep-ai-alt-text-generator'); ?></h3>
                            <p><?php esc_html_e('Add images to your WordPress Media Library. BeepBeep AI will help you generate alt text for them.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        </div>
                    </div>
                    <div class="bbai-onboarding-checklist-item" data-checklist-step="generate">
                        <div class="bbai-onboarding-checklist-number">3</div>
                        <div class="bbai-onboarding-checklist-content">
                            <h3><?php esc_html_e('Generate Alt Text', 'beepbeep-ai-alt-text-generator'); ?></h3>
                            <p><?php esc_html_e('Click "Generate Missing Alt Text" to automatically create SEO-optimized descriptions for your images.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bbai-onboarding-footer">
                <button type="button" class="bbai-btn-secondary" data-onboarding-action="prev">
                    <?php esc_html_e('Back', 'beepbeep-ai-alt-text-generator'); ?>
                </button>
                <button type="button" class="bbai-btn-primary" data-onboarding-action="next">
                    <?php esc_html_e('Next', 'beepbeep-ai-alt-text-generator'); ?>
                </button>
            </div>
        </div>

        <!-- Step 3: Key Features -->
        <div class="bbai-onboarding-step" data-step="3">
            <div class="bbai-onboarding-header">
                <h2 class="bbai-onboarding-title">
                    <?php esc_html_e('Key Features', 'beepbeep-ai-alt-text-generator'); ?>
                </h2>
                <p class="bbai-onboarding-subtitle">
                    <?php esc_html_e('Here\'s what you can do with BeepBeep AI:', 'beepbeep-ai-alt-text-generator'); ?>
                </p>
            </div>
            <div class="bbai-onboarding-body">
                <div class="bbai-onboarding-features-grid">
                    <div class="bbai-onboarding-feature-card">
                        <div class="bbai-onboarding-feature-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                                <rect x="3" y="3" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                                <path d="M9 9h6M9 15h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <h3><?php esc_html_e('Bulk Processing', 'beepbeep-ai-alt-text-generator'); ?></h3>
                        <p><?php esc_html_e('Generate alt text for multiple images at once.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                    <div class="bbai-onboarding-feature-card">
                        <div class="bbai-onboarding-feature-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                                <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <h3><?php esc_html_e('Auto-Generation', 'beepbeep-ai-alt-text-generator'); ?></h3>
                        <p><?php esc_html_e('Automatically generate alt text when you upload images.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                    <div class="bbai-onboarding-feature-card">
                        <div class="bbai-onboarding-feature-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                                <path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
                                <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <h3><?php esc_html_e('Quality Control', 'beepbeep-ai-alt-text-generator'); ?></h3>
                        <p><?php esc_html_e('Review and edit generated alt text before applying.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                </div>
            </div>
            <div class="bbai-onboarding-footer">
                <button type="button" class="bbai-btn-secondary" data-onboarding-action="prev">
                    <?php esc_html_e('Back', 'beepbeep-ai-alt-text-generator'); ?>
                </button>
                <button type="button" class="bbai-btn-primary" data-onboarding-action="complete">
                    <?php esc_html_e('Start Using BeepBeep AI', 'beepbeep-ai-alt-text-generator'); ?>
                </button>
            </div>
        </div>

        <!-- Progress Indicator -->
        <div class="bbai-onboarding-progress">
            <div class="bbai-onboarding-progress-bar">
                <div class="bbai-onboarding-progress-fill" style="width: 33.33%;"></div>
            </div>
            <div class="bbai-onboarding-progress-steps">
                <span class="bbai-onboarding-progress-step bbai-onboarding-progress-step--active">1</span>
                <span class="bbai-onboarding-progress-step">2</span>
                <span class="bbai-onboarding-progress-step">3</span>
            </div>
        </div>
    </div>
</div>
