<?php
/**
 * Settings card: opt-in usage analytics toggle (off by default).
 *
 * Saves through admin-post.php so it works for every plan, signed in or not.
 *
 * @package BeepBeep_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\BeepBeepAI\AltTextGenerator\BBAI_Telemetry') || !current_user_can('manage_options')) {
    return;
}

$bbai_usage_analytics_on = \BeepBeepAI\AltTextGenerator\BBAI_Telemetry::stored_consent_is_yes();
$bbai_usage_analytics_forced = defined('BBAI_TELEMETRY_CONSENT');
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag after a nonce-checked save.
$bbai_usage_analytics_saved = isset($_GET['bbai_usage_analytics_saved']) ? sanitize_key(wp_unslash($_GET['bbai_usage_analytics_saved'])) : '';
?>
<div class="bbai-card bbai-settings-card bbai-page-section" id="bbai-usage-analytics">
    <h3 class="bbai-settings-card-title bbai-section-title"><?php esc_html_e('Usage analytics', 'beepbeep-ai-alt-text-generator'); ?></h3>
    <p class="bbai-settings-card-description bbai-description"><?php esc_html_e('Help us improve OpptiAI Alt Text by sharing anonymous usage data: which screens and buttons are used, and whether actions succeed. We never send ALT text, file names, image content, emails or passwords. Off unless you turn it on.', 'beepbeep-ai-alt-text-generator'); ?></p>

    <?php if ('on' === $bbai_usage_analytics_saved || 'off' === $bbai_usage_analytics_saved) : ?>
    <p class="bbai-settings-form-description" role="status">
        <?php echo 'on' === $bbai_usage_analytics_saved
            ? esc_html__('Saved. Usage analytics are on.', 'beepbeep-ai-alt-text-generator')
            : esc_html__('Saved. Usage analytics are off.', 'beepbeep-ai-alt-text-generator'); ?>
    </p>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="<?php echo esc_attr(\BeepBeepAI\AltTextGenerator\BBAI_Telemetry::CONSENT_FORM_ACTION); ?>">
        <?php wp_nonce_field(\BeepBeepAI\AltTextGenerator\BBAI_Telemetry::CONSENT_FORM_ACTION); ?>
        <input type="hidden" name="bbai_usage_analytics" value="0">
        <div class="bbai-settings-form-group">
            <div class="bbai-settings-form-field bbai-settings-form-field--toggle">
                <div class="bbai-settings-form-field-content">
                    <label for="bbai-usage-analytics-toggle" class="bbai-settings-form-label">
                        <?php esc_html_e('Share anonymous usage analytics', 'beepbeep-ai-alt-text-generator'); ?>
                    </label>
                    <p class="bbai-settings-form-description">
                        <?php
                        echo wp_kses(
                            sprintf(
                                /* translators: %s: link to the PostHog privacy policy */
                                esc_html__('Sent to PostHog (%s). You can turn this off at any time.', 'beepbeep-ai-alt-text-generator'),
                                '<a href="https://posthog.com/privacy" target="_blank" rel="noopener noreferrer">' . esc_html__('privacy policy', 'beepbeep-ai-alt-text-generator') . '</a>'
                            ),
                            array('a' => array('href' => array(), 'target' => array(), 'rel' => array()))
                        );
                        ?>
                    </p>
                    <?php if ($bbai_usage_analytics_forced) : ?>
                    <p class="bbai-settings-form-description">
                        <?php esc_html_e('This site sets BBAI_TELEMETRY_CONSENT in wp-config.php, which overrides this choice.', 'beepbeep-ai-alt-text-generator'); ?>
                    </p>
                    <?php endif; ?>
                </div>
                <label class="bbai-settings-toggle">
                    <input
                        type="checkbox"
                        id="bbai-usage-analytics-toggle"
                        name="bbai_usage_analytics"
                        value="1"
                        <?php checked($bbai_usage_analytics_on); ?>
                    >
                    <span class="bbai-settings-toggle-slider"></span>
                </label>
            </div>
        </div>
        <div class="bbai-settings-form-actions bbai-settings-form-actions--right">
            <button type="submit" class="bbai-btn bbai-btn-secondary">
                <?php esc_html_e('Save analytics choice', 'beepbeep-ai-alt-text-generator'); ?>
            </button>
        </div>
    </form>
</div>
