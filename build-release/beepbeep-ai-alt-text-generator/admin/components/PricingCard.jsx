import React, { useState } from 'react';

/**
 * Pricing Card Component - Redesigned for Maximum Conversion
 * 
 * Key Improvements:
 * - Use case labels under plan titles
 * - Value-driven feature bullets with icons
 * - Monthly/annual pricing support
 * - Enhanced shadows, spacing, and hover states
 * - Improved typography hierarchy
 * - Better CTA button placement and states
 */
const PricingCard = ({ plan, currentPlan = null, onSelect, billingPeriod = 'monthly', annualSavings = null }) => {
  const [ripple, setRipple] = useState(null);
  const [isPressed, setIsPressed] = useState(false);

  // Check if user is already on this plan
  const isCurrentPlan = currentPlan && currentPlan.toLowerCase() === plan.id.toLowerCase();
  const isDisabled = isCurrentPlan;

  // Get display price based on billing period
  const displayPrice = billingPeriod === 'annual' && plan.price > 0 
    ? plan.priceAnnual 
    : plan.price;
  
  const displayPeriod = billingPeriod === 'annual' ? 'year' : 'month';

  const handleSelectPlan = (e) => {
    if (isDisabled) {
      return;
    }

    if (onSelect && typeof onSelect === 'function') {
      // Pass plan ID and subscription mode, billing period can be handled separately if needed
      onSelect(plan.id, 'subscription');
    } else {
      console.log('Selected plan:', plan.id, billingPeriod);
    }
    
    // Ripple effect
    const button = e.currentTarget;
    const rect = button.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = e.clientX - rect.left - size / 2;
    const y = e.clientY - rect.top - size / 2;
    
    setRipple({ x, y, size });
    setIsPressed(true);
    
    setTimeout(() => {
      setRipple(null);
      setIsPressed(false);
    }, 600);
  };

  // Get CTA button text
  const getCtaText = () => {
    if (isCurrentPlan) {
      return 'Current Plan';
    }
    if (plan.id === 'free') {
      return 'Get Started Free';
    }
    return billingPeriod === 'annual' ? 'Get Started' : 'Upgrade';
  };

  return (
    <div
      className={`relative bg-white border-2 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 p-8 flex flex-col h-full ${
        plan.popular 
          ? 'border-blue-600 ring-2 ring-blue-100 scale-105 md:scale-105' 
          : 'border-slate-200 hover:border-slate-300'
      } ${plan.id === 'free' ? 'opacity-95' : ''}`}
    >
      {/* Popular Badge */}
      {plan.popular && (
        <div className="absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-1/2 z-10">
          <span className="bg-gradient-to-r from-blue-600 to-blue-700 text-white text-xs font-bold px-4 py-1.5 rounded-full shadow-lg font-inter uppercase tracking-wide">
            Most Popular
          </span>
        </div>
      )}

      {/* Plan Header */}
      <div className="mb-6">
        <h3 className="text-3xl font-bold text-slate-900 mb-2 font-inter text-left">
          {plan.name}
        </h3>
        {/* Use Case Label */}
        <p className="text-slate-600 font-normal text-sm font-inter leading-5 text-left mb-4">
          {plan.useCase}
        </p>
      </div>

      {/* Price Section */}
      <div className="mb-6">
        <div className="flex items-baseline mb-2">
          <span className="text-5xl font-extrabold tracking-tight text-slate-900 font-inter">
            {plan.price === 0 ? 'Free' : `£${displayPrice.toFixed(displayPrice % 1 !== 0 ? 2 : 0)}`}
          </span>
          {plan.price > 0 && (
            <span className="text-slate-600 text-lg font-normal ml-2 font-inter">
              /{displayPeriod}
            </span>
          )}
        </div>
        {/* Monthly Quota */}
        <p className="text-sm text-slate-600 font-medium font-inter">
          {plan.monthlyQuota}
        </p>
        {/* Annual Savings Badge */}
        {billingPeriod === 'annual' && annualSavings && (
          <div className="mt-2 inline-flex items-center px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200">
            <span className="text-xs font-semibold text-emerald-700 font-inter">
              Save £{annualSavings} per year
            </span>
          </div>
        )}
      </div>

      {/* Features List */}
      <div className="flex-1 mb-8">
        <ul className="space-y-4">
          {plan.features.map((feature, index) => (
            <li key={index} className="flex items-start">
              <div className="flex-shrink-0 mt-0.5">
                <svg
                  className="w-5 h-5 text-emerald-500"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2.5}
                    d="M5 13l4 4L19 7"
                  />
                </svg>
              </div>
              <span className="text-sm text-slate-700 font-normal leading-6 font-inter ml-3">
                {feature}
              </span>
            </li>
          ))}
        </ul>
      </div>

      {/* CTA Button */}
      <button
        onClick={handleSelectPlan}
        onMouseDown={() => !isDisabled && setIsPressed(true)}
        onMouseUp={() => setIsPressed(false)}
        onMouseLeave={() => setIsPressed(false)}
        disabled={isDisabled}
        className={`relative w-full py-4 px-6 rounded-xl font-semibold text-base font-inter transition-all duration-200 overflow-hidden focus:outline-none focus:ring-2 focus:ring-offset-2 ${
          isDisabled
            ? 'bg-gradient-to-r from-slate-400 to-slate-500 text-white cursor-not-allowed opacity-70'
            : plan.popular
            ? 'bg-gradient-to-r from-emerald-500 to-emerald-600 text-white hover:from-emerald-600 hover:to-emerald-700 shadow-lg hover:shadow-xl'
            : plan.id === 'free'
            ? 'bg-gradient-to-r from-slate-500 to-slate-600 text-white hover:from-slate-600 hover:to-slate-700 border-2 border-slate-500 shadow-md hover:shadow-lg'
            : 'bg-gradient-to-r from-violet-600 to-violet-700 text-white hover:from-violet-700 hover:to-violet-800 shadow-md hover:shadow-lg'
        } ${
          isPressed && !isDisabled ? 'scale-95' : ''
        }`}
        tabIndex={isDisabled ? -1 : 0}
        aria-label={isDisabled ? `Current plan: ${plan.name}` : `Select ${plan.name} plan`}
        aria-disabled={isDisabled}
      >
        {ripple && !isDisabled && (
          <span
            className="absolute rounded-full bg-white/30 pointer-events-none animate-ripple"
            style={{
              left: ripple.x,
              top: ripple.y,
              width: ripple.size,
              height: ripple.size,
              transform: 'scale(0)',
            }}
          />
        )}
        <span className="relative z-10">
          {getCtaText()}
        </span>
      </button>
    </div>
  );
};

export default PricingCard;
