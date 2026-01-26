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

$is_authenticated = $this->api_client->is_authenticated();
$has_license = $this->api_client->has_active_license();
?>
<?php
// Relax gating: allow settings for anyone, but show a soft prompt if not logged in/licensed.
if (!$is_authenticated && !$has_license) :
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
                        $live = $this->api_client->get_usage();
                        if (is_array($live) && !empty($live)) { \BeepBeepAI\AltTextGenerator\Usage_Tracker::update_usage($live); }
                    }
                    $usage_box = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_stats_display();
                    $o = wp_parse_args($opts, []);
                    
                    // Check for license plan first
                    $license_data = $this->api_client->get_license_data();
                
                $plan = $usage_box['plan'] ?? 'free';
                
                // If license is active, use license plan
                if ($has_license && $license_data && isset($license_data['organization'])) {
                    $license_plan = strtolower($license_data['organization']['plan'] ?? 'free');
                    if ($license_plan !== 'free') {
                        $plan = $license_plan;
                    }
                }
                
                $is_pro = $plan === 'pro';
                $is_agency = $plan === 'agency';
                $usage_percent = $usage_box['limit'] > 0 ? ($usage_box['used'] / $usage_box['limit'] * 100) : 0;

                // Determine plan badge text
                if ($is_agency) {
                    $plan_badge_text = esc_html__('AGENCY', 'beepbeep-ai-alt-text-generator');
                } elseif ($is_pro) {
                    $plan_badge_text = esc_html__('PRO', 'beepbeep-ai-alt-text-generator');
                } else {
                    $plan_badge_text = esc_html__('FREE', 'beepbeep-ai-alt-text-generator');
                }
                ?>

                <!-- Header Section -->
                <div class="bbai-dashboard-header-section">
                    <h1 class="bbai-dashboard-title"><?php esc_html_e('Settings & Account', 'beepbeep-ai-alt-text-generator'); ?></h1>
                    <p class="bbai-dashboard-subtitle"><?php esc_html_e('Configure automatic alt text generation, manage your monthly quota, and track usage. Optimize settings to maximize Google Images rankings.', 'beepbeep-ai-alt-text-generator'); ?></p>
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

                <!-- Plan Summary Card -->
                <div class="bbai-settings-plan-summary-card">
                    <div class="bbai-settings-plan-badge-top">
                        <span class="bbai-settings-plan-badge-text"><?php echo esc_html($plan_badge_text); ?></span>
                    </div>
                    <div class="bbai-settings-plan-quota">
                        <div class="bbai-settings-plan-quota-meter">
                            <span class="bbai-settings-plan-quota-used"><?php echo esc_html($usage_box['used']); ?></span>
                            <span class="bbai-settings-plan-quota-divider">/</span>
                            <span class="bbai-settings-plan-quota-limit"><?php echo esc_html($usage_box['limit']); ?></span>
                        </div>
                        <div class="bbai-settings-plan-quota-label">
                            <?php esc_html_e('image descriptions', 'beepbeep-ai-alt-text-generator'); ?>
                    </div>
                    </div>
                    <div class="bbai-settings-plan-info">
                        <?php if (!$is_pro && !$is_agency) : ?>
                            <div class="bbai-settings-plan-info-item">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                    <path d="M8 4V8L10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                </svg>
                                <span>
                                    <?php
                                    if (isset($usage_box['reset_date']) && !empty($usage_box['reset_date'])) {
                                        // Ensure consistent date format - parse and reformat if needed
                                        $reset_date = $usage_box['reset_date'];
                                        $reset_timestamp = isset($usage_box['reset_timestamp']) ? $usage_box['reset_timestamp'] : strtotime($reset_date);
                                        if ($reset_timestamp !== false && $reset_timestamp > 0) {
                                            $formatted_date = date_i18n('F j, Y', $reset_timestamp);
                                        } else {
                                            $formatted_date = $reset_date; // Fallback to original if parsing fails
                                        }
                                        printf(
                                            esc_html__('Resets %s', 'beepbeep-ai-alt-text-generator'),
                                            '<strong>' . esc_html($formatted_date) . '</strong>'
                                        );
                                    } else {
                                        esc_html_e('Monthly quota', 'beepbeep-ai-alt-text-generator');
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="bbai-settings-plan-info-item">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                    <circle cx="8" cy="8" r="2" fill="currentColor"/>
                                </svg>
                                <span><?php esc_html_e('Shared across all users', 'beepbeep-ai-alt-text-generator'); ?></span>
                            </div>
                        <?php elseif ($is_agency) : ?>
                            <div class="bbai-settings-plan-info-item">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('Multi-site license', 'beepbeep-ai-alt-text-generator'); ?></span>
                            </div>
                            <div class="bbai-settings-plan-info-item">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php 
                                    $reset_date = $usage_box['reset_date'] ?? '';
                                    if (!empty($reset_date) && $reset_date !== 'Monthly') {
                                        // Ensure consistent date format - parse and reformat if needed
                                        $reset_timestamp = isset($usage_box['reset_timestamp']) ? $usage_box['reset_timestamp'] : strtotime($reset_date);
                                        if ($reset_timestamp !== false && $reset_timestamp > 0) {
                                            $formatted_date = date_i18n('F j, Y', $reset_timestamp);
                                        } else {
                                            $formatted_date = $reset_date; // Fallback to original if parsing fails
                                        }
                                        echo sprintf(esc_html__('Resets %s', 'beepbeep-ai-alt-text-generator'), '<strong>' . esc_html($formatted_date) . '</strong>');
                                    } else {
                                        esc_html_e('Monthly quota', 'beepbeep-ai-alt-text-generator');
                                    }
                                ?></span>
                            </div>
                        <?php else : ?>
                            <div class="bbai-settings-plan-info-item">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('1,000 generations per month', 'beepbeep-ai-alt-text-generator'); ?></span>
                            </div>
                            <div class="bbai-settings-plan-info-item">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('Priority support', 'beepbeep-ai-alt-text-generator'); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!$is_pro && !$is_agency) : ?>
                    <button type="button" class="bbai-btn bbai-btn-primary bbai-btn-lg" data-action="show-upgrade-modal" data-bbai-tooltip="<?php esc_attr_e('Get 1,000 images/month and advanced features', 'beepbeep-ai-alt-text-generator'); ?>" data-bbai-tooltip-position="bottom">
                        <?php esc_html_e('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'); ?>
                    </button>
                    <?php endif; ?>
                </div>

                <!-- License Management Card -->
                <?php
                // Reuse license variables if already set above
                if (!isset($has_license)) {
                    $has_license = $this->api_client->has_active_license();
                    $license_data = $this->api_client->get_license_data();
                }
                
                // Also check if license key exists (even if data not fully loaded)
                $stored_license_key = $this->api_client->get_license_key();
                $has_stored_license_key = !empty($stored_license_key);
                ?>

                <div class="bbai-settings-card">
                    <div class="bbai-settings-card-header">
                        <div class="bbai-settings-card-header-icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <circle cx="7" cy="7" r="4" stroke="currentColor" stroke-width="1.5"/>
                                <path d="M10 10L18 18M18 18V14M18 18H14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <h3 class="bbai-settings-card-title"><?php esc_html_e('License', 'beepbeep-ai-alt-text-generator'); ?></h3>
                    </div>

                    <?php if ($has_license && $license_data) : ?>
                        <?php
                        $org = $license_data['organization'] ?? null;
                        if ($org) :
                        ?>
                        <!-- Active License Display -->
                        <div class="bbai-settings-license-active">
                            <div class="bbai-settings-license-status">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                    <circle cx="10" cy="10" r="8" fill="#10b981" opacity="0.1"/>
                                    <path d="M6 10L9 13L14 7" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <div>
                                    <div class="bbai-settings-license-title"><?php esc_html_e('License Active', 'beepbeep-ai-alt-text-generator'); ?></div>
                                    <div class="bbai-settings-license-subtitle"><?php echo esc_html($org['name'] ?? ''); ?></div>
                                    <?php 
                                    // Display license key for Growth and Agency users
                                    $license_key = $this->api_client->get_license_key();
                                    if (!empty($license_key)) :
                                        $license_plan = strtolower($org['plan'] ?? 'free');
                                        if ($license_plan === 'pro' || $license_plan === 'agency') :
                                    ?>
                                    <div class="bbai-settings-license-key bbai-license-key-display">
                                        <strong><?php esc_html_e('License Key:', 'beepbeep-ai-alt-text-generator'); ?></strong> <?php echo esc_html($license_key); ?>
                                    </div>
                                    <?php
                                        endif;
                                    endif;
                                    ?>
                                </div>
                            </div>
                            <button type="button" class="bbai-btn bbai-btn-danger" data-action="deactivate-license" data-bbai-tooltip="<?php esc_attr_e('Remove license from this site', 'beepbeep-ai-alt-text-generator'); ?>" data-bbai-tooltip-position="top">
                                <?php esc_html_e('Deactivate', 'beepbeep-ai-alt-text-generator'); ?>
                            </button>
                        </div>

                        <?php
                        // Show site usage for agency licenses (can use license key or JWT auth)
                        $is_authenticated = $this->api_client->is_authenticated();
                        $is_agency_license = isset($license_data['organization']['plan']) && $license_data['organization']['plan'] === 'agency';

                        // Show for agency licenses with either JWT auth or license key
                        if ($is_agency_license && ($is_authenticated || $has_license)) :
                        ?>
                        <!-- License Site Usage Section -->
                        <div class="bbai-settings-license-sites" id="bbai-license-sites">
                            <div class="bbai-settings-license-sites-header">
                                <h3 class="bbai-settings-license-sites-title">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="bbai-mr-2">
                                        <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                        <circle cx="8" cy="8" r="2" fill="currentColor"/>
                                    </svg>
                                    <?php esc_html_e('Sites Using This License', 'beepbeep-ai-alt-text-generator'); ?>
                                </h3>
                            </div>
                            <div class="bbai-settings-license-sites-content" id="bbai-license-sites-content">
                                <div class="bbai-settings-license-sites-loading">
                                    <span class="bbai-spinner"></span>
                                    <?php esc_html_e('Loading site usage...', 'beepbeep-ai-alt-text-generator'); ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php endif; ?>
                        
                    <?php elseif ($has_stored_license_key) : ?>
                        <!-- License Key Exists But Data Not Loaded -->
                        <div class="bbai-settings-license-active">
                            <div class="bbai-settings-license-status">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                    <circle cx="10" cy="10" r="8" fill="#10b981" opacity="0.1"/>
                                    <path d="M6 10L9 13L14 7" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <div>
                                    <div class="bbai-settings-license-title"><?php esc_html_e('License Active', 'beepbeep-ai-alt-text-generator'); ?></div>
                                    <div class="bbai-settings-license-subtitle"><?php esc_html_e('Your license key is active and ready to use.', 'beepbeep-ai-alt-text-generator'); ?></div>
                                    <div class="bbai-settings-license-key bbai-license-key-display">
                                        <strong><?php esc_html_e('License Key:', 'beepbeep-ai-alt-text-generator'); ?></strong> <?php echo esc_html($stored_license_key); ?>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="bbai-btn bbai-btn-danger" data-action="deactivate-license" data-bbai-tooltip="<?php esc_attr_e('Remove license from this site', 'beepbeep-ai-alt-text-generator'); ?>" data-bbai-tooltip-position="top">
                                <?php esc_html_e('Deactivate', 'beepbeep-ai-alt-text-generator'); ?>
                            </button>
                        </div>
                    <?php else : ?>
                        <!-- License Activation Form -->
                        <div class="bbai-settings-license-form">
                            <p class="bbai-settings-license-description">
                                <?php esc_html_e('Enter your license key to activate this site. Agency licenses can be used across multiple sites.', 'beepbeep-ai-alt-text-generator'); ?>
                            </p>
                            <form id="license-activation-form">
                                <div class="bbai-settings-license-input-group">
                                    <label for="license-key-input" class="bbai-settings-license-label">
                                        <?php esc_html_e('License Key', 'beepbeep-ai-alt-text-generator'); ?>
                                    </label>
                                    <input type="text"
                                           id="license-key-input"
                                           name="license_key"
                                           class="bbai-settings-license-input"
                                           placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                                           pattern="[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"
                                           required>
                                </div>
                                <div id="license-activation-status" class="bbai-hidden bbai-alert"></div>
                                <button type="submit" id="activate-license-btn" class="bbai-btn bbai-btn-primary" data-bbai-tooltip="<?php esc_attr_e('Activate your Growth or Agency license on this site', 'beepbeep-ai-alt-text-generator'); ?>" data-bbai-tooltip-position="right">
                                    <?php esc_html_e('Activate License', 'beepbeep-ai-alt-text-generator'); ?>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Hidden nonce for AJAX requests -->
                <input type="hidden" id="license-nonce" value="<?php echo esc_attr(wp_create_nonce('beepbeepai_nonce')); ?>">

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
                    
                    <?php if (!$is_pro && !$is_agency) : ?>
                    <div class="bbai-settings-account-info-banner">
                        <span><?php esc_html_e('You are on the free plan.', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </div>
                    <div class="bbai-settings-account-upgrade-link">
                        <button type="button" class="bbai-btn bbai-btn-primary bbai-btn-lg" data-action="show-upgrade-modal" data-bbai-tooltip="<?php esc_attr_e('Get 1,000 images/month and advanced features', 'beepbeep-ai-alt-text-generator'); ?>" data-bbai-tooltip-position="bottom">
                            <?php esc_html_e('Upgrade Now', 'beepbeep-ai-alt-text-generator'); ?>
                        </button>
                    </div>
                    <?php else : ?>
                    <div class="bbai-settings-account-status">
                        <span class="bbai-settings-account-status-label"><?php esc_html_e('Current Plan:', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <span class="bbai-settings-account-status-value"><?php 
                            if ($is_agency) {
                                esc_html_e('Agency', 'beepbeep-ai-alt-text-generator');
                            } else {
                                esc_html_e('Growth', 'beepbeep-ai-alt-text-generator');
                            }
                        ?></span>
                    </div>
                    <?php 
                    // Check if using license vs authenticated account
                    $is_authenticated_for_account = $this->api_client->is_authenticated();
                    $is_license_only = $has_license && !$is_authenticated_for_account;
                    
                    if ($is_license_only) : 
                        // License-based plan - provide contact info
                    ?>
                    <div class="bbai-settings-account-actions">
                        <div class="bbai-settings-account-action-info">
                            <p><strong><?php esc_html_e('License-Based Plan', 'beepbeep-ai-alt-text-generator'); ?></strong></p>
                            <p><?php esc_html_e('Your subscription is managed through your license. To manage billing, invoices, or update your subscription:', 'beepbeep-ai-alt-text-generator'); ?></p>
                            <ul>
                                <li><?php esc_html_e('Contact your license administrator', 'beepbeep-ai-alt-text-generator'); ?></li>
                                <li><?php esc_html_e('Email support for billing inquiries', 'beepbeep-ai-alt-text-generator'); ?></li>
                                <li><?php esc_html_e('View license details in the License section above', 'beepbeep-ai-alt-text-generator'); ?></li>
                            </ul>
                        </div>
                    </div>
                    <?php elseif ($is_authenticated_for_account) : 
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
                                        <?php esc_html_e('Auto-generate on Image Upload', 'beepbeep-ai-alt-text-generator'); ?>
                                    </label>
                                    <p class="bbai-settings-form-description">
                                        <?php esc_html_e('Automatically generate alt text when new images are uploaded to your media library.', 'beepbeep-ai-alt-text-generator'); ?>
                                    </p>
                                </div>
                                <label class="bbai-settings-toggle">
                                    <input 
                                        type="checkbox" 
                                        id="bbai-enable-on-upload"
                                        name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_on_upload]" 
                                        value="1"
                                        <?php checked(!empty($o['enable_on_upload'] ?? true)); ?>
                                    >
                                    <span class="bbai-settings-toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="bbai-settings-form-group">
                            <label for="bbai-tone" class="bbai-settings-form-label" data-bbai-tooltip="Customize how the AI writes alt text. Examples: 'casual and friendly', 'technical and detailed', 'simple and concise'. Affects all generated descriptions." data-bbai-tooltip-position="right">
                                <?php esc_html_e('Tone & Style', 'beepbeep-ai-alt-text-generator'); ?>
                            </label>
                            <input
                                type="text"
                                id="bbai-tone"
                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[tone]"
                                value="<?php echo esc_attr($o['tone'] ?? 'professional, accessible'); ?>"
                                placeholder="<?php esc_attr_e('professional, accessible', 'beepbeep-ai-alt-text-generator'); ?>"
                                class="bbai-settings-form-input"
                            />
                        </div>

                        <div class="bbai-settings-form-group">
                            <label for="bbai-custom-prompt" class="bbai-settings-form-label" data-bbai-tooltip="Add specific instructions for the AI. Example: 'Always mention brand colors', 'Focus on product features', 'Include emotional context'. These instructions apply to every image." data-bbai-tooltip-position="right">
                                <?php esc_html_e('Additional Instructions', 'beepbeep-ai-alt-text-generator'); ?>
                            </label>
                            <textarea
                                id="bbai-custom-prompt"
                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[custom_prompt]"
                                rows="4"
                                placeholder="<?php esc_attr_e('Enter any specific instructions for the AI...', 'beepbeep-ai-alt-text-generator'); ?>"
                                class="bbai-settings-form-textarea"
                            ><?php echo esc_textarea($o['custom_prompt'] ?? ''); ?></textarea>
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
    $is_free = ($plan === 'free');
    $is_growth = ($plan === 'pro' || $plan === 'growth');
    $is_agency = ($plan === 'agency');
    
    $bottom_upsell_partial = dirname(__FILE__) . '/bottom-upsell-cta.php';
    if (file_exists($bottom_upsell_partial)) {
        include $bottom_upsell_partial;
    }
    ?>
