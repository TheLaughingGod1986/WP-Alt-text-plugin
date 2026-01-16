import React from 'react';

interface DashboardHeaderProps {
  currentPlan?: string;
}

const DashboardHeader: React.FC<DashboardHeaderProps> = ({ currentPlan = 'Free' }) => {
  return (
    <div className="mb-8 space-y-2">
      <h1 className="text-[32px] font-bold text-gray-900 md:text-[28px] sm:text-[24px]">
        Dashboard
      </h1>
      <p className="text-[16px] text-gray-600 md:text-[15px]">
        Automated, accessible alt text generation for your WordPress media library.
      </p>
      {currentPlan === 'Free' && (
        <p className="text-[14px] text-gray-500">
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
  hoursSaved?: number;
  onUpgradeClick?: () => void;
  onGenerateMissing?: () => void;
  onReoptimizeAll?: () => void;
}

const FreeUsageCard: React.FC<FreeUsageCardProps> = ({
  usedImages = 6,
  totalImages = 50,
  librarySize,
  hoursSaved = 1,
  onUpgradeClick,
  onGenerateMissing,
  onReoptimizeAll,
}) => {
  const percentage = Math.min(100, Math.round((usedImages / totalImages) * 100));
  const remaining = Math.max(0, totalImages - usedImages);
  const circumference = 2 * Math.PI * 48;
  const strokeDashoffset = circumference - (percentage / 100) * circumference;
  const seoCoverage = Math.round((usedImages / totalImages) * 100);

  return (
    <div className="rounded-[24px] bg-white p-8 shadow-[0_4px_35px_rgba(0,0,0,0.07)] md:p-6 sm:p-5 space-y-6">
      {/* Badge */}
      <div className="flex">
        <span className="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-gray-600">
          FREE
        </span>
      </div>

      {/* Title */}
      <h2 className="text-[18px] font-bold uppercase tracking-tight text-gray-900">
        YOUR ALT TEXT PROGRESS THIS MONTH
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
                <stop offset="0%" stopColor="#3B82F6" stopOpacity="1" />
                <stop offset="100%" stopColor="#14b8a6" stopOpacity="1" />
              </linearGradient>
            </defs>
          </svg>
          <div className="absolute inset-0 flex flex-col items-center justify-center">
            <div className="text-[32px] font-bold text-gray-900">{percentage}%</div>
            <div className="text-[11px] font-medium uppercase tracking-wider text-gray-500 mt-1">
              IMAGES USED
            </div>
          </div>
        </div>
      </div>

      {/* Usage Text */}
      <div className="text-center space-y-1">
        <p className="text-[15px] text-gray-700">
          <span className="font-semibold">{usedImages}</span> of{' '}
          <span className="font-semibold">{totalImages}</span> images used this month
        </p>
        <p className="text-[13px] text-gray-600">
          Only <span className="font-semibold">{remaining}</span> free images left this month
        </p>
      </div>

      {/* Upgrade CTA */}
      <button
        onClick={onUpgradeClick}
        className="w-full rounded-xl bg-gradient-to-r from-[#3B82F6] to-[#14b8a6] px-6 py-4 text-[15px] font-bold text-white shadow-[0_4px_16px_rgba(59,130,246,0.4)] transition-all duration-200 hover:shadow-[0_6px_20px_rgba(59,130,246,0.5)] hover:-translate-y-0.5 focus:outline-none focus:ring-4 focus:ring-blue-500/30 active:translate-y-0"
      >
        <span className="flex items-center justify-center gap-2">
          Upgrade to Growth
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="2.5">
            <path d="M6 12L10 8L6 4" strokeLinecap="round" strokeLinejoin="round" />
          </svg>
        </span>
        <span className="block text-[12px] font-normal opacity-90 mt-1">
          Unlock 1,000 images/month
        </span>
      </button>

      {/* Operational Buttons */}
      <div className="grid grid-cols-2 gap-3">
        <button
          onClick={onGenerateMissing}
          className="flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#8B5CF6] to-[#A78BFA] px-4 py-3 text-[14px] font-semibold text-white shadow-[0_2px_8px_rgba(139,92,246,0.3)] transition-all duration-200 hover:shadow-[0_4px_12px_rgba(139,92,246,0.4)] hover:-translate-y-0.5 focus:outline-none focus:ring-4 focus:ring-purple-500/30 active:translate-y-0"
        >
          <svg className="h-4 w-4" fill="none" viewBox="0 0 16 16" stroke="currentColor" strokeWidth="2">
            <circle cx="8" cy="8" r="6" stroke="currentColor" fill="none" />
            <path d="M8 4V8L10 10" strokeLinecap="round" />
          </svg>
          Generate Missing
        </button>
        <button
          onClick={onReoptimizeAll}
          className="flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#F59E0B] to-[#FBBF24] px-4 py-3 text-[14px] font-semibold text-white shadow-[0_2px_8px_rgba(245,158,11,0.3)] transition-all duration-200 hover:shadow-[0_4px_12px_rgba(245,158,11,0.4)] hover:-translate-y-0.5 focus:outline-none focus:ring-4 focus:ring-orange-500/30 active:translate-y-0"
        >
          <svg className="h-4 w-4" fill="none" viewBox="0 0 16 16" stroke="currentColor" strokeWidth="2">
            <path d="M8 2L10 6L14 8L10 10L8 14L6 10L2 8L6 6L8 2Z" />
            <circle cx="8" cy="8" r="2" fill="currentColor" />
          </svg>
          Re-optimize All
        </button>
      </div>

      {/* Feature Summary Cards */}
      <div className="grid grid-cols-3 gap-3 pt-4">
        <div className="rounded-xl border border-gray-200 bg-gradient-to-br from-green-50 to-green-100/50 p-4 text-center">
          <div className="flex justify-center mb-2">
            <svg className="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
              <circle cx="12" cy="12" r="10" />
              <path d="M12 6v6l4 2" strokeLinecap="round" />
            </svg>
          </div>
          <div className="text-[16px] font-bold text-gray-900">{hoursSaved}h</div>
          <div className="mt-1 text-[10px] font-semibold uppercase tracking-wider text-gray-600">
            HOURS SAVED
          </div>
        </div>
        <div className="rounded-xl border border-gray-200 bg-gradient-to-br from-blue-50 to-blue-100/50 p-4 text-center">
          <div className="flex justify-center mb-2">
            <svg className="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
              <path d="M3 15c0 2.5 2 5 5 5h8c3 0 5-2.5 5-5V9c0-2.5-2-5-5-5H8C5 4 3 6.5 3 9v6z" />
              <path d="M8 12h8M12 8v8" strokeLinecap="round" />
            </svg>
          </div>
          <div className="text-[10px] font-medium text-gray-700 leading-tight">
            LIBRARY
          </div>
          <div className="text-[9px] text-gray-600 mt-0.5">
            sites & media libraries
          </div>
        </div>
        <div className="rounded-xl border border-gray-200 bg-gradient-to-br from-purple-50 to-purple-100/50 p-4 text-center">
          <div className="flex justify-center mb-2">
            <svg className="h-5 w-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
              <path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
          </div>
          <div className="text-[16px] font-bold text-gray-900">{seoCoverage}%</div>
          <div className="mt-1 text-[10px] font-semibold uppercase tracking-wider text-gray-600">
            SEO LIFT POTENTIAL
          </div>
          <div className="mt-1 text-[9px] text-gray-500">
            Alt text coverage: {usedImages} of {totalImages} images ({percentage}%)
          </div>
        </div>
      </div>

      {/* Footer */}
      <p className="text-center text-[12px] text-gray-400">
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
    <div className="rounded-[24px] bg-gradient-to-br from-blue-50 via-blue-100/30 to-cyan-50/50 border border-blue-200/50 p-8 shadow-[0_4px_35px_rgba(0,0,0,0.07)] md:p-6 sm:p-5 space-y-6 relative overflow-hidden">
      {/* Decorative Elements */}
      <div className="absolute top-0 right-0 w-64 h-64 opacity-10">
        <svg viewBox="0 0 200 200" className="w-full h-full">
          <circle cx="100" cy="100" r="80" fill="url(#cloudGradient)" />
          <defs>
            <radialGradient id="cloudGradient">
              <stop offset="0%" stopColor="#60A5FA" />
              <stop offset="100%" stopColor="#3B82F6" />
            </radialGradient>
          </defs>
        </svg>
      </div>
      <div className="absolute top-20 right-20 w-2 h-2 bg-blue-400 rounded-full opacity-60 animate-pulse" />
      <div className="absolute top-32 right-32 w-1.5 h-1.5 bg-cyan-400 rounded-full opacity-60 animate-pulse" style={{ animationDelay: '0.5s' }} />
      <div className="absolute top-24 right-40 w-1 h-1 bg-blue-300 rounded-full opacity-60 animate-pulse" style={{ animationDelay: '1s' }} />

      {/* Badge */}
      <div className="flex relative z-10">
        <span className="inline-flex items-center rounded-full bg-blue-600 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-white">
          GROWTH
        </span>
      </div>

      {/* Title */}
      <h2 className="text-[24px] font-bold text-gray-900 md:text-[20px] relative z-10">
        Upgrade to automate your campaigns
      </h2>

      {/* Subtitle */}
      <p className="text-[15px] text-gray-700 leading-relaxed relative z-10">
        Automate alt text so your clients rank better & stay compliant
      </p>

      {/* Benefit List */}
      <ul className="space-y-3 relative z-10">
        <li className="flex items-start gap-3">
          <div className="mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-green-100">
            <svg
              className="h-4 w-4 text-green-600"
              fill="none"
              viewBox="0 0 16 16"
              stroke="currentColor"
              strokeWidth="3"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <path d="M13 4L6 11L3 8" />
            </svg>
          </div>
          <span className="text-[15px] leading-relaxed text-gray-800">
            1,000 AI alt texts/month across client sites
          </span>
        </li>
        <li className="flex items-start gap-3">
          <div className="mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-yellow-100">
            <svg
              className="h-4 w-4 text-yellow-600"
              fill="none"
              viewBox="0 0 16 16"
              stroke="currentColor"
              strokeWidth="3"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <path d="M13 4L6 11L3 8" />
            </svg>
          </div>
          <span className="text-[15px] leading-relaxed text-gray-800">
            Bulk processing to boost entire libraries in minutes
          </span>
        </li>
        <li className="flex items-start gap-3">
          <div className="mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-purple-100">
            <svg
              className="h-4 w-4 text-purple-600"
              fill="none"
              viewBox="0 0 16 16"
              stroke="currentColor"
              strokeWidth="3"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <path d="M13 4L6 11L3 8" />
            </svg>
          </div>
          <span className="text-[15px] leading-relaxed text-gray-800">
            Priority queue for 3x faster results during busy uploads
          </span>
        </li>
        <li className="flex items-start gap-3">
          <div className="mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-blue-100">
            <svg
              className="h-4 w-4 text-blue-600"
              fill="none"
              viewBox="0 0 16 16"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <circle cx="8" cy="8" r="6" />
              <path d="M8 2v2M8 12v2M2 8h2M12 8h2M4.343 4.343l1.414 1.414M10.243 10.243l1.414 1.414M4.343 11.657l1.414-1.414M10.243 5.757l1.414-1.414" />
            </svg>
          </div>
          <span className="text-[15px] leading-relaxed text-gray-800">
            50+ languages supported for reach global audiences
          </span>
        </li>
      </ul>

      {/* Target Audience */}
      <p className="text-[14px] font-medium text-gray-700 relative z-10">
        Perfect for agencies managing multiple client sites.
      </p>

      {/* CTA Section */}
      <div className="space-y-3 pt-2 relative z-10">
        {/* Primary CTA */}
        <button
          onClick={onUpgradeClick}
          className="w-full min-h-[44px] rounded-full bg-blue-600 px-6 py-4 text-[15px] font-semibold text-white shadow-[0_4px_16px_rgba(37,99,235,0.4)] transition-all duration-200 hover:bg-blue-700 hover:shadow-[0_6px_20px_rgba(37,99,235,0.5)] hover:-translate-y-0.5 hover:scale-[1.02] focus:outline-none focus:ring-4 focus:ring-blue-500/50 active:translate-y-0 active:scale-100"
        >
          Start 14 day free trial
        </button>

        {/* Microcopy */}
        <p className="text-center text-[12px] leading-relaxed text-gray-600">
          Upgrade to Growth. Cancel anytime.
        </p>
      </div>

      {/* Integration Cards */}
      <div className="grid grid-cols-3 gap-3 pt-4 relative z-10">
        <div className="rounded-xl border border-green-200 bg-gradient-to-br from-green-50 to-green-100/50 p-3 text-center">
          <div className="flex justify-center mb-2">
            <svg className="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
              <path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
              <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </div>
          <div className="text-[11px] font-medium text-gray-700 leading-tight">
            Editorial SEO workflows
          </div>
        </div>
        <div className="rounded-xl border border-orange-200 bg-gradient-to-br from-orange-50 to-orange-100/50 p-3 text-center">
          <div className="flex justify-center mb-2">
            <svg className="h-5 w-5 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
              <path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          </div>
          <div className="text-[11px] font-medium text-gray-700 leading-tight">
            Ecommerce catalog optimizations
          </div>
        </div>
        <div className="rounded-xl border border-purple-200 bg-gradient-to-br from-purple-50 to-purple-100/50 p-3 text-center">
          <div className="flex justify-center mb-2">
            <svg className="h-5 w-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
              <path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
          </div>
          <div className="text-[11px] font-medium text-gray-700 leading-tight">
            Reporting- metrics
          </div>
        </div>
      </div>

      {/* Trust Indicator */}
      <p className="text-center text-[12px] text-gray-600 relative z-10">
        Trusted by top agencies worldwide.
      </p>
    </div>
  );
};

interface DashboardOverviewProps {
  usedImages?: number;
  totalImages?: number;
  librarySize?: number;
  hoursSaved?: number;
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
  hoursSaved = 1,
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
    <div className="space-y-8">
      <DashboardHeader currentPlan={currentPlan} />
      <div className="flex flex-row gap-8 lg:flex-col lg:gap-6">
        <div className="flex-1">
          <FreeUsageCard
            usedImages={usedImages}
            totalImages={totalImages}
            librarySize={librarySize}
            hoursSaved={hoursSaved}
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