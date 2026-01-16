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
    <div className="rounded-[24px] bg-white p-8 shadow-[0_4px_35px_rgba(0,0,0,0.07)] md:p-6 sm:p-5 space-y-6">
      {/* Badge */}
      <div className="flex">
        <span className="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-gray-600">
          FREE
        </span>
      </div>

      {/* Title */}
      <h2 className="text-[28px] font-semibold text-gray-900 md:text-[22px]">
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
                <stop offset="100%" stopColor="#10b981" stopOpacity="1" />
              </linearGradient>
            </defs>
          </svg>
          <div className="absolute inset-0 flex flex-col items-center justify-center">
            <div className="text-[32px] font-bold text-gray-900">{percentage}%</div>
            <div className="text-[11px] font-medium uppercase tracking-wider text-gray-500 mt-1">
              CREDITS USED
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
        {librarySize !== undefined && (
          <p className="text-[13px] text-gray-600 mt-2">
            You have <span className="font-semibold">{librarySize}</span> images without alt text.
          </p>
        )}
      </div>

      {/* Progress Bar */}
      <div className="h-1.5 overflow-hidden rounded-full bg-gray-100">
        <div
          className="h-full rounded-full bg-gradient-to-r from-[#14b8a6] to-[#10b981] transition-all duration-500 ease-out"
          style={{ width: `${percentage}%` }}
          role="progressbar"
          aria-valuenow={percentage}
          aria-valuemin={0}
          aria-valuemax={100}
          aria-label={`${usedImages} of ${totalImages} images used`}
        />
      </div>

      {/* Upgrade CTA */}
      <button
        onClick={onUpgradeClick}
        className="w-full rounded-full bg-gradient-to-r from-[#079D52] to-[#17C67A] px-6 py-4 text-[15px] font-bold text-white shadow-[0_4px_16px_rgba(5,150,105,0.4)] transition-all duration-200 hover:shadow-[0_6px_20px_rgba(5,150,105,0.5)] hover:-translate-y-0.5 focus:outline-none focus:ring-4 focus:ring-green-500/30 active:translate-y-0"
      >
        <span className="block">Upgrade to Growth</span>
        <span className="block text-[12px] font-normal opacity-90 mt-0.5">
          Unlock 1,000 images/month
        </span>
      </button>

      {/* Operational Buttons */}
      <div className="grid grid-cols-2 gap-3 border-t border-gray-100 pt-6">
        <button
          onClick={onGenerateMissing}
          className="flex items-center justify-center gap-2 rounded-full bg-gradient-to-r from-[#6366F1] to-[#8B5CF6] px-4 py-3 text-[14px] font-semibold text-white shadow-[0_2px_8px_rgba(99,102,241,0.3)] transition-all duration-200 hover:shadow-[0_4px_12px_rgba(99,102,241,0.4)] hover:-translate-y-0.5 focus:outline-none focus:ring-4 focus:ring-indigo-500/30 active:translate-y-0"
        >
          <svg className="h-4 w-4" fill="none" viewBox="0 0 16 16" stroke="currentColor" strokeWidth="2">
            <rect x="2" y="2" width="12" height="12" rx="2" />
            <path d="M6 6H10M6 10H10" strokeLinecap="round" />
          </svg>
          Generate Missing
        </button>
        <button
          onClick={onReoptimizeAll}
          className="flex items-center justify-center gap-2 rounded-full bg-gradient-to-r from-[#F59E0B] to-[#F97316] px-4 py-3 text-[14px] font-semibold text-white shadow-[0_2px_8px_rgba(245,158,11,0.3)] transition-all duration-200 hover:shadow-[0_4px_12px_rgba(245,158,11,0.4)] hover:-translate-y-0.5 focus:outline-none focus:ring-4 focus:ring-orange-500/30 active:translate-y-0"
        >
          <svg className="h-4 w-4" fill="none" viewBox="0 0 16 16" stroke="currentColor" strokeWidth="2">
            <path d="M8 2L10 6L14 8L10 10L8 14L6 10L2 8L6 6L8 2Z" />
            <circle cx="8" cy="8" r="2" fill="currentColor" />
          </svg>
          Re-optimise All
        </button>
      </div>

      {/* Stats Row */}
      <div className="grid grid-cols-3 gap-3 pt-4">
        <div className="rounded-lg border border-gray-100 bg-gray-50 p-4 text-center">
          <div className="text-[20px] font-bold text-gray-900">00</div>
          <div className="mt-1 text-[11px] font-semibold uppercase tracking-wider text-gray-500">
            HRS
          </div>
          <div className="mt-1 text-[10px] text-gray-400">
            Start generating alt text to see time saved.
          </div>
        </div>
        <div className="rounded-lg border border-gray-100 bg-gray-50 p-4 text-center">
          <div className="text-[20px] font-bold text-gray-900">0</div>
          <div className="mt-1 text-[11px] font-semibold uppercase tracking-wider text-gray-500">
            IMAGES
          </div>
          <div className="mt-1 text-[10px] text-gray-400">
            We'll show your progress here.
          </div>
        </div>
        <div className="rounded-lg border border-gray-100 bg-gray-50 p-4 text-center">
          <div className="text-[20px] font-bold text-gray-900">–%</div>
          <div className="mt-1 text-[11px] font-semibold uppercase tracking-wider text-gray-500">
            SEO
          </div>
          <div className="mt-1 text-[10px] text-gray-400">
            We estimate impact after you optimise enough images.
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
    <div className="rounded-[24px] bg-gradient-to-br from-[#3C84FF] via-[#54A8FF] to-[#7CDDFF] p-8 shadow-[0_4px_35px_rgba(0,0,0,0.07)] md:p-6 sm:p-5 space-y-6">
      {/* Badge */}
      <div className="flex">
        <span className="inline-flex items-center rounded-full bg-white/15 backdrop-blur-sm px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-white">
          GROWTH
        </span>
      </div>

      {/* Title */}
      <h2 className="text-[28px] font-semibold leading-tight text-white md:text-[22px]">
        Stop writing alt text manually
      </h2>

      {/* Subheading */}
      <p className="text-[15px] leading-relaxed text-white/85">
        Generate alt text for your entire media library automatically and save hours every month.
      </p>

      {/* Benefit List */}
      <ul className="space-y-3">
        <li className="flex items-start gap-3">
          <div className="mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-white/15">
            <svg
              className="h-3.5 w-3.5 text-white"
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
          <span className="text-[15px] leading-relaxed text-white/90">
            1,000 AI alt texts per month so you never run out on growing sites
          </span>
        </li>
        <li className="flex items-start gap-3">
          <div className="mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-white/15">
            <svg
              className="h-3.5 w-3.5 text-white"
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
          <span className="text-[15px] leading-relaxed text-white/90">
            Bulk processing to optimise entire image libraries in one click
          </span>
        </li>
        <li className="flex items-start gap-3">
          <div className="mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-white/15">
            <svg
              className="h-3.5 w-3.5 text-white"
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
          <span className="text-[15px] leading-relaxed text-white/90">
            Priority queues for 3× faster results during busy uploads
          </span>
        </li>
        <li className="flex items-start gap-3">
          <div className="mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-white/15">
            <svg
              className="h-3.5 w-3.5 text-white"
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
          <span className="text-[15px] leading-relaxed text-white/90">
            50+ languages for global SEO reach
          </span>
        </li>
      </ul>

      {/* Social Proof */}
      <p className="text-[13px] text-white/80">
        Trusted by 1,200+ sites generating millions of alt texts.
      </p>

      {/* CTA Section */}
      <div className="space-y-3 pt-2">
        {/* Primary CTA */}
        <button
          onClick={onUpgradeClick}
          className="w-full min-h-[44px] rounded-full bg-white px-6 py-4 text-[15px] font-semibold text-[#1e293b] shadow-[0_4px_16px_rgba(0,0,0,0.15)] transition-all duration-200 hover:shadow-[0_6px_20px_rgba(0,0,0,0.2)] hover:-translate-y-0.5 hover:scale-[1.02] focus:outline-none focus:ring-4 focus:ring-white/50 active:translate-y-0 active:scale-100"
        >
          Start 14 day free trial
        </button>

        {/* Microcopy */}
        <p className="text-center text-[12px] leading-relaxed text-white/80">
          Upgrade to Growth. Cancel anytime.
        </p>

        {/* Price Note */}
        {monthlyPrice && (
          <p className="text-center text-[12px] text-white/75">
            Then £{monthlyPrice}/month after trial.
          </p>
        )}

        {/* Secondary Link */}
        <a
          href="#"
          className="block text-center text-[13px] text-white/90 underline underline-offset-2 transition-colors hover:text-white focus:outline-none focus:ring-2 focus:ring-white/50 focus:ring-offset-2 focus:ring-offset-blue-500 rounded"
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
      // Scroll to Growth card or trigger upgrade flow
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