<?php
/**
 * Phase 17 — Marketing content pipeline structures (merge with growth SEO brief via filter).
 *
 * @package BeepBeep_AI
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Article / landing outlines for external CMS or WP-CLI exporters.
 *
 * @return list<array{slug:string,title:string,target_keyword:string,outline:list<string>,cta_wporg:bool}>
 */
function bbai_phase17_content_pipeline_outlines(): array {
    $wporg = 'https://wordpress.org/plugins/beepbeep-ai-alt-text-generator/';
    $base  = [
        [
            'slug'            => 'wordpress-bulk-alt-text',
            'title'           => 'How to bulk-fix missing ALT text in WordPress (without burning a weekend)',
            'target_keyword'  => 'bulk alt text wordpress',
            'outline'         => [
                'Why missing ALT hurts SEO and accessibility',
                'Media Library vs WooCommerce product images',
                'Scan → generate → review workflow',
                'When to use automation on upload',
            ],
            'cta_wporg'       => true,
        ],
        [
            'slug'            => 'google-images-woocommerce',
            'title'           => 'WooCommerce image SEO: ALT text that helps Google Images',
            'target_keyword'  => 'woocommerce image seo',
            'outline'         => [
                'Product image discovery problems',
                'ALT patterns that stay human-readable',
                'Scaling to hundreds of SKUs',
            ],
            'cta_wporg'       => true,
        ],
        [
            'slug'            => 'wcag-alt-text-basics',
            'title'           => 'WCAG-friendly ALT text: what to write (and what to skip)',
            'target_keyword'  => 'wcag alt text',
            'outline'         => [
                'Decorative vs informative images',
                'Length and keyword stuffing',
                'QA checklist before publish',
            ],
            'cta_wporg'       => true,
        ],
    ];

    $merged = apply_filters('bbai_phase17_content_pipeline_outlines', $base);

    foreach ($merged as $i => $row) {
        if (!is_array($row)) {
            unset($merged[ $i ]);
            continue;
        }
        $merged[ $i ]['plugin_url'] = $wporg;
    }

    return array_values(array_filter($merged, 'is_array'));
}
