<?php
/**
 * Smart upgrade prompt - contextual, soft prompt below usage banner.
 * Shown when user has reached free limit (dashboard only).
 *
 * Expects: $bbai_banner_out_of_credits (bool)
 *
 * @package BeepBeep_AI_Alt_Text_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( empty( $bbai_banner_out_of_credits ) ) {
    return;
}
?>
<section class="bbai-smart-upgrade-prompt" aria-label="<?php esc_attr_e( 'Upgrade suggestion', 'beepbeep-ai-alt-text-generator' ); ?>">
    <p class="bbai-smart-upgrade-prompt__copy">
        <?php esc_html_e( "You've reached your free limit.", 'beepbeep-ai-alt-text-generator' ); ?>
        <?php esc_html_e( 'Sites like yours typically optimise 300+ images per month.', 'beepbeep-ai-alt-text-generator' ); ?>
        <?php esc_html_e( 'Upgrade to Growth to continue generating alt text automatically.', 'beepbeep-ai-alt-text-generator' ); ?>
    </p>
    <button type="button" class="bbai-smart-upgrade-prompt__cta bbai-btn bbai-btn-primary" data-action="show-upgrade-modal">
        <?php esc_html_e( 'Upgrade to Growth', 'beepbeep-ai-alt-text-generator' ); ?>
    </button>
</section>
