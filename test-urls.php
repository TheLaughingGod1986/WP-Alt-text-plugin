<?php
/**
 * Test script to verify CSS file paths and URLs
 * Load this via: http://localhost:8080/wp-content/plugins/beepbeep-ai-alt-text-generator/test-urls.php
 */

// Load WordPress
require_once('../../../wp-load.php');

echo "<h1>CSS File Path Test</h1>";

$base_path = BBAI_PLUGIN_DIR;
$base_url = BBAI_PLUGIN_URL;

echo "<p><strong>BBAI_PLUGIN_DIR:</strong> " . esc_html($base_path) . "</p>";
echo "<p><strong>BBAI_PLUGIN_URL:</strong> " . esc_html($base_url) . "</p>";

$css_file = 'assets/css/modern.bundle.min.css';
$full_path = $base_path . $css_file;
$full_url = $base_url . $css_file;

echo "<p><strong>CSS File Path:</strong> " . esc_html($full_path) . "</p>";
echo "<p><strong>File Exists:</strong> " . (file_exists($full_path) ? 'YES' : 'NO') . "</p>";
echo "<p><strong>File Size:</strong> " . (file_exists($full_path) ? number_format(filesize($full_path)) . ' bytes' : 'N/A') . "</p>";
echo "<p><strong>CSS URL:</strong> <a href='" . esc_url($full_url) . "' target='_blank'>" . esc_html($full_url) . "</a></p>";

echo "<hr>";

// Test all enqueued styles
global $wp_styles;
if (!empty($wp_styles->registered)) {
    echo "<h2>Registered Styles</h2><ul>";
    foreach ($wp_styles->registered as $handle => $style) {
        if (strpos($handle, 'bbai') !== false) {
            echo "<li><strong>" . esc_html($handle) . "</strong>: " . esc_html($style->src) . "</li>";
        }
    }
    echo "</ul>";
}
