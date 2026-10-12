<?php
/**
 * Settings: anonymous usage-stats opt-in (off by default).
 *
 * @package BeepBeep_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) || ! class_exists( '\BeepBeepAI\AltTextGenerator\BBAI_Telemetry' ) ) {
	return;
}

$bbai_usage_stats_on = \BeepBeepAI\AltTextGenerator\BBAI_Telemetry::has_telemetry_consent();
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bbai-card bbai-settings-card bbai-page-section bbai-settings-usage-stats">
	<input type="hidden" name="action" value="<?php echo esc_attr( \BeepBeepAI\AltTextGenerator\BBAI_Telemetry::OPTIN_ACTION ); ?>">
	<?php wp_nonce_field( \BeepBeepAI\AltTextGenerator\BBAI_Telemetry::OPTIN_ACTION ); ?>
	<h3 class="bbai-settings-card-title bbai-section-title"><?php esc_html_e( 'Privacy', 'beepbeep-ai-alt-text-generator' ); ?></h3>
	<div class="bbai-settings-form-group">
		<div class="bbai-settings-form-field bbai-settings-form-field--toggle">
			<div class="bbai-settings-form-field-content">
				<label for="bbai-usage-stats" class="bbai-settings-form-label">
					<?php esc_html_e( 'Share anonymous usage stats to help improve OpptiAI Alt Text', 'beepbeep-ai-alt-text-generator' ); ?>
				</label>
				<p class="bbai-settings-form-description">
					<?php esc_html_e( 'Off by default. Sends anonymous feature usage only, never content or personal data.', 'beepbeep-ai-alt-text-generator' ); ?>
				</p>
			</div>
			<label class="bbai-settings-toggle">
				<input type="checkbox" id="bbai-usage-stats" name="bbai_usage_stats" value="1" <?php checked( $bbai_usage_stats_on ); ?>>
				<span class="bbai-settings-toggle-slider"></span>
			</label>
		</div>
	</div>
	<div class="bbai-settings-form-actions bbai-settings-form-actions--right">
		<button type="submit" class="bbai-btn bbai-btn-secondary"><?php esc_html_e( 'Save', 'beepbeep-ai-alt-text-generator' ); ?></button>
	</div>
</form>
