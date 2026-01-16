import React from 'react';

interface DashboardHeaderProps {
  currentPlan?: string;
}

const DashboardHeader: React.FC<DashboardHeaderProps> = ({ currentPlan = 'Free' }) => {
  return (
    <div className="mb-12 space-y-3">
      <h1 className="text-[32px] font-medium tracking-tight text-gray-900 md:text-[28px] sm:text-[24px]">
        Dashboard
      </h1>
      <p className="text-[15px] font-light text-gray-600 md:text-[14px] leading-relaxed">
        Automated, accessible alt text generation for your WordPress media library.
      </p>
      {currentPlan === 'Free' && (
        <p className="text-[13px] font-light text-gray-500">
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
    <div className="rounded-[16px] bg-[#fafbfc] border border-gray-200/60 p-10 md:p-8 sm:p-6 space-y-8">
      {/* Badge */}
      <div className="flex">
        <span className="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-[10px] font-medium uppercase tracking-wider text-gray-600">
          FREE
        </span>
      </div>

      {/* Title */}
      <h2 className="text-[24px] font-medium tracking-tight text-gray-900 md:text-[20px]">
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
              stroke="#e5e7eb"
              strokeWidth="10"
            />
            <circle
              cx="60"
              cy="60"
              r="48"
              fill="none"
              stroke="#1e293b"
              strokeWidth="10"
              strokeLinecap="round"
              strokeDasharray={circumference}
              strokeDashoffset={strokeDashoffset}
              className="transition-all duration-500 ease-out"
            />
          </svg>
          <div className="absolute inset-0 flex flex-col items-center justify-center">
            <div className="text-[28px] font-medium text-gray-900">{percentage}%</div>
            <div className="text-[10px] font-medium uppercase tracking-wider text-gray-500 mt-1">
              CREDITS USED
            </div>
          </div>
        </div>
      </div>

      {/* Usage Text */}
      <div className="text-center space-y-1.5">
        <p className="text-[14px] font-light text-gray-700">
          <span className="font-medium">{usedImages}</span> of{' '}
          <span className="font-medium">{totalImages}</span> images used this month
        </p>
        <p className="text-[12px] font-light text-gray-600">
          Only <span className="font-medium">{remaining}</span> free images left this month
        </p>
        {librarySize !== undefined && (
          <p className="text-[12px] font-light text-gray-600 mt-2">
            You have <span className="font-medium">{librarySize}</span> images without alt text.
          </p>
        )}
      </div>

      {/* Progress Bar */}
      <div className="h-1 overflow-hidden rounded-full bg-gray-200">
        <div
          className="h-full rounded-full bg-[#1e293b] transition-all duration-500 ease-out"
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
        className="w-full rounded-[12px] bg-[#1e293b] px-6 py-3.5 text-[14px] font-medium text-white transition-all duration-200 hover:bg-[#0f172a] focus:outline-none focus:ring-2 focus:ring-[#1e293b] focus:ring-offset-2 active:bg-[#0f172a]"
      >
        <span className="block">Upgrade to Growth</span>
        <span className="block text-[11px] font-light opacity-80 mt-0.5">
          Unlock 1,000 images/month
        </span>
      </button>

      {/* Operational Buttons */}
      <div className="grid grid-cols-2 gap-3 border-t border-gray-200 pt-6">
        <button
          onClick={onGenerateMissing}
          className="flex items-center justify-center gap-2 rounded-[12px] border border-gray-300 bg-white px-4 py-2.5 text-[13px] font-medium text-gray-700 transition-all duration-200 hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-1 active:bg-gray-50"
        >
          <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 16 16" stroke="currentColor" strokeWidth="2">
            <rect x="2" y="2" width="12" height="12" rx="1.5" />
            <path d="M6 6H10M6 10H10" strokeLinecap="round" />
          </svg>
          Generate Missing
        </button>
        <button
          onClick={onReoptimizeAll}
          className="flex items-center justify-center gap-2 rounded-[12px] border border-gray-300 bg-white px-4 py-2.5 text-[13px] font-medium text-gray-700 transition-all duration-200 hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-1 active:bg-gray-50"
        >
          <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 16 16" stroke="currentColor" strokeWidth="2">
            <path d="M8 2L10 6L14 8L10 10L8 14L6 10L2 8L6 6L8 2Z" />
            <circle cx="8" cy="8" r="1.5" fill="currentColor" />
          </svg>
          Re-optimise All
        </button>
      </div>

      {/* Stats Row */}
      <div className="grid grid-cols-3 gap-3 pt-4">
        <div className="rounded-[12px] border border-gray-200 bg-white p-4 text-center">
          <div className="text-[18px] font-medium text-gray-900">00</div>
          <div className="mt-1 text-[10px] font-medium uppercase tracking-wider text-gray-500">
            HRS
          </div>
          <div className="mt-1.5 text-[10px] font-light text-gray-400">
            Start generating alt text to see time saved.
          </div>
        </div>
        <div className="rounded-[12px] border border-gray-200 bg-white p-4 text-center">
          <div className="text-[18px] font-medium text-gray-900">0</div>
          <div className="mt-1 text-[10px] font-medium uppercase tracking-wider text-gray-500">
            IMAGES
          </div>
          <div className="mt-1.5 text-[10px] font-light text-gray-400">
            We'll show your progress here.
          </div>
        </div>
        <div className="rounded-[12px] border border-gray-200 bg-white p-4 text-center">
          <div className="text-[18px] font-medium text-gray-900">–%</div>
          <div className="mt-1 text-[10px] font-medium uppercase tracking-wider text-gray-500">
            SEO
          </div>
          <div className="mt-1.5 text-[10px] font-light text-gray-400">
            We estimate impact after you optimise enough images.
          </div>
        </div>
      </div>

      {/* Footer */}
      <p className="text-center text-[11px] font-light text-gray-400">
        Limited to {totalImages} images/month on Free. Growth unlocks 1,000 images/month.
      </p>
    </div>
  );
};

interface GrowthUpgradeCardProps {
  onUpgradeClick?: () => void;
  onEnterpriseClick?: () => void;
  monthlyPrice?: number;
}

const GrowthUpgradeCard: React.FC<GrowthUpgradeCardProps> = ({
  onUpgradeClick,
  onEnterpriseClick,
  monthlyPrice,
}) => {
  return (
    <div className="rounded-[16px] bg-white border border-gray-200/60 p-10 md:p-8 sm:p-6 space-y-8">
      {/* Badge */}
      <div className="flex">
        <span className="inline-flex items-center rounded-full bg-[#1e293b] px-3 py-1 text-[10px] font-medium uppercase tracking-wider text-white">
          GROWTH
        </span>
      </div>

      {/* Title */}
      <h2 className="text-[24px] font-medium tracking-tight text-gray-900 md:text-[20px]">
        Automate alt text at scale for global libraries.
      </h2>

      {/* Subheading */}
      <p className="text-[14px] font-light leading-relaxed text-gray-600">
        Reduce operational SEO and accessibility overhead.
      </p>

      {/* Benefit List */}
      <ul className="space-y-3.5">
        <li className="flex items-start gap-3">
          <div className="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full border border-gray-300 bg-white">
            <svg
              className="h-3 w-3 text-gray-700"
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
          <span className="text-[14px] font-light leading-relaxed text-gray-700">
            1,000 AI alt texts per month for growing libraries
          </span>
        </li>
        <li className="flex items-start gap-3">
          <div className="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full border border-gray-300 bg-white">
            <svg
              className="h-3 w-3 text-gray-700"
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
          <span className="text-[14px] font-light leading-relaxed text-gray-700">
            Bulk processing for ecommerce & editorial pipelines
          </span>
        </li>
        <li className="flex items-start gap-3">
          <div className="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full border border-gray-300 bg-white">
            <svg
              className="h-3 w-3 text-gray-700"
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
          <span className="text-[14px] font-light leading-relaxed text-gray-700">
            Priority queue for high-volume ingestion
          </span>
        </li>
        <li className="flex items-start gap-3">
          <div className="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full border border-gray-300 bg-white">
            <svg
              className="h-3 w-3 text-gray-700"
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
          <span className="text-[14px] font-light leading-relaxed text-gray-700">
            Multilingual library support (&gt;50 languages)
          </span>
        </li>
      </ul>

      {/* Enterprise Credibility */}
      <p className="text-[12px] font-light text-gray-500">
        Used by high-volume ecommerce and editorial teams.
      </p>

      {/* CTA Section */}
      <div className="space-y-3 pt-2">
        {/* Primary CTA */}
        <button
          onClick={onUpgradeClick}
          className="w-full min-h-[44px] rounded-[12px] bg-[#1e293b] px-6 py-3.5 text-[14px] font-medium text-white transition-all duration-200 hover:bg-[#0f172a] focus:outline-none focus:ring-2 focus:ring-[#1e293b] focus:ring-offset-2 active:bg-[#0f172a] sm:w-full"
        >
          Start 14 day free trial
        </button>

        {/* Microcopy */}
        <p className="text-center text-[11px] font-light text-gray-500">
          Cancel anytime. No lock-in.
        </p>

        {/* Price Note */}
        {monthlyPrice && (
          <p className="text-center text-[11px] font-light text-gray-400">
            Then £{monthlyPrice}/month after trial.
          </p>
        )}

        {/* Enterprise Link */}
        <div className="pt-2 border-t border-gray-200">
          <button
            onClick={onEnterpriseClick}
            className="w-full text-center text-[12px] font-medium text-gray-600 hover:text-gray-900 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-1 rounded py-2"
          >
            Contact Enterprise Sales
          </button>
        </div>

        {/* Capability Footnotes */}
        <div className="pt-2 space-y-1.5">
          <p className="text-[10px] font-light text-gray-400 text-center">
            Supports API workflows
          </p>
          <p className="text-[10px] font-light text-gray-400 text-center">
            SSO / SAML available
          </p>
          <p className="text-[10px] font-light text-gray-400 text-center">
            Priority support for enterprise customers
          </p>
        </div>

        {/* Secondary Link */}
        <a
          href="#"
          className="block text-center text-[12px] font-light text-gray-500 hover:text-gray-700 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-1 rounded py-1"
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
  onEnterpriseClick?: () => void;
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
  onEnterpriseClick,
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
          <GrowthUpgradeCard
            onUpgradeClick={handleUpgradeClick}
            onEnterpriseClick={onEnterpriseClick}
            monthlyPrice={monthlyPrice}
          />
        </div>
      </div>
    </div>
  );
};

export default DashboardOverview;