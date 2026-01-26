import React from 'react';

/**
 * Upgrade Card
 * Dynamic upgrade card that adapts based on current plan
 * Shows upgrade path and benefits
 */
const UpgradeCard = ({ 
  currentPlan = 'free',
  upgradeTarget = 'growth',
  onUpgradeClick,
  usagePercent = 0
}) => {
  const isFree = currentPlan === 'free';
  const isGrowth = currentPlan === 'growth' || currentPlan === 'pro';
  const isAgency = currentPlan === 'agency';
  const isNearLimit = usagePercent >= 80;

  // Upgrade target content
  const upgradeContent = {
    growth: {
      badge: 'Upgrade',
      title: 'Upgrade to Growth',
      subtitle: 'Unlock bulk processing, more credits, and priority queues.',
      supportingText: 'Perfect for growing stores with hundreds of images.',
      features: [
        '1,000 AI alt text per month',
        'Bulk processing',
        'Priority queues'
      ]
    },
    agency: {
      badge: 'Scale',
      title: 'Scale to Agency as you add more sites',
      subtitle: 'Allocate AI alt text across client sites, see usage per site, and export reports for billing.',
      features: [
        'Multi site usage overview',
        'Usage per site for invoicing',
        'Export-ready SEO and billing reports'
      ]
    }
  };

  // Dynamic messaging when near limit
  const getUrgentMessage = () => {
    if (isNearLimit && isFree) {
      return 'You are almost out of free credits. Upgrade to Growth to keep optimising.';
    }
    return null;
  };

  if (isFree) {
    const content = upgradeContent.growth;
    return (
      <div className="bg-gradient-to-br from-sky-600 via-sky-500 to-indigo-500 rounded-xl shadow-lg p-6 text-white flex flex-col">
        <span className="inline-flex items-center rounded-full bg-white/15 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-white mb-4 w-fit">
          {content.badge}
        </span>
        
        <h2 className="text-[22px] font-semibold mb-2 text-white">{content.title}</h2>
        
        {isNearLimit && (
          <p className="text-[13px] text-slate-100 mb-3 font-semibold bg-white/15 rounded-md px-3 py-2">
            {getUrgentMessage()}
          </p>
        )}
        
        <p className="text-[15px] text-slate-100 mb-4 leading-relaxed">{content.subtitle}</p>
        <p className="text-[12px] text-slate-200 mb-4">{content.supportingText}</p>
        
        <ul className="space-y-2 mb-6 flex-grow">
          {content.features.map((feature, index) => (
            <li key={index} className="flex items-start gap-3 text-[15px] text-slate-100">
              <span className="mt-0.5 inline-flex h-6 w-6 items-center justify-center rounded-full bg-white/20">
                <svg
                  className="h-4 w-4 text-white"
                  viewBox="0 0 16 16"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2.5"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                >
                  <path d="M13 4L6 11L3 8" />
                </svg>
              </span>
              <span>{feature}</span>
            </li>
          ))}
        </ul>
        
        <button
          onClick={onUpgradeClick}
          className="w-full rounded-full bg-white px-6 py-3 text-[15px] font-semibold text-slate-900 shadow-lg transition duration-200 hover:-translate-y-0.5 hover:bg-slate-50 hover:shadow-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-sky-600"
        >
          Upgrade to Growth
        </button>
      </div>
    );
  }

  if (isGrowth) {
    const content = upgradeContent.agency;
    return (
      <div className="bg-gradient-to-br from-indigo-600 to-purple-600 rounded-xl shadow-lg p-6 text-white flex flex-col">
        <span className="inline-flex items-center rounded-full bg-white/15 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-white mb-4 w-fit">
          {content.badge}
        </span>
        
        <h2 className="text-[22px] font-semibold mb-3 text-white">{content.title}</h2>
        <p className="text-[15px] text-slate-100 mb-4 leading-relaxed">{content.subtitle}</p>
        
        <ul className="space-y-2 mb-6 flex-grow">
          {content.features.map((feature, index) => (
            <li key={index} className="flex items-start gap-3 text-[15px] text-slate-100">
              <span className="mt-0.5 inline-flex h-6 w-6 items-center justify-center rounded-full bg-white/20">
                <svg
                  className="h-4 w-4 text-white"
                  viewBox="0 0 16 16"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2.5"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                >
                  <path d="M13 4L6 11L3 8" />
                </svg>
              </span>
              <span>{feature}</span>
            </li>
          ))}
        </ul>
        
        <button
          onClick={onUpgradeClick}
          className="w-full rounded-full bg-white px-6 py-3 text-[15px] font-semibold text-slate-900 shadow-lg transition duration-200 hover:-translate-y-0.5 hover:bg-slate-50 hover:shadow-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-indigo-600"
        >
          Explore Agency
        </button>
      </div>
    );
  }

  // Agency plan - show billing management
  return (
    <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
      <h2 className="text-lg font-semibold text-slate-900 mb-2">Agency Plan Active</h2>
      <p className="text-sm text-slate-700 mb-4">
        Managing multiple sites with unlimited AI alt text generations.
      </p>
      {onUpgradeClick && (
        <button
          onClick={onUpgradeClick}
          className="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white px-5 py-2.5 text-[13px] font-semibold text-slate-700 transition duration-200 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
        >
          Manage Billing
        </button>
      )}
    </div>
  );
};

export default UpgradeCard;
