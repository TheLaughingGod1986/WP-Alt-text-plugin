import React, { useEffect, useRef, useState } from 'react';
import PricingCard from './PricingCard';
import CreditsPack from './CreditsPack';
import TrustBadges from './TrustBadges';
import ComparisonTable from './ComparisonTable';
import MultiUserNotice from './MultiUserNotice';
import './pricing-modal.css';

/**
 * Enterprise Pricing Modal - Redesigned for Maximum Conversion
 * 
 * Key Improvements:
 * - Added Free, Growth, Agency plans (replacing Pro, Agency, Enterprise)
 * - Monthly/Annual billing toggle with savings highlights
 * - Use case labels under each plan title
 * - Value-driven feature bullets (outcomes vs features)
 * - Enhanced UI polish (shadows, spacing, hover states, icons)
 * - Improved CTA placement and discoverability
 * - Reworked credits pack as mini product card
 */
const EnterprisePricingModal = ({ isOpen, onClose, currentPlan = null, onPlanSelect, isLoading = false }) => {
  const modalRef = useRef(null);
  const overlayRef = useRef(null);
  const firstFocusableRef = useRef(null);
  const lastFocusableRef = useRef(null);
  const [billingPeriod, setBillingPeriod] = useState('monthly'); // 'monthly' or 'annual'

  // Handle ESC key to close modal
  useEffect(() => {
    const handleEsc = (e) => {
      if (e.key === 'Escape' && isOpen) {
        onClose();
      }
    };

    if (isOpen) {
      document.addEventListener('keydown', handleEsc);
      // Store the previously focused element
      const previousFocus = document.activeElement;
      
      // Focus trap: Get all focusable elements
      const focusableElements = modalRef.current?.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
      );
      
      if (focusableElements && focusableElements.length > 0) {
        firstFocusableRef.current = focusableElements[0];
        lastFocusableRef.current = focusableElements[focusableElements.length - 1];
        
        // Focus first element after a short delay to ensure modal is rendered
        setTimeout(() => {
          firstFocusableRef.current?.focus();
        }, 100);
      }

      // Prevent body scroll when modal is open
      document.body.style.overflow = 'hidden';

      return () => {
        document.removeEventListener('keydown', handleEsc);
        document.body.style.overflow = '';
        // Restore focus to previously focused element
        if (previousFocus instanceof HTMLElement) {
          previousFocus.focus();
        }
      };
    }
  }, [isOpen, onClose]);

  // Handle focus trap with Tab key
  useEffect(() => {
    const handleTab = (e) => {
      if (!isOpen || !modalRef.current) return;

      const focusableElements = modalRef.current.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
      );

      if (focusableElements.length === 0) return;

      const firstElement = focusableElements[0];
      const lastElement = focusableElements[focusableElements.length - 1];

      if (e.shiftKey) {
        // Shift + Tab
        if (document.activeElement === firstElement) {
          e.preventDefault();
          lastElement.focus();
        }
      } else {
        // Tab
        if (document.activeElement === lastElement) {
          e.preventDefault();
          firstElement.focus();
        }
      }
    };

    if (isOpen) {
      document.addEventListener('keydown', handleTab);
      return () => {
        document.removeEventListener('keydown', handleTab);
      };
    }
  }, [isOpen]);

  if (!isOpen) return null;

  // Calculate annual prices (2 months free = 10 months of monthly price)
  const calculateAnnualPrice = (monthlyPrice) => {
    return (monthlyPrice * 10).toFixed(2);
  };

  const plans = [
    {
      id: 'free',
      name: 'Free',
      price: 0,
      priceAnnual: 0,
      period: billingPeriod,
      useCase: 'For testing and trying out the tool',
      monthlyQuota: '50 AI-generations per month',
      features: [
        'Optimise full libraries in seconds',
        'Auto-generate on upload',
        'Review and edit every description',
        'Track usage in real-time dashboard',
        'WCAG-compliant output',
      ],
    },
    {
      id: 'growth',
      name: 'Growth',
      price: 12.99,
      priceAnnual: parseFloat(calculateAnnualPrice(12.99)),
      period: billingPeriod,
      useCase: 'For growing stores and content-heavy sites',
      monthlyQuota: '1,000 AI-generations per month',
      features: [
        'Optimise full libraries in seconds',
        'Instant processing, no waiting',
        'Advanced SEO keyword optimization',
        'Priority support response',
        'API access for automation',
      ],
      popular: true,
    },
    {
      id: 'agency',
      name: 'Agency',
      price: 49.99,
      priceAnnual: parseFloat(calculateAnnualPrice(49.99)),
      period: billingPeriod,
      useCase: 'For freelancers, SEO/Shopify agencies, and site managers',
      monthlyQuota: '10,000 AI-generations per month',
      features: [
        'Optimise full libraries in seconds',
        'Instant processing, no waiting',
        'Manage and bill client sites from one dashboard',
        'Priority support with dedicated channel',
        'Multi-user access and permissions',
        'API access + webhooks',
      ],
    },
  ];

  // Calculate savings for annual billing
  const getAnnualSavings = (monthlyPrice) => {
    const annualTotal = monthlyPrice * 12;
    const annualPrice = monthlyPrice * 10;
    return (annualTotal - annualPrice).toFixed(2);
  };

  return (
    <div
      ref={overlayRef}
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm animate-fadeIn"
      onClick={onClose}
      role="dialog"
      aria-modal="true"
      aria-labelledby="modal-title"
      aria-describedby="modal-description"
    >
      <div
        ref={modalRef}
        className="bbai-upgrade-modal__content relative mx-4"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Close Button */}
        <button
          ref={lastFocusableRef}
          onClick={onClose}
          className="absolute top-6 right-6 z-10 text-slate-500 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-300 rounded-lg p-1 transition-colors"
          aria-label="Close modal"
          tabIndex={0}
        >
          <svg
            className="w-6 h-6"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M6 18L18 6M6 6l12 12"
            />
          </svg>
        </button>

        {/* Modal Content */}
        <div className="overflow-x-hidden p-10 pb-20 space-y-10 mb-16">
          {/* Header Section */}
          <div>
            <h2
              id="modal-title"
              className="text-4xl font-semibold text-slate-900 mb-4 font-inter text-left"
            >
              Choose Your Plan
            </h2>
            <p
              id="modal-description"
              className="text-base text-slate-600 font-normal font-inter text-left max-w-2xl"
            >
              Select the plan that best fits your needs. All plans include
              our core features with varying capacity and support levels.
            </p>
          </div>

          {/* Billing Period Toggle */}
          <div className="flex items-center justify-center">
            <div className="bg-slate-100 rounded-lg p-1 inline-flex">
              <button
                onClick={() => setBillingPeriod('monthly')}
                className={`px-6 py-2 rounded-md text-sm font-medium transition-all ${
                  billingPeriod === 'monthly'
                    ? 'bg-white text-slate-900 shadow-sm'
                    : 'text-slate-600 hover:text-slate-900'
                }`}
                aria-label="Monthly billing"
              >
                Monthly
              </button>
              <button
                onClick={() => setBillingPeriod('annual')}
                className={`px-6 py-2 rounded-md text-sm font-medium transition-all relative ${
                  billingPeriod === 'annual'
                    ? 'bg-white text-slate-900 shadow-sm'
                    : 'text-slate-600 hover:text-slate-900'
                }`}
                aria-label="Annual billing"
              >
                Annual
                <span className="ml-2 text-xs bg-emerald-500 text-white px-2 py-0.5 rounded-full font-semibold">
                  Save 2 months
                </span>
              </button>
            </div>
          </div>

          {/* Trust Badges */}
          <div className="bg-slate-50 border border-slate-200 rounded-xl p-6">
            <TrustBadges />
          </div>

          {/* Pricing Cards */}
          <div>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              {plans.map((plan) => (
                <PricingCard
                  key={plan.id}
                  plan={plan}
                  currentPlan={currentPlan}
                  onSelect={onPlanSelect}
                  billingPeriod={billingPeriod}
                  annualSavings={plan.price > 0 ? getAnnualSavings(plan.price) : null}
                />
              ))}
            </div>
          </div>

          {/* Credits Pack Section */}
          <div className="mt-16">
            <CreditsPack onPurchase={onPlanSelect} />
          </div>

          {/* Comparison Table */}
          <div>
            <h3 className="text-2xl font-semibold text-slate-900 mb-4 font-inter text-left">
              Feature Comparison
            </h3>
            <ComparisonTable plans={plans} />
          </div>

          {/* Multi-User Notice */}
          <div>
            <MultiUserNotice />
          </div>

          {/* Footer */}
          <div className="border-t border-slate-200 pt-6">
            <p className="text-sm text-slate-500 font-normal font-inter text-left">
              Questions? Contact our sales team at{' '}
              <a
                href="mailto:sales@example.com"
                className="text-blue-600 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-300 rounded px-1 transition-colors underline"
                tabIndex={0}
                aria-label="Contact sales team"
              >
                sales@example.com
              </a>
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};

export default EnterprisePricingModal;
