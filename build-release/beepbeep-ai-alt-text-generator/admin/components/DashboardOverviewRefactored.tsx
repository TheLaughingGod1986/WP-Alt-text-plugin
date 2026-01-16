import React from 'react';

interface MetricTileProps {
  icon: React.ReactNode;
  value: string;
  label: string;
  sublabel: string;
  description: string;
}

const MetricTile: React.FC<MetricTileProps> = ({ icon, value, label, sublabel, description }) => {
  return (
    <div className="rounded-2xl bg-white border border-gray-200 shadow-sm px-5 py-4">
      <div className="flex items-center justify-between mb-3">
        <div className="w-10 h-10 flex items-center justify-center rounded-xl bg-gradient-to-br from-teal-50 to-blue-50 text-teal-600">
          {icon}
        </div>
        <div className="h-px flex-1 mx-3 bg-gray-200"></div>
      </div>
      <div className="text-[28px] font-bold text-gray-900 mb-1">{value}</div>
      <div className="flex items-baseline gap-2 mb-1">
        <div className="text-[13px] font-bold uppercase tracking-wide text-gray-700">{label}</div>
        {sublabel && (
          <div className="text-[11px] font-medium uppercase tracking-wide text-gray-500">{sublabel}</div>
        )}
      </div>
      <div className="text-[12px] font-normal text-gray-500 leading-relaxed mt-2">{description}</div>
    </div>
  );
};

interface DashboardHeaderProps {
  currentPlan?: string;
}

const DashboardHeader: React.FC<DashboardHeaderProps> = ({ currentPlan = 'Free' }) => {
  return (
    <div className="mb-16 space-y-4">
      <h1 className="text-[36px] font-semibold tracking-tight text-gray-900 leading-tight md:text-[32px] sm:text-[28px]">
        Dashboard
      </h1>
      <p className="text-[16px] font-normal text-gray-600 leading-relaxed md:text-[15px]">
        Automated, accessible alt text generation for your WordPress media library.
      </p>
      {currentPlan === 'Free' && (
        <p className="text-[14px] font-normal text-gray-600 leading-relaxed">
          You're on the Free plan.{' '}
          <button
            onClick={() => {
              const growthCard = document.querySelector('[data-growth-card]');
              if (growthCard) {
                growthCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
              }
            }}
            className="text-blue-600 hover:text-blue-700 underline underline-offset-2 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 rounded"
          >
            Upgrade to Growth
          </button>{' '}
          to automate your entire library.
        </p>
      )}
    </div>
  );
};

interface FreeCardProps {
  usedImages?: number;
  totalImages?: number;
  onUpgradeClick?: () => void;
  onGenerateMissing?: () => void;
  onReoptimizeAll?: () => void;
}

const FreeCard: React.FC<FreeCardProps> = ({
  usedImages = 6,
  totalImages = 50,
  onUpgradeClick,
  onGenerateMissing,
  onReoptimizeAll,
}) => {
  const percentage = Math.min(100, Math.round((usedImages / totalImages) * 100));
  const remaining = Math.max(0, totalImages - usedImages);
  const circumference = 2 * Math.PI * 48;
  const strokeDashoffset = circumference - (percentage / 100) * circumference;

  return (
    <div className="rounded-3xl bg-gradient-to-br from-white via-white to-sky-50 p-10 shadow-xl relative overflow-hidden">
      {/* Subtle teal/blue mist on right */}
      <div className="absolute top-0 right-0 w-64 h-64 opacity-[0.03] pointer-events-none">
        <div className="absolute top-20 right-20 w-32 h-32 bg-teal-400 rounded-full blur-3xl" />
        <div className="absolute bottom-20 right-10 w-24 h-24 bg-blue-400 rounded-full blur-3xl" />
      </div>

      {/* Badge */}
      <div className="flex mb-6 relative z-10">
        <span className="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-[10px] font-medium uppercase tracking-wider text-gray-600">
          FREE
        </span>
      </div>

      {/* Title */}
      <h2 className="text-xl font-semibold uppercase tracking-wider text-gray-900 mb-8 relative z-10">
        YOUR ALT TEXT PROGRESS THIS MONTH
      </h2>

      {/* Donut Chart */}
      <div className="flex justify-center mb-8 relative z-10">
        <div className="relative h-[160px] w-[160px]">
          <svg className="h-full w-full -rotate-90 transform" viewBox="0 0 120 120">
            <circle
              cx="60"
              cy="60"
              r="48"
              fill="none"
              stroke="#e5e7eb"
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
                <stop offset="0%" stopColor="#2dd4bf" stopOpacity="1" />
                <stop offset="50%" stopColor="#38bdf8" stopOpacity="1" />
                <stop offset="100%" stopColor="#3b82f6" stopOpacity="1" />
              </linearGradient>
            </defs>
          </svg>
          <div className="absolute inset-0 flex flex-col items-center justify-center">
            <div className="text-[36px] font-bold text-gray-900">{percentage}%</div>
            <div className="text-[11px] font-medium uppercase tracking-wider text-gray-500 mt-1">
              IMAGES USED
            </div>
          </div>
        </div>
      </div>

      {/* Usage Text */}
      <div className="text-center space-y-1.5 mb-4 relative z-10">
        <p className="text-[15px] font-normal text-gray-700">
          <span className="font-semibold">{usedImages}</span> of{' '}
          <span className="font-semibold">{totalImages}</span> images used this month
        </p>
        <p className="text-[13px] font-normal text-gray-600">
          50 images/month included
        </p>
        {usedImages > 0 && (
          <p className="text-[13px] font-normal text-gray-500">
            Only <span className="font-semibold">{remaining}</span> free images left this month
          </p>
        )}
      </div>

      {/* Thin Progress Bar */}
      <div className="h-1.5 overflow-hidden rounded-full bg-gray-200 mb-6 relative z-10">
        <div
          className="h-full rounded-full bg-gradient-to-r from-teal-400 via-sky-400 to-blue-500 transition-all duration-500 ease-out"
          style={{ width: `${Math.max(2, percentage)}%` }}
          role="progressbar"
          aria-valuenow={percentage}
          aria-valuemin={0}
          aria-valuemax={100}
        />
      </div>

      {/* Upgrade CTA */}
      <div className="space-y-2 mb-8 relative z-10">
        <button
          onClick={onUpgradeClick}
          className="w-full rounded-full bg-gradient-to-r from-teal-400 via-sky-400 to-blue-500 px-6 py-4 text-[15px] font-semibold text-white shadow-lg transition-all duration-200 hover:shadow-xl hover:-translate-y-0.5 focus:outline-none focus:ring-4 focus:ring-teal-500/30 active:translate-y-0"
        >
          Upgrade to Growth
        </button>
        <p className="text-center text-[12px] font-normal text-slate-500">
          Unlock 1,000 images/month
        </p>
      </div>

      {/* Actions Row */}
      <div className="grid grid-cols-2 gap-3 mb-8 relative z-10">
        <button
          onClick={onGenerateMissing}
          className="flex items-center justify-center gap-2 rounded-full bg-gradient-to-r from-purple-500 to-purple-600 px-5 py-3 text-[14px] font-semibold text-white shadow-lg transition-all duration-200 hover:shadow-xl hover:-translate-y-1 focus:outline-none focus:ring-4 focus:ring-purple-500/30 active:translate-y-0"
        >
          <svg className="h-4 w-4" fill="none" viewBox="0 0 16 16" stroke="currentColor" strokeWidth="2">
            <circle cx="8" cy="8" r="6" stroke="currentColor" fill="none" />
            <path d="M8 4v4l3 2" strokeLinecap="round" />
          </svg>
          Generate Missing
        </button>
        <button
          onClick={onReoptimizeAll}
          className="flex items-center justify-center gap-2 rounded-full bg-gradient-to-r from-orange-500 to-orange-600 px-5 py-3 text-[14px] font-semibold text-white shadow-lg transition-all duration-200 hover:shadow-xl hover:-translate-y-1 focus:outline-none focus:ring-4 focus:ring-orange-500/30 active:translate-y-0"
        >
          <svg className="h-4 w-4" fill="none" viewBox="0 0 16 16" stroke="currentColor" strokeWidth="2">
            <path d="M8 2L10 6L14 8L10 10L8 14L6 10L2 8L6 6L8 2Z" />
            <circle cx="8" cy="8" r="2" fill="currentColor" />
          </svg>
          Re-optimise All
        </button>
      </div>
    </div>
  );
};

interface GrowthCardProps {
  onUpgradeClick?: () => void;
}

const GrowthCard: React.FC<GrowthCardProps> = ({ onUpgradeClick }) => {
  return (
    <div className="rounded-3xl bg-gradient-to-br from-sky-500 via-sky-400 to-indigo-400 p-10 shadow-xl relative overflow-hidden">
      {/* Subtle sparkles */}
      <div className="absolute top-8 right-12 w-1.5 h-1.5 bg-white rounded-full opacity-50 animate-pulse" />
      <div className="absolute top-16 right-20 w-1 h-1 bg-cyan-200 rounded-full opacity-60 animate-pulse" style={{ animationDelay: '0.5s' }} />
      <div className="absolute top-24 right-16 w-1.5 h-1.5 bg-white rounded-full opacity-40 animate-pulse" style={{ animationDelay: '1s' }} />
      <div className="absolute bottom-32 right-24 w-1 h-1 bg-blue-200 rounded-full opacity-50 animate-pulse" style={{ animationDelay: '1.5s' }} />

      {/* Badge */}
      <div className="flex mb-6 relative z-10">
        <span className="inline-flex items-center rounded-full bg-white/20 backdrop-blur-sm px-3 py-1 text-[10px] font-medium uppercase tracking-wider text-white">
          GROWTH
        </span>
      </div>

      {/* Title + Subheading */}
      <div className="space-y-2 mb-8 relative z-10">
        <h2 className="text-xl font-semibold text-white leading-tight">
          Upgrade to automate your campaigns
        </h2>
        <p className="text-sm text-slate-100 leading-relaxed">
          Automate alt text so your clients rank better & stay compliant.
        </p>
      </div>

      {/* White Feature Panel */}
      <div className="rounded-2xl bg-white/95 shadow-sm p-6 space-y-4 mb-6 relative z-10">
        <ul className="space-y-3">
          <li className="flex items-start gap-3">
            <div className="mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-green-100">
              <svg
                className="h-4 w-4 text-green-600"
                fill="none"
                viewBox="0 0 16 16"
                stroke="currentColor"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <path d="M13 4L6 11L3 8" />
              </svg>
            </div>
            <span className="text-[14px] font-normal leading-relaxed text-gray-700">
              <span className="font-semibold">1,000 AI alt texts/month</span> across client sites
            </span>
          </li>
          <li className="flex items-start gap-3">
            <div className="mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-yellow-100">
              <svg
                className="h-4 w-4 text-yellow-600"
                fill="none"
                viewBox="0 0 16 16"
                stroke="currentColor"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <path d="M13 4L6 11L3 8" />
              </svg>
            </div>
            <span className="text-[14px] font-normal leading-relaxed text-gray-700">
              <span className="font-semibold">Bulk processing</span> to boost entire libraries in minutes
            </span>
          </li>
          <li className="flex items-start gap-3">
            <div className="mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-purple-100">
              <svg
                className="h-4 w-4 text-purple-600"
                fill="none"
                viewBox="0 0 16 16"
                stroke="currentColor"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <path d="M13 4L6 11L3 8" />
              </svg>
            </div>
            <span className="text-[14px] font-normal leading-relaxed text-gray-700">
              <span className="font-semibold">Priority queue</span> for 3× faster results during busy uploads
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
                <path d="M8 2v2M8 12v2M2 8h2M12 8h2M3.172 3.172l1.414 1.414M11.414 11.414l1.414 1.414M3.172 12.828l1.414-1.414M11.414 4.586l1.414-1.414" />
              </svg>
            </div>
            <span className="text-[14px] font-normal leading-relaxed text-gray-700">
              <span className="font-semibold">50+ languages</span> supported for global SEO reach
            </span>
          </li>
          <li className="flex items-start gap-3">
            <div className="mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-emerald-100">
              <svg
                className="h-4 w-4 text-emerald-600"
                fill="none"
                viewBox="0 0 16 16"
                stroke="currentColor"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <path d="M13 4L6 11L3 8" />
              </svg>
            </div>
            <span className="text-[14px] font-normal leading-relaxed text-gray-700">
              <span className="font-semibold">WCAG/ADA compliant</span> alt text for accessibility standards
            </span>
          </li>
          <li className="flex items-start gap-3">
            <div className="mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100">
              <svg
                className="h-4 w-4 text-indigo-600"
                fill="none"
                viewBox="0 0 16 16"
                stroke="currentColor"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <path d="M13 4L6 11L3 8" />
              </svg>
            </div>
            <span className="text-[14px] font-normal leading-relaxed text-gray-700">
              <span className="font-semibold">Google semantic indexing</span> optimized for search visibility
            </span>
          </li>
        </ul>
      </div>

      {/* Agency Line */}
      <p className="text-sm text-slate-100 mb-8 relative z-10">
        Perfect for agencies managing multiple client sites.
      </p>

      {/* Primary CTA */}
      <div className="space-y-3 mb-6 relative z-10">
        <button
          onClick={onUpgradeClick}
          className="w-full min-h-[48px] rounded-full bg-gradient-to-r from-blue-600 to-indigo-700 px-6 py-4 text-[15px] font-semibold text-white shadow-xl transition-all duration-200 hover:shadow-2xl hover:-translate-y-1 focus:outline-none focus:ring-4 focus:ring-blue-500/50 active:translate-y-0"
        >
          Start 14 day free trial
        </button>
        <p className="text-center text-[12px] font-normal leading-relaxed text-slate-100">
          Upgrade to Growth. Cancel anytime.
        </p>
        <a
          href="#"
          className="block text-center text-[13px] font-normal text-slate-100 hover:text-white underline underline-offset-2 transition-colors focus:outline-none focus:ring-2 focus:ring-white/50 focus:ring-offset-2 focus:ring-offset-indigo-400 rounded py-1"
        >
          Compare plans
        </a>
      </div>

      {/* Trust & Value Copy */}
      <div className="space-y-2 mb-6 relative z-10">
        <p className="text-center text-[13px] font-medium text-white">
          Trusted by agencies worldwide
        </p>
        <p className="text-center text-[12px] font-normal text-slate-200">
          Save 12+ hours/month on AI alt text automation
        </p>
      </div>

      {/* Persona Alignment Strip */}
      <div className="rounded-2xl bg-white/95 shadow-sm px-4 py-3 relative z-10">
        <div className="grid grid-cols-3 gap-2">
          <div className="rounded-lg border border-gray-200 bg-gradient-to-br from-green-50 to-green-100/50 p-2.5 text-center">
            <div className="text-[11px] font-semibold text-gray-900 mb-0.5">Editorial</div>
            <div className="text-[9px] font-normal text-gray-600">SEO workflows</div>
          </div>
          <div className="rounded-lg border border-gray-200 bg-gradient-to-br from-orange-50 to-orange-100/50 p-2.5 text-center">
            <div className="text-[11px] font-semibold text-gray-900 mb-0.5">Ecommerce</div>
            <div className="text-[9px] font-normal text-gray-600">catalogue optimisation</div>
          </div>
          <div className="rounded-lg border border-gray-200 bg-gradient-to-br from-purple-50 to-purple-100/50 p-2.5 text-center">
            <div className="text-[11px] font-semibold text-gray-900 mb-0.5">Reporting</div>
            <div className="text-[9px] font-normal text-gray-600">client SEO metrics</div>
          </div>
        </div>
      </div>
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
    <div className="space-y-12">
      <DashboardHeader currentPlan={currentPlan} />
      <div className="flex flex-row gap-8 lg:flex-col lg:gap-8">
        <div className="flex-1">
          <FreeCard
            usedImages={usedImages}
            totalImages={totalImages}
            onUpgradeClick={handleUpgradeClick}
            onGenerateMissing={onGenerateMissing}
            onReoptimizeAll={onReoptimizeAll}
          />
        </div>
        <div className="flex-1" data-growth-card>
          <GrowthCard onUpgradeClick={handleUpgradeClick} />
        </div>
      </div>
      {/* Metrics Tiles - Reduced spacing from Growth card */}
      <div className="mt-4 grid grid-cols-3 gap-6">
        <MetricTile
          icon={
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
              <circle cx="12" cy="12" r="10" />
              <path d="M12 6V12L16 14" strokeLinecap="round" />
            </svg>
          }
          value="00"
          label="HRS"
          sublabel="TIME SAVED"
          description="Start optimizing to see your time savings"
        />
        <MetricTile
          icon={
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
              <path d="M9 11l3 3L22 4" strokeLinecap="round" strokeLinejoin="round" />
              <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
          }
          value="0"
          label="IMAGES"
          sublabel="OPTIMIZED"
          description="Start optimizing to see your progress"
        />
        <MetricTile
          icon={
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
              <path d="M2 12L12 2L22 12M12 8V22" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
          }
          value="-%"
          label="SEO"
          sublabel="IMPACT"
          description="Start optimizing to boost your SEO score"
        />
      </div>
    </div>
  );
};

export default DashboardOverview;