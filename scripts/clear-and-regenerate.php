#!/usr/bin/env php
<?php
/**
 * Clear existing alt text and regenerate for a few images
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

echo "Clear and Regenerate Test\n";
echo str_repeat("=", 70) . "\n\n";

// Clear alt text for images 16-20
$image_ids = [16, 17, 18];

echo "Step 1: Clearing existing alt text...\n";
foreach ($image_ids as $id) {
    delete_post_meta($id, '_wp_attachment_image_alt');
    echo "  Cleared alt text for image #$id\n";
}

echo "\nStep 2: Generating fresh alt text...\n";
$api_client = new BbAI_API_Client_V2();

foreach ($image_ids as $image_id) {
    $file_path = get_attached_file($image_id);
    $title = get_the_title($image_id);

    echo "\nImage #$image_id: $title\n";

    $context = [
        'filename' => basename($file_path),
        'title' => $title,
        'caption' => '',
        'post_title' => '',
    ];

    $result = $api_client->generate_alt_text($image_id, $context, false);

    if (is_wp_error($result)) {
        echo "  ❌ Error: " . $result->get_error_message() . "\n";
    } else {
        $alt_text = $result['alt_text'] ?? 'N/A';
        echo "  ✓ Generated: $alt_text\n";

        // Save it manually
        update_post_meta($image_id, '_wp_attachment_image_alt', wp_strip_all_tags($alt_text));
        echo "  ✓ Saved to database\n";

        // Verify it saved
        $saved_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
        echo "  ✓ Verified: " . substr($saved_alt, 0, 50) . "...\n";
    }

    sleep(1);
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "Complete!\n";
