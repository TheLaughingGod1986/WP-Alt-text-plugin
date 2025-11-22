import React, { useState } from 'react';

/**
 * Credits Pack Component
 * Displays one-time credit pack options
 */
const CreditsPack = ({ onPurchase }) => {
  const [ripples, setRipples] = useState({});
  const [pressed, setPressed] = useState({});

  const creditPacks = [
    {
      id: 'pack-small',
      credits: 500,
      price: 9.99,
      savings: null,
    },
    {
      id: 'pack-medium',
      credits: 2000,
      price: 34.99,
      savings: 10,
    },
    {
      id: 'pack-large',
      credits: 5000,
      price: 79.99,
      savings: 20,
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
    setPressed((prev) => ({ ...prev, [packId]: true }));
    
    setTimeout(() => {
      setRipples((prev) => {
        const next = { ...prev };
        delete next[packId];
        return next;
      });
      setPressed((prev) => {
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
    <div>
      <div className="mb-6">
        <h3 className="text-2xl font-semibold text-slate-900 mb-3 font-inter text-left">
          One-Time Credit Packs
        </h3>
        <p className="text-slate-600 font-normal text-sm font-inter text-left">
          Need extra credits? Purchase one-time credit packs that never expire.
        </p>
      </div>

      <div className="bbai-pricing-container">
        {creditPacks.map((pack) => (
          <div
            key={pack.id}
            className="bg-white border border-slate-200 rounded-xl shadow-sm hover:shadow-md transition-all duration-200 p-6 md:p-4"
          >
            <div className="mb-6">
              <div className="text-3xl font-bold tracking-tight text-slate-900 mb-2 font-inter">
                {pack.credits.toLocaleString()}
              </div>
              <div className="text-sm text-slate-600 font-normal font-inter">
                Credits
              </div>
            </div>

            <div className="mb-6">
              <div className="flex items-baseline mb-2">
                <span className="text-3xl font-bold tracking-tight text-slate-900 font-inter">
                  £{pack.price.toFixed(2)}
                </span>
              </div>
              {pack.savings && (
                <div className="text-sm text-emerald-500 font-medium font-inter">
                  Save {pack.savings}%
                </div>
              )}
            </div>

            <button
              onClick={(e) => handlePurchaseCredits(pack.id, e)}
              onMouseDown={() => setPressed((prev) => ({ ...prev, [pack.id]: true }))}
              onMouseUp={() => setPressed((prev) => {
                const next = { ...prev };
                delete next[pack.id];
                return next;
              })}
              onMouseLeave={() => setPressed((prev) => {
                const next = { ...prev };
                delete next[pack.id];
                return next;
              })}
              className={`relative w-full py-3 px-6 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-300 transition-all duration-150 overflow-hidden text-sm font-inter ${
                pressed[pack.id] ? 'scale-95' : ''
              }`}
              tabIndex={0}
              aria-label={`Purchase ${pack.credits} credits pack`}
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
              <span className="relative z-10">Purchase Pack</span>
            </button>
          </div>
        ))}
      </div>
    </div>
  );
};

export default CreditsPack;

