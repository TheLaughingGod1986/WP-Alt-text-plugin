<?php
/**
 * Schema.org Markup Generator
 *
 * Adds ImageObject schema markup for enhanced Google Images SEO
 *
 * @package BeepBeepAI_AltText
 * @since 4.2.4
 */

if (!defined('ABSPATH')) {
    exit;
}

class BBAI_Schema_Markup {

    /**
     * Initialize schema markup hooks
     */
    public static function init() {
        // Add schema markup to image attachments
        add_filter('wp_get_attachment_image_attributes', [__CLASS__, 'add_image_attributes'], 10, 3);

        // Output schema JSON-LD in head (standard location for structured data)
        add_action('wp_head', [__CLASS__, 'output_schema_json_ld'], 99);

        // Admin setting to enable/disable schema
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    /**
     * Register schema settings
     */
    public static function register_settings() {
        register_setting(
            'bbai_settings_group',
            'bbai_enable_schema_markup',
            [
                'type' => 'boolean',
                'default' => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ]
        );
    }

    /**
     * Check if schema markup is enabled
     *
     * @return bool
     */
    public static function is_enabled() {
        return (bool) get_option('bbai_enable_schema_markup', true);
    }

    /**
     * Store image data for schema output
     *
     * @var array
     */
    private static $images_for_schema = [];

    /**
     * Add schema-related attributes to images
     *
     * @param array        $attr       Image attributes
     * @param WP_Post      $attachment Image attachment post
     * @param string|array $size       Image size
     * @return array Modified attributes
     */
    public static function add_image_attributes($attr, $attachment, $size) {
        if (!self::is_enabled()) {
            return $attr;
        }

        // Only add schema for images with alt text
        $alt_text = get_post_meta($attachment->ID, '_wp_attachment_image_alt', true);
        if (empty($alt_text)) {
            return $attr;
        }

        // Get image data
        $image_url = wp_get_attachment_image_src($attachment->ID, 'full');
        if (!$image_url) {
            return $attr;
        }

        // Store image data for JSON-LD output
        self::$images_for_schema[$attachment->ID] = [
            'url' => $image_url[0],
            'width' => $image_url[1],
            'height' => $image_url[2],
            'alt' => $alt_text,
            'title' => get_the_title($attachment->ID),
            'caption' => wp_get_attachment_caption($attachment->ID),
            'description' => $attachment->post_content,
            'date' => get_post_time('c', false, $attachment->ID),
            'author' => get_the_author_meta('display_name', $attachment->post_author),
        ];

        // Add itemprop for compatibility
        $attr['itemprop'] = 'image';

        return $attr;
    }

    /**
     * Output JSON-LD schema markup for images via wp_head hook.
     * Uses wp_print_inline_script_tag for proper WordPress compliance.
     */
    public static function output_schema_json_ld() {
        if (is_admin() || !self::is_enabled() || empty(self::$images_for_schema)) {
            return;
        }

        // Build schema objects
        $schema_objects = [];
        foreach (self::$images_for_schema as $image_id => $image_data) {
            $schema_objects[] = self::build_image_object_schema($image_data);
        }

        // Output single schema or array
        if (count($schema_objects) === 1) {
            $schema = $schema_objects[0];
        } else {
            $schema = [
                '@context' => 'https://schema.org',
                '@graph' => $schema_objects,
            ];
        }

        echo "\n<!-- BeepBeep AI - Image Schema Markup -->\n";
        wp_print_inline_script_tag(
            wp_json_encode($schema),
            ['type' => 'application/ld+json']
        );
        echo "<!-- /BeepBeep AI - Image Schema Markup -->\n";
    }

    /**
     * Build ImageObject schema for a single image
     *
     * @param array $image_data Image data array
     * @return array Schema object
     */
    private static function build_image_object_schema($image_data) {
        $url = isset( $image_data['url'] ) ? esc_url_raw( $image_data['url'] ) : '';
        $alt = isset( $image_data['alt'] ) ? sanitize_text_field( $image_data['alt'] ) : '';
        $title = isset( $image_data['title'] ) ? sanitize_text_field( $image_data['title'] ) : '';
        $caption = isset( $image_data['caption'] ) ? wp_strip_all_tags( $image_data['caption'] ) : '';
        $author = isset( $image_data['author'] ) ? sanitize_text_field( $image_data['author'] ) : '';

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'ImageObject',
            'contentUrl' => $url,
            'description' => $alt,
            'name' => $title,
        ];

        // Add optional properties if available
        if (!empty($image_data['width'])) {
            $schema['width'] = (int) $image_data['width'];
        }

        if (!empty($image_data['height'])) {
            $schema['height'] = (int) $image_data['height'];
        }

        if (!empty($caption)) {
            $schema['caption'] = $caption;
        }

        if (!empty($image_data['date'])) {
            $schema['uploadDate'] = $image_data['date'];
        }

        if (!empty($author)) {
            $schema['author'] = [
                '@type' => 'Person',
                'name' => $author,
            ];
        }

        // Add content location if geo data available
        $geo_lat = get_post_meta(get_post_thumbnail_id(), '_wp_attachment_image_geo_latitude', true);
        $geo_lon = get_post_meta(get_post_thumbnail_id(), '_wp_attachment_image_geo_longitude', true);

        if ($geo_lat && $geo_lon) {
            $schema['contentLocation'] = [
                '@type' => 'Place',
                'geo' => [
                    '@type' => 'GeoCoordinates',
                    'latitude' => (float) $geo_lat,
                    'longitude' => (float) $geo_lon,
                ],
            ];
        }

        // Add license if specified
        $license = get_post_meta(get_post_thumbnail_id(), '_wp_attachment_image_license', true);
        if ($license) {
            $schema['license'] = sanitize_text_field( $license );
        }

        return $schema;
    }

    /**
     * Add settings field to admin
     *
     * @param array $settings Existing settings
     * @return array Modified settings
     */
    public static function add_settings_field($settings) {
        $settings[] = [
            'id' => 'bbai_enable_schema_markup',
            'title' => __('Enable Schema.org Markup', 'opptiai-alt'),
            'desc' => __('Add ImageObject schema markup to images with alt text for enhanced Google Images SEO. Helps images appear in rich results.', 'opptiai-alt'),
            'type' => 'checkbox',
            'default' => true,
        ];

        return $settings;
    }
}

// Initialize schema markup
BBAI_Schema_Markup::init();
