#!/usr/bin/env php
<?php
/**
 * Setup demo content for screenshot capture.
 * Creates a demo user and imports stock images (replacing personal media).
 *
 * Run from WordPress root: php setup-demo-content.php
 * Or via Docker: docker cp scripts/setup-demo-content.php CONTAINER:/var/www/html/ && docker exec CONTAINER php /var/www/html/setup-demo-content.php
 */

// Bootstrap WordPress (script is copied to WP root before running)
$wp_load = getenv('WP_LOAD') ?: (dirname(__FILE__) . '/wp-load.php');
if (!file_exists($wp_load)) {
    fwrite(STDERR, "Error: wp-load.php not found. Run from WordPress root or set WP_LOAD.\n");
    exit(1);
}
require_once $wp_load;

// Demo images: Picsum Photos (free, no attribution required for demo use)
$demo_images = [
    'https://picsum.photos/id/10/1200/800',  // nature
    'https://picsum.photos/id/11/1200/800',  // architecture
    'https://picsum.photos/id/15/1200/800',  // mountain
    'https://picsum.photos/id/16/1200/800',  // nature
    'https://picsum.photos/id/17/1200/800',  // landscape
    'https://picsum.photos/id/18/1200/800',  // beach
    'https://picsum.photos/id/20/1200/800',  // mountain
];

echo "=== Demo Content Setup ===\n\n";

// 1. Create demo user if not exists
$demo_user = get_user_by('login', 'demo');
if (!$demo_user) {
    $user_id = wp_create_user('demo', 'demo123', 'demo@example.com');
    if (is_wp_error($user_id)) {
        fwrite(STDERR, "Error creating demo user: " . $user_id->get_error_message() . "\n");
    } else {
        $user = get_user_by('id', $user_id);
        $user->set_role('administrator');
        echo "Created demo user: demo / demo123\n";
    }
} else {
    echo "Demo user already exists: demo / demo123\n";
}

// 2. Delete existing media (attachments) to remove personal images
global $wpdb;
$count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'");
if ($count > 0) {
    $attachments = get_posts(['post_type' => 'attachment', 'numberposts' => -1, 'post_status' => 'any']);
    foreach ($attachments as $att) {
        wp_delete_attachment($att->ID, true);
    }
    echo "Removed {$count} existing media items.\n";
}

// 3. Import demo images
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$upload_dir = wp_upload_dir();
if ($upload_dir['error']) {
    fwrite(STDERR, "Upload dir error: " . $upload_dir['error'] . "\n");
    exit(1);
}

$titles = ['Mountain landscape', 'Modern architecture', 'Scenic valley', 'Forest path', 'Coastal view', 'Alpine meadow', 'Lakeside sunset'];
$imported = 0;

foreach ($demo_images as $i => $url) {
    $url = $url . '?t=' . time(); // Avoid cache
    $tmp = download_url($url);
    if (is_wp_error($tmp)) {
        echo "  Skip {$url}: " . $tmp->get_error_message() . "\n";
        continue;
    }
    $file_array = [
        'name'     => 'demo-' . ($i + 1) . '.jpg',
        'tmp_name' => $tmp,
    ];
    $id = media_handle_sideload($file_array, 0);
    @unlink($tmp);
    if (is_wp_error($id)) {
        echo "  Import failed for demo-" . ($i + 1) . ": " . $id->get_error_message() . "\n";
        continue;
    }
    wp_update_post([
        'ID'         => $id,
        'post_title' => $titles[$i] ?? 'Demo image ' . ($i + 1),
    ]);
    $imported++;
}
echo "Imported {$imported} demo images.\n\n";
echo "Done. You can now run: node scripts/capture-screenshots.js\n";
echo "Login: admin / Plymouth.09 (or demo / demo123)\n";
