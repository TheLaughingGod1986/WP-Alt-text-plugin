#!/usr/bin/env php
<?php
/**
 * Debug Image Resize Process
 */

// Load WordPress
$wp_load_paths = [
    __DIR__ . '/../../../../wp-load.php',
    '/var/www/html/wp-load.php',
];

foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

if (!defined('ABSPATH')) {
    echo "❌ Failed to load WordPress\n";
    exit(1);
}

echo "Debug Image Resize for Image #18\n";
echo str_repeat("=", 60) . "\n\n";

$image_id = 18;
$file_path = get_attached_file($image_id);
$file_size = filesize($file_path);
$metadata = wp_get_attachment_metadata($image_id);

echo "Original Image:\n";
echo "  Path: $file_path\n";
echo "  Size: " . round($file_size / 1024, 2) . " KB\n";
echo "  Dimensions: {$metadata['width']}x{$metadata['height']}\n\n";

// Check thresholds
$max_file_size = 512 * 1024;
echo "Size threshold: " . round($max_file_size / 1024, 2) . " KB\n";
echo "Should resize: " . ($file_size > $max_file_size ? 'YES' : 'NO') . "\n";
echo "Max dimension: 200px\n\n";

// Try resizing manually
$editor = wp_get_image_editor($file_path);
if (is_wp_error($editor)) {
    echo "❌ Failed to create editor: " . $editor->get_error_message() . "\n";
    exit(1);
}

echo "Resizing to 200px with quality 45...\n";

// Resize
$orig_width = $metadata['width'];
$orig_height = $metadata['height'];
if ($orig_width > $orig_height) {
    $new_width = 200;
    $new_height = intval(($orig_height / $orig_width) * 200);
} else {
    $new_height = 200;
    $new_width = intval(($orig_width / $orig_height) * 200);
}

echo "New dimensions: {$new_width}x{$new_height}\n";

$editor->resize($new_width, $new_height, false);

if (method_exists($editor, 'set_quality')) {
    $editor->set_quality(45);
    echo "Quality set to: 45\n";
}

$upload_dir = wp_upload_dir();
$temp_filename = 'test-resize-' . time() . '.jpg';
$temp_path = $upload_dir['path'] . '/' . $temp_filename;

$saved = $editor->save($temp_path, 'image/jpeg');

if (is_wp_error($saved)) {
    echo "❌ Failed to save: " . $saved->get_error_message() . "\n";
    exit(1);
}

$resized_contents = file_get_contents($saved['path']);
$resized_size = strlen($resized_contents);
$base64 = base64_encode($resized_contents);
$base64_size = strlen($base64);

@unlink($saved['path']);

echo "\nResults:\n";
echo "  Resized file size: " . round($resized_size / 1024, 2) . " KB\n";
echo "  Base64 size: " . round($base64_size / 1024, 2) . " KB\n";
echo "  Total payload (with context): ~" . round(($base64_size + 2000) / 1024, 2) . " KB\n\n";

if ($base64_size <= 90 * 1024) {
    echo "✓ Base64 is under 90KB - should work!\n";
} else {
    echo "❌ Base64 is over 90KB - will fail\n";
}
