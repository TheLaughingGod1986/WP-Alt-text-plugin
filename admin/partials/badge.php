<?php
/**
 * Reusable Badge Component
 *
 * Displays a standardized badge with consistent styling across the plugin.
 *
 * @param string $text Badge text
 * @param string $variant Badge variant: 'free', 'growth', 'pro', 'agency', 'getting-started', 'default'
 * @param string $class Additional CSS classes
 */
if (!defined('ABSPATH')) {
    exit;
}

// Extract parameters
$badge_text = isset($badge_text) ? $badge_text : '';
$badge_variant = isset($badge_variant) ? $badge_variant : 'default';
$badge_class = isset($badge_class) ? $badge_class : '';

// Build class string
$classes = ['bbai-badge'];
$classes[] = 'bbai-badge--' . esc_attr($badge_variant);
if ($badge_class) {
    $classes[] = esc_attr($badge_class);
}
$class_string = implode(' ', $classes);
?>
<span class="<?php echo esc_attr($class_string); ?>">
    <?php echo esc_html($badge_text); ?>
</span>
