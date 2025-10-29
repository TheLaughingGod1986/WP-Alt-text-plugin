<?php
/**
 * Plugin Name: AI Alt Text Generator (JavaScript Links)
 * Description: Generate AI alt text with JavaScript-opened Stripe links
 * Version: 4.1.0
 */

if (!defined('ABSPATH')) exit;

class AI_Alt_Text_Generator_JS {
    
    private $stripe_links = [
        'pro' => 'https://buy.stripe.com/test_6oU9AUf5Q2EYaKq0fp7ss00',
        'agency' => 'https://buy.stripe.com/test_28E14og9U0wQ19Q4vF7ss01', 
        'credits' => 'https://buy.stripe.com/test_dRm28s4rc5Raf0GbY77ss02'
    ];
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
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
                <h2>Upgrade Your Plan</h2>
                
                <div class="pricing-grid">
                    <div class="plan-card">
                        <h3>Pro Plan</h3>
                        <div class="price">£12.99/month</div>
                        <ul>
                            <li>1000 images per month</li>
                            <li>Advanced quality scoring</li>
                            <li>Bulk processing</li>
                        </ul>
                        <button onclick="openStripeLink('<?php echo $this->stripe_links['pro']; ?>')" 
                                class="button button-primary button-large">Upgrade to Pro</button>
                    </div>
                    
                    <div class="plan-card">
                        <h3>Agency Plan</h3>
                        <div class="price">£49.99/month</div>
                        <ul>
                            <li>10,000 images per month</li>
                            <li>White-label options</li>
                            <li>Priority support</li>
                        </ul>
                        <button onclick="openStripeLink('<?php echo $this->stripe_links['agency']; ?>')" 
                                class="button button-primary button-large">Upgrade to Agency</button>
                    </div>
                    
                    <div class="plan-card">
                        <h3>Credits Pack</h3>
                        <div class="price">£9.99 one-time</div>
                        <ul>
                            <li>100 images</li>
                            <li>No expiration</li>
                            <li>Use with any plan</li>
                        </ul>
                        <button onclick="openStripeLink('<?php echo $this->stripe_links['credits']; ?>')" 
                                class="button button-primary button-large">Buy Credits</button>
                    </div>
                </div>
                
                <!-- Alternative direct links -->
                <div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border: 1px solid #ddd;">
                    <h3>Direct Links (if buttons don't work):</h3>
                    <p><a href="<?php echo $this->stripe_links['pro']; ?>" target="_blank">Pro Plan Link</a></p>
                    <p><a href="<?php echo $this->stripe_links['agency']; ?>" target="_blank">Agency Plan Link</a></p>
                    <p><a href="<?php echo $this->stripe_links['credits']; ?>" target="_blank">Credits Link</a></p>
                </div>
            </div>
        </div>
        
        <script>
        function openStripeLink(url) {
            console.log('Opening Stripe link:', url);
            window.open(url, '_blank');
        }
        </script>
        
        <style>
        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .plan-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background: #fff;
        }
        
        .plan-card h3 {
            margin-top: 0;
            color: #0073aa;
        }
        
        .price {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
            margin: 10px 0;
        }
        
        .plan-card ul {
            list-style: none;
            padding: 0;
            margin: 15px 0;
        }
        
        .plan-card li {
            padding: 5px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .button-large {
            font-size: 16px;
            padding: 12px 24px;
            margin-top: 15px;
        }
        </style>
        <?php
    }
}

new AI_Alt_Text_Generator_JS();
