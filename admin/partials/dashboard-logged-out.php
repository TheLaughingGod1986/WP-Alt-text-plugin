<?php
/**
 * Logged-Out Dashboard State
 * Clean onboarding screen for unauthenticated users.
 * Includes trial mode banner when free generations remain.
 *
 * @package BeepBeep_AI
 * @since 6.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-trial-quota.php';
$bbai_trial_remaining = \BeepBeepAI\AltTextGenerator\Trial_Quota::get_remaining();
$bbai_trial_limit     = \BeepBeepAI\AltTextGenerator\Trial_Quota::get_limit();
$bbai_trial_exhausted = \BeepBeepAI\AltTextGenerator\Trial_Quota::is_exhausted();

// Count images missing alt text.
$bbai_missing_alt_count = (int) $GLOBALS['wpdb']->get_var(
	"SELECT COUNT(*) FROM {$GLOBALS['wpdb']->posts} p
	 LEFT JOIN {$GLOBALS['wpdb']->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
	 WHERE p.post_type = 'attachment'
	 AND p.post_mime_type LIKE 'image/%'
	 AND p.post_status = 'inherit'
	 AND (pm.meta_value IS NULL OR pm.meta_value = '')"
); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time count for UI display.
$bbai_missing_alt_count_display = number_format_i18n( (int) $bbai_missing_alt_count );
$bbai_images_to_fix             = (int) min( $bbai_missing_alt_count, $bbai_trial_remaining );
$bbai_images_to_fix_display     = number_format_i18n( $bbai_images_to_fix );

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
$bbai_page_input = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : 'bbai';
$bbai_current_page = $bbai_page_input ?: 'bbai';
$bbai_fallback_url = admin_url('admin.php?page=' . $bbai_current_page);
?>
<div class="bbai-logged-out" role="main" aria-labelledby="bbai-logged-out-title">
    <div class="bbai-logged-out__container">
        <div class="bbai-logged-out__card">
            <?php if ( ! $bbai_trial_exhausted ) : ?>
            <!-- Trial banner: show remaining free generations -->
            <div class="bbai-trial-banner" role="status" aria-live="polite">
                <span class="bbai-trial-banner__icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 2L12.09 7.26L18 8.27L14 12.14L14.18 18.02L10 15.77L5.82 18.02L6 12.14L2 8.27L7.91 7.26L10 2Z" fill="currentColor"/></svg>
                </span>
                <span class="bbai-trial-banner__text">
                    <?php
                    printf(
                        /* translators: 1: remaining count, 2: total limit */
                        esc_html__( 'Trial: %1$d of %2$d free generations remaining', 'beepbeep-ai-alt-text-generator' ),
                        (int) $bbai_trial_remaining,
                        (int) $bbai_trial_limit
                    );
                    ?>
                </span>
            </div>
            <?php endif; ?>

            <header class="bbai-logged-out__header">
                <h1 id="bbai-logged-out-title" class="bbai-logged-out__title">
                    <?php if ( $bbai_trial_exhausted ) : ?>
                    <span class="bbai-logged-out__title-line">
                        <?php esc_html_e( "You've used your free trial", 'beepbeep-ai-alt-text-generator' ); ?>
                    </span>
                    <?php else : ?>
                    <span class="bbai-logged-out__title-line">
                        <?php esc_html_e('Get found in Google Images', 'beepbeep-ai-alt-text-generator'); ?>
                    </span>
                    <span class="bbai-logged-out__title-line">
                        <?php esc_html_e('with complete alt text', 'beepbeep-ai-alt-text-generator'); ?>
                    </span>
                    <?php endif; ?>
                </h1>
                <?php if ( ! $bbai_trial_exhausted && $bbai_missing_alt_count > 0 ) : ?>
                <div class="bbai-logged-out__missing-alert" role="status">
                    <span class="bbai-logged-out__missing-badge" id="bbai-missing-badge"><?php echo esc_html( $bbai_missing_alt_count_display ); ?></span>
                    <span class="bbai-logged-out__missing-text" id="bbai-missing-text">
                        <?php
                        echo esc_html(
                            _n(
                                'image missing alt text',
                                'images missing alt text',
                                (int) $bbai_missing_alt_count,
                                'beepbeep-ai-alt-text-generator'
                            )
                        );
                        ?>
                    </span>
                </div>
                <p class="bbai-logged-out__subtitle">
                    <?php esc_html_e('Generate SEO-ready descriptions directly from WordPress.', 'beepbeep-ai-alt-text-generator'); ?>
                </p>
                <?php else : ?>
                <p class="bbai-logged-out__subtitle">
                    <?php if ( $bbai_trial_exhausted ) : ?>
                        <?php esc_html_e('Create a free account to unlock 50 more credits per month.', 'beepbeep-ai-alt-text-generator'); ?>
                    <?php else : ?>
                        <?php esc_html_e('Generate SEO-ready, accessible alt text for your entire media library — directly from WordPress.', 'beepbeep-ai-alt-text-generator'); ?>
                    <?php endif; ?>
                </p>
                <?php endif; ?>
            </header>

            <div class="bbai-logged-out__actions">
                <?php if ( ! $bbai_trial_exhausted ) : ?>
                <button
                    type="button"
                    class="bbai-logged-out__btn-primary"
                    id="bbai-trial-generate-btn"
                >
                    <?php
                    if ( $bbai_missing_alt_count > 0 ) {
                        printf(
                            /* translators: 1: number of images to fix, 2: remaining free generations. */
                            esc_html__( 'Fix %1$s images free — %2$s left', 'beepbeep-ai-alt-text-generator' ),
                            esc_html( $bbai_images_to_fix_display ),
                            esc_html( number_format_i18n( (int) $bbai_trial_remaining ) )
                        );
                    } else {
                        printf(
                            /* translators: %s: remaining free trial generations. */
                            esc_html__( 'Generate alt text free — %s left', 'beepbeep-ai-alt-text-generator' ),
                            esc_html( number_format_i18n( (int) $bbai_trial_remaining ) )
                        );
                    }
                    ?>
                </button>
                <a
                    class="bbai-logged-out__link-secondary"
                    href="<?php echo esc_url($bbai_fallback_url); ?>"
                    data-action="show-auth-modal"
                    data-auth-tab="register"
                >
                    <?php esc_html_e('Create free account', 'beepbeep-ai-alt-text-generator'); ?>
                </a>
                <?php else : ?>
                <a
                    class="bbai-logged-out__btn-primary"
                    href="<?php echo esc_url($bbai_fallback_url); ?>"
                    data-action="show-auth-modal"
                    data-auth-tab="register"
                >
                    <?php esc_html_e('Create free account', 'beepbeep-ai-alt-text-generator'); ?>
                </a>
                <a
                    class="bbai-logged-out__link-secondary"
                    href="<?php echo esc_url($bbai_fallback_url); ?>"
                    data-action="show-upgrade-modal"
                >
                    <?php esc_html_e('View plans', 'beepbeep-ai-alt-text-generator'); ?>
                </a>
                <?php endif; ?>
            </div>

            <div class="bbai-logged-out__divider" aria-hidden="true"></div>

            <section class="bbai-logged-out__benefits" aria-label="<?php esc_attr_e('Benefits', 'beepbeep-ai-alt-text-generator'); ?>">
                <ul class="bbai-logged-out__benefits-list">
                    <li class="bbai-logged-out__benefit">
                        <span class="bbai-logged-out__benefit-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                        </span>
                        <span class="bbai-logged-out__benefit-text">
                            <strong><?php esc_html_e('Rank in Google Images', 'beepbeep-ai-alt-text-generator'); ?></strong>
                            <?php esc_html_e('— descriptive alt text helps images appear in search', 'beepbeep-ai-alt-text-generator'); ?>
                        </span>
                    </li>
                    <li class="bbai-logged-out__benefit">
                        <span class="bbai-logged-out__benefit-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                        </span>
                        <span class="bbai-logged-out__benefit-text">
                            <strong><?php esc_html_e('Meet accessibility standards', 'beepbeep-ai-alt-text-generator'); ?></strong>
                            <?php esc_html_e('— WCAG-compliant descriptions for screen readers', 'beepbeep-ai-alt-text-generator'); ?>
                        </span>
                    </li>
                    <li class="bbai-logged-out__benefit">
                        <span class="bbai-logged-out__benefit-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                        </span>
                        <span class="bbai-logged-out__benefit-text">
                            <strong><?php esc_html_e('Save hours of writing', 'beepbeep-ai-alt-text-generator'); ?></strong>
                            <?php esc_html_e('— generate alt text in seconds, not minutes per image', 'beepbeep-ai-alt-text-generator'); ?>
                        </span>
                    </li>
                </ul>
            </section>

            <?php if ( ! $bbai_trial_exhausted ) : ?>
            <section class="bbai-logged-out__how-it-works" aria-labelledby="bbai-how-it-works-title">
                <h2 id="bbai-how-it-works-title" class="bbai-logged-out__section-title">
                    <?php esc_html_e('How it works', 'beepbeep-ai-alt-text-generator'); ?>
                </h2>

                <div class="bbai-logged-out__steps">
                    <div class="bbai-logged-out__step">
                        <div class="bbai-logged-out__step-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" role="presentation" focusable="false">
                                <rect x="4" y="5" width="16" height="14" rx="2" fill="none" stroke="currentColor" stroke-width="1.6" />
                                <path d="M8 13l2.5 3 3.5-4 4 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                <circle cx="9" cy="9" r="1.5" fill="currentColor" />
                            </svg>
                        </div>
                        <p class="bbai-logged-out__step-title">
                            <?php esc_html_e('Click generate', 'beepbeep-ai-alt-text-generator'); ?>
                        </p>
                        <p class="bbai-logged-out__step-desc">
                            <?php esc_html_e('No account needed for your first 10', 'beepbeep-ai-alt-text-generator'); ?>
                        </p>
                    </div>

                    <div class="bbai-logged-out__step">
                        <div class="bbai-logged-out__step-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" role="presentation" focusable="false">
                                <path d="M12 2L15 8.5L22 9.5L17 14L18.5 21L12 17.5L5.5 21L7 14L2 9.5L9 8.5L12 2Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <p class="bbai-logged-out__step-title">
                            <?php esc_html_e('AI generates alt text', 'beepbeep-ai-alt-text-generator'); ?>
                        </p>
                        <p class="bbai-logged-out__step-desc">
                            <?php esc_html_e('Watch as each image gets described', 'beepbeep-ai-alt-text-generator'); ?>
                        </p>
                    </div>

                    <div class="bbai-logged-out__step">
                        <div class="bbai-logged-out__step-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" role="presentation" focusable="false">
                                <rect x="5" y="10" width="14" height="10" rx="2" fill="none" stroke="currentColor" stroke-width="1.6" />
                                <path d="M8 10V7a4 4 0 0 1 8 0v3" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                                <circle cx="12" cy="15" r="1.5" fill="currentColor" />
                            </svg>
                        </div>
                        <p class="bbai-logged-out__step-title">
                            <?php esc_html_e('Create account for more', 'beepbeep-ai-alt-text-generator'); ?>
                        </p>
                        <p class="bbai-logged-out__step-desc">
                            <?php esc_html_e('Unlock 50/month free, including bulk generation tools', 'beepbeep-ai-alt-text-generator'); ?>
                        </p>
                    </div>
                </div>
            </section>
            <?php else : ?>
            <section class="bbai-logged-out__how-it-works" aria-labelledby="bbai-how-it-works-title">
                <h2 id="bbai-how-it-works-title" class="bbai-logged-out__section-title">
                    <?php esc_html_e('How it works', 'beepbeep-ai-alt-text-generator'); ?>
                </h2>

                <div class="bbai-logged-out__steps">
                    <div class="bbai-logged-out__step">
                        <div class="bbai-logged-out__step-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" role="presentation" focusable="false">
                                <rect x="5" y="10" width="14" height="10" rx="2" fill="none" stroke="currentColor" stroke-width="1.6" />
                                <path d="M8 10V7a4 4 0 0 1 8 0v3" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                                <circle cx="12" cy="15" r="1.5" fill="currentColor" />
                            </svg>
                        </div>
                        <p class="bbai-logged-out__step-title">
                            <?php esc_html_e('Create your free account', 'beepbeep-ai-alt-text-generator'); ?>
                        </p>
                        <p class="bbai-logged-out__step-desc">
                            <?php esc_html_e('No credit card required', 'beepbeep-ai-alt-text-generator'); ?>
                        </p>
                    </div>

                    <div class="bbai-logged-out__step">
                        <div class="bbai-logged-out__step-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" role="presentation" focusable="false">
                                <rect x="4" y="5" width="16" height="14" rx="2" fill="none" stroke="currentColor" stroke-width="1.6" />
                                <path d="M8 13l2.5 3 3.5-4 4 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                <circle cx="9" cy="9" r="1.5" fill="currentColor" />
                            </svg>
                        </div>
                        <p class="bbai-logged-out__step-title">
                            <?php esc_html_e('Generate alt text', 'beepbeep-ai-alt-text-generator'); ?>
                        </p>
                        <p class="bbai-logged-out__step-desc">
                            <?php esc_html_e('Select images or process your entire library', 'beepbeep-ai-alt-text-generator'); ?>
                        </p>
                    </div>

                    <div class="bbai-logged-out__step">
                        <div class="bbai-logged-out__step-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" role="presentation" focusable="false">
                                <polyline points="20 6 9 17 4 12" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <p class="bbai-logged-out__step-title">
                            <?php esc_html_e('Improve SEO instantly', 'beepbeep-ai-alt-text-generator'); ?>
                        </p>
                        <p class="bbai-logged-out__step-desc">
                            <?php esc_html_e('Alt text is saved directly to your images', 'beepbeep-ai-alt-text-generator'); ?>
                        </p>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <p class="bbai-logged-out__supporting-line">
                <?php if ( $bbai_trial_exhausted ) : ?>
                    <?php esc_html_e('Free plan includes 50 images per month with bulk generation tools. Growth includes 1,000.', 'beepbeep-ai-alt-text-generator'); ?>
                <?php else : ?>
                    <?php esc_html_e('Try 10 generations now, no account needed; sign up for 50/month.', 'beepbeep-ai-alt-text-generator'); ?>
                <?php endif; ?>
            </p>

            <div class="bbai-logged-out__pill" role="note">
                <span class="bbai-logged-out__pill-icon" aria-hidden="true">
                    <svg viewBox="0 0 122.52 122.523" role="presentation" focusable="false">
                        <g fill="currentColor">
                            <path d="M8.708,61.26c0,20.802,12.089,38.779,29.619,47.298L13.258,39.872C10.342,46.408,8.708,53.63,8.708,61.26z"/>
                            <path d="M96.74,58.608c0-6.495-2.333-10.993-4.334-14.494c-2.664-4.329-5.161-7.995-5.161-12.324 c0-4.831,3.664-9.328,8.825-9.328c0.233,0,0.454,0.029,0.681,0.042c-9.35-8.566-21.807-13.796-35.489-13.796 c-18.36,0-34.513,9.42-43.91,23.688c1.233,0.037,2.395,0.063,3.382,0.063c5.497,0,14.006-0.667,14.006-0.667 c2.833-0.167,3.167,3.994,0.337,4.329c0,0-2.847,0.335-6.015,0.501L48.2,93.547l11.501-34.493l-8.188-22.434 c-2.83-0.166-5.511-0.501-5.511-0.501c-2.832-0.166-2.5-4.496,0.332-4.329c0,0,8.679,0.667,13.843,0.667 c5.496,0,14.006-0.667,14.006-0.667c2.835-0.167,3.168,3.994,0.337,4.329c0,0-2.853,0.335-6.015,0.501l18.992,56.494l5.242-17.517 C94.612,69.188,96.74,63.439,96.74,58.608z"/>
                            <path d="M62.184,65.857l-15.768,45.819c4.708,1.384,9.687,2.141,14.846,2.141c6.12,0,11.989-1.058,17.452-2.979 c-0.141-0.225-0.269-0.464-0.374-0.724L62.184,65.857z"/>
                            <path d="M107.376,36.046c0.226,1.674,0.354,3.471,0.354,5.404c0,5.333-0.996,11.328-3.996,18.824l-16.053,46.413 c15.624-9.111,26.133-26.038,26.133-45.426C113.813,52.756,111.456,43.753,107.376,36.046z"/>
                            <path d="M61.262,0C27.483,0,0,27.481,0,61.26c0,33.783,27.483,61.263,61.262,61.263 c33.778,0,61.265-27.48,61.265-61.263C122.526,27.481,95.04,0,61.262,0z M61.262,119.715c-32.23,0-58.453-26.223-58.453-58.455 c0-32.23,26.222-58.451,58.453-58.451c32.229,0,58.45,26.221,58.45,58.451C119.712,93.492,93.491,119.715,61.262,119.715z"/>
                        </g>
                    </svg>
                </span>
                <span class="bbai-logged-out__pill-text">
                    <?php esc_html_e('Works with the WordPress Media Library, WooCommerce, and most themes.', 'beepbeep-ai-alt-text-generator'); ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Trial Generation Modal -->
<div id="bbai-trial-modal" class="bbai-trial-modal" role="dialog" aria-modal="true" aria-labelledby="bbai-trial-modal-title" style="display:none;">
    <div class="bbai-trial-modal__backdrop"></div>
    <div class="bbai-trial-modal__dialog">
        <button type="button" class="bbai-trial-modal__close" aria-label="<?php esc_attr_e('Close', 'beepbeep-ai-alt-text-generator'); ?>">&times;</button>

        <!-- Phase: Loading -->
        <div class="bbai-trial-modal__phase" id="bbai-trial-phase-loading">
            <div class="bbai-trial-modal__spinner"></div>
            <p class="bbai-trial-modal__status"><?php esc_html_e('Finding images without alt text...', 'beepbeep-ai-alt-text-generator'); ?></p>
        </div>

        <!-- Phase: No images found -->
        <div class="bbai-trial-modal__phase" id="bbai-trial-phase-empty" style="display:none;">
            <div class="bbai-trial-modal__icon bbai-trial-modal__icon--success">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
            </div>
            <h2 class="bbai-trial-modal__title"><?php esc_html_e('All images have alt text!', 'beepbeep-ai-alt-text-generator'); ?></h2>
            <p class="bbai-trial-modal__desc"><?php esc_html_e('Your media library is already optimized. Upload new images and we\'ll generate alt text automatically.', 'beepbeep-ai-alt-text-generator'); ?></p>
            <button type="button" class="bbai-trial-modal__btn-primary bbai-trial-modal__close-btn"><?php esc_html_e('Got it', 'beepbeep-ai-alt-text-generator'); ?></button>
        </div>

        <!-- Phase: Generating -->
        <div class="bbai-trial-modal__phase" id="bbai-trial-phase-generating" style="display:none;">
            <h2 id="bbai-trial-modal-title" class="bbai-trial-modal__title"><?php esc_html_e('Generating alt text...', 'beepbeep-ai-alt-text-generator'); ?></h2>
            <p class="bbai-trial-modal__counter"><span id="bbai-trial-done">0</span> / <span id="bbai-trial-total">0</span></p>
            <div class="bbai-trial-modal__progress-bar">
                <div class="bbai-trial-modal__progress-fill" id="bbai-trial-progress" style="width:0%"></div>
            </div>
            <div class="bbai-trial-modal__current" id="bbai-trial-current-wrap" style="display:none;">
                <img id="bbai-trial-current-thumb" src="" alt="" class="bbai-trial-modal__thumb" />
                <div class="bbai-trial-modal__current-info">
                    <span class="bbai-trial-modal__current-title" id="bbai-trial-current-title"></span>
                    <span class="bbai-trial-modal__current-status" id="bbai-trial-current-status"><?php esc_html_e('Generating...', 'beepbeep-ai-alt-text-generator'); ?></span>
                </div>
            </div>
            <div class="bbai-trial-modal__results" id="bbai-trial-results"></div>
        </div>

        <!-- Phase: Complete -->
        <div class="bbai-trial-modal__phase" id="bbai-trial-phase-complete" style="display:none;">
            <div class="bbai-trial-modal__icon bbai-trial-modal__icon--success">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
            </div>
            <h2 class="bbai-trial-modal__title" id="bbai-trial-complete-title"></h2>
            <p class="bbai-trial-modal__desc" id="bbai-trial-complete-desc"></p>
            <div class="bbai-trial-modal__complete-actions">
                <a class="bbai-trial-modal__btn-primary" href="<?php echo esc_url($bbai_fallback_url); ?>" data-action="show-auth-modal" data-auth-tab="register" id="bbai-trial-complete-signup">
                    <?php esc_html_e('Create free account — 50/month', 'beepbeep-ai-alt-text-generator'); ?>
                </a>
                <button type="button" class="bbai-trial-modal__btn-secondary bbai-trial-modal__close-btn"><?php esc_html_e('Close', 'beepbeep-ai-alt-text-generator'); ?></button>
            </div>
        </div>

        <!-- Phase: Error -->
        <div class="bbai-trial-modal__phase" id="bbai-trial-phase-error" style="display:none;">
            <div class="bbai-trial-modal__icon bbai-trial-modal__icon--error">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
            </div>
            <h2 class="bbai-trial-modal__title"><?php esc_html_e('Something went wrong', 'beepbeep-ai-alt-text-generator'); ?></h2>
            <p class="bbai-trial-modal__desc" id="bbai-trial-error-msg"></p>
            <button type="button" class="bbai-trial-modal__btn-primary bbai-trial-modal__close-btn"><?php esc_html_e('Close', 'beepbeep-ai-alt-text-generator'); ?></button>
        </div>
    </div>
</div>

<style>
/* Missing alt text alert */
.bbai-logged-out__missing-alert {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: #fef3c7;
    border: 1px solid #f59e0b;
    border-radius: 24px;
    padding: 8px 18px 8px 10px;
    margin: 0 auto 12px;
    font-size: 15px;
    color: #92400e;
    font-weight: 500;
}
.bbai-logged-out__missing-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 32px;
    padding: 0 8px;
    background: #f59e0b;
    color: #fff;
    font-weight: 700;
    font-size: 16px;
    border-radius: 16px;
    line-height: 1;
}
.bbai-logged-out__missing-text {
    line-height: 1.3;
}

/* Trial Generation Modal */
.bbai-trial-modal { position: fixed; inset: 0; z-index: 100100; display: flex; align-items: center; justify-content: center; }
.bbai-trial-modal__backdrop { position: absolute; inset: 0; background: rgba(0,0,0,0.6); }
.bbai-trial-modal__dialog { position: relative; background: #fff; border-radius: 12px; padding: 32px; max-width: 480px; width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
.bbai-trial-modal__close { position: absolute; top: 12px; right: 12px; background: none; border: none; font-size: 24px; color: #94a3b8; cursor: pointer; padding: 4px 8px; line-height: 1; border-radius: 4px; }
.bbai-trial-modal__close:hover { color: #1e293b; background: #f1f5f9; }
.bbai-trial-modal__phase { text-align: center; }
.bbai-trial-modal__title { font-size: 20px; font-weight: 700; color: #0f172a; margin: 0 0 8px; }
.bbai-trial-modal__desc { font-size: 14px; color: #64748b; margin: 0 0 20px; line-height: 1.5; }
.bbai-trial-modal__counter { font-size: 32px; font-weight: 700; color: #10b981; margin: 8px 0 16px; }
.bbai-trial-modal__progress-bar { width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; margin-bottom: 20px; }
.bbai-trial-modal__progress-fill { height: 100%; background: linear-gradient(90deg, #10b981, #14b8a6); border-radius: 4px; transition: width 0.4s ease; }
.bbai-trial-modal__current { display: flex; align-items: center; gap: 12px; padding: 12px; background: #f8fafc; border-radius: 8px; margin-bottom: 16px; text-align: left; }
.bbai-trial-modal__thumb { width: 48px; height: 48px; object-fit: cover; border-radius: 6px; flex-shrink: 0; border: 1px solid #e2e8f0; }
.bbai-trial-modal__current-info { flex: 1; min-width: 0; }
.bbai-trial-modal__current-title { display: block; font-size: 13px; font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.bbai-trial-modal__current-status { display: block; font-size: 12px; color: #10b981; margin-top: 2px; }
.bbai-trial-modal__results { max-height: 200px; overflow-y: auto; }
.bbai-trial-modal__result { display: flex; align-items: flex-start; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f1f5f9; text-align: left; }
.bbai-trial-modal__result:last-child { border-bottom: none; }
.bbai-trial-modal__result-thumb { width: 36px; height: 36px; object-fit: cover; border-radius: 4px; flex-shrink: 0; }
.bbai-trial-modal__result-info { flex: 1; min-width: 0; }
.bbai-trial-modal__result-alt { font-size: 12px; color: #475569; line-height: 1.4; margin-top: 2px; }
.bbai-trial-modal__result-title { font-size: 12px; font-weight: 600; color: #1e293b; }
.bbai-trial-modal__result-icon { flex-shrink: 0; width: 18px; height: 18px; margin-top: 2px; }
.bbai-trial-modal__result-icon--ok { color: #10b981; }
.bbai-trial-modal__result-icon--fail { color: #ef4444; }
.bbai-trial-modal__icon { margin: 0 auto 16px; width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
.bbai-trial-modal__icon--success { background: #ecfdf5; color: #10b981; }
.bbai-trial-modal__icon--error { background: #fef2f2; color: #ef4444; }
.bbai-trial-modal__spinner { width: 40px; height: 40px; border: 3px solid #e2e8f0; border-top-color: #10b981; border-radius: 50%; animation: bbai-spin 0.8s linear infinite; margin: 0 auto 16px; }
.bbai-trial-modal__status { font-size: 14px; color: #64748b; }
@keyframes bbai-spin { to { transform: rotate(360deg); } }
.bbai-trial-modal__btn-primary { display: inline-block; padding: 12px 24px; background: #10b981; color: #fff !important; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; transition: background 0.2s; }
.bbai-trial-modal__btn-primary:hover { background: #059669; color: #fff !important; }
.bbai-trial-modal__btn-secondary { display: inline-block; padding: 10px 20px; background: transparent; color: #64748b; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; text-decoration: none; }
.bbai-trial-modal__btn-secondary:hover { background: #f8fafc; color: #1e293b; }
.bbai-trial-modal__complete-actions { display: flex; flex-direction: column; gap: 10px; align-items: center; }
</style>

<script>
(function() {
    'use strict';

    var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
    var nonce   = <?php echo wp_json_encode( wp_create_nonce( 'beepbeepai_nonce' ) ); ?>;
    var trialRemaining  = <?php echo (int) $bbai_trial_remaining; ?>;
    var trialLimit      = <?php echo (int) $bbai_trial_limit; ?>;
    var missingAltCount = <?php echo (int) $bbai_missing_alt_count; ?>;
    var lastSucceeded   = 0;

    var modal       = document.getElementById('bbai-trial-modal');
    var btn         = document.getElementById('bbai-trial-generate-btn');
    var backdrop    = modal ? modal.querySelector('.bbai-trial-modal__backdrop') : null;
    var closeBtn    = modal ? modal.querySelector('.bbai-trial-modal__close') : null;
    var closeBtns   = modal ? modal.querySelectorAll('.bbai-trial-modal__close-btn') : [];

    if (!btn || !modal) return;

    function showPhase(id) {
        var phases = modal.querySelectorAll('.bbai-trial-modal__phase');
        for (var i = 0; i < phases.length; i++) {
            phases[i].style.display = 'none';
        }
        var target = document.getElementById(id);
        if (target) target.style.display = 'block';
    }

    function openModal() {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        showPhase('bbai-trial-phase-loading');
        fetchMissing();
    }

    function closeModal() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    btn.addEventListener('click', function(e) {
        e.preventDefault();
        openModal();
    });

    if (backdrop) backdrop.addEventListener('click', closeModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    for (var i = 0; i < closeBtns.length; i++) {
        closeBtns[i].addEventListener('click', closeModal);
    }

    // Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.style.display !== 'none') {
            closeModal();
        }
    });

    function fetchMissing() {
        var formData = new FormData();
        formData.append('action', 'bbai_trial_get_missing');
        formData.append('nonce', nonce);

        fetch(ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(resp) {
                if (!resp.success) {
                    if (resp.data && resp.data.code === 'bbai_trial_exhausted') {
                        showExhausted();
                    } else {
                        showError(resp.data && resp.data.message ? resp.data.message : 'Failed to find images.');
                    }
                    return;
                }
                var images = resp.data.images || [];
                if (images.length === 0) {
                    showPhase('bbai-trial-phase-empty');
                    return;
                }
                startGenerating(images, resp.data.remaining);
            })
            .catch(function(err) {
                showError(err.message || 'Network error.');
            });
    }

    function startGenerating(images, remaining) {
        showPhase('bbai-trial-phase-generating');

        var total = images.length;
        var done = 0;
        var succeeded = 0;
        var failed = 0;

        document.getElementById('bbai-trial-total').textContent = total;
        document.getElementById('bbai-trial-done').textContent = '0';
        document.getElementById('bbai-trial-progress').style.width = '0%';
        document.getElementById('bbai-trial-results').innerHTML = '';

        function processNext(index) {
            if (index >= total) {
                showComplete(succeeded, failed, total);
                return;
            }

            var img = images[index];
            var wrap = document.getElementById('bbai-trial-current-wrap');
            var thumb = document.getElementById('bbai-trial-current-thumb');
            var title = document.getElementById('bbai-trial-current-title');
            var status = document.getElementById('bbai-trial-current-status');

            wrap.style.display = 'flex';
            if (img.thumb) {
                thumb.src = img.thumb;
                thumb.style.display = 'block';
            } else {
                thumb.style.display = 'none';
            }
            title.textContent = img.title || ('Image #' + img.id);
            status.textContent = 'Generating...';
            status.style.color = '#10b981';

            var formData = new FormData();
            formData.append('action', 'bbai_trial_generate_single');
            formData.append('nonce', nonce);
            formData.append('attachment_id', img.id);

            fetch(ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(resp) {
                    done++;
                    var pct = Math.round((done / total) * 100);
                    document.getElementById('bbai-trial-done').textContent = done;
                    document.getElementById('bbai-trial-progress').style.width = pct + '%';

                    if (resp.success) {
                        succeeded++;
                        addResult(img, resp.data.alt_text, true);
                        trialRemaining = resp.data.remaining;
                        updateBanner(resp.data.remaining);
                    } else {
                        failed++;
                        var isExhausted = resp.data && resp.data.code === 'bbai_trial_exhausted';
                        addResult(img, resp.data && resp.data.message ? resp.data.message : 'Failed', false);
                        if (isExhausted) {
                            // Trial exhausted mid-generation — show complete with what we have.
                            showComplete(succeeded, failed, done);
                            return;
                        }
                    }

                    processNext(index + 1);
                })
                .catch(function(err) {
                    done++;
                    failed++;
                    addResult(img, err.message || 'Network error', false);
                    document.getElementById('bbai-trial-done').textContent = done;
                    document.getElementById('bbai-trial-progress').style.width = Math.round((done / total) * 100) + '%';
                    processNext(index + 1);
                });
        }

        processNext(0);
    }

    function addResult(img, text, success) {
        var container = document.getElementById('bbai-trial-results');
        var div = document.createElement('div');
        div.className = 'bbai-trial-modal__result';

        var thumbHtml = img.thumb
            ? '<img class="bbai-trial-modal__result-thumb" src="' + escHtml(img.thumb) + '" alt="" />'
            : '';

        var iconClass = success ? 'bbai-trial-modal__result-icon--ok' : 'bbai-trial-modal__result-icon--fail';
        var iconSvg = success
            ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>'
            : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line></svg>';

        div.innerHTML = '<span class="bbai-trial-modal__result-icon ' + iconClass + '">' + iconSvg + '</span>'
            + thumbHtml
            + '<div class="bbai-trial-modal__result-info">'
            + '<span class="bbai-trial-modal__result-title">' + escHtml(img.title || ('Image #' + img.id)) + '</span>'
            + '<span class="bbai-trial-modal__result-alt">' + escHtml(text) + '</span>'
            + '</div>';

        container.insertBefore(div, container.firstChild);
    }

    function showComplete(succeeded, failed, total) {
        lastSucceeded = succeeded;
        showPhase('bbai-trial-phase-complete');
        var titleEl = document.getElementById('bbai-trial-complete-title');
        var descEl  = document.getElementById('bbai-trial-complete-desc');

        if (succeeded > 0) {
            titleEl.textContent = succeeded + ' image' + (succeeded !== 1 ? 's' : '') + ' updated!';
        } else {
            titleEl.textContent = 'Generation complete';
        }

        if (trialRemaining <= 0) {
            descEl.textContent = "You've used all " + trialLimit + " free generations. Create a free account to unlock 50 more per month.";
        } else {
            descEl.textContent = succeeded + ' of ' + total + ' images processed. ' + trialRemaining + ' free generation' + (trialRemaining !== 1 ? 's' : '') + ' remaining.';
        }

        // Update the main page button and banner.
        updateButtonState();
    }

    function showExhausted() {
        showPhase('bbai-trial-phase-complete');
        var titleEl = document.getElementById('bbai-trial-complete-title');
        var descEl  = document.getElementById('bbai-trial-complete-desc');
        titleEl.textContent = "You've used your free trial";
        descEl.textContent = 'Create a free account to unlock 50 more credits per month.';
        updateButtonState();
    }

    function showError(msg) {
        showPhase('bbai-trial-phase-error');
        document.getElementById('bbai-trial-error-msg').textContent = msg;
    }

    function updateBanner(remaining) {
        var bannerText = document.querySelector('.bbai-trial-banner__text');
        if (bannerText) {
            bannerText.textContent = 'Trial: ' + remaining + ' of ' + trialLimit + ' free generations remaining';
        }
    }

    function updateButtonState() {
        if (trialRemaining <= 0 && btn) {
            btn.textContent = <?php echo wp_json_encode( __( "You've used your free trial", 'beepbeep-ai-alt-text-generator' ) ); ?>;
            btn.disabled = true;
            btn.style.opacity = '0.6';
            btn.style.cursor = 'not-allowed';

            // Update banner to exhausted state.
            var banner = document.querySelector('.bbai-trial-banner');
            if (banner) {
                banner.classList.add('bbai-trial-banner--exhausted');
                var bannerText = banner.querySelector('.bbai-trial-banner__text');
                if (bannerText) {
                    bannerText.textContent = 'Free trial exhausted \u2014 create a free account to continue';
                }
            }

            // Update the page title and subtitle.
            var titleEl = document.getElementById('bbai-logged-out-title');
            if (titleEl) {
                titleEl.innerHTML = '<span class="bbai-logged-out__title-line">' + escHtml(<?php echo wp_json_encode( __( "You've used your free trial", 'beepbeep-ai-alt-text-generator' ) ); ?>) + '</span>';
            }
            var subtitleEl = document.querySelector('.bbai-logged-out__subtitle');
            if (subtitleEl) {
                subtitleEl.textContent = <?php echo wp_json_encode( __( 'Create a free account to unlock 50 more credits per month.', 'beepbeep-ai-alt-text-generator' ) ); ?>;
            }
            // Hide the missing alert when trial exhausted.
            var alertEl = document.querySelector('.bbai-logged-out__missing-alert');
            if (alertEl) alertEl.style.display = 'none';
        } else if (btn) {
            // Update missing count after generations.
            missingAltCount = Math.max(0, missingAltCount - lastSucceeded);

            // Update the alert badge.
            var badge = document.getElementById('bbai-missing-badge');
            var badgeText = document.getElementById('bbai-missing-text');
            var alertEl2 = document.querySelector('.bbai-logged-out__missing-alert');
            if (missingAltCount > 0) {
                if (badge) badge.textContent = missingAltCount;
                if (badgeText) badgeText.textContent = missingAltCount === 1 ? 'image missing alt text' : 'images missing alt text';
            } else if (alertEl2) {
                alertEl2.style.display = 'none';
            }

            // Update button text.
            if (missingAltCount > 0) {
                var fixCount = Math.min(missingAltCount, trialRemaining);
                btn.textContent = 'Fix ' + fixCount + ' image' + (fixCount !== 1 ? 's' : '') + ' free \u2014 ' + trialRemaining + ' left';
            } else {
                btn.textContent = <?php echo wp_json_encode(
                    /* translators: placeholder replaced by JS */
                    __( 'Generate alt text free', 'beepbeep-ai-alt-text-generator' )
                ); ?> + ' \u2014 ' + trialRemaining + ' left';
            }
        }
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

})();
</script>
