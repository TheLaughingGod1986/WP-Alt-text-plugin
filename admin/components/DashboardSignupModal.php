<?php
/**
 * Dashboard Signup Modal Component
 * Renders a modal for collecting user information on plugin activation and pro upgrades
 */

if (!defined('ABSPATH')) {
    exit;
}

class DashboardSignupModal {
    
    /**
     * Render the dashboard signup modal
     */
    public static function render() {
        $current_user = wp_get_current_user();
        $user_email = $current_user->exists() ? $current_user->user_email : '';
        $user_name = $current_user->exists() ? $current_user->display_name : '';
        $site_domain = self::get_site_domain();
        $nonce = wp_create_nonce('bbai_dashboard_signup');
        // Terms URL - update with actual terms URL if different
        $terms_url = apply_filters('bbai_terms_url', 'https://oppti.dev/terms');
        
        // Check if modal should be shown
        $show_on_activation = get_transient('bbai_show_dashboard_signup');
        $show_on_upgrade = get_transient('bbai_show_dashboard_signup_upgrade');
        $should_show = $show_on_activation || $show_on_upgrade;
        
        if (!$should_show) {
            return;
        }
        
        ?>
        <div id="bbai-dashboard-signup-modal" class="alttext-auth-modal" style="display: <?php echo $should_show ? 'block' : 'none'; ?>;" role="dialog" aria-modal="true" aria-labelledby="bbai-dashboard-signup-title" aria-describedby="bbai-dashboard-signup-desc">
            <div class="alttext-auth-modal__overlay">
                <div class="alttext-auth-modal__content">
                    <button class="alttext-auth-modal__close" type="button" aria-label="Close dialog" id="bbai-dashboard-signup-close">&times;</button>
                    
                    <div class="alttext-auth-modal__header">
                        <h2 class="alttext-auth-modal__title" id="bbai-dashboard-signup-title">Welcome to AltText AI!</h2>
                        <p class="alttext-auth-modal__subtitle" id="bbai-dashboard-signup-desc">Stay updated with the latest features and updates.</p>
                    </div>
                    
                    <div class="alttext-auth-modal__body">
                        <form id="bbai-dashboard-signup-form" class="alttext-auth-form" autocomplete="off" aria-label="Dashboard signup form">
                            <div class="alttext-form-group">
                                <label for="bbai-signup-name">Name</label>
                                <input 
                                    type="text" 
                                    id="bbai-signup-name" 
                                    name="name" 
                                    placeholder="Your name" 
                                    autocomplete="name" 
                                    value="<?php echo esc_attr($user_name); ?>"
                                    required 
                                    aria-required="true"
                                >
                            </div>
                            
                            <div class="alttext-form-group">
                                <label for="bbai-signup-email">Email</label>
                                <input 
                                    type="email" 
                                    id="bbai-signup-email" 
                                    name="email" 
                                    placeholder="your@email.com" 
                                    autocomplete="email" 
                                    value="<?php echo esc_attr($user_email); ?>"
                                    required 
                                    aria-required="true"
                                >
                            </div>
                            
                            <div class="alttext-form-group">
                                <label class="alttext-checkbox-label" style="display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer;">
                                    <input 
                                        type="checkbox" 
                                        id="bbai-signup-terms" 
                                        name="accept_terms" 
                                        value="1" 
                                        required 
                                        aria-required="true"
                                        style="margin-top: 0.25rem; cursor: pointer;"
                                    >
                                    <span style="font-size: 14px; line-height: 1.5;">
                                        I accept the <a href="<?php echo esc_url($terms_url); ?>" target="_blank" rel="noopener noreferrer">Terms of Service</a>
                                    </span>
                                </label>
                            </div>
                            
                            <button type="submit" class="alttext-btn alttext-btn--primary" aria-label="Submit signup">
                                <span class="alttext-btn__text">Get Started</span>
                                <span class="alttext-btn__spinner" style="display: none;" aria-hidden="true">⏳</span>
                            </button>
                        </form>
                        
                        <div id="bbai-dashboard-signup-message" class="alttext-alert" style="display: none;" role="status" aria-live="polite"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
        // Pass data to JavaScript
        window.bbaiDashboardSignupData = {
            apiUrl: <?php 
                // Get API URL - use from bbai_ajax if available, otherwise from API client
                $api_url = '';
                if (isset($GLOBALS['bbai_ajax']) && isset($GLOBALS['bbai_ajax']['api_url'])) {
                    $api_url = $GLOBALS['bbai_ajax']['api_url'];
                } else {
                    // Fallback to default API URL
                    $api_url = 'https://alttext-ai-backend.onrender.com';
                }
                echo wp_json_encode($api_url); 
            ?>,
            siteDomain: <?php echo wp_json_encode($site_domain); ?>,
            nonce: <?php echo wp_json_encode($nonce); ?>,
            ajaxUrl: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
            showOnUpgrade: <?php echo $show_on_upgrade ? 'true' : 'false'; ?>
        };
        
        // Auto-show modal if upgrade transient is set
        <?php if ($show_on_upgrade): ?>
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                if (window.bbaiDashboardSignupHandler) {
                    window.bbaiDashboardSignupHandler.showModal();
                }
            });
        } else {
            if (window.bbaiDashboardSignupHandler) {
                window.bbaiDashboardSignupHandler.showModal();
            }
        }
        <?php endif; ?>
        </script>
        <?php
    }
    
    /**
     * Get email from current WP user
     */
    public static function get_user_email() {
        $current_user = wp_get_current_user();
        return $current_user->exists() ? $current_user->user_email : '';
    }
    
    /**
     * Get site domain for auto-fill
     */
    public static function get_site_domain() {
        $site_url = get_site_url();
        $parsed = parse_url($site_url);
        return isset($parsed['host']) ? $parsed['host'] : '';
    }
}

