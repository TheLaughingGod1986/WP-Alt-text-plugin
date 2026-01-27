<?php
/**
 * Logged-Out Dashboard State
 * Clean onboarding screen for unauthenticated users.
 *
 * @package BeepBeep_AI
 * @since 6.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_page = isset($_GET['page']) ? sanitize_key($_GET['page']) : 'bbai';
$fallback_url = admin_url('admin.php?page=' . $current_page);
?>
<div class="bbai-logged-out" role="main" aria-labelledby="bbai-logged-out-title">
    <div class="bbai-logged-out__container">
        <div class="bbai-logged-out__card">
            <header class="bbai-logged-out__header">
                <h1 id="bbai-logged-out-title" class="bbai-logged-out__title">
                    <span class="bbai-logged-out__title-line">
                        <?php esc_html_e('Get found in Google Images', 'beepbeep-ai-alt-text-generator'); ?>
                    </span>
                    <span class="bbai-logged-out__title-line">
                        <?php esc_html_e('with complete alt text', 'beepbeep-ai-alt-text-generator'); ?>
                    </span>
                </h1>
                <p class="bbai-logged-out__subtitle">
                    <?php esc_html_e('Generate SEO-ready, accessible alt text for your entire media library — directly from WordPress.', 'beepbeep-ai-alt-text-generator'); ?>
                </p>
            </header>

            <div class="bbai-logged-out__actions">
                <a
                    class="bbai-logged-out__btn-primary"
                    href="<?php echo esc_url($fallback_url); ?>"
                    data-action="show-auth-modal"
                    data-auth-tab="login"
                >
                    <?php esc_html_e('Sign in to start generating', 'beepbeep-ai-alt-text-generator'); ?>
                </a>
                <a
                    class="bbai-logged-out__link-secondary"
                    href="<?php echo esc_url($fallback_url); ?>"
                    data-action="show-auth-modal"
                    data-auth-tab="register"
                >
                    <?php esc_html_e('Create a free account', 'beepbeep-ai-alt-text-generator'); ?>
                </a>
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
                            <svg viewBox="0 0 122.52 122.523" role="presentation" focusable="false">
                                <g fill="currentColor">
                                    <path d="M8.708,61.26c0,20.802,12.089,38.779,29.619,47.298L13.258,39.872C10.342,46.408,8.708,53.63,8.708,61.26z"/>
                                    <path d="M96.74,58.608c0-6.495-2.333-10.993-4.334-14.494c-2.664-4.329-5.161-7.995-5.161-12.324 c0-4.831,3.664-9.328,8.825-9.328c0.233,0,0.454,0.029,0.681,0.042c-9.35-8.566-21.807-13.796-35.489-13.796 c-18.36,0-34.513,9.42-43.91,23.688c1.233,0.037,2.395,0.063,3.382,0.063c5.497,0,14.006-0.667,14.006-0.667 c2.833-0.167,3.167,3.994,0.337,4.329c0,0-2.847,0.335-6.015,0.501L48.2,93.547l11.501-34.493l-8.188-22.434 c-2.83-0.166-5.511-0.501-5.511-0.501c-2.832-0.166-2.5-4.496,0.332-4.329c0,0,8.679,0.667,13.843,0.667 c5.496,0,14.006-0.667,14.006-0.667c2.835-0.167,3.168,3.994,0.337,4.329c0,0-2.853,0.335-6.015,0.501l18.992,56.494l5.242-17.517 C94.612,69.188,96.74,63.439,96.74,58.608z"/>
                                    <path d="M62.184,65.857l-15.768,45.819c4.708,1.384,9.687,2.141,14.846,2.141c6.12,0,11.989-1.058,17.452-2.979 c-0.141-0.225-0.269-0.464-0.374-0.724L62.184,65.857z"/>
                                    <path d="M107.376,36.046c0.226,1.674,0.354,3.471,0.354,5.404c0,5.333-0.996,11.328-3.996,18.824l-16.053,46.413 c15.624-9.111,26.133-26.038,26.133-45.426C113.813,52.756,111.456,43.753,107.376,36.046z"/>
                                    <path d="M61.262,0C27.483,0,0,27.481,0,61.26c0,33.783,27.483,61.263,61.262,61.263 c33.778,0,61.265-27.48,61.265-61.263C122.526,27.481,95.04,0,61.262,0z M61.262,119.715c-32.23,0-58.453-26.223-58.453-58.455 c0-32.23,26.222-58.451,58.453-58.451c32.229,0,58.45,26.221,58.45,58.451C119.712,93.492,93.491,119.715,61.262,119.715z"/>
                                </g>
                            </svg>
                        </div>
                        <p class="bbai-logged-out__step-title">
                            <?php esc_html_e('Connect your site', 'beepbeep-ai-alt-text-generator'); ?>
                        </p>
                        <p class="bbai-logged-out__step-desc">
                            <?php esc_html_e('One click, fully automatic', 'beepbeep-ai-alt-text-generator'); ?>
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
                </div>
            </section>

            <p class="bbai-logged-out__supporting-line">
                <?php esc_html_e('Free plan includes 50 images per month. No credit card required.', 'beepbeep-ai-alt-text-generator'); ?>
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
