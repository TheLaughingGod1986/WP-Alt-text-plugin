<?php
/**
 * Upcoming Plugins Subscribe Modal Component
 * Renders a modal for users to subscribe to upcoming OpttiAI plugins
 */

namespace BeepBeepAI\AltTextGenerator;

if (!defined('ABSPATH')) {
    exit;
}

class UpcomingPluginsSubscribeModal {
    
    public static function render() {
        // Get current user data for pre-filling
        $current_user = wp_get_current_user();
        $user_email = $current_user->exists() ? $current_user->user_email : '';
        $site_url = get_site_url();
        $nonce = wp_create_nonce('bbai_upcoming_plugins_subscribe');
        ?>
        <div id="bbai-upcoming-plugins-subscribe-modal" class="bbai-upcoming-plugins-subscribe-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="bbai-upcoming-plugins-subscribe-modal-title">
            <div class="bbai-upcoming-plugins-subscribe-modal-overlay">
                <div class="bbai-upcoming-plugins-subscribe-modal-content">
                    <button class="bbai-upcoming-plugins-subscribe-modal-close" type="button" aria-label="<?php esc_attr_e('Close', 'wp-alt-text-plugin'); ?>">&times;</button>
                    <div class="bbai-upcoming-plugins-subscribe-modal-header">
                        <h2 id="bbai-upcoming-plugins-subscribe-modal-title"><?php esc_html_e('Get Early Access to New OpttiAI Plugins', 'wp-alt-text-plugin'); ?></h2>
                        <p><?php esc_html_e('Be the first to know when we launch new AI-powered tools for WordPress.', 'wp-alt-text-plugin'); ?></p>
                    </div>
                    <div class="bbai-upcoming-plugins-subscribe-modal-body">
                        <form id="bbai-upcoming-plugins-subscribe-form" autocomplete="off">
                            <div class="bbai-form-group">
                                <label for="bbai-upcoming-plugins-email"><?php esc_html_e('Email', 'wp-alt-text-plugin'); ?></label>
                                <input 
                                    type="email" 
                                    id="bbai-upcoming-plugins-email" 
                                    name="email" 
                                    value="<?php echo esc_attr($user_email); ?>" 
                                    placeholder="<?php esc_attr_e('your@email.com', 'wp-alt-text-plugin'); ?>" 
                                    required
                                >
                            </div>
                            <button type="submit" class="bbai-btn bbai-btn-primary">
                                <span class="bbai-btn-text"><?php esc_html_e('Subscribe', 'wp-alt-text-plugin'); ?></span>
                                <span class="bbai-btn-spinner" style="display: none;">⏳</span>
                            </button>
                        </form>
                        <div id="bbai-upcoming-plugins-subscribe-message" class="bbai-message" style="display: none;"></div>
                    </div>
                    <div class="bbai-upcoming-plugins-subscribe-modal-footer">
                        <p style="font-size: 12px; color: #6b7280; margin: 0;">
                            <?php esc_html_e('We\'ll only send you updates about new plugins. No spam.', 'wp-alt-text-plugin'); ?>
                            <a href="https://oppti.dev/privacy" target="_blank" rel="noopener" style="color: #3A74FF; text-decoration: none;">
                                <?php esc_html_e('Privacy Policy', 'wp-alt-text-plugin'); ?>
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .bbai-upcoming-plugins-subscribe-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, visibility 0.2s ease;
        }
        
        .bbai-upcoming-plugins-subscribe-modal.active {
            opacity: 1;
            visibility: visible;
        }
        
        .bbai-upcoming-plugins-subscribe-modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
        
        .bbai-upcoming-plugins-subscribe-modal-content {
            position: relative;
            z-index: 1;
            background: #ffffff;
            border-radius: 12px;
            width: 90%;
            max-width: 480px;
            padding: 32px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            transform: scale(0.95);
            transition: transform 0.2s ease;
        }
        
        .bbai-upcoming-plugins-subscribe-modal.active .bbai-upcoming-plugins-subscribe-modal-content {
            transform: scale(1);
        }
        
        .bbai-upcoming-plugins-subscribe-modal-close {
            position: absolute;
            top: 16px;
            right: 16px;
            background: none;
            border: none;
            font-size: 28px;
            line-height: 1;
            color: #6b7280;
            cursor: pointer;
            padding: 8px;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .bbai-upcoming-plugins-subscribe-modal-close:hover {
            background: #f3f4f6;
            color: #111827;
        }
        
        .bbai-upcoming-plugins-subscribe-modal-header {
            text-align: center;
            margin-bottom: 24px;
        }
        
        .bbai-upcoming-plugins-subscribe-modal-header h2 {
            margin: 0 0 8px;
            font-size: 24px;
            font-weight: 600;
            color: #111827;
        }
        
        .bbai-upcoming-plugins-subscribe-modal-header p {
            margin: 0;
            font-size: 14px;
            color: #6b7280;
        }
        
        .bbai-upcoming-plugins-subscribe-modal-body {
            margin-bottom: 20px;
        }
        
        .bbai-form-group {
            margin-bottom: 20px;
        }
        
        .bbai-form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
            color: #374151;
        }
        
        .bbai-form-group input[type="email"] {
            width: 100%;
            padding: 12px 16px;
            font-size: 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            transition: border-color 0.2s ease;
            box-sizing: border-box;
        }
        
        .bbai-form-group input[type="email"]:focus {
            outline: none;
            border-color: #3A74FF;
            box-shadow: 0 0 0 3px rgba(58, 116, 255, 0.1);
        }
        
        .bbai-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 100%;
        }
        
        .bbai-btn-primary {
            background: #3A74FF;
            color: #ffffff;
        }
        
        .bbai-btn-primary:hover:not(:disabled) {
            background: #2d5dd8;
        }
        
        .bbai-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .bbai-btn-spinner {
            margin-left: 8px;
        }
        
        .bbai-message {
            margin-top: 16px;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .bbai-message--success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .bbai-message--error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .bbai-upcoming-plugins-subscribe-modal-footer {
            text-align: center;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
        }
        
        .bbai-upcoming-plugins-subscribe-btn {
            margin-top: 12px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            color: #3A74FF;
            background: #ffffff;
            border: 1px solid #3A74FF;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: block;
            width: 100%;
            text-align: center;
        }
        
        .bbai-upcoming-plugins-subscribe-btn:hover {
            background: #3A74FF;
            color: #ffffff;
        }
        
        @media (min-width: 640px) {
            .bbai-premium-footer-cta {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
            }
            
            .bbai-premium-footer-cta .bbai-footer-cta-text {
                flex: 1;
                margin: 0;
            }
            
            .bbai-upcoming-plugins-subscribe-btn {
                margin-top: 0;
                width: auto;
                flex-shrink: 0;
            }
        }
        </style>
        
        <script type="text/javascript">
        // Pass data to JavaScript
        window.bbaiUpcomingPluginsSubscribeData = {
            userEmail: <?php echo wp_json_encode($user_email); ?>,
            siteUrl: <?php echo wp_json_encode($site_url); ?>,
            nonce: <?php echo wp_json_encode($nonce); ?>
        };
        </script>
        <?php
    }
}

