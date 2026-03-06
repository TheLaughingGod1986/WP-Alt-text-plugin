<?php
/**
 * Settings tab content partial.
 *
 * Expects $this (core class), $opts, and Usage_Tracker in scope.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\BeepBeepAI\AltTextGenerator\Usage_Tracker')) {
    require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
}

$bbai_is_authenticated = $this->api_client->is_authenticated();
$bbai_has_license = $this->api_client->has_active_license();
?>
<?php
// Relax gating: allow settings for anyone, but show a soft prompt if not logged in/licensed.
if (!$bbai_is_authenticated && !$bbai_has_license) :
?>
                <!-- Settings require authentication -->
                <div class="bbai-dashboard-container">
                    <div class="bbai-settings-required">
                    <div class="bbai-settings-required-content">
                        <div class="bbai-settings-required-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <rect x="4" y="10" width="16" height="12" rx="2" stroke="currentColor" stroke-width="2"/>
                                <path d="M7 10V7C7 4.23858 9.23858 2 12 2C14.7614 2 17 4.23858 17 7V10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <h2><?php esc_html_e('Authentication Required', 'beepbeep-ai-alt-text-generator'); ?></h2>
                        <p><?php esc_html_e('Settings are now available to all users. Log in to save settings to your account.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <p class="bbai-settings-required-note">
                            <?php esc_html_e('If you continue without logging in, your changes may be stored locally for this site only.', 'beepbeep-ai-alt-text-generator'); ?>
                        </p>
                        <div class="bbai-settings-required-actions">
                            <button type="button" class="bbai-btn bbai-btn-primary bbai-btn-icon" data-action="show-auth-modal" data-auth-tab="login">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                    <circle cx="8" cy="8" r="2" fill="currentColor"/>
                                </svg>
                                <span><?php esc_html_e('Log In', 'beepbeep-ai-alt-text-generator'); ?></span>
                            </button>
                            <a class="bbai-btn bbai-btn-outline-primary" href="<?php echo esc_url(add_query_arg(['tab' => 'settings'])); ?>">
                                <?php esc_html_e('Continue without login', 'beepbeep-ai-alt-text-generator'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php else : ?>
            <!-- Settings Page -->
            <div class="bbai-dashboard-container">
                <?php
                    // Pull fresh usage from backend to avoid stale cache in Settings
                    if (isset($this->api_client)) {
                        $bbai_live = $this->api_client->get_usage();
                        if (is_array($bbai_live) && !empty($bbai_live)) { \BeepBeepAI\AltTextGenerator\Usage_Tracker::update_usage($bbai_live); }
                    }
                    $bbai_usage_box = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_stats_display();
                    $bbai_o = wp_parse_args($opts, []);
                    
                    // Check for license plan first
                    $bbai_license_data = $this->api_client->get_license_data();
                
                $bbai_plan = $bbai_usage_box['plan'] ?? 'free';
                
                // If license is active, use license plan
                if ($bbai_has_license && $bbai_license_data && isset($bbai_license_data['organization'])) {
                    $bbai_license_plan = strtolower($bbai_license_data['organization']['plan'] ?? 'free');
                    if ($bbai_license_plan !== 'free') {
                        $bbai_plan = $bbai_license_plan;
                    }
                }
                
                $bbai_is_pro = ($bbai_plan === 'pro' || $bbai_plan === 'growth');
                $bbai_is_agency = $bbai_plan === 'agency';
                $bbai_is_growth_plan = ($bbai_is_pro || $bbai_is_agency);

                $bbai_plan_label = $bbai_is_growth_plan
                    ? esc_html__('Growth', 'beepbeep-ai-alt-text-generator')
                    : esc_html__('Free', 'beepbeep-ai-alt-text-generator');

                $bbai_used_credits = intval($bbai_usage_box['used'] ?? 0);
                $bbai_total_credits = intval($bbai_usage_box['limit'] ?? 0);

                $bbai_reset_label = esc_html__('Monthly', 'beepbeep-ai-alt-text-generator');
                $bbai_reset_raw = $bbai_usage_box['reset_date'] ?? '';
                $bbai_reset_timestamp = isset($bbai_usage_box['reset_timestamp']) ? intval($bbai_usage_box['reset_timestamp']) : strtotime((string) $bbai_reset_raw);
                if ($bbai_reset_timestamp !== false && $bbai_reset_timestamp > 0) {
                    $bbai_reset_label = date_i18n('F j', $bbai_reset_timestamp);
                } elseif (!empty($bbai_reset_raw) && strtolower((string) $bbai_reset_raw) !== 'monthly') {
                    $bbai_reset_label = (string) $bbai_reset_raw;
                }

                $bbai_stored_license_key = $this->api_client->get_license_key();
                $bbai_show_debug_license_key = defined('WP_DEBUG') && WP_DEBUG && !empty($bbai_stored_license_key);

                $bbai_tone_options = [
                    'Professional',
                    'SEO Optimized',
                    'Accessibility Focused',
                    'E-commerce',
                ];
                $bbai_tone_saved = (string) ($bbai_o['tone'] ?? '');
                $bbai_tone_value = 'Professional';
                if (in_array($bbai_tone_saved, $bbai_tone_options, true)) {
                    $bbai_tone_value = $bbai_tone_saved;
                } else {
                    $bbai_tone_hint = strtolower($bbai_tone_saved);
                    if (strpos($bbai_tone_hint, 'seo') !== false) {
                        $bbai_tone_value = 'SEO Optimized';
                    } elseif (strpos($bbai_tone_hint, 'access') !== false || strpos($bbai_tone_hint, 'screen') !== false) {
                        $bbai_tone_value = 'Accessibility Focused';
                    } elseif (strpos($bbai_tone_hint, 'commerce') !== false || strpos($bbai_tone_hint, 'product') !== false || strpos($bbai_tone_hint, 'shop') !== false) {
                        $bbai_tone_value = 'E-commerce';
                    }
                }
                ?>

                <!-- Header Section -->
                <div class="bbai-dashboard-header-section">
                    <h1 class="bbai-dashboard-title"><?php esc_html_e('Settings & Account', 'beepbeep-ai-alt-text-generator'); ?></h1>
                    <p class="bbai-dashboard-subtitle"><?php esc_html_e('Configure automatic alt text generation, manage your monthly quota, and track usage. Optimize settings to maximize Google Images rankings.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    <p class="bbai-dashboard-subtitle bbai-settings-disclosure">
                        <?php esc_html_e('Alt text generation and review send image data and related context to external AI services over HTTPS. The free plan includes 50 generations per month; paid plans increase your monthly limits.', 'beepbeep-ai-alt-text-generator'); ?>
                        <a href="<?php echo esc_url('https://oppti.dev/privacy'); ?>" target="_blank" rel="noopener">
                            <?php esc_html_e('Privacy Policy', 'beepbeep-ai-alt-text-generator'); ?>
                        </a>
                    </p>
                </div>
                
                <!-- Site-Wide Settings Banner -->
                <div class="bbai-settings-sitewide-banner">
                    <svg class="bbai-settings-sitewide-icon" width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <circle cx="10" cy="10" r="8" stroke="#3b82f6" stroke-width="2" fill="none"/>
                        <path d="M10 6V10M10 14H10.01" stroke="#3b82f6" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <div class="bbai-settings-sitewide-content">
                        <strong class="bbai-settings-sitewide-title"><?php esc_html_e('Site-Wide Settings', 'beepbeep-ai-alt-text-generator'); ?></strong>
                        <span class="bbai-settings-sitewide-text">
                            <?php esc_html_e('These settings apply to all users on this WordPress site.', 'beepbeep-ai-alt-text-generator'); ?>
                        </span>
                    </div>
                </div>

                <!-- Account Status Card -->
                <div class="bbai-settings-plan-summary-card">
                    <h3 class="bbai-settings-card-title"><?php esc_html_e('Account Status', 'beepbeep-ai-alt-text-generator'); ?></h3>

                    <div class="bbai-settings-plan-info">
                        <div class="bbai-settings-plan-info-item">
                            <span class="bbai-settings-account-status-label"><?php esc_html_e('Plan:', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <span class="bbai-settings-account-status-value"><?php echo esc_html($bbai_plan_label); ?></span>
                        </div>
                        <div class="bbai-settings-plan-info-item">
                            <span class="bbai-settings-account-status-label"><?php esc_html_e('Credits used:', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <span class="bbai-settings-account-status-value">
                                <?php echo esc_html(number_format_i18n($bbai_used_credits) . ' / ' . number_format_i18n($bbai_total_credits)); ?>
                            </span>
                        </div>
                        <div class="bbai-settings-plan-info-item">
                            <span class="bbai-settings-account-status-label"><?php esc_html_e('Next reset:', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <span class="bbai-settings-account-status-value"><?php echo esc_html($bbai_reset_label); ?></span>
                        </div>
                        <?php if ($bbai_show_debug_license_key) : ?>
                        <div class="bbai-settings-plan-info-item">
                            <span class="bbai-settings-account-status-label"><?php esc_html_e('License key:', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <span class="bbai-settings-account-status-value"><?php echo esc_html($bbai_stored_license_key); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!$bbai_is_growth_plan) : ?>
                    <button type="button" class="bbai-btn bbai-btn-primary bbai-btn-lg" data-action="show-upgrade-modal" data-bbai-tooltip="<?php esc_attr_e('Get 1,000 images/month and advanced features', 'beepbeep-ai-alt-text-generator'); ?>" data-bbai-tooltip-position="bottom">
                        <?php esc_html_e('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'); ?>
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Account Management Card -->
                <div class="bbai-settings-card">
                    <div class="bbai-settings-card-header">
                        <div class="bbai-settings-card-header-icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <circle cx="10" cy="6" r="4" stroke="currentColor" stroke-width="1.5"/>
                                <path d="M3 18C3 14.134 6.13401 11 10 11C13.866 11 17 14.134 17 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <h3 class="bbai-settings-card-title"><?php esc_html_e('Account Management', 'beepbeep-ai-alt-text-generator'); ?></h3>
                    </div>
                    
                    <?php if (!$bbai_is_pro && !$bbai_is_agency) : ?>
                    <div class="bbai-settings-account-info-banner">
                        <span><?php esc_html_e('Subscription tools appear here after upgrading to Growth.', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </div>
                    <?php else : ?>
                    <?php 
                    // Check if using license vs authenticated account
                    $bbai_is_authenticated_for_account = $this->api_client->is_authenticated();
                    $bbai_is_license_only = $bbai_has_license && !$bbai_is_authenticated_for_account;
                    
                    if ($bbai_is_license_only) : 
                        // License-based plan - provide contact info
                    ?>
                    <div class="bbai-settings-account-actions">
                        <div class="bbai-settings-account-action-info">
                            <p><strong><?php esc_html_e('License-Based Plan', 'beepbeep-ai-alt-text-generator'); ?></strong></p>
                            <p><?php esc_html_e('Your subscription is managed through your license. To manage billing, invoices, or update your subscription:', 'beepbeep-ai-alt-text-generator'); ?></p>
                            <ul>
                                <li><?php esc_html_e('Contact your license administrator', 'beepbeep-ai-alt-text-generator'); ?></li>
                                <li><?php esc_html_e('Email support for billing inquiries', 'beepbeep-ai-alt-text-generator'); ?></li>
                                <li><?php esc_html_e('Contact support with your site URL for license assistance', 'beepbeep-ai-alt-text-generator'); ?></li>
                            </ul>
                        </div>
                    </div>
                    <?php elseif ($bbai_is_authenticated_for_account) : 
                        // Authenticated user - show Stripe portal
                    ?>
                    <div class="bbai-settings-account-actions">
                        <button type="button" class="bbai-btn bbai-btn-secondary" data-action="manage-subscription" data-bbai-tooltip="<?php esc_attr_e('View invoices, update payment method, or modify your subscription', 'beepbeep-ai-alt-text-generator'); ?>" data-bbai-tooltip-position="bottom">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                <circle cx="8" cy="8" r="2" fill="currentColor"/>
                            </svg>
                            <span><?php esc_html_e('Manage Subscription', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </button>
                        <div class="bbai-settings-account-action-info">
                            <p><?php esc_html_e('In Stripe Customer Portal you can:', 'beepbeep-ai-alt-text-generator'); ?></p>
                            <ul>
                                <li><?php esc_html_e('View and download invoices', 'beepbeep-ai-alt-text-generator'); ?></li>
                                <li><?php esc_html_e('Update payment method', 'beepbeep-ai-alt-text-generator'); ?></li>
                                <li><?php esc_html_e('View payment history', 'beepbeep-ai-alt-text-generator'); ?></li>
                                <li><?php esc_html_e('Cancel or modify subscription', 'beepbeep-ai-alt-text-generator'); ?></li>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Settings Form -->
                <form method="post" action="options.php" autocomplete="off">
                    <?php settings_fields('bbai_group'); ?>

                    <!-- Generation Settings Card -->
                    <div class="bbai-settings-card">
                        <h3 class="bbai-settings-generation-title"><?php esc_html_e('Generation Settings', 'beepbeep-ai-alt-text-generator'); ?></h3>

                        <div class="bbai-settings-form-group">
                            <div class="bbai-settings-form-field bbai-settings-form-field--toggle">
                                <div class="bbai-settings-form-field-content">
                                    <label for="bbai-enable-on-upload" class="bbai-settings-form-label" data-bbai-tooltip="When enabled, alt text is automatically generated for every image you upload. Saves time and ensures no image is left without alt text." data-bbai-tooltip-position="right">
                                        <?php esc_html_e('Auto-generate alt text on image upload', 'beepbeep-ai-alt-text-generator'); ?>
                                    </label>
                                    <p class="bbai-settings-form-description">
                                        <?php esc_html_e('Automatically generate alt text whenever new images are uploaded to the WordPress media library.', 'beepbeep-ai-alt-text-generator'); ?>
                                    </p>
                                </div>
                                <label class="bbai-settings-toggle">
                                    <input 
                                        type="checkbox" 
                                        id="bbai-enable-on-upload"
                                        name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_on_upload]" 
                                        value="1"
                                        <?php checked(!empty($bbai_o['enable_on_upload'] ?? true)); ?>
                                    >
                                    <span class="bbai-settings-toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="bbai-settings-form-group">
                            <label for="bbai-tone" class="bbai-settings-form-label" data-bbai-tooltip="Choose a style profile for generated alt text. This applies to all new AI descriptions." data-bbai-tooltip-position="right">
                                <?php esc_html_e('AI Description Style', 'beepbeep-ai-alt-text-generator'); ?>
                            </label>
                            <select
                                id="bbai-tone"
                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[tone]"
                                class="bbai-settings-form-input bbai-select"
                            >
                                <option value="Professional" <?php selected($bbai_tone_value, 'Professional'); ?>>
                                    <?php esc_html_e('Professional', 'beepbeep-ai-alt-text-generator'); ?>
                                </option>
                                <option value="SEO Optimized" <?php selected($bbai_tone_value, 'SEO Optimized'); ?>>
                                    <?php esc_html_e('SEO Optimized', 'beepbeep-ai-alt-text-generator'); ?>
                                </option>
                                <option value="Accessibility Focused" <?php selected($bbai_tone_value, 'Accessibility Focused'); ?>>
                                    <?php esc_html_e('Accessibility Focused', 'beepbeep-ai-alt-text-generator'); ?>
                                </option>
                                <option value="E-commerce" <?php selected($bbai_tone_value, 'E-commerce'); ?>>
                                    <?php esc_html_e('E-commerce', 'beepbeep-ai-alt-text-generator'); ?>
                                </option>
                            </select>
                        </div>

                        <div class="bbai-settings-form-group">
                            <label for="bbai-custom-prompt" class="bbai-settings-form-label" data-bbai-tooltip="Add specific instructions for the AI. Example: 'Always mention brand colors', 'Focus on product features', 'Include emotional context'. These instructions apply to every image." data-bbai-tooltip-position="right">
                                <?php esc_html_e('Additional Instructions', 'beepbeep-ai-alt-text-generator'); ?>
                            </label>
                            <p class="bbai-settings-form-description">
                                <?php esc_html_e('Example: "Focus on accessibility and clarity for screen readers."', 'beepbeep-ai-alt-text-generator'); ?>
                            </p>
                            <textarea
                                id="bbai-custom-prompt"
                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[custom_prompt]"
                                rows="4"
                                placeholder="<?php esc_attr_e('Enter any specific instructions for the AI...', 'beepbeep-ai-alt-text-generator'); ?>"
                                class="bbai-settings-form-textarea"
                            ><?php echo esc_textarea($bbai_o['custom_prompt'] ?? ''); ?></textarea>
                        </div>

                        <div class="bbai-settings-form-actions">
                            <button type="submit" class="bbai-btn bbai-btn-primary">
                                <?php esc_html_e('Save Settings', 'beepbeep-ai-alt-text-generator'); ?>
                            </button>
                        </div>
                    </div>
                </form>

            </div>
            <?php endif; // End if/else for authentication check in settings tab ?>
    
    <!-- Bottom Upsell CTA (reusable component) -->
    <?php
    // Set plan variables for bottom CTA component
    $bbai_is_free = ($bbai_plan === 'free');
    $bbai_is_growth = ($bbai_plan === 'pro' || $bbai_plan === 'growth');
    $bbai_is_agency = ($bbai_plan === 'agency');
    
    $bbai_bottom_upsell_partial = plugin_dir_path( BBAI_PLUGIN_FILE ) . 'admin/partials/bottom-upsell-cta.php';
    if (file_exists($bbai_bottom_upsell_partial)) {
        include $bbai_bottom_upsell_partial;
    }
    ?>
