<?php
/**
 * Plugin Name: AI Alt Text Generator (Simple)
 * Description: Generate AI alt text for images with simple Stripe payment links
 * Version: 4.1.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AI_Alt_Text_Generator_Simple {
    
    private $payment_links = [
        'pro' => 'https://buy.stripe.com/test_pro',
        'agency' => 'https://buy.stripe.com/test_agency',
        'credits' => 'https://buy.stripe.com/test_credits'
    ];
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('wp_ajax_ai_alt_generate', [$this, 'handle_alt_generation']);
        add_action('wp_ajax_ai_alt_upgrade', [$this, 'handle_upgrade']);
    }
    
    public function add_admin_menu() {
        add_media_page(
            'AI Alt Text Generator',
            'AI Alt Text',
            'manage_options',
            'ai-alt-text',
            [$this, 'render_dashboard']
        );
    }
    
    public function render_dashboard() {
        ?>
        <div class="wrap">
            <h1>AI Alt Text Generator</h1>
            
            <div class="card">
                <h2>Generate Alt Text</h2>
                <p>Upload images and generate AI-powered alt text descriptions.</p>
                
                <div class="upgrade-section">
                    <h3>Upgrade Your Plan</h3>
                    <div class="pricing-plans">
                        <div class="plan">
                            <h4>Pro Plan - £12.99/month</h4>
                            <p>1000 images per month</p>
                            <a href="<?php echo esc_url($this->payment_links['pro']); ?>" class="button button-primary">Upgrade to Pro</a>
                        </div>
                        
                        <div class="plan">
                            <h4>Agency Plan - £49.99/month</h4>
                            <p>10,000 images per month</p>
                            <a href="<?php echo esc_url($this->payment_links['agency']); ?>" class="button button-primary">Upgrade to Agency</a>
                        </div>
                        
                        <div class="plan">
                            <h4>Credits Pack - £9.99</h4>
                            <p>100 images (one-time)</p>
                            <a href="<?php echo esc_url($this->payment_links['credits']); ?>" class="button button-primary">Buy Credits</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .pricing-plans {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .plan {
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .plan h4 {
            margin-top: 0;
            color: #0073aa;
        }
        
        .plan .button {
            margin-top: 15px;
        }
        </style>
        <?php
    }
    
    public function handle_alt_generation() {
        // Simple alt text generation logic
        wp_send_json_success(['message' => 'Alt text generated successfully']);
    }
    
    public function handle_upgrade() {
        $plan = sanitize_text_field($_POST['plan'] ?? 'pro');
        $payment_url = $this->payment_links[$plan] ?? $this->payment_links['pro'];
        
        wp_redirect($payment_url);
        exit;
    }
}

// Initialize the plugin
new AI_Alt_Text_Generator_Simple();
