import React from 'react';

/**
 * Plan Status Card
 * Displays circular usage indicator for the current plan
 * Shows quota usage percentage, count, and reset date
 */
const PlanStatusCard = ({ 
  planName = 'Free Plan',
  used = 0,
  limit = 50,
  resetDate = '',
  usagePercent = 0,
  onManageBilling,
  showBillingLink = false
}) => {
  // Calculate percentage (0-100)
  const percentage = Math.min(100, Math.max(0, usagePercent));
  
  // Calculate circle circumference
  const radius = 70;
  const circumference = 2 * Math.PI * radius;
  const offset = circumference - (percentage / 100) * circumference;

  // Format reset date
  const formatResetDate = (dateString) => {
    if (!dateString) return 'Renews monthly';
    try {
      const date = new Date(dateString);
      return `Renews ${date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}`;
    } catch {
      return 'Renews monthly';
    }
  };

  // Determine color based on usage
  const getRingColor = () => {
    if (percentage >= 90) return '#ef4444'; // red
    if (percentage >= 80) return '#f59e0b'; // amber
    return '#10b981'; // green
  };

  return (
    <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex flex-col items-center">
      {/* Plan Badge */}
      <span className="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-600 mb-4">
        {planName}
      </span>

      {/* Circular Progress Ring */}
      <div className="relative mb-6" style={{ width: '160px', height: '160px' }}>
        <svg width="160" height="160" className="transform -rotate-90">
          {/* Background circle */}
          <circle
            cx="80"
            cy="80"
            r={radius}
            fill="none"
            stroke="#e2e8f0"
            strokeWidth="10"
          />
          {/* Progress circle */}
          <circle
            cx="80"
            cy="80"
            r={radius}
            fill="none"
            stroke={getRingColor()}
            strokeWidth="10"
            strokeDasharray={circumference}
            strokeDashoffset={offset}
            strokeLinecap="round"
            className="transition-all duration-500 ease-out"
          />
        </svg>
        {/* Percentage text in center */}
        <div className="absolute inset-0 flex items-center justify-center">
          <div className="text-center">
            <div className="text-3xl font-bold text-slate-900">{Math.round(percentage)}%</div>
          </div>
        </div>
      </div>

      {/* Usage Info */}
      <div className="text-center mb-4">
        <div className="text-2xl font-bold text-slate-900 mb-1">
          {used.toLocaleString()} / {limit.toLocaleString()}
        </div>
        <div className="text-sm text-slate-700 mb-2">
          AI alt text this month
        </div>
        <div className="text-xs text-slate-500">
          {formatResetDate(resetDate)}
        </div>
      </div>

      {/* Billing Link (if applicable) */}
      {showBillingLink && onManageBilling && (
        <button
          onClick={onManageBilling}
          className="text-sm font-semibold text-blue-600 transition-colors hover:text-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white rounded"
        >
          Manage subscription
        </button>
      )}
    </div>
  );
};

export default PlanStatusCard;
