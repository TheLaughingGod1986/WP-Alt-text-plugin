import React from 'react';

interface UsageCardProps {
  usageStats: {
    used: number;
    limit: number;
    percentage: number;
    resetDate?: string;
  };
  canGenerate: boolean;
  onUpgrade: () => void;
  onGenerateMissing: () => void;
  onReoptimizeAll: () => void;
}

const UsageCard: React.FC<UsageCardProps> = ({
  usageStats,
  canGenerate,
  onUpgrade,
  onGenerateMissing,
  onReoptimizeAll,
}) => {
  const { used, limit, percentage, resetDate } = usageStats;
  const circumference = 2 * Math.PI * 48;
  const strokeDashoffset = circumference - (percentage / 100) * circumference;

  const formatResetDate = (date?: string) => {
    if (!date) return 'Resets monthly';
    try {
      const d = new Date(date);
      return `Resets ${d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}`;
    } catch {
      return 'Resets monthly';
    }
  };

  return (
    <div className="rounded-[24px] bg-white p-6 shadow-[0_4px_35px_rgba(0,0,0,0.07)] space-y-6">
      {/* Badge */}
      <div className="flex">
        <span className="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-600">
          FREE
        </span>
      </div>

      {/* Title */}
      <h2 className="text-[18px] font-semibold uppercase tracking-[0.08em] text-slate-900 leading-snug">
        YOUR ALT TEXT PROGRESS THIS MONTH
      </h2>

      {/* Circular Progress */}
      <div className="flex justify-center">
        <div className="relative h-[140px] w-[140px]">
          <svg className="h-full w-full -rotate-90 transform" viewBox="0 0 120 120">
            {/* Background circle */}
            <circle
              cx="60"
              cy="60"
              r="48"
              fill="none"
              stroke="#f3f4f6"
              strokeWidth="12"
            />
            {/* Progress circle */}
            <circle
              cx="60"
              cy="60"
              r="48"
              fill="none"
              stroke="url(#progressGradient)"
              strokeWidth="12"
              strokeLinecap="round"
              strokeDasharray={circumference}
              strokeDashoffset={strokeDashoffset}
              className="transition-all duration-500 ease-out"
            />
            <defs>
              <linearGradient id="progressGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stopColor="#3B82F6" stopOpacity="1" />
                <stop offset="100%" stopColor="#60A5FA" stopOpacity="1" />
              </linearGradient>
            </defs>
          </svg>
          <div className="absolute inset-0 flex flex-col items-center justify-center">
            <div className="text-[32px] font-semibold text-slate-900">{percentage}%</div>
            <div className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">
              CREDITS USED
            </div>
          </div>
        </div>
      </div>

      {/* Usage Count */}
      <div className="text-center">
        <p className="text-[15px] leading-relaxed text-slate-700">
          <span className="font-semibold">{used}</span> of{' '}
          <span className="font-semibold">{limit}</span> images used this month
        </p>
      </div>

      {/* Linear Progress Bar */}
      <div className="h-1.5 overflow-hidden rounded-full bg-slate-100">
        <div
          className="h-full rounded-full bg-gradient-to-r from-[#3B82F6] to-[#60A5FA] transition-all duration-500 ease-out"
          style={{ width: `${percentage}%` }}
        />
      </div>

      {/* Reset Date */}
      <p className="text-center text-[13px] text-slate-500">{formatResetDate(resetDate)}</p>

      {/* Upgrade CTA */}
      <button
        onClick={onUpgrade}
        className="w-full rounded-full bg-slate-900 px-6 py-3 text-[15px] font-semibold text-white shadow-lg shadow-slate-900/20 transition duration-200 hover:-translate-y-0.5 hover:shadow-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white active:translate-y-0"
      >
        <span className="flex items-center justify-center gap-2">
          Upgrade to 1,000 images/month
          <svg
            className="h-4 w-4"
            fill="none"
            viewBox="0 0 16 16"
            stroke="currentColor"
            strokeWidth="2.5"
          >
            <path d="M6 12L10 8L6 4" strokeLinecap="round" strokeLinejoin="round" />
          </svg>
        </span>
      </button>

      {/* Action Buttons Grid */}
      <div className="grid grid-cols-2 gap-3 border-t border-slate-200 pt-6">
        <button
          onClick={onGenerateMissing}
          disabled={!canGenerate}
          {...(!canGenerate && {
            'data-bbai-tooltip': 'Upgrade to unlock more generations',
            'data-bbai-tooltip-position': 'top'
          })}
          className="flex items-center justify-center gap-2 rounded-full bg-gradient-to-r from-indigo-500 to-violet-500 px-4 py-3 text-[14px] font-semibold text-white shadow-[0_4px_12px_rgba(99,102,241,0.4)] transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_6px_16px_rgba(99,102,241,0.5)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:ring-offset-2 focus-visible:ring-offset-white disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0 disabled:hover:shadow-[0_4px_12px_rgba(99,102,241,0.4)] active:translate-y-0"
        >
          <svg className="h-4 w-4" fill="none" viewBox="0 0 16 16" stroke="currentColor" strokeWidth="2">
            <rect x="2" y="2" width="12" height="12" rx="2" />
            <path d="M6 6H10M6 10H10" strokeLinecap="round" />
          </svg>
          Generate Missing
        </button>
        <button
          onClick={onReoptimizeAll}
          disabled={!canGenerate}
          {...(!canGenerate && {
            'data-bbai-tooltip': 'Upgrade to unlock more generations',
            'data-bbai-tooltip-position': 'top'
          })}
          className="flex items-center justify-center gap-2 rounded-full bg-gradient-to-r from-amber-500 to-orange-500 px-4 py-3 text-[14px] font-semibold text-white shadow-[0_4px_12px_rgba(245,158,11,0.4)] transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_6px_16px_rgba(245,158,11,0.5)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 focus-visible:ring-offset-white disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0 disabled:hover:shadow-[0_4px_12px_rgba(245,158,11,0.4)] active:translate-y-0"
        >
          <svg className="h-4 w-4" fill="none" viewBox="0 0 16 16" stroke="currentColor" strokeWidth="2">
            <path d="M8 2L10 6L14 8L10 10L8 14L6 10L2 8L6 6L8 2Z" />
            <circle cx="8" cy="8" r="2" fill="currentColor" />
          </svg>
          Re-optimize All
        </button>
      </div>

      {/* Stats Tiles */}
      <div className="grid grid-cols-3 gap-3 pt-4">
        <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 text-center">
          <div className="text-[20px] font-semibold text-slate-900">00</div>
          <div className="mt-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">
            HRS
          </div>
          <div className="mt-0.5 text-[11px] text-slate-500">TIME SAVED</div>
        </div>
        <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 text-center">
          <div className="text-[20px] font-semibold text-slate-900">{used}</div>
          <div className="mt-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">
            IMAGES
          </div>
          <div className="mt-0.5 text-[11px] text-slate-500">OPTIMIZED</div>
        </div>
        <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 text-center">
          <div className="text-[20px] font-semibold text-slate-900">-%</div>
          <div className="mt-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">
            SEO IMPACT
          </div>
          <div className="mt-0.5 text-[11px] text-slate-500">IMPACT</div>
        </div>
      </div>

      {/* Footer Text */}
      <p className="text-center text-[12px] text-slate-500">
        Limited to {limit} images/month. Upgrade to Growth for 1,000 images/month.
      </p>
    </div>
  );
};

export default UsageCard;
