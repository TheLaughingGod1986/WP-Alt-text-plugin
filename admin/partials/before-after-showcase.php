<?php
/**
 * Before/After Showcase - Trust-building visual aid
 * Shows users what good output looks like
 *
 * @package BeepBeep_AI
 * @since 6.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="bbai-before-after-showcase bbai-how-it-works" aria-labelledby="bbai-before-after-heading">
    <h2 id="bbai-before-after-heading" class="bbai-before-after-showcase__title"><?php esc_html_e('How BeepBeep works', 'beepbeep-ai-alt-text-generator'); ?></h2>
    <div class="bbai-before-after-showcase__grid">
        <div class="bbai-before-after-card bbai-before-after-card--before">
            <span class="bbai-before-after-card__label"><?php esc_html_e('Before', 'beepbeep-ai-alt-text-generator'); ?></span>
            <p class="bbai-before-after-card__text"><?php esc_html_e('Image had no ALT text', 'beepbeep-ai-alt-text-generator'); ?></p>
        </div>
        <div class="bbai-before-after-card bbai-before-after-card--after">
            <span class="bbai-before-after-card__label"><?php esc_html_e('After', 'beepbeep-ai-alt-text-generator'); ?></span>
            <p class="bbai-before-after-card__text"><?php esc_html_e('"Golden retriever running through green field under blue sky"', 'beepbeep-ai-alt-text-generator'); ?></p>
            <span class="bbai-before-after-card__meta"><?php esc_html_e('Quality: Excellent • Optimized automatically • Ready for review', 'beepbeep-ai-alt-text-generator'); ?></span>
        </div>
    </div>
</section>
