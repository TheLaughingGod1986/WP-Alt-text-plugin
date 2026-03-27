<?php
/**
 * Social Proof Widget
 * Testimonials carousel and trust badges
 *
 * @package BeepBeep_AI
 * @since 6.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('bbai_get_social_proof_avatar_pool')) {
    /**
     * Shared avatar pool used to anonymize reviewer identities.
     *
     * @return array<int, string>
     */
    function bbai_get_social_proof_avatar_pool() {
        return [
            'assets/img/testimonials/jessica.svg',
            'assets/img/testimonials/ryan.svg',
            'assets/img/testimonials/aisha.svg',
            'assets/img/testimonials/chris.svg',
            'assets/img/testimonials/maria.svg',
            'assets/img/testimonials/tom.svg',
        ];
    }
}

if (!function_exists('bbai_prepare_social_proof_reviews')) {
    /**
     * Normalize social proof cards: 5-star only, pseudonyms, anonymized avatars, and short quotes.
     *
     * @param array<int, array<string, mixed>> $reviews    Raw review rows.
     * @param int                              $limit      Number of reviews to return.
     * @param int                              $max_words  Maximum quote words.
     * @return array<int, array<string, mixed>>
     */
    function bbai_prepare_social_proof_reviews($reviews, $limit = 6, $max_words = 28) {
        $limit = max(1, absint($limit));
        $max_words = max(8, absint($max_words));
        if (!is_array($reviews) || empty($reviews)) {
            return [];
        }

        $bbai_pseudonyms = [
            __('Alex R.', 'beepbeep-ai-alt-text-generator'),
            __('Jordan K.', 'beepbeep-ai-alt-text-generator'),
            __('Taylor M.', 'beepbeep-ai-alt-text-generator'),
            __('Casey P.', 'beepbeep-ai-alt-text-generator'),
            __('Morgan S.', 'beepbeep-ai-alt-text-generator'),
            __('Riley T.', 'beepbeep-ai-alt-text-generator'),
            __('Sam C.', 'beepbeep-ai-alt-text-generator'),
            __('Jamie L.', 'beepbeep-ai-alt-text-generator'),
        ];
        $bbai_roles = [
            __('Verified Store Owner', 'beepbeep-ai-alt-text-generator'),
            __('Verified Marketing Team', 'beepbeep-ai-alt-text-generator'),
            __('Verified Developer', 'beepbeep-ai-alt-text-generator'),
            __('Verified Content Team', 'beepbeep-ai-alt-text-generator'),
            __('Verified Accessibility Lead', 'beepbeep-ai-alt-text-generator'),
        ];
        $bbai_taglines = [
            __('Accessibility workflow simplified.', 'beepbeep-ai-alt-text-generator'),
            __('Bulk updates without manual rewrites.', 'beepbeep-ai-alt-text-generator'),
            __('Consistent alt text quality at scale.', 'beepbeep-ai-alt-text-generator'),
            __('Cleaner image metadata with less effort.', 'beepbeep-ai-alt-text-generator'),
            __('Faster media-library optimization.', 'beepbeep-ai-alt-text-generator'),
            __('Alt text automation for SEO & accessibility teams.', 'beepbeep-ai-alt-text-generator'),
            __('Saves hours of manual work.', 'beepbeep-ai-alt-text-generator'),
            __('WCAG compliance made easy.', 'beepbeep-ai-alt-text-generator'),
        ];
        $bbai_avatars = bbai_get_social_proof_avatar_pool();

        $prepared = [];
        $bbai_alias_index = 0;

        foreach ($reviews as $idx => $review) {
            if (!is_array($review)) {
                continue;
            }

            $rating = isset($review['rating']) ? (int) $review['rating'] : 0;
            if ($rating !== 5) {
                continue;
            }

            $raw_quote = isset($review['quote']) ? sanitize_text_field((string) $review['quote']) : '';
            if ($raw_quote === '') {
                continue;
            }

            $quote = wp_trim_words($raw_quote, $max_words, '…');
            $seed = (string) ($review['name'] ?? '') . '|' . $raw_quote . '|' . (string) $idx;
            $hash = abs(crc32($seed));
            $tagline_hash = abs(crc32($seed . '_tag'));

            $name = $bbai_pseudonyms[$bbai_alias_index % count($bbai_pseudonyms)];
            $bbai_alias_index++;
            $role = $bbai_roles[$hash % count($bbai_roles)];
            $avatar = !empty($bbai_avatars) ? $bbai_avatars[$hash % count($bbai_avatars)] : '';
            $tagline = $bbai_taglines[$tagline_hash % count($bbai_taglines)];

            $prepared[] = [
                'name' => $name,
                'role' => $role,
                'company' => '',
                'avatar' => $avatar,
                'quote' => $quote,
                'rating' => 5,
                'tagline' => $tagline,
            ];

            if (count($prepared) >= $limit) {
                break;
            }
        }

        return $prepared;
    }
}

if (!function_exists('bbai_get_wporg_reviews_for_social_proof')) {
    /**
     * Fetch WordPress.org plugin review snippets for social proof cards.
     *
     * @param int $limit Number of reviews to return.
     * @return array<int, array<string, mixed>>
     */
    function bbai_get_wporg_reviews_for_social_proof($limit = 6) {
        $limit = max(1, absint($limit));
        $cache_key = 'bbai_wporg_reviews_social_proof_v3';
        $cached = get_transient($cache_key);

        if (is_array($cached) && !empty($cached)) {
            return bbai_prepare_social_proof_reviews($cached, $limit);
        }

        $snapshot_file = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/data/wporg-reviews-snapshot.php';
        $snapshot = file_exists($snapshot_file) ? include $snapshot_file : [];

        $response = wp_remote_get(
            'https://wordpress.org/support/plugin/beepbeep-ai-alt-text-generator/reviews/feed/',
            [
                'timeout'   => 12,
                'sslverify' => true,
            ]
        );

        if (is_wp_error($response)) {
            return is_array($snapshot) ? bbai_prepare_social_proof_reviews($snapshot, $limit) : [];
        }

        $body = wp_remote_retrieve_body($response);
        if (!is_string($body) || trim($body) === '') {
            return is_array($snapshot) ? bbai_prepare_social_proof_reviews($snapshot, $limit) : [];
        }

        if (!function_exists('simplexml_load_string')) {
            return is_array($snapshot) ? bbai_prepare_social_proof_reviews($snapshot, $limit) : [];
        }

        $bbai_prev_use_internal_errors = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_clear_errors();
        libxml_use_internal_errors($bbai_prev_use_internal_errors);
        if (!$xml || !isset($xml->channel->item)) {
            return is_array($snapshot) ? bbai_prepare_social_proof_reviews($snapshot, $limit) : [];
        }

        $reviews = [];
        foreach ($xml->channel->item as $item) {
            $title = isset($item->title) ? (string) $item->title : '';
            $creator = isset($item->children('http://purl.org/dc/elements/1.1/')->creator)
                ? (string) $item->children('http://purl.org/dc/elements/1.1/')->creator
                : '';
            $description = isset($item->description) ? (string) $item->description : '';

            $rating = 5;
            if (preg_match('/\((\d)\s+stars?\)/i', $title, $m)) {
                $rating = (int) $m[1];
            } elseif (preg_match('/Rating:\s*(\d)\s*stars?/i', $description, $m)) {
                $rating = (int) $m[1];
            }
            $rating = max(1, min(5, $rating));
            if ($rating !== 5) {
                continue;
            }

            $quote = wp_strip_all_tags($description);
            $quote = preg_replace('/Replies:\s*\d+/i', '', $quote);
            $quote = preg_replace('/Rating:\s*\d+\s*stars?/i', '', $quote);
            $quote = trim(preg_replace('/\s+/', ' ', (string) $quote));

            if ($quote === '' || $creator === '') {
                continue;
            }

            $reviews[] = [
                'name' => sanitize_text_field($creator),
                'role' => __('WordPress.org Reviewer', 'beepbeep-ai-alt-text-generator'),
                'company' => '',
                'avatar' => '',
                'quote' => sanitize_text_field($quote),
                'rating' => 5,
            ];
        }

        if (!empty($reviews)) {
            $reviews = bbai_prepare_social_proof_reviews($reviews, $limit);
            set_transient($cache_key, $reviews, 12 * HOUR_IN_SECONDS);
        } elseif (is_array($snapshot) && !empty($snapshot)) {
            $reviews = bbai_prepare_social_proof_reviews($snapshot, $limit);
        }

        return array_slice($reviews, 0, $limit);
    }
}

// Load local fallback testimonials data.
$bbai_testimonials_file = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/data/testimonials.php';
$bbai_fallback_testimonials = file_exists($bbai_testimonials_file) ? include $bbai_testimonials_file : [];
$bbai_fallback_testimonials = is_array($bbai_fallback_testimonials) ? $bbai_fallback_testimonials : [];
$bbai_fallback_testimonials = bbai_prepare_social_proof_reviews($bbai_fallback_testimonials, max(1, count($bbai_fallback_testimonials)));
$bbai_testimonials = [];

// Prefer real WordPress.org reviews, then fill remaining slots with local fallback cards.
$bbai_max_testimonials = max(3, count($bbai_fallback_testimonials));
$bbai_real_reviews = bbai_get_wporg_reviews_for_social_proof($bbai_max_testimonials);
if (!empty($bbai_real_reviews)) {
    $bbai_testimonials = $bbai_real_reviews;
}
if (count($bbai_testimonials) < $bbai_max_testimonials && !empty($bbai_fallback_testimonials)) {
    $bbai_needed = $bbai_max_testimonials - count($bbai_testimonials);
    $bbai_testimonials = array_merge($bbai_testimonials, array_slice($bbai_fallback_testimonials, 0, $bbai_needed));
}
if (empty($bbai_testimonials)) {
    $bbai_testimonials = $bbai_fallback_testimonials;
}

// Get usage stats for social proof
$bbai_usage_stats = isset($bbai_usage_stats) && is_array($bbai_usage_stats) ? $bbai_usage_stats : [];
$bbai_total_sites = 10000; // This could be dynamic from API
$bbai_plugin_url = defined('BEEPBEEP_AI_PLUGIN_URL') ? BEEPBEEP_AI_PLUGIN_URL : '';
?>

<div class="bbai-social-proof-widget">
    <!-- Trust Badges -->
    <div class="bbai-trust-badges">
        <div class="bbai-trust-badge bbai-trust-badge--wcag">
            <span class="bbai-trust-badge-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                </svg>
            </span>
            <span class="bbai-trust-badge-text">
                <span class="bbai-trust-badge-title"><?php esc_html_e('WCAG Compliant', 'beepbeep-ai-alt-text-generator'); ?></span>
            </span>
        </div>
        <div class="bbai-trust-badge bbai-trust-badge--gdpr">
            <span class="bbai-trust-badge-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z" stroke="currentColor" stroke-width="2"/>
                </svg>
            </span>
            <span class="bbai-trust-badge-text">
                <span class="bbai-trust-badge-title"><?php esc_html_e('GDPR Ready', 'beepbeep-ai-alt-text-generator'); ?></span>
            </span>
        </div>
        <div class="bbai-trust-badge bbai-trust-badge--uptime">
            <span class="bbai-trust-badge-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <path d="M12 2v20M2 12h20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <circle cx="12" cy="12" r="3" fill="currentColor"/>
                </svg>
            </span>
            <span class="bbai-trust-badge-text">
                <span class="bbai-trust-badge-title"><?php esc_html_e('99.9% Uptime', 'beepbeep-ai-alt-text-generator'); ?></span>
            </span>
        </div>
        <div class="bbai-trust-badge bbai-trust-badge--users">
            <span class="bbai-trust-badge-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
                    <path d="M23 21v-2a4 4 0 00-3-3.87" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M16 3.13a4 4 0 010 7.75" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
            <span class="bbai-trust-badge-text">
                    <span class="bbai-trust-badge-title">
                    <?php
                    printf(
                        /* translators: 1: site count */
                        esc_html__('Join %s+ sites', 'beepbeep-ai-alt-text-generator'),
                        esc_html(number_format($bbai_total_sites))
                    );
                    ?>
                </span>
                <span class="bbai-trust-badge-subtitle"><?php esc_html_e('Using BeepBeep AI', 'beepbeep-ai-alt-text-generator'); ?></span>
            </span>
        </div>
    </div>

    <!-- Testimonials Carousel -->
    <?php if (!empty($bbai_testimonials)) : ?>
    <div class="bbai-testimonials-carousel">
        <div class="bbai-testimonials-viewport" id="bbai-testimonials-viewport">
            <div class="bbai-testimonials-track" id="bbai-testimonials-track">
                <?php foreach ($bbai_testimonials as $bbai_index => $bbai_testimonial) : ?>
                <?php
                $bbai_author_name = isset($bbai_testimonial['name']) ? $bbai_testimonial['name'] : '';
                $bbai_author_role = isset($bbai_testimonial['role']) ? $bbai_testimonial['role'] : '';
                $bbai_author_company = isset($bbai_testimonial['company']) ? $bbai_testimonial['company'] : '';
                $bbai_author_title = trim($bbai_author_role . ($bbai_author_company ? ', ' . $bbai_author_company : ''));
                $bbai_rating = isset($bbai_testimonial['rating']) ? (int) $bbai_testimonial['rating'] : 5;
                $bbai_card_tagline = isset($bbai_testimonial['tagline']) ? sanitize_text_field((string) $bbai_testimonial['tagline']) : '';
                $bbai_avatar = isset($bbai_testimonial['avatar']) ? $bbai_testimonial['avatar'] : '';
                if (!empty($bbai_avatar)) {
                    $bbai_avatar_is_relative = strpos($bbai_avatar, 'data:') !== 0
                        && !preg_match('#^(?:https?:)?//#', $bbai_avatar)
                        && $bbai_avatar[0] !== '/';
                    if ($bbai_avatar_is_relative) {
                        $bbai_avatar = $bbai_plugin_url . ltrim($bbai_avatar, '/');
                    }
                }
                $bbai_name_parts = preg_split('/\s+/', trim($bbai_author_name));
                $bbai_initials = '';
                if (!empty($bbai_name_parts[0])) {
                    $bbai_initials = strtoupper(substr($bbai_name_parts[0], 0, 1));
                    if (!empty($bbai_name_parts[1])) {
                        $bbai_initials .= strtoupper(substr($bbai_name_parts[1], 0, 1));
                    }
                }
                if ($bbai_initials === '') {
                    $bbai_initials = 'AI';
                }
                $bbai_rating_label = sprintf(
                    /* translators: 1: star rating */
                    esc_attr__('%d out of 5 stars', 'beepbeep-ai-alt-text-generator'),
                    $bbai_rating
                );
                ?>
                <div class="bbai-testimonial-card <?php echo esc_attr( $bbai_index === 0 ? 'active' : '' ); ?>" data-index="<?php echo esc_attr($bbai_index); ?>">
                    <article class="bbai-testimonial-shell">
                        <header class="bbai-testimonial-header">
                            <div class="bbai-testimonial-avatar" aria-hidden="true">
                                <?php if (!empty($bbai_avatar)) : ?>
                                    <img src="<?php echo esc_url($bbai_avatar); ?>" alt="" loading="lazy"/>
                                <?php else : ?>
                                    <div class="bbai-testimonial-avatar-fallback" aria-hidden="true">
                                        <?php echo esc_html($bbai_initials); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="bbai-testimonial-author">
                                <strong class="bbai-testimonial-author-name"><?php echo esc_html($bbai_author_name); ?></strong>
                                <?php if ($bbai_author_title !== '') : ?>
                                    <span class="bbai-testimonial-author-role"><?php echo esc_html($bbai_author_title); ?></span>
                                <?php endif; ?>
                            </div>
                        </header>
                        <blockquote class="bbai-testimonial-quote">
                            <span class="bbai-testimonial-quote-mark" aria-hidden="true">&ldquo;</span>
                            <p><?php echo esc_html($bbai_testimonial['quote']); ?></p>
                        </blockquote>
                        <div class="bbai-testimonial-meta">
                            <div class="bbai-testimonial-stars" role="img" aria-label="<?php echo esc_attr($bbai_rating_label); ?>">
                                <?php for ($bbai_i = 0; $bbai_i < 5; $bbai_i++) : ?>
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" class="bbai-testimonial-star <?php echo esc_attr( $bbai_i < $bbai_rating ? 'is-filled' : 'is-empty' ); ?>" aria-hidden="true">
                                        <path d="M8 1L10 5L14 6L11 9L11.5 13L8 11L4.5 13L5 9L2 6L6 5L8 1Z"/>
                                    </svg>
                                <?php endfor; ?>
                            </div>
                            <?php if ($bbai_card_tagline !== '') : ?>
                                <p class="bbai-testimonial-tagline"><?php echo esc_html($bbai_card_tagline); ?></p>
                            <?php endif; ?>
                        </div>
                    </article>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
