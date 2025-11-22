import React, { useState } from 'react';

/**
 * Pricing Card Component
 * Displays individual plan information with enterprise styling
 */
const PricingCard = ({ plan, currentPlan = null, onSelect }) => {
  const [ripple, setRipple] = useState(null);
  const [isPressed, setIsPressed] = useState(false);

  // Check if user is already on this plan
  const isCurrentPlan = currentPlan && currentPlan.toLowerCase() === plan.id.toLowerCase();
  const isDisabled = isCurrentPlan;

  const handleSelectPlan = (e) => {
    if (isDisabled) {
      return;
    }

    if (onSelect && typeof onSelect === 'function') {
      onSelect(plan.id);
    } else {
      console.log('Selected plan:', plan.id);
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

  return (
    <div
      className={`relative bg-white border border-slate-200 rounded-xl shadow-sm hover:shadow-md transition-all duration-200 p-6 md:p-4 flex flex-col h-full ${
        plan.popular ? 'border-blue-600' : ''
      }`}
    >
      {/* Popular Badge */}
      {plan.popular && (
        <div className="absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-1/2">
          <span className="bg-blue-600 text-white text-xs font-semibold px-3 py-1 rounded-lg font-inter">
            MOST POPULAR
          </span>
        </div>
      )}

      {/* SLA Badge */}
      {plan.sla && (
        <div className="absolute top-4 right-4">
          <span className="bg-slate-900 text-white text-xs font-semibold px-2 py-1 rounded font-inter">
            SLA
          </span>
        </div>
      )}

      {/* Plan Header */}
      <div className="mb-6">
        <h3 className="text-2xl font-semibold text-slate-900 mb-2 font-inter text-left">
          {plan.name}
        </h3>
        <p className="text-slate-700 font-medium text-sm font-normal font-inter leading-6 text-left">
          {plan.description}
        </p>
      </div>

      {/* Price */}
      <div className="mb-6">
        <div className="flex items-baseline">
          <span className="text-3xl font-bold tracking-tight text-slate-900 font-inter">
            £{plan.price.toFixed(plan.price % 1 !== 0 ? 2 : 0)}
          </span>
          <span className="text-slate-600 text-sm font-normal ml-1 font-inter">
            /{plan.period}
          </span>
        </div>
      </div>

      {/* Features List */}
      <div className="flex-1 mb-6">
        <ul className="space-y-3">
          {plan.features.map((feature, index) => (
            <li key={index} className="flex items-start">
              <svg
                className="w-5 h-5 text-emerald-500 mt-0.5 mr-2 flex-shrink-0"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M5 13l4 4L19 7"
                />
              </svg>
              <span className="text-sm text-slate-700 font-normal leading-6 font-inter">
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
        className={`relative w-full py-3 px-6 rounded-lg font-medium text-base font-inter transition-all duration-150 overflow-hidden focus:outline-none focus:ring-2 focus:ring-blue-300 ${
          isDisabled
            ? 'bg-slate-300 text-slate-500 cursor-not-allowed'
            : 'bg-blue-600 text-white hover:bg-blue-700'
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
          {isDisabled ? `Current Plan` : `Select ${plan.name}`}
        </span>
      </button>
    </div>
  );
};

export default PricingCard;

