import React from 'react';

interface DashboardHeaderProps {
  currentPlan?: string;
}

const DashboardHeader: React.FC<DashboardHeaderProps> = ({ currentPlan = 'Free' }) => {
  return (
    <div className="mb-12 space-y-3">
      <h1 className="text-[32px] font-semibold tracking-tight text-gray-900 leading-tight md:text-[28px] sm:text-[24px]">
        Dashboard
      </h1>
      <p className="text-[15px] font-normal text-gray-600 leading-relaxed md:text-[14px]">
        Automated, accessible alt text generation for your WordPress media library.
      </p>
      {currentPlan === 'Free' && (
        <p className="text-[13px] font-normal text-gray-500">
          You're on the Free plan. Upgrade to Growth to automate your entire library.
        </p>
      )}
    </div>
  );
};

interface FreeUsageCardProps {
  usedImages?: number;
  totalImages?: number;
  librarySize?: number;
  onUpgradeClick?: () => void;
  onGenerateMissing?: () => void;
  onReoptimizeAll?: () => void;
}

const FreeUsageCard: React.FC<FreeUsageCardProps> = ({
  usedImages = 6,
  totalImages = 50,
  librarySize,
  onUpgradeClick,
  onGenerateMissing,
  onReoptimizeAll,
}) => {
  const percentage = Math.min(100, Math.round((usedImages / totalImages) * 100));
  const remaining = Math.max(0, totalImages - usedImages);
  const circumference = 2 * Math.PI * 48;
  const strokeDashoffset = circumference - (percentage / 100) * circumference;

  return (
    <div className="rounded-[24px] bg-white p-10 shadow-[0_2px_20px_rgba(0,0,0,0.04)] md:p-8 sm:p-6 space-y-8">
      {/* Badge */}
      <div className="flex">
        <span className="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-[10px] font-medium uppercase tracking-wider text-gray-600">
          FREE
        </span>
      </div>

      {/* Title */}
      <h2 className="text-[24px] font-semibold tracking-tight text-gray-900 leading-tight md:text-[20px]">
        Your alt text progress this month
      </h2>

      {/* Circular Progress */}
      <div className="flex justify-center">
        <div className="relative h-[140px] w-[140px]">
          <svg className="h-full w-full -rotate-90 transform" viewBox="0 0 120 120">
            <circle
              cx="60"
              cy="60"
              r="48"
              fill="none"
              stroke="#f3f4f6"
              strokeWidth="12"
            />
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
                <stop offset="0%" stopColor="#14b8a6" stopOpacity="1" />
                <stop offset="100%" stopColor="#06b6d4" stopOpacity="1" />
              </linearGradient>
            </defs>
          </svg>
          <div className="absolute inset-0 flex flex-col items-center justify-center">
            <div className="text-[32px] font-semibold text-gray-900">{percentage}%</div>
            <div className="text-[10px] font-medium uppercase tracking-wider text-gray-500 mt-1">
              CREDITS USED
            </div>
          </div>
        </div>
      </div>

      {/* Usage Text */}
      <div className="text-center space-y-2">
        <p className="text-[14px] font-normal text-gray-700">
          <span className="font-semibold">{usedImages}</span> of{' '}
          <span className="font-semibold">{totalImages}</span> images used this month
        </p>
        <p className="text-[12px] font-normal text-gray-500">
          {totalImages} free images per month
        </p>
      </div>

      {/* Upgrade Hint CTA */}
      <button
        onClick={onUpgradeClick}
        className="w-full rounded-full bg-gradient-to-r from-[#14b8a6] to-[#06b6d4] px-6 py-3.5 text-[14px] font-semibold text-white shadow-[0_2px_12px_rgba(20,184,166,0.25)] transition-all duration-200 hover:shadow-[0_4px_16px_rgba(20,184,166,0.35)] hover:-translate-y-0.5 focus:outline-none focus:ring-4 focus:ring-teal-500/30 active:translate-y-0"
      >
        <span className="block">Upgrade to Growth</span>
        <span className="block text-[11px] font-normal opacity-90 mt-0.5">
          Unlock 1,000 images per month
        </span>
      </button>

      {/* Operational Buttons */}
      <div className="grid grid-cols-2 gap-3 pt-2">
        <button
          onClick={onGenerateMissing}
          className="flex items-center justify-center gap-2 rounded-full border border-gray-200 bg-gradient-to-r from-white to-gray-50 px-4 py-2.5 text-[13px] font-medium text-gray-700 transition-all duration-200 hover:border-gray-300 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-gray-200 focus:ring-offset-1 active:bg-gray-50"
        >
          <svg className="h-3.5 w-3.5 text-teal-500" fill="none" viewBox="0 0 16 16" stroke="currentColor" strokeWidth="2">
            <rect x="2" y="2" width="12" height="12" rx="2" />
            <path d="M6 6H10M6 10H10" strokeLinecap="round" />
          </svg>
          Generate Missing
        </button>
        <button
          onClick={onReoptimizeAll}
          className="flex items-center justify-center gap-2 rounded-full border border-gray-200 bg-gradient-to-r from-white to-gray-50 px-4 py-2.5 text-[13px] font-medium text-gray-700 transition-all duration-200 hover:border-gray-300 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-gray-200 focus:ring-offset-1 active:bg-gray-50"
        >
          <svg className="h-3.5 w-3.5 text-blue-500" fill="none" viewBox="0 0 16 16" stroke="currentColor" strokeWidth="2">
            <path d="M8 2L10 6L14 8L10 10L8 14L6 10L2 8L6 6L8 2Z" />
            <circle cx="8" cy="8" r="2" fill="currentColor" />
          </svg>
          Re-optimise All
        </button>
      </div>

      {/* Stats Row */}
      <div className="grid grid-cols-3 gap-3 pt-6 border-t border-gray-100">
        <div className="rounded-xl border border-gray-100 bg-gray-50/50 p-4 text-center">
          <div className="flex justify-center mb-2">
            <svg className="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
              <circle cx="12" cy="12" r="10" />
              <path d="M12 6v6l4 2" strokeLinecap="round" />
            </svg>
          </div>
          <div className="text-[11px] font-semibold uppercase tracking-wider text-gray-500 mb-1.5">
            TIME SAVED
          </div>
          <div className="text-[11px] font-normal text-gray-400 leading-relaxed">
            Start optimizing to see hours saved
          </div>
        </div>
        <div className="rounded-xl border border-gray-100 bg-gray-50/50 p-4 text-center">
          <div className="flex justify-center mb-2">
            <svg className="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
              <path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>
          <div className="text-[11px] font-semibold uppercase tracking-wider text-gray-500 mb-1.5">
            IMAGES OPTIMIZED
          </div>
          <div className="text-[11px] font-normal text-gray-400 leading-relaxed">
            Track optimizations across client sites
          </div>
        </div>
        <div className="rounded-xl border border-gray-100 bg-gray-50/50 p-4 text-center">
          <div className="flex justify-center mb-2">
            <svg className="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
              <path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
          </div>
          <div className="text-[11px] font-semibold uppercase tracking-wider text-gray-500 mb-1.5">
            SEO IMPACT
          </div>
          <div className="text-[11px] font-normal text-gray-400 leading-relaxed">
            See SEO lift once enough images are optimized
          </div>
        </div>
      </div>

      {/* Footer */}
      <p className="text-center text-[11px] font-normal text-gray-400 pt-2">
        Limited to {totalImages} images/month on Free. Growth unlocks 1,000 images/month.
      </p>
    </div>
  );
};

interface GrowthUpgradeCardProps {
  onUpgradeClick?: () => void;
  monthlyPrice?: number;
}

const GrowthUpgradeCard: React.FC<GrowthUpgradeCardProps> = ({
  onUpgradeClick,
  monthlyPrice,
}) => {
  return (
    <div className="rounded-[24px] bg-gradient-to-br from-blue-50/80 via-teal-50/60 to-cyan-50/40 border border-blue-100/60 p-10 shadow-[0_2px_20px_rgba(0,0,0,0.04)] md:p-8 sm:p-6 space-y-8 relative overflow-hidden">
      {/* Subtle Sparkle Effects */}
      <div className="absolute top-8 right-12 w-1 h-1 bg-teal-400 rounded-full opacity-40 animate-pulse" />
      <div className="absolute top-16 right-20 w-1.5 h-1.5 bg-blue-400 rounded-full opacity-30 animate-pulse" style={{ animationDelay: '0.5s' }} />
      <div className="absolute top-24 right-16 w-1 h-1 bg-cyan-400 rounded-full opacity-35 animate-pulse" style={{ animationDelay: '1s' }} />
      <div className="absolute bottom-32 right-24 w-1 h-1 bg-teal-300 rounded-full opacity-25 animate-pulse" style={{ animationDelay: '1.5s' }} />

      {/* Badge */}
      <div className="flex relative z-10">
        <span className="inline-flex items-center rounded-full bg-gradient-to-r from-blue-500 to-teal-500 px-3 py-1 text-[10px] font-medium uppercase tracking-wider text-white shadow-sm">
          GROWTH
        </span>
      </div>

      {/* Title */}
      <h2 className="text-[24px] font-semibold tracking-tight text-gray-900 leading-tight md:text-[20px] relative z-10">
        Upgrade to automate your client sites
      </h2>

      {/* Subtitle */}
      <p className="text-[14px] font-normal leading-relaxed text-gray-700 relative z-10">
        Generate SEO-friendly alt text automatically across multiple client sites.
      </p>

      {/* Benefit List */}
      <ul className="space-y-3.5 relative z-10">
        <li className="flex items-start gap-3">
          <div className="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-teal-100 to-blue-100">
            <svg
              className="h-3 w-3 text-teal-600"
              fill="none"
              viewBox="0 0 12 12"
              stroke="currentColor"
              strokeWidth="2.5"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <path d="M10 3L4.5 8.5L2 6" />
            </svg>
          </div>
          <span className="text-[14px] font-normal leading-relaxed text-gray-700">
            1,000 AI alt texts per month
          </span>
        </li>
        <li className="flex items-start gap-3">
          <div className="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-100 to-cyan-100">
            <svg
              className="h-3 w-3 text-blue-600"
              fill="none"
              viewBox="0 0 12 12"
              stroke="currentColor"
              strokeWidth="2.5"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <path d="M10 3L4.5 8.5L2 6" />
            </svg>
          </div>
          <span className="text-[14px] font-normal leading-relaxed text-gray-700">
            Bulk processing for entire media libraries
          </span>
        </li>
        <li className="flex items-start gap-3">
          <div className="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-teal-100 to-blue-100">
            <svg
              className="h-3 w-3 text-teal-600"
              fill="none"
              viewBox="0 0 12 12"
              stroke="currentColor"
              strokeWidth="2.5"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <path d="M10 3L4.5 8.5L2 6" />
            </svg>
          </div>
          <span className="text-[14px] font-normal leading-relaxed text-gray-700">
            Priority queues for 3× faster results
          </span>
        </li>
        <li className="flex items-start gap-3">
          <div className="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-100 to-cyan-100">
            <svg
              className="h-3 w-3 text-blue-600"
              fill="none"
              viewBox="0 0 12 12"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <circle cx="6" cy="6" r="5" />
              <path d="M6 2v2M6 10v2M2 6h2M10 6h2M3.172 3.172l1.414 1.414M7.414 7.414l1.414 1.414M3.172 8.828l1.414-1.414M7.414 4.586l1.414-1.414" />
            </svg>
          </div>
          <span className="text-[14px] font-normal leading-relaxed text-gray-700">
            Multilingual support for global SEO reach
          </span>
        </li>
      </ul>

      {/* Persona Chips */}
      <div className="flex flex-wrap gap-2 relative z-10">
        <span className="inline-flex items-center gap-1.5 rounded-full bg-pink-50 border border-pink-100 px-3 py-1.5 text-[11px] font-medium text-pink-700">
          <svg className="h-3 w-3" fill="none" viewBox="0 0 16 16" stroke="currentColor" strokeWidth="2">
            <path d="M4 4h8v8H4z" />
            <path d="M6 6h4v4H6z" />
          </svg>
          Editorial
        </span>
        <span className="inline-flex items-center gap-1.5 rounded-full bg-orange-50 border border-orange-100 px-3 py-1.5 text-[11px] font-medium text-orange-700">
          <svg className="h-3 w-3" fill="none" viewBox="0 0 16 16" stroke="currentColor" strokeWidth="2">
            <path d="M3 3h2l.4 2M7 13h6l2-8H5.4M7 13L5.4 5M7 13l-1.293 1.293c-.63.63-.184 1.707.707 1.707H13" />
          </svg>
          Ecommerce
        </span>
        <span className="inline-flex items-center gap-1.5 rounded-full bg-purple-50 border border-purple-100 px-3 py-1.5 text-[11px] font-medium text-purple-700">
          <svg className="h-3 w-3" fill="none" viewBox="0 0 16 16" stroke="currentColor" strokeWidth="2">
            <path d="M3 3h2v10H3zM7 5h2v8H7zM11 7h2v6h-2z" />
          </svg>
          Reporting
        </span>
      </div>

      {/* CTA Section */}
      <div className="space-y-3 pt-2 relative z-10">
        {/* Primary CTA */}
        <button
          onClick={onUpgradeClick}
          className="w-full min-h-[44px] rounded-full bg-white px-6 py-3.5 text-[14px] font-semibold text-[#1e293b] shadow-[0_2px_12px_rgba(0,0,0,0.08)] transition-all duration-200 hover:shadow-[0_4px_16px_rgba(0,0,0,0.12)] hover:-translate-y-0.5 hover:scale-[1.01] focus:outline-none focus:ring-4 focus:ring-white/50 focus:ring-offset-2 active:translate-y-0 active:scale-100 sm:w-full"
        >
          Start 14 day free trial
        </button>

        {/* Microcopy */}
        <p className="text-center text-[11px] font-normal leading-relaxed text-gray-600">
          Cancel anytime. No lock-in.
        </p>

        {/* Trust Microcopy */}
        <p className="text-center text-[11px] font-normal text-gray-500 pt-1">
          Trusted by agencies managing multiple client sites.
        </p>

        {/* Secondary Link */}
        <a
          href="#"
          className="block text-center text-[12px] font-normal text-gray-600 hover:text-gray-900 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-200 focus:ring-offset-1 rounded py-1"
        >
          Compare plans
        </a>
      </div>
    </div>
  );
};

interface DashboardOverviewProps {
  usedImages?: number;
  totalImages?: number;
  librarySize?: number;
  currentPlan?: string;
  monthlyPrice?: number;
  onUpgradeClick?: () => void;
  onGenerateMissing?: () => void;
  onReoptimizeAll?: () => void;
}

const DashboardOverview: React.FC<DashboardOverviewProps> = ({
  usedImages = 6,
  totalImages = 50,
  librarySize,
  currentPlan = 'Free',
  monthlyPrice,
  onUpgradeClick,
  onGenerateMissing,
  onReoptimizeAll,
}) => {
  const handleUpgradeClick = () => {
    if (onUpgradeClick) {
      onUpgradeClick();
    } else {
      const growthCard = document.querySelector('[data-growth-card]');
      if (growthCard) {
        growthCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }
  };

  return (
    <div className="space-y-12">
      <DashboardHeader currentPlan={currentPlan} />
      <div className="flex flex-row gap-8 lg:flex-col lg:gap-8">
        <div className="flex-1">
          <FreeUsageCard
            usedImages={usedImages}
            totalImages={totalImages}
            librarySize={librarySize}
            onUpgradeClick={handleUpgradeClick}
            onGenerateMissing={onGenerateMissing}
            onReoptimizeAll={onReoptimizeAll}
          />
        </div>
        <div className="flex-1" data-growth-card>
          <GrowthUpgradeCard onUpgradeClick={handleUpgradeClick} monthlyPrice={monthlyPrice} />
        </div>
      </div>
    </div>
  );
};

export default DashboardOverview;