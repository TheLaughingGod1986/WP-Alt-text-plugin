<?php
/**
 * Simple Stripe Payment Links Integration
 * No backend required - direct payment links
 */

class Simple_Stripe_Integration {
    
    private $payment_links = [
        'pro' => 'https://buy.stripe.com/test_pro',
        'agency' => 'https://buy.stripe.com/test_agency', 
        'credits' => 'https://buy.stripe.com/test_credits'
    ];
    
    public function get_payment_url($plan_type) {
        return $this->payment_links[$plan_type] ?? $this->payment_links['pro'];
    }
    
    public function handle_upgrade($plan_type) {
        $payment_url = $this->get_payment_url($plan_type);
        wp_redirect($payment_url);
        exit;
    }
}
