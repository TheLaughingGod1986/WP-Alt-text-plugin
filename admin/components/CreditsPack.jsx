import React, { useState } from 'react';

/**
 * Credits Pack Component - Redesigned as Mini Product Card
 * 
 * Key Improvements:
 * - Converted from passive grey block to engaging product card
 * - Added title, subtitle, and clear value proposition
 * - Improved CTA button with "Buy credits" action
 * - Added "Credits never expire" micro text
 * - Enhanced visual hierarchy and spacing
 */
const CreditsPack = ({ onPurchase }) => {
  const [ripples, setRipples] = useState({});

  const creditPacks = [
    {
      id: 'pack-small',
      credits: 100,
      price: 9.99,
      savings: null,
      popular: false,
    },
    {
      id: 'pack-medium',
      credits: 500,
      price: 34.99,
      savings: 10,
      popular: true,
    },
    {
      id: 'pack-large',
      credits: 2000,
      price: 129.99,
      savings: 20,
      popular: false,
    },
  ];

  const handlePurchaseCredits = async (packId, e) => {
    // Ripple effect
    const button = e.currentTarget;
    const rect = button.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = e.clientX - rect.left - size / 2;
    const y = e.clientY - rect.top - size / 2;
    
    setRipples((prev) => ({ ...prev, [packId]: { x, y, size } }));
    setTimeout(() => {
      setRipples((prev) => {
        const next = { ...prev };
        delete next[packId];
        return next;
      });
    }, 600);

    // Call the purchase handler for one-time payment
    if (onPurchase && typeof onPurchase === 'function') {
      await onPurchase(packId, 'payment'); // 'payment' mode for one-time
    } else {
      console.error('[AltText AI] No purchase handler available');
    }
  };

  return (
    <div className="bg-gradient-to-br from-slate-50 to-white border-2 border-slate-200 rounded-2xl shadow-lg p-8">
      {/* Header Section */}
      <div className="mb-8 text-center">
        <h3 className="text-3xl font-bold text-slate-900 mb-2 font-inter">
          One-Time Credits
        </h3>
        <p className="text-slate-600 font-normal text-base font-inter leading-6">
          Perfect for audits or one-off projects
        </p>
      </div>

      {/* Credit Packs Grid */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        {creditPacks.map((pack) => (
          <div
            key={pack.id}
            className={`relative bg-white border-2 rounded-xl shadow-md hover:shadow-lg transition-all duration-300 p-6 flex flex-col ${
              pack.popular 
                ? 'border-blue-600 ring-2 ring-blue-100' 
                : 'border-slate-200 hover:border-slate-300'
            }`}
          >
            {/* Popular Badge */}
            {pack.popular && (
              <div className="absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-1/2 z-10">
                <span className="bg-blue-600 text-white text-xs font-bold px-3 py-1 rounded-full shadow-md font-inter uppercase">
                  Best Value
                </span>
              </div>
            )}

            {/* Credits Amount */}
            <div className="mb-4 text-center">
              <div className="text-4xl font-extrabold tracking-tight text-slate-900 mb-1 font-inter">
                {pack.credits.toLocaleString()}
              </div>
              <div className="text-sm text-slate-600 font-medium font-inter">
                Credits
              </div>
            </div>

            {/* Price */}
            <div className="mb-6 text-center">
              <div className="flex items-baseline justify-center mb-2">
                <span className="text-3xl font-bold tracking-tight text-slate-900 font-inter">
                  £{pack.price.toFixed(2)}
                </span>
              </div>
              {pack.savings && (
                <div className="inline-flex items-center px-2 py-1 rounded-full bg-emerald-50 border border-emerald-200">
                  <span className="text-xs font-semibold text-emerald-700 font-inter">
                    Save {pack.savings}%
                  </span>
                </div>
              )}
            </div>

            {/* CTA Button */}
            <button
              onClick={(e) => handlePurchaseCredits(pack.id, e)}
              className="bbai-btn bbai-btn-lg bbai-btn-info bbai-btn-block"
              tabIndex={0}
              aria-label={`Buy ${pack.credits} credits pack`}
            >
              {ripples[pack.id] && (
                <span
                  className="absolute rounded-full bg-white/30 pointer-events-none animate-ripple"
                  style={{
                    left: ripples[pack.id].x,
                    top: ripples[pack.id].y,
                    width: ripples[pack.id].size,
                    height: ripples[pack.id].size,
                    transform: 'scale(0)',
                  }}
                />
              )}
              <span className="relative z-10">Buy Credits</span>
            </button>
          </div>
        ))}
      </div>

      {/* Micro Text - Credits Never Expire */}
      <div className="text-center">
        <p className="text-xs text-slate-500 font-normal font-inter">
          <svg
            className="w-4 h-4 inline-block mr-1 text-slate-400"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
          Credits never expire
        </p>
      </div>
    </div>
  );
};

export default CreditsPack;
