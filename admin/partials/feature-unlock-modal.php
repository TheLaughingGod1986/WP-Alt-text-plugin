<?php
/**
 * Feature unlock modal - small modal shown when user clicks a locked feature.
 * Content is populated via JS from data attributes on trigger elements.
 *
 * @package BeepBeep_AI_Alt_Text_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="bbai-feature-unlock-modal" class="bbai-modal-backdrop bbai-feature-unlock-modal" role="dialog" aria-modal="true" aria-labelledby="bbai-feature-unlock-title" aria-hidden="true" style="display: none;">
    <div class="bbai-feature-unlock-modal__content" role="document">
        <button type="button" class="bbai-feature-unlock-modal__close" aria-label="<?php esc_attr_e( 'Close', 'beepbeep-ai-alt-text-generator' ); ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
        <h2 id="bbai-feature-unlock-title" class="bbai-feature-unlock-modal__title"></h2>
        <p class="bbai-feature-unlock-modal__desc"></p>
        <button type="button" class="bbai-feature-unlock-modal__cta bbai-btn bbai-btn-primary" data-action="show-upgrade-modal">
            <?php esc_html_e( 'Upgrade to Growth', 'beepbeep-ai-alt-text-generator' ); ?>
        </button>
    </div>
</div>
