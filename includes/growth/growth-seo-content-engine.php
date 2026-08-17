<?php
/**
 * Phase 16 — SEO & content engine data for marketing site / blog (no runtime UI).
 *
 * Consume via bbai_growth_seo_engine_data() from theme, headless CMS, or export scripts.
 *
 * @package BeepBeep_AI
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return array<string, mixed>
 */
function bbai_growth_seo_engine_data(): array {
    $plugin_url = 'https://wordpress.org/plugins/beepbeep-ai-alt-text-generator/';
    $support    = 'https://wordpress.org/support/plugin/beepbeep-ai-alt-text-generator/';

    $data = [
        'primary_keywords' => [
            'ai alt text wordpress',
            'woocommerce image alt text',
            'bulk alt text generator',
            'wordpress accessibility alt text',
            'google images seo wordpress',
            'missing alt text fix',
            'media library alt text',
            'wcag alt text',
        ],
        'long_tail_clusters' => [
            'woocommerce' => [
                'woocommerce product image alt text bulk',
                'woocommerce seo images',
                'woocommerce accessibility images',
            ],
            'seo' => [
                'how to improve google image search wordpress',
                'image seo best practices wordpress',
                'alt text for featured images',
            ],
            'accessibility' => [
                'wordpress wcag image descriptions',
                'ada compliant alt text',
            ],
        ],
        'pillar_pages' => [
            [
                'slug'        => 'wordpress-alt-text-guide',
                'title'       => 'Complete guide to WordPress ALT text (SEO + accessibility)',
                'target_kw'   => 'wordpress alt text',
                'internal_to' => [$plugin_url],
            ],
            [
                'slug'        => 'woocommerce-image-seo',
                'title'       => 'WooCommerce image SEO: ALT text at scale',
                'target_kw'   => 'woocommerce image seo',
                'internal_to' => [$plugin_url, $support],
            ],
            [
                'slug'        => 'google-images-wordpress',
                'title'       => 'How ALT text affects Google Images visibility',
                'target_kw'   => 'google images seo',
                'internal_to' => [$plugin_url],
            ],
        ],
        'article_topics' => [
            'Checklist: auditing missing ALT text on an existing WordPress site',
            'Before/after: manual ALT vs AI-assisted workflows for large catalogs',
            'How often to regenerate ALT when you replace product photos',
            'ALT text length: what search engines and screen readers actually need',
            'Bulk editing ALT in the block editor vs media library workflows',
        ],
        'landing_page_templates' => [
            'hero_value'     => 'Fix missing ALT text across WordPress & WooCommerce in minutes — AI-assisted, review before publish.',
            'proof_points'   => ['WCAG-friendly workflow', 'Image SEO & Google Images', 'Bulk + on-upload automation on paid plans'],
            'primary_cta'    => 'Install from WordPress.org',
            'secondary_cta'    => 'View live demo / screenshots',
            'wporg_link'     => $plugin_url,
        ],
        'internal_linking_rules' => [
            'Every commercial landing page links to the WordPress.org plugin page with branded anchor (e.g. “BeepBeep AI on WordPress.org”).',
            'Blog posts link pillar → cluster articles → plugin page in footer CTA.',
            'Use “ALT text” and “alternative text” variants naturally; avoid duplicate anchors sitewide.',
        ],
        'community_elevator_pitch' => 'We built an AI ALT text plugin for WordPress/WooCommerce that bulk-fills gaps and optional on-upload automation — happy to share what we learned about image SEO if useful.',
        'distribution_channels' => [
            'wordpress_org_support' => $support,
            'reddit'                => ['r/wordpress', 'r/SEO', 'r/woocommerce'],
            'wp_org_forums'         => 'https://wordpress.org/support/forums/',
        ],
        'metrics_definitions' => [
            'install_velocity'    => 'Net active installs change / day from WordPress.org stats (export weekly).',
            'activation_proxy'   => 'First successful generation or bbai_growth_installed_at → first alt telemetry (client/server).',
            'review_rate'        => 'New 5★ reviews / month ÷ estimated active engaged admins (usage heuristic).',
            'organic_sessions'   => 'Landing + blog organic sessions from GA4 / Plausible with landing group /plugin.',
        ],
    ];

    return apply_filters('bbai_growth_seo_engine_data', $data);
}
