<?php
/** Hero/intro for dashboard. */
if (!defined('ABSPATH')) { exit; }
?>
<?php
/**
 * Dashboard authenticated content markup.
 */
if (!defined('ABSPATH')) { exit; }
?>
<!-- Clean Dashboard Design -->
                <div class="bbai-dashboard-shell max-w-5xl mx-auto px-6">

                     <!-- HERO Section Styles -->
                     <style>
                         .bbai-hero-section {
                             background: linear-gradient(to bottom, #f7fee7 0%, #ffffff 100%);
                             border-radius: 24px;
                             margin-bottom: 32px;
                             padding: 48px 40px;
                             text-align: center;
                             box-shadow: 0 4px 16px rgba(0,0,0,0.08);
                         }
                         .bbai-hero-content {
                             margin-bottom: 32px;
                         }
                         .bbai-hero-title {
                             margin: 0 0 16px 0;
                             font-size: 2.5rem;
                             font-weight: 700;
                             color: #0f172a;
                             line-height: 1.2;
                         }
                         .bbai-hero-subtitle {
                             margin: 0;
                             font-size: 1.125rem;
                             color: #475569;
                             line-height: 1.6;
                             max-width: 600px;
                             margin-left: auto;
                             margin-right: auto;
                         }
                         .bbai-hero-actions {
                             display: flex;
                             flex-direction: column;
                             align-items: center;
                             gap: 16px;
                             margin-bottom: 24px;
                         }
                         .bbai-hero-btn-primary {
                             background: linear-gradient(135deg, #14b8a6 0%, #84cc16 100%);
                             color: white;
                             border: none;
                             padding: 16px 32px;
                             border-radius: 16px;
                             font-size: 16px;
                             font-weight: 600;
                             cursor: pointer;
                             transition: opacity 0.2s ease;
                             box-shadow: 0 4px 12px rgba(20, 184, 166, 0.3);
                         }
                         .bbai-hero-btn-primary:hover {
                             opacity: 0.9;
                         }
                         .bbai-hero-link-secondary {
                             background: transparent;
                             border: none;
                             color: #6b7280;
                             text-decoration: underline;
                             font-size: 14px;
                             cursor: pointer;
                             transition: color 0.2s ease;
                             padding: 0;
                         }
                         .bbai-hero-link-secondary:hover {
                             color: #14b8a6;
                         }
                         .bbai-hero-micro-copy {
                             font-size: 14px;
                             color: #64748b;
                             font-weight: 500;
                         }
                     </style>

                     <?php 
                     // Check for stored credentials, not just API validation
                     $check_stored_token = get_option('beepbeepai_jwt_token', '');
                     $has_stored_token_check = !empty($check_stored_token);
                     $check_stored_license = '';
                     try {
                         $check_stored_license = $this->api_client->get_license_key();
                     } catch (Exception $e) {
                         $check_stored_license = '';
                     } catch (Error $e) {
                         $check_stored_license = '';
                     }
                     $has_stored_license_check = !empty($check_stored_license);
                     $is_registered = $this->api_client->is_authenticated() || $this->api_client->has_active_license() || $has_stored_token_check || $has_stored_license_check;
                     ?>
                     <?php if (!$is_registered) : ?>
                     <!-- HERO Section - Not Authenticated -->
                     <div class="bbai-hero-section">
                         <div class="bbai-hero-content">
                             <h2 class="bbai-hero-title">
                                 <?php esc_html_e('Stop Losing Traffic from Google Images — Fix Alt Text Automatically', 'beepbeep-ai-alt-text-generator'); ?>
                             </h2>
                             <p class="bbai-hero-subtitle">
                                 <?php esc_html_e('Generate SEO-optimized, WCAG-compliant alt text for every image automatically. Boost Google Images rankings and improve accessibility in seconds. Start with 50 free generations monthly.', 'beepbeep-ai-alt-text-generator'); ?>
                             </p>
                         </div>
                         <div class="bbai-hero-actions">
                             <button type="button" class="bbai-hero-btn-primary" id="bbai-show-auth-banner-btn">
                                <?php esc_html_e('Get Started Free — Generate 50 AI Alt Texts Now', 'beepbeep-ai-alt-text-generator'); ?>
                             </button>
                             <button type="button" class="bbai-hero-link-secondary" id="bbai-show-auth-login-btn">
                                 <?php esc_html_e('Already have an account? Sign in', 'beepbeep-ai-alt-text-generator'); ?>
                             </button>
                         </div>
                         <div class="bbai-hero-micro-copy">
                             <?php esc_html_e('⚡ SEO Boost · 🦾 Accessibility · 🕒 Saves Hours', 'beepbeep-ai-alt-text-generator'); ?>
                         </div>
                     </div>
                     <?php endif; ?>
                     <!-- Subscription management now in header -->
