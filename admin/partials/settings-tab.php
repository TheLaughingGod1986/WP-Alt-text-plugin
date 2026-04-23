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
if (!class_exists('\BeepBeepAI\AltTextGenerator\Auth_State')) {
    require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-auth-state.php';
}
if (!class_exists('\BeepBeepAI\AltTextGenerator\Services\Usage_Helper')) {
    require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-usage-helper.php';
}

$bbai_is_authenticated = $this->api_client->is_authenticated();
$bbai_has_license = $this->api_client->has_active_license();
$bbai_can_show_debug_section = isset($bbai_can_show_debug_tab)
    ? (bool) $bbai_can_show_debug_tab
    : ((defined('WP_DEBUG') && WP_DEBUG) || current_user_can('manage_options'));
$bbai_settings_section = isset($bbai_settings_section) && 'debug' === (string) $bbai_settings_section && $bbai_can_show_debug_section
    ? 'debug'
    : 'general';
$bbai_settings_general_url = admin_url('admin.php?page=bbai-settings');
$bbai_settings_debug_url = add_query_arg(
    [
        'page'    => 'bbai-settings',
        'section' => 'debug',
    ],
    admin_url('admin.php')
);
?>
<?php
// Relax gating: allow settings for anyone, but show a soft prompt if not logged in/licensed.
if (!$bbai_is_authenticated && !$bbai_has_license) :
?>
                <!-- Settings Control Panel (unauthenticated) -->
                <div class="bbai-container bbai-settings-page bbai-settings-page--gated">

                    <?php if ($bbai_can_show_debug_section) : ?>
                    <div class="bbai-settings-section-nav bbai-page-section" role="navigation" aria-label="<?php esc_attr_e('Settings sections', 'beepbeep-ai-alt-text-generator'); ?>">
                        <a href="<?php echo esc_url($bbai_settings_general_url); ?>" class="bbai-settings-section-link<?php echo 'general' === $bbai_settings_section ? ' active' : ''; ?>"<?php echo 'general' === $bbai_settings_section ? ' aria-current="page"' : ''; ?>>
                            <?php esc_html_e('General', 'beepbeep-ai-alt-text-generator'); ?>
                        </a>
                        <a href="<?php echo esc_url($bbai_settings_debug_url); ?>" class="bbai-settings-section-link<?php echo 'debug' === $bbai_settings_section ? ' active' : ''; ?>"<?php echo 'debug' === $bbai_settings_section ? ' aria-current="page"' : ''; ?>>
                            <?php esc_html_e('Debug', 'beepbeep-ai-alt-text-generator'); ?>
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php if ('debug' === $bbai_settings_section) : ?>
                    <?php
                    $bbai_debug_embedded = true;
                    $bbai_debug_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/debug-tab.php';
                    if (file_exists($bbai_debug_partial)) {
                        include $bbai_debug_partial;
                    } else {
                        esc_html_e('Debug content unavailable.', 'beepbeep-ai-alt-text-generator');
                    }
                    ?>
                    <?php else : ?>
                    <!-- Settings Control Panel: hero + 2-column feature grid -->

                    <!-- Hero -->
                    <div class="bbai-hero bbai-hero--neutral bbai-sg-hero">
                        <div class="bbai-sg-hero__text">
                            <h1 class="bbai-sg-hero__title"><?php esc_html_e('Control how BeepBeep AI writes your ALT text', 'beepbeep-ai-alt-text-generator'); ?></h1>
                            <p class="bbai-sg-hero__desc"><?php esc_html_e('Tune the tone, length, and style for every image. Log in to save your preferences across all your sites.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        </div>
                        <div class="bbai-sg-hero__actions">
                            <button type="button" class="bbai-btn bbai-btn-primary" data-action="show-auth-modal" data-auth-tab="login">
                                <?php esc_html_e('Log in', 'beepbeep-ai-alt-text-generator'); ?>
                            </button>
                            <a class="bbai-sg-hero__skip" href="<?php echo esc_url(add_query_arg(['tab' => 'settings'])); ?>">
                                <?php esc_html_e('Continue with local settings', 'beepbeep-ai-alt-text-generator'); ?>
                            </a>
                        </div>
                    </div><!-- /.bbai-sg-hero -->

                    <div class="bbai-metrics bbai-page-section" aria-label="<?php esc_attr_e('Settings availability', 'beepbeep-ai-alt-text-generator'); ?>">
                        <div>
                            <strong><?php esc_html_e('Local controls visible', 'beepbeep-ai-alt-text-generator'); ?></strong>
                            <span><?php esc_html_e('You can review all settings before login.', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                        <div>
                            <strong><?php esc_html_e('Cloud sync locked', 'beepbeep-ai-alt-text-generator'); ?></strong>
                            <span><?php esc_html_e('Log in to sync preferences across sites.', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                        <div>
                            <strong><?php esc_html_e('Automation available on Growth', 'beepbeep-ai-alt-text-generator'); ?></strong>
                            <span><?php esc_html_e('Enable upload automation once upgraded.', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                    </div>

                    <!-- 2-column grid -->
                    <div class="bbai-sg-grid">

                        <!-- LEFT: ALT Generation + Advanced -->
                        <div class="bbai-sg-main">

                            <!-- ALT Generation section -->
                            <div class="bbai-card bbai-sg-section">
                                <div class="bbai-sg-section__head bbai-section-header bbai-ui-section-header">
                                    <div class="bbai-ui-section-header__text">
                                    <h2 class="bbai-sg-section__title bbai-section-title"><?php esc_html_e('ALT Generation', 'beepbeep-ai-alt-text-generator'); ?></h2>
                                    <p class="bbai-sg-section__desc bbai-section-description"><?php esc_html_e('Control the style and format of every AI-generated description.', 'beepbeep-ai-alt-text-generator'); ?></p>
                                    </div>
                                </div>

                                <div class="bbai-sg-feature-grid">

                                    <div class="bbai-sg-feature">
                                        <div class="bbai-sg-feature__head">
                                            <span class="bbai-sg-feature__label"><?php esc_html_e('Description Style', 'beepbeep-ai-alt-text-generator'); ?></span>
                                            <span class="bbai-sg-feature__lock" title="<?php echo esc_attr(__('Available on Growth plan', 'beepbeep-ai-alt-text-generator')); ?>">
                                                <svg width="9" height="11" viewBox="0 0 9 11" fill="none" aria-hidden="true"><rect x="0.75" y="4.75" width="7.5" height="5.75" rx="1" stroke="currentColor" stroke-width="1.3"/><path d="M2.75 4.75V3.25C2.75 2.283 3.533 1.5 4.5 1.5C5.467 1.5 6.25 2.283 6.25 3.25V4.75" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                                                <?php esc_html_e('Pro', 'beepbeep-ai-alt-text-generator'); ?>
                                            </span>
                                        </div>
                                        <p class="bbai-sg-feature__desc"><?php esc_html_e('Set the AI\'s writing tone — professional, SEO-optimised, or accessibility-focused.', 'beepbeep-ai-alt-text-generator'); ?></p>
                                        <div class="bbai-sg-feature__default"><?php esc_html_e('Default: Professional', 'beepbeep-ai-alt-text-generator'); ?></div>
                                        <button type="button" class="bbai-sg-feature__cta" data-action="show-auth-modal" data-auth-tab="login"><?php esc_html_e('Log in to configure', 'beepbeep-ai-alt-text-generator'); ?></button>
                                    </div>

                                    <div class="bbai-sg-feature">
                                        <div class="bbai-sg-feature__head">
                                            <span class="bbai-sg-feature__label"><?php esc_html_e('Text Length', 'beepbeep-ai-alt-text-generator'); ?></span>
                                            <span class="bbai-sg-feature__lock" title="<?php echo esc_attr(__('Available on Growth plan', 'beepbeep-ai-alt-text-generator')); ?>">
                                                <svg width="9" height="11" viewBox="0 0 9 11" fill="none" aria-hidden="true"><rect x="0.75" y="4.75" width="7.5" height="5.75" rx="1" stroke="currentColor" stroke-width="1.3"/><path d="M2.75 4.75V3.25C2.75 2.283 3.533 1.5 4.5 1.5C5.467 1.5 6.25 2.283 6.25 3.25V4.75" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                                                <?php esc_html_e('Pro', 'beepbeep-ai-alt-text-generator'); ?>
                                            </span>
                                        </div>
                                        <p class="bbai-sg-feature__desc"><?php esc_html_e('Choose how detailed descriptions should be — from a concise phrase to a full sentence.', 'beepbeep-ai-alt-text-generator'); ?></p>
                                        <div class="bbai-sg-feature__default"><?php esc_html_e('Default: Standard', 'beepbeep-ai-alt-text-generator'); ?></div>
                                        <button type="button" class="bbai-sg-feature__cta" data-action="show-auth-modal" data-auth-tab="login"><?php esc_html_e('Log in to configure', 'beepbeep-ai-alt-text-generator'); ?></button>
                                    </div>

                                    <div class="bbai-sg-feature">
                                        <div class="bbai-sg-feature__head">
                                            <span class="bbai-sg-feature__label"><?php esc_html_e('SEO Keywords', 'beepbeep-ai-alt-text-generator'); ?></span>
                                            <span class="bbai-sg-feature__lock" title="<?php echo esc_attr(__('Available on Growth plan', 'beepbeep-ai-alt-text-generator')); ?>">
                                                <svg width="9" height="11" viewBox="0 0 9 11" fill="none" aria-hidden="true"><rect x="0.75" y="4.75" width="7.5" height="5.75" rx="1" stroke="currentColor" stroke-width="1.3"/><path d="M2.75 4.75V3.25C2.75 2.283 3.533 1.5 4.5 1.5C5.467 1.5 6.25 2.283 6.25 3.25V4.75" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                                                <?php esc_html_e('Pro', 'beepbeep-ai-alt-text-generator'); ?>
                                            </span>
                                        </div>
                                        <p class="bbai-sg-feature__desc"><?php esc_html_e('Weave target keywords into descriptions to improve image search rankings.', 'beepbeep-ai-alt-text-generator'); ?></p>
                                        <div class="bbai-sg-feature__default"><?php esc_html_e('Default: Off', 'beepbeep-ai-alt-text-generator'); ?></div>
                                        <button type="button" class="bbai-sg-feature__cta" data-action="show-auth-modal" data-auth-tab="login"><?php esc_html_e('Log in to configure', 'beepbeep-ai-alt-text-generator'); ?></button>
                                    </div>

                                </div><!-- /.bbai-sg-feature-grid -->
                            </div><!-- /.bbai-sg-section ALT Generation -->

                            <!-- Advanced section -->
                            <div class="bbai-card bbai-sg-section">
                                <div class="bbai-sg-section__head bbai-section-header bbai-ui-section-header">
                                    <div class="bbai-ui-section-header__text">
                                    <h2 class="bbai-sg-section__title bbai-section-title"><?php esc_html_e('Advanced', 'beepbeep-ai-alt-text-generator'); ?></h2>
                                    <p class="bbai-sg-section__desc bbai-section-description"><?php esc_html_e('Fine-tune language and prompt behaviour.', 'beepbeep-ai-alt-text-generator'); ?></p>
                                    </div>
                                </div>

                                <div class="bbai-sg-feature bbai-sg-feature--row">
                                    <div class="bbai-sg-feature__row-body">
                                        <div class="bbai-sg-feature__head">
                                            <span class="bbai-sg-feature__label"><?php esc_html_e('Language', 'beepbeep-ai-alt-text-generator'); ?></span>
                                            <span class="bbai-sg-feature__lock" title="<?php echo esc_attr(__('Available on Growth plan', 'beepbeep-ai-alt-text-generator')); ?>">
                                                <svg width="9" height="11" viewBox="0 0 9 11" fill="none" aria-hidden="true"><rect x="0.75" y="4.75" width="7.5" height="5.75" rx="1" stroke="currentColor" stroke-width="1.3"/><path d="M2.75 4.75V3.25C2.75 2.283 3.533 1.5 4.5 1.5C5.467 1.5 6.25 2.283 6.25 3.25V4.75" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                                                <?php esc_html_e('Pro', 'beepbeep-ai-alt-text-generator'); ?>
                                            </span>
                                        </div>
                                        <p class="bbai-sg-feature__desc"><?php esc_html_e('Generate descriptions in your audience\'s language.', 'beepbeep-ai-alt-text-generator'); ?></p>
                                    </div>
                                    <div class="bbai-sg-feature__row-aside">
                                        <div class="bbai-sg-feature__default"><?php esc_html_e('English', 'beepbeep-ai-alt-text-generator'); ?></div>
                                        <button type="button" class="bbai-sg-feature__cta" data-action="show-auth-modal" data-auth-tab="login"><?php esc_html_e('Log in', 'beepbeep-ai-alt-text-generator'); ?></button>
                                    </div>
                                </div>

                                <div class="bbai-sg-feature bbai-sg-feature--row">
                                    <div class="bbai-sg-feature__row-body">
                                        <div class="bbai-sg-feature__head">
                                            <span class="bbai-sg-feature__label"><?php esc_html_e('Custom Prompt', 'beepbeep-ai-alt-text-generator'); ?></span>
                                            <span class="bbai-sg-feature__lock" title="<?php echo esc_attr(__('Available on Growth plan', 'beepbeep-ai-alt-text-generator')); ?>">
                                                <svg width="9" height="11" viewBox="0 0 9 11" fill="none" aria-hidden="true"><rect x="0.75" y="4.75" width="7.5" height="5.75" rx="1" stroke="currentColor" stroke-width="1.3"/><path d="M2.75 4.75V3.25C2.75 2.283 3.533 1.5 4.5 1.5C5.467 1.5 6.25 2.283 6.25 3.25V4.75" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                                                <?php esc_html_e('Pro', 'beepbeep-ai-alt-text-generator'); ?>
                                            </span>
                                        </div>
                                        <p class="bbai-sg-feature__desc"><?php esc_html_e('Add specific instructions for the AI to follow on every image.', 'beepbeep-ai-alt-text-generator'); ?></p>
                                    </div>
                                    <div class="bbai-sg-feature__row-aside">
                                        <div class="bbai-sg-feature__default"><?php esc_html_e('Not set', 'beepbeep-ai-alt-text-generator'); ?></div>
                                        <button type="button" class="bbai-sg-feature__cta" data-action="show-auth-modal" data-auth-tab="login"><?php esc_html_e('Log in', 'beepbeep-ai-alt-text-generator'); ?></button>
                                    </div>
                                </div>
                            </div><!-- /.bbai-sg-section Advanced -->

                        </div><!-- /.bbai-sg-main -->

                        <!-- RIGHT: Automation + Scan Frequency -->
                        <div class="bbai-sg-aside">

                            <!-- Automation: primary feature card -->
                            <div class="bbai-card bbai-sg-section bbai-sg-section--automation">
                                <div class="bbai-sg-automation__icon" aria-hidden="true">
                                    <svg width="18" height="18" viewBox="0 0 20 20" fill="none">
                                        <path d="M10 2.5L18 6.5V10C18 14.14 14.47 17.97 10 19C5.53 17.97 2 14.14 2 10V6.5L10 2.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                        <path d="M7 10l2 2 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                                <h2 class="bbai-sg-section__title bbai-sg-automation__title"><?php esc_html_e('Auto-optimise Uploads', 'beepbeep-ai-alt-text-generator'); ?></h2>
                                <p class="bbai-sg-automation__desc"><?php esc_html_e('Every image you upload gets ALT text instantly — no manual steps, no missed files.', 'beepbeep-ai-alt-text-generator'); ?></p>

                                <div class="bbai-sg-automation__toggle-row">
                                    <div class="bbai-sg-toggle-visual" aria-hidden="true">
                                        <span class="bbai-sg-toggle-track"><span class="bbai-sg-toggle-thumb"></span></span>
                                    </div>
                                    <span class="bbai-sg-automation__toggle-label"><?php esc_html_e('Auto-generate on upload', 'beepbeep-ai-alt-text-generator'); ?></span>
                                    <span class="bbai-sg-feature__lock" title="<?php echo esc_attr(__('Available on Growth plan', 'beepbeep-ai-alt-text-generator')); ?>">
                                        <svg width="9" height="11" viewBox="0 0 9 11" fill="none" aria-hidden="true"><rect x="0.75" y="4.75" width="7.5" height="5.75" rx="1" stroke="currentColor" stroke-width="1.3"/><path d="M2.75 4.75V3.25C2.75 2.283 3.533 1.5 4.5 1.5C5.467 1.5 6.25 2.283 6.25 3.25V4.75" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                                        <?php esc_html_e('Growth', 'beepbeep-ai-alt-text-generator'); ?>
                                    </span>
                                </div>

                                <button type="button" class="bbai-btn bbai-btn-primary bbai-sg-automation__cta" data-action="show-upgrade-modal">
                                    <?php esc_html_e('Enable Auto-Optimisation', 'beepbeep-ai-alt-text-generator'); ?>
                                </button>
                                <p class="bbai-sg-automation__note">
                                    <a href="#" data-action="show-auth-modal" data-auth-tab="login"><?php esc_html_e('Log in first', 'beepbeep-ai-alt-text-generator'); ?></a>
                                    <?php esc_html_e('to access your current plan.', 'beepbeep-ai-alt-text-generator'); ?>
                                </p>
                            </div><!-- /.bbai-sg-section--automation -->

                            <!-- Scan Frequency -->
                            <div class="bbai-card bbai-sg-section bbai-sg-section--loose">
                                <div class="bbai-sg-feature__head">
                                    <span class="bbai-sg-feature__label"><?php esc_html_e('Scan Frequency', 'beepbeep-ai-alt-text-generator'); ?></span>
                                    <span class="bbai-sg-feature__lock" title="<?php echo esc_attr(__('Available on Growth plan', 'beepbeep-ai-alt-text-generator')); ?>">
                                        <svg width="9" height="11" viewBox="0 0 9 11" fill="none" aria-hidden="true"><rect x="0.75" y="4.75" width="7.5" height="5.75" rx="1" stroke="currentColor" stroke-width="1.3"/><path d="M2.75 4.75V3.25C2.75 2.283 3.533 1.5 4.5 1.5C5.467 1.5 6.25 2.283 6.25 3.25V4.75" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                                        <?php esc_html_e('Growth', 'beepbeep-ai-alt-text-generator'); ?>
                                    </span>
                                </div>
                                <p class="bbai-sg-feature__desc"><?php esc_html_e('Schedule automatic scans to catch newly uploaded images missing ALT text.', 'beepbeep-ai-alt-text-generator'); ?></p>
                                <div class="bbai-sg-feature__default"><?php esc_html_e('Default: Manual only', 'beepbeep-ai-alt-text-generator'); ?></div>
                                <button type="button" class="bbai-sg-feature__cta" data-action="show-auth-modal" data-auth-tab="login"><?php esc_html_e('Log in to configure', 'beepbeep-ai-alt-text-generator'); ?></button>
                            </div><!-- /.bbai-sg-section Scan Frequency -->

                        </div><!-- /.bbai-sg-aside -->

                    </div><!-- /.bbai-sg-grid -->

                    <div class="bbai-card bbai-settings-upgrade-block">
                        <h3 class="bbai-settings-card-title"><?php esc_html_e('Unlock synced settings across sites', 'beepbeep-ai-alt-text-generator'); ?></h3>
                        <ul>
                            <li><?php esc_html_e('Sync settings across sites', 'beepbeep-ai-alt-text-generator'); ?></li>
                            <li><?php esc_html_e('Track optimisation history', 'beepbeep-ai-alt-text-generator'); ?></li>
                            <li><?php esc_html_e('Access usage and credits', 'beepbeep-ai-alt-text-generator'); ?></li>
                        </ul>
                        <div class="bbai-settings-upgrade-block__actions">
                            <button type="button" class="bbai-btn bbai-btn-primary" data-action="show-auth-modal" data-auth-tab="login">
                                <?php esc_html_e('Log in', 'beepbeep-ai-alt-text-generator'); ?>
                            </button>
                            <a class="bbai-btn bbai-btn-secondary" href="<?php echo esc_url(add_query_arg(['tab' => 'settings'])); ?>">
                                <?php esc_html_e('Continue with local settings', 'beepbeep-ai-alt-text-generator'); ?>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php else : ?>
            <!-- Settings Page -->
            <div class="bbai-container bbai-settings-page">
                <?php
                    $bbai_settings_auth = \BeepBeepAI\AltTextGenerator\Auth_State::resolve($this->api_client);
                    $bbai_usage_box = \BeepBeepAI\AltTextGenerator\Services\Usage_Helper::get_usage(
                        $this->api_client,
                        (bool) ($bbai_settings_auth['has_connected_account'] ?? false)
                    );
                    $bbai_o = wp_parse_args($opts, []);
                    
                    // Check for license plan first
                    $bbai_license_data = $this->api_client->get_license_data();
                
                $bbai_plan = sanitize_key((string) ($bbai_usage_box['plan_type'] ?? $bbai_usage_box['plan'] ?? 'free'));
                $bbai_usage_source = strtolower((string) ($bbai_usage_box['source'] ?? ''));
                
                // License data is only a fallback when backend usage truth was unavailable.
                if ($bbai_has_license && $bbai_license_data && isset($bbai_license_data['organization'])) {
                    $bbai_license_plan = strtolower($bbai_license_data['organization']['plan'] ?? 'free');
                    if ($bbai_license_plan !== 'free' && in_array($bbai_usage_source, ['local_snapshot', 'local_trial_snapshot', ''], true)) {
                        $bbai_plan = $bbai_license_plan;
                    }
                }
                
                $bbai_is_pro = ($bbai_plan === 'pro' || $bbai_plan === 'growth');
                $bbai_is_agency = $bbai_plan === 'agency';
                $bbai_is_growth_plan = ($bbai_is_pro || $bbai_is_agency);

                $bbai_plan_label = !empty($bbai_usage_box['plan_label']) && is_string($bbai_usage_box['plan_label'])
                    ? sanitize_text_field($bbai_usage_box['plan_label'])
                    : ($bbai_is_growth_plan
                    ? esc_html__('Growth', 'beepbeep-ai-alt-text-generator')
                    : esc_html__('Free', 'beepbeep-ai-alt-text-generator'));

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
                <div class="bbai-hero bbai-hero--neutral bbai-dashboard-header-section bbai-page-section">
                    <h1 class="bbai-dashboard-title bbai-page-title"><?php esc_html_e('Settings', 'beepbeep-ai-alt-text-generator'); ?></h1>
                    <p class="bbai-dashboard-subtitle bbai-page-subtitle"><?php esc_html_e('Configure BeepBeep AI and control how ALT text is generated for your media library.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    <p class="bbai-dashboard-subtitle bbai-settings-disclosure bbai-sub-label">
                        <?php esc_html_e('Alt text generation and review send image data and related context to external AI services over HTTPS. The free plan includes 50 generations per month; paid plans increase your monthly limits.', 'beepbeep-ai-alt-text-generator'); ?>
                        <a href="<?php echo esc_url('https://oppti.dev/privacy'); ?>" target="_blank" rel="noopener"><?php esc_html_e('Privacy Policy', 'beepbeep-ai-alt-text-generator'); ?></a>
                    </p>
                </div>

                <div class="bbai-metrics bbai-page-section" aria-label="<?php esc_attr_e('Settings account metrics', 'beepbeep-ai-alt-text-generator'); ?>">
                    <div>
                        <strong><?php echo esc_html($bbai_plan_label); ?></strong>
                        <span><?php esc_html_e('current plan', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </div>
                    <div>
                        <strong><?php echo esc_html(number_format_i18n($bbai_used_credits) . ' / ' . number_format_i18n($bbai_total_credits)); ?></strong>
                        <span><?php esc_html_e('credits used this cycle', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </div>
                    <div>
                        <strong><?php echo esc_html($bbai_reset_label); ?></strong>
                        <span><?php esc_html_e('next reset', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </div>
                </div>

                <?php if ($bbai_can_show_debug_section) : ?>
                <div class="bbai-settings-section-nav bbai-page-section" role="navigation" aria-label="<?php esc_attr_e('Settings sections', 'beepbeep-ai-alt-text-generator'); ?>">
                    <a href="<?php echo esc_url($bbai_settings_general_url); ?>" class="bbai-settings-section-link<?php echo 'general' === $bbai_settings_section ? ' active' : ''; ?>"<?php echo 'general' === $bbai_settings_section ? ' aria-current="page"' : ''; ?>>
                        <?php esc_html_e('General', 'beepbeep-ai-alt-text-generator'); ?>
                    </a>
                    <a href="<?php echo esc_url($bbai_settings_debug_url); ?>" class="bbai-settings-section-link<?php echo 'debug' === $bbai_settings_section ? ' active' : ''; ?>"<?php echo 'debug' === $bbai_settings_section ? ' aria-current="page"' : ''; ?>>
                        <?php esc_html_e('Debug', 'beepbeep-ai-alt-text-generator'); ?>
                    </a>
                </div>
                <?php endif; ?>

                <?php
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only, no action
                if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') :
                ?>
                <div class="bbai-settings-saved-notice bbai-page-section" role="status">
                    <span class="bbai-settings-saved-icon" aria-hidden="true">✓</span>
                    <?php esc_html_e('Settings saved successfully.', 'beepbeep-ai-alt-text-generator'); ?>
                </div>
                <?php endif; ?>

                <?php if ('general' === $bbai_settings_section) : ?>
                <!-- Site-Wide Settings Banner -->
                <div class="bbai-alert bbai-alert--info bbai-settings-sitewide-banner bbai-page-section">
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
                <div class="bbai-card bbai-card--compact bbai-settings-plan-summary-card bbai-page-section">
                    <h3 class="bbai-settings-card-title bbai-card-title"><?php esc_html_e('Account Status', 'beepbeep-ai-alt-text-generator'); ?></h3>

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
                    <button type="button" class="bbai-btn bbai-btn-primary bbai-btn-lg" data-action="show-upgrade-modal" data-bbai-tooltip="<?php esc_attr_e('Automation, bulk optimisation, and higher monthly limits', 'beepbeep-ai-alt-text-generator'); ?>" data-bbai-tooltip-position="bottom">
                        <?php esc_html_e('Enable Auto-Optimisation', 'beepbeep-ai-alt-text-generator'); ?>
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Account Management Card -->
                <div class="bbai-card bbai-settings-card bbai-page-section">
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

                    <!-- ALT Text Generation Card -->
                    <div class="bbai-card bbai-settings-card bbai-page-section">
                        <h3 class="bbai-settings-card-title bbai-section-title"><?php esc_html_e('ALT Text Generation', 'beepbeep-ai-alt-text-generator'); ?></h3>
                        <p class="bbai-settings-card-description bbai-description"><?php esc_html_e('Control how AI-generated ALT text behaves.', 'beepbeep-ai-alt-text-generator'); ?></p>

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
                                class="bbai-settings-form-input bbai-select bbai-input"
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
                                class="bbai-settings-form-textarea bbai-textarea"
                            ><?php echo esc_textarea($bbai_o['custom_prompt'] ?? ''); ?></textarea>
                        </div>

                        <div class="bbai-settings-form-actions bbai-settings-form-actions--right">
                            <button type="submit" class="bbai-btn bbai-btn-primary bbai-cta-save">
                                <?php esc_html_e('Save Settings', 'beepbeep-ai-alt-text-generator'); ?>
                            </button>
                        </div>
                    </div>
                </form>
                <?php else : ?>
                <?php
                $bbai_debug_embedded = true;
                $bbai_debug_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/debug-tab.php';
                if (file_exists($bbai_debug_partial)) {
                    include $bbai_debug_partial;
                } else {
                    esc_html_e('Debug content unavailable.', 'beepbeep-ai-alt-text-generator');
                }
                ?>
                <?php endif; ?>

            </div>
            <?php endif; // End if/else for authentication check in settings tab ?>
    
