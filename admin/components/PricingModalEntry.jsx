import React, { useState, useEffect } from 'react';
import ReactDOM from 'react-dom/client';
import EnterprisePricingModal from './EnterprisePricingModal';

/**
 * Pricing Modal Entry Point
 * Renders the pricing modal and handles API integration
 */
const PricingModalWrapper = ({ onPlanSelect }) => {
  const [isOpen, setIsOpen] = useState(false);
  const [currentPlan, setCurrentPlan] = useState(null);
  const [isLoading, setIsLoading] = useState(false);

  // Fetch current user plan
  useEffect(() => {
    const fetchUserPlan = async () => {
      try {
        setIsLoading(true);
        const apiUrl = window.bbai_ajax?.api_url || '/api/user/plan';
        const token = window.bbai_ajax?.jwt_token || localStorage.getItem('bbai_jwt_token');
        
        const response = await fetch(apiUrl, {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            ...(token && { 'Authorization': `Bearer ${token}` }),
          },
          credentials: 'include',
        });

        if (response.ok) {
          const data = await response.json();
          setCurrentPlan(data.plan || data.data?.plan || 'free');
        } else {
          // If API not available, default to free
          setCurrentPlan('free');
        }
      } catch (error) {
        console.warn('[AltText AI] Could not fetch user plan:', error);
        setCurrentPlan('free');
      } finally {
        setIsLoading(false);
      }
    };

    if (isOpen) {
      fetchUserPlan();
    }
  }, [isOpen]);

  // Expose open function globally
  useEffect(() => {
    window.openPricingModal = (variant = 'enterprise') => {
      setIsOpen(true);
    };

    window.closePricingModal = () => {
      setIsOpen(false);
    };

    return () => {
      delete window.openPricingModal;
      delete window.closePricingModal;
    };
  }, []);

  const handlePlanSelect = async (planId, mode = 'subscription') => {
    if (onPlanSelect && typeof onPlanSelect === 'function') {
      await onPlanSelect(planId, mode);
      return;
    }

    // Enterprise plan: Redirect to Book a Call page
    if (planId === 'enterprise') {
      const bookCallUrl = 'https://github.com/beepbeepv2/beepbeep-ai-alt-text-generator';
      window.location.href = bookCallUrl;
      setIsOpen(false);
      return;
    }

    // Get WordPress context data
    const siteUrl = window.location.origin || '';
    const userId = window.bbai_ajax?.user_id || (typeof get_current_user_id === 'function' ? get_current_user_id() : '') || '';
    const siteId = window.bbai_ajax?.site_id || '';
    
    try {
      // Use WordPress AJAX handler to create Stripe checkout session
      if (!window.bbai_ajax || !window.bbai_ajax.ajaxurl) {
        throw new Error('WordPress AJAX not available');
      }

      // Prepare request body with all required parameters
      const requestBody = new URLSearchParams({
        action: 'beepbeepai_create_checkout',
        nonce: window.bbai_ajax.nonce || '',
        plan_id: planId,
        mode: mode, // 'subscription' or 'payment' for one-time
        site_url: siteUrl,
        user_id: userId.toString(),
        wordpress_site_id: siteId,
      });

      // If it's a credits pack, include pack info
      if (planId.startsWith('pack-')) {
        requestBody.append('product_type', 'credits');
      }

      const response = await fetch(window.bbai_ajax.ajaxurl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: requestBody,
      });

      const data = await response.json();
      
      if (data.success && data.data && data.data.url) {
        // Redirect to Stripe checkout (opens in same window)
        window.location.href = data.data.url;
        setIsOpen(false);
      } else {
        throw new Error(data.data?.message || 'Failed to create checkout session');
      }
    } catch (error) {
      console.error('[AltText AI] Checkout error:', error);
      window.bbaiModal.error('Unable to start checkout. Please try again or contact support.');
    }
  };

  return (
    <EnterprisePricingModal
      isOpen={isOpen}
      onClose={() => setIsOpen(false)}
      currentPlan={currentPlan}
      onPlanSelect={handlePlanSelect}
      isLoading={isLoading}
    />
  );
};

/**
 * Initialize the pricing modal
 * Call this function to mount the React component
 */
export const initPricingModal = (containerId = 'bbai-pricing-modal-root', onPlanSelect) => {
  // Check if React is available
  if (typeof React === 'undefined' || typeof ReactDOM === 'undefined') {
    console.error('[AltText AI] React is not loaded. Please include React and ReactDOM.');
    return;
  }

  // Create or get container
  let container = document.getElementById(containerId);
  if (!container) {
    container = document.createElement('div');
    container.id = containerId;
    document.body.appendChild(container);
  }

  // Create root and render
  const root = ReactDOM.createRoot(container);
  root.render(<PricingModalWrapper onPlanSelect={onPlanSelect} />);
  
  return root;
};

// Auto-initialize if in WordPress environment
if (typeof window !== 'undefined' && window.document) {
  // Wait for DOM to be ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      if (window.bbai_ajax && typeof React !== 'undefined' && typeof ReactDOM !== 'undefined') {
        initPricingModal();
      }
    });
  } else {
    if (window.bbai_ajax && typeof React !== 'undefined' && typeof ReactDOM !== 'undefined') {
      initPricingModal();
    }
  }
}

export default PricingModalWrapper;

