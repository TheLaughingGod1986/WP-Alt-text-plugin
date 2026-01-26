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

// Load testimonials data
$testimonials_file = dirname(__FILE__) . '/data/testimonials.php';
$testimonials = file_exists($testimonials_file) ? include $testimonials_file : [];

// Get usage stats for social proof
$usage_stats = isset($usage_stats) && is_array($usage_stats) ? $usage_stats : [];
$total_sites = 10000; // This could be dynamic from API
$plugin_url = defined('BEEPBEEP_AI_PLUGIN_URL') ? BEEPBEEP_AI_PLUGIN_URL : (defined('BBAI_PLUGIN_URL') ? BBAI_PLUGIN_URL : '');
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
                        esc_html__('Join %s+ sites', 'beepbeep-ai-alt-text-generator'),
                        esc_html(number_format($total_sites))
                    );
                    ?>
                </span>
                <span class="bbai-trust-badge-subtitle"><?php esc_html_e('Using BeepBeep AI', 'beepbeep-ai-alt-text-generator'); ?></span>
            </span>
        </div>
    </div>

    <!-- Testimonials Carousel -->
    <?php if (!empty($testimonials)) : ?>
    <div class="bbai-testimonials-carousel">
        <div class="bbai-testimonials-viewport" id="bbai-testimonials-viewport">
            <div class="bbai-testimonials-track" id="bbai-testimonials-track">
                <?php foreach ($testimonials as $index => $testimonial) : ?>
                <?php
                $author_name = isset($testimonial['name']) ? $testimonial['name'] : '';
                $author_role = isset($testimonial['role']) ? $testimonial['role'] : '';
                $author_company = isset($testimonial['company']) ? $testimonial['company'] : '';
                $author_title = trim($author_role . ($author_company ? ', ' . $author_company : ''));
                $rating = isset($testimonial['rating']) ? (int) $testimonial['rating'] : 5;
                $avatar = isset($testimonial['avatar']) ? $testimonial['avatar'] : '';
                if (!empty($avatar)) {
                    $avatar_is_relative = strpos($avatar, 'data:') !== 0
                        && !preg_match('#^(?:https?:)?//#', $avatar)
                        && $avatar[0] !== '/';
                    if ($avatar_is_relative) {
                        $avatar = $plugin_url . ltrim($avatar, '/');
                    }
                }
                $name_parts = preg_split('/\s+/', trim($author_name));
                $initials = '';
                if (!empty($name_parts[0])) {
                    $initials = strtoupper(substr($name_parts[0], 0, 1));
                    if (!empty($name_parts[1])) {
                        $initials .= strtoupper(substr($name_parts[1], 0, 1));
                    }
                }
                if ($initials === '') {
                    $initials = 'AI';
                }
                $rating_label = sprintf(
                    esc_attr__('%d out of 5 stars', 'beepbeep-ai-alt-text-generator'),
                    $rating
                );
                ?>
                <div class="bbai-testimonial-card <?php echo esc_attr( $index === 0 ? 'active' : '' ); ?>" data-index="<?php echo esc_attr($index); ?>">
                    <article class="bbai-testimonial-shell">
                        <header class="bbai-testimonial-header">
                            <div class="bbai-testimonial-avatar" aria-hidden="true">
                                <?php if (!empty($avatar)) : ?>
                                    <img src="<?php echo esc_url($avatar); ?>" alt="" loading="lazy"/>
                                <?php else : ?>
                                    <div class="bbai-testimonial-avatar-fallback" aria-hidden="true">
                                        <?php echo esc_html($initials); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="bbai-testimonial-author">
                                <strong class="bbai-testimonial-author-name"><?php echo esc_html($author_name); ?></strong>
                                <?php if ($author_title !== '') : ?>
                                    <span class="bbai-testimonial-author-role"><?php echo esc_html($author_title); ?></span>
                                <?php endif; ?>
                            </div>
                        </header>
                        <blockquote class="bbai-testimonial-quote">
                            <span class="bbai-testimonial-quote-mark" aria-hidden="true">&ldquo;</span>
                            <p><?php echo esc_html($testimonial['quote']); ?></p>
                        </blockquote>
                        <div class="bbai-testimonial-meta">
                            <div class="bbai-testimonial-stars" role="img" aria-label="<?php echo esc_attr($rating_label); ?>">
                                <?php for ($i = 0; $i < 5; $i++) : ?>
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" class="bbai-testimonial-star <?php echo esc_attr( $i < $rating ? 'is-filled' : 'is-empty' ); ?>" aria-hidden="true">
                                        <path d="M8 1L10 5L14 6L11 9L11.5 13L8 11L4.5 13L5 9L2 6L6 5L8 1Z"/>
                                    </svg>
                                <?php endfor; ?>
                            </div>
                            <p class="bbai-testimonial-tagline"><?php esc_html_e('Alt text automation for SEO & accessibility teams.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        </div>
                    </article>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
